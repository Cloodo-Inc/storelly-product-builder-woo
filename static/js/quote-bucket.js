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

	function toggleSubmit( hasItems ) {
		var submit = formEl ? formEl.querySelector( '.spbwc-rfq-submit' ) : null;
		if ( submit ) {
			submit.disabled = ! hasItems;
		}
	}

	function renderList( items ) {
		items = items || [];
		if ( ! listEl ) {
			return;
		}
		toggleSubmit( items.length > 0 );
		if ( ! items.length ) {
			listEl.innerHTML = '<li class="spbwc-bucket-empty">' + esc( i18n.empty || '' ) + '</li>';
			return;
		}
		listEl.innerHTML = items.map( function ( it ) {
			return '<li class="spbwc-bucket-item" data-product="' + parseInt( it.product_id, 10 ) + '">' +
				'<span class="spbwc-bucket-item__name">' + esc( it.name ) + '</span>' +
				'<span class="spbwc-bucket-item__qty spbwc-bucket-qty">' +
					'<button type="button" class="spbwc-bucket-qty__btn" data-step="-1" aria-label="' + esc( i18n.remove || 'Decrease' ) + '">&minus;</button>' +
					'<span class="spbwc-bucket-qty__val">' + parseInt( it.qty, 10 ) + '</span>' +
					'<button type="button" class="spbwc-bucket-qty__btn" data-step="1" aria-label="' + esc( i18n.add || 'Increase' ) + '">+</button>' +
				'</span>' +
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
			var qbtn = e.target.closest ? e.target.closest( '.spbwc-bucket-qty__btn' ) : null;
			if ( qbtn ) {
				var qli = qbtn.closest( '.spbwc-bucket-item' );
				var qpid = qli ? parseInt( qli.getAttribute( 'data-product' ), 10 ) : 0;
				var valEl = qli ? qli.querySelector( '.spbwc-bucket-qty__val' ) : null;
				var cur = valEl ? ( parseInt( valEl.textContent, 10 ) || 1 ) : 1;
				var step = parseInt( qbtn.getAttribute( 'data-step' ), 10 ) || 0;
				var next = Math.max( 1, cur + step );
				post( 'spbwc_bucket_setqty', { product_id: qpid, quantity: next } ).then( function ( res ) {
					if ( res && res.success ) {
						renderList( res.data.items );
						setCount( res.data.count );
					}
				} );
				return;
			}
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

	/* ── Product search picker (QF6) ─────────────────────────────── */
	var searchInput   = modal.querySelector( '#spbwc-bucket-search' );
	var searchResults = modal.querySelector( '#spbwc-bucket-results' );
	if ( searchInput && searchResults ) {
		var searchTimer = null;
		var renderResults = function ( results ) {
			results = results || [];
			if ( ! results.length ) {
				searchResults.innerHTML = '<li class="spbwc-bucket-search__empty">' + esc( i18n.noResults || 'No products found.' ) + '</li>';
				return;
			}
			searchResults.innerHTML = results.map( function ( r ) {
				return '<li class="spbwc-bucket-search__row">' +
					'<span class="spbwc-bucket-search__name">' + esc( r.name ) + ( r.price ? ' <small>' + esc( r.price ) + '</small>' : '' ) + '</span>' +
					'<button type="button" class="spbwc-bucket-search__add" data-product="' + parseInt( r.id, 10 ) + '">' + esc( i18n.add || 'Add' ) + '</button>' +
					'</li>';
			} ).join( '' );
		};
		searchInput.addEventListener( 'input', function () {
			var term = searchInput.value.trim();
			if ( searchTimer ) { clearTimeout( searchTimer ); }
			if ( term.length < 2 ) { searchResults.innerHTML = ''; return; }
			searchResults.innerHTML = '<li class="spbwc-bucket-search__empty">' + esc( i18n.searching || 'Searching…' ) + '</li>';
			searchTimer = setTimeout( function () {
				post( 'spbwc_bucket_search', { term: term } ).then( function ( res ) {
					if ( res && res.success ) { renderResults( res.data.results ); }
				} ).catch( function () { searchResults.innerHTML = ''; } );
			}, 280 );
		} );
		searchResults.addEventListener( 'click', function ( e ) {
			var add = e.target.closest ? e.target.closest( '.spbwc-bucket-search__add' ) : null;
			if ( ! add ) { return; }
			var pid = add.getAttribute( 'data-product' );
			add.disabled = true;
			post( 'spbwc_bucket_add', { product_id: pid, quantity: 1 } ).then( function ( res ) {
				add.disabled = false;
				if ( res && res.success ) {
					renderList( res.data.items );
					setCount( res.data.count );
					searchInput.value = '';
					searchResults.innerHTML = '';
				}
			} ).catch( function () { add.disabled = false; } );
		} );
	}

	/* ── Standalone-page trigger (QF7) ───────────────────────────── */
	document.addEventListener( 'click', function ( e ) {
		var opener = e.target.closest ? e.target.closest( '.spbwc-bucket-open' ) : null;
		if ( opener ) { e.preventDefault(); openModal(); }
	} );

	/* ── File-upload fields inside the cart form (QF8) ───────────── */
	function humanSize( bytes ) {
		if ( ! bytes ) { return '0 B'; }
		var u = [ 'B', 'KB', 'MB', 'GB' ], i = Math.floor( Math.log( bytes ) / Math.log( 1024 ) );
		return ( bytes / Math.pow( 1024, i ) ).toFixed( i ? 1 : 0 ) + ' ' + u[ i ];
	}
	function initFileField( field ) {
		var input = field.querySelector( '.spbwc-rfq-file' ),
			drop  = field.querySelector( '.spbwc-rfq-drop' ),
			list  = field.querySelector( '.spbwc-rfq-files' );
		if ( ! input || ! drop || ! list || typeof DataTransfer === 'undefined' ) { return; }
		var multiple = field.getAttribute( 'data-multiple' ) === '1',
			maxFiles = parseInt( field.getAttribute( 'data-max-files' ), 10 ) || 1,
			maxSize  = parseFloat( field.getAttribute( 'data-max-size' ) ) || 0,
			store    = [];
		function err( m ) {
			field.classList.toggle( 'has-error', !! m );
			var el = field.querySelector( '.spbwc-rfq-error' );
			if ( el ) { el.textContent = m || ''; }
		}
		function sync() {
			var dt = new DataTransfer();
			store.forEach( function ( f ) { dt.items.add( f ); } );
			input.files = dt.files;
			list.innerHTML = '';
			store.forEach( function ( f, idx ) {
				var li = document.createElement( 'li' );
				li.className = 'spbwc-rfq-file-chip';
				li.innerHTML = '<span class="spbwc-rfq-file-chip__name"></span><span class="spbwc-rfq-file-chip__size"></span><button type="button" class="spbwc-rfq-file-chip__remove" aria-label="x">&times;</button>';
				li.querySelector( '.spbwc-rfq-file-chip__name' ).textContent = f.name;
				li.querySelector( '.spbwc-rfq-file-chip__size' ).textContent = humanSize( f.size );
				li.querySelector( '.spbwc-rfq-file-chip__remove' ).addEventListener( 'click', function () { store.splice( idx, 1 ); err( '' ); sync(); } );
				list.appendChild( li );
			} );
		}
		function accept( files ) {
			var incoming = Array.prototype.slice.call( files || [] );
			if ( ! incoming.length ) { return; }
			err( '' );
			if ( ! multiple ) { store = []; }
			incoming.forEach( function ( f ) {
				if ( maxSize > 0 && f.size > maxSize * 1024 * 1024 ) { err( f.name + ' — too large.' ); return; }
				if ( store.length >= maxFiles ) { err( 'At most ' + maxFiles + ' file(s).' ); return; }
				store.push( f );
			} );
			sync();
		}
		input.addEventListener( 'change', function () { accept( input.files ); } );
		drop.addEventListener( 'click', function ( e ) { if ( e.target !== input ) { input.click(); } } );
		[ 'dragenter', 'dragover' ].forEach( function ( ev ) { drop.addEventListener( ev, function ( e ) { e.preventDefault(); drop.classList.add( 'is-drag' ); } ); } );
		[ 'dragleave', 'drop' ].forEach( function ( ev ) { drop.addEventListener( ev, function ( e ) { e.preventDefault(); drop.classList.remove( 'is-drag' ); } ); } );
		drop.addEventListener( 'drop', function ( e ) { if ( e.dataTransfer && e.dataTransfer.files ) { accept( e.dataTransfer.files ); } } );
	}
	if ( formEl ) {
		Array.prototype.forEach.call( formEl.querySelectorAll( '.spbwc-rfq-field--file' ), initFileField );
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
			if ( btn ) { btn.disabled = true; btn.textContent = i18n.sending || 'Sending…'; }

			// FormData(form) carries text fields AND any attached files.
			var body = new FormData( formEl );
			body.append( 'action', 'spbwc_bucket_submit' );
			body.append( 'nonce', cfg.nonce || '' );

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
							} else if ( i18n.guestHint ) {
								var hint = success.querySelector( '.spbwc-rfq-success__hint' );
								if ( ! hint ) {
									hint = document.createElement( 'p' );
									hint.className = 'spbwc-rfq-success__hint spbwc-rfq-success__text';
									success.appendChild( hint );
								}
								hint.textContent = i18n.guestHint;
							}
						}
						setCount( 0 );
					} else {
						var errs = res && res.data && res.data.errors ? res.data.errors : {};
						Object.keys( errs ).forEach( function ( name ) {
							var field = formEl.querySelector( '[name="quote_fields[' + name + ']"]' )
								|| formEl.querySelector( '[name="quote_files[' + name + ']"]' )
								|| formEl.querySelector( '[name="quote_files[' + name + '][]"]' );
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
