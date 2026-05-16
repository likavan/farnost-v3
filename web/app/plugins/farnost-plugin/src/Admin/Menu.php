<?php

declare(strict_types=1);

namespace Farnost\Plugin\Admin;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Custom top-level "Farnosť" admin menu.
 *
 * Zoskupuje všetky farské obrazovky pod jednou položkou v ľavom sidebare WP adminu.
 * CPT-čka (kostol, oznam, upratovacia_skupina) sa pripoja cez `show_in_menu => self::SLUG`,
 * vlastné stránky (Kalendár, Mimoriadny oznam, Nastavenia, Návod) tu registrujeme priamo.
 *
 * `omsa_vynimka` a `umysel` v menu nemajú vlastnú položku — vznikajú z kontextu
 * (kalendár, editor oznamu).
 */
final class Menu
{
    public const SLUG = 'farnost';
    public const CAPABILITY = 'edit_posts';

    public static function register(): void
    {
        add_action('admin_menu', [self::class, 'addMenuPages']);
        add_action('admin_menu', [self::class, 'reorderSubmenu'], 99);
        add_action('admin_enqueue_scripts', [self::class, 'enqueueAssets']);
    }

    public static function enqueueAssets(): void
    {
        if (!isset($_GET['page'])) {
            return;
        }
        $page = (string) $_GET['page'];
        if ($page === 'farnost-nastavenia') {
            // WP media JS pre logo picker.
            wp_enqueue_media();
        }
    }

    public static function addMenuPages(): void
    {
        // Top-level „Farnosť"
        add_menu_page(
            __('Farnosť', 'farnost-plugin'),
            __('Farnosť', 'farnost-plugin'),
            self::CAPABILITY,
            self::SLUG,
            [self::class, 'renderCalendar'], // default obrazovka = Kalendár omší
            self::iconSvg(),
            1 // úplne navrch admin sidebaru — nad Dashboard (default 2).
        );

        // Submenu: Kalendár omší (rovnaký slug ako parent → premenuje default položku)
        add_submenu_page(
            self::SLUG,
            __('Kalendár omší', 'farnost-plugin'),
            __('Kalendár omší', 'farnost-plugin'),
            self::CAPABILITY,
            self::SLUG,
            [self::class, 'renderCalendar']
        );

        // Submenu: Mimoriadny oznam
        add_submenu_page(
            self::SLUG,
            __('Mimoriadny oznam', 'farnost-plugin'),
            __('Mimoriadny oznam', 'farnost-plugin'),
            self::CAPABILITY,
            'farnost-mimoriadny-oznam',
            [self::class, 'renderMimoriadny']
        );

        // Submenu: Nastavenia (vyžaduje vyššie oprávnenie)
        add_submenu_page(
            self::SLUG,
            __('Nastavenia', 'farnost-plugin'),
            __('Nastavenia', 'farnost-plugin'),
            'manage_options',
            'farnost-nastavenia',
            [self::class, 'renderSettings']
        );

        // Submenu: Návod
        add_submenu_page(
            self::SLUG,
            __('Návod', 'farnost-plugin'),
            __('Návod', 'farnost-plugin'),
            self::CAPABILITY,
            'farnost-navod',
            [self::class, 'renderNavod']
        );
    }

    /**
     * Reorder submenu items: Kalendár, Kostoly, Oznamy, Mimoriadny, Upratovacie, ─, Nastavenia, Návod.
     * CPT submenu pages WordPress vkladá automaticky cez `show_in_menu`, pridáme ich do správneho poradia.
     */
    public static function reorderSubmenu(): void
    {
        global $submenu;
        if (!isset($submenu[self::SLUG]) || !is_array($submenu[self::SLUG])) {
            return;
        }

        $desired = [
            self::SLUG,                          // Kalendár omší (parent slug)
            'edit.php?post_type=kostol',         // Kostoly
            'edit.php?post_type=oznam',          // Oznamy
            'farnost-mimoriadny-oznam',          // Mimoriadny oznam
            'edit.php?post_type=upratovacia_skupina', // Upratovacie skupiny
            'farnost-nastavenia',                // Nastavenia
            'farnost-navod',                     // Návod
        ];

        $byKey = [];
        foreach ($submenu[self::SLUG] as $item) {
            // $item[2] je slug / URL submenu položky
            $byKey[$item[2]] = $item;
        }

        $reordered = [];
        $idx = 0;
        foreach ($desired as $key) {
            if (isset($byKey[$key])) {
                $reordered[$idx++] = $byKey[$key];
                unset($byKey[$key]);
            }
        }
        // Append anything that wasn't in desired list (shouldn't happen, ale poistka).
        foreach ($byKey as $item) {
            $reordered[$idx++] = $item;
        }

        $submenu[self::SLUG] = $reordered;
    }

    public static function renderCalendar(): void
    {
        self::renderPlaceholder(
            __('Kalendár omší', 'farnost-plugin'),
            __('Tu pribudne kalendárny pohľad omší a úmyslov (mesačný / týždenný).', 'farnost-plugin'),
            __('Detaily v doc/07-admin-ux.md → „Kalendárny pohľad — omše a úmysly".', 'farnost-plugin')
        );
    }

    public static function renderMimoriadny(): void
    {
        self::renderPlaceholder(
            __('Mimoriadny oznam', 'farnost-plugin'),
            __('Tu pribudne rýchly editor mimoriadneho oznamu (banner s voliteľnou expiry).', 'farnost-plugin'),
            __('Detaily v doc/06-struktura-stranky.md → „Mimoriadny oznam (banner)".', 'farnost-plugin')
        );
    }

    public static function renderSettings(): void
    {
        SettingsPage::render();
    }

    public static function renderNavod(): void
    {
        self::renderPlaceholder(
            __('Návod', 'farnost-plugin'),
            __('Tu pribudne onboarding návod — knowledge base s článkami a kontextovou pomocou.', 'farnost-plugin'),
            __('Detaily v doc/07-admin-ux.md → „Pomoc a dokumentácia".', 'farnost-plugin')
        );
    }

    private static function renderPlaceholder(string $title, string $description, string $reference): void
    {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html($title); ?></h1>
            <div style="max-width:720px;background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:24px;margin-top:16px;">
                <p style="font-size:16px;line-height:1.6;margin:0 0 12px;"><?php echo esc_html($description); ?></p>
                <p style="font-size:13px;color:#6b7280;margin:0;"><?php echo esc_html($reference); ?></p>
            </div>
        </div>
        <?php
    }

    /**
     * SVG ikona pre menu — jednoduchý latinský kríž, encoded ako data URI.
     */
    private static function iconSvg(): string
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path fill="black" d="M8 0v6H2v3h6v11h4V9h6V6h-6V0H8z"/></svg>';
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }
}
