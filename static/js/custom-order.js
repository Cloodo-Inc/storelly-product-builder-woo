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

	var msg =
		( window.spbwcCustomOrder && window.spbwcCustomOrder.confirmDelete ) ||
		'Delete this saved design? This cannot be undone.';

	document.addEventListener( 'submit', function ( e ) {
		var form = e.target;
		if ( ! form || ! form.querySelector ) {
			return;
		}
		if ( ! form.querySelector( '.spbwc-saved-designs__delete' ) ) {
			return;
		}
		if ( ! window.confirm( msg ) ) {
			e.preventDefault();
		}
	} );
} )();
