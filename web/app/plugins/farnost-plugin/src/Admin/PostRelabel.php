<?php

declare(strict_types=1);

namespace Farnost\Plugin\Admin;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Premenovanie natívneho WP `post` CPT na "Udalosti" naprieč admin UI.
 *
 * V projekte WP posty slúžia ako udalosti / akcie farnosti (viď doc/01-funkcie.md
 * č. 5 „Udalosti a život farnosti"). Default WP label „Príspevky" je zavádzajúci.
 */
final class PostRelabel
{
    public static function register(): void
    {
        // Native 'post' labels sú nastavené v create_initial_post_types() ešte pred načítaním pluginov;
        // filter `post_type_labels_post` by sa už neuplatnil. Preto priamo mutujeme labels objekt
        // na globálnom $wp_post_types po WP init.
        add_action('init', [self::class, 'mutateLabels'], 100);
        add_filter('post_updated_messages', [self::class, 'updateMessages']);
    }

    public static function mutateLabels(): void
    {
        global $wp_post_types;
        if (!isset($wp_post_types['post']) || !isset($wp_post_types['post']->labels)) {
            return;
        }
        $labels = self::relabel($wp_post_types['post']->labels);
        $wp_post_types['post']->labels = $labels;
        $wp_post_types['post']->label  = $labels->name;
    }

    public static function relabel(object $labels): object
    {
        $labels->name                  = __('Udalosti', 'farnost-plugin');
        $labels->singular_name         = __('Udalosť', 'farnost-plugin');
        $labels->add_new               = __('Pridať novú', 'farnost-plugin');
        $labels->add_new_item          = __('Pridať udalosť', 'farnost-plugin');
        $labels->edit_item             = __('Upraviť udalosť', 'farnost-plugin');
        $labels->new_item              = __('Nová udalosť', 'farnost-plugin');
        $labels->view_item             = __('Zobraziť udalosť', 'farnost-plugin');
        $labels->view_items            = __('Zobraziť udalosti', 'farnost-plugin');
        $labels->search_items          = __('Hľadať udalosti', 'farnost-plugin');
        $labels->not_found             = __('Žiadne udalosti.', 'farnost-plugin');
        $labels->not_found_in_trash    = __('Žiadne udalosti v koši.', 'farnost-plugin');
        $labels->all_items             = __('Všetky udalosti', 'farnost-plugin');
        $labels->archives              = __('Archív udalostí', 'farnost-plugin');
        $labels->attributes            = __('Atribúty udalosti', 'farnost-plugin');
        $labels->insert_into_item      = __('Vložiť do udalosti', 'farnost-plugin');
        $labels->uploaded_to_this_item = __('Nahrané k tejto udalosti', 'farnost-plugin');
        $labels->menu_name             = __('Udalosti', 'farnost-plugin');
        $labels->name_admin_bar        = __('Udalosť', 'farnost-plugin');
        $labels->filter_items_list     = __('Filtrovať udalosti', 'farnost-plugin');
        $labels->items_list_navigation = __('Navigácia v udalostiach', 'farnost-plugin');
        $labels->items_list            = __('Zoznam udalostí', 'farnost-plugin');
        $labels->item_published        = __('Udalosť bola zverejnená.', 'farnost-plugin');
        $labels->item_published_privately = __('Udalosť bola zverejnená súkromne.', 'farnost-plugin');
        $labels->item_reverted_to_draft   = __('Udalosť bola vrátená do konceptu.', 'farnost-plugin');
        $labels->item_scheduled        = __('Udalosť bola naplánovaná.', 'farnost-plugin');
        $labels->item_updated          = __('Udalosť bola aktualizovaná.', 'farnost-plugin');

        return $labels;
    }

    /**
     * Hlášky „Príspevok bol aktualizovaný" → „Udalosť bola aktualizovaná" atď.
     *
     * @param array<string, array<int, string|false>> $messages
     * @return array<string, array<int, string|false>>
     */
    public static function updateMessages(array $messages): array
    {
        $post = get_post();
        if (!$post) {
            return $messages;
        }

        $revision = isset($_GET['revision']) ? (int) $_GET['revision'] : 0;

        $messages['post'] = [
            0  => '',
            1  => __('Udalosť aktualizovaná.', 'farnost-plugin'),
            2  => __('Vlastné pole aktualizované.', 'farnost-plugin'),
            3  => __('Vlastné pole zmazané.', 'farnost-plugin'),
            4  => __('Udalosť aktualizovaná.', 'farnost-plugin'),
            5  => $revision
                ? sprintf(__('Udalosť obnovená z revízie z %s.', 'farnost-plugin'), wp_post_revision_title($revision, false))
                : false,
            6  => __('Udalosť publikovaná.', 'farnost-plugin'),
            7  => __('Udalosť uložená.', 'farnost-plugin'),
            8  => __('Udalosť odoslaná na schválenie.', 'farnost-plugin'),
            9  => sprintf(
                __('Udalosť naplánovaná na: %s.', 'farnost-plugin'),
                date_i18n(__('j. F Y, H:i', 'farnost-plugin'), strtotime((string) $post->post_date))
            ),
            10 => __('Koncept udalosti aktualizovaný.', 'farnost-plugin'),
        ];

        return $messages;
    }
}
