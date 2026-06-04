<?php
/**
 * Overview Page View — corporate / professional layout (v2)
 *
 * Variables available: $total_products, $total_pricing, $total_orders,
 *                      $total_quotes, $remote_stats, $license
 *
 * Styles: static/css/overview.css (enqueued via spbwc_admin_enqueue_scripts
 * when the current admin page is the Overview).
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- template-scope locals passed in from controller; not true globals.

// Determine which counts to use (prefer remote API when available).
$is_remote_ok   = ! is_wp_error( $remote_stats );
$disp_products  = $is_remote_ok ? $remote_stats['total_products'] : $total_products;
$disp_orders    = $is_remote_ok ? $remote_stats['total_orders'] : $total_orders;
$disp_quotes    = $is_remote_ok ? $remote_stats['total_quotes'] : $total_quotes;
$disp_pricing   = $total_pricing; // Always from local DB.

$pkg_name       = esc_html( $license['package_name'] );
$pkg_slug       = esc_attr( $license['status'] );
$is_free        = ( $pkg_slug === 'free' );
$expires_at     = $license['expires_at'] ? esc_html( $license['expires_at'] ) : null;
$license_url    = admin_url( 'admin.php?page=' . SPBWC_PB_LICENSE_SLUG );
$nonce_license  = wp_create_nonce( 'spbwc_license_action' );

// === Recent activity data ====================================
// Recent products (any post_type=product, ordered by last modified)
$recent_products = get_posts( array(
    'post_type'      => 'product',
    'post_status'    => array( 'publish', 'draft', 'pending' ),
    'posts_per_page' => 5,
    'orderby'        => 'modified',
    'order'          => 'DESC',
    'suppress_filters' => false,
) );

// Recent WooCommerce orders
$recent_orders = function_exists( 'wc_get_orders' ) ? wc_get_orders( array(
    'limit'   => 5,
    'orderby' => 'date',
    'order'   => 'DESC',
    'status'  => array( 'wc-processing', 'wc-completed', 'wc-on-hold', 'wc-pending' ),
) ) : array();

// TODO: hook real data once schema is wired:
//   $recent_options, $recent_designs, $recent_quotes, $recent_templates
$recent_options   = array();
$recent_designs   = array();
$recent_templates = array();

// Helpful benefits text per plan tier.
$plan_benefits = $is_free
    ? array(
        array( 'icon' => 'yes-alt', 'text' => __( 'Full product builder, quotes & custom orders — no product limit', 'storelly-product-builder-for-woocommerce' ) ),
        array( 'icon' => 'no-alt',  'text' => __( 'Storelly Cloud off: print-ready PDF, order sync & analytics', 'storelly-product-builder-for-woocommerce' ) ),
        array( 'icon' => 'no-alt',  'text' => __( 'Community support', 'storelly-product-builder-for-woocommerce' ) ),
    )
    : array(
        array( 'icon' => 'yes-alt', 'text' => __( 'Everything in Free, with no product limit', 'storelly-product-builder-for-woocommerce' ) ),
        array( 'icon' => 'yes-alt', 'text' => __( 'Storelly Cloud: print-ready PDF, order sync & dashboard analytics', 'storelly-product-builder-for-woocommerce' ) ),
        array( 'icon' => 'yes-alt', 'text' => __( 'Priority support from Storelly team', 'storelly-product-builder-for-woocommerce' ) ),
    );
?>
<div class="wrap spbwc-overview-wrap">

    <!-- ============ Page hero block (corporate header band) ============ -->
    <header class="spbwc-page-hero">
        <div class="spbwc-page-hero__grid">
            <div class="spbwc-page-hero__body">
                <div class="spbwc-page-hero__eyebrow">
                    <span class="dashicons dashicons-store" aria-hidden="true"></span>
                    <?php esc_html_e( 'Storelly Product Builder', 'storelly-product-builder-for-woocommerce' ); ?>
                </div>
                <h1 class="spbwc-page-hero__title">
                    <span class="dashicons dashicons-dashboard" aria-hidden="true"></span>
                    <?php esc_html_e( 'Overview', 'storelly-product-builder-for-woocommerce' ); ?>
                </h1>
                <p class="spbwc-page-hero__subtitle">
                    <?php esc_html_e( 'A snapshot of your Storelly Product Builder activity — license status, key metrics, and quick links to manage your store. Stats refresh hourly from your Storelly dashboard when connected.', 'storelly-product-builder-for-woocommerce' ); ?>
                </p>
            </div>
            <div class="spbwc-page-hero__actions">
                <a class="spbwc-cta-btn spbwc-cta-btn--ghost" href="https://storelly.com/docs" target="_blank" rel="noopener">
                    <span class="dashicons dashicons-book" aria-hidden="true"></span>
                    <?php esc_html_e( 'Documentation', 'storelly-product-builder-for-woocommerce' ); ?>
                </a>
                <a class="spbwc-cta-btn spbwc-cta-btn--solid" href="<?php echo esc_url( admin_url( 'admin.php?page=' . SPBWC_PB_BUILDER_SLUG ) ); ?>">
                    <span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
                    <?php esc_html_e( 'Create option group', 'storelly-product-builder-for-woocommerce' ); ?>
                </a>
            </div>
        </div>
    </header>

    <?php
    // ============ Getting started (Welcome mode) ============
    // Auto-shown right after activation and until the two core steps are
    // done (or the merchant skips). The fastest path — Import demo — leads.
    $spbwc_welcome_mode = class_exists( 'SPBWC_Onboarding' ) && SPBWC_Onboarding::is_welcome_mode();
    if ( $spbwc_welcome_mode ) :
        $spbwc_checklist   = SPBWC_Onboarding::get_checklist();
        $spbwc_done_count  = 0;
        foreach ( $spbwc_checklist as $spbwc_ci ) {
            if ( ! empty( $spbwc_ci['done'] ) ) {
                $spbwc_done_count++;
            }
        }
        $spbwc_total_steps = count( $spbwc_checklist );
        $spbwc_url_sample  = admin_url( 'admin.php?page=' . SPBWC_PB_OPTIONS_SLUG . '/global-import&tab=sample' );
        $spbwc_url_woo     = admin_url( 'admin.php?page=' . SPBWC_PB_OPTIONS_SLUG . '/global-import&tab=woo' );
        $spbwc_url_builder = admin_url( 'admin.php?page=' . SPBWC_PB_BUILDER_SLUG );
        $spbwc_dismiss_url = SPBWC_Onboarding::get_dismiss_url();

        // Bundled one-click demo (M9.1) — installs locally, works offline. Falls
        // back to the remote sample importer only if the bundle isn't present.
        $spbwc_demo_avail  = class_exists( 'SPBWC_Demo_Seeder' ) && SPBWC_Demo_Seeder::bundle_available();
        $spbwc_demo_seeded = $spbwc_demo_avail && SPBWC_Demo_Seeder::is_seeded();
        $spbwc_demo_state  = $spbwc_demo_seeded ? (array) get_option( 'spbwc_demo_seeded', array() ) : array();
        $spbwc_demo_view   = ! empty( $spbwc_demo_state['product_id'] ) ? get_permalink( (int) $spbwc_demo_state['product_id'] ) : '';
        $spbwc_demo_nonce  = wp_create_nonce( 'spbwc_demo_seed' );
    ?>
    <section class="spbwc-welcome" aria-labelledby="spbwc-welcome-title">
        <div class="spbwc-welcome__head">
            <div class="spbwc-welcome__intro">
                <div class="spbwc-welcome__eyebrow">
                    <span class="dashicons dashicons-flag" aria-hidden="true"></span>
                    <?php esc_html_e( 'Welcome to Storelly', 'storelly-product-builder-for-woocommerce' ); ?>
                </div>
                <h2 id="spbwc-welcome-title" class="spbwc-welcome__title">
                    <?php esc_html_e( 'Let\'s get your store selling custom products', 'storelly-product-builder-for-woocommerce' ); ?>
                </h2>
                <p class="spbwc-welcome__sub">
                    <?php esc_html_e( 'Pick a starting point. The fastest is importing a ready-made demo — you\'ll see the product builder live on your storefront in seconds.', 'storelly-product-builder-for-woocommerce' ); ?>
                </p>
            </div>
            <a class="spbwc-welcome__dismiss" href="<?php echo esc_url( $spbwc_dismiss_url ); ?>">
                <span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
                <?php esc_html_e( 'Skip setup', 'storelly-product-builder-for-woocommerce' ); ?>
            </a>
        </div>

        <div class="spbwc-welcome__cards">
            <!-- Fastest path — leads on purpose. -->
            <?php if ( $spbwc_demo_seeded ) : ?>
            <!-- Demo already installed: offer a quick look + a clean remove. -->
            <div class="spbwc-welcome-card spbwc-welcome-card--primary spbwc-welcome-card--done" id="spbwc-demo-card-done" data-nonce="<?php echo esc_attr( $spbwc_demo_nonce ); ?>">
                <span class="spbwc-welcome-card__flag"><?php esc_html_e( 'Demo added', 'storelly-product-builder-for-woocommerce' ); ?></span>
                <div class="spbwc-welcome-card__icon">
                    <span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
                </div>
                <h3 class="spbwc-welcome-card__title">
                    <?php esc_html_e( 'Your demo product is live', 'storelly-product-builder-for-woocommerce' ); ?>
                </h3>
                <p class="spbwc-welcome-card__desc">
                    <?php esc_html_e( 'A ready-made customizable product is on your storefront. Open it to see the builder in action.', 'storelly-product-builder-for-woocommerce' ); ?>
                </p>
                <a class="spbwc-cta-btn spbwc-cta-btn--solid" href="<?php echo esc_url( $spbwc_demo_view ); ?>" target="_blank" rel="noopener">
                    <span class="dashicons dashicons-external" aria-hidden="true"></span>
                    <?php esc_html_e( 'View on store', 'storelly-product-builder-for-woocommerce' ); ?>
                </a>
                <button type="button" class="spbwc-cta-btn spbwc-cta-btn--link" id="spbwc-demo-remove">
                    <?php esc_html_e( 'Remove demo', 'storelly-product-builder-for-woocommerce' ); ?>
                </button>
            </div>
            <?php elseif ( $spbwc_demo_avail ) : ?>
            <!-- One-click local seed — no network, works offline. -->
            <a class="spbwc-welcome-card spbwc-welcome-card--primary" id="spbwc-demo-card" href="#" data-nonce="<?php echo esc_attr( $spbwc_demo_nonce ); ?>">
                <span class="spbwc-welcome-card__flag"><?php esc_html_e( 'Fastest', 'storelly-product-builder-for-woocommerce' ); ?></span>
                <div class="spbwc-welcome-card__icon">
                    <span class="dashicons dashicons-archive" aria-hidden="true"></span>
                </div>
                <h3 class="spbwc-welcome-card__title">
                    <?php esc_html_e( 'See it live with a demo product', 'storelly-product-builder-for-woocommerce' ); ?>
                </h3>
                <p class="spbwc-welcome-card__desc">
                    <?php esc_html_e( 'Add a ready-made customizable product in one click — the quickest way to watch the builder work on your store.', 'storelly-product-builder-for-woocommerce' ); ?>
                </p>
                <span class="spbwc-cta-btn spbwc-cta-btn--solid" id="spbwc-demo-cta">
                    <span class="dashicons dashicons-download" aria-hidden="true"></span>
                    <?php esc_html_e( 'Add demo product', 'storelly-product-builder-for-woocommerce' ); ?>
                </span>
            </a>
            <?php else : ?>
            <!-- Fallback: remote sample importer (bundle not present). -->
            <a class="spbwc-welcome-card spbwc-welcome-card--primary" href="<?php echo esc_url( $spbwc_url_sample ); ?>">
                <span class="spbwc-welcome-card__flag"><?php esc_html_e( 'Fastest', 'storelly-product-builder-for-woocommerce' ); ?></span>
                <div class="spbwc-welcome-card__icon">
                    <span class="dashicons dashicons-archive" aria-hidden="true"></span>
                </div>
                <h3 class="spbwc-welcome-card__title">
                    <?php esc_html_e( 'See it live with demo products', 'storelly-product-builder-for-woocommerce' ); ?>
                </h3>
                <p class="spbwc-welcome-card__desc">
                    <?php esc_html_e( 'Add ready-made customizable products in one click — the quickest way to watch the builder work on your store.', 'storelly-product-builder-for-woocommerce' ); ?>
                </p>
                <span class="spbwc-cta-btn spbwc-cta-btn--solid">
                    <span class="dashicons dashicons-download" aria-hidden="true"></span>
                    <?php esc_html_e( 'Import demo products', 'storelly-product-builder-for-woocommerce' ); ?>
                </span>
            </a>
            <?php endif; ?>

            <a class="spbwc-welcome-card" href="<?php echo esc_url( $spbwc_url_woo ); ?>">
                <div class="spbwc-welcome-card__icon">
                    <span class="dashicons dashicons-update" aria-hidden="true"></span>
                </div>
                <h3 class="spbwc-welcome-card__title">
                    <?php esc_html_e( 'Use my existing products', 'storelly-product-builder-for-woocommerce' ); ?>
                </h3>
                <p class="spbwc-welcome-card__desc">
                    <?php esc_html_e( 'Turn your WooCommerce variable products into Storelly pricing options. You review everything before it goes live.', 'storelly-product-builder-for-woocommerce' ); ?>
                </p>
                <span class="spbwc-cta-btn">
                    <span class="dashicons dashicons-search" aria-hidden="true"></span>
                    <?php esc_html_e( 'Scan my products', 'storelly-product-builder-for-woocommerce' ); ?>
                </span>
            </a>

            <a class="spbwc-welcome-card" href="<?php echo esc_url( $spbwc_url_builder ); ?>">
                <div class="spbwc-welcome-card__icon">
                    <span class="dashicons dashicons-edit" aria-hidden="true"></span>
                </div>
                <h3 class="spbwc-welcome-card__title">
                    <?php esc_html_e( 'Build from scratch', 'storelly-product-builder-for-woocommerce' ); ?>
                </h3>
                <p class="spbwc-welcome-card__desc">
                    <?php esc_html_e( 'Create a pricing option group by hand — full control over fields, prices and conditional logic.', 'storelly-product-builder-for-woocommerce' ); ?>
                </p>
                <span class="spbwc-cta-btn">
                    <span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
                    <?php esc_html_e( 'Open builder', 'storelly-product-builder-for-woocommerce' ); ?>
                </span>
            </a>
        </div>

        <?php if ( $spbwc_demo_avail ) : ?>
        <script>
        (function($){
            var fail = '<?php echo esc_js( __( 'Could not add the demo. Please try again.', 'storelly-product-builder-for-woocommerce' ) ); ?>';
            var $card = $('#spbwc-demo-card');
            if ( $card.length ) {
                $card.on('click', function(e){
                    e.preventDefault();
                    if ( $card.hasClass('is-loading') ) { return; }
                    $card.addClass('is-loading');
                    $('#spbwc-demo-cta').html('<span class="dashicons dashicons-update" aria-hidden="true"></span> <?php echo esc_js( __( 'Adding…', 'storelly-product-builder-for-woocommerce' ) ); ?>');
                    $.post(ajaxurl, { action:'spbwc_demo_seed', nonce: $card.data('nonce') }, function(res){
                        if ( res && res.success && res.data && res.data.view_url ) {
                            window.open(res.data.view_url, '_blank', 'noopener');
                            location.reload();
                        } else {
                            alert( (res && res.data && res.data.message) ? res.data.message : fail );
                            $card.removeClass('is-loading');
                            $('#spbwc-demo-cta').html('<span class="dashicons dashicons-download" aria-hidden="true"></span> <?php echo esc_js( __( 'Add demo product', 'storelly-product-builder-for-woocommerce' ) ); ?>');
                        }
                    }).fail(function(){
                        alert(fail);
                        $card.removeClass('is-loading');
                        $('#spbwc-demo-cta').html('<span class="dashicons dashicons-download" aria-hidden="true"></span> <?php echo esc_js( __( 'Add demo product', 'storelly-product-builder-for-woocommerce' ) ); ?>');
                    });
                });
            }
            $('#spbwc-demo-remove').on('click', function(){
                if ( ! window.confirm('<?php echo esc_js( __( 'Remove the demo product and its images?', 'storelly-product-builder-for-woocommerce' ) ); ?>') ) { return; }
                var $b = $(this).prop('disabled', true);
                $.post(ajaxurl, { action:'spbwc_demo_undo', nonce: $('#spbwc-demo-card-done').data('nonce') }, function(){ location.reload(); })
                 .fail(function(){ $b.prop('disabled', false); });
            });
        })(jQuery);
        </script>
        <?php endif; ?>

        <div class="spbwc-welcome__checklist">
            <div class="spbwc-welcome__checklist-head">
                <span class="dashicons dashicons-yes" aria-hidden="true"></span>
                <span class="spbwc-welcome__checklist-title">
                    <?php esc_html_e( 'Setup progress', 'storelly-product-builder-for-woocommerce' ); ?>
                </span>
                <span class="spbwc-welcome__progress">
                    <?php
                    printf(
                        /* translators: 1: completed step count, 2: total step count. */
                        esc_html__( '%1$d of %2$d', 'storelly-product-builder-for-woocommerce' ),
                        (int) $spbwc_done_count,
                        (int) $spbwc_total_steps
                    );
                    ?>
                </span>
            </div>
            <ul class="spbwc-welcome__steps">
                <?php foreach ( $spbwc_checklist as $spbwc_ci ) : ?>
                    <li class="spbwc-welcome__step <?php echo ! empty( $spbwc_ci['done'] ) ? 'is-done' : 'is-todo'; ?>">
                        <span class="dashicons <?php echo ! empty( $spbwc_ci['done'] ) ? 'dashicons-yes-alt' : 'dashicons-marker'; ?>" aria-hidden="true"></span>
                        <?php echo esc_html( $spbwc_ci['label'] ); ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <?php
        // Cloud 1-click consent (M5). No network call happens until the
        // merchant clicks Enable — see includes/class-cloud-connect.php.
        $spbwc_cloud_connected = class_exists( 'SPBWC_Cloud_Connect' ) && SPBWC_Cloud_Connect::is_connected();
        $spbwc_cloud_nonce     = wp_create_nonce( 'spbwc_cloud_connect' );
        $spbwc_privacy_url     = 'https://storelly.com/privacy';
        ?>
        <div class="spbwc-welcome__cloud<?php echo $spbwc_cloud_connected ? ' is-connected' : ''; ?>"
             id="spbwc-cloud-card" data-nonce="<?php echo esc_attr( $spbwc_cloud_nonce ); ?>">
            <?php if ( $spbwc_cloud_connected ) : ?>
                <div class="spbwc-welcome__cloud-row">
                    <span class="dashicons dashicons-cloud-saved" aria-hidden="true"></span>
                    <div>
                        <strong><?php esc_html_e( 'Cloud connected', 'storelly-product-builder-for-woocommerce' ); ?></strong>
                        <p class="spbwc-welcome__cloud-desc">
                            <?php esc_html_e( 'High-quality print PDF and your saved store profile are active.', 'storelly-product-builder-for-woocommerce' ); ?>
                        </p>
                    </div>
                    <button type="button" class="spbwc-cta-btn spbwc-cta-btn--link" id="spbwc-cloud-disconnect">
                        <?php esc_html_e( 'Disconnect', 'storelly-product-builder-for-woocommerce' ); ?>
                    </button>
                </div>
            <?php else : ?>
                <div class="spbwc-welcome__cloud-row">
                    <span class="dashicons dashicons-cloud" aria-hidden="true"></span>
                    <div class="spbwc-welcome__cloud-main">
                        <strong><?php esc_html_e( 'Enable Cloud — high-quality print PDF + saved store profile', 'storelly-product-builder-for-woocommerce' ); ?></strong>
                        <p class="spbwc-welcome__cloud-desc">
                            <?php esc_html_e( 'One click connects your store to Storelly so you get print-ready PDFs and your setup is safely backed up.', 'storelly-product-builder-for-woocommerce' ); ?>
                        </p>
                        <p class="spbwc-welcome__cloud-fine">
                            <?php
                            printf(
                                /* translators: %s: link to the privacy policy. */
                                wp_kses(
                                    /* translators: %s: link to the privacy policy. */
                                    __( 'On click we share your admin email, store URL and a store ID with Storelly to create your profile. <a href="%s" target="_blank" rel="noopener noreferrer">Privacy</a>.', 'storelly-product-builder-for-woocommerce' ),
                                    array( 'a' => array( 'href' => array(), 'target' => array(), 'rel' => array() ) )
                                ),
                                esc_url( $spbwc_privacy_url )
                            );
                            ?>
                        </p>
                        <p class="spbwc-welcome__cloud-manual">
                            <a href="#" id="spbwc-cloud-manual-toggle"><?php esc_html_e( 'Already have a Store ID? Link it', 'storelly-product-builder-for-woocommerce' ); ?></a>
                        </p>
                        <div class="spbwc-welcome__cloud-manual-box" id="spbwc-cloud-manual-box" hidden>
                            <input type="text" id="spbwc-cloud-store-id" placeholder="<?php esc_attr_e( 'Store ID from your Storelly dashboard', 'storelly-product-builder-for-woocommerce' ); ?>">
                            <button type="button" class="spbwc-cta-btn" id="spbwc-cloud-link"><?php esc_html_e( 'Link', 'storelly-product-builder-for-woocommerce' ); ?></button>
                        </div>
                    </div>
                    <button type="button" class="spbwc-cta-btn spbwc-cta-btn--solid" id="spbwc-cloud-connect">
                        <span class="dashicons dashicons-cloud" aria-hidden="true"></span>
                        <?php esc_html_e( 'Enable Cloud', 'storelly-product-builder-for-woocommerce' ); ?>
                    </button>
                </div>
            <?php endif; ?>
        </div>
        <script>
        (function($){
            var $c = $('#spbwc-cloud-card');
            if ( ! $c.length ) { return; }
            var nonce = $c.data('nonce');
            function send(action, extra, btn){
                var data = $.extend({ action: action, nonce: nonce }, extra || {});
                if ( btn ) { $(btn).addClass('is-loading').prop('disabled', true); }
                $.post(ajaxurl, data, function(res){
                    if ( res && res.success ) { location.reload(); }
                    else {
                        alert( (res && res.data && res.data.message) ? res.data.message : '<?php echo esc_js( __( 'Something went wrong. Please try again.', 'storelly-product-builder-for-woocommerce' ) ); ?>' );
                        if ( btn ) { $(btn).removeClass('is-loading').prop('disabled', false); }
                    }
                }).fail(function(){
                    alert('<?php echo esc_js( __( 'Request failed. Check your connection.', 'storelly-product-builder-for-woocommerce' ) ); ?>');
                    if ( btn ) { $(btn).removeClass('is-loading').prop('disabled', false); }
                });
            }
            $('#spbwc-cloud-connect').on('click', function(){ send('spbwc_cloud_connect', {}, this); });
            $('#spbwc-cloud-disconnect').on('click', function(){
                if ( window.confirm('<?php echo esc_js( __( 'Disconnect from Storelly Cloud? PDF and sync will stop.', 'storelly-product-builder-for-woocommerce' ) ); ?>') ) { send('spbwc_cloud_disconnect', {}, this); }
            });
            $('#spbwc-cloud-manual-toggle').on('click', function(e){ e.preventDefault(); $('#spbwc-cloud-manual-box').prop('hidden', false); });
            $('#spbwc-cloud-link').on('click', function(){
                var id = $.trim($('#spbwc-cloud-store-id').val() || '');
                if ( ! id ) { return; }
                send('spbwc_cloud_link_manual', { store_id: id }, this);
            });
        })(jQuery);
        </script>
    </section>
    <?php endif; ?>

    <?php
    // ============ Resume setup (after "Skip setup") ============
    // If the merchant skipped the Welcome before finishing onboarding, leave a
    // quiet one-line way back in — so a stray click on "Skip" isn't permanent.
    if ( ! $spbwc_welcome_mode
        && class_exists( 'SPBWC_Onboarding' )
        && SPBWC_Onboarding::is_dismissed()
        && ! SPBWC_Onboarding::is_onboarding_complete() ) :
    ?>
    <div class="spbwc-notice-banner spbwc-notice-banner--info spbwc-resume-setup" role="status">
        <span class="dashicons dashicons-flag" aria-hidden="true"></span>
        <div class="spbwc-notice-banner__body">
            <div class="spbwc-notice-banner__text">
                <?php esc_html_e( 'Setup isn\'t finished yet.', 'storelly-product-builder-for-woocommerce' ); ?>
                <a href="<?php echo esc_url( SPBWC_Onboarding::get_restore_url() ); ?>">
                    <?php esc_html_e( 'Show the setup guide again', 'storelly-product-builder-for-woocommerce' ); ?>
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php
    // ============ Woo migrate — prepared/Publish-all banner (M4) ============
    // Shows while the background prepare runs, then surfaces a one-click
    // "Publish all" once products are staged (published=0, invisible to
    // buyers until the merchant publishes). Rendered on every Overview load,
    // not only in Welcome mode, so the merchant can publish whenever.
    $spbwc_prep       = class_exists( 'SPBWC_Woo_Prepare' ) ? SPBWC_Woo_Prepare::instance() : null;
    $spbwc_prep_state = $spbwc_prep ? $spbwc_prep->public_state() : array( 'status' => 'none' );
    $spbwc_prep_st    = isset( $spbwc_prep_state['status'] ) ? $spbwc_prep_state['status'] : 'none';
    if ( $spbwc_prep && in_array( $spbwc_prep_st, array( 'scheduled', 'preparing', 'prepared' ), true ) ) :
        $spbwc_prep_nonce = wp_create_nonce( SPBWC_Woo_Prepare::NONCE );
        $spbwc_prep_busy  = in_array( $spbwc_prep_st, array( 'scheduled', 'preparing' ), true );
    ?>
    <div class="spbwc-notice-banner <?php echo $spbwc_prep_busy ? 'spbwc-notice-banner--info' : 'spbwc-notice-banner--warn'; ?> spbwc-prep-banner"
         id="spbwc-prep-banner"
         data-nonce="<?php echo esc_attr( $spbwc_prep_nonce ); ?>"
         data-status="<?php echo esc_attr( $spbwc_prep_st ); ?>">
        <span class="dashicons <?php echo $spbwc_prep_busy ? 'dashicons-update' : 'dashicons-yes-alt'; ?>" aria-hidden="true"></span>
        <div class="spbwc-notice-banner__body">
            <?php if ( $spbwc_prep_busy ) : ?>
                <div class="spbwc-notice-banner__title">
                    <?php esc_html_e( 'Preparing your existing products…', 'storelly-product-builder-for-woocommerce' ); ?>
                </div>
                <div class="spbwc-notice-banner__text">
                    <?php esc_html_e( 'We\'re turning your WooCommerce variable products into Storelly pricing options in the background. Nothing changes on your store yet — you\'ll get a one-click Publish when it\'s ready.', 'storelly-product-builder-for-woocommerce' ); ?>
                </div>
            <?php else : ?>
                <div class="spbwc-notice-banner__title">
                    <?php
                    printf(
                        /* translators: %d: number of products prepared as pending option sets. */
                        esc_html__( '%d product(s) ready to go live', 'storelly-product-builder-for-woocommerce' ),
                        (int) $spbwc_prep_state['prepared']
                    );
                    ?>
                </div>
                <div class="spbwc-notice-banner__text">
                    <?php esc_html_e( 'These are staged from your existing WooCommerce products and are still hidden from buyers. Publish to show them on your storefront — your native Woo variations stay intact, and you can undo anytime.', 'storelly-product-builder-for-woocommerce' ); ?>
                    <?php if ( (int) $spbwc_prep_state['multi_attr'] > 0 ) : ?>
                        <br>
                        <em><?php
                        printf(
                            /* translators: %d: number of multi-attribute products whose price was left blank. */
                            esc_html__( '%d multi-attribute product(s) have prices left blank for you to review first.', 'storelly-product-builder-for-woocommerce' ),
                            (int) $spbwc_prep_state['multi_attr']
                        );
                        ?></em>
                    <?php endif; ?>
                </div>
                <div class="spbwc-prep-banner__actions">
                    <button type="button" class="spbwc-cta-btn spbwc-cta-btn--solid" id="spbwc-prep-publish">
                        <span class="dashicons dashicons-yes" aria-hidden="true"></span>
                        <?php esc_html_e( 'Publish all', 'storelly-product-builder-for-woocommerce' ); ?>
                    </button>
                    <a class="spbwc-cta-btn" href="<?php echo esc_url( admin_url( 'admin.php?page=' . SPBWC_PB_BUILDER_SLUG ) ); ?>">
                        <?php esc_html_e( 'Review first', 'storelly-product-builder-for-woocommerce' ); ?>
                    </a>
                    <button type="button" class="spbwc-cta-btn spbwc-cta-btn--link" id="spbwc-prep-undo">
                        <?php esc_html_e( 'Undo', 'storelly-product-builder-for-woocommerce' ); ?>
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <script>
    (function($){
        var $b = $('#spbwc-prep-banner');
        if ( ! $b.length ) { return; }
        var nonce  = $b.data('nonce');
        var status = String($b.data('status'));

        function post(action, done){
            $.post(ajaxurl, { action: action, nonce: nonce }, function(res){ done(res); })
             .fail(function(){ done({ success:false }); });
        }

        // While preparing, poll until staged, then reload to reveal Publish all.
        if ( status === 'scheduled' || status === 'preparing' ) {
            var poll = setInterval(function(){
                $.post(ajaxurl, { action:'spbwc_woo_prepare_status', nonce:nonce }, function(res){
                    if ( res && res.success && res.data ) {
                        var s = res.data.status;
                        if ( s === 'prepared' || s === 'empty' || s === 'published' ) {
                            clearInterval(poll);
                            location.reload();
                        }
                    }
                });
            }, 4000);
        }

        $('#spbwc-prep-publish').on('click', function(){
            var $btn = $(this);
            if ( $btn.hasClass('is-loading') ) { return; }
            $btn.addClass('is-loading').prop('disabled', true)
                .html('<span class="dashicons dashicons-update" aria-hidden="true"></span> <?php echo esc_js( __( 'Publishing…', 'storelly-product-builder-for-woocommerce' ) ); ?>');
            post('spbwc_woo_prepare_publish', function(res){
                if ( res && res.success ) { location.reload(); }
                else {
                    alert('<?php echo esc_js( __( 'Publish failed. Please try again.', 'storelly-product-builder-for-woocommerce' ) ); ?>');
                    $btn.removeClass('is-loading').prop('disabled', false);
                }
            });
        });

        $('#spbwc-prep-undo').on('click', function(){
            if ( ! window.confirm('<?php echo esc_js( __( 'Remove the prepared option sets and unlink those products? Your Woo variations are not affected.', 'storelly-product-builder-for-woocommerce' ) ); ?>') ) { return; }
            post('spbwc_woo_prepare_undo', function(res){
                if ( res && res.success ) { location.reload(); }
                else { alert('<?php echo esc_js( __( 'Undo failed. Please try again.', 'storelly-product-builder-for-woocommerce' ) ); ?>'); }
            });
        });
    })(jQuery);
    </script>
    <?php endif; ?>

    <!-- ============ Notices ============ -->
    <?php if ( ! $is_remote_ok ) : ?>
        <div class="spbwc-notice-banner spbwc-notice-banner--info" role="status">
            <span class="dashicons dashicons-cloud-saved" aria-hidden="true"></span>
            <div class="spbwc-notice-banner__body">
                <div class="spbwc-notice-banner__title">
                    <?php esc_html_e( 'Showing local data only', 'storelly-product-builder-for-woocommerce' ); ?>
                </div>
                <div class="spbwc-notice-banner__text">
                    <?php
                    printf(
                        /* translators: %s: URL to license page */
                        wp_kses(
                            /* translators: %s: URL to license page */
                            __( 'Couldn\'t reach the Storelly server. Stats below are pulled from your local database. <a href="%s">Check your connection</a> to enable real-time metrics.', 'storelly-product-builder-for-woocommerce' ),
                            array( 'a' => array( 'href' => array() ) )
                        ),
                        esc_url( $license_url )
                    );
                    ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php // Always-visible language widget — see includes/class-i18n-notice.php
    if ( class_exists( 'SPBWC_I18n_Notice' ) ) {
        SPBWC_I18n_Notice::render_language_widget();
    } ?>

    <?php if ( $is_free ) : ?>
        <div class="spbwc-notice-banner spbwc-notice-banner--warn" role="status">
            <span class="dashicons dashicons-star-filled" aria-hidden="true"></span>
            <div class="spbwc-notice-banner__body">
                <div class="spbwc-notice-banner__title">
                    <?php esc_html_e( 'You\'re on the Free plan', 'storelly-product-builder-for-woocommerce' ); ?>
                </div>
                <div class="spbwc-notice-banner__text">
                    <?php
                    printf(
                        /* translators: %s: URL to license page */
                        wp_kses(
                            /* translators: %s: URL to license page */
                            __( 'Add a Storelly Cloud plan to enable print-ready PDF rendering, order sync and dashboard analytics. <a href="%s">See Cloud plans</a>. Your existing builder, quotes and custom orders stay free and unchanged.', 'storelly-product-builder-for-woocommerce' ),
                            array( 'a' => array( 'href' => array() ) )
                        ),
                        esc_url( $license_url )
                    );
                    ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- ============ License hero ============ -->
    <!-- Free plan → brand-blue gradient (encourages upgrade).      -->
    <!-- Paid plan → royal-gold gradient (luxurious "premium" feel).-->
    <section class="spbwc-license-hero<?php echo $is_free ? '' : ' spbwc-license-hero--premium'; ?>" aria-labelledby="spbwc-license-hero-title">
        <div class="spbwc-license-hero__grid">
            <div class="spbwc-license-hero__info">
                <div class="spbwc-license-hero__eyebrow">
                    <span class="dashicons dashicons-admin-network" aria-hidden="true"></span>
                    <?php esc_html_e( 'Current license plan', 'storelly-product-builder-for-woocommerce' ); ?>
                </div>
                <div class="spbwc-license-hero__title">
                    <span id="spbwc-license-hero-title" class="spbwc-license-hero__pkg">
                        <?php echo esc_html( $pkg_name ); ?>
                    </span>
                    <span class="spbwc-license-hero__badge"><?php echo esc_html( $pkg_slug ); ?></span>
                </div>
                <?php if ( $expires_at ) : ?>
                    <div class="spbwc-license-hero__meta">
                        <span class="dashicons dashicons-clock" aria-hidden="true"></span>
                        <?php
                        printf(
                            /* translators: %s: expiry date */
                            esc_html__( 'Expires on %s', 'storelly-product-builder-for-woocommerce' ),
                            esc_html( $expires_at )
                        );
                        ?>
                    </div>
                <?php elseif ( ! $is_free ) : ?>
                    <div class="spbwc-license-hero__meta">
                        <span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
                        <?php esc_html_e( 'Lifetime license — no expiry', 'storelly-product-builder-for-woocommerce' ); ?>
                    </div>
                <?php else : ?>
                    <div class="spbwc-license-hero__meta">
                        <span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
                        <?php esc_html_e( 'No expiry — but limited feature set', 'storelly-product-builder-for-woocommerce' ); ?>
                    </div>
                <?php endif; ?>

                <ul class="spbwc-license-hero__benefits">
                    <?php foreach ( $plan_benefits as $benefit ) : ?>
                        <li>
                            <span class="dashicons dashicons-<?php echo esc_attr( $benefit['icon'] ); ?>" aria-hidden="true"></span>
                            <?php echo esc_html( $benefit['text'] ); ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="spbwc-license-hero__actions">
                <?php if ( $is_free ) : ?>
                    <a class="spbwc-btn-upgrade" href="<?php echo esc_url( $license_url ); ?>">
                        <span class="dashicons dashicons-superhero-alt" aria-hidden="true"></span>
                        <?php esc_html_e( 'Upgrade plan', 'storelly-product-builder-for-woocommerce' ); ?>
                    </a>
                <?php else : ?>
                    <a class="spbwc-btn-upgrade" href="<?php echo esc_url( $license_url ); ?>">
                        <span class="dashicons dashicons-admin-tools" aria-hidden="true"></span>
                        <?php esc_html_e( 'Manage license', 'storelly-product-builder-for-woocommerce' ); ?>
                    </a>
                <?php endif; ?>

                <button class="spbwc-btn-sync" id="spbwc-overview-sync-btn"
                        data-nonce="<?php echo esc_attr( $nonce_license ); ?>"
                        title="<?php esc_attr_e( 'Refresh license status from the Storelly server', 'storelly-product-builder-for-woocommerce' ); ?>">
                    <span class="dashicons dashicons-update" aria-hidden="true"></span>
                    <?php esc_html_e( 'Sync from server', 'storelly-product-builder-for-woocommerce' ); ?>
                </button>

                <div class="spbwc-license-hero__last-sync">
                    <?php esc_html_e( 'Click to verify status', 'storelly-product-builder-for-woocommerce' ); ?>
                </div>
            </div>
        </div>
    </section>

    <!-- ============ Stat cards ============ -->
    <section class="spbwc-section" aria-labelledby="spbwc-metrics-title">
        <header class="spbwc-section__header">
            <h2 id="spbwc-metrics-title" class="spbwc-section__title">
                <span class="dashicons dashicons-chart-bar" aria-hidden="true"></span>
                <?php esc_html_e( 'Key metrics', 'storelly-product-builder-for-woocommerce' ); ?>
            </h2>
            <p class="spbwc-section__subtitle">
                <?php
                if ( $is_remote_ok ) {
                    esc_html_e( 'Live numbers from your Storelly dashboard. Each card links straight to the related management screen.', 'storelly-product-builder-for-woocommerce' );
                } else {
                    esc_html_e( 'Local counts from your WordPress database. Connect your Storelly account to see live numbers.', 'storelly-product-builder-for-woocommerce' );
                }
                ?>
            </p>
        </header>

        <div class="spbwc-stat-grid">
            <article class="spbwc-stat-card spbwc-stat-card--brand">
                <div class="spbwc-stat-card__head">
                    <div class="spbwc-stat-card__icon">
                        <span class="dashicons dashicons-products" aria-hidden="true"></span>
                    </div>
                    <div class="spbwc-stat-card__label"><?php esc_html_e( 'Products', 'storelly-product-builder-for-woocommerce' ); ?></div>
                </div>
                <div class="spbwc-stat-card__value"><?php echo esc_html( number_format_i18n( $disp_products ) ); ?></div>
                <p class="spbwc-stat-card__hint">
                    <?php esc_html_e( 'WooCommerce products that have the Storelly product builder enabled.', 'storelly-product-builder-for-woocommerce' ); ?>
                </p>
                <div class="spbwc-stat-card__footer">
                    <a class="spbwc-cta-btn" href="<?php echo esc_url( admin_url( 'admin.php?page=' . SPBWC_PB_PRODUCTS_SLUG ) ); ?>">
                        <?php esc_html_e( 'Manage products', 'storelly-product-builder-for-woocommerce' ); ?>
                        <span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
                    </a>
                </div>
            </article>

            <article class="spbwc-stat-card spbwc-stat-card--success">
                <div class="spbwc-stat-card__head">
                    <div class="spbwc-stat-card__icon">
                        <span class="dashicons dashicons-editor-table" aria-hidden="true"></span>
                    </div>
                    <div class="spbwc-stat-card__label"><?php esc_html_e( 'Pricing Options', 'storelly-product-builder-for-woocommerce' ); ?></div>
                </div>
                <div class="spbwc-stat-card__value"><?php echo esc_html( number_format_i18n( $disp_pricing ) ); ?></div>
                <p class="spbwc-stat-card__hint">
                    <?php esc_html_e( 'Active option groups (material, size, finish, quantity…) configured for your products.', 'storelly-product-builder-for-woocommerce' ); ?>
                </p>
                <div class="spbwc-stat-card__footer">
                    <a class="spbwc-cta-btn" href="<?php echo esc_url( admin_url( 'admin.php?page=' . SPBWC_PB_BUILDER_SLUG ) ); ?>">
                        <?php esc_html_e( 'Edit options', 'storelly-product-builder-for-woocommerce' ); ?>
                        <span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
                    </a>
                </div>
            </article>

            <article class="spbwc-stat-card spbwc-stat-card--warning">
                <div class="spbwc-stat-card__head">
                    <div class="spbwc-stat-card__icon">
                        <span class="dashicons dashicons-cart" aria-hidden="true"></span>
                    </div>
                    <div class="spbwc-stat-card__label"><?php esc_html_e( 'Orders', 'storelly-product-builder-for-woocommerce' ); ?></div>
                </div>
                <div class="spbwc-stat-card__value"><?php echo esc_html( number_format_i18n( $disp_orders ) ); ?></div>
                <p class="spbwc-stat-card__hint">
                    <?php esc_html_e( 'All-time WooCommerce orders containing a customized Storelly product.', 'storelly-product-builder-for-woocommerce' ); ?>
                </p>
                <div class="spbwc-stat-card__footer">
                    <a class="spbwc-cta-btn" href="<?php echo esc_url( admin_url( 'admin.php?page=' . SPBWC_PB_ORDERS_SLUG ) ); ?>">
                        <?php esc_html_e( 'View orders', 'storelly-product-builder-for-woocommerce' ); ?>
                        <span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
                    </a>
                </div>
            </article>

            <article class="spbwc-stat-card spbwc-stat-card--accent">
                <div class="spbwc-stat-card__head">
                    <div class="spbwc-stat-card__icon">
                        <span class="dashicons dashicons-email-alt" aria-hidden="true"></span>
                    </div>
                    <div class="spbwc-stat-card__label"><?php esc_html_e( 'Quote Requests', 'storelly-product-builder-for-woocommerce' ); ?></div>
                </div>
                <div class="spbwc-stat-card__value"><?php echo esc_html( number_format_i18n( $disp_quotes ) ); ?></div>
                <p class="spbwc-stat-card__hint">
                    <?php esc_html_e( 'Customer-submitted quote requests awaiting your response or follow-up.', 'storelly-product-builder-for-woocommerce' ); ?>
                </p>
                <div class="spbwc-stat-card__footer">
                    <a class="spbwc-cta-btn" href="<?php echo esc_url( admin_url( 'admin.php?page=' . SPBWC_PB_QUOTES_SLUG ) ); ?>">
                        <?php esc_html_e( 'Respond to quotes', 'storelly-product-builder-for-woocommerce' ); ?>
                        <span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
                    </a>
                </div>
            </article>
        </div>
    </section>

    <?php if ( ! empty( $spbwc_design_activity ) ) : ?>
    <!-- ============ Customer design activity (M14) ============ -->
    <section class="spbwc-section" aria-labelledby="spbwc-design-activity-title">
        <header class="spbwc-section__header">
            <h2 id="spbwc-design-activity-title" class="spbwc-section__title">
                <span class="dashicons dashicons-art" aria-hidden="true"></span>
                <?php esc_html_e( 'Customer design activity', 'storelly-product-builder-for-woocommerce' ); ?>
            </h2>
            <p class="spbwc-section__subtitle">
                <?php esc_html_e( 'How buyers are saving, ordering and downloading their custom designs.', 'storelly-product-builder-for-woocommerce' ); ?>
            </p>
        </header>
        <div class="spbwc-stat-grid">
            <article class="spbwc-stat-card spbwc-stat-card--brand">
                <div class="spbwc-stat-card__head">
                    <div class="spbwc-stat-card__icon"><span class="dashicons dashicons-saved" aria-hidden="true"></span></div>
                    <div class="spbwc-stat-card__label"><?php esc_html_e( 'Saved designs', 'storelly-product-builder-for-woocommerce' ); ?></div>
                </div>
                <div class="spbwc-stat-card__value"><?php echo esc_html( number_format_i18n( (int) $spbwc_design_activity['saved_total'] ) ); ?></div>
                <p class="spbwc-stat-card__hint"><?php esc_html_e( 'Designs buyers have saved to their account for re-ordering later.', 'storelly-product-builder-for-woocommerce' ); ?></p>
            </article>

            <article class="spbwc-stat-card spbwc-stat-card--success">
                <div class="spbwc-stat-card__head">
                    <div class="spbwc-stat-card__icon"><span class="dashicons dashicons-groups" aria-hidden="true"></span></div>
                    <div class="spbwc-stat-card__label"><?php esc_html_e( 'Buyers saving designs', 'storelly-product-builder-for-woocommerce' ); ?></div>
                </div>
                <div class="spbwc-stat-card__value"><?php echo esc_html( number_format_i18n( (int) $spbwc_design_activity['saved_authors'] ) ); ?></div>
                <p class="spbwc-stat-card__hint"><?php esc_html_e( 'Distinct customers who have saved at least one design.', 'storelly-product-builder-for-woocommerce' ); ?></p>
            </article>

            <article class="spbwc-stat-card spbwc-stat-card--warning">
                <div class="spbwc-stat-card__head">
                    <div class="spbwc-stat-card__icon"><span class="dashicons dashicons-cart" aria-hidden="true"></span></div>
                    <div class="spbwc-stat-card__label"><?php esc_html_e( 'Custom design orders', 'storelly-product-builder-for-woocommerce' ); ?></div>
                </div>
                <div class="spbwc-stat-card__value"><?php echo esc_html( number_format_i18n( (int) $spbwc_design_activity['design_items'] ) ); ?></div>
                <p class="spbwc-stat-card__hint"><?php esc_html_e( 'Order line items that carry a customer design file.', 'storelly-product-builder-for-woocommerce' ); ?></p>
                <div class="spbwc-stat-card__footer">
                    <a class="spbwc-cta-btn" href="<?php echo esc_url( admin_url( 'admin.php?page=' . SPBWC_PB_ORDERS_SLUG ) ); ?>">
                        <?php esc_html_e( 'View orders', 'storelly-product-builder-for-woocommerce' ); ?>
                        <span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
                    </a>
                </div>
            </article>

            <article class="spbwc-stat-card spbwc-stat-card--accent">
                <div class="spbwc-stat-card__head">
                    <div class="spbwc-stat-card__icon"><span class="dashicons dashicons-download" aria-hidden="true"></span></div>
                    <div class="spbwc-stat-card__label"><?php esc_html_e( 'Preview downloads', 'storelly-product-builder-for-woocommerce' ); ?></div>
                </div>
                <div class="spbwc-stat-card__value"><?php echo esc_html( number_format_i18n( (int) $spbwc_design_activity['preview_dls'] ) ); ?></div>
                <p class="spbwc-stat-card__hint"><?php esc_html_e( 'Times buyers downloaded a design preview from My Account.', 'storelly-product-builder-for-woocommerce' ); ?></p>
            </article>
        </div>
    </section>
    <?php endif; ?>

    <!-- ============ Recent activity ============ -->
    <section class="spbwc-section" aria-labelledby="spbwc-activity-title">
        <header class="spbwc-section__header">
            <h2 id="spbwc-activity-title" class="spbwc-section__title">
                <span class="dashicons dashicons-list-view" aria-hidden="true"></span>
                <?php esc_html_e( 'Recent activity', 'storelly-product-builder-for-woocommerce' ); ?>
            </h2>
            <p class="spbwc-section__subtitle">
                <?php esc_html_e( 'A quick look at the latest products, orders, quotes, and designs flowing through your Storelly builder. Click any card to dive in.', 'storelly-product-builder-for-woocommerce' ); ?>
            </p>
        </header>

        <div class="spbwc-list-grid">

            <!-- Recent Products -->
            <section class="spbwc-block spbwc-block--brand">
                <header class="spbwc-block__head">
                    <h3 class="spbwc-block__title">
                        <span class="dashicons dashicons-products" aria-hidden="true"></span>
                        <?php esc_html_e( 'Recent products', 'storelly-product-builder-for-woocommerce' ); ?>
                    </h3>
                    <span class="spbwc-block__badge"><?php echo esc_html( number_format_i18n( $disp_products ) ); ?> <?php esc_html_e( 'total', 'storelly-product-builder-for-woocommerce' ); ?></span>
                </header>
                <div class="spbwc-block__body<?php echo empty( $recent_products ) ? '' : ' spbwc-block__body--flush'; ?>">
                    <?php if ( ! empty( $recent_products ) ) : ?>
                        <ul class="spbwc-list" style="padding: 0 var(--nbd-space-5);">
                            <?php foreach ( $recent_products as $p ) :
                                $thumb     = get_the_post_thumbnail_url( $p->ID, 'thumbnail' );
                                $edit_url  = get_edit_post_link( $p->ID );
                                $status    = ( $p->post_status === 'publish' ) ? 'active' : ( ( $p->post_status === 'draft' ) ? 'draft' : 'pending' );
                                $modified  = human_time_diff( get_post_modified_time( 'U', false, $p ), current_time( 'U' ) );
                            ?>
                                <a class="spbwc-list__item" href="<?php echo esc_url( $edit_url ); ?>">
                                    <div class="spbwc-list__item-thumb">
                                        <?php if ( $thumb ) : ?>
                                            <img src="<?php echo esc_url( $thumb ); ?>" alt="">
                                        <?php else : ?>
                                            <span class="dashicons dashicons-products" aria-hidden="true"></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="spbwc-list__item-body">
                                        <div class="spbwc-list__item-title"><?php echo esc_html( $p->post_title ?: __( '(no title)', 'storelly-product-builder-for-woocommerce' ) ); ?></div>
                                        <div class="spbwc-list__item-meta">
                                            <span class="spbwc-list__item-status spbwc-list__item-status--<?php echo esc_attr( $status ); ?>"><?php echo esc_html( $p->post_status ); ?></span>
                                            <span class="sep">·</span>
                                            <span><?php
                                                /* translators: %s: human time diff like "2 hours" */
                                                printf( esc_html__( 'updated %s ago', 'storelly-product-builder-for-woocommerce' ), esc_html( $modified ) );
                                            ?></span>
                                        </div>
                                    </div>
                                    <span class="spbwc-list__item-action">
                                        <span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
                                    </span>
                                </a>
                            <?php endforeach; ?>
                        </ul>
                    <?php else : ?>
                        <div class="spbwc-empty-state">
                            <div class="spbwc-empty-state__icon">
                                <span class="dashicons dashicons-products" aria-hidden="true"></span>
                            </div>
                            <div class="spbwc-empty-state__title"><?php esc_html_e( 'No products yet', 'storelly-product-builder-for-woocommerce' ); ?></div>
                            <p class="spbwc-empty-state__text">
                                <?php esc_html_e( 'Enable the product builder on a WooCommerce product to start customizing.', 'storelly-product-builder-for-woocommerce' ); ?>
                            </p>
                            <a class="spbwc-cta-btn spbwc-cta-btn--solid" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=product' ) ); ?>">
                                <span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
                                <?php esc_html_e( 'Add first product', 'storelly-product-builder-for-woocommerce' ); ?>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
                <footer class="spbwc-block__foot">
                    <a class="spbwc-block__foot-link" href="<?php echo esc_url( admin_url( 'admin.php?page=' . SPBWC_PB_PRODUCTS_SLUG ) ); ?>">
                        <?php esc_html_e( 'View all products', 'storelly-product-builder-for-woocommerce' ); ?>
                        <span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
                    </a>
                    <span class="spbwc-block__foot-meta"><?php esc_html_e( 'Last 5 updated', 'storelly-product-builder-for-woocommerce' ); ?></span>
                </footer>
            </section>

            <!-- Recent Orders -->
            <section class="spbwc-block spbwc-block--warning">
                <header class="spbwc-block__head">
                    <h3 class="spbwc-block__title">
                        <span class="dashicons dashicons-cart" aria-hidden="true"></span>
                        <?php esc_html_e( 'Recent orders', 'storelly-product-builder-for-woocommerce' ); ?>
                    </h3>
                    <span class="spbwc-block__badge"><?php echo esc_html( number_format_i18n( $disp_orders ) ); ?> <?php esc_html_e( 'total', 'storelly-product-builder-for-woocommerce' ); ?></span>
                </header>
                <div class="spbwc-block__body<?php echo empty( $recent_orders ) ? '' : ' spbwc-block__body--flush'; ?>">
                    <?php if ( ! empty( $recent_orders ) ) : ?>
                        <ul class="spbwc-list" style="padding: 0 var(--nbd-space-5);">
                            <?php foreach ( $recent_orders as $order ) :
                                $order_id   = $order->get_id();
                                $order_url  = $order->get_edit_order_url();
                                $customer   = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
                                $customer   = $customer ?: $order->get_billing_email() ?: __( 'Guest', 'storelly-product-builder-for-woocommerce' );
                                $order_date = $order->get_date_created() ? human_time_diff( $order->get_date_created()->getTimestamp(), current_time( 'U' ) ) : '';
                                $status     = $order->get_status();
                                $status_cls = ( $status === 'completed' ) ? 'active' : ( ( $status === 'on-hold' || $status === 'pending' ) ? 'pending' : 'draft' );
                            ?>
                                <a class="spbwc-list__item" href="<?php echo esc_url( $order_url ); ?>">
                                    <div class="spbwc-list__item-thumb">
                                        <span class="dashicons dashicons-cart" aria-hidden="true"></span>
                                    </div>
                                    <div class="spbwc-list__item-body">
                                        <div class="spbwc-list__item-title">
                                            <?php echo esc_html( '#' . $order_id . ' · ' . $customer ); ?>
                                        </div>
                                        <div class="spbwc-list__item-meta">
                                            <span class="spbwc-list__item-status spbwc-list__item-status--<?php echo esc_attr( $status_cls ); ?>"><?php echo esc_html( $status ); ?></span>
                                            <span class="sep">·</span>
                                            <span><?php echo wp_kses_post( wc_price( $order->get_total() ) ); ?></span>
                                            <?php if ( $order_date ) : ?>
                                                <span class="sep">·</span>
                                                <span><?php echo esc_html( $order_date ); ?> <?php esc_html_e( 'ago', 'storelly-product-builder-for-woocommerce' ); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <span class="spbwc-list__item-action">
                                        <span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
                                    </span>
                                </a>
                            <?php endforeach; ?>
                        </ul>
                    <?php else : ?>
                        <div class="spbwc-empty-state">
                            <div class="spbwc-empty-state__icon">
                                <span class="dashicons dashicons-cart" aria-hidden="true"></span>
                            </div>
                            <div class="spbwc-empty-state__title"><?php esc_html_e( 'No orders yet', 'storelly-product-builder-for-woocommerce' ); ?></div>
                            <p class="spbwc-empty-state__text">
                                <?php esc_html_e( 'Orders containing customized products will appear here when customers check out.', 'storelly-product-builder-for-woocommerce' ); ?>
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
                <footer class="spbwc-block__foot">
                    <a class="spbwc-block__foot-link" href="<?php echo esc_url( admin_url( 'admin.php?page=' . SPBWC_PB_ORDERS_SLUG ) ); ?>">
                        <?php esc_html_e( 'View all orders', 'storelly-product-builder-for-woocommerce' ); ?>
                        <span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
                    </a>
                    <span class="spbwc-block__foot-meta"><?php esc_html_e( 'Last 5', 'storelly-product-builder-for-woocommerce' ); ?></span>
                </footer>
            </section>

            <!-- Recent Printing Options -->
            <section class="spbwc-block spbwc-block--success">
                <header class="spbwc-block__head">
                    <h3 class="spbwc-block__title">
                        <span class="dashicons dashicons-editor-table" aria-hidden="true"></span>
                        <?php esc_html_e( 'Printing options', 'storelly-product-builder-for-woocommerce' ); ?>
                    </h3>
                    <span class="spbwc-block__badge"><?php echo esc_html( number_format_i18n( $disp_pricing ) ); ?> <?php esc_html_e( 'total', 'storelly-product-builder-for-woocommerce' ); ?></span>
                </header>
                <div class="spbwc-block__body">
                    <div class="spbwc-empty-state">
                        <div class="spbwc-empty-state__icon">
                            <span class="dashicons dashicons-editor-table" aria-hidden="true"></span>
                        </div>
                        <div class="spbwc-empty-state__title">
                            <?php
                            if ( $disp_pricing > 0 ) {
                                esc_html_e( 'Active option groups', 'storelly-product-builder-for-woocommerce' );
                            } else {
                                esc_html_e( 'No option groups yet', 'storelly-product-builder-for-woocommerce' );
                            }
                            ?>
                        </div>
                        <p class="spbwc-empty-state__text">
                            <?php esc_html_e( 'Build dynamic pricing — material, size, quantity, conditional logic — for your products.', 'storelly-product-builder-for-woocommerce' ); ?>
                        </p>
                        <a class="spbwc-cta-btn spbwc-cta-btn--solid" href="<?php echo esc_url( admin_url( 'admin.php?page=' . SPBWC_PB_BUILDER_SLUG ) ); ?>">
                            <span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
                            <?php esc_html_e( 'Open builder', 'storelly-product-builder-for-woocommerce' ); ?>
                        </a>
                    </div>
                </div>
                <footer class="spbwc-block__foot">
                    <a class="spbwc-block__foot-link" href="<?php echo esc_url( admin_url( 'admin.php?page=' . SPBWC_PB_BUILDER_SLUG ) ); ?>">
                        <?php esc_html_e( 'Manage options', 'storelly-product-builder-for-woocommerce' ); ?>
                        <span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
                    </a>
                </footer>
            </section>

            <!-- Recent Quotes -->
            <section class="spbwc-block spbwc-block--accent">
                <header class="spbwc-block__head">
                    <h3 class="spbwc-block__title">
                        <span class="dashicons dashicons-email-alt" aria-hidden="true"></span>
                        <?php esc_html_e( 'Quote requests', 'storelly-product-builder-for-woocommerce' ); ?>
                    </h3>
                    <span class="spbwc-block__badge"><?php echo esc_html( number_format_i18n( $disp_quotes ) ); ?> <?php esc_html_e( 'total', 'storelly-product-builder-for-woocommerce' ); ?></span>
                </header>
                <div class="spbwc-block__body">
                    <div class="spbwc-empty-state">
                        <div class="spbwc-empty-state__icon">
                            <span class="dashicons dashicons-email-alt" aria-hidden="true"></span>
                        </div>
                        <div class="spbwc-empty-state__title"><?php esc_html_e( 'No pending quotes', 'storelly-product-builder-for-woocommerce' ); ?></div>
                        <p class="spbwc-empty-state__text">
                            <?php esc_html_e( 'B2B quote requests from your customers will appear here. Respond to convert them into orders.', 'storelly-product-builder-for-woocommerce' ); ?>
                        </p>
                        <a class="spbwc-cta-btn" href="<?php echo esc_url( admin_url( 'admin.php?page=' . SPBWC_PB_QUOTES_SLUG ) ); ?>">
                            <?php esc_html_e( 'Open quotes', 'storelly-product-builder-for-woocommerce' ); ?>
                            <span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
                        </a>
                    </div>
                </div>
                <footer class="spbwc-block__foot">
                    <a class="spbwc-block__foot-link" href="<?php echo esc_url( admin_url( 'admin.php?page=' . SPBWC_PB_QUOTES_SLUG ) ); ?>">
                        <?php esc_html_e( 'View all quotes', 'storelly-product-builder-for-woocommerce' ); ?>
                        <span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
                    </a>
                </footer>
            </section>

            <!-- Customer Design Files -->
            <section class="spbwc-block spbwc-block--brand">
                <header class="spbwc-block__head">
                    <h3 class="spbwc-block__title">
                        <span class="dashicons dashicons-art" aria-hidden="true"></span>
                        <?php esc_html_e( 'Customer designs', 'storelly-product-builder-for-woocommerce' ); ?>
                    </h3>
                    <span class="spbwc-block__pro-badge" title="<?php esc_attr_e( 'Premium feature', 'storelly-product-builder-for-woocommerce' ); ?>">
                        <span class="dashicons dashicons-star-filled" aria-hidden="true"></span>
                        <?php esc_html_e( 'Pro', 'storelly-product-builder-for-woocommerce' ); ?>
                    </span>
                </header>
                <div class="spbwc-block__body">
                    <div class="spbwc-empty-state">
                        <div class="spbwc-empty-state__icon">
                            <span class="dashicons dashicons-art" aria-hidden="true"></span>
                        </div>
                        <div class="spbwc-empty-state__title"><?php esc_html_e( 'No saved designs yet', 'storelly-product-builder-for-woocommerce' ); ?></div>
                        <p class="spbwc-empty-state__text">
                            <?php esc_html_e( 'Print-ready design files customers create with the Storelly designer canvas will be listed here for download and review.', 'storelly-product-builder-for-woocommerce' ); ?>
                        </p>
                        <?php if ( $is_free ) : ?>
                            <a class="spbwc-cta-btn spbwc-cta-btn--solid" href="<?php echo esc_url( $license_url ); ?>">
                                <span class="dashicons dashicons-superhero-alt" aria-hidden="true"></span>
                                <?php esc_html_e( 'Upgrade to unlock', 'storelly-product-builder-for-woocommerce' ); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <footer class="spbwc-block__foot">
                    <a class="spbwc-block__foot-link" href="<?php echo esc_url( admin_url( 'admin.php?page=' . SPBWC_PB_ORDERS_SLUG ) ); ?>">
                        <?php esc_html_e( 'View order designs', 'storelly-product-builder-for-woocommerce' ); ?>
                        <span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
                    </a>
                </footer>
            </section>

            <!-- Templates -->
            <section class="spbwc-block spbwc-block--gold">
                <header class="spbwc-block__head">
                    <h3 class="spbwc-block__title">
                        <span class="dashicons dashicons-layout" aria-hidden="true"></span>
                        <?php esc_html_e( 'Design templates', 'storelly-product-builder-for-woocommerce' ); ?>
                    </h3>
                    <span class="spbwc-block__pro-badge" title="<?php esc_attr_e( 'Premium feature', 'storelly-product-builder-for-woocommerce' ); ?>">
                        <span class="dashicons dashicons-star-filled" aria-hidden="true"></span>
                        <?php esc_html_e( 'Pro', 'storelly-product-builder-for-woocommerce' ); ?>
                    </span>
                </header>
                <div class="spbwc-block__body">
                    <div class="spbwc-empty-state">
                        <div class="spbwc-empty-state__icon">
                            <span class="dashicons dashicons-layout" aria-hidden="true"></span>
                        </div>
                        <div class="spbwc-empty-state__title"><?php esc_html_e( 'No templates yet', 'storelly-product-builder-for-woocommerce' ); ?></div>
                        <p class="spbwc-empty-state__text">
                            <?php esc_html_e( 'Reusable design templates speed up customer customization. Import from the Storelly marketplace or create your own.', 'storelly-product-builder-for-woocommerce' ); ?>
                        </p>
                        <?php if ( $is_free ) : ?>
                            <a class="spbwc-cta-btn spbwc-cta-btn--solid" href="<?php echo esc_url( $license_url ); ?>">
                                <span class="dashicons dashicons-superhero-alt" aria-hidden="true"></span>
                                <?php esc_html_e( 'Upgrade to unlock', 'storelly-product-builder-for-woocommerce' ); ?>
                            </a>
                        <?php else : ?>
                            <a class="spbwc-cta-btn spbwc-cta-btn--solid" href="https://storelly.com/marketplace" target="_blank" rel="noopener">
                                <span class="dashicons dashicons-download" aria-hidden="true"></span>
                                <?php esc_html_e( 'Browse marketplace', 'storelly-product-builder-for-woocommerce' ); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <footer class="spbwc-block__foot">
                    <a class="spbwc-block__foot-link" href="https://storelly.com/marketplace" target="_blank" rel="noopener">
                        <?php esc_html_e( 'Browse marketplace', 'storelly-product-builder-for-woocommerce' ); ?>
                        <span class="dashicons dashicons-external" aria-hidden="true"></span>
                    </a>
                </footer>
            </section>

        </div>
    </section>

    <!-- ============ Quick actions ============ -->
    <section class="spbwc-section" aria-labelledby="spbwc-actions-title">
        <header class="spbwc-section__header">
            <h2 id="spbwc-actions-title" class="spbwc-section__title">
                <span class="dashicons dashicons-admin-tools" aria-hidden="true"></span>
                <?php esc_html_e( 'Quick actions', 'storelly-product-builder-for-woocommerce' ); ?>
            </h2>
            <p class="spbwc-section__subtitle">
                <?php esc_html_e( 'Common tasks for managing your product builder. Pick a card to jump into the right section.', 'storelly-product-builder-for-woocommerce' ); ?>
            </p>
        </header>

        <div class="spbwc-quick-grid">
            <a class="spbwc-quick-card" href="<?php echo esc_url( admin_url( 'admin.php?page=' . SPBWC_PB_BUILDER_SLUG ) ); ?>">
                <div class="spbwc-quick-card__head">
                    <div class="spbwc-quick-card__icon">
                        <span class="dashicons dashicons-editor-table" aria-hidden="true"></span>
                    </div>
                    <h3 class="spbwc-quick-card__title">
                        <?php esc_html_e( 'Build pricing options', 'storelly-product-builder-for-woocommerce' ); ?>
                    </h3>
                </div>
                <p class="spbwc-quick-card__desc">
                    <?php esc_html_e( 'Create dynamic price rules — material, size, quantity tiers, conditional logic — for any WooCommerce product.', 'storelly-product-builder-for-woocommerce' ); ?>
                </p>
                <div class="spbwc-quick-card__footer">
                    <span class="spbwc-cta-btn">
                        <?php esc_html_e( 'Open builder', 'storelly-product-builder-for-woocommerce' ); ?>
                        <span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
                    </span>
                </div>
            </a>

            <a class="spbwc-quick-card" href="<?php echo esc_url( admin_url( 'admin.php?page=' . SPBWC_PB_PRODUCTS_SLUG ) ); ?>">
                <div class="spbwc-quick-card__head">
                    <div class="spbwc-quick-card__icon">
                        <span class="dashicons dashicons-products" aria-hidden="true"></span>
                    </div>
                    <h3 class="spbwc-quick-card__title">
                        <?php esc_html_e( 'Manage products', 'storelly-product-builder-for-woocommerce' ); ?>
                    </h3>
                </div>
                <p class="spbwc-quick-card__desc">
                    <?php esc_html_e( 'Review which products have the builder enabled, assign option groups, and configure per-product designer settings.', 'storelly-product-builder-for-woocommerce' ); ?>
                </p>
                <div class="spbwc-quick-card__footer">
                    <span class="spbwc-cta-btn">
                        <?php esc_html_e( 'View products', 'storelly-product-builder-for-woocommerce' ); ?>
                        <span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
                    </span>
                </div>
            </a>

            <a class="spbwc-quick-card" href="<?php echo esc_url( admin_url( 'admin.php?page=' . SPBWC_PB_ORDERS_SLUG ) ); ?>">
                <div class="spbwc-quick-card__head">
                    <div class="spbwc-quick-card__icon">
                        <span class="dashicons dashicons-cart" aria-hidden="true"></span>
                    </div>
                    <h3 class="spbwc-quick-card__title">
                        <?php esc_html_e( 'Review orders', 'storelly-product-builder-for-woocommerce' ); ?>
                    </h3>
                </div>
                <p class="spbwc-quick-card__desc">
                    <?php esc_html_e( 'Inspect customized orders, download print-ready designs, and export PDFs for your print partners.', 'storelly-product-builder-for-woocommerce' ); ?>
                </p>
                <div class="spbwc-quick-card__footer">
                    <span class="spbwc-cta-btn">
                        <?php esc_html_e( 'Open orders', 'storelly-product-builder-for-woocommerce' ); ?>
                        <span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
                    </span>
                </div>
            </a>

            <a class="spbwc-quick-card" href="<?php echo esc_url( admin_url( 'admin.php?page=' . SPBWC_PB_QUOTES_SLUG ) ); ?>">
                <div class="spbwc-quick-card__head">
                    <div class="spbwc-quick-card__icon">
                        <span class="dashicons dashicons-email-alt" aria-hidden="true"></span>
                    </div>
                    <h3 class="spbwc-quick-card__title">
                        <?php esc_html_e( 'Handle quotes', 'storelly-product-builder-for-woocommerce' ); ?>
                    </h3>
                </div>
                <p class="spbwc-quick-card__desc">
                    <?php esc_html_e( 'Respond to B2B quote requests, convert approved quotes into orders, and track customer follow-ups.', 'storelly-product-builder-for-woocommerce' ); ?>
                </p>
                <div class="spbwc-quick-card__footer">
                    <span class="spbwc-cta-btn">
                        <?php esc_html_e( 'View quotes', 'storelly-product-builder-for-woocommerce' ); ?>
                        <span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
                    </span>
                </div>
            </a>

            <a class="spbwc-quick-card" href="<?php echo esc_url( $license_url ); ?>">
                <div class="spbwc-quick-card__head">
                    <div class="spbwc-quick-card__icon">
                        <span class="dashicons dashicons-admin-network" aria-hidden="true"></span>
                    </div>
                    <h3 class="spbwc-quick-card__title">
                        <?php esc_html_e( 'License & billing', 'storelly-product-builder-for-woocommerce' ); ?>
                    </h3>
                </div>
                <p class="spbwc-quick-card__desc">
                    <?php esc_html_e( 'Activate or change your license key, view subscription details, and manage billing on the Storelly portal.', 'storelly-product-builder-for-woocommerce' ); ?>
                </p>
                <div class="spbwc-quick-card__footer">
                    <span class="spbwc-cta-btn">
                        <?php esc_html_e( 'Open license', 'storelly-product-builder-for-woocommerce' ); ?>
                        <span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
                    </span>
                </div>
            </a>

            <a class="spbwc-quick-card" href="<?php echo esc_url( admin_url( 'admin.php?page=' . SPBWC_PB_OPTIONS_SLUG ) ); ?>">
                <div class="spbwc-quick-card__head">
                    <div class="spbwc-quick-card__icon">
                        <span class="dashicons dashicons-admin-settings" aria-hidden="true"></span>
                    </div>
                    <h3 class="spbwc-quick-card__title">
                        <?php esc_html_e( 'Plugin settings', 'storelly-product-builder-for-woocommerce' ); ?>
                    </h3>
                </div>
                <p class="spbwc-quick-card__desc">
                    <?php esc_html_e( 'Configure global options, API sync, designer fonts, watermarks, and PDF export defaults.', 'storelly-product-builder-for-woocommerce' ); ?>
                </p>
                <div class="spbwc-quick-card__footer">
                    <span class="spbwc-cta-btn">
                        <?php esc_html_e( 'Open settings', 'storelly-product-builder-for-woocommerce' ); ?>
                        <span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
                    </span>
                </div>
            </a>
        </div>
    </section>

    <!-- ============ Help / resources ============ -->
    <section class="spbwc-help-section" aria-labelledby="spbwc-help-title">
        <div class="spbwc-help-section__body">
            <h3 id="spbwc-help-title" class="spbwc-help-section__title">
                <span class="dashicons dashicons-sos" aria-hidden="true"></span>
                <?php esc_html_e( 'Need a hand?', 'storelly-product-builder-for-woocommerce' ); ?>
            </h3>
            <p class="spbwc-help-section__text">
                <?php esc_html_e( 'Read the documentation, check the latest changelog, or reach out to the Storelly team. We typically respond within one business day on weekdays.', 'storelly-product-builder-for-woocommerce' ); ?>
            </p>
        </div>
        <div class="spbwc-help-section__links">
            <a class="spbwc-cta-btn spbwc-cta-btn--ghost" href="https://storelly.com/docs" target="_blank" rel="noopener">
                <span class="dashicons dashicons-book" aria-hidden="true"></span>
                <?php esc_html_e( 'Documentation', 'storelly-product-builder-for-woocommerce' ); ?>
            </a>
            <a class="spbwc-cta-btn spbwc-cta-btn--ghost" href="https://storelly.com/changelog" target="_blank" rel="noopener">
                <span class="dashicons dashicons-megaphone" aria-hidden="true"></span>
                <?php esc_html_e( 'Changelog', 'storelly-product-builder-for-woocommerce' ); ?>
            </a>
            <a class="spbwc-cta-btn" href="https://storelly.com/support" target="_blank" rel="noopener">
                <span class="dashicons dashicons-email-alt" aria-hidden="true"></span>
                <?php esc_html_e( 'Contact support', 'storelly-product-builder-for-woocommerce' ); ?>
            </a>
        </div>
    </section>

    <script>
    (function($){
        var $btn = $('#spbwc-overview-sync-btn');
        if ( ! $btn.length ) { return; }
        var btnHtml = $btn.html();
        $btn.on('click', function(){
            if ( $btn.hasClass('is-loading') ) { return; }
            $btn.addClass('is-loading').prop('disabled', true)
                .html('<span class="dashicons dashicons-update" aria-hidden="true"></span> <?php echo esc_js( __( 'Syncing...', 'storelly-product-builder-for-woocommerce' ) ); ?>');
            $.post(ajaxurl, {
                action: 'spbwc_license_sync',
                nonce: $btn.data('nonce')
            }, function(res){
                if ( res.success ) {
                    location.reload();
                } else {
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
<?php // phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
