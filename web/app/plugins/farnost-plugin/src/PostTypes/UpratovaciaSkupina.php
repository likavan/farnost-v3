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
