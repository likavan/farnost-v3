<?php

declare(strict_types=1);

namespace Farnost\Plugin\Rest;

use Farnost\Plugin\Settings\Settings;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * GET /wp-json/farnost/v1/settings — verejné nastavenia farnosti (kontakt, IBAN,
 * sociálne, branding atď.). Dáta sú beztak verejné (zobrazujú sa v päte webu),
 * preto endpoint neautorizuje.
 */
final class SettingsController
{
    public function registerRoutes(): void
    {
        register_rest_route(FARNOST_REST_NAMESPACE, '/settings', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function get(WP_REST_Request $request): WP_REST_Response
    {
        return new WP_REST_Response(Settings::get(), 200);
    }
}
