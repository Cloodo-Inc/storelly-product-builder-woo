<?php if (!defined('ABSPATH')) exit; ?>
<?php
$link = add_query_arg(array(
    'paged'    => sanitize_text_field($_GET['paged'])
), admin_url('admin.php?page=pc-product-builder-options'));
$link_update = add_query_arg(array(
    'action'    => 'update',
    'id'        => $options['id'],
), admin_url('admin.php?page=pc-product-builder-options'));
$link_unpublish = add_query_arg(array(
    'id'        => sanitize_text_field($_GET['id']),
    'action'    => 'unpublish'
), $link);
$link_create_option = add_query_arg(
    array(
        'action'    => 'edit',
        'paged'     => 1,
        'id'        => 0
    ),
    admin_url('admin.php?page=pc-product-builder-options')
);
wp_enqueue_media();
$current_url = add_query_arg($_GET, admin_url('admin.php?page=pc-product-builder-options'));
$link_create_pre_builder = add_query_arg(array(
    'oid'   => sanitize_text_field($_GET['id']),
    'paged' => sanitize_text_field($_GET['paged']),
    'rd'    => 'print_option'
), Printcart_PB_Util::printcartGetUrlPage('product_builder'));
$max_input_vars = Printcart_PB_Util::printcart_get_max_input_var();
?>
<!-- No inline scripts or styles unless dynamic. -->
<script type="text/javascript">
    var PRINTCART_OPTIONS = <?php echo json_encode($options); ?>;
    var PRINTCART_OPTION_FIELD = <?php echo json_encode($default_field); ?>;
    var ajax_url = "<?php echo admin_url('admin-ajax.php'); ?>",
        nbnonce = "<?php echo wp_create_nonce('save-design'); ?>",
        max_input_vars = parseInt(<?php echo ($max_input_vars); ?>);
</script>
<div class="wrap">
    <h2>
        <?php esc_html_e('Edit Options', 'pc-product-builder'); ?>
        <a class="nbd-page-title-action" href="<?php echo ($link_create_option); ?>"><?php esc_html_e('Add new', 'pc-product-builder'); ?></a>
    </h2>
</div>
<div class="message">
    <?php if (isset($message['flag'])) {
        $message = Printcart_PB_Util::printcart_custom_notices($message['flag'], $message['content']);
        echo ($message);
    } ?>
