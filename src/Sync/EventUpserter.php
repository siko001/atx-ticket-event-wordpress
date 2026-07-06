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
	 * Re-derive the structured meta and terms for an already-synced event from
	 * its stored _atx_payload — a purely local refresh (no network round-trip,
	 * no media re-download). Used after a plugin update so newly introduced meta
	 * keys are populated without a manual "Sync now". Returns false when there
	 * is no usable stored payload.
	 */
	public function reindex( int $post_id ): bool {
		$raw     = (string) get_post_meta( $post_id, '_atx_payload', true );
		$payload = '' !== $raw ? json_decode( $raw, true ) : null;

		if ( ! is_array( $payload ) || empty( $payload['id'] ) ) {
			return false;
		}

		$this->sync_meta( $post_id, $payload );
		$this->sync_terms( $post_id, is_array( $payload['categories'] ?? null ) ? $payload['categories'] : [] );

		return true;
	}

	/**
	 * @param array<string, mixed> $event Payload "event" object.
	 */
	private function sync_meta( int $post_id, array $event ): void {
		$occurrences = is_array( $event['occurrences'] ?? null ) ? $event['occurrences'] : [];
		$now         = time();
		$primary     = $this->primary_occurrence( $occurrences, $now );
		$last_ts     = $this->last_occurrence_ts( $occurrences );
		$venue       = is_array( $event['venue'] ?? null ) ? $event['venue'] : [];

		update_post_meta( $post_id, '_atx_event_id', (int) $event['id'] );
		update_post_meta( $post_id, '_atx_status', sanitize_text_field( (string) ( $event['status'] ?? '' ) ) );
		$next_starts = (string) ( $primary['starts_at'] ?? '' );
		update_post_meta( $post_id, '_atx_starts_at', sanitize_text_field( $next_starts ) );
		// Numeric (unix) mirror of the representative date — the soonest upcoming
		// occurrence, or the most recent one when they have all passed — used for
		// chronological sorting.
		update_post_meta( $post_id, '_atx_starts_at_ts', '' !== $next_starts ? (int) strtotime( $next_starts ) : 0 );
		// The unix time of the last occurrence to finish. An event counts as
		// "past" only once this has elapsed, so upcoming/past blocks split on it
		// against the current time — an event with any future date stays upcoming
		// even when some of its earlier dates are already over.
		update_post_meta( $post_id, '_atx_last_ts', $last_ts );
		update_post_meta( $post_id, '_atx_ends_at', sanitize_text_field( (string) ( $primary['ends_at'] ?? '' ) ) );
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
	 * The representative occurrence for display and sorting: the soonest
	 * upcoming one, or — when every occurrence is in the past — the most recent.
	 *
	 * @param array<int, array<string, mixed>> $occurrences Occurrence list.
	 * @return array<string, mixed>
	 */
	private function primary_occurrence( array $occurrences, int $now ): array {
		$soonest_upcoming = null;
		$latest_past      = null;

		foreach ( $occurrences as $occurrence ) {
			$start = (int) strtotime( (string) ( $occurrence['starts_at'] ?? '' ) );

			if ( ! $start ) {
				continue;
			}

			if ( $start >= $now ) {
				if ( null === $soonest_upcoming || $start < (int) strtotime( (string) $soonest_upcoming['starts_at'] ) ) {
					$soonest_upcoming = $occurrence;
				}
			} elseif ( null === $latest_past || $start > (int) strtotime( (string) $latest_past['starts_at'] ) ) {
				$latest_past = $occurrence;
			}
		}

		return $soonest_upcoming ?? $latest_past ?? ( $occurrences[0] ?? [] );
	}

	/**
	 * The unix time of the last occurrence to finish — its end, or its start
	 * when no end is set. 0 when there are no occurrences.
	 *
	 * @param array<int, array<string, mixed>> $occurrences Occurrence list.
	 */
	private function last_occurrence_ts( array $occurrences ): int {
		$last = 0;

		foreach ( $occurrences as $occurrence ) {
			$end = (int) strtotime( (string) ( $occurrence['ends_at'] ?? '' ) );

			if ( ! $end ) {
				$end = (int) strtotime( (string) ( $occurrence['starts_at'] ?? '' ) );
			}

			if ( $end > $last ) {
				$last = $end;
			}
		}

		return $last;
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
