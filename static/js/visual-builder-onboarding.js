/**
 * Visual Builder — first-visit sample-data loader.
 *
 * The bag demo is the heavy seed, so it isn't installed at activation. The first
 * time the merchant opens the Visual Builder screen, this builds a loading
 * overlay, fires the (draft) seed via AJAX, and polls a status endpoint until the
 * import finishes — then reloads so the new Visual appears.
 *
 * The overlay carries a non-blocking escape hatch: "I'll create my own option
 * instead" hides the overlay and lets the merchant start building immediately.
 * The seed request is already in flight on the server, so it keeps running to
 * completion in the background — the skip never cancels it.
 */
( function ( $ ) {
	'use strict';

	var cfg = window.spbwcVbOnboarding || {};
	if ( ! cfg.ajaxUrl || ! cfg.nonce ) {
		return;
	}
	var i18n     = cfg.i18n || {};
	var skipped  = false;
	var finished = false;
	var pollTimer = null;

	function buildOverlay() {
		var $ov = $(
			'<div class="spbwc-vb-seed" role="dialog" aria-live="polite" aria-modal="false">' +
				'<div class="spbwc-vb-seed__panel">' +
					'<div class="spbwc-vb-seed__spinner" aria-hidden="true"></div>' +
					'<h2 class="spbwc-vb-seed__title"></h2>' +
					'<p class="spbwc-vb-seed__body"></p>' +
					'<div class="spbwc-vb-seed__progress" hidden>' +
						'<div class="spbwc-vb-seed__bar"><span class="spbwc-vb-seed__fill"></span></div>' +
						'<span class="spbwc-vb-seed__count"></span>' +
					'</div>' +
					'<button type="button" class="spbwc-vb-seed__skip"></button>' +
					'<p class="spbwc-vb-seed__hint"></p>' +
				'</div>' +
			'</div>'
		);
		$ov.find( '.spbwc-vb-seed__title' ).text( i18n.title || 'Setting up your first sample data…' );
		$ov.find( '.spbwc-vb-seed__body' ).text( i18n.body || '' );
		$ov.find( '.spbwc-vb-seed__skip' ).text( i18n.skip || 'I’ll create my own' );
		$ov.find( '.spbwc-vb-seed__hint' ).text( i18n.skipHint || '' );

		$ov.find( '.spbwc-vb-seed__skip' ).on( 'click', onSkip );
		return $ov;
	}

	function updateProgress( done, total ) {
		if ( ! total || total < 1 ) {
			return;
		}
		var pct = Math.min( 100, Math.round( ( done / total ) * 100 ) );
		var $p  = $( '.spbwc-vb-seed__progress' );
		$p.prop( 'hidden', false );
		$p.find( '.spbwc-vb-seed__fill' ).css( 'width', pct + '%' );
		$p.find( '.spbwc-vb-seed__count' ).text( done + ' / ' + total );
	}

	function onSkip() {
		skipped = true;
		var $ov = $( '.spbwc-vb-seed' );
		$ov.addClass( 'is-closing' );
		setTimeout( function () {
			$ov.remove();
		}, 220 );
		// Nudge the merchant toward creating: send them to the Create picker.
		if ( cfg.createUrl ) {
			window.location.href = cfg.createUrl;
		}
	}

	function finish() {
		if ( finished ) {
			return;
		}
		finished = true;
		if ( pollTimer ) {
			clearTimeout( pollTimer );
		}
		if ( skipped ) {
			return; // merchant already moved on — don't yank the page.
		}
		var $title = $( '.spbwc-vb-seed__title' );
		if ( $title.length ) {
			$title.text( i18n.done || 'Sample data ready! Reloading…' );
		}
		setTimeout( function () {
			window.location.reload();
		}, 700 );
	}

	function fail() {
		if ( skipped ) {
			return;
		}
		var $body = $( '.spbwc-vb-seed__body' );
		if ( $body.length ) {
			$body.text( i18n.failed || '' );
		}
		$( '.spbwc-vb-seed__spinner' ).hide();
	}

	function poll() {
		$.post( cfg.ajaxUrl, {
			action: cfg.statusAction,
			nonce: cfg.nonce
		} ).done( function ( res ) {
			if ( res && res.success && res.data ) {
				updateProgress( res.data.done, res.data.total );
				if ( res.data.seeded ) {
					finish();
					return;
				}
			}
			pollTimer = setTimeout( poll, 3000 );
		} ).fail( function () {
			pollTimer = setTimeout( poll, 5000 );
		} );
	}

	$( function () {
		var $overlay = buildOverlay();
		$( 'body' ).append( $overlay );
		// Move focus into the overlay so keyboard users land on the escape hatch.
		$overlay.find( '.spbwc-vb-seed__skip' ).trigger( 'focus' );

		// Kick the seed (draft). This request may run for many seconds while
		// ~100 images sideload; that's fine — the poller drives the UI, and a
		// skip leaves this running server-side.
		$.post( cfg.ajaxUrl, {
			action: cfg.seedAction,
			nonce: cfg.nonce,
			status: 'draft'
		} ).done( function ( res ) {
			if ( res && res.success ) {
				finish();
			} else {
				fail();
			}
		} ).fail( function () {
			// The request can also "fail" by timing out at the proxy while the
			// server keeps seeding — the poller will still catch completion.
			fail();
		} );

		// Start polling in parallel so completion is caught even if the seed
		// POST is severed by a gateway timeout.
		pollTimer = setTimeout( poll, 3000 );
	} );
} )( jQuery );
