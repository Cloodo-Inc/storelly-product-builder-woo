/**
 * Woo Variation Seed — wizard state machine.
 *
 * Plain ES2017 module-less script. Drives Setup Wizard › Import Woo
 * Variations end-to-end (scan → rules → confirm → run → done) using
 * window.spbwcWooSeed for ajaxUrl, nonce and i18n strings.
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
	//  Renderers
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
		root.innerHTML = ''
			+ header( 1 )
			+ '<h2 style="margin-top:0;">' + esc( cfg.i18n.scanResults ) + '</h2>'
			+ statRows( s )
			+ detectedBlock( s )
			+ previewBlock( s )
			+ footer( {
				back:  null,
				next:  cfg.i18n.next,
				cancel: cfg.i18n.cancel,
				rescan: true
			} );
		bindFooter( {
			next:   function () { state.step = 'step2'; render(); },
			cancel: function () { window.location.href = cfg.landingUrl; },
			rescan: function () { state.step = 'scanning'; renderScanning(); init(); }
		} );
	}

	function renderStep2() {
		var s = state.scan;
		var r = state.rules;
		root.innerHTML = ''
			+ header( 2 )
			+ '<h2 style="margin-top:0;">' + esc( cfg.i18n.rules ) + '</h2>'
			+ '<div class="spbwc-woo-seed-rules">'

			+ ruleGroup( cfg.i18n.displayTypeLabel,
				radioRow( 'display_type', 'd', cfg.i18n.dropdown, r.display_type === 'd' )
				+ radioRow( 'display_type', 'r', cfg.i18n.radio,    r.display_type === 'r' )
				+ radioRow( 'display_type', 's', cfg.i18n.swatch + ' ★', r.display_type === 's' ),
				cfg.i18n.displayTypeHelp )

			+ ruleGroup( '',
				checkboxRow( 'import_images', cfg.i18n.importImagesLabel, r.import_images ),
				sprintf( cfg.i18n.importImagesHelp, s.with_image ) )

			+ ruleGroup( cfg.i18n.priceRuleLabel,
				radioRow( 'multi_attr_price', 'avg',   cfg.i18n.priceRuleAvg + ' ★',  r.multi_attr_price === 'avg' )
				+ radioRow( 'multi_attr_price', 'empty', cfg.i18n.priceRuleEmpty,    r.multi_attr_price === 'empty' ),
				sprintf( cfg.i18n.priceRuleHelp, s.multi_attr ) )

			+ ruleGroup( cfg.i18n.nonVariationLabel,
				checkboxRow( 'include_non_variation', cfg.i18n.nonVariationCheck, r.include_non_variation ),
				cfg.i18n.nonVariationHelp )

			+ '</div>'

			+ autoVsManualBlock()

			+ footer( {
				back: cfg.i18n.back,
				next: cfg.i18n.next
			} );

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
		root.innerHTML = ''
			+ header( 3 )
			+ '<h2 style="margin-top:0;">' + esc( cfg.i18n.readyToImport ) + '</h2>'
			+ '<ul style="margin:8px 0 16px 18px;font-size:14px;line-height:1.7;">'
			+   '<li>' + sprintf( cfg.i18n.summaryCreate, willCreate ) + '</li>'
			+   '<li>' + sprintf( cfg.i18n.summarySkip,   s.linked )  + '</li>'
			+   '<li>' + esc( cfg.i18n.summaryDisplay ) + ' <strong>'
			+     ( r.display_type === 's' ? cfg.i18n.swatch
				  : r.display_type === 'r' ? cfg.i18n.radio
				  : cfg.i18n.dropdown ) + '</strong></li>'
			+   '<li>' + esc( cfg.i18n.summaryImages ) + ' <strong>'
			+     ( r.import_images ? cfg.i18n.on : cfg.i18n.off ) + '</strong></li>'
			+   '<li>' + esc( cfg.i18n.summaryMultiAttr ) + ' <strong>'
			+     ( r.multi_attr_price === 'avg' ? cfg.i18n.priceRuleAvg : cfg.i18n.priceRuleEmpty ) + '</strong></li>'
			+ '</ul>'

			+ '<p style="background:#fff8e1;border-left:3px solid #f0b40a;padding:10px 12px;font-size:13px;">'
			+   '<strong>⚠</strong> ' + esc( cfg.i18n.stockWarning )
			+ '</p>'

			+ '<p><label><input type="checkbox" id="spbwc-ws-ack" /> '
			+   esc( cfg.i18n.acknowledge ) + '</label></p>'

			+ footer( {
				back: cfg.i18n.back,
				next: sprintf( cfg.i18n.runBtn, willCreate ),
				nextDisabled: true,
				nextPrimaryIcon: '🚀'
			} );

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
		root.innerHTML = ''
			+ '<h2 style="margin-top:0;">' + esc( cfg.i18n.running ) + '</h2>'
			+ progressBar( j.progress )
			+ '<p style="font-size:13px;color:#444;">'
			+   sprintf( cfg.i18n.runningCounts, j.processed, j.skipped, j.errors, j.total )
			+ '</p>'
			+ logBlock( j.log )
			+ '<p style="margin-top:16px;">'
			+   '<button type="button" class="button" data-action="stop">'
			+     esc( cfg.i18n.stop ) + '</button>'
			+ '</p>';

		var stopBtn = root.querySelector( '[data-action="stop"]' );
		if ( stopBtn ) {
			stopBtn.addEventListener( 'click', function () {
				state.stopRequested = true;
				stopBtn.disabled = true;
				stopBtn.textContent = cfg.i18n.stopping;
			} );
		}
	}

	function renderDone() {
		var j = state.job;
		root.innerHTML = ''
			+ '<h2 style="margin-top:0;">' + esc( cfg.i18n.done ) + ' ✅</h2>'
			+ '<ul style="margin:8px 0 16px 18px;font-size:14px;line-height:1.7;">'
			+   '<li>' + sprintf( cfg.i18n.doneProcessed, j.processed ) + '</li>'
			+   '<li>' + sprintf( cfg.i18n.doneSkipped,   j.skipped )   + '</li>'
			+   '<li>' + sprintf( cfg.i18n.doneErrors,    j.errors )    + '</li>'
			+   '<li><code>woo_seed_' + esc( j.job_id ) + '</code></li>'
			+ '</ul>'
			+ '<p>'
			+   '<a class="button button-primary" href="' + esc( root.closest( '[data-builder-url]' ).getAttribute( 'data-builder-url' ) ) + '">'
			+     esc( cfg.i18n.openPricing ) + '</a> '
			+   '<button type="button" class="button" data-action="undo">🔄 '
			+     esc( cfg.i18n.undoBtn ) + '</button>'
			+ '</p>'
			+ logBlock( j.log );

		root.querySelector( '[data-action="undo"]' ).addEventListener( 'click', confirmUndo );
	}

	function renderError( msg ) {
		state.step = 'error';
		root.innerHTML = ''
			+ '<div class="notice notice-error" style="margin:0;padding:12px;">'
			+   '<p><strong>' + esc( cfg.i18n.error ) + '</strong> ' + esc( msg ) + '</p>'
			+ '</div>';
	}

	function renderScanning() {
		root.innerHTML = ''
			+ '<p style="color:#666;">'
			+   '<span class="spinner is-active" style="float:none;margin:0 6px 0 0;"></span>'
			+   esc( cfg.i18n.scanning )
			+ '</p>';
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
			// Treat stop as "done with what we have" — the next render shows
			// the partial Done state with Undo available.
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
			// Tiny pacing so the progress bar visibly animates between batches.
			setTimeout( runBatch, 200 );
		} ).catch( function ( err ) {
			renderError( err && err.message ? err.message : cfg.i18n.runFailed );
		} );
	}

	function confirmUndo() {
		if ( ! state.job ) { return; }
		var msg = sprintf( cfg.i18n.undoConfirm, state.job.processed );
		if ( ! window.confirm( msg ) ) { return; }
		ajax( 'spbwc_woo_seed_undo', { job_id: state.job.job_id } ).then( function ( res ) {
			if ( ! res || ! res.success ) {
				renderError( ( res && res.data && res.data.message ) || cfg.i18n.undoFailed );
				return;
			}
			window.alert( sprintf( cfg.i18n.undoOk, res.data.deleted, res.data.unlinked ) );
			window.location.href = cfg.landingUrl;
		} ).catch( function ( err ) {
			renderError( err && err.message ? err.message : cfg.i18n.undoFailed );
		} );
	}

	// ────────────────────────────────────────────────────────────────────
	//  Small UI helpers
	// ────────────────────────────────────────────────────────────────────

	function header( step ) {
		var label = sprintf( cfg.i18n.stepOf, step, 3 );
		return '<p style="float:right;color:#888;font-size:12px;">' + esc( label ) + '</p>';
	}

	function statRows( s ) {
		return ''
			+ '<table class="widefat" style="max-width:520px;margin-bottom:14px;"><tbody>'
			+   row( '🟢 ' + cfg.i18n.eligible,         s.eligible )
			+   row( '🟡 ' + cfg.i18n.alreadyLinked,    s.linked, cfg.i18n.willSkip )
			+   row( '⚪ ' + cfg.i18n.simpleSkipped,    s.simple )
			+ '</tbody></table>';
	}

	function detectedBlock( s ) {
		var attrLine = s.attribute_types.length
			? s.attribute_types.map( function ( a ) {
				return esc( a.label ) + ' (' + a.term_count + ')';
			} ).join( ' · ' )
			: '—';
		return ''
			+ '<table class="widefat" style="max-width:720px;margin-bottom:14px;"><tbody>'
			+   row( cfg.i18n.attrTypes,       attrLine )
			+   row( cfg.i18n.totalVariations, s.total_variations )
			+   row( cfg.i18n.withImage,       s.with_image )
			+   row( cfg.i18n.multiAttr,       s.multi_attr, cfg.i18n.lossyNote )
			+ '</tbody></table>';
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
			? '<p style="color:#888;font-size:12px;">'
				+ sprintf( cfg.i18n.previewMore, s.preview_top.length, s.eligible )
				+ '</p>'
			: '';
		return ''
			+ '<details style="margin-bottom:14px;">'
			+   '<summary style="cursor:pointer;font-weight:600;">'
			+     esc( cfg.i18n.viewList )
			+   '</summary>'
			+   '<table class="widefat striped" style="max-width:720px;margin-top:8px;">'
			+     '<tbody>' + rows + '</tbody>'
			+   '</table>'
			+   note
			+ '</details>';
	}

	function autoVsManualBlock() {
		var auto = ( cfg.i18n.autoList || [] ).map( function ( x ) { return '<li>' + esc( x ) + '</li>'; } ).join( '' );
		var man  = ( cfg.i18n.manualList || [] ).map( function ( x ) { return '<li>' + esc( x ) + '</li>'; } ).join( '' );
		return ''
			+ '<div style="display:flex;flex-wrap:wrap;gap:24px;margin-top:20px;padding-top:16px;border-top:1px solid #ddd;">'
			+   '<div style="flex:1 1 280px;">'
			+     '<h3 style="margin-top:0;font-size:13px;text-transform:uppercase;color:#2c7;">'
			+       '✅ ' + esc( cfg.i18n.autoTitle ) + '</h3>'
			+     '<ul style="margin:6px 0 0 18px;font-size:13px;line-height:1.7;">' + auto + '</ul>'
			+   '</div>'
			+   '<div style="flex:1 1 280px;">'
			+     '<h3 style="margin-top:0;font-size:13px;text-transform:uppercase;color:#c70;">'
			+       '⚙ ' + esc( cfg.i18n.manualTitle ) + '</h3>'
			+     '<ul style="margin:6px 0 0 18px;font-size:13px;line-height:1.7;">' + man + '</ul>'
			+   '</div>'
			+ '</div>';
	}

	function progressBar( pct ) {
		pct = Math.max( 0, Math.min( 100, pct | 0 ) );
		return ''
			+ '<div style="background:#eef;border-radius:4px;height:18px;overflow:hidden;margin:12px 0;">'
			+   '<div style="background:#1971c2;height:100%;width:' + pct + '%;transition:width 200ms;"></div>'
			+ '</div>'
			+ '<p style="font-size:12px;color:#666;margin:0 0 12px;">' + pct + '%</p>';
	}

	function logBlock( lines ) {
		var safe = ( lines || [] ).map( esc ).join( '\n' );
		return ''
			+ '<details open style="margin-top:8px;">'
			+   '<summary style="cursor:pointer;font-weight:600;">' + esc( cfg.i18n.log ) + '</summary>'
			+   '<pre style="background:#1e1e1e;color:#d4d4d4;padding:12px;max-height:280px;overflow:auto;font-size:12px;line-height:1.5;border-radius:4px;margin-top:6px;">'
			+     safe
			+   '</pre>'
			+ '</details>';
	}

	function row( label, value, note ) {
		return '<tr>'
			+ '<th style="text-align:left;width:60%;">' + label + '</th>'
			+ '<td><strong>' + esc( String( value ) ) + '</strong>'
			+ ( note ? ' <span style="color:#888;font-size:12px;">' + esc( note ) + '</span>' : '' )
			+ '</td></tr>';
	}

	function ruleGroup( label, body, help ) {
		return ''
			+ '<div style="margin-bottom:14px;">'
			+ ( label ? '<div style="font-weight:600;margin-bottom:4px;">' + esc( label ) + '</div>' : '' )
			+ body
			+ ( help ? '<div style="color:#666;font-size:12px;margin-top:4px;">' + esc( help ) + '</div>' : '' )
			+ '</div>';
	}

	function radioRow( name, value, label, checked ) {
		return '<label style="display:inline-block;margin-right:16px;">'
			+ '<input type="radio" name="' + name + '" value="' + value + '"' + ( checked ? ' checked' : '' ) + ' /> '
			+ esc( label ) + '</label>';
	}

	function checkboxRow( name, label, checked ) {
		return '<label><input type="checkbox" name="' + name + '"' + ( checked ? ' checked' : '' ) + ' /> ' + esc( label ) + '</label>';
	}

	function footer( opts ) {
		var html = '<p style="margin-top:24px;padding-top:16px;border-top:1px solid #ddd;display:flex;gap:8px;align-items:center;">';
		if ( opts.rescan ) {
			html += '<button type="button" class="button" data-action="rescan">↻ ' + esc( cfg.i18n.rescan ) + '</button>';
		}
		html += '<span style="flex:1;"></span>';
		if ( opts.cancel ) {
			html += '<a class="button" data-action="cancel" href="#">' + esc( opts.cancel ) + '</a> ';
		}
		if ( opts.back ) {
			html += '<button type="button" class="button" data-action="back">← ' + esc( opts.back ) + '</button> ';
		}
		if ( opts.next ) {
			html += '<button type="button" class="button button-primary" data-action="next"'
				+ ( opts.nextDisabled ? ' disabled' : '' ) + '>'
				+ ( opts.nextPrimaryIcon ? opts.nextPrimaryIcon + ' ' : '' )
				+ esc( opts.next ) + ' →</button>';
		}
		html += '</p>';
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
			c.addEventListener( 'change', function () {
				state.rules[ c.name ] = c.checked;
			} );
		} );
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

	function sprintf( tpl ) {
		var args = Array.prototype.slice.call( arguments, 1 );
		var i = 0;
		return String( tpl ).replace( /%[sd]/g, function () {
			return i < args.length ? args[ i++ ] : '';
		} );
	}

	init();
} )();
