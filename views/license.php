<?php
/**
 * Account & Plan page (SPEC_ACCOUNT_PLAN_UX — U2/U3/U4).
 *
 * One home for: connection status, plan/entitlement, the caps comparison matrix, and the
 * connect-back upgrade flow. "Unlocked" is driven by SPBWC_License_Manager::can() (never by
 * raw consent flags) so the status never lies.
 *
 * Variables available:
 *   $license       (array)  — current license (now includes plan_slug + caps[])
 *   $packages      (array)  — live packages (kept for price override; matrix uses plan_matrix())
 *   $nonce         (string) — license AJAX nonce (sync / activate)
 *   $connect_nonce (string) — cloud connect/disconnect AJAX nonce
 *   $connected     (bool)   — store linked to Storelly Cloud (consent on)
 *   $just_connected(bool)   — true on the first load right after a successful connect (welcome banner)
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- view-local vars.

$just_connected = ! empty( $just_connected );
$status       = isset( $license['status'] ) ? (string) $license['status'] : 'free';
$active       = SPBWC_License_Manager::cloud_license_active();
$expired      = ( 'expired' === $status );
$plan_family  = SPBWC_License_Manager::plan_family( isset( $license['plan_slug'] ) ? $license['plan_slug'] : 'free' );
$pkg_name     = isset( $license['package_name'] ) ? (string) $license['package_name'] : 'Free';
$expires_at   = ! empty( $license['expires_at'] ) ? (string) $license['expires_at'] : '';
$synced_at    = ! empty( $license['synced_at'] ) ? (string) $license['synced_at'] : '';
$have_caps    = isset( $license['caps'] ) && is_array( $license['caps'] ) ? $license['caps'] : array();

// 4-state model (connected? × licensed?). Licensed states are only reachable while connected.
if ( ! $connected ) {
    $state = 'S0'; // not connected
} elseif ( $expired ) {
    $state = 'S3'; // connected, expired
} elseif ( $active ) {
    $state = 'S2'; // connected, licensed
} else {
    $state = 'S1'; // connected, free
}

$matrix       = SPBWC_License_Manager::plan_matrix();
$caps_catalog = SPBWC_License_Manager::caps_catalog();
$can_checkout = class_exists( 'SPBWC_Cloud_Activation' );
$std_url      = $can_checkout ? SPBWC_Cloud_Activation::checkout_url( 'cloud-standard-monthly' ) : '#';
$b2b_url      = $can_checkout ? SPBWC_Cloud_Activation::checkout_url( 'cloud-b2b-monthly' ) : '#';
$dash_url     = 'https://app.storelly.com/login?redirect=woocomerce';

/** ✓ / 🔒 / — cell for a cap row in a given plan family column. */
$cell = function ( $cap_row, $col_family ) {
    $included = in_array( $col_family, $cap_row['plans'], true );
    if ( ! $included ) {
        return '<span class="spbwc-cm__no" aria-label="' . esc_attr__( 'Not included', 'storelly-product-builder-for-woocommerce' ) . '">—</span>';
    }
    return '<span class="dashicons dashicons-yes-alt spbwc-cm__yes" aria-hidden="true"></span>';
};
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
                    <span class="dashicons dashicons-admin-users" aria-hidden="true"></span>
                    <?php esc_html_e( 'Account &amp; Plan', 'storelly-product-builder-for-woocommerce' ); ?>
                </h1>
                <p class="spbwc-page-hero__subtitle">
                    <?php esc_html_e( 'Everything runs free inside your WordPress. Cloud features — print-ready PDF and B2B services — run on app.storelly.com and need a plan.', 'storelly-product-builder-for-woocommerce' ); ?>
                </p>
            </div>
            <div class="spbwc-page-hero__actions">
                <a href="https://storelly.com/docs" target="_blank" rel="noopener noreferrer"
                   class="spbwc-cta-btn spbwc-cta-btn--ghost">
                    <span class="dashicons dashicons-book-alt" aria-hidden="true"></span>
                    <?php esc_html_e( 'Documentation', 'storelly-product-builder-for-woocommerce' ); ?>
                </a>
            </div>
        </div>
    </header>

    <?php if ( $just_connected ) : ?>
    <!-- ── One-time welcome banner (post-connect) ── -->
    <div class="spbwc-welcome-banner" role="status">
        <span class="spbwc-welcome-banner__icon dashicons dashicons-yes-alt" aria-hidden="true"></span>
        <div class="spbwc-welcome-banner__body">
            <strong class="spbwc-welcome-banner__title"><?php esc_html_e( '🎉 You’re connected to Storelly Cloud!', 'storelly-product-builder-for-woocommerce' ); ?></strong>
            <span class="spbwc-welcome-banner__text"><?php esc_html_e( 'Your free account is ready and this store is linked. Choose a plan below whenever you want to unlock print-ready PDF and B2B services.', 'storelly-product-builder-for-woocommerce' ); ?></span>
        </div>
        <button type="button" class="spbwc-welcome-banner__close" aria-label="<?php esc_attr_e( 'Dismiss', 'storelly-product-builder-for-woocommerce' ); ?>">
            <span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
        </button>
    </div>
    <?php endif; ?>

    <!-- ── Status card (S0–S3) ── -->
    <section class="spbwc-license-hero spbwc-account-card<?php echo ( 'S2' === $state ) ? ' spbwc-license-hero--premium' : ''; ?>"
             id="spbwc-account-card" data-nonce="<?php echo esc_attr( $connect_nonce ); ?>">
        <div class="spbwc-license-hero__grid">
            <div class="spbwc-license-hero__info">
                <div class="spbwc-license-hero__eyebrow">
                    <?php if ( 'S0' === $state ) : ?>
                        <span class="spbwc-block__badge spbwc-block__badge--muted">
                            <span class="dashicons dashicons-minus" aria-hidden="true"></span>
                            <?php esc_html_e( 'Not connected', 'storelly-product-builder-for-woocommerce' ); ?>
                        </span>
                    <?php elseif ( 'S3' === $state ) : ?>
                        <span class="spbwc-block__badge spbwc-block__badge--warn">
                            <span class="dashicons dashicons-warning" aria-hidden="true"></span>
                            <?php esc_html_e( 'Plan expired', 'storelly-product-builder-for-woocommerce' ); ?>
                        </span>
                    <?php else : ?>
                        <span class="spbwc-block__badge">
                            <span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
                            <?php esc_html_e( 'Connected', 'storelly-product-builder-for-woocommerce' ); ?>
                        </span>
                    <?php endif; ?>
                </div>

                <div class="spbwc-license-hero__title">
                    <span class="spbwc-license-hero__pkg">
                        <?php
                        echo esc_html(
                            'S2' === $state ? $pkg_name : __( 'Free plan', 'storelly-product-builder-for-woocommerce' )
                        );
                        ?>
                    </span>
                </div>

                <p class="spbwc-license-hero__meta">
                    <?php
                    if ( 'S0' === $state ) {
                        esc_html_e( 'All local tools already work, free. Choose a plan below to unlock Cloud features — we create your Storelly account and link this store automatically, then open secure checkout.', 'storelly-product-builder-for-woocommerce' );
                    } elseif ( 'S1' === $state ) {
                        esc_html_e( 'Connected. Cloud features are locked — choose a plan below to unlock print-ready PDF and B2B services.', 'storelly-product-builder-for-woocommerce' );
                    } elseif ( 'S2' === $state ) {
                        if ( $expires_at ) {
                            printf(
                                /* translators: %s: expiry date */
                                esc_html__( 'Active until %s. Your Cloud features are unlocked.', 'storelly-product-builder-for-woocommerce' ),
                                esc_html( $expires_at )
                            );
                        } else {
                            esc_html_e( 'Active. Your Cloud features are unlocked.', 'storelly-product-builder-for-woocommerce' );
                        }
                    } else { // S3
                        esc_html_e( 'Your plan has expired. Local tools still work fully; Cloud features are paused until you renew.', 'storelly-product-builder-for-woocommerce' );
                    }
                    ?>
                </p>
            </div>

            <div class="spbwc-license-hero__actions">
                <?php if ( 'S0' === $state ) : ?>
                    <a href="#spbwc-plans" class="spbwc-btn-upgrade">
                        <span class="dashicons dashicons-awards" aria-hidden="true"></span>
                        <?php esc_html_e( 'Choose a plan', 'storelly-product-builder-for-woocommerce' ); ?>
                    </a>
                    <p class="spbwc-license-hero__note">
                        <?php esc_html_e( 'Free account &amp; checkout happen in one step. Already bought on the web? Use “Advanced” below.', 'storelly-product-builder-for-woocommerce' ); ?>
                    </p>
                <?php elseif ( 'S1' === $state ) : ?>
                    <a href="#spbwc-plans" class="spbwc-btn-upgrade">
                        <span class="dashicons dashicons-awards" aria-hidden="true"></span>
                        <?php esc_html_e( 'Choose a plan', 'storelly-product-builder-for-woocommerce' ); ?>
                    </a>
                <?php elseif ( 'S2' === $state ) : ?>
                    <a href="<?php echo esc_url( $dash_url ); ?>" target="_blank" rel="noopener noreferrer" class="spbwc-btn-upgrade">
                        <span class="dashicons dashicons-admin-network" aria-hidden="true"></span>
                        <?php esc_html_e( 'Manage subscription', 'storelly-product-builder-for-woocommerce' ); ?>
                    </a>
                <?php else : // S3 ?>
                    <a href="<?php echo esc_url( $b2b_url ); ?>" class="spbwc-btn-upgrade">
                        <span class="dashicons dashicons-update" aria-hidden="true"></span>
                        <?php esc_html_e( 'Renew plan', 'storelly-product-builder-for-woocommerce' ); ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- ── Plans & features ── -->
    <section class="spbwc-section" id="spbwc-plans">
        <div class="spbwc-section__header">
            <h2 class="spbwc-section__title">
                <span class="dashicons dashicons-star-filled" aria-hidden="true"></span>
                <?php esc_html_e( 'Plans &amp; features', 'storelly-product-builder-for-woocommerce' ); ?>
            </h2>
            <p class="spbwc-section__subtitle">
                <?php esc_html_e( 'Compare what each plan unlocks. Billed monthly or annually (2 months free on annual). Upgrade anytime — your local tools never change.', 'storelly-product-builder-for-woocommerce' ); ?>
            </p>
        </div>

        <div class="spbwc-cm-wrap">
            <table class="spbwc-cm">
                <thead>
                    <tr>
                        <th class="spbwc-cm__feature"><?php esc_html_e( 'Feature', 'storelly-product-builder-for-woocommerce' ); ?></th>
                        <?php foreach ( $matrix as $plan ) :
                            $is_current = ( $plan['family'] === $plan_family );
                            ?>
                        <th class="spbwc-cm__plan<?php echo $is_current ? ' is-current' : ''; ?>">
                            <span class="spbwc-cm__plan-name"><?php echo esc_html( $plan['name'] ); ?></span>
                            <span class="spbwc-cm__plan-price">
                                <?php
                                if ( $plan['price_monthly'] > 0 ) {
                                    printf(
                                        /* translators: 1: monthly price, 2: annual price */
                                        esc_html__( '$%1$s/mo · $%2$s/yr', 'storelly-product-builder-for-woocommerce' ),
                                        esc_html( number_format( (float) $plan['price_monthly'], 0 ) ),
                                        esc_html( number_format( (float) $plan['price_annual'], 0 ) )
                                    );
                                } else {
                                    esc_html_e( 'Free', 'storelly-product-builder-for-woocommerce' );
                                }
                                ?>
                            </span>
                            <span class="spbwc-cm__plan-tag"><?php echo esc_html( $plan['tagline'] ); ?></span>
                            <span class="spbwc-cm__plan-cta">
                                <?php
                                if ( $is_current ) {
                                    echo '<span class="spbwc-cm__current">' . esc_html__( 'Current', 'storelly-product-builder-for-woocommerce' ) . '</span>';
                                } elseif ( 'standard' === $plan['family'] ) {
                                    echo '<a class="spbwc-cta-btn spbwc-cta-btn--solid spbwc-cta-btn--sm" href="' . esc_url( $std_url ) . '">' . esc_html__( 'Choose', 'storelly-product-builder-for-woocommerce' ) . '</a>';
                                } elseif ( 'b2b' === $plan['family'] ) {
                                    echo '<a class="spbwc-cta-btn spbwc-cta-btn--solid spbwc-cta-btn--sm" href="' . esc_url( $b2b_url ) . '">' . esc_html__( 'Choose', 'storelly-product-builder-for-woocommerce' ) . '</a>';
                                } else {
                                    echo '<span class="spbwc-cm__current spbwc-cm__current--muted">' . esc_html__( 'No payment', 'storelly-product-builder-for-woocommerce' ) . '</span>';
                                }
                                ?>
                            </span>
                        </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <tr class="spbwc-cm__local-row">
                        <td class="spbwc-cm__feature">
                            <strong><?php esc_html_e( 'All local tools — builder, options, quotes, custom orders', 'storelly-product-builder-for-woocommerce' ); ?></strong>
                            <span class="spbwc-cm__hint"><?php esc_html_e( 'unlimited, no account needed', 'storelly-product-builder-for-woocommerce' ); ?></span>
                        </td>
                        <?php foreach ( $matrix as $plan ) : ?>
                        <td class="<?php echo ( $plan['family'] === $plan_family ) ? 'is-current' : ''; ?>">
                            <span class="dashicons dashicons-yes-alt spbwc-cm__yes" aria-hidden="true"></span>
                        </td>
                        <?php endforeach; ?>
                    </tr>
                    <?php foreach ( $caps_catalog as $row ) : ?>
                    <tr>
                        <td class="spbwc-cm__feature"><?php echo esc_html( $row['label'] ); ?></td>
                        <?php foreach ( $matrix as $plan ) : ?>
                        <td class="<?php echo ( $plan['family'] === $plan_family ) ? 'is-current' : ''; ?>">
                            <?php echo wp_kses_post( $cell( $row, $plan['family'] ) ); ?>
                        </td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <p class="spbwc-cm-note">
            <?php esc_html_e( 'Payment and billing are handled securely on app.storelly.com — your card never touches WordPress. After purchase you are returned here and your plan unlocks automatically.', 'storelly-product-builder-for-woocommerce' ); ?>
        </p>
    </section>

    <!-- ── Advanced: connection, license key, diagnostics ── -->
    <details class="spbwc-advanced spbwc-advanced--adv">
        <summary class="spbwc-advanced__summary">
            <span class="dashicons dashicons-admin-tools" aria-hidden="true"></span>
            <?php esc_html_e( 'Advanced — connection, manual license key &amp; diagnostics', 'storelly-product-builder-for-woocommerce' ); ?>
        </summary>

        <div class="spbwc-adv-grid">
            <!-- Connection -->
            <div class="spbwc-adv-item">
                <div class="spbwc-adv-item__head">
                    <h4 class="spbwc-adv-item__title">
                        <span class="dashicons dashicons-cloud" aria-hidden="true"></span>
                        <?php esc_html_e( 'Cloud connection', 'storelly-product-builder-for-woocommerce' ); ?>
                    </h4>
                    <p class="spbwc-adv-item__desc"><?php esc_html_e( 'Connecting creates your free Storelly account and links this store. Nothing is sent until you click. Disconnecting stops all Cloud features; your local data is never touched.', 'storelly-product-builder-for-woocommerce' ); ?></p>
                </div>
                <div class="spbwc-adv-item__control spbwc-action-btns">
                    <?php if ( $connected ) : ?>
                        <a href="<?php echo esc_url( $dash_url ); ?>" target="_blank" rel="noopener noreferrer" class="spbwc-cta-btn spbwc-cta-btn--ghost">
                            <span class="dashicons dashicons-external" aria-hidden="true"></span>
                            <?php esc_html_e( 'Open Storelly dashboard', 'storelly-product-builder-for-woocommerce' ); ?>
                        </a>
                        <button type="button" class="spbwc-cta-btn spbwc-cta-btn--ghost spbwc-do-disconnect" data-nonce="<?php echo esc_attr( $connect_nonce ); ?>">
                            <span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
                            <?php esc_html_e( 'Disconnect', 'storelly-product-builder-for-woocommerce' ); ?>
                        </button>
                    <?php else : ?>
                        <button type="button" class="spbwc-cta-btn spbwc-cta-btn--solid spbwc-do-connect" data-nonce="<?php echo esc_attr( $connect_nonce ); ?>">
                            <span class="dashicons dashicons-cloud" aria-hidden="true"></span>
                            <span class="spbwc-do-connect__label"><?php esc_html_e( 'Connect to Storelly — free', 'storelly-product-builder-for-woocommerce' ); ?></span>
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ( $connected ) : ?>
            <!-- Refresh status (was the hero "Sync") -->
            <div class="spbwc-adv-item">
                <div class="spbwc-adv-item__head">
                    <h4 class="spbwc-adv-item__title">
                        <span class="dashicons dashicons-update" aria-hidden="true"></span>
                        <?php esc_html_e( 'Refresh plan status', 'storelly-product-builder-for-woocommerce' ); ?>
                    </h4>
                    <p class="spbwc-adv-item__desc">
                        <?php esc_html_e( 'Re-check your plan and expiry from Storelly. Normally this happens automatically after a purchase — use this only if your plan looks out of date.', 'storelly-product-builder-for-woocommerce' ); ?>
                        <span class="spbwc-adv-item__meta" id="spbwc-sync-status">
                        <?php
                        if ( $synced_at ) {
                            printf(
                                /* translators: %s: last sync datetime */
                                esc_html__( 'Last synced: %s', 'storelly-product-builder-for-woocommerce' ),
                                esc_html( $synced_at )
                            );
                        }
                        ?>
                        </span>
                    </p>
                </div>
                <div class="spbwc-adv-item__control spbwc-action-btns">
                    <button id="spbwc-sync-btn" type="button" class="spbwc-cta-btn spbwc-cta-btn--ghost" data-nonce="<?php echo esc_attr( $nonce ); ?>">
                        <span class="dashicons dashicons-update" aria-hidden="true"></span>
                        <?php esc_html_e( 'Refresh now', 'storelly-product-builder-for-woocommerce' ); ?>
                    </button>
                </div>
            </div>
            <?php endif; ?>

            <!-- Manual license key -->
            <div class="spbwc-adv-item">
                <div class="spbwc-adv-item__head">
                    <h4 class="spbwc-adv-item__title">
                        <span class="dashicons dashicons-admin-network" aria-hidden="true"></span>
                        <label for="spbwc-license-key"><?php esc_html_e( 'Have a license key?', 'storelly-product-builder-for-woocommerce' ); ?></label>
                    </h4>
                    <p class="spbwc-adv-item__desc"><?php esc_html_e( 'Use this only if you bought on the web and were given a key. Normally, choosing a plan above activates automatically.', 'storelly-product-builder-for-woocommerce' ); ?></p>
                    <p class="spbwc-adv-item__meta" id="spbwc-activate-status"></p>
                </div>
                <div class="spbwc-adv-item__control spbwc-adv-item__control--inline">
                    <input type="text" id="spbwc-license-key" class="spbwc-input spbwc-adv-item__input"
                           placeholder="<?php esc_attr_e( 'Paste your license key', 'storelly-product-builder-for-woocommerce' ); ?>" />
                    <button type="button" class="spbwc-cta-btn spbwc-cta-btn--solid" id="spbwc-license-activate" data-nonce="<?php echo esc_attr( $nonce ); ?>">
                        <?php esc_html_e( 'Activate', 'storelly-product-builder-for-woocommerce' ); ?>
                    </button>
                </div>
            </div>
        </div>
    </details>

    <!-- ── Help ── -->
    <div class="spbwc-help-section">
        <div class="spbwc-help-section__body">
            <h3 class="spbwc-help-section__title">
                <span class="dashicons dashicons-sos" aria-hidden="true"></span>
                <?php esc_html_e( 'Need help with your account or billing?', 'storelly-product-builder-for-woocommerce' ); ?>
            </h3>
            <p class="spbwc-help-section__text">
                <?php esc_html_e( 'Our team can help with activation, upgrades and invoices.', 'storelly-product-builder-for-woocommerce' ); ?>
            </p>
        </div>
        <div class="spbwc-help-section__links">
            <a href="https://storelly.com/docs" target="_blank" rel="noopener noreferrer" class="spbwc-cta-btn spbwc-cta-btn--ghost">
                <span class="dashicons dashicons-book-alt" aria-hidden="true"></span>
                <?php esc_html_e( 'Documentation', 'storelly-product-builder-for-woocommerce' ); ?>
            </a>
            <a href="https://storelly.com/support" target="_blank" rel="noopener noreferrer" class="spbwc-cta-btn spbwc-cta-btn--ghost">
                <span class="dashicons dashicons-email-alt" aria-hidden="true"></span>
                <?php esc_html_e( 'Contact Support', 'storelly-product-builder-for-woocommerce' ); ?>
            </a>
        </div>
    </div>

