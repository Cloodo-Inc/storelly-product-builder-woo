/**
 * Template Library — search/filter, Preview dialog (live storefront iframe,
 * fields, about) and Apply dialog (radio cards + Select2 product/category
 * picker).
 *
 * Preview fidelity: the "Preview" tab loads an <iframe> served by
 * SPBWC_Template_Preview_Render, which renders the template through the SAME
 * storefront view + CSS + JS a real product page uses. There is no parallel
 * mockup — updating the storefront styling updates this preview automatically.
 *
 * Note on Select2: WooCommerce auto-inits `.wc-product-search` on DOM ready,
 * but our product picker sits inside a <dialog> that is `display:none` until
 * opened — so we manually init it the first time the apply dialog opens.
 */
(function ($) {
	'use strict';

	if (typeof window.spbwcTemplateLibrary === 'undefined') {
		return;
	}
	var L = window.spbwcTemplateLibrary;
	var $previewDialog, $applyDialog, $cards, currentTemplate = null;

	// localStorage key for the merchant's last-typed sample base price
	// (single value, not per-slug — that matches the "what does $X look
	// like with this template" mental model).
	var BASE_STORAGE_KEY = 'spbwcTplPreviewBase';

	// Debounce + first-load tracking for the iframe loader.
	var basePriceTimer = null;
	var firstFrameLoad = true;     // true = show full overlay; false = subtle "Updating…"
	var lastFrameSlug  = null;
	var lastFrameBase  = null;
	var heroBase       = '';       // subtitle text built from meta (without total)
	var lastLiveTotal  = '';
	var currentProductId   = 0;   // optional "preview against this real product"
	var currentProductName = '';
	var previewProductSelectInited = false;

	function readStoredBase() {
		try {
			var v = window.localStorage.getItem(BASE_STORAGE_KEY);
			var n = parseFloat(v);
			return (!isNaN(n) && n >= 0) ? n : 0;
		} catch (e) { return 0; }
	}
	function writeStoredBase(v) {
		try { window.localStorage.setItem(BASE_STORAGE_KEY, String(v)); } catch (e) {}
	}

	$(function () {
		$previewDialog = $('#spbwc-tl-preview-dialog');
		$applyDialog   = $('#spbwc-tl-apply-dialog');
		$cards         = $('#spbwc-tl-grid .spbwc-tl-card');

		initFilters();
		initPreview();
		initApply();
	});

	// ─── Filters ─────────────────────────────────────────────────────
	function initFilters() {
		var $search  = $('#spbwc-tl-search');
		var $cat     = $('#spbwc-tl-category-filter');
		var $count   = $('#spbwc-tl-count');
		var $empty   = $('#spbwc-tl-empty-search');
		var totalLbl = $count.text();

		function applyFilter() {
			var q   = ($search.val() || '').toLowerCase().trim();
			var cat = $cat.val() || '';
			var visible = 0;
			$cards.each(function () {
				var $c = $(this);
				var matchText = !q || ($c.data('name') + '').indexOf(q) !== -1;
				var matchCat  = !cat || $c.data('category') === cat;
				var show = matchText && matchCat;
				$c.prop('hidden', !show);
				if (show) visible++;
			});
			$empty.prop('hidden', visible !== 0);
			$count.text(visible === $cards.length
				? totalLbl
				: visible + ' / ' + $cards.length);
		}
		$search.on('input', applyFilter);
		$cat.on('change', applyFilter);
	}

	// ─── Preview dialog ──────────────────────────────────────────────
	function initPreview() {
		$(document).on('click', '.spbwc-tl-preview', function () {
			var slug = $(this).data('slug');
			var name = $(this).data('name');
			openPreview(slug, name);
		});

		// Tab switching.
		$previewDialog.on('click', '.spbwc-tl-tab', function () {
			var tab = $(this).data('tab');
			$previewDialog.find('.spbwc-tl-tab').removeClass('spbwc-tl-tab--active');
			$(this).addClass('spbwc-tl-tab--active');
			$previewDialog.find('.spbwc-tl-tabpanel').removeClass('spbwc-tl-tabpanel--active');
			$previewDialog.find('[data-tabpanel="' + tab + '"]').addClass('spbwc-tl-tabpanel--active');
		});

		// Close handlers.
		$previewDialog.on('click', '[data-close="preview"]', function () {
			closeDialog($previewDialog);
		});

		// Viewport switcher — resizes the iframe stage (desktop/tablet/mobile).
		$previewDialog.on('click', '.spbwc-tl-vp__btn[data-pv]', function () {
			var vp = $(this).data('pv');
			$(this).siblings('.spbwc-tl-vp__btn').removeClass('spbwc-tl-vp__btn--active');
			$(this).addClass('spbwc-tl-vp__btn--active');
			$('#spbwc-tl-preview-live').find('.spbwc-tl-preview-stage').attr('data-viewport', vp);
		});

		// Currency-symbol affordance next to the base-price input.
		if (L.currencySymbol) {
			$('#spbwc-tl-baseprice-symbol').text(L.currencySymbol);
		}

		// Sample base price — debounce on input (every keystroke would
		// otherwise re-fetch the iframe), fire immediately on blur (change).
		$previewDialog.on('input', '#spbwc-tl-baseprice', function () {
			var raw = $(this).val();
			if (basePriceTimer) { clearTimeout(basePriceTimer); }
			basePriceTimer = setTimeout(function () { applyBasePrice(raw); }, 400);
		});
		$previewDialog.on('change', '#spbwc-tl-baseprice', function () {
			if (basePriceTimer) { clearTimeout(basePriceTimer); basePriceTimer = null; }
			applyBasePrice($(this).val());
		});

		// Iframe load handler — hide overlays + sanity-check the doc rendered.
		$('#spbwc-tl-preview-frame').on('load', onFrameLoad);

		// Retry from the error overlay.
		$(document).on('click', '#spbwc-tl-preview-frame-retry', function () {
			if (currentTemplate) {
				firstFrameLoad = true; // re-show the full overlay on retry
				loadPreviewFrame(currentTemplate.slug, lastFrameBase || 0);
			}
		});

		// Preview-against-product picker — Select2 ajax for WC products.
		// When the merchant picks one, the endpoint uses that product's real
		// price as the base; clearing it falls back to the sample input.
		$previewDialog.on('change', '#spbwc-tl-preview-product', function (ev) {
			// `change.spbwc-internal` is fired by our own cleanup → don't loop.
			if (ev && ev.namespace === 'spbwc-internal') { return; }
			var $sel = $(this);
			var val  = parseInt($sel.val(), 10);
			if (val > 0) {
				currentProductId = val;
				var $opt = $sel.find('option:selected');
				currentProductName = $opt.length ? $opt.text().replace(/\s*\(#\d+\)\s*$/, '') : '';
				// Manual sample base no longer drives the preview while a real
				// product is selected — disable the input as a visual cue.
				$('#spbwc-tl-baseprice').prop('disabled', true).addClass('is-disabled');
			} else {
				currentProductId   = 0;
				currentProductName = '';
				$('#spbwc-tl-baseprice').prop('disabled', false).removeClass('is-disabled');
			}
			refreshSubtitle();
			if (currentTemplate) {
				var manualBase = parseFloat($('#spbwc-tl-baseprice').val()) || 0;
				loadPreviewFrame(currentTemplate.slug, currentProductId > 0 ? 0 : manualBase);
			}
		});

		// Postmessage bridge — auto-grow iframe to content + reflect live total
		// in the subtitle. Same origin verified before trusting the payload.
		window.addEventListener('message', onPreviewMessage);

		// "Apply this template" CTA from preview footer.
		$('#spbwc-tl-preview-apply-cta').on('click', function () {
			if (!currentTemplate) return;
			closeDialog($previewDialog);
			openApply(currentTemplate.slug, currentTemplate.name);
		});
	}

	function applyBasePrice(rawVal) {
		var base = parseFloat(rawVal);
		if (isNaN(base) || base < 0) base = 0;
		writeStoredBase(base);
		// Typing a manual base means the merchant wants the synthetic mode —
		// clear any "preview against product" selection so the two inputs
		// don't fight for which price wins.
		clearPreviewProduct();
		if (currentTemplate) loadPreviewFrame(currentTemplate.slug, base);
	}

	function clearPreviewProduct() {
		if (currentProductId === 0 && !currentProductName) return;
		currentProductId   = 0;
		currentProductName = '';
		var $sel = $('#spbwc-tl-preview-product');
		if (previewProductSelectInited && $sel.length) {
			try {
				var fn = $.fn.selectWoo ? 'selectWoo' : ($.fn.select2 ? 'select2' : null);
				if (fn) { $sel.val(null).trigger('change.spbwc-internal'); }
			} catch (e) {}
		}
		refreshSubtitle();
	}

	function initPreviewProductSelect($el) {
		if (!$.fn.selectWoo && !$.fn.select2) { return; }
		var fn = $.fn.selectWoo ? 'selectWoo' : 'select2';
		try { if ($el.data(fn) || $el.hasClass('select2-hidden-accessible')) { $el[fn]('destroy'); } } catch (e) {}
		var action = $el.data('action') || 'woocommerce_json_search_products_and_variations';
		$el[fn]({
			placeholder: $el.data('placeholder') || L.i18n.previewAgainstProduct || 'Use sample base price',
			allowClear: true,
			minimumInputLength: 1,
			width: '100%',
			dropdownParent: $('body'),
			ajax: {
				url: L.ajaxUrl,
				dataType: 'json',
				delay: 250,
				cache: true,
				data: function (params) {
					return { term: params.term, action: action, security: L.searchProductsNonce };
				},
				processResults: function (data) {
					var results = [];
					if (data && typeof data === 'object') {
						$.each(data, function (id, txt) { results.push({ id: id, text: txt }); });
					}
					return { results: results };
				}
			},
			escapeMarkup: function (m) { return m; }
		});
	}

	function onFrameLoad() {
		var $wrap    = $('#spbwc-tl-preview-frame-wrap');
		var $loading = $('#spbwc-tl-preview-frame-loading');
		var $update  = $('#spbwc-tl-preview-frame-updating');
		var $error   = $('#spbwc-tl-preview-frame-error');
		$loading.prop('hidden', true);
		$update.prop('hidden', true);
		$wrap.removeClass('is-updating');

		// Defer the success check one tick so Angular has a chance to mount
		// the wrapper element after parsing the document.
		setTimeout(function () {
			var doc = null;
			try { doc = document.getElementById('spbwc-tl-preview-frame').contentDocument; } catch (e) {}
			var ok = !!(doc && doc.querySelector('.nbo-wrapper.nbo-style-cloodo'));
			$error.prop('hidden', ok);
			$wrap.toggleClass('has-error', !ok);
		}, 250);
	}

	function onPreviewMessage(ev) {
		if (!ev || !ev.data || ev.data.source !== 'spbwc-tpl-preview') return;
		// Same-origin check — admin and preview live under the WP site URL.
		var expected = L.previewOrigin || window.location.origin;
		if (ev.origin !== expected) return;

		if (ev.data.type === 'height') {
			var h = parseInt(ev.data.value, 10);
			if (h > 0) {
				// Cap to avoid runaway growth; the CSS minimum (420px) still applies.
				var maxH = Math.max(420, Math.round(window.innerHeight * 0.82));
				h = Math.min(h, maxH);
				$('#spbwc-tl-preview-frame').css('height', h + 'px');
			}
		} else if (ev.data.type === 'total') {
			lastLiveTotal = (ev.data.value || '').trim();
			refreshSubtitle();
		} else if (ev.data.type === 'product' && ev.data.value) {
			// Bridge confirms the server resolved the product context — use the
			// authoritative name from the server in case the dropdown text drifted.
			var pid  = parseInt(ev.data.value.product_id, 10) || 0;
			var pname = String(ev.data.value.product_name || '').trim();
			if (pid > 0 && pname) {
				currentProductId = pid;
				currentProductName = pname;
				refreshSubtitle();
			}
		}
	}

	function refreshSubtitle() {
		var sub = heroBase || '';
		if (currentProductId > 0 && currentProductName) {
			var withLbl = L.i18n.previewWith || 'with';
			sub = sub ? (sub + ' · ' + withLbl + ' ' + currentProductName) : (withLbl + ' ' + currentProductName);
		}
		if (lastLiveTotal) {
			var prefix = L.i18n.estimatedTotal || 'est.';
			sub = sub ? (sub + ' · ' + prefix + ' ' + lastLiveTotal) : (prefix + ' ' + lastLiveTotal);
		}
		$('#spbwc-tl-preview-subtitle').text(sub);
	}

	function openPreview(slug, name) {
		currentTemplate = { slug: slug, name: name };

		// Populate header immediately from card data — subtitle updated after load.
		$('#spbwc-tl-preview-title').text(name);
		heroBase = '';
		lastLiveTotal = '';
		refreshSubtitle();

		// Reset to Preview tab and clear the metadata panels.
		$previewDialog.find('.spbwc-tl-tab').removeClass('spbwc-tl-tab--active');
		$previewDialog.find('.spbwc-tl-tab[data-tab="live"]').addClass('spbwc-tl-tab--active');
		$previewDialog.find('.spbwc-tl-tabpanel').removeClass('spbwc-tl-tabpanel--active');
		$previewDialog.find('[data-tabpanel="live"]').addClass('spbwc-tl-tabpanel--active');
		$('#spbwc-tl-preview-fields').empty();
		$('#spbwc-tl-preview-about').empty();

		// Reset preview controls + clear any prior error/updating state.
		$previewDialog.find('.spbwc-tl-vp__btn').removeClass('spbwc-tl-vp__btn--active');
		$previewDialog.find('.spbwc-tl-vp__btn[data-pv="desktop"]').addClass('spbwc-tl-vp__btn--active');
		$('#spbwc-tl-preview-live').find('.spbwc-tl-preview-stage').attr('data-viewport', 'desktop');
		$('#spbwc-tl-preview-frame-wrap').removeClass('is-updating has-error');
		$('#spbwc-tl-preview-frame-error').prop('hidden', true);
		$('#spbwc-tl-preview-frame-updating').prop('hidden', true);

		// Reset the "preview against product" picker between templates so each
		// preview starts in the sample-base-price mode.
		clearPreviewProduct();
		$('#spbwc-tl-baseprice').prop('disabled', false).removeClass('is-disabled');

		// Restore the merchant's last-typed sample base price so reopening
		// the dialog keeps the live total they were comparing against.
		var startBase = readStoredBase();
		$('#spbwc-tl-baseprice').val(startBase);

		firstFrameLoad = true; // first open → full overlay
		loadPreviewFrame(slug, startBase);

		openDialog($previewDialog);

		// Lazy-init the product picker after the dialog is open (Select2
		// reads width:0 on hidden elements otherwise).
		if (!previewProductSelectInited) {
			initPreviewProductSelect($('#spbwc-tl-preview-product'));
			previewProductSelectInited = true;
		}

		// Fields + About come from the lightweight metadata endpoint; the live
		// UI itself is rendered by the iframe (real storefront view).
		$.post(L.ajaxUrl, {
			action: 'spbwc_template_preview',
			_ajax_nonce: L.nonce,
			slug: slug
		}).done(function (resp) {
			if (!resp || !resp.success) return;
			renderPreviewFields(resp.data);
			renderPreviewAbout(resp.data);
			heroBase = resp.data.meta.category + ' · ' + resp.data.meta.field_count + ' fields';
			refreshSubtitle();
		});
	}

	// Point the preview iframe at the shared storefront renderer for this
	// template + sample base price. First load shows a full overlay; later
	// reloads (base-price tweaks) dim the iframe + show a corner "Updating…"
	// pill, keeping the prior content visible to avoid a jarring flash.
	function loadPreviewFrame(slug, base) {
		var $frame   = $('#spbwc-tl-preview-frame');
		var $wrap    = $('#spbwc-tl-preview-frame-wrap');
		var $loading = $('#spbwc-tl-preview-frame-loading');
		var $update  = $('#spbwc-tl-preview-frame-updating');
		var $error   = $('#spbwc-tl-preview-frame-error');
		if (!L.previewUrl) {
			$loading.prop('hidden', true);
			return;
		}
		// Same slug + same base → nothing to reload (defensive against
		// stray events; harmless if it slips through).
		if (lastFrameSlug === slug && lastFrameBase === base && !firstFrameLoad) { return; }
		lastFrameSlug = slug;
		lastFrameBase = base;

		$error.prop('hidden', true);
		$wrap.removeClass('has-error');
		if (firstFrameLoad) {
			$loading.prop('hidden', false);
			$update.prop('hidden', true);
			$wrap.removeClass('is-updating');
		} else {
			$loading.prop('hidden', true);
			$update.prop('hidden', false);
			$wrap.addClass('is-updating');
		}

		var url = L.previewUrl +
			'&slug=' + encodeURIComponent(slug) +
			'&base=' + encodeURIComponent(base || 0);
		if (currentProductId > 0) {
			url += '&product_id=' + encodeURIComponent(currentProductId);
		}
		$frame.attr('src', url);
		firstFrameLoad = false; // any subsequent reload is an update
	}

	function renderPreviewFields(data) {
		var render = data.render || {};
		var fields = render.fields || [];
		if (!fields.length) {
			$('#spbwc-tl-preview-fields').html(
				'<p class="spbwc-tl-empty">' + esc(L.i18n.noFields || 'No fields in this template.') + '</p>'
			);
			return;
		}
		// One card per field — easier to scan than a flat 5-col table, and the
		// card structure mirrors what the buyer actually sees in the preview.
		var html = '<div class="spbwc-tl-fields-list">';
		fields.forEach(function (f, idx) {
			var typeLabel = (f.data_type === 'i')
				? displayLabel(f)            // input fields — show input type ("Text input", "Upload"…)
				: displayTypeLabel(f.display); // multiple-choice — show display style ("Dropdown", "Swatch"…)
			html += '<article class="spbwc-tl-field-card">';
			html += '<header class="spbwc-tl-field-card__head">';
			html += '<div class="spbwc-tl-field-card__title">';
			html += '<span class="spbwc-tl-field-card__index">' + (idx + 1) + '</span>';
			html += '<h4>' + esc(f.title || '(untitled)') + '</h4>';
			html += '</div>';
			html += '<div class="spbwc-tl-field-card__chips">';
			html += '<span class="spbwc-tl-pill spbwc-tl-pill--type">' + esc(typeLabel) + '</span>';
			if (f.required) {
				html += '<span class="spbwc-tl-pill spbwc-tl-pill--required">' + esc(L.i18n.required || 'Required') + '</span>';
			}
			html += '</div>';
			html += '</header>';
			if (f.description) {
				html += '<p class="spbwc-tl-field-card__desc">' + esc(f.description) + '</p>';
			}
			if (f.attributes && f.attributes.length) {
				html += '<div class="spbwc-tl-field-card__attrs">';
				f.attributes.slice(0, 12).forEach(function (a) {
					html += '<span class="spbwc-tl-attr-chip">' + esc(a.name || '—') + '</span>';
				});
				if (f.attributes.length > 12) {
					html += '<span class="spbwc-tl-attr-chip spbwc-tl-attr-chip--more">+' + (f.attributes.length - 12) + '</span>';
				}
				html += '</div>';
			}
			html += '</article>';
		});
		html += '</div>';
		$('#spbwc-tl-preview-fields').html(html);
	}

	function displayLabel(f) {
		if (f.data_type === 'i') {
			switch (f.input_type) {
				case 'u': return L.i18n.inputUpload;
				case 'a': return L.i18n.inputTextarea;
				case 'n': case 'r': return L.i18n.inputNumber;
				default:  return L.i18n.inputText;
			}
		}
		return f.nbd_type || 'multiple';
	}

	function displayTypeLabel(d) {
		switch (d) {
			case 'd': case 'ad': return L.i18n.displayDropdown;
			case 'r': return L.i18n.displayRadio;
			case 's': return L.i18n.displaySwatch;
			case 'l': case 'xl': return L.i18n.displayLabel;
			default:  return d || '—';
		}
	}

	function renderPreviewAbout(data) {
		var m   = data.meta || {};
		var i18 = L.i18n || {};
		var html = '<div class="spbwc-tl-about">';

		// Compact 2-col key-value grid for the structural metadata.
		html += '<dl class="spbwc-tl-about__grid">';
		html += aboutPair(i18.aboutSlug || 'Template slug', '<code class="spbwc-tl-about__code">' + esc(m.slug) + '</code>');
		html += aboutPair(i18.aboutCategory || 'Category', esc(m.category));
		html += aboutPair(i18.aboutFieldCount || 'Field count', '<strong>' + esc(String(m.field_count)) + '</strong>');
		html += aboutPair(i18.aboutPricingMethod || 'Pricing method', esc((m.pricing_method || '').replace(/_/g, ' ')));
		html += aboutPair(i18.aboutVersion || 'Template version', '<code class="spbwc-tl-about__code">' + esc(m.template_version) + '</code>');
		html += '</dl>';

		// Description gets its own block — paragraph reads better than a row.
		if (m.description) {
			html += '<div class="spbwc-tl-about__block">';
			html += '<div class="spbwc-tl-about__block-label">' + esc(i18.aboutDescription || 'Description') + '</div>';
			html += '<p class="spbwc-tl-about__block-text">' + esc(m.description) + '</p>';
			html += '</div>';
		}

		// Pricing-source caveat — surfaced as an amber callout so merchants
		// don't miss a "configured but unsupported" warning.
		if (m.pricing_source) {
			html += '<div class="spbwc-tl-about__callout">';
			html += '<span class="dashicons dashicons-warning spbwc-tl-about__callout-icon" aria-hidden="true"></span>';
			html += '<div>';
			html += '<div class="spbwc-tl-about__callout-title">' + esc(i18.aboutPricingSource || 'Pricing source') + '</div>';
			html += '<div class="spbwc-tl-about__callout-text">' + esc(m.pricing_source) + '</div>';
			html += '</div>';
			html += '</div>';
		}

		html += '</div>';
		$('#spbwc-tl-preview-about').html(html);
	}

	function aboutPair(label, valueHtml) {
		return '<div class="spbwc-tl-about__pair">' +
			'<dt>' + esc(label) + '</dt>' +
			'<dd>' + valueHtml + '</dd>' +
			'</div>';
	}

	// ─── Apply dialog ────────────────────────────────────────────────
	var productSearchInitialized = false;
	var catSelectInitialized     = false;
	// Cache the submit button's initial HTML (set by PHP) so we can restore
	// it after error/fail without duplicating the icon + label in JS.
	var applySubmitHtml = '';

	function initApply() {
		applySubmitHtml = $('#spbwc-tl-apply-submit').html();
		$(document).on('click', '.spbwc-tl-apply', function () {
			openApply($(this).data('slug'), $(this).data('name'));
		});

		// Radio cards toggle.
		$applyDialog.on('change', 'input[name="apply_for"]', function () {
			var mode = $(this).val();
			$applyDialog.find('.spbwc-tl-radio-card').removeClass('spbwc-tl-radio-card--active');
			$(this).closest('.spbwc-tl-radio-card').addClass('spbwc-tl-radio-card--active');
			$applyDialog.find('.spbwc-tl-scope').prop('hidden', true);
			$applyDialog.find('.spbwc-tl-scope--' + mode).prop('hidden', false);
			// Lazy-init category Select2 the first time the category scope is shown.
			// (Can't init while hidden — Select2 calculates width:0 on hidden elements.)
			if (mode === 'c' && !catSelectInitialized) {
				initCatSelect($('#spbwc-tl-categories'));
				catSelectInitialized = true;
			}
		});

		// Live product count badge — update whenever product selection changes.
		$applyDialog.on('change', '#spbwc-tl-products', function () {
			var selected = $(this).val() || [];
			updateProductBadge(selected.length);
		});

		// Close.
		$applyDialog.on('click', '[data-close="apply"]', function () {
			closeDialog($applyDialog);
		});

		// Submit.
		$('#spbwc-tl-apply-form').on('submit', submitApply);
	}

	function openApply(slug, name) {
		// Restore form view in case the dialog previously showed the success state.
		$('#spbwc-tl-apply-body-content').prop('hidden', false);
		$('#spbwc-tl-apply-success').prop('hidden', true);
		$('#spbwc-tl-apply-footer').show();

		$('#spbwc-tl-apply-error').prop('hidden', true).find('p').text('');
		$('#spbwc-tl-apply-slug').val(slug);
		$('#spbwc-tl-apply-title').val(name);
		$('#spbwc-tl-apply-subtitle').text(name);

		// Reset scope to products mode.
		$applyDialog.find('input[name="apply_for"][value="p"]').prop('checked', true).trigger('change');
		// Clear previous selections and badge.
		if (productSearchInitialized) {
			$('#spbwc-tl-products').val(null).trigger('change');
			updateProductBadge(0);
		}
		if (catSelectInitialized) $('#spbwc-tl-categories').val(null).trigger('change');
		$('#spbwc-tl-apply-submit').prop('disabled', false).html(
			applySubmitHtml
		);

		openDialog($applyDialog);

		// Lazy-init product search Select2 the first time the dialog is opened.
		// WC may have auto-init'd .wc-product-search on DOM ready (while dialog
		// was hidden) with wrong settings — destroy that instance first.
		if (!productSearchInitialized) {
			initWcProductSearch($('#spbwc-tl-products'));
			productSearchInitialized = true;
		}
		// Category select is lazy-init'd in the radio change handler (not here)
		// because initialising on a [hidden] element gives width:0 in Select2.
	}

	// Update the "N selected" badge next to the Products label.
	function updateProductBadge(count) {
		var $badge = $('#spbwc-tl-products-count');
		if (count > 0) {
			$badge.text(count).prop('hidden', false);
		} else {
			$badge.prop('hidden', true);
		}
	}

	function initWcProductSearch($el) {
		if (!$.fn.selectWoo && !$.fn.select2) {
			// Fallback: make the native select usable.
			$el.attr('size', 6);
			return;
		}
		var fn = $.fn.selectWoo ? 'selectWoo' : 'select2';
		// Destroy any WC auto-init that ran on DOM ready with wrong settings.
		try { if ($el.data(fn) || $el.hasClass('select2-hidden-accessible')) { $el[fn]('destroy'); } } catch (e) {}
		var action = $el.data('action') || 'woocommerce_json_search_products_and_variations';
		$el[fn]({
			placeholder: $el.data('placeholder') || L.i18n.selectProduct,
			allowClear: false,
			minimumInputLength: 1,
			width: '100%',
			// Append to <body> — NOT to the dialog.  The dialog has
			// transform:translate(-50%,-50%) which breaks Select2's
			// offsetTop/Left positioning when dropdownParent is inside
			// a transformed element.  Body-level appending + z-index
			// 160002 (set in CSS) keeps the dropdown above the dialog.
			dropdownParent: $('body'),
			ajax: {
				url: L.ajaxUrl,
				dataType: 'json',
				delay: 250,
				cache: true,
				data: function (params) {
					return {
						term: params.term,
						action: action,
						security: L.searchProductsNonce
					};
				},
				processResults: function (data) {
					var results = [];
					if (data && typeof data === 'object') {
						$.each(data, function (id, txt) {
							results.push({ id: id, text: txt });
						});
					}
					return { results: results };
				}
			},
			escapeMarkup: function (m) { return m; }
		});
	}

	function initCatSelect($el) {
		if (!$.fn.selectWoo && !$.fn.select2) return;
		var fn = $.fn.selectWoo ? 'selectWoo' : 'select2';
		$el[fn]({
			placeholder: L.i18n.selectCategory,
			width: '100%',
			dropdownParent: $('body') // see note in initWcProductSearch
		});
	}

	function submitApply(e) {
		e.preventDefault();
		var $err    = $('#spbwc-tl-apply-error');
		var $submit = $('#spbwc-tl-apply-submit');
		$err.prop('hidden', true).find('p').text('');

		var mode  = $('#spbwc-tl-apply-form').find('input[name="apply_for"]:checked').val();
		var scope = mode === 'p'
			? ($('#spbwc-tl-products').val() || [])
			: ($('#spbwc-tl-categories').val() || []);
		// Product/category selection is OPTIONAL: user can apply the template
		// without assigning it to any product or category and configure the
		// assignment later from the Pricing Options screen.
		// (No validation block here — empty scope_ids is a valid submission.)

		postApply(mode, scope, false);
	}

	// Issues the apply POST (with optional force=1 after the conflict confirm).
	// Splitting this out lets the conflict dialog re-invoke the same flow
	// without rebuilding the form state.
	function postApply(mode, scope, force) {
		var $err    = $('#spbwc-tl-apply-error');
		var $submit = $('#spbwc-tl-apply-submit');
		$err.prop('hidden', true).find('p').text('');
		$submit.prop('disabled', true).text(L.i18n.applying);

		$.post(L.ajaxUrl, {
			action: 'spbwc_template_apply',
			_ajax_nonce: L.nonce,
			slug: $('#spbwc-tl-apply-slug').val(),
			title: $('#spbwc-tl-apply-title').val(),
			apply_for: mode,
			scope_ids: scope,
			force: force ? 1 : 0
		}).done(function (resp) {
			// Conflict — server detected products already assigned to another
			// product-level option. Ask the merchant whether to move them, and
			// resubmit with force=1 on confirm.
			if (resp && resp.success && resp.data && resp.data.conflict) {
				confirmConflict(resp.data.conflicts || []).then(function (ok) {
					if (ok) {
						postApply(mode, scope, true);
					} else {
						$submit.prop('disabled', false).html(applySubmitHtml);
					}
				});
				return;
			}
			if (!resp || !resp.success) {
				$err.find('p').text((resp && resp.data && resp.data.message) || L.i18n.genericError);
				$err.prop('hidden', false);
				$submit.prop('disabled', false).html(
					applySubmitHtml
				);
				return;
			}
			if (resp.data && resp.data.edit_url) {
				// Show inline success state — hide form, show checkmark + spinner.
				$('#spbwc-tl-apply-body-content').prop('hidden', true);
				$('#spbwc-tl-apply-footer').hide();
				$('#spbwc-tl-apply-success').prop('hidden', false);
				// Redirect after a short pause so the user sees the confirmation.
				setTimeout(function () {
					window.location.href = resp.data.edit_url;
				}, 1400);
			} else {
				// Applied without a specific edit URL — close dialog and show page notice.
				closeDialog($applyDialog);
				$submit.prop('disabled', false).html(
					applySubmitHtml
				);
				var $wrap = $('.spbwc-template-library');
				$wrap.find('.spbwc-tl-apply-success-notice').remove();
				var $notice = $('<div class="notice notice-success is-dismissible spbwc-tl-apply-success-notice">' +
					'<p>' + esc(L.i18n.applySuccess || 'Template applied successfully.') + '</p></div>');
				$wrap.prepend($notice);
				setTimeout(function () { $notice.fadeOut(400, function () { $notice.remove(); }); }, 5000);
			}
		}).fail(function () {
			$err.find('p').text(L.i18n.genericError);
			$err.prop('hidden', false);
			$submit.prop('disabled', false).html(
				applySubmitHtml
			);
		});
	}

	// Confirm listing each product → its current pricing option. Returns a
	// Promise<boolean> (true = merchant accepts the replacement). Prefers the
	// shared token-styled dialog; falls back to native confirm.
	function confirmConflict(conflicts) {
		var title    = L.i18n.applyConflictTitle || 'Replace existing pricing option?';
		var intro    = L.i18n.applyConflictIntro || '';
		var itemTpl  = L.i18n.applyConflictItem || '%1$s — currently uses "%2$s"';
		var untitled = L.i18n.applyConflictUntitled || '(untitled)';

		var lines = conflicts.map(function (c) {
			var productName = (c.product_title && String(c.product_title).trim()) ||
				('#' + (c.product_id || ''));
			var optionName  = (c.current_option_title && String(c.current_option_title).trim()) ||
				untitled;
			return '• ' + itemTpl
				.replace('%1$s', productName)
				.replace('%2$s', optionName);
		});

		if (window.spbwcDialog) {
			var body = (intro ? intro + '\n\n' : '') + lines.join('\n');
			return window.spbwcDialog.confirm({
				title: title,
				message: body,
				tone: 'warning',
				okText: L.i18n.applyConflictOk || ''
			});
		}
		var nativeBody = title + '\n\n' + intro + (intro ? '\n\n' : '') + lines.join('\n');
		// eslint-disable-next-line no-alert
		return Promise.resolve(window.confirm(nativeBody));
	}

	// ─── Dialog helpers ──────────────────────────────────────────────
	// We use el.show() (non-modal) instead of el.showModal() to avoid the
	// CSS top-layer stacking context that showModal() creates.  When showModal()
	// is used, the <dialog> enters the browser top-layer which renders above
	// ALL other elements regardless of z-index — including Select2 dropdowns
	// that are appended to <body>.  show() + a manual high-z-index backdrop
	// gives us the same visual result without breaking Select2 autocomplete.
	var $backdrop = $('#spbwc-tl-backdrop');

	// Escape key: close whichever dialog is open.
	// show() (non-modal) does NOT get the browser's native Esc handling.
	$(document).on('keydown.spbwcdlg', function (e) {
		if (e.key !== 'Escape') return;
		// Apply dialog takes priority (it may have been opened from preview).
		if ($applyDialog[0] && $applyDialog[0].open) {
			e.preventDefault();
			closeDialog($applyDialog);
		} else if ($previewDialog[0] && $previewDialog[0].open) {
			e.preventDefault();
			closeDialog($previewDialog);
		}
	});

	function openDialog($dlg) {
		var el = $dlg[0];
		if (!el) return;
		if (typeof el.show === 'function') {
			try { el.show(); } catch (e) { $dlg.attr('open', 'open'); }
		} else {
			$dlg.attr('open', 'open');
		}
		$backdrop.addClass('is-active');
		// Close on backdrop click (one-shot rebind to avoid stacking handlers).
		$backdrop.off('click.spbwcdlg').on('click.spbwcdlg', function () {
			closeDialog($dlg);
		});
	}
	function closeDialog($dlg) {
		var el = $dlg[0];
		if (!el) return;
		if (typeof el.close === 'function') {
			try { el.close(); } catch (e) { $dlg.removeAttr('open'); }
		} else {
			$dlg.removeAttr('open');
		}
		$backdrop.removeClass('is-active');
	}

	function esc(s) {
		if (s == null) return '';
		return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
	}

})(jQuery);
