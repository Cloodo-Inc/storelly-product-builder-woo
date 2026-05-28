<?php
/**
 * License Page View
 *
 * Variables available:
 *   $license  (array)           — current license info
 *   $packages (array of arrays) — available plans
 *   $nonce    (string)          — AJAX nonce
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- view template; variables here are local to this included view, not plugin globals.

$current_slug = esc_attr( $license['status'] );
$pkg_name     = esc_html( $license['package_name'] );
$expires_at   = $license['expires_at'] ? esc_html( $license['expires_at'] ) : null;
$synced_at    = $license['synced_at']  ? esc_html( $license['synced_at'] )  : null;
$is_free      = ( $current_slug === 'free' || empty( $current_slug ) );
?>
<div class="wrap spbwc-license-wrap">

    <!-- ── Page hero ── -->
    <header class="spbwc-page-hero">
        <div class="spbwc-page-hero__grid">
            <div class="spbwc-page-hero__body">
                <div class="spbwc-page-hero__eyebrow">
                    <span class="dashicons dashicons-admin-network" aria-hidden="true"></span>
                    <?php esc_html_e( 'Storelly Product Builder', 'storelly-product-builder-for-woocommerce' ); ?>
                </div>
                <h1 class="spbwc-page-hero__title">
                    <span class="dashicons dashicons-awards" aria-hidden="true"></span>
                    <?php esc_html_e( 'License &amp; Plans', 'storelly-product-builder-for-woocommerce' ); ?>
                </h1>
                <p class="spbwc-page-hero__subtitle">
                    <?php esc_html_e( 'Manage your Storelly subscription, compare plans and unlock premium features for your print store.', 'storelly-product-builder-for-woocommerce' ); ?>
                </p>
            </div>
            <div class="spbwc-page-hero__actions">
                <a href="https://storelly.com/docs" target="_blank" rel="noopener noreferrer"
                   class="spbwc-cta-btn spbwc-cta-btn--ghost">
                    <span class="dashicons dashicons-book-alt" aria-hidden="true"></span>
                    <?php esc_html_e( 'Documentation', 'storelly-product-builder-for-woocommerce' ); ?>
                </a>
                <?php if ( $is_free ) : ?>
                <a href="<?php echo esc_url( rtrim( SPBWC_API_URL, '/' ) . '/subscription' ); ?>"
                   target="_blank" rel="noopener noreferrer"
                   class="spbwc-cta-btn spbwc-cta-btn--solid">
                    <span class="dashicons dashicons-cart" aria-hidden="true"></span>
                    <?php esc_html_e( 'Upgrade Now', 'storelly-product-builder-for-woocommerce' ); ?>
                </a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- ── Current plan hero card ── -->
    <section class="spbwc-license-hero<?php echo $is_free ? '' : ' spbwc-license-hero--premium'; ?>"
             style="margin-bottom: 32px;">
        <div class="spbwc-license-hero__grid">
            <div class="spbwc-license-hero__info">
                <div class="spbwc-license-hero__eyebrow">
                    <span class="dashicons dashicons-admin-network" aria-hidden="true"></span>
                    <?php esc_html_e( 'Your Current Plan', 'storelly-product-builder-for-woocommerce' ); ?>
                </div>
                <div class="spbwc-license-hero__title">
                    <span class="spbwc-license-hero__pkg">
                        <?php echo esc_html( $pkg_name ); ?>
                    </span>
                    <span class="spbwc-license-hero__badge">
                        <?php echo esc_html( strtoupper( $current_slug ) ); ?>
                    </span>
                </div>
                <div class="spbwc-license-hero__meta">
                    <span class="dashicons dashicons-calendar-alt" aria-hidden="true"></span>
                    <?php if ( $expires_at ) : ?>
                        <?php
                        printf(
                            /* translators: %s: expiry date */
                            esc_html__( 'License expires: %s', 'storelly-product-builder-for-woocommerce' ),
                            esc_html( $expires_at )
                        );
                        ?>
                    <?php elseif ( ! $is_free ) : ?>
                        <?php esc_html_e( 'Lifetime license — no expiry', 'storelly-product-builder-for-woocommerce' ); ?>
                    <?php else : ?>
                        <?php esc_html_e( 'Free plan — limited features', 'storelly-product-builder-for-woocommerce' ); ?>
                    <?php endif; ?>
                    <?php if ( $synced_at ) : ?>
                        &nbsp;·&nbsp;
                        <?php
                        printf(
                            /* translators: %s: last sync datetime */
                            esc_html__( 'Last synced: %s', 'storelly-product-builder-for-woocommerce' ),
                            esc_html( $synced_at )
                        );
                        ?>
                    <?php endif; ?>
                </div>
            </div>
            <div class="spbwc-license-hero__actions">
                <?php if ( $is_free ) : ?>
                <a href="<?php echo esc_url( rtrim( SPBWC_API_URL, '/' ) . '/subscription' ); ?>"
                   target="_blank" rel="noopener noreferrer"
                   class="spbwc-btn-upgrade">
                    <span class="dashicons dashicons-cart" aria-hidden="true"></span>
                    <?php esc_html_e( 'Upgrade Plan', 'storelly-product-builder-for-woocommerce' ); ?>
                </a>
                <?php else : ?>
                <a href="<?php echo esc_url( rtrim( SPBWC_API_URL, '/' ) . '/subscription' ); ?>"
                   target="_blank" rel="noopener noreferrer"
                   class="spbwc-btn-upgrade">
                    <span class="dashicons dashicons-admin-network" aria-hidden="true"></span>
                    <?php esc_html_e( 'Manage Subscription', 'storelly-product-builder-for-woocommerce' ); ?>
                </a>
                <?php endif; ?>
                <button id="spbwc-sync-btn" type="button" class="spbwc-btn-sync"
                        data-nonce="<?php echo esc_attr( $nonce ); ?>">
                    <span class="dashicons dashicons-update" aria-hidden="true"></span>
                    <?php esc_html_e( 'Sync License', 'storelly-product-builder-for-woocommerce' ); ?>
                </button>
                <p class="spbwc-license-hero__last-sync" id="spbwc-sync-status"></p>
            </div>
        </div>
    </section>

    <!-- ── Available Plans ── -->
    <section class="spbwc-section">
        <div class="spbwc-section__header">
            <h2 class="spbwc-section__title">
                <span class="dashicons dashicons-star-filled" aria-hidden="true"></span>
                <?php esc_html_e( 'Available Plans', 'storelly-product-builder-for-woocommerce' ); ?>
            </h2>
            <p class="spbwc-section__subtitle">
                <?php esc_html_e( 'Choose the plan that fits your print business. Upgrade anytime.', 'storelly-product-builder-for-woocommerce' ); ?>
            </p>
        </div>

        <div class="spbwc-pricing-grid">
            <?php foreach ( $packages as $pkg ) :
                $is_current = ( $current_slug === $pkg['slug'] );
                $head_class = $is_current ? 'spbwc-pkg-head--brand' : 'spbwc-pkg-head--plain';
                $card_class = $is_current ? 'spbwc-pkg-card is-current' : 'spbwc-pkg-card';
            ?>
            <div class="<?php echo esc_attr( $card_class ); ?>">
                <div class="spbwc-pkg-head <?php echo esc_attr( $head_class ); ?>">
                    <?php if ( $is_current ) : ?>
                    <div class="spbwc-pkg-current-tag">
                        ⭐ <?php esc_html_e( 'Current Plan', 'storelly-product-builder-for-woocommerce' ); ?>
                    </div>
                    <?php endif; ?>
                    <p class="spbwc-pkg-name"><?php echo esc_html( $pkg['name'] ); ?></p>
                    <p class="spbwc-pkg-price <?php echo $pkg['price'] > 0 ? 'spbwc-pkg-price--paid' : 'spbwc-pkg-price--free'; ?>">
                        <?php echo $pkg['price'] > 0
                            ? '$' . esc_html( number_format( $pkg['price'], 0 ) )
                            : esc_html__( 'Free', 'storelly-product-builder-for-woocommerce' );
                        ?>
                    </p>
                    <?php if ( $pkg['price'] > 0 ) : ?>
                    <p class="spbwc-pkg-cycle">
                        <?php echo esc_html( '/ ' . $pkg['billing_cycle'] ); ?>
                    </p>
                    <?php endif; ?>
                    <p class="spbwc-pkg-desc"><?php echo esc_html( $pkg['description'] ); ?></p>
                </div>

                <div class="spbwc-pkg-body">
                    <ul class="spbwc-pkg-features">
                        <?php foreach ( (array) $pkg['features'] as $feat ) : ?>
                        <li>
                            <span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
                            <?php echo esc_html( $feat ); ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>

                    <div class="spbwc-pkg-limits">
                        <div class="spbwc-pkg-limits__row">
                            <span class="spbwc-pkg-limits__label"><?php esc_html_e( 'Products', 'storelly-product-builder-for-woocommerce' ); ?></span>
                            <strong class="spbwc-pkg-limits__value">
                                <?php echo $pkg['max_products'] > 0 ? esc_html( (string) $pkg['max_products'] ) : '∞'; ?>
                            </strong>
                        </div>
                        <div class="spbwc-pkg-limits__row">
                            <span class="spbwc-pkg-limits__label"><?php esc_html_e( 'Orders', 'storelly-product-builder-for-woocommerce' ); ?></span>
                            <strong class="spbwc-pkg-limits__value">
                                <?php echo $pkg['max_orders'] > 0 ? esc_html( (string) $pkg['max_orders'] ) : '∞'; ?>
                            </strong>
                        </div>
                        <div class="spbwc-pkg-limits__row">
                            <span class="spbwc-pkg-limits__label"><?php esc_html_e( 'Pricing Options', 'storelly-product-builder-for-woocommerce' ); ?></span>
                            <strong class="spbwc-pkg-limits__value">
                                <?php echo $pkg['max_pricing_options'] > 0 ? esc_html( (string) $pkg['max_pricing_options'] ) : '∞'; ?>
                            </strong>
                        </div>
                    </div>

                    <?php if ( $is_current ) : ?>
                    <span class="spbwc-pkg-cta spbwc-pkg-cta--current">
                        <span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
                        <?php esc_html_e( 'Current Plan', 'storelly-product-builder-for-woocommerce' ); ?>
                    </span>
                    <?php elseif ( $pkg['price'] > 0 ) : ?>
                    <a class="spbwc-pkg-cta spbwc-pkg-cta--upgrade"
                       href="<?php echo esc_url( rtrim( SPBWC_API_URL, '/' ) . '/subscription' ); ?>"
                       target="_blank" rel="noopener noreferrer">
                        <span class="dashicons dashicons-cart" aria-hidden="true"></span>
                        <?php
                        printf(
                            /* translators: %s: plan name */
                            esc_html__( 'Upgrade to %s', 'storelly-product-builder-for-woocommerce' ),
                            esc_html( $pkg['name'] )
                        );
                        ?>
                    </a>
                    <?php else : ?>
                    <span class="spbwc-pkg-cta spbwc-pkg-cta--disabled">
                        <?php esc_html_e( 'Free — No payment needed', 'storelly-product-builder-for-woocommerce' ); ?>
                    </span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- ── Manage subscription card ── -->
    <div class="spbwc-activate-card">
        <h3 class="spbwc-activate-card__title">
            <span class="dashicons dashicons-admin-network" aria-hidden="true"></span>
            <?php esc_html_e( 'Manage License Subscriptions', 'storelly-product-builder-for-woocommerce' ); ?>
        </h3>
        <p class="spbwc-activate-card__desc">
            <?php esc_html_e( 'Your license is managed directly via the Storelly Dashboard. To purchase a plan or update your active subscriptions, go to your Storelly portal.', 'storelly-product-builder-for-woocommerce' ); ?>
        </p>
        <a href="<?php echo esc_url( rtrim( SPBWC_API_URL, '/' ) . '/subscription' ); ?>"
           target="_blank" rel="noopener noreferrer"
           class="spbwc-cta-btn spbwc-cta-btn--solid">
            <span class="dashicons dashicons-external" aria-hidden="true"></span>
            <?php esc_html_e( 'Go to Storelly Dashboard', 'storelly-product-builder-for-woocommerce' ); ?>
        </a>
    </div>

    <!-- ── Help section ── -->
    <div class="spbwc-help-section" style="margin-top: 32px;">
        <div class="spbwc-help-section__body">
            <h3 class="spbwc-help-section__title">
                <span class="dashicons dashicons-sos" aria-hidden="true"></span>
                <?php esc_html_e( 'Need help with your license?', 'storelly-product-builder-for-woocommerce' ); ?>
            </h3>
            <p class="spbwc-help-section__text">
                <?php esc_html_e( 'Having trouble with activation or billing? Our support team is ready to help.', 'storelly-product-builder-for-woocommerce' ); ?>
            </p>
        </div>
        <div class="spbwc-help-section__links">
            <a href="https://storelly.com/docs" target="_blank" rel="noopener noreferrer"
               class="spbwc-cta-btn spbwc-cta-btn--ghost">
                <span class="dashicons dashicons-book-alt" aria-hidden="true"></span>
                <?php esc_html_e( 'Documentation', 'storelly-product-builder-for-woocommerce' ); ?>
            </a>
            <a href="https://storelly.com/support" target="_blank" rel="noopener noreferrer"
               class="spbwc-cta-btn spbwc-cta-btn--ghost">
                <span class="dashicons dashicons-email-alt" aria-hidden="true"></span>
                <?php esc_html_e( 'Contact Support', 'storelly-product-builder-for-woocommerce' ); ?>
            </a>
        </div>
    </div>

