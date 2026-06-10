/**
 * Storelly — shared dialog & toast utility (spbwcDialog).
 *
 * Dependency-free, token-styled replacement for the native browser
 * window.alert / window.confirm / window.prompt popups (the ugly
 * "<site> says" chrome) and an ad-hoc toast/snackbar.
 *
 * Works in BOTH the WP admin and the WooCommerce storefront. The
 * companion stylesheet (spbwc-dialog.css) ships its own token
 * fallbacks, so the dialog looks correct even on pages where
 * _tokens.css is not loaded.
 *
 * Public API (all promise-based, except toast):
 *   spbwcDialog.alert(opts)   -> Promise<void>
 *   spbwcDialog.confirm(opts) -> Promise<boolean>
 *   spbwcDialog.prompt(opts)  -> Promise<string|null>   (null = cancelled)
 *   spbwcDialog.toast(opts)   -> void
 *
 * `opts` may be a plain string (used as the message/body) or an object:
 *   { title, message|body, okText, cancelText, tone, defaultValue,
 *     placeholder, required, icon }
 *   tone: 'default' | 'danger' | 'success' | 'warning'
 *
 * Progressive enhancement: any <form> or <a> carrying a
 * [data-spbwc-confirm="message"] attribute is auto-wired to a styled
 * confirm. The element's inline onsubmit/onclick="return confirm(...)"
 * fallback (for no-JS users) is neutralised once JS takes over, so the
 * confirmation is never shown twice.
 */
