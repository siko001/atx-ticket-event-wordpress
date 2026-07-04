<?php
/**
 * Ticket purchase form. Override via {theme}/atx-ticketing/partials/ticket-form.php
 *
 * Available: $event (array — synced payload).
 *
 * The form is enhanced by assets/js/ticket-form.js, which posts the selection
 * to the WordPress checkout proxy and redirects to Stripe Checkout.
 *
 * @package AtxDigitalTicketing
 */

defined( 'ABSPATH' ) || exit;

/** @var array<string, mixed> $event */

$atx_event_id     = (int) ( $event['id'] ?? 0 );
$atx_occurrences  = array_values(
	array_filter(
		is_array( $event['occurrences'] ?? null ) ? $event['occurrences'] : [],
		static fn ( $atx_o ) => strtotime( (string) ( $atx_o['starts_at'] ?? '' ) ) >= time()
	)
);
$atx_ticket_types = is_array( $event['ticket_types'] ?? null ) ? $event['ticket_types'] : [];
$atx_questions    = is_array( $event['registration_questions'] ?? null ) ? $event['registration_questions'] : [];

if ( 0 === $atx_event_id || ! $atx_occurrences || ! $atx_ticket_types ) {
	echo '<p>' . esc_html__( 'Ticket sales are not open for this event.', 'atx-digital-ticketing-connect' ) . '</p>';

	return;
}

$atx_format_price = static function ( int $minor, string $currency ): string {
	return strtoupper( $currency ) . ' ' . number_format_i18n( $minor / 100, 2 );
};
?>
<?php if ( ! empty( \AtxDigitalTicketing\Plugin::settings()['test_mode'] ) ) : ?>
	<div class="atx-test-banner" role="status">
		<strong><?php esc_html_e( 'TEST MODE', 'atx-digital-ticketing-connect' ); ?></strong>
		<?php esc_html_e( '— this ticket shop is running in test mode. Orders placed here are for testing and are not real bookings.', 'atx-digital-ticketing-connect' ); ?>
	</div>
