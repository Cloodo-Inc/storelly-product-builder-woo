<?php
if (!defined('ABSPATH')) exit;
$link_create_option = add_query_arg(
    array(
        'action'    => 'edit',
        'paged'     => 1,
        'id'        => 0
    ),
    admin_url('admin.php?page=pc-product-builder-options')
);
?>
<div class="wrap">
    <h1>
        <?php esc_html_e('Product Builder Options', 'pc-product-builder'); ?>
        <a class="nbd-page-title-action" href="<?php echo ($link_create_option); ?>"><?php esc_html_e('Add new', 'pc-product-builder'); ?></a>
        <p class="note-text"><?php esc_html_e('There are no products that have integrated product builder yet. Please', 'pc-product-builder'); ?> <a href="<?php echo get_home_url() . '/wp-admin/post-new.php?post_type=product'; ?>"><?php esc_html_e('click here', 'pc-product-builder'); ?></a><?php esc_html_e(' to create a product and integrate product builder', 'pc-product-builder'); ?></p>
    </h1>
    <div id="poststuff">
        <div id="post-body" class="metabox-holder">
            <div id="post-body-content">
                <div class="meta-box-sortables ui-sortable">
                    <form method="post">
                        <?php
                        $nbd_options->prepare_items();
                        $nbd_options->display();
                        ?>
                    </form>
                </div>
            </div>
        </div>
        <br class="clear">
    </div>
</div>
