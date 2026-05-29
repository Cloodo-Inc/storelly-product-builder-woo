<?php 
if (!defined('ABSPATH')) exit; 
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables used in local scope.
?>
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
            <?php elseif( $field['general']['input_type'] == 'n' ):
                $nbd_input_opt = isset( $field['general']['input_option'] ) ? $field['general']['input_option'] : array();
                $nbd_n_min  = isset( $nbd_input_opt['min'] )  ? $nbd_input_opt['min']  : '';
                $nbd_n_max  = isset( $nbd_input_opt['max'] )  ? $nbd_input_opt['max']  : '';
                $nbd_n_step = isset( $nbd_input_opt['step'] ) ? $nbd_input_opt['step'] : '';
            ?>
            string-to-number type="number" min="<?php echo esc_attr( $nbd_n_min ); ?>" max="<?php echo esc_attr( $nbd_n_max ); ?>" step="<?php echo esc_attr( $nbd_n_step ); ?>" ng-step="<?php echo esc_attr( $nbd_n_step !== '' ? $nbd_n_step : '0.0001' ); ?>"
            <?php elseif( $field['general']['input_type'] == 'r' ):
                $nbd_input_opt = isset( $field['general']['input_option'] ) ? $field['general']['input_option'] : array();
                $nbd_n_min  = isset( $nbd_input_opt['min'] )  ? $nbd_input_opt['min']  : '';
                $nbd_n_max  = isset( $nbd_input_opt['max'] )  ? $nbd_input_opt['max']  : '';
                $nbd_n_step = isset( $nbd_input_opt['step'] ) ? $nbd_input_opt['step'] : '';
            ?>
            string-to-number type="range" min="<?php echo esc_attr( $nbd_n_min ); ?>" max="<?php echo esc_attr( $nbd_n_max ); ?>" step="<?php echo esc_attr( $nbd_n_step ); ?>" ng-step="<?php echo esc_attr( $nbd_n_step !== '' ? $nbd_n_step : '0.0001' ); ?>"
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
                data-file="<?php echo esc_url( $file_url ); ?>" data-filename="<?php echo esc_attr( $filename ); ?>" data-uploaded="<?php echo esc_attr( $uploaded ); ?>"
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
        <?php if( $field['general']['input_type'] == 'u' ) :
            $nbd_up        = isset( $field['general']['upload_option'] ) ? $field['general']['upload_option'] : array();
            $nbd_up_types  = isset( $nbd_up['allow_type'] ) && '' !== trim( (string) $nbd_up['allow_type'] ) ? strtoupper( str_replace( ',', ', ', trim( (string) $nbd_up['allow_type'] ) ) ) : '';
            $nbd_up_min    = isset( $nbd_up['min_size'] ) ? (string) $nbd_up['min_size'] : '';
            $nbd_up_max    = isset( $nbd_up['max_size'] ) ? (string) $nbd_up['max_size'] : '';
            if ( '' !== $nbd_up_types || '' !== $nbd_up_min || '' !== $nbd_up_max ) : ?>
            <div class="nbd-upload-notes">
                <?php if ( '' !== $nbd_up_types ) : ?><span class="nbd-upload-note"><?php printf( /* translators: %s: list of accepted file extensions */ esc_html__( 'Accepted: %s', 'storelly-product-builder-for-woocommerce' ), esc_html( $nbd_up_types ) ); ?></span><?php endif; ?>
                <?php if ( '' !== $nbd_up_min && '0' !== $nbd_up_min ) : ?><span class="nbd-upload-note"><?php printf( /* translators: %s: minimum upload size in MB */ esc_html__( 'Min %s MB', 'storelly-product-builder-for-woocommerce' ), esc_html( $nbd_up_min ) ); ?></span><?php endif; ?>
                <?php if ( '' !== $nbd_up_max ) : ?><span class="nbd-upload-note"><?php printf( /* translators: %s: maximum upload size in MB */ esc_html__( 'Max %s MB', 'storelly-product-builder-for-woocommerce' ), esc_html( $nbd_up_max ) ); ?></span><?php endif; ?>
            </div>
        <?php endif; endif; ?>
        <?php if( $field['general']['input_type'] == 'r' ): ?>
        <span class="nbd-input-range" ng-bind="nbd_fields['<?php echo esc_attr( $field['id'] ); ?>'].value"></span>
        <?php endif; ?>
        <?php if( $field['general']['input_type'] == 'n' || $field['general']['input_type'] == 'r' ): ?>
        <?php /* translators: %s: minimum allowed value. */ ?>
        <span class="nbd-invalid-notice nbd-invalid-min"><?php printf( esc_html__('Invalid value, min: %s', 'storelly-product-builder-for-woocommerce'), esc_html( $nbd_n_min ) ); ?></span>
        <?php /* translators: %s: maximum allowed value. */ ?>
        <span class="nbd-invalid-notice nbd-invalid-max"><?php printf( esc_html__('Invalid value, max: %s', 'storelly-product-builder-for-woocommerce'), esc_html( $nbd_n_max ) ); ?></span>
        <?php endif; ?>
    </div>
</div>