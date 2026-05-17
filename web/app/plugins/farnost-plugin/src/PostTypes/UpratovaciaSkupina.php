<?php

declare(strict_types=1);

namespace Farnost\Plugin\PostTypes;

if (!defined('ABSPATH')) {
    exit;
}

final class UpratovaciaSkupina
{
    public const POST_TYPE = 'upratovacia_skupina';

    public static function register(): void
    {
        register_post_type(self::POST_TYPE, [
            'labels'       => [
                'name'          => __('Upratovacie skupiny', 'farnost-plugin'),
                'singular_name' => __('Upratovacia skupina', 'farnost-plugin'),
                'add_new_item'  => __('Pridať skupinu', 'farnost-plugin'),
                'edit_item'     => __('Upraviť skupinu', 'farnost-plugin'),
                'menu_name'     => __('Upratovacie skupiny', 'farnost-plugin'),
            ],
            'public'       => false,
            'show_ui'      => true,
            'has_archive'  => false,
            // `custom-fields` v supports je nevyhnutné pre REST meta endpoint:
            // WP_REST_Posts_Controller registruje `meta` schema field iba ak je
            // tento support prítomný (wp-includes/rest-api/endpoints/
            // class-wp-rest-posts-controller.php:2679). Bez neho REST POST
            // so `meta` body server ticho zahodí. Gutenberg „Custom Fields"
            // panel nás netrápi — táto CPT nemá `editor` support, do Gutenberg
            // editora sa farár nedostane (vlastná React admin obrazovka).
            'supports'     => ['title', 'page-attributes', 'custom-fields'],
            'show_in_rest' => true,
            'rest_base'    => 'upratovacie-skupiny',
            // Vlastná admin obrazovka (`Menu::SLUG . '-upratovacie'`) supluje CPT listing.
            // CPT editor (post.php?action=edit) ostáva funkčný cez `show_ui`.
            'show_in_menu' => false,
        ]);
    }

    public static function registerMeta(): void
    {
        register_post_meta(self::POST_TYPE, 'farnost_skupina_kontakt', [
            'type'         => 'string',
            'single'       => true,
            'show_in_rest' => true,
            'default'      => '',
        ]);
        register_post_meta(self::POST_TYPE, 'farnost_skupina_clenovia', [
            'type'         => 'string',
            'single'       => true,
            'show_in_rest' => true,
            'default'      => '',
        ]);
    }
}
