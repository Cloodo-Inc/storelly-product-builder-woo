<?php
/**
 * v3 — Customer design tools section (free-form text/image layers).
 *
 * Lets the merchant enable buyer-added TEXT and IMAGE layers on the canvas
 * customizer, with per-layer fees and simple constraints. Binds to
 * $scope.options.free_design_tools.* exactly like the quantity section binds to
 * $scope.options.quantity_* — the whole options object is serialized on save and
 * resolved at runtime (with a global fallback) by
 * SPBWC_Storelly_PB_Util::spbwc_get_free_design_tools().
 *
 * @package Storelly_Product_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="v2-card spbwc-fdt-card">
    <div class="v2-card__head v2-card__head--brand">
        <h2 class="v2-card__title">
            <span class="v2-card__title-icon">🎨</span>
            <?php esc_html_e( 'Customer design tools', 'storelly-product-builder-for-woocommerce' ); ?>
        </h2>
    </div>
    <div class="v2-card__body">
        <p class="v2-form-row__help" style="margin:0 0 var(--nbd-space-3);">
            <?php esc_html_e( 'Let customers add their own text and images directly on the design canvas. Each added layer can carry a fixed fee.', 'storelly-product-builder-for-woocommerce' ); ?>
        </p>

        <!-- TEXT tool -->
        <div class="v2-section-head">
            <span class="v2-section-head__icon">🔤</span>
            <?php esc_html_e( 'Add text', 'storelly-product-builder-for-woocommerce' ); ?>
            <label class="v2-switch" style="margin-left:auto;">
                <input type="checkbox" ng-model="options.free_design_tools.text.enabled" ng-true-value="'y'" ng-false-value="'n'" ng-checked="options.free_design_tools.text.enabled === 'y' || options.free_design_tools.text.enabled === 'on'" />
                <input type="hidden" name="options[free_design_tools][text][enabled]" ng-value="(options.free_design_tools.text.enabled === 'y' || options.free_design_tools.text.enabled === 'on') ? 'y' : 'n'" />
                <span class="v2-switch__track"></span>
                <span class="v2-switch__label" ng-bind="(options.free_design_tools.text.enabled === 'y' || options.free_design_tools.text.enabled === 'on') ? '<?php echo esc_js( __( 'Enabled', 'storelly-product-builder-for-woocommerce' ) ); ?>' : '<?php echo esc_js( __( 'Disabled', 'storelly-product-builder-for-woocommerce' ) ); ?>'"></span>
            </label>
        </div>
        <div class="v2-qty-grid" ng-show="options.free_design_tools.text.enabled === 'y'">
            <div class="v2-form-row">
                <label class="v2-form-row__label"><?php esc_html_e( 'Max text layers', 'storelly-product-builder-for-woocommerce' ); ?></label>
                <input class="v2-input spbwc-vb-text-input" type="number" string-to-number min="0" ng-model="options.free_design_tools.text.max_layers" name="options[free_design_tools][text][max_layers]" style="max-width:120px;" />
                <span class="v2-form-row__help"><?php esc_html_e( '0 = unlimited', 'storelly-product-builder-for-woocommerce' ); ?></span>
            </div>
            <div class="v2-form-row">
                <label class="v2-form-row__label"><?php esc_html_e( 'Price per text layer', 'storelly-product-builder-for-woocommerce' ); ?></label>
                <input class="v2-input spbwc-vb-text-input" type="number" string-to-number min="0" step="0.01" ng-model="options.free_design_tools.text.price_per_layer" name="options[free_design_tools][text][price_per_layer]" style="max-width:120px;" />
            </div>
            <div class="v2-form-row">
                <label class="v2-form-row__label"><?php esc_html_e( 'Let customer change colour', 'storelly-product-builder-for-woocommerce' ); ?></label>
                <label class="v2-switch">
                    <input type="checkbox" ng-model="options.free_design_tools.text.allow_change_color" ng-true-value="'y'" ng-false-value="'n'" ng-checked="options.free_design_tools.text.allow_change_color !== 'n'" />
                    <input type="hidden" name="options[free_design_tools][text][allow_change_color]" ng-value="options.free_design_tools.text.allow_change_color === 'n' ? 'n' : 'y'" />
                    <span class="v2-switch__track"></span>
                </label>
            </div>
            <div class="v2-form-row">
                <label class="v2-form-row__label"><?php esc_html_e( 'Default text', 'storelly-product-builder-for-woocommerce' ); ?></label>
                <input class="v2-input spbwc-vb-text-input" type="text" ng-model="options.free_design_tools.text.default_text" name="options[free_design_tools][text][default_text]" placeholder="<?php esc_attr_e( 'e.g. Your name', 'storelly-product-builder-for-woocommerce' ); ?>" />
            </div>
        </div>

        <!-- IMAGE tool -->
        <div class="v2-section-head" style="margin-top:var(--nbd-space-4);">
            <span class="v2-section-head__icon">🖼️</span>
            <?php esc_html_e( 'Add images', 'storelly-product-builder-for-woocommerce' ); ?>
            <label class="v2-switch" style="margin-left:auto;">
                <input type="checkbox" ng-model="options.free_design_tools.image.enabled" ng-true-value="'y'" ng-false-value="'n'" ng-checked="options.free_design_tools.image.enabled === 'y' || options.free_design_tools.image.enabled === 'on'" />
                <input type="hidden" name="options[free_design_tools][image][enabled]" ng-value="(options.free_design_tools.image.enabled === 'y' || options.free_design_tools.image.enabled === 'on') ? 'y' : 'n'" />
                <span class="v2-switch__track"></span>
                <span class="v2-switch__label" ng-bind="(options.free_design_tools.image.enabled === 'y' || options.free_design_tools.image.enabled === 'on') ? '<?php echo esc_js( __( 'Enabled', 'storelly-product-builder-for-woocommerce' ) ); ?>' : '<?php echo esc_js( __( 'Disabled', 'storelly-product-builder-for-woocommerce' ) ); ?>'"></span>
            </label>
        </div>
        <div class="v2-qty-grid" ng-show="options.free_design_tools.image.enabled === 'y'">
            <div class="v2-form-row">
                <label class="v2-form-row__label"><?php esc_html_e( 'Max image layers', 'storelly-product-builder-for-woocommerce' ); ?></label>
                <input class="v2-input spbwc-vb-text-input" type="number" string-to-number min="0" ng-model="options.free_design_tools.image.max_layers" name="options[free_design_tools][image][max_layers]" style="max-width:120px;" />
                <span class="v2-form-row__help"><?php esc_html_e( '0 = unlimited', 'storelly-product-builder-for-woocommerce' ); ?></span>
            </div>
            <div class="v2-form-row">
                <label class="v2-form-row__label"><?php esc_html_e( 'Price per image layer', 'storelly-product-builder-for-woocommerce' ); ?></label>
                <input class="v2-input spbwc-vb-text-input" type="number" string-to-number min="0" step="0.01" ng-model="options.free_design_tools.image.price_per_layer" name="options[free_design_tools][image][price_per_layer]" style="max-width:120px;" />
            </div>
            <div class="v2-form-row">
                <label class="v2-form-row__label"><?php esc_html_e( 'Allowed file types', 'storelly-product-builder-for-woocommerce' ); ?></label>
                <input class="v2-input spbwc-vb-text-input" type="text" ng-model="options.free_design_tools.image.allow_type" name="options[free_design_tools][image][allow_type]" placeholder="png,jpg,jpeg,svg" />
            </div>
            <div class="v2-form-row">
                <label class="v2-form-row__label"><?php esc_html_e( 'Max file size (MB)', 'storelly-product-builder-for-woocommerce' ); ?></label>
                <input class="v2-input spbwc-vb-text-input" type="number" string-to-number min="0" ng-model="options.free_design_tools.image.max_size" name="options[free_design_tools][image][max_size]" style="max-width:120px;" />
            </div>
        </div>
    </div>
</div>
<?php // phpcs:ignore Generic.Files.EndFileNewline -- template partial. ?>
