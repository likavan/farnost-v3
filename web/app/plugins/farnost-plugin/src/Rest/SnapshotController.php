<?php

declare(strict_types=1);

namespace Farnost\Plugin\Rest;

use Farnost\Plugin\Oznam\SnapshotBuilder;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * POST /wp-json/farnost/v1/snapshot/build
 *
 * Body: { tyzdenOd: 'YYYY-MM-DD', tyzdenDo: 'YYYY-MM-DD' }
 * Vracia: { dni: [...], snapshotAt: 'ISO datetime' }
 *
 * Použitie: tlačidlo „Obnoviť snapshot" v rozpis-snapshot bloku — zoberie
 * aktuálny stav rozpis omší + výnimiek + úmyslov a vráti čerstvé `dni`.
 */
final class SnapshotController
{
    public function registerRoutes(): void
    {
        register_rest_route(FARNOST_REST_NAMESPACE, '/snapshot/build', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'build'],
            'permission_callback' => static fn(): bool => current_user_can('edit_posts'),
            'args'                => [
                'tyzdenOd' => [
                    'type'              => 'string',
                    'required'          => true,
                    'validate_callback' => static fn($v): bool => is_string($v)
                        && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) === 1,
                ],
                'tyzdenDo' => [
                    'type'              => 'string',
                    'required'          => true,
                    'validate_callback' => static fn($v): bool => is_string($v)
                        && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) === 1,
                ],
            ],
        ]);
    }

    public function build(WP_REST_Request $request): WP_REST_Response
    {
        $od = (string) $request->get_param('tyzdenOd');
        $do = (string) $request->get_param('tyzdenDo');

        $dni = SnapshotBuilder::buildForWeek($od, $do);
        return new WP_REST_Response([
            'dni'        => $dni,
            'snapshotAt' => current_datetime()->format(DATE_ATOM),
        ], 200);
    }
}
