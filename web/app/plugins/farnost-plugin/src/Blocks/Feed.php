<?php

declare(strict_types=1);

namespace Farnost\Plugin\Blocks;

use DateTimeImmutable;
use Farnost\Plugin\PostTypes\Oznam;
use WP_Post;
use WP_Query;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Dynamic block `farnost/feed` — zlúči `oznam` CPT a natívne `post` (udalosti)
 * do chronologického feedu kariet podľa post_date DESC.
 *
 * Detekcia post-card variantu:
 *  - oznam               → `oznamy` (Farské oznamy s rozpis-snapshot blokom)
 *  - post + event meta   → `udalost` (Pozvánka s Kedy/Kde gridom)
 *  - post + thumbnail    → `text-foto` (Pripomenutie s fotkami)
 *  - post                → `text` (Krátka správa)
 */
final class Feed
{
    public const NAME = 'farnost/feed';

    private const SLOVAK_MONTHS = [
        1 => 'januára', 2 => 'februára', 3 => 'marca', 4 => 'apríla',
        5 => 'mája',    6 => 'júna',     7 => 'júla',  8 => 'augusta',
        9 => 'septembra', 10 => 'októbra', 11 => 'novembra', 12 => 'decembra',
    ];

    private const SLOVAK_WEEKDAYS = [
        1 => 'Pondelok', 2 => 'Utorok',  3 => 'Streda',  4 => 'Štvrtok',
        5 => 'Piatok',   6 => 'Sobota',  7 => 'Nedeľa',
    ];

    public static function register(): void
    {
        add_action('init', [self::class, 'registerBlock']);
    }

