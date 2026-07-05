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
		// Enqueue at the proper time (during wp_head) for any view that shows
		// events. Blocks/shortcodes render inside the_content — far too late to
		// enqueue their own CSS/JS into the head — so we detect them up front.
		add_action( 'wp_enqueue_scripts', [ self::class, 'maybe_enqueue_frontend_assets' ], 20 );
		// Style server-rendered block previews inside the editor (wp_enqueue_scripts
		// never runs in wp-admin, so the front-end handles are absent there).
		add_action( 'enqueue_block_assets', [ self::class, 'enqueue_editor_preview_assets' ] );
		add_action( 'update_option_atx_ticketing_settings', [ self::class, 'maybe_notify_mode_change' ], 10, 2 );
		add_action( 'admin_notices', [ self::class, 'test_mode_notice' ] );
		add_action( 'admin_init', [ self::class, 'maybe_reindex_after_update' ] );
	}

	/**
	 * After a plugin update, re-derive every mirrored event's structured meta
	 * and terms from its stored _atx_payload. This is a local refresh (no
	 * network) that populates any newly introduced meta keys, so a plugin
	 * update never leaves events showing stale/missing data until a manual
	 * "Sync now". Runs once per version.
	 */
	public static function maybe_reindex_after_update(): void {
		if ( get_option( 'atx_ticketing_indexed_version' ) === ATX_TICKETING_VERSION ) {
			return;
		}

		$ids = get_posts(
			[
				'post_type'      => \AtxDigitalTicketing\PostTypes\EventPostType::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => 1000, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- Bounded one-per-version reindex.
				'fields'         => 'ids',
			]
		);

		$upserter = new \AtxDigitalTicketing\Sync\EventUpserter();

		foreach ( $ids as $post_id ) {
			$upserter->reindex( (int) $post_id );
		}

		update_option( 'atx_ticketing_indexed_version', ATX_TICKETING_VERSION );
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

		wp_register_script(
			'atx-ticketing-gallery',
			ATX_TICKETING_URL . 'assets/js/gallery.js',
			[],
			ATX_TICKETING_VERSION,
			true
		);

		wp_register_script(
			'atx-ticketing-events-carousel',
			ATX_TICKETING_URL . 'assets/js/events-carousel.js',
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
	 * Front-of-site: enqueue the styles/scripts a page needs during wp_head,
	 * so they land in the document head/footer correctly. Runs whenever the
	 * current view shows events (event CPT single/archive, or a page/post whose
	 * content uses an ATX block or shortcode). The render callbacks still call
	 * the on-demand enqueues too, which is harmless (WP de-dupes handles).
	 */
	public static function maybe_enqueue_frontend_assets(): void {
		if ( ! self::current_view_has_events() ) {
			return;
		}

		self::enqueue_frontend_style();

		// The carousel script self-guards (no-ops without a carousel on the
		// page) and injects its own critical layout CSS, so a list/slider works
		// even when plugin styling is off.
		wp_enqueue_script( 'atx-ticketing-events-carousel' );

		// A single event also renders the buy form and the gallery lightbox.
		if ( is_singular( EventPostType::POST_TYPE ) ) {
			wp_enqueue_script( 'atx-ticketing-ticket-form' );
			wp_enqueue_script( 'atx-ticketing-gallery' );
		}
	}

	/**
	 * Load the front-end stylesheet (and carousel script) into the block editor
	 * so server-rendered previews of the ATX blocks look like the front end.
	 * Runs in the admin only; the front end is handled on wp_enqueue_scripts.
	 * The handles are registered here too because wp_enqueue_scripts — where
	 * register_assets() normally runs — does not fire in wp-admin.
	 */
	public static function enqueue_editor_preview_assets(): void {
		if ( ! is_admin() ) {
			return;
		}

		if ( ! wp_style_is( 'atx-ticketing-frontend', 'registered' ) ) {
			wp_register_style(
				'atx-ticketing-frontend',
				ATX_TICKETING_URL . 'assets/css/frontend.css',
				[],
				ATX_TICKETING_VERSION
			);
		}

		if ( ! wp_script_is( 'atx-ticketing-events-carousel', 'registered' ) ) {
			wp_register_script(
				'atx-ticketing-events-carousel',
				ATX_TICKETING_URL . 'assets/js/events-carousel.js',
				[],
				ATX_TICKETING_VERSION,
				true
			);
		}

		self::enqueue_frontend_style();
		wp_enqueue_script( 'atx-ticketing-events-carousel' );
	}

	/**
	 * Whether the current main view will display event content — the event CPT
	 * single/archive/category, or a singular post/page whose content contains
	 * one of the plugin's blocks or shortcodes.
	 */
	private static function current_view_has_events(): bool {
		if (
			is_singular( EventPostType::POST_TYPE )
			|| is_post_type_archive( EventPostType::POST_TYPE )
			|| is_tax( EventPostType::TAXONOMY )
		) {
			return true;
		}

		$post = get_post();

		if ( ! $post instanceof \WP_Post ) {
			return false;
		}

		if ( has_block( 'atx-ticketing/events', $post ) || has_block( 'atx-ticketing/featured-event', $post ) ) {
			return true;
		}

		foreach ( [ 'atx_events', 'atx_event', 'atx_featured_event' ] as $shortcode ) {
			if ( has_shortcode( (string) $post->post_content, $shortcode ) ) {
				return true;
			}
		}

		return false;
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
