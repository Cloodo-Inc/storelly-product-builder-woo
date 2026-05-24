<?php
if (!defined('ABSPATH')) exit;
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variable.
$link_create_option = add_query_arg(
    array(
        'action' => 'edit',
        'paged'  => 1,
        'id'     => 0,
    ),
    admin_url('admin.php?page=' . SPBWC_PB_BUILDER_SLUG)
);

// Pre-compute values used in both list and block views.
$_nonce_block = wp_create_nonce('spbwc_options_nonce');
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Readonly page slug from request.
$_page_slug = isset($_REQUEST['page']) ? sanitize_text_field(wp_unslash($_REQUEST['page'])) : SPBWC_PB_BUILDER_SLUG;

// Toolbar: status filter state.
// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$_status_filter  = isset($_REQUEST['status_filter']) && strlen($_REQUEST['status_filter'])
    ? sanitize_text_field(wp_unslash($_REQUEST['status_filter'])) : '';
// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$_search_term    = isset($_REQUEST['s']) ? sanitize_text_field(wp_unslash($_REQUEST['s'])) : '';
$_status_counts  = SPBWC_Storelly_Options_List_Table::spbwc_count_all_statuses();
$_base_tab_url   = admin_url('admin.php?page=' . $_page_slug);
if ($_search_term) {
    $_base_tab_url = add_query_arg('s', $_search_term, $_base_tab_url);
}
$_pager_base     = $_status_filter !== '' ? add_query_arg('status_filter', $_status_filter, $_base_tab_url) : $_base_tab_url;
$_per_page_n     = 10;
$_total_items_n  = SPBWC_Storelly_Options_List_Table::spbwc_record_count();
$_total_pages_n  = max(1, (int) ceil($_total_items_n / $_per_page_n));
// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$_current_page_n = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
?>
<div class="wrap">
    <header class="spbwc-page-hero">
        <div class="spbwc-page-hero__grid">
            <div class="spbwc-page-hero__body">
                <div class="spbwc-page-hero__eyebrow">
                    <span class="dashicons dashicons-admin-plugins" aria-hidden="true"></span>
                    <?php esc_html_e('Storelly Product Builder', 'storelly-product-builder-for-woocommerce'); ?>
                </div>
                <h1 class="spbwc-page-hero__title">
                    <span class="dashicons dashicons-tickets-alt" aria-hidden="true"></span>
                    <?php esc_html_e('Pricing Options', 'storelly-product-builder-for-woocommerce'); ?>
                </h1>
                <p class="spbwc-page-hero__subtitle">
                    <?php esc_html_e('Create and manage printing option groups. Assign them to products or categories to let customers configure their order.', 'storelly-product-builder-for-woocommerce'); ?>
                </p>
            </div>
            <div class="spbwc-page-hero__actions">
                <a href="<?php echo esc_url($link_create_option); ?>"
                   class="spbwc-cta-btn spbwc-cta-btn--solid">
                    <span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
                    <?php esc_html_e('Add New Option', 'storelly-product-builder-for-woocommerce'); ?>
                </a>
            </div>
        </div>
    </header>

    <!-- Unified toolbar: status tabs + block search + view toggle -->
    <div class="spbwc-list-toolbar">
        <div class="spbwc-status-tabs" role="tablist">
            <?php
            $spbwc_tabs = array(
                array(
                    'label' => esc_html__('All', 'storelly-product-builder-for-woocommerce'),
                    'value' => '',
                    'count' => $_status_counts['all'],
                ),
                array(
                    'label' => esc_html__('Published', 'storelly-product-builder-for-woocommerce'),
                    'value' => '1',
                    'count' => $_status_counts['published'],
                ),
                array(
                    'label' => esc_html__('Draft', 'storelly-product-builder-for-woocommerce'),
                    'value' => '0',
                    'count' => $_status_counts['draft'],
                ),
            );
            foreach ($spbwc_tabs as $spbwc_tab) :
                $spbwc_tab_active = ($_status_filter === $spbwc_tab['value']);
                $spbwc_tab_url    = $spbwc_tab['value'] !== ''
                    ? add_query_arg('status_filter', $spbwc_tab['value'], $_base_tab_url)
                    : $_base_tab_url;
            ?>
            <a class="spbwc-status-tab<?php echo $spbwc_tab_active ? ' is-active' : ''; ?>"
               href="<?php echo esc_url($spbwc_tab_url); ?>"
               aria-current="<?php echo $spbwc_tab_active ? 'page' : 'false'; ?>">
                <?php echo esc_html($spbwc_tab['label']); ?>
                <span class="spbwc-status-tab__count"><?php echo esc_html(number_format_i18n($spbwc_tab['count'])); ?></span>
            </a>
            <?php endforeach; ?>
        </div>

        <div class="spbwc-list-toolbar__right">
            <!-- Block-view live search (shown only in block view by JS) -->
            <div class="spbwc-block-search" id="spbwc-block-search-wrap" hidden>
                <span class="dashicons dashicons-search spbwc-block-search__icon" aria-hidden="true"></span>
                <input type="search" id="spbwc-block-search"
                       class="spbwc-block-search__input"
                       placeholder="<?php esc_attr_e('Filter by name…', 'storelly-product-builder-for-woocommerce'); ?>"
                       aria-label="<?php esc_attr_e('Filter options by name', 'storelly-product-builder-for-woocommerce'); ?>">
            </div>

            <!-- View toggle -->
            <div class="spbwc-view-toggle" role="group" aria-label="<?php esc_attr_e('Switch view', 'storelly-product-builder-for-woocommerce'); ?>">
                <button type="button" class="spbwc-view-btn" data-view="list"
                        title="<?php esc_attr_e('List view', 'storelly-product-builder-for-woocommerce'); ?>"
                        aria-pressed="true">
                    <span class="dashicons dashicons-list-view" aria-hidden="true"></span>
                    <span class="screen-reader-text"><?php esc_html_e('List view', 'storelly-product-builder-for-woocommerce'); ?></span>
                </button>
                <button type="button" class="spbwc-view-btn" data-view="block"
                        title="<?php esc_attr_e('Block view', 'storelly-product-builder-for-woocommerce'); ?>"
                        aria-pressed="false">
                    <span class="dashicons dashicons-screenoptions" aria-hidden="true"></span>
                    <span class="screen-reader-text"><?php esc_html_e('Block view', 'storelly-product-builder-for-woocommerce'); ?></span>
                </button>
            </div>
        </div>
    </div>

    <div id="poststuff">
        <div id="post-body" class="metabox-holder">
            <div id="post-body-content">
                <div class="meta-box-sortables ui-sortable">
                    <form method="post" id="spbwc-options-list-form">
                        <?php
                        wp_nonce_field('bulk-options', '_wpnonce');
                        $spbwc_options->spbwc_prepare_items();
                        $spbwc_options->search_box(esc_html__('Search Options', 'storelly-product-builder-for-woocommerce'), 'spbwc-option');
                        $spbwc_options->display();
                        ?>
                    </form>

                    <!-- Block/Grid view — toggled by JS; data comes from same paginated $spbwc_options->items -->
                    <div id="spbwc-block-view" class="spbwc-block-view" aria-hidden="true">
                        <?php if (!empty($spbwc_options->items)) : ?>
                        <div class="spbwc-options-grid">
                            <?php
                            $palette = array('#3b82f6', '#8b5cf6', '#ec4899', '#f97316', '#10b981', '#0ea5e9', '#f59e0b', '#6366f1');
                            foreach ($spbwc_options->items as $spbwc_item) :
                                $spbwc_title   = $spbwc_item['title'];
                                $spbwc_initial = mb_strtoupper(mb_substr($spbwc_title, 0, 1));
                                $spbwc_color   = $palette[ord($spbwc_initial) % count($palette)];
                                $spbwc_count   = SPBWC_Storelly_Options_List_Table::spbwc_count_fields($spbwc_item['fields']);
                                $spbwc_pub     = (int) $spbwc_item['published'];

                                $spbwc_edit_url = esc_url(add_query_arg(array(
                                    'page'     => $_page_slug,
                                    'action'   => 'edit',
                                    'id'       => absint($spbwc_item['id']),
                                    'paged'    => 1,
                                    '_wpnonce' => $_nonce_block,
                                ), admin_url('admin.php')));

                                $spbwc_copy_url = esc_url(add_query_arg(array(
                                    'page'     => $_page_slug,
                                    'action'   => 'copy',
                                    'id'       => absint($spbwc_item['id']),
                                    'paged'    => 1,
                                    '_wpnonce' => $_nonce_block,
                                ), admin_url('admin.php')));

                                // Build categories string.
                                $spbwc_cat_html = '';
                                if (!empty($spbwc_item['product_cats'])) {
                                    $spbwc_cats = maybe_unserialize($spbwc_item['product_cats']);
                                    if (is_array($spbwc_cats)) {
                                        $spbwc_cat_names = array();
                                        foreach ($spbwc_cats as $spbwc_cid) {
                                            $spbwc_term = get_term(absint($spbwc_cid), 'product_cat');
                                            if ($spbwc_term && !is_wp_error($spbwc_term)) {
                                                $spbwc_cat_names[] = esc_html($spbwc_term->name);
                                            }
                                        }
                                        if ($spbwc_cat_names) {
                                            $spbwc_cat_html = implode(', ', $spbwc_cat_names);
                                        }
                                    }
                                }
                            ?>
                            <article class="spbwc-option-card" data-title="<?php echo esc_attr(mb_strtolower($spbwc_title)); ?>">
                                <a href="<?php echo $spbwc_edit_url; ?>" class="spbwc-option-card__thumb" aria-label="<?php echo esc_attr($spbwc_title); ?>" style="background-color:<?php echo esc_attr($spbwc_color); ?>">
                                    <span class="spbwc-option-card__initial" aria-hidden="true"><?php echo esc_html($spbwc_initial); ?></span>
                                </a>
                                <div class="spbwc-option-card__body">
                                    <h3 class="spbwc-option-card__title">
                                        <a href="<?php echo $spbwc_edit_url; ?>"><?php echo esc_html($spbwc_title); ?></a>
                                    </h3>
                                    <div class="spbwc-option-card__meta">
                                        <?php if (1 === $spbwc_pub) : ?>
                                        <span class="spbwc-badge spbwc-badge--published"><?php esc_html_e('Published', 'storelly-product-builder-for-woocommerce'); ?></span>
                                        <?php else : ?>
                                        <span class="spbwc-badge spbwc-badge--draft"><?php esc_html_e('Draft', 'storelly-product-builder-for-woocommerce'); ?></span>
                                        <?php endif; ?>
                                        <span class="spbwc-field-count-badge">
                                            <?php
                                            echo esc_html(sprintf(
                                                /* translators: %d: number of fields */
                                                _n('%d field', '%d fields', $spbwc_count, 'storelly-product-builder-for-woocommerce'),
                                                $spbwc_count
                                            ));
                                            ?>
                                        </span>
                                    </div>
                                    <?php if ($spbwc_cat_html) : ?>
                                    <div class="spbwc-option-card__cats">
                                        <span class="dashicons dashicons-category" aria-hidden="true"></span>
                                        <?php echo $spbwc_cat_html; ?>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (!empty($spbwc_item['modified'])) : ?>
                                    <div class="spbwc-option-card__date">
                                        <span class="dashicons dashicons-clock" aria-hidden="true"></span>
                                        <?php
                                        echo esc_html(sprintf(
                                            /* translators: %s: human time diff */
                                            __('Updated %s ago', 'storelly-product-builder-for-woocommerce'),
                                            human_time_diff(strtotime($spbwc_item['modified']), current_time('timestamp'))
                                        ));
                                        ?>
                                    </div>
                                    <?php endif; ?>
                                    <div class="spbwc-option-card__actions">
                                        <a href="<?php echo $spbwc_edit_url; ?>" class="spbwc-card-btn spbwc-card-btn--primary">
                                            <span class="dashicons dashicons-edit" aria-hidden="true"></span>
                                            <?php esc_html_e('Edit', 'storelly-product-builder-for-woocommerce'); ?>
                                        </a>
                                        <a href="<?php echo $spbwc_copy_url; ?>" class="spbwc-card-btn" title="<?php esc_attr_e('Duplicate this option group', 'storelly-product-builder-for-woocommerce'); ?>">
                                            <span class="dashicons dashicons-admin-page" aria-hidden="true"></span>
                                        </a>
                                    </div>
                                </div>
                            </article>
                            <?php endforeach; ?>
                        </div>
                        <?php else : ?>
                        <div class="spbwc-block-empty" id="spbwc-block-empty-default">
                            <span class="dashicons dashicons-tickets-alt" aria-hidden="true"></span>
                            <p><?php esc_html_e('No options yet.', 'storelly-product-builder-for-woocommerce'); ?></p>
                            <a href="<?php echo esc_url($link_create_option); ?>" class="spbwc-cta-btn spbwc-cta-btn--solid" style="margin-top:12px;">
                                <span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
                                <?php esc_html_e('Create your first option', 'storelly-product-builder-for-woocommerce'); ?>
                            </a>
                        </div>
                        <?php endif; ?>
                        <!-- Shown by JS when live search yields 0 matches -->
                        <div class="spbwc-block-empty" id="spbwc-block-empty-search" hidden>
                            <span class="dashicons dashicons-search" aria-hidden="true"></span>
                            <p><?php esc_html_e('No options match your search.', 'storelly-product-builder-for-woocommerce'); ?></p>
                        </div>

                    <?php if ($_total_pages_n > 1) : ?>
                    <nav class="spbwc-block-pagination" aria-label="<?php esc_attr_e('Pages', 'storelly-product-builder-for-woocommerce'); ?>">
                        <?php if ($_current_page_n > 1) : ?>
                        <a class="spbwc-page-btn"
                           href="<?php echo esc_url(add_query_arg('paged', $_current_page_n - 1, $_pager_base)); ?>"
                           aria-label="<?php esc_attr_e('Previous page', 'storelly-product-builder-for-woocommerce'); ?>">
                            <span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span>
                        </a>
                        <?php else : ?>
                        <span class="spbwc-page-btn is-disabled" aria-hidden="true">
                            <span class="dashicons dashicons-arrow-left-alt2"></span>
                        </span>
                        <?php endif; ?>

                        <span class="spbwc-page-info">
                            <?php
                            printf(
                                /* translators: 1: current page number, 2: total pages */
                                esc_html__('Page %1$d of %2$d', 'storelly-product-builder-for-woocommerce'),
                                $_current_page_n,
                                $_total_pages_n
                            );
                            ?>
                        </span>

                        <?php if ($_current_page_n < $_total_pages_n) : ?>
                        <a class="spbwc-page-btn"
                           href="<?php echo esc_url(add_query_arg('paged', $_current_page_n + 1, $_pager_base)); ?>"
                           aria-label="<?php esc_attr_e('Next page', 'storelly-product-builder-for-woocommerce'); ?>">
                            <span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
                        </a>
                        <?php else : ?>
                        <span class="spbwc-page-btn is-disabled" aria-hidden="true">
                            <span class="dashicons dashicons-arrow-right-alt2"></span>
                        </span>
                        <?php endif; ?>
                    </nav>
                    <?php endif; ?>
                    </div><!-- #spbwc-block-view -->
                </div>
            </div>
        </div>
        <br class="clear">
    </div>
