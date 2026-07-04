<?php
/**
 * Full-page wrapper for event archive/single views rendered by the plugin.
 * Override by copying to {theme}/atx-ticketing/wrapper.php — but usually you
 * would override archive-event.php / single-event.php instead.
 *
 * @package AtxDigitalTicketing
 */

defined( 'ABSPATH' ) || exit;

use AtxDigitalTicketing\Frontend\TemplateOverride;

// A theme copy of this wrapper takes precedence.
$atx_theme_wrapper = locate_template( [ 'atx-ticketing/wrapper.php' ] );

if ( '' !== $atx_theme_wrapper && realpath( $atx_theme_wrapper ) !== realpath( __FILE__ ) ) {
	include $atx_theme_wrapper;

	return;
}

get_header();

$atx_heading = TemplateOverride::heading();
?>
<main class="atx-wrap">
	<?php if ( '' !== $atx_heading ) : ?>
		<h1 class="atx-wrap__title"><?php echo esc_html( $atx_heading ); ?></h1>
	<?php endif; ?>

	<?php
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside the plugin templates.
	echo TemplateOverride::content();
	?>
</main>
<?php
get_footer();
