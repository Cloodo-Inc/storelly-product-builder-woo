<?php 
if (!defined('ABSPATH')) exit; 
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables used in local scope.
?>
<div class="nbd-option-field pcpb-field-dropdown-wrap <?php echo esc_attr($class); ?>" data-id="<?php echo esc_attr($field['id']); ?>" ng-if="nbd_fields['<?php echo esc_attr($field['id']); ?>'].enable">
    <?php include($currentDir . '/options-builder/field-header.php'); ?>
    <div class="pcpb-field-content">
        <div class="__nbd-dropdown-wrap">
            <select ng-change="check_valid();updateMapOptions('<?php echo esc_attr($field['id']); ?>')" name="pcpb-field[<?php echo esc_attr($field['id']); ?>]{{nbd_fields['<?php echo esc_attr($field['id']); ?>'].form_name}}" class="nbo-dropdown" ng-model="nbd_fields['<?php echo esc_attr($field['id']); ?>'].value">
                <?php
                foreach ($field['general']['attributes']["options"] as $key => $attr) :
                    $enable_subattr = isset($attr['enable_subattr']) ? $attr['enable_subattr'] : 0;
                    $attr['sub_attributes'] = isset($attr['sub_attributes']) ? $attr['sub_attributes'] : array();
                    $show_subattr = ($enable_subattr == 'on' && count($attr['sub_attributes']) > 0) ? true : false;
                    $field['general']['attributes']["options"][$key]['show_subattr'] = $show_subattr;
                    $selected = isset($attr['selected']) ? $attr['selected'] : 'off';
                    $current = 'on';
                    if (isset($form_values[$field['id']])) {
                        $selected = (is_array($form_values[$field['id']]) && isset($form_values[$field['id']]['value'])) ? $form_values[$field['id']]['value'] : $form_values[$field['id']];
                        $current = $key;
                    }
                ?>
                    <option value="<?php echo esc_attr($key); ?>" nbo-disabled="!status_fields['<?php echo esc_attr($field['id']); ?>'][<?php echo esc_attr($key); ?>].enable" nbo-disabled-type="attr" <?php selected($selected, $current); ?>>
                        <?php echo esc_html($attr['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="nbo-invalid-option" ng-class="nbd_fields['<?php echo esc_attr($field['id']); ?>'].valid === false ? 'active' : ''" ng-if="nbd_fields['<?php echo esc_attr($field['id']); ?>'].valid === false">{{nbd_fields['<?php echo esc_attr($field['id']); ?>'].invalidOption}} <?php esc_html_e('is not available', 'storelly-product-builder-for-woocommerce'); ?></div>
    </div>
</div>