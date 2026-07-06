<?php
/**
 * [atx_events], [atx_event] and [atx_featured_event] shortcodes.
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
 * blocks — one code path for all markup.
 */
final class Shortcodes {

	public static function register(): void {
		add_shortcode( 'atx_events', [ self::class, 'events_list' ] );
		add_shortcode( 'atx_event', [ self::class, 'single_event' ] );
		add_shortcode( 'atx_featured_event', [ self::class, 'featured_event' ] );
	}

	/**
	 * [atx_events scope="upcoming" category="conference" limit="12"
	 *   orderby="date" order="ASC" layout="grid" columns="3"]
	 *
	 * @param array<string, mixed>|string $atts Shortcode attributes.
	 */
	public static function events_list( $atts ): string {
		$atts = shortcode_atts(
			[
				'scope'    => 'upcoming',
				'category' => '',
				'limit'    => 12,
				'orderby'  => 'date',
				'order'    => '',
				'layout'   => 'grid',
				'columns'  => 3,
			],
			(array) $atts,
			'atx_events'
		);

		$scope  = self::clean_scope( (string) $atts['scope'] );
		$layout = 'carousel' === $atts['layout'] ? 'carousel' : 'grid';

		$query = self::query( $atts );

		Plugin::enqueue_frontend_style();

		if ( 'carousel' === $layout ) {
			wp_enqueue_script( 'atx-ticketing-events-carousel' );
		}

		$html = TemplateLoader::render(
			'archive-event',
			[
				'events_query' => $query,
				'layout'       => $layout,
				'columns'      => max( 1, min( 4, (int) $atts['columns'] ) ),
				'scope'        => $scope,
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
	 * [atx_featured_event post_id="34" layout="banner"] — highlights one event.
	 * post_id is the WordPress post id; 0 falls back to the next upcoming event.
	 *
	 * @param array<string, mixed>|string $atts Shortcode attributes.
	 */
	public static function featured_event( $atts ): string {
		$atts = shortcode_atts(
			[
				'post_id'          => 0,
				'layout'           => 'banner',
				'show_description' => 1,
				'show_date'        => 1,
				'show_venue'       => 1,
				'show_button'      => 1,
				'button_text'      => '',
			],
			(array) $atts,
			'atx_featured_event'
		);

		$post = self::resolve_featured_post( (int) $atts['post_id'] );

		if ( ! $post ) {
			return '<p class="atx-events__empty">' . esc_html__( 'No event to feature yet.', 'atx-digital-ticketing-connect' ) . '</p>';
		}

		Plugin::enqueue_frontend_style();

		return TemplateLoader::render(
			'featured-event',
			[
				'event_post'       => $post,
				'layout'           => 'card' === $atts['layout'] ? 'card' : 'banner',
				'show_description' => ! empty( $atts['show_description'] ),
				'show_date'        => ! empty( $atts['show_date'] ),
				'show_venue'       => ! empty( $atts['show_venue'] ),
				'show_button'      => ! empty( $atts['show_button'] ),
				'button_text'      => '' !== (string) $atts['button_text']
					? sanitize_text_field( (string) $atts['button_text'] )
					: __( 'Details & tickets', 'atx-digital-ticketing-connect' ),
			]
		);
	}

	/**
	 * Shared single-event renderer (shortcode, block and template override).
	 */
	public static function render_single_post( \WP_Post $post ): string {
		Plugin::enqueue_frontend_style();
		wp_enqueue_script( 'atx-ticketing-ticket-form' );
		wp_enqueue_script( 'atx-ticketing-gallery' );

		return TemplateLoader::render(
			'single-event',
			[
				'event_post' => $post,
				'event'      => EventPostType::payload( $post->ID ),
			]
		);
	}

	/**
	 * Public query builder for synced events, reusable by theme/block
	 * developers via atx_ticketing_get_events(). Accepts: scope
	 * (upcoming|past|all), category (slug), limit, orderby (date|title),
	 * order (ASC|DESC).
	 *
	 * @param array<string, mixed> $atts
	 */
	public static function query( array $atts = [] ): WP_Query {
		$atts = array_merge(
			[
				'scope'    => 'upcoming',
				'category' => '',
				'limit'    => 12,
				'orderby'  => 'date',
				'order'    => '',
			],
			$atts
		);

		return new WP_Query( self::query_args( self::clean_scope( (string) $atts['scope'] ), $atts ) );
	}

	/**
	 * Builds the WP_Query args for the events list, honouring the requested
	 * time scope (upcoming / past / all), ordering and category filter.
	 *
	 * @param string               $scope upcoming|past|all.
	 * @param array<string, mixed> $atts  Normalised attributes.
	 * @return array<string, mixed>
	 */
	private static function query_args( string $scope, array $atts ): array {
		$order = strtoupper( (string) $atts['order'] );

		if ( 'ASC' !== $order && 'DESC' !== $order ) {
			// Sensible default: soonest first for upcoming, most recent first for past.
			$order = 'past' === $scope ? 'DESC' : 'ASC';
		}

		$args = [
			'post_type'      => EventPostType::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => min( 50, max( 1, (int) $atts['limit'] ) ),
			'no_found_rows'  => true,
		];

		if ( 'title' === $atts['orderby'] ) {
			$args['orderby'] = 'title';
			$args['order']   = $order;
		} else {
			$args['meta_key'] = '_atx_starts_at_ts'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			$args['orderby']  = 'meta_value_num';
			$args['order']    = $order;
		}

		$now        = time();
		$meta_query = [];

		// Split on the last occurrence's end (_atx_last_ts): an event is
		// "upcoming" while it still has a date that has not finished, and only
		// becomes "past" once every one of its occurrences is over.
		if ( 'upcoming' === $scope ) {
			$meta_query[] = [
				'key'     => '_atx_last_ts',
				'value'   => $now,
				'compare' => '>=',
				'type'    => 'NUMERIC',
			];
		} elseif ( 'past' === $scope ) {
			$meta_query[] = [
				'relation' => 'AND',
				[
					'key'     => '_atx_last_ts',
					'value'   => 0,
					'compare' => '>',
					'type'    => 'NUMERIC',
				],
				[
					'key'     => '_atx_last_ts',
					'value'   => $now,
					'compare' => '<',
					'type'    => 'NUMERIC',
				],
			];
		}

		if ( $meta_query ) {
			$args['meta_query'] = $meta_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		}

		if ( '' !== (string) $atts['category'] ) {
			$args['tax_query'] = [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				[
					'taxonomy' => EventPostType::TAXONOMY,
					'field'    => 'slug',
					'terms'    => sanitize_title( (string) $atts['category'] ),
				],
			];
		}

		return $args;
	}

	private static function clean_scope( string $scope ): string {
		return in_array( $scope, [ 'upcoming', 'past', 'all' ], true ) ? $scope : 'upcoming';
	}

	/**
	 * Resolves the post to feature: an explicit published event, otherwise the
	 * next upcoming one.
	 */
	private static function resolve_featured_post( int $post_id ): ?\WP_Post {
		if ( $post_id > 0 ) {
			$post = get_post( $post_id );

			return ( $post instanceof \WP_Post && EventPostType::POST_TYPE === $post->post_type && 'publish' === $post->post_status )
				? $post
				: null;
		}

		$query = new WP_Query(
			self::query_args(
				'upcoming',
				[
					'limit'    => 1,
					'orderby'  => 'date',
					'order'    => 'ASC',
					'category' => '',
				]
			)
		);

		$post = $query->have_posts() ? $query->posts[0] : null;
		wp_reset_postdata();

		return $post instanceof \WP_Post ? $post : null;
	}
}
