<?php

declare(strict_types=1);

namespace Farnost\Plugin;

use Farnost\Plugin\Admin\BlockCategory;
use Farnost\Plugin\Admin\CategoryAdmin;
use Farnost\Plugin\Blocks\ArchiveList;
use Farnost\Plugin\Blocks\Banner as BannerBlock;
use Farnost\Plugin\Blocks\ContactWidget;
use Farnost\Plugin\Blocks\Feed;
use Farnost\Plugin\Blocks\MainNav;
use Farnost\Plugin\Blocks\MassWidget;
use Farnost\Plugin\Blocks\QuoteWidget;
use Farnost\Plugin\Blocks\RozpisSnapshot;
use Farnost\Plugin\Blocks\ScheduleTable;
use Farnost\Plugin\Blocks\SiteBrand;
use Farnost\Plugin\Blocks\SiteFooter;
use Farnost\Plugin\Oznam\BufferManager;
use Farnost\Plugin\Admin\CommentsHide;
use Farnost\Plugin\Admin\EditorAssets;
use Farnost\Plugin\Admin\Menu;
use Farnost\Plugin\Admin\PostRelabel;
use Farnost\Plugin\Admin\SetupNotice;
use Farnost\Plugin\Admin\WizardPage;
use Farnost\Plugin\Oznam\AutoTemplate;
use Farnost\Plugin\Oznam\Upratovanie;
use Farnost\Plugin\Meta\CategoryMeta;
use Farnost\Plugin\Meta\PostMeta;
use Farnost\Plugin\PostTypes\Kostol;
use Farnost\Plugin\PostTypes\OmsaVynimka;
use Farnost\Plugin\PostTypes\Oznam;
use Farnost\Plugin\PostTypes\Umysel;
use Farnost\Plugin\PostTypes\UpratovaciaSkupina;
use Farnost\Plugin\Rest\BannerController;
use Farnost\Plugin\Rest\RotationPointerController;
use Farnost\Plugin\Rest\ScheduleController;
use Farnost\Plugin\Rest\SettingsController;
use Farnost\Plugin\Rest\SnapshotController;
use Farnost\Plugin\Settings\Settings;

if (!defined('ABSPATH')) {
    exit;
}

final class Plugin
{
    private static ?Plugin $instance = null;

    public static function instance(): Plugin
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function boot(): void
    {
        add_action('init', [$this, 'loadTextDomain']);
        add_action('init', [$this, 'registerPostTypes']);
        add_action('init', [$this, 'registerMeta']);
        add_action('init', [Settings::class, 'register']);
        add_action('rest_api_init', [$this, 'registerRest']);

        // Komentáre — odstavené naprieč admin aj frontend (hooks musia byť globálne).
        CommentsHide::register();

        // Gutenberg block kategória + bloky + oznam workflow.
        BlockCategory::register();
        RozpisSnapshot::register();
        BannerBlock::register();
        Feed::register();
        MassWidget::register();
        ContactWidget::register();
        QuoteWidget::register();
        ScheduleTable::register();
        ArchiveList::register();
        MainNav::register();
        SiteBrand::register();
        SiteFooter::register();
        AutoTemplate::register();
        BufferManager::register();
        Upratovanie::register();

        if (is_admin()) {
            Menu::register();
            PostRelabel::register();
            EditorAssets::register();
            WizardPage::register();
            SetupNotice::register();
            CategoryAdmin::register();
        }
    }

    public function loadTextDomain(): void
    {
        load_plugin_textdomain(
            'farnost-plugin',
            false,
            dirname(plugin_basename(FARNOST_PLUGIN_FILE)) . '/languages'
        );
    }

    public function registerPostTypes(): void
    {
        Kostol::register();
        Oznam::register();
        OmsaVynimka::register();
        Umysel::register();
        UpratovaciaSkupina::register();
    }

    public function registerMeta(): void
    {
        Kostol::registerMeta();
        Oznam::registerMeta();
        OmsaVynimka::registerMeta();
        Umysel::registerMeta();
        UpratovaciaSkupina::registerMeta();
        PostMeta::register();
        CategoryMeta::register();
    }

    public function registerRest(): void
    {
        (new SettingsController())->registerRoutes();
        (new ScheduleController())->registerRoutes();
        (new BannerController())->registerRoutes();
        (new SnapshotController())->registerRoutes();
        (new RotationPointerController())->registerRoutes();
    }
}
