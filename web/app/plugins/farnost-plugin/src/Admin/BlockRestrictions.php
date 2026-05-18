<?php

declare(strict_types=1);

namespace Farnost\Plugin\Admin;

use WP_Block_Editor_Context;
use WP_Block_Type_Registry;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Skryje naše kostry bloky z Gutenberg inserter-u v post / page / oznam editor-i.
 *
 * Site Editor (Dizajn) ich vidí naďalej — tam ich admin reálne edituje v
 * template parts (header.html, footer.html, sidebar.html). V obyčajnom
 * post / page editor-i nemá zmysel ich vkladať do obsahu.
 *
 * Toggle cez allowed_block_types_all filter — v post editor context vráti
 * full block list mínus naše site bloky; v Site Editor nezasahuje.
 */
final class BlockRestrictions
{
    private const SITE_BLOCKS = [
        'farnost/site-header',
        'farnost/site-footer',
        'farnost/banner',
        'farnost/feed',
        'farnost/main-nav',
        'farnost/site-brand',
        'farnost/mass-widget',
        'farnost/contact-widget',
        'farnost/quote-widget',
        'farnost/schedule-table',
        'farnost/archive-list',
    ];

    public static function register(): void
    {
        add_filter('allowed_block_types_all', [self::class, 'restrictForPostEditor'], 10, 2);
    }

    /**
     * @param array<int, string>|bool $allowed
     */
    public static function restrictForPostEditor($allowed, WP_Block_Editor_Context $context): array|bool
    {
        // Site Editor (core/edit-site) → vidí všetko, vrátane našich kostier.
        if ($context->name === 'core/edit-site') {
            return $allowed;
        }
        // Pre post / page / CPT editor zostavíme allow-list bez site blokov.
        if (!is_array($allowed)) {
            $registry = WP_Block_Type_Registry::get_instance();
            $allowed = array_keys($registry->get_all_registered());
        }
        return array_values(array_diff($allowed, self::SITE_BLOCKS));
    }
}
