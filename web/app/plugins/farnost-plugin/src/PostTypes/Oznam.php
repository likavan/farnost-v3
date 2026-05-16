<?php

declare(strict_types=1);

namespace Farnost\Plugin\PostTypes;

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
            'supports'     => ['title', 'editor', 'thumbnail', 'excerpt', 'revisions', 'author'],
            'show_in_rest' => true,
            'rest_base'    => 'oznamy',
            'menu_icon'    => 'dashicons-megaphone',
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
    }
}
