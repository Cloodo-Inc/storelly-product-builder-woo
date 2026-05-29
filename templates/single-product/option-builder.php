<?php
if (!defined('ABSPATH')) exit;
do_action('spbwc_head', 'single-product');
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables are used within the local scope of the template.
$in_quick_view      = false;
$is_wqv             = false;
$display_type       = '1';
$in_design_editor   = false;
$group_mode = false;
$nbds_frontend = array(
    'wc_currency_format_num_decimals'               =>  SPBWC_Storelly_PB_Util::spbwc_get_option_decimals(),
    'currency_format_num_decimals'                  =>  SPBWC_Storelly_PB_Util::spbwc_get_option_decimals(),
    'currency_format_symbol'                        =>  html_entity_decode((string) get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8'),
    'currency_format_decimal_sep'                   =>  stripslashes(wc_get_price_decimal_separator()),
    'currency_format_thousand_sep'                  =>  stripslashes(wc_get_price_thousand_separator()),
    'currency_format'                               =>  esc_attr(str_replace(array('%1$s', '%2$s'), array('%s', '%v'), get_woocommerce_price_format())),
    'nbstorelly_hide_add_cart_until_form_filled'    =>  get_option('spbwc_hide_add_cart_until_form_filled', 'no') === 'yes' ? 'yes' : 'no'
);

$prefix             = '';
$style_class        = 'nbo-style-1';
// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$currentDir = realpath(dirname(__FILE__)); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variable.

?>
<div class="nbo-wrapper nbo-style-cloodo <?php if ($is_wqv) echo esc_attr('nbd-option-in-wqv'); ?> <?php echo esc_attr('wrapper-type-' . $display_type); ?>">
    <div class="nbd-option-wrapper" id="<?php echo esc_attr($appid); ?>">
        <div ng-controller="optionCtrl" ng-form="nboForm" id="nbo-ctrl-<?php echo esc_attr($appid); ?>">
            <div class="nbo-fields-wrapper" spbwc-conditional-logic spbwc-storefront-enhance>
                <!-- Live price showcase + completion progress (token storefront UI) -->
                <div class="nbo-price-showcase" ng-if="fields.length">
                    <div class="nbo-price-showcase__label"><?php esc_html_e('Your total', 'storelly-product-builder-for-woocommerce'); ?></div>
                    <div class="nbo-price-showcase__total">
                        <span class="nbo-price-showcase__amount" data-spbwc-cloodo-total><span ng-bind-html="total_cart_price | to_trusted"></span></span>
                        <span class="nbo-price-showcase__saved" data-spbwc-cloodo-saved hidden></span>
                    </div>
                    <div class="nbo-price-showcase__sub" data-spbwc-cloodo-sub
                        data-label-item="<?php esc_attr_e('item', 'storelly-product-builder-for-woocommerce'); ?>"
                        data-label-items="<?php esc_attr_e('items', 'storelly-product-builder-for-woocommerce'); ?>"
                        data-label-each="<?php esc_attr_e('each', 'storelly-product-builder-for-woocommerce'); ?>"></div>
                    <div class="nbo-progress" data-spbwc-progress data-label="<?php echo esc_attr__('chosen', 'storelly-product-builder-for-woocommerce'); ?>" data-label-done="<?php echo esc_attr__('Ready to order', 'storelly-product-builder-for-woocommerce'); ?>" hidden>
                        <div class="nbo-progress__bar"></div>
                        <div class="nbo-progress__text" data-spbwc-progress-text></div>
                    </div>
                </div>
                <?php
                // Trust bar — merchant-customizable via the `spbwc_cloodo_trust_items` filter.
                // Defaults are statements that are true for the plugin context (no shipping/return
                // claims that could be inaccurate for a given store).
                $spbwc_trust_items = apply_filters( 'spbwc_cloodo_trust_items', array(
                    __( 'Secure checkout', 'storelly-product-builder-for-woocommerce' ),
                    __( 'Made to order', 'storelly-product-builder-for-woocommerce' ),
                    __( 'Live, transparent pricing', 'storelly-product-builder-for-woocommerce' ),
                ) );
                if ( ! empty( $spbwc_trust_items ) && is_array( $spbwc_trust_items ) ) : ?>
                <div class="nbo-trust" ng-if="fields.length">
                    <?php foreach ( $spbwc_trust_items as $spbwc_trust_item ) :
                        $spbwc_trust_text = is_array( $spbwc_trust_item ) ? ( isset( $spbwc_trust_item['text'] ) ? $spbwc_trust_item['text'] : '' ) : $spbwc_trust_item;
                        if ( '' === $spbwc_trust_text ) { continue; }
                        ?>
                        <span class="nbo-trust__item">
                            <svg class="nbo-trust__icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6L9 17l-5-5"/></svg>
                            <?php echo esc_html( $spbwc_trust_text ); ?>
                        </span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <!-- Smart-recommend: one-click apply the recommended combo (filled + toggled by storefront-enhance.js) -->
                <div class="nbo-recommend" data-spbwc-recommend ng-if="fields.length" hidden>
                    <span class="nbo-recommend__icon" aria-hidden="true">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18h6M10 22h4M12 2a7 7 0 0 0-4 12.7c.6.5 1 1.3 1 2.1h6c0-.8.4-1.6 1-2.1A7 7 0 0 0 12 2z"/></svg>
                    </span>
                    <div class="nbo-recommend__body">
                        <div class="nbo-recommend__title"><?php esc_html_e('Most-picked combo', 'storelly-product-builder-for-woocommerce'); ?> <span class="nbo-recommend__tag"><?php esc_html_e('Popular', 'storelly-product-builder-for-woocommerce'); ?></span></div>
                        <div class="nbo-recommend__desc" data-spbwc-recommend-desc></div>
                    </div>
                    <button type="button" class="nbo-recommend__apply" data-spbwc-recommend-apply><?php esc_html_e('Apply', 'storelly-product-builder-for-woocommerce'); ?></button>
                </div>
                <?php
                // phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables used in loop.
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
                            if ($field['general']['input_type'] == 'a') {
                                $tempalte = $currentDir . '/options-builder/textarea.php';
                            } else {
                                $tempalte = $currentDir . '/options-builder/input.php';
                            }
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
                // phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
                if ($has_nbpb) do_action('spbwc_after_default_options');

                // Quantity quick-select from the merchant's configured quantity breaks.
                // NOTE: the pricing engine does not apply a volume discount (no quantity_breaks
                // handling in option-builder.js or option_processing), so we DO NOT advertise
                // "save %": that would be a false claim. These are honest quick-pick quantities;
                // the live total updates to quantity x price.
                $spbwc_qtiers = array();
                if ( isset($options['quantity_breaks']) && is_array($options['quantity_breaks']) ) {
                    foreach ($options['quantity_breaks'] as $spbwc_b) {
                        $spbwc_bval = isset($spbwc_b['val']) ? ( is_array($spbwc_b['val']) ? ( $spbwc_b['val']['value'] ?? '' ) : $spbwc_b['val'] ) : '';
                        if ( '' !== (string) $spbwc_bval && (int) $spbwc_bval > 0 ) {
                            $spbwc_qtiers[] = (int) $spbwc_bval;
                        }
                    }
                    $spbwc_qtiers = array_values( array_unique( $spbwc_qtiers ) );
                }
                ?>
                <?php if ( count($spbwc_qtiers) > 1 ) : ?>
                <div class="nbo-qty-tiers" ng-if="fields.length">
                    <div class="pcpb-field-header">
                        <div class="pcpb-field-header__row">
                            <label><?php esc_html_e('Quantity', 'storelly-product-builder-for-woocommerce'); ?></label>
                            <span class="pcpb-field-count"><?php esc_html_e('Quick pick', 'storelly-product-builder-for-woocommerce'); ?></span>
                        </div>
                        <p class="pcpb-field-desc"><?php esc_html_e('Pick a common quantity, or set your own below — the total updates instantly.', 'storelly-product-builder-for-woocommerce'); ?></p>
                    </div>
                    <div class="nbo-qty-tiers__grid" data-spbwc-qty-tiers>
                        <?php foreach ($spbwc_qtiers as $spbwc_qv) : ?>
                        <button type="button" class="nbo-qty-tier" data-spbwc-qty="<?php echo esc_attr($spbwc_qv); ?>">
                            <span class="nbo-qty-tier__qty"><?php echo esc_html($spbwc_qv); ?></span>
                            <span class="nbo-qty-tier__unit"><?php esc_html_e('units', 'storelly-product-builder-for-woocommerce'); ?></span>
                        </button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                <div ng-if="fields.length" class="nbo-clear-option-wrap">
                    <?php if ($num_visible_field > 0) : ?>
                        <a class="button nbd-button" ng-click="reset_options()"><?php esc_html_e('Clear selection', 'storelly-product-builder-for-woocommerce'); ?></a>
                    <?php endif; ?>
                </div>
                <input type="hidden" value="<?php echo esc_attr($product_id); ?>" name="pcpb-add-to-cart" />
                <p ng-if="!valid_form" class="nbd-invalid-form"><?php esc_html_e('Please check invalid fields and quantity input or choose a different combination!', 'storelly-product-builder-for-woocommerce'); ?></p>
            </div>
            <div class="nbo-summary-wrapper">
                <div ng-if="valid_form" class="nbo-table-summary-wrap <?php echo esc_attr($style_class); ?>">
                    <p class="nbo-summary-title" ng-init="showNboSummary = true">
                        <b><?php esc_html_e('Summary options', 'storelly-product-builder-for-woocommerce'); ?></b>
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
                                    <br ng-if="field.ind_qty" /><small ng-if="field.ind_qty && field.price != ''"> <?php esc_html_e('( cart fee )', 'storelly-product-builder-for-woocommerce'); ?></small>
                                    <br ng-if="field.fixed_amount" /><small ng-if="field.fixed_amount && field.price != ''"> <?php esc_html_e('( for all items )', 'storelly-product-builder-for-woocommerce'); ?></small>
                                </td>
                                <td ng-bind-html="field.price | to_trusted"></td>
                            </tr>
                        </tbody>
                        <tfoot style="border-top: 1px solid #404762;">
                            <tr>
                                <td><b><?php esc_html_e('Options price', 'storelly-product-builder-for-woocommerce'); ?></b></td>
                                <td><span id="nbd-option-total"><span ng-bind-html="total_price | to_trusted"></span> / <?php esc_html_e('1 item', 'storelly-product-builder-for-woocommerce'); ?></span></td>
                            </tr>
                            <tr class="nbo-final-price">
                                <td><b><?php esc_html_e('Final price', 'storelly-product-builder-for-woocommerce'); ?></b></td>
                                <td>
                                    <span id="nbd-option-total">
                                        <span ng-hide="_qty == 1" ng-bind-html="final_price | to_trusted"></span><span ng-show="_qty == 1" ng-bind-html="total_cart_price | to_trusted"></span> / <?php esc_html_e('1 item', 'storelly-product-builder-for-woocommerce'); ?>
                                    </span>
                                </td>
                            </tr>
                            <tr class="nbo-final-price" ng-if="cart_item_fee.enable">
                                <td><b><?php esc_html_e('Cart item fee', 'storelly-product-builder-for-woocommerce'); ?></b></td>
                                <td><span id="nbd-option-total"><span ng-bind-html="cart_item_fee.value | to_trusted"></span> / <?php esc_html_e('all items', 'storelly-product-builder-for-woocommerce'); ?></span></td>
                            </tr>
                            <tr class="nbo-final-price nbo-total-price" ng-if="_qty &gt; 1">
                                <td><b><?php esc_html_e('Subtotal price', 'storelly-product-builder-for-woocommerce'); ?></b></td>
                                <td><span id="nbd-option-total"><span ng-bind-html="total_cart_price | to_trusted"></span> / {{_qty}} <?php esc_html_e('items', 'storelly-product-builder-for-woocommerce'); ?></span></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            <!-- Save-build: store the current configuration in the browser for reuse (storefront-enhance.js) -->
            <div class="nbo-savebuild" data-spbwc-savebuild ng-if="fields.length">
                <div class="nbo-savebuild__body">
                    <div class="nbo-savebuild__title"><?php esc_html_e('Save your build', 'storelly-product-builder-for-woocommerce'); ?></div>
                    <div class="nbo-savebuild__sub"><?php esc_html_e('Store this configuration in your browser to reuse later.', 'storelly-product-builder-for-woocommerce'); ?></div>
                    <div class="nbo-savebuild__list" data-spbwc-savebuild-list></div>
                </div>
                <button type="button" class="nbo-savebuild__save" data-spbwc-savebuild-save><?php esc_html_e('Save build', 'storelly-product-builder-for-woocommerce'); ?></button>
            </div>
            <!-- Mobile sticky total + CTA (shown on small screens via CSS) -->
            <div class="nbo-sticky-mobile" ng-if="fields.length">
                <div class="nbo-sticky-mobile__price">
                    <span class="nbo-sticky-mobile__label"><?php esc_html_e('Total', 'storelly-product-builder-for-woocommerce'); ?></span>
                    <span class="nbo-sticky-mobile__amount" ng-bind-html="total_cart_price | to_trusted"></span>
                </div>
                <button type="button" class="nbo-sticky-mobile__cta" data-spbwc-sticky-cta><?php esc_html_e('Add to cart', 'storelly-product-builder-for-woocommerce'); ?></button>
            </div>
        </div>
    </div>
</div>