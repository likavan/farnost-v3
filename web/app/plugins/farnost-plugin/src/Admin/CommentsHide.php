<?php

declare(strict_types=1);

namespace Farnost\Plugin\Admin;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Skryje funkcionalitu komentárov naprieč admin UI aj frontend.
 *
 * Farské weby vo v3 komentáre nepoužívajú (viď doc/06-struktura-stranky.md,
 * sekcia „Detail oznamu / udalosti"). Default WP komentáre by farára len mätli
 * a generovali UX zbytočne, preto ich kompletne odstaviť.
 *
 * Existujúce komentárové dáta sa nemažú (defenzívne pre prípad reaktivácie).
 */
final class CommentsHide
{
    public static function register(): void
    {
        // Admin menu
        add_action('admin_menu', [self::class, 'removeMenuItem']);
        // Admin bar (horný panel)
        add_action('admin_bar_menu', [self::class, 'removeAdminBarComments'], 999);
        // „At a glance" dashboard widget — schovaj riadok s počtom komentárov
        add_action('admin_print_styles-index.php', [self::class, 'hideDashboardCommentsCss']);
        // Recent comments dashboard widget — odstrániť úplne
        add_action('wp_dashboard_setup', [self::class, 'removeDashboardWidgets'], 99);
        // Odhlás post type support pre komentáre na všetkých CPT
        add_action('init', [self::class, 'disablePostTypeSupport'], 100);
        // Nové príspevky majú zatvorenú diskusiu by default
        add_filter('comments_open', '__return_false', 20);
        add_filter('pings_open', '__return_false', 20);
        // Existujúce komentáre vrátia prázdne pole pri načítaní pre frontend
        add_filter('comments_array', '__return_empty_array', 10);
        // Skry položku v admin baru pre návštevníkov tiež
        add_action('wp_before_admin_bar_render', [self::class, 'removeAdminBarComments']);
    }

    public static function removeDashboardWidgets(): void
    {
        remove_meta_box('dashboard_recent_comments', 'dashboard', 'normal');
        // dashboard_activity obsahuje aj komentáre, ale aj nedávne príspevky.
        // Pre čistejší dashboard ho tiež odstránime — vlastný onboarding widget pridáme v Etape 2.
        remove_meta_box('dashboard_activity', 'dashboard', 'normal');
    }

    public static function removeMenuItem(): void
    {
        remove_menu_page('edit-comments.php');
    }

    public static function removeAdminBarComments(): void
    {
        if (function_exists('is_admin_bar_showing') && !is_admin_bar_showing()) {
            return;
        }
        global $wp_admin_bar;
        if (isset($wp_admin_bar) && method_exists($wp_admin_bar, 'remove_node')) {
            $wp_admin_bar->remove_node('comments');
        }
    }

    public static function hideDashboardCommentsCss(): void
    {
        echo "<style>#dashboard_right_now li.comment-count, #latest-comments { display: none !important; }</style>\n";
    }

    public static function disablePostTypeSupport(): void
    {
        foreach (get_post_types() as $type) {
            if (post_type_supports($type, 'comments')) {
                remove_post_type_support($type, 'comments');
            }
            if (post_type_supports($type, 'trackbacks')) {
                remove_post_type_support($type, 'trackbacks');
            }
        }
    }
}
