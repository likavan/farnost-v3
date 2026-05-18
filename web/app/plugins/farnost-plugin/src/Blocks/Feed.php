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
                <?php echo self::renderBody($post, $variant); ?>
            </div>
            <?php if ($variant !== 'oznamy') : ?>
                <?php echo self::renderFeedGallery($post, $permalink); ?>
            <?php endif; ?>
            <p class="farnost-post-more">
                <a href="<?php echo $permalink; ?>"><?php esc_html_e('Čítať viac', 'farnost-plugin'); ?> <span aria-hidden="true">→</span></a>
            </p>
        </article>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Oznam vo feede zobrazí plný obsah (týždenný rozpis + voľný text — to je
     * core hodnota pre veriaceho). `post` (udalost/text-foto/text) zobrazí len
     * krátky náhľad — plný text až na single page po klike „Čítať viac".
     */
    private static function renderBody(WP_Post $post, string $variant): string
    {
        if ($variant === 'oznamy') {
            return (string) apply_filters('the_content', $post->post_content);
        }
        // Explicit excerpt má prednosť; ak chýba, auto-trim z post_content.
        $excerpt = trim((string) $post->post_excerpt);
        if ($excerpt === '') {
            $stripped = wp_strip_all_tags(strip_shortcodes((string) $post->post_content));
            $excerpt  = wp_trim_words($stripped, 40, '…');
        }
        if ($excerpt === '') {
            return '';
        }
        return '<p>' . esc_html($excerpt) . '</p>';
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
        $whenLabel = $when !== '' ? self::formatEventWhen($when) : '';
        ?>
        <div class="farnost-udalost-grid">
            <?php if ($whenLabel !== '') : ?>
                <div>
                    <div class="farnost-udalost-label"><?php esc_html_e('Kedy', 'farnost-plugin'); ?></div>
                    <div class="farnost-udalost-value"><?php echo esc_html($whenLabel); ?></div>
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
     * Detekuje prvý `farnost/gallery` blok v obsahu postu (vrátane nested
     * innerBlocks) a vykreslí teaser grid pre feed kartu — max 3 viditeľné
     * fotky, 4. dlaždica je „+N" overlay ak je v galérii viac fotiek.
     *
     * Všetky dlaždice sú odkaz na permalink článku (NIE lightbox) — vo feed-e
     * fotka slúži ako teaser, browse celej galérie a lightbox sa otvára až
     * na detail stránke.
     */
    private static function renderFeedGallery(WP_Post $post, string $permalinkEscaped): string
    {
        $images = self::findFirstGalleryImages((string) $post->post_content);
        if (empty($images)) {
            return '';
        }
        $count    = count($images);
        $visible  = array_slice($images, 0, 3);
        $overflow = $count - count($visible);
        $variant  = match (true) {
            $count === 1 => 'count-1',
            $count === 2 => 'count-2',
            default      => 'count-3',
        };

        ob_start();
        ?>
        <div class="farnost-feed-gallery farnost-feed-gallery--<?php echo esc_attr($variant); ?>">
            <?php foreach ($visible as $i => $img) :
                $url    = (string) ($img['url'] ?? '');
                $alt    = (string) ($img['alt'] ?? '');
                $isLast = $i === count($visible) - 1;
                $hasOverlay = $isLast && $overflow > 0;
                if ($url === '') { continue; }
                ?>
                <a
                    class="farnost-feed-gallery__item<?php echo $hasOverlay ? ' has-overlay' : ''; ?>"
                    href="<?php echo $permalinkEscaped; ?>"
                    aria-label="<?php echo esc_attr($hasOverlay
                        ? sprintf(__('Otvoriť článok — galéria obsahuje +%d ďalších fotiek', 'farnost-plugin'), $overflow)
                        : __('Otvoriť článok', 'farnost-plugin')); ?>"
                >
                    <img class="farnost-feed-gallery__img" src="<?php echo esc_url($url); ?>" alt="<?php echo esc_attr($alt); ?>" loading="lazy" decoding="async" />
                    <?php if ($hasOverlay) : ?>
                        <span class="farnost-feed-gallery__overlay">+<?php echo (int) $overflow; ?></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Rekurzívne prejde block tree post_content-u a vráti `images[]` atribút
     * prvej `farnost/gallery` ktorú nájde. Prázdne pole ak galéria nie je.
     *
     * Public pre unit testy (pure deterministická logika, žiadne side-effects
     * okrem WP block parser-u).
     *
     * @return list<array<string, mixed>>
     */
    public static function findFirstGalleryImages(string $content): array
    {
        if ($content === '' || !has_block('farnost/gallery', $content)) {
            return [];
        }
        $blocks = parse_blocks($content);
        $found  = self::walkForGallery($blocks);
        if (!is_array($found)) {
            return [];
        }
        return array_values(array_filter($found, 'is_array'));
    }

    /**
     * @param list<array<string, mixed>> $blocks
     * @return list<array<string, mixed>>|null
     */
    private static function walkForGallery(array $blocks): ?array
    {
        foreach ($blocks as $b) {
            if (($b['blockName'] ?? '') === 'farnost/gallery') {
                $imgs = $b['attrs']['images'] ?? [];
                if (is_array($imgs) && !empty($imgs)) {
                    return $imgs;
                }
            }
            if (!empty($b['innerBlocks']) && is_array($b['innerBlocks'])) {
                $nested = self::walkForGallery($b['innerBlocks']);
                if ($nested !== null) {
                    return $nested;
                }
            }
        }
        return null;
    }

    /**
     * Lokalizuje raw event-when string do slovenského formátu cez `wp_date()`.
     * Akceptuje viaceré tvary uložené v meta `farnost_event_when`:
     *  - "Y-m-d H:i"      (nový default zo DateTimePicker — pripísal ' '+čas)
     *  - "Y-m-d\TH:i:s"   (ISO 8601 ak by sa raw hodnota z DateTimePicker uložila)
     *  - "Y-m-d"          (len dátum, bez času)
     * Pre legacy / free-form text ktorý sa neparsne vráti ho raw — autor si
     * mohol napísať čokoľvek (napr. „Sobota 18:30").
     *
     * Public pre unit testy.
     */
    public static function formatEventWhen(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }
        $tz = wp_timezone();
        $formats = [
            ['Y-m-d H:i',     true],
            ['Y-m-d\\TH:i:s', true],
            ['Y-m-d\\TH:i',   true],
            ['Y-m-d',         false],
        ];
        foreach ($formats as [$fmt, $hasTime]) {
            $dt = DateTimeImmutable::createFromFormat($fmt, $raw, $tz);
            if ($dt instanceof DateTimeImmutable) {
                $pattern = $hasTime ? 'j. F Y, H:i' : 'j. F Y';
                return (string) wp_date($pattern, $dt->getTimestamp());
            }
        }
        return $raw;
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
