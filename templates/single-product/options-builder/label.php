<?php 
if (!defined('ABSPATH')) exit; 
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables used in local scope.
?>
<div class="nbd-option-field <?php echo esc_attr( $class ); ?>" data-id="<?php echo esc_attr( $field['id'] ); ?>" ng-if="nbd_fields['<?php echo esc_attr( $field['id'] ); ?>'].enable">
    <?php include( $currentDir .'/options-builder/field-header.php' ); ?>
    <div class="pcpb-field-content">
        <?php
        // If any option carries a description (e.g. upload / design / service choices),
        // render this field's options as prominent button-cards instead of plain chips.
        $has_card_desc = false;
        foreach ( $field['general']['attributes']["options"] as $attr_check ) {
            if ( ! empty( $attr_check['des'] ) ) { $has_card_desc = true; break; }
        }
        ?>
        <div class="nbd-label-wrap<?php echo $has_card_desc ? ' nbd-label-wrap--cards' : ''; ?>">
        <?php
            foreach ($field['general']['attributes']["options"] as $key => $attr):
                $enable_subattr = isset($attr['enable_subattr']) ? $attr['enable_subattr'] : 0;
                $attr['sub_attributes'] = isset( $attr['sub_attributes'] ) ? $attr['sub_attributes'] : array();
                $show_subattr = ($enable_subattr == 'on' && count($attr['sub_attributes']) > 0) ? true : false;
                $field['general']['attributes']["options"][$key]['show_subattr'] = $show_subattr;
                $attr_price_raw = isset( $attr['price'] ) ? ( is_array( $attr['price'] ) ? ( isset( $attr['price'][0] ) ? $attr['price'][0] : '' ) : $attr['price'] ) : '';
                $attr_price_val = is_numeric( $attr_price_raw ) ? (float) $attr_price_raw : 0;
        ?>
        <input ng-change="check_valid();updateMapOptions('<?php echo esc_attr( $field['id'] ); ?>')" value="<?php echo esc_attr( $key ); ?>" ng-model="nbd_fields['<?php echo esc_attr( $field['id'] ); ?>'].value" name="pcpb-field[<?php echo esc_attr( $field['id'] ); ?>]<?php if($show_subattr) echo esc_attr('[value]'); ?>" type="radio" id='pcpb-field-<?php echo esc_attr( $field['id'].'-'.$key ); ?>'
            <?php
                if( isset($form_values[$field['id']]) ){
                    $fvalue = (is_array($form_values[$field['id']]) && isset($form_values[$field['id']]['value'])) ? $form_values[$field['id']]['value'] : $form_values[$field['id']];
                    checked( $fvalue, $key );
                }else{
                    checked( isset($attr['selected']) ? $attr['selected'] : 'off', 'on' );
                }
            ?> />
        <label class="nbd-label" for='pcpb-field-<?php echo esc_attr( $field['id'].'-'.$key ); ?>'
            nbo-disabled="!status_fields['<?php echo esc_attr( $field['id'] ); ?>'][<?php echo esc_attr( $key ); ?>].enable" nbo-disabled-type="class" >
            <span class="nbd-label__head">
                <span class="nbd-label__name"><?php echo esc_html( $attr['name'] ); ?></span>
                <?php if ( $attr_price_val > 0 ) : ?><span class="chip__price">+<?php echo wp_kses_post( wc_price( $attr_price_val ) ); ?></span><?php endif; ?>
            </span>
            <?php if ( ! empty( $attr['des'] ) ) : ?><span class="nbd-label__desc"><?php echo esc_html( $attr['des'] ); ?></span><?php endif; ?>
        </label>
        <?php endforeach; ?>
        </div>
        <div class="nbo-invalid-option" 
            ng-class="nbd_fields['<?php echo esc_attr( $field['id'] ); ?>'].valid === false ? 'active' : ''"
            ng-if="nbd_fields['<?php echo esc_attr( $field['id'] ); ?>'].valid === false"><span ng-bind="nbd_fields['<?php echo esc_attr( $field['id'] ); ?>'].invalidOption"></span> <?php esc_html_e('is not available', 'storelly-product-builder-for-woocommerce'); ?></div>
    </div>
</div>

