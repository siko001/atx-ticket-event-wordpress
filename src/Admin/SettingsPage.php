<?php
/**
 * Settings screen (Settings API, no dependencies).
 *
 * @package AtxDigitalTicketing
 */

namespace AtxDigitalTicketing\Admin;

use AtxDigitalTicketing\Plugin;
use AtxDigitalTicketing\PostTypes\EventPostType;
use AtxDigitalTicketing\Support\Logger;

defined( 'ABSPATH' ) || exit;

/**
 * Events → Settings: a tabbed screen (Connection / Pages / Tools) for the
 * Laravel connection, checkout pages and sync tools.
 */
final class SettingsPage {

	private const OPTION = 'atx_ticketing_settings';
	private const SLUG   = 'atx-ticketing';

	public static function register_menu(): void {
		add_submenu_page(
			'edit.php?post_type=' . EventPostType::POST_TYPE,
			__( 'ATX Ticketing settings', 'atx-digital-ticketing-connect' ),
			__( 'Settings', 'atx-digital-ticketing-connect' ),
			'manage_options',
			self::SLUG,
			[ self::class, 'render' ]
		);
	}

	public static function register_settings(): void {
		register_setting(
			'atx_ticketing',
			self::OPTION,
			[
				'type'              => 'array',
				'sanitize_callback' => [ self::class, 'sanitize' ],
			]
		);

		// Connection tab.
		add_settings_section( 'atx_ticketing_connection', '', '__return_false', 'atx-ticketing-connection' );
		add_settings_field( 'api_base_url', __( 'Laravel API base URL', 'atx-digital-ticketing-connect' ), [ self::class, 'field_api_base_url' ], 'atx-ticketing-connection', 'atx_ticketing_connection' );
		add_settings_field( 'webhook_secret', __( 'Webhook shared secret', 'atx-digital-ticketing-connect' ), [ self::class, 'field_webhook_secret' ], 'atx-ticketing-connection', 'atx_ticketing_connection' );
		add_settings_field( 'admin_url', __( 'ATX admin panel URL', 'atx-digital-ticketing-connect' ), [ self::class, 'field_admin_url' ], 'atx-ticketing-connection', 'atx_ticketing_connection' );
		add_settings_field( 'test_mode', __( 'Test mode', 'atx-digital-ticketing-connect' ), [ self::class, 'field_test_mode' ], 'atx-ticketing-connection', 'atx_ticketing_connection' );

		// Display tab.
		add_settings_section( 'atx_ticketing_display', '', '__return_false', 'atx-ticketing-display' );
		add_settings_field( 'use_plugin_templates', __( 'Event page templates', 'atx-digital-ticketing-connect' ), [ self::class, 'field_use_plugin_templates' ], 'atx-ticketing-display', 'atx_ticketing_display' );
		add_settings_field( 'use_plugin_styles', __( 'Styling', 'atx-digital-ticketing-connect' ), [ self::class, 'field_use_plugin_styles' ], 'atx-ticketing-display', 'atx_ticketing_display' );

		// Pages tab.
		add_settings_section( 'atx_ticketing_pages', '', '__return_false', 'atx-ticketing-pages' );
		add_settings_field( 'success_page_id', __( 'Checkout success page', 'atx-digital-ticketing-connect' ), [ self::class, 'field_success_page' ], 'atx-ticketing-pages', 'atx_ticketing_pages' );
		add_settings_field( 'cancel_page_id', __( 'Checkout cancel page', 'atx-digital-ticketing-connect' ), [ self::class, 'field_cancel_page' ], 'atx-ticketing-pages', 'atx_ticketing_pages' );
	}

	/**
	 * Each tab posts only its own fields, so merge with the stored settings
	 * instead of wiping the other tab's values.
	 *
	 * @param mixed $input Raw option input.
	 * @return array<string, mixed>
	 */
	public static function sanitize( $input ): array {
		$input   = (array) $input;
		$current = Plugin::settings();

		return [
			'api_base_url'         => array_key_exists( 'api_base_url', $input )
				? esc_url_raw( untrailingslashit( (string) $input['api_base_url'] ) )
				: $current['api_base_url'],
			'webhook_secret'       => array_key_exists( 'webhook_secret', $input )
				? sanitize_text_field( (string) $input['webhook_secret'] )
				: $current['webhook_secret'],
			'admin_url'            => array_key_exists( 'admin_url', $input )
				? esc_url_raw( (string) $input['admin_url'] )
				: $current['admin_url'],
			'success_page_id'      => array_key_exists( 'success_page_id', $input )
				? absint( $input['success_page_id'] )
				: (int) $current['success_page_id'],
			'cancel_page_id'       => array_key_exists( 'cancel_page_id', $input )
				? absint( $input['cancel_page_id'] )
				: (int) $current['cancel_page_id'],
			'use_plugin_templates' => array_key_exists( 'use_plugin_templates', $input )
				? (int) (bool) $input['use_plugin_templates']
				: (int) $current['use_plugin_templates'],
			'use_plugin_styles'    => array_key_exists( 'use_plugin_styles', $input )
				? (int) (bool) $input['use_plugin_styles']
				: (int) $current['use_plugin_styles'],
			'test_mode'            => array_key_exists( 'test_mode', $input )
				? (int) (bool) $input['test_mode']
				: (int) $current['test_mode'],
		];
	}

