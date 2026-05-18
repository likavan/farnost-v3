<?php

declare(strict_types=1);

namespace Farnost\Plugin\Admin;

use Farnost\Plugin\Settings\Settings;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin obrazovka `Farnosť → Citáty`.
 *
 * Pipe-separated textarea — jeden citát na riadok vo formáte
 *   text | autor
 * Autor je voliteľný. Pattern je rovnaký aký mala sekcia v Nastaveniach,
 * len presunutý na samostatnú obrazovku pre lepšiu dostupnosť.
 */
final class CitatyPage
{
    public const SLUG = 'farnost-citaty';
    public const NONCE_ACTION = 'farnost_citaty_save';

    public static function render(): void
    {
        if (!current_user_can('edit_posts')) {
            wp_die(esc_html__('Nedostatočné oprávnenia.', 'farnost-plugin'));
        }

        $saved = false;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            check_admin_referer(self::NONCE_ACTION);
            $raw = isset($_POST['fp_citaty']) ? (string) wp_unslash($_POST['fp_citaty']) : '';
            $citaty = self::parseFromText($raw);
            $settings = Settings::get();
            $settings['citaty'] = $citaty;
            update_option(Settings::OPTION_KEY, $settings);
            $saved = true;
        }

        $citaty = Settings::get()['citaty'] ?? [];
        $text = self::toText(is_array($citaty) ? $citaty : []);

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Citáty pre sidebar', 'farnost-plugin'); ?></h1>

            <?php if ($saved) : ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e('Citáty boli uložené.', 'farnost-plugin'); ?></p>
                </div>
            <?php endif; ?>

            <p class="description" style="max-width:720px;margin:6px 0 16px;">
                <?php esc_html_e('Citáty sa zobrazujú v bočnom stĺpci, rotujú podľa dňa v roku. Ak žiadne nepridáte, sekcia citátu sa na frontende nezobrazí.', 'farnost-plugin'); ?>
            </p>

            <form method="post" action="">
                <?php wp_nonce_field(self::NONCE_ACTION); ?>
                <textarea name="fp_citaty" rows="14" class="large-text code" placeholder="Boh je láska. | 1 Jn 4,8
Kde sú dvaja alebo traja zhromaždení v mojom mene, tam som ja medzi nimi. | Mt 18, 20"><?php echo esc_textarea($text); ?></textarea>
                <p class="description">
                    <?php esc_html_e('Jeden citát = jeden riadok. Voliteľný autor / zdroj oddeľte znakom „|" (napr. „Boh je láska. | 1 Jn 4,8").', 'farnost-plugin'); ?>
                </p>
                <p class="submit">
                    <button type="submit" class="button button-primary"><?php esc_html_e('Uložiť citáty', 'farnost-plugin'); ?></button>
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * @param array<int, array{text: string, autor?: string}> $citaty
     */
    private static function toText(array $citaty): string
    {
        $lines = [];
        foreach ($citaty as $c) {
            $text = isset($c['text']) ? (string) $c['text'] : '';
            $autor = isset($c['autor']) ? (string) $c['autor'] : '';
            if ($text === '') {
                continue;
            }
            $lines[] = $autor === '' ? $text : "{$text} | {$autor}";
        }
        return implode("\n", $lines);
    }

    /**
     * @return array<int, array{text: string, autor: string}>
     */
    private static function parseFromText(string $text): array
    {
        $out = [];
        foreach (preg_split('/\r?\n/', $text) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $parts = array_map('trim', explode('|', $line, 2));
            $out[] = [
                'text'  => sanitize_text_field((string) $parts[0]),
                'autor' => isset($parts[1]) ? sanitize_text_field((string) $parts[1]) : '',
            ];
        }
        return $out;
    }
}
