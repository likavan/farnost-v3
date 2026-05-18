<?php

declare(strict_types=1);

namespace Farnost\Plugin\Blocks;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Dynamic block `farnost/gallery` — vlastná galéria fotiek pre farský web.
 *
 * Nahradzuje `core/gallery` (skrytý z inserter-u cez BlockRestrictions) — chceme
 * editor­ský layout s mosaicom 1+2 (3 fotky) / 2×2 (4 fotky) / big+overlay „+N"
 * (5+ fotiek) a vlastný carousel lightbox so šípkami, counterom a swipe-om.
 *
 * Atribúty:
 *   - images: list<{ id, url, alt, caption, width, height, fullUrl }>
 *   - lightbox: bool — kliknutie na fotku otvorí carousel; default true
 *   - showCaptions: bool — captions sa zobrazia pod každou fotkou; default false
 *
 * Render: ne­zobrazuje pre prázdny zoznam (autor v editori môže blok ponechať
 * prázdny — render to skipne aby vznikol „prázdny div"). Layout class
 * `--count-{N}` určuje grid pattern v CSS.
 */
final class Gallery
{
    public const NAME = 'farnost/gallery';

    public static function register(): void
    {
        add_action('init', [self::class, 'registerBlock']);
        // Force-lightbox na core/image — share rovnaký carousel JS ako gallery,
        // aby UX bol konzistentný (single image otvorí lightbox bez šípok/counter).
        add_filter('render_block_core/image', [self::class, 'augmentCoreImage'], 10, 2);
    }

    /**
     * Pripojí lightbox hook na core/image render output. Pridá na koreňový
     * <figure> alebo <div class="wp-block-image…"> atribúty:
     *   - data-farnost-lightbox="single"
     *   - data-full-src="<plná URL>"
     *
     * Plná URL pochádza z attachment_id (uloženého v block attrs); pre staršie
     * obrázky bez ID skúsi prvé `src=` v HTML ako fallback.
     *
     * @param string $html
     * @param array<string, mixed> $block
     */
    public static function augmentCoreImage(string $html, array $block): string
    {
        $id = isset($block['attrs']['id']) ? (int) $block['attrs']['id'] : 0;
        $fullUrl = '';
        if ($id > 0) {
            $src = wp_get_attachment_image_src($id, 'full');
            if (is_array($src) && !empty($src[0])) {
                $fullUrl = (string) $src[0];
            }
        }
        if ($fullUrl === '' && preg_match('/<img[^>]+src="([^"]+)"/i', $html, $m) === 1) {
            $fullUrl = (string) $m[1];
        }
        if ($fullUrl === '') {
            return $html;
        }
        $attrs = ' data-farnost-lightbox="single" data-full-src="' . esc_attr($fullUrl) . '"';
        // Inject atribúty na prvý <figure ...> alebo <div ...> ktorý má class
        // obsahujúcu „wp-block-image". Použijeme stricter regex aby sme nezasiahli
        // hlbšie wrappery (linked image cez core/cover atď.).
        $patched = preg_replace(
            '/^(<(?:figure|div)\b[^>]*class="[^"]*\bwp-block-image\b[^"]*")/i',
            '$1' . $attrs,
            $html,
            1
        );
        return is_string($patched) ? $patched : $html;
    }

    public static function registerBlock(): void
    {
        register_block_type(self::NAME, [
            'api_version'     => 3,
            'editor_script'   => 'farnost-block-gallery',
            'render_callback' => [self::class, 'render'],
            'attributes'      => [
                'images'       => ['type' => 'array',   'default' => []],
                'lightbox'     => ['type' => 'boolean', 'default' => true],
                'showCaptions' => ['type' => 'boolean', 'default' => false],
            ],
        ]);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public static function render(array $attributes): string
    {
        $images = isset($attributes['images']) && is_array($attributes['images'])
            ? array_values(array_filter($attributes['images'], 'is_array'))
            : [];
        if (empty($images)) {
            return '';
        }
        $lightbox     = !empty($attributes['lightbox']);
        $showCaptions = !empty($attributes['showCaptions']);
        $count        = count($images);
        $variant      = self::variantFor($count);
        // Konzistentný layout naprieč feed + detail: pre 3+ fotky vždy 1+2
        // mosaic so „+N" overlay na 3. dlaždici ak count > 3. Render obsahuje
        // VŠETKY figures — CSS skryje 4+ pre lightbox traversal (klik na overlay
        // otvorí carousel cez všetky fotky bez ohľadu na to či sú v DOM viditeľné).
        $overflow = $variant === 'count-3' ? max(0, $count - 3) : 0;

        ob_start();
        ?>
        <div class="wp-block-farnost-gallery farnost-gallery farnost-gallery--<?php echo esc_attr($variant); ?>" data-count="<?php echo (int) $count; ?>">
            <?php foreach ($images as $i => $img) :
                $url     = (string) ($img['url']     ?? '');
                $fullUrl = (string) ($img['fullUrl'] ?? $url);
                $alt     = (string) ($img['alt']     ?? '');
                $caption = (string) ($img['caption'] ?? '');
                if ($url === '') { continue; }
                $hasOverlay = $variant === 'count-3' && $i === 2 && $overflow > 0;
                ?>
                <figure
                    class="farnost-gallery__item<?php echo $hasOverlay ? ' has-overlay' : ''; ?>"
                    <?php if ($lightbox) : ?>data-farnost-lightbox="gallery" data-full-src="<?php echo esc_url($fullUrl); ?>"<?php endif; ?>
                    <?php if ($caption !== '') : ?>data-caption="<?php echo esc_attr(wp_strip_all_tags($caption)); ?>"<?php endif; ?>
                >
                    <img
                        class="farnost-gallery__img"
                        src="<?php echo esc_url($url); ?>"
                        alt="<?php echo esc_attr($alt); ?>"
                        loading="lazy"
                        decoding="async"
                    />
                    <?php if ($hasOverlay) : ?>
                        <span class="farnost-gallery__overlay" aria-label="<?php echo esc_attr(sprintf(__('+%d ďalších fotiek', 'farnost-plugin'), $overflow)); ?>">+<?php echo (int) $overflow; ?></span>
                    <?php endif; ?>
                    <?php if ($showCaptions && $caption !== '' && !$hasOverlay) : ?>
                        <figcaption class="farnost-gallery__caption"><?php echo wp_kses_post($caption); ?></figcaption>
                    <?php endif; ?>
                </figure>
            <?php endforeach; ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Public static — testable + zdieľané s `Feed::renderFeedGallery` aby
     * single source of truth určoval ako sa 3+ fotky zobrazia naprieč feed
     * a detail stránkou.
     */
    public static function variantFor(int $count): string
    {
        if ($count <= 1) {
            return 'count-1';
        }
        if ($count === 2) {
            return 'count-2';
        }
        return 'count-3';
    }
}
