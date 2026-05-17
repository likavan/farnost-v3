<?php

declare(strict_types=1);

namespace Farnost\Plugin\Admin;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Skryje všetky „Pridať nový" affordances pre CPT `oznam`.
 *
 * Oznamy vytvára BufferManager automaticky (status `future`), preto manuálne
 * pridávanie nedáva zmysel — viedlo by k duplicitám alebo k oznamom, ktoré
 * nikto nestihne vyplniť. Farár drafty len edituje a publikujú sa samé.
 */
final class HideOznamAddNew
{
    public static function register(): void
    {
        add_action('admin_menu', [self::class, 'removeSubmenu'], 999);
        add_action('admin_bar_menu', [self::class, 'removeAdminBar'], 999);
        add_action('admin_head-edit.php', [self::class, 'hidePageButtonCss']);
    }

    public static function removeSubmenu(): void
    {
        // Submenu „Pridať novú" pod Farnosť → Oznamy.
        remove_submenu_page('edit.php?post_type=oznam', 'post-new.php?post_type=oznam');
    }

    public static function removeAdminBar(\WP_Admin_Bar $bar): void
    {
        $bar->remove_node('new-oznam');
    }

    public static function hidePageButtonCss(): void
    {
        if (!isset($_GET['post_type']) || $_GET['post_type'] !== 'oznam') {
            return;
        }
        echo "<style>.page-title-action[href*='post-new.php?post_type=oznam'] { display: none !important; }</style>\n";
    }
}
