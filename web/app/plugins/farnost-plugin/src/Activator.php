<?php

declare(strict_types=1);

namespace Farnost\Plugin;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Aktivačná logika pluginu — beží raz pri zapnutí.
 */
final class Activator
{
    public const ROLE = 'farnost_asistent';

    public static function activate(): void
    {
        self::ensureRole();
        self::ensureDefaultCategories();
        flush_rewrite_rules();
    }

    public static function deactivate(): void
    {
        // Rolu necháme — keby ju admin omylom deaktivoval, neprídeme o nakonfigurovaných asistentov.
        // Kategórie tiež nemažeme — sú to bežné WP termy s obsahom.
        flush_rewrite_rules();
    }

    private static function ensureRole(): void
    {
        if (get_role(self::ROLE) !== null) {
            return;
        }

        add_role(self::ROLE, __('Farský asistent', 'farnost-plugin'), [
            'read'                  => true,
            'upload_files'          => true,
            // vlastné posty (udalosti)
            'edit_posts'            => true,
            'edit_published_posts'  => true,
            'publish_posts'         => true,
            'delete_posts'          => true,
            // úmysly (custom CPT s vlastnými capabilities)
            'edit_umysel'           => true,
            'edit_umysly'           => true,
            'edit_published_umysly' => true,
            'publish_umysly'        => true,
            'delete_umysly'         => true,
            'read_umysel'           => true,
        ]);
    }

    private static function ensureDefaultCategories(): void
    {
        $defaults = [
            ['name' => 'Udalosti',               'slug' => 'udalosti',                'color' => '#1e40af'],
            ['name' => 'Zo života farnosti',     'slug' => 'zo-zivota-farnosti',      'color' => '#15803d'],
            ['name' => 'Pozvánky',               'slug' => 'pozvanky',                'color' => '#b45309'],
        ];

        foreach ($defaults as $cat) {
            $existing = get_term_by('slug', $cat['slug'], 'category');
            if ($existing) {
                continue;
            }
            $result = wp_insert_term($cat['name'], 'category', ['slug' => $cat['slug']]);
            if (is_wp_error($result)) {
                continue;
            }
            $termId = (int) $result['term_id'];
            update_term_meta($termId, 'farnost_color', $cat['color']);
            update_term_meta($termId, 'farnost_show_in_menu', true);
        }
    }
}
