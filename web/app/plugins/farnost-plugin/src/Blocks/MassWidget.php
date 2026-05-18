<?php

declare(strict_types=1);

namespace Farnost\Plugin\Blocks;

use DateTimeImmutable;
use Farnost\Plugin\PostTypes\Kostol;
use Farnost\Plugin\Schedule\RozpisReader;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Dynamic block `farnost/mass-widget` — bočný stĺpec widget s rozpisom omší
 * tento týždeň pre všetky kostoly (hlavný kostol prvý, ostatné podľa menu_order).
 *
 * Pre každý kostol: 7-dňový týždeň (Pon–Ned) s časmi z Resolver-a (zlúči
 * pravidelný rozpis + výnimky pre konkrétny dátum).
 */
final class MassWidget
{
    public const NAME = 'farnost/mass-widget';

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
        ]);
    }

    public static function render(): string
    {
        $kostoly = self::loadKostoly();
        if (empty($kostoly)) {
            return '';
        }

        $weekStart = self::weekStart();
        $today = (new DateTimeImmutable('now', wp_timezone()))->format('Y-m-d');

        ob_start();
        ?>
        <section class="farnost-widget">
            <h3 class="farnost-widget-title"><?php esc_html_e('Bohoslužby tento týždeň', 'farnost-plugin'); ?></h3>
            <?php foreach ($kostoly as $k) : ?>
                <div class="farnost-kostol-section">
                    <?php if (count($kostoly) > 1) : ?>
                        <h4 class="farnost-kostol-section-title"><?php echo esc_html($k['title']); ?></h4>
                    <?php endif; ?>
                    <ul class="farnost-mass-list">
                        <?php for ($i = 0; $i < 7; $i++) :
                            $day = $weekStart->modify("+{$i} day");
                            $iso = $day->format('Y-m-d');
                            $resolved = RozpisReader::forDate((int) $k['id'], $iso);
                            $name = self::SLOVAK_WEEKDAYS[(int) $day->format('N')] ?? '';
                            $isHighlight = $iso === $today;
                            $notes = self::buildNotes($resolved);
                        ?>
                            <li class="farnost-mass-row<?php echo $isHighlight ? ' is-highlight' : ''; ?>">
                                <div>
                                    <span class="farnost-mass-day-name"><?php echo esc_html($name); ?></span>
                                    <span class="farnost-mass-day-date"><?php echo esc_html(self::shortDate($day)); ?></span>
                                </div>
                                <div class="farnost-mass-times">
                                    <?php if (empty($resolved)) : ?>
                                        <span class="farnost-mass-time">—</span>
                                    <?php else : ?>
                                        <?php foreach ($resolved as $m) : ?>
                                            <span class="farnost-mass-time"><?php echo esc_html((string) ($m['cas'] ?? '')); ?></span>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                <?php foreach ($notes as $note) : ?>
                                    <div class="farnost-mass-note"><?php echo esc_html($note); ?></div>
                                <?php endforeach; ?>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </div>
            <?php endforeach; ?>
        </section>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Hlavný kostol prvý, ostatné podľa menu_order.
     *
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
        if (empty($posts)) {
            return [];
        }
        $main = [];
        $rest = [];
        foreach ($posts as $p) {
            $row = ['id' => (int) $p->ID, 'title' => (string) $p->post_title];
            $isHlavny = (bool) get_post_meta($p->ID, 'farnost_je_hlavny', true);
            if ($isHlavny) {
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
        $dow = (int) $today->format('N'); // 1=Mon..7=Sun
        return $today->modify('-' . ($dow - 1) . ' day');
    }

    private static function shortDate(DateTimeImmutable $d): string
    {
        return $d->format('j.') . ' ' . $d->format('n') . '.';
    }

    /**
     * Pre každú omšu s neprázdnym označením alebo úmyslom vyrobí jeden note
     * riadok vo formáte „HH:MM označenie — úmysel". Tým má sidebar užitočný
     * kontext (svadobná / pohrebná / Fatimská pobožnosť + meno úmyslu) bez
     * zaberania extra šírky.
     *
     * @param array<int, array{cas?: string, oznacenie?: string, umysel?: string, zdroj?: string}> $resolved
     * @return list<string>
     */
    private static function buildNotes(array $resolved): array
    {
        $notes = [];
        foreach ($resolved as $m) {
            $oznacenie = trim((string) ($m['oznacenie'] ?? ''));
            $umysel    = trim((string) ($m['umysel']    ?? ''));
            if ($oznacenie === '' && $umysel === '') {
                continue;
            }
            $cas = trim((string) ($m['cas'] ?? ''));
            $line = $cas;
            if ($oznacenie !== '') {
                $line .= ($line === '' ? '' : ' ') . $oznacenie;
            }
            if ($umysel !== '') {
                $line .= ($line === '' ? '' : ' — ') . $umysel;
            }
            $notes[] = trim($line);
        }
        return $notes;
    }
}
