<?php

declare(strict_types=1);

namespace Farnost\Plugin\Admin;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Enqueue Gutenberg editor JS pre konkrétne CPT.
 *
 * Skripty sa nahrávajú **iba** na editorové obrazovky príslušného post type,
 * aby sme nezaťažovali editor zbytočným JS na ostatných typoch.
 *
 * Build artefakty žijú v `build/` (vyprodukované z `editor/<panel>/index.js`
 * cez `wp-scripts build`), sprievodný `*.asset.php` obsahuje zoznam závislostí.
 */
final class EditorAssets
{
    public static function register(): void
    {
        add_action('enqueue_block_editor_assets', [self::class, 'enqueue']);
        // Site Editor (Dizajn) potrebuje site-bloky vždy — registrujeme cez
        // samostatný hook ktorý beží naprieč všetkými Gutenberg editormi.
        add_action('enqueue_block_editor_assets', [self::class, 'enqueueSiteBlocks']);
        // Skryť header/footer/sidebar template-parts v post/page editor
        // canvas-e — autor edituje len content, okolité chrome ho rozptyľuje.
        // V Site editori (Dizajn) ostávajú viditeľné — tam $context->post nie je.
        add_filter('block_editor_settings_all', [self::class, 'hideTemplatePartsInPostEditor'], 10, 2);
    }

    /**
     * @param array<string, mixed> $settings
     * @param object $context  Block editor context — v post editori má `->post`.
     * @return array<string, mixed>
     */
    public static function hideTemplatePartsInPostEditor(array $settings, $context): array
    {
        if (!isset($context->post)) {
            return $settings;
        }
        $css = '.wp-block-template-part { display: none !important; }';
        $settings['styles'] = $settings['styles'] ?? [];
        $settings['styles'][] = ['css' => $css];
        return $settings;
    }

    public static function enqueueSiteBlocks(): void
    {
        self::enqueueBuilt('farnost-site-blocks', 'site-blocks');
    }

    public static function enqueue(): void
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen) {
            return;
        }

        // Panely sa nahrávajú per CPT — každý panel má vlastný entry v build/.
        // CPT `kostol` Gutenberg editor nepoužíva — má vlastnú admin obrazovku
        // (Admin\KostolyPage). Žiadny panel-kostol.
        switch ($screen->post_type) {
            case 'oznam':
                self::enqueueBuilt('farnost-panel-oznam', 'panel-oznam');
                // Bloky kategórie „Farnosť" — zatiaľ len rozpis-snapshot, ďalšie pribudnú.
                self::enqueueBuilt('farnost-block-rozpis-snapshot', 'block-rozpis-snapshot');
                self::enqueueBuilt('farnost-block-gallery', 'block-gallery');
                break;
            case 'omsa_vynimka':
                self::enqueueBuilt('farnost-panel-vynimka', 'panel-vynimka');
                break;
            case 'umysel':
                self::enqueueBuilt('farnost-panel-umysel', 'panel-umysel');
                break;
            case 'post':
                self::enqueueBuilt('farnost-panel-udalost', 'panel-udalost');
                self::enqueueBuilt('farnost-block-gallery', 'block-gallery');
                break;
            case 'page':
                self::enqueueBuilt('farnost-block-gallery', 'block-gallery');
                break;
        }
    }

    private static function enqueueBuilt(string $handle, string $name): void
    {
        $assetPath = FARNOST_PLUGIN_DIR . "/build/{$name}.asset.php";
        $jsPath    = FARNOST_PLUGIN_DIR . "/build/{$name}.js";
        if (!is_readable($assetPath) || !is_readable($jsPath)) {
            return;
        }
        $asset = include $assetPath;
        if (!is_array($asset) || empty($asset['dependencies'])) {
            return;
        }
        wp_enqueue_script(
            $handle,
            plugins_url("build/{$name}.js", FARNOST_PLUGIN_FILE),
            (array) $asset['dependencies'],
            (string) ($asset['version'] ?? FARNOST_PLUGIN_VERSION),
            true
        );
        wp_set_script_translations($handle, 'farnost-plugin');
    }
}
