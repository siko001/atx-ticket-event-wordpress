<?php
/**
 * Single event template. Override by copying to {theme}/atx-ticketing/single-event.php
 *
 * Available:
 *   $event_post (WP_Post)
 *   $event      (array — the full synced payload: occurrences, speakers,
 *               sponsors, ticket_types, registration_questions, checkout_url…)
 *
 * @package AtxDigitalTicketing
 */

defined( 'ABSPATH' ) || exit;

use AtxDigitalTicketing\Support\TemplateLoader;

/** @var WP_Post $event_post */
/** @var array<string, mixed> $event */

$atx_status   = (string) ( $event['status'] ?? 'published' );
$atx_venue    = is_array( $event['venue'] ?? null ) ? $event['venue'] : [];
$atx_speakers = is_array( $event['speakers'] ?? null ) ? $event['speakers'] : [];
$atx_sponsors = is_array( $event['sponsors'] ?? null ) ? $event['sponsors'] : [];
?>
<article class="atx-event">
	<header class="atx-event__header">
		<h1 class="atx-event__title"><?php echo esc_html( get_the_title( $event_post ) ); ?></h1>
		<?php if ( 'cancelled' === $atx_status ) : ?>
			<p class="atx-badge atx-badge--cancelled"><?php esc_html_e( 'This event has been cancelled.', 'atx-digital-ticketing-connect' ); ?></p>
		<?php endif; ?>
	</header>

	<?php if ( has_post_thumbnail( $event_post ) ) : ?>
		<div class="atx-event__thumb"><?php echo get_the_post_thumbnail( $event_post, 'large' ); ?></div>
	<?php endif; ?>

	<div class="atx-event__description">
		<?php echo wp_kses_post( (string) ( $event['description'] ?? $event_post->post_content ) ); ?>
	</div>

	<?php if ( ! empty( $atx_venue['name'] ) ) : ?>
		<section class="atx-event__venue">
			<h2><?php esc_html_e( 'Venue', 'atx-digital-ticketing-connect' ); ?></h2>
			<p>
				<strong><?php echo esc_html( (string) $atx_venue['name'] ); ?></strong>
				<?php if ( ! empty( $atx_venue['address'] ) ) : ?>
					<br><?php echo esc_html( (string) $atx_venue['address'] ); ?>
				<?php endif; ?>
				<?php if ( ! empty( $atx_venue['lat'] ) && ! empty( $atx_venue['lng'] ) ) : ?>
					<br><a href="<?php echo esc_url( 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( $atx_venue['lat'] . ',' . $atx_venue['lng'] ) ); ?>" target="_blank" rel="noopener">
						<?php esc_html_e( 'View on map ↗', 'atx-digital-ticketing-connect' ); ?>
					</a>
				<?php endif; ?>
			</p>
		</section>
	<?php endif; ?>

	<?php if ( array_filter( $atx_speakers ) ) : ?>
		<section class="atx-event__speakers">
			<h2><?php esc_html_e( 'Speakers', 'atx-digital-ticketing-connect' ); ?></h2>
			<ul class="atx-speakers">
				<?php foreach ( $atx_speakers as $atx_speaker ) : ?>
					<li class="atx-speakers__item">
						<?php if ( ! empty( $atx_speaker['photo_url'] ) ) : ?>
							<img class="atx-speakers__photo" src="<?php echo esc_url( (string) $atx_speaker['photo_url'] ); ?>" alt="<?php echo esc_attr( (string) ( $atx_speaker['name'] ?? '' ) ); ?>">
						<?php endif; ?>
						<strong><?php echo esc_html( (string) ( $atx_speaker['name'] ?? '' ) ); ?></strong>
						<?php if ( ! empty( $atx_speaker['role'] ) ) : ?>
							<em class="atx-speakers__role"><?php echo esc_html( (string) $atx_speaker['role'] ); ?></em>
						<?php endif; ?>
						<?php if ( ! empty( $atx_speaker['organisation'] ) ) : ?>
							<span class="atx-speakers__org"><?php echo esc_html( (string) $atx_speaker['organisation'] ); ?></span>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>
	<?php endif; ?>

	<?php if ( 'cancelled' !== $atx_status ) : ?>
		<section class="atx-event__tickets">
			<h2><?php esc_html_e( 'Tickets', 'atx-digital-ticketing-connect' ); ?></h2>
			<?php
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- template output is escaped within the partial.
			echo TemplateLoader::render( 'partials/ticket-form', [ 'event' => $event ] );
			?>
		</section>
	<?php endif; ?>

	<?php if ( array_filter( $atx_sponsors ) ) : ?>
		<section class="atx-event__sponsors">
			<h2><?php esc_html_e( 'Sponsors', 'atx-digital-ticketing-connect' ); ?></h2>
			<ul class="atx-sponsors">
				<?php foreach ( $atx_sponsors as $atx_sponsor ) : ?>
					<li class="atx-sponsors__item">
						<?php $atx_sponsor_name = (string) ( $atx_sponsor['name'] ?? '' ); ?>
						<?php if ( ! empty( $atx_sponsor['url'] ) ) : ?>
							<a href="<?php echo esc_url( (string) $atx_sponsor['url'] ); ?>" target="_blank" rel="noopener sponsored">
								<?php if ( ! empty( $atx_sponsor['logo_url'] ) ) : ?>
									<img src="<?php echo esc_url( (string) $atx_sponsor['logo_url'] ); ?>" alt="<?php echo esc_attr( $atx_sponsor_name ); ?>">
								<?php else : ?>
									<?php echo esc_html( $atx_sponsor_name ); ?>
								<?php endif; ?>
							</a>
						<?php else : ?>
							<?php echo esc_html( $atx_sponsor_name ); ?>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>
	<?php endif; ?>
</article>
