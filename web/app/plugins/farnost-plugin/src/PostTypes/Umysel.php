<?php

declare(strict_types=1);

namespace Farnost\Plugin\PostTypes;

if (!defined('ABSPATH')) {
    exit;
}

final class Umysel
{
    public const POST_TYPE = 'umysel';

    public static function register(): void
    {
        register_post_type(self::POST_TYPE, [
            'labels'       => [
                'name'          => __('Úmysly', 'farnost-plugin'),
                'singular_name' => __('Úmysel', 'farnost-plugin'),
                'add_new_item'  => __('Pridať úmysel', 'farnost-plugin'),
                'edit_item'     => __('Upraviť úmysel', 'farnost-plugin'),
                'menu_name'     => __('Úmysly', 'farnost-plugin'),
            ],
            'public'       => true,
            'has_archive'  => 'umysly',
            'rewrite'      => ['slug' => 'umysly'],
            'supports'     => ['title', 'author'],
            'show_in_rest' => true,
            'rest_base'    => 'umysly',
            'show_in_menu' => false, // vzniká z kalendára, nemá vlastné menu
            'capability_type' => ['umysel', 'umysly'],
            'map_meta_cap'    => true,
        ]);
    }

    public static function registerMeta(): void
    {
        register_post_meta(self::POST_TYPE, 'farnost_datum', [
            'type'         => 'string',
            'single'       => true,
            'show_in_rest' => true,
            'default'      => '',
        ]);
        register_post_meta(self::POST_TYPE, 'farnost_cas', [
            'type'         => 'string',
            'single'       => true,
            'show_in_rest' => true,
            'default'      => '',
        ]);
        register_post_meta(self::POST_TYPE, 'farnost_kostol_id', [
            'type'         => 'integer',
            'single'       => true,
            'show_in_rest' => true,
            'default'      => 0,
        ]);
        register_post_meta(self::POST_TYPE, 'farnost_text', [
            'type'         => 'string',
            'single'       => true,
            'show_in_rest' => true,
            'default'      => '',
        ]);
        register_post_meta(self::POST_TYPE, 'farnost_anonymny', [
            'type'         => 'boolean',
            'single'       => true,
            'show_in_rest' => true,
            'default'      => false,
        ]);
    }
}
