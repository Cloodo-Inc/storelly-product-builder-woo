/**
 * Woo Variation Seed — wizard state machine.
 *
 * Plain ES2017 module-less script. Drives Setup Wizard › Import Woo
 * Variations end-to-end (scan → rules → confirm → run → done) using
 * window.spbwcWooSeed for ajaxUrl, nonce and i18n strings.
 *
 * All markup uses the shared admin-ui component classes
 * (.spbwc-page-hero, .spbwc-cta-btn, .spbwc-notice-banner, .spbwc-block)
 * plus wizard-specific .spbwc-ws-* classes from static/css/woo-seed.css —
 * no inline styles, no hardcoded colors.
 */
( function () {
	'use strict';

	if ( typeof window.spbwcWooSeed === 'undefined' ) {
		return;
	}
	var cfg = window.spbwcWooSeed;
	var root = document.getElementById( 'spbwc-woo-seed-app' );
	if ( ! root ) {
		return;
	}

	var state = {
		step:    'scanning', // scanning | step1 | step2 | step3 | running | done | error
		scan:    null,
		rules:   {
			display_type:          's',
			import_images:         true,
			multi_attr_price:      'avg',
			include_non_variation: false
		},
		job:     null,        // { job_id, status, processed, skipped, errors, total, progress, log[] }
		stopRequested: false
	};

	// ────────────────────────────────────────────────────────────────────
	//  Bootstrap
	// ────────────────────────────────────────────────────────────────────

	function init() {
		ajax( 'spbwc_woo_seed_scan', {} ).then( function ( res ) {
			if ( ! res || ! res.success ) {
				renderError( ( res && res.data && res.data.message ) || cfg.i18n.scanFailed );
				return;
			}
			state.scan = res.data;
			state.step = 'step1';
			render();
		} ).catch( function ( err ) {
			renderError( err && err.message ? err.message : cfg.i18n.scanFailed );
		} );
	}

	// ────────────────────────────────────────────────────────────────────
	//  Renderers — every render wraps its content in .spbwc-ws-block-body
	// ────────────────────────────────────────────────────────────────────

	function render() {
		switch ( state.step ) {
			case 'step1':   renderStep1(); break;
			case 'step2':   renderStep2(); break;
			case 'step3':   renderStep3(); break;
			case 'running': renderRunning(); break;
			case 'done':    renderDone(); break;
			default:        /* scanning shell is the PHP default */ break;
		}
	}

	function renderStep1() {
		var s = state.scan;
		var html = ''
			+ stepHeader( 1 )
			+ '<h2>' + esc( cfg.i18n.scanResults ) + '</h2>'
			+ statBlock( s )
			+ detectedBlock( s )
			+ previewBlock( s )
			+ footer( {
				rescan: true,
				cancel: cfg.i18n.cancel,
				next:   cfg.i18n.next
			} );
		mount( html );
		bindFooter( {
			next:   function () { state.step = 'step2'; render(); },
			cancel: function () { window.location.href = cfg.landingUrl; },
			rescan: function () { state.step = 'scanning'; renderScanning(); init(); }
		} );
	}

	function renderStep2() {
		var s = state.scan;
		var r = state.rules;
		var html = ''
			+ stepHeader( 2 )
			+ '<h2>' + esc( cfg.i18n.rules ) + '</h2>'

			+ ruleGroup( cfg.i18n.displayTypeLabel,
				radioRow( 'display_type', 'd', cfg.i18n.dropdown, false, r.display_type === 'd' )
				+ radioRow( 'display_type', 'r', cfg.i18n.radio,    false, r.display_type === 'r' )
				+ radioRow( 'display_type', 's', cfg.i18n.swatch,   true,  r.display_type === 's' ),
				cfg.i18n.displayTypeHelp )

			+ ruleGroup( '',
				checkboxRow( 'import_images', cfg.i18n.importImagesLabel, r.import_images ),
				sprintf( cfg.i18n.importImagesHelp, s.with_image ) )

			+ ruleGroup( cfg.i18n.priceRuleLabel,
				radioRow( 'multi_attr_price', 'avg',   cfg.i18n.priceRuleAvg,   true,  r.multi_attr_price === 'avg' )
				+ radioRow( 'multi_attr_price', 'empty', cfg.i18n.priceRuleEmpty, false, r.multi_attr_price === 'empty' ),
				sprintf( cfg.i18n.priceRuleHelp, s.multi_attr ) )

			+ ruleGroup( cfg.i18n.nonVariationLabel,
				checkboxRow( 'include_non_variation', cfg.i18n.nonVariationCheck, r.include_non_variation ),
				cfg.i18n.nonVariationHelp )

			+ policyBlock()

			+ footer( {
				back: cfg.i18n.back,
				next: cfg.i18n.next
			} );
		mount( html );
		bindRules();
		bindFooter( {
			back: function () { state.step = 'step1'; render(); },
			next: function () { state.step = 'step3'; render(); }
		} );
	}

	function renderStep3() {
		var s = state.scan;
		var r = state.rules;
		var willCreate = s.eligible;
		var html = ''
			+ stepHeader( 3 )
			+ '<h2>' + esc( cfg.i18n.readyToImport ) + '</h2>'
			+ '<ul class="spbwc-ws-summary">'
			+   li( sprintf( cfg.i18n.summaryCreate, willCreate ) )
			+   li( sprintf( cfg.i18n.summarySkip,   s.linked ) )
			+   li( esc( cfg.i18n.summaryDisplay ) + ' <strong>' + esc( displayLabel( r.display_type ) ) + '</strong>' )
			+   li( esc( cfg.i18n.summaryImages )  + ' <strong>' + esc( r.import_images ? cfg.i18n.on : cfg.i18n.off ) + '</strong>' )
			+   li( esc( cfg.i18n.summaryMultiAttr ) + ' <strong>'
				+ esc( r.multi_attr_price === 'avg' ? cfg.i18n.priceRuleAvg : cfg.i18n.priceRuleEmpty )
				+ '</strong>' )
			+ '</ul>'

			+ '<div class="spbwc-notice-banner spbwc-notice-banner--warn">'
			+   '<span class="dashicons dashicons-warning" aria-hidden="true"></span>'
			+   '<span>' + esc( cfg.i18n.stockWarning ) + '</span>'
			+ '</div>'

			+ '<label class="spbwc-ws-ack">'
			+   '<input type="checkbox" id="spbwc-ws-ack" /> '
			+   esc( cfg.i18n.acknowledge )
			+ '</label>'

			+ footer( {
				back: cfg.i18n.back,
				next: sprintf( cfg.i18n.runBtn, willCreate ),
				nextDisabled: true,
				nextPrimaryIcon: 'media-spreadsheet'
			} );
		mount( html );

		var ack  = document.getElementById( 'spbwc-ws-ack' );
		var next = root.querySelector( '[data-action="next"]' );
		ack.addEventListener( 'change', function () {
			next.disabled = ! ack.checked;
		} );
		bindFooter( {
			back: function () { state.step = 'step2'; render(); },
			next: function () { startRun(); }
		} );
	}

	function renderRunning() {
		var j = state.job || { processed: 0, skipped: 0, errors: 0, total: state.scan.eligible, progress: 0, log: [] };
		var html = ''
			+ '<h2>' + esc( cfg.i18n.running ) + '</h2>'
			+ progressBar( j.progress )
			+ '<p class="spbwc-ws-counts">'
			+   esc( sprintf( cfg.i18n.runningCounts, j.processed, j.skipped, j.errors, j.total ) )
			+ '</p>'
			+ logBlock( j.log )
			+ '<div class="spbwc-ws-footer">'
			+   '<span class="spbwc-ws-footer__spacer"></span>'
			+   '<button type="button" class="spbwc-cta-btn spbwc-cta-btn--ghost spbwc-cta-btn--sm" data-action="stop">'
			+     '<span class="dashicons dashicons-controls-pause" aria-hidden="true"></span> '
			+     esc( cfg.i18n.stop )
			+   '</button>'
			+ '</div>';
		mount( html );

		var stopBtn = root.querySelector( '[data-action="stop"]' );
		if ( stopBtn ) {
			stopBtn.addEventListener( 'click', function () {
				state.stopRequested = true;
				stopBtn.disabled = true;
				stopBtn.innerHTML = '<span class="dashicons dashicons-update spin" aria-hidden="true"></span> ' + esc( cfg.i18n.stopping );
			} );
		}
	}

	function renderDone() {
		var j = state.job;
		var html = ''
			+ '<div class="spbwc-ws-done">'
			+   '<h2><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span> ' + esc( cfg.i18n.done ) + '</h2>'
			+   '<ul class="spbwc-ws-summary">'
			+     li( sprintf( cfg.i18n.doneProcessed, j.processed ) )
			+     li( sprintf( cfg.i18n.doneSkipped,   j.skipped ) )
			+     li( sprintf( cfg.i18n.doneErrors,    j.errors ) )
			+     li( '<span class="spbwc-ws-done__tag">woo_seed_' + esc( j.job_id ) + '</span>' )
			+   '</ul>'
			+   '<div class="spbwc-ws-footer">'
			+     '<a class="spbwc-cta-btn spbwc-cta-btn--solid" href="'
			+       esc( root.closest( '[data-builder-url]' ).getAttribute( 'data-builder-url' ) ) + '">'
			+       '<span class="dashicons dashicons-list-view" aria-hidden="true"></span> '
			+       esc( cfg.i18n.openPricing )
			+     '</a>'
			+     '<span class="spbwc-ws-footer__spacer"></span>'
			+     '<button type="button" class="spbwc-cta-btn spbwc-cta-btn--ghost" data-action="undo">'
			+       '<span class="dashicons dashicons-undo" aria-hidden="true"></span> '
			+       esc( cfg.i18n.undoBtn )
			+     '</button>'
			+   '</div>'
			+   logBlock( j.log )
			+ '</div>';
		mount( html );

		root.querySelector( '[data-action="undo"]' ).addEventListener( 'click', confirmUndo );
	}

	function renderError( msg ) {
		state.step = 'error';
		var html = ''
			+ '<div class="spbwc-notice-banner spbwc-notice-banner--warn">'
			+   '<span class="dashicons dashicons-warning" aria-hidden="true"></span>'
			+   '<span><strong>' + esc( cfg.i18n.error ) + '</strong> ' + esc( msg ) + '</span>'
			+ '</div>';
		mount( html );
	}

	function renderScanning() {
		mount(
			'<p class="spbwc-ws-loading">'
			+ '<span class="spinner is-active" aria-hidden="true"></span>'
			+ esc( cfg.i18n.scanning )
			+ '</p>'
		);
	}

	function mount( html ) {
		root.innerHTML = '<div class="spbwc-ws-block-body">' + html + '</div>';
	}

	// ────────────────────────────────────────────────────────────────────
	//  Run loop
	// ────────────────────────────────────────────────────────────────────

	function startRun() {
		state.step = 'running';
		state.job  = null;
		state.stopRequested = false;
		renderRunning();
		runBatch();
	}

	function runBatch() {
		if ( state.stopRequested && state.job ) {
			state.job.status = 'done';
			state.step = 'done';
			render();
			return;
		}
		var payload = {
			job_id: state.job ? state.job.job_id : '',
			rules:  state.rules
		};
		ajax( 'spbwc_woo_seed_run', payload ).then( function ( res ) {
			if ( ! res || ! res.success ) {
				renderError( ( res && res.data && res.data.message ) || cfg.i18n.runFailed );
				return;
			}
			state.job = res.data;
			if ( 'done' === state.job.status ) {
				state.step = 'done';
				render();
				return;
			}
			renderRunning();
			setTimeout( runBatch, 200 );
		} ).catch( function ( err ) {
			renderError( err && err.message ? err.message : cfg.i18n.runFailed );
		} );
	}

	function confirmUndo() {
		if ( ! state.job ) { return; }
		var msg = sprintf( cfg.i18n.undoConfirm, state.job.processed );
		var ask = window.spbwcDialog
			? window.spbwcDialog.confirm( { message: msg, tone: 'danger', okText: cfg.i18n.undoOkBtn || '' } )
			: Promise.resolve( window.confirm( msg ) );
		ask.then( function ( ok ) {
			if ( ! ok ) { return; }
			ajax( 'spbwc_woo_seed_undo', { job_id: state.job.job_id } ).then( function ( res ) {
				if ( ! res || ! res.success ) {
					renderError( ( res && res.data && res.data.message ) || cfg.i18n.undoFailed );
					return;
				}
				var doneMsg = sprintf( cfg.i18n.undoOk, res.data.deleted, res.data.unlinked );
				if ( window.spbwcDialog ) {
					window.spbwcDialog.alert( { message: doneMsg, tone: 'success' } ).then( function () { window.location.href = cfg.landingUrl; } );
				} else {
					window.alert( doneMsg );
					window.location.href = cfg.landingUrl;
				}
			} ).catch( function ( err ) {
				renderError( err && err.message ? err.message : cfg.i18n.undoFailed );
			} );
		} );
	}

	// ────────────────────────────────────────────────────────────────────
	//  UI building blocks
	// ────────────────────────────────────────────────────────────────────

	function stepHeader( step ) {
		return '<span class="spbwc-ws-step-meta">' + esc( sprintf( cfg.i18n.stepOf, step, 3 ) ) + '</span>';
	}

	function statBlock( s ) {
		return ''
			+ '<div class="spbwc-ws-stats">'
			+   statCard( '🟢', cfg.i18n.eligible,       s.eligible, 'success' )
			+   statCard( '🟡', cfg.i18n.alreadyLinked,  s.linked,   'warning', cfg.i18n.willSkip )
			+   statCard( '⚪', cfg.i18n.simpleSkipped,  s.simple,   'mute' )
			+ '</div>';
	}

	function statCard( emoji, label, value, variant, hint ) {
		return ''
			+ '<div class="spbwc-ws-stat spbwc-ws-stat--' + variant + '">'
			+   '<div class="spbwc-ws-stat__label">' + emoji + ' ' + esc( label ) + '</div>'
			+   '<div class="spbwc-ws-stat__value">' + esc( String( value ) ) + '</div>'
			+   ( hint ? '<div class="spbwc-ws-stat__hint">' + esc( hint ) + '</div>' : '' )
			+ '</div>';
	}

	function detectedBlock( s ) {
		var attrLine = s.attribute_types.length
			? s.attribute_types.map( function ( a ) {
				return esc( a.label ) + ' (' + a.term_count + ')';
			} ).join( ' · ' )
			: '—';
		return ''
			+ '<div class="spbwc-ws-detected">'
			+   detectedRow( cfg.i18n.attrTypes,       attrLine )
			+   detectedRow( cfg.i18n.totalVariations, String( s.total_variations ) )
			+   detectedRow( cfg.i18n.withImage,       String( s.with_image ) )
			+   detectedRow( cfg.i18n.multiAttr,       String( s.multi_attr ), cfg.i18n.lossyNote )
			+ '</div>';
	}

	function detectedRow( label, value, hint ) {
		return ''
			+ '<div class="spbwc-ws-detected__row">'
			+   '<div class="spbwc-ws-detected__label">' + esc( label ) + '</div>'
			+   '<div class="spbwc-ws-detected__value">' + value
			+     ( hint ? '<span class="spbwc-ws-detected__hint">' + esc( hint ) + '</span>' : '' )
			+   '</div>'
			+ '</div>';
	}

	function previewBlock( s ) {
		if ( ! s.preview_top || ! s.preview_top.length ) { return ''; }
		var rows = s.preview_top.map( function ( p ) {
			return '<tr>'
				+ '<td>#' + p.id + '</td>'
				+ '<td>' + esc( p.name ) + '</td>'
				+ '<td>' + p.attr_count + ' × attr</td>'
				+ '<td>' + p.variation_count + ' var</td>'
				+ '<td>' + p.with_image + ' img</td>'
				+ '</tr>';
		} ).join( '' );
		var note = s.eligible > s.preview_top.length
			? '<p class="spbwc-ws-preview__more">'
				+ esc( sprintf( cfg.i18n.previewMore, s.preview_top.length, s.eligible ) )
				+ '</p>'
			: '';
		return ''
			+ '<details class="spbwc-ws-preview">'
			+   '<summary>' + esc( cfg.i18n.viewList ) + '</summary>'
			+   '<table>'
			+     '<thead><tr><th>ID</th><th>Name</th><th>Attrs</th><th>Variations</th><th>Images</th></tr></thead>'
			+     '<tbody>' + rows + '</tbody>'
			+   '</table>'
			+   note
			+ '</details>';
	}

	function policyBlock() {
		var auto = ( cfg.i18n.autoList || [] ).map( function ( x ) { return '<li>' + esc( x ) + '</li>'; } ).join( '' );
		var man  = ( cfg.i18n.manualList || [] ).map( function ( x ) { return '<li>' + esc( x ) + '</li>'; } ).join( '' );
		return ''
			+ '<div class="spbwc-ws-policy">'
			+   '<div class="spbwc-ws-policy--auto">'
			+     '<h3><span class="dashicons dashicons-yes" aria-hidden="true"></span> ' + esc( cfg.i18n.autoTitle ) + '</h3>'
			+     '<ul>' + auto + '</ul>'
			+   '</div>'
			+   '<div class="spbwc-ws-policy--manual">'
			+     '<h3><span class="dashicons dashicons-admin-generic" aria-hidden="true"></span> ' + esc( cfg.i18n.manualTitle ) + '</h3>'
			+     '<ul>' + man + '</ul>'
			+   '</div>'
			+ '</div>';
	}

	function progressBar( pct ) {
		pct = Math.max( 0, Math.min( 100, pct | 0 ) );
		return ''
			+ '<div class="spbwc-ws-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' + pct + '">'
			+   '<div class="spbwc-ws-progress__bar" style="width:' + pct + '%"></div>'
			+ '</div>'
			+ '<p class="spbwc-ws-progress__label">' + pct + '%</p>';
	}

	function logBlock( lines ) {
		var safe = ( lines || [] ).map( esc ).join( '\n' );
		return ''
			+ '<details class="spbwc-ws-log" open>'
			+   '<summary>' + esc( cfg.i18n.log ) + '</summary>'
			+   '<pre class="spbwc-ws-log__pre">' + safe + '</pre>'
			+ '</details>';
	}

	function ruleGroup( label, body, help ) {
		return ''
			+ '<div class="spbwc-ws-rule">'
			+   ( label ? '<div class="spbwc-ws-rule__label">' + esc( label ) + '</div>' : '' )
			+   body
			+   ( help ? '<div class="spbwc-ws-rule__hint">' + esc( help ) + '</div>' : '' )
			+ '</div>';
	}

	function radioRow( name, value, label, recommended, checked ) {
		return ''
			+ '<label class="spbwc-ws-rule__choice">'
			+   '<input type="radio" name="' + name + '" value="' + value + '"' + ( checked ? ' checked' : '' ) + ' /> '
			+   esc( label )
			+   ( recommended ? '<span class="spbwc-ws-rule__star" title="' + esc( cfg.i18n.recommendedHint || '' ) + '">★</span>' : '' )
			+ '</label>';
	}

	function checkboxRow( name, label, checked ) {
		return ''
			+ '<label class="spbwc-ws-rule__choice">'
			+   '<input type="checkbox" name="' + name + '"' + ( checked ? ' checked' : '' ) + ' /> '
			+   esc( label )
			+ '</label>';
	}

	function footer( opts ) {
		var html = '<div class="spbwc-ws-footer">';
		if ( opts.rescan ) {
			html += '<button type="button" class="spbwc-cta-btn spbwc-cta-btn--ghost spbwc-cta-btn--sm" data-action="rescan">'
				+ '<span class="dashicons dashicons-update" aria-hidden="true"></span> '
				+ esc( cfg.i18n.rescan )
				+ '</button>';
		}
		html += '<span class="spbwc-ws-footer__spacer"></span>';
		if ( opts.cancel ) {
			html += '<a class="spbwc-cta-btn spbwc-cta-btn--ghost spbwc-cta-btn--sm" data-action="cancel" href="#">'
				+ esc( opts.cancel ) + '</a>';
		}
		if ( opts.back ) {
			html += '<button type="button" class="spbwc-cta-btn spbwc-cta-btn--ghost spbwc-cta-btn--sm" data-action="back">'
				+ '<span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span> '
				+ esc( opts.back ) + '</button>';
		}
		if ( opts.next ) {
			html += '<button type="button" class="spbwc-cta-btn spbwc-cta-btn--solid" data-action="next"'
				+ ( opts.nextDisabled ? ' disabled' : '' ) + '>'
				+ ( opts.nextPrimaryIcon
					? '<span class="dashicons dashicons-' + esc( opts.nextPrimaryIcon ) + '" aria-hidden="true"></span> '
					: '' )
				+ esc( opts.next )
				+ ' <span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>'
				+ '</button>';
		}
		html += '</div>';
		return html;
	}

	function bindFooter( handlers ) {
		[ 'back', 'next', 'cancel', 'rescan' ].forEach( function ( act ) {
			var el = root.querySelector( '[data-action="' + act + '"]' );
			if ( el && handlers[ act ] ) {
				el.addEventListener( 'click', function ( e ) {
					e.preventDefault();
					handlers[ act ]();
				} );
			}
		} );
	}

	function bindRules() {
		root.querySelectorAll( 'input[type=radio]' ).forEach( function ( r ) {
			r.addEventListener( 'change', function () {
				if ( r.checked ) { state.rules[ r.name ] = r.value; }
			} );
		} );
		root.querySelectorAll( 'input[type=checkbox]' ).forEach( function ( c ) {
			if ( c.id === 'spbwc-ws-ack' ) { return; } // ack toggles button, not rules
			c.addEventListener( 'change', function () {
				state.rules[ c.name ] = c.checked;
			} );
		} );
	}

	function displayLabel( v ) {
		if ( v === 's' ) { return cfg.i18n.swatch; }
		if ( v === 'r' ) { return cfg.i18n.radio; }
		return cfg.i18n.dropdown;
	}

	function li( inner ) {
		return '<li>' + inner + '</li>';
	}

	// ────────────────────────────────────────────────────────────────────
	//  Misc
	// ────────────────────────────────────────────────────────────────────

	function ajax( action, data ) {
		var body = new URLSearchParams();
		body.append( 'action', action );
		body.append( 'nonce',  cfg.nonce );
		Object.keys( data || {} ).forEach( function ( k ) {
			var v = data[ k ];
			if ( v && typeof v === 'object' ) {
				Object.keys( v ).forEach( function ( kk ) {
					var vv = v[ kk ];
					body.append( k + '[' + kk + ']', typeof vv === 'boolean' ? ( vv ? '1' : '0' ) : String( vv ) );
				} );
			} else if ( v !== undefined && v !== null ) {
				body.append( k, String( v ) );
			}
		} );
		return fetch( cfg.ajaxUrl, {
			method:      'POST',
			credentials: 'same-origin',
			headers:     { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body:        body.toString()
		} ).then( function ( r ) { return r.json(); } );
	}

	function esc( s ) {
		return String( s == null ? '' : s )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' )
			.replace( /'/g, '&#39;' );
	}

	// sprintf — supports both sequential (%s, %d) and positional (%1$s, %2$d)
	// placeholders so the JS stays aligned with WordPress i18n strings, which
	// PHPCS auto-rewrites to positional form for any string with >1 arg.
	function sprintf( tpl ) {
		var args = Array.prototype.slice.call( arguments, 1 );
		var seq  = 0;
		return String( tpl ).replace( /%(?:(\d+)\$)?([sd])/g, function ( _m, idx ) {
			var pick = idx ? ( parseInt( idx, 10 ) - 1 ) : ( seq++ );
			return pick < args.length ? args[ pick ] : '';
		} );
	}

	init();
} )();
