<?php

declare(strict_types=1);

namespace Farnost\Plugin\Admin;

use Farnost\Plugin\Meta\CategoryMeta;
use WP_Term;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin UI pre term meta na natívnej taxonómii `category`.
 *
 * Pridáva:
 * - color picker (wp-color-picker, jQuery iris) pre `farnost_color`,
 * - checkbox „Zobraziť v menu webu" pre `farnost_show_in_menu`,
 * - vlastné stĺpce vo výpise kategórií (farba + viditeľnosť),
 * - save handlery na `created_category` / `edited_category`.
 *
 * Meta sú zaregistrované v `Farnost\Plugin\Meta\CategoryMeta` (vrátane sanitácie).
 */
final class CategoryAdmin
{
    private const DEFAULT_COLOR = '#6b7280';

    public static function register(): void
    {
        // Form polia
        add_action('category_add_form_fields', [self::class, 'renderAddFields']);
        add_action('category_edit_form_fields', [self::class, 'renderEditFields']);

        // Save handler — beží na rovnaký hook pre create aj edit.
        add_action('created_category', [self::class, 'save']);
        add_action('edited_category', [self::class, 'save']);

        // Listing
        add_filter('manage_edit-category_columns', [self::class, 'addColumns']);
        add_filter('manage_category_custom_column', [self::class, 'renderColumn'], 10, 3);

        // Color picker assety len na taxonomy=category obrazovkách.
        add_action('admin_enqueue_scripts', [self::class, 'enqueueAssets']);
    }

    public static function enqueueAssets(string $hook): void
    {
        // edit-tags.php (zoznam + add form), term.php (edit form)
        if (!in_array($hook, ['edit-tags.php', 'term.php'], true)) {
            return;
        }
        if (!isset($_GET['taxonomy']) || $_GET['taxonomy'] !== 'category') {
            return;
        }
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
        // Inline init — pripojí iris na vstupy s našou triedou.
        wp_add_inline_script(
            'wp-color-picker',
            "jQuery(function(\$){\$('.farnost-color-field').wpColorPicker();});"
        );
    }

    public static function renderAddFields(): void
    {
        ?>
        <div class="form-field term-farnost-color-wrap">
            <label for="farnost_color"><?php esc_html_e('Farba kategórie', 'farnost-plugin'); ?></label>
            <input type="text"
                   name="farnost_color"
                   id="farnost_color"
                   value="<?php echo esc_attr(self::DEFAULT_COLOR); ?>"
                   class="farnost-color-field"
                   data-default-color="<?php echo esc_attr(self::DEFAULT_COLOR); ?>">
            <p class="description"><?php esc_html_e('Použije sa na badge kategórie vo feed-e na frontende.', 'farnost-plugin'); ?></p>
        </div>
        <div class="form-field term-farnost-show-in-menu-wrap">
            <label>
                <input type="checkbox" name="farnost_show_in_menu" value="1" checked>
                <?php esc_html_e('Zobraziť v menu webu', 'farnost-plugin'); ?>
            </label>
            <p class="description"><?php esc_html_e('Ak je odškrtnuté, kategória sa nezobrazí v hlavnom menu (ale obsah ostáva prístupný cez archív).', 'farnost-plugin'); ?></p>
        </div>
        <?php
    }

