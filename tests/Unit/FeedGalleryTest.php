<?php

declare(strict_types=1);

// Minimal WP stuby pre Feed::findFirstGalleryImages a Feed::formatEventWhen.
// `has_block` + `parse_blocks` musia vrátiť reálne tvary block tree-u —
// stuby napodobňujú core implementáciu pre náš subset (1 farnost/gallery
// blok, prípadne nested v core/group).
if (!defined('ABSPATH')) {
    define('ABSPATH', '/tmp/wp-fake/');
}

if (!function_exists('add_action')) {
    function add_action(string $h, callable $cb, int $p = 10, int $a = 1): bool { return true; }
}
if (!function_exists('add_filter')) {
    function add_filter(string $h, callable $cb, int $p = 10, int $a = 1): bool { return true; }
}
if (!function_exists('register_block_type')) {
    function register_block_type(string $n, array $a = []): void {}
}
if (!function_exists('esc_attr')) {
    function esc_attr(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('esc_url')) {
    function esc_url(string $s): string { return $s; }
}
if (!function_exists('esc_html__')) {
    function esc_html__(string $s, string $d = ''): string { return $s; }
}
if (!function_exists('__')) {
    function __(string $s, string $d = ''): string { return $s; }
}
if (!function_exists('has_block')) {
    function has_block(string $name, string $content): bool {
        return str_contains($content, '<!-- wp:' . $name . ' ')
            || str_contains($content, '<!-- wp:' . $name . ' /-->');
    }
}
if (!function_exists('parse_blocks')) {
    function parse_blocks(string $content): array {
        // Veľmi zjednodušený parser pre náš testovací subset:
        // <!-- wp:NAME ATTRS_JSON /--> alebo wrapping cez <!-- wp:NAME -->...<!-- /wp:NAME -->.
        // Pre core/group rekurzívne extrahuje innerBlocks.
        $blocks = [];
        $offset = 0;
        $len = strlen($content);
        while ($offset < $len) {
            $start = strpos($content, '<!-- wp:', $offset);
            if ($start === false) break;
            $tagEnd = strpos($content, '-->', $start);
            if ($tagEnd === false) break;
            $header = substr($content, $start + 8, $tagEnd - $start - 8);
            $selfClose = str_ends_with(trim($header), '/');
            $headerTrim = rtrim($header, ' /');
            // Rozdel meno bloku a JSON atribúty.
            $spacePos = strpos($headerTrim, ' ');
            if ($spacePos === false) {
                $name = trim($headerTrim);
                $attrs = [];
            } else {
                $name = trim(substr($headerTrim, 0, $spacePos));
                $attrsRaw = trim(substr($headerTrim, $spacePos + 1));
                $attrs = $attrsRaw !== '' ? (json_decode($attrsRaw, true) ?: []) : [];
            }
            $offset = $tagEnd + 3;
            if ($selfClose) {
                $blocks[] = ['blockName' => $name, 'attrs' => $attrs, 'innerBlocks' => [], 'innerHTML' => ''];
                continue;
            }
            // Hľadaj closer "<!-- /wp:NAME -->"
            $closer = '<!-- /wp:' . $name . ' -->';
            $closeStart = strpos($content, $closer, $offset);
            if ($closeStart === false) break;
            $innerRaw = substr($content, $offset, $closeStart - $offset);
            $innerBlocks = parse_blocks($innerRaw);
            $blocks[] = ['blockName' => $name, 'attrs' => $attrs, 'innerBlocks' => $innerBlocks, 'innerHTML' => $innerRaw];
            $offset = $closeStart + strlen($closer);
        }
        return $blocks;
    }
}
if (!function_exists('wp_timezone')) {
    function wp_timezone(): DateTimeZone {
        return new DateTimeZone('Europe/Bratislava');
    }
}
if (!function_exists('wp_date')) {
    function wp_date(string $format, int $ts): string {
        // Honit wp_timezone() — bez toho by sa UTC timestamp z parsed-v-CEST
        // hodnoty zobrazil ako UTC time (18:00 CEST = 16:00 UTC). V teste neover-
        // ujeme slovenskú lokalizáciu, len že parsing + TZ konverzia sedia.
        $dt = (new DateTimeImmutable('@' . $ts))->setTimezone(wp_timezone());
        return $dt->format($format);
    }
}

require_once __DIR__ . '/../../web/app/plugins/farnost-plugin/src/Blocks/Feed.php';

use Farnost\Plugin\Blocks\Feed;

describe('Feed::findFirstGalleryImages', function () {
    test('prázdny obsah → prázdne pole', function () {
        expect(Feed::findFirstGalleryImages(''))->toBe([]);
    });

    test('obsah bez gallery → prázdne pole', function () {
        $content = '<!-- wp:paragraph --><p>Lorem ipsum</p><!-- /wp:paragraph -->';
        expect(Feed::findFirstGalleryImages($content))->toBe([]);
    });

    test('top-level gallery → vráti images', function () {
        $attrs = ['images' => [['id' => 1, 'url' => 'a.jpg'], ['id' => 2, 'url' => 'b.jpg']]];
        $content = '<!-- wp:farnost/gallery ' . json_encode($attrs) . ' /-->';
        $found = Feed::findFirstGalleryImages($content);
        expect($found)->toHaveCount(2);
        expect($found[0]['url'])->toBe('a.jpg');
    });

    test('gallery nested v core/group → recursive find vráti images', function () {
        $attrs = ['images' => [['id' => 7, 'url' => 'nested.jpg']]];
        $content = '<!-- wp:group --><!-- wp:farnost/gallery ' . json_encode($attrs) . ' /--><!-- /wp:group -->';
        $found = Feed::findFirstGalleryImages($content);
        expect($found)->toHaveCount(1);
        expect($found[0]['url'])->toBe('nested.jpg');
    });

    test('viac gallery v obsahu → vráti tie z prvej', function () {
        $a = ['images' => [['id' => 1, 'url' => 'first.jpg']]];
        $b = ['images' => [['id' => 2, 'url' => 'second.jpg']]];
        $content = '<!-- wp:farnost/gallery ' . json_encode($a) . ' /-->'
                 . '<!-- wp:farnost/gallery ' . json_encode($b) . ' /-->';
        $found = Feed::findFirstGalleryImages($content);
        expect($found)->toHaveCount(1);
        expect($found[0]['url'])->toBe('first.jpg');
    });

    test('gallery s prázdnym images → pokračuje hľadať ďalej', function () {
        $empty = ['images' => []];
        $real  = ['images' => [['id' => 9, 'url' => 'real.jpg']]];
        $content = '<!-- wp:farnost/gallery ' . json_encode($empty) . ' /-->'
                 . '<!-- wp:farnost/gallery ' . json_encode($real) . ' /-->';
        $found = Feed::findFirstGalleryImages($content);
        expect($found)->toHaveCount(1);
        expect($found[0]['url'])->toBe('real.jpg');
    });
});

describe('Feed::formatEventWhen', function () {
    test('prázdny string → prázdny output', function () {
        expect(Feed::formatEventWhen(''))->toBe('');
    });

    test('Y-m-d H:i format → parsne a vyformatuje', function () {
        $out = Feed::formatEventWhen('2026-05-20 18:00');
        // Stub wp_date používa PHP date() → "20. May 2026, 18:00" (en).
        // Test overuje že parsing prešiel — výstup obsahuje rok, čas a deň.
        expect($out)->toContain('2026');
        expect($out)->toContain('18:00');
        expect($out)->toContain('20');
    });

    test('len Y-m-d → bez času, ale stále lokalizované', function () {
        $out = Feed::formatEventWhen('2026-05-20');
        expect($out)->toContain('2026');
        expect($out)->toContain('20');
        expect($out)->not->toContain(':');
    });

    test('ISO 8601 format → parsne', function () {
        $out = Feed::formatEventWhen('2026-05-20T18:30:00');
        expect($out)->toContain('2026');
        expect($out)->toContain('18:30');
    });

    test('legacy free-form text → vráti raw bez parsingu', function () {
        $out = Feed::formatEventWhen('Sobota 18:30');
        expect($out)->toBe('Sobota 18:30');
    });
});
