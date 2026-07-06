<?php
/**
 * "How should we delete?" prompt shown on the Plugins screen.
 *
 * @package AtxDigitalTicketing
 */

namespace AtxDigitalTicketing\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * When the admin clicks "Deactivate" (or "Delete") on this plugin, a modal asks
 * what should happen to the site's event data. On Deactivate it offers three
 * choices — deactivate only, deactivate + delete keeping data, or deactivate +
 * delete removing all data. On Delete (plugin already inactive) it offers the
 * two data choices. The keep/remove decision is stored in the
 * atx_ticketing_delete_data_on_uninstall option, which uninstall.php reads. The
 * Tools tab exposes the same setting, so if the modal is ever bypassed the last
 * saved preference (default: keep) applies.
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
				'deactivateTitle' => __( 'Deactivate ATX Digital Ticketing Connect', 'atx-digital-ticketing-connect' ),
				'deleteTitle'     => __( 'Delete ATX Digital Ticketing Connect', 'atx-digital-ticketing-connect' ),
				'intro'           => __( 'Choose what should happen to the events and data this site has stored.', 'atx-digital-ticketing-connect' ),
				'cancel'          => __( 'Cancel', 'atx-digital-ticketing-connect' ),
				'working'         => __( 'Working…', 'atx-digital-ticketing-connect' ),
				'offTitle'        => __( 'Deactivate only — keep the plugin and all data', 'atx-digital-ticketing-connect' ),
				'offDesc'         => __( 'Turns the plugin off but leaves it installed and keeps every event and setting. Best for pausing (hibernating) your events for a while — reactivate any time with nothing to re-sync.', 'atx-digital-ticketing-connect' ),
				'delKeepTitle'    => __( 'Deactivate and delete the plugin — keep data', 'atx-digital-ticketing-connect' ),
				'delWipeTitle'    => __( 'Deactivate and delete the plugin — remove all data', 'atx-digital-ticketing-connect' ),
				'rmKeepTitle'     => __( 'Delete the plugin — keep data', 'atx-digital-ticketing-connect' ),
				'rmWipeTitle'     => __( 'Delete the plugin — remove all data', 'atx-digital-ticketing-connect' ),
				'keepDesc'        => __( 'Removes the plugin files but keeps mirrored events, categories, downloaded images and settings in the database, so you can reinstall or resume later without re-syncing.', 'atx-digital-ticketing-connect' ),
				'wipeDesc'        => __( 'Removes the plugin and permanently deletes all mirrored events (including past ones), their sponsors/speakers/locations, categories, downloaded media, logs and settings. No custom database tables are created, so nothing else is left behind. This cannot be undone.', 'atx-digital-ticketing-connect' ),
			],
		];
		?>
		<div id="atx-uninstall-modal" class="atx-uninstall-modal" hidden>
			<div class="atx-uninstall-modal__box" role="dialog" aria-modal="true" aria-labelledby="atx-uninstall-title">
				<h2 id="atx-uninstall-title"></h2>
				<p class="atx-uninstall-modal__intro"></p>
				<div class="atx-uninstall-modal__opts"></div>
				<p style="margin:1em 0 0;text-align:right;">
					<button type="button" class="button-link atx-uninstall-modal__cancel"></button>
				</p>
			</div>
		</div>
		<style>
			.atx-uninstall-modal{position:fixed;inset:0;z-index:100000;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.5);}
			.atx-uninstall-modal[hidden]{display:none;}
			.atx-uninstall-modal__box{background:#fff;border-radius:6px;max-width:540px;width:90%;padding:1.5em;box-shadow:0 5px 30px rgba(0,0,0,.3);max-height:90vh;overflow:auto;}
			.atx-uninstall-modal__box h2{margin-top:0;}
			.atx-uninstall-opt{display:block;width:100%;height:auto;text-align:left;padding:.7em .9em;margin-bottom:.6em;white-space:normal;cursor:pointer;}
			.atx-uninstall-opt strong{display:block;}
			.atx-uninstall-opt span{display:block;font-weight:400;opacity:.8;margin-top:.25em;}
			.atx-uninstall-opt--danger{color:#b32d2e;border-color:#b32d2e;}
		</style>
		<script>
		( function () {
			var cfg = <?php echo wp_json_encode( $config ); ?>;
			var modal = document.getElementById( 'atx-uninstall-modal' );
			if ( ! modal ) { return; }

			var titleEl  = modal.querySelector( '#atx-uninstall-title' );
			var introEl  = modal.querySelector( '.atx-uninstall-modal__intro' );
			var optsEl   = modal.querySelector( '.atx-uninstall-modal__opts' );
			var cancelEl = modal.querySelector( '.atx-uninstall-modal__cancel' );

			introEl.textContent = cfg.i18n.intro;

			var proceed = false;

			function go( href ) { proceed = true; window.location = href; }
			function closeModal() { modal.hidden = true; }

			function post( action, del ) {
				var body = new FormData();
				body.append( 'action', action );
				body.append( 'nonce', cfg.nonce );
				body.append( 'delete', del ? '1' : '0' );
				return fetch( cfg.ajax, { method: 'POST', credentials: 'same-origin', body: body } );
			}

			function busy() {
				optsEl.querySelectorAll( 'button' ).forEach( function ( b ) { b.disabled = true; } );
				cancelEl.textContent = cfg.i18n.working;
			}

			// Deactivate, then delete (server does both; runs uninstall.php).
			function deactivateDelete( del ) {
				busy();
				post( 'atx_ticketing_deactivate_delete', del )
					.then( function ( r ) { return r.json(); } )
					.then( function ( res ) {
						if ( res && res.data && res.data.redirect ) { go( res.data.redirect ); } else { window.location.reload(); }
					} )
					.catch( function () { window.location.reload(); } );
			}

			// Record the keep/remove choice, then follow WordPress's own delete link.
			function setPrefThenGo( del, href ) {
				busy();
				post( 'atx_ticketing_set_uninstall_pref', del ).then( function () { go( href ); } ).catch( function () { go( href ); } );
			}

			function option( title, desc, danger, handler ) {
				var btn = document.createElement( 'button' );
				btn.type = 'button';
				btn.className = 'button atx-uninstall-opt' + ( danger ? ' atx-uninstall-opt--danger' : '' );
				var t = document.createElement( 'strong' );
				t.textContent = title;
				var d = document.createElement( 'span' );
				d.textContent = desc;
				btn.appendChild( t );
				btn.appendChild( d );
				btn.addEventListener( 'click', handler );
				return btn;
			}

			function openModal( mode, href ) {
				optsEl.innerHTML = '';
				cancelEl.textContent = cfg.i18n.cancel;

				if ( 'deactivate' === mode ) {
					titleEl.textContent = cfg.i18n.deactivateTitle;
					optsEl.appendChild( option( cfg.i18n.offTitle, cfg.i18n.offDesc, false, function () { go( href ); } ) );
					optsEl.appendChild( option( cfg.i18n.delKeepTitle, cfg.i18n.keepDesc, false, function () { deactivateDelete( false ); } ) );
					optsEl.appendChild( option( cfg.i18n.delWipeTitle, cfg.i18n.wipeDesc, true, function () { deactivateDelete( true ); } ) );
				} else {
					titleEl.textContent = cfg.i18n.deleteTitle;
					optsEl.appendChild( option( cfg.i18n.rmKeepTitle, cfg.i18n.keepDesc, false, function () { setPrefThenGo( false, href ); } ) );
					optsEl.appendChild( option( cfg.i18n.rmWipeTitle, cfg.i18n.wipeDesc, true, function () { setPrefThenGo( true, href ); } ) );
				}

				modal.hidden = false;
			}

			// Intercept the Deactivate / Delete links for this plugin's row
			// (capture phase, so it runs before WordPress's own handlers).
			document.addEventListener( 'click', function ( e ) {
				if ( proceed ) { return; }
				var link = e.target.closest( 'a' );
				if ( ! link ) { return; }
				var row = link.closest( 'tr[data-plugin="' + cfg.plugin + '"]' );
				if ( ! row ) { return; }

				var href = link.href || '';
				var isDeactivate = ( link.id && 0 === link.id.indexOf( 'deactivate-' ) ) || /[?&]action=deactivate(&|$)/.test( href );
				var isDelete     = link.classList.contains( 'delete' ) || /[?&]action=delete-selected(&|$)/.test( href ) || /[?&]action=delete(&|$)/.test( href );

				if ( ! isDeactivate && ! isDelete ) { return; }

				e.preventDefault();
				e.stopImmediatePropagation();
				openModal( isDeactivate ? 'deactivate' : 'delete', href );
			}, true );

			cancelEl.addEventListener( 'click', closeModal );
			modal.addEventListener( 'click', function ( e ) { if ( e.target === modal ) { closeModal(); } } );
		} )();
		</script>
		<?php
	}
}