    public static function renderEditFields(WP_Term $term): void
    {
        $color       = get_term_meta($term->term_id, 'farnost_color', true);
        $color       = is_string($color) && $color !== '' ? $color : self::DEFAULT_COLOR;
        $showInMenuRaw = get_term_meta($term->term_id, 'farnost_show_in_menu', true);
        // Default = true: ak meta ešte nikdy nebola zapísaná (prázdny string), interpretujeme ako on.
        $showInMenu  = ($showInMenuRaw === '' ) ? true : (bool) $showInMenuRaw;
        ?>
        <tr class="form-field term-farnost-color-wrap">
            <th scope="row"><label for="farnost_color"><?php esc_html_e('Farba kategórie', 'farnost-plugin'); ?></label></th>
            <td>
                <input type="text"
                       name="farnost_color"
                       id="farnost_color"
                       value="<?php echo esc_attr($color); ?>"
                       class="farnost-color-field"
                       data-default-color="<?php echo esc_attr(self::DEFAULT_COLOR); ?>">
                <p class="description"><?php esc_html_e('Použije sa na badge kategórie vo feed-e na frontende.', 'farnost-plugin'); ?></p>
            </td>
        </tr>
        <tr class="form-field term-farnost-show-in-menu-wrap">
            <th scope="row"><?php esc_html_e('Menu webu', 'farnost-plugin'); ?></th>
            <td>
                <label>
                    <input type="checkbox"
                           name="farnost_show_in_menu"
                           value="1"
                           <?php checked($showInMenu, true); ?>>
                    <?php esc_html_e('Zobraziť v menu webu', 'farnost-plugin'); ?>
                </label>
                <p class="description"><?php esc_html_e('Ak je odškrtnuté, kategória sa nezobrazí v hlavnom menu (ale obsah ostáva prístupný cez archív).', 'farnost-plugin'); ?></p>
            </td>
        </tr>
        <?php
    }

    public static function save(int $termId): void
    {
        if (!current_user_can('manage_categories')) {
            return;
        }

        if (isset($_POST['farnost_color'])) {
            $raw = (string) wp_unslash($_POST['farnost_color']);
            // Sanitácia využíva tú istú logiku, akú má registered meta — nech sa
            // do DB nedostane neplatná hodnota (REST endpoint by ju filtroval, ale
            // tu sa zapisuje priamo cez update_term_meta).
            $color = CategoryMeta::sanitizeHexColor($raw);
            update_term_meta($termId, 'farnost_color', $color);
        }

        // Checkboxy v HTML form-e neposielajú nič, keď sú odškrtnuté → vždy uložiť
        // explicitnú hodnotu na základe prítomnosti kľúča.
        $showInMenu = isset($_POST['farnost_show_in_menu']);
        update_term_meta($termId, 'farnost_show_in_menu', $showInMenu);
    }

    public static function addColumns(array $columns): array
    {
        // Vložíme „Farba" + „V menu" hneď za názov (slug). Defaultné stĺpce sú:
        // cb, name, description, slug, count.
        $insert = [
            'farnost_color' => __('Farba', 'farnost-plugin'),
            'farnost_menu'  => __('V menu', 'farnost-plugin'),
        ];
        $out = [];
        foreach ($columns as $key => $label) {
            $out[$key] = $label;
            if ($key === 'slug') {
                foreach ($insert as $k => $v) {
                    $out[$k] = $v;
                }
            }
        }
        // Ak by 'slug' chýbal (vlastné customizácie), pripneme na koniec.
        if (!isset($out['farnost_color'])) {
            $out = array_merge($out, $insert);
        }
        return $out;
    }

    public static function renderColumn(string $content, string $columnName, int $termId): string
    {
        if ($columnName === 'farnost_color') {
            $color = get_term_meta($termId, 'farnost_color', true);
            $color = is_string($color) && $color !== '' ? $color : self::DEFAULT_COLOR;
            return sprintf(
                '<span style="display:inline-block;width:18px;height:18px;border-radius:3px;background:%s;border:1px solid rgba(0,0,0,0.1);vertical-align:middle;" title="%s"></span> <code style="font-size:11px;color:#6b7280;">%s</code>',
                esc_attr($color),
                esc_attr($color),
                esc_html($color)
            );
        }
        if ($columnName === 'farnost_menu') {
            $raw = get_term_meta($termId, 'farnost_show_in_menu', true);
            $on  = ($raw === '') ? true : (bool) $raw;
            return $on
                ? '<span style="color:#15803d;" aria-label="' . esc_attr__('Áno', 'farnost-plugin') . '">✓</span>'
                : '<span style="color:#9ca3af;" aria-label="' . esc_attr__('Nie', 'farnost-plugin') . '">—</span>';
        }
        return $content;
    }
}
