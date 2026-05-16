<?php
/**
 * Main plugin entry class.
 *
 * @package Farnost\Plugin
 */

declare(strict_types=1);

namespace Farnost\Plugin;

if (!defined('ABSPATH')) {
    exit;
}

final class Plugin
{
    private static ?Plugin $instance = null;

    public static function instance(): Plugin
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function boot(): void
    {
        // Bootstrap subsystems (CPT, REST, blocks, …) — pridáme v Etape 1.
    }
}
