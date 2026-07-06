<?php
/**
 * Events list template. Override by copying to {theme}/atx-ticketing/archive-event.php
 *
 * Available:
 *   $items        array of card items: [ 'post' => WP_Post, 'date_ts' => int|false,
 *                 'status' => string, 'is_past' => bool ]. In "individual past
 *                 dates" mode one event can appear as several items.
 *   $events_query WP_Query of atx_event posts (null for occurrence-expanded lists)
 *   $layout       'grid' | 'carousel'
 *   $columns      1-4 (grid columns / carousel item sizing hint)
 *   $scope        'upcoming' | 'past' | 'all'
 *
 * @package AtxDigitalTicketing
 */

defined( 'ABSPATH' ) || exit;

$atx_layout  = isset( $layout ) && 'carousel' === $layout ? 'carousel' : 'grid';
$atx_columns = isset( $columns ) ? max( 1, min( 4, (int) $columns ) ) : 3;
$atx_scope   = isset( $scope ) ? (string) $scope : 'upcoming';

// Normalise to a list of card items. Fall back to deriving them from the query
// so older callers / template overrides that only pass $events_query still work.
$atx_items = isset( $items ) && is_array( $items ) ? $items : [];

if ( ! $atx_items && isset( $events_query ) && $events_query instanceof WP_Query ) {
	foreach ( $events_query->posts as $atx_fallback_post ) {
		$atx_fallback_starts = (string) get_post_meta( $atx_fallback_post->ID, '_atx_starts_at', true );
		$atx_fallback_ts     = '' !== $atx_fallback_starts ? (int) strtotime( $atx_fallback_starts ) : 0;

		$atx_items[] = [
			'post'    => $atx_fallback_post,
			'date_ts' => $atx_fallback_ts > 0 ? $atx_fallback_ts : false,
			'status'  => (string) get_post_meta( $atx_fallback_post->ID, '_atx_status', true ),
			'is_past' => $atx_fallback_ts > 0 && $atx_fallback_ts < time(),
		];
	}
}

$atx_has_items = ! empty( $atx_items );

$atx_empty = 'past' === $atx_scope
	? __( 'No past events to show yet.', 'atx-digital-ticketing-connect' )
	: __( 'No upcoming events at the moment — check back soon.', 'atx-digital-ticketing-connect' );

$atx_events_query = isset( $events_query ) ? $events_query : null;
?>
<?php
/**
 * Fires before the events list — inject headings, filters or custom wrappers.
 *
 * @param WP_Query|null $events_query The events query (null when the list was
 *                                    expanded from occurrences).
 * @param string        $scope        upcoming|past|all.
 */
do_action( 'atx_ticketing_before_events', $atx_events_query, $atx_scope );
?>
<div class="atx-events atx-events--<?php echo esc_attr( $atx_layout ); ?> atx-events--cols-<?php echo esc_attr( (string) $atx_columns ); ?>">
	<?php if ( ! $atx_has_items ) : ?>
		<p class="atx-events__empty"><?php echo esc_html( $atx_empty ); ?></p>
	<?php endif; ?>

	<?php if ( 'carousel' === $atx_layout && $atx_has_items ) : ?>
		<button type="button" class="atx-events__nav atx-events__nav--prev" aria-label="<?php esc_attr_e( 'Previous', 'atx-digital-ticketing-connect' ); ?>">&#8249;</button>
		<button type="button" class="atx-events__nav atx-events__nav--next" aria-label="<?php esc_attr_e( 'Next', 'atx-digital-ticketing-connect' ); ?>">&#8250;</button>
	<?php endif; ?>

	<div class="atx-events__track">
	<?php
	global $post;

	foreach ( $atx_items as $atx_item ) :
		$post = $atx_item['post']; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		setup_postdata( $post );

		$venue      = (string) get_post_meta( get_the_ID(), '_atx_venue_name', true );
		$atx_status = (string) ( $atx_item['status'] ?? '' );
		$timestamp  = isset( $atx_item['date_ts'] ) && false !== $atx_item['date_ts'] ? (int) $atx_item['date_ts'] : false;
		$is_past    = ! empty( $atx_item['is_past'] );
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
	<?php endforeach; ?>
	<?php wp_reset_postdata(); ?>
	</div>
</div>
<?php
/**
 * Fires after the events list.
 *
 * @param WP_Query|null $events_query The events query (null when the list was
 *                                    expanded from occurrences).
 * @param string        $scope        upcoming|past|all.
 */
do_action( 'atx_ticketing_after_events', $atx_events_query, $atx_scope );