<?php endif; ?>
<form class="atx-ticket-form"
	data-event-id="<?php echo esc_attr( (string) $atx_event_id ); ?>"
	data-requires-attendees="<?php echo ! empty( $event['requires_attendee_details'] ) ? '1' : '0'; ?>"
	novalidate>
	<div class="atx-ticket-form__errors" hidden></div>

	<?php if ( count( $atx_occurrences ) > 1 ) : ?>
		<p class="atx-field">
			<label for="atx-occurrence"><?php esc_html_e( 'Date', 'atx-digital-ticketing-connect' ); ?></label>
			<select id="atx-occurrence" name="occurrence_id" required>
				<?php foreach ( $atx_occurrences as $atx_occurrence ) : ?>
					<option value="<?php echo esc_attr( (string) $atx_occurrence['id'] ); ?>">
						<?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( (string) $atx_occurrence['starts_at'] ) ) ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</p>
	<?php else : ?>
		<input type="hidden" name="occurrence_id" value="<?php echo esc_attr( (string) $atx_occurrences[0]['id'] ); ?>">
	<?php endif; ?>

	<fieldset class="atx-ticket-form__tickets">
		<legend><?php esc_html_e( 'Tickets', 'atx-digital-ticketing-connect' ); ?></legend>
		<?php foreach ( $atx_ticket_types as $atx_ticket ) : ?>
			<div class="atx-ticket-row">
				<div class="atx-ticket-row__info">
					<strong><?php echo esc_html( (string) ( $atx_ticket['name'] ?? '' ) ); ?></strong>
					<span class="atx-ticket-row__price"><?php echo esc_html( $atx_format_price( (int) ( $atx_ticket['price'] ?? 0 ), (string) ( $atx_ticket['currency'] ?? 'eur' ) ) ); ?></span>
					<?php if ( ! empty( $atx_ticket['description'] ) ) : ?>
						<small><?php echo esc_html( (string) $atx_ticket['description'] ); ?></small>
					<?php endif; ?>
				</div>
				<label class="atx-ticket-row__qty">
					<span class="screen-reader-text"><?php esc_html_e( 'Quantity', 'atx-digital-ticketing-connect' ); ?></span>
					<input type="number" min="0" max="10" value="0" name="qty[<?php echo esc_attr( (string) $atx_ticket['id'] ); ?>]" data-ticket-type="<?php echo esc_attr( (string) $atx_ticket['id'] ); ?>" data-ticket-name="<?php echo esc_attr( (string) ( $atx_ticket['name'] ?? '' ) ); ?>">
				</label>
			</div>
		<?php endforeach; ?>
	</fieldset>

	<fieldset class="atx-ticket-form__attendees" data-attendee-fields hidden>
		<legend><?php esc_html_e( 'Who are the tickets for?', 'atx-digital-ticketing-connect' ); ?></legend>
		<p class="atx-field__hint"><?php esc_html_e( 'This event issues personal tickets — enter a name for each one. Email is optional (tickets are also sent to the buyer).', 'atx-digital-ticketing-connect' ); ?></p>
		<div class="atx-attendees"></div>
	</fieldset>

	<fieldset class="atx-ticket-form__purchaser">
		<legend><?php esc_html_e( 'Your details', 'atx-digital-ticketing-connect' ); ?></legend>
		<p class="atx-field">
			<label for="atx-name"><?php esc_html_e( 'Full name', 'atx-digital-ticketing-connect' ); ?> *</label>
			<input id="atx-name" type="text" name="purchaser_name" required autocomplete="name">
		</p>
		<p class="atx-field">
			<label for="atx-email"><?php esc_html_e( 'Email', 'atx-digital-ticketing-connect' ); ?> *</label>
			<input id="atx-email" type="email" name="purchaser_email" required autocomplete="email">
		</p>
	</fieldset>

	<?php if ( $atx_questions ) : ?>
		<fieldset class="atx-ticket-form__questions">
			<legend><?php esc_html_e( 'Registration details', 'atx-digital-ticketing-connect' ); ?></legend>
			<?php
			foreach ( $atx_questions as $atx_question ) :
				$atx_q_id       = (int) ( $atx_question['id'] ?? 0 );
				$atx_q_label    = (string) ( $atx_question['label'] ?? '' );
				$atx_q_type     = (string) ( $atx_question['type'] ?? 'text' );
				$atx_q_required = ! empty( $atx_question['is_required'] );
				$atx_q_options  = is_array( $atx_question['options'] ?? null ) ? $atx_question['options'] : [];
				$atx_q_name     = 'answer[' . $atx_q_id . ']';
				?>
				<div class="atx-field" data-question-type="<?php echo esc_attr( $atx_q_type ); ?>">
					<?php if ( 'checkbox' === $atx_q_type ) : ?>
						<label>
							<input type="checkbox" name="<?php echo esc_attr( $atx_q_name ); ?>" value="1" <?php echo $atx_q_required ? 'required' : ''; ?>>
							<?php echo esc_html( $atx_q_label ); ?><?php echo $atx_q_required ? ' *' : ''; ?>
						</label>
					<?php else : ?>
						<label for="atx-q-<?php echo esc_attr( (string) $atx_q_id ); ?>">
							<?php echo esc_html( $atx_q_label ); ?><?php echo $atx_q_required ? ' *' : ''; ?>
						</label>
						<?php if ( 'textarea' === $atx_q_type ) : ?>
							<textarea id="atx-q-<?php echo esc_attr( (string) $atx_q_id ); ?>" name="<?php echo esc_attr( $atx_q_name ); ?>" rows="3" <?php echo $atx_q_required ? 'required' : ''; ?>></textarea>
						<?php elseif ( 'select' === $atx_q_type ) : ?>
							<select id="atx-q-<?php echo esc_attr( (string) $atx_q_id ); ?>" name="<?php echo esc_attr( $atx_q_name ); ?>" <?php echo $atx_q_required ? 'required' : ''; ?>>
								<option value=""><?php esc_html_e( 'Please choose…', 'atx-digital-ticketing-connect' ); ?></option>
								<?php foreach ( $atx_q_options as $atx_q_option ) : ?>
									<option value="<?php echo esc_attr( (string) $atx_q_option ); ?>"><?php echo esc_html( (string) $atx_q_option ); ?></option>
								<?php endforeach; ?>
							</select>
						<?php elseif ( 'radio' === $atx_q_type ) : ?>
							<?php foreach ( $atx_q_options as $atx_index => $atx_q_option ) : ?>
								<label class="atx-field__radio">
									<input type="radio" name="<?php echo esc_attr( $atx_q_name ); ?>" value="<?php echo esc_attr( (string) $atx_q_option ); ?>" <?php echo $atx_q_required && 0 === $atx_index ? 'required' : ''; ?>>
									<?php echo esc_html( (string) $atx_q_option ); ?>
								</label>
							<?php endforeach; ?>
						<?php else : ?>
							<input id="atx-q-<?php echo esc_attr( (string) $atx_q_id ); ?>" type="text" name="<?php echo esc_attr( $atx_q_name ); ?>" <?php echo $atx_q_required ? 'required' : ''; ?>>
						<?php endif; ?>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</fieldset>
	<?php endif; ?>

	<p class="atx-field atx-field--code">
		<label for="atx-discount"><?php esc_html_e( 'Discount code', 'atx-digital-ticketing-connect' ); ?></label>
		<input id="atx-discount" type="text" name="discount_code" autocomplete="off">
	</p>

	<p class="atx-ticket-form__actions">
		<button type="submit" class="atx-button atx-button--buy">
			<?php esc_html_e( 'Buy tickets', 'atx-digital-ticketing-connect' ); ?>
		</button>
		<span class="atx-ticket-form__busy" hidden><?php esc_html_e( 'Redirecting to secure checkout…', 'atx-digital-ticketing-connect' ); ?></span>
	</p>
</form>