	private static function current_tab(): string {
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'connection'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return in_array( $tab, [ 'connection', 'display', 'pages', 'tools', 'logs' ], true ) ? $tab : 'connection';
	}

	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$tab  = self::current_tab();
		$base = admin_url( 'edit.php?post_type=' . EventPostType::POST_TYPE . '&page=' . self::SLUG );
		$tabs = [
			'connection' => __( 'Connection', 'atx-digital-ticketing-connect' ),
			'display'    => __( 'Display', 'atx-digital-ticketing-connect' ),
			'pages'      => __( 'Pages', 'atx-digital-ticketing-connect' ),
			'tools'      => __( 'Tools', 'atx-digital-ticketing-connect' ),
			'logs'       => __( 'Logs', 'atx-digital-ticketing-connect' ),
		];

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'ATX Ticketing', 'atx-digital-ticketing-connect' ); ?></h1>

			<nav class="nav-tab-wrapper" style="margin-bottom:1em;">
				<?php foreach ( $tabs as $key => $label ) : ?>
					<a href="<?php echo esc_url( add_query_arg( 'tab', $key, $base ) ); ?>"
						class="nav-tab <?php echo $tab === $key ? 'nav-tab-active' : ''; ?>">
						<?php echo esc_html( $label ); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<?php
			if ( 'tools' === $tab ) {
				self::render_tools_tab();
			} elseif ( 'logs' === $tab ) {
				self::render_logs_tab();
			} else {
				self::render_settings_tab( $tab );
			}
			?>
		</div>
		<?php
	}

	private static function render_settings_tab( string $tab ): void {
		if ( 'connection' === $tab ) {
			?>
			<p>
				<?php esc_html_e( 'Incoming webhook endpoint (configure this as TICKETING_WP_WEBHOOK_URL on the Laravel side):', 'atx-digital-ticketing-connect' ); ?>
				<code><?php echo esc_html( rest_url( 'atx-ticketing/v1/webhook' ) ); ?></code>
			</p>
			<?php
		}

		if ( 'pages' === $tab ) {
			$settings       = Plugin::settings();
			$page_usable    = static fn ( int $page_id ): bool => $page_id > 0 && 'page' === get_post_type( $page_id ) && 'trash' !== get_post_status( $page_id );
			$events_page_id = (int) get_option( 'atx_ticketing_events_page_id', 0 );
			$all_exist      = $page_usable( $events_page_id )
				&& $page_usable( (int) $settings['success_page_id'] )
				&& $page_usable( (int) $settings['cancel_page_id'] );

			?>
			<p><?php esc_html_e( 'Buyers land on these pages after Stripe checkout.', 'atx-digital-ticketing-connect' ); ?></p>
			<?php if ( ! $all_exist ) : ?>
				<p>
					<button type="button" class="button atx-tool" data-action="atx_ticketing_create_pages">
						<?php esc_html_e( 'Create default pages', 'atx-digital-ticketing-connect' ); ?>
					</button>
					<span class="atx-tool-result" aria-live="polite"></span>
				</p>
				<p class="description"><?php esc_html_e( 'Creates whichever of the defaults are missing: an Events listing page, a checkout success page and a cancel page.', 'atx-digital-ticketing-connect' ); ?></p>
			<?php endif; ?>
			<hr>
			<?php
		}

		?>
		<form action="options.php" method="post">
			<?php
			settings_fields( 'atx_ticketing' );
			do_settings_sections( 'atx-ticketing-' . $tab );
			submit_button();
			?>
		</form>
		<?php

		if ( 'connection' === $tab ) {
			?>
			<hr>
			<p>
				<button type="button" class="button button-secondary atx-tool" data-action="atx_ticketing_test_connection">
					<?php esc_html_e( 'Test connection', 'atx-digital-ticketing-connect' ); ?>
				</button>
				<span class="atx-tool-result" aria-live="polite"></span>
			</p>
			<p class="description"><?php esc_html_e( 'Tests the saved values: reaches the Laravel app and verifies the shared secret. Save your changes first.', 'atx-digital-ticketing-connect' ); ?></p>
			<?php
		}
	}

	private static function render_tools_tab(): void {
		$last = get_option( 'atx_ticketing_last_sync', [] );

		?>
		<h2><?php esc_html_e( 'Sync events from Laravel', 'atx-digital-ticketing-connect' ); ?></h2>
		<p><?php esc_html_e( 'Events normally arrive automatically when they are published or updated in the ATX admin. Use this to pull everything at once — for the first install, after downtime, or if something looks out of date.', 'atx-digital-ticketing-connect' ); ?></p>
		<p>
			<button type="button" class="button button-primary atx-tool" data-action="atx_ticketing_sync">
				<?php esc_html_e( 'Sync now', 'atx-digital-ticketing-connect' ); ?>
			</button>
			<span class="atx-tool-result" aria-live="polite"></span>
		</p>
		<?php if ( is_array( $last ) && ! empty( $last['time'] ) ) : ?>
			<p class="description">
				<?php
				printf(
					/* translators: 1: date/time, 2: number of events. */
					esc_html__( 'Last sync: %1$s — %2$d event(s).', 'atx-digital-ticketing-connect' ),
					esc_html( wp_date( (string) get_option( 'date_format' ) . ' ' . (string) get_option( 'time_format' ), (int) $last['time'] ) ),
					(int) ( $last['synced'] ?? 0 )
				);
				?>
			</p>
		<?php endif; ?>
		<?php
	}

	private static function render_logs_tab(): void {
		$entries = Logger::entries();

		?>
		<h2><?php esc_html_e( 'Activity logs', 'atx-digital-ticketing-connect' ); ?></h2>
		<p><?php esc_html_e( 'Sync traffic from the ATX platform and ticket checkout activity on this site. The ATX admin keeps its own copy under System → Logs.', 'atx-digital-ticketing-connect' ); ?></p>
		<p>
			<button type="button" class="button atx-tool" data-action="atx_ticketing_clear_logs">
				<?php esc_html_e( 'Clear logs', 'atx-digital-ticketing-connect' ); ?>
			</button>
			<span class="atx-tool-result" aria-live="polite"></span>
		</p>

		<?php if ( ! $entries ) : ?>
			<p><em><?php esc_html_e( 'No activity yet — publish an event in the ATX admin or run a sync.', 'atx-digital-ticketing-connect' ); ?></em></p>
			<?php return; ?>
		<?php endif; ?>

		<table class="widefat striped" style="max-width:1000px;">
			<thead>
				<tr>
					<th style="width:170px;"><?php esc_html_e( 'When', 'atx-digital-ticketing-connect' ); ?></th>
					<th style="width:80px;"><?php esc_html_e( 'Type', 'atx-digital-ticketing-connect' ); ?></th>
					<th><?php esc_html_e( 'Message', 'atx-digital-ticketing-connect' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $entries as $entry ) : ?>
					<tr>
						<td><?php echo esc_html( wp_date( 'j M Y, H:i:s', (int) ( $entry['time'] ?? 0 ) ) ); ?></td>
						<td>
							<?php
							$level   = (string) ( $entry['level'] ?? 'info' );
							$colours = [
								'error'   => '#b32d2e',
								'warning' => '#996800',
								'info'    => '#2271b1',
							];
							?>
							<strong style="color:<?php echo esc_attr( $colours[ $level ] ?? '#2271b1' ); ?>;">
								<?php echo esc_html( (string) ( $entry['channel'] ?? '' ) ); ?>
							</strong>
						</td>
						<td><?php echo esc_html( (string) ( $entry['message'] ?? '' ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	public static function enqueue_assets( string $hook_suffix ): void {
		if ( EventPostType::POST_TYPE . '_page_' . self::SLUG !== $hook_suffix ) {
			return;
		}

		wp_enqueue_script(
			'atx-ticketing-admin-settings',
			ATX_TICKETING_URL . 'assets/js/admin-settings.js',
			[],
			ATX_TICKETING_VERSION,
			true
		);

		wp_localize_script(
			'atx-ticketing-admin-settings',
			'atxTicketingAdmin',
			[
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'nonce'          => wp_create_nonce( Tools::NONCE ),
				'regenerateWarn' => __( 'Regenerating will break the connection until you copy the new secret into TICKETING_WP_WEBHOOK_SECRET in the Laravel .env (then run php artisan config:clear). Continue?', 'atx-digital-ticketing-connect' ),
				'copyHint'       => __( 'New secret generated. Click "Save Changes", then copy it into TICKETING_WP_WEBHOOK_SECRET in the Laravel .env.', 'atx-digital-ticketing-connect' ),
				'workingLabel'   => __( 'Working…', 'atx-digital-ticketing-connect' ),
			]
		);
	}

	public static function field_use_plugin_templates(): void {
		printf(
			'<input type="hidden" name="%1$s[use_plugin_templates]" value="0"><label><input type="checkbox" name="%1$s[use_plugin_templates]" value="1" %2$s> %3$s</label>',
			esc_attr( self::OPTION ),
			checked( ! empty( Plugin::settings()['use_plugin_templates'] ), true, false ),
			esc_html__( 'Use the plugin templates for the events archive and single event pages', 'atx-digital-ticketing-connect' )
		);
		echo '<p class="description">' . esc_html__( 'Overrides the theme\'s generic templates on /events/ and event pages — useful when the theme hides or minimises the event details. Individual templates can still be customised by copying them to {your-theme}/atx-ticketing/. Turn off to let the theme handle those pages entirely.', 'atx-digital-ticketing-connect' ) . '</p>';
	}

	public static function field_use_plugin_styles(): void {
		printf(
			'<input type="hidden" name="%1$s[use_plugin_styles]" value="0"><label><input type="checkbox" name="%1$s[use_plugin_styles]" value="1" %2$s> %3$s</label>',
			esc_attr( self::OPTION ),
			checked( ! empty( Plugin::settings()['use_plugin_styles'] ), true, false ),
			esc_html__( 'Use the plugin\'s built-in styling', 'atx-digital-ticketing-connect' )
		);
		echo '<p class="description">' . esc_html__( 'Untick to stop loading the plugin stylesheet and style the event cards, forms and buttons yourself (classes are prefixed atx-).', 'atx-digital-ticketing-connect' ) . '</p>';
	}

	public static function field_test_mode(): void {
		printf(
			'<input type="hidden" name="%1$s[test_mode]" value="0"><label><input type="checkbox" name="%1$s[test_mode]" value="1" %2$s> <strong>%3$s</strong></label>',
			esc_attr( self::OPTION ),
			checked( ! empty( Plugin::settings()['test_mode'] ), true, false ),
			esc_html__( 'This site is in test mode', 'atx-digital-ticketing-connect' )
		);
		echo '<p class="description">' . esc_html__( 'Shows a prominent warning in this dashboard and a banner on the ticket form. Saving also updates the connection in the ATX admin — and toggling it there updates this site.', 'atx-digital-ticketing-connect' ) . '</p>';
	}

	public static function field_api_base_url(): void {
		printf(
			'<input type="url" class="regular-text code" name="%s[api_base_url]" value="%s" placeholder="https://tickets.example.com">',
			esc_attr( self::OPTION ),
			esc_attr( Plugin::settings()['api_base_url'] )
		);
		echo '<p class="description">' . esc_html__( 'The Laravel application root, without a trailing slash.', 'atx-digital-ticketing-connect' ) . '</p>';
	}

	public static function field_webhook_secret(): void {
		printf(
			'<input type="password" id="atx-webhook-secret" class="regular-text code" name="%s[webhook_secret]" value="%s" autocomplete="new-password">',
			esc_attr( self::OPTION ),
			esc_attr( Plugin::settings()['webhook_secret'] )
		);
		printf(
			' <button type="button" class="button" id="atx-generate-secret">%s</button>',
			esc_html__( 'Generate', 'atx-digital-ticketing-connect' )
		);
		printf(
			' <button type="button" class="button" id="atx-toggle-secret">%s</button>',
			esc_html__( 'Show', 'atx-digital-ticketing-connect' )
		);
		echo '<p class="description">' . esc_html__( 'Must match TICKETING_WP_WEBHOOK_SECRET in the Laravel .env. Generate one here, save, and copy it there.', 'atx-digital-ticketing-connect' ) . '</p>';
		echo '<p class="description atx-secret-hint" style="color:#996800;display:none;"></p>';
	}

	public static function field_admin_url(): void {
		printf(
			'<input type="url" class="regular-text code" name="%s[admin_url]" value="%s" placeholder="https://tickets.example.com/admin">',
			esc_attr( self::OPTION ),
			esc_attr( Plugin::settings()['admin_url'] )
		);
		echo '<p class="description">' . esc_html__( 'Used for the "Edit in ATX admin" links shown on mirrored events.', 'atx-digital-ticketing-connect' ) . '</p>';
	}

	public static function field_success_page(): void {
		wp_dropdown_pages(
			[
				'name'              => esc_attr( self::OPTION . '[success_page_id]' ),
				'selected'          => (int) Plugin::settings()['success_page_id'],
				'show_option_none'  => esc_html__( '— Use Laravel default —', 'atx-digital-ticketing-connect' ),
				'option_none_value' => '0',
			]
		);
	}

	public static function field_cancel_page(): void {
		wp_dropdown_pages(
			[
				'name'              => esc_attr( self::OPTION . '[cancel_page_id]' ),
				'selected'          => (int) Plugin::settings()['cancel_page_id'],
				'show_option_none'  => esc_html__( '— Use Laravel default —', 'atx-digital-ticketing-connect' ),
				'option_none_value' => '0',
			]
		);
	}
}
