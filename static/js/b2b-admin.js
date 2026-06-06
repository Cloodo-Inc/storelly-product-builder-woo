/**
 * Storelly B2B admin — company-detail tabs + "Upgrade a customer" picker.
 *
 * Vanilla JS, no dependencies. Data + nonce come from wp_localize_script
 * (spbwcB2BAdmin).
 */

/**
 * Company-detail tabs — instant client-side switching.
 *
 * All panels are server-rendered; this only shows/hides the active one (no AJAX,
 * no reload → tabs switch instantly). Adds `.js` to the detail root so CSS hides
 * inactive panels; without JS every panel stays visible (graceful fallback).
 * Deep-links via location.hash and supports ARIA arrow-key navigation. Runs in
 * its own IIFE so it is independent from the picker below.
 */
( function () {
	'use strict';
	var root = document.querySelector( '.spbwc-b2b-detail' );
	if ( ! root ) { return; }
	root.classList.add( 'js' );

	var btns   = Array.prototype.slice.call( root.querySelectorAll( '.spbwc-tab[data-tab]' ) );
	var panels = Array.prototype.slice.call( root.querySelectorAll( '.spbwc-b2b-panel' ) );
	if ( ! btns.length || ! panels.length ) { return; }

	function activate( id, focus ) {
		btns.forEach( function ( b ) {
			var on = b.getAttribute( 'data-tab' ) === id;
			b.classList.toggle( 'is-active', on );
			b.setAttribute( 'aria-selected', on ? 'true' : 'false' );
			b.tabIndex = on ? 0 : -1;
			if ( on && focus ) { b.focus(); }
		} );
		panels.forEach( function ( p ) {
			p.classList.toggle( 'is-active', p.id === 'spbwc-b2b-panel-' + id );
		} );
		if ( history.replaceState ) { history.replaceState( null, '', '#b2b-tab-' + id ); }
	}

	btns.forEach( function ( b, i ) {
		b.addEventListener( 'click', function () { activate( b.getAttribute( 'data-tab' ) ); } );
		b.addEventListener( 'keydown', function ( e ) {
			var dir = e.key === 'ArrowRight' ? 1 : ( e.key === 'ArrowLeft' ? -1 : 0 );
			if ( ! dir ) { return; }
			e.preventDefault();
			var next = btns[ ( i + dir + btns.length ) % btns.length ];
			activate( next.getAttribute( 'data-tab' ), true );
		} );
	} );

	// Initial tab: location.hash wins, else the server-marked active button.
	var hash  = ( location.hash || '' ).replace( /^#b2b-tab-/, '' );
	var valid = btns.some( function ( b ) { return b.getAttribute( 'data-tab' ) === hash; } );
	var seed  = root.querySelector( '.spbwc-tab.is-active[data-tab]' );
	activate( valid ? hash : ( seed ? seed.getAttribute( 'data-tab' ) : btns[0].getAttribute( 'data-tab' ) ) );
} )();

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

/**
 * Payment-terms "Custom" label — reveal the free-text input only when the
 * Payment terms select is on "custom". Delegated so it covers both the create
 * form and the company-detail Account-settings form. Vanilla, no dependencies.
 */
( function () {
	'use strict';
	document.addEventListener( 'change', function ( e ) {
		var sel = e.target.closest ? e.target.closest( '.js-spbwc-terms-select' ) : null;
		if ( ! sel ) {
			return;
		}
		var scope  = sel.closest( '.spbwc-setting-row' ) || sel.parentNode;
		var custom = scope ? scope.querySelector( '.js-spbwc-terms-custom' ) : null;
		if ( custom ) {
			custom.style.display = ( 'custom' === sel.value ) ? '' : 'none';
		}
	} );
} )();
