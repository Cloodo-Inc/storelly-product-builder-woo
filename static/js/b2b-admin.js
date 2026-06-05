/**
 * Storelly B2B admin — "Upgrade a customer" picker (B2B Companies hub).
 *
 * Opens a modal, searches WooCommerce customers not already in a company via
 * AJAX, and jumps to the upgrade form for the chosen customer. Vanilla JS, no
 * dependencies. Data + nonce come from wp_localize_script (spbwcB2BAdmin).
 */
( function () {
	'use strict';

	var cfg = window.spbwcB2BAdmin || {};
	var modal = document.getElementById( 'spbwc-b2b-picker' );
	if ( ! modal ) {
		return;
	}

	var input = modal.querySelector( '.js-spbwc-picker-input' );
	var list = modal.querySelector( '.js-spbwc-picker-results' );
	var timer = null;
	var lastTerm = null;

	function open() {
		modal.hidden = false;
		document.body.classList.add( 'spbwc-modal-open' );
		if ( input ) {
			input.value = '';
			input.focus();
		}
		if ( list ) {
			list.innerHTML = '';
		}
	}

	function close() {
		modal.hidden = true;
		document.body.classList.remove( 'spbwc-modal-open' );
	}

	function esc( s ) {
		var d = document.createElement( 'div' );
		d.textContent = s == null ? '' : String( s );
		return d.innerHTML;
	}

	function render( results ) {
		if ( ! list ) {
			return;
		}
		if ( ! results || ! results.length ) {
			list.innerHTML = '<li class="spbwc-picker__empty">' + esc( ( cfg.i18n && cfg.i18n.empty ) || 'No results' ) + '</li>';
			return;
		}
		list.innerHTML = results.map( function ( r ) {
			var initials = ( r.name || '?' ).trim().split( /\s+/ ).slice( 0, 2 ).map( function ( p ) {
				return p.charAt( 0 );
			} ).join( '' ).toUpperCase();
			return '<li><a class="spbwc-picker__item" href="' + esc( r.url ) + '">' +
				'<span class="spbwc-avatar spbwc-avatar--sm">' + esc( initials ) + '</span>' +
				'<span class="spbwc-picker__meta"><strong>' + esc( r.name ) + '</strong>' +
				'<span class="spbwc-muted-sm">' + esc( r.email ) + '</span></span>' +
				'<span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span></a></li>';
		} ).join( '' );
	}

	function search( term ) {
		if ( ! cfg.ajaxUrl ) {
			return;
		}
		if ( term === lastTerm ) {
			return;
		}
		lastTerm = term;
		if ( list ) {
			list.innerHTML = '<li class="spbwc-picker__empty">' + esc( ( cfg.i18n && cfg.i18n.searching ) || 'Searching…' ) + '</li>';
		}
		var body = new URLSearchParams();
		body.set( 'action', 'spbwc_b2b_search_customers' );
		body.set( 'nonce', cfg.nonce || '' );
		body.set( 'term', term );
		fetch( cfg.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body.toString()
		} ).then( function ( r ) {
			return r.json();
		} ).then( function ( json ) {
			render( json && json.success && json.data ? json.data.results : [] );
		} ).catch( function () {
			render( [] );
		} );
	}

	// Open buttons.
	document.addEventListener( 'click', function ( e ) {
		if ( e.target.closest( '.js-spbwc-open-picker' ) ) {
			e.preventDefault();
			open();
		} else if ( e.target.closest( '.js-spbwc-close-picker' ) ) {
			e.preventDefault();
			close();
		}
	} );

	// Esc to close.
	document.addEventListener( 'keydown', function ( e ) {
		if ( 'Escape' === e.key && ! modal.hidden ) {
			close();
		}
	} );

	// Debounced search.
	if ( input ) {
		input.addEventListener( 'input', function () {
			var term = input.value.trim();
			window.clearTimeout( timer );
			if ( term.length < 2 ) {
				lastTerm = null;
				if ( list ) {
					list.innerHTML = '';
				}
				return;
			}
			timer = window.setTimeout( function () {
				search( term );
			}, 250 );
		} );
	}
} )();
