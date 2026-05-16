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
    }

    public static function enqueue(): void
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen) {
            return;
        }

        // Panely sa nahrávajú per CPT — každý panel má vlastný entry v build/.
        switch ($screen->post_type) {
            case 'kostol':
                self::enqueueBuilt('farnost-panel-kostol', 'panel-kostol');
                break;
            case 'oznam':
                self::enqueueBuilt('farnost-panel-oznam', 'panel-oznam');
                // Bloky kategórie „Farnosť" — zatiaľ len rozpis-snapshot, ďalšie pribudnú.
                self::enqueueBuilt('farnost-block-rozpis-snapshot', 'block-rozpis-snapshot');
                break;
            case 'omsa_vynimka':
                self::enqueueBuilt('farnost-panel-vynimka', 'panel-vynimka');
                break;
            case 'umysel':
                self::enqueueBuilt('farnost-panel-umysel', 'panel-umysel');
                break;
            case 'post':
                self::enqueueBuilt('farnost-panel-udalost', 'panel-udalost');
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
