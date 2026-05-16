<?php
/**
 * Plugin Name:       Farnosť Online
 * Plugin URI:        https://farnost.online
 * Description:       Farská funkcionalita pre slovenské farnosti — CPT, logika rozpisu omší, settings, Gutenberg bloky.
 * Version:           0.1.0
 * Requires at least: 6.5
 * Requires PHP:      8.2
 * Author:            Digitalka
 * Author URI:        https://digitalka.sk
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       farnost-plugin
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

define('FARNOST_PLUGIN_VERSION', '0.1.0');
define('FARNOST_PLUGIN_FILE', __FILE__);
define('FARNOST_PLUGIN_DIR', __DIR__);
define('FARNOST_REST_NAMESPACE', 'farnost/v1');

// PSR-4 autoloader (Farnost\Plugin\ -> src/)
spl_autoload_register(static function (string $class): void {
    $prefix = 'Farnost\\Plugin\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $path = FARNOST_PLUGIN_DIR . '/src/' . str_replace('\\', '/', $relative) . '.php';
    if (is_readable($path)) {
        require_once $path;
    }
});

// Activation / deactivation
register_activation_hook(__FILE__, [\Farnost\Plugin\Activator::class, 'activate']);
register_deactivation_hook(__FILE__, [\Farnost\Plugin\Activator::class, 'deactivate']);

// Boot
\Farnost\Plugin\Plugin::instance()->boot();
