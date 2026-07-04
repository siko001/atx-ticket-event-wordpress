<?php
/**
 * Events list template. Override by copying to {theme}/atx-ticketing/archive-event.php
 *
 * Available: $events_query (WP_Query of atx_event posts, ordered by start date).
 *
 * @package AtxDigitalTicketing
 */

defined( 'ABSPATH' ) || exit;

/** @var WP_Query $events_query */
?>
<div class="atx-events">
	<?php if ( ! $events_query->have_posts() ) : ?>
		<p class="atx-events__empty"><?php esc_html_e( 'No upcoming events at the moment — check back soon.', 'atx-digital-ticketing-connect' ); ?></p>
	<?php endif; ?>

	<?php
	while ( $events_query->have_posts() ) :
		$events_query->the_post();

		$starts_at = (string) get_post_meta( get_the_ID(), '_atx_starts_at', true );
		$venue     = (string) get_post_meta( get_the_ID(), '_atx_venue_name', true );
		$status    = (string) get_post_meta( get_the_ID(), '_atx_status', true );
		$timestamp = $starts_at ? strtotime( $starts_at ) : false;
		?>
		<article class="atx-events__card<?php echo 'cancelled' === $status ? ' is-cancelled' : ''; ?>">
			<?php if ( has_post_thumbnail() ) : ?>
				<a href="<?php the_permalink(); ?>" class="atx-events__thumb"><?php the_post_thumbnail( 'medium_large' ); ?></a>
			<?php endif; ?>

			<div class="atx-events__body">
				<h3 class="atx-events__title">
					<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
					<?php if ( 'cancelled' === $status ) : ?>
						<span class="atx-badge atx-badge--cancelled"><?php esc_html_e( 'Cancelled', 'atx-digital-ticketing-connect' ); ?></span>
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

				<a class="atx-button" href="<?php the_permalink(); ?>"><?php esc_html_e( 'Details & tickets', 'atx-digital-ticketing-connect' ); ?></a>
			</div>
		</article>
	<?php endwhile; ?>
</div>
