/**
 * Multi-item quote cart (P4.3) — vanilla JS, no jQuery dependency.
 *
 * "Add to quote list" collects products into a server-side session bucket; the
 * floating "Quote list (N)" badge opens a modal to review items + submit one
 * multi-line quote request.
 */
( function () {
	'use strict';

	var cfg = window.spbwcBucket || {};
	if ( ! cfg.ajaxUrl ) {
		return;
	}
	var i18n = cfg.i18n || {};

	var fab     = document.getElementById( 'spbwc-bucket-fab' );
	var countEl = document.getElementById( 'spbwc-bucket-count' );
	var modal   = document.getElementById( 'spbwc-bucket-modal' );
	if ( ! modal ) {
		return;
	}
	var listEl  = modal.querySelector( '#spbwc-bucket-list' );
	var alertEl = modal.querySelector( '.spbwc-rfq-alert' );
	var formEl  = modal.querySelector( '#spbwc-bucket-form' );
	var success = modal.querySelector( '.spbwc-rfq-success' );
	var lastActive = null;

	function post( action, data ) {
		var body = new FormData();
		body.append( 'action', action );
		body.append( 'nonce', cfg.nonce || '' );
		Object.keys( data || {} ).forEach( function ( k ) {
			body.append( k, data[ k ] );
		} );
		return fetch( cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
			.then( function ( r ) { return r.json(); } );
	}

	function setCount( n ) {
		n = parseInt( n, 10 ) || 0;
		if ( countEl ) {
			countEl.textContent = String( n );
		}
		if ( fab ) {
			fab.style.display = n > 0 ? '' : 'none';
		}
	}

	function esc( s ) {
		var d = document.createElement( 'div' );
		d.textContent = s == null ? '' : String( s );
		return d.innerHTML;
	}

	function renderList( items ) {
		items = items || [];
		if ( ! listEl ) {
			return;
		}
		if ( ! items.length ) {
			listEl.innerHTML = '<li class="spbwc-bucket-empty">' + esc( i18n.empty || '' ) + '</li>';
			return;
		}
		listEl.innerHTML = items.map( function ( it ) {
			return '<li class="spbwc-bucket-item" data-product="' + parseInt( it.product_id, 10 ) + '">' +
				'<span class="spbwc-bucket-item__name">' + esc( it.name ) + '</span>' +
				'<span class="spbwc-bucket-item__qty">' + esc( i18n.qty || 'Qty' ) + ' ' + parseInt( it.qty, 10 ) + '</span>' +
				'<span class="spbwc-bucket-item__price">' + ( it.price_html || '' ) + '</span>' +
				'<button type="button" class="spbwc-bucket-item__remove" aria-label="' + esc( i18n.remove || 'Remove' ) + '">&times;</button>' +
				'</li>';
		} ).join( '' );
	}

	function showAlert( msg ) {
		if ( ! alertEl ) {
			return;
		}
		alertEl.textContent = msg || '';
		alertEl.style.display = msg ? 'block' : 'none';
	}

	function openModal() {
		lastActive = document.activeElement;
		// Fetch the current bucket (remove with no id is a harmless no-op that
		// returns the live item list).
		post( 'spbwc_bucket_remove', { product_id: 0 } ).then( function ( res ) {
			if ( res && res.success ) {
				renderList( res.data.items );
				setCount( res.data.count );
			}
		} );
		if ( success ) { success.classList.remove( 'is-visible' ); }
		if ( formEl ) { formEl.style.display = ''; }
		showAlert( '' );
		modal.classList.add( 'is-open' );
		modal.setAttribute( 'aria-hidden', 'false' );
		document.body.style.overflow = 'hidden';
		var focusable = modal.querySelector( 'input, textarea, button' );
		if ( focusable ) { focusable.focus(); }
	}

	function closeModal() {
		modal.classList.remove( 'is-open' );
		modal.setAttribute( 'aria-hidden', 'true' );
		document.body.style.overflow = '';
		if ( lastActive && lastActive.focus ) { lastActive.focus(); }
	}

	/* ── Add to quote ────────────────────────────────────────────── */
	document.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest ? e.target.closest( '.spbwc-bucket-add' ) : null;
		if ( ! btn ) {
			return;
		}
		e.preventDefault();
		var pid = btn.getAttribute( 'data-product' );
		var qtyInput = document.querySelector( 'form.cart input.qty, form.cart input[name="quantity"]' );
		var qty = qtyInput ? ( parseInt( qtyInput.value, 10 ) || 1 ) : 1;
		btn.disabled = true;
		post( 'spbwc_bucket_add', { product_id: pid, quantity: qty } ).then( function ( res ) {
			btn.disabled = false;
			if ( res && res.success ) {
				setCount( res.data.count );
				renderList( res.data.items );
				btn.classList.add( 'is-added' );
				var prev = btn.textContent;
				btn.textContent = i18n.added || 'Added';
				setTimeout( function () { btn.textContent = prev; btn.classList.remove( 'is-added' ); }, 1600 );
			}
		} ).catch( function () { btn.disabled = false; } );
	} );

	/* ── FAB + modal controls ────────────────────────────────────── */
	if ( fab ) {
		fab.addEventListener( 'click', openModal );
	}
	modal.addEventListener( 'click', function ( e ) {
		if ( e.target === modal || ( e.target.classList && e.target.classList.contains( 'spbwc-rfq-close' ) ) ) {
			closeModal();
		}
	} );
	document.addEventListener( 'keydown', function ( e ) {
		if ( 'Escape' === e.key && modal.classList.contains( 'is-open' ) ) {
			closeModal();
		}
	} );

	/* ── Remove item ─────────────────────────────────────────────── */
	if ( listEl ) {
		listEl.addEventListener( 'click', function ( e ) {
			var rm = e.target.closest ? e.target.closest( '.spbwc-bucket-item__remove' ) : null;
			if ( ! rm ) {
				return;
			}
			var li = rm.closest( '.spbwc-bucket-item' );
			var pid = li ? li.getAttribute( 'data-product' ) : 0;
			post( 'spbwc_bucket_remove', { product_id: pid } ).then( function ( res ) {
				if ( res && res.success ) {
					renderList( res.data.items );
					setCount( res.data.count );
				}
			} );
		} );
	}

	/* ── Submit ──────────────────────────────────────────────────── */
	if ( formEl ) {
		formEl.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			// Clear field errors.
			Array.prototype.forEach.call( formEl.querySelectorAll( '.spbwc-rfq-error' ), function ( el ) { el.textContent = ''; } );
			Array.prototype.forEach.call( formEl.querySelectorAll( '.spbwc-rfq-field' ), function ( el ) { el.classList.remove( 'has-error' ); } );
			showAlert( '' );

			var btn = formEl.querySelector( '.spbwc-rfq-submit' );
			var data = {};
			Array.prototype.forEach.call( formEl.querySelectorAll( '[name^="quote_fields["]' ), function ( el ) {
				data[ el.name ] = el.value;
			} );
			if ( btn ) { btn.disabled = true; btn.textContent = i18n.sending || 'Sending…'; }

			var body = new FormData();
			body.append( 'action', 'spbwc_bucket_submit' );
			body.append( 'nonce', cfg.nonce || '' );
			Object.keys( data ).forEach( function ( k ) { body.append( k, data[ k ] ); } );

			fetch( cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
				.then( function ( r ) { return r.json(); } )
				.then( function ( res ) {
					if ( btn ) { btn.disabled = false; btn.textContent = i18n.submit || 'Submit request'; }
					if ( res && res.success ) {
						if ( formEl ) { formEl.style.display = 'none'; }
						if ( success ) {
							success.classList.add( 'is-visible' );
							var cta = success.querySelector( '.spbwc-rfq-success__cta' );
							if ( cta && cfg.myQuotesUrl ) {
								cta.href = cfg.myQuotesUrl;
								cta.textContent = i18n.trackQuote || 'Track your quote';
								cta.style.display = '';
							}
						}
						setCount( 0 );
					} else {
						var errs = res && res.data && res.data.errors ? res.data.errors : {};
						Object.keys( errs ).forEach( function ( name ) {
							var field = formEl.querySelector( '[name="quote_fields[' + name + ']"]' );
							if ( field ) {
								var wrap = field.closest( '.spbwc-rfq-field' );
								if ( wrap ) {
									wrap.classList.add( 'has-error' );
									var em = wrap.querySelector( '.spbwc-rfq-error' );
									if ( em ) { em.textContent = errs[ name ]; }
								}
							}
						} );
						showAlert( ( res && res.data && res.data.message ) || i18n.network );
					}
				} )
				.catch( function () {
					if ( btn ) { btn.disabled = false; btn.textContent = i18n.submit || 'Submit request'; }
					showAlert( i18n.network || 'Request failed.' );
				} );
		} );
	}

	// Initial badge state already rendered server-side.
}() );
