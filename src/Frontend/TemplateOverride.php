<?php
/**
 * Routes event archive/single views through the plugin templates.
 *
 * @package AtxDigitalTicketing
 */

namespace AtxDigitalTicketing\Frontend;

use AtxDigitalTicketing\Plugin;
use AtxDigitalTicketing\PostTypes\EventPostType;
use WP_Post;
use WP_Term;

defined( 'ABSPATH' ) || exit;

/**
 * When "Use plugin templates" is enabled (default), /events/, category
 * archives and single event permalinks render with the plugin's templates
 * inside the theme's header/footer — instead of whatever generic
 * archive.php/single.php the theme falls back to. Individual templates can
 * still be overridden in {theme}/atx-ticketing/.
 */
final class TemplateOverride {

	private static string $content = '';

	public static function register(): void {
		add_filter( 'template_include', [ self::class, 'maybe_override' ] );
	}

	public static function maybe_override( string $template ): string {
		if ( empty( Plugin::settings()['use_plugin_templates'] ) ) {
			return $template;
		}

		if ( is_singular( EventPostType::POST_TYPE ) ) {
			$post = get_queried_object();

			if ( ! $post instanceof WP_Post ) {
				return $template;
			}

			self::$content = Shortcodes::render_single_post( $post );

			return ATX_TICKETING_DIR . 'templates/wrapper.php';
		}

		if ( is_post_type_archive( EventPostType::POST_TYPE ) || is_tax( EventPostType::TAXONOMY ) ) {
			$term = is_tax( EventPostType::TAXONOMY ) ? get_queried_object() : null;

			// An explicit ?scope= wins; otherwise the archive's configured default.
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display filter, no state change.
			$requested = isset( $_GET['scope'] ) ? sanitize_key( wp_unslash( $_GET['scope'] ) ) : '';
			$scope     = in_array( $requested, [ 'upcoming', 'past', 'all' ], true )
				? $requested
				: (string) ( Plugin::settings()['archive_scope'] ?? 'upcoming' );

			self::$content = Shortcodes::events_list(
				[
					'scope'    => $scope,
					'category' => $term instanceof WP_Term ? $term->slug : '',
					'limit'    => 50,
				]
			);

			return ATX_TICKETING_DIR . 'templates/wrapper.php';
		}

		return $template;
	}

	/**
	 * Rendered inner HTML for templates/wrapper.php.
	 */
	public static function content(): string {
		return self::$content;
	}

	/**
	 * Page heading for the wrapper (archive views only; single events render
	 * their own title).
	 */
	public static function heading(): string {
		if ( is_tax( EventPostType::TAXONOMY ) ) {
			$term = get_queried_object();

			return $term instanceof WP_Term ? $term->name : '';
		}

		if ( is_post_type_archive( EventPostType::POST_TYPE ) ) {
			return (string) post_type_archive_title( '', false );
		}

		return '';
	}
}
