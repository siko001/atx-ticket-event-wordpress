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

	public function boot(): void {
		add_action( 'init', [ EventPostType::class, 'register' ] );
		add_action( 'init', [ Block::class, 'register' ] );
		add_action( 'init', [ Shortcodes::class, 'register' ] );
		add_action( 'rest_api_init', [ WebhookController::class, 'register_routes' ] );
		add_action( 'rest_api_init', [ CheckoutProxy::class, 'register_routes' ] );
		add_action( 'admin_menu', [ SettingsPage::class, 'register_menu' ] );
		add_action( 'admin_init', [ SettingsPage::class, 'register_settings' ] );
		add_action( 'admin_enqueue_scripts', [ SettingsPage::class, 'enqueue_assets' ] );
		Tools::register();
		add_action( 'add_meta_boxes', [ EventPostType::class, 'register_meta_boxes' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'register_assets' ] );
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
	 * Plugin settings with defaults.
	 *
	 * @return array{api_base_url: string, webhook_secret: string, success_page_id: int, cancel_page_id: int, admin_url: string}
	 */
	public static function settings(): array {
		$defaults = [
			'api_base_url'    => '',
			'webhook_secret'  => '',
			'success_page_id' => 0,
			'cancel_page_id'  => 0,
			'admin_url'       => '',
		];

		$stored = get_option( 'atx_ticketing_settings', [] );

		return array_merge( $defaults, is_array( $stored ) ? $stored : [] );
	}
}
