/**
 * Template-preview iframe → admin-dialog bridge.
 *
 * Loaded only inside the standalone preview document rendered by
 * SPBWC_Template_Preview_Render. Posts two pieces of state to the parent
 * admin dialog via window.postMessage:
 *
 *   - height : the document's natural height, so the dialog can auto-grow
 *              the <iframe> and avoid a nested scrollbar on tall templates.
 *   - total  : the live "YOUR TOTAL" value coming from storefront-enhance,
 *              so the dialog subtitle can reflect the running estimate.
 *
 * Same origin is required (admin + preview are both served from the WP
 * front-end), and the script bails out if it isn't running inside a frame.
 */
( function () {
	'use strict';

	if ( window.parent === window ) { return; }
	// Only attach when we are in the dedicated preview document (set by the
	// renderer's body class) so this is a no-op if storefront-enhance ever
	// pulls the script onto a real product page.
	if ( ! document.body || ! document.body.classList.contains( 'spbwc-tpl-preview-doc' ) ) { return; }

	var parentOrigin = window.location.origin;
	var lastHeight = 0;
	var lastTotal = '';

	function postHeight() {
		// Use the documentElement's scrollHeight — covers the stage padding
		// plus the rendered cart form (Cloodo hero, fields, sticky CTA, etc.).
		var h = Math.max(
			document.documentElement.scrollHeight,
			document.body.scrollHeight
		);
		if ( h && h !== lastHeight ) {
			lastHeight = h;
			try {
				window.parent.postMessage(
					{ source: 'spbwc-tpl-preview', type: 'height', value: h },
					parentOrigin
				);
			} catch ( e ) { /* parent gone */ }
		}
	}

	function postTotal() {
		var el = document.querySelector( '[data-spbwc-cloodo-total]' );
		var txt = el ? ( el.textContent || '' ).trim() : '';
		if ( txt !== lastTotal ) {
			lastTotal = txt;
			try {
				window.parent.postMessage(
					{ source: 'spbwc-tpl-preview', type: 'total', value: txt },
					parentOrigin
				);
			} catch ( e ) { /* parent gone */ }
		}
	}

	function ready() {
		// Initial post — Angular needs a beat to bootstrap, so wait one tick
		// and then a slightly longer delay to catch the first hero animation.
		setTimeout( function () { postHeight(); postTotal(); }, 50 );
		setTimeout( function () { postHeight(); postTotal(); }, 450 );

		// Auto-grow on layout changes (option clicks, sticky CTA appearing,
		// summary expand). ResizeObserver is in every browser we support.
		if ( typeof window.ResizeObserver === 'function' ) {
			try {
				var ro = new window.ResizeObserver( function () { postHeight(); } );
				ro.observe( document.body );
				ro.observe( document.documentElement );
			} catch ( e ) { /* observer unsupported — fall back to events below */ }
		}
		window.addEventListener( 'resize', postHeight, { passive: true } );

		// Watch the hero amount text for live total changes (Angular re-renders
		// + storefront-enhance's animateTotal both mutate this node).
		var totalEl = document.querySelector( '[data-spbwc-cloodo-total]' );
		if ( totalEl && typeof window.MutationObserver === 'function' ) {
			try {
				var mo = new window.MutationObserver( postTotal );
				mo.observe( totalEl, { childList: true, characterData: true, subtree: true } );
			} catch ( e ) { /* observer unsupported — periodic poll below */ }
		}
		// Belt-and-braces poll for the first 4s in case neither observer attaches
		// (e.g. the hero element gets re-rendered by Angular after we observed it).
		var polls = 0;
		var pollId = setInterval( function () {
			postTotal();
			if ( ++polls >= 8 ) { clearInterval( pollId ); }
		}, 500 );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', ready );
	} else {
		ready();
	}
} )();
