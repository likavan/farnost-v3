<?php

declare(strict_types=1);

namespace Farnost\Plugin\Blocks;

use DateTimeImmutable;
use Farnost\Plugin\PostTypes\Kostol;
use Farnost\Plugin\Schedule\WeeklyResolver;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Dynamic block `farnost/schedule-table` — kompletný týždenný rozpis svätých
 * omší pre všetky publish kostoly (hlavný prvý). Použitý na stránke Bohoslužby.
 */
final class ScheduleTable
{
    public const NAME = 'farnost/schedule-table';

    private const WEEKDAYS = [
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
        ]);
    }

    public static function render(): string
    {
        $kostoly = self::loadKostoly();
        if (empty($kostoly)) {
            return '<p class="farnost-empty">' . esc_html__('Rozpis omší zatiaľ nie je dostupný.', 'farnost-plugin') . '</p>';
        }
        $weekStart = self::weekStart();
        $weekEnd = $weekStart->modify('+6 day');
        $kostolIds = array_map(static fn(array $k): int => (int) $k['id'], $kostoly);
        $weekIndex = WeeklyResolver::forWeek(
            $kostolIds,
            $weekStart->format('Y-m-d'),
            $weekEnd->format('Y-m-d')
        );

        ob_start();
        foreach ($kostoly as $k) :
            ?>
            <section class="farnost-schedule-section">
                <?php if (count($kostoly) > 1) : ?>
                    <h2 class="farnost-schedule-section-title"><?php echo esc_html($k['title']); ?></h2>
                <?php endif; ?>
                <table class="farnost-schedule-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Deň', 'farnost-plugin'); ?></th>
                            <th><?php esc_html_e('Časy', 'farnost-plugin'); ?></th>
                            <th><?php esc_html_e('Poznámka', 'farnost-plugin'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php for ($i = 0; $i < 7; $i++) :
                            $day = $weekStart->modify("+{$i} day");
                            $iso = $day->format('Y-m-d');
                            $resolved = $weekIndex[(int) $k['id']][$iso] ?? [];
                            $name = self::WEEKDAYS[(int) $day->format('N')] ?? '';
                            $note = self::extractNote($resolved);
                            $times = array_map(static fn(array $m): string => (string) ($m['cas'] ?? ''), $resolved);
                            ?>
                            <tr>
                                <td><strong><?php echo esc_html($name); ?></strong> <span class="muted"><?php echo esc_html($day->format('j') . '. ' . $day->format('n') . '.'); ?></span></td>
                                <td><?php echo empty($times) ? '<span class="muted">—</span>' : esc_html(implode(' · ', $times)); ?></td>
                                <td class="muted"><?php echo $note !== '' ? esc_html($note) : '—'; ?></td>
                            </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </section>
            <?php
        endforeach;
        return (string) ob_get_clean();
    }

    /**
     * @return list<array{id: int, title: string}>
     */
    private static function loadKostoly(): array
    {
        $posts = get_posts([
            'post_type'      => Kostol::POST_TYPE,
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'menu_order',
            'order'          => 'ASC',
            'no_found_rows'  => true,
        ]);
        $main = [];
        $rest = [];
        foreach ($posts as $p) {
            $row = ['id' => (int) $p->ID, 'title' => (string) $p->post_title];
            if ((bool) get_post_meta($p->ID, 'farnost_je_hlavny', true)) {
                $main[] = $row;
            } else {
                $rest[] = $row;
            }
        }
        return array_merge($main, $rest);
    }

    private static function weekStart(): DateTimeImmutable
    {
        $tz = wp_timezone();
        $today = new DateTimeImmutable('today', $tz);
        $dow = (int) $today->format('N');
        return $today->modify('-' . ($dow - 1) . ' day');
    }

    /**
     * @param array<int, array{cas?: string, oznacenie?: string, umysel?: string, zdroj?: string}> $resolved
     */
    private static function extractNote(array $resolved): string
    {
        foreach ($resolved as $m) {
            $o = (string) ($m['oznacenie'] ?? '');
            if ($o !== '') {
                return $o;
            }
        }
        return '';
    }
}
