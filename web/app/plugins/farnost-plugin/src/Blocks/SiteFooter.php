<?php

declare(strict_types=1);

namespace Farnost\Plugin\Blocks;

use Farnost\Plugin\Settings\Settings;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Dynamic block `farnost/site-footer` — 3-stĺpcový footer grid + spodný
 * copyright riadok. Načíta z farnost_settings.identita, kontakt, financie,
 * odkazy. GDPR KBS link je vždy posledný (default, nemení sa cez admin).
 */
final class SiteFooter
{
    public const NAME = 'farnost/site-footer';

    private const GDPR_LINK = 'https://gdpr.kbs.sk/';
    private const GDPR_LABEL = 'Ochrana osobných údajov';

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
        $emaily   = is_array($s['kontakt']['emaily']   ?? null) ? $s['kontakt']['emaily']   : [];
        $year     = (int) current_datetime()->format('Y');

        $odkazy = is_array($s['odkazy'] ?? null) ? $s['odkazy'] : [];
        // GDPR KBS odkaz vždy posledný — admin si naň nemusí pamätať.
        $odkazy[] = ['popis' => __(self::GDPR_LABEL, 'farnost-plugin'), 'url' => self::GDPR_LINK];

        ob_start();
        ?>
        <div class="site-footer-grid">
            <div>
                <h3 class="farnost-footer-title"><?php echo esc_html($nazov); ?></h3>
                <?php if ($adresa !== '') : ?>
                    <div class="farnost-footer-line"><?php echo esc_html($adresa); ?></div>
                <?php endif; ?>
                <?php foreach ($telefony as $row) :
                    if (!is_array($row)) { continue; }
                    $popis = trim((string) ($row['popis'] ?? ''));
                    $cislo = trim((string) ($row['cislo'] ?? ''));
                    if ($cislo === '') { continue; }
                ?>
                    <div class="farnost-footer-line farnost-footer-contact">
                        <a href="tel:<?php echo esc_attr(preg_replace('/\s+/', '', $cislo)); ?>"><?php echo esc_html($cislo); ?></a>
                        <?php if ($popis !== '') : ?>
                            <span class="farnost-footer-muted"> · <?php echo esc_html($popis); ?></span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <?php foreach ($emaily as $row) :
                    if (!is_array($row)) { continue; }
                    $popis = trim((string) ($row['popis'] ?? ''));
                    $adr   = trim((string) ($row['adresa'] ?? ''));
                    if ($adr === '') { continue; }
                ?>
                    <div class="farnost-footer-line farnost-footer-contact">
                        <a href="mailto:<?php echo esc_attr($adr); ?>"><?php echo esc_html($adr); ?></a>
                        <?php if ($popis !== '') : ?>
                            <span class="farnost-footer-muted"> · <?php echo esc_html($popis); ?></span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <div>
                <h3 class="farnost-footer-title"><?php esc_html_e('Odkazy', 'farnost-plugin'); ?></h3>
                <ul class="farnost-footer-list">
                    <?php foreach ($odkazy as $row) :
                        if (!is_array($row)) { continue; }
                        $popis = trim((string) ($row['popis'] ?? ''));
                        $url   = trim((string) ($row['url'] ?? ''));
                        if ($url === '' || $popis === '') { continue; }
                    ?>
                        <li><a href="<?php echo esc_url($url); ?>" rel="noopener" target="_blank"><?php echo esc_html($popis); ?></a></li>
                    <?php endforeach; ?>
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
                $year,
                esc_html($nazov)
            );
            ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }
}
