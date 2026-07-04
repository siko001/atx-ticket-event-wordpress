/**
 * Settings screen helpers: secret generation and the AJAX tool buttons
 * (test connection / sync now / create default pages).
 */
( function () {
	'use strict';

	var config = window.atxTicketingAdmin || {};

	function randomSecret() {
		var bytes = new Uint8Array( 32 );
		window.crypto.getRandomValues( bytes );

		return Array.prototype.map
			.call( bytes, function ( b ) {
				return ( '0' + b.toString( 16 ) ).slice( -2 );
			} )
			.join( '' );
	}

	function setupSecretField() {
		var input = document.getElementById( 'atx-webhook-secret' );
		var generate = document.getElementById( 'atx-generate-secret' );
		var toggle = document.getElementById( 'atx-toggle-secret' );
		var hint = document.querySelector( '.atx-secret-hint' );

		if ( ! input ) {
			return;
		}

		if ( generate ) {
			generate.addEventListener( 'click', function () {
				if ( input.value !== '' && ! window.confirm( config.regenerateWarn ) ) {
					return;
				}

				input.value = randomSecret();
				input.type = 'text';

				if ( hint ) {
					hint.textContent = config.copyHint;
					hint.style.display = 'block';
				}
			} );
		}

		if ( toggle ) {
			toggle.addEventListener( 'click', function () {
				input.type = input.type === 'password' ? 'text' : 'password';
				toggle.textContent = input.type === 'password' ? 'Show' : 'Hide';
			} );
		}
	}

	function setupToolButtons() {
		document.querySelectorAll( '.atx-tool' ).forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				var result = button.parentElement.querySelector( '.atx-tool-result' );
				var label = button.textContent;

				button.disabled = true;
				button.textContent = config.workingLabel || '…';

				if ( result ) {
					result.textContent = '';
					result.style.color = '';
				}

				var body = new window.FormData();
				body.append( 'action', button.dataset.action );
				body.append( 'nonce', config.nonce );

				window
					.fetch( config.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
					.then( function ( response ) {
						return response.json();
					} )
					.then( function ( payload ) {
						var ok = payload && payload.success;
						var message =
							payload && payload.data && payload.data.message
								? payload.data.message
								: 'Unexpected response.';

						if ( result ) {
							result.textContent = ( ok ? '✓ ' : '✗ ' ) + message;
							result.style.color = ok ? '#00753b' : '#b32d2e';
						}

						if ( ok && payload.data && payload.data.reload ) {
							window.setTimeout( function () {
								window.location.reload();
							}, 1500 );
						}
					} )
					.catch( function ( error ) {
						if ( result ) {
							result.textContent = '✗ ' + error;
							result.style.color = '#b32d2e';
						}
					} )
					.finally( function () {
						button.disabled = false;
						button.textContent = label;
					} );
			} );
		} );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		setupSecretField();
		setupToolButtons();
	} );
} )();
