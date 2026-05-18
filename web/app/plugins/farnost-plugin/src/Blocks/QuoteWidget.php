<?php

declare(strict_types=1);

namespace Farnost\Plugin\Blocks;

use Farnost\Plugin\Settings\Settings;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Dynamic block `farnost/quote-widget` — bočný stĺpec widget s citátom zo
 * settings. Pri každom request vyberie deterministicky podľa dňa v roku
 * (stabilný rotujúci citát počas dňa, mení sa o polnoci).
 */
final class QuoteWidget
{
    public const NAME = 'farnost/quote-widget';

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
        $s = Settings::get();
        $citaty = is_array($s['citaty'] ?? null) ? $s['citaty'] : [];
        // Filter platné citáty.
        $citaty = array_values(array_filter($citaty, static fn($c): bool =>
            is_array($c) && isset($c['text']) && is_string($c['text']) && trim($c['text']) !== ''
        ));

        // Žiadne fallback citáty — ak admin nemá nič nastavené, widget sa nezobrazí.
        if (empty($citaty)) {
            return '';
        }

        $dayOfYear = (int) current_datetime()->format('z');
        $idx = $dayOfYear % count($citaty);
        $c = $citaty[$idx];
        $text = (string) ($c['text'] ?? '');
        $autor = (string) ($c['autor'] ?? '');

        ob_start();
        ?>
        <section class="farnost-widget farnost-widget-quote">
            <p class="farnost-quote-text">„<?php echo esc_html($text); ?>“</p>
            <?php if ($autor !== '') : ?>
                <p class="farnost-quote-source">— <?php echo esc_html($autor); ?></p>
            <?php endif; ?>
        </section>
        <?php
        return (string) ob_get_clean();
    }
}
