<?php

declare(strict_types=1);

namespace Farnost\Plugin\Blocks;

use Farnost\Plugin\MimoriadnyOznam\Banner as BannerData;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Dynamic block `farnost/banner` — vykresľuje mimoriadny oznam bar
 * z farnost_mimoriadny_oznam option. Ak option je prázdny alebo expirovaný,
 * blok nič nevyrenderuje (čisté DOM).
 */
final class Banner
{
    public const NAME = 'farnost/banner';

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
        $data = BannerData::get();
        if ($data === null) {
            return '';
        }

        ob_start();
        ?>
        <div class="farnost-alert" role="status" data-banner-id="<?php echo esc_attr($data['id']); ?>">
            <div class="farnost-alert-inner">
                <span class="farnost-alert-mark" aria-hidden="true">!</span>
                <span class="farnost-alert-eyebrow"><?php esc_html_e('Mimoriadny oznam', 'farnost-plugin'); ?></span>
                <span class="farnost-alert-sep" aria-hidden="true"></span>
                <span class="farnost-alert-text"><?php echo wp_kses_post($data['text']); ?></span>
                <button class="farnost-alert-close" type="button" aria-label="<?php esc_attr_e('Zavrieť oznam', 'farnost-plugin'); ?>">✕</button>
            </div>
        </div>
        <?php
        return (string) ob_get_clean();
    }
}
