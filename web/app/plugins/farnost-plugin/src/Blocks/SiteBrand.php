<?php

declare(strict_types=1);

namespace Farnost\Plugin\Blocks;

use Farnost\Plugin\Settings\Settings;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Dynamic block `farnost/site-brand` — brand mark (cross pattée SVG) + názov
 * farnosti + sub-line (dekanát · od r. YYYY). Číta z farnost_settings.identita.
 * Linkne na home.
 *
 * Predtým bol brand hardcoded v parts/header.html — farár ho nemohol meniť
 * cez admin. Teraz sa updatuje cez Farnosť → Nastavenia → Identita.
 */
final class SiteBrand
{
    public const NAME = 'farnost/site-brand';

    public static function register(): void
    {
        add_action('init', [self::class, 'registerBlock']);
    }

    public static function registerBlock(): void
    {
        register_block_type(self::NAME, [
            'api_version'     => 3,
            'render_callback' => [self::class, 'render'],
        ]);
    }

    public static function render(): string
    {
        $s = Settings::get();
        $nazov   = trim((string) ($s['identita']['nazov'] ?? ''));
        $dekanat = trim((string) ($s['identita']['dekanat'] ?? ''));
        $rok     = (int) ($s['identita']['rok_zalozenia'] ?? 0);

        if ($nazov === '') {
            $nazov = (string) get_bloginfo('name');
        }

        // Sub-line zložená dynamicky — len neprázdne časti.
        $subParts = [];
        if ($dekanat !== '') {
            $subParts[] = $dekanat;
        }
        if ($rok > 0) {
            $subParts[] = sprintf(__('od r. %d', 'farnost-plugin'), $rok);
        }
        $subLine = implode(' · ', $subParts);

        ob_start();
        ?>
        <a class="farnost-brand" href="<?php echo esc_url(home_url('/')); ?>" aria-label="<?php esc_attr_e('Domov', 'farnost-plugin'); ?>">
            <span class="farnost-brand-mark" aria-hidden="true">
                <svg width="24" height="24" viewBox="0 0 24 24">
                    <path d="M10 2 L14 2 L13.4 8.6 L20.4 8 L22 10 L22 14 L20.4 16 L13.4 15.4 L14 22 L10 22 L10.6 15.4 L3.6 16 L2 14 L2 10 L3.6 8 L10.6 8.6 Z" fill="currentColor"/>
                </svg>
            </span>
            <span class="farnost-brand-text">
                <span class="farnost-brand-name"><?php echo esc_html($nazov); ?></span>
                <?php if ($subLine !== '') : ?>
                    <span class="farnost-brand-city"><?php echo esc_html($subLine); ?></span>
                <?php endif; ?>
            </span>
        </a>
        <?php
        return (string) ob_get_clean();
    }
}
