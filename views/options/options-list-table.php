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

    <!-- View mode controls -->
    <div class="spbwc-list-controls">
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
                            <article class="spbwc-option-card">
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
                                    <div class="spbwc-option-card__actions">
                                        <a href="<?php echo $spbwc_edit_url; ?>" class="button button-small"><?php esc_html_e('Edit', 'storelly-product-builder-for-woocommerce'); ?></a>
                                        <a href="<?php echo $spbwc_copy_url; ?>" class="button button-small"><?php esc_html_e('Copy', 'storelly-product-builder-for-woocommerce'); ?></a>
                                    </div>
                                </div>
                            </article>
                            <?php endforeach; ?>
                        </div>
                        <?php else : ?>
                        <div class="spbwc-block-empty">
                            <span class="dashicons dashicons-tickets-alt" aria-hidden="true"></span>
                            <p><?php esc_html_e('No options available.', 'storelly-product-builder-for-woocommerce'); ?></p>
                        </div>
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

    function setView(view) {
        var form      = document.getElementById('spbwc-options-list-form');
        var blockView = document.getElementById('spbwc-block-view');
        var btns      = document.querySelectorAll('.spbwc-view-btn');

        if (!form || !blockView) return;

        var table = form.querySelector('table.wp-list-table');

        if ('block' === view) {
            if (table)      table.style.display = 'none';
            blockView.style.display = '';
            blockView.removeAttribute('aria-hidden');
        } else {
            if (table)      table.style.display = '';
            blockView.style.display = 'none';
            blockView.setAttribute('aria-hidden', 'true');
        }

        btns.forEach(function (btn) {
            var isActive = btn.getAttribute('data-view') === view;
            btn.setAttribute('aria-pressed', isActive ? 'true' : 'false');
            if (isActive) {
                btn.classList.add('active');
            } else {
                btn.classList.remove('active');
            }
        });

        try {
            localStorage.setItem(STORAGE_KEY, view);
        } catch (e) { /* storage unavailable */ }
    }

    document.addEventListener('DOMContentLoaded', function () {
        var saved = 'list';
        try {
            saved = localStorage.getItem(STORAGE_KEY) || 'list';
        } catch (e) { /* storage unavailable */ }

        setView(saved);

        document.querySelectorAll('.spbwc-view-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                setView(this.getAttribute('data-view'));
            });
        });
    });
}());
</script>