</div><!-- .spbwc-license-wrap -->

<script>
(function ($) {
    'use strict';
    var card  = document.getElementById('spbwc-account-card');
    var cNonce = card ? card.getAttribute('data-nonce') : '';

    function post(action, data, btn, onErr) {
        if (btn) { $(btn).addClass('is-loading').prop('disabled', true); }
        $.post(ajaxurl, $.extend({ action: action }, data), function (res) {
            if (res && res.success) { location.reload(); return; }
            var msg = (res && res.data && (res.data.message || res.data.msg)) ? (res.data.message || res.data.msg) : '<?php echo esc_js( __( 'Something went wrong. Please try again.', 'storelly-product-builder-for-woocommerce' ) ); ?>';
            if (onErr) { onErr(msg); }
            if (btn) { $(btn).removeClass('is-loading').prop('disabled', false); }
        }).fail(function () {
            var m = '<?php echo esc_js( __( 'Request failed. Check your connection.', 'storelly-product-builder-for-woocommerce' ) ); ?>';
            if (onErr) { onErr(m); }
            if (btn) { $(btn).removeClass('is-loading').prop('disabled', false); }
        });
    }

    // Build the post-connect welcome URL (keeps current page params, adds the flag, drops hash).
    function welcomeUrl() {
        var u = window.location.href.split('#')[0];
        if (u.indexOf('spbwc_welcome=') !== -1) { return u; }
        return u + (u.indexOf('?') === -1 ? '?' : '&') + 'spbwc_welcome=1';
    }

    // Swap the status card for a celebratory panel, then continue to the connected view.
    function celebrate() {
        if (!card) { window.location.href = welcomeUrl(); return; }
        card.classList.add('is-celebrating');
        card.innerHTML =
            '<div class="spbwc-connect-celebrate">' +
              '<span class="spbwc-connect-celebrate__check dashicons dashicons-yes" aria-hidden="true"></span>' +
              '<h2 class="spbwc-connect-celebrate__title"><?php echo esc_js( __( '🎉 Connected!', 'storelly-product-builder-for-woocommerce' ) ); ?></h2>' +
              '<p class="spbwc-connect-celebrate__text"><?php echo esc_js( __( 'Your free Storelly account is ready and this store is now linked. Taking you in…', 'storelly-product-builder-for-woocommerce' ) ); ?></p>' +
            '</div>';
        window.setTimeout(function () { window.location.href = welcomeUrl(); }, 1500);
    }

    // Connect (reuses SPBWC_Cloud_Connect AJAX; bound by class so it works for the
    // status-card button AND the Advanced one). Shows progress, then celebrates.
    $('.spbwc-do-connect').on('click', function () {
        var $btn   = $(this);
        var $label = $btn.find('.spbwc-do-connect__label');
        var orig   = $label.length ? $label.text() : $btn.text();
        var $status = $('#spbwc-connect-status');
        $btn.addClass('is-loading').prop('disabled', true);
        $('.spbwc-do-connect__alt').addClass('is-disabled');
        if ($label.length) { $label.text('<?php echo esc_js( __( 'Connecting…', 'storelly-product-builder-for-woocommerce' ) ); ?>'); }
        $status.removeClass('is-error').text('<?php echo esc_js( __( 'Creating your free account & linking this store…', 'storelly-product-builder-for-woocommerce' ) ); ?>');

        $.post(ajaxurl, { action: 'spbwc_cloud_connect', nonce: $btn.data('nonce') || cNonce }, function (res) {
            if (res && res.success) { celebrate(); return; }
            var msg = (res && res.data && (res.data.message || res.data.msg)) ? (res.data.message || res.data.msg) : '<?php echo esc_js( __( 'Something went wrong. Please try again.', 'storelly-product-builder-for-woocommerce' ) ); ?>';
            $status.addClass('is-error').text(msg);
            $btn.removeClass('is-loading').prop('disabled', false);
            $('.spbwc-do-connect__alt').removeClass('is-disabled');
            if ($label.length) { $label.text(orig); }
        }).fail(function () {
            $status.addClass('is-error').text('<?php echo esc_js( __( 'Request failed. Check your connection and try again.', 'storelly-product-builder-for-woocommerce' ) ); ?>');
            $btn.removeClass('is-loading').prop('disabled', false);
            $('.spbwc-do-connect__alt').removeClass('is-disabled');
            if ($label.length) { $label.text(orig); }
        });
    });

    $('.spbwc-do-disconnect').on('click', function () {
        var self = this;
        var msg = '<?php echo esc_js( __( 'Disconnect from Storelly Cloud? Cloud features will stop.', 'storelly-product-builder-for-woocommerce' ) ); ?>';
        var ask = window.spbwcDialog
            ? window.spbwcDialog.confirm({ message: msg, tone: 'danger', okText: '<?php echo esc_js( __( 'Disconnect', 'storelly-product-builder-for-woocommerce' ) ); ?>' })
            : Promise.resolve(window.confirm(msg));
        ask.then(function (ok) {
            if (!ok) { return; }
            post('spbwc_cloud_disconnect', { nonce: $(self).data('nonce') || cNonce }, self);
        });
    });

    // Refresh plan status (moved into Advanced).
    var $sync = $('#spbwc-sync-btn');
    $sync.on('click', function () {
        post('spbwc_license_sync', { nonce: $sync.data('nonce') }, this, function (m) {
            $('#spbwc-sync-status').text(m);
        });
    });

    // Dismiss the one-time welcome banner + strip the flag so a refresh won't re-show it.
    $('.spbwc-welcome-banner__close').on('click', function () {
        $(this).closest('.spbwc-welcome-banner').slideUp(160, function () { $(this).remove(); });
        if (window.history && window.history.replaceState) {
            try {
                var u = new URL(window.location.href);
                u.searchParams.delete('spbwc_welcome');
                window.history.replaceState({}, '', u.toString());
            } catch (e) {}
        }
    });

    // Manual license-key activate.
    $('#spbwc-license-activate').on('click', function () {
        var key = $.trim($('#spbwc-license-key').val() || '');
        var $status = $('#spbwc-activate-status');
        if (!key) { $status.text('<?php echo esc_js( __( 'Please paste a license key.', 'storelly-product-builder-for-woocommerce' ) ); ?>'); return; }
        post('spbwc_license_activate', { nonce: $(this).data('nonce'), license_key: key }, this, function (m) {
            $status.text(m);
        });
    });
})(jQuery);
</script>
