<?php

declare(strict_types=1);

namespace Farnost\Plugin\Meta;

if (!defined('ABSPATH')) {
    exit;
}

final class CategoryMeta
{
    public static function register(): void
    {
        register_term_meta('category', 'farnost_color', [
            'type'         => 'string',
            'single'       => true,
            'show_in_rest' => true,
            'default'      => '#6b7280',
            'sanitize_callback' => [self::class, 'sanitizeHexColor'],
        ]);
        register_term_meta('category', 'farnost_show_in_menu', [
            'type'         => 'boolean',
            'single'       => true,
            'show_in_rest' => true,
            'default'      => true,
        ]);
    }

    public static function sanitizeHexColor(mixed $value): string
    {
        if (!is_string($value)) {
            return '#6b7280';
        }
        $value = trim($value);
        if (preg_match('/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/', $value) === 1) {
            return strtolower($value);
        }
        return '#6b7280';
    }
}
