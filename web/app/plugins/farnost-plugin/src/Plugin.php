<?php

declare(strict_types=1);

namespace Farnost\Plugin;

use Farnost\Plugin\Admin\CommentsHide;
use Farnost\Plugin\Admin\Menu;
use Farnost\Plugin\Admin\PostRelabel;
use Farnost\Plugin\Meta\CategoryMeta;
use Farnost\Plugin\Meta\PostMeta;
use Farnost\Plugin\PostTypes\Kostol;
use Farnost\Plugin\PostTypes\OmsaVynimka;
use Farnost\Plugin\PostTypes\Oznam;
use Farnost\Plugin\PostTypes\Umysel;
use Farnost\Plugin\PostTypes\UpratovaciaSkupina;
use Farnost\Plugin\Rest\ScheduleController;
use Farnost\Plugin\Rest\SettingsController;
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
        add_action('init', [$this, 'registerPostTypes']);
        add_action('init', [$this, 'registerMeta']);
        add_action('init', [Settings::class, 'register']);
        add_action('rest_api_init', [$this, 'registerRest']);

        // Komentáre — odstavené naprieč admin aj frontend (hooks musia byť globálne).
        CommentsHide::register();

        if (is_admin()) {
            Menu::register();
            PostRelabel::register();
        }
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
    }
}
