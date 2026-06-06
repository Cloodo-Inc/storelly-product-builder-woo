/**
 * B2B Pricing — tier ladder add/remove rows.
 *
 * Clones the inert <template id="spbwc-tier-row-tpl"> row on "Add tier", swapping
 * the __INDEX__ token for a running counter so every new row submits a unique
 * field group. New (unsaved) rows carry a remove button that just drops them from
 * the DOM. No dependencies — vanilla, runs after DOM ready.
 */
( function () {
	'use strict';

	function init() {
		var table = document.getElementById( 'spbwc-tier-table' );
		var tpl   = document.getElementById( 'spbwc-tier-row-tpl' );
		if ( ! table || ! tpl ) {
			return;
		}
		var tbody   = table.querySelector( 'tbody' );
		var counter = parseInt( table.getAttribute( 'data-next-index' ), 10 );
		if ( isNaN( counter ) ) {
			counter = 0;
		}

		function addRow() {
			var html = tpl.innerHTML.replace( /__INDEX__/g, String( counter ) );
			counter++;
			// Parse the <tr> string in a detached <tbody> so table rules apply.
			var holder = document.createElement( 'tbody' );
			holder.innerHTML = html.trim();
			var row = holder.firstElementChild;
			if ( ! row ) {
				return;
			}
			tbody.appendChild( row );
			var first = row.querySelector( 'input[type="text"]' );
			if ( first ) {
				first.focus();
			}
		}

		// "Add tier" / "Add another tier" buttons (there can be several).
		document.querySelectorAll( '.js-spbwc-add-tier' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				addRow();
			} );
		} );

		// Show the free-text "Custom label" input only when the Payment terms
		// select is on "custom" (delegated — covers cloned rows too).
		tbody.addEventListener( 'change', function ( e ) {
			var sel = e.target.closest ? e.target.closest( '.js-spbwc-terms-select' ) : null;
			if ( ! sel ) {
				return;
			}
			var cell   = sel.closest( 'td' );
			var custom = cell ? cell.querySelector( '.js-spbwc-terms-custom' ) : null;
			if ( custom ) {
				custom.style.display = ( 'custom' === sel.value ) ? '' : 'none';
			}
		} );

		// Remove an unsaved row (delegated — covers cloned rows too).
		tbody.addEventListener( 'click', function ( e ) {
			var btn = e.target.closest ? e.target.closest( '.js-spbwc-remove-row' ) : null;
			if ( ! btn ) {
				return;
			}
			e.preventDefault();
			var row = btn.closest( 'tr' );
			if ( row && row.parentNode ) {
				row.parentNode.removeChild( row );
			}
		} );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
