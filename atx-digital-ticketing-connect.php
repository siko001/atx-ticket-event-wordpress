<?php
/**
 * Plugin Name:       ATX Digital Ticketing Connect
 * Plugin URI:        https://atxdigital.example/ticketing
 * Description:       Read-only mirror of events from the ATX Digital ticketing platform, with ticket checkout hand-off to Stripe. Events are managed in the Laravel admin — WordPress displays them.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            ATX Digital
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       atx-digital-ticketing-connect
 *
 * @package AtxDigitalTicketing
 */

defined( 'ABSPATH' ) || exit;

define( 'ATX_TICKETING_VERSION', '1.0.0' );
define( 'ATX_TICKETING_FILE', __FILE__ );
define( 'ATX_TICKETING_DIR', plugin_dir_path( __FILE__ ) );
define( 'ATX_TICKETING_URL', plugin_dir_url( __FILE__ ) );

// Composer autoloader when available, lightweight PSR-4 fallback otherwise.
if ( file_exists( ATX_TICKETING_DIR . 'vendor/autoload.php' ) ) {
	require ATX_TICKETING_DIR . 'vendor/autoload.php';
} else {
	spl_autoload_register(
		static function ( string $class ): void {
			if ( ! str_starts_with( $class, 'AtxDigitalTicketing\\' ) ) {
				return;
			}

			$relative = substr( $class, strlen( 'AtxDigitalTicketing\\' ) );
			$path     = ATX_TICKETING_DIR . 'src/' . str_replace( '\\', '/', $relative ) . '.php';

			if ( file_exists( $path ) ) {
				require $path;
			}
		}
	);
}

\AtxDigitalTicketing\Plugin::instance()->boot();

register_activation_hook(
	__FILE__,
	static function (): void {
		\AtxDigitalTicketing\PostTypes\EventPostType::register();
		flush_rewrite_rules();
	}
);

// Deactivation only clears rewrites — event data is never deleted.
register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );

// Self-updates from GitHub Releases (admin only; config in config/github-updater.php).
add_action(
	'plugins_loaded',
	static function (): void {
		if ( is_admin() && class_exists( \AtxDigitalTicketing\Support\GitHubPluginUpdater::class ) ) {
			( new \AtxDigitalTicketing\Support\GitHubPluginUpdater( __FILE__, __DIR__ ) )->register();
		}
	}
);

