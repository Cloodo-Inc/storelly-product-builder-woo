<?php
/**
 * v3 — Quantity & bulk pricing section.
 *
 * v3.0: kept structurally identical to the classic Quantity card so the
 * AngularJS handlers (add_quantity_break, remove_quantity_break,
 * set_default_quantity_break) and form name patterns are unchanged. Only
 * the outer chrome / token usage updated. Full redesign in v3.2.
 *
 * @package Storelly_Product_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="v2-card v2-qty-card">
    <div class="v2-card__head v2-card__head--brand">
        <h2 class="v2-card__title">
            <span class="v2-card__title-icon">🔢</span>
            <?php esc_html_e( 'Quantity & bulk pricing', 'storelly-product-builder-for-woocommerce' ); ?>
        </h2>
        <label class="v2-switch v2-card__sub" style="margin-left:auto;">
            <input type="checkbox" ng-model="options.quantity_enable" ng-true-value="'y'" ng-false-value="'n'" name="options[quantity_enable]" />
            <span class="v2-switch__track"></span>
            <span class="v2-switch__label" ng-bind="options.quantity_enable === 'y' ? '<?php echo esc_js( __( 'Enabled', 'storelly-product-builder-for-woocommerce' ) ); ?>' : '<?php echo esc_js( __( 'Disabled', 'storelly-product-builder-for-woocommerce' ) ); ?>'"></span>
        </label>
    </div>
    <div class="v2-card__body" ng-show="options.quantity_enable === 'y'">
        <p class="v2-form-row__help" style="margin:0 0 var(--nbd-space-3);">
            <?php esc_html_e( 'Define quantity tiers customers can pick from. Each tier can have its own discount (fixed amount or percent off).', 'storelly-product-builder-for-woocommerce' ); ?>
        </p>

        <div class="v2-qty-grid">
            <div class="v2-form-row">
                <label class="v2-form-row__label"><?php esc_html_e( 'Display as', 'storelly-product-builder-for-woocommerce' ); ?></label>
                <div class="v2-radio-cards">
                    <label class="v2-radio-card" ng-class="{'is-active': options.quantity_type === 'r'}">
                        <input type="radio" ng-model="options.quantity_type" value="r" name="options[quantity_type]" />
                        <strong><?php esc_html_e( 'Tier cards', 'storelly-product-builder-for-woocommerce' ); ?></strong>
                        <small><?php esc_html_e( '100 / 250 / 500 cards w/ save%', 'storelly-product-builder-for-woocommerce' ); ?></small>
                    </label>
                    <label class="v2-radio-card" ng-class="{'is-active': options.quantity_type === 'd'}">
                        <input type="radio" ng-model="options.quantity_type" value="d" name="options[quantity_type]" />
                        <strong><?php esc_html_e( 'Dropdown', 'storelly-product-builder-for-woocommerce' ); ?></strong>
                        <small><?php esc_html_e( 'Compact pick-one list', 'storelly-product-builder-for-woocommerce' ); ?></small>
                    </label>
                    <label class="v2-radio-card" ng-class="{'is-active': options.quantity_type === 's'}">
                        <input type="radio" ng-model="options.quantity_type" value="s" name="options[quantity_type]" />
                        <strong><?php esc_html_e( 'Stepper', 'storelly-product-builder-for-woocommerce' ); ?></strong>
                        <small><?php esc_html_e( 'Free input min/max/step', 'storelly-product-builder-for-woocommerce' ); ?></small>
                    </label>
                </div>
            </div>

            <div class="v2-form-row">
                <label class="v2-form-row__label"><?php esc_html_e( 'Discount type', 'storelly-product-builder-for-woocommerce' ); ?></label>
                <select class="v2-select spbwc-vb-output__select" ng-model="options.quantity_discount_type" name="options[quantity_discount_type]" style="max-width:200px;">
                    <option value="p"><?php esc_html_e( 'Percent off (%)', 'storelly-product-builder-for-woocommerce' ); ?></option>
                    <option value="f"><?php esc_html_e( 'Fixed amount off ($)', 'storelly-product-builder-for-woocommerce' ); ?></option>
                </select>
            </div>
        </div>

        <div class="v2-qty-grid v2-qty-grid--minmax" ng-show="options.quantity_type === 's'">
            <div class="v2-form-row">
                <label class="v2-form-row__label"><?php esc_html_e( 'Min quantity', 'storelly-product-builder-for-woocommerce' ); ?></label>
                <input class="v2-input spbwc-vb-text-input" type="number" string-to-number min="1" ng-model="options.quantity_min" name="options[quantity_min]" style="max-width:120px;" />
            </div>
            <div class="v2-form-row">
                <label class="v2-form-row__label"><?php esc_html_e( 'Max quantity', 'storelly-product-builder-for-woocommerce' ); ?></label>
                <input class="v2-input spbwc-vb-text-input" type="number" string-to-number min="1" ng-model="options.quantity_max" name="options[quantity_max]" style="max-width:120px;" />
            </div>
            <div class="v2-form-row">
                <label class="v2-form-row__label"><?php esc_html_e( 'Step', 'storelly-product-builder-for-woocommerce' ); ?></label>
                <input class="v2-input spbwc-vb-text-input" type="number" string-to-number min="1" ng-model="options.quantity_step" name="options[quantity_step]" style="max-width:120px;" />
            </div>
        </div>

        <div class="v2-section-head">
            <span class="v2-section-head__icon">📊</span>
            <?php esc_html_e( 'Quantity tiers', 'storelly-product-builder-for-woocommerce' ); ?>
            <span style="margin-left:auto; text-transform:none; letter-spacing:0; font-weight:var(--font-normal); color:var(--nbd-st-text-mute);">
                {{ options.quantity_breaks.length || 0 }} tier{{ options.quantity_breaks.length === 1 ? '' : 's' }}
            </span>
        </div>

        <div class="v2-qty-breaks">
            <div class="v2-qty-break v2-qty-break--head">
                <div><?php esc_html_e( 'Quantity', 'storelly-product-builder-for-woocommerce' ); ?></div>
                <div><?php esc_html_e( 'Discount', 'storelly-product-builder-for-woocommerce' ); ?></div>
                <div><?php esc_html_e( 'Default', 'storelly-product-builder-for-woocommerce' ); ?></div>
                <div></div>
            </div>
            <div class="v2-qty-break" ng-repeat="b in options.quantity_breaks track by $index">
                <div>
                    <input class="v2-input spbwc-vb-text-input" type="number" string-to-number min="1"
                           ng-model="b.val"
                           name="options[quantity_breaks][{{$index}}][val]"
                           placeholder="<?php esc_attr_e( 'e.g. 100', 'storelly-product-builder-for-woocommerce' ); ?>" />
                </div>
                <div>
                    <span class="v2-qty-break__prefix" ng-show="options.quantity_discount_type === 'f'">$</span>
                    <input class="v2-input spbwc-vb-text-input" type="text"
                           ng-model="b.dis"
                           name="options[quantity_breaks][{{$index}}][dis]"
                           placeholder="0" />
                    <span class="v2-qty-break__suffix" ng-show="options.quantity_discount_type === 'p'">%</span>
                </div>
                <div class="v2-qty-break__default">
                    <label>
                        <input type="radio"
                               name="options[quantity_breaks_default]"
                               value="{{$index}}"
                               ng-checked="b.default === 'on'"
                               ng-click="set_default_quantity_break($index)" />
                        <input type="hidden"
                               name="options[quantity_breaks][{{$index}}][default]"
                               ng-value="b.default" />
                        <?php esc_html_e( 'Default', 'storelly-product-builder-for-woocommerce' ); ?>
                    </label>
                </div>
                <button type="button" class="v2-qty-break__remove" ng-click="remove_quantity_break($index)" title="<?php esc_attr_e( 'Remove tier', 'storelly-product-builder-for-woocommerce' ); ?>">✕</button>
            </div>
            <div class="v2-qty-break-empty" ng-show="!options.quantity_breaks || options.quantity_breaks.length === 0">
                <span class="v2-qty-break-empty__icon" aria-hidden="true">∅</span>
                <span><?php esc_html_e( 'No quantity tiers yet. Add at least one to enable bulk pricing.', 'storelly-product-builder-for-woocommerce' ); ?></span>
            </div>
        </div>

        <button type="button" class="spbwc-cta-btn spbwc-cta-btn--ghost spbwc-cta-btn--sm" ng-click="add_quantity_break()" style="margin-top: var(--nbd-space-3);">
            <span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
            <?php esc_html_e( 'Add quantity tier', 'storelly-product-builder-for-woocommerce' ); ?>
        </button>
    </div>
</div>
