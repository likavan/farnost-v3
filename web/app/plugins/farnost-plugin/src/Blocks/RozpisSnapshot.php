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
                'tyzdenOd' => ['type' => 'string', 'default' => ''],
                'tyzdenDo' => ['type' => 'string', 'default' => ''],
                'dni'      => ['type' => 'array', 'default' => []],
            ],
        ]);
    }

    /**
     * Minimal frontend render — bude prekreslené v Etape 3 cez block patterns / theme.json.
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
                $label = self::DAY_LABELS[$day['dayKey'] ?? ''] ?? '';
                $omse  = isset($day['omse']) && is_array($day['omse']) ? $day['omse'] : [];
                ?>
                <div class="farnost-rozpis-snapshot__card">
                    <div class="farnost-rozpis-snapshot__header">
                        <strong><?php echo esc_html($label); ?></strong>
                        <?php if (!empty($day['date'])) : ?>
                            <span class="farnost-rozpis-snapshot__date"><?php echo esc_html((string) $day['date']); ?></span>
                        <?php endif; ?>
                        <?php if (!empty($day['sviatok'])) : ?>
                            <div class="farnost-rozpis-snapshot__sviatok"><?php echo esc_html((string) $day['sviatok']); ?></div>
                        <?php endif; ?>
                    </div>
                    <?php if (empty($omse)) : ?>
                        <div class="farnost-rozpis-snapshot__empty"><?php esc_html_e('Sv. omša nie je', 'farnost-plugin'); ?></div>
                    <?php else : ?>
                        <ul class="farnost-rozpis-snapshot__list">
                            <?php foreach ($omse as $m) : ?>
                                <li>
                                    <span class="farnost-rozpis-snapshot__time"><?php echo esc_html((string) ($m['time'] ?? '')); ?></span>
                                    <?php if (!empty($m['oznacenie'])) : ?>
                                        <span class="farnost-rozpis-snapshot__oznacenie"> · <?php echo esc_html((string) $m['oznacenie']); ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($m['umysel'])) : ?>
                                        <div class="farnost-rozpis-snapshot__umysel"><?php echo esc_html((string) $m['umysel']); ?></div>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }
}
