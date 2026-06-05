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
            var input = form.querySelector('[name="quote_fields[' + name + ']"]')
                || form.querySelector('[name="quote_files[' + name + ']"]')
                || form.querySelector('[name="quote_files[' + name + '][]"]');
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
            } else if (cfg.i18n && cfg.i18n.guestHint) {
                // Guests have no My-Account quotes list — tell them to watch their inbox.
                var hint = successBox.querySelector('.spbwc-rfq-success__hint');
                if (!hint) {
                    hint = document.createElement('p');
                    hint.className = 'spbwc-rfq-success__hint spbwc-rfq-success__text';
                    successBox.appendChild(hint);
                }
                hint.textContent = cfg.i18n.guestHint;
            }
            successBox.classList.add('is-visible');
            successBox.setAttribute('role', 'status');
        }
    }

    // Progress bar lives just above the footer; created lazily.
    function progressEl() {
        var foot = form ? form.querySelector('.spbwc-rfq-foot') : null;
        if (!foot) { return null; }
        var el = form.querySelector('.spbwc-rfq-progress');
        if (!el) {
            el = document.createElement('div');
            el.className = 'spbwc-rfq-progress';
            el.innerHTML = '<div class="spbwc-rfq-progress__bar"></div>';
            foot.parentNode.insertBefore(el, foot);
        }
        return el;
    }

    function hasFiles() {
        if (!form) { return false; }
        var inputs = form.querySelectorAll('input[type="file"]');
        for (var i = 0; i < inputs.length; i++) {
            if (inputs[i].files && inputs[i].files.length) { return true; }
        }
        return false;
    }

    function resetBtn() {
        if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = cfg.i18n && cfg.i18n.submit ? cfg.i18n.submit : 'Submit request'; }
    }

    function handleResponse(res) {
        resetBtn();
        if (res && res.success) {
            showSuccess(res.data && res.data.message ? res.data.message : '');
        } else {
            var d = res && res.data ? res.data : {};
            if (d.errors) { showFieldErrors(d.errors); }
            showAlert(d.message || (cfg.i18n && cfg.i18n.failed ? cfg.i18n.failed : 'Something went wrong.'));
        }
    }

    function submit(e) {
        e.preventDefault();
        clearErrors();
        // Final client-side pass so obvious mistakes never round-trip.
        if (!validateAll()) { return; }
        if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = cfg.i18n && cfg.i18n.sending ? cfg.i18n.sending : 'Sending…'; }

        var data = new FormData(form);
        data.append('action', cfg.action || 'spbwc_submit_quote');
        data.append('nonce', cfg.nonce || '');

        // Use XHR (not fetch) so we can render real upload progress when the
        // buyer attached files; plain text submissions skip the bar.
        var bar = hasFiles() ? progressEl() : null;
        var fill = bar ? bar.querySelector('.spbwc-rfq-progress__bar') : null;
        if (bar) { bar.classList.add('is-active'); if (fill) { fill.style.width = '0%'; } }

        var xhr = new XMLHttpRequest();
        xhr.open('POST', cfg.ajaxUrl, true);
        xhr.withCredentials = true;
        if (bar && xhr.upload) {
            xhr.upload.addEventListener('progress', function (ev) {
                if (ev.lengthComputable && fill) {
                    fill.style.width = Math.round((ev.loaded / ev.total) * 100) + '%';
                }
            });
        }
        xhr.addEventListener('load', function () {
            if (bar) { bar.classList.remove('is-active'); }
            var res = null;
            try { res = JSON.parse(xhr.responseText); } catch (err) { res = null; }
            if (res) { handleResponse(res); }
            else { resetBtn(); showAlert(cfg.i18n && cfg.i18n.failed ? cfg.i18n.failed : 'Something went wrong.'); }
        });
        xhr.addEventListener('error', function () {
            if (bar) { bar.classList.remove('is-active'); }
            resetBtn();
            showAlert(cfg.i18n && cfg.i18n.network ? cfg.i18n.network : 'Request failed. Please try again.');
        });
        xhr.send(data);
    }

    /* ── Lightweight client-side validation ───────────────────────────── */
    function validateField(input) {
        if (!input) { return true; }
        var field = input.closest('.spbwc-rfq-field');
        if (!field) { return true; }
        var val = (input.value || '').trim();
        var msg = '';
        if (input.hasAttribute('required') && '' === val) {
            msg = (cfg.i18n && cfg.i18n.required) ? cfg.i18n.required : 'This field is required.';
        } else if ('' !== val && (input.type === 'email' || /\[email\]/.test(input.name)) && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val)) {
            msg = (cfg.i18n && cfg.i18n.invalidEmail) ? cfg.i18n.invalidEmail : 'Please enter a valid email.';
        } else if ('' !== val && input.type === 'tel' && !/^[0-9\-\+\(\)\s]{6,20}$/.test(val)) {
            msg = (cfg.i18n && cfg.i18n.invalidPhone) ? cfg.i18n.invalidPhone : 'Please enter a valid phone.';
        }
        field.classList.toggle('has-error', !!msg);
        input.setAttribute('aria-invalid', msg ? 'true' : 'false');
        var err = field.querySelector('.spbwc-rfq-error');
        if (err) { err.textContent = msg; }
        return !msg;
    }

    function validateAll() {
        if (!form) { return true; }
        var ok = true;
        form.querySelectorAll('input[required], textarea[required], input[type="email"], input[type="tel"]').forEach(function (input) {
            if (input.type === 'file') { return; }
            if (!validateField(input)) { ok = false; }
        });
        if (!ok) { showAlert(cfg.i18n && cfg.i18n.fixErrors ? cfg.i18n.fixErrors : 'Please correct the highlighted fields.'); }
        return ok;
    }

    trigger.addEventListener('click', open);
    if (closeBtn) { closeBtn.addEventListener('click', close); }
    overlay.addEventListener('click', function (e) { if (e.target === overlay) { close(); } });
    if (form) {
        form.addEventListener('submit', submit);
        // Validate text fields on blur (skip files — those have their own checks).
        form.querySelectorAll('input, textarea').forEach(function (input) {
            if (input.type === 'file') { return; }
            input.addEventListener('blur', function () { validateField(input); });
        });
    }

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

    // File-upload fields: drag-and-drop, file chips with remove, client-side
    // count/size checks. Files live in the native input (rebuilt via
    // DataTransfer when supported) so FormData picks them up on submit.
    function humanSize(bytes) {
        if (!bytes) { return '0 B'; }
        var units = ['B', 'KB', 'MB', 'GB'];
        var i = Math.floor(Math.log(bytes) / Math.log(1024));
        return (bytes / Math.pow(1024, i)).toFixed(i ? 1 : 0) + ' ' + units[i];
    }

    function initFileField(field) {
        var input = field.querySelector('.spbwc-rfq-file');
        var drop = field.querySelector('.spbwc-rfq-drop');
        var list = field.querySelector('.spbwc-rfq-files');
        if (!input || !drop || !list) { return; }

        var multiple = field.getAttribute('data-multiple') === '1';
        var maxFiles = parseInt(field.getAttribute('data-max-files'), 10) || 1;
        var maxSizeMB = parseFloat(field.getAttribute('data-max-size')) || 0;
        var supportsDT = (typeof DataTransfer !== 'undefined');
        var store = [];

        function fieldError(msg) {
            var err = field.querySelector('.spbwc-rfq-error');
            field.classList.toggle('has-error', !!msg);
            if (err) { err.textContent = msg || ''; }
        }

        function sync() {
            if (supportsDT) {
                var dt = new DataTransfer();
                store.forEach(function (f) { dt.items.add(f); });
                input.files = dt.files;
            }
            render();
        }

        function render() {
            list.innerHTML = '';
            store.forEach(function (f, idx) {
                var li = document.createElement('li');
                li.className = 'spbwc-rfq-file-chip';
                var nameEl = document.createElement('span');
                nameEl.className = 'spbwc-rfq-file-chip__name';
                nameEl.textContent = f.name;
                var sizeEl = document.createElement('span');
                sizeEl.className = 'spbwc-rfq-file-chip__size';
                sizeEl.textContent = humanSize(f.size);
                li.appendChild(nameEl);
                li.appendChild(sizeEl);
                if (supportsDT) {
                    var rm = document.createElement('button');
                    rm.type = 'button';
                    rm.className = 'spbwc-rfq-file-chip__remove';
                    rm.setAttribute('aria-label', 'Remove');
                    rm.textContent = '×';
                    rm.addEventListener('click', function () {
                        store.splice(idx, 1);
                        fieldError('');
                        sync();
                    });
                    li.appendChild(rm);
                }
                list.appendChild(li);
            });
        }

        function accept(fileList) {
            var incoming = Array.prototype.slice.call(fileList || []);
            if (!incoming.length) { return; }
            fieldError('');
            if (!multiple) { store = []; }
            for (var i = 0; i < incoming.length; i++) {
                var f = incoming[i];
                if (maxSizeMB > 0 && f.size > maxSizeMB * 1024 * 1024) {
                    fieldError(f.name + ' — too large.');
                    continue;
                }
                if (store.length >= maxFiles) {
                    fieldError('At most ' + maxFiles + ' file(s).');
                    break;
                }
                store.push(f);
            }
            sync();
        }

        // When DataTransfer is unavailable we cannot rebuild input.files, so let
        // the native input own the selection and just render its current files.
        if (!supportsDT) {
            input.addEventListener('change', function () {
                store = Array.prototype.slice.call(input.files || []);
                render();
            });
            return;
        }

        input.addEventListener('change', function () { accept(input.files); });
        drop.addEventListener('click', function (e) {
            if (e.target === input) { return; }
            input.click();
        });
        ['dragenter', 'dragover'].forEach(function (ev) {
            drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.add('is-drag'); });
        });
        ['dragleave', 'drop'].forEach(function (ev) {
            drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.remove('is-drag'); });
        });
        drop.addEventListener('drop', function (e) {
            if (e.dataTransfer && e.dataTransfer.files) { accept(e.dataTransfer.files); }
        });
    }

    modal.querySelectorAll('.spbwc-rfq-field--file').forEach(initFileField);
}());
