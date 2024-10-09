<?php
do_action('storelly_head', 'single-product');
if (!defined('ABSPATH')) exit;
$in_quick_view      = false;
$is_wqv             = false;
$appid              = "nbo-app-6204";
$display_type       = '1';
$in_design_editor   = false;
$group_mode = false;

$nbds_frontend = array(
    'wc_currency_format_num_decimals'               =>  wc_get_price_decimals(),
    'currency_format_num_decimals'                  =>  4,
    'currency_format_symbol'                        =>  html_entity_decode((string) get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8'),
    'currency_format_decimal_sep'                   =>  stripslashes(wc_get_price_decimal_separator()),
    'currency_format_thousand_sep'                  =>  stripslashes(wc_get_price_thousand_separator()),
    'currency_format'                               =>  esc_attr(str_replace(array('%1$s', '%2$s'), array('%s', '%v'), get_woocommerce_price_format())),
    'nbstorelly_hide_add_cart_until_form_filled'    =>  'yes'
);

$prefix             = '';
$style_class        = 'nbo-style-1';

$currentDir = realpath(dirname(__FILE__));

?>
<div class="nbo-wrapper <?php if ($is_wqv) echo esc_attr('nbd-option-in-wqv'); ?> <?php echo esc_attr('wrapper-type-' . $display_type); ?>">
    <div class="nbd-option-wrapper" id="<?php echo esc_attr($appid); ?>">
        <div ng-controller="optionCtrl" ng-form="nboForm" id="nbo-ctrl-<?php echo esc_attr($appid); ?>" ng-cloak>
            <div class="nbo-fields-wrapper">
                <?php
                $html_field         = '';
                $has_nbpb           = false;
                $artwork_action     = '';
                $num_visible_field  = 0;
                $matrix_type        = 1;
                $options['matrix_type'] = $matrix_type;
                $options_fields     = $options["fields"];
                if (is_array($options_fields)) {
                    foreach ($options_fields as $key => $field) {
                        $class = '';
                        if (isset($field['nbpb_type']) && ($field['nbpb_type'] == 'nbpb_com' || $field['nbpb_type'] == 'nbpb_text' || $field['nbpb_type'] == 'nbpb_image')) {
                            $class      = 'nbo-hidden';
                            $has_nbpb   = true;
                        }

                        if (isset($field['general']['published']) && $field['general']['published'] == 'n') {
                            $class .= ' nbo-hidden';
                        }

                        if (isset($field['appearance']['css_class'])) {
                            $class .= ' ' . $field['appearance']['css_class'];
                        }
                        $class      = apply_filters('storelly_field_class', $class, $field);
                        $need_show  = true;
                        if ($field['general']['data_type'] == 'i') {
                            $tempalte = $currentDir . '/options-builder/input.php';
                        } else {
                            if (count($field['general']['attributes']["options"]) == 0) {
                                $need_show = false;
                            }
                            switch ($field['appearance']['display_type']) {
                                case 's':
                                    $tempalte = $currentDir . '/options-builder/swatch.php';
                                    break;
                                case 'l':
                                    $tempalte = $currentDir . '/options-builder/label.php';
                                    break;
                                case 'r':
                                    $tempalte = $currentDir . '/options-builder/radio.php';
                                    break;
                                case 'ad':
                                    $tempalte = $currentDir . '/options-builder/advanced-dropdown.php';
                                    break;
                                case 'xl':
                                    $tempalte = $currentDir . '/options-builder/xlabel.php';
                                    break;
                                default:
                                    $tempalte = $currentDir . '/options-builder/dropdown.php';
                                    break;
                            }
                        }
                        $options["fields"][$key]['template']    = $tempalte;
                        $options["fields"][$key]['need_show']   = $need_show;
                        $options["fields"][$key]['class']       = $class;
                        if ($field['general']['enabled'] == 'y' && $need_show) include($tempalte);
                        if ($field['general']['enabled'] == 'y' && $need_show && false === strpos($class, 'nbo-hidden')) {
                            $num_visible_field += 1;
                        }
                    }
                }

                $disable_quantity_input = false;
                $show_quantity_option   = false;

                $popup_fields   = array();
                if ($has_nbpb) do_action('storelly_after_default_options');
                ?>
                <div ng-if="fields.length" class="nbo-clear-option-wrap">
                    <?php if ($num_visible_field > 0) : ?>
                        <a class="button nbd-button" ng-click="reset_options()"><?php esc_html_e('Clear selection', 'pc-product-builder'); ?></a>
                    <?php endif; ?>
                </div>
                <input type="hidden" value="<?php echo esc_attr($product_id); ?>" name="pcpb-add-to-cart" />
                <p ng-if="!valid_form" class="nbd-invalid-form"><?php esc_html_e('Please check invalid fields and quantity input or choose a different combination!', 'pc-product-builder'); ?></p>
            </div>
            <div class="nbo-summary-wrapper">
                <div ng-if="valid_form" class="nbo-table-summary-wrap <?php echo esc_attr($style_class); ?>">
                    <p class="nbo-summary-title" ng-init="showNboSummary = true">
                        <b><?php esc_html_e('Summary options', 'pc-product-builder'); ?></b>
                        <span class="nbo-minus nbo-toggle" ng-show="showNboSummary" ng-click="showNboSummary = !showNboSummary">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
                                <path d="M19 13H5v-2h14v2z" />
                                <path d="M0 0h24v24H0z" fill="none" />
                            </svg>
                        </span>
                        <span class="nbo-plus nbo-toggle" ng-show="!showNboSummary" ng-click="showNboSummary = !showNboSummary">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
                                <path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z" />
                                <path d="M0 0h24v24H0z" fill="none" />
                            </svg>
                        </span>
                        <span class="nbo-float-summary-toggle" ng-click="toggle_float_summary()">
                            <svg xmlns="http://www.w3.org/2000/svg" version="1.1" width="24" height="24" viewBox="0 0 24 24">
                                <path d="M16.594 8.578l1.406 1.406-6 6-6-6 1.406-1.406 4.594 4.594z" />
                            </svg>
                        </span>
                    </p>
                    <table class="nbo-summary-table" ng-show="showNboSummary">
                        <tbody>
                            <tr ng-repeat="(key, field) in nbd_fields" ng-show="field.enable && field.published">
                                <td>{{field.title}} : <b>{{field.value_name}}</b>
                                    <br ng-if="field.ind_qty" /><small ng-if="field.ind_qty && field.price != ''"> <?php esc_html_e('( cart fee )', 'pc-product-builder'); ?></small>
                                    <br ng-if="field.fixed_amount" /><small ng-if="field.fixed_amount && field.price != ''"> <?php esc_html_e('( for all items )', 'pc-product-builder'); ?></small>
                                </td>
                                <td ng-bind-html="field.price | to_trusted"></td>
                            </tr>
                        </tbody>
                        <tfoot style="border-top: 1px solid #404762;">
                            <tr>
                                <td><b><?php esc_html_e('Options price', 'pc-product-builder'); ?></b></td>
                                <td><span id="nbd-option-total"><span ng-bind-html="total_price | to_trusted"></span> / <?php esc_html_e('1 item', 'pc-product-builder'); ?></span></td>
                            </tr>
                            <tr class="nbo-final-price">
                                <td><b><?php esc_html_e('Final price', 'pc-product-builder'); ?></b></td>
                                <td>
                                    <span id="nbd-option-total">
                                        <span ng-hide="_qty == 1" ng-bind-html="final_price | to_trusted"></span><span ng-show="_qty == 1" ng-bind-html="total_cart_price | to_trusted"></span> / <?php esc_html_e('1 item', 'pc-product-builder'); ?>
                                    </span>
                                </td>
                            </tr>
                            <tr class="nbo-final-price" ng-if="cart_item_fee.enable">
                                <td><b><?php esc_html_e('Cart item fee', 'pc-product-builder'); ?></b></td>
                                <td><span id="nbd-option-total"><span ng-bind-html="cart_item_fee.value | to_trusted"></span> / <?php esc_html_e('all items', 'pc-product-builder'); ?></span></td>
                            </tr>
                            <tr class="nbo-final-price nbo-total-price" ng-if="_qty > 1">
                                <td><b><?php esc_html_e('Subtotal price', 'pc-product-builder'); ?></b></td>
                                <td><span id="nbd-option-total"><span ng-bind-html="total_cart_price | to_trusted"></span> / {{_qty}} <?php esc_html_e('items', 'pc-product-builder'); ?></span></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>