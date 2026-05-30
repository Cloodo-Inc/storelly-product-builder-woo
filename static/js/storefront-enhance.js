/**
 * Storefront pricing-options enhancements (token UI flow).
 *
 * Directive on the existing nboApp module (no edit to the minified core).
 * Powers:
 *   - completion progress bar ("N / M selected") from nbd_fields,
 *   - mobile sticky-bar CTA → scrolls to the WooCommerce Add-to-cart.
 *
 * Reads only scope.nbd_fields; never mutates pricing/cart state.
 */
(function () {
    'use strict';
    if (typeof angular === 'undefined') { return; }
    try {
        angular.module('nboApp').directive('spbwcStorefrontEnhance', ['$timeout', function ($timeout) {
            return {
                restrict: 'A',
                link: function (scope, element) {
                    var root = (element[0].closest && element[0].closest('.nbo-wrapper')) || document;

                    /* ── Grand total: feed the WooCommerce product price into the app ──
                     * The minified core hardcodes _.price="" (base 0), so total_cart_price is
                     * only the option surcharge. Seed scope.price once from the localized value;
                     * the core's pricing pass re-reads _.price and folds in the base price. */
                    var basePriceApplied = false;
                    function applyBasePrice() {
                        if (basePriceApplied) { return; }
                        var cfg = (typeof spbwc_option_builder_variable !== 'undefined') ? spbwc_option_builder_variable : null;
                        var pv = cfg ? parseFloat(cfg.price) : NaN;
                        if (cfg && !isNaN(pv) && pv > 0 && !scope.basePrice) {
                            scope.price = cfg.price;
                            if (typeof scope.check_valid === 'function') { scope.check_valid(); }
                            basePriceApplied = true;
                        }
                    }
                    $timeout(applyBasePrice, 0);

                    /* ── Price hero: count-up total + per-unit + live CTA price ── */
                    var nbdsFmt = (typeof spbwc_option_builder_variable !== 'undefined' && spbwc_option_builder_variable.nbds_frontend) ? spbwc_option_builder_variable.nbds_frontend : {};

                    /* ── Quantity volume-discount mirror (client-side preview) ──
                     * Mirrors the server engine in STORELLY_FRONTEND_OPTIONS::option_processing()
                     * so the hero / sticky / CTA reflect the discount BEFORE add-to-cart.
                     * Reads the tier data straight from the rendered Quick Pick cards — each
                     * `.nbo-qty-tier` has `data-spbwc-qty` + `data-spbwc-discount`, and the
                     * `.nbo-qty-tiers__grid` carries `data-spbwc-discount-type` ('p'/'f'). DOM
                     * is the source of truth, no global-var coupling. */
                    function computeTierDiscount(qty, perItemBase) {
                        if (qty < 1) { return 0; }
                        var grid = root.querySelector('[data-spbwc-qty-tiers]');
                        if (!grid) { return 0; }
                        var dtype = grid.getAttribute('data-spbwc-discount-type') || 'f';
                        var cards = grid.querySelectorAll('[data-spbwc-qty][data-spbwc-discount]');
                        var tierVal = 0, tierDis = 0;
                        for (var i = 0; i < cards.length; i++) {
                            var bv = parseInt(cards[i].getAttribute('data-spbwc-qty'), 10);
                            var bd = parseFloat(cards[i].getAttribute('data-spbwc-discount'));
                            if (!bv || !bd || isNaN(bd) || bd <= 0) { continue; }
                            if (qty >= bv && bv > tierVal) { tierVal = bv; tierDis = bd; }
                        }
                        if (tierDis <= 0) { return 0; }
                        return dtype === 'p' ? (perItemBase * tierDis / 100) : tierDis; // per-item
                    }
                    function parseAmount(str) {
                        if (str == null) { return NaN; }
                        var s = String(str).replace(/<[^>]*>/g, '');
                        if (nbdsFmt.currency_format_thousand_sep) { s = s.split(nbdsFmt.currency_format_thousand_sep).join(''); }
                        if (nbdsFmt.currency_format_decimal_sep && nbdsFmt.currency_format_decimal_sep !== '.') { s = s.split(nbdsFmt.currency_format_decimal_sep).join('.'); }
                        s = s.replace(/[^0-9.\-]/g, '');
                        var n = parseFloat(s);
                        return isNaN(n) ? NaN : n;
                    }
                    function formatMoney(num) {
                        if (isNaN(num)) { return ''; }
                        var dec = parseInt(nbdsFmt.wc_currency_format_num_decimals, 10);
                        if (isNaN(dec)) { dec = 2; }
                        var n = (num < 0 ? 0 : num).toFixed(dec);
                        var parts = n.split('.');
                        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, nbdsFmt.currency_format_thousand_sep || ',');
                        var val = parts.length > 1 ? parts.join(nbdsFmt.currency_format_decimal_sep || '.') : parts[0];
                        return (nbdsFmt.currency_format || '%s%v').replace('%s', nbdsFmt.currency_format_symbol || '').replace('%v', val);
                    }
                    var heroPrev = null, heroRAF = null;
                    function animateTotal(el, target) {
                        if (heroRAF) { cancelAnimationFrame(heroRAF); heroRAF = null; }
                        var start = (heroPrev === null) ? target : heroPrev;
                        if (start === target) { el.textContent = formatMoney(target); heroPrev = target; return; }
                        var t0 = (window.performance && performance.now) ? performance.now() : Date.now();
                        var dur = 420;
                        el.classList.add('is-bumping');
                        function frame(now) {
                            var p = Math.min(((now || Date.now()) - t0) / dur, 1);
                            var eased = 1 - Math.pow(1 - p, 3);
                            el.textContent = formatMoney(start + (target - start) * eased);
                            if (p < 1) { heroRAF = requestAnimationFrame(frame); }
                            else { el.textContent = formatMoney(target); heroPrev = target; heroRAF = null; setTimeout(function () { el.classList.remove('is-bumping'); }, 140); }
                        }
                        heroRAF = requestAnimationFrame(frame);
                    }
                    function getQty() {
                        var q = parseInt(scope._qty != null ? scope._qty : scope.quantity, 10);
                        return (isNaN(q) || q < 1) ? 1 : q;
                    }
                    function updateHeroCta() {
                        var rawTotal = parseAmount(scope.total_cart_price);
                        var qty = getQty();
                        /* Per-item base = qty-scaled total ÷ qty; safe-guard against /0. */
                        var perItemBase = (!isNaN(rawTotal) && qty > 0) ? (rawTotal / qty) : 0;
                        var perItemDis  = computeTierDiscount(qty, perItemBase);
                        var totalDis    = perItemDis * qty;
                        var total       = isNaN(rawTotal) ? NaN : Math.max(0, rawTotal - totalDis);
                        var perItemNet  = Math.max(0, perItemBase - perItemDis);

                        var totEl = root.querySelector('[data-spbwc-cloodo-total]');
                        if (totEl && !isNaN(total)) { animateTotal(totEl, total); }

                        /* Saved badge — only shown when a tier discount applies. */
                        var savedEl = root.querySelector('[data-spbwc-cloodo-saved]');
                        if (savedEl) {
                            if (totalDis > 0 && !isNaN(total)) {
                                savedEl.textContent = '−' + formatMoney(totalDis) + ' saved';
                                savedEl.removeAttribute('hidden');
                            } else {
                                savedEl.setAttribute('hidden', 'hidden');
                                savedEl.textContent = '';
                            }
                        }

                        var subEl = root.querySelector('[data-spbwc-cloodo-sub]');
                        if (subEl) {
                            var lblItem = subEl.getAttribute('data-label-item') || 'item';
                            var lblItems = subEl.getAttribute('data-label-items') || 'items';
                            if (qty > 1 && !isNaN(total)) {
                                subEl.innerHTML = '<strong></strong> / ' + lblItem + ' · ' + qty + ' ' + lblItems;
                                subEl.querySelector('strong').textContent = formatMoney(perItemNet);
                            } else {
                                subEl.textContent = qty + ' ' + (qty === 1 ? lblItem : lblItems);
                            }
                        }
                        if (!isNaN(total)) {
                            var btn = document.querySelector('form.cart button.single_add_to_cart_button');
                            if (btn && btn.tagName === 'BUTTON') {
                                var pe = btn.querySelector('[data-spbwc-cloodo-cta-price]');
                                if (!pe) {
                                    pe = document.createElement('span');
                                    pe.className = 'spbwc-cloodo-cta-price';
                                    pe.setAttribute('data-spbwc-cloodo-cta-price', '');
                                    btn.appendChild(document.createTextNode(' '));
                                    btn.appendChild(pe);
                                }
                                pe.textContent = '· ' + formatMoney(total);
                            }
                        }
                        /* Mirror the discounted total onto the sticky mobile bar too. The
                         * template's `ng-bind-html=total_cart_price` re-renders during digest;
                         * this $watch callback runs in the same digest cycle AFTER the bind, so
                         * the overwrite wins. */
                        var stkAmt = root.querySelector('.nbo-sticky-mobile__amount');
                        if (stkAmt && !isNaN(total)) { stkAmt.textContent = formatMoney(total); }
                        /* Reflect the active quantity tier */
                        var qtiles = root.querySelectorAll('[data-spbwc-qty-tiers] [data-spbwc-qty]');
                        for (var qti = 0; qti < qtiles.length; qti++) {
                            qtiles[qti].classList.toggle('is-active', parseInt(qtiles[qti].getAttribute('data-spbwc-qty'), 10) === qty);
                        }
                    }
                    scope.$watch('total_cart_price', function () { updateHeroCta(); });
                    scope.$watch(function () { return '' + scope._qty + '|' + scope.quantity; }, function () { updateHeroCta(); });
                    $timeout(updateHeroCta, 0);

                    /* ── A11y: keyboard + aria-expanded for advanced-dropdown ──
                     * The custom dropdown UI (.nbo-ad-result + .nbo-ad-pseudo-list) needs
                     * keyboard parity with a native <select>: Enter/Space toggles open,
                     * ArrowDown opens + moves focus into the list, ArrowUp/Down navigates
                     * items, Enter selects, Escape closes & returns focus. aria-expanded
                     * mirrors the directive's `.active` class on the wrap so AT users see
                     * the state. Delegated handler — works for late-rendered fields too. */
                    $timeout(function () {
                        root.addEventListener('keydown', function (e) {
                            var t = e.target;
                            if (!t || !t.classList) { return; }
                            if (t.classList.contains('nbo-ad-result')) {
                                if (e.key === 'Enter' || e.key === ' ') {
                                    e.preventDefault();
                                    t.click();
                                } else if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                                    e.preventDefault();
                                    var wrap = t.closest('.pcpb-field-ad-dropdown-wrap');
                                    if (wrap && !wrap.classList.contains('active')) { t.click(); }
                                    setTimeout(function () {
                                        var first = wrap && wrap.querySelector('.nbo-ad-list-item');
                                        if (first) { first.focus(); }
                                    }, 60);
                                }
                            } else if (t.classList.contains('nbo-ad-list-item')) {
                                if (e.key === 'Enter' || e.key === ' ') {
                                    e.preventDefault();
                                    t.click();
                                } else if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                                    e.preventDefault();
                                    var dir = e.key === 'ArrowDown' ? 'nextElementSibling' : 'previousElementSibling';
                                    var n = t[dir];
                                    while (n && !n.classList.contains('nbo-ad-list-item')) { n = n[dir]; }
                                    if (n) { n.focus(); }
                                } else if (e.key === 'Escape') {
                                    e.preventDefault();
                                    var w = t.closest('.pcpb-field-ad-dropdown-wrap');
                                    var trig = w && w.querySelector('.nbo-ad-result');
                                    if (trig) { trig.click(); trig.focus(); }
                                }
                            }
                        });
                        /* aria-expanded ↔ `.active` class on the wrap, via MutationObserver. */
                        if (typeof MutationObserver === 'function') {
                            var wraps = root.querySelectorAll('.pcpb-field-ad-dropdown-wrap');
                            for (var i = 0; i < wraps.length; i++) {
                                (function (wrap) {
                                    var trig = wrap.querySelector('.nbo-ad-result');
                                    if (!trig) { return; }
                                    var sync = function () { trig.setAttribute('aria-expanded', wrap.classList.contains('active') ? 'true' : 'false'); };
                                    sync();
                                    new MutationObserver(sync).observe(wrap, { attributes: true, attributeFilter: ['class'] });
                                })(wraps[i]);
                            }
                        }
                    }, 100);

                    /* Sticky price+CTA bar: reveal only once the hero has scrolled out of view
                     * AND the real Add-to-cart is still off-screen (so it never overlaps the top). */
                    $timeout(function () {
                        var bar = root.querySelector('.nbo-sticky-mobile');
                        var atc = document.querySelector('form.cart button.single_add_to_cart_button, form.cart .single_add_to_cart_button, .single_add_to_cart_button');
                        var hero = root.querySelector('.nbo-price-showcase');
                        if (!bar || !atc || !('IntersectionObserver' in window)) { return; }
                        var heroVisible = true, atcVisible = false;
                        function sync() { bar.classList.toggle('is-visible', !heroVisible && !atcVisible); }
                        new IntersectionObserver(function (e) { heroVisible = e[0].isIntersecting; sync(); }, { threshold: 0 }).observe(hero || atc);
                        new IntersectionObserver(function (e) { atcVisible = e[0].isIntersecting; sync(); }, { threshold: 0, rootMargin: '0px 0px -8% 0px' }).observe(atc);
                    }, 80);

                    /* ── Completion progress (segmented "N of M chosen") ──
                     * The progress markup lives inside ng-if="fields.length", so it is NOT in
                     * the DOM when this directive links. Query the nodes lazily on each update. */
                    var segCount = -1; // remembers how many segments are currently rendered
                    var hasInteracted = false; // gate the "Ready to order" cue until a real change

                    function renderSegments(pBar, total) {
                        if (!pBar || segCount === total) { return; }
                        pBar.innerHTML = '';
                        for (var i = 0; i < total; i++) {
                            var seg = document.createElement('span');
                            seg.className = 'nbo-progress__seg';
                            pBar.appendChild(seg);
                        }
                        segCount = total;
                    }

                    function updateProgress() {
                        var pWrap = root.querySelector('[data-spbwc-progress]');
                        if (!scope.nbd_fields || !pWrap) { return; }
                        var pBar  = pWrap.querySelector('.nbo-progress__bar');
                        var pText = pWrap.querySelector('[data-spbwc-progress-text]');
                        var labelChosen = pWrap.getAttribute('data-label') || 'chosen';
                        var labelDone = pWrap.getAttribute('data-label-done') || 'Ready to order';
                        var total = 0, filled = 0;
                        for (var k in scope.nbd_fields) {
                            if (!Object.prototype.hasOwnProperty.call(scope.nbd_fields, k)) { continue; }
                            var f = scope.nbd_fields[k];
                            if (!f || f.enable === false) { continue; }
                            total++;
                            var v = f.value;
                            if (v !== undefined && v !== null && v !== '') { filled++; }
                        }
                        if (total <= 0) { pWrap.hidden = true; return; }
                        pWrap.hidden = false;
                        renderSegments(pBar, total);
                        var segs = pBar ? pBar.querySelectorAll('.nbo-progress__seg') : [];
                        for (var s = 0; s < segs.length; s++) {
                            if (s < filled) { segs[s].classList.add('is-filled'); }
                            else { segs[s].classList.remove('is-filled'); }
                        }
                        var complete = (total > 0 && filled >= total && hasInteracted);
                        pWrap.classList.toggle('is-complete', complete);
                        if (pText) { pText.textContent = complete ? ('✓ ' + labelDone) : (filled + ' of ' + total + ' ' + labelChosen); }
                        setCtaReady(complete);
                    }

                    /* "Ready to order" momentum cue on the WooCommerce add-to-cart button */
                    function setCtaReady(on) {
                        var btn = document.querySelector('form.cart button.single_add_to_cart_button');
                        if (btn) { btn.classList.toggle('spbwc-cloodo-cta--ready', !!on); }
                    }

                    scope.$watch(function () {
                        if (!scope.nbd_fields) { return ''; }
                        var sig = '';
                        for (var k in scope.nbd_fields) {
                            if (Object.prototype.hasOwnProperty.call(scope.nbd_fields, k)) {
                                var f = scope.nbd_fields[k];
                                sig += k + ':' + (f ? f.value : '') + ':' + (f ? f.enable : '') + '|';
                            }
                        }
                        return sig;
                    }, function (newSig, oldSig) {
                        if (newSig !== oldSig) { hasInteracted = true; }
                        updateProgress();
                    });
                    $timeout(updateProgress, 0);

                    /* ── Smart-recommend: default combo + one-click Apply ── */
                    function getRecommendDefaults() {
                        var cfg = (typeof spbwc_option_builder_variable !== 'undefined') ? spbwc_option_builder_variable.options : null;
                        var out = [];
                        if (!cfg || !angular.isArray(cfg.fields)) { return out; }
                        cfg.fields.forEach(function (f) {
                            if (!f || !f.id || !f.general) { return; }
                            var g = f.general;
                            if (g.enabled !== 'y' || g.data_type !== 'm') { return; }
                            if (!g.attributes || !angular.isArray(g.attributes.options) || !g.attributes.options.length) { return; }
                            var opts = g.attributes.options, idx = 0;
                            for (var i = 0; i < opts.length; i++) { if (opts[i] && opts[i].selected === 'on') { idx = i; break; } }
                            out.push({ id: f.id, index: idx, name: (opts[idx] && opts[idx].name != null) ? String(opts[idx].name) : '' });
                        });
                        return out;
                    }

                    function initRecommend() {
                        var card = root.querySelector('[data-spbwc-recommend]');
                        if (!card) { return; }
                        var named = getRecommendDefaults().filter(function (d) { return d.name; });
                        if (!named.length) { card.hidden = true; return; }
                        var descEl = card.querySelector('[data-spbwc-recommend-desc]');
                        if (descEl) {
                            var names = named.map(function (d) { return d.name; });
                            var shown = names.slice(0, 4).join(' · ');
                            if (names.length > 4) { shown += ' +' + (names.length - 4); }
                            descEl.textContent = shown;
                        }
                        card.hidden = false;
                    }
                    $timeout(initRecommend, 0);

                    /* ── Save-build: store/restore configurations in localStorage ── */
                    function buildsKey() {
                        var cfg = (typeof spbwc_option_builder_variable !== 'undefined') ? spbwc_option_builder_variable : {};
                        return 'spbwc_builds_' + (cfg.product_id || '0');
                    }
                    function loadBuilds() {
                        try { var v = JSON.parse(window.localStorage.getItem(buildsKey()) || '[]'); return angular.isArray(v) ? v : []; }
                        catch (err) { return []; }
                    }
                    function storeBuilds(arr) {
                        try { window.localStorage.setItem(buildsKey(), JSON.stringify(arr)); } catch (err) {}
                    }
                    function renderBuilds() {
                        var list = root.querySelector('[data-spbwc-savebuild-list]');
                        if (!list) { return; }
                        var builds = loadBuilds();
                        list.innerHTML = '';
                        builds.forEach(function (b, i) {
                            var chip = document.createElement('span');
                            chip.className = 'nbo-savebuild__chip';
                            var load = document.createElement('button');
                            load.type = 'button';
                            load.className = 'nbo-savebuild__chip-load';
                            load.setAttribute('data-spbwc-build-load', i);
                            load.textContent = b && b.name ? b.name : ('Build ' + (i + 1));
                            var del = document.createElement('button');
                            del.type = 'button';
                            del.className = 'nbo-savebuild__chip-del';
                            del.setAttribute('data-spbwc-build-del', i);
                            del.setAttribute('aria-label', 'Delete');
                            del.textContent = '×';
                            chip.appendChild(load);
                            chip.appendChild(del);
                            list.appendChild(chip);
                        });
                    }
                    $timeout(renderBuilds, 0);

                    root.addEventListener('click', function (e) {
                        var t = e.target;
                        if (!t || !t.closest) { return; }

                        /* Quantity volume-discount tier → set the WooCommerce quantity */
                        var qtBtn = t.closest('[data-spbwc-qty]');
                        if (qtBtn) {
                            var qv = parseInt(qtBtn.getAttribute('data-spbwc-qty'), 10);
                            if (qv > 0) {
                                var qInput = document.querySelector('form.cart input[name="quantity"], form.cart input.qty, .quantity input.qty');
                                if (qInput) {
                                    qInput.value = qv;
                                    qInput.dispatchEvent(new Event('input', { bubbles: true }));
                                    qInput.dispatchEvent(new Event('change', { bubbles: true }));
                                }
                                var grid = qtBtn.closest('[data-spbwc-qty-tiers]');
                                if (grid) {
                                    var all = grid.querySelectorAll('[data-spbwc-qty]');
                                    for (var qi = 0; qi < all.length; qi++) { all[qi].classList.toggle('is-active', all[qi] === qtBtn); }
                                }
                            }
                            return;
                        }

                        /* Save-build: save current selection */
                        if (t.closest('[data-spbwc-savebuild-save]')) {
                            if (!scope.nbd_fields) { return; }
                            var values = {};
                            for (var k in scope.nbd_fields) {
                                if (!Object.prototype.hasOwnProperty.call(scope.nbd_fields, k)) { continue; }
                                var f = scope.nbd_fields[k];
                                if (f && f.enable !== false) { values[k] = f.value; }
                            }
                            var builds = loadBuilds();
                            builds.push({ name: 'Build ' + (builds.length + 1), values: values, ts: Date.now() });
                            storeBuilds(builds);
                            renderBuilds();
                            return;
                        }

                        /* Save-build: load a saved selection */
                        var loadBtn = t.closest('[data-spbwc-build-load]');
                        if (loadBtn) {
                            var li = parseInt(loadBtn.getAttribute('data-spbwc-build-load'), 10);
                            var lb = loadBuilds()[li];
                            if (lb && lb.values && scope.nbd_fields) {
                                scope.$apply(function () {
                                    for (var key in lb.values) {
                                        if (!Object.prototype.hasOwnProperty.call(lb.values, key)) { continue; }
                                        if (!scope.nbd_fields[key]) { continue; }
                                        scope.nbd_fields[key].value = lb.values[key];
                                        if (typeof scope.updateMapOptions === 'function') {
                                            try { scope.updateMapOptions(key); } catch (err) {}
                                        }
                                    }
                                    if (typeof scope.check_valid === 'function') { scope.check_valid(); }
                                });
                            }
                            return;
                        }

                        /* Save-build: delete a saved selection */
                        var delBtn = t.closest('[data-spbwc-build-del]');
                        if (delBtn) {
                            var di = parseInt(delBtn.getAttribute('data-spbwc-build-del'), 10);
                            var arr = loadBuilds();
                            if (di >= 0 && di < arr.length) { arr.splice(di, 1); storeBuilds(arr); renderBuilds(); }
                            return;
                        }

                        /* Smart-recommend: Apply the default combo */
                        if (t.closest('[data-spbwc-recommend-apply]')) {
                            var defaults = getRecommendDefaults();
                            if (!defaults.length || !scope.nbd_fields) { return; }
                            scope.$apply(function () {
                                defaults.forEach(function (d) {
                                    if (!scope.nbd_fields[d.id]) { return; }
                                    scope.nbd_fields[d.id].value = String(d.index);
                                    if (typeof scope.updateMapOptions === 'function') {
                                        try { scope.updateMapOptions(d.id); } catch (err) {}
                                    }
                                });
                                if (typeof scope.check_valid === 'function') { scope.check_valid(); }
                            });
                            return;
                        }

                        /* Sticky CTA → trigger the real WooCommerce Add-to-cart */
                        if (t.closest('[data-spbwc-sticky-cta]')) {
                            var atc = document.querySelector('form.cart button.single_add_to_cart_button, form.cart .single_add_to_cart_button, .single_add_to_cart_button');
                            if (atc) {
                                if (typeof atc.click === 'function') { atc.click(); }
                                else { atc.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
                            }
                        }
                    });
                }
            };
        }]);
    } catch (e) { /* nboApp not registered — skip */ }
}());
