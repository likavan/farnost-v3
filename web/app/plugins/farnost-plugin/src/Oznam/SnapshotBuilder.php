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
     * Vracia per-deň pole s `kostoly[]` (každý kostol so svojím zoznamom omší).
     * Tým editorial render v RozpisSnapshot môže zobraziť omše v kontexte kostola
     * (kostol_title viditeľný, farba badge, kompaktný layout) namiesto flat zoznamu
     * kde nie je jasné kde je ktorá omša.
     *
     * Shape:
     *   [
     *     {
     *       date: 'YYYY-MM-DD', dayKey: 'mon', sviatok: '',
     *       kostoly: [
     *         { id, title, color, omse: [ {cas, oznacenie, umysel, source}, ... ] },
     *         ...
     *       ]
     *     }, ... 7×
     *   ]
     *
     * @return list<array{date: string, dayKey: string, sviatok: string, kostoly: list<array{id: int, title: string, color: string, omse: list<array{cas: string, oznacenie: string, umysel: string, source: string}>}>}>
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
                'kostoly' => self::kostolyForDate($iso, $kostoly),
            ];
        }
        return $dni;
    }

    /**
     * @return list<array{id: int, title: string, color: string, rozpis: list<array<string, mixed>>}>
     */
    private static function loadKostoly(): array
    {
        $posts = get_posts([
            'post_type'      => Kostol::POST_TYPE,
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'menu_order title',
            'order'          => 'ASC',
            'no_found_rows'  => true,
        ]);
        // Hlavný kostol na začiatok (matches widget order).
        $main = [];
        $rest = [];
        foreach ($posts as $p) {
            $raw    = (string) get_post_meta($p->ID, 'farnost_rozpis', true);
            $rozpis = [];
            if ($raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $rozpis = $decoded;
                }
            }
            $row = [
                'id'     => (int) $p->ID,
                'title'  => (string) $p->post_title,
                'color'  => (string) get_post_meta($p->ID, 'farnost_color', true),
                'rozpis' => $rozpis,
            ];
            if ((bool) get_post_meta($p->ID, 'farnost_je_hlavny', true)) {
                $main[] = $row;
            } else {
                $rest[] = $row;
            }
        }
        return array_values(array_merge($main, $rest));
    }

    /**
     * Pre daný dátum vráti zoznam kostolov s omšami (len kostoly ktoré majú aspoň
     * jednu omšu daný deň — kostoly bez omší nevkladáme do snapshot-u).
     *
     * @param list<array{id: int, title: string, color: string, rozpis: list<array<string, mixed>>}> $kostoly
     * @return list<array{id: int, title: string, color: string, omse: list<array{cas: string, oznacenie: string, umysel: string, source: string}>}>
     */
    private static function kostolyForDate(string $date, array $kostoly): array
    {
        $out = [];
        foreach ($kostoly as $k) {
            $vynimky = self::loadVynimky($k['id'], $date);
            $resolved = Resolver::resolve($k['rozpis'], $vynimky, $date);
            if (empty($resolved)) {
                continue;
            }
            $omse = [];
            foreach ($resolved as $m) {
                $umysel = (string) ($m['umysel'] ?? '');
                if (($m['zdroj'] ?? '') === 'rozpis' && $umysel === '') {
                    $umysel = self::loadUmysel($k['id'], $date, (string) ($m['cas'] ?? ''));
                }
                $omse[] = [
                    'cas'       => (string) ($m['cas'] ?? ''),
                    'oznacenie' => (string) ($m['oznacenie'] ?? ''),
                    'umysel'    => $umysel,
                    'source'    => (string) ($m['zdroj'] ?? 'rozpis'),
                ];
            }
            $out[] = [
                'id'    => (int) $k['id'],
                'title' => (string) $k['title'],
                'color' => (string) $k['color'],
                'omse'  => $omse,
            ];
        }
        return $out;
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