</div><!-- .spbwc-license-wrap -->

<script>
(function($){
    var $syncBtn    = $('#spbwc-sync-btn');
    var $syncStatus = $('#spbwc-sync-status');
    var syncBtnHtml = $syncBtn.html();

    $syncBtn.on('click', function(){
        if ( $syncBtn.hasClass('is-loading') ) return;
        $syncBtn.addClass('is-loading').prop('disabled', true);
        $syncStatus.text('');

        $.post(ajaxurl, {
            action: 'spbwc_license_sync',
            nonce: $syncBtn.data('nonce')
        }, function(res){
            if (res.success) {
                location.reload();
            } else {
                var msg = (res.data && res.data.msg) ? res.data.msg : '<?php echo esc_js( __( 'Sync failed.', 'storelly-product-builder-for-woocommerce' ) ); ?>';
                $syncStatus.text(msg);
                $syncBtn.removeClass('is-loading').prop('disabled', false).html(syncBtnHtml);
            }
        }).fail(function(){
            $syncStatus.text('<?php echo esc_js( __( 'Request failed. Check your connection.', 'storelly-product-builder-for-woocommerce' ) ); ?>');
            $syncBtn.removeClass('is-loading').prop('disabled', false).html(syncBtnHtml);
        });
    });
})(jQuery);
</script>
