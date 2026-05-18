<?php

declare(strict_types=1);

namespace Farnost\Plugin\PostTypes;

use Farnost\Plugin\Admin\Menu;

if (!defined('ABSPATH')) {
    exit;
}

final class Oznam
{
    public const POST_TYPE = 'oznam';

    public static function register(): void
    {
        register_post_type(self::POST_TYPE, [
            'labels'       => [
                'name'          => __('Oznamy', 'farnost-plugin'),
                'singular_name' => __('Oznam', 'farnost-plugin'),
                'add_new_item'  => __('Pridať oznam', 'farnost-plugin'),
                'edit_item'     => __('Upraviť oznam', 'farnost-plugin'),
                'view_item'     => __('Zobraziť oznam', 'farnost-plugin'),
                'menu_name'     => __('Oznamy', 'farnost-plugin'),
            ],
            'public'       => true,
            'has_archive'  => 'oznamy',
            'rewrite'      => ['slug' => 'oznamy'],
            'supports'     => ['title', 'editor', 'thumbnail', 'excerpt', 'revisions', 'author', 'custom-fields'],
            'show_in_rest' => true,
            'rest_base'    => 'oznamy',
            'show_in_menu' => Menu::SLUG,
            // Lifecycle oznamov je výhradne automatický: BufferManager ich vytvára
            // a WP cron publikuje pri dosiahnutí post_date. Manuálne ovládanie
            // (Pridať / Publikovať / Zmazať) by spôsobilo desync s rotáciou
            // upratovania a duplicity v týždňoch — preto kompletný UI lockdown.
            //
            // Cron flow (future→publish) volá wp_publish_post() priamo, bez cap
            // checku, takže auto-publikácia funguje aj pri do_not_allow. Rovnako
            // BufferManager::createWeekOznam volá wp_insert_post() priamo.
            //
            // Technický fallback pre adminov: WP-CLI `wp post delete <id>` obíde
            // capability check (CLI nemá user context).
            'capabilities' => [
                'create_posts'           => 'do_not_allow',
                'publish_posts'          => 'do_not_allow',
                'delete_post'            => 'do_not_allow',
                'delete_posts'           => 'do_not_allow',
                'delete_published_posts' => 'do_not_allow',
                'delete_others_posts'    => 'do_not_allow',
                'delete_private_posts'   => 'do_not_allow',
            ],
            'map_meta_cap' => true,
        ]);
    }

    public static function registerMeta(): void
    {
        // ISO dátumy začiatku a konca týždňa (pondelok–nedeľa), ku ktorému oznam patrí.
        register_post_meta(self::POST_TYPE, 'farnost_tyzden_od', [
            'type'         => 'string',
            'single'       => true,
            'show_in_rest' => true,
            'default'      => '',
        ]);
        register_post_meta(self::POST_TYPE, 'farnost_tyzden_do', [
            'type'         => 'string',
            'single'       => true,
            'show_in_rest' => true,
            'default'      => '',
        ]);
        // Pridelená upratovacia skupina pre tento týždeň (post ID). Pri publikácii
        // sa cez Upratovanie::onTransition posunie pointer v farnost_settings.
        register_post_meta(self::POST_TYPE, 'farnost_upratuje_id', [
            'type'         => 'integer',
            'single'       => true,
            'show_in_rest' => true,
            'default'      => 0,
        ]);
    }
}
