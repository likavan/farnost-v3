<?php

declare(strict_types=1);

namespace Farnost\Plugin\Blocks;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Dynamic block `farnost/site-header` — kompletná hlavička webu v jednom
 * bloku, analóg s farnost/site-footer. Skladá brand + search + main-nav +
 * banner, takže `parts/header.html` obsahuje len `<!-- wp:farnost/site-header /-->`.
 *
 * Výhody:
 * - Single point of truth — žiadne HTML cookbookov v template parts
 * - Site Editor preview (cez ServerSideRender) ukazuje plnú hlavičku
 * - User nemôže omylom rozbiť layout (kríž, brand, search wrapper)
 */
final class SiteHeader
{
    public const NAME = 'farnost/site-header';

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
        ob_start();
        ?>
        <div class="site-header-inner">
            <div class="site-header-top">
                <?php echo SiteBrand::render(); ?>
                <div class="site-header-utility">
                    <form class="farnost-search" role="search" method="get" action="<?php echo esc_url(home_url('/')); ?>">
                        <button type="submit" class="farnost-search-trigger" aria-label="<?php esc_attr_e('Hľadať', 'farnost-plugin'); ?>">
                            <svg width="18" height="18" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                <circle cx="8.5" cy="8.5" r="5.5" stroke="currentColor" stroke-width="1.6"/>
                                <line x1="12.6" y1="12.6" x2="17" y2="17" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                            </svg>
                        </button>
                        <input class="farnost-search-field" type="search" name="s" placeholder="<?php esc_attr_e('Hľadať v oznamoch…', 'farnost-plugin'); ?>" aria-label="<?php esc_attr_e('Hľadať', 'farnost-plugin'); ?>" aria-hidden="true" tabindex="-1" />
                    </form>
                </div>
            </div>
        </div>
        <?php echo MainNav::render(); ?>
        <?php echo Banner::render(); ?>
        <?php
        return (string) ob_get_clean();
    }
}
