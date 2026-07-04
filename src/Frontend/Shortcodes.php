<?php
/**
 * [atx_events] and [atx_event] shortcodes.
 *
 * @package AtxDigitalTicketing
 */

namespace AtxDigitalTicketing\Frontend;

use AtxDigitalTicketing\Plugin;
use AtxDigitalTicketing\PostTypes\EventPostType;
use AtxDigitalTicketing\Support\TemplateLoader;
use WP_Query;

defined( 'ABSPATH' ) || exit;

/**
 * Shortcodes and the shared render callbacks also used by the Gutenberg
 * block — one code path for all markup.
 */
final class Shortcodes {

	public static function register(): void {
		add_shortcode( 'atx_events', [ self::class, 'events_list' ] );
		add_shortcode( 'atx_event', [ self::class, 'single_event' ] );
	}

	/**
	 * [atx_events category="conference" limit="12"]
	 *
	 * @param array<string, mixed>|string $atts Shortcode attributes.
	 */
	public static function events_list( $atts ): string {
		$atts = shortcode_atts(
			[
				'category' => '',
				'limit'    => 12,
			],
			(array) $atts,
			'atx_events'
		);

		$args = [
			'post_type'      => EventPostType::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => min( 50, max( 1, (int) $atts['limit'] ) ),
			'meta_key'       => '_atx_starts_at', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'orderby'        => 'meta_value',
			'order'          => 'ASC',
		];

		if ( '' !== $atts['category'] ) {
			$args['tax_query'] = [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				[
					'taxonomy' => EventPostType::TAXONOMY,
					'field'    => 'slug',
					'terms'    => sanitize_title( (string) $atts['category'] ),
				],
			];
		}

		$query = new WP_Query( $args );

		Plugin::enqueue_frontend_style();

		$html = TemplateLoader::render(
			'archive-event',
			[
				'events_query' => $query,
			]
		);

		wp_reset_postdata();

		return $html;
	}

	/**
	 * [atx_event id="12"] — id is the Laravel-side event id.
	 *
	 * @param array<string, mixed>|string $atts Shortcode attributes.
	 */
	public static function single_event( $atts ): string {
		$atts = shortcode_atts( [ 'id' => 0 ], (array) $atts, 'atx_event' );
		$post = EventPostType::find_by_event_id( (int) $atts['id'] );

		if ( ! $post || 'publish' !== $post->post_status ) {
			return '<p>' . esc_html__( 'Event not found.', 'atx-digital-ticketing-connect' ) . '</p>';
		}

		return self::render_single_post( $post );
	}

	/**
	 * Shared single-event renderer (shortcode, block and template override).
	 */
	public static function render_single_post( \WP_Post $post ): string {
		Plugin::enqueue_frontend_style();
		wp_enqueue_script( 'atx-ticketing-ticket-form' );

		return TemplateLoader::render(
			'single-event',
			[
				'event_post' => $post,
				'event'      => EventPostType::payload( $post->ID ),
			]
		);
	}
}
