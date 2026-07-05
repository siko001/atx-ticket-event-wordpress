<?php
/**
 * Featured event template. Override by copying to
 * {theme}/atx-ticketing/featured-event.php
 *
 * Available:
 *   $event_post       WP_Post (atx_event)
 *   $layout           'banner' | 'card'
 *   $show_description  bool
 *   $show_date        bool
 *   $show_venue       bool
 *   $show_button      bool
 *   $button_text      string
 *
 * @package AtxDigitalTicketing
 */

defined( 'ABSPATH' ) || exit;

/** @var WP_Post $event_post */
$atx_layout = isset( $layout ) && 'card' === $layout ? 'card' : 'banner';
$atx_id     = $event_post->ID;
$atx_status = (string) get_post_meta( $atx_id, '_atx_status', true );
$starts_at  = (string) get_post_meta( $atx_id, '_atx_starts_at', true );
$venue      = (string) get_post_meta( $atx_id, '_atx_venue_name', true );
$timestamp  = $starts_at ? strtotime( $starts_at ) : false;
$permalink  = (string) get_permalink( $atx_id );
?>
<div class="atx-featured atx-featured--<?php echo esc_attr( $atx_layout ); ?><?php echo 'cancelled' === $atx_status ? ' is-cancelled' : ''; ?>">
	<?php if ( has_post_thumbnail( $event_post ) ) : ?>
		<a href="<?php echo esc_url( $permalink ); ?>" class="atx-featured__media">
			<?php echo get_the_post_thumbnail( $event_post, 'large' ); ?>
		</a>
	<?php endif; ?>

	<div class="atx-featured__body">
		<h2 class="atx-featured__title">
			<a href="<?php echo esc_url( $permalink ); ?>"><?php echo esc_html( get_the_title( $event_post ) ); ?></a>
			<?php if ( 'cancelled' === $atx_status ) : ?>
				<span class="atx-badge atx-badge--cancelled"><?php esc_html_e( 'Cancelled', 'atx-digital-ticketing-connect' ); ?></span>
			<?php endif; ?>
		</h2>

		<?php if ( ! empty( $show_date ) && false !== $timestamp ) : ?>
			<p class="atx-featured__date">
				<?php echo esc_html( wp_date( get_option( 'date_format' ) . ' — ' . get_option( 'time_format' ), $timestamp ) ); ?>
			</p>
		<?php endif; ?>

		<?php if ( ! empty( $show_venue ) && '' !== $venue ) : ?>
			<p class="atx-featured__venue"><?php echo esc_html( $venue ); ?></p>
		<?php endif; ?>

		<?php if ( ! empty( $show_description ) && '' !== $event_post->post_content ) : ?>
			<p class="atx-featured__excerpt">
				<?php echo esc_html( wp_trim_words( wp_strip_all_tags( $event_post->post_content ), 45 ) ); ?>
			</p>
		<?php endif; ?>

		<?php if ( ! empty( $show_button ) ) : ?>
			<a class="atx-button" href="<?php echo esc_url( $permalink ); ?>">
				<?php echo esc_html( isset( $button_text ) ? (string) $button_text : __( 'Details & tickets', 'atx-digital-ticketing-connect' ) ); ?>
			</a>
		<?php endif; ?>
	</div>
</div>
