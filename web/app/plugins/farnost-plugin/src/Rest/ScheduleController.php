<?php

declare(strict_types=1);

namespace Farnost\Plugin\Rest;

use Farnost\Plugin\Schedule\RozpisReader;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * GET /wp-json/farnost/v1/schedule?date=YYYY-MM-DD&kostol_id=N
 *
 * Vracia kombinovaný rozpis omší pre daný kostol a deň (pravidelné omše + výnimky).
 */
final class ScheduleController
{
    public function registerRoutes(): void
    {
        register_rest_route(FARNOST_REST_NAMESPACE, '/schedule', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get'],
            'permission_callback' => '__return_true',
            'args'                => [
                'date'      => [
                    'type'              => 'string',
                    'required'          => true,
                    'validate_callback' => static fn($v): bool => is_string($v)
                        && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) === 1,
                ],
                'kostol_id' => [
                    'type'     => 'integer',
                    'required' => true,
                    'minimum'  => 1,
                ],
            ],
        ]);
    }

    public function get(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $date = (string) $request->get_param('date');
        $kostolId = (int) $request->get_param('kostol_id');

        $kostol = get_post($kostolId);
        if (!$kostol || $kostol->post_type !== 'kostol' || $kostol->post_status !== 'publish') {
            return new WP_Error('farnost_kostol_not_found', 'Kostol nenájdený.', ['status' => 404]);
        }

        $masses = RozpisReader::forDate($kostolId, $date);

        return new WP_REST_Response([
            'date'      => $date,
            'kostol_id' => $kostolId,
            'masses'    => $masses,
        ], 200);
    }
}