</div>
<div class="wrap" ng-app="optionApp" ng-cloak>
    <div ng-controller="optionCtrl">
        <form name="nboForm" action="" method="post" id="post">
            <div id="poststuff">
                <div id="post-body" class="metabox-holder columns-2">
                    <div id="post-body-content">
                        <div id="titlediv">
                            <div id="titlewrap">
                                <label class="screen-reader-text" id="title-prompt-text" for="title"><?php esc_html_e('Enter title here', 'pc-product-builder'); ?></label>
                                <input required="required" ng-model="options.title" type="text" name="title" size="30" value="<?php echo ($options['title']); ?>" id="title" autocomplete="off">
                                <span style="color: red;" ng-show="nboForm.title.$invalid">* <small><i><?php esc_html_e('required', 'pc-product-builder'); ?></i></small></span>
                                <input type="hidden" name="options[version]" value="<?php echo PRINTCART_PB_VERSION; ?>" />
                            </div>
                        </div>
                    </div>
                    <div id="postbox-container-1" class="postbox-container">
                        <div id="submitdiv" class="postbox ">
                            <h2 class="hndle ui-sortable-handle"><span><?php esc_html_e('Publish', 'pc-product-builder'); ?></span></h2>
                            <div class="inside">
                                <div class="submitbox" id="submitpost">
                                    <div class="minor-publishing">
                                        <div class="misc-publishing-actions nbo-dates">
                                            <div style="margin-bottom: 15px;">
                                                <label for="date_from"><?php _e('Status:', 'pc-product-builder'); ?></label>
                                                <b style="vertical-align: middle;"><?php echo $options['published'] ? 'Published' : 'Trash';  ?></b>
                                            </div>
                                            <div style="margin-bottom: 15px;">
                                                <label for="date_from"><?php _e('Published on:', 'pc-product-builder'); ?></label>
                                                <b style="vertical-align: middle;"><?php echo $options['created']; ?></b>
                                            </div>
                                            <div>
                                                <label for="date_to"><?php _e('Modified on:', 'pc-product-builder'); ?></label>
                                                <b style="vertical-align: middle;"><?php echo $options['modified']; ?></b>
                                            </div>
                                        </div>
                                        <div class="clear"></div>
                                    </div>
                                    <div id="major-publishing-actions">
                                        <div id="delete-action">
                                            <?php if ($options['published'] == 1) : ?>
                                                <a class="submitdelete deletion" href="<?php echo $link_unpublish; ?>"><?php _e('Move to Trash', 'pc-product-builder'); ?></a>
                                            <?php endif; ?>
                                        </div>
                                        <div id="publishing-action">
                                            <input ng-disabled="!nboForm.$valid" name="save" type="submit" class="button button-primary button-large" id="publish" ng-click="updateJsonFields($event)" accesskey="p" value="<?php ($id != 0) ? esc_attr_e('Update') : esc_attr_e('Publish'); ?>" />
                                        </div>
                                        <div class="clear"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div id="product_catdiv" class="postbox">
                            <h2 class="hndle ui-sortable-handle"><span><?php esc_html_e('Apply for', 'pc-product-builder'); ?></span></h2>
                            <div class="inside nbo-toggle active" id="nbo-products-wrap">
                                <label for="product_ids" style="display: inline-block;margin-bottom: 10px;"><?php esc_html_e('Select the Products to apply the options', 'pc-product-builder') ?></label>
                                <select name="product_ids[]" id="product_ids" class="wc-product-search" multiple="multiple" style="width: 100%;" data-placeholder="<?php esc_html_e('Search for a product&hellip;', 'pc-product-builder'); ?>" data-action="woocommerce_json_search_products">
                                    <?php
                                    foreach ($options['product_ids'] as $product_id) {
                                        $product = wc_get_product($product_id);
                                        if (is_object($product)) {
                                            echo '<option value="' . esc_attr($product_id) . '"' . selected(TRUE, TRUE, FALSE) . '>' . wp_kses_post($product->get_formatted_name()) . '</option>';
                                        }
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                        <div id="notice-max-input-vars" class="postbox" ng-show="current_input_vars > max_input_vars">
                            <h2 style="color: #ff4136;" class="hndle ui-sortable-handle"><span style="vertical-align: bottom; margin-top: 0;" class="dashicons dashicons-warning"></span> <span><?php esc_html_e('Notice', 'pc-product-builder'); ?></span></h2>
                            <div class="inside">
                                <p><?php esc_html_e('PHP max input vars:', 'pc-product-builder'); ?> <?php echo ($max_input_vars); ?></p>
                                <p><?php esc_html_e('Current input vars:', 'pc-product-builder'); ?> <span>{{current_input_vars}}</span></p>
                                <p><?php esc_html_e('Please increase "PHP max input vars"!', 'pc-product-builder'); ?></p>
                            </div>
                        </div>
                    </div>
                    <div id="postbox-container-2" class="postbox-container">
                        <div class="postbox">
                            <div class="inside">
                                <div class="nbd-option-actions">
                                    <a ng-click="import()" class="button-primary"><span class="dashicons dashicons-migrate nbd-r180"></span> <?php esc_html_e('Import', 'pc-product-builder'); ?></a>
                                    <a ng-click="export()" class="button-primary"><span class="dashicons dashicons-migrate"></span> <?php esc_html_e('Export', 'pc-product-builder'); ?></a>
                                </div>
                            </div>
                        </div>
                        <div class="postbox pcpb-fields-wrap">
                            <h2 style="border-bottom: 1px solid #ddd;"><?php esc_html_e('Production builder fields', 'pc-product-builder'); ?></h2>
                            <div class="inside">
                                <div>
                                    <p class="section-title"><input class="nbd-ip-readonly" value="<?php esc_html_e('Default field', 'pc-product-builder'); ?>" readonly=""></p>
                                    <div class="nbd-section-wrap">
                                        <a title="<?php esc_html_e('Add fields', 'pc-product-builder'); ?>" class="pcpb-field-btn button" ng-click="add_field()">
                                            <?php esc_html_e('Default field', 'pc-product-builder'); ?> <span class="nbo-type-label default">1</span>
                                        </a>
                                    </div>
                                </div>
                                <div style="margin-top: 10px;">
                                    <p class="section-title"><input class="nbd-ip-readonly" value="<?php esc_html_e('Product builder fields', 'pc-product-builder'); ?>" readonly=""></p>
                                    <div class="nbd-section-wrap">
                                        <a class="pcpb-field-btn button" ng-click="add_field('nbpb_com', 'nbpb_com')"><?php esc_html_e('Component', 'pc-product-builder'); ?> <span class="nbo-type-label wpo">2</span></a>
                                        <a class="pcpb-field-btn button" ng-click="add_field('nbpb_text', 'nbpb_text')"><?php esc_html_e('Text', 'pc-product-builder'); ?> <span class="nbo-type-label wpo">3</span></a>
                                        <a class="pcpb-field-btn button" ng-click="add_field('nbpb_image', 'nbpb_image')"><?php esc_html_e('Image', 'pc-product-builder'); ?> <span class="nbo-type-label wpo">4</span></a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="postbox">
                            <h2 style="border-bottom: 1px solid #ddd;"><?php esc_html_e('Production builder options', 'pc-product-builder'); ?></h2>
                            <div class="inside">
                                <div class="pcpb-fields-builder">
                                    <?php include_once('field.php'); ?>
                                </div>
                                <div ng-repeat="view in options.views">
                                    <input ng-hide="true" ng-model="view.name" name="options[views][{{$index}}][name]" />
                                    <input ng-hide="true" ng-model="view.base" name="options[views][{{$index}}][base]" />
                                    <input ng-hide="true" ng-model="view.base_width" name="options[views][{{$index}}][base_width]" />
                                    <input ng-hide="true" ng-model="view.base_height" name="options[views][{{$index}}][base_height]" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="clear"></div>
        </form>
        <?php include_once('preview.php'); ?>
    </div>
</div>
<div class="nbp-loading-wrap">
    <div class="nbp-loading-spinner">
        <div class="nbp-loading-ball"></div>
        <p id="nbp-processing" style="display: none;font-weight: bold;white-space: nowrap;"><?php esc_html_e('Processing', 'pc-product-builder'); ?> <span id="nbp-process-loaded"></span> / <span id="nbp-process-total"></span></p>
    </div>
</div>