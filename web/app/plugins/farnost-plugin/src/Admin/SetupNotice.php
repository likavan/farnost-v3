<?php

declare(strict_types=1);

namespace Farnost\Plugin\Admin;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin notice „Setup farnosti nie je dokončený" kým farár neprejde wizard.
 *
 * Zobrazuje sa všetkým s `manage_options` cap, na všetkých admin stránkach
 * okrem samotného wizardu. Mizne, keď je `farnost_settings.setup.completed = true`.
 */
final class SetupNotice
{
    public static function register(): void
    {
        add_action('admin_notices', [self::class, 'maybeRender']);
    }

    public static function maybeRender(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        if (WizardPage::isCompleted()) {
            return;
        }
        // V samotnom wizardu sa hláška neukazuje — má vlastný UI.
        if (isset($_GET['page']) && $_GET['page'] === WizardPage::SLUG) {
            return;
        }

        $url = esc_url(WizardPage::url());
        ?>
        <div class="notice notice-info" style="border-left-color:#1e40af;padding:14px 16px;">
            <p style="margin:0 0 6px;font-size:14px;">
                <strong><?php esc_html_e('Vitajte vo Farnosť Online!', 'farnost-plugin'); ?></strong>
                <?php esc_html_e('Pre rozbeh farského webu prejdite krátkym sprievodcom (cca 2 min).', 'farnost-plugin'); ?>
            </p>
            <p style="margin:0;">
                <a href="<?php echo $url; ?>" class="button button-primary">
                    <?php esc_html_e('Začať nastavenie', 'farnost-plugin'); ?>
                </a>
            </p>
        </div>
        <?php
    }
}
