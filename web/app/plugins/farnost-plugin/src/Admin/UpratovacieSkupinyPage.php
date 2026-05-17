<?php

declare(strict_types=1);

namespace Farnost\Plugin\Admin;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Custom admin obrazovka `Farnosť → Upratovacie skupiny`.
 *
 * React app (`build/upratovacie.js`) sa pripojí na `#farnost-upratovacie-root` a:
 * - listuje skupiny zoradené podľa `menu_order` (drag-and-drop ich prerovná),
 * - vyznačuje aktuálnu skupinu na rade (`farnost_settings.upratovanie.dalsia_skupina`),
 * - umožňuje manuálne posunúť pointer cez REST `farnost/v1/rotation-pointer`,
 * - vie pridať / zmazať skupinu cez `/wp/v2/upratovacie-skupiny`.
 *
 * Editácia konkrétnej skupiny (názov, kontakt, členovia) je klasický Gutenberg edit
 * screen (`post.php?post=...&action=edit`), kde sa cez `OznamPanel`-like sidebar
 * pridáva contact + members. (Zatiaľ stačí title + custom-fields, panel sa môže
 * dopridať neskôr.)
 */
final class UpratovacieSkupinyPage
{
    public const SLUG = 'farnost-upratovacie';

    public static function render(): void
    {
        if (!current_user_can('edit_posts')) {
            wp_die(esc_html__('Nedostatočné oprávnenia.', 'farnost-plugin'));
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Upratovacie skupiny', 'farnost-plugin'); ?></h1>
            <p class="description" style="margin:6px 0 16px;max-width:720px;">
                <?php esc_html_e('Skupiny rotujú v poradí, v akom sú zoradené. Aktuálnu skupinu na rade systém vkladá automaticky do týždenného oznamu. Po publikácii oznamu sa pointer posunie na ďalšiu skupinu.', 'farnost-plugin'); ?>
            </p>
            <div id="farnost-upratovacie-root"></div>
        </div>
        <?php
    }
}
