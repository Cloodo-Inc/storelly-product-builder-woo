<?php
/**
 * Products Manager Page View
 *
 * Variables from spbwc_products_manager():
 *   $products_query          WP_Query
 *   $search                  string
 *   $paged                   int
 *   $filter                  'all'|'mapped'|'unmapped'
 *   $count_all               int
 *   $count_mapped            int
 *   $count_unmapped          int
 *   $spbwc_product_data      array [product_id => ['option_id', 'field_count', 'is_mapped']]
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$spbwc_base_search_url = add_query_arg( 'page', SPBWC_PB_PRODUCTS_SLUG, admin_url( 'admin.php' ) );
?>
<div class="wrap spbwc-products-wrap"
     data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
     data-nonce="<?php echo esc_attr( wp_create_nonce( 'spbwc_load_products_nonce' ) ); ?>"
     data-search="<?php echo esc_attr( $search ); ?>"
>

    <!-- ── Page hero ──────────────────────────────────────────── -->
    <header class="spbwc-page-hero">
        <div class="spbwc-page-hero__grid">
            <div class="spbwc-page-hero__body">
                <div class="spbwc-page-hero__eyebrow">
                    <span class="dashicons dashicons-store" aria-hidden="true"></span>
                    <?php esc_html_e( 'Storelly Product Builder', 'storelly-product-builder-for-woocommerce' ); ?>
                </div>
                <h1 class="spbwc-page-hero__title">
                    <span class="dashicons dashicons-products" aria-hidden="true"></span>
                    <?php esc_html_e( 'Products', 'storelly-product-builder-for-woocommerce' ); ?>
                </h1>
                <p class="spbwc-page-hero__subtitle">
                    <?php esc_html_e( 'Manage WooCommerce products and their printing option assignments.', 'storelly-product-builder-for-woocommerce' ); ?>
                </p>
            </div>
            <div class="spbwc-page-hero__actions">
                <a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=product' ) ); ?>" class="spbwc-cta-btn spbwc-cta-btn--solid">
                    <span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
                    <?php esc_html_e( 'Add Product', 'storelly-product-builder-for-woocommerce' ); ?>
                </a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . SPBWC_PB_OPTIONS_SLUG ) ); ?>" class="spbwc-cta-btn spbwc-cta-btn--ghost">
                    <span class="dashicons dashicons-admin-settings" aria-hidden="true"></span>
                    <?php esc_html_e( 'Printing Options', 'storelly-product-builder-for-woocommerce' ); ?>
                </a>
            </div>
        </div>
    </header>

    <!-- ── Toolbar ─────────────────────────────────────────────── -->
    <div class="spbwc-products-toolbar">

        <div class="spbwc-toolbar-left">
            <!-- Filter tabs (client-side, no page reload) -->
            <div class="spbwc-filter-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Filter products', 'storelly-product-builder-for-woocommerce' ); ?>">
                <button
                    type="button"
                    class="spbwc-filter-tab<?php echo 'all' === $filter ? ' is-active' : ''; ?>"
                    data-filter="all"
                    role="tab"
                    aria-selected="<?php echo 'all' === $filter ? 'true' : 'false'; ?>"
                >
                    <?php esc_html_e( 'All', 'storelly-product-builder-for-woocommerce' ); ?>
                    <span class="spbwc-filter-tab__count" data-count-for="all"><?php echo esc_html( (string) $count_all ); ?></span>
                </button>
                <button
                    type="button"
                    class="spbwc-filter-tab<?php echo 'mapped' === $filter ? ' is-active' : ''; ?>"
                    data-filter="mapped"
                    role="tab"
                    aria-selected="<?php echo 'mapped' === $filter ? 'true' : 'false'; ?>"
                >
                    <?php esc_html_e( 'Has Option', 'storelly-product-builder-for-woocommerce' ); ?>
                    <span class="spbwc-filter-tab__count" data-count-for="mapped"><?php echo esc_html( (string) $count_mapped ); ?></span>
                </button>
                <button
                    type="button"
                    class="spbwc-filter-tab<?php echo 'unmapped' === $filter ? ' is-active' : ''; ?>"
                    data-filter="unmapped"
                    role="tab"
                    aria-selected="<?php echo 'unmapped' === $filter ? 'true' : 'false'; ?>"
                >
                    <?php esc_html_e( 'No Option', 'storelly-product-builder-for-woocommerce' ); ?>
                    <span class="spbwc-filter-tab__count" data-count-for="unmapped"><?php echo esc_html( (string) $count_unmapped ); ?></span>
                </button>
            </div>
        </div>

        <div class="spbwc-toolbar-right">
            <!-- WooCommerce extra filters -->
            <div class="spbwc-toolbar-filters">
                <select class="spbwc-select-filter" data-filter-key="post_status_filter" aria-label="<?php esc_attr_e( 'Filter by status', 'storelly-product-builder-for-woocommerce' ); ?>">
                    <option value=""><?php esc_html_e( 'All Statuses', 'storelly-product-builder-for-woocommerce' ); ?></option>
                    <option value="publish"><?php esc_html_e( 'Published', 'storelly-product-builder-for-woocommerce' ); ?></option>
                    <option value="draft"><?php esc_html_e( 'Draft', 'storelly-product-builder-for-woocommerce' ); ?></option>
                    <option value="pending"><?php esc_html_e( 'Pending', 'storelly-product-builder-for-woocommerce' ); ?></option>
                    <option value="private"><?php esc_html_e( 'Private', 'storelly-product-builder-for-woocommerce' ); ?></option>
                </select>
                <select class="spbwc-select-filter" data-filter-key="product_type" aria-label="<?php esc_attr_e( 'Filter by type', 'storelly-product-builder-for-woocommerce' ); ?>">
                    <option value=""><?php esc_html_e( 'All Types', 'storelly-product-builder-for-woocommerce' ); ?></option>
                    <option value="simple"><?php esc_html_e( 'Simple', 'storelly-product-builder-for-woocommerce' ); ?></option>
                    <option value="variable"><?php esc_html_e( 'Variable', 'storelly-product-builder-for-woocommerce' ); ?></option>
                    <option value="grouped"><?php esc_html_e( 'Grouped', 'storelly-product-builder-for-woocommerce' ); ?></option>
                    <option value="external"><?php esc_html_e( 'External', 'storelly-product-builder-for-woocommerce' ); ?></option>
                </select>
                <?php if ( ! empty( $product_categories ) && ! is_wp_error( $product_categories ) ) : ?>
                    <select class="spbwc-select-filter" data-filter-key="product_cat" aria-label="<?php esc_attr_e( 'Filter by category', 'storelly-product-builder-for-woocommerce' ); ?>">
                        <option value=""><?php esc_html_e( 'All Categories', 'storelly-product-builder-for-woocommerce' ); ?></option>
                        <?php foreach ( $product_categories as $spbwc_cat ) : ?>
                            <option value="<?php echo esc_attr( (string) $spbwc_cat->term_id ); ?>">
                                <?php echo esc_html( $spbwc_cat->name ); ?>
                                (<?php echo esc_html( (string) $spbwc_cat->count ); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
                <select class="spbwc-select-filter" data-filter-key="stock_status" aria-label="<?php esc_attr_e( 'Filter by stock', 'storelly-product-builder-for-woocommerce' ); ?>">
                    <option value=""><?php esc_html_e( 'All Stock', 'storelly-product-builder-for-woocommerce' ); ?></option>
                    <option value="instock"><?php esc_html_e( 'In Stock', 'storelly-product-builder-for-woocommerce' ); ?></option>
                    <option value="outofstock"><?php esc_html_e( 'Out of Stock', 'storelly-product-builder-for-woocommerce' ); ?></option>
                    <option value="onbackorder"><?php esc_html_e( 'On Backorder', 'storelly-product-builder-for-woocommerce' ); ?></option>
                </select>
            </div>

            <!-- Search (AJAX) — shared partial: views/partials/search-box.php -->
            <form method="get" class="spbwc-products-search" id="spbwc-search-form" role="search">
                <input type="hidden" name="page" value="<?php echo esc_attr( SPBWC_PB_PRODUCTS_SLUG ); ?>" />
                <?php
                $spbwc_search = array(
                    'id'          => 'spbwc-search-input',
                    'name'        => 's',
                    'clear_id'    => 'spbwc-search-clear',
                    'value'       => $search,
                    'placeholder' => esc_html__( 'Search products…', 'storelly-product-builder-for-woocommerce' ),
                    'aria_label'  => esc_html__( 'Search products', 'storelly-product-builder-for-woocommerce' ),
                );
                include SPBWC_PB_PLUGIN_DIR . 'views/partials/search-box.php';
                ?>
            </form>

            <!-- View toggle -->
            <div class="spbwc-view-toggle" role="group" aria-label="<?php esc_attr_e( 'View mode', 'storelly-product-builder-for-woocommerce' ); ?>">
                <button type="button" class="spbwc-view-btn is-active" data-view="grid" title="<?php esc_attr_e( 'Grid view', 'storelly-product-builder-for-woocommerce' ); ?>" aria-pressed="true">
                    <span class="dashicons dashicons-grid-view" aria-hidden="true"></span>
                </button>
                <button type="button" class="spbwc-view-btn" data-view="list" title="<?php esc_attr_e( 'List view', 'storelly-product-builder-for-woocommerce' ); ?>" aria-pressed="false">
                    <span class="dashicons dashicons-list-view" aria-hidden="true"></span>
                </button>
            </div>
        </div>
    </div>

    <?php
    $spbwc_found       = (int) $products_query->found_posts;
    $spbwc_has_results = $products_query->have_posts();
    $spbwc_total_pages = (int) $products_query->max_num_pages;
    $spbwc_from        = $spbwc_has_results ? ( ( $paged - 1 ) * 20 ) + 1 : 0;
    $spbwc_to          = $spbwc_has_results ? min( $paged * 20, $spbwc_found ) : 0;
    ?>

    <!-- ── Results summary ──────────────────────────────────── -->
    <div class="spbwc-products-summary">
        <span id="spbwc-result-count">
            <?php if ( $spbwc_has_results ) : ?>
                <?php
                printf(
                    /* translators: 1: first, 2: last, 3: total */
                    esc_html__( 'Showing %1$s–%2$s of %3$s products', 'storelly-product-builder-for-woocommerce' ),
                    '<strong>' . esc_html( (string) $spbwc_from ) . '</strong>',
                    '<strong>' . esc_html( (string) $spbwc_to ) . '</strong>',
                    '<strong>' . esc_html( (string) $spbwc_found ) . '</strong>'
                );
                ?>
            <?php endif; ?>
        </span>
    </div>

    <!-- ── Products section (loading overlay + container) ────── -->
    <div class="spbwc-products-section">

        <!-- Loading overlay -->
        <div class="spbwc-loading-overlay" id="spbwc-loading-overlay" hidden aria-hidden="true">
            <span class="spbwc-loading-spinner"></span>
        </div>

        <!-- List view column header (shown/hidden via JS) -->
        <div class="spbwc-list-view-header" id="spbwc-list-view-header" hidden aria-hidden="true">
            <div class="spbwc-list-header__thumb"></div>
            <div class="spbwc-list-header__product"><?php esc_html_e( 'Product', 'storelly-product-builder-for-woocommerce' ); ?></div>
            <div class="spbwc-list-header__fields"><?php esc_html_e( 'Fields', 'storelly-product-builder-for-woocommerce' ); ?></div>
            <div class="spbwc-list-header__sku"><?php esc_html_e( 'SKU', 'storelly-product-builder-for-woocommerce' ); ?></div>
            <div class="spbwc-list-header__actions"><?php esc_html_e( 'Actions', 'storelly-product-builder-for-woocommerce' ); ?></div>
        </div>

        <!-- Products container (grid or list) -->
        <div
            class="spbwc-products-container view-grid"
            id="spbwc-products-container"
            data-count-all="<?php echo esc_attr( (string) $count_all ); ?>"
            data-count-mapped="<?php echo esc_attr( (string) $count_mapped ); ?>"
            data-count-unmapped="<?php echo esc_attr( (string) $count_unmapped ); ?>"
            data-active-filter="<?php echo esc_attr( $filter ); ?>"
            data-current-page="<?php echo esc_attr( (string) $paged ); ?>"
        >
            <?php if ( $spbwc_has_results ) : ?>
                <?php include SPBWC_PB_PLUGIN_DIR . 'views/_products-cards.php'; ?>
            <?php else : ?>
                <div class="spbwc-products-ajax-empty">
                    <div class="spbwc-empty-state">
                        <div class="spbwc-empty-state__icon">
                            <span class="dashicons dashicons-products" aria-hidden="true"></span>
                        </div>
                        <?php if ( $search ) : ?>
                            <h3 class="spbwc-empty-state__title"><?php esc_html_e( 'No products found', 'storelly-product-builder-for-woocommerce' ); ?></h3>
                            <p class="spbwc-empty-state__text">
                                <?php
                                printf(
                                    /* translators: %s: search term */
                                    esc_html__( 'No products matched "%s". Try a different keyword.', 'storelly-product-builder-for-woocommerce' ),
                                    esc_html( $search )
                                );
                                ?>
                            </p>
                        <?php else : ?>
                            <h3 class="spbwc-empty-state__title"><?php esc_html_e( 'No WooCommerce products found', 'storelly-product-builder-for-woocommerce' ); ?></h3>
                            <p class="spbwc-empty-state__text"><?php esc_html_e( 'Create your first product to get started.', 'storelly-product-builder-for-woocommerce' ); ?></p>
                            <a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=product' ) ); ?>" class="spbwc-cta-btn">
                                <span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
                                <?php esc_html_e( 'Add Product', 'storelly-product-builder-for-woocommerce' ); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <div id="spbwc-products-pagination-wrap">
            <?php if ( $spbwc_has_results && $spbwc_total_pages > 1 ) : ?>
                <div class="spbwc-products-pagination">
                    <?php
                    echo paginate_links( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                        array(
                            'base'      => add_query_arg(
                                array( 'page' => SPBWC_PB_PRODUCTS_SLUG, 's' => $search, 'paged' => '%#%' ),
                                admin_url( 'admin.php' )
                            ),
                            'format'    => '',
                            'prev_text' => '&laquo;',
                            'next_text' => '&raquo;',
                            'total'     => $spbwc_total_pages,
                            'current'   => $paged,
                            'type'      => 'plain',
                        )
                    );
                    ?>
                </div>
            <?php endif; ?>
        </div>

    </div><!-- .spbwc-products-section -->