</div>

<script>
(function () {
    'use strict';
    var STORAGE_KEY = 'spbwc_options_view';

    function filterBlockView(q) {
        var cards       = document.querySelectorAll('.spbwc-option-card');
        var emptySearch = document.getElementById('spbwc-block-empty-search');
        var emptyDefault = document.getElementById('spbwc-block-empty-default');
        var visible     = 0;

        cards.forEach(function (card) {
            var title = card.dataset.title || '';
            var show  = !q || title.indexOf(q) !== -1;
            card.style.display = show ? '' : 'none';
            if (show) { visible++; }
        });

        if (emptySearch)  { emptySearch.hidden  = (visible > 0 || cards.length === 0); }
        if (emptyDefault) { emptyDefault.hidden  = (cards.length > 0); }
    }

    function setView(view) {
        var form        = document.getElementById('spbwc-options-list-form');
        var blockView   = document.getElementById('spbwc-block-view');
        var searchWrap  = document.getElementById('spbwc-block-search-wrap');
        var searchInput = document.getElementById('spbwc-block-search');
        var btns        = document.querySelectorAll('.spbwc-view-btn');

        if (!form || !blockView) { return; }

        var table    = form.querySelector('table.wp-list-table');
        var tabnav   = form.querySelectorAll('.tablenav');
        var searchBox = form.querySelector('.search-box');

        if ('block' === view) {
            if (table)    { table.style.display = 'none'; }
            tabnav.forEach(function (el) { el.style.display = 'none'; });
            if (searchBox) { searchBox.style.display = 'none'; }
            blockView.style.display = '';
            blockView.removeAttribute('aria-hidden');
            if (searchWrap) { searchWrap.removeAttribute('hidden'); }
        } else {
            if (table)    { table.style.display = ''; }
            tabnav.forEach(function (el) { el.style.display = ''; });
            if (searchBox) { searchBox.style.display = ''; }
            blockView.style.display = 'none';
            blockView.setAttribute('aria-hidden', 'true');
            if (searchWrap)  { searchWrap.setAttribute('hidden', ''); }
            if (searchInput) { searchInput.value = ''; filterBlockView(''); }
        }

        btns.forEach(function (btn) {
            var isActive = btn.getAttribute('data-view') === view;
            btn.setAttribute('aria-pressed', isActive ? 'true' : 'false');
            btn.classList.toggle('active', isActive);
        });

        try { localStorage.setItem(STORAGE_KEY, view); } catch (e) { /* unavailable */ }
    }

    document.addEventListener('DOMContentLoaded', function () {
        var saved = 'list';
        try { saved = localStorage.getItem(STORAGE_KEY) || 'list'; } catch (e) { /* unavailable */ }
        setView(saved);

        document.querySelectorAll('.spbwc-view-btn').forEach(function (btn) {
            btn.addEventListener('click', function () { setView(this.getAttribute('data-view')); });
        });

        var blockSearch = document.getElementById('spbwc-block-search');
        if (blockSearch) {
            blockSearch.addEventListener('input', function () {
                filterBlockView(this.value.toLowerCase().trim());
            });
        }
    });
}());
</script>
