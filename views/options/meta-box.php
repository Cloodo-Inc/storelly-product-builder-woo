<?php
/**
 * Storelly product builder metabox on the WooCommerce product edit screen.
 *
 * Control panel for the product's linked pricing option — see
 * docs/SPEC_LINKED_PRODUCT_UX.md. Variables provided by spbwc_meta_box():
 *   $post_id, $nbdpb_enable, $spbwc_enable_quote, $spbwc_quote_display_mode,
 *   $option_id, $option_title, $field_count, $has_designer, $field_chips,
 *   $field_overflow, $display_label, $shared_products, $shared_count,
 *   $swap_options, $spbwc_link_nonce, $extra_options, $link_edit_option.
 */
if (!defined('ABSPATH')) {
    exit;
}
?>
<div id="storelly-wraper" class="spbwc-lp<?php echo empty($nbdpb_enable) ? ' is-builder-off' : ''; ?>"
     data-product="<?php echo esc_attr((string) $post_id); ?>"
     data-nonce="<?php echo esc_attr($spbwc_link_nonce); ?>">
    <?php wp_nonce_field('pc_box', 'pc_box_nonce'); ?>

    <p class="spbwc-lp__toggle storelly-form-field">
        <label for="_storelly_pb_enable">
            <input type="hidden" value="0" name="_storelly_pb_enable" />
            <input type="checkbox" value="1" name="_storelly_pb_enable" id="_storelly_pb_enable" <?php checked($nbdpb_enable); ?> class="short" data-spbwc-builder-toggle />
            <?php esc_html_e('Enable product builder', 'storelly-product-builder-for-woocommerce'); ?>
        </label>
    </p>

    <?php if ($option_id > 0) : ?>
        <div class="spbwc-lp-card">
            <div class="spbwc-lp-card__head">
                <span class="dashicons dashicons-admin-settings spbwc-lp-card__icon" aria-hidden="true"></span>
                <span class="spbwc-lp-card__title"><?php echo esc_html($option_title ? $option_title : sprintf(/* translators: %d: option id */ esc_html__('Option #%d', 'storelly-product-builder-for-woocommerce'), $option_id)); ?></span>
                <code class="spbwc-lp-card__id">#<?php echo esc_html((string) $option_id); ?></code>
            </div>

            <div class="spbwc-lp-card__summary">
                <span class="spbwc-lp-meta">
                    <span class="dashicons dashicons-list-view" aria-hidden="true"></span>
                    <?php
                    /* translators: %d: number of fields in the option */
                    printf(esc_html(_n('%d field', '%d fields', $field_count, 'storelly-product-builder-for-woocommerce')), (int) $field_count);
                    ?>
                </span>
                <?php if ($has_designer) : ?>
                    <span class="spbwc-lp-meta spbwc-lp-meta--designer">
                        <span class="dashicons dashicons-art" aria-hidden="true"></span>
                        <?php esc_html_e('Designer', 'storelly-product-builder-for-woocommerce'); ?>
                    </span>
                <?php endif; ?>
                <span class="spbwc-lp-meta">
                    <span class="dashicons dashicons-cart" aria-hidden="true"></span>
                    <?php echo esc_html($display_label); ?>
                </span>
            </div>

            <?php if (!empty($field_chips)) : ?>
                <div class="spbwc-lp-chips">
                    <?php foreach ($field_chips as $chip) : ?>
                        <span class="spbwc-lp-chip"><?php echo esc_html($chip); ?></span>
                    <?php endforeach; ?>
                    <?php if ($field_overflow > 0) : ?>
                        <span class="spbwc-lp-chip spbwc-lp-chip--more">+<?php echo esc_html((string) $field_overflow); ?></span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($shared_count > 0) : ?>
                <div class="spbwc-lp-shared">
                    <button type="button" class="spbwc-lp-shared__badge" aria-expanded="false" data-spbwc-shared-toggle>
                        <span class="dashicons dashicons-warning" aria-hidden="true"></span>
                        <?php
                        /* translators: %d: number of other products sharing this option */
                        printf(esc_html(_n('Shared by %d other product', 'Shared by %d other products', $shared_count, 'storelly-product-builder-for-woocommerce')), (int) $shared_count);
                        ?>
                        <span class="dashicons dashicons-arrow-down-alt2 spbwc-lp-shared__caret" aria-hidden="true"></span>
                    </button>
                    <ul class="spbwc-lp-shared__list" hidden>
                        <?php foreach ($shared_products as $shared) : ?>
                            <li>
                                <a href="<?php echo esc_url($shared['link']); ?>">
                                    <?php echo esc_html($shared['title'] ? $shared['title'] : sprintf(/* translators: %d: product id */ esc_html__('Product #%d', 'storelly-product-builder-for-woocommerce'), $shared['id'])); ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="spbwc-lp-card__actions">
                <a href="<?php echo esc_url($link_edit_option); ?>"
                   class="button button-primary spbwc-lp-action spbwc-lp-action--edit"
                   data-spbwc-edit-fields
                   data-shared="<?php echo esc_attr((string) $shared_count); ?>">
                    <span class="dashicons dashicons-edit" aria-hidden="true"></span>
                    <?php esc_html_e('Edit fields', 'storelly-product-builder-for-woocommerce'); ?>
                </a>

                <?php if (!empty($swap_options)) : ?>
                    <span class="spbwc-lp-swap">
                        <label class="screen-reader-text" for="spbwc-lp-swap-select"><?php esc_html_e('Swap mapped option', 'storelly-product-builder-for-woocommerce'); ?></label>
                        <select id="spbwc-lp-swap-select" class="spbwc-lp-swap__select" data-spbwc-swap>
                            <option value=""><?php esc_html_e('Swap option…', 'storelly-product-builder-for-woocommerce'); ?></option>
                            <?php foreach ($swap_options as $swap) : ?>
                                <option value="<?php echo esc_attr((string) $swap['id']); ?>"><?php echo esc_html($swap['title']); ?> (#<?php echo esc_html((string) $swap['id']); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </span>
                <?php endif; ?>

                <button type="button" class="button-link spbwc-lp-action spbwc-lp-action--unlink" data-spbwc-unlink>
                    <span class="dashicons dashicons-editor-unlink" aria-hidden="true"></span>
                    <?php esc_html_e('Unlink', 'storelly-product-builder-for-woocommerce'); ?>
                </button>
            </div>
        </div>
    <?php else : ?>
        <div class="spbwc-lp-empty">
            <p class="spbwc-lp-empty__text"><?php esc_html_e('No pricing option is linked to this product yet.', 'storelly-product-builder-for-woocommerce'); ?></p>
            <a href="<?php echo esc_url($link_edit_option); ?>" class="button button-primary spbwc-lp-action">
                <span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
                <?php esc_html_e('Create option for this product', 'storelly-product-builder-for-woocommerce'); ?>
            </a>
        </div>
    <?php endif; ?>

    <?php
    // Defensive warning: legacy data may still have more than one product-level
    // option listing this product. See docs/SPEC_PRICING_OPTION_ASSIGNMENT.md §3.5.
    if (!empty($extra_options) && is_array($extra_options)) :
    ?>
        <div class="spbwc-lp-notice notice notice-warning inline">
            <p>
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
            <ul>
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

    <div class="spbwc-lp-quote<?php echo empty($spbwc_enable_quote) ? ' is-quote-off' : ''; ?>" data-spbwc-quote>
        <p class="spbwc-lp-quote__head"><?php esc_html_e('Request a quote', 'storelly-product-builder-for-woocommerce'); ?></p>
        <p class="storelly-form-field">
            <label for="_spbwc_enable_quote">
                <input type="hidden" value="0" name="_spbwc_enable_quote" />
                <input type="checkbox" value="1" name="_spbwc_enable_quote" id="_spbwc_enable_quote" <?php checked(!empty($spbwc_enable_quote)); ?> class="short" data-spbwc-quote-toggle />
                <?php esc_html_e('Enable request quote', 'storelly-product-builder-for-woocommerce'); ?>
            </label>
        </p>
        <p class="storelly-form-field spbwc-lp-quote__mode">
            <label for="_spbwc_quote_display_mode"><?php esc_html_e('Quote display', 'storelly-product-builder-for-woocommerce'); ?></label>
            <select name="_spbwc_quote_display_mode" id="_spbwc_quote_display_mode" class="short">
                <option value="" <?php selected(empty($spbwc_quote_display_mode)); ?>><?php esc_html_e('Use global default', 'storelly-product-builder-for-woocommerce'); ?></option>
                <option value="both" <?php selected(isset($spbwc_quote_display_mode) ? $spbwc_quote_display_mode : '', 'both'); ?>><?php esc_html_e('Add to cart + Get Quote', 'storelly-product-builder-for-woocommerce'); ?></option>
                <option value="replace" <?php selected(isset($spbwc_quote_display_mode) ? $spbwc_quote_display_mode : '', 'replace'); ?>><?php esc_html_e('Get Quote replaces Add to cart (keep price)', 'storelly-product-builder-for-woocommerce'); ?></option>
                <option value="quote_only" <?php selected(isset($spbwc_quote_display_mode) ? $spbwc_quote_display_mode : '', 'quote_only'); ?>><?php esc_html_e('Quote only — hide price &amp; cart', 'storelly-product-builder-for-woocommerce'); ?></option>
            </select>
            <span class="description"><?php esc_html_e('Only applies when request quote is enabled above.', 'storelly-product-builder-for-woocommerce'); ?></span>
        </p>
    </div>
</div>
