<?php

declare(strict_types=1);

namespace Farnost\Plugin\Schedule;

if (!defined('ABSPATH')) {
    // Allow standalone unit testing without WP loaded.
    if (!defined('FARNOST_RESOLVER_STANDALONE')) {
        return;
    }
}

/**
 * Pure-function logika: pre daný dátum kombinuje pravidelný rozpis kostola
 * a aplikované výnimky a vracia výsledný zoznam omší daného dňa.
 *
 * - Pravidelný rozpis je pole položiek typu:
 *   ['day_of_week' => 'mon'|'tue'|..|'sun', 'time' => 'HH:MM', 'oznacenie' => string?]
 * - Výnimky sú už filtrované pre daný dátum a kostol; aditívne (pridávajú omše).
 *
 * Vracia pole pre daný deň, zoradené podľa času:
 *   [
 *     ['cas' => '08:00', 'oznacenie' => '', 'umysel' => '', 'zdroj' => 'rozpis'],
 *     ['cas' => '10:30', 'oznacenie' => '', 'umysel' => 'Za farníkov', 'zdroj' => 'vynimka'],
 *   ]
 */
final class Resolver
{
    private const DAY_MAP = [
        1 => 'mon',
        2 => 'tue',
        3 => 'wed',
        4 => 'thu',
        5 => 'fri',
        6 => 'sat',
        7 => 'sun',
    ];

    /**
     * @param array<int, array{day_of_week: string, time: string, oznacenie?: string}> $rozpis
     * @param array<int, array{cas: string, oznacenie?: string, umysel?: string}>      $vynimky
     * @return array<int, array{cas: string, oznacenie: string, umysel: string, zdroj: string}>
     */
    public static function resolve(array $rozpis, array $vynimky, string $date): array
    {
        $dayKey = self::dayKeyForDate($date);
        $regular = array_values(array_filter(
            $rozpis,
            static fn(array $slot): bool => ($slot['day_of_week'] ?? '') === $dayKey
        ));

        $masses = [];
        foreach ($regular as $slot) {
            $masses[] = [
                'cas'       => (string) ($slot['time'] ?? ''),
                'oznacenie' => (string) ($slot['oznacenie'] ?? ''),
                'umysel'    => '',
                'zdroj'     => 'rozpis',
            ];
        }
        foreach ($vynimky as $vyn) {
            $masses[] = [
                'cas'       => (string) ($vyn['cas'] ?? ''),
                'oznacenie' => (string) ($vyn['oznacenie'] ?? ''),
                'umysel'    => (string) ($vyn['umysel'] ?? ''),
                'zdroj'     => 'vynimka',
            ];
        }

        usort($masses, static fn(array $a, array $b): int => strcmp($a['cas'], $b['cas']));
        return $masses;
    }

    public static function dayKeyForDate(string $date): string
    {
        $ts = strtotime($date . ' 12:00:00 UTC');
        if ($ts === false) {
            return '';
        }
        $dow = (int) date('N', $ts); // 1 = Monday, 7 = Sunday
        return self::DAY_MAP[$dow] ?? '';
    }
}
