<?php

declare(strict_types=1);

namespace Farnost\Plugin\Blocks;

use WP_Post;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Dynamic block `farnost/main-nav` — automaticky generuje hlavné menu z WP
 * Pages podľa parent/child hierarchie. Stránka sa zobrazí, len ak nemá
 * `farnost_show_in_menu` explicitne nastavené na false.
 *
 * Žiadny klasický WP Vzhľad → Menu — farár nemusí pamätať pridať novú stránku
 * do menu (doc/06-struktura-stranky.md:43-49).
 *
 * Mobile: ikona hamburgeru toggluje overlay menu so všetkými položkami
 * (riadené v assets/chrome.js + media query v style.css).
 */
final class MainNav
{
    public const NAME = 'farnost/main-nav';

    public static function register(): void
    {
        add_action('init', [self::class, 'registerBlock']);
    }

    public static function registerBlock(): void
    {
        register_block_type(self::NAME, [
            'api_version'     => 3,
            'render_callback' => [self::class, 'render'],
        ]);
    }

    public static function render(): string
    {
        $tree = self::buildTree();

        ob_start();
        ?>
        <nav class="site-nav" aria-label="<?php esc_attr_e('Hlavné menu', 'farnost-plugin'); ?>">
            <button class="site-nav-toggle" type="button" aria-expanded="false" aria-controls="farnost-nav-list" aria-label="<?php esc_attr_e('Otvoriť menu', 'farnost-plugin'); ?>">
                <span class="site-nav-toggle-bars" aria-hidden="true"><span></span><span></span><span></span></span>
                <span class="site-nav-toggle-label"><?php esc_html_e('Menu', 'farnost-plugin'); ?></span>
            </button>
            <ul id="farnost-nav-list" class="site-nav-list">
                <li class="site-nav-item<?php echo is_front_page() ? ' is-active' : ''; ?>">
                    <a class="site-nav-link" href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Domov', 'farnost-plugin'); ?></a>
                </li>
                <?php foreach ($tree as $node) : ?>
                    <?php echo self::renderNode($node); ?>
                <?php endforeach; ?>
            </ul>
        </nav>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * @return list<array{post: WP_Post, children: list<array{post: WP_Post, children: array}>}>
     */
    private static function buildTree(): array
    {
        $pages = get_pages([
            'post_status' => 'publish',
            'sort_column' => 'menu_order,post_title',
            'sort_order'  => 'ASC',
        ]);
        if (!is_array($pages)) {
            return [];
        }
        $visible = array_filter($pages, static function (WP_Post $p): bool {
            $flag = get_post_meta($p->ID, 'farnost_show_in_menu', true);
            // Default true — keď meta nikdy nebola zapísaná, ostáva v menu.
            return ($flag === '' || $flag === null) ? true : (bool) $flag;
        });

        $byParent = [];
        foreach ($visible as $p) {
            $byParent[(int) $p->post_parent][] = $p;
        }

        $build = static function (int $parentId) use (&$byParent, &$build): array {
            $out = [];
            foreach ($byParent[$parentId] ?? [] as $p) {
                $out[] = ['post' => $p, 'children' => $build((int) $p->ID)];
            }
            return $out;
        };
        return $build(0);
    }

    /**
     * @param array{post: WP_Post, children: array} $node
     */
    private static function renderNode(array $node, int $depth = 0): string
    {
        $post = $node['post'];
        $children = $node['children'] ?? [];
        $hasChildren = !empty($children);
        $isActive = is_page($post->ID) || self::isAncestorOfCurrent($post);
        $classes = ['site-nav-item'];
        if ($hasChildren) {
            $classes[] = 'has-children';
        }
        if ($isActive) {
            $classes[] = 'is-active';
        }

        ob_start();
        ?>
        <li class="<?php echo esc_attr(implode(' ', $classes)); ?>">
            <a class="site-nav-link" href="<?php echo esc_url(get_permalink($post)); ?>"><?php echo esc_html($post->post_title); ?><?php if ($hasChildren && $depth === 0) : ?> <svg class="site-nav-caret" width="9" height="6" viewBox="0 0 9 6" aria-hidden="true"><path d="M1 1l3.5 3.5L8 1" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg><?php endif; ?></a>
            <?php if ($hasChildren) : ?>
                <ul class="site-nav-submenu">
                    <?php foreach ($children as $child) : ?>
                        <li><a href="<?php echo esc_url(get_permalink($child['post'])); ?>"><?php echo esc_html($child['post']->post_title); ?></a></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </li>
        <?php
        return (string) ob_get_clean();
    }

    private static function isAncestorOfCurrent(WP_Post $post): bool
    {
        $current = get_queried_object();
        if (!$current instanceof WP_Post) {
            return false;
        }
        $ancestors = get_post_ancestors($current);
        return in_array($post->ID, $ancestors, true);
    }
}