( function ( window, document ) {
	'use strict';

	if ( window.spbwcDialog ) {
		return; // Already loaded (handle deduped, but be safe).
	}

	var L10N = window.spbwcDialogI18n || {};
	function t( key, fallback ) {
		return ( L10N && L10N[ key ] ) || fallback;
	}

	var supportsDialog = typeof window.HTMLDialogElement === 'function' &&
		typeof document.createElement( 'dialog' ).showModal === 'function';

	// Focusable selector for the focus trap fallback (non-native path).
	var FOCUSABLE = 'a[href],button:not([disabled]),input:not([disabled]),' +
		'select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])';

	function normalize( opts ) {
		if ( typeof opts === 'string' || typeof opts === 'number' ) {
			return { message: String( opts ) };
		}
		return opts || {};
	}

	function el( tag, cls, text ) {
		var n = document.createElement( tag );
		if ( cls ) { n.className = cls; }
		if ( text != null ) { n.textContent = text; }
		return n;
	}

	/**
	 * Core builder. Returns a Promise resolving with:
	 *   confirm -> boolean, prompt -> string|null, alert -> undefined.
	 */
	function build( type, opts ) {
		opts = normalize( opts );

		return new Promise( function ( resolve ) {
			var lastFocus = document.activeElement;

			var dlg = el( supportsDialog ? 'dialog' : 'div', 'spbwc-dlg spbwc-dlg--' + ( opts.tone || 'default' ) );
			dlg.setAttribute( 'role', type === 'alert' ? 'alertdialog' : 'dialog' );
			dlg.setAttribute( 'aria-modal', 'true' );

			var titleId = 'spbwc-dlg-title-' + Math.floor( ( window.performance && window.performance.now ? window.performance.now() : 1 ) * 1000 % 1e7 );

			var box = el( 'div', 'spbwc-dlg__box' );

			// Optional leading icon (tone-coloured dashicon when available).
			if ( opts.icon !== false ) {
				var iconName = opts.icon || ( {
					danger: 'dashicons-warning',
					warning: 'dashicons-warning',
					success: 'dashicons-yes-alt'
				}[ opts.tone ] );
				if ( iconName ) {
					var ico = el( 'span', 'spbwc-dlg__icon dashicons ' + iconName );
					ico.setAttribute( 'aria-hidden', 'true' );
					box.appendChild( ico );
				}
			}

			var content = el( 'div', 'spbwc-dlg__content' );

			if ( opts.title ) {
				var h = el( 'h2', 'spbwc-dlg__title', opts.title );
				h.id = titleId;
				dlg.setAttribute( 'aria-labelledby', titleId );
				content.appendChild( h );
			}

			var msg = opts.message != null ? opts.message : opts.body;
			if ( msg != null && msg !== '' ) {
				var p = el( 'div', 'spbwc-dlg__msg' );
				// Preserve simple line breaks; never inject HTML from callers.
				String( msg ).split( '\n' ).forEach( function ( line, i ) {
					if ( i ) { p.appendChild( document.createElement( 'br' ) ); }
					p.appendChild( document.createTextNode( line ) );
				} );
				content.appendChild( p );
				if ( ! opts.title ) {
					dlg.setAttribute( 'aria-label', String( msg ) );
				}
			}

			var input = null;
			if ( type === 'prompt' ) {
				input = el( 'input', 'spbwc-dlg__input' );
				input.type = 'text';
				input.name = 'spbwc-dialog-value';
				input.value = opts.defaultValue != null ? opts.defaultValue : '';
				if ( opts.placeholder ) { input.placeholder = opts.placeholder; }
				input.setAttribute( 'aria-label', opts.title || ( msg != null ? String( msg ) : t( 'prompt', 'Enter a value' ) ) );
				content.appendChild( input );
			}

			box.appendChild( content );

			// Footer / actions.
			var foot = el( 'div', 'spbwc-dlg__foot' );

			function done( result ) {
				cleanup();
				resolve( result );
			}

			if ( type === 'confirm' || type === 'prompt' ) {
				var cancel = el( 'button', 'spbwc-dlg__btn spbwc-dlg__btn--ghost', opts.cancelText || t( 'cancel', 'Cancel' ) );
				cancel.type = 'button';
				cancel.addEventListener( 'click', function () { done( type === 'prompt' ? null : false ); } );
				foot.appendChild( cancel );
			}

			var okTone = opts.tone === 'danger' ? 'danger' : 'primary';
			var ok = el( 'button', 'spbwc-dlg__btn spbwc-dlg__btn--' + okTone,
				opts.okText || ( type === 'confirm' || type === 'prompt' ? t( 'confirm', 'OK' ) : t( 'ok', 'OK' ) ) );
			ok.type = 'button';
			ok.addEventListener( 'click', function () {
				if ( type === 'prompt' ) {
					var v = input ? input.value : '';
					if ( opts.required && ! String( v ).trim() ) {
						input.classList.add( 'spbwc-dlg__input--error' );
						input.focus();
						return;
					}
					done( v );
				} else if ( type === 'confirm' ) {
					done( true );
				} else {
					done();
				}
			} );
			foot.appendChild( ok );
			// Footer lives INSIDE the content column so title/message/input/
			// actions stack vertically; the box itself is a flex row that only
			// places the optional leading icon beside this column.
			content.appendChild( foot );
			dlg.appendChild( box );

			// Backdrop (only needed for the non-native fallback path).
			var backdrop = null;
			if ( ! supportsDialog ) {
				backdrop = el( 'div', 'spbwc-dlg-backdrop' );
				backdrop.appendChild( dlg );
				document.body.appendChild( backdrop );
			} else {
				document.body.appendChild( dlg );
			}

			document.body.classList.add( 'spbwc-dlg-open' );

			function onKeydown( e ) {
				if ( e.key === 'Escape' ) {
					e.preventDefault();
					done( type === 'prompt' ? null : ( type === 'confirm' ? false : undefined ) );
				} else if ( e.key === 'Enter' && type !== 'prompt' && document.activeElement !== cancelRef() ) {
					// Enter confirms alert/confirm when focus isn't on Cancel.
					e.preventDefault();
					ok.click();
				} else if ( e.key === 'Tab' && ! supportsDialog ) {
					trapTab( e );
				}
			}
			function cancelRef() { return foot.querySelector( '.spbwc-dlg__btn--ghost' ); }
			function trapTab( e ) {
				var f = dlg.querySelectorAll( FOCUSABLE );
				if ( ! f.length ) { return; }
				var first = f[ 0 ], last = f[ f.length - 1 ];
				if ( e.shiftKey && document.activeElement === first ) {
					e.preventDefault(); last.focus();
				} else if ( ! e.shiftKey && document.activeElement === last ) {
					e.preventDefault(); first.focus();
				}
			}

			// Enter inside the prompt input confirms.
			if ( input ) {
				input.addEventListener( 'keydown', function ( e ) {
					if ( e.key === 'Enter' ) { e.preventDefault(); ok.click(); }
					input.classList.remove( 'spbwc-dlg__input--error' );
				} );
			}

			document.addEventListener( 'keydown', onKeydown, true );

			// Click on backdrop dismisses (treated as cancel / no-op for alert).
			function onBackdropClick( e ) {
				if ( e.target === dlg || e.target === backdrop ) {
					done( type === 'prompt' ? null : ( type === 'confirm' ? false : undefined ) );
				}
			}
			if ( supportsDialog ) {
				dlg.addEventListener( 'click', onBackdropClick );
				dlg.addEventListener( 'cancel', function ( e ) {
					e.preventDefault();
					done( type === 'prompt' ? null : ( type === 'confirm' ? false : undefined ) );
				} );
			} else if ( backdrop ) {
				backdrop.addEventListener( 'click', onBackdropClick );
			}

			function cleanup() {
				document.removeEventListener( 'keydown', onKeydown, true );
				document.body.classList.remove( 'spbwc-dlg-open' );
				if ( supportsDialog && dlg.open ) {
					try { dlg.close(); } catch ( err ) {}
				}
				var root = backdrop || dlg;
				if ( root && root.parentNode ) { root.parentNode.removeChild( root ); }
				if ( lastFocus && typeof lastFocus.focus === 'function' ) {
					try { lastFocus.focus(); } catch ( err2 ) {}
				}
			}

			// Show + initial focus.
			if ( supportsDialog ) {
				try { dlg.showModal(); } catch ( err ) {}
			}
			window.requestAnimationFrame( function () {
				dlg.classList.add( 'is-visible' );
				if ( input ) {
					input.focus();
					input.select();
				} else if ( type === 'confirm' && opts.tone === 'danger' ) {
					// For destructive confirms, focus Cancel (safer default).
					var c = cancelRef();
					( c || ok ).focus();
				} else {
					ok.focus();
				}
			} );
		} );
	}

	var TOAST_ICONS = {
		success: 'dashicons-yes-alt',
		error: 'dashicons-dismiss',
		danger: 'dashicons-dismiss',
		warning: 'dashicons-warning',
		info: 'dashicons-info'
	};

	function toast( opts ) {
		opts = normalize( opts );
		var tone = opts.tone || 'info';
		var region = document.querySelector( '.spbwc-toast-region' );
		if ( ! region ) {
			region = el( 'div', 'spbwc-toast-region' );
			region.setAttribute( 'role', 'status' );
			region.setAttribute( 'aria-live', 'polite' );
			document.body.appendChild( region );
		}
		var node = el( 'div', 'spbwc-toast spbwc-toast--' + tone );
		var iconName = TOAST_ICONS[ tone ];
		if ( iconName && opts.icon !== false ) {
			var ico = el( 'span', 'spbwc-toast__icon dashicons ' + iconName );
			ico.setAttribute( 'aria-hidden', 'true' );
			node.appendChild( ico );
		}
		node.appendChild( el( 'span', 'spbwc-toast__msg', opts.message != null ? opts.message : opts.body ) );

		var close = el( 'button', 'spbwc-toast__close dashicons dashicons-no-alt' );
		close.type = 'button';
		close.setAttribute( 'aria-label', t( 'cancel', 'Dismiss' ) );
		node.appendChild( close );

		region.appendChild( node );
		window.requestAnimationFrame( function () { node.classList.add( 'is-visible' ); } );

		var timer = null;
		function remove() {
			if ( timer ) { window.clearTimeout( timer ); }
			node.classList.remove( 'is-visible' );
			window.setTimeout( function () {
				if ( node.parentNode ) { node.parentNode.removeChild( node ); }
			}, 200 );
		}
		close.addEventListener( 'click', remove );
		var duration = opts.duration != null ? opts.duration : ( tone === 'error' || tone === 'danger' ? 6000 : 4000 );
		if ( duration > 0 ) { timer = window.setTimeout( remove, duration ); }
		return { dismiss: remove };
	}

	var api = {
		alert: function ( opts ) { return build( 'alert', opts ); },
		confirm: function ( opts ) { return build( 'confirm', opts ); },
		prompt: function ( opts ) { return build( 'prompt', opts ); },
		toast: toast
	};

	window.spbwcDialog = api;

	/**
	 * Progressive-enhancement auto-wiring for [data-spbwc-confirm].
	 *
	 *   <form ... onsubmit="return confirm('…')" data-spbwc-confirm="…">
	 *   <a ... onclick="return confirm('…')" data-spbwc-confirm="…">
	 *
	 * When JS is available we neutralise the inline native confirm and
	 * show the styled dialog instead. No-JS users keep the inline
	 * onsubmit/onclick fallback untouched.
	 */
	function wireConfirm( node ) {
		if ( node._spbwcWired ) { return; }
		node._spbwcWired = true;

		var message = node.getAttribute( 'data-spbwc-confirm' ) || t( 'confirm', 'Are you sure?' );
		var tone = node.getAttribute( 'data-spbwc-confirm-tone' ) || 'danger';
		var okText = node.getAttribute( 'data-spbwc-confirm-ok' ) || '';
		var title = node.getAttribute( 'data-spbwc-confirm-title' ) || '';

		var tag = node.tagName.toLowerCase();

		if ( tag === 'form' ) {
			// Drop the inline native fallback so it never double-prompts.
			node.onsubmit = null;
			node.removeAttribute( 'onsubmit' );
			var submitted = false;
			node.addEventListener( 'submit', function ( e ) {
				if ( submitted ) { return; }
				e.preventDefault();
				api.confirm( { title: title, message: message, tone: tone, okText: okText } ).then( function ( ok ) {
					if ( ok ) {
						submitted = true;
						if ( typeof node.requestSubmit === 'function' ) {
							node.requestSubmit();
						} else {
							node.submit();
						}
					}
				} );
			} );
		} else {
			node.onclick = null;
			node.removeAttribute( 'onclick' );
			node.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				var href = node.getAttribute( 'href' );
				api.confirm( { title: title, message: message, tone: tone, okText: okText } ).then( function ( ok ) {
					if ( ok && href ) { window.location.href = href; }
				} );
			} );
		}
	}

	function scan( root ) {
		var nodes = ( root || document ).querySelectorAll( '[data-spbwc-confirm]' );
		for ( var i = 0; i < nodes.length; i++ ) { wireConfirm( nodes[ i ] ); }
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', function () { scan(); } );
	} else {
		scan();
	}
	// Expose for dynamically-injected markup.
	api.wireConfirms = scan;

} )( window, document );
