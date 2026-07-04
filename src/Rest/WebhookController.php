<?php
/**
 * Incoming webhook endpoint (Laravel → WordPress).
 *
 * @package AtxDigitalTicketing
 */

namespace AtxDigitalTicketing\Rest;

use AtxDigitalTicketing\Plugin;
use AtxDigitalTicketing\Support\Logger;
use AtxDigitalTicketing\Support\Signature;
use AtxDigitalTicketing\Sync\EventUpserter;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * POST /wp-json/atx-ticketing/v1/webhook
 *
 * HMAC-verified receiver for event.published / updated / cancelled / deleted.
 */
final class WebhookController {

	public static function register_routes(): void {
		register_rest_route(
			'atx-ticketing/v1',
			'/webhook',
			[
				'methods'             => 'POST',
				'callback'            => [ self::class, 'handle' ],
				// Authentication is the HMAC signature, checked in handle().
				'permission_callback' => '__return_true',
			]
		);
	}

	public static function handle( WP_REST_Request $request ): WP_REST_Response {
		$raw       = $request->get_body();
		$timestamp = (string) $request->get_header( 'X-Atx-Ticketing-Timestamp' );
		$signature = (string) $request->get_header( 'X-Atx-Ticketing-Signature' );
		$secret    = Plugin::settings()['webhook_secret'];

		if ( ! Signature::verify( $raw, $timestamp, $signature, $secret ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( '[atx-ticketing] Rejected webhook with invalid signature from ' . self::client_ip() );

			Logger::log( 'sync', __( 'Rejected an incoming webhook: invalid signature.', 'atx-digital-ticketing-connect' ), 'error' );

			return new WP_REST_Response( [ 'error' => 'Invalid signature.' ], 401 );
		}

		$payload = json_decode( $raw, true );

		if ( ! is_array( $payload ) || empty( $payload['type'] ) || ! is_array( $payload['event'] ?? null ) ) {
			return new WP_REST_Response( [ 'error' => 'Malformed payload.' ], 422 );
		}

		$type = (string) $payload['type'];

		if ( 'connection.mode' === $type ) {
			$test_mode = ! empty( $payload['event']['test_mode'] );

			Plugin::$suppress_mode_notify = true;
			$settings                     = Plugin::settings();
			$settings['test_mode']        = $test_mode ? 1 : 0;
			update_option( 'atx_ticketing_settings', $settings );
			Plugin::$suppress_mode_notify = false;

			Logger::log(
				'sync',
				sprintf( 'ATX admin switched this site to %s mode.', $test_mode ? 'TEST' : 'live' ),
				$test_mode ? 'warning' : 'info'
			);

			return new WP_REST_Response(
				[
					'ok'        => true,
					'test_mode' => $test_mode,
				],
				200
			);
		}

		if ( ! in_array( $type, [ 'event.published', 'event.updated', 'event.cancelled', 'event.deleted' ], true ) ) {
			if ( 'connection.test' === $type ) {
				Logger::log( 'sync', __( 'Laravel tested the connection (signature OK).', 'atx-digital-ticketing-connect' ) );
			}

			return new WP_REST_Response( [ 'ignored' => $type ], 200 );
		}

		$result = ( new EventUpserter() )->apply( $type, $payload['event'] );

		if ( is_wp_error( $result ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( '[atx-ticketing] Webhook upsert failed: ' . $result->get_error_message() );
			Logger::log( 'sync', sprintf( 'Failed to apply %s: %s', $type, $result->get_error_message() ), 'error' );

			return new WP_REST_Response( [ 'error' => $result->get_error_message() ], 500 );
		}

		$event_title = (string) ( $payload['event']['title'] ?? ( '#' . ( $payload['event']['id'] ?? '?' ) ) );
		Logger::log( 'sync', sprintf( 'Received %1$s for "%2$s".', $type, $event_title ) );

		return new WP_REST_Response(
			[
				'ok'      => true,
				'type'    => $type,
				'post_id' => $result,
			],
			200
		);
	}

	private static function client_ip(): string {
		return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
	}
}
