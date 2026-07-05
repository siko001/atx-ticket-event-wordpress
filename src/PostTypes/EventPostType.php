<?php
/**
 * The mirrored event custom post type and taxonomy.
 *
 * @package AtxDigitalTicketing
 */

namespace AtxDigitalTicketing\PostTypes;

use AtxDigitalTicketing\Plugin;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the atx_event CPT, its structured meta and the mirrored
 * category taxonomy. Laravel owns the data: the CPT is not creatable or
 * content-editable in wp-admin.
 */
final class EventPostType {

	public const POST_TYPE = 'atx_event';
	public const TAXONOMY  = 'atx_event_category';

	public static function register(): void {
		register_post_type(
			self::POST_TYPE,
			[
				'labels'       => [
					'name'          => __( 'Events', 'atx-digital-ticketing-connect' ),
					'singular_name' => __( 'Event', 'atx-digital-ticketing-connect' ),
				],
				'public'       => true,
				'has_archive'  => 'events',
				'rewrite'      => [ 'slug' => 'events' ],
				'menu_icon'    => 'dashicons-tickets-alt',
				'show_in_rest' => true,
				// Content is owned by Laravel; the editor is intentionally absent.
				'supports'     => [ 'title', 'thumbnail' ],
				'capabilities' => [
					// Events can only be created by the incoming webhook.
					'create_posts' => 'do_not_allow',
				],
				'map_meta_cap' => true,
			]
		);

		register_taxonomy(
			self::TAXONOMY,
			self::POST_TYPE,
			[
				'labels'       => [
					'name'          => __( 'Event categories', 'atx-digital-ticketing-connect' ),
					'singular_name' => __( 'Event category', 'atx-digital-ticketing-connect' ),
				],
				'public'       => true,
				'hierarchical' => true,
				'show_in_rest' => true,
				'rewrite'      => [ 'slug' => 'event-category' ],
			]
		);

		foreach ( self::structured_meta_keys() as $key => $type ) {
			register_post_meta(
				self::POST_TYPE,
				$key,
				[
					'type'          => $type,
					'single'        => true,
					'show_in_rest'  => false,
					'auth_callback' => '__return_false',
				]
			);
		}
	}

	/**
	 * Structured meta for querying/sorting; nested display data (speakers,
	 * sponsors, ticket types, questions, occurrences) lives in _atx_payload.
	 *
	 * @return array<string, string>
	 */
	public static function structured_meta_keys(): array {
		return [
			'_atx_event_id'                  => 'integer',
			'_atx_starts_at'                 => 'string',
			'_atx_starts_at_ts'              => 'integer',
			'_atx_ends_at'                   => 'string',
			'_atx_timezone'                  => 'string',
			'_atx_max_capacity'              => 'integer',
			'_atx_is_recurring'              => 'boolean',
			'_atx_requires_attendee_details' => 'boolean',
			'_atx_published_at'              => 'string',
			'_atx_venue_name'                => 'string',
			'_atx_venue_address'             => 'string',
			'_atx_venue_lat'                 => 'string',
			'_atx_venue_lng'                 => 'string',
			'_atx_checkout_url'              => 'string',
			'_atx_status'                    => 'string',
			'_atx_payload'                   => 'string',
		];
	}

	public static function register_meta_boxes(): void {
		add_meta_box(
			'atx-ticketing-source',
			__( 'ATX Ticketing — event details', 'atx-digital-ticketing-connect' ),
			[ self::class, 'render_source_meta_box' ],
			self::POST_TYPE,
			'normal',
			'high'
		);

		add_meta_box(
			'atx-ticketing-gallery',
			__( 'Event gallery (synced)', 'atx-digital-ticketing-connect' ),
			[ self::class, 'render_gallery_meta_box' ],
			self::POST_TYPE,
			'side',
			'default'
		);
	}

