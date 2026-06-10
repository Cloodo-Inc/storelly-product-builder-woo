/**
 * Welcome Wizard — step 1 picker behaviour.
 *
 * Enforces the 1–3 sample contract client-side: enables the "continue" button
 * only once at least the minimum is selected, blocks selecting past the maximum
 * (the would-be checkbox simply won't tick), and keeps a live "n of max" counter
 * in sync. The server re-validates the same bounds, so this is purely UX.
 */
( function ( $ ) {
	'use strict';

	$( function () {
		var cfg = window.spbwcWizard || {};
		var min = parseInt( cfg.min, 10 ) || 1;
		var max = parseInt( cfg.max, 10 ) || 3;

		var $form = $( '#spbwc-wiz-pick-form' );
		if ( ! $form.length ) {
			return;
		}

		var $boxes   = $form.find( '.spbwc-wiz-card__cb' );
		var $next    = $( '#spbwc-wiz-next' );
		var $counter = $( '#spbwc-wiz-counter' );

		function checked() {
			return $boxes.filter( ':checked' );
		}

		function sync() {
			var n = checked().length;

			// Cap: once at max, disable the remaining unchecked boxes so a click
			// can't push past the limit. Re-enable when back under the cap.
			$boxes.each( function () {
				var $cb = $( this );
				if ( ! $cb.prop( 'checked' ) ) {
					$cb.prop( 'disabled', n >= max );
				}
				$cb.closest( '.spbwc-wiz-card' ).toggleClass( 'is-selected', $cb.prop( 'checked' ) );
				$cb.closest( '.spbwc-wiz-card' ).toggleClass( 'is-disabled', $cb.prop( 'disabled' ) && ! $cb.prop( 'checked' ) );
			} );

			$next.prop( 'disabled', n < min );

			if ( $counter.length ) {
				if ( n >= max ) {
					$counter.text( cfg.maxHint || '' );
				} else if ( n === 0 ) {
					$counter.text( cfg.pickHint || '' );
				} else {
					$counter.text( n + ' / ' + max );
				}
			}
		}

		$boxes.on( 'change', sync );

		// Preview toggle: the button lives inside the <label>, so we must stop the
		// click from toggling the card's checkbox, then expand the details panel.
		$form.on( 'click', '.spbwc-wiz-card__preview-btn', function ( e ) {
			e.preventDefault();
			e.stopPropagation();
			var $btn     = $( this );
			var $details = $btn.siblings( '.spbwc-wiz-card__details' );
			var open     = $details.prop( 'hidden' );
			$details.prop( 'hidden', ! open );
			$btn.attr( 'aria-expanded', open ? 'true' : 'false' );
		} );

		// Submit feedback: disable + swap label so the full-page POST doesn't feel
		// frozen while the samples install.
		$form.on( 'submit', function ( e ) {
			var n = checked().length;
			if ( n < min ) {
				e.preventDefault();
				sync();
				return;
			}
			$next.prop( 'disabled', true ).addClass( 'is-loading' );
			$next.text( cfg.installing || 'Installing…' );
		} );

		sync();
	} );
} )( jQuery );
