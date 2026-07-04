<?php
/**
 * AJAX tools for the settings screen: test connection, sync, create pages.
 *
 * @package AtxDigitalTicketing
 */

namespace AtxDigitalTicketing\Admin;

use AtxDigitalTicketing\Plugin;
use AtxDigitalTicketing\Support\Logger;
use AtxDigitalTicketing\Support\Signature;
use AtxDigitalTicketing\Sync\EventUpserter;

defined( 'ABSPATH' ) || exit;

/**
 * Admin-only AJAX endpoints behind the atx_ticketing_tools nonce.
 */
final class Tools {

	/**
	 * Adopt the connection's test-mode flag reported by Laravel (pull-side
	 * reconciliation — Laravel is the source of truth on pulls).
	 *
	 * @param array<string, mixed> $body Response body.
	 */
	private static function adopt_test_mode( array $body ): void {
		if ( ! array_key_exists( 'test_mode', $body ) ) {
			return;
		}

		$settings = Plugin::settings();
		$incoming = ! empty( $body['test_mode'] ) ? 1 : 0;

		if ( (int) $settings['test_mode'] === $incoming ) {
			return;
		}

		Plugin::$suppress_mode_notify = true;
		$settings['test_mode']        = $incoming;
		update_option( 'atx_ticketing_settings', $settings );
		Plugin::$suppress_mode_notify = false;

		Logger::log( 'sync', sprintf( 'Adopted %s mode from the ATX admin.', $incoming ? 'TEST' : 'live' ), $incoming ? 'warning' : 'info' );
	}

	public const NONCE = 'atx_ticketing_tools';

	public static function register(): void {
		add_action( 'wp_ajax_atx_ticketing_test_connection', [ self::class, 'test_connection' ] );
		add_action( 'wp_ajax_atx_ticketing_sync', [ self::class, 'sync' ] );
		add_action( 'wp_ajax_atx_ticketing_create_pages', [ self::class, 'create_pages' ] );
		add_action( 'wp_ajax_atx_ticketing_clear_logs', [ self::class, 'clear_logs' ] );
	}

	private static function authorize(): void {
		if ( ! current_user_can( 'manage_options' ) || ! check_ajax_referer( self::NONCE, 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => __( 'Not allowed.', 'atx-digital-ticketing-connect' ) ], 403 );
		}
	}

	/**
	 * Signed GET to a Laravel wp/* endpoint.
	 *
	 * @return array{status: int, body: array<string, mixed>}|\WP_Error
	 */
	private static function signed_get( string $path ) {
		$settings = Plugin::settings();
		$base     = untrailingslashit( $settings['api_base_url'] );

		if ( '' === $base ) {
			return new \WP_Error( 'atx_not_configured', __( 'Set the Laravel API base URL first (and save).', 'atx-digital-ticketing-connect' ) );
		}

		if ( '' === $settings['webhook_secret'] ) {
			return new \WP_Error( 'atx_not_configured', __( 'Set the webhook shared secret first (and save).', 'atx-digital-ticketing-connect' ) );
		}

		$response = wp_remote_get(
			$base . '/api/ticketing/' . $path,
			[
				'timeout' => 30,
				'headers' => Signature::headers( $settings['webhook_secret'] ),
			]
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$decoded = json_decode( wp_remote_retrieve_body( $response ), true );

		return [
			'status' => (int) wp_remote_retrieve_response_code( $response ),
			'body'   => is_array( $decoded ) ? $decoded : [],
		];
	}

	public static function test_connection(): void {
		self::authorize();

		$result = self::signed_get( 'wp/ping' );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => __( 'Could not reach the Laravel app: ', 'atx-digital-ticketing-connect' ) . $result->get_error_message() ] );
		}

		if ( 401 === $result['status'] ) {
			wp_send_json_error( [ 'message' => __( 'Reached the Laravel app, but the shared secret does not match. Check TICKETING_WP_WEBHOOK_SECRET in the Laravel .env (then run php artisan config:clear).', 'atx-digital-ticketing-connect' ) ] );
		}

		if ( 503 === $result['status'] ) {
			wp_send_json_error( [ 'message' => (string) ( $result['body']['error'] ?? __( 'The Laravel side is not configured.', 'atx-digital-ticketing-connect' ) ) ] );
		}

		if ( 200 !== $result['status'] || empty( $result['body']['ok'] ) ) {
			wp_send_json_error(
				[
					'message' => sprintf(
						/* translators: %d: HTTP status code. */
						__( 'Unexpected response (HTTP %d). Is the API base URL correct?', 'atx-digital-ticketing-connect' ),
						$result['status']
					),
				]
			);
		}

		self::adopt_test_mode( $result['body'] );

		wp_send_json_success(
			[
				'message' => sprintf(
					/* translators: 1: Laravel app name, 2: number of published events. */
					__( 'Connected to "%1$s" — secret verified, %2$d published event(s) available.', 'atx-digital-ticketing-connect' ),
					(string) ( $result['body']['app'] ?? 'Laravel' ),
					(int) ( $result['body']['published_events'] ?? 0 )
				),
			]
		);
	}

	public static function sync(): void {
		self::authorize();

		$result = self::signed_get( 'wp/events' );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		}

		if ( 200 !== $result['status'] ) {
			wp_send_json_error(
				[
					'message' => sprintf(
						/* translators: %d: HTTP status code. */
						__( 'Sync failed (HTTP %d). Use "Test connection" to diagnose.', 'atx-digital-ticketing-connect' ),
						$result['status']
					),
				]
			);
		}

		self::adopt_test_mode( $result['body'] );

		$events   = is_array( $result['body']['events'] ?? null ) ? $result['body']['events'] : [];
		$upserter = new EventUpserter();
		$synced   = 0;
		$failed   = 0;

		foreach ( $events as $event ) {
			if ( ! is_array( $event ) ) {
				continue;
			}

			$applied = $upserter->apply( 'event.updated', $event );

			if ( is_wp_error( $applied ) ) {
				++$failed;
				continue;
			}

			++$synced;
		}

		Logger::log(
			'sync',
			sprintf( 'Manual sync pulled %1$d event(s) from Laravel%2$s.', $synced, $failed > 0 ? sprintf( ' (%d failed)', $failed ) : '' ),
			$failed > 0 ? 'warning' : 'info'
		);

		update_option(
			'atx_ticketing_last_sync',
			[
				'time'   => time(),
				'synced' => $synced,
				'failed' => $failed,
			]
		);

		wp_send_json_success(
			[
				'message' => sprintf(
					/* translators: 1: number synced, 2: number failed. */
					__( 'Synced %1$d event(s) from Laravel%2$s.', 'atx-digital-ticketing-connect' ),
					$synced,
					$failed > 0 ? sprintf( ' (%d failed)', $failed ) : ''
				),
			]
		);
	}

	/**
	 * Creates the default front-end pages and stores their IDs in settings.
	 */
	public static function create_pages(): void {
		self::authorize();

		$settings = Plugin::settings();
		$created  = [];

		$events_page_id = (int) get_option( 'atx_ticketing_events_page_id', 0 );

		if ( ! $events_page_id || 'page' !== get_post_type( $events_page_id ) || 'trash' === get_post_status( $events_page_id ) ) {
			$events_page_id = wp_insert_post(
				[
					'post_type'    => 'page',
					'post_status'  => 'publish',
					'post_title'   => __( 'Events', 'atx-digital-ticketing-connect' ),
					'post_content' => '[atx_events]',
				]
			);

			if ( ! is_wp_error( $events_page_id ) && $events_page_id ) {
				update_option( 'atx_ticketing_events_page_id', (int) $events_page_id );
				$created[] = __( 'Events', 'atx-digital-ticketing-connect' );
			}
		}

		if ( ! self::page_usable( (int) $settings['success_page_id'] ) ) {
			$success_id = wp_insert_post(
				[
					'post_type'    => 'page',
					'post_status'  => 'publish',
					'post_title'   => __( 'Thanks — see you there!', 'atx-digital-ticketing-connect' ),
					'post_content' => '<p>' . __( 'Your tickets are booked. Check your inbox — your tickets and a calendar invite are on their way.', 'atx-digital-ticketing-connect' ) . '</p>',
				]
			);

			if ( ! is_wp_error( $success_id ) && $success_id ) {
				$settings['success_page_id'] = (int) $success_id;
				$created[]                   = __( 'Checkout success', 'atx-digital-ticketing-connect' );
			}
		}

		if ( ! self::page_usable( (int) $settings['cancel_page_id'] ) ) {
			$cancel_id = wp_insert_post(
				[
					'post_type'    => 'page',
					'post_status'  => 'publish',
					'post_title'   => __( 'Checkout cancelled', 'atx-digital-ticketing-connect' ),
					'post_content' => '<p>' . __( 'No payment was taken. Your tickets were not booked — you can try again any time.', 'atx-digital-ticketing-connect' ) . '</p>',
				]
			);

			if ( ! is_wp_error( $cancel_id ) && $cancel_id ) {
				$settings['cancel_page_id'] = (int) $cancel_id;
				$created[]                  = __( 'Checkout cancelled', 'atx-digital-ticketing-connect' );
			}
		}

		update_option( 'atx_ticketing_settings', $settings );

		wp_send_json_success(
			[
				'message' => $created
					? sprintf(
						/* translators: %s: comma-separated list of created pages. */
						__( 'Created: %s. The checkout pages have been selected and saved automatically.', 'atx-digital-ticketing-connect' ),
						implode( ', ', $created )
					)
					: __( 'All default pages already exist — nothing to create.', 'atx-digital-ticketing-connect' ),
				'reload'  => (bool) $created,
			]
		);
	}

	private static function page_usable( int $page_id ): bool {
		return $page_id > 0 && 'page' === get_post_type( $page_id ) && 'trash' !== get_post_status( $page_id );
	}

	public static function clear_logs(): void {
		self::authorize();

		Logger::clear();

		wp_send_json_success(
			[
				'message' => __( 'Logs cleared.', 'atx-digital-ticketing-connect' ),
				'reload'  => true,
			]
		);
	}
}
