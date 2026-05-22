/*
 * Marketplace admin — companion JS for the PHP admin (PR B).
 *
 * Handles:
 *   - inline toggle of designer enable/disable + featured
 *   - approve / cancel of withdraw requests
 *   - publish / unpublish / delete of designs
 *   - report dashboard charts (Chart.js, bundled under static/libs/)
 *
 * All AJAX calls send `_wpnonce` from the localized `spbwcMarketplaceAdmin`
 * object and target the spbwc_marketplace_* actions registered in
 * SPBWC_Marketplace_Admin_Ajax. Errors are surfaced via a generic alert
 * fallback; a richer notice system can be layered later without changing
 * the wire format.
 */

(function ($) {
    'use strict';

    if (typeof spbwcMarketplaceAdmin === 'undefined') {
        return;
    }
    var cfg = spbwcMarketplaceAdmin;

    function post(action, data) {
        var payload = $.extend({
            action: action,
            _wpnonce: cfg.nonce
        }, data);
        return $.ajax({
            url: cfg.ajaxUrl,
            method: 'POST',
            dataType: 'json',
            data: payload
        });
    }

    function setBusy($el, busy) {
        if (busy) {
            $el.addClass('spbwc-mp-busy');
        } else {
            $el.removeClass('spbwc-mp-busy');
        }
    }

    function fail() {
        window.alert(cfg.i18n.failed); // eslint-disable-line no-alert
    }

    // Toggle designer enable/disable + featured.
    $(document).on('click', '.spbwc-toggle-designer', function (e) {
        e.preventDefault();
        var $a = $(this);
        var designerId = parseInt($a.data('id'), 10);
        var action = $a.data('action');
        if (!designerId || !action) {
            return;
        }
        setBusy($a, true);
        post('spbwc_marketplace_toggle_designer_status', {
            designer_id: designerId,
            mp_action: action
        })
            .done(function (resp) {
                setBusy($a, false);
                if (resp && resp.success) {
                    window.location.reload();
                } else {
                    fail();
                }
            })
            .fail(function () {
                setBusy($a, false);
                fail();
            });
    });

    // Withdraw approve / cancel.
    $(document).on('click', '.spbwc-withdraw-action', function (e) {
        e.preventDefault();
        var $a = $(this);
        var id = parseInt($a.data('id'), 10);
        var action = $a.data('action');
        if (!id || !action) {
            return;
        }
        var confirmMsg = (action === 'approve') ? cfg.i18n.confirmApprove : cfg.i18n.confirmCancel;
        if (!window.confirm(confirmMsg)) { // eslint-disable-line no-alert
            return;
        }
        setBusy($a, true);
        post('spbwc_marketplace_approve_withdraw', {
            withdraw_id: id,
            mp_action: action
        })
            .done(function (resp) {
                setBusy($a, false);
                if (resp && resp.success) {
                    window.location.reload();
                } else {
                    fail();
                }
            })
            .fail(function () {
                setBusy($a, false);
                fail();
            });
    });

    // Design publish / unpublish / delete.
    $(document).on('click', '.spbwc-toggle-design', function (e) {
        e.preventDefault();
        var $a = $(this);
        var id = parseInt($a.data('id'), 10);
        var action = $a.data('action');
        if (!id || !action) {
            return;
        }
        if (action === 'delete' && !window.confirm(cfg.i18n.confirmDelete)) { // eslint-disable-line no-alert
            return;
        }
        setBusy($a, true);
        post('spbwc_marketplace_publish_design', {
            design_id: id,
            mp_action: action
        })
            .done(function (resp) {
                setBusy($a, false);
                if (resp && resp.success) {
                    window.location.reload();
                } else {
                    fail();
                }
            })
            .fail(function () {
                setBusy($a, false);
                fail();
            });
    });

    // Report dashboard charts.
    $(function () {
        var data;
        var raw = document.getElementById('spbwc-mp-chart-data');
        if (!raw || typeof window.Chart === 'undefined') {
            return;
        }
        try {
            data = JSON.parse(raw.textContent || '{}');
        } catch (err) {
            return;
        }

        function asXY(series) {
            var labels = [];
            var values = [];
            if (series && typeof series === 'object') {
                Object.keys(series).forEach(function (k) {
                    labels.push(k);
                    values.push(parseFloat(series[k]) || 0);
                });
            }
            return { labels: labels, values: values };
        }

        var designs = asXY(data.designs || {});
        var sales = asXY(data.sales || {});

        var designCanvas = document.getElementById('spbwc-mp-chart-designs');
        if (designCanvas && designs.labels.length) {
            // eslint-disable-next-line no-new
            new window.Chart(designCanvas, {
                type: 'line',
                data: {
                    labels: designs.labels,
                    datasets: [{
                        label: 'Designs',
                        data: designs.values,
                        borderColor: '#2271b1',
                        backgroundColor: 'rgba(34, 113, 177, 0.15)',
                        tension: 0.25,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } }
                }
            });
        }

        var salesCanvas = document.getElementById('spbwc-mp-chart-sales');
        if (salesCanvas && sales.labels.length) {
            // eslint-disable-next-line no-new
            new window.Chart(salesCanvas, {
                type: 'bar',
                data: {
                    labels: sales.labels,
                    datasets: [{
                        label: 'Sales',
                        data: sales.values,
                        backgroundColor: '#46b450'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } }
                }
            });
        }
    });
})(jQuery);