</div><!-- .wrap -->

<script>
(function () {
    'use strict';

    var STORAGE_KEY    = 'spbwc_products_view';
    var wrap           = document.querySelector('.spbwc-products-wrap');
    var container      = document.getElementById('spbwc-products-container');
    var filterTabs     = document.querySelectorAll('.spbwc-filter-tab');
    var viewBtns       = document.querySelectorAll('.spbwc-view-btn');
    var paginationWrap = document.getElementById('spbwc-products-pagination-wrap');
    var summaryEl      = document.getElementById('spbwc-result-count');
    var loadingOverlay = document.getElementById('spbwc-loading-overlay');
    var listHeader     = document.getElementById('spbwc-list-view-header');
    var searchInput    = document.getElementById('spbwc-search-input');
    var searchClear    = document.getElementById('spbwc-search-clear');
    var searchForm     = document.getElementById('spbwc-search-form');

    var ajaxUrl   = wrap ? (wrap.dataset.ajaxUrl || '') : '';
    var ajaxNonce = wrap ? (wrap.dataset.nonce   || '') : '';

    var savedView    = 'grid';
    var activeFilter = 'all';
    var isLoading    = false;
    var searchTimer  = null;

    /* ---------- filter tab UI ---------- */
    function applyFilterUI(filter) {
        activeFilter = filter;
        filterTabs.forEach(function (tab) {
            var active = tab.dataset.filter === filter;
            tab.classList.toggle('is-active', active);
            tab.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        if (container) { container.dataset.activeFilter = filter; }
    }

    /* ---------- view toggle ---------- */
    function applyView(view) {
        savedView = view;
        if (container) {
            container.classList.remove('view-grid', 'view-list');
            container.classList.add('view-' + view);
        }
        if (listHeader) {
            if (view === 'list') { listHeader.removeAttribute('hidden'); listHeader.removeAttribute('aria-hidden'); }
            else { listHeader.setAttribute('hidden', ''); listHeader.setAttribute('aria-hidden', 'true'); }
        }
        viewBtns.forEach(function (btn) {
            var active = btn.dataset.view === view;
            btn.classList.toggle('is-active', active);
            btn.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
        try { localStorage.setItem(STORAGE_KEY, view); } catch (e) {}
    }

    /* ---------- loading overlay ---------- */
    function showLoading() {
        if (loadingOverlay) { loadingOverlay.removeAttribute('hidden'); loadingOverlay.removeAttribute('aria-hidden'); }
        if (paginationWrap) { paginationWrap.style.opacity = '0.4'; paginationWrap.style.pointerEvents = 'none'; }
    }
    function hideLoading() {
        if (loadingOverlay) { loadingOverlay.setAttribute('hidden', ''); loadingOverlay.setAttribute('aria-hidden', 'true'); }
        if (paginationWrap) { paginationWrap.style.opacity = ''; paginationWrap.style.pointerEvents = ''; }
    }

    /* ---------- search clear button ---------- */
    function syncClearBtn() {
        if (!searchClear || !searchInput) { return; }
        searchClear.hidden = !searchInput.value.trim();
    }

    /* ---------- build AJAX payload ---------- */
    function buildFormData(page) {
        var fd = new FormData();
        fd.append('action',        'spbwc_load_products');
        fd.append('nonce',         ajaxNonce);
        fd.append('paged',         page || 1);
        fd.append('s',             searchInput ? searchInput.value : '');
        fd.append('option_filter', activeFilter);
        document.querySelectorAll('.spbwc-select-filter').forEach(function (sel) {
            fd.append(sel.dataset.filterKey, sel.value);
        });
        return fd;
    }

    /* ---------- AJAX load ---------- */
    function loadPage(page) {
        if (isLoading || !ajaxUrl || !ajaxNonce) { return; }
        isLoading = true;
        showLoading();

        fetch(ajaxUrl, { method: 'POST', body: buildFormData(page) })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                isLoading = false;
                hideLoading();
                if (!res.success) { return; }
                var d = res.data;

                /* swap cards */
                if (container) {
                    container.innerHTML = d.cards_html;
                    container.dataset.currentPage = d.current_page;
                }

                /* swap pagination */
                if (paginationWrap) {
                    paginationWrap.innerHTML = d.pagination_html
                        ? '<div class="spbwc-products-pagination">' + d.pagination_html + '</div>'
                        : '';
                }

                /* update summary */
                if (summaryEl) { summaryEl.innerHTML = d.summary_html || ''; }

                /* update tab counts */
                document.querySelectorAll('[data-count-for]').forEach(function (el) {
                    var k = el.dataset.countFor;
                    if      (k === 'all'      && d.count_all      != null) { el.textContent = d.count_all; }
                    else if (k === 'mapped'   && d.count_mapped   != null) { el.textContent = d.count_mapped; }
                    else if (k === 'unmapped' && d.count_unmapped != null) { el.textContent = d.count_unmapped; }
                });

                /* re-apply view */
                applyView(savedView);

                /* push URL */
                pushState(page, activeFilter);
            })
            .catch(function () { isLoading = false; hideLoading(); });
    }

    /* ---------- URL state ---------- */
    function pushState(page, filter) {
        if (!window.history || !window.history.pushState) { return; }
        var params = new URLSearchParams(window.location.search);
        params.set('paged', page);
        params.set('filter', filter);
        var sv = searchInput ? searchInput.value.trim() : '';
        sv ? params.set('s', sv) : params.delete('s');
        document.querySelectorAll('.spbwc-select-filter').forEach(function (sel) {
            sel.value ? params.set(sel.dataset.filterKey, sel.value) : params.delete(sel.dataset.filterKey);
        });
        window.history.pushState({ page: page, filter: filter }, '', '?' + params.toString());
    }

    function pageFromHref(href) {
        var m = (href || '').match(/[?&]paged=(\d+)/);
        return m ? parseInt(m[1], 10) : 1;
    }

    /* ---------- init ---------- */
    try { savedView = localStorage.getItem(STORAGE_KEY) || 'grid'; } catch (e) {}
    applyView(savedView);
    activeFilter = (container && container.dataset.activeFilter) || 'all';
    applyFilterUI(activeFilter);
    syncClearBtn();

    /* ---------- events ---------- */

    /* Filter tabs → server-side AJAX */
    filterTabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            applyFilterUI(tab.dataset.filter);
            loadPage(1);
        });
    });

    /* View toggle */
    viewBtns.forEach(function (btn) {
        btn.addEventListener('click', function () { applyView(btn.dataset.view); });
    });

    /* Search form submit */
    if (searchForm) {
        searchForm.addEventListener('submit', function (e) {
            e.preventDefault();
            clearTimeout(searchTimer);
            loadPage(1);
        });
    }

    /* Search input — debounced AJAX */
    if (searchInput) {
        searchInput.addEventListener('input', function () {
            syncClearBtn();
            clearTimeout(searchTimer);
            searchTimer = setTimeout(function () { loadPage(1); }, 380);
        });
    }

    /* Clear button */
    if (searchClear) {
        searchClear.addEventListener('click', function () {
            if (searchInput) { searchInput.value = ''; }
            syncClearBtn();
            clearTimeout(searchTimer);
            loadPage(1);
        });
    }

    /* Filter dropdowns → AJAX */
    document.querySelectorAll('.spbwc-select-filter').forEach(function (sel) {
        sel.addEventListener('change', function () { loadPage(1); });
    });

    /* Pagination clicks — event delegation */
    document.addEventListener('click', function (e) {
        var link = e.target.closest('#spbwc-products-pagination-wrap .page-numbers');
        if (!link || link.classList.contains('current') || link.classList.contains('dots')) { return; }
        e.preventDefault();
        loadPage(pageFromHref(link.href));
    });

    /* popstate */
    window.addEventListener('popstate', function (e) {
        var state  = e.state || {};
        var filter = state.filter || 'all';
        var page   = state.page   || 1;
        applyFilterUI(filter);
        loadPage(page);
    });
})();
</script>
