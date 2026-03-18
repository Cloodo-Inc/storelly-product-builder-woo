<?php
if (!defined('ABSPATH')) exit;
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variable.
$link_create_option = add_query_arg(
    array(
        'action'    => 'edit',
        'paged'     => 1,
        'id'        => 0
    ),
    admin_url('admin.php?page=' . SPBWC_PB_BUILDER_SLUG)
);
?>
<div class="wrap">
    <h1>
        <?php esc_html_e('Product Builder Options', 'storelly-product-builder-for-woocommerce'); ?>
        <a class="nbd-page-title-action" href="<?php echo esc_url($link_create_option); ?>"><?php esc_html_e('Add new', 'storelly-product-builder-for-woocommerce'); ?></a>
        <p class="note-text"><?php esc_html_e('There are no products that have integrated product builder yet. Please', 'storelly-product-builder-for-woocommerce'); ?> <a href="<?php echo esc_url(get_home_url() . '/wp-admin/post-new.php?post_type=product'); ?>"><?php esc_html_e('click here', 'storelly-product-builder-for-woocommerce'); ?></a><?php esc_html_e(' to create a product and integrate product builder', 'storelly-product-builder-for-woocommerce'); ?></p>
    </h1>
    <div id="poststuff">
        <div id="post-body" class="metabox-holder">
            <div id="post-body-content">
                <div class="meta-box-sortables ui-sortable">
                    <form method="post">
                        <?php
                        // Add nonce field for bulk actions.
                        wp_nonce_field('bulk-options', '_wpnonce');
                        $spbwc_options->spbwc_prepare_items();
                        $spbwc_options->display();
                        ?>
                    </form>
                </div>
            </div>
        </div>
        <br class="clear">
    </div>
</div>
