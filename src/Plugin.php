<?php
/**
 * Plugin orchestrator.
 *
 * @package AtxDigitalTicketing
 */

namespace AtxDigitalTicketing;

use AtxDigitalTicketing\Admin\SettingsPage;
use AtxDigitalTicketing\Admin\Tools;
use AtxDigitalTicketing\Frontend\Block;
use AtxDigitalTicketing\Frontend\CheckoutProxy;
use AtxDigitalTicketing\Frontend\Shortcodes;
use AtxDigitalTicketing\Frontend\TemplateOverride;
use AtxDigitalTicketing\PostTypes\EventPostType;
use AtxDigitalTicketing\Rest\WebhookController;

defined( 'ABSPATH' ) || exit;

/**
 * Wires all plugin components into WordPress hooks.
 */
final class Plugin {

	private static ?Plugin $instance = null;

	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Set while applying a mode change that came FROM Laravel, so the
	 * update_option hook does not echo it straight back.
	 */
	public static bool $suppress_mode_notify = false;

	public function boot(): void {
		add_action( 'init', [ EventPostType::class, 'register' ] );
		add_action( 'init', [ Block::class, 'register' ] );
		add_action( 'init', [ Shortcodes::class, 'register' ] );
		TemplateOverride::register();
		add_action( 'rest_api_init', [ WebhookController::class, 'register_routes' ] );
		add_action( 'rest_api_init', [ CheckoutProxy::class, 'register_routes' ] );
		add_action( 'admin_menu', [ SettingsPage::class, 'register_menu' ] );
		add_action( 'admin_init', [ SettingsPage::class, 'register_settings' ] );
		add_action( 'admin_enqueue_scripts', [ SettingsPage::class, 'enqueue_assets' ] );
		Tools::register();
		add_action( 'add_meta_boxes', [ EventPostType::class, 'register_meta_boxes' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'register_assets' ] );
		add_action( 'update_option_atx_ticketing_settings', [ self::class, 'maybe_notify_mode_change' ], 10, 2 );
		add_action( 'admin_notices', [ self::class, 'test_mode_notice' ] );
	}

	/**
	 * WP → Laravel: tell the platform when test mode is toggled locally.
	 *
	 * @param mixed $old_value Previous option value.
	 * @param mixed $value     New option value.
	 */
	public static function maybe_notify_mode_change( $old_value, $value ): void {
		if ( self::$suppress_mode_notify ) {
			return;
		}

		$old_mode = ! empty( is_array( $old_value ) ? ( $old_value['test_mode'] ?? 0 ) : 0 );
		$new_mode = ! empty( is_array( $value ) ? ( $value['test_mode'] ?? 0 ) : 0 );

		if ( $old_mode === $new_mode ) {
			return;
		}

		$settings = self::settings();
		$base     = untrailingslashit( $settings['api_base_url'] );

		if ( '' === $base || '' === $settings['webhook_secret'] ) {
			return;
		}

		$body = (string) wp_json_encode( [ 'test_mode' => $new_mode ] );

		$response = wp_remote_post(
			$base . '/api/ticketing/wp/mode',
			[
				'timeout' => 15,
				'headers' => array_merge(
					\AtxDigitalTicketing\Support\Signature::headers( $settings['webhook_secret'], $body ),
					[ 'Content-Type' => 'application/json' ]
				),
				'body'    => $body,
			]
		);

		\AtxDigitalTicketing\Support\Logger::log(
			'sync',
			is_wp_error( $response )
				? sprintf( 'Could not tell Laravel about the mode change: %s', $response->get_error_message() )
				: sprintf( 'Switched to %s mode (Laravel notified).', $new_mode ? 'TEST' : 'live' ),
			$new_mode ? 'warning' : 'info'
		);
	}

	/**
	 * Loud admin notice while the connection is in test mode.
	 */
	public static function test_mode_notice(): void {
		if ( empty( self::settings()['test_mode'] ) ) {
			return;
		}

		echo '<div class="notice notice-warning" style="border-left-width:6px;font-weight:600;">'
			. '<p style="font-size:14px;">⚠️ '
			. esc_html__( 'ATX Ticketing is in TEST MODE — ticket sales on this site are for testing. Visitors see a test banner on the buy form.', 'atx-digital-ticketing-connect' )
			. ' <a href="' . esc_url( admin_url( 'edit.php?post_type=atx_event&page=atx-ticketing' ) ) . '">'
			. esc_html__( 'Settings', 'atx-digital-ticketing-connect' ) . '</a></p></div>';
	}

	/**
	 * Assets are registered globally but only enqueued from the templates
	 * that actually need them.
	 */
	public function register_assets(): void {
		wp_register_style(
			'atx-ticketing-frontend',
			ATX_TICKETING_URL . 'assets/css/frontend.css',
			[],
			ATX_TICKETING_VERSION
		);

		wp_register_script(
			'atx-ticketing-ticket-form',
			ATX_TICKETING_URL . 'assets/js/ticket-form.js',
			[],
			ATX_TICKETING_VERSION,
			true
		);

		wp_localize_script(
			'atx-ticketing-ticket-form',
			'atxTicketing',
			[
				'checkoutEndpoint' => esc_url_raw( rest_url( 'atx-ticketing/v1/checkout' ) ),
				'nonce'            => wp_create_nonce( 'wp_rest' ),
			]
		);
	}

	/**
	 * Enqueue the plugin's front-end stylesheet unless "Use plugin styling"
	 * is switched off (custom/theme styling takes over entirely).
	 */
	public static function enqueue_frontend_style(): void {
		if ( ! empty( self::settings()['use_plugin_styles'] ) ) {
			wp_enqueue_style( 'atx-ticketing-frontend' );
		}
	}

	/**
	 * Plugin settings with defaults.
	 *
	 * @return array{api_base_url: string, webhook_secret: string, success_page_id: int, cancel_page_id: int, admin_url: string, use_plugin_templates: int, use_plugin_styles: int, test_mode: int}
	 */
	public static function settings(): array {
		$defaults = [
			'api_base_url'         => '',
			'webhook_secret'       => '',
			'success_page_id'      => 0,
			'cancel_page_id'       => 0,
			'admin_url'            => '',
			'use_plugin_templates' => 1,
			'use_plugin_styles'    => 1,
			'test_mode'            => 0,
		];

		$stored = get_option( 'atx_ticketing_settings', [] );

		return array_merge( $defaults, is_array( $stored ) ? $stored : [] );
	}
}
