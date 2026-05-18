<?php

declare(strict_types=1);

namespace Farnost\Plugin\Blocks;

use Farnost\Plugin\Settings\Settings;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Dynamic block `farnost/contact-widget` — bočný stĺpec widget s kontaktom
 * farského úradu zo settings. Adresa, telefóny, e-maily.
 */
final class ContactWidget
{
    public const NAME = 'farnost/contact-widget';

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
        $adresa = (string) ($s['kontakt']['adresa'] ?? '');
        $telefony = is_array($s['kontakt']['telefony'] ?? null) ? $s['kontakt']['telefony'] : [];
        $emaily   = is_array($s['kontakt']['emaily']   ?? null) ? $s['kontakt']['emaily']   : [];

        if ($adresa === '' && empty($telefony) && empty($emaily)) {
            return '';
        }

        ob_start();
        ?>
        <section class="farnost-widget">
            <h3 class="farnost-widget-title"><?php esc_html_e('Farský úrad', 'farnost-plugin'); ?></h3>
            <?php if ($adresa !== '') : ?>
                <div class="farnost-contact-line"><?php echo esc_html($adresa); ?></div>
            <?php endif; ?>
            <?php foreach ($telefony as $row) :
                if (!is_array($row)) { continue; }
                $popis = (string) ($row['popis'] ?? '');
                $cislo = (string) ($row['cislo'] ?? '');
                if ($cislo === '') { continue; }
            ?>
                <div class="farnost-contact-line">
                    <?php if ($popis !== '') : ?>
                        <span class="farnost-contact-label"><?php echo esc_html($popis); ?></span>
                    <?php else : ?>
                        <span class="farnost-contact-label"><?php esc_html_e('Tel.', 'farnost-plugin'); ?></span>
                    <?php endif; ?>
                    <a href="tel:<?php echo esc_attr(preg_replace('/\s+/', '', $cislo)); ?>"><?php echo esc_html($cislo); ?></a>
                </div>
            <?php endforeach; ?>
            <?php foreach ($emaily as $row) :
                if (!is_array($row)) { continue; }
                $popis = (string) ($row['popis'] ?? '');
                $adr   = (string) ($row['adresa'] ?? '');
                if ($adr === '') { continue; }
            ?>
                <div class="farnost-contact-line">
                    <?php if ($popis !== '') : ?>
                        <span class="farnost-contact-label"><?php echo esc_html($popis); ?></span>
                    <?php else : ?>
                        <span class="farnost-contact-label"><?php esc_html_e('E-mail', 'farnost-plugin'); ?></span>
                    <?php endif; ?>
                    <a href="mailto:<?php echo esc_attr($adr); ?>"><?php echo esc_html($adr); ?></a>
                </div>
            <?php endforeach; ?>
        </section>
        <?php
        return (string) ob_get_clean();
    }
}
