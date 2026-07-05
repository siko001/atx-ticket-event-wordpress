<?php
/**
 * Idempotent upsert of webhook payloads into the CPT mirror.
 *
 * @package AtxDigitalTicketing
 */

namespace AtxDigitalTicketing\Sync;

use AtxDigitalTicketing\PostTypes\EventPostType;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Applies event.published / event.updated / event.cancelled / event.deleted
 * payloads to the local atx_event mirror. Keyed on _atx_event_id, so repeated
 * deliveries of the same payload are harmless.
 */
final class EventUpserter {

	/**
	 * @param array<string, mixed> $event Payload "event" object.
	 * @return int|WP_Error Post ID, 0 for no-op deletes, or WP_Error.
	 */
	public function apply( string $type, array $event ) {
		$event_id = (int) ( $event['id'] ?? 0 );

		if ( $event_id <= 0 ) {
			return new WP_Error( 'atx_invalid_payload', 'Missing event id.' );
		}

		$existing = EventPostType::find_by_event_id( $event_id );

		if ( 'event.deleted' === $type ) {
			if ( $existing instanceof \WP_Post ) {
				wp_trash_post( $existing->ID );
			}

			return $existing->ID ?? 0;
		}

		$postarr = [
			'post_type'    => EventPostType::POST_TYPE,
			'post_title'   => sanitize_text_field( (string) ( $event['title'] ?? '' ) ),
			'post_name'    => sanitize_title( (string) ( $event['slug'] ?? '' ) ),
			'post_content' => wp_kses_post( (string) ( $event['description'] ?? '' ) ),
			'post_status'  => 'publish',
		];

		if ( $existing instanceof \WP_Post ) {
			$postarr['ID'] = $existing->ID;
			$post_id       = wp_update_post( wp_slash( $postarr ), true );
		} else {
			$post_id = wp_insert_post( wp_slash( $postarr ), true );
		}

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$this->sync_meta( (int) $post_id, $event );
		$this->sync_terms( (int) $post_id, is_array( $event['categories'] ?? null ) ? $event['categories'] : [] );
		( new MediaSideloader() )->sync( (int) $post_id, $event );

		return (int) $post_id;
	}

	/**
	 * @param array<string, mixed> $event Payload "event" object.
	 */
	private function sync_meta( int $post_id, array $event ): void {
		$occurrences = is_array( $event['occurrences'] ?? null ) ? $event['occurrences'] : [];
		$next        = $this->next_occurrence( $occurrences );
		$venue       = is_array( $event['venue'] ?? null ) ? $event['venue'] : [];

		update_post_meta( $post_id, '_atx_event_id', (int) $event['id'] );
		update_post_meta( $post_id, '_atx_status', sanitize_text_field( (string) ( $event['status'] ?? '' ) ) );
		$next_starts = (string) ( $next['starts_at'] ?? '' );
		update_post_meta( $post_id, '_atx_starts_at', sanitize_text_field( $next_starts ) );
		// Numeric (unix) mirror of the next date, so blocks can filter
		// upcoming/past and sort chronologically with a NUMERIC meta_query.
		update_post_meta( $post_id, '_atx_starts_at_ts', '' !== $next_starts ? (int) strtotime( $next_starts ) : 0 );
		update_post_meta( $post_id, '_atx_ends_at', sanitize_text_field( (string) ( $next['ends_at'] ?? '' ) ) );
		update_post_meta( $post_id, '_atx_venue_name', sanitize_text_field( (string) ( $venue['name'] ?? '' ) ) );
		update_post_meta( $post_id, '_atx_venue_address', sanitize_text_field( (string) ( $venue['address'] ?? '' ) ) );
		update_post_meta( $post_id, '_atx_venue_lat', sanitize_text_field( (string) ( $venue['lat'] ?? '' ) ) );
		update_post_meta( $post_id, '_atx_venue_lng', sanitize_text_field( (string) ( $venue['lng'] ?? '' ) ) );
		update_post_meta( $post_id, '_atx_timezone', sanitize_text_field( (string) ( $event['timezone'] ?? '' ) ) );
		update_post_meta( $post_id, '_atx_max_capacity', absint( $event['max_capacity'] ?? 0 ) );
		update_post_meta( $post_id, '_atx_is_recurring', ! empty( $event['is_recurring'] ) ? 1 : 0 );
		update_post_meta( $post_id, '_atx_requires_attendee_details', ! empty( $event['requires_attendee_details'] ) ? 1 : 0 );
		update_post_meta( $post_id, '_atx_published_at', sanitize_text_field( (string) ( $event['published_at'] ?? '' ) ) );
		update_post_meta( $post_id, '_atx_checkout_url', esc_url_raw( (string) ( $event['checkout_url'] ?? '' ) ) );
		// wp_slash guards the JSON against WP's unslashing on save; the flags
		// keep URLs/unicode readable (and free of the backslashes that would
		// otherwise be stripped, corrupting the stored payload).
		update_post_meta( $post_id, '_atx_payload', wp_slash( (string) wp_json_encode( $event, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ) );
	}

	/**
	 * The next upcoming occurrence, falling back to the first one.
	 *
	 * @param array<int, array<string, mixed>> $occurrences Occurrence list.
	 * @return array<string, mixed>
	 */
	private function next_occurrence( array $occurrences ): array {
		$now      = time();
		$upcoming = array_filter(
			$occurrences,
			static fn ( $occurrence ) => strtotime( (string) ( $occurrence['starts_at'] ?? '' ) ) >= $now
		);

		return $upcoming ? reset( $upcoming ) : ( $occurrences[0] ?? [] );
	}

	/**
	 * @param array<int, array<string, mixed>> $categories Category payloads.
	 */
	private function sync_terms( int $post_id, array $categories ): void {
		$term_ids = [];

		foreach ( $categories as $category ) {
			$name = sanitize_text_field( (string) ( $category['name'] ?? '' ) );
			$slug = sanitize_title( (string) ( $category['slug'] ?? '' ) );

			if ( '' === $name || '' === $slug ) {
				continue;
			}

			$term = get_term_by( 'slug', $slug, EventPostType::TAXONOMY );

			if ( ! $term ) {
				$created = wp_insert_term( $name, EventPostType::TAXONOMY, [ 'slug' => $slug ] );

				if ( is_wp_error( $created ) ) {
					continue;
				}

				$term_ids[] = (int) $created['term_id'];
				continue;
			}

			$term_ids[] = (int) $term->term_id;
		}

		wp_set_object_terms( $post_id, $term_ids, EventPostType::TAXONOMY );
	}
}
