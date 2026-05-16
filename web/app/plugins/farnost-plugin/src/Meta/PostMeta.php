<?php

declare(strict_types=1);

namespace Farnost\Plugin\Meta;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Meta polia na natívnom WP `post` type — pre udalosti.
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
    }
}
