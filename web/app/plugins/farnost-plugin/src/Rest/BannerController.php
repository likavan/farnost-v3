<?php

declare(strict_types=1);

namespace Farnost\Plugin\Rest;

use Farnost\Plugin\MimoriadnyOznam\Banner;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * GET /wp-json/farnost/v1/banner
 *
 * Vracia aktuálny mimoriadny oznam alebo `null`, ak žiadny nie je publikovaný
 * alebo expirovaný. Frontend banner sa naň pripojí v Etape 3.
 */
final class BannerController
{
    public function registerRoutes(): void
    {
        register_rest_route(FARNOST_REST_NAMESPACE, '/banner', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function get(WP_REST_Request $request): WP_REST_Response
    {
        return new WP_REST_Response(Banner::get(), 200);
    }
}
