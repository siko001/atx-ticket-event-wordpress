<?php
/**
 * Events list template. Override by copying to {theme}/atx-ticketing/archive-event.php
 *
 * Available:
 *   $events_query (WP_Query of atx_event posts)
 *   $layout       'grid' | 'carousel'
 *   $columns      1-4 (grid columns / carousel item sizing hint)
 *   $scope        'upcoming' | 'past' | 'all'
 *
 * @package AtxDigitalTicketing
 */

defined( 'ABSPATH' ) || exit;

/** @var WP_Query $events_query */
$atx_layout  = isset( $layout ) && 'carousel' === $layout ? 'carousel' : 'grid';
$atx_columns = isset( $columns ) ? max( 1, min( 4, (int) $columns ) ) : 3;
$atx_scope   = isset( $scope ) ? (string) $scope : 'upcoming';

$atx_empty = 'past' === $atx_scope
	? __( 'No past events to show yet.', 'atx-digital-ticketing-connect' )
	: __( 'No upcoming events at the moment — check back soon.', 'atx-digital-ticketing-connect' );
?>
<div class="atx-events atx-events--<?php echo esc_attr( $atx_layout ); ?> atx-events--cols-<?php echo esc_attr( (string) $atx_columns ); ?>">
	<?php if ( ! $events_query->have_posts() ) : ?>
		<p class="atx-events__empty"><?php echo esc_html( $atx_empty ); ?></p>
	<?php endif; ?>

	<?php if ( 'carousel' === $atx_layout && $events_query->have_posts() ) : ?>
		<button type="button" class="atx-events__nav atx-events__nav--prev" aria-label="<?php esc_attr_e( 'Previous', 'atx-digital-ticketing-connect' ); ?>">&#8249;</button>
		<button type="button" class="atx-events__nav atx-events__nav--next" aria-label="<?php esc_attr_e( 'Next', 'atx-digital-ticketing-connect' ); ?>">&#8250;</button>
	<?php endif; ?>

	<div class="atx-events__track">
	<?php
	while ( $events_query->have_posts() ) :
		$events_query->the_post();

		$starts_at  = (string) get_post_meta( get_the_ID(), '_atx_starts_at', true );
		$venue      = (string) get_post_meta( get_the_ID(), '_atx_venue_name', true );
		$atx_status = (string) get_post_meta( get_the_ID(), '_atx_status', true );
		$timestamp  = $starts_at ? strtotime( $starts_at ) : false;
		$is_past    = false !== $timestamp && $timestamp < time();
		?>
		<article class="atx-events__card<?php echo 'cancelled' === $atx_status ? ' is-cancelled' : ''; ?><?php echo $is_past ? ' is-past' : ''; ?>">
			<?php if ( has_post_thumbnail() ) : ?>
				<a href="<?php the_permalink(); ?>" class="atx-events__thumb"><?php the_post_thumbnail( 'medium_large' ); ?></a>
			<?php endif; ?>

			<div class="atx-events__body">
				<h3 class="atx-events__title">
					<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
					<?php if ( 'cancelled' === $atx_status ) : ?>
						<span class="atx-badge atx-badge--cancelled"><?php esc_html_e( 'Cancelled', 'atx-digital-ticketing-connect' ); ?></span>
					<?php elseif ( $is_past ) : ?>
						<span class="atx-badge atx-badge--past"><?php esc_html_e( 'Past', 'atx-digital-ticketing-connect' ); ?></span>
					<?php endif; ?>
				</h3>

				<?php if ( false !== $timestamp ) : ?>
					<p class="atx-events__date">
						<?php echo esc_html( wp_date( get_option( 'date_format' ) . ' — ' . get_option( 'time_format' ), $timestamp ) ); ?>
					</p>
				<?php endif; ?>

				<?php if ( '' !== $venue ) : ?>
					<p class="atx-events__venue"><?php echo esc_html( $venue ); ?></p>
				<?php endif; ?>

				<?php if ( '' !== get_the_content() ) : ?>
					<p class="atx-events__excerpt">
						<?php echo esc_html( wp_trim_words( wp_strip_all_tags( get_the_content() ), 28 ) ); ?>
					</p>
				<?php endif; ?>

				<a class="atx-button" href="<?php the_permalink(); ?>"><?php esc_html_e( 'Details & tickets', 'atx-digital-ticketing-connect' ); ?></a>
			</div>
		</article>
	<?php endwhile; ?>
	</div>
</div>
