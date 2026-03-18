<?php if (!defined('ABSPATH')) exit; ?>
<?php
$link = add_query_arg(array(
    'paged'    => isset($_GET['paged']) ? sanitize_text_field( wp_unslash( $_GET['paged'] ) ) : 1 // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin query arg used to build navigation link.
), admin_url('admin.php?page=' . SPBWC_PB_BUILDER_SLUG));
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variable.
$link_update = add_query_arg(array(
    'action'    => 'update',
    'id'        => $options['id'],
), admin_url('admin.php?page=' . SPBWC_PB_BUILDER_SLUG));
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variable.
$link_unpublish = add_query_arg(array(
    'id'        => isset($options['id']) ? absint( $options['id'] ) : 0,
    'action'    => 'unpublish',
    '_wpnonce'  => wp_create_nonce('spbwc_unpublish_option_action'),
), $link);
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variable.
$link_create_option = add_query_arg(
    array(
        'action'    => 'create',
        'paged'     => 1,
        'id'        => 0
    ),
    admin_url('admin.php?page=' . SPBWC_PB_BUILDER_SLUG)
);
wp_enqueue_media();

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variable.
$link_create_pre_builder = add_query_arg(array(
    'oid'   => isset($options['id']) ? absint( $options['id'] ) : 0,
    'paged' => isset($_GET['paged']) ? sanitize_text_field( wp_unslash( $_GET['paged'] ) ) : 1, // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin query arg used to build action link.
    'rd'      => 'print_option',
    '_wpnonce' => wp_create_nonce( 'spbwc_builder_preview_action' ),
), SPBWC_Storelly_PB_Util::spbwc_get_url_page('product_builder'));
?>
<div class="wrap">
    <h2>
        <?php 
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variable.
        $current_id = isset($options['id']) ? $options['id'] : 0; 
        if ($current_id == 0) {
            esc_html_e('Create New Option', 'storelly-product-builder-for-woocommerce'); 
        } else {
            esc_html_e('Edit Options', 'storelly-product-builder-for-woocommerce');      
        }
        ?>
        <a class="nbd-page-title-action" href="<?php echo esc_url($link_create_option); ?>"><?php esc_html_e('Add new', 'storelly-product-builder-for-woocommerce'); ?></a>
    </h2>
</div>
<div class="message">
    <?php if (isset($message['flag'])) {
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variable.
        $message = SPBWC_Storelly_PB_Util::spbwc_custom_notices($message['flag'], $message['content']);
        echo wp_kses_post($message);
    } ?>
