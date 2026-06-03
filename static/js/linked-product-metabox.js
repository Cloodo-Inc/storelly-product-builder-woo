/**
 * Linked Product metabox — Storelly control panel interactions on the
 * WooCommerce product edit screen.
 *
 * Behaviors: shared-option disclosure, "Edit fields" shared-edit guard,
 * Swap (repoint pointer), Unlink (clear pointer). Confirms use SweetAlert
 * (v2) when available, falling back to window.confirm. AJAX is admin-only and
 * nonce-protected; see docs/SPEC_LINKED_PRODUCT_UX.md §3.3.
 */
(function ($) {
    'use strict';

    var L = window.spbwcLinkedProduct || {};

    function t(key, fallback) {
        return (L.i18n && L.i18n[key]) ? L.i18n[key] : fallback;
    }

    // Promise<boolean> confirm. SweetAlert v2 (has dangerMode/buttons) when
    // present, else native confirm so the action never silently no-ops.
    function confirmDialog(opts) {
        return new Promise(function (resolve) {
            if (typeof window.swal === 'function') {
                window.swal({
                    title: opts.title || '',
                    text: opts.text || '',
                    icon: opts.danger ? 'warning' : 'info',
                    buttons: true,
                    dangerMode: !!opts.danger
                }).then(function (v) {
                    resolve(!!v);
                });
            } else {
                resolve(window.confirm(opts.text || opts.title || ''));
            }
        });
    }

    $(function () {
        var $wrap = $('.spbwc-lp');
        if (!$wrap.length) {
            return;
        }
        var product = $wrap.data('product');
        var nonce = $wrap.data('nonce');
        var ajaxUrl = (typeof window.ajaxurl !== 'undefined') ? window.ajaxurl : (L.ajaxUrl || '');

        // Dim the mapped-option card while "Enable product builder" is off —
        // the option won't render on the storefront until it's re-enabled.
        $wrap.on('change', '[data-spbwc-builder-toggle]', function () {
            $wrap.toggleClass('is-builder-off', !this.checked);
        });

        // Dim the "Quote display" row until "Enable request quote" is checked.
        $wrap.on('change', '[data-spbwc-quote-toggle]', function () {
            $wrap.find('[data-spbwc-quote]').toggleClass('is-quote-off', !this.checked);
        });

        // Shared-option disclosure list.
        $wrap.on('click', '[data-spbwc-shared-toggle]', function () {
            var $btn = $(this);
            var expanded = $btn.attr('aria-expanded') === 'true';
            $btn.attr('aria-expanded', expanded ? 'false' : 'true');
            $btn.closest('.spbwc-lp-shared').find('.spbwc-lp-shared__list').prop('hidden', expanded);
        });

        // Edit fields — warn before editing a shared option (§2.4, warn-only).
        $wrap.on('click', '[data-spbwc-edit-fields]', function (e) {
            var shared = parseInt($(this).data('shared'), 10) || 0;
            if (shared <= 0) {
                return; // not shared → navigate normally
            }
            e.preventDefault();
            var href = this.href;
            var msg = t('sharedEdit', 'This option is shared by %d other product(s). Editing its fields affects all of them. Continue?').replace('%d', shared);
            confirmDialog({
                title: t('sharedEditTitle', 'Shared option'),
                text: msg,
                danger: true
            }).then(function (ok) {
                if (ok) {
                    window.location.href = href;
                }
            });
        });

        function post(action, data) {
            $wrap.addClass('is-busy');
            return $.post(ajaxUrl, $.extend({
                action: action,
                nonce: nonce,
                product_id: product
            }, data)).done(function (res) {
                if (res && res.success) {
                    window.location.reload();
                } else {
                    $wrap.removeClass('is-busy');
                    var m = (res && res.data && res.data.msg) ? res.data.msg : t('failed', 'Action failed. Please try again.');
                    window.alert(m);
                }
            }).fail(function () {
                $wrap.removeClass('is-busy');
                window.alert(t('failed', 'Action failed. Please try again.'));
            });
        }

        // Swap mapped option.
        $wrap.on('change', '[data-spbwc-swap]', function () {
            var $sel = $(this);
            var optionId = parseInt($sel.val(), 10) || 0;
            if (!optionId) {
                return;
            }
            var label = $sel.find('option:selected').text();
            confirmDialog({
                title: t('swapTitle', 'Swap option'),
                text: t('swapConfirm', 'Map this product to the selected option instead?') + '\n' + label
            }).then(function (ok) {
                if (ok) {
                    post('spbwc_swap_product_option', { option_id: optionId });
                } else {
                    $sel.val('');
                }
            });
        });

        // Unlink mapped option. Destructive-styled + warns the builder stops
        // rendering on this product (#8).
        $wrap.on('click', '[data-spbwc-unlink]', function () {
            confirmDialog({
                title: t('unlinkTitle', 'Unlink option'),
                text: t('unlinkConfirm', 'Unlink this option from the product? The product builder will no longer show on this product (the option itself is not deleted).'),
                danger: true
            }).then(function (ok) {
                if (ok) {
                    post('spbwc_unlink_product_option', {});
                }
            });
        });
    });
})(jQuery);
