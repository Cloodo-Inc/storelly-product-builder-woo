/**
 * Request a Quote — storefront modal behaviour (B2B quote redesign, M3).
 *
 * Vanilla JS (no jQuery dependency). Handles open/close, scroll-lock, ESC,
 * focus trap, AJAX submit, per-field error rendering and the success state.
 * Config is injected via wp_localize_script as window.spbwcRfq.
 */
(function () {
    'use strict';

    var cfg = window.spbwcRfq || {};
    var overlay = document.getElementById('spbwc-quote-modal');
    var trigger = document.getElementById('spbwc-open-quote-popup');
    if (!overlay || !trigger) {
        return;
    }

    var modal = overlay.querySelector('.spbwc-rfq-modal');
    var form = document.getElementById('spbwc-quote-form');
    var closeBtn = overlay.querySelector('.spbwc-rfq-close');
    var alertBox = overlay.querySelector('.spbwc-rfq-alert');
    var successBox = overlay.querySelector('.spbwc-rfq-success');
    var submitBtn = form ? form.querySelector('.spbwc-rfq-submit') : null;
    var lastFocused = null;

    function focusable() {
        return Array.prototype.slice.call(
            modal.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]), textarea:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])')
        ).filter(function (el) { return el.offsetParent !== null; });
    }

    function open() {
        lastFocused = document.activeElement;
        overlay.classList.add('is-open');
        overlay.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        var first = focusable()[0];
        if (first) { first.focus(); }
        document.addEventListener('keydown', onKeydown, true);
    }

    function close() {
        overlay.classList.remove('is-open');
        overlay.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        document.removeEventListener('keydown', onKeydown, true);
        if (lastFocused && typeof lastFocused.focus === 'function') { lastFocused.focus(); }
    }

    function onKeydown(e) {
        if (e.key === 'Escape') {
            e.preventDefault();
            close();
            return;
        }
        if (e.key === 'Tab') {
            var items = focusable();
            if (!items.length) { return; }
            var firstEl = items[0];
            var lastEl = items[items.length - 1];
            if (e.shiftKey && document.activeElement === firstEl) {
                e.preventDefault();
                lastEl.focus();
            } else if (!e.shiftKey && document.activeElement === lastEl) {
                e.preventDefault();
                firstEl.focus();
            }
        }
    }

    function clearErrors() {
        if (alertBox) { alertBox.className = 'spbwc-rfq-alert'; alertBox.textContent = ''; }
        form.querySelectorAll('.spbwc-rfq-field.has-error').forEach(function (f) {
            f.classList.remove('has-error');
            var err = f.querySelector('.spbwc-rfq-error');
            if (err) { err.textContent = ''; }
            var input = f.querySelector('input, textarea');
            if (input) { input.removeAttribute('aria-invalid'); }
        });
    }

    function showFieldErrors(errors) {
        Object.keys(errors || {}).forEach(function (name) {
            var input = form.querySelector('[name="quote_fields[' + name + ']"]');
            var field = input ? input.closest('.spbwc-rfq-field') : null;
            if (!field) { return; }
            field.classList.add('has-error');
            input.setAttribute('aria-invalid', 'true');
            var err = field.querySelector('.spbwc-rfq-error');
            if (err) { err.textContent = errors[name]; }
        });
    }

    function showAlert(msg) {
        if (!alertBox) { return; }
        alertBox.className = 'spbwc-rfq-alert is-error';
        alertBox.textContent = msg;
    }

    function showSuccess(msg) {
        if (form) { form.style.display = 'none'; }
        if (successBox) {
            var text = successBox.querySelector('.spbwc-rfq-success__text');
            if (text && msg) { text.textContent = msg; }
            var cta = successBox.querySelector('.spbwc-rfq-success__cta');
            if (cta && cfg.myQuotesUrl) {
                cta.href = cfg.myQuotesUrl;
                cta.textContent = (cfg.i18n && cfg.i18n.trackQuote) ? cfg.i18n.trackQuote : 'Track your quote';
                cta.style.display = '';
            }
            successBox.classList.add('is-visible');
            successBox.setAttribute('role', 'status');
        }
    }

    function submit(e) {
        e.preventDefault();
        clearErrors();
        if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = cfg.i18n && cfg.i18n.sending ? cfg.i18n.sending : 'Sending…'; }

        var data = new FormData(form);
        data.append('action', cfg.action || 'spbwc_submit_quote');
        data.append('nonce', cfg.nonce || '');

        fetch(cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: data })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = cfg.i18n && cfg.i18n.submit ? cfg.i18n.submit : 'Submit request'; }
                if (res && res.success) {
                    showSuccess(res.data && res.data.message ? res.data.message : '');
                } else {
                    var d = res && res.data ? res.data : {};
                    if (d.errors) { showFieldErrors(d.errors); }
                    showAlert(d.message || (cfg.i18n && cfg.i18n.failed ? cfg.i18n.failed : 'Something went wrong.'));
                }
            })
            .catch(function () {
                if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = cfg.i18n && cfg.i18n.submit ? cfg.i18n.submit : 'Submit request'; }
                showAlert(cfg.i18n && cfg.i18n.network ? cfg.i18n.network : 'Request failed. Please try again.');
            });
    }

    trigger.addEventListener('click', open);
    if (closeBtn) { closeBtn.addEventListener('click', close); }
    overlay.addEventListener('click', function (e) { if (e.target === overlay) { close(); } });
    if (form) { form.addEventListener('submit', submit); }

    // Quantity stepper.
    var qtyInput = document.getElementById('spbwc_quote_quantity');
    modal.querySelectorAll('.spbwc-rfq-stepper__btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (!qtyInput) { return; }
            var step = parseInt(btn.getAttribute('data-step'), 10) || 0;
            var val = (parseInt(qtyInput.value, 10) || 1) + step;
            if (val < 1) { val = 1; }
            qtyInput.value = val;
        });
    });
}());
