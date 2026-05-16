<?php

declare(strict_types=1);

namespace Farnost\Plugin\Admin;

use Farnost\Plugin\MimoriadnyOznam\Banner;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin obrazovka `Farnosť → Mimoriadny oznam`.
 *
 * Jeden formulár: rich text (cez wp_editor) + voliteľná expirácia + tlačidlá
 * Publikovať / Aktualizovať a Zrušiť banner.
 */
final class MimoriadnyOznamPage
{
    public const NONCE_ACTION = 'farnost_mimoriadny_save';

    public static function render(): void
    {
        if (!current_user_can('edit_posts')) {
            wp_die(esc_html__('Nedostatočné oprávnenia.', 'farnost-plugin'));
        }

        $saved   = false;
        $cleared = false;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            check_admin_referer(self::NONCE_ACTION);

            if (isset($_POST['fp_clear'])) {
                Banner::clear();
                $cleared = true;
            } else {
                $text   = isset($_POST['fp_text']) ? (string) wp_unslash($_POST['fp_text']) : '';
                $expiry = isset($_POST['fp_expiry']) ? sanitize_text_field((string) wp_unslash($_POST['fp_expiry'])) : '';
                Banner::save($text, $expiry !== '' ? $expiry : null);
                $saved = true;
            }
        }

        $current = Banner::get();

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Mimoriadny oznam', 'farnost-plugin'); ?></h1>

            <?php if ($saved) : ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e('Mimoriadny oznam bol publikovaný.', 'farnost-plugin'); ?></p>
                </div>
            <?php endif; ?>
            <?php if ($cleared) : ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e('Mimoriadny oznam bol zrušený.', 'farnost-plugin'); ?></p>
                </div>
            <?php endif; ?>

            <?php if ($current !== null) : ?>
                <div class="notice notice-info" style="padding:12px 16px;">
                    <p style="margin:0 0 8px;"><strong><?php esc_html_e('Práve publikované:', 'farnost-plugin'); ?></strong></p>
                    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:4px;padding:10px 14px;margin:0 0 8px;">
                        <?php echo wp_kses_post($current['text']); ?>
                    </div>
                    <?php if ($current['expiry'] !== null) : ?>
                        <p style="margin:0;color:#6b7280;font-size:13px;">
                            <?php
                            printf(
                                /* translators: %s = local date/time when banner expires */
                                esc_html__('Vyprší: %s', 'farnost-plugin'),
                                esc_html(self::formatExpiryForDisplay($current['expiry']))
                            );
                            ?>
                        </p>
                    <?php else : ?>
                        <p style="margin:0;color:#6b7280;font-size:13px;">
                            <?php esc_html_e('Bez expirácie — banner žije, kým ho ručne nezrušíte.', 'farnost-plugin'); ?>
                        </p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <form method="post" action="">
                <?php wp_nonce_field(self::NONCE_ACTION); ?>

                <h2><?php esc_html_e('Obsah', 'farnost-plugin'); ?></h2>
                <p class="description" style="margin-bottom:8px;">
                    <?php esc_html_e('Krátky text s formátovaním (tučné, kurzíva, odkaz, zoznam). Žiadne obrázky.', 'farnost-plugin'); ?>
                </p>
                <?php
                wp_editor(
                    $current['text'] ?? '',
                    'fp_text',
                    [
                        'textarea_name' => 'fp_text',
                        'media_buttons' => false,
                        'textarea_rows' => 5,
                        'teeny'         => true,
                        'tinymce'       => [
                            'toolbar1' => 'bold,italic,underline,link,unlink,bullist,numlist,undo,redo',
                            'toolbar2' => '',
                        ],
                        'quicktags' => true,
                    ]
                );
                ?>

                <h2 style="margin-top:24px;"><?php esc_html_e('Voliteľná expirácia', 'farnost-plugin'); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="fp_expiry"><?php esc_html_e('Skryť po dátume', 'farnost-plugin'); ?></label></th>
                        <td>
                            <input type="datetime-local" id="fp_expiry" name="fp_expiry" value="<?php echo esc_attr($current['expiry'] ?? ''); ?>">
                            <p class="description">
                                <?php esc_html_e('Ak je nastavená, banner sa po tomto čase automaticky skryje. Ak nie je, žije, kým ho ručne nezrušíte.', 'farnost-plugin'); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" class="button button-primary">
                        <?php
                        echo $current !== null
                            ? esc_html__('Aktualizovať banner', 'farnost-plugin')
                            : esc_html__('Publikovať banner', 'farnost-plugin');
                        ?>
                    </button>
                    <?php if ($current !== null) : ?>
                        <button type="submit" name="fp_clear" value="1" class="button button-secondary" style="margin-left:8px;color:#b32d2e;"
                                onclick="return confirm('<?php echo esc_js(__('Naozaj zrušiť aktuálny banner?', 'farnost-plugin')); ?>')">
                            <?php esc_html_e('Zrušiť banner', 'farnost-plugin'); ?>
                        </button>
                    <?php endif; ?>
                </p>
            </form>
        </div>
        <?php
    }

    private static function formatExpiryForDisplay(string $expiry): string
    {
        $dt = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $expiry, wp_timezone());
        if ($dt === false) {
            return $expiry;
        }
        return wp_date('j. F Y, H:i', $dt->getTimestamp()) ?: $expiry;
    }
}
