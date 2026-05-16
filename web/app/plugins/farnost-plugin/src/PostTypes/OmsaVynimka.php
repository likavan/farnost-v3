<?php

declare(strict_types=1);

namespace Farnost\Plugin\PostTypes;

use Farnost\Plugin\Admin\Menu;

if (!defined('ABSPATH')) {
    exit;
}

final class OmsaVynimka
{
    public const POST_TYPE = 'omsa_vynimka';

    public static function register(): void
    {
        register_post_type(self::POST_TYPE, [
            'labels'       => [
                'name'          => __('Výnimky omší', 'farnost-plugin'),
                'singular_name' => __('Výnimka omše', 'farnost-plugin'),
                'add_new_item'  => __('Pridať výnimku', 'farnost-plugin'),
                'edit_item'     => __('Upraviť výnimku', 'farnost-plugin'),
                'menu_name'     => __('Výnimky', 'farnost-plugin'),
            ],
            'public'       => false,
            'show_ui'      => true,
            // Pôvodný plán: vzniká z kalendára a editora oznamu (doc/07-admin-ux.md).
            // Dočasne pripnuté pod „Farnosť" submenu, kým nemáme kalendár — vtedy
            // sa hodnota vráti na false.
            'show_in_menu' => Menu::SLUG,
            'has_archive'  => false,
            'supports'     => ['title', 'custom-fields'],
            'show_in_rest' => true,
            'rest_base'    => 'omsa-vynimky',
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
        register_post_meta(self::POST_TYPE, 'farnost_oznacenie', [
            'type'         => 'string',
            'single'       => true,
            'show_in_rest' => true,
            'default'      => '',
        ]);
        register_post_meta(self::POST_TYPE, 'farnost_umysel', [
            'type'         => 'string',
            'single'       => true,
            'show_in_rest' => true,
            'default'      => '',
        ]);
    }
}
