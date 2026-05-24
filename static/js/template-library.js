/**
 * Template Library — search/filter, Preview dialog (classic mockup, fields,
 * about) and Apply dialog (radio cards + Select2 product/category picker).
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

		// Viewport switcher inside live panel (delegated — content replaced on each open).
		var $live = $('#spbwc-tl-preview-live');

		$live.on('click', '.preview-segment__btn[data-pv]', function () {
			var vp = $(this).data('pv');
			$(this).siblings('.preview-segment__btn').removeClass('preview-segment__btn--active');
			$(this).addClass('preview-segment__btn--active');
			$(this).closest('.std-classic-wrap').find('.viewport-frame').attr('data-viewport', vp);
		});

		// ── Interactive field controls ─────────────────────────────────

		// Swatch click: activate selected, deactivate siblings, update chosen.
		$live.on('click', '.std-classic-wrap .swatch', function () {
			var $s = $(this);
			$s.closest('.swatch-grid').find('.swatch').removeClass('swatch--active');
			$s.addClass('swatch--active');
			var name = $s.find('.swatch__name').text();
			$s.closest('.po-section').find('.po-section__chosen strong').text(name);
		});

		// Radio click.
		$live.on('click', '.std-classic-wrap .po-radio', function () {
			var $r = $(this);
			$r.closest('.po-radios').find('.po-radio').removeClass('po-radio--active');
			$r.addClass('po-radio--active');
			var name = $r.find('span:not(.po-radio__icon)').first().text().split(' · ')[0];
			$r.closest('.po-section').find('.po-section__chosen strong').text(name);
		});

		// Qty-break click.
		$live.on('click', '.std-classic-wrap .qty-break', function () {
			$(this).closest('.qty-breaks').find('.qty-break').removeClass('qty-break--active');
			$(this).addClass('qty-break--active');
		});

		// Fake-select: toggle dropdown open/close.
		$live.on('click', '.std-classic-wrap .po-select__display', function (e) {
			e.stopPropagation();
			var $sel = $(this).closest('.po-select');
			var $dd  = $sel.find('.po-select__dropdown');
			var opening = $dd.prop('hidden');
			// Close all dropdowns first.
			$live.find('.po-select__dropdown').prop('hidden', true);
			$live.find('.po-select').removeClass('po-select--open');
			if (opening) {
				$dd.prop('hidden', false);
				$sel.addClass('po-select--open');
			}
		});

		// Fake-select: option pick.
		$live.on('click', '.std-classic-wrap .po-select__option', function (e) {
			e.stopPropagation();
			var $opt = $(this);
			var $dd  = $opt.closest('.po-select__dropdown');
			var $sel = $opt.closest('.po-select');
			$dd.find('.po-select__option').removeClass('po-select__option--active');
			$opt.addClass('po-select__option--active');
			$sel.find('.po-select__text').text($opt.text());
			$dd.prop('hidden', true);
			$sel.removeClass('po-select--open');
			$sel.closest('.po-section').find('.po-section__chosen strong').text($opt.data('name'));
		});

		// Fake-select keyboard: Enter/Space open; Escape close.
		$live.on('keydown', '.std-classic-wrap .po-select__display', function (e) {
			if (e.key === 'Enter' || e.key === ' ') {
				e.preventDefault();
				$(this).trigger('click');
			} else if (e.key === 'Escape') {
				$(this).closest('.po-select').find('.po-select__dropdown').prop('hidden', true);
				$(this).closest('.po-select').removeClass('po-select--open');
			}
		});

		// Close any open dropdown when clicking elsewhere in the live panel.
		$live.on('click.po-select-close', function (e) {
			if (!$(e.target).closest('.po-select').length) {
				$live.find('.po-select__dropdown').prop('hidden', true);
				$live.find('.po-select').removeClass('po-select--open');
			}
		});

		// "Apply this template" CTA from preview footer.
		$('#spbwc-tl-preview-apply-cta').on('click', function () {
			if (!currentTemplate) return;
			closeDialog($previewDialog);
			openApply(currentTemplate.slug, currentTemplate.name);
		});
	}

	function openPreview(slug, name) {
		currentTemplate = { slug: slug, name: name };

		// Populate header immediately from card data — subtitle updated after load.
		$('#spbwc-tl-preview-title').text(name);
		$('#spbwc-tl-preview-subtitle').text('');

		// Reset to Preview tab and clear other panels.
		$previewDialog.find('.spbwc-tl-tab').removeClass('spbwc-tl-tab--active');
		$previewDialog.find('.spbwc-tl-tab[data-tab="live"]').addClass('spbwc-tl-tab--active');
		$previewDialog.find('.spbwc-tl-tabpanel').removeClass('spbwc-tl-tabpanel--active');
		$previewDialog.find('[data-tabpanel="live"]').addClass('spbwc-tl-tabpanel--active');
		$('#spbwc-tl-preview-fields').empty();
		$('#spbwc-tl-preview-about').empty();

		// Show inline skeleton inside the live panel; disable tabs until data arrives.
		$('#spbwc-tl-preview-live').html(
			'<div class="spbwc-tl-preview-skeleton">' +
			'<span class="spinner is-active"></span>' +
			'<span>' + esc(L.i18n.loadingPreview) + '</span>' +
			'</div>'
		);
		$previewDialog.find('.spbwc-tl-tab').prop('disabled', true);

		openDialog($previewDialog);

		$.post(L.ajaxUrl, {
			action: 'spbwc_template_preview',
			_ajax_nonce: L.nonce,
			slug: slug
		}).done(function (resp) {
			$previewDialog.find('.spbwc-tl-tab').prop('disabled', false);
			if (!resp || !resp.success) {
				$('#spbwc-tl-preview-live').html(errorBlock((resp && resp.data && resp.data.message) || L.i18n.previewFailed));
				return;
			}
			renderPreviewLive(resp.data);
			renderPreviewFields(resp.data);
			renderPreviewAbout(resp.data);
			$('#spbwc-tl-preview-subtitle').text(resp.data.meta.category + ' · ' + resp.data.meta.field_count + ' fields');
		}).fail(function () {
			$previewDialog.find('.spbwc-tl-tab').prop('disabled', false);
			$('#spbwc-tl-preview-live').html(errorBlock(L.i18n.previewFailed));
		});
	}

	function renderPreviewLive(data) {
		var render  = data.render || {};
		var fields  = render.fields || [];
		var meta    = data.meta || {};
		var name    = meta.name || (currentTemplate && currentTemplate.name) || '';
		var cat     = (meta.category || '').replace(/_/g, ' ');
		var catLabel = cat.charAt(0).toUpperCase() + cat.slice(1);
		var desc    = meta.description || '';

		// Gallery gradient per category.
		var gradients = {
			stationery:   'linear-gradient(135deg,#6366f1 0%,#4f46e5 100%)',
			marketing:    'linear-gradient(135deg,#f59e0b 0%,#d97706 100%)',
			large_format: 'linear-gradient(135deg,#10b981 0%,#059669 100%)',
			promotional:  'linear-gradient(135deg,#ec4899 0%,#be185d 100%)',
			signage:      'linear-gradient(135deg,#ef4444 0%,#b91c1c 100%)',
			envelopes:    'linear-gradient(135deg,#78716c 0%,#57534e 100%)'
		};
		var galleryBg = gradients[meta.category] || 'linear-gradient(135deg,#3b82f6 0%,#2563eb 100%)';

		var sample = sampleTotal(fields);

		var html = '<div class="std-classic-wrap">';

		// ── Preview shell ──────────────────────────────────────────────
		html += '<div class="preview-shell">';
		html += '<div class="preview-shell__inner">';
		html += '<div class="preview-shell__title">📐 Customer View — Template Preview</div>';
		html += '<div class="preview-segment" role="group" aria-label="Viewport">';
		html += '<button class="preview-segment__btn preview-segment__btn--active" data-pv="desktop">🖥 Desktop</button>';
		html += '<button class="preview-segment__btn" data-pv="tablet">⊞ Tablet</button>';
		html += '<button class="preview-segment__btn" data-pv="mobile">📱 Mobile</button>';
		html += '</div>';
		html += '</div></div>';

		// ── Viewport frame ─────────────────────────────────────────────
		html += '<div class="viewport-frame" data-viewport="desktop">';

		// Breadcrumb
		html += '<div class="breadcrumb">';
		html += 'Home › <span>' + esc(catLabel || 'Products') + '</span> › <strong>' + esc(name) + '</strong>';
		html += '</div>';

		// Product detail grid
		html += '<div class="product-detail">';

		// ── Gallery (left) ─────────────────────────────────────────────
		html += '<section class="gallery">';
		html += '<div class="gallery__main">';
		html += '<span class="gallery__badge">★ Preview</span>';
		html += '<div class="gallery__product" style="background:' + galleryBg + '">';
		html += '<div class="gallery__product-text">';
		html += '<strong>' + esc(name) + '</strong><span>Template Preview</span>';
		html += '</div></div></div>';
		html += '<div class="gallery__thumbs">';
		html += '<button class="gallery__thumb gallery__thumb--active">Front</button>';
		html += '<button class="gallery__thumb">Back</button>';
		html += '<button class="gallery__thumb">Detail</button>';
		html += '</div>';
		html += '</section>';

		// ── Product info (right) ───────────────────────────────────────
		html += '<section class="product-info">';
		if (catLabel) {
			html += '<div class="product-info__category">' + esc(catLabel) + '</div>';
		}
		html += '<h1 class="product-info__title">' + esc(name) + '</h1>';
		html += '<div class="product-info__rating">';
		html += '<span class="product-info__stars">★★★★★</span>';
		html += '<span><strong>5.0</strong></span><span>· Template preview</span>';
		html += '</div>';

		if (sample.value) {
			html += '<div class="product-info__price-wrap">';
			html += '<div class="product-info__total-label">Starting from</div>';
			html += '<div class="product-info__total">' + esc(L.currencySymbol + sample.value);
			html += '<span class="product-info__total-unit">/ item (est.)</span></div>';
			html += '<div class="product-info__price-note">Estimated using default selections — actual price varies.</div>';
			html += '</div>';
		}

		if (desc) {
			html += '<p class="product-info__desc">' + esc(desc) + '</p>';
		}

		// Fields
		if (!fields.length) {
			html += '<div class="preview-empty">This template has no configurable fields.</div>';
		} else {
			fields.forEach(function (f) { html += renderClassicField(f); });
		}

		// Quantity breaks
		if (render.quantity_breaks && render.quantity_breaks.length > 1) {
			html += renderQtyBreaks(render.quantity_breaks);
		}

		// Add to cart (disabled — preview only)
		html += '<div>';
		html += '<button class="std-btn std-btn--primary" disabled>🛒 Add to cart — preview only</button>';
		html += '</div>';

		html += '</section>';
		html += '</div>'; // product-detail
		html += '</div>'; // viewport-frame
		html += '</div>'; // std-classic-wrap

		$('#spbwc-tl-preview-live').html(html);
	}

	function renderClassicField(f) {
		var html = '<div class="po-section">';
		html += '<div class="po-section__heading">';
		html += '<div class="po-section__label"><span>' + esc(f.title || '(untitled)') + '</span>';
		if (f.required) html += '<span class="po-section__required">*</span>';
		html += '</div>';
		// Don't show chosen-value badge for free-text / upload input fields.
		var chosen = (f.data_type !== 'i') ? pickChosen(f) : '';
		if (chosen) html += '<div class="po-section__chosen"><strong>' + esc(chosen) + '</strong></div>';
		html += '</div>';

		if (f.description) {
			html += '<div class="po-section__desc">' + esc(f.description) + '</div>';
		}

		if (f.data_type === 'i') {
			html += renderInputField(f);
		} else if (f.display === 's') {
			html += renderSwatch(f);
		} else if (f.display === 'd' || f.display === 'ad') {
			html += renderSelect(f);
		} else {
			html += renderRadios(f);
		}

		html += '</div>';
		return html;
	}

	function renderSwatch(f) {
		if (!f.attributes || !f.attributes.length) {
			return '<div class="po-section__desc">No options configured.</div>';
		}
		var textures = ['matte', 'glossy', 'soft', 'linen', 'metallic', 'recycled'];
		var hasSelected = f.attributes.some(function (x) { return x.selected; });
		var html = '<div class="swatch-grid">';
		f.attributes.forEach(function (a, i) {
			var active  = a.selected || (!hasSelected && i === 0);
			var popular = active && i === 0;

			// Visual: solid color if preview_type === 'c' + color; else cycle textures.
			var visualClass = '', visualStyle = '';
			if (a.preview_type === 'c' && a.color) {
				visualStyle = ' style="background:' + esc(a.color) + ';"';
			} else {
				visualClass = ' swatch__visual--' + textures[i % textures.length];
			}

			html += '<div class="swatch' + (active ? ' swatch--active' : '') + '">';
			html += '<div class="swatch__visual' + visualClass + '"' + visualStyle + '>';
			if (popular) html += '<span class="swatch__popular">Popular</span>';
			html += '</div>';
			html += '<div class="swatch__body">';
			html += '<div class="swatch__name">' + esc(a.name || '—') + '</div>';
			html += '<div class="swatch__price' + (priceIsFree(a.price) ? ' swatch__price--free' : '') + '">' + esc(formatPrice(a.price, f.price_type)) + '</div>';
			html += '</div></div>';
		});
		html += '</div>';
		return html;
	}

	function renderRadios(f) {
		if (!f.attributes || !f.attributes.length) {
			return '<div class="po-section__desc">No options configured.</div>';
		}
		// Orientation icon hints for common option names.
		var icons = { landscape: '▭', portrait: '▯', horizontal: '▭', vertical: '▯', square: '⬜' };
		var hasSelected = f.attributes.some(function (x) { return x.selected; });
		var html = '<div class="po-radios">';
		f.attributes.forEach(function (a, i) {
			var active     = a.selected || (!hasSelected && i === 0);
			var label      = a.name || '—';
			var pricePart  = !priceIsFree(a.price) ? ' · ' + formatPrice(a.price, f.price_type) : '';
			var icon       = icons[label.toLowerCase()];
			html += '<button type="button" class="po-radio' + (active ? ' po-radio--active' : '') + '">';
			if (icon) html += '<span class="po-radio__icon">' + icon + '</span>';
			html += '<span>' + esc(label + pricePart) + '</span>';
			html += '</button>';
		});
		html += '</div>';
		return html;
	}

	function renderSelect(f) {
		if (!f.attributes || !f.attributes.length) {
			return '<div class="po-section__desc">No options configured.</div>';
		}
		// Fake-select — avoids WP admin native <select> CSS conflicts.
		// Dropdown panel holds all options; JS handles open/close + selection.
		var selIdx = 0;
		f.attributes.forEach(function (a, i) { if (a.selected) selIdx = i; });
		var sel   = f.attributes[selIdx];
		var label = sel ? sel.name || '—' : '—';
		var price = sel && !priceIsFree(sel.price) ? ' · ' + formatPrice(sel.price, f.price_type) : '';

		var html = '<div class="po-select">';
		// Display row (trigger)
		html += '<div class="po-select__display" role="button" tabindex="0" aria-haspopup="listbox">';
		html += '<span class="po-select__text">' + esc(label + price) + '</span>';
		html += '<span class="po-select__chevron">▾</span>';
		html += '</div>';
		// Dropdown panel
		html += '<div class="po-select__dropdown" hidden role="listbox">';
		f.attributes.forEach(function (a, i) {
			var optLabel = a.name || '—';
			var optPrice = !priceIsFree(a.price) ? ' · ' + formatPrice(a.price, f.price_type) : '';
			html += '<div class="po-select__option' + (i === selIdx ? ' po-select__option--active' : '') + '"';
			html += ' role="option" aria-selected="' + (i === selIdx ? 'true' : 'false') + '"';
			html += ' data-name="' + esc(optLabel) + '">';
			html += esc(optLabel + optPrice);
			html += '</div>';
		});
		html += '</div>';
		html += '</div>';
		return html;
	}

	function renderInputField(f) {
		var html = '<div class="po-input">';
		if (f.input_type === 'u') {
			html += '<div class="po-upload">📎 ' + esc(L.i18n.inputUpload) + '</div>';
		} else if (f.input_type === 'a') {
			html += '<textarea placeholder="' + esc(L.i18n.inputTextarea) + '" disabled></textarea>';
		} else if (f.input_type === 'n' || f.input_type === 'r') {
			html += '<input type="number" placeholder="' + esc(L.i18n.inputNumber) + '" disabled />';
		} else {
			html += '<input type="text" placeholder="' + esc(L.i18n.inputText) + '" disabled />';
		}
		html += '</div>';
		return html;
	}

	function renderQtyBreaks(breaks) {
		var html = '<div class="po-section">';
		html += '<div class="po-section__heading">';
		html += '<div class="po-section__label"><span>Quantity</span><span class="po-section__required">*</span></div>';
		html += '</div>';
		html += '<div class="qty-breaks">';
		breaks.forEach(function (b, i) {
			var hasSave = b.dis && b.dis !== '0' && b.dis !== '';
			html += '<button class="qty-break' + (i === 0 ? ' qty-break--active' : '') + '">';
			html += '<div class="qty-break__count"><strong>' + esc(b.val) + '</strong> units</div>';
			if (hasSave) html += '<span class="qty-break__save">Save ' + esc(b.dis) + '%</span>';
			html += '</button>';
		});
		html += '</div></div>';
		return html;
	}

	function pickChosen(f) {
		if (!f.attributes || !f.attributes.length) return '';
		var sel = f.attributes.find(function (a) { return a.selected; });
		return (sel || f.attributes[0]).name || '';
	}

	function sampleTotal(fields) {
		// Sum first non-empty attribute price across fields as a rough estimate.
		var total = 0;
		var hasAny = false;
		(fields || []).forEach(function (f) {
			if (!f.attributes || !f.attributes.length) return;
			var pick = f.attributes.find(function (a) { return a.selected; }) || f.attributes[0];
			var p = parseFloat(pick.price);
			if (!isNaN(p)) { total += p; hasAny = true; }
		});
		if (!hasAny) return { value: '', suffix: '', note: '' };
		return {
			value: total.toFixed(2),
			suffix: 'per item (est.)',
			note: 'Estimated using default selections — actual price varies by quantity and chosen options.'
		};
	}

	function formatPrice(p, priceType) {
		if (p === '' || p == null || p === '0') return L.i18n.free;
		var sign = '+';
		if (priceType === 'p' || priceType === 'p+') {
			return sign + p + '%';
		}
		return sign + L.currencySymbol + p;
	}

	function priceIsFree(p) {
		return p === '' || p == null || p === '0' || parseFloat(p) === 0;
	}

	function renderPreviewFields(data) {
		var render = data.render || {};
		var fields = render.fields || [];
		if (!fields.length) {
			$('#spbwc-tl-preview-fields').html('<p>No fields in this template.</p>');
			return;
		}
		var html = '<table class="spbwc-tl-fields-table"><thead><tr>';
		html += '<th>Field</th><th>Type</th><th>Display</th><th>Required</th><th>Options</th>';
		html += '</tr></thead><tbody>';
		fields.forEach(function (f) {
			html += '<tr>';
			html += '<td><strong>' + esc(f.title || '(untitled)') + '</strong>';
			if (f.description) html += '<br><small>' + esc(f.description) + '</small>';
			html += '</td>';
			html += '<td>' + esc(displayLabel(f)) + '</td>';
			html += '<td>' + esc(displayTypeLabel(f.display)) + '</td>';
			html += '<td>' + (f.required ? '<span class="spbwc-tl-badge spbwc-tl-badge--required">' + L.i18n.required + '</span>' : '—') + '</td>';
			html += '<td>';
			if (f.attributes && f.attributes.length) {
				html += '<div class="attr-chips">';
				f.attributes.slice(0, 8).forEach(function (a) {
					html += '<span class="attr-chip">' + esc(a.name || '—') + '</span>';
				});
				if (f.attributes.length > 8) html += '<span class="attr-chip">+' + (f.attributes.length - 8) + '</span>';
				html += '</div>';
			} else {
				html += '<em>' + esc(displayLabel(f)) + '</em>';
			}
			html += '</td>';
			html += '</tr>';
		});
		html += '</tbody></table>';
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
		var m = data.meta;
		var html = '<div class="spbwc-tl-about">';
		html += aboutRow('Template slug', '<code>' + esc(m.slug) + '</code>');
		html += aboutRow('Category', esc(m.category));
		html += aboutRow('Field count', m.field_count);
		html += aboutRow('Pricing method', esc((m.pricing_method || '').replace(/_/g, ' ')));
		html += aboutRow('Template version', esc(m.template_version));
		if (m.description) html += aboutRow('Description', esc(m.description));
		if (m.pricing_source) {
			html += '<div class="spbwc-tl-about__row spbwc-tl-about__pricing-note">';
			html += '<dt>⚠ Pricing source</dt><dd>' + esc(m.pricing_source) + '</dd>';
			html += '</div>';
		}
		html += '</div>';
		$('#spbwc-tl-preview-about').html(html);
	}

	function aboutRow(label, value) {
		return '<div class="spbwc-tl-about__row"><dt>' + esc(label) + '</dt><dd>' + value + '</dd></div>';
	}

	function errorBlock(msg) {
		return '<div class="notice notice-error inline" style="margin:20px"><p>' + esc(msg) + '</p></div>';
	}

	// ─── Apply dialog ────────────────────────────────────────────────
	var productSearchInitialized = false;
	var catSelectInitialized     = false;

	function initApply() {
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
			'<span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span> ' + esc(L.i18n.apply)
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

		if (!scope.length) {
			$err.find('p').text(mode === 'p' ? L.i18n.noProductSelected : L.i18n.noCategorySelected);
			$err.prop('hidden', false);
			return;
		}

		$submit.prop('disabled', true).text(L.i18n.applying);

		$.post(L.ajaxUrl, {
			action: 'spbwc_template_apply',
			_ajax_nonce: L.nonce,
			slug: $('#spbwc-tl-apply-slug').val(),
			title: $('#spbwc-tl-apply-title').val(),
			apply_for: mode,
			scope_ids: scope
		}).done(function (resp) {
			if (!resp || !resp.success) {
				$err.find('p').text((resp && resp.data && resp.data.message) || L.i18n.genericError);
				$err.prop('hidden', false);
				$submit.prop('disabled', false).html(
					'<span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span> ' + esc(L.i18n.apply)
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
					'<span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span> ' + esc(L.i18n.apply)
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
				'<span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span> ' + esc(L.i18n.apply)
			);
		});
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
