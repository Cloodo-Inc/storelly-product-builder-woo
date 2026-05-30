<?php
if (!defined('ABSPATH')) exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables used in local scope.
?>
<div class="pcpb-field-header">
    <div class="pcpb-field-header__row">
        <label for='pcpb-field-<?php echo esc_attr($field['id']); ?>'><?php echo esc_html($field['general']['title']); ?><?php if ($field['general']['required'] == 'y') : ?> <span class="nbd-required" aria-label="<?php esc_attr_e( 'Required', 'storelly-product-builder-for-woocommerce' ); ?>" title="<?php esc_attr_e( 'Required', 'storelly-product-builder-for-woocommerce' ); ?>">*</span><?php endif; ?></label>
        <?php
        $spbwc_opt_count = ( isset($field['general']['data_type'], $field['general']['attributes']['options'])
            && $field['general']['data_type'] === 'm'
            && is_array($field['general']['attributes']['options']) )
            ? count($field['general']['attributes']['options']) : 0;
        if ($spbwc_opt_count > 1) :
            /* translators: %d: number of available choices for this option field. */
            $spbwc_opt_label = sprintf( _n( '%d option', '%d options', $spbwc_opt_count, 'storelly-product-builder-for-woocommerce' ), $spbwc_opt_count );
        ?>
            <span class="pcpb-field-count"><?php echo esc_html( $spbwc_opt_label ); ?></span>
        <?php endif; ?>
        <span class="pcpb-field-chosen" ng-show="nbd_fields['<?php echo esc_attr($field['id']); ?>'].value_name">
            <b ng-bind="nbd_fields['<?php echo esc_attr($field['id']); ?>'].value_name"></b>
            <span class="pcpb-field-chosen__price" ng-show="nbd_fields['<?php echo esc_attr($field['id']); ?>'].price" ng-bind-html="nbd_fields['<?php echo esc_attr($field['id']); ?>'].price | to_trusted"></span>
        </span>
    </div>
    <?php if ($field['general']['description'] != '') : ?>
        <p class="pcpb-field-desc"><?php echo esc_html($field['general']['description']); ?></p>
    <?php endif; ?>
</div>
