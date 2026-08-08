/**
 * Custom Order — "Save design" button for the WooCommerce Cart *block* (option B).
 *
 * The Cart block is React and does not fire classic PHP cart hooks, so the
 * server-rendered "Save design" link (woocommerce_after_cart_item_name) never
 * appears there. This enhancement reads the official data store
 * (wp.data → wc/store/cart), finds line items that carry a Storelly design
 * (item.extensions.storelly.is_design, fed by the Store API in E1), and injects a
 * token-styled "Save design" button into each matching row, wired to the existing
 * nonce save handler. Best-effort: if the block markup changes and rows can't be
 * matched, nothing renders (graceful) — order detail + classic cart stay reliable.
 *
 * No build step / no framework: plain JS + the official data store.
 *
 * @package Storelly
 */
( function () {
	'use strict';

	var cfg = window.spbwcCartSave || {};
	var FLAG = 'spbwcSaveDone';

	function buildButton( ext ) {
		var a = document.createElement( 'a' );
		a.className = 'spbwc-co-chip spbwc-co-chip--ghost spbwc-cart-block-save';
		var saveIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>';
		if ( cfg.loggedIn && ext.save_url ) {
			a.href = ext.save_url;
			a.innerHTML = saveIcon + '<span>' + ( cfg.i18nSave || 'Save design to my account' ) + '</span>';
		} else {
			a.href = cfg.loginUrl || '#';
			a.innerHTML = saveIcon + '<span>' + ( cfg.i18nLogin || 'Log in to save this design' ) + '</span>';
		}
		return a;
	}

	function inject() {
		if ( ! window.wp || ! wp.data || ! wp.data.select ) {
			return;
		}
		var store = wp.data.select( 'wc/store/cart' );
		if ( ! store || ! store.getCartData ) {
			return;
		}
		var cart = store.getCartData();
		if ( ! cart || ! cart.items || ! cart.items.length ) {
			return;
		}
		var rows = document.querySelectorAll( '.wc-block-cart-items__row' );
		if ( ! rows.length ) {
			return;
		}
		// Match design items to the correct DOM row by product permalink instead
		// of list position — the Store API item order and the rendered row order
		// are not guaranteed to align after the block re-renders (qty change,
		// remove, extra rows), which previously put the button on the wrong row.
		// Falls back to positional matching when identity can't disambiguate, so
		// behaviour is unchanged in the common one-of-each-product case.
		var normalize = function ( url ) {
			return ( url || '' ).replace( /^https?:\/\/[^/]+/, '' ).replace( /[?#].*$/, '' ).replace( /\/+$/, '' );
		};
		var rowInfo = [];
		for ( var r = 0; r < rows.length; r++ ) {
			var link = rows[ r ].querySelector( '.wc-block-components-product-name' )
				|| rows[ r ].querySelector( '.wc-block-cart-item__product a' )
				|| rows[ r ].querySelector( 'a[href]' );
			rowInfo.push( {
				row: rows[ r ],
				link: normalize( link && link.getAttribute( 'href' ) ),
				claimed: false
			} );
		}

		for ( var i = 0; i < cart.items.length; i++ ) {
			var item = cart.items[ i ];
			var ext = item && item.extensions && item.extensions.storelly;
			if ( ! ext || ! ext.is_design ) {
				continue;
			}
			// Prefer an unclaimed row whose product link matches this item;
			// otherwise fall back to the same positional row.
			var target = null;
			var itemLink = normalize( item.permalink );
			if ( itemLink ) {
				for ( var k = 0; k < rowInfo.length; k++ ) {
					if ( ! rowInfo[ k ].claimed && rowInfo[ k ].link === itemLink ) {
						target = rowInfo[ k ];
						break;
					}
				}
			}
			if ( ! target && rowInfo[ i ] && ! rowInfo[ i ].claimed ) {
				target = rowInfo[ i ];
			}
			if ( ! target ) {
				continue;
			}
			target.claimed = true;
			var row = target.row;
			if ( row.dataset[ FLAG ] === '1' || row.querySelector( '.spbwc-cart-block-save' ) ) {
				continue;
			}
			// Anchor point: the product info cell, falling back to the row itself.
			var host = row.querySelector( '.wc-block-cart-item__product' )
				|| row.querySelector( '.wc-block-components-product-metadata' )
				|| row;
			var wrap = document.createElement( 'p' );
			wrap.className = 'spbwc-co-action spbwc-cart-save';
			wrap.appendChild( buildButton( ext ) );
			host.appendChild( wrap );
			row.dataset[ FLAG ] = '1';
		}
	}

	function start() {
		inject();
		// Re-inject when the cart store changes (qty update, item add/remove).
		if ( window.wp && wp.data && wp.data.subscribe ) {
			wp.data.subscribe( function () { window.requestAnimationFrame( inject ); } );
		}
		// Re-inject when the block re-renders its rows (React replaces our nodes).
		var container = document.querySelector( '.wc-block-cart, .wp-block-woocommerce-cart' );
		if ( container && window.MutationObserver ) {
			new MutationObserver( function () { window.requestAnimationFrame( inject ); } )
				.observe( container, { childList: true, subtree: true } );
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', start );
	} else {
		start();
	}
} )();
