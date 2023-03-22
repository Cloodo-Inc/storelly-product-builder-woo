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
    'nbdesigner_hide_add_cart_until_form_filled'    =>  'yes'
);

$prefix             = '';
$style_class        = 'nbo-style-1';

$currentDir = realpath(dirname(__FILE__));

?>
<div class="nbo-wrapper <?php if ($is_wqv) echo 'nbd-option-in-wqv'; ?> <?php echo 'wrapper-type-' . $display_type; ?>">
    <div class="nbd-option-wrapper" id="<?php echo $appid; ?>">
        <div ng-controller="optionCtrl" ng-form="nboForm" id="nbo-ctrl-<?php echo $appid; ?>" ng-cloak>
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
                        if ($field['nbpb_type'] == 'nbpb_text' || $field['nbpb_type'] == 'nbpb_image') {
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
                    <?php if ($in_design_editor) : ?>
                        <a ng-class="printingOptionsAvailable ? '' : 'nbd-disabled'" class="nbd-button nbo-apply" ng-click="applyOptions()">{{settings.task2 == '' ? "<?php _e('Apply options', 'web-to-print-online-designer'); ?>" : "<?php _e('Start design', 'web-to-print-online-designer'); ?>" }}</a>
                    <?php endif; ?>
                    <?php if ($num_visible_field > 0) : ?>
                        <a class="button nbd-button" ng-click="reset_options()"><?php _e('Clear selection', 'web-to-print-online-designer'); ?></a>
                    <?php endif; ?>
                </div>
                <input type="hidden" value="<?php echo $product_id; ?>" name="nbo-add-to-cart" />
                <p ng-if="!valid_form" class="nbd-invalid-form"><?php _e('Please check invalid fields and quantity input or choose a different combination!', 'web-to-print-online-designer'); ?></p>
            </div>
            <div class="nbo-summary-wrapper">
                <div ng-if="valid_form" class="nbo-table-summary-wrap <?php echo ($style_class); ?>">
                    <p class="nbo-summary-title" ng-init="showNboSummary = true">
                        <b><?php esc_html_e('Summary options', 'web-to-print-online-designer'); ?></b>
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
                                    <br ng-if="field.ind_qty" /><small ng-if="field.ind_qty && field.price != ''"> <?php esc_html_e('( cart fee )', 'web-to-print-online-designer'); ?></small>
                                    <br ng-if="field.fixed_amount" /><small ng-if="field.fixed_amount && field.price != ''"> <?php esc_html_e('( for all items )', 'web-to-print-online-designer'); ?></small>
                                </td>
                                <td ng-bind-html="field.price | to_trusted"></td>
                            </tr>
                        </tbody>
                        <tfoot style="border-top: 1px solid #404762;">
                            <tr>
                                <td><b><?php esc_html_e('Options price', 'web-to-print-online-designer'); ?></b></td>
                                <td><span id="nbd-option-total"><span ng-bind-html="total_price | to_trusted"></span> / <?php esc_html_e('1 item', 'web-to-print-online-designer'); ?></span></td>
                            </tr>
                            <tr>
                                <td><b><?php esc_html_e('Quantity Discount', 'web-to-print-online-designer'); ?></b></td>
                                <td><span id="nbd-option-total"><span ng-bind-html="discount_by_qty | to_trusted"></span> / <?php esc_html_e('1 item', 'web-to-print-online-designer'); ?></span></td>
                            </tr>
                            <tr class="nbo-final-price">
                                <td><b><?php esc_html_e('Final price', 'web-to-print-online-designer'); ?></b></td>
                                <td>
                                    <span id="nbd-option-total">
                                        <span ng-hide="_qty == 1" ng-bind-html="final_price | to_trusted"></span><span ng-show="_qty == 1" ng-bind-html="total_cart_price | to_trusted"></span> / <?php esc_html_e('1 item', 'web-to-print-online-designer'); ?>
                                    </span>
                                </td>
                            </tr>
                            <tr class="nbo-final-price" ng-if="cart_item_fee.enable">
                                <td><b><?php esc_html_e('Cart item fee', 'web-to-print-online-designer'); ?></b></td>
                                <td><span id="nbd-option-total"><span ng-bind-html="cart_item_fee.value | to_trusted"></span> / <?php esc_html_e('all items', 'web-to-print-online-designer'); ?></span></td>
                            </tr>
                            <tr class="nbo-final-price nbo-total-price" ng-if="_qty > 1">
                                <td><b><?php esc_html_e('Subtotal price', 'web-to-print-online-designer'); ?></b></td>
                                <td><span id="nbd-option-total"><span ng-bind-html="total_cart_price | to_trusted"></span> / {{_qty}} <?php esc_html_e('items', 'web-to-print-online-designer'); ?></span></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <script type="text/javascript">
        (function($) {
            $.fn.tipTip = function(options) {
                var defaults = {
                    activation: "hover",
                    keepAlive: false,
                    maxWidth: "200px",
                    edgeOffset: 3,
                    defaultPosition: "bottom",
                    delay: 400,
                    fadeIn: 200,
                    fadeOut: 200,
                    attribute: "title",
                    content: false, // HTML or String to fill TipTIp with
                    enter: function() {},
                    exit: function() {}
                };
                var opts = $.extend(defaults, options);

                // Setup tip tip elements and render them to the DOM
                if ($("#tiptip_holder").length <= 0) {
                    var tiptip_holder = $('<div id="tiptip_holder" style="max-width:' + opts.maxWidth + ';"></div>');
                    var tiptip_content = $('<div id="tiptip_content"></div>');
                    var tiptip_arrow = $('<div id="tiptip_arrow"></div>');
                    $("body").append(tiptip_holder.html(tiptip_content).prepend(tiptip_arrow.html('<div id="tiptip_arrow_inner"></div>')));
                } else {
                    var tiptip_holder = $("#tiptip_holder");
                    var tiptip_content = $("#tiptip_content");
                    var tiptip_arrow = $("#tiptip_arrow");
                }

                return this.each(function() {
                    var org_elem = $(this);
                    if (opts.content) {
                        var org_title = opts.content;
                    } else {
                        var org_title = org_elem.attr(opts.attribute);
                    }
                    if (org_title != "") {
                        if (!opts.content) {
                            org_elem.removeAttr(opts.attribute); //remove original Attribute
                        }
                        var timeout = false;

                        if (opts.activation == "hover") {
                            org_elem.hover(function() {
                                active_tiptip();
                            }, function() {
                                if (!opts.keepAlive) {
                                    deactive_tiptip();
                                }
                            });
                            if (opts.keepAlive) {
                                tiptip_holder.hover(function() {}, function() {
                                    deactive_tiptip();
                                });
                            }
                        } else if (opts.activation == "focus") {
                            org_elem.focus(function() {
                                active_tiptip();
                            }).blur(function() {
                                deactive_tiptip();
                            });
                        } else if (opts.activation == "click") {
                            org_elem.click(function() {
                                active_tiptip();
                                return false;
                            }).hover(function() {}, function() {
                                if (!opts.keepAlive) {
                                    deactive_tiptip();
                                }
                            });
                            if (opts.keepAlive) {
                                tiptip_holder.hover(function() {}, function() {
                                    deactive_tiptip();
                                });
                            }
                        }

                        function active_tiptip() {
                            opts.enter.call(this);
                            tiptip_content.html(org_title);
                            tiptip_holder.hide().removeAttr("class").css("margin", "0");
                            tiptip_arrow.removeAttr("style");

                            var top = parseInt(org_elem.offset()['top']);
                            var left = parseInt(org_elem.offset()['left']);
                            var org_width = parseInt(org_elem.outerWidth());
                            var org_height = parseInt(org_elem.outerHeight());
                            var tip_w = tiptip_holder.outerWidth();
                            var tip_h = tiptip_holder.outerHeight();
                            var w_compare = Math.round((org_width - tip_w) / 2);
                            var h_compare = Math.round((org_height - tip_h) / 2);
                            var marg_left = Math.round(left + w_compare);
                            var marg_top = Math.round(top + org_height + opts.edgeOffset);
                            var t_class = "";
                            var arrow_top = "";
                            var arrow_left = Math.round(tip_w - 12) / 2;

                            if (opts.defaultPosition == "bottom") {
                                t_class = "_bottom";
                            } else if (opts.defaultPosition == "top") {
                                t_class = "_top";
                            } else if (opts.defaultPosition == "left") {
                                t_class = "_left";
                            } else if (opts.defaultPosition == "right") {
                                t_class = "_right";
                            }

                            var right_compare = (w_compare + left) < parseInt($(window).scrollLeft());
                            var left_compare = (tip_w + left) > parseInt($(window).width());

                            if ((right_compare && w_compare < 0) || (t_class == "_right" && !left_compare) || (t_class == "_left" && left < (tip_w + opts.edgeOffset + 5))) {
                                t_class = "_right";
                                arrow_top = Math.round(tip_h - 13) / 2;
                                arrow_left = -12;
                                marg_left = Math.round(left + org_width + opts.edgeOffset);
                                marg_top = Math.round(top + h_compare);
                            } else if ((left_compare && w_compare < 0) || (t_class == "_left" && !right_compare)) {
                                t_class = "_left";
                                arrow_top = Math.round(tip_h - 13) / 2;
                                arrow_left = Math.round(tip_w);
                                marg_left = Math.round(left - (tip_w + opts.edgeOffset + 5));
                                marg_top = Math.round(top + h_compare);
                            }

                            var top_compare = (top + org_height + opts.edgeOffset + tip_h + 8) > parseInt($(window).height() + $(window).scrollTop());
                            var bottom_compare = ((top + org_height) - (opts.edgeOffset + tip_h + 8)) < 0;

                            if (top_compare || (t_class == "_bottom" && top_compare) || (t_class == "_top" && !bottom_compare)) {
                                if (t_class == "_top" || t_class == "_bottom") {
                                    t_class = "_top";
                                } else {
                                    t_class = t_class + "_top";
                                }
                                arrow_top = tip_h;
                                marg_top = Math.round(top - (tip_h + 5 + opts.edgeOffset));
                            } else if (bottom_compare | (t_class == "_top" && bottom_compare) || (t_class == "_bottom" && !top_compare)) {
                                if (t_class == "_top" || t_class == "_bottom") {
                                    t_class = "_bottom";
                                } else {
                                    t_class = t_class + "_bottom";
                                }
                                arrow_top = -12;
                                marg_top = Math.round(top + org_height + opts.edgeOffset);
                            }

                            if (t_class == "_right_top" || t_class == "_left_top") {
                                marg_top = marg_top + 5;
                            } else if (t_class == "_right_bottom" || t_class == "_left_bottom") {
                                marg_top = marg_top - 5;
                            }
                            if (t_class == "_left_top" || t_class == "_left_bottom") {
                                marg_left = marg_left + 5;
                            }
                            tiptip_arrow.css({
                                "margin-left": arrow_left + "px",
                                "margin-top": arrow_top + "px"
                            });
                            tiptip_holder.css({
                                "margin-left": marg_left + "px",
                                "margin-top": marg_top + "px"
                            }).attr("class", "tip" + t_class);

                            if (timeout) {
                                clearTimeout(timeout);
                            }
                            timeout = setTimeout(function() {
                                tiptip_holder.stop(true, true).fadeIn(opts.fadeIn);
                            }, opts.delay);
                        }

                        function deactive_tiptip() {
                            opts.exit.call(this);
                            if (timeout) {
                                clearTimeout(timeout);
                            }
                            tiptip_holder.fadeOut(opts.fadeOut);
                        }
                    }
                });
            }
        })(jQuery);

        var in_quick_view = <?php echo $in_quick_view ? 1 : 0; ?>;
        nbds_frontend = <?php echo json_encode($nbds_frontend); ?>;
        var nbOption = {
            status: false,
            initialed: false,
            options: <?php echo json_encode($options); ?>,
            nbd_fields: {},
            odOption: {},
            extraOdOption: {},
            lastOdOption: {},
            lastExtraOdOption: {},
            crtlId: 'nbo-ctrl-<?php echo $appid; ?>',
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
                var scope = angular.element(document.getElementById("nbo-ctrl-<?php echo $appid; ?>")).scope();
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
            $scope.product_id = <?php echo $product_id; ?>;
            $scope.options = nbOption.options;
            $scope.fields = $scope.options["fields"];
            $scope.price = "<?php echo $price; ?>";
            $scope.type = "<?php echo $type; ?>";
            $scope.variations = <?php echo $variations; ?>;
            $scope.form_values = <?php echo json_encode($form_values); ?>;
            $scope.is_sold_individually = "<?php echo $is_sold_individually; ?>";
            $scope._quantity = "<?php echo $quantity; ?>";
            $scope.ajax_url = "<?php echo admin_url('admin-ajax.php'); ?>";
            $scope.valid_form = false;
            $scope.product_image = [];
            $scope.product_img = [];
            $scope.price_table = [];
            $scope.turnaround_matrix = [];
            $scope.has_price_matrix = false;
            $scope.can_start_design = true;
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
                                    if (origin_field.appearance.display_type == 'ad') {
                                        $scope.nbd_fields[field_id].form_name = '[value]';
                                    }
                                }
                                if (origin_field.general.attributes.options.length) {
                                    if (!$scope.status_fields[field_id][field.value].enable) {
                                        check[field_id] = false;
                                        field.valid = false;
                                        field.invalidOption = selected_option.name;
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
                        $scope.postOptionsToEditor();
                        $scope.valid_form = true;
                        jQuery('.single_add_to_cart_button').removeClass("nbo-disabled nbo-hidden");
                        jQuery('.variations_form, form.cart').find('[name="nbo-ignore-design"]').remove();
                        if ($scope.can_start_design) {
                            if ($scope.type == 'variable') {
                                var variation_id = jQuery('input[name="variation_id"], input.variation_id').val();
                                if (variation_id != '' && variation_id != 0) {
                                    jQuery('#triggerDesign').removeClass('nbdesigner_disable');
                                }
                            } else {
                                jQuery('#triggerDesign').removeClass('nbdesigner_disable');
                            }
                        } else {
                            jQuery('.variations_form, form.cart').append('<input name="nbo-ignore-design" type="hidden" value="1" />');
                            jQuery('#triggerDesign').addClass('nbdesigner_disable');
                        };
                        jQuery(document).triggerHandler('nbo_valid_form');
                    } else {
                        jQuery(document).triggerHandler('invalid_nbo_options');
                        jQuery('.single_add_to_cart_button').addClass("nbo-disabled");
                        if (nbds_frontend.nbdesigner_hide_add_cart_until_form_filled == 'yes') {
                            jQuery('.single_add_to_cart_button').addClass("nbo-hidden");
                        }
                        $scope.valid_form = false;
                        jQuery('#triggerDesign').addClass('nbdesigner_disable');
                        jQuery(document).triggerHandler('nbo_invalid_form');
                    }
                    $scope.may_be_change_product_image();
                    if ($scope.has_price_matrix && (angular.isUndefined(calculate_pm) || calculate_pm)) {
                        $scope.calculate_price_matrix();
                    }
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
            $scope.postOptionsToEditor = function() {
                angular.copy(nbOption.odOption, nbOption.lastOdOption);
                angular.copy(nbOption.extraOdOption, nbOption.lastExtraOdOption);
                nbOption.odOption = {};
                nbOption.extraOdOption = {};
                var options_str = '';
                angular.forEach($scope.nbd_fields, function(field, field_id) {
                    if (field.enable) {
                        var origin_field = $scope.get_field(field_id);
                    }
                });
                /* send option to editor */
                if (angular.equals(nbOption.odOption, nbOption.lastOdOption)) {
                    jQuery(document).triggerHandler('change_nbo_options_without_od_option');
                } else {
                    jQuery(document).triggerHandler('change_nbo_options_with_od_option');
                };
                if (!angular.equals(nbOption.extraOdOption, nbOption.lastExtraOdOption)) {
                    jQuery(document).triggerHandler('change_nbo_extra_od_options');
                }
                jQuery(document).triggerHandler('change_nbo_options');
            };
            $scope.getFieldIndexById = function(field_id) {
                var currentFieldIndex = 0;
                angular.forEach($scope.options.fields, function(__field, __index) {
                    if (__field.id == field_id) currentFieldIndex = __index;
                });
                return currentFieldIndex;
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
            $scope.updateMultiselectValue = function(field_id) {
                $scope.nbd_fields[field_id].values = [];
                angular.forEach($scope.nbd_fields[field_id]._values, function(val, index) {
                    if (val) {
                        $scope.nbd_fields[field_id].values.push(index);
                    }
                });
                $scope.nbd_fields[field_id].value = $scope.nbd_fields[field_id].values[0];
                $scope.check_valid();
            };
            $scope.lastTickDpi = new Date().getTime();
            $scope.update_dpi = function() {
                $scope.lastTickDpi = new Date().getTime();
                $timeout(function() {
                    var current = new Date().getTime();
                    if ((current - $scope.lastTickDpi) >= 500) {
                        $scope.check_valid();
                    };
                }, 500);
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
            $scope.reset_product_image_attr = function(ele, attr, id) {
                ele.attr(attr, $scope.product_image[id][attr]);
                delete $scope.product_image[id][attr];
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
            $scope.change_gallery_image = function(gallery_images, folder) {
                if (angular.isDefined(folder)) {
                    nbOption.template_folder = folder;
                    nbOption.gallery = {};
                    nbOption.design_stored = 1;
                }
                var _options_folder = 'product_id,' + $scope.product_id + '|' + 'template,' + nbOption.template_folder + '|' + nbOption.options_str;
                _options_folder = window.btoa(_options_folder);
                nbOption.gallery[_options_folder] = gallery_images;
                var product_element = jQuery('#product-' + $scope.product_id),
                    product_images = product_element.find('.woocommerce-product-gallery__image:not(.clone), .woocommerce-product-gallery__image--placeholder:not(.clone)'),
                    thumbnail_images = product_element.find('.flex-control-nav li');
                if (product_images.length > 1 && gallery_images.length > 0) {
                    jQuery.each(product_images, function(index, el) {
                        if (index > 0 && index <= gallery_images.length) {
                            var timestamp = new Date().getTime(),
                                src = gallery_images[index - 1].src + '?t=' + timestamp;
                            jQuery(el).find('a img').attr({
                                'src': src,
                                'srcset': src + ' 320w',
                                'sizes': gallery_images[index - 1].sizes,
                                'title': gallery_images[index - 1].title,
                                'data-src': src,
                                'data-large_image': src,
                                'data-large_image_width': gallery_images[index - 1].width,
                                'data-large_image_height': gallery_images[index - 1].height,
                                'data-thumb': src
                            });
                            jQuery(el).find('a').attr('href', src);
                            jQuery(el).addClass('nbo-gallery-loading');
                            thumbnail_images.eq(index).addClass('nbo-gallery-loading');
                            var image = new Image();
                            image.onload = function() {
                                thumbnail_images.eq(index).find('img').attr({
                                    'src': src,
                                    'alt': gallery_images[index - 1].title
                                });
                                thumbnail_images.eq(index).removeClass('nbo-gallery-loading');
                                jQuery(el).removeClass('nbo-gallery-loading');
                                jQuery('#nbdesigner_frontend_area .img-con').eq(index - 1).find('img').attr({
                                    'src': src,
                                    'alt': gallery_images[index - 1].title
                                });
                            };
                            image.src = src;
                        }
                    });
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
            $scope.get_field_index = function(field_id) {
                var _index = null;
                angular.forEach($scope.fields, function(field, index) {
                    if (field.id == field_id) _index = index;
                });
                return _index;
            };
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
                    enable: true
                };

                function assignCheck(check) {
                    $scope.status_fields[field_id][attr_index].enable = check;
                }
                option = currentOption;
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
                                if ($scope.isMultipleSelectPage(field)) {
                                    if (angular.isDefined($scope.form_values[field.id])) {
                                        $scope.nbd_fields[field.id].values = [parseInt($scope.nbd_fields[field.id].value)];
                                    } else {
                                        $scope.nbd_fields[field.id].values = [];
                                    }
                                    $scope.nbd_fields[field.id]._values = [];
                                    angular.forEach(field.general.attributes.options, function(op, k) {
                                        if (angular.isDefined($scope.form_values[field.id])) {
                                            $scope.nbd_fields[field.id]._values[k] = false;
                                        } else {
                                            if (angular.isDefined(field.general.auto_select_page) && field.general.auto_select_page == 'n') {
                                                if (op.selected == 'on') {
                                                    $scope.nbd_fields[field.id]._values[k] = true;
                                                    $scope.nbd_fields[field.id].values.push(k);
                                                }
                                            } else {
                                                $scope.nbd_fields[field.id]._values[k] = true;
                                                $scope.nbd_fields[field.id].values.push(k);
                                            }
                                        }
                                    });
                                    if ($scope.nbd_fields[field.id]._values.length == 0) {
                                        $scope.nbd_fields[field.id]._values[0] = true;
                                        $scope.nbd_fields[field.id].values.push(0);
                                    }
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
                    var origin_field = $scope.get_field(field_id);
                    if ($scope.isMultipleSelectPage(origin_field)) {
                        $scope.nbd_fields[field_id].value = value[0];
                        $scope.nbd_fields[field_id].values = value;
                        angular.forEach(value, function(val) {
                            $scope.nbd_fields[origin_field.id]._values[val] = true;
                        });
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
                    $scope.upDateVaritionQty(NBDESIGNERPRODUCT.variations);
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
                                        var option = optionWrap.find('.nbd-field-content ' + selector).eq(index - 1);
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
                                        var option = optionWrap.find('.nbd-field-content ' + selector).eq(index - 1);
                                    } else {
                                        option = optionWrap.find('.nbd-field-content ' + selector).eq(0);
                                    }
                                    if (option.attr('disabled') == 'disabled') {
                                        var enabledOption = optionWrap.find('.nbd-field-content ' + selector + ':enabled').eq(0);
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
            // $scope.change_delivery_date = function(qty_break_index, delivery_index) {
            //     $scope.quantity = $scope.validate_int($scope.turnaround_quantity_breaks[qty_break_index].val);
            //     $scope.nbd_fields[nbOption.delivery_field_id].value = '' + delivery_index;
            //     var delivery_field = $scope.get_field(nbOption.delivery_field_id);
            //     angular.forEach($scope.turnaround_quantity_breaks, function(_break, key) {
            //         angular.forEach(delivery_field.general.attributes.options, function(op, okey) {
            //             $scope.turnaround_matrix[key][okey].active = false;
            //         });
            //     });
            //     $scope.turnaround_matrix[qty_break_index][delivery_index].active = true;
            //     $scope.custom_quantity = false;
            //     $scope.current_turnaround_position = [qty_break_index, delivery_index];
            //     $scope.change_quantity();
            // };
            $scope.update_delivery_date = function() {
                var qty = $scope.validate_int(jQuery('input[name="quantity"]').val()),
                    quantity_break = $scope.get_quantity_break(qty),
                    position = quantity_break.index;
                if (angular.isDefined($scope.current_turnaround_position[1])) {
                    if ($scope.turnaround_matrix[position][$scope.current_turnaround_position[1]].show == false) {
                        $scope.turnaround_matrix[$scope.current_turnaround_position[0]][$scope.current_turnaround_position[1]].active = false;
                        var delivery_field = $scope.get_field(nbOption.delivery_field_id);
                        for (i = 0; i < delivery_field.general.attributes.options.length; i++) {
                            if ($scope.turnaround_matrix[position][i].show == true) {
                                $scope.nbd_fields[nbOption.delivery_field_id].value = '' + i;
                                $scope.current_turnaround_position[1] = i;
                                $scope.current_turnaround_position[0] = position;
                                $scope.turnaround_matrix[position][i].active = true;
                                break;
                            }
                        }
                    }
                }
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
            $scope.select_price_matrix = function(_i, _j) {
                var i, j;
                for (i = 0; i < $scope.options.pm_num_row; i++) {
                    for (j = 0; j < $scope.options.pm_num_col; j++) {
                        $scope.options.price_matrix[i][j].class = '';
                    }
                }
                $scope.options.price_matrix[_i][_j].class = 'selected';
                angular.copy($scope.options.price_matrix[_i][_j].fields, $scope.nbd_fields);
                $scope.options.pm_selected = [_i, _j];
                $scope.check_valid(false);
            };
            $scope.get_mpm_base_price = function(i, j) {
                var index = i * $scope.options.pm_num_col + j;
                if (angular.isDefined($scope.options.mpm_prices[index])) return $scope.convert_wc_price_to_float($scope.options.mpm_prices[index]);
                return 0;
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
            $scope.get_quantity_break = function(qty) {
                var quantity_break = {
                    index: 0,
                    oparator: 'gt'
                };
                var quantity_breaks = [];
                angular.forEach($scope.options.quantity_breaks, function(_break, key) {
                    quantity_breaks[key] = $scope.validate_int(_break.val);
                });
                angular.forEach(quantity_breaks, function(_break, key) {
                    if (key == 0 && qty < _break) {
                        quantity_break = {
                            index: 0,
                            oparator: 'lt'
                        };
                    }
                    if (qty >= _break && key < (quantity_breaks.length - 1)) {
                        quantity_break = {
                            index: key,
                            oparator: 'bw'
                        };
                    }
                    if (key == (quantity_breaks.length - 1) && qty >= _break) {
                        quantity_break = {
                            index: key,
                            oparator: 'gt'
                        };
                    }
                });
                return quantity_break;
            };
            $scope.is_fixed_amount = function(field) {
                if (angular.isDefined(field.general.depend_qty) && field.general.depend_qty == 'n2') {
                    return true;
                } else {
                    return false;
                }
            };
            $scope.isMultipleSelectPage = function(field) {
                return false;
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
                            jQuery.each(jQuery('.nbd-field-ad-dropdown-wrap'), function(ind, el) {
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
                                        alert("<?php _e('Sorry, file is too big, max size: ', 'web-to-print-online-designer'); ?>" + scope.maxsize + 'MB');
                                        resetInput();
                                    }
                                }
                                if (scope.minsize != '') {
                                    var minsize = parseInt(scope.minsize) * 1024 * 1024;
                                    if (minsize > file.size) {
                                        alert("<?php _e('Sorry, file is too small, min size: ', 'web-to-print-online-designer'); ?>" + scope.minsize + 'MB');
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
                                        alert("<?php _e('Sorry, this file type is not permitted for security reasons. Only accept: ', 'web-to-print-online-designer'); ?>" + scope.types);
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
                        jQuery(element).parent('.nbd-field-content').find('.nbd-upload-hidden').remove();
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
            var appEl = document.getElementById('<?php echo $appid; ?>');
            angular.element(function() {
                angular.bootstrap(appEl, ['nboApp']);
            });
        <?php endif; ?>
        jQuery(document).on('update_nbo_options_from_builder', function(e, data) {
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