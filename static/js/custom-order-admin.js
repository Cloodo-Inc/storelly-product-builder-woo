/**
 * Custom Order Detail (admin) — instant client-side tabs.
 *
 * All panels are server-rendered; this only shows/hides the active one (no AJAX,
 * no reload → tabs switch instantly). Adds `.js` to the root so CSS can hide
 * inactive panels; without JS every panel stays visible (graceful fallback).
 * Deep-links via location.hash and supports arrow-key navigation (ARIA tabs).
 *
 * @package Storelly
 */
( function () {
	'use strict';
	var root = document.querySelector( '.spbwc-co-detail' );
	if ( ! root ) { return; }
	root.classList.add( 'js' );

	var btns   = Array.prototype.slice.call( root.querySelectorAll( '.spbwc-co-tab-btn' ) );
	var panels = Array.prototype.slice.call( root.querySelectorAll( '.spbwc-co-tab' ) );
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
			p.classList.toggle( 'is-active', p.id === 'tab-' + id );
		} );
		if ( history.replaceState ) { history.replaceState( null, '', '#tab-' + id ); }
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

	// Initial tab from hash, else the first.
	var hash = ( location.hash || '' ).replace( /^#tab-/, '' );
	var valid = btns.some( function ( b ) { return b.getAttribute( 'data-tab' ) === hash; } );
	activate( valid ? hash : btns[0].getAttribute( 'data-tab' ) );
} )();
