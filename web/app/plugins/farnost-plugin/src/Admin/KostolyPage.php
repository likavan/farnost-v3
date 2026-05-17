<?php

declare(strict_types=1);

namespace Farnost\Plugin\Admin;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Custom admin obrazovka `Farnosť → Kostoly`.
 *
 * Analog UpratovacieSkupinyPage — žiadny Gutenberg, len React (`build/kostoly.js`)
 * s inline edit pre názov, farbu (color picker), „hlavný kostol" toggle (exkluzívny)
 * a expandable rozpis omší per riadok.
 *
 * CPT `kostol` má `show_in_menu => false` — táto obrazovka je single source of truth
 * pre správu kostolov. Webová stránka kostola sa pre v3 nestavia (interný evidenčný
 * model, doc 02-datovy-model.md).
 */
final class KostolyPage
{
    public const SLUG = 'farnost-kostoly';

    public static function render(): void
    {
        if (!current_user_can('edit_posts')) {
            wp_die(esc_html__('Nedostatočné oprávnenia.', 'farnost-plugin'));
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Kostoly', 'farnost-plugin'); ?></h1>
            <p class="description" style="margin:6px 0 16px;max-width:720px;">
                <?php esc_html_e('Evidencia kostolov pre rozpisy omší a kalendár. Každý kostol má farbu pre vizuálne rozlíšenie v kalendári a vlastný týždenný rozpis omší. Webovú stránku o kostole môžete spraviť ako bežnú WP stránku.', 'farnost-plugin'); ?>
            </p>
            <div id="farnost-kostoly-root"></div>
        </div>
        <?php
    }
}