	public static function render_source_meta_box( WP_Post $post ): void {
		$settings  = Plugin::settings();
		$event_id  = (int) get_post_meta( $post->ID, '_atx_event_id', true );
		$status    = (string) get_post_meta( $post->ID, '_atx_status', true );
		$lat       = (string) get_post_meta( $post->ID, '_atx_venue_lat', true );
		$lng       = (string) get_post_meta( $post->ID, '_atx_venue_lng', true );
		$payload   = self::payload( $post->ID );
		$admin_url = $settings['admin_url'];

		$rows = [
			__( 'Status', 'atx-digital-ticketing-connect' ) => '' !== $status ? $status : 'unknown',
			__( 'Event ID', 'atx-digital-ticketing-connect' ) => $event_id > 0 ? (string) $event_id : '',
			__( 'Timezone', 'atx-digital-ticketing-connect' ) => (string) get_post_meta( $post->ID, '_atx_timezone', true ),
			__( 'Capacity', 'atx-digital-ticketing-connect' ) => (string) get_post_meta( $post->ID, '_atx_max_capacity', true ),
			__( 'Recurring', 'atx-digital-ticketing-connect' ) => get_post_meta( $post->ID, '_atx_is_recurring', true ) ? __( 'Yes', 'atx-digital-ticketing-connect' ) : __( 'No', 'atx-digital-ticketing-connect' ),
			__( 'Next date', 'atx-digital-ticketing-connect' ) => (string) get_post_meta( $post->ID, '_atx_starts_at', true ),
			__( 'Venue', 'atx-digital-ticketing-connect' ) => (string) get_post_meta( $post->ID, '_atx_venue_name', true ),
			__( 'Address', 'atx-digital-ticketing-connect' ) => (string) get_post_meta( $post->ID, '_atx_venue_address', true ),
			__( 'Latitude', 'atx-digital-ticketing-connect' ) => $lat,
			__( 'Longitude', 'atx-digital-ticketing-connect' ) => $lng,
			__( 'Dates synced', 'atx-digital-ticketing-connect' ) => (string) count( is_array( $payload['occurrences'] ?? null ) ? $payload['occurrences'] : [] ),
			__( 'Ticket types', 'atx-digital-ticketing-connect' ) => (string) count( is_array( $payload['ticket_types'] ?? null ) ? $payload['ticket_types'] : [] ),
			__( 'Speakers', 'atx-digital-ticketing-connect' ) => (string) count( is_array( $payload['speakers'] ?? null ) ? $payload['speakers'] : [] ),
			__( 'Sponsors', 'atx-digital-ticketing-connect' ) => (string) count( is_array( $payload['sponsors'] ?? null ) ? $payload['sponsors'] : [] ),
		];

		echo '<p>' . esc_html__( 'This event is managed in the ATX Digital admin platform. Changes made here will be overwritten by the next sync.', 'atx-digital-ticketing-connect' ) . '</p>';

		echo '<style>
			.atx-meta-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 0; border: 1px solid #dcdcde; border-radius: 4px; overflow: hidden; }
			.atx-meta-grid__cell { padding: 10px 14px; border-bottom: 1px solid #f0f0f1; border-right: 1px solid #f0f0f1; }
			.atx-meta-grid__label { display: block; font-size: 11px; text-transform: uppercase; letter-spacing: 0.04em; color: #646970; margin-bottom: 2px; }
			.atx-meta-grid__value { font-size: 14px; font-weight: 500; word-break: break-word; }
			.atx-meta-actions { margin-top: 12px; display: flex; gap: 8px; flex-wrap: wrap; }
		</style>';

		echo '<div class="atx-meta-grid">';

		foreach ( $rows as $label => $value ) {
			if ( '' === $value ) {
				continue;
			}

			echo '<div class="atx-meta-grid__cell"><span class="atx-meta-grid__label">' . esc_html( $label ) . '</span><span class="atx-meta-grid__value">' . esc_html( $value ) . '</span></div>';
		}

		echo '</div>';

		$tickets = is_array( $payload['ticket_types'] ?? null ) ? $payload['ticket_types'] : [];

		if ( $tickets ) {
			echo '<h4 style="margin:16px 0 8px;">' . esc_html__( 'Ticket types', 'atx-digital-ticketing-connect' ) . '</h4>';
			echo '<table class="widefat striped"><thead><tr>'
				. '<th>' . esc_html__( 'Name', 'atx-digital-ticketing-connect' ) . '</th>'
				. '<th>' . esc_html__( 'Price', 'atx-digital-ticketing-connect' ) . '</th>'
				. '<th>' . esc_html__( 'Available', 'atx-digital-ticketing-connect' ) . '</th>'
				. '<th>' . esc_html__( 'Sold', 'atx-digital-ticketing-connect' ) . '</th>'
				. '</tr></thead><tbody>';

			foreach ( $tickets as $ticket ) {
				$quantity = $ticket['quantity_available'] ?? null;
				$sold     = (int) ( $ticket['quantity_sold'] ?? 0 );
				$left     = null === $quantity ? __( 'Unlimited', 'atx-digital-ticketing-connect' ) : max( 0, (int) $quantity - $sold ) . ' / ' . (int) $quantity;

				echo '<tr>'
					. '<td><strong>' . esc_html( (string) ( $ticket['name'] ?? '' ) ) . '</strong></td>'
					. '<td>' . esc_html( strtoupper( (string) ( $ticket['currency'] ?? '' ) ) . ' ' . number_format_i18n( ( (int) ( $ticket['price'] ?? 0 ) ) / 100, 2 ) ) . '</td>'
					. '<td>' . esc_html( $left ) . '</td>'
					. '<td>' . esc_html( (string) $sold ) . '</td>'
					. '</tr>';
			}

			echo '</tbody></table>';
		}

		$occurrences = is_array( $payload['occurrences'] ?? null ) ? $payload['occurrences'] : [];

		if ( $occurrences ) {
			echo '<h4 style="margin:16px 0 8px;">' . esc_html__( 'Upcoming dates', 'atx-digital-ticketing-connect' ) . '</h4><ul style="margin:0 0 4px 18px;list-style:disc;">';

			$shown = 0;
			foreach ( $occurrences as $occurrence ) {
				$start = strtotime( (string) ( $occurrence['starts_at'] ?? '' ) );

				if ( false === $start || $start < time() ) {
					continue;
				}

				echo '<li>' . esc_html( wp_date( (string) get_option( 'date_format' ) . ' ' . (string) get_option( 'time_format' ), $start ) ) . '</li>';

				if ( ++$shown >= 6 ) {
					$remaining = count( $occurrences ) - $shown;
					if ( $remaining > 0 ) {
						/* translators: %d: number of further dates. */
						echo '<li>' . esc_html( sprintf( __( '… and %d more', 'atx-digital-ticketing-connect' ), $remaining ) ) . '</li>';
					}
					break;
				}
			}

			echo '</ul>';
		}

		echo '<div class="atx-meta-actions">';

		if ( '' !== $admin_url && $event_id > 0 ) {
			$url = trailingslashit( $admin_url ) . 'events/' . $event_id . '/edit';
			echo '<a class="button button-primary" href="' . esc_url( $url ) . '" target="_blank" rel="noopener">'
				. esc_html__( 'Edit in ATX admin ↗', 'atx-digital-ticketing-connect' ) . '</a>';
		}

		if ( '' !== $lat && '' !== $lng ) {
			$map_url = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( $lat . ',' . $lng );
			echo '<a class="button" href="' . esc_url( $map_url ) . '" target="_blank" rel="noopener">'
				. esc_html__( 'View on Google Maps ↗', 'atx-digital-ticketing-connect' ) . '</a>';
		}

		echo '</div>';
	}

	/**
	 * Find a mirrored post by its Laravel-side event id.
	 */
	public static function find_by_event_id( int $event_id ): ?WP_Post {
		$posts = get_posts(
			[
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'meta_key'       => '_atx_event_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => $event_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			]
		);

		return $posts[0] ?? null;
	}

	/**
	 * Decoded sync payload for a mirrored event post.
	 *
	 * @return array<string, mixed>
	 */
	public static function payload( int $post_id ): array {
		$raw     = (string) get_post_meta( $post_id, '_atx_payload', true );
		$decoded = json_decode( $raw, true );

		return is_array( $decoded ) ? $decoded : [];
	}

	/**
	 * Read-only gallery panel (WooCommerce-style) showing the media synced
	 * from the ATX admin — managed there, mirrored here.
	 */
	public static function render_gallery_meta_box( WP_Post $post ): void {
		$gallery_ids = get_post_meta( $post->ID, '_atx_gallery_ids', true );
		$gallery_ids = is_array( $gallery_ids ) ? array_filter( array_map( 'absint', $gallery_ids ) ) : [];

		if ( ! $gallery_ids ) {
			echo '<p>' . esc_html__( 'No gallery media synced yet. Add images/videos to the event in the ATX admin and re-sync.', 'atx-digital-ticketing-connect' ) . '</p>';

			return;
		}

		echo '<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:6px;">';

		foreach ( $gallery_ids as $attachment_id ) {
			$edit_link = get_edit_post_link( $attachment_id );
			$is_video  = str_starts_with( (string) get_post_mime_type( $attachment_id ), 'video/' );

			echo '<a href="' . esc_url( (string) $edit_link ) . '" style="display:block;position:relative;border-radius:4px;overflow:hidden;aspect-ratio:1;background:#f0f0f1;">';

			if ( $is_video ) {
				echo '<span class="dashicons dashicons-video-alt3" style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:28px;color:#646970;"></span>';
			} else {
				echo wp_get_attachment_image(
					$attachment_id,
					'thumbnail',
					false,
					[ 'style' => 'width:100%;height:100%;object-fit:cover;display:block;' ]
				);
			}

			echo '</a>';
		}

		echo '</div>';
		echo '<p class="description" style="margin-top:8px;">' . esc_html__( 'Managed in the ATX admin — synced automatically.', 'atx-digital-ticketing-connect' ) . '</p>';
	}
}
