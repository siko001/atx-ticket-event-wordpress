<?php
/**
 * Gutenberg blocks wrapping the shortcode render callbacks.
 *
 * @package AtxDigitalTicketing
 */

namespace AtxDigitalTicketing\Frontend;

defined( 'ABSPATH' ) || exit;

/**
 * Two server-rendered blocks, both routed through the shortcode callbacks so
 * markup logic exists exactly once:
 *   - atx-ticketing/events         — a list of events (upcoming/past/all).
 *   - atx-ticketing/featured-event — one highlighted event.
 */
final class Block {

	public static function register(): void {
		wp_register_script(
			'atx-ticketing-block',
			ATX_TICKETING_URL . 'assets/js/block.js',
			[ 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-server-side-render', 'wp-api-fetch', 'wp-i18n' ],
			ATX_TICKETING_VERSION,
			true
		);

		register_block_type(
			'atx-ticketing/events',
			[
				'api_version'     => 3,
				'editor_script'   => 'atx-ticketing-block',
				'render_callback' => [ self::class, 'render_events' ],
				'attributes'      => [
					// Kept for backward compatibility: a non-zero id renders a
					// single event (superseded by the Featured event block).
					'eventId'  => [
						'type'    => 'number',
						'default' => 0,
					],
					'scope'    => [
						'type'    => 'string',
						'default' => 'upcoming',
					],
					'category' => [
						'type'    => 'string',
						'default' => '',
					],
					'limit'    => [
						'type'    => 'number',
						'default' => 12,
					],
					'orderby'  => [
						'type'    => 'string',
						'default' => 'date',
					],
					'order'    => [
						'type'    => 'string',
						'default' => '',
					],
					'layout'   => [
						'type'    => 'string',
						'default' => 'grid',
					],
					'columns'  => [
						'type'    => 'number',
						'default' => 3,
					],
				],
			]
		);

		register_block_type(
			'atx-ticketing/featured-event',
			[
				'api_version'     => 3,
				'editor_script'   => 'atx-ticketing-block',
				'render_callback' => [ self::class, 'render_featured' ],
				'attributes'      => [
					'postId'          => [
						'type'    => 'number',
						'default' => 0,
					],
					'layout'          => [
						'type'    => 'string',
						'default' => 'banner',
					],
					'showDescription' => [
						'type'    => 'boolean',
						'default' => true,
					],
					'showDate'        => [
						'type'    => 'boolean',
						'default' => true,
					],
					'showVenue'       => [
						'type'    => 'boolean',
						'default' => true,
					],
					'showButton'      => [
						'type'    => 'boolean',
						'default' => true,
					],
					'buttonText'      => [
						'type'    => 'string',
						'default' => '',
					],
				],
			]
		);
	}

	/**
	 * @param array<string, mixed> $attributes Block attributes.
	 */
	public static function render_events( array $attributes ): string {
		if ( ! empty( $attributes['eventId'] ) ) {
			return Shortcodes::single_event( [ 'id' => (int) $attributes['eventId'] ] );
		}

		return Shortcodes::events_list(
			[
				'scope'    => (string) ( $attributes['scope'] ?? 'upcoming' ),
				'category' => (string) ( $attributes['category'] ?? '' ),
				'limit'    => (int) ( $attributes['limit'] ?? 12 ),
				'orderby'  => (string) ( $attributes['orderby'] ?? 'date' ),
				'order'    => (string) ( $attributes['order'] ?? '' ),
				'layout'   => (string) ( $attributes['layout'] ?? 'grid' ),
				'columns'  => (int) ( $attributes['columns'] ?? 3 ),
			]
		);
	}

	/**
	 * @param array<string, mixed> $attributes Block attributes.
	 */
	public static function render_featured( array $attributes ): string {
		return Shortcodes::featured_event(
			[
				'post_id'          => (int) ( $attributes['postId'] ?? 0 ),
				'layout'           => (string) ( $attributes['layout'] ?? 'banner' ),
				'show_description' => ! empty( $attributes['showDescription'] ),
				'show_date'        => ! empty( $attributes['showDate'] ),
				'show_venue'       => ! empty( $attributes['showVenue'] ),
				'show_button'      => ! empty( $attributes['showButton'] ),
				'button_text'      => (string) ( $attributes['buttonText'] ?? '' ),
			]
		);
	}
}
