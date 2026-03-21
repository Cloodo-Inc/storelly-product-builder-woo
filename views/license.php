<?php
/**
 * License Page View
 * Variables available: $license (array), $packages (array of packages), $nonce (string)
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$current_slug = esc_attr( $license['status'] );
$pkg_name     = esc_html( $license['package_name'] );
$expires_at   = $license['expires_at'] ? esc_html( $license['expires_at'] ) : null;
$synced_at    = $license['synced_at'] ? esc_html( $license['synced_at'] ) : null;
?>
<div class="wrap spbwc-license-wrap" style="max-width:1200px;">
    <h1 style="font-size:26px;font-weight:800;color:#1d2327;margin-bottom:24px;display:flex;align-items:center;gap:10px;">
        <span class="dashicons dashicons-admin-network" style="font-size:28px;color:#667eea;"></span>
        <?php esc_html_e( 'License', 'storelly-product-builder-for-woocommerce' ); ?>
    </h1>

    <style>
    .spbwc-license-wrap { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }

    /* --- Current plan card --- */
    .spbwc-current-plan {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 16px; padding: 28px 32px; color: #fff;
        margin-bottom: 32px; display: flex; gap: 20px; align-items: center;
        flex-wrap: wrap; box-shadow: 0 8px 28px rgba(102,126,234,0.35);
    }
    .spbwc-current-plan .plan-badge {
        background: rgba(255,255,255,0.2); border-radius: 20px;
        padding: 4px 18px; font-weight: 800; font-size: 16px; text-transform: uppercase;
    }
    .spbwc-current-plan .plan-detail { font-size: 13px; opacity: .8; margin-top: 5px; }

    /* --- Pricing grid --- */
    .spbwc-pricing-grid {
        display: grid; grid-template-columns: repeat(auto-fill, minmax(230px, 1fr)); gap: 20px;
        margin-bottom: 40px;
    }
    .spbwc-pkg-card {
        background: #fff; border-radius: 16px; overflow: hidden;
        border: 2px solid #eef0f5;
        box-shadow: 0 2px 12px rgba(60,80,150,0.08);
        transition: transform .2s, box-shadow .2s;
        display: flex; flex-direction: column;
    }
    .spbwc-pkg-card:hover { transform: translateY(-4px); box-shadow: 0 12px 32px rgba(102,126,234,0.18); }
    .spbwc-pkg-card.is-current { border-color: #667eea; transform: translateY(-6px); box-shadow: 0 14px 36px rgba(102,126,234,0.28); }

    .spbwc-pkg-head { padding: 24px 20px 18px; text-align: center; }
    .spbwc-pkg-head.grad { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; }
    .spbwc-pkg-head.plain { background: #f8f9fc; color: #1d2327; }

    .spbwc-pkg-name { font-size: 20px; font-weight: 800; margin: 0 0 8px; }
    .spbwc-pkg-price { font-size: 36px; font-weight: 900; line-height: 1; }
    .spbwc-pkg-cycle { font-size: 13px; opacity: .7; }
    .spbwc-pkg-desc  { font-size: 12px; margin-top: 8px; opacity: .75; }

    .spbwc-pkg-body { padding: 18px 20px; flex: 1; display: flex; flex-direction: column; }
    .spbwc-pkg-features { list-style: none; padding: 0; margin: 0 0 14px; flex: 1; }
    .spbwc-pkg-features li {
        padding: 6px 0; border-bottom: 1px solid #f5f5f5;
        font-size: 13px; color: #555; display: flex; align-items: flex-start; gap: 6px;
    }
    .spbwc-pkg-features li .dashicons { color: #667eea; font-size: 16px; flex-shrink: 0; margin-top: 1px; }

    .spbwc-pkg-limits {
        background: #f8f9fc; border-radius: 8px; padding: 10px 14px;
        margin-bottom: 16px; font-size: 12px;
    }
    .spbwc-pkg-limits div { display: flex; justify-content: space-between; margin-bottom: 4px; }
    .spbwc-pkg-limits div:last-child { margin-bottom: 0; }
    .spbwc-pkg-limits span { color: #888; }
    .spbwc-pkg-limits strong { color: #1d2327; }

    /* Buttons */
    .spbwc-btn-cta {
        display: block; text-align: center; border-radius: 10px; padding: 11px 16px;
        font-weight: 700; font-size: 14px; border: none; cursor: pointer; text-decoration: none;
        transition: all .2s;
    }
    .spbwc-btn-cta.primary { background: linear-gradient(135deg,#667eea,#764ba2); color: #fff; }
    .spbwc-btn-cta.primary:hover { filter: brightness(1.1); color: #fff; }
    .spbwc-btn-cta.current { background: #e8ecff; color: #667eea; cursor: default; }
    .spbwc-btn-cta.disabled { background: #f0f0f0; color: #aaa; cursor: default; }

    /* --- Activate form --- */
    .spbwc-activate-card {
        background: #fff; border-radius: 16px; padding: 28px 32px;
        border: 1px solid #eef0f5; box-shadow: 0 2px 12px rgba(60,80,150,0.07);
        max-width: 560px; margin-bottom: 24px;
    }
    .spbwc-activate-card h3 { margin: 0 0 8px; font-size: 18px; font-weight: 800; color: #1d2327; }
    .spbwc-activate-card p { color: #888; font-size: 13px; margin: 0 0 18px; }
    .spbwc-activate-row { display: flex; gap: 8px; }
    .spbwc-activate-row input {
        flex: 1; padding: 10px 14px; border: 1.5px solid #d0d7de;
        border-radius: 8px; font-size: 14px; font-family: monospace; letter-spacing: 1px;
        transition: border-color .2s;
    }
    .spbwc-activate-row input:focus { border-color: #667eea; outline: none; box-shadow: 0 0 0 3px rgba(102,126,234,0.12); }
    #spbwc-activate-btn {
        background: linear-gradient(135deg,#667eea,#764ba2); color: #fff; border: none;
        border-radius: 8px; padding: 10px 22px; font-weight: 700; font-size: 14px;
        cursor: pointer; white-space: nowrap; transition: filter .2s;
    }
    #spbwc-activate-btn:hover { filter: brightness(1.1); }
    #spbwc-license-msg { margin-top: 12px; display: none; }
    </style>

    <!-- Current Plan Banner -->
    <div class="spbwc-current-plan">
        <div style="flex:1;">
            <div style="font-size:13px;opacity:.8;margin-bottom:4px;">
                <?php esc_html_e( 'Your Current Plan', 'storelly-product-builder-for-woocommerce' ); ?>
            </div>
            <div style="font-size:24px;font-weight:800;display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                <span class="dashicons dashicons-yes-alt" style="font-size:28px;"></span>
                <?php echo esc_html( $pkg_name ); ?>
                <span class="plan-badge"><?php echo esc_html( $current_slug ); ?></span>
            </div>
            <div class="plan-detail">
                <?php if ( $expires_at ) : ?>
                    <?php
                    printf(
                        /* translators: %s: expiry date */
                        esc_html__( 'License expires: %s', 'storelly-product-builder-for-woocommerce' ),
                        esc_html( $expires_at )
                    );
                    ?>
                <?php elseif ( $current_slug !== 'free' ) : ?>
                    <?php esc_html_e( 'Lifetime license – no expiry', 'storelly-product-builder-for-woocommerce' ); ?>
                <?php else : ?>
                    <?php esc_html_e( 'Free plan – limited features', 'storelly-product-builder-for-woocommerce' ); ?>
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
        <div>
            <button id="spbwc-sync-btn"
                    style="background:rgba(255,255,255,.15);color:#fff;border:1px solid rgba(255,255,255,.4);border-radius:8px;padding:9px 18px;font-size:13px;cursor:pointer;"
                    data-nonce="<?php echo esc_attr( $nonce ); ?>">
                <span class="dashicons dashicons-update" style="font-size:14px;vertical-align:middle;"></span>
                <?php esc_html_e( 'Sync License', 'storelly-product-builder-for-woocommerce' ); ?>
            </button>
        </div>
    </div>

    <!-- Pricing Packages Grid -->
    <h3 style="font-size:16px;font-weight:700;color:#374151;margin-bottom:16px;">
        <?php esc_html_e( 'Available Plans', 'storelly-product-builder-for-woocommerce' ); ?>
    </h3>
    <div class="spbwc-pricing-grid">
        <?php foreach ( $packages as $pkg ) :
            $is_current = ( $current_slug === $pkg['slug'] );
            $head_class = $is_current ? 'grad' : 'plain';
            $price_clr  = $is_current ? '#fff' : ( $pkg['is_free'] ? '#28a745' : '#667eea' );
            $card_class = $is_current ? 'spbwc-pkg-card is-current' : 'spbwc-pkg-card';
        ?>
        <div class="<?php echo esc_attr( $card_class ); ?>">
            <!-- Header -->
            <div class="spbwc-pkg-head <?php echo esc_attr( $head_class ); ?>">
                <?php if ( $is_current ) : ?>
                    <div style="font-size:11px;background:rgba(255,255,255,.2);border-radius:12px;padding:2px 12px;display:inline-block;margin-bottom:6px;">
                        ⭐ <?php esc_html_e( 'Current Plan', 'storelly-product-builder-for-woocommerce' ); ?>
                    </div><br>
                <?php endif; ?>
                <div class="spbwc-pkg-name"><?php echo esc_html( $pkg['name'] ); ?></div>
                <div class="spbwc-pkg-price" style="color:<?php echo esc_attr( $price_clr ); ?>;">
                    <?php echo $pkg['price'] > 0 ? '$' . esc_html( number_format( $pkg['price'], 0 ) ) : esc_html__( 'Free', 'storelly-product-builder-for-woocommerce' ); ?>
                </div>
                <?php if ( $pkg['price'] > 0 ) : ?>
                    <div class="spbwc-pkg-cycle">
                        <?php echo esc_html( '/ ' . $pkg['billing_cycle'] ); ?>
                    </div>
                <?php endif; ?>
                <div class="spbwc-pkg-desc"><?php echo esc_html( $pkg['description'] ); ?></div>
            </div>

            <!-- Body -->
            <div class="spbwc-pkg-body">
                <ul class="spbwc-pkg-features">
                    <?php foreach ( (array) $pkg['features'] as $feat ) : ?>
                        <li>
                            <span class="dashicons dashicons-yes-alt"></span>
                            <?php echo esc_html( $feat ); ?>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <div class="spbwc-pkg-limits">
                    <div>
                        <span><?php esc_html_e( 'Products', 'storelly-product-builder-for-woocommerce' ); ?></span>
                        <strong><?php echo $pkg['max_products'] > 0 ? esc_html( (string) $pkg['max_products'] ) : '∞'; ?></strong>
                    </div>
                    <div>
                        <span><?php esc_html_e( 'Orders', 'storelly-product-builder-for-woocommerce' ); ?></span>
                        <strong><?php echo $pkg['max_orders'] > 0 ? esc_html( (string) $pkg['max_orders'] ) : '∞'; ?></strong>
                    </div>
                    <div>
                        <span><?php esc_html_e( 'Pricing Options', 'storelly-product-builder-for-woocommerce' ); ?></span>
                        <strong><?php echo $pkg['max_pricing_options'] > 0 ? esc_html( (string) $pkg['max_pricing_options'] ) : '∞'; ?></strong>
                    </div>
                </div>

                <?php if ( $is_current ) : ?>
                    <span class="spbwc-btn-cta current">
                        ✔ <?php esc_html_e( 'Current Plan', 'storelly-product-builder-for-woocommerce' ); ?>
                    </span>
                <?php elseif ( $pkg['price'] > 0 ) : ?>
                    <a class="spbwc-btn-cta primary"
                       href="<?php echo esc_url( rtrim( SPBWC_API_URL, '/' ) . '/subscription' ); ?>"
                       target="_blank" rel="noopener noreferrer">
                        🛒 <?php
                            printf(
                                /* translators: %s: plan name */
                                esc_html__( 'Upgrade to %s', 'storelly-product-builder-for-woocommerce' ),
                                esc_html( $pkg['name'] )
                            );
                        ?>
                    </a>
                <?php else : ?>
                    <span class="spbwc-btn-cta disabled">
                        <?php esc_html_e( 'Free – No payment needed', 'storelly-product-builder-for-woocommerce' ); ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Manage License Subscription -->
    <div class="spbwc-activate-card">
        <h3>
            <span class="dashicons dashicons-admin-network" style="font-size:22px;color:#667eea;vertical-align:bottom;"></span>
            <?php esc_html_e( 'Manage License Subscriptions', 'storelly-product-builder-for-woocommerce' ); ?>
        </h3>
        <p>
            <?php esc_html_e( 'Your license is managed directly via the Storelly Dashboard. To purchase a plan or update your active subscriptions, go to your Storelly portal.', 'storelly-product-builder-for-woocommerce' ); ?>
        </p>
        <div class="spbwc-activate-row">
            <a href="<?php echo esc_url( rtrim( SPBWC_API_URL, '/' ) . '/subscription' ); ?>" 
               target="_blank" 
               class="spbwc-btn-cta primary" 
               style="background: linear-gradient(135deg,#667eea,#764ba2); color: #fff; padding: 12px 24px; text-decoration: none; border-radius: 8px; font-weight: bold; display: inline-block;">
                <?php esc_html_e( 'Go to Storelly Dashboard', 'storelly-product-builder-for-woocommerce' ); ?> &rarr;
            </a>
        </div>
    </div>

    <script>
    (function($){
        // Sync license
        $('#spbwc-sync-btn').on('click', function(){
            var $btn = $(this);
            $btn.prop('disabled', true);
            $.post(ajaxurl, {
                action: 'spbwc_license_sync',
                nonce:  $btn.data('nonce')
            }, function(res){
                if (res.success) { location.reload(); }
                else {
                    alert((res.data && res.data.msg) ? res.data.msg : 'Sync failed.');
                    $btn.prop('disabled', false);
                }
            }).fail(function(){
                alert('<?php echo esc_js( __( 'Request failed. Check your connection.', 'storelly-product-builder-for-woocommerce' ) ); ?>');
                $btn.prop('disabled', false);
            });
        });
    })(jQuery);
    </script>
</div>
