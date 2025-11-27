<?php if (!defined('ABSPATH')) exit; ?>
<div class="nbd-option-field <?php echo esc_attr( $class ); ?>" data-id="<?php echo esc_attr( $field['id'] ); ?>" ng-if="nbd_fields['<?php echo esc_attr( $field['id'] ); ?>'].enable">
    <?php include( $currentDir .'/options-builder/field-header.php' ); ?>
    <div class="pcpb-field-content">
        <div class="nbd-swatch-wrap">
            <?php 
                foreach ($field['general']['attributes']["options"] as $key => $attr): 
                    $image_url = SPBWC_Storelly_PB_Util::spbwc_get_image_thumbnail( $attr['image'] );
                    $enable_subattr = isset($attr['enable_subattr']) ? $attr['enable_subattr'] : 0;
                    $attr['sub_attributes'] = isset( $attr['sub_attributes'] ) ? $attr['sub_attributes'] : array();
                    $show_subattr = ($enable_subattr == 'on' && count($attr['sub_attributes']) > 0) ? true : false;
                    $field['general']['attributes']["options"][$key]['show_subattr'] = $show_subattr;
            ?>
                <input ng-change="check_valid();updateMapOptions('<?php echo esc_attr( $field['id'] ); ?>')" value="<?php echo esc_attr( $key ); ?>" ng-model="nbd_fields['<?php echo esc_attr( $field['id'] ); ?>'].value" name="pcpb-field[<?php echo esc_attr( $field['id'] ); ?>]<?php if($show_subattr) echo esc_attr('[value]'); ?>" 
                   type="radio" id='pcpb-field-<?php echo esc_attr( $field['id'].'-'.$key ); ?>' 
                <?php 
                    if( isset($form_values[$field['id']]) ){
                        $fvalue = (is_array($form_values[$field['id']]) && isset($form_values[$field['id']]['value'])) ? $form_values[$field['id']]['value'] : $form_values[$field['id']];
                        checked( $fvalue, $key ); 
                    }else{
                        checked( isset($attr['selected']) ? $attr['selected'] : 'off', 'on' ); 
                    }
                ?> />
                <label class="nbd-swatch" style="<?php if( $attr['preview_type'] == 'i' ){echo esc_attr('background: url('.$image_url . ') 0% 0% / cover');}else{ 
                    if(isset( $attr['color2'] )){
                        $style  = "background: -moz-linear-gradient(-35deg,  ". $attr['color'] ." 0%, ". $attr['color'] ." 50%, ". $attr['color2'] ." 51%, ". $attr['color2'] ." 100%);";
                        $style .= "background: -webkit-linear-gradient(-35deg,  ". $attr['color'] ." 0%, ". $attr['color'] ." 50%, ". $attr['color2'] ." 51%, ". $attr['color2'] ." 100%);";
                        $style .= "background: linear-gradient(150deg,  ". $attr['color'] ." 0%, ". $attr['color'] ." 50%, ". $attr['color2'] ." 51%, ". $attr['color2'] ." 100%);";
                        echo esc_attr($style); 
                        
                    }else{
                        echo esc_attr('background: ' . $attr['color']);  
                    }
                }; ?>" 
                title="<?php echo esc_attr( $attr['name'] ); ?>" for='pcpb-field-<?php echo esc_attr( $field['id'].'-'.$key ); ?>'
                nbo-disabled="!status_fields['<?php echo esc_attr( $field['id'] ); ?>'][<?php echo esc_attr( $key ); ?>].enable" nbo-disabled-type="class" >
                <span class="nbd-swatch-tooltip"><?php echo esc_html( $attr['name'] ); ?></span>
            </label>
            <?php endforeach; ?>
        </div>
        <div class="nbo-invalid-option" 
            ng-class="nbd_fields['<?php echo esc_attr( $field['id'] ); ?>'].valid === false ? 'active' : ''"
            ng-if="nbd_fields['<?php echo esc_attr( $field['id'] ); ?>'].valid === false">{{nbd_fields['<?php echo esc_attr( $field['id'] ); ?>'].invalidOption}} <?php esc_html_e('is not available.', 'spbwc-product-builder'); ?>
        </div>
    </div>
</div>
