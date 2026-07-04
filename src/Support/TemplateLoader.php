<?php
/**
 * Theme-overridable template loading.
 *
 * @package AtxDigitalTicketing
 */

namespace AtxDigitalTicketing\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Templates ship in the plugin's /templates directory and can be overridden
 * by copying them to {theme}/atx-ticketing/{template}.php.
 */
final class TemplateLoader {

	/**
	 * @param array<string, mixed> $vars Variables extracted into the template scope.
	 */
	public static function render( string $template, array $vars = [] ): string {
		$theme_template = locate_template( [ 'atx-ticketing/' . $template . '.php' ] );
		$file           = '' !== $theme_template
			? $theme_template
			: ATX_TICKETING_DIR . 'templates/' . $template . '.php';

		if ( ! file_exists( $file ) ) {
			return '';
		}

		ob_start();
		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- controlled template scope.
		extract( $vars, EXTR_SKIP );
		include $file;

		return (string) ob_get_clean();
	}
}
