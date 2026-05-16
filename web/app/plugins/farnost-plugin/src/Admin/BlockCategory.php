<?php

declare(strict_types=1);

namespace Farnost\Plugin\Admin;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Registruje samostatnú "Farnosť" kategóriu v Gutenberg block inserteri.
 * Tu žijú všetky vlastné bloky pluginu (`farnost/*`).
 */
final class BlockCategory
{
    public const SLUG = 'farnost';

    public static function register(): void
    {
        add_filter('block_categories_all', [self::class, 'addCategory']);
    }

    /**
     * @param array<int, array{slug: string, title: string, icon?: string|null}> $cats
     * @return array<int, array{slug: string, title: string, icon?: string|null}>
     */
    public static function addCategory(array $cats): array
    {
        return array_merge(
            [
                [
                    'slug'  => self::SLUG,
                    'title' => __('Farnosť', 'farnost-plugin'),
                    'icon'  => null,
                ],
            ],
            $cats
        );
    }
}
