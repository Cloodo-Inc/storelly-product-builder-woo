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
                // Shared swatch visual resolver: real image → photo; real colour →
                // solid/gradient; else a tasteful gradient tile by name/index. Reused for
                // both the parent category tiles and the sub-attribute (colour) tiles.
                $spbwc_swatch_style = function ( $pt, $img, $img_url, $color, $color2, $name, $idx ) use ( $spbwc_sw_keywords, $spbwc_sw_palette ) {
                    $is_color = ( $pt === 'c' && ! empty( $color )
                        && ! in_array( strtolower( $color ), array( '#ffffff', '#fff', '#fdfdfd' ), true ) );
                    if ( $pt == 'i' && ! empty( $img ) && $img != 0 && $img_url && false === strpos( $img_url, 'placeholder' ) ) {
                        return array( 'style' => 'background-image: url(' . $img_url . ')', 'is_photo' => true );
                    }
                    if ( $is_color ) {
                        $style = ! empty( $color2 )
                            ? 'background-image: linear-gradient(150deg, ' . $color . ' 0%, ' . $color . ' 50%, ' . $color2 . ' 51%, ' . $color2 . ' 100%)'
                            : 'background-color: ' . $color;
                        return array( 'style' => $style, 'is_photo' => false );
                    }
                    $nlc  = strtolower( (string) $name );
                    $grad = '';
                    foreach ( $spbwc_sw_keywords as $kw => $g ) {
                        if ( '' !== $nlc && false !== strpos( $nlc, $kw ) ) { $grad = $g; break; }
                    }
                    if ( '' === $grad ) { $grad = $spbwc_sw_palette[ $idx % count( $spbwc_sw_palette ) ]; }
                    return array( 'style' => 'background-image: ' . $grad, 'is_photo' => false );
                };
                // Detect a shared "placeholder" parent image: when two or more parent
                // options in this field point to the SAME attachment id, that image is a
                // generic category placeholder (genuinely distinct categories don't reuse
                // one photo) — e.g. the bundled design-component placeholder reused across
                // Leather/Cotton/Suede. Such an image is named like any real upload (no
                // "placeholder" in the URL), so the strpos check below can't catch it;
                // counting reuse does. Treat it as non-representative so the tile borrows
                // the real fabric photo from its sub-attributes.
                $spbwc_parent_img_freq = array();
                foreach ( $field['general']['attributes']['options'] as $spbwc_o ) {
                    $spbwc_oi = ( isset( $spbwc_o['image'] ) && $spbwc_o['image'] ) ? (string) $spbwc_o['image'] : '';
                    if ( '' !== $spbwc_oi ) {
                        $spbwc_parent_img_freq[ $spbwc_oi ] = isset( $spbwc_parent_img_freq[ $spbwc_oi ] ) ? $spbwc_parent_img_freq[ $spbwc_oi ] + 1 : 1;
                    }
                }
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
                    // Visual fill. Design-component categories (e.g. Leather/Cotton) often
                    // use a placeholder parent image while the real fabric photos live on the
                    // sub-attributes — borrow the default/first sub's image or colour so the
                    // category tile shows a real swatch instead of a neutral gradient.
                    $spbwc_vis_pt    = isset( $attr['preview_type'] ) ? $attr['preview_type'] : 'i';
                    $spbwc_vis_img   = isset( $attr['image'] ) ? $attr['image'] : 0;
                    $spbwc_vis_url   = $image_url;
                    $spbwc_vis_color = isset( $attr['color'] ) ? $attr['color'] : '';
                    $spbwc_vis_col2  = isset( $attr['color2'] ) ? $attr['color2'] : '';
                    $spbwc_img_shared = ( ! empty( $spbwc_vis_img ) && isset( $spbwc_parent_img_freq[ (string) $spbwc_vis_img ] ) && $spbwc_parent_img_freq[ (string) $spbwc_vis_img ] > 1 );
                    $parent_img_ok   = ( $spbwc_vis_pt == 'i' && ! empty( $spbwc_vis_img ) && $spbwc_vis_img != 0 && $image_url && false === strpos( $image_url, 'placeholder' ) && ! $spbwc_img_shared );
                    if ( ! $parent_img_ok && $show_subattr ) {
                        $spbwc_rep = null;
                        foreach ( $attr['sub_attributes'] as $spbwc_s ) {
                            if ( isset( $spbwc_s['selected'] ) && $spbwc_s['selected'] === 'on' ) { $spbwc_rep = $spbwc_s; break; }
                        }
                        if ( null === $spbwc_rep ) { $spbwc_rep = reset( $attr['sub_attributes'] ); }
                        if ( is_array( $spbwc_rep ) ) {
                            $spbwc_vis_pt    = isset( $spbwc_rep['preview_type'] ) ? $spbwc_rep['preview_type'] : 'i';
                            $spbwc_vis_img   = isset( $spbwc_rep['image'] ) ? $spbwc_rep['image'] : 0;
                            $spbwc_vis_url   = SPBWC_Storelly_PB_Util::spbwc_get_image_thumbnail( $spbwc_vis_img );
                            $spbwc_vis_color = isset( $spbwc_rep['color'] ) ? $spbwc_rep['color'] : '';
                            $spbwc_vis_col2  = isset( $spbwc_rep['color2'] ) ? $spbwc_rep['color2'] : '';
                        }
                    }
                    $spbwc_vis      = $spbwc_swatch_style( $spbwc_vis_pt, $spbwc_vis_img, $spbwc_vis_url, $spbwc_vis_color, $spbwc_vis_col2, $attr['name'], $key );
                    $swatch_style   = $spbwc_vis['style'];
                    $spbwc_is_photo = $spbwc_vis['is_photo'];
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
                    <span class="nbd-swatch__visual<?php echo $spbwc_is_photo ? ' nbd-swatch__visual--photo' : ''; ?>" style="<?php echo esc_attr( $swatch_style ); ?>">
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
        <?php
        // Sub-attribute swatch rows for design components (e.g. Leather → Black/Grey…).
        // Shown only when the parent category is selected; tile clicks set
        // nbd_fields[id].sub_value via select_adv_subattr (the same model the advanced
        // dropdown uses), and the visually-hidden <select> carries it to the form. Only
        // the active parent's <select> is enabled, so exactly one sub_value is submitted.
        foreach ( $field['general']['attributes']["options"] as $pkey => $pattr ) :
            $p_enable = isset( $pattr['enable_subattr'] ) ? $pattr['enable_subattr'] : 0;
            $p_subs   = ( isset( $pattr['sub_attributes'] ) && is_array( $pattr['sub_attributes'] ) ) ? $pattr['sub_attributes'] : array();
            if ( $p_enable != 'on' || count( $p_subs ) == 0 ) { continue; }
        ?>
        <div class="nbd-subswatch" ng-show="nbd_fields['<?php echo esc_attr( $field['id'] ); ?>'].value == '<?php echo esc_attr( $pkey ); ?>'">
            <div class="nbd-subswatch__label">
                <?php
                /* translators: %s = parent option name (e.g. Leather). */
                printf( esc_html__( 'Choose %s option', 'storelly-product-builder-for-woocommerce' ), esc_html( $pattr['name'] ) );
                ?>
            </div>
            <select class="nbd-subswatch__select screen-reader-text" ng-change="check_valid()"
                    ng-disabled="nbd_fields['<?php echo esc_attr( $field['id'] ); ?>'].value != '<?php echo esc_attr( $pkey ); ?>'"
                    name="pcpb-field[<?php echo esc_attr( $field['id'] ); ?>][sub_value]"
                    ng-model="nbd_fields['<?php echo esc_attr( $field['id'] ); ?>'].sub_value">
                <?php foreach ( $p_subs as $skey => $sattr ) :
                    $s_selected = isset( $sattr['selected'] ) ? $sattr['selected'] : 'off';
                    $s_current  = 'on';
                    if ( isset( $form_values[ $field['id'] ]['sub_value'] ) ) {
                        $s_selected = $form_values[ $field['id'] ]['sub_value'];
                        $s_current  = $skey;
                    }
                ?>
                    <option value="<?php echo esc_attr( $skey ); ?>" <?php selected( $s_selected, $s_current ); ?>><?php echo esc_html( $sattr['name'] ); ?></option>
                <?php endforeach; ?>
            </select>
            <div class="nbd-swatch-wrap nbd-swatch-grid nbd-subswatch__grid">
                <?php foreach ( $p_subs as $skey => $sattr ) :
                    $s_pt    = isset( $sattr['preview_type'] ) ? $sattr['preview_type'] : 'i';
                    $s_img   = isset( $sattr['image'] ) ? $sattr['image'] : 0;
                    $s_url   = SPBWC_Storelly_PB_Util::spbwc_get_image_thumbnail( $s_img );
                    $s_color = isset( $sattr['color'] ) ? $sattr['color'] : '';
                    $s_col2  = isset( $sattr['color2'] ) ? $sattr['color2'] : '';
                    $s_vis   = $spbwc_swatch_style( $s_pt, $s_img, $s_url, $s_color, $s_col2, $sattr['name'], $skey );
                    $s_price_raw  = isset( $sattr['price'] ) ? ( is_array( $sattr['price'] ) ? ( isset( $sattr['price'][0] ) ? $sattr['price'][0] : '' ) : $sattr['price'] ) : '';
                    $s_price_val  = is_numeric( $s_price_raw ) ? (float) $s_price_raw : 0;
                    $s_is_default = ( isset( $sattr['selected'] ) && $sattr['selected'] === 'on' );
                ?>
                <label class="nbd-swatch nbd-swatch-card nbd-subswatch__card"
                    title="<?php echo esc_attr( $sattr['name'] ); ?>"
                    ng-class="( nbd_fields['<?php echo esc_attr( $field['id'] ); ?>'].value == '<?php echo esc_attr( $pkey ); ?>' && nbd_fields['<?php echo esc_attr( $field['id'] ); ?>'].sub_value == '<?php echo esc_attr( $skey ); ?>' ) ? 'is-active' : ''"
                    ng-click="select_adv_subattr('<?php echo esc_attr( $field['id'] ); ?>', '<?php echo esc_attr( $pkey ); ?>', '<?php echo esc_attr( $skey ); ?>')"
                    nbo-disabled="status_fields['<?php echo esc_attr( $field['id'] ); ?>'][<?php echo esc_attr( $pkey ); ?>].sub_attributes &amp;&amp; status_fields['<?php echo esc_attr( $field['id'] ); ?>'][<?php echo esc_attr( $pkey ); ?>].sub_attributes[<?php echo esc_attr( $skey ); ?>] === false" nbo-disabled-type="class">
                    <span class="nbd-swatch__visual<?php echo $s_vis['is_photo'] ? ' nbd-swatch__visual--photo' : ''; ?>" style="<?php echo esc_attr( $s_vis['style'] ); ?>">
                        <span class="nbd-swatch__check" aria-hidden="true">✓</span>
                    </span>
                    <span class="nbd-swatch__body">
                        <span class="nbd-swatch__name"><?php echo esc_html( $sattr['name'] ); ?></span>
                        <?php if ( $s_price_val > 0 ) : ?>
                            <span class="nbd-swatch__price">+<?php echo wp_kses_post( wc_price( $s_price_val ) ); ?></span>
                        <?php endif; ?>
                    </span>
                    <?php if ( $s_is_default ) : ?><span class="nbd-swatch__badge"><?php esc_html_e( 'Popular', 'storelly-product-builder-for-woocommerce' ); ?></span><?php endif; ?>
                </label>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
        <div class="nbo-invalid-option"
            ng-class="nbd_fields['<?php echo esc_attr( $field['id'] ); ?>'].valid === false ? 'active' : ''"
            ng-if="nbd_fields['<?php echo esc_attr( $field['id'] ); ?>'].valid === false"><span ng-bind="nbd_fields['<?php echo esc_attr( $field['id'] ); ?>'].invalidOption"></span> <?php esc_html_e('is not available.', 'storelly-product-builder-for-woocommerce'); ?>
        </div>
    </div>
</div>
