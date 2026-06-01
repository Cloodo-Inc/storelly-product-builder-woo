<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div id="storelly-wraper">
    <?php wp_nonce_field('pc_box', 'pc_box_nonce'); ?>
    <div style="overflow: hidden;">
        <div class="storelly_options_panel" id="storelly-options">
            <p class="storelly-form-field">
                <label for="_storelly_pb_enable"><?php esc_html_e ('Enable Product builder', 'storelly-product-builder-for-woocommerce'); ?></label>
                <span class="storelly-option-val">
                    <input type="hidden" value="0" name="_storelly_pb_enable" />
                    <input type="checkbox" value="1" name="_storelly_pb_enable" id="_storelly_pb_enable" <?php checked($nbdpb_enable); ?> class="short" />
                </span>
            </p>
            <p class="storelly-form-field">
                <label for="_spbwc_enable_quote"><?php esc_html_e ('Enable request quote', 'storelly-product-builder-for-woocommerce'); ?></label>
                <span class="storelly-option-val">
                    <input type="hidden" value="0" name="_spbwc_enable_quote" />
                    <input type="checkbox" value="1" name="_spbwc_enable_quote" id="_spbwc_enable_quote" <?php checked(!empty($spbwc_enable_quote)); ?> class="short" />
                </span>
            </p>
            <p class="storelly-form-field">
                <label for="_spbwc_quote_display_mode"><?php esc_html_e ('Quote display', 'storelly-product-builder-for-woocommerce'); ?></label>
                <span class="storelly-option-val">
                    <select name="_spbwc_quote_display_mode" id="_spbwc_quote_display_mode" class="short">
                        <option value="" <?php selected( empty( $spbwc_quote_display_mode ) ); ?>><?php esc_html_e( 'Use global default', 'storelly-product-builder-for-woocommerce' ); ?></option>
                        <option value="both" <?php selected( isset( $spbwc_quote_display_mode ) ? $spbwc_quote_display_mode : '', 'both' ); ?>><?php esc_html_e( 'Add to cart + Get Quote', 'storelly-product-builder-for-woocommerce' ); ?></option>
                        <option value="replace" <?php selected( isset( $spbwc_quote_display_mode ) ? $spbwc_quote_display_mode : '', 'replace' ); ?>><?php esc_html_e( 'Get Quote replaces Add to cart (keep price)', 'storelly-product-builder-for-woocommerce' ); ?></option>
                        <option value="quote_only" <?php selected( isset( $spbwc_quote_display_mode ) ? $spbwc_quote_display_mode : '', 'quote_only' ); ?>><?php esc_html_e( 'Quote only — hide price &amp; cart', 'storelly-product-builder-for-woocommerce' ); ?></option>
                    </select>
                    <span class="description" style="display:block;margin-top:4px;"><?php esc_html_e( 'Only applies when request quote is enabled above.', 'storelly-product-builder-for-woocommerce' ); ?></span>
                </span>
            </p>
            <?php if ($option_id > 0 && !empty($option_title)) : ?>
            <p class="storelly-form-field">
                <label><?php esc_html_e('Mapped printing option:', 'storelly-product-builder-for-woocommerce'); ?> <strong><?php echo esc_html($option_title); ?></strong></label>
            </p>
            <?php endif; ?>
            <?php
            // Defensive warning: legacy data may still have more than one
            // product-level option listing this product. See
            // docs/SPEC_PRICING_OPTION_ASSIGNMENT.md §3.5.
            if (!empty($extra_options) && is_array($extra_options)) :
            ?>
            <div class="storelly-form-field notice notice-warning inline" style="margin:8px 0;padding:8px 12px;">
                <p style="margin:0 0 4px;">
                    <strong><?php esc_html_e('Heads up:', 'storelly-product-builder-for-woocommerce'); ?></strong>
                    <?php
                    echo esc_html(
                        sprintf(
                            /* translators: %d is the number of additional pricing options that still target this product besides the one currently rendered. */
                            _n(
                                'Another %d pricing option still targets this product. Edit it to remove the product from its list.',
                                'Another %d pricing options still target this product. Edit each to remove the product from their lists.',
                                count($extra_options),
                                'storelly-product-builder-for-woocommerce'
                            ),
                            count($extra_options)
                        )
                    );
                    ?>
                </p>
                <ul style="margin:4px 0 0 18px;list-style:disc;">
                    <?php foreach ($extra_options as $extra_option) :
                        $extra_edit_url = add_query_arg(
                            array(
                                'product_id' => $post_id,
                                'action'     => 'edit',
                                'paged'      => 1,
                                'id'         => (int) $extra_option['id'],
                            ),
                            admin_url('admin.php?page=' . SPBWC_PB_BUILDER_SLUG)
                        );
                        ?>
                        <li>
                            <a href="<?php echo esc_url($extra_edit_url); ?>" target="_blank" rel="noopener">
                                <?php echo esc_html($extra_option['title']); ?>
                            </a>
                            <code>#<?php echo (int) $extra_option['id']; ?></code>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
            <p class="storelly-form-field">
                <label>
                    <a href="<?php echo esc_url($link_edit_option); ?>" target="_blank" class="button">
                        <?php if ($option_id != 0) {
                            esc_html_e ('Edit option', 'storelly-product-builder-for-woocommerce');
                        } else {
                            esc_html_e ('Create option', 'storelly-product-builder-for-woocommerce');
                        }; ?>
                    </a>
                </label>
            </p>
        </div>
        <div class="clear"></div>
    </div>
</div>