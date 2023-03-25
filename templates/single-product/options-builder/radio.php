<?php if (!defined('ABSPATH')) exit; ?>
<div class="nbd-option-field pcpb-field-radio-wrap <?php echo( $class ); ?>" data-id="<?php echo( $field['id'] ); ?>" ng-if="nbd_fields['<?php echo( $field['id'] ); ?>'].enable">
    <?php include( $currentDir .'/options-builder/field-header.php' ); ?>
    <div class="pcpb-field-content">
        <div class="nbd-radio __nbd-radio-wrap">
            <?php 
                foreach ($field['general']['attributes']["options"] as $key => $attr): 
                    $enable_subattr = isset($attr['enable_subattr']) ? $attr['enable_subattr'] : 0;
                    $attr['sub_attributes'] = isset( $attr['sub_attributes'] ) ? $attr['sub_attributes'] : array();
                    $show_subattr = ($enable_subattr == 'on' && count($attr['sub_attributes']) > 0) ? true : false;
                    $field['general']['attributes']["options"][$key]['show_subattr'] = $show_subattr;
            ?>
            <input ng-change="check_valid();updateMapOptions('<?php echo( $field['id'] ); ?>')" value="<?php echo( $key ); ?>" ng-model="nbd_fields['<?php echo( $field['id'] ); ?>'].value" id='pcpb-field-<?php echo( $field['id'].'-'.$key ); ?>' 
                   name="pcpb-field[<?php echo( $field['id'] ); ?>]<?php if($show_subattr) echo '[value]'; ?>" type="radio" 
                   nbo-disabled="!status_fields['<?php echo( $field['id'] ); ?>'][<?php echo( $key ); ?>].enable" nbo-disabled-type="attr" 
                <?php
                    if( isset($form_values[$field['id']]) ){
                        $fvalue = (is_array($form_values[$field['id']]) && isset($form_values[$field['id']]['value'])) ? $form_values[$field['id']]['value'] : $form_values[$field['id']];
                        checked( $fvalue, $key ); 
                    }else{
                        checked( isset($attr['selected']) ? $attr['selected'] : 'off', 'on' ); 
                    }
                ?>/> <label for='pcpb-field-<?php echo( $field['id'].'-'.$key ); ?>' nbo-disabled="!status_fields['<?php echo( $field['id'] ); ?>'][<?php echo( $key ); ?>].enable" nbo-disabled-type="class" ><?php echo( $attr['name'] ); ?></label>
            <?php endforeach; ?>
        </div>
        <div class="nbo-invalid-option" 
            ng-class="nbd_fields['<?php echo( $field['id'] ); ?>'].valid === false ? 'active' : ''"
            ng-if="nbd_fields['<?php echo( $field['id'] ); ?>'].valid === false">{{nbd_fields['<?php echo( $field['id'] ); ?>'].invalidOption}} <?php esc_html_e('is not available', 'web-to-print-online-designer'); ?>
        </div>
    </div>
</div>