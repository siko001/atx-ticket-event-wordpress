<?php
/**
 * HMAC signature verification for incoming Laravel webhooks.
 *
 * @package AtxDigitalTicketing
 */

namespace AtxDigitalTicketing\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Verifies the X-Atx-Ticketing-* signature headers.
 *
 * Contract (see ARCHITECTURE.md in the Laravel package):
 *   X-Atx-Ticketing-Timestamp: <unix timestamp>
 *   X-Atx-Ticketing-Signature: sha256=<hex hmac_sha256(secret, "{timestamp}.{raw body}")>
 */
final class Signature {

	private const TOLERANCE_SECONDS = 300;

	public static function verify( string $raw_body, string $timestamp, string $signature_header, string $secret ): bool {
		if ( '' === $secret || '' === $timestamp || '' === $signature_header ) {
			return false;
		}

		if ( ! preg_match( '/^\d+$/', $timestamp ) ) {
			return false;
		}

		if ( abs( time() - (int) $timestamp ) > self::TOLERANCE_SECONDS ) {
			return false;
		}

		if ( ! str_starts_with( $signature_header, 'sha256=' ) ) {
			return false;
		}

		$provided = substr( $signature_header, strlen( 'sha256=' ) );
		$expected = hash_hmac( 'sha256', $timestamp . '.' . $raw_body, $secret );

		return hash_equals( $expected, $provided );
	}

	/**
	 * Signature headers for outgoing WP → Laravel requests (same contract,
	 * other direction). Body is empty for the GET endpoints.
	 *
	 * @return array<string, string>
	 */
	public static function headers( string $secret, string $body = '' ): array {
		$timestamp = (string) time();

		return [
			'X-Atx-Ticketing-Timestamp' => $timestamp,
			'X-Atx-Ticketing-Signature' => 'sha256=' . hash_hmac( 'sha256', $timestamp . '.' . $body, $secret ),
			'Accept'                    => 'application/json',
		];
	}
}
