/**
 * Custom Order — My Account progressive enhancement.
 *
 * Adds a confirm() guard to the "Delete" button on the Saved designs tab (D4).
 * The server still nonce-checks and ownership-checks; this only prevents an
 * accidental one-click delete. No dependencies.
 *
 * @package Storelly
 */
( function () {
	'use strict';

	var cfg = window.spbwcCustomOrder || {};
	var msg = cfg.confirmDelete || 'Delete this saved design? This cannot be undone.';

	document.addEventListener( 'submit', function ( e ) {
		var form = e.target;
		if ( ! form || ! form.querySelector ) {
			return;
		}
		if ( ! form.querySelector( '.spbwc-saved-designs__delete' ) ) {
			return;
		}
		// Let the confirmed re-submit pass straight through.
		if ( form._spbwcConfirmed ) {
			return;
		}
		// Native fallback when the styled dialog isn't available.
		if ( ! window.spbwcDialog ) {
			if ( ! window.confirm( msg ) ) {
				e.preventDefault();
			}
			return;
		}
		e.preventDefault();
		window.spbwcDialog.confirm( {
			title: cfg.confirmTitle || '',
			message: msg,
			tone: 'danger',
			okText: cfg.confirmOk || ''
		} ).then( function ( ok ) {
			if ( ! ok ) {
				return;
			}
			form._spbwcConfirmed = true;
			if ( typeof form.requestSubmit === 'function' ) {
				form.requestSubmit();
			} else {
				form.submit();
			}
		} );
	} );
} )();
