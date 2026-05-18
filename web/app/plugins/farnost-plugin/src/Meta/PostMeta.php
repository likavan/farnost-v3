<?php

declare(strict_types=1);

namespace Farnost\Plugin\Meta;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Meta polia na natívnom WP `post` type (udalosti) + `page` (menu flag).
 */
final class PostMeta
{
    public static function register(): void
    {
        register_post_meta('post', 'farnost_event_when', [
            'type'         => 'string',
            'single'       => true,
            'show_in_rest' => true,
            'default'      => '',
        ]);
        register_post_meta('post', 'farnost_event_where', [
            'type'         => 'string',
            'single'       => true,
            'show_in_rest' => true,
            'default'      => '',
        ]);
        // Auto-menu flag pre WP pages. Default true — nové stránky sa zobrazia
        // v hlavnom menu, kým ich admin explicitne nevypne. Analóg s
        // CategoryMeta::farnost_show_in_menu (doc/06-struktura-stranky.md:45).
        register_post_meta('page', 'farnost_show_in_menu', [
            'type'         => 'boolean',
            'single'       => true,
            'show_in_rest' => true,
            'default'      => true,
        ]);
    }
}
