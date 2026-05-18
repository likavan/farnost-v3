<?php

declare(strict_types=1);

namespace Farnost\Plugin\Blocks;

use DateTimeImmutable;
use Farnost\Plugin\PostTypes\Oznam;
use WP_Post;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Dynamic block `farnost/archive-list` — listing všetkých publish oznamov,
 * DESC, s dátumom + titulkom (linkne na single post). Pre stránku Oznamy archív.
 */
final class ArchiveList
{
    public const NAME = 'farnost/archive-list';

    private const WEEKDAYS = [
        1 => 'Pondelok', 2 => 'Utorok',  3 => 'Streda',  4 => 'Štvrtok',
        5 => 'Piatok',   6 => 'Sobota',  7 => 'Nedeľa',
    ];

    private const MONTHS = [
        1 => 'januára', 2 => 'februára', 3 => 'marca', 4 => 'apríla',
        5 => 'mája',    6 => 'júna',     7 => 'júla',  8 => 'augusta',
        9 => 'septembra', 10 => 'októbra', 11 => 'novembra', 12 => 'decembra',
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
        ]);
    }

    public static function render(): string
    {
        $posts = get_posts([
            'post_type'      => Oznam::POST_TYPE,
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'date',
            'order'          => 'DESC',
            'no_found_rows'  => true,
        ]);

        if (empty($posts)) {
            return '<p class="farnost-empty">' . esc_html__('Zatiaľ žiadne publikované oznamy.', 'farnost-plugin') . '</p>';
        }

        ob_start();
        ?>
        <ul class="farnost-archive-list">
            <?php foreach ($posts as $p) : ?>
                <li>
                    <a class="farnost-archive-link" href="<?php echo esc_url(get_permalink($p)); ?>">
                        <span class="farnost-archive-date"><?php echo esc_html(self::formatDate($p)); ?></span>
                        <span class="farnost-archive-title"><?php echo esc_html(get_the_title($p)); ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php
        return (string) ob_get_clean();
    }

    private static function formatDate(WP_Post $p): string
    {
        $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', (string) $p->post_date, wp_timezone());
        if ($dt === false) {
            return (string) $p->post_date;
        }
        $wd = self::WEEKDAYS[(int) $dt->format('N')] ?? '';
        $m  = self::MONTHS[(int) $dt->format('n')] ?? '';
        return sprintf('%s, %d. %s %s', $wd, (int) $dt->format('j'), $m, $dt->format('Y'));
    }
}
