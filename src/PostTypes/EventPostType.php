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
			'_atx_event_id'      => 'integer',
			'_atx_starts_at'     => 'string',
			'_atx_ends_at'       => 'string',
			'_atx_venue_name'    => 'string',
			'_atx_venue_address' => 'string',
			'_atx_status'        => 'string',
			'_atx_payload'       => 'string',
		];
	}

	public static function register_meta_boxes(): void {
		add_meta_box(
			'atx-ticketing-source',
			__( 'ATX Ticketing', 'atx-digital-ticketing-connect' ),
			[ self::class, 'render_source_meta_box' ],
			self::POST_TYPE,
			'side',
			'high'
		);
	}

	public static function render_source_meta_box( WP_Post $post ): void {
		$settings  = Plugin::settings();
		$event_id  = (int) get_post_meta( $post->ID, '_atx_event_id', true );
		$status    = (string) get_post_meta( $post->ID, '_atx_status', true );
		$admin_url = $settings['admin_url'];

		echo '<p>' . esc_html__( 'This event is managed in the ATX Digital admin platform. Changes made here will be overwritten by the next sync.', 'atx-digital-ticketing-connect' ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Status:', 'atx-digital-ticketing-connect' ) . '</strong> ' . esc_html( $status ?: 'unknown' ) . '</p>';

		if ( '' !== $admin_url && $event_id > 0 ) {
			$url = trailingslashit( $admin_url ) . 'events/' . $event_id . '/edit';
			echo '<p><a class="button button-primary" href="' . esc_url( $url ) . '" target="_blank" rel="noopener">'
				. esc_html__( 'Edit in ATX admin ↗', 'atx-digital-ticketing-connect' ) . '</a></p>';
		}
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
}
