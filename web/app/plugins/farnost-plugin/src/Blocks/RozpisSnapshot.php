<?php

declare(strict_types=1);

namespace Farnost\Plugin\Blocks;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Server-side registrácia bloku `farnost/rozpis-snapshot` (dynamic block).
 *
 * JS strana (`editor/block-rozpis-snapshot`) poskytuje edit/save UI v editore.
 * Save() vracia `null`, takže blok je dynamický a frontend ho rendruje cez
 * render callback nižšie.
 *
 * Vlastný front-end vzhľad (karty per deň) dorobíme v Etape 3 v rámci block themy.
 */
final class RozpisSnapshot
{
    public const NAME = 'farnost/rozpis-snapshot';

    private const DAY_LABELS = [
        'mon' => 'Pondelok',
        'tue' => 'Utorok',
        'wed' => 'Streda',
        'thu' => 'Štvrtok',
        'fri' => 'Piatok',
        'sat' => 'Sobota',
        'sun' => 'Nedeľa',
    ];

    private const MONTH_LABELS = [
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
            'editor_script'   => 'farnost-block-rozpis-snapshot',
            'render_callback' => [self::class, 'render'],
            'attributes'      => [
                'tyzdenOd'   => ['type' => 'string', 'default' => ''],
                'tyzdenDo'   => ['type' => 'string', 'default' => ''],
                'dni'        => ['type' => 'array', 'default' => []],
                'snapshotAt' => ['type' => 'string', 'default' => ''],
            ],
        ]);
    }

    /**
     * Editorial bulletin layout — per deň hlavička, kostoly s kompaktným zoznamom
     * časov a úmyslov. Kostol farba (farnost_color) ide ako vertical accent border.
     *
     * Podporuje 2 dátové shape:
     *  - Nový (SnapshotBuilder od mája 2026): $day['kostoly'][] = {id, title, color, omse}
     *  - Legacy (staršie uložené oznamy): $day['omse'][] = {kostol_title, time, oznacenie, umysel, source}
     *
     * @param array<string, mixed> $attributes
     */
    public static function render(array $attributes): string
    {
        $dni = isset($attributes['dni']) && is_array($attributes['dni']) ? $attributes['dni'] : [];
        if (empty($dni)) {
            return '';
        }

        ob_start();
        ?>
        <div class="wp-block-farnost-rozpis-snapshot farnost-rozpis-snapshot">
            <?php foreach ($dni as $day) :
                $dayLabel = self::DAY_LABELS[$day['dayKey'] ?? ''] ?? '';
                $shortDate = self::formatShortDate((string) ($day['date'] ?? ''));
                $kostoly = self::resolveKostoly($day);
                ?>
                <article class="farnost-rozpis-snapshot__day<?php echo empty($kostoly) ? ' farnost-rozpis-snapshot__day--empty' : ''; ?>">
                    <header class="farnost-rozpis-snapshot__day-head">
                        <span class="farnost-rozpis-snapshot__day-name"><?php echo esc_html($dayLabel); ?></span>
                        <?php if ($shortDate !== '') : ?>
                            <time class="farnost-rozpis-snapshot__day-date" datetime="<?php echo esc_attr((string) ($day['date'] ?? '')); ?>"><?php echo esc_html($shortDate); ?></time>
                        <?php endif; ?>
                        <?php if (!empty($day['sviatok'])) : ?>
                            <span class="farnost-rozpis-snapshot__sviatok"><?php echo esc_html((string) $day['sviatok']); ?></span>
                        <?php endif; ?>
                    </header>
                    <?php if (empty($kostoly)) : ?>
                        <p class="farnost-rozpis-snapshot__empty"><?php esc_html_e('Sv. omša nie je', 'farnost-plugin'); ?></p>
                    <?php else : ?>
                        <ul class="farnost-rozpis-snapshot__kostoly">
                            <?php foreach ($kostoly as $k) :
                                $hasIntents = false;
                                foreach ($k['omse'] as $m) {
                                    if (trim((string) ($m['umysel'] ?? '')) !== '') { $hasIntents = true; break; }
                                }
                                $color = self::sanitizeHexColor((string) ($k['color'] ?? ''));
                                $styleAttr = $color !== '' ? ' style="--frs-kostol-color: ' . esc_attr($color) . '"' : '';
                                ?>
                                <li class="farnost-rozpis-snapshot__kostol"<?php echo $styleAttr; ?>>
                                    <div class="farnost-rozpis-snapshot__kostol-row">
                                        <?php if (count($kostoly) > 1) : ?>
                                            <span class="farnost-rozpis-snapshot__kostol-name"><?php echo esc_html((string) ($k['title'] ?? '')); ?></span>
                                        <?php endif; ?>
                                        <span class="farnost-rozpis-snapshot__times">
                                            <?php foreach ($k['omse'] as $i => $m) :
                                                $cas = (string) ($m['cas'] ?? '');
                                                $oznacenie = trim((string) ($m['oznacenie'] ?? ''));
                                                $isVynimka = ($m['source'] ?? '') === 'vynimka';
                                                if ($cas === '') { continue; }
                                                ?>
                                                <span class="farnost-rozpis-snapshot__time<?php echo $isVynimka ? ' is-vynimka' : ''; ?>"><?php echo esc_html($cas); ?><?php if ($oznacenie !== '') : ?> <em class="farnost-rozpis-snapshot__oznacenie"><?php echo esc_html($oznacenie); ?></em><?php endif; ?></span>
                                            <?php endforeach; ?>
                                        </span>
                                    </div>
                                    <?php if ($hasIntents) : ?>
                                        <ul class="farnost-rozpis-snapshot__intents">
                                            <?php foreach ($k['omse'] as $m) :
                                                $umysel = trim((string) ($m['umysel'] ?? ''));
                                                if ($umysel === '') { continue; }
                                                $cas = (string) ($m['cas'] ?? '');
                                                ?>
                                                <li>
                                                    <?php if ($cas !== '') : ?>
                                                        <span class="farnost-rozpis-snapshot__intent-time"><?php echo esc_html($cas); ?></span>
                                                    <?php endif; ?>
                                                    <span class="farnost-rozpis-snapshot__intent-text"><?php echo esc_html($umysel); ?></span>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Normalizuje deň na zoznam kostolov bez ohľadu na shape (new/legacy).
     *
     * @param array<string, mixed> $day
     * @return list<array{id: int, title: string, color: string, omse: list<array<string, mixed>>}>
     */
    private static function resolveKostoly(array $day): array
    {
        // Nový shape: priame `kostoly` pole z SnapshotBuilder.
        if (!empty($day['kostoly']) && is_array($day['kostoly'])) {
            $out = [];
            foreach ($day['kostoly'] as $k) {
                if (!is_array($k)) { continue; }
                $omse = isset($k['omse']) && is_array($k['omse']) ? $k['omse'] : [];
                if (empty($omse)) { continue; }
                $out[] = [
                    'id'    => (int) ($k['id'] ?? 0),
                    'title' => (string) ($k['title'] ?? ''),
                    'color' => (string) ($k['color'] ?? ''),
                    'omse'  => array_values($omse),
                ];
            }
            return $out;
        }
        // Legacy shape: flat `omse` zoznam, grupujeme podľa kostol_title.
        if (!empty($day['omse']) && is_array($day['omse'])) {
            $byTitle = [];
            foreach ($day['omse'] as $m) {
                if (!is_array($m)) { continue; }
                $title = (string) ($m['kostol_title'] ?? '');
                if (!isset($byTitle[$title])) {
                    $byTitle[$title] = ['id' => 0, 'title' => $title, 'color' => '', 'omse' => []];
                }
                $byTitle[$title]['omse'][] = [
                    'cas'       => (string) ($m['cas']  ?? $m['time'] ?? ''),
                    'oznacenie' => (string) ($m['oznacenie'] ?? ''),
                    'umysel'    => (string) ($m['umysel']    ?? ''),
                    'source'    => (string) ($m['source']    ?? 'rozpis'),
                ];
            }
            return array_values($byTitle);
        }
        return [];
    }

    private static function formatShortDate(string $iso): string
    {
        if ($iso === '') {
            return '';
        }
        $ts = strtotime($iso . ' 12:00:00');
        if ($ts === false) {
            return '';
        }
        $day = (int) date('j', $ts);
        $month = self::MONTH_LABELS[(int) date('n', $ts)] ?? '';
        return sprintf('%d. %s', $day, $month);
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
}