    public static function registerBlock(): void
    {
        register_block_type(self::NAME, [
            'api_version'     => 3,
            'render_callback' => [self::class, 'render'],
            'attributes'      => [
                'limit' => ['type' => 'integer', 'default' => 20],
            ],
        ]);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public static function render(array $attributes): string
    {
        $limit = isset($attributes['limit']) ? max(1, min(100, (int) $attributes['limit'])) : 20;

        $q = new WP_Query([
            'post_type'      => [Oznam::POST_TYPE, 'post'],
            'post_status'    => 'publish',
            'posts_per_page' => $limit,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'no_found_rows'  => true,
        ]);

        if (empty($q->posts)) {
            return '<div class="farnost-feed-empty">'
                . esc_html__('Zatiaľ žiadne príspevky.', 'farnost-plugin')
                . '</div>';
        }

        $out = '<div class="farnost-feed">';
        foreach ($q->posts as $post) {
            $out .= self::renderPost($post);
        }
        $out .= '</div>';
        return $out;
    }

    private static function renderPost(WP_Post $post): string
    {
        $variant = self::variantFor($post);
        $type = self::typeMeta($post, $variant);
        $meta = self::renderMeta($post, $type);
        $title = esc_html(get_the_title($post));
        $permalink = esc_url(get_permalink($post));
        // Farba kategórie sa aplikuje na celý article ako custom property —
        // CSS ju použije pre top-border. Ak chýba (oznam alebo post bez
        // viditeľnej kategórie), CSS sa preklopí na variant-default.
        $styleAttr = $type['color'] !== ''
            ? ' style="--farnost-cat-color: ' . esc_attr($type['color']) . '"'
            : '';

        ob_start();
        ?>
        <article id="post-<?php echo (int) $post->ID; ?>" class="farnost-post farnost-post--<?php echo esc_attr($variant); ?>"<?php echo $styleAttr; ?>>
            <?php echo $meta; // already escaped ?>
            <?php if ($variant === 'udalost') : ?>
                <?php echo self::renderUdalostGrid($post); ?>
            <?php endif; ?>
            <h2 class="farnost-post-title"><a href="<?php echo $permalink; ?>"><?php echo $title; ?></a></h2>
            <div class="farnost-post-body">
                <?php echo apply_filters('the_content', $post->post_content); ?>
            </div>
        </article>
        <?php
        return (string) ob_get_clean();
    }

    private static function variantFor(WP_Post $post): string
    {
        if ($post->post_type === Oznam::POST_TYPE) {
            return 'oznamy';
        }
        $hasEvent = get_post_meta($post->ID, 'farnost_event_when', true) !== ''
            || get_post_meta($post->ID, 'farnost_event_where', true) !== '';
        if ($hasEvent) {
            return 'udalost';
        }
        if (has_post_thumbnail($post->ID)) {
            return 'text-foto';
        }
        return 'text';
    }

    /**
     * @param array{label: string, color: string} $type
     */
    private static function renderMeta(WP_Post $post, array $type): string
    {
        $dateLabel = self::formatDateSlovak((string) $post->post_date);
        $author = self::authorName($post);

        ob_start();
        ?>
        <div class="farnost-post-meta">
            <span class="farnost-post-type"><?php echo esc_html($type['label']); ?></span>
            <span class="farnost-post-meta-dot">·</span>
            <span class="farnost-post-date"><?php echo esc_html($dateLabel); ?></span>
            <?php if ($author !== '') : ?>
                <span class="farnost-post-meta-dot">·</span>
                <span class="farnost-post-author"><?php echo esc_html($author); ?></span>
            <?php endif; ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    private static function renderUdalostGrid(WP_Post $post): string
    {
        $when  = (string) get_post_meta($post->ID, 'farnost_event_when', true);
        $where = (string) get_post_meta($post->ID, 'farnost_event_where', true);
        if ($when === '' && $where === '') {
            return '';
        }
        ob_start();
        ?>
        <div class="farnost-udalost-grid">
            <?php if ($when !== '') : ?>
                <div>
                    <div class="farnost-udalost-label"><?php esc_html_e('Kedy', 'farnost-plugin'); ?></div>
                    <div class="farnost-udalost-value"><?php echo esc_html($when); ?></div>
                </div>
            <?php endif; ?>
            <?php if ($where !== '') : ?>
                <div>
                    <div class="farnost-udalost-label"><?php esc_html_e('Kde', 'farnost-plugin'); ?></div>
                    <div class="farnost-udalost-value"><?php echo esc_html($where); ?></div>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Vráti label + farbu kategórie pre meta riadok.
     *
     * Oznam CPT má fixný label "Farské oznamy" bez farby (akcent default).
     * Natívny `post` použije primárnu (prvú podľa term_id ASC) WP kategóriu
     * filtrovanú cez farnost_show_in_menu. Farba pochádza z term meta
     * farnost_color — admin ju editoval v Príspevky → Kategórie (CategoryAdmin
     * color picker). Ak nie je nastavená, vraciame '' a CSS prefere accent.
     *
     * @return array{label: string, color: string}
     */
    private static function typeMeta(WP_Post $post, string $variant): array
    {
        if ($variant === 'oznamy') {
            return ['label' => __('Farské oznamy', 'farnost-plugin'), 'color' => ''];
        }
        $cats = get_the_category($post->ID);
        $visible = array_filter($cats, static function (\WP_Term $c): bool {
            $flag = get_term_meta($c->term_id, 'farnost_show_in_menu', true);
            return ($flag === '' || $flag === null) ? true : (bool) $flag;
        });
        // Sortne podľa term_id ASC — stabilná „primárna" kategória.
        usort($visible, static fn(\WP_Term $a, \WP_Term $b): int => $a->term_id <=> $b->term_id);
        if (!empty($visible)) {
            $primary = reset($visible);
            $color = (string) get_term_meta($primary->term_id, 'farnost_color', true);
            return [
                'label' => (string) $primary->name,
                'color' => self::sanitizeHexColor($color),
            ];
        }
        $fallback = match ($variant) {
            'udalost'   => __('Pozvánka', 'farnost-plugin'),
            'text-foto' => __('Pripomenutie', 'farnost-plugin'),
            default     => __('Oznam', 'farnost-plugin'),
        };
        return ['label' => $fallback, 'color' => ''];
    }

    private static function sanitizeHexColor(string $color): string
    {
        $color = trim($color);
        if ($color === '') {
            return '';
        }
        return preg_match('/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/', $color) === 1
            ? strtolower($color)
            : '';
    }

    private static function authorName(WP_Post $post): string
    {
        $name = (string) get_the_author_meta('display_name', (int) $post->post_author);
        return trim($name);
    }

    private static function formatDateSlovak(string $mysqlDate): string
    {
        $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $mysqlDate, wp_timezone());
        if ($dt === false) {
            return $mysqlDate;
        }
        $weekday = self::SLOVAK_WEEKDAYS[(int) $dt->format('N')] ?? '';
        $day     = (int) $dt->format('j');
        $month   = self::SLOVAK_MONTHS[(int) $dt->format('n')] ?? '';
        $year    = $dt->format('Y');
        return sprintf('%s, %d. %s %s', $weekday, $day, $month, $year);
    }
}
