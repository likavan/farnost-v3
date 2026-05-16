<?php

declare(strict_types=1);

namespace Farnost\Plugin\Oznam;

use DateTimeImmutable;
use Farnost\Plugin\PostTypes\Oznam;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Auto-prefill nového `oznam` postu.
 *
 * Pri vytvorení nového auto-draftu (klik „Pridať oznam") systém:
 *  - Vypočíta cieľový týždeň (Mon–Sun nasledujúci po dnešnom dni)
 *  - Uloží do post meta `farnost_tyzden_od` / `farnost_tyzden_do`
 *
 * Naplnenie obsahu bloku `farnost/rozpis-snapshot` aktuálnymi dátami (rozpis,
 * výnimky, úmysly, sviatky) pridáme v ďalšom kroku.
 */
final class AutoTemplate
{
    public static function register(): void
    {
        // Spustí sa pri vytvorení auto-draftu — tam si pripravíme všetky default meta.
        add_action('wp_insert_post', [self::class, 'onInsert'], 10, 3);
    }

    public static function onInsert(int $postId, \WP_Post $post, bool $update): void
    {
        if ($update) {
            return;
        }
        if ($post->post_type !== Oznam::POST_TYPE) {
            return;
        }
        // Iba na čerstvé drafty / auto-drafty bez existujúcich meta hodnôt.
        $existingOd = get_post_meta($postId, 'farnost_tyzden_od', true);
        if (!empty($existingOd)) {
            return;
        }

        [$od, $do] = self::computeNextWeek();
        update_post_meta($postId, 'farnost_tyzden_od', $od);
        update_post_meta($postId, 'farnost_tyzden_do', $do);
    }

    /**
     * Vráti dvojicu [Monday, Sunday] týždňa, ktorý začína **po** dnešnom dni.
     * Príklady (publikačný_den = nedeľa):
     *   - dnes Streda 13. 5.   → Mon 18. 5. – Sun 24. 5.
     *   - dnes Nedeľa 17. 5.   → Mon 18. 5. – Sun 24. 5.
     *   - dnes Pondelok 18. 5. → Mon 25. 5. – Sun 31. 5.
     *
     * @return array{0: string, 1: string}  Mon a Sun ako 'Y-m-d'
     */
    public static function computeNextWeek(): array
    {
        $tz       = wp_timezone();
        $tomorrow = new DateTimeImmutable('tomorrow', $tz);
        $dow      = (int) $tomorrow->format('N'); // 1=Mon..7=Sun
        $daysToMon = $dow === 1 ? 0 : (8 - $dow);
        $mon      = $tomorrow->modify("+{$daysToMon} day");
        $sun      = $mon->modify('+6 day');
        return [
            $mon->format('Y-m-d'),
            $sun->format('Y-m-d'),
        ];
    }
}
