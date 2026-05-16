<?php

declare(strict_types=1);

namespace Farnost\Plugin\PostTypes;

if (!defined('ABSPATH')) {
    exit;
}

final class Kostol
{
    public const POST_TYPE = 'kostol';

    public static function register(): void
    {
        register_post_type(self::POST_TYPE, [
            'labels'       => [
                'name'          => __('Kostoly', 'farnost-plugin'),
                'singular_name' => __('Kostol', 'farnost-plugin'),
                'add_new_item'  => __('Pridať kostol', 'farnost-plugin'),
                'edit_item'     => __('Upraviť kostol', 'farnost-plugin'),
                'view_item'     => __('Zobraziť kostol', 'farnost-plugin'),
                'menu_name'     => __('Kostoly', 'farnost-plugin'),
            ],
            'public'       => true,
            'has_archive'  => 'kostoly',
            'rewrite'      => ['slug' => 'kostoly'],
            'supports'     => ['title', 'editor', 'thumbnail', 'excerpt', 'revisions'],
            'show_in_rest' => true,
            'rest_base'    => 'kostoly',
            'menu_icon'    => 'dashicons-bank',
        ]);
    }

    public static function registerMeta(): void
    {
        register_post_meta(self::POST_TYPE, 'farnost_adresa', [
            'type'         => 'string',
            'single'       => true,
            'show_in_rest' => true,
            'default'      => '',
        ]);
        register_post_meta(self::POST_TYPE, 'farnost_gps_lat', [
            'type'         => 'number',
            'single'       => true,
            'show_in_rest' => true,
            'default'      => 0,
        ]);
        register_post_meta(self::POST_TYPE, 'farnost_gps_lng', [
            'type'         => 'number',
            'single'       => true,
            'show_in_rest' => true,
            'default'      => 0,
        ]);
        register_post_meta(self::POST_TYPE, 'farnost_je_hlavny', [
            'type'         => 'boolean',
            'single'       => true,
            'show_in_rest' => true,
            'default'      => false,
        ]);
        // Rozpis je JSON-encoded — uložený ako string, validovaný v aplikačnej vrstve.
        register_post_meta(self::POST_TYPE, 'farnost_rozpis', [
            'type'         => 'string',
            'single'       => true,
            'show_in_rest' => true,
            'default'      => '[]',
        ]);
    }
}
