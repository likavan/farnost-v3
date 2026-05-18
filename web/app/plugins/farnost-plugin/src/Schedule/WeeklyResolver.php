<?php

declare(strict_types=1);

namespace Farnost\Plugin\Schedule;

use DateTimeImmutable;
use Farnost\Plugin\PostTypes\OmsaVynimka;
use WP_Query;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Batch verzia RozpisReader-u pre celý týždeň + viac kostolov naraz.
 *
 * Riešenie N+1 problému: pôvodný flow (MassWidget, ScheduleTable) volal
 * RozpisReader::forDate per kostol per deň → pre 5 kostolov 35 queries.
 * forWeek() spraví 1 WP_Query pre všetky výnimky týždňa naprieč všetkými
 * kostolmi, in-memory ich dispatchne a vráti pripravený index
 *   [$kostolId][$isoDate] => array<{cas, oznacenie, umysel, zdroj}>
 *
 * Pre N kostolov: 1 (load kostoly) + 1 (výnimky) = 2 queries total
 * namiesto 1 + N×7.
 */
final class WeeklyResolver
{
    /**
     * @param list<int> $kostolIds
     * @return array<int, array<string, array<int, array{cas: string, oznacenie: string, umysel: string, zdroj: string}>>>
     */
    public static function forWeek(array $kostolIds, string $weekStart, string $weekEnd): array
    {
        if (empty($kostolIds)) {
            return [];
        }
        $tz = wp_timezone();
        $start = DateTimeImmutable::createFromFormat('Y-m-d', $weekStart, $tz);
        if ($start === false) {
            return [];
        }

        $exceptions = self::loadExceptionsForWeek($kostolIds, $weekStart, $weekEnd);

        $result = [];
        foreach ($kostolIds as $kid) {
            $rozpis = self::readRozpis((int) $kid);
            for ($i = 0; $i < 7; $i++) {
                $day = $start->modify("+{$i} day");
                $iso = $day->format('Y-m-d');
                $vyn = $exceptions["{$kid}|{$iso}"] ?? [];
                $result[(int) $kid][$iso] = Resolver::resolve($rozpis, $vyn, $iso);
            }
        }
        return $result;
    }

    /**
     * @return array<int, array{day_of_week: string, time: string, oznacenie: string}>
     */
    private static function readRozpis(int $kostolId): array
    {
        $raw = get_post_meta($kostolId, 'farnost_rozpis', true);
        if (!is_string($raw) || $raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }
        $out = [];
        foreach ($decoded as $item) {
            if (!is_array($item)) {
                continue;
            }
            $out[] = [
                'day_of_week' => (string) ($item['day_of_week'] ?? ''),
                'time'        => (string) ($item['time'] ?? ''),
                'oznacenie'   => (string) ($item['oznacenie'] ?? ''),
            ];
        }
        return $out;
    }

    /**
     * 1× WP_Query, vráti dict indexovaný "$kostolId|$datum".
     *
     * @param list<int> $kostolIds
     * @return array<string, array<int, array{cas: string, oznacenie: string, umysel: string}>>
     */
    private static function loadExceptionsForWeek(array $kostolIds, string $weekStart, string $weekEnd): array
    {
        $q = new WP_Query([
            'post_type'      => OmsaVynimka::POST_TYPE,
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'no_found_rows'  => true,
            'meta_query'     => [
                'relation' => 'AND',
                [
                    'key'     => 'farnost_kostol_id',
                    'value'   => array_map('intval', $kostolIds),
                    'compare' => 'IN',
                ],
                [
                    'key'     => 'farnost_datum',
                    'value'   => [$weekStart, $weekEnd],
                    'compare' => 'BETWEEN',
                    'type'    => 'CHAR',
                ],
            ],
        ]);

        $out = [];
        foreach ($q->posts as $post) {
            $kid    = (int) get_post_meta($post->ID, 'farnost_kostol_id', true);
            $datum  = (string) get_post_meta($post->ID, 'farnost_datum', true);
            $cas    = (string) get_post_meta($post->ID, 'farnost_cas', true);
            $oz     = (string) get_post_meta($post->ID, 'farnost_oznacenie', true);
            $umysel = (string) get_post_meta($post->ID, 'farnost_umysel', true);
            $out["{$kid}|{$datum}"][] = [
                'cas'       => $cas,
                'oznacenie' => $oz,
                'umysel'    => $umysel,
            ];
        }
        return $out;
    }
}
