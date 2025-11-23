<?php if (!defined('ABSPATH')) exit; ?>
<div class="nbd-option-field pcpb-field-input-wrap <?php echo esc_attr( $class ); ?>" ng-if="nbd_fields['<?php echo esc_attr( $field['id'] ); ?>'].enable">
    <?php include( $currentDir .'/options-builder/field-header.php' ); ?>
    <div class="pcpb-field-content">
        <input 
            ng-change="check_valid()"
            ng-model="nbd_fields['<?php echo esc_attr( $field['id'] ); ?>'].value" class="nbd-input-<?php echo esc_attr( $field['general']['input_type'] ); ?>"
            <?php if( $field['general']['required'] == 'y' ) echo esc_attr('required'); ?> name="nbd-field[<?php echo esc_attr( $field['id'] ); ?>]" id="pcpb-field-<?php echo esc_attr( $field['id'] ); ?>"
            <?php if( $field['general']['input_type'] == 't' ): ?>
            type="text" <?php if( $field['general']['text_option']['min'] != '' ): ?>pattern=".{0}|.{<?php echo esc_attr( $field['general']['text_option']['min'] ); ?>,}"<?php endif; ?> <?php if( $field['general']['text_option']['max'] != '' ): ?>maxlength="<?php echo esc_attr( $field['general']['text_option']['max'] ); ?>"<?php endif; ?>
                <?php if( isset( $field['general']['placeholder'] ) && $field['general']['placeholder'] != '' ): ?>
                    placeholder="<?php echo esc_attr( $field['general']['placeholder'] ); ?>"
                <?php endif; ?>
            <?php elseif( $field['general']['input_type'] == 'u' ): ?>
            type="file" nbo-input-file="check_valid()" data-field-id="<?php echo esc_attr( $field['id'] ); ?>" data-types="<?php echo esc_attr(strtolower( trim( $field['general']['upload_option']['allow_type'] ) )); ?>" 
                data-minsize="<?php echo esc_attr( $field['general']['upload_option']['min_size'] ); ?>" data-maxsize="<?php echo esc_attr( $field['general']['upload_option']['max_size'] ); ?>"
                <?php 
                    $file_url = '';
                    $filename = '';
                    $uploaded = 0;
                    if( isset($form_values[$field['id']]) ){
                        $file_url = SPBWC_PB_UPLOAD_URL . '/' . $form_values[$field['id']];
                        $filename = explode('/', $form_values[$field['id']])[1];
                        $uploaded = 1;
                    }
                ?>
                data-file="<?php printf( esc_attr__( '%s', 'pc-product-builder' ), esc_attr( $file_url ) ); ?>" data-filename="<?php echo esc_attr( $filename ); ?>" data-uploaded="<?php echo esc_attr( $uploaded ); ?>"
                <?php 
                    if( $field['general']['upload_option']['allow_type'] != '' ):
                        $allow_type = strtolower( trim( $field['general']['upload_option']['allow_type'] ) );
                        $allow_type_arr = explode(',', $allow_type);
                        $delimiter = '';
                ?> 
                accept="<?php foreach( $allow_type_arr as $_type ){ $_type = trim($_type); if($_type == 'jpg' || $_type == 'jpeg'){ $_type = 'jpg,.jpeg'; }; $__type = $delimiter . '.' . $_type; $delimiter = ','; echo esc_attr( $__type ); } ?>"
                <?php endif; ?>
            <?php endif; ?>
        />
        <?php 
            if( $field['general']['input_type'] == 'u' && isset($form_values[$field['id']]) ):
        ?>
        <input class="nbd-upload-hidden" id="nbd-upload-hidden-<?php echo esc_attr( $field['id'] ); ?>" type="hidden" name="nbd-field[<?php echo esc_attr( $field['id'] ); ?>]" value="<?php echo esc_attr( $form_values[$field['id']] ); ?>" />
        <?php endif; ?>
        <?php if( $field['general']['input_type'] == 'u' && $field['general']['upload_option']['min_size'] != '' ): ?>
        <span style="display: block; font-size: 12px;margin-top: 10px;"><?php echo esc_html__('Min size: ', 'pc-product-builder') . $field['general']['upload_option']['min_size'] . ' MB'; ?></span>
        <?php endif; ?>
        <?php if( $field['general']['input_type'] == 'u' && $field['general']['upload_option']['max_size'] != '' ): ?>
        <span style="display: block; font-size: 12px;"><?php echo esc_html__('Max size: ', 'pc-product-builder') . $field['general']['upload_option']['max_size'] . ' MB'; ?></span>
        <?php endif; ?>
    </div>
</div>