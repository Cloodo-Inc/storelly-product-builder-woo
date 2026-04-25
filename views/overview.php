<?php
/**
 * Overview Page View
 * Variables available: $total_products, $total_pricing, $total_orders, $total_quotes, $remote_stats, $license
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Determine which counts to use (prefer remote API when available)
$is_remote_ok   = ! is_wp_error( $remote_stats );
$disp_products  = $is_remote_ok ? $remote_stats['total_products'] : $total_products;
$disp_orders    = $is_remote_ok ? $remote_stats['total_orders'] : $total_orders;
$disp_quotes    = $is_remote_ok ? $remote_stats['total_quotes'] : $total_quotes;
$disp_pricing   = $total_pricing; // Always from local DB

$pkg_name       = esc_html( $license['package_name'] );
$pkg_slug       = esc_attr( $license['status'] );
$expires_at     = $license['expires_at'] ? esc_html( $license['expires_at'] ) : null;
$license_url    = admin_url( 'admin.php?page=' . SPBWC_PB_LICENSE_SLUG );
$nonce_license  = wp_create_nonce( 'spbwc_license_action' );
?>
<div class="wrap spbwc-overview-wrap" style="max-width:1200px;">
    <h1 style="font-size:26px;font-weight:800;color:#1d2327;margin-bottom:24px;display:flex;align-items:center;gap:10px;">
        <span class="dashicons dashicons-dashboard" style="font-size:28px;color:#667eea;"></span>
        <?php esc_html_e( 'Overview', 'storelly-product-builder-for-woocommerce' ); ?>
    </h1>

    <style>
    .spbwc-overview-wrap { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }

    /* ---- Stat Cards ---- */
    .spbwc-stat-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 18px; margin-bottom: 32px; }
    .spbwc-stat-card {
        background: #fff; border-radius: 14px; padding: 22px 20px;
        box-shadow: 0 2px 12px rgba(60,80,150,0.09);
        border: 1px solid #eef0f5;
        display: flex; flex-direction: column; gap: 8px;
        transition: transform .2s, box-shadow .2s;
    }
    .spbwc-stat-card:hover { transform: translateY(-3px); box-shadow: 0 8px 24px rgba(102,126,234,0.18); }
    .spbwc-stat-icon {
        width: 44px; height: 44px; border-radius: 12px;
        display: flex; align-items: center; justify-content: center;
        font-size: 22px;
    }
    .spbwc-stat-value { font-size: 34px; font-weight: 800; color: #1d2327; line-height: 1; }
    .spbwc-stat-label { font-size: 13px; color: #777; font-weight: 500; }

    /* ---- Notices ---- */
    .spbwc-notice-banner {
        padding: 14px 20px; border-radius: 10px; margin-bottom: 24px;
        display: flex; align-items: center; gap: 10px; font-size: 14px;
    }
    .spbwc-notice-banner.warn { background: #fff8e5; border-left: 4px solid #f0a500; color: #7a5900; }
    .spbwc-notice-banner.success { background: #eafaf1; border-left: 4px solid #28a745; color: #155724; }
    .spbwc-notice-banner.info { background: #eef3fb; border-left: 4px solid #667eea; color: #1e3a8a; }

    /* ---- License Banner ---- */
    .spbwc-license-banner {
        border-radius: 14px; overflow: hidden; margin-bottom: 32px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        box-shadow: 0 8px 28px rgba(102,126,234,0.35);
        color: #fff; padding: 28px 32px;
        display: flex; align-items: center; gap: 24px;
        flex-wrap: wrap;
    }
    .spbwc-license-banner .badge-plan {
        background: rgba(255,255,255,0.2); border-radius: 20px;
        padding: 4px 18px; font-weight: 800; font-size: 16px;
        letter-spacing: 0.5px; text-transform: uppercase;
    }
    .spbwc-license-banner .btn-upgrade {
        background: #fff; color: #667eea; border: none; border-radius: 8px;
        padding: 9px 22px; font-weight: 700; font-size: 14px; cursor: pointer;
        text-decoration: none; transition: background .2s;
        display: inline-block;
    }
    .spbwc-license-banner .btn-upgrade:hover { background: #f0f0ff; color: #4a5ae8; }
    .spbwc-license-banner .btn-sync {
        background: rgba(255,255,255,0.15); color: #fff; border: 1px solid rgba(255,255,255,0.4);
        border-radius: 8px; padding: 8px 16px; font-size: 13px; cursor: pointer;
        transition: background .2s;
    }
    .spbwc-license-banner .btn-sync:hover { background: rgba(255,255,255,0.25); }
    .spbwc-license-banner .btn-sync.is-loading { cursor: wait; opacity: 0.85; pointer-events: none; }
    .spbwc-license-banner .btn-sync.is-loading .dashicons-update { animation: spbwc-spin 0.8s linear infinite; }
    @keyframes spbwc-spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }

    /* ---- Quick Links ---- */
    .spbwc-quick-links { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 24px; }
    .spbwc-quick-link {
        background: #fff; border: 1px solid #e8eaf0; border-radius: 10px;
        padding: 12px 18px; display: flex; align-items: center; gap: 8px;
        text-decoration: none; color: #374151; font-size: 14px; font-weight: 600;
        transition: all .2s; box-shadow: 0 1px 4px rgba(60,80,150,0.06);
    }
    .spbwc-quick-link:hover { background: #667eea; color: #fff; box-shadow: 0 4px 16px rgba(102,126,234,0.3); }
    .spbwc-quick-link .dashicons { font-size: 18px; }
    </style>

    <?php if ( ! $is_remote_ok ) : ?>
    <div class="spbwc-notice-banner info">
        <span class="dashicons dashicons-cloud-saved"></span>
        <?php esc_html_e( 'Overview stats are showing local data. Connect your Storelly account to see real-time data from the dashboard.', 'storelly-product-builder-for-woocommerce' ); ?>
    </div>
    <?php endif; ?>

    <?php if ( $pkg_slug === 'free' ) : ?>
    <div class="spbwc-notice-banner warn">
        <span class="dashicons dashicons-star-filled"></span>
        <?php
        printf(
            /* translators: %s: URL to license page */
            wp_kses( __( 'You are on the <strong>Free</strong> plan. <a href="%s">Upgrade your license</a> to unlock unlimited products, orders, and priority support.', 'storelly-product-builder-for-woocommerce' ), array( 'strong' => array(), 'a' => array( 'href' => array() ) ) ),
            esc_url( $license_url )
        );
        ?>
    </div>
    <?php endif; ?>

    <!-- Stat Cards -->
    <div class="spbwc-stat-grid">
        <div class="spbwc-stat-card">
            <div class="spbwc-stat-icon" style="background:#eef3fb;">
                <span class="dashicons dashicons-products" style="color:#667eea;"></span>
            </div>
            <div class="spbwc-stat-value"><?php echo esc_html( number_format_i18n( $disp_products ) ); ?></div>
            <div class="spbwc-stat-label"><?php esc_html_e( 'Products', 'storelly-product-builder-for-woocommerce' ); ?></div>
        </div>
        <div class="spbwc-stat-card">
            <div class="spbwc-stat-icon" style="background:#eafdf5;">
                <span class="dashicons dashicons-editor-table" style="color:#28a745;"></span>
            </div>
            <div class="spbwc-stat-value"><?php echo esc_html( number_format_i18n( $disp_pricing ) ); ?></div>
            <div class="spbwc-stat-label"><?php esc_html_e( 'Pricing Options', 'storelly-product-builder-for-woocommerce' ); ?></div>
        </div>
        <div class="spbwc-stat-card">
            <div class="spbwc-stat-icon" style="background:#fff8e5;">
                <span class="dashicons dashicons-cart" style="color:#f0a500;"></span>
            </div>
            <div class="spbwc-stat-value"><?php echo esc_html( number_format_i18n( $disp_orders ) ); ?></div>
            <div class="spbwc-stat-label"><?php esc_html_e( 'Orders', 'storelly-product-builder-for-woocommerce' ); ?></div>
        </div>
        <div class="spbwc-stat-card">
            <div class="spbwc-stat-icon" style="background:#fef0f5;">
                <span class="dashicons dashicons-email-alt" style="color:#e91e8c;"></span>
            </div>
            <div class="spbwc-stat-value"><?php echo esc_html( number_format_i18n( $disp_quotes ) ); ?></div>
            <div class="spbwc-stat-label"><?php esc_html_e( 'Quote Requests', 'storelly-product-builder-for-woocommerce' ); ?></div>
        </div>
    </div>

    <!-- License Banner -->
    <div class="spbwc-license-banner">
        <div style="flex:1;">
            <div style="font-size:13px;opacity:.8;margin-bottom:4px;"><?php esc_html_e( 'Current License Plan', 'storelly-product-builder-for-woocommerce' ); ?></div>
            <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                <span class="dashicons dashicons-admin-network" style="font-size:26px;"></span>
                <span style="font-size:22px;font-weight:800;"><?php echo esc_html( $pkg_name ); ?></span>
                <span class="badge-plan"><?php echo esc_html( $pkg_slug ); ?></span>
            </div>
            <?php if ( $expires_at ) : ?>
                <div style="margin-top:6px;font-size:13px;opacity:.8;">
                    <span class="dashicons dashicons-clock" style="font-size:14px;vertical-align:middle;"></span>
                    <?php
                    printf(
                        /* translators: %s: expiry date */
                        esc_html__( 'Expires: %s', 'storelly-product-builder-for-woocommerce' ),
                        esc_html( $expires_at )
                    );
                    ?>
                </div>
            <?php elseif ( $pkg_slug !== 'free' ) : ?>
                <div style="margin-top:6px;font-size:13px;opacity:.8;">
                    <span class="dashicons dashicons-yes-alt" style="font-size:14px;vertical-align:middle;"></span>
                    <?php esc_html_e( 'Lifetime license – no expiry', 'storelly-product-builder-for-woocommerce' ); ?>
                </div>
            <?php endif; ?>
        </div>
        <div style="display:flex;flex-direction:column;gap:8px;align-items:flex-end;">
            <?php if ( $pkg_slug === 'free' ) : ?>
                <a class="btn-upgrade" href="<?php echo esc_url( $license_url ); ?>">
                    ⚡ <?php esc_html_e( 'Upgrade Plan', 'storelly-product-builder-for-woocommerce' ); ?>
                </a>
            <?php else : ?>
                <a class="btn-upgrade" href="<?php echo esc_url( $license_url ); ?>">
                    <?php esc_html_e( 'Manage License', 'storelly-product-builder-for-woocommerce' ); ?>
                </a>
            <?php endif; ?>
            <button class="btn-sync" id="spbwc-overview-sync-btn"
                    data-nonce="<?php echo esc_attr( $nonce_license ); ?>">
                <span class="dashicons dashicons-update" style="font-size:14px;vertical-align:middle;"></span>
                <?php esc_html_e( 'Sync License', 'storelly-product-builder-for-woocommerce' ); ?>
            </button>
        </div>
    </div>

    <!-- Quick Links -->
    <h3 style="font-size:15px;font-weight:700;color:#374151;margin-bottom:12px;">
        <?php esc_html_e( 'Quick Links', 'storelly-product-builder-for-woocommerce' ); ?>
    </h3>
    <div class="spbwc-quick-links">
        <a class="spbwc-quick-link" href="<?php echo esc_url( admin_url( 'admin.php?page=' . SPBWC_PB_BUILDER_SLUG ) ); ?>">
            <span class="dashicons dashicons-editor-table"></span>
            <?php esc_html_e( 'Pricing Options', 'storelly-product-builder-for-woocommerce' ); ?>
        </a>
        <a class="spbwc-quick-link" href="<?php echo esc_url( admin_url( 'admin.php?page=' . SPBWC_PB_PRODUCTS_SLUG ) ); ?>">
            <span class="dashicons dashicons-products"></span>
            <?php esc_html_e( 'Products', 'storelly-product-builder-for-woocommerce' ); ?>
        </a>
        <a class="spbwc-quick-link" href="<?php echo esc_url( admin_url( 'admin.php?page=' . SPBWC_PB_ORDERS_SLUG ) ); ?>">
            <span class="dashicons dashicons-cart"></span>
            <?php esc_html_e( 'Orders', 'storelly-product-builder-for-woocommerce' ); ?>
        </a>
        <a class="spbwc-quick-link" href="<?php echo esc_url( admin_url( 'admin.php?page=' . SPBWC_PB_QUOTES_SLUG ) ); ?>">
            <span class="dashicons dashicons-email-alt"></span>
            <?php esc_html_e( 'Quotes', 'storelly-product-builder-for-woocommerce' ); ?>
        </a>
        <a class="spbwc-quick-link" href="<?php echo esc_url( $license_url ); ?>">
            <span class="dashicons dashicons-admin-network"></span>
            <?php esc_html_e( 'License', 'storelly-product-builder-for-woocommerce' ); ?>
        </a>
        <a class="spbwc-quick-link" href="<?php echo esc_url( admin_url( 'admin.php?page=' . SPBWC_PB_OPTIONS_SLUG ) ); ?>">
            <span class="dashicons dashicons-admin-settings"></span>
            <?php esc_html_e( 'Settings', 'storelly-product-builder-for-woocommerce' ); ?>
        </a>
    </div>

    <script>
    (function($){
        var $btn = $('#spbwc-overview-sync-btn');
        var btnHtml = $btn.html();
        $btn.on('click', function(){
            if ( $btn.hasClass('is-loading') ) return;
            $btn.addClass('is-loading').prop('disabled', true)
                .html('<span class="dashicons dashicons-update" style="font-size:14px;vertical-align:middle;"></span> <?php echo esc_js( __( 'Syncing...', 'storelly-product-builder-for-woocommerce' ) ); ?>');
            $.post(ajaxurl, {
                action: 'spbwc_license_sync',
                nonce: $btn.data('nonce')
            }, function(res){
                if ( res.success ) { location.reload(); }
                else {
                    alert( (res.data && res.data.msg) ? res.data.msg : '<?php echo esc_js( __( 'Sync failed.', 'storelly-product-builder-for-woocommerce' ) ); ?>' );
                    $btn.removeClass('is-loading').prop('disabled', false).html(btnHtml);
                }
            }).fail(function(){
                alert('<?php echo esc_js( __( 'Request failed. Check your connection.', 'storelly-product-builder-for-woocommerce' ) ); ?>');
                $btn.removeClass('is-loading').prop('disabled', false).html(btnHtml);
            });
        });
    })(jQuery);
    </script>
</div>
