<?php

declare(strict_types=1);

namespace Farnost\Plugin\Oznam;

use DateTimeImmutable;
use Farnost\Plugin\PostTypes\Kostol;
use Farnost\Plugin\PostTypes\OmsaVynimka;
use Farnost\Plugin\PostTypes\Umysel;
use Farnost\Plugin\Schedule\Resolver;
use WP_Query;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Buildér snapshot dát pre `farnost/rozpis-snapshot` blok.
 *
 * Pre daný týždeň (pondelok–nedeľa) zostaví štruktúru:
 *   [
 *     { date, dayKey, sviatok, omse: [{ kostol_title, time, oznacenie, umysel, source }] },
 *     ... 7×
 *   ]
 *
 * Zlúči pravidelné omše z `kostol.farnost_rozpis` s výnimkami pre daný dátum (cez
 * Schedule\Resolver) a napáruje úmysly z CPT `umysel` na (datum, čas, kostol_id).
 *
 * Sviatky/liturgické spomienky zatiaľ ostávajú prázdne — pribudnú v ďalšom kroku.
 */
final class SnapshotBuilder
{
    private const DAY_KEYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

    /**
     * @return array<int, array{date: string, dayKey: string, sviatok: string, omse: array<int, array{kostol_title: string, time: string, oznacenie: string, umysel: string, source: string}>}>
     */
    public static function buildForWeek(string $tyzdenOd, string $tyzdenDo): array
    {
        $tz    = wp_timezone();
        $start = DateTimeImmutable::createFromFormat('Y-m-d', $tyzdenOd, $tz);
        if ($start === false) {
            return [];
        }

        $kostoly = self::loadKostoly();

        $dni = [];
        for ($i = 0; $i < 7; $i++) {
            $date = $start->modify("+{$i} day");
            $iso  = $date->format('Y-m-d');
            $dni[] = [
                'date'    => $iso,
                'dayKey'  => self::dayKey($date),
                'sviatok' => '',
                'omse'    => self::massesForDate($iso, $kostoly),
            ];
        }
        return $dni;
    }

    /**
     * @return array<int, array{id: int, title: string, rozpis: array<int, array<string, mixed>>}>
     */
    private static function loadKostoly(): array
    {
        $posts = get_posts([
            'post_type'      => Kostol::POST_TYPE,
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'no_found_rows'  => true,
        ]);
        $out = [];
        foreach ($posts as $p) {
            $raw    = (string) get_post_meta($p->ID, 'farnost_rozpis', true);
            $rozpis = [];
            if ($raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $rozpis = $decoded;
                }
            }
            $out[] = [
                'id'     => (int) $p->ID,
                'title'  => (string) $p->post_title,
                'rozpis' => $rozpis,
            ];
        }
        return $out;
    }

    /**
     * @param array<int, array{id: int, title: string, rozpis: array<int, array<string, mixed>>}> $kostoly
     * @return array<int, array{kostol_title: string, time: string, oznacenie: string, umysel: string, source: string}>
     */
    private static function massesForDate(string $date, array $kostoly): array
    {
        $masses = [];
        foreach ($kostoly as $k) {
            $vynimky = self::loadVynimky($k['id'], $date);
            $resolved = Resolver::resolve($k['rozpis'], $vynimky, $date);

            foreach ($resolved as $m) {
                $umysel = (string) ($m['umysel'] ?? '');
                if ($m['zdroj'] === 'rozpis' && $umysel === '') {
                    $umysel = self::loadUmysel($k['id'], $date, (string) ($m['cas'] ?? ''));
                }
                $masses[] = [
                    'kostol_title' => $k['title'],
                    'time'         => (string) ($m['cas'] ?? ''),
                    'oznacenie'    => (string) ($m['oznacenie'] ?? ''),
                    'umysel'       => $umysel,
                    'source'       => (string) ($m['zdroj'] ?? 'rozpis'),
                ];
            }
        }

        // Stabilné poradie: čas (numericky, nie lex — "6:30" < "18:00"), potom názov kostola.
        usort($masses, static function (array $a, array $b): int {
            $byTime = \Farnost\Plugin\Schedule\Resolver::timeKey((string) $a['time'])
                <=> \Farnost\Plugin\Schedule\Resolver::timeKey((string) $b['time']);
            if ($byTime !== 0) {
                return $byTime;
            }
            return strcmp((string) $a['kostol_title'], (string) $b['kostol_title']);
        });

        return $masses;
    }

    /**
     * @return array<int, array{cas: string, oznacenie: string, umysel: string}>
     */
    private static function loadVynimky(int $kostolId, string $date): array
    {
        $q = new WP_Query([
            'post_type'      => OmsaVynimka::POST_TYPE,
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'no_found_rows'  => true,
            'meta_query'     => [
                'relation' => 'AND',
                ['key' => 'farnost_kostol_id', 'value' => $kostolId, 'compare' => '='],
                ['key' => 'farnost_datum', 'value' => $date, 'compare' => '='],
            ],
        ]);
        $out = [];
        foreach ($q->posts as $p) {
            $out[] = [
                'cas'       => (string) get_post_meta($p->ID, 'farnost_cas', true),
                'oznacenie' => (string) get_post_meta($p->ID, 'farnost_oznacenie', true),
                'umysel'    => (string) get_post_meta($p->ID, 'farnost_umysel', true),
            ];
        }
        return $out;
    }

    private static function loadUmysel(int $kostolId, string $date, string $cas): string
    {
        if ($cas === '') {
            return '';
        }
        $q = new WP_Query([
            'post_type'      => Umysel::POST_TYPE,
            'posts_per_page' => 1,
            'post_status'    => 'publish',
            'no_found_rows'  => true,
            'meta_query'     => [
                'relation' => 'AND',
                ['key' => 'farnost_kostol_id', 'value' => $kostolId, 'compare' => '='],
                ['key' => 'farnost_datum', 'value' => $date, 'compare' => '='],
                ['key' => 'farnost_cas', 'value' => $cas, 'compare' => '='],
            ],
        ]);
        if (empty($q->posts)) {
            return '';
        }
        return (string) get_post_meta($q->posts[0]->ID, 'farnost_text', true);
    }

    private static function dayKey(DateTimeImmutable $d): string
    {
        $dow = (int) $d->format('N'); // 1=Mon..7=Sun
        return self::DAY_KEYS[$dow - 1] ?? '';
    }
}
