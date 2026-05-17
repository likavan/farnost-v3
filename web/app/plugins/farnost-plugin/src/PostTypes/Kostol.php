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
                'menu_name'     => __('Kostoly', 'farnost-plugin'),
            ],
            // Kostol je interná evidencia pre fungovanie farnosti — žiadna verejná
            // stránka. Ak farnosť bude chcieť vlastnú stránku o kostole, spraví si
            // ju ako klasickú WP Page. Tým pádom:
            //  - public => false (žiadny frontend, žiadny single-kostol template),
            //  - bez has_archive a rewrite,
            //  - show_in_menu => false — vlastná React obrazovka KostolyPage
            //    supluje default CPT listing.
            //
            // `custom-fields` v supports je nevyhnutné pre REST meta endpoint:
            // WP_REST_Posts_Controller registruje `meta` schema field iba ak je
            // tento support prítomný (wp-includes/rest-api/endpoints/
            // class-wp-rest-posts-controller.php:2679). Bez neho POST so `meta`
            // body server ticho prijme s 200 OK, ale dáta zahodí. Custom Fields
            // panel Gutenbergu nás netrápi — táto CPT vôbec nemá `editor` support,
            // takže do Gutenbergu sa farár nedostane (admin obrazovka je React UI).
            'public'       => false,
            'show_ui'      => true,
            'supports'     => ['title', 'page-attributes', 'custom-fields'],
            'show_in_rest' => true,
            'rest_base'    => 'kostoly',
            'show_in_menu' => false,
        ]);
    }

    public static function registerMeta(): void
    {
        register_post_meta(self::POST_TYPE, 'farnost_je_hlavny', [
            'type'         => 'boolean',
            'single'       => true,
            'show_in_rest' => true,
            'default'      => false,
        ]);
        // Farba kostola pre vizuálne odlíšenie v kalendári (a v budúcnosti aj inde).
        // Empty string znamená „farba nie je explicitne nastavená" — kalendár vtedy
        // padne na pozičný fallback z palety. Sanitácia hex je rovnaká ako pri
        // kategóriách v Meta\CategoryMeta.
        register_post_meta(self::POST_TYPE, 'farnost_color', [
            'type'              => 'string',
            'single'             => true,
            'show_in_rest'       => true,
            'default'            => '',
            'sanitize_callback'  => [self::class, 'sanitizeHexColor'],
        ]);
        // Rozpis je JSON-encoded — uložený ako string, validovaný v aplikačnej vrstve.
        register_post_meta(self::POST_TYPE, 'farnost_rozpis', [
            'type'         => 'string',
            'single'       => true,
            'show_in_rest' => true,
            'default'      => '[]',
        ]);
    }

    public static function sanitizeHexColor(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (preg_match('/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/', $value) === 1) {
            return strtolower($value);
        }
        return '';
    }
}
