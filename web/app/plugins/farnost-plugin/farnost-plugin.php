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
 *
 * @package Farnost\Plugin
 */

declare(strict_types=1);

namespace Farnost\Plugin;

if (!defined('ABSPATH')) {
    exit;
}

define('FARNOST_PLUGIN_VERSION', '0.1.0');
define('FARNOST_PLUGIN_FILE', __FILE__);
define('FARNOST_PLUGIN_DIR', __DIR__);

require_once __DIR__ . '/src/Plugin.php';

Plugin::instance()->boot();
