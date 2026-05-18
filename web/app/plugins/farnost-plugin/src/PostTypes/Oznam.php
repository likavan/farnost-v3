<?php

declare(strict_types=1);

namespace Farnost\Plugin\PostTypes;

use Farnost\Plugin\Admin\Menu;

if (!defined('ABSPATH')) {
    exit;
}

final class Oznam
{
    public const POST_TYPE = 'oznam';

    public static function register(): void
    {
        // Gutenberg detektuje že user nemá publish_posts (náš lockdown) a UI prepne
        // tlačidlo "Update/Publish" na "Submit for Review", ktoré posiela status=pending.
        // Pre oznam to nedáva zmysel — žiadny review flow nemáme. Filter zachová
        // pôvodný status (future/publish/draft) pri každom update.
        add_filter('wp_insert_post_data', [self::class, 'preserveStatusOnUpdate'], 10, 2);

        register_post_type(self::POST_TYPE, [
            'labels'       => [
                'name'          => __('Oznamy', 'farnost-plugin'),
                'singular_name' => __('Oznam', 'farnost-plugin'),
                'add_new_item'  => __('Pridať oznam', 'farnost-plugin'),
                'edit_item'     => __('Upraviť oznam', 'farnost-plugin'),
                'view_item'     => __('Zobraziť oznam', 'farnost-plugin'),
                'menu_name'     => __('Oznamy', 'farnost-plugin'),
            ],
            'public'       => true,
            'has_archive'  => 'oznamy',
            'rewrite'      => ['slug' => 'oznamy'],
            'supports'     => ['title', 'editor', 'thumbnail', 'excerpt', 'revisions', 'author', 'custom-fields'],
            'show_in_rest' => true,
            'rest_base'    => 'oznamy',
            'show_in_menu' => Menu::SLUG,
            // Lifecycle oznamov je výhradne automatický: BufferManager ich vytvára
            // a WP cron publikuje pri dosiahnutí post_date. Manuálne ovládanie
            // (Pridať / Publikovať / Zmazať) by spôsobilo desync s rotáciou
            // upratovania a duplicity v týždňoch — preto kompletný UI lockdown.
            //
            // Cron flow (future→publish) volá wp_publish_post() priamo, bez cap
            // checku, takže auto-publikácia funguje aj pri do_not_allow. Rovnako
            // BufferManager::createWeekOznam volá wp_insert_post() priamo.
            //
            // Technický fallback pre adminov: WP-CLI `wp post delete <id>` obíde
            // capability check (CLI nemá user context).
            // Override len primary caps. `delete_post` (meta cap) WP rieši cez
            // map_meta_cap → primary caps, takže ho v override netreba a jeho
            // explicitné nastavenie vyvolávalo „delete_post called incorrectly"
            // notice z WP 6.1+ keď admin code volal current_user_can('delete_post')
            // bez post ID (napr. niektoré template parts pred resolved postom).
            'capabilities' => [
                'create_posts'           => 'do_not_allow',
                'publish_posts'          => 'do_not_allow',
                'delete_posts'           => 'do_not_allow',
                'delete_published_posts' => 'do_not_allow',
                'delete_others_posts'    => 'do_not_allow',
                'delete_private_posts'   => 'do_not_allow',
            ],
            'map_meta_cap' => true,
        ]);
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $postarr
     * @return array<string, mixed>
     */
    public static function preserveStatusOnUpdate(array $data, array $postarr): array
    {
        if (($data['post_type'] ?? '') !== self::POST_TYPE) {
            return $data;
        }
        // Iba pre update, nie create.
        if (empty($postarr['ID'])) {
            return $data;
        }
        // Reagujeme len keď Gutenberg downgrade-uje na pending.
        if (($data['post_status'] ?? '') !== 'pending') {
            return $data;
        }
        $original = get_post_status((int) $postarr['ID']);
        if (is_string($original) && in_array($original, ['draft', 'future', 'publish', 'private'], true)) {
            $data['post_status'] = $original;
        }
        return $data;
    }

    public static function registerMeta(): void
    {
        // ISO dátumy začiatku a konca týždňa (pondelok–nedeľa), ku ktorému oznam patrí.
        register_post_meta(self::POST_TYPE, 'farnost_tyzden_od', [
            'type'         => 'string',
            'single'       => true,
            'show_in_rest' => true,
            'default'      => '',
        ]);
        register_post_meta(self::POST_TYPE, 'farnost_tyzden_do', [
            'type'         => 'string',
            'single'       => true,
            'show_in_rest' => true,
            'default'      => '',
        ]);
        // Pridelená upratovacia skupina pre tento týždeň (post ID). Pri publikácii
        // sa cez Upratovanie::onTransition posunie pointer v farnost_settings.
        register_post_meta(self::POST_TYPE, 'farnost_upratuje_id', [
            'type'         => 'integer',
            'single'       => true,
            'show_in_rest' => true,
            'default'      => 0,
        ]);
    }
}
