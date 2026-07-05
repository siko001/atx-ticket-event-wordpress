<?php
/**
 * "How should we delete?" prompt shown on the Plugins screen.
 *
 * @package AtxDigitalTicketing
 */

namespace AtxDigitalTicketing\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * When the admin clicks "Delete" on this plugin, a modal asks whether to keep
 * or purge the site's event data first. The choice is stored in the
 * atx_ticketing_delete_data_on_uninstall option, which uninstall.php reads.
 * The Tools tab exposes the same setting, so this is purely a convenience — if
 * the modal is bypassed, the last saved preference (default: keep) applies.
 */
final class UninstallPrompt {

	public static function register(): void {
		add_action( 'admin_footer-plugins.php', [ self::class, 'render' ] );
	}

	public static function render(): void {
		if ( ! current_user_can( 'delete_plugins' ) ) {
			return;
		}

		$config = [
			'plugin' => plugin_basename( ATX_TICKETING_FILE ),
			'ajax'   => admin_url( 'admin-ajax.php' ),
			'nonce'  => wp_create_nonce( Tools::NONCE ),
			'i18n'   => [
				'title'     => __( 'Delete ATX Digital Ticketing Connect', 'atx-digital-ticketing-connect' ),
				'intro'     => __( 'What should happen to the events and data this site has stored?', 'atx-digital-ticketing-connect' ),
				'keepTitle' => __( 'Delete the plugin, keep all data', 'atx-digital-ticketing-connect' ),
				'keepDesc'  => __( 'Recommended. Mirrored events, categories, downloaded images and settings stay in the database so you can reinstall or resume later without re-syncing.', 'atx-digital-ticketing-connect' ),
				'wipeTitle' => __( 'Delete the plugin and all its data', 'atx-digital-ticketing-connect' ),
				'wipeDesc'  => __( 'Permanently removes all mirrored events (including past ones), their sponsors/speakers/locations, event categories, downloaded media, logs and settings. No custom database tables are created, so nothing else is left behind. This cannot be undone.', 'atx-digital-ticketing-connect' ),
				'cancel'    => __( 'Cancel', 'atx-digital-ticketing-connect' ),
			],
		];
		?>
		<div id="atx-uninstall-modal" class="atx-uninstall-modal" hidden>
			<div class="atx-uninstall-modal__box" role="dialog" aria-modal="true" aria-labelledby="atx-uninstall-title">
				<h2 id="atx-uninstall-title"></h2>
				<p class="atx-uninstall-modal__intro"></p>
				<button type="button" class="button button-secondary atx-uninstall-modal__keep" style="width:100%;height:auto;text-align:left;padding:.6em .9em;margin-bottom:.6em;white-space:normal;">
					<strong class="atx-uninstall-keep-title"></strong>
					<span class="atx-uninstall-keep-desc" style="display:block;font-weight:400;opacity:.8;"></span>
				</button>
				<button type="button" class="button atx-uninstall-modal__wipe" style="width:100%;height:auto;text-align:left;padding:.6em .9em;white-space:normal;color:#b32d2e;border-color:#b32d2e;">
					<strong class="atx-uninstall-wipe-title"></strong>
					<span class="atx-uninstall-wipe-desc" style="display:block;font-weight:400;opacity:.85;"></span>
				</button>
				<p style="margin:1em 0 0;text-align:right;">
					<button type="button" class="button-link atx-uninstall-modal__cancel"></button>
				</p>
			</div>
		</div>
		<style>
			.atx-uninstall-modal{position:fixed;inset:0;z-index:100000;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.5);}
			.atx-uninstall-modal__box{background:#fff;border-radius:6px;max-width:520px;width:90%;padding:1.5em;box-shadow:0 5px 30px rgba(0,0,0,.3);}
			.atx-uninstall-modal__box h2{margin-top:0;}
		</style>
		<script>
		( function () {
			var cfg = <?php echo wp_json_encode( $config ); ?>;
			var modal = document.getElementById( 'atx-uninstall-modal' );
			if ( ! modal ) { return; }

			modal.querySelector( '#atx-uninstall-title' ).textContent = cfg.i18n.title;
			modal.querySelector( '.atx-uninstall-modal__intro' ).textContent = cfg.i18n.intro;
			modal.querySelector( '.atx-uninstall-keep-title' ).textContent = cfg.i18n.keepTitle;
			modal.querySelector( '.atx-uninstall-keep-desc' ).textContent = cfg.i18n.keepDesc;
			modal.querySelector( '.atx-uninstall-wipe-title' ).textContent = cfg.i18n.wipeTitle;
			modal.querySelector( '.atx-uninstall-wipe-desc' ).textContent = cfg.i18n.wipeDesc;
			modal.querySelector( '.atx-uninstall-modal__cancel' ).textContent = cfg.i18n.cancel;

			var proceed = false;
			var pendingHref = '';

			function openModal( href ) { pendingHref = href; modal.hidden = false; }
			function closeModal() { modal.hidden = true; }

			function choose( del ) {
				var body = new FormData();
				body.append( 'action', 'atx_ticketing_set_uninstall_pref' );
				body.append( 'nonce', cfg.nonce );
				body.append( 'delete', del ? '1' : '0' );
				var go = function () { proceed = true; window.location = pendingHref; };
				fetch( cfg.ajax, { method: 'POST', credentials: 'same-origin', body: body } ).then( go ).catch( go );
			}

			// Intercept the Delete link for this plugin's row (capture phase, so
			// it runs before WordPress's own confirm/AJAX handler).
			document.addEventListener( 'click', function ( e ) {
				if ( proceed ) { return; }
				var link = e.target.closest( 'a' );
				if ( ! link ) { return; }
				var row = link.closest( 'tr[data-plugin="' + cfg.plugin + '"]' );
				if ( ! row ) { return; }
				if ( ! link.classList.contains( 'delete' ) && ! /action=delete/.test( link.href || '' ) ) { return; }
				e.preventDefault();
				e.stopImmediatePropagation();
				openModal( link.href );
			}, true );

			modal.querySelector( '.atx-uninstall-modal__keep' ).addEventListener( 'click', function () { choose( false ); } );
			modal.querySelector( '.atx-uninstall-modal__wipe' ).addEventListener( 'click', function () { choose( true ); } );
			modal.querySelector( '.atx-uninstall-modal__cancel' ).addEventListener( 'click', closeModal );
			modal.addEventListener( 'click', function ( e ) { if ( e.target === modal ) { closeModal(); } } );
		} )();
		</script>
		<?php
	}
}
