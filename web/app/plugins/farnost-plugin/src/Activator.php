<?php

declare(strict_types=1);

namespace Farnost\Plugin;

use Farnost\Plugin\Oznam\BufferManager;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Aktivačná logika pluginu — beží raz pri zapnutí.
 */
final class Activator
{
    public const ROLE = 'farnost_asistent';

    /**
     * Bumpni keď meníš `rewrite` slug / `has_archive` / pridávaš nový CPT —
     * tým sa pri ďalšom admin loade rewrite rules sám refreshne, bez toho
     * aby admin musel re-aktivovať plugin alebo manuálne kliknúť Save
     * v Permalinks.
     */
    private const REWRITE_VERSION = '2026-05-18';

    public static function activate(): void
    {
        self::ensureSlovakTimezone();
        self::ensureRole();
        self::ensureAdministratorCaps();
        self::ensureDefaultCategories();
        BufferManager::scheduleCron();
        // Naplníme buffer rovno teraz. CPT-čka už sú registrované (Plugin::boot beží
        // skôr na init), wp_insert_post bude fungovať.
        BufferManager::refill();
        flush_rewrite_rules();
        update_option('farnost_rewrite_version', self::REWRITE_VERSION);
    }

    /**
     * Auto-flush rewrite rules pri zmene REWRITE_VERSION. Beží na `admin_init`
     * neskorej priorite (po `init` kde sú CPT registrované). Bez tohto by
     * `/oznamy/<slug>/` URL vracali 404 po deploy-i kde sme zmenili CPT slug,
     * lebo Activator::activate beží len pri zapnutí pluginu — nie pri update.
     */
    public static function maybeFlushRewriteRules(): void
    {
        if (get_option('farnost_rewrite_version', '') === self::REWRITE_VERSION) {
            return;
        }
        flush_rewrite_rules();
        update_option('farnost_rewrite_version', self::REWRITE_VERSION);
    }

    /**
     * Default WP inštalácia má timezone_string="" a gmt_offset=0 → UTC.
     * Pre slovenskú farnosť to znamená že "14:40" zadané farárom v admin form
     * sa interpretuje ako UTC, nie ako lokálny čas → expiry banner nefunguje
     * správne v máji-októbri (CEST = UTC+2).
     *
     * Pri prvej aktivácii nastavíme `Europe/Bratislava`. Ak admin už explicitne
     * nastavil iný timezone (timezone_string alebo gmt_offset !== 0), nech tam
     * ostane — nezasahujeme.
     */
    private static function ensureSlovakTimezone(): void
    {
        $current = (string) get_option('timezone_string', '');
        $offset  = (float) get_option('gmt_offset', 0);
        if ($current !== '' || $offset !== 0.0) {
            return;
        }
        update_option('timezone_string', 'Europe/Bratislava');
        update_option('gmt_offset', '');
    }

    public static function deactivate(): void
    {
        // Rolu necháme — keby ju admin omylom deaktivoval, neprídeme o nakonfigurovaných asistentov.
        // Kategórie tiež nemažeme — sú to bežné WP termy s obsahom.
        BufferManager::unscheduleCron();
        flush_rewrite_rules();
    }

    private static function ensureRole(): void
    {
        if (get_role(self::ROLE) !== null) {
            return;
        }

        add_role(self::ROLE, __('Farský asistent', 'farnost-plugin'), [
            'read'                  => true,
            'upload_files'          => true,
            // vlastné posty (udalosti)
            'edit_posts'            => true,
            'edit_published_posts'  => true,
            'publish_posts'         => true,
            'delete_posts'          => true,
            // úmysly (custom CPT s vlastnými capabilities)
            'edit_umysel'           => true,
            'edit_umysly'           => true,
            'edit_published_umysly' => true,
            'publish_umysly'        => true,
            'delete_umysly'         => true,
            'read_umysel'           => true,
        ]);
    }

    /**
     * Administrátorská rola nedostane automaticky custom capabilities pre `umysel` CPT
     * (capability_type = ['umysel', 'umysly']). Treba ich pridať explicitne, inak admin
     * nevie úmysly editovať / publikovať / mazať.
     */
    private static function ensureAdministratorCaps(): void
    {
        $admin = get_role('administrator');
        if ($admin === null) {
            return;
        }
        $caps = [
            'read_umysel',
            'read_private_umysly',
            'edit_umysel',
            'edit_umysly',
            'edit_others_umysly',
            'edit_published_umysly',
            'edit_private_umysly',
            'publish_umysly',
            'delete_umysel',
            'delete_umysly',
            'delete_others_umysly',
            'delete_published_umysly',
            'delete_private_umysly',
        ];
        foreach ($caps as $cap) {
            $admin->add_cap($cap, true);
        }
    }

    private static function ensureDefaultCategories(): void
    {
        $defaults = [
            ['name' => 'Udalosti',               'slug' => 'udalosti',                'color' => '#1e40af'],
            ['name' => 'Zo života farnosti',     'slug' => 'zo-zivota-farnosti',      'color' => '#15803d'],
            ['name' => 'Pozvánky',               'slug' => 'pozvanky',                'color' => '#b45309'],
        ];

        foreach ($defaults as $cat) {
            $existing = get_term_by('slug', $cat['slug'], 'category');
            if ($existing) {
                continue;
            }
            $result = wp_insert_term($cat['name'], 'category', ['slug' => $cat['slug']]);
            if (is_wp_error($result)) {
                continue;
            }
            $termId = (int) $result['term_id'];
            update_term_meta($termId, 'farnost_color', $cat['color']);
            update_term_meta($termId, 'farnost_show_in_menu', true);
        }
    }
}
