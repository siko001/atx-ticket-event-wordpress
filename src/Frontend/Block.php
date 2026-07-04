<?php
/**
 * Gutenberg block wrapping the shortcode render callbacks.
 *
 * @package AtxDigitalTicketing
 */

namespace AtxDigitalTicketing\Frontend;

defined( 'ABSPATH' ) || exit;

/**
 * "ATX Events" block. Server-rendered through the same callbacks as the
 * shortcodes, so markup logic exists exactly once.
 */
final class Block {

	public static function register(): void {
		wp_register_script(
			'atx-ticketing-block',
			ATX_TICKETING_URL . 'assets/js/block.js',
			[ 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-server-side-render', 'wp-i18n' ],
			ATX_TICKETING_VERSION,
			true
		);

		register_block_type(
			'atx-ticketing/events',
			[
				'api_version'     => 3,
				'editor_script'   => 'atx-ticketing-block',
				'render_callback' => [ self::class, 'render' ],
				'attributes'      => [
					'eventId'  => [
						'type'    => 'number',
						'default' => 0,
					],
					'category' => [
						'type'    => 'string',
						'default' => '',
					],
					'limit'    => [
						'type'    => 'number',
						'default' => 12,
					],
				],
			]
		);
	}

	/**
	 * @param array<string, mixed> $attributes Block attributes.
	 */
	public static function render( array $attributes ): string {
		if ( ! empty( $attributes['eventId'] ) ) {
			return Shortcodes::single_event( [ 'id' => (int) $attributes['eventId'] ] );
		}

		return Shortcodes::events_list(
			[
				'category' => (string) ( $attributes['category'] ?? '' ),
				'limit'    => (int) ( $attributes['limit'] ?? 12 ),
			]
		);
	}
}
