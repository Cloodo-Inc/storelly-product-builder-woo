/**
 * Template Library — search/filter, preview & apply.
 *
 * Depends on jQuery, wc-enhanced-select (for Select2) and the localized
 * `spbwcTemplateLibrary` object.
 */
(function ($) {
	'use strict';

	if (typeof window.spbwcTemplateLibrary === 'undefined') {
		return;
	}
	var L = window.spbwcTemplateLibrary;

	$(function () {
		initFilters();
		initPreview();
		initApply();
	});

	function initFilters() {
		var $search = $('#spbwc-tl-search');
		var $cat = $('#spbwc-tl-category-filter');
		var $cards = $('#spbwc-tl-grid .spbwc-tl-card');

		function apply() {
			var q = ($search.val() || '').toLowerCase().trim();
			var cat = $cat.val() || '';
			$cards.each(function () {
				var $c = $(this);
				var matchText = !q || ($c.data('name') || '').indexOf(q) !== -1;
				var matchCat = !cat || $c.data('category') === cat;
				$c.prop('hidden', !(matchText && matchCat));
			});
		}

		$search.on('input', apply);
		$cat.on('change', apply);
	}

	function initPreview() {
		var $dialog = $('#spbwc-tl-preview-dialog');
		var $title = $('#spbwc-tl-preview-title');
		var $body = $('#spbwc-tl-preview-body');

		$(document).on('click', '.spbwc-tl-preview', function () {
			var slug = $(this).data('slug');
			$title.text('Loading…');
			$body.html('<p>Loading template details…</p>');
			openDialog($dialog);

			$.post(L.ajaxUrl, {
				action: 'spbwc_template_preview',
				_ajax_nonce: L.nonce,
				slug: slug
			}).done(function (resp) {
				if (!resp || !resp.success) {
					$body.html('<p>' + escapeHtml((resp && resp.data && resp.data.message) || L.i18n.genericError) + '</p>');
					return;
				}
				$title.text(resp.data.meta.name);
				$body.html(renderPreview(resp.data));
			}).fail(function () {
				$body.html('<p>' + escapeHtml(L.i18n.genericError) + '</p>');
			});
		});
	}

	function renderPreview(data) {
		var meta = data.meta;
		var summary = data.summary || [];
		var html = '<div class="spbwc-tl-preview-meta">';
		html += '<p><strong>Category:</strong> ' + escapeHtml(meta.category) + '</p>';
		html += '<p><strong>Fields:</strong> ' + meta.field_count + ' &middot; <strong>Pricing:</strong> ' + escapeHtml((meta.pricing_method || '').replace(/_/g, ' ')) + '</p>';
		if (meta.description) {
			html += '<p>' + escapeHtml(meta.description) + '</p>';
		}
		if (meta.pricing_source) {
			html += '<p class="description"><em>' + escapeHtml(meta.pricing_source) + '</em></p>';
		}
		html += '</div>';

		if (summary.length) {
			html += '<h3 style="margin-top:16px">Fields in this template</h3>';
			html += '<ul class="spbwc-tl-preview-fields">';
			summary.forEach(function (f) {
				html += '<li>';
				html += '<strong>' + escapeHtml(f.title || '(untitled)') + '</strong>';
				if (f.nbd_type) html += ' <span class="spbwc-tl-badge spbwc-tl-badge--neutral">' + escapeHtml(f.nbd_type) + '</span>';
				if (f.display_type) html += ' <span class="spbwc-tl-badge spbwc-tl-badge--info">' + escapeHtml(f.display_type) + '</span>';
				if (f.attr_count) html += ' &middot; ' + f.attr_count + ' options';
				html += '</li>';
			});
			html += '</ul>';
		}
		return html;
	}

	function initApply() {
		var $dialog = $('#spbwc-tl-apply-dialog');
		var $form = $('#spbwc-tl-apply-form');
		var $slug = $('#spbwc-tl-apply-slug');
		var $title = $('#spbwc-tl-apply-title');
		var $error = $('#spbwc-tl-apply-error');
		var $submit = $('#spbwc-tl-apply-submit');

		// Init Select2 on category picker if available.
		if ($.fn.select2) {
			$('#spbwc-tl-categories').select2({
				placeholder: L.i18n.selectCategory,
				width: '100%'
			});
		}

		$(document).on('click', '.spbwc-tl-apply', function () {
			$error.hide().text('');
			$slug.val($(this).data('slug'));
			$title.val($(this).data('name'));
			$form[0].reset();
			$slug.val($(this).data('slug'));
			$title.val($(this).data('name'));
			$form.find('input[name="apply_for"][value="p"]').prop('checked', true).trigger('change');
			openDialog($dialog);
		});

		$(document).on('change', 'input[name="apply_for"]', function () {
			var mode = $(this).val();
			$('.spbwc-tl-scope').hide();
			$('.spbwc-tl-scope--' + mode).show();
		});

		$(document).on('click', '[data-close="apply"]', function () {
			closeDialog($dialog);
		});

		$form.on('submit', function (e) {
			e.preventDefault();
			$error.hide().text('');

			var mode = $form.find('input[name="apply_for"]:checked').val();
			var scope = mode === 'p'
				? ($('#spbwc-tl-products').val() || [])
				: ($('#spbwc-tl-categories').val() || []);

			if (!scope.length) {
				$error.text('Please select at least one ' + (mode === 'p' ? 'product' : 'category') + '.').show();
				return;
			}

			$submit.prop('disabled', true).text(L.i18n.applying);

			$.post(L.ajaxUrl, {
				action: 'spbwc_template_apply',
				_ajax_nonce: L.nonce,
				slug: $slug.val(),
				title: $title.val(),
				apply_for: mode,
				scope_ids: scope
			}).done(function (resp) {
				if (!resp || !resp.success) {
					$error.text((resp && resp.data && resp.data.message) || L.i18n.genericError).show();
					$submit.prop('disabled', false).text(L.i18n.apply);
					return;
				}
				if (resp.data.edit_url) {
					window.location.href = resp.data.edit_url;
				} else {
					closeDialog($dialog);
					$submit.prop('disabled', false).text(L.i18n.apply);
				}
			}).fail(function () {
				$error.text(L.i18n.genericError).show();
				$submit.prop('disabled', false).text(L.i18n.apply);
			});
		});
	}

	function openDialog($dlg) {
		var el = $dlg[0];
		if (!el) return;
		if (typeof el.showModal === 'function') {
			el.showModal();
		} else {
			$dlg.attr('open', 'open');
		}
	}

	function closeDialog($dlg) {
		var el = $dlg[0];
		if (!el) return;
		if (typeof el.close === 'function') {
			el.close();
		} else {
			$dlg.removeAttr('open');
		}
	}

	function escapeHtml(s) {
		if (s == null) return '';
		return String(s)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;');
	}

})(jQuery);
