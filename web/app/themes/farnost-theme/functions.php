<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

add_action('after_setup_theme', static function (): void {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('html5', ['search-form', 'navigation-widgets', 'style', 'script']);
    add_theme_support('responsive-embeds');
    add_theme_support('editor-styles');
    add_editor_style('style.css');
});

add_action('init', static function (): void {
    register_block_pattern_category('farnost-pages', [
        'label' => __('Farnosť — stránky', 'farnost-theme'),
    ]);
});

add_action('wp_enqueue_scripts', static function (): void {
    wp_enqueue_style(
        'farnost-fonts',
        'https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;0,700;1,400;1,500&family=Source+Serif+4:opsz,wght@8..60,400;8..60,500;8..60,600;8..60,700;ital,opsz,wght@1,8..60,400&display=swap',
        [],
        null
    );
    wp_enqueue_style(
        'farnost-theme',
        get_stylesheet_uri(),
        ['farnost-fonts'],
        wp_get_theme()->get('Version')
    );
    wp_enqueue_script(
        'farnost-chrome',
        get_theme_file_uri('assets/chrome.js'),
        [],
        wp_get_theme()->get('Version'),
        ['strategy' => 'defer']
    );
});
