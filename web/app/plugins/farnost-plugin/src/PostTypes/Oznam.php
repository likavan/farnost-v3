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
