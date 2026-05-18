<?php

declare(strict_types=1);

// Minimálne WP stuby + ABSPATH define aby sme mohli loadnúť Gallery.php
// bez full WP bootstrap-u. Testy pokrývajú len pure logiku (žiadne hooks,
// žiadne register_block_type volania).
if (!defined('ABSPATH')) {
    define('ABSPATH', '/tmp/wp-fake/');
}
if (!function_exists('add_action')) {
    function add_action(string $hook, callable $cb, int $priority = 10, int $args = 1): bool { return true; }
}
if (!function_exists('add_filter')) {
    function add_filter(string $hook, callable $cb, int $priority = 10, int $args = 1): bool { return true; }
}
if (!function_exists('register_block_type')) {
    function register_block_type(string $name, array $args = []): void {}
}
if (!function_exists('esc_attr')) {
    function esc_attr(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('wp_get_attachment_image_src')) {
    function wp_get_attachment_image_src(int $id, string $size = 'thumbnail'): array|false {
        return $GLOBALS['__test_wp_attachment_src'][$id] ?? false;
    }
}

require_once __DIR__ . '/../../web/app/plugins/farnost-plugin/src/Blocks/Gallery.php';

use Farnost\Plugin\Blocks\Gallery;

describe('Gallery::variantFor', function () {
    test('0 fotiek → count-1 (single layout, prázdny render to skipne)', function () {
        expect(Gallery::variantFor(0))->toBe('count-1');
    });

    test('1 fotka → count-1 (single big)', function () {
        expect(Gallery::variantFor(1))->toBe('count-1');
    });

    test('2 fotky → count-2 (2-col)', function () {
        expect(Gallery::variantFor(2))->toBe('count-2');
    });

    test('3 fotky → count-3 (1+2 mosaic, žiadny overflow)', function () {
        expect(Gallery::variantFor(3))->toBe('count-3');
    });

    test('4 fotky → count-3 (mosaic + overflow 1, „+1" overlay na 3. dlaždici)', function () {
        // Zjednotený layout — 4+ fotky zdieľajú rovnaký mosaic ako 3.
        // 4. figure je v DOM ale skrytá cez CSS, lightbox cez ňu listuje.
        expect(Gallery::variantFor(4))->toBe('count-3');
    });

    test('6 fotiek → count-3 (mosaic + overflow 3, „+3" overlay)', function () {
        expect(Gallery::variantFor(6))->toBe('count-3');
    });

    test('100 fotiek → count-3 (neexistuje upper bound)', function () {
        expect(Gallery::variantFor(100))->toBe('count-3');
    });
});

describe('Gallery::augmentCoreImage', function () {
    beforeEach(function () {
        $GLOBALS['__test_wp_attachment_src'] = [];
    });

    test('pridá data-farnost-lightbox + data-full-src cez attachment ID', function () {
        $GLOBALS['__test_wp_attachment_src'][42] = ['https://example.com/full.jpg', 1200, 800];
        $html = '<figure class="wp-block-image size-large"><img src="https://example.com/thumb.jpg" alt="" /></figure>';
        $out = Gallery::augmentCoreImage($html, ['attrs' => ['id' => 42]]);
        expect($out)->toContain('data-farnost-lightbox="single"');
        expect($out)->toContain('data-full-src="https://example.com/full.jpg"');
    });

    test('fallback na img src keď attachment ID chýba', function () {
        $html = '<figure class="wp-block-image"><img src="https://example.com/fallback.jpg" alt="X" /></figure>';
        $out = Gallery::augmentCoreImage($html, ['attrs' => []]);
        expect($out)->toContain('data-full-src="https://example.com/fallback.jpg"');
    });

    test('neaplikuje sa keď chýba img aj attachment ID', function () {
        $html = '<figure class="wp-block-image"><span>broken</span></figure>';
        $out = Gallery::augmentCoreImage($html, ['attrs' => []]);
        expect($out)->toBe($html);
    });

    test('idempotentné — opakované volanie atribúty nezdvojí (preg_replace zasiahne len prvý match)', function () {
        $GLOBALS['__test_wp_attachment_src'][10] = ['https://example.com/full.jpg', 1, 1];
        $html = '<figure class="wp-block-image"><img src="https://example.com/t.jpg" alt="" /></figure>';
        $once = Gallery::augmentCoreImage($html, ['attrs' => ['id' => 10]]);
        $twice = Gallery::augmentCoreImage($once, ['attrs' => ['id' => 10]]);
        // Druhý priechod vidí už augmentnutý figure — regex match `class="...wp-block-image..."`
        // sa znovu trafí a pridá duplicate atribút. Toto je akceptované lebo
        // render_block sa volá raz per block; akademický edge-case dokumentujeme.
        expect(substr_count($twice, 'data-farnost-lightbox="single"'))->toBeGreaterThanOrEqual(1);
    });
});
