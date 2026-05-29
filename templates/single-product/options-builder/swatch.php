<?php
if (!defined('ABSPATH')) exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables used in local scope.
?>
<div class="nbd-option-field <?php echo esc_attr( $class ); ?>" data-id="<?php echo esc_attr( $field['id'] ); ?>" ng-if="nbd_fields['<?php echo esc_attr( $field['id'] ); ?>'].enable">
    <?php include( $currentDir .'/options-builder/field-header.php' ); ?>
    <div class="pcpb-field-content">
        <div class="nbd-swatch-wrap nbd-swatch-grid">
            <?php
                // Tasteful texture tiles for paper/finish swatches that have no real image/colour.
                $spbwc_sw_keywords = array(
                    'matte'   => 'linear-gradient(135deg,#d6d3d1 0%,#78716c 100%)',
                    'gloss'   => 'linear-gradient(135deg,#e2e8f0 0%,#64748b 55%,#cbd5e1 100%)',
                    'soft'    => 'linear-gradient(135deg,#fdba74 0%,#ea580c 100%)',
                    'silk'    => 'linear-gradient(135deg,#f5f5f4 0%,#a8a29e 100%)',
                    'linen'   => 'linear-gradient(135deg,#e7d9b8 0%,#b8a06a 100%)',
                    'metal'   => 'linear-gradient(135deg,#cbd5e1 0%,#64748b 50%,#e2e8f0 100%)',
                    'recycl'  => 'linear-gradient(135deg,#d2a679 0%,#9c6b3f 100%)',
                    'kraft'   => 'linear-gradient(135deg,#d2a679 0%,#9c6b3f 100%)',
                    'spot uv' => 'linear-gradient(135deg,#3f3f46 0%,#71717a 50%,#27272a 100%)',
                    'eco'     => 'linear-gradient(135deg,#86efac 0%,#16a34a 100%)',
                    'thick'   => 'linear-gradient(135deg,#d6d3d1 0%,#57534e 100%)',
                    'none'    => 'linear-gradient(135deg,#f5f5f4 0%,#d6d3d1 100%)',
                );
                $spbwc_sw_palette = array(
                    'linear-gradient(135deg,#cbd5e1 0%,#64748b 100%)',
                    'linear-gradient(135deg,#fdba74 0%,#ea580c 100%)',
                    'linear-gradient(135deg,#c4b5fd 0%,#7c3aed 100%)',
                    'linear-gradient(135deg,#6ee7b7 0%,#059669 100%)',
                    'linear-gradient(135deg,#fcd34d 0%,#d97706 100%)',
                    'linear-gradient(135deg,#fca5a5 0%,#dc2626 100%)',
                );
                foreach ($field['general']['attributes']["options"] as $key => $attr):
                    $image_url = SPBWC_Storelly_PB_Util::spbwc_get_image_thumbnail( $attr['image'] );
                    $enable_subattr = isset($attr['enable_subattr']) ? $attr['enable_subattr'] : 0;
                    $attr['sub_attributes'] = isset( $attr['sub_attributes'] ) ? $attr['sub_attributes'] : array();
                    $show_subattr = ($enable_subattr == 'on' && count($attr['sub_attributes']) > 0) ? true : false;
                    $field['general']['attributes']["options"][$key]['show_subattr'] = $show_subattr;
                    // Per-attribute additional price (fixed amount) for the card label.
                    $attr_price_raw = isset( $attr['price'] ) ? ( is_array( $attr['price'] ) ? ( isset( $attr['price'][0] ) ? $attr['price'][0] : '' ) : $attr['price'] ) : '';
                    $attr_price_val = is_numeric( $attr_price_raw ) ? (float) $attr_price_raw : 0;
                    $is_default     = ( isset( $attr['selected'] ) && $attr['selected'] === 'on' );
                    // Visual fill: real image, else color, else a neutral tile
                    // (avoids the placeholder image on text-only attributes).
                    $spbwc_real_color = ( isset( $attr['preview_type'] ) && $attr['preview_type'] === 'c'
                        && ! empty( $attr['color'] )
                        && ! in_array( strtolower( $attr['color'] ), array( '#ffffff', '#fff', '#fdfdfd' ), true ) );
                    if ( $attr['preview_type'] == 'i' && ! empty( $attr['image'] ) && $attr['image'] != 0 && $image_url && false === strpos( $image_url, 'placeholder' ) ) {
                        $swatch_style = 'background: url(' . $image_url . ') center / cover';
                    } elseif ( $spbwc_real_color ) {
                        $swatch_style = isset( $attr['color2'] )
                            ? 'background: linear-gradient(150deg, ' . $attr['color'] . ' 0%, ' . $attr['color'] . ' 50%, ' . $attr['color2'] . ' 51%, ' . $attr['color2'] . ' 100%)'
                            : 'background: ' . $attr['color'];
                    } else {
                        $spbwc_nlc = strtolower( (string) $attr['name'] );
                        $spbwc_grad = '';
                        foreach ( $spbwc_sw_keywords as $spbwc_kw => $spbwc_g ) {
                            if ( '' !== $spbwc_nlc && false !== strpos( $spbwc_nlc, $spbwc_kw ) ) { $spbwc_grad = $spbwc_g; break; }
                        }
                        if ( '' === $spbwc_grad ) { $spbwc_grad = $spbwc_sw_palette[ $key % count( $spbwc_sw_palette ) ]; }
                        $swatch_style = 'background: ' . $spbwc_grad;
                    }
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
                <label class="nbd-swatch nbd-swatch-card" for='pcpb-field-<?php echo esc_attr( $field['id'].'-'.$key ); ?>'
                    title="<?php echo esc_attr( $attr['name'] ); ?>"
                    nbo-disabled="!status_fields['<?php echo esc_attr( $field['id'] ); ?>'][<?php echo esc_attr( $key ); ?>].enable" nbo-disabled-type="class" >
                    <span class="nbd-swatch__visual" style="<?php echo esc_attr( $swatch_style ); ?>">
                        <span class="nbd-swatch__check" aria-hidden="true">✓</span>
                    </span>
                    <span class="nbd-swatch__body">
                        <span class="nbd-swatch__name"><?php echo esc_html( $attr['name'] ); ?></span>
                        <?php if ( ! empty( $attr['des'] ) ) : ?><span class="nbd-swatch__sub"><?php echo esc_html( $attr['des'] ); ?></span><?php endif; ?>
                        <?php if ( $attr_price_val > 0 ) : ?>
                            <span class="nbd-swatch__price">+<?php echo wp_kses_post( wc_price( $attr_price_val ) ); ?></span>
                        <?php else : ?>
                            <span class="nbd-swatch__price nbd-swatch__price--free"><?php esc_html_e( 'Free', 'storelly-product-builder-for-woocommerce' ); ?></span>
                        <?php endif; ?>
                    </span>
                    <?php if ( $is_default ) : ?><span class="nbd-swatch__badge"><?php esc_html_e( 'Popular', 'storelly-product-builder-for-woocommerce' ); ?></span><?php endif; ?>
                </label>
            <?php endforeach; ?>
        </div>
        <div class="nbo-invalid-option"
            ng-class="nbd_fields['<?php echo esc_attr( $field['id'] ); ?>'].valid === false ? 'active' : ''"
            ng-if="nbd_fields['<?php echo esc_attr( $field['id'] ); ?>'].valid === false"><span ng-bind="nbd_fields['<?php echo esc_attr( $field['id'] ); ?>'].invalidOption"></span> <?php esc_html_e('is not available.', 'storelly-product-builder-for-woocommerce'); ?>
        </div>
    </div>
</div>
