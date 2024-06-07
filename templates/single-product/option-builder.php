<?php
do_action('pc_head', 'single-product');
if (!defined('ABSPATH')) exit;
$in_quick_view  = false;
$is_wqv         = false;
$appid              = "nbo-app-" . time() . rand(1, 1000);
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
                        $class      = apply_filters('nbo_field_class', $class, $field);
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
                if ($has_nbpb) do_action('nbo_after_default_options');
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
    <!-- No inline scripts or styles unless dynamic. -->
    <script type="text/javascript">
        var in_quick_view = <?php echo esc_js($in_quick_view ? 1 : 0); ?>;
        nbds_frontend = <?php echo wp_json_encode($nbds_frontend); ?>;
        var nbOption = {
            status: false,
            initialed: false,
            options: <?php echo wp_json_encode($options); ?>,
            nbd_fields: {},
            extraOdOption: {},
            lastOdOption: {},
            lastExtraOdOption: {},
           c
            updateVariations: function() {
                var scope = angular.element(document.getElementById(nbOption.crtlId)).scope();
                scope.updateVariations();
            },
            updateBulkPrice: function() {
                var scope = angular.element(document.getElementById(nbOption.crtlId)).scope();
                scope.calculate_bulk_total_price();
            },
            options_str: '',
            prev_options_str: '',
            design_stored: 0,
        };
        jQuery('.variations_form').on('woocommerce_variation_has_changed wc_variation_form', function() {
            startApp();
        });
        jQuery('.variations_form').on('found_variation', function() {
            setTimeout(function() {
                startApp();
            }, 100);
        });

        function _debounce(func, wait, immediate) {
            var timeout;
            return function() {
                var context = this,
                    args = arguments;
                var later = function() {
                    timeout = null;
                    if (!immediate) func.apply(context, args);
                };
                var callNow = immediate && !timeout;
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
                if (callNow) func.apply(context, args);
            };
        };
        jQuery(document).ready(function() {
            <?php if ($show_quantity_option && !$disable_quantity_input && $options['quantity_type'] == 'r') : ?>
                if (nbOption.options.quantity_min != '') {
                    jQuery('input[name="quantity"]').attr('min', nbOption.options.quantity_min);
                }
                if (nbOption.options.quantity_max != '') {
                    jQuery('input[name="quantity"]').attr('max', nbOption.options.quantity_max);
                }
                var changeQtyFn = _debounce(function(event) {
                    if (event.namespace == 'nbo') {
                        startApp();
                    } else {
                        startApp(true);
                    }
                }, 1000);
            <?php else : ?>
                var changeQtyFn = function(event) {
                    if (event.namespace == 'nbo') {
                        startApp();
                    } else {
                        startApp(true);
                    }
                };
            <?php endif ?>
            jQuery('input[name="quantity"]').on('input change change.nbo', changeQtyFn);
            <?php if ($disable_quantity_input) : ?>
                jQuery('input[name="quantity"]').on('click', function() {
                    if (nbOption.status) {
                        jQuery('html,body').animate({
                            scrollTop: jQuery("#nbo-quantity-option-wrap").offset().top
                        }, 'slow');
                    }
                });
            <?php endif; ?>
            jQuery('#nbd-trigger-nbo-popup').on('click', function() {
                jQuery('#nbo-detail-popup-wrap').showNBDPopup();
            });
            jQuery('#nbo-sumit-popup-action').on('click', function() {
                jQuery('.single_add_to_cart_button').trigger('click');
                jQuery('#nbo-detail-popup-wrap .popup-inner').trigger('click');
            });
        });

        function startApp(updateQty) {
            if (nbOption.status) {
                var scope = angular.element(document.getElementById("nbo-ctrl-<?php echo esc_attr($appid); ?>")).scope();
                scope.mapOptions();
                scope.check_valid();
                scope.update_app();
                <?php if ($show_quantity_option && !$disable_quantity_input) : ?>
                    if (angular.isDefined(updateQty)) {
                        scope.quantity = scope.validate_int(jQuery('input[name="quantity"]').val());
                    }
                <?php endif; ?>
            }
        };
        <?php if ($in_design_editor) : ?>
            var nboApp = nbdApp;
        <?php else : ?>
            var nboApp = angular.module('nboApp', []);
        <?php endif; ?>

        function nbo_variation_calculator(variation_attributes, product_variations, all_set_callback, not_all_set_callback) {
            this.recalc_needed = true;

            this.all_set_callback = all_set_callback;
            this.not_all_set_callback = not_all_set_callback;
            this.variation_attributes = variation_attributes;
            this.variations_available = product_variations;
            this.variations_current = {};
            this.variations_selected = {};

            this.reset_current = function() {
                for (var attribute in this.variation_attributes) {
                    this.variations_current[attribute] = {};
                    for (var av = 0; av < this.variation_attributes[attribute].length; av++) {
                        this.variations_current[attribute.toString()][this.variation_attributes[attribute][av].toString()] = 0;
                    }
                }
            };

            this.update_current = function() {
                this.reset_current();
                for (var i = 0; i < this.variations_available.length; i++) {
                    if (!this.variations_available[i].variation_is_active) {
                        continue;
                    }

                    var variation_attributes = this.variations_available[i].attributes;

                    for (var attribute in variation_attributes) {
                        var maybe_available_attribute_value = variation_attributes[attribute];
                        var selected_value = this.variations_selected[attribute];

                        if (selected_value && selected_value == maybe_available_attribute_value) {
                            this.variations_current[attribute][maybe_available_attribute_value] = 1;
                        } else {
                            var result = true;
                            for (var other_selected_attribute in this.variations_selected) {
                                if (other_selected_attribute == attribute) {
                                    continue;
                                }

                                var other_selected_attribute_value = this.variations_selected[other_selected_attribute];
                                var other_available_attribute_value = variation_attributes[other_selected_attribute];

                                if (other_selected_attribute_value) {
                                    if (other_available_attribute_value) {
                                        if (other_selected_attribute_value != other_available_attribute_value) {
                                            result = false;
                                        }
                                    }
                                }
                            }
                            if (result) {
                                if (maybe_available_attribute_value === "") {
                                    for (var av in this.variations_current[attribute]) {
                                        this.variations_current[attribute][av] = 1;
                                    }
                                } else {
                                    this.variations_current[attribute][maybe_available_attribute_value] = 1;
                                }
                            }
                        }
                    }
                }
                this.recalc_needed = false;
            };

            this.get_current = function() {
                if (this.recalc_needed) {
                    this.update_current();
                }
                return this.variations_current;
            };

            this.reset_selected = function() {
                this.recalc_needed = true;
                this.variations_selected = {};
            }

            this.set_selected = function(key, value) {
                this.recalc_needed = true;
                this.variations_selected[key] = value;
            };

            this.get_selected = function() {
                return this.variations_selected;
            }
        };

        nboApp.controller('optionCtrl', ['$scope', '$timeout', function($scope, $timeout) {
            $scope.product_id = <?php echo esc_attr($product_id); ?>;
            $scope.options = nbOption.options;
            $scope.fields = $scope.options["fields"];
            $scope.price = "<?php echo($price); ?>";
            $scope.type = "<?php echo($type); ?>";
            $scope.variations = <?php echo($variations); ?>;
            $scope.form_values = <?php echo wp_json_encode($form_values); ?>;
            $scope.is_sold_individually = "<?php echo($is_sold_individually); ?>";
            $scope._quantity = "<?php echo($quantity); ?>";
            $scope.ajax_url = "<?php echo esc_url(admin_url('admin-ajax.php')); ?>";
            $scope.valid_form = false;
            $scope.product_image = [];
            $scope.product_img = [];
            $scope.price_table = [];
            $scope.turnaround_matrix = [];
            $scope.custom_quantity = false;
            $scope.current_group_panel = 0;
            $scope.total_cart_item_price_num = 0;
            $scope.check_valid = function(calculate_pm, pro) {
                $timeout(function() {
                    $scope.$emit("nbo_options_changed", $scope.nbd_fields);
                    var check = {},
                        total_check = true,
                        show_popup_trigger = false;
                    angular.forEach($scope.nbd_fields, function(field, field_id) {
                        $scope.check_depend(field_id);
                        field.valid = true;
                        field.invalidOption = '';
                        check[field_id] = (field.enable && field.required == 'y' && (field.value === '' || angular.isUndefined(field.value))) ? false : true;
                        var origin_field = $scope.get_field(field_id);
                        if (angular.isUndefined(origin_field.general.published)) {
                            field.published = true;
                        } else {
                            field.published = origin_field.general.published == 'y' ? true : false;
                        }
                        if (origin_field.general.data_type == 'i') {
                            if (origin_field.general.input_type != 't' && origin_field.general.input_type != 'a') {
                                if (angular.isUndefined(field.value)) check[field_id] = false;
                                if (origin_field.general.input_type == 'u' && field.required != 'y') check[field_id] = true;
                            } else {
                                if (field.enable && field.required == 'y') {
                                    if (angular.isDefined(origin_field.general.text_option.min) && origin_field.general.text_option.min != '') {
                                        var min = $scope.validate_int(origin_field.general.text_option.min);
                                        if (field.value.length < min) check[field_id] = false;
                                    }
                                    if (angular.isDefined(origin_field.general.text_option.max) && origin_field.general.text_option.max != '') {
                                        var max = $scope.validate_int(origin_field.general.text_option.max);
                                        if (field.value.length > max) check[field_id] = false;
                                    }
                                }

                            }
                            field.value_name = '';
                            if (angular.isDefined(field.value)) {
                                if (origin_field.general.input_type != 'u') {
                                    field.value_name = field.value;
                                } else if (angular.isDefined(field.value.name)) {
                                    field.value_name = field.value.name;
                                }
                            }
                        } else {
                            if (angular.isDefined(field.values)) {
                                field.value_name = '';
                                angular.forEach(field.values, function(val, index) {
                                    field.value_name += (index == 0 ? '' : ', ') + origin_field.general.attributes.options[val].name;
                                });
                            } else {
                                var selected_option = origin_field.general.attributes.options[field.value];
                                field.value_name = selected_option.name;
                                if (angular.isDefined($scope.nbd_fields[field_id])) {
                                    $scope.nbd_fields[field_id].form_name = '';
                                    if (angular.isDefined(selected_option.enable_subattr) && selected_option.enable_subattr == 'on') {
                                        if (angular.isDefined(selected_option.sub_attributes) && selected_option.sub_attributes.length > 0) {
                                            $scope.nbd_fields[field_id].form_name = selected_option.form_name;
                                            if (angular.isUndefined(selected_option.sub_attributes[$scope.nbd_fields[field_id].sub_value])) {
                                                $scope.nbd_fields[field_id].sub_value = '0';
                                            }
                                            field.value_name += ' - ' + selected_option.sub_attributes[$scope.nbd_fields[field_id].sub_value].name;
                                        }
                                    }
                                    if (origin_field.appearance.display_type == 'ad') {
                                        $scope.nbd_fields[field_id].form_name = '[value]';
                                    }
                                }
                                if (origin_field.general.attributes.options.length) {
                                    origin_field.general.attributes.options.forEach(function(op, opIndex) {
                                        $scope.checkAttributeStatus(field_id, opIndex);

                                        if (angular.isDefined(op.enable_subattr) && op.enable_subattr == 'on' && op.sub_attributes.length > 0) {
                                            op.sub_attributes.forEach(function(sop, sopIndex) {
                                                $scope.checkAttributeStatus(field_id, opIndex, sopIndex);
                                            });
                                        }
                                    });

                                    if (!$scope.status_fields[field_id][field.value].enable) {
                                        check[field_id] = false;
                                        field.valid = false;
                                        field.invalidOption = selected_option.name;
                                    }

                                    if (angular.isDefined(field.sub_value)) {
                                        if (angular.isDefined(selected_option.enable_subattr) && selected_option.enable_subattr == 'on' && selected_option.sub_attributes.length > 0) {
                                            var selected_sub_option = selected_option.sub_attributes[field.sub_value];
                                            if (!$scope.status_fields[field_id][field.value].sub_attributes[field.sub_value]) {
                                                check[field_id] = false;
                                                field.valid = false;
                                                field.invalidOption = selected_sub_option.name;
                                            }
                                        }
                                    }
                                }

                            }
                        }
                        if (!field.enable) check[field_id] = true;
                    });
                    if (show_popup_trigger) {
                        jQuery('#nbd-trigger-nbo-popup').css('display', 'inline-block');
                        jQuery('.single_add_to_cart_button').addClass('nbop-hidden');
                    } else {
                        jQuery('#nbd-trigger-nbo-popup').css('display', 'none');
                        jQuery('.single_add_to_cart_button').removeClass('nbop-hidden');
                    }
                    angular.forEach(check, function(c) {
                        total_check = total_check && c;
                    });
                    if (total_check) {
                        $scope.calculate_price();
                        $scope.valid_form = true;
                        jQuery('.single_add_to_cart_button').removeClass("nbo-disabled nbo-hidden");
                        jQuery('.variations_form, form.cart').find('[name="nbo-ignore-design"]').remove();
                        jQuery(document).triggerHandler('nbo_valid_form');
                    } else {
                        jQuery(document).triggerHandler('invalid_nbo_options');
                        jQuery('.single_add_to_cart_button').addClass("nbo-disabled");
                        $scope.valid_form = false;
                        jQuery(document).triggerHandler('nbo_invalid_form');
                    }
                    $scope.may_be_change_product_image();
                    angular.copy($scope.nbd_fields, nbOption.nbd_fields);
                    if (!nbOption.initialed) {
                        jQuery(document).triggerHandler('initialed_nbo_options');
                        nbOption.initialed = true;

                        function inIframe() {
                            try {
                                return window.self !== window.top;
                            } catch (e) {
                                return true;
                            }
                        }
                        if (inIframe()) {
                            window.parent.postMessage('initialed_nbo_options', window.location.origin);
                        }
                    } else {
                        jQuery(document).triggerHandler('update_nbo_options', {
                            pro: pro
                        });
                    };

                    var preventEnter = function(event) {
                        if (event.keyCode == 13) {
                            event.preventDefault();
                            return false;
                        }
                    };
                    jQuery('.variations_form input, form.cart input').off('keydown', preventEnter).on('keydown', preventEnter);

                    if (angular.isDefined($scope.no_of_group) && $scope.no_of_group != 0) {
                        $scope.changeGroupPanel(null, -1);
                    }
                    jQuery(document).triggerHandler('trigger_nbo_options_changed', {
                        fields: $scope.nbd_fields,
                        pro: pro
                    });

                    $scope.update_app();
                });
            };
            $scope.updateVariations = function() {
                nbOption.variations = [];
                var bulkForm = jQuery('.nbo-bulk-variation input, .nbo-bulk-variation select').serializeJSON();
                angular.forEach(bulkForm['nbb-qty-fields'], function(bf_field, bf_index) {
                    angular.forEach(bulkForm['nbb-fields'], function(bff_field, bff_id) {
                        var origin_field = $scope.get_field(bff_id);
                    });
                });
                if (nbOption.variations.length) {
                    jQuery(document).triggerHandler('change_nbo_size_variations');
                }
            };
            $scope.set_product_image_attr = function(ele, attr, value, id) {
                if (angular.isUndefined($scope.product_image[id]) || angular.isUndefined($scope.product_image[id][attr])) {
                    if (angular.isUndefined($scope.product_image[id])) $scope.product_image[id] = {};
                    $scope.product_image[id][attr] = ele.attr(attr);
                }
                if (false === value) {
                    ele.removeAttr(attr);
                } else {
                    ele.attr(attr, value);
                }
            };
            $scope.may_be_change_product_image = function() {
                $scope.product_img = [];
                angular.forEach($scope.nbd_fields, function(_field, field_id) {
                    var field = $scope.get_field(field_id);
                    if (field.general.data_type == 'm' && field.appearance.change_image_product == 'y' &&
                        field.general.attributes.options[_field.value].imagep == 'y' && _field.enable) {
                        $scope.product_img.field_id = field_id;
                        $scope.product_img.option_index = _field.value;
                    }
                });
                if (angular.isDefined($scope.product_img.field_id) && angular.isDefined($scope.product_img.option_index)) {
                    $scope.change_product_image($scope.product_img.field_id, $scope.product_img.option_index);
                }
            };
            $scope.change_product_image = function(field_id, option_index) {
                var field = $scope.get_field(field_id);
                if (field.appearance.change_image_product == 'y' && field.general.attributes.options[option_index].imagep == 'y') {
                    var product_element = jQuery('#product-' + $scope.product_id);
                    var product_image = product_element.find('.woocommerce-product-gallery__image:not(.clone), .woocommerce-product-gallery__image--placeholder:not(.clone)').eq(0).find('.wp-post-image').first();
                    if (product_image.length === 0) {
                        product_image = product_element.find("a.woocommerce-main-image img, img.woocommerce-main-image").not('.thumbnails img,.product_list_widget img').first();
                    }
                    if (jQuery(product_image).length > 1) {
                        product_image = jQuery(product_image).first();
                    }
                    var gallery_image = product_element.find('.flex-control-nav li:eq(0) img'),
                        gallery_wrapper = product_element.find('.woocommerce-product-gallery__wrapper '),
                        product_image_wrap = gallery_wrapper.find('.woocommerce-product-gallery__image, .woocommerce-product-gallery__image--placeholder').eq(0),
                        product_link = product_image.closest('a');
                    var option_data = field.general.attributes.options[option_index];
                    if (!option_data.full_src) option_data.full_src = option_data.image_link;
                    if (product_image.length) {
                        if (!option_data.full_src_w) option_data.full_src = product_image.attr('data-large_image_width');
                        if (!option_data.full_src_h) option_data.full_src_h = product_image.attr('data-large_image_height');
                        $scope.set_product_image_attr(product_image, 'src', option_data.image_link, 0);
                        $scope.set_product_image_attr(product_image, 'srcset', option_data.image_srcset, 0);
                        $scope.set_product_image_attr(product_image, 'sizes', option_data.image_sizes, 0);
                        $scope.set_product_image_attr(product_image, 'title', option_data.image_title, 0);
                        $scope.set_product_image_attr(product_image, 'alt', option_data.image_alt, 0);
                        $scope.set_product_image_attr(product_image, 'data-src', option_data.full_src, 0);
                        $scope.set_product_image_attr(product_image, 'data-large_image', option_data.full_src, 0);
                        $scope.set_product_image_attr(product_image, 'data-large_image_width', option_data.full_src_w, 0);
                        $scope.set_product_image_attr(product_image, 'data-large_image_height', option_data.full_src_h, 0);

                        $scope.set_product_image_attr(product_image, 'alt', option_data.alt, 0);
                        $scope.set_product_image_attr(product_image_wrap, 'data-thumb', option_data.image_link, 1);
                    }
                    if (gallery_image.length) {
                        $scope.set_product_image_attr(gallery_image, 'src', option_data.image_link, 2);
                    }
                    if (product_link.length) {
                        $scope.set_product_image_attr(product_link, 'href', option_data.full_src, 3);
                        $scope.set_product_image_attr(product_link, 'title', option_data.image_caption, 3);
                    }
                    $scope.init_product_gallery_and_zoom();
                }
            };
            $scope.change_product_image_without_field = function(option) {
                var product_element = jQuery('#product-' + $scope.product_id);
                var product_image = product_element.find('.woocommerce-product-gallery__image:not(.clone), .woocommerce-product-gallery__image--placeholder:not(.clone)').eq(0).find('.wp-post-image').first();
                if (product_image.length === 0) {
                    product_image = product_element.find("a.woocommerce-main-image img, img.woocommerce-main-image,a img").not('.thumbnails img,.product_list_widget img').first();
                }
                if (jQuery(product_image).length > 1) {
                    product_image = jQuery(product_image).first();
                }
                var gallery_image = product_element.find('.flex-control-nav li:eq(0) img'),
                    gallery_wrapper = product_element.find('.woocommerce-product-gallery__wrapper '),
                    product_image_wrap = gallery_wrapper.find('.woocommerce-product-gallery__image, .woocommerce-product-gallery__image--placeholder').eq(0),
                    product_link = product_image.closest('a');
                if (product_image.length) {
                    $scope.set_product_image_attr(product_image, 'src', option.image_link, 0);
                    $scope.set_product_image_attr(product_image, 'srcset', option.image_srcset, 0);
                    $scope.set_product_image_attr(product_image, 'sizes', option.image_sizes, 0);
                    $scope.set_product_image_attr(product_image, 'title', option.image_title, 0);
                    $scope.set_product_image_attr(product_image, 'alt', option.image_alt, 0);
                    $scope.set_product_image_attr(product_image, 'data-src', option.full_src, 0);
                    $scope.set_product_image_attr(product_image, 'data-large_image', option.full_src, 0);
                    $scope.set_product_image_attr(product_image, 'data-large_image_width', option.full_src_w, 0);
                    $scope.set_product_image_attr(product_image, 'data-large_image_height', option.full_src_h, 0);

                    $scope.set_product_image_attr(product_image, 'alt', option.alt, 0);
                    $scope.set_product_image_attr(product_image_wrap, 'data-thumb', option.image_link, 1);
                }
                if (gallery_image.length) {
                    $scope.set_product_image_attr(gallery_image, 'src', option.image_link, 2);
                }
                if (product_link.length) {
                    $scope.set_product_image_attr(product_link, 'href', option.full_src, 3);
                    $scope.set_product_image_attr(product_link, 'title', option.image_caption, 3);
                }
                $scope.init_product_gallery_and_zoom();
            };
            $scope.init_product_gallery_and_zoom = function() {
                var product_element = jQuery('#product-' + $scope.product_id);
                var gallery_element = product_element.find('.woocommerce-product-gallery');
                if (gallery_element.length && gallery_element.data('flexslider')) {
                    $timeout(function() {
                        gallery_element.flexslider(0);
                    }, 100);
                    window.setTimeout(function() {
                        gallery_element.trigger('woocommerce_gallery_init_zoom');
                        jQuery(window).trigger('resize');
                    }, 10);
                }
                var zoom_images = product_element.find('.woocommerce-product-gallery__image'),
                    galleryWidth = product_element.find('.woocommerce-product-gallery--with-images').width(),
                    zoomEnabled = false;
                jQuery(zoom_images).each(function(index, target) {
                    var image = jQuery(target).find('img.wp-post-image');
                    if (image.attr('data-large_image_width') > galleryWidth) {
                        zoomEnabled = true;
                        return false;
                    }
                });
                if (zoomEnabled) {
                    var zoom_options = {
                        touch: false
                    };
                    if ('ontouchstart' in window) {
                        zoom_options.on = 'click';
                    }
                    zoom_images.trigger('zoom.destroy');
                    if (typeof zoom_images.zoom == 'function') zoom_images.zoom(zoom_options);
                } else {
                    zoom_images.trigger('zoom.destroy');
                }
            };
            $scope.debug = function() {
                jQuery('input[name="quantity"]').val(100);
                jQuery('input[name="quantity"]').trigger('change.nbo');
            };
            $scope.get_field = function(field_id) {
                var _field = null;
                angular.forEach($scope.fields, function(field) {
                    if (field.id == field_id) _field = field;
                });
                return _field;
            };
            // $scope.get_field_index = function(field_id) {
            //     var _index = null;
            //     angular.forEach($scope.fields, function(field, index) {
            //         if (field.id == field_id) _index = index;
            //     });
            //     return _index;
            // };
            $scope.check_depend = function(field_id) {
                if (angular.isUndefined($scope.nbd_fields[field_id])) return;
                var field = $scope.get_field(field_id),
                    check = [];
                $scope.nbd_fields[field_id].enable = true;
                return $scope.nbd_fields[field_id].enable;
            };
            $scope.checkAttributeStatus = function(field_id, attr_index, sub_attr_index) {
                var check = true,
                    checks = [];
                var origin_field = $scope.get_field(field_id),
                    currentOption = origin_field.general.attributes.options[attr_index],
                    option;
                $scope.status_fields[field_id][attr_index] = $scope.status_fields[field_id][attr_index] || {
                    sub_attributes: [],
                    enable: true
                };

                function assignCheck(check) {
                    if( typeof sub_attr_index != 'undefined' ){
                        $scope.status_fields[field_id][attr_index].sub_attributes = $scope.status_fields[field_id][attr_index].sub_attributes || [];
                        $scope.status_fields[field_id][attr_index].sub_attributes[sub_attr_index] = check;
                    }else{
                        $scope.status_fields[field_id][attr_index].enable = check;
                    }
                }
                if( typeof sub_attr_index != 'undefined' ){
                    option = currentOption.sub_attributes[sub_attr_index];
                }else{
                    option = currentOption;
                }
                assignCheck(check);
            };
            $scope.init = function() {
                $scope.current_dimensions = {};
                nbOption.status = true;
                $scope.nbd_fields = {};
                $scope.status_fields = {};
                $scope.basePrice = $scope.convert_wc_price_to_float($scope.price);
                $scope.total_price = 0;
                angular.forEach($scope.fields, function(field) {
                    if (field.general.enabled == 'y') {
                        $scope.nbd_fields[field.id] = {
                            title: field.general.title,
                            price: $scope.convert_to_wc_price(0),
                            required: field.general.required
                        };
                        if (field.general.data_type == 'i') {
                            if (field.general.input_type != 't' && field.general.input_type != 'a') {
                                if (field.general.input_type != 'u') {
                                    if (angular.isDefined(field.general.input_option.default)) {
                                        $scope.nbd_fields[field.id].value = field.general.input_option.default != '' ? field.general.input_option.default : 0;
                                    } else {
                                        $scope.nbd_fields[field.id].value = field.general.input_option.min != '' ? field.general.input_option.min : 0;
                                    }
                                }
                            }
                        } else {
                            if (field.general.attributes.options.length == 0) {
                                $scope.nbd_fields[field.id].value = '0';
                            } else {
                                $scope.nbd_fields[field.id].value = '0';
                                var selectedOp;
                                $scope.status_fields[field.id] = [];
                                angular.forEach(field.general.attributes.options, function(op, k) {
                                    if (op.selected == 'on') {
                                        $scope.nbd_fields[field.id].value = '' + k;
                                        selectedOp = op;
                                    }
                                    op.form_name = '';
                                    $scope.status_fields[field.id][k] = {
                                        enable: true
                                    };
                                });
                                if (!selectedOp) {
                                    selectedOp = field.general.attributes.options[0];
                                }
                            }
                        }
                    }
                });
                angular.forEach($scope.form_values, function(value, field_id) {
                    if (field_id) {
                        if (angular.isDefined(value['sub_value'])) {
                            $scope.nbd_fields[field_id].value = value['value'];
                            $scope.nbd_fields[field_id].sub_value = value['sub_value'];
                        } else if (angular.isDefined(value['value'])) {
                            $scope.nbd_fields[field_id].value = value['value'];
                        } else {
                            $scope.nbd_fields[field_id].value = value;
                        }
                    }
                });
                angular.forEach($scope.fields, function(field) {
                    $scope.check_depend(field.id);
                });
                $scope.check_valid();
                $timeout(function() {
                    jQuery('.nbd-option-field:first').removeClass('nbo-collapse');

                    if (angular.isDefined($scope.no_of_group) && $scope.no_of_group != 0) {
                        $scope.changeGroupPanel(null, 0);
                        $scope.initGroupTimeline();
                    }
                });
                jQuery(document).on('change_nbo_variations', function() {
                    $scope.upDateVaritionQty(NBSTORELLYPRODUCT.variations);
                });
            };
            $scope.mapOptions = function() {
                if (!$scope.variations_form) {
                    $scope.variations_form = jQuery('.variations_form');
                    $scope.variations_form_obj = {
                        calculator: null,
                        use_ajax: false,
                        swatches_xhr: null,
                        checked: false,
                        first: true
                    };
                    if ($scope.variations_form.length && $scope.variations_form.find('select.nbo-mapping-select').length) {
                        var getSelector = function(field_id) {
                            var field = $scope.get_field(field_id),
                                type = field.appearance.display_type,
                                selector = '';
                            switch (type) {
                                case 's':
                                    selector = '> .nbd-swatch-wrap input[type="radio"]';
                                    break;
                                case 'r':
                                    selector = '> .__nbd-radio-wrap input[type="radio"]';
                                    break;
                                case 'xl':
                                    selector = '> .nbd-xlabel-wrapper input[type="radio"]';
                                    break;
                                case 'ad':
                                    selector = '> div > select option';
                                    break;
                                case 'l':
                                    selector = '> .nbd-label-wrap input[type="radio"]';
                                    break;
                                default:
                                    selector = '> .__nbd-dropdown-wrap select option';
                                    break;
                            }
                            return selector;
                        };

                        var updateFieldStatus = function(current_options) {
                            var mustCheckValid = false;
                            $scope.variations_form.find('.variations select.nbo-mapping-select').each(function() {
                                var classList = jQuery(this).attr('class').split(/\s+/),
                                    field_id, optionWrap;
                                jQuery.each(classList, function(index, _class) {
                                    if (_class.indexOf("nbo_field_id-") > -1) {
                                        var arr = _class.split("-");
                                        field_id = arr[1];
                                    }
                                });
                                optionWrap = jQuery('.nbd-option-field[data-id="' + field_id + '"]');
                                var selector = getSelector(field_id);

                                var attribute_name = jQuery(this).data('attribute_name') || jQuery(this).attr('name'),
                                    avaiable_options = current_options[attribute_name];

                                jQuery(this).find('option').each(function(index, el) {
                                    var val = jQuery(el).val();
                                    if (index > 0) {
                                        var option = optionWrap.find('.pcpb-field-content ' + selector).eq(index - 1);
                                        if (!avaiable_options[val]) {
                                            option.addClass('nbo_map_disable').attr('disabled', 'disabled');
                                        } else {
                                            option.removeClass('nbo_map_disable').removeAttr('disabled');
                                        }
                                    }
                                });
                            });
                        };

                        var init = function() {
                            $scope.variations_form.find('.variations select.nbo-mapping-select').each(function() {
                                var classList = jQuery(this).attr('class').split(/\s+/),
                                    val = jQuery(this).val(),
                                    field_id, optionWrap;
                                jQuery.each(classList, function(index, _class) {
                                    if (_class.indexOf("nbo_field_id-") > -1) {
                                        var arr = _class.split("-");
                                        field_id = arr[1];
                                    }
                                });
                                optionWrap = jQuery('.nbd-option-field[data-id="' + field_id + '"]');
                                var selector = getSelector(field_id);
                                if (optionWrap.length) {
                                    jQuery(this).parents('tr').hide();
                                    if (val != '') {
                                        var index = jQuery(this).find("[value='" + val + "']").index();
                                        var option = optionWrap.find('.pcpb-field-content ' + selector).eq(index - 1);
                                    } else {
                                        option = optionWrap.find('.pcpb-field-content ' + selector).eq(0);
                                    }
                                    if (option.attr('disabled') == 'disabled') {
                                        var enabledOption = optionWrap.find('.pcpb-field-content ' + selector + ':enabled').eq(0);
                                        if (enabledOption.length) {
                                            enabledIndex = enabledOption.val();
                                            $scope.nbd_fields[field_id].value = enabledIndex;
                                            $scope.updateMapOptions(field_id);
                                        }
                                    }
                                } else {
                                    jQuery(this).show();
                                }
                            });
                            $scope.check_valid();
                            $scope.variations_form_obj.first = false;
                        };

                        $scope.variations_form.on('bind_calculator', function() {
                            var $product_variations = $scope.variations_form.data('product_variations');
                            $scope.variations_form_obj.use_ajax = $product_variations === false;

                            if ($scope.variations_form_obj.use_ajax && jQuery.fn.block) {
                                $scope.variations_form.block({
                                    message: null,
                                    overlayCSS: {
                                        background: '#fff',
                                        opacity: 0.6
                                    }
                                });
                            }

                            var attribute_keys = {};
                            $scope.variations_form.find('.variations select').each(function(index, el) {
                                var $current_attr_select = jQuery(el);
                                var current_attribute_name = $current_attr_select.data('attribute_name') || $current_attr_select.attr('name');
                                attribute_keys[current_attribute_name] = [];
                                var current_options = '';
                                current_options = $current_attr_select.find('option:gt(0)').get();
                                if (current_options.length) {
                                    for (var i = 0; i < current_options.length; i++) {
                                        var option = current_options[i];
                                        attribute_keys[current_attribute_name].push(jQuery(option).val());
                                    }
                                }
                            });

                            if ($scope.variations_form_obj.use_ajax) {
                                if ($scope.variations_form_obj.swatches_xhr) {
                                    $scope.variations_form_obj.swatches_xhr.abort();
                                }

                                var data = {
                                    product_id: $scope.product_id,
                                    action: 'nbo_get_product_variations'
                                };

                                $scope.variations_form_obj.swatches_xhr = jQuery.ajax({
                                    url: $scope.ajax_url,
                                    type: 'POST',
                                    data: data,
                                    success: function(response) {
                                        $scope.variations_form_obj.calculator = new nbo_variation_calculator(attribute_keys, response.data, null, null);
                                        if (jQuery.fn.unblock) {
                                            $scope.variations_form.unblock();
                                        }

                                        $scope.variations_form.trigger('woocommerce_variation_has_changed');
                                        if ($scope.variations_form_obj.first) {
                                            init();
                                        }
                                    }
                                });
                            } else {
                                $scope.variations_form_obj.calculator = new nbo_variation_calculator(attribute_keys, $product_variations, null, null);
                            }

                            $scope.variations_form.trigger('woocommerce_variation_has_changed');

                            if (!$scope.variations_form_obj.use_ajax) {
                                if ($scope.variations_form_obj.first) {
                                    init();
                                }
                            }
                        });

                        $scope.variations_form.on('reset_data', function() {
                                if ($scope.variations_form_obj.calculator == null) {
                                    return;
                                }

                                var current_options = $scope.variations_form_obj.calculator.get_current();
                                if (!$scope.variations_form_obj.checked) {
                                    updateFieldStatus(current_options);
                                    $scope.variations_form_obj.checked = true;
                                }
                            })
                            .on('woocommerce_variation_has_changed', function() {
                                if ($scope.variations_form_obj.calculator == null) {
                                    return;
                                }

                                $scope.variations_form.find('.variations select').each(function() {
                                    var attribute_name = jQuery(this).data('attribute_name') || jQuery(this).attr('name');
                                    $scope.variations_form_obj.calculator.set_selected(attribute_name, jQuery(this).val());
                                });

                                var current_options = $scope.variations_form_obj.calculator.get_current();
                                updateFieldStatus(current_options);

                                if ($scope.variations_form_obj.use_ajax) {
                                    $scope.variations_form.find('.nbo-default-select').each(function(index, element) {
                                        var $wc_select_box = jQuery(element);

                                        var attribute_name = $wc_select_box.data('attribute_name') || $wc_select_box.attr('name');
                                        var avaiable_options = current_options[attribute_name];

                                        $wc_select_box.find('option:gt(0)').removeClass('attached');
                                        $wc_select_box.find('option:gt(0)').removeClass('enabled');
                                        $wc_select_box.find('option:gt(0)').removeAttr('disabled');

                                        $wc_select_box.find('option:gt(0)').each(function(optindex, option_element) {
                                            if (!avaiable_options[jQuery(option_element).val()]) {
                                                jQuery(option_element).addClass('disabled', 'disabled');
                                            } else {
                                                jQuery(option_element).addClass('attached');
                                                jQuery(option_element).addClass('enabled');
                                            }
                                        });

                                        $wc_select_box.find('option:gt(0):not(.enabled)').attr('disabled', 'disabled');
                                    });
                                }
                            });

                        $scope.variations_form.trigger('bind_calculator');
                        $scope.variations_form.on('reload_product_variations', function() {
                            $scope.variations_form.trigger('woocommerce_variation_has_changed');
                            $scope.variations_form.trigger('bind_calculator');
                            $scope.variations_form.trigger('woocommerce_variation_has_changed');
                        });

                        $scope.variations_form.trigger('check_variations');
                    }
                }
            };
            $scope.updateMapOptions = function(field_id) {
                if (!$scope.variations_form) return;
                $timeout(function() {
                    var _class = "nbo_field_id-" + field_id,
                        index = parseInt($scope.nbd_fields[field_id].value);
                    if ($scope.variations_form.find('select.' + _class).length) {
                        $scope.variations_form.find('select.' + _class).find('option').eq(index + 1).prop("selected", "selected").change();
                    }
                });
            };
            $scope.upDateVaritionQty = function(variations) {
                jQuery.each(jQuery('.nbb-qty-field'), function(index, ip) {
                    jQuery(ip).val(variations[index].qty);
                });
            };
            $scope.reset_options = function() {
                $scope.init();
                if (angular.isDefined($scope.quantity)) $scope.change_quantity();
                jQuery(document).triggerHandler('reset_nbo_options');
            };
            $scope.custom_qty = {
                enable: false,
                value: !!$scope.quantity ? $scope.quantity : 1
            };
            var debounce_change_quantity = _debounce(function(event) {
                $scope.quantity = $scope.custom_qty.value;
                $scope.change_quantity();
            }, 300);
            $scope._change_quantity = function() {
                debounce_change_quantity();
            };
            $scope.disable_custom_qty = function() {
                $timeout(function() {
                    $scope.custom_qty = {
                        enable: false,
                        value: $scope.quantity
                    };
                });
            };
            $scope.change_quantity = function() {
                $timeout(function() {
                    jQuery('input[name="quantity"]').val($scope.quantity).trigger('change.nbo');
                });
            };
            $scope.select_all_variation = function($event) {
                var el = angular.element($event.target),
                    list = el.parents('table.nbo-bulk-variation').find('tbody input.nbo-bulk-checkbox'),
                    check = el.prop('checked') ? true : false;
                jQuery.each(list, function() {
                    jQuery(this).prop('checked', check);
                });
            };
            $scope.add_variaion = function($event) {
                var el = angular.element($event.target),
                    tb = el.parents('table.nbo-bulk-variation').find('tbody'),
                    row = tb.find('tr').last().clone();
                tb.append(row);
                $scope.calculate_bulk_total_price();
            };
            $scope.delete_variaions = function($event) {
                var el = angular.element($event.target),
                    tb = el.parents('table.nbo-bulk-variation').find('tbody');
                jQuery.each(tb.find('input.nbo-bulk-checkbox:checked'), function() {
                    if (tb.find('tr').length > 1) jQuery(this).parents('tr').remove();
                });
                el.parents('table.nbo-bulk-variation').find('input.nbo-bulk-checkbox').prop('checked', false);
                $scope.calculate_bulk_total_price();
            };
            $scope.convert_to_wc_price = function(price, required) {
                var precision = parseInt(nbds_frontend.wc_currency_format_num_decimals);
                if (price.toFixed(precision) == 0 && angular.isUndefined(required)) return '';
                return accounting.formatMoney(price, {
                    symbol: nbds_frontend.currency_format_symbol,
                    decimal: nbds_frontend.currency_format_decimal_sep,
                    thousand: nbds_frontend.currency_format_thousand_sep,
                    precision: angular.isUndefined(required) ? nbds_frontend.wc_currency_format_num_decimals : nbds_frontend.currency_format_num_decimals,
                    format: nbds_frontend.currency_format
                });
            };
            $scope.convert_wc_price_to_float = function(price) {
                return $scope.validate_float(price);
                var c = jQuery.trim(nbds_frontend.currency_format_thousand_sep).toString(),
                    d = jQuery.trim(nbds_frontend.currency_format_decimal_sep).toString();
                return price = price.replace(/ /g, ""), price = "." === c ? price.replace(/\./g, "") : price.replace(new RegExp(c, "g"), ""), price = price.replace(d, "."), price = parseFloat(price);
            };
            $scope.validate_int = function(input) {
                var output = parseInt(input);
                if (isNaN(output)) output = 0;
                if (output < 0) output = 0;
                return output;
            };
            $scope.shorten = function(num) {
                num += '';
                num = num.replace(/(\.\d*?)0{5,}\d+$/, '$1');
                if (/(\.\d*?)9{5,}\d+$/.test(num)) {
                    var tem = num.replace(/(\.\d*?)9{5,}\d+$/, '$1');
                    var decimals = tem.slice(tem.indexOf('.') + 1),
                        num_decimal = decimals.length;
                    if (num_decimal > 0) {
                        var new_decimals = decimals * 1;
                        new_decimals += 1;
                        tem = tem.replace(/(\d+\.)(\d+)/, '$1' + new_decimals);
                    } else if ((/\d+\.$/).test(tem)) {
                        tem = (tem.replace("\.", "") * 1) + 1;
                    }
                    return tem.replace(/(\.\d*?)0{5,}\d+$/, '$1') * 1;
                }
                return num * 1;
            };
            $scope.validate_float = function(input) {
                var output = parseFloat(input);
                if (isNaN(output)) output = 0;
                return output;
            };
            $scope.calculate_price = function() {
                $scope.basePrice = $scope.price;
                if (this.type == 'variable') {
                    var variation_id = jQuery('input[name="variation_id"], input.variation_id').val();
                    $scope.basePrice = (variation_id != '' && variation_id != 0) ? $scope.variations[variation_id] : $scope.basePrice;
                }
                $scope.basePrice = $scope.convert_wc_price_to_float($scope.basePrice);
                $scope.total_price = 0;
                $scope.cart_item_fee = {
                    enable: false,
                    value: 0
                };
                var qty = 0;
                if ($scope.is_sold_individually == 1) {
                    qty = 1;
                } else {
                    qty = $scope.validate_int(jQuery('input[name="quantity"]').val());
                }
                $scope._qty = qty;
                var xfactor = 1,
                    line_price = {
                        fixed: 0,
                        percent: 0,
                        xfactor: 1
                    },
                    fixed_amount = 0;
                angular.forEach($scope.nbd_fields, function(field, field_id) {
                    if (field.enable) {
                        var origin_field = $scope.get_field(field_id);
                        var factor = null;
                        if (origin_field.general.data_type == 'i') {
                            factor = origin_field.general.price;
                            if (origin_field.general.input_type == 'u' && (angular.isUndefined(field.value) || field.value == "")) {
                                factor = 0;
                            }
                        } else {
                            var option = origin_field.general.attributes.options[field.value];
                            if (option) {
                                var option_price = option.price;
                                factor = $scope.validate_float(option_price[0]);
                                if (angular.isDefined(option.enable_subattr) && option.enable_subattr == 'on') {
                                    if (angular.isDefined(option.sub_attributes) && option.sub_attributes.length > 0) {
                                        soption_price = option.sub_attributes[field.sub_value].price;
                                        factor += $scope.validate_float(soption_price[0]);
                                    }
                                }
                            }
                        }

                        factor = $scope.validate_float(factor);
                        field.is_pp = 0;
                        var _factor = factor;
                        if ($scope.is_independent_qty(origin_field)) {
                            factor = 0;
                            field.ind_qty = true;
                        }
                        if ($scope.is_fixed_amount(origin_field)) {
                            factor /= qty;
                        }
                        switch (origin_field.general.price_type) {
                            case 'f':
                                field.price_val = _factor;
                                field.price = $scope.convert_to_wc_price(_factor);
                                $scope.total_price += factor;
                                if ($scope.is_independent_qty(origin_field)) {
                                    line_price.fixed += _factor;
                                }
                                break;
                            case 'p':
                                field.price_val = $scope.basePrice * _factor / 100;
                                field.price = $scope.convert_to_wc_price(field.price_val);
                                $scope.total_price += ($scope.basePrice * factor / 100);
                                if ($scope.is_independent_qty(origin_field)) {
                                    line_price.percent += _factor;
                                }
                                break;
                            case 'p+':
                                field.price = factor / 100;
                                field._price = _factor / 100;
                                xfactor *= (1 + factor / 100);
                                field.is_pp = 1;
                                if ($scope.is_independent_qty(origin_field)) {
                                    line_price.xfactor *= (1 + _factor / 100);
                                }
                                break;
                            case 'c':
                                var current_value = $scope.validate_int(field.value);
                                field.price_val = _factor * current_value;
                                field.price = $scope.convert_to_wc_price(field.price_val);
                                $scope.total_price += factor * current_value;
                                if ($scope.is_independent_qty(origin_field)) {
                                    line_price.fixed += field.price_val;
                                }
                                break;
                            case 'cp':
                                field.price_val = _factor * $scope.validate_int(field.value.length);
                                field.price = $scope.convert_to_wc_price(field.price_val);
                                $scope.total_price += factor * $scope.validate_int(field.value.length);
                                if ($scope.is_independent_qty(origin_field)) {
                                    line_price.fixed += field.price_val;
                                }
                                break;
                        }
                        if ($scope.is_fixed_amount(origin_field)) {
                            field.fixed_amount = true;
                        }

                    }
                });
                $scope.total_price += (($scope.basePrice + $scope.total_price) * (xfactor - 1));
                angular.forEach($scope.nbd_fields, function(field) {
                    if (field.is_pp == 1) {
                        field.price_val = field.price * ($scope.basePrice + $scope.total_price) / (field.price + 1);
                        field.price = $scope.convert_to_wc_price(field.price_val);
                    }
                });
                $scope.final_price = $scope.total_price + $scope.basePrice;
                $scope.final_price = $scope.final_price > 0 ? $scope.final_price : 0;
                $scope.total_cart_price = $scope.final_price * qty;
                if (line_price.fixed != 0 || line_price.xfactor != 1 || line_price.percent != 0) {
                    $scope.cart_item_fee.enable = true;
                    var _total_cart_price = $scope.total_cart_price;
                    if (line_price.fixed != 0) {
                        $scope.total_cart_price += line_price.fixed;
                    }
                    if (line_price.percent != 0) {
                        $scope.total_cart_price += ($scope.basePrice * line_price.percent / 100);
                    }
                    if (line_price.xfactor != 1) {
                        $scope.total_cart_price += ($scope.total_cart_price * (line_price.xfactor - 1));
                        angular.forEach($scope.nbd_fields, function(field) {
                            if (field.is_pp == 1 && field.ind_qty) {
                                field.price = $scope.convert_to_wc_price(field._price * $scope.total_cart_price / (field._price + 1));
                            }
                        });
                    }
                    $scope.cart_item_fee.value = $scope.total_cart_price - _total_cart_price;
                    $scope.cart_item_fee.value = $scope.convert_to_wc_price($scope.cart_item_fee.value);
                }
                $scope.total_cart_item_price_num = $scope.total_cart_price;
                $scope.total_cart_price = $scope.convert_to_wc_price($scope.total_cart_price);
                $scope.final_price = $scope.convert_to_wc_price($scope.final_price, true);
                $scope.total_price = $scope.convert_to_wc_price($scope.total_price, true);
            };
            $scope.is_independent_qty = function(field) {
                if (angular.isDefined(field.general.depend_qty) && field.general.depend_qty == 'n') {
                    return true;
                } else {
                    return false;
                }
            };
            $scope.is_fixed_amount = function(field) {
                if (angular.isDefined(field.general.depend_qty) && field.general.depend_qty == 'n2') {
                    return true;
                } else {
                    return false;
                }
            };
            $scope.eval_price = function(formula, origin_field, qty, fields) {
                if (!formula) return 0;

                var price = 0,
                    area = $scope.calculate_product_area();

                formula = formula.replace(/{quantity}/g, qty);
                formula = formula.replace(/{price}/g, $scope.basePrice);
                formula = formula.replace(/{area}/g, area);
                formula = formula.replace(/{this.value}/g, fields[origin_field.id].value);
                formula = formula.replace(/{this.value_length}/g, fields[origin_field.id].value.length);

                if (formula.match(/\{(\s)*?field\.([^}]*)}/)) {
                    var matches = formula.match(/\{(\s)*?field\.([^}]*)}/g),
                        pos, reg, field_id, type, val;
                    matches.forEach(function(field) {
                        match = field.match(/\{(\s)*?field\.([^}]*)}/);
                        if (undefined !== match[2] && "string" == typeof match[2]) {
                            pos = match[2].lastIndexOf(".");
                            val = 0;

                            if (pos !== -1) {
                                field_id = match[2].substr(0, pos);
                                type = match[2].substr(pos + 1);

                                switch (type) {
                                    case 'price':
                                        val = angular.isDefined(fields[field_id].price_val) ? fields[field_id].price_val : 0;
                                        break;
                                    case 'value':
                                        val = fields[field_id].value;
                                        break;
                                    case 'value_length':
                                        val = fields[field_id].value.length;
                                        break;
                                }
                            }

                            reg = new RegExp(match[0]);
                            formula = formula.replace(reg, val + '');
                        }
                    });
                }

                try {
                    price = mexp.eval(formula);
                } catch (e) {
                    price = 0;
                }

                return price;
            };
            $scope.toggle_group = function($event) {
                jQuery($event.target).parents('.nbo-group-body').toggleClass('nbo-collapse');
                jQuery($event.target).parents('.nbo-group-type2-wrap').toggleClass('nbo-collapse');
            };
            $scope.toggle_float_summary = function() {
                jQuery('.nbo-float-summary').toggleClass('nbo-collapse');
            };
            $scope.toggle_field = function($event) {
                jQuery($event.target).parents('.nbd-option-field').toggleClass('nbo-collapse');
            };
            $scope.select_adv_attr = function(field_id, attr_index) {
                $scope.nbd_fields[field_id].value = attr_index;
                $scope.check_valid();
            };
            $scope.select_adv_subattr = function(field_id, attr_index, subattr_index) {
                $scope.nbd_fields[field_id].value = attr_index;
                $scope.nbd_fields[field_id].sub_value = subattr_index;
                $scope.check_valid();
            };
            $scope.changeGroupPanel = function($event, command) {
                $timeout(function() {
                    var wrapper = jQuery('#' + nbOption.crtlId).find('.nbo-fields-wrapper');
                    if (command == 'prev') {
                        if ($scope.current_group_panel > 0) $scope.current_group_panel--;
                    } else if (command == 'next') {
                        if ($scope.current_group_panel < ($scope.no_of_group - 1)) $scope.current_group_panel++;
                    } else {
                        if (command >= 0 && command < $scope.no_of_group) {
                            $scope.current_group_panel = command;
                        }
                    }
                    var height = wrapper.find('.nbo-group-wrap:nth(' + ($scope.current_group_panel) + ')').outerHeight();
                    if (wrapper.find('.nbo-group-type2-wrap').length) {
                        height = wrapper.find('.nbo-group-type2-wrap:nth(' + ($scope.current_group_panel) + ')').outerHeight();
                    }
                    wrapper.find('.nbo_group_panel_wrap').css('height', (height + 15) + 'px');
                });
            };
            $scope.groupPageInit = false;
            $scope.currentGroupPage = 0;
            $scope.totalGroupPage = 1;
            $scope.groupTimeLineTranslate = '0%';
            $scope.initGroupTimeline = function() {
                $timeout(function() {
                    var wrapper = jQuery('#' + nbOption.crtlId).find('.nbo-fields-wrapper'),
                        timelineCon = wrapper.find('.nbo-group-timeline-wrap'),
                        timelineLine = wrapper.find('.nbo-group-timeline-line'),
                        containerWidth = timelineCon.innerWidth(),
                        timelineWidth = timelineLine.outerWidth();

                    if (timelineWidth > containerWidth) {
                        $scope.totalGroupPage++;
                        $timeout(function() {
                            containerWidth = timelineCon.innerWidth(),
                                timelineWidth = timelineLine.outerWidth();

                            $scope.totalGroupPage = Math.ceil(timelineWidth / containerWidth);
                            $scope.changeGroupPage(null, 0);
                        });
                    } else {
                        $scope.changeGroupPage(null, 0);
                    }
                });
            };
            $scope.changeGroupPage = function($event, command) {
                if (command == 'prev') {
                    if ($scope.currentGroupPage > 0) $scope.currentGroupPage--;
                } else if (command == 'next') {
                    if ($scope.currentGroupPage < ($scope.totalGroupPage - 1)) $scope.currentGroupPage++;
                } else {
                    if (command >= 0 && command < $scope.totalGroupPage) {
                        $scope.currentGroupPage = command;
                    }
                }
                if ($scope.currentGroupPage == ($scope.totalGroupPage - 1)) {
                    var wrapper = jQuery('#' + nbOption.crtlId).find('.nbo-fields-wrapper');
                    timelineCon = wrapper.find('.nbo-group-timeline-wrap'),
                        timelineLine = wrapper.find('.nbo-group-timeline-line'),
                        containerWidth = timelineCon.innerWidth(),
                        timelineWidth = timelineLine.outerWidth();
                    if (containerWidth < timelineWidth) $scope.groupTimeLineTranslate = (containerWidth - timelineWidth) + 'px';
                } else {
                    $scope.groupTimeLineTranslate = -$scope.currentGroupPage * 100 / $scope.totalGroupPage + '%';
                }
            };
            $scope.update_app = function() {
                if ($scope.$root.$$phase !== "$apply" && $scope.$root.$$phase !== "$digest") $scope.$apply();
            };
            $scope.init();
        }]).directive('stringToNumber', function() {
            return {
                require: 'ngModel',
                link: function(scope, element, attrs, ngModel) {
                    ngModel.$parsers.push(function(value) {
                        if (value === null) value = '';
                        return '' + value;
                    });
                    ngModel.$formatters.push(function(value) {
                        return parseFloat(value);
                    });
                }
            };
        }).directive('convertToNumber', function() {
            return {
                require: 'ngModel',
                link: function(scope, element, attrs, ngModel) {
                    ngModel.$parsers.push(function(val) {
                        return val != null ? parseInt(val, 10) : null;
                    });
                    ngModel.$formatters.push(function(val) {
                        return val != null ? '' + val : null;
                    });
                }
            };
        }).directive('nboClickDebounce', function($timeout) {
            var delay = 500;
            return {
                restrict: 'A',
                priority: -1,
                link: function(scope, elem) {
                    var disabled = false;

                    function onClick(evt) {
                        if (disabled) {
                            evt.preventDefault();
                            evt.stopImmediatePropagation();
                        } else {
                            disabled = true;
                            $timeout(function() {
                                disabled = false;
                            }, delay, false);
                        }
                    }
                    scope.$on('$destroy', function() {
                        elem.off('click', onClick);
                    });
                    elem.on('click', onClick);
                }
            };
        }).directive('nbdHelpTip', function($timeout) {
            return {
                restrict: 'C',
                scope: {
                    position: '@position'
                },
                link: function(scope, element, attrs) {
                    var tiptip_args = {
                        'attribute': 'data-tip',
                        'fadeIn': 50,
                        'fadeOut': 50,
                        'delay': 200,
                        defaultPosition: scope.position ? scope.position : "top"
                    };
                    $timeout(function() {
                        jQuery(element).tipTip(tiptip_args);
                    }, 0);
                }
            };
        }).directive('nboAdvDropdown', function($timeout) {
            return {
                restrict: 'A',
                link: function(scope, element, attrs) {
                    $timeout(function() {
                        jQuery('body').click(function(event) {
                            jQuery.each(jQuery('.pcpb-field-ad-dropdown-wrap'), function(ind, el) {
                                var re_el = jQuery(el).find('.nbo-ad-result');
                                if (!(re_el.is(jQuery(event.target)) ||
                                        jQuery(event.target).parents('.nbo-ad-result').is(re_el) ||
                                        jQuery(event.target).is(jQuery(element).find('.nbo-ad-pseudo-sublist-toggle')))) {
                                    jQuery(el).removeClass('active');
                                    jQuery(el).find('.nbo-ad-pseudo-sublist-toggle').removeClass('nbo-rotate-180');
                                    jQuery(el).find('.nbo-ad-pseudo-sublist').removeClass('active');
                                }
                            });
                        });
                        jQuery(element).find('.nbo-ad-result').on('click', function() {
                            jQuery(element).toggleClass('active');
                        });
                        jQuery(element).find('.nbo-ad-pseudo-sublist-toggle').on('click', function(e) {
                            e.stopPropagation();
                            var sublist_el = jQuery(this).next('.nbo-ad-pseudo-sublist');
                            jQuery.each(jQuery(element).find('.nbo-ad-pseudo-sublist'), function() {
                                if (!jQuery(this).is(sublist_el)) {
                                    jQuery(this).removeClass('active');
                                    jQuery(this).prev('.nbo-ad-pseudo-sublist-toggle').removeClass('nbo-rotate-180');
                                }
                            });
                            jQuery(this).toggleClass('nbo-rotate-180');
                            sublist_el.toggleClass('active');
                        });
                    });
                }
            }
        }).directive('nboInputFile', function($timeout, $window) {
            return {
                restrict: 'A',
                require: 'ngModel',
                scope: {
                    fileChange: '&',
                    fieldId: '@fieldId',
                    types: '@types',
                    file: '@',
                    filename: '@',
                    uploaded: '@',
                    minsize: '@',
                    maxsize: '@'
                },
                link: function(scope, element, attrs, ctrl) {
                    if (scope.uploaded == 1) {
                        ClipboardEvent = $window.ClipboardEvent,
                            DataTransfer = $window.DataTransfer;
                        try {
                            var el = element[0];
                            if (ClipboardEvent || DataTransfer) {
                                var dT = new ClipboardEvent('').clipboardData || new DataTransfer();
                                dT.items.add(new File([scope.file], scope.filename));
                                el.files = dT.files;
                                onChange('init');
                            }
                        } catch (err) {
                            console.log(err);
                        }
                    }
                    element.on('change', onChange);
                    scope.$on('destroy', function() {
                        element.off('change', onChange);
                    });

                    function onChange(init) {
                        if (init != 'init') {
                            var file = element[0].files[0];
                            if (file) {
                                function resetInput() {
                                    ctrl.$setViewValue('');
                                    jQuery(element).val('');
                                    scope.fileChange();
                                    return false;
                                };
                                if (scope.maxsize != '') {
                                    var max_size = parseInt(scope.maxsize) * 1024 * 1024;
                                    if (max_size < file.size) {
                                        alert("<?php _e('Sorry, file is too big, max size: ', 'pc-product-builder'); ?>" + scope.maxsize + 'MB');
                                        resetInput();
                                    }
                                }
                                if (scope.minsize != '') {
                                    var minsize = parseInt(scope.minsize) * 1024 * 1024;
                                    if (minsize > file.size) {
                                        alert("<?php _e('Sorry, file is too small, min size: ', 'pc-product-builder'); ?>" + scope.minsize + 'MB');
                                        resetInput();
                                    }
                                }
                                if (scope.types != '') {
                                    var types = scope.types.replace(/ /g, '').split(','),
                                        filetype = file.type.toLowerCase(),
                                        checType = false;
                                    filetype = '';
                                    filetype = filetype != '' ? filetype : file.name.substring(file.name.lastIndexOf('.') + 1).toLowerCase();
                                    angular.forEach(types, function(type) {
                                        if (filetype.indexOf(type) > -1) {
                                            checType = true;
                                        }
                                    });
                                    if (!checType) {
                                        alert("<?php _e('Sorry, this file type is not permitted for security reasons. Only accept: ', 'pc-product-builder'); ?>" + scope.types);
                                        resetInput();
                                    }
                                }
                            }
                        }
                        if (element[0].files[0]) {
                            ctrl.$setViewValue(element[0].files[0]);
                        } else {
                            ctrl.$setViewValue('');
                        }
                        jQuery(element).parent('.pcpb-field-content').find('.nbd-upload-hidden').remove();
                        scope.fileChange();
                    }
                }
            };
        }).directive('nboDisabled', function($timeout) {
            return {
                restrict: 'A',
                scope: {
                    nboDisabled: '=',
                    nboDisabledType: '@'
                },
                link: function(scope, element, attrs) {
                    function updateStatus() {
                        if (scope.nboDisabled) {
                            if (scope.nboDisabledType == 'attr') {
                                jQuery(element).attr('disabled', true);
                            } else {
                                jQuery(element).addClass('nbo-disabled-wrap');
                            }
                        } else {
                            if (scope.nboDisabledType == 'attr') {
                                jQuery(element).removeAttr('disabled');
                            } else {
                                jQuery(element).removeClass('nbo-disabled-wrap');
                            }
                        }
                    };

                    $timeout(function() {
                        updateStatus();
                    });

                    scope.$watch('nboDisabled', function(newValue, oldValue) {
                        if (newValue != oldValue) {
                            $timeout(function() {
                                updateStatus();
                            });
                        }
                    }, true)
                }
            };
        }).filter('to_trusted', ['$sce', function($sce) {
            return function(text) {
                var div = document.createElement('div');
                text += '';
                div.innerHTML = text;
                return $sce.trustAsHtml(div.textContent);
            };
        }]).filter('svg_trusted', ['$sce', function($sce) {
            return function(text) {
                return $sce.trustAsHtml(text);
            };
        }]);
        <?php if (!$in_design_editor) : ?>
            var appEl = document.getElementById('<?php echo esc_attr($appid); ?>');
            angular.element(function() {
                angular.bootstrap(appEl, ['nboApp']);
            });
        <?php endif; ?>
        jQuery(document).on('update_pcpb_options_from_builder', function(e, data) {
            var $scope = angular.element(document.getElementById(nbOption.crtlId)).scope();
            angular.forEach(data.nbd_fields, function(nbd_field, field_id) {
                $scope.nbd_fields[field_id].value = nbd_field.value;
                $scope.nbd_fields[field_id].sub_value = nbd_field.sub_value;
            });
            $scope.check_valid(true, true);
        });
        jQuery(document).on('update_product_image_from_builder', function(e, data) {
            var $scope = angular.element(document.getElementById(nbOption.crtlId)).scope();
            $scope.change_product_image_without_field(data);
        });
        jQuery(document).on('update_nbo_options_from_advenced_upload', function(e, data) {
            var $scope = angular.element(document.getElementById(nbOption.crtlId)).scope();
            angular.forEach(data.options, function(option) {
                $scope.nbd_fields[option.field_id].value = option.value;
            });
            $scope.check_valid(true, true);
        });
    </script>
</div>