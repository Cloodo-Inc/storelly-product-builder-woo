<?php
if (!defined('ABSPATH')) exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables used in local scope.
?>
<div class="nbd-option-field pcpb-field-input-wrap <?php echo esc_attr( $class ); ?>" ng-if="nbd_fields['<?php echo esc_attr( $field['id'] ); ?>'].enable">
    <?php include( $currentDir . '/options-builder/field-header.php' ); ?>
    <div class="pcpb-field-content">
        <textarea name="nbd-field[<?php echo esc_attr( $field['id'] ); ?>]" class="nbd-field-textarea nbd-input-a" rows="3"
            id="pcpb-field-<?php echo esc_attr( $field['id'] ); ?>"
            ng-model="nbd_fields['<?php echo esc_attr( $field['id'] ); ?>'].value" ng-change="check_valid()"
            <?php if ( $field['general']['required'] == 'y' ) echo esc_attr( 'required' ); ?>
            <?php if ( isset( $field['general']['placeholder'] ) && $field['general']['placeholder'] != '' ) : ?>placeholder="<?php echo esc_attr( $field['general']['placeholder'] ); ?>"<?php endif; ?>
            <?php if ( isset( $field['general']['text_option']['min'] ) && $field['general']['text_option']['min'] != '' ) : ?>pattern=".{0}|.{<?php echo esc_attr( $field['general']['text_option']['min'] ); ?>,}"<?php endif; ?>
            <?php if ( isset( $field['general']['text_option']['max'] ) && $field['general']['text_option']['max'] != '' ) : ?>maxlength="<?php echo esc_attr( $field['general']['text_option']['max'] ); ?>"<?php endif; ?>></textarea>
    </div>
</div>
