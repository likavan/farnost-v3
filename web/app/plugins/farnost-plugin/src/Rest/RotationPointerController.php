<?php

declare(strict_types=1);

namespace Farnost\Plugin\Rest;

use Farnost\Plugin\Settings\Settings;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * POST /wp-json/farnost/v1/rotation-pointer
 *
 * Nastaví, ktorá upratovacia skupina je „na rade" — zapíše do
 * `farnost_settings.upratovanie.dalsia_skupina`. Body: { id: number }.
 *
 * Vlastný endpoint je tu preto, lebo zápis cez `/wp/v2/settings` by vyžadoval
 * `manage_options`, čo farský asistent nemá. Pointer je posun cyklu, nie
 * konfigurácia — `edit_posts` postačuje.
 */
final class RotationPointerController
{
    public function registerRoutes(): void
    {
        register_rest_route(FARNOST_REST_NAMESPACE, '/rotation-pointer', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'set'],
            'permission_callback' => [$this, 'permission'],
            'args'                => [
                'id' => [
                    'type'     => 'integer',
                    'required' => true,
                ],
            ],
        ]);
    }

    public function permission(): bool
    {
        return current_user_can('edit_posts');
    }

    public function set(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $id = (int) $request->get_param('id');
        if ($id < 0) {
            return new WP_Error('farnost_invalid_id', __('Neplatné ID skupiny.', 'farnost-plugin'), ['status' => 400]);
        }

        // Hodnota 0 = „pointer žiadny" (po vymazaní všetkých skupín). Inak validujeme existenciu.
        if ($id !== 0) {
            $post = get_post($id);
            if (!$post || $post->post_type !== 'upratovacia_skupina') {
                return new WP_Error('farnost_not_found', __('Skupina nenájdená.', 'farnost-plugin'), ['status' => 404]);
            }
        }

        $settings = Settings::get();
        $settings['upratovanie']['dalsia_skupina'] = $id;
        update_option(Settings::OPTION_KEY, $settings);

        return new WP_REST_Response(['ok' => true, 'id' => $id], 200);
    }
}
