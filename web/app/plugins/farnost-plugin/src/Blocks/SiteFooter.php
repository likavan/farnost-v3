<?php

declare(strict_types=1);

namespace Farnost\Plugin\Blocks;

use Farnost\Plugin\Settings\Settings;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Dynamic block `farnost/site-footer` — 3-stĺpcový footer grid + spodný
 * copyright riadok. Načíta z farnost_settings.identita, kontakt, financie.
 *
 * Odkazy (Banskobystrická diecéza, KBS, Liturgia hodín atď.) sú statické
 * v markupe — domén-konštantné, žiadny dôvod ich admin-editovať.
 */
final class SiteFooter
{
    public const NAME = 'farnost/site-footer';

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
        $nazov   = trim((string) ($s['identita']['nazov'] ?? '')) ?: (string) get_bloginfo('name');
        $adresa  = trim((string) ($s['kontakt']['adresa'] ?? ''));
        $iban    = trim((string) ($s['financie']['iban'] ?? ''));
        $telefony = is_array($s['kontakt']['telefony'] ?? null) ? $s['kontakt']['telefony'] : [];
        $emaily   = is_array($s['kontakt']['emaily'] ?? null) ? $s['kontakt']['emaily'] : [];

        $contactBits = [];
        foreach ($telefony as $row) {
            if (is_array($row) && !empty($row['cislo'])) {
                $contactBits[] = (string) $row['cislo'];
            }
        }
        foreach ($emaily as $row) {
            if (is_array($row) && !empty($row['adresa'])) {
                $contactBits[] = (string) $row['adresa'];
            }
        }
        $contactLine = implode(' · ', $contactBits);

        ob_start();
        ?>
        <div class="site-footer-grid">
            <div>
                <h3 class="farnost-footer-title"><?php echo esc_html($nazov); ?></h3>
                <?php if ($adresa !== '') : ?>
                    <div class="farnost-footer-line"><?php echo esc_html($adresa); ?></div>
                <?php endif; ?>
                <?php if ($contactLine !== '') : ?>
                    <div class="farnost-footer-line"><?php echo esc_html($contactLine); ?></div>
                <?php endif; ?>
            </div>
            <div>
                <h3 class="farnost-footer-title"><?php esc_html_e('Odkazy', 'farnost-plugin'); ?></h3>
                <ul class="farnost-footer-list">
                    <li><a href="https://www.bbdieceza.sk/" rel="noopener" target="_blank">Banskobystrická diecéza</a></li>
                    <li><a href="https://www.kbs.sk/" rel="noopener" target="_blank">Konferencia biskupov Slovenska</a></li>
                    <li><a href="https://lh.kbs.sk/" rel="noopener" target="_blank">Liturgia hodín</a></li>
                </ul>
            </div>
            <?php if ($iban !== '') : ?>
                <div>
                    <h3 class="farnost-footer-title"><?php esc_html_e('Bankové spojenie', 'farnost-plugin'); ?></h3>
                    <div class="farnost-footer-line"><?php echo esc_html($iban); ?></div>
                </div>
            <?php endif; ?>
        </div>
        <div class="site-footer-bottom">
            <?php
            printf(
                /* translators: %1$d = current year, %2$s = parish name */
                esc_html__('© %1$d %2$s. Všetky práva vyhradené.', 'farnost-plugin'),
                (int) current_datetime()->format('Y'),
                esc_html($nazov)
            );
            ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }
}
