<?php

declare(strict_types=1);

namespace Farnost\Plugin\Schedule;

use Farnost\Plugin\PostTypes\Kostol;
use Farnost\Plugin\PostTypes\OmsaVynimka;
use WP_Query;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WP adaptér nad Resolverom — načíta rozpis kostola z post meta a výnimky cez WP_Query,
 * potom volá pure Resolver.
 */
final class RozpisReader
{
    /**
     * @return array<int, array{cas: string, oznacenie: string, umysel: string, zdroj: string}>
     */
    public static function forDate(int $kostolId, string $date): array
    {
        $rozpis = self::readRozpis($kostolId);
        $vynimky = self::readVynimky($kostolId, $date);
        return Resolver::resolve($rozpis, $vynimky, $date);
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
     * @return array<int, array{cas: string, oznacenie: string, umysel: string}>
     */
    private static function readVynimky(int $kostolId, string $date): array
    {
        $query = new WP_Query([
            'post_type'      => OmsaVynimka::POST_TYPE,
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'meta_query'     => [
                'relation' => 'AND',
                ['key' => 'farnost_kostol_id', 'value' => $kostolId, 'compare' => '='],
                ['key' => 'farnost_datum', 'value' => $date, 'compare' => '='],
            ],
            'no_found_rows'  => true,
        ]);

        $out = [];
        foreach ($query->posts as $post) {
            $out[] = [
                'cas'       => (string) get_post_meta($post->ID, 'farnost_cas', true),
                'oznacenie' => (string) get_post_meta($post->ID, 'farnost_oznacenie', true),
                'umysel'    => (string) get_post_meta($post->ID, 'farnost_umysel', true),
            ];
        }
        return $out;
    }
}
