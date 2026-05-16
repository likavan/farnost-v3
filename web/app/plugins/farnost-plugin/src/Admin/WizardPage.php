<?php

declare(strict_types=1);

namespace Farnost\Plugin\Admin;

use Farnost\Plugin\Settings\Settings;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Setup wizard — plnoobrazovková admin stránka mimo štandardného WP admin chrome.
 *
 * Registruje sa ako submenu pod null parent slug (hidden) a má URL
 * `admin.php?page=farnost-setup`. Pri renderovaní obíde admin sidebar/toolbar
 * a vyrenderuje len <html><body><div id="farnost-wizard-root"></div></body>,
 * aby React aplikácia mala plnú plochu.
 */
final class WizardPage
{
    public const SLUG = 'farnost-setup';

    public static function register(): void
    {
        add_action('admin_menu', [self::class, 'addHiddenPage'], 200);
        add_action('admin_init', [self::class, 'maybeRenderStandalone'], 0);
    }

    public static function addHiddenPage(): void
    {
        add_submenu_page(
            'options.php', // hidden — neprejaví sa v žiadnom viditeľnom menu
            __('Nastavenie farnosti', 'farnost-plugin'),
            __('Nastavenie farnosti', 'farnost-plugin'),
            'manage_options',
            self::SLUG,
            [self::class, 'render']
        );
    }

    /**
     * Ak je current request `admin.php?page=farnost-setup`, vyhodíme všetok WP chrome
     * a vyrenderujeme vlastný plnoobrazovkový shell. Inak nič nerobíme.
     */
    public static function maybeRenderStandalone(): void
    {
        if (!is_admin() || !isset($_GET['page']) || $_GET['page'] !== self::SLUG) {
            return;
        }
        if (!current_user_can('manage_options')) {
            return;
        }
        // Po načítaní WP a všetkých initov vyrenderujeme našu obrazovku a exit.
        add_action('admin_init', [self::class, 'renderStandalone'], 9999);
    }

    public static function renderStandalone(): void
    {
        if (!isset($_GET['page']) || $_GET['page'] !== self::SLUG) {
            return;
        }

        $title = esc_html__('Nastavenie farnosti', 'farnost-plugin');
        $homeUrl = esc_url(admin_url('admin.php?page=farnost'));

        // Načítaj WP scripts manuálne — potrebujeme api-fetch s nonce.
        wp_enqueue_script('wp-api-fetch');
        wp_enqueue_script('wp-components');
        wp_enqueue_script('wp-element');
        wp_enqueue_script('wp-i18n');

        $assetPath = FARNOST_PLUGIN_DIR . '/build/wizard.asset.php';
        $jsUrl     = plugins_url('build/wizard.js', FARNOST_PLUGIN_FILE);
        $deps      = ['wp-api-fetch', 'wp-components', 'wp-element', 'wp-i18n'];
        $version   = FARNOST_PLUGIN_VERSION;
        if (is_readable($assetPath)) {
            $asset   = include $assetPath;
            $deps    = is_array($asset['dependencies'] ?? null) ? $asset['dependencies'] : $deps;
            $version = (string) ($asset['version'] ?? $version);
        }
        wp_enqueue_script('farnost-wizard', $jsUrl, $deps, $version, true);
        wp_set_script_translations('farnost-wizard', 'farnost-plugin');
        wp_enqueue_style('wp-components');

        // Vlastný HTML shell — žiadny admin sidebar / topbar.
        nocache_headers();
        header('Content-Type: text/html; charset=utf-8');
        ?><!doctype html>
<html lang="sk">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $title; ?></title>
    <?php wp_print_styles(); ?>
    <?php wp_print_head_scripts(); ?>
    <style>
        body { margin: 0; background: #f3f4f6; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
        .farnost-wizard-shell { min-height: 100vh; display: flex; flex-direction: column; }
    </style>
</head>
<body>
    <div class="farnost-wizard-shell">
        <div id="farnost-wizard-root"
             data-home-url="<?php echo $homeUrl; ?>"></div>
    </div>
    <?php wp_print_footer_scripts(); ?>
</body>
</html>
<?php
        exit;
    }

    /**
     * Render callback pre add_submenu_page (nikdy by sa nemal volať, lebo
     * renderStandalone() volá exit predtým, ale necháme fallback).
     */
    public static function render(): void
    {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Nastavenie farnosti', 'farnost-plugin'); ?></h1>
            <p><?php esc_html_e('Wizard sa nepodarilo načítať. Skúste stránku obnoviť.', 'farnost-plugin'); ?></p>
        </div>
        <?php
    }

    public static function isCompleted(): bool
    {
        $settings = Settings::get();
        return !empty($settings['setup']['completed']);
    }

    public static function url(): string
    {
        return admin_url('admin.php?page=' . self::SLUG);
    }
}
