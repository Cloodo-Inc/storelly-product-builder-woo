/**
 * Overview (A11): the page renders at once with cached or local counts; when the
 * Storelly numbers were not cached yet, fetch them after paint and swap them in.
 * Values replace text in boxes that already have their final size, so nothing moves.
 */
( function () {
	'use strict';

	var cfg = window.spbwcOverviewStats;
	if ( ! cfg || ! window.fetch ) {
		return;
	}

	var body = new URLSearchParams();
	body.append( 'action', 'spbwc_overview_stats' );
	body.append( 'nonce', cfg.nonce );

	window.fetch( cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
		.then( function ( res ) { return res.json(); } )
		.then( function ( json ) {
			if ( ! json || ! json.success || ! json.data ) {
				return; // Keep the local counts and the "Local data" badge.
			}
			[ 'products', 'orders', 'quotes' ].forEach( function ( key ) {
				var el = document.querySelector( '[data-spbwc-stat="' + key + '"]' );
				if ( el && typeof json.data[ key ] === 'string' ) {
					el.textContent = json.data[ key ];
				}
			} );
			var badge = document.getElementById( 'spbwc-local-data-badge' );
			if ( badge ) {
				badge.hidden = true;
			}
			var subtitle = document.getElementById( 'spbwc-metrics-subtitle' );
			if ( subtitle && cfg.subtitle ) {
				subtitle.textContent = cfg.subtitle;
			}
		} )
		.catch( function () {} );
}() );
