<?php
/**
 * Products cards partial.
 *
 * Required variables (from caller scope):
 *   $products_query      WP_Query  — have_posts() must be true, internal pointer at start.
 *   $spbwc_product_data  array     — [product_id => ['option_id', 'field_count', 'is_mapped']]
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

while ( $products_query->have_posts() ) :
    $products_query->the_post();
    $spbwc_pid         = get_the_ID();
    $spbwc_data        = isset( $spbwc_product_data[ $spbwc_pid ] )
        ? $spbwc_product_data[ $spbwc_pid ]
        : array( 'option_id' => 0, 'field_count' => 0, 'is_mapped' => false );
    $spbwc_opt_id      = $spbwc_data['option_id'];
    $spbwc_has_opt     = ! empty( $spbwc_opt_id );
    $spbwc_fcount      = (int) $spbwc_data['field_count'];
    $spbwc_status      = $spbwc_has_opt ? 'mapped' : 'unmapped';
    $spbwc_product     = wc_get_product( $spbwc_pid );
    $spbwc_thumb_id    = get_post_thumbnail_id( $spbwc_pid );
    $spbwc_thumb_url   = $spbwc_thumb_id
        ? wp_get_attachment_image_url( $spbwc_thumb_id, 'medium' )
        : wc_placeholder_img_src();
    $spbwc_post_status = get_post_status( $spbwc_pid );
    $spbwc_front_url   = $spbwc_product ? $spbwc_product->get_permalink() : '#';
    $spbwc_opt_link    = add_query_arg(
        array(
            'page'         => SPBWC_PB_BUILDER_SLUG,
            'action'       => $spbwc_has_opt ? 'edit' : 'create',
            'id'           => absint( $spbwc_opt_id ),
            'product_id'   => $spbwc_pid,
            'paged'        => 1,
            'spbwc_return' => 1,
        ),
        admin_url( 'admin.php' )
    );
    ?>
    <div class="spbwc-product-card-wrap" data-status="<?php echo esc_attr( $spbwc_status ); ?>">
        <article class="spbwc-product-card">

            <a class="spbwc-product-card__thumb" href="<?php echo esc_url( $spbwc_front_url ); ?>" target="_blank" rel="noopener" tabindex="-1" aria-hidden="true">
                <img src="<?php echo esc_url( $spbwc_thumb_url ); ?>" alt="<?php echo esc_attr( get_the_title() ); ?>" loading="lazy" />
                <span class="spbwc-product-card__status spbwc-product-card__status--<?php echo esc_attr( $spbwc_status ); ?>">
                    <span class="dashicons <?php echo $spbwc_has_opt ? 'dashicons-yes-alt' : 'dashicons-minus'; ?>" aria-hidden="true"></span>
                    <?php echo $spbwc_has_opt ? esc_html__( 'Has option', 'storelly-product-builder-for-woocommerce' ) : esc_html__( 'No option', 'storelly-product-builder-for-woocommerce' ); ?>
                </span>
                <?php if ( 'publish' !== $spbwc_post_status ) : ?>
                    <span class="spbwc-product-card__post-status spbwc-product-card__post-status--<?php echo esc_attr( $spbwc_post_status ); ?>">
                        <?php echo esc_html( ucfirst( $spbwc_post_status ) ); ?>
                    </span>
                <?php endif; ?>
            </a>

            <div class="spbwc-product-card__body">
                <h3 class="spbwc-product-card__title">
                    <a href="<?php echo esc_url( (string) get_edit_post_link( $spbwc_pid ) ); ?>">
                        <?php echo esc_html( get_the_title() ); ?>
                    </a>
                </h3>
                <div class="spbwc-product-card__meta">
                    <div class="spbwc-product-card__option-row">
                        <span class="spbwc-product-card__option-label">
                            <span class="dashicons dashicons-admin-settings" aria-hidden="true"></span>
                            <?php if ( $spbwc_has_opt ) : ?>
                                <strong>
                                    <?php
                                    /* translators: %d: option ID */
                                    printf( esc_html__( 'Option #%d', 'storelly-product-builder-for-woocommerce' ), absint( $spbwc_opt_id ) );
                                    ?>
                                </strong>
                            <?php else : ?>
                                <?php esc_html_e( 'No option', 'storelly-product-builder-for-woocommerce' ); ?>
                            <?php endif; ?>
                        </span>
                        <?php if ( $spbwc_has_opt && $spbwc_fcount > 0 ) : ?>
                            <span class="spbwc-product-card__field-count" title="<?php esc_attr_e( 'Number of fields in this option', 'storelly-product-builder-for-woocommerce' ); ?>">
                                <span class="dashicons dashicons-list-view" aria-hidden="true"></span>
                                <?php
                                /* translators: %d: number of fields */
                                printf( esc_html__( '%d fields', 'storelly-product-builder-for-woocommerce' ), (int) $spbwc_fcount );
                                ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <span class="spbwc-product-card__id">#<?php echo esc_html( (string) $spbwc_pid ); ?></span>
                </div>
            </div>

            <div class="spbwc-product-card__fields-col">
                <?php if ( $spbwc_has_opt && $spbwc_fcount > 0 ) : ?>
                    <span class="spbwc-product-card__fields-val">
                        <span class="dashicons dashicons-list-view" aria-hidden="true"></span>
                        <?php echo esc_html( (string) $spbwc_fcount ); ?>
                    </span>
                <?php else : ?>
                    <span class="spbwc-product-card__fields-empty">—</span>
                <?php endif; ?>
            </div>

            <div class="spbwc-product-card__sku-col">
                <?php $spbwc_sku = $spbwc_product ? $spbwc_product->get_sku() : ''; ?>
                <?php if ( $spbwc_sku ) : ?>
                    <span class="spbwc-product-card__sku-val"><?php echo esc_html( $spbwc_sku ); ?></span>
                <?php else : ?>
                    <span class="spbwc-product-card__sku-empty">—</span>
                <?php endif; ?>
            </div>

            <footer class="spbwc-product-card__actions">
                <a
                    class="spbwc-product-card__action-icon"
                    href="<?php echo esc_url( (string) get_edit_post_link( $spbwc_pid ) ); ?>"
                    title="<?php esc_attr_e( 'Edit product', 'storelly-product-builder-for-woocommerce' ); ?>"
                >
                    <span class="dashicons dashicons-edit" aria-hidden="true"></span>
                    <span class="screen-reader-text"><?php esc_html_e( 'Edit', 'storelly-product-builder-for-woocommerce' ); ?></span>
                </a>
                <?php if ( $spbwc_product ) : ?>
                    <a
                        class="spbwc-product-card__action-icon"
                        href="<?php echo esc_url( $spbwc_front_url ); ?>"
                        target="_blank" rel="noopener"
                        title="<?php esc_attr_e( 'View product', 'storelly-product-builder-for-woocommerce' ); ?>"
                    >
                        <span class="dashicons dashicons-visibility" aria-hidden="true"></span>
                        <span class="screen-reader-text"><?php esc_html_e( 'View', 'storelly-product-builder-for-woocommerce' ); ?></span>
                    </a>
                <?php endif; ?>
                <button
                    type="button"
                    class="spbwc-product-card__action-icon spbwc-export-ref"
                    data-id="<?php echo esc_attr( (string) $spbwc_pid ); ?>"
                    title="<?php esc_attr_e( 'Export reference data', 'storelly-product-builder-for-woocommerce' ); ?>"
                    style="display:none"
                >
                    <span class="dashicons dashicons-download" aria-hidden="true"></span>
                    <span class="screen-reader-text"><?php esc_html_e( 'Export', 'storelly-product-builder-for-woocommerce' ); ?></span>
                </button>
                <a
                    class="spbwc-product-card__action-primary <?php echo $spbwc_has_opt ? 'spbwc-product-card__action-primary--edit' : 'spbwc-product-card__action-primary--create'; ?>"
                    href="<?php echo esc_url( $spbwc_opt_link ); ?>"
                >
                    <span class="dashicons <?php echo $spbwc_has_opt ? 'dashicons-edit' : 'dashicons-plus-alt2'; ?>" aria-hidden="true"></span>
                    <?php echo $spbwc_has_opt ? esc_html__( 'Edit Option', 'storelly-product-builder-for-woocommerce' ) : esc_html__( 'Create Option', 'storelly-product-builder-for-woocommerce' ); ?>
                </a>
            </footer>

        </article>
    </div>
    <?php
endwhile;
wp_reset_postdata();