</div>
<div class="wrap" ng-app="optionApp" ng-cloak>
    <div ng-controller="optionCtrl">
        <form name="nboForm" action="" method="post" id="post">
            <?php wp_nonce_field( 'spbwc_save_option_action', '_wpnonce' ) ?>
            <div id="poststuff">
                <div id="post-body" class="metabox-holder columns-2">
                    <div id="post-body-content">
                        <div id="titlediv">
                            <div id="titlewrap">
                                <label class="screen-reader-text" id="title-prompt-text" for="title"><?php esc_html_e('Enter title here', 'storelly-product-builder-for-woocommerce'); ?></label>
                                <input required="required" ng-model="options.title" type="text" name="title" size="30" value="<?php echo esc_attr($options['title']); ?>" id="title" autocomplete="off">
                                <span style="color: red;" ng-show="nboForm.title.$invalid">* <small><i><?php esc_html_e('required', 'storelly-product-builder-for-woocommerce'); ?></i></small></span>
                                <input type="hidden" name="options[version]" value="<?php echo esc_attr(SPBWC_PB_VERSION); ?>" />
                            </div>
                        </div>
                    </div>
                    <div id="postbox-container-1" class="postbox-container">
                        <div id="submitdiv" class="postbox ">
                            <h2 class="hndle ui-sortable-handle"><span><?php esc_html_e('Publish', 'storelly-product-builder-for-woocommerce'); ?></span></h2>
                            <div class="inside">
                                <div class="submitbox" id="submitpost">
                                    <div class="minor-publishing">
                                        <div class="misc-publishing-actions nbo-dates">
                                            <div style="margin-bottom: 15px;">
                                                <label for="date_from"><?php esc_html_e('Status:', 'storelly-product-builder-for-woocommerce'); ?></label>
                                                <b style="vertical-align: middle;"><?php echo esc_html($options['published'] ? 'Published' : 'Trash');  ?></b>
                                            </div>
                                            <div style="margin-bottom: 15px;">
                                                <label for="date_from"><?php esc_html_e('Published on:', 'storelly-product-builder-for-woocommerce'); ?></label>
                                                <b style="vertical-align: middle;"><?php echo esc_html($options['created']); ?></b>
                                            </div>
                                            <div>
                                                <label for="date_to"><?php esc_html_e('Modified on:', 'storelly-product-builder-for-woocommerce'); ?></label>
                                                <b style="vertical-align: middle;"><?php echo esc_html($options['modified']); ?></b>
                                            </div>
                                        </div>
                                        <div class="clear"></div>
                                    </div>
                                    <div id="major-publishing-actions">
                                        <div id="delete-action">
                                            <?php if ($options['published'] == 1) : ?>
                                                <a class="submitdelete deletion" href="<?php echo esc_url($link_unpublish); ?>"><?php esc_html_e('Move to Trash', 'storelly-product-builder-for-woocommerce'); ?></a>
                                            <?php endif; ?>
                                        </div>
                                        <div id="publishing-action">
                                            <input ng-disabled="!nboForm.$valid" name="save" type="submit" class="button button-primary button-large" id="publish" ng-click="updateJsonFields($event)" accesskey="p" value="<?php ($id != 0) ? esc_attr_e('Update', 'storelly-product-builder-for-woocommerce') : esc_attr_e('Publish', 'storelly-product-builder-for-woocommerce'); ?>" />
                                        </div>
                                        <div class="clear"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div id="product_catdiv" class="postbox">
                            <h2 class="hndle ui-sortable-handle"><span><?php esc_html_e('Apply for', 'storelly-product-builder-for-woocommerce'); ?></span></h2>
                            <div class="inside nbo-toggle active" id="nbo-products-wrap">
                                <label for="product_ids" style="display: inline-block;margin-bottom: 10px;"><?php esc_html_e('Select the Products to apply the options', 'storelly-product-builder-for-woocommerce') ?></label>
                                <select name="product_ids[]" id="product_ids" class="wc-product-search" multiple="multiple" style="width: 100%;" data-placeholder="<?php esc_html_e('Search for a product&hellip;', 'storelly-product-builder-for-woocommerce'); ?>" data-action="woocommerce_json_search_products">
                                    <?php
                                    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variable.
                                    foreach ($options['product_ids'] as $product_id) {
                                        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variable.
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
                            <h2 style="color: #ff4136;" class="hndle ui-sortable-handle"><span style="vertical-align: bottom; margin-top: 0;" class="dashicons dashicons-warning"></span> <span><?php esc_html_e('Notice', 'storelly-product-builder-for-woocommerce'); ?></span></h2>
                            <div class="inside">
                                <p><?php esc_html_e('PHP max input vars:', 'storelly-product-builder-for-woocommerce'); ?> <?php echo esc_html($max_input_vars); ?></p>
                                <p><?php esc_html_e('Current input vars:', 'storelly-product-builder-for-woocommerce'); ?> <span>{{current_input_vars}}</span></p>
                                <p><?php esc_html_e('Please increase "PHP max input vars"!', 'storelly-product-builder-for-woocommerce'); ?></p>
                            </div>
                        </div>
                    </div>
                    <div id="postbox-container-2" class="postbox-container">
                        <div class="postbox">
                            <div class="inside">
                                <div class="nbd-option-actions">
                                    <a ng-click="import()" class="button-primary"><span class="dashicons dashicons-migrate nbd-r180"></span> <?php esc_html_e('Import', 'storelly-product-builder-for-woocommerce'); ?></a>
                                    <a ng-click="export()" class="button-primary"><span class="dashicons dashicons-migrate"></span> <?php esc_html_e('Export', 'storelly-product-builder-for-woocommerce'); ?></a>
                                </div>
                            </div>
                        </div>
                        <div class="postbox pcpb-fields-wrap">
                            <h2 style="border-bottom: 1px solid #ddd;"><?php esc_html_e('Production builder fields', 'storelly-product-builder-for-woocommerce'); ?></h2>
                            <div class="inside">
                                <div>
                                    <p class="section-title"><input class="nbd-ip-readonly" value="<?php esc_html_e('Default field', 'storelly-product-builder-for-woocommerce'); ?>" readonly=""></p>
                                    <div class="nbd-section-wrap">
                                        <a title="<?php esc_html_e('Add fields', 'storelly-product-builder-for-woocommerce'); ?>" class="pcpb-field-btn button" ng-click="add_field()">
                                            <?php esc_html_e('Default field', 'storelly-product-builder-for-woocommerce'); ?> <span class="nbo-type-label default">1</span>
                                        </a>
                                    </div>
                                </div>
                                <div style="margin-top: 10px;">
                                    <p class="section-title"><input class="nbd-ip-readonly" value="<?php esc_html_e('Product builder fields', 'storelly-product-builder-for-woocommerce'); ?>" readonly=""></p>
                                    <div class="nbd-section-wrap">
                                        <a class="pcpb-field-btn button" ng-click="add_field('nbpb_com', 'nbpb_com')"><?php esc_html_e('Component', 'storelly-product-builder-for-woocommerce'); ?> <span class="nbo-type-label wpo">2</span></a>
                                        <a class="pcpb-field-btn button" ng-click="add_field('nbpb_text', 'nbpb_text')"><?php esc_html_e('Text', 'storelly-product-builder-for-woocommerce'); ?> <span class="nbo-type-label wpo">3</span></a>
                                        <a class="pcpb-field-btn button" ng-click="add_field('nbpb_image', 'nbpb_image')"><?php esc_html_e('Image', 'storelly-product-builder-for-woocommerce'); ?> <span class="nbo-type-label wpo">4</span></a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="postbox">
                            <h2 style="border-bottom: 1px solid #ddd;"><?php esc_html_e('Production builder options', 'storelly-product-builder-for-woocommerce'); ?></h2>
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
                        <div class="postbox">
                            <h2 style="border-bottom: 1px solid #ddd;"><?php esc_html_e('Output design file ( PDF ).', 'storelly-product-builder-for-woocommerce'); ?></h2>
                            <div class="inside">
                                <div style="margin-top: 10px;">
                                    <div class="pcpb-field-info ng-scope">
                                        <div>
                                            <div class="pcpb-field-info-1">
                                                <label>
                                                    <b>
                                                        <?php esc_html_e('Output resolution - DPI', 'storelly-product-builder-for-woocommerce'); ?>
                                                    </b>
                                                </label>
                                            </div>
                                            <div class="pcpb-field-info-2">
                                                <div>
                                                    <input name="options[design_output][dpi]" type="text" string-to-number name="dpi" size="30" id="dpi" ng-model="options.design_output.dpi" autocomplete="off">
                                                </div>
                                            </div>
                                        </div>
                                        <div>
                                            <div class="pcpb-field-info-1">
                                                <label>
                                                    <b>
                                                        <?php esc_html_e('Dimensions unit', 'storelly-product-builder-for-woocommerce'); ?>
                                                    </b>
                                                </label>
                                            </div>
                                            <div class="pcpb-field-info-2">
                                                <div>
                                                    <select name="options[design_output][dimension_unit]" ng-model="options.design_output.dimension_unit">
                                                        <option value="cm">cm</option>
                                                        <option value="in">in</option>
                                                        <option value="mm">mm</option>
                                                        <option value="px">px</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
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
        <p id="nbp-processing" style="display: none;font-weight: bold;white-space: nowrap;"><?php esc_html_e('Processing', 'storelly-product-builder-for-woocommerce'); ?> <span id="nbp-process-loaded"></span> / <span id="nbp-process-total"></span></p>
    </div>
</div>
