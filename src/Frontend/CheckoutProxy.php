<?php
/**
 * Server-side proxy to the Laravel checkout endpoint.
 *
 * @package AtxDigitalTicketing
 */

namespace AtxDigitalTicketing\Frontend;

use AtxDigitalTicketing\Plugin;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * POST /wp-json/atx-ticketing/v1/checkout
 *
 * The browser posts the ticket selection here; WordPress forwards it to the
 * Laravel checkout API server-side (so no secrets or internal URLs are
 * exposed client-side) and returns the Stripe Checkout URL.
 */
final class CheckoutProxy {

	public static function register_routes(): void {
		register_rest_route(
			'atx-ticketing/v1',
			'/checkout',
			[
				'methods'             => 'POST',
				'callback'            => [ self::class, 'handle' ],
				// Public visitors buy tickets; CSRF is covered by the REST
				// nonce sent from our own front-end script.
				'permission_callback' => static function ( WP_REST_Request $request ): bool {
					return (bool) wp_verify_nonce( (string) $request->get_header( 'X-WP-Nonce' ), 'wp_rest' );
				},
			]
		);
	}

	public static function handle( WP_REST_Request $request ): WP_REST_Response {
		$settings = Plugin::settings();
		$base_url = untrailingslashit( $settings['api_base_url'] );

		if ( '' === $base_url ) {
			return new WP_REST_Response(
				[ 'message' => __( 'Ticketing is not configured yet. Set the API base URL in Settings → ATX Ticketing.', 'atx-digital-ticketing-connect' ) ],
				500
			);
		}

		$body     = $request->get_json_params();
		$event_id = isset( $body['event_id'] ) ? absint( $body['event_id'] ) : 0;

		if ( $event_id <= 0 ) {
			return new WP_REST_Response( [ 'message' => __( 'Missing event.', 'atx-digital-ticketing-connect' ) ], 422 );
		}

		$payload = [
			'occurrence_id' => isset( $body['occurrence_id'] ) ? absint( $body['occurrence_id'] ) : 0,
			'items'         => self::sanitize_items( $body['items'] ?? [] ),
			'purchaser'     => self::sanitize_purchaser( $body['purchaser'] ?? [] ),
			'answers'       => self::sanitize_answers( $body['answers'] ?? [] ),
			'discount_code' => isset( $body['discount_code'] ) && '' !== $body['discount_code']
				? sanitize_text_field( (string) $body['discount_code'] )
				: null,
			'success_url'   => self::page_url( (int) $settings['success_page_id'] ),
			'cancel_url'    => self::page_url( (int) $settings['cancel_page_id'] ),
		];

		$response = wp_remote_post(
			$base_url . '/api/ticketing/events/' . $event_id . '/checkout',
			[
				'timeout' => 20,
				'headers' => [
					'Content-Type' => 'application/json',
					'Accept'       => 'application/json',
				],
				'body'    => wp_json_encode( $payload ),
			]
		);

		if ( is_wp_error( $response ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( '[atx-ticketing] Checkout proxy failed: ' . $response->get_error_message() );

			return new WP_REST_Response(
				[ 'message' => __( 'Could not reach the ticketing service. Please try again shortly.', 'atx-digital-ticketing-connect' ) ],
				502
			);
		}

		$status  = (int) wp_remote_retrieve_response_code( $response );
		$decoded = json_decode( wp_remote_retrieve_body( $response ), true );

		return new WP_REST_Response( is_array( $decoded ) ? $decoded : [], $status );
	}

	/**
	 * @param mixed $items Raw items input.
	 * @return array<int, array{ticket_type_id: int, quantity: int}>
	 */
	private static function sanitize_items( $items ): array {
		$clean = [];

		foreach ( (array) $items as $item ) {
			$ticket_type_id = isset( $item['ticket_type_id'] ) ? absint( $item['ticket_type_id'] ) : 0;
			$quantity       = isset( $item['quantity'] ) ? absint( $item['quantity'] ) : 0;

			if ( $ticket_type_id > 0 && $quantity > 0 ) {
				$clean[] = [
					'ticket_type_id' => $ticket_type_id,
					'quantity'       => $quantity,
				];
			}
		}

		return $clean;
	}

	/**
	 * @param mixed $purchaser Raw purchaser input.
	 * @return array<string, string|null>
	 */
	private static function sanitize_purchaser( $purchaser ): array {
		$purchaser = (array) $purchaser;

		return [
			'name'         => sanitize_text_field( (string) ( $purchaser['name'] ?? '' ) ),
			'email'        => sanitize_email( (string) ( $purchaser['email'] ?? '' ) ),
			'phone'        => '' !== ( $purchaser['phone'] ?? '' ) ? sanitize_text_field( (string) $purchaser['phone'] ) : null,
			'organisation' => '' !== ( $purchaser['organisation'] ?? '' ) ? sanitize_text_field( (string) $purchaser['organisation'] ) : null,
			'country'      => '' !== ( $purchaser['country'] ?? '' ) ? sanitize_text_field( (string) $purchaser['country'] ) : null,
		];
	}

	/**
	 * @param mixed $answers Raw answers input keyed by question id.
	 * @return array<int, string>
	 */
	private static function sanitize_answers( $answers ): array {
		$clean = [];

		foreach ( (array) $answers as $question_id => $value ) {
			$question_id = absint( $question_id );

			if ( $question_id > 0 ) {
				$clean[ $question_id ] = sanitize_textarea_field( (string) $value );
			}
		}

		return $clean;
	}

	private static function page_url( int $page_id ): ?string {
		if ( $page_id <= 0 ) {
			return null;
		}

		$url = get_permalink( $page_id );

		return is_string( $url ) ? $url : null;
	}
}
