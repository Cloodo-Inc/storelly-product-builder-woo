<?php
 if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly 
/**
 * @package Storelly 
 */
/*

Plugin Name:            Storelly Product Builder for WooCommerce
Plugin URI:             https://storelly.com/product-builder
Description:            Create product builder for Woocommerce products
Version:                1.5.7
Requires Plugins:       woocommerce
WC requires at least:   6.0.0
WC tested up to:        6.9.4
PHP:                    >=7.0
Author:                 Storelly Team
Author URI:             https://storelly.com
License:                GPL v2 or later
License URI:            https://www.gnu.org/licenses/gpl-2.0.html 
Text Domain:            storelly-product-builder-for-woocommerce
Domain Path:            /languages
*/

$spbwc_upload_dir = wp_upload_dir();
$spbwc_basedir    = $spbwc_upload_dir['basedir'];
$spbwc_baseurl    = $spbwc_upload_dir['baseurl'];
define('SPBWC_PB_VERSION',                  '1.5.7');
define('SPBWC_PB_NUMBER_VERSION',           129);
define('SPBWC_PB_PLUGIN_URL',               plugin_dir_url(__FILE__));
define('SPBWC_PB_PLUGIN_DIR',               plugin_dir_path(__FILE__));
define('SPBWC_PB_DATA_DIR',                 $spbwc_basedir . '/storelly-product-builder');
define('SPBWC_PB_DATA_URL',                 $spbwc_baseurl . '/storelly-product-builder');
define('SPBWC_PB_DATA_CONFIG_URL',          SPBWC_PB_PLUGIN_URL . 'storage/');
define('SPBWC_PB_DATA_CONFIG_DIR',          SPBWC_PB_PLUGIN_DIR . 'storage/');
define('SPBWC_PB_FONT_URL',                 SPBWC_PB_DATA_URL . '/fonts');
define('SPBWC_PB_FONT_DIR',                 SPBWC_PB_DATA_DIR . '/fonts');
define('SPBWC_PB_UPLOAD_DIR',               SPBWC_PB_DATA_DIR . '/uploads');
define('SPBWC_PB_UPLOAD_URL',               SPBWC_PB_DATA_URL . '/uploads');
define('SPBWC_PB_CUSTOMER_DIR',             SPBWC_PB_DATA_DIR . '/designs');
define('SPBWC_PB_CUSTOMER_URL',             SPBWC_PB_DATA_URL . '/designs');
define('SPBWC_PB_ASSETS_URL',               SPBWC_PB_PLUGIN_URL . 'static/');
define('SPBWC_PB_ASSETS_DIR',               SPBWC_PB_PLUGIN_DIR . 'static/');
define('SPBWC_PB_JS_URL',                   SPBWC_PB_PLUGIN_URL . 'static/js/');
define('SPBWC_PB_CSS_URL',                  SPBWC_PB_PLUGIN_URL . 'static/css/');
define('SPBWC_ENABLE_NONCE',                TRUE);
define('SPBWC_API_URL',                     'https://app.storelly.com');
define('SPBWC_PB_OPTIONS_SLUG',             'storelly-product-builder-for-woocommerce-options');
define('SPBWC_PB_BUILDER_SLUG',             'storelly-product-builder-for-woocommerce-builder');
define('SPBWC_PB_PRODUCTS_SLUG',            'storelly-product-builder-for-woocommerce-products');
define('SPBWC_PB_ORDERS_SLUG',              'storelly-product-builder-for-woocommerce-orders');
define('SPBWC_PB_QUOTES_SLUG',              'storelly-product-builder-for-woocommerce-quotes');
define('SPBWC_PB_EMAILS_SLUG',              'storelly-product-builder-for-woocommerce-emails');
define('SPBWC_PB_LICENSE_SLUG',             'storelly-product-builder-for-woocommerce-license');
define('SPBWC_PB_OVERVIEW_SLUG',            'storelly-product-builder-for-woocommerce-overview');
define('SPBWC_PB_TEMPLATE_LIBRARY_SLUG',    'storelly-product-builder-for-woocommerce-templates');
define('SPBWC_PB_DESIGNS_SLUG',             'storelly-product-builder-for-woocommerce-designs');
define('SPBWC_PB_VISUAL_BUILDER_SLUG',      'storelly-product-builder-for-woocommerce-visual-builder');

// Load translations (bundled .mo files from /languages, plus WP.org language packs).
add_action( 'init', 'spbwc_load_textdomain' );
function spbwc_load_textdomain() {
    load_plugin_textdomain(
        'storelly-product-builder-for-woocommerce',
        false,
        dirname( plugin_basename( __FILE__ ) ) . '/languages'
    );
}


// check if woocommerce works
register_activation_hook(__FILE__, 'spbwc_plugin_activation');
function spbwc_plugin_activation() {
    if (!is_plugin_active('woocommerce/woocommerce.php')) {
        $message  = '<div class="error"><p>';
        $message .= esc_html__('WooCommerce is not active. Please install and activate WooCommerce before using', 'storelly-product-builder-for-woocommerce');
        $message .= ' <b>' . esc_html__('Storelly Product Builder', 'storelly-product-builder-for-woocommerce') . '</b>.';
        $message .= '</p><p><a class="button button-primary" href="' . esc_url( admin_url( 'plugin-install.php?s=woocommerce&tab=search&type=term' ) ) . '">';
        $message .= esc_html__('Install WooCommerce', 'storelly-product-builder-for-woocommerce');
        $message .= '</a> <a class="button" href="' . esc_url( admin_url( 'plugins.php' ) ) . '">&larr; ' . esc_html__('Back to Plugins', 'storelly-product-builder-for-woocommerce') . '</a></p></div>';
        wp_die(wp_kses_post($message));
    }
    SPBWC_Storelly_Product_Builder_Backend::spbwc_plugin_activation();
    // Onboarding plumbing: stamp activation time, mint the stable store UUID,
    // and arm the one-shot Welcome redirect. Local only — no network calls.
    if ( class_exists( 'SPBWC_Onboarding' ) ) {
        SPBWC_Onboarding::on_activate();
    }
    // Arm the sample B2B client seeder; it installs on the next admin load,
    // once WooCommerce + the CPTs + the media stack are guaranteed ready.
    if ( class_exists( 'SPBWC_B2B_Sample' ) ) {
        SPBWC_B2B_Sample::arm();
    }
}

/**
 * Persistent admin notice when WooCommerce is missing or inactive.
 *
 * The activation hook above blocks first activation without WooCommerce, but a
 * site can deactivate WooCommerce later while Storelly stays active. This notice
 * makes the dependency obvious at any time and gives a one-click path to fix it
 * (install if absent, activate if installed-but-inactive).
 */
add_action( 'admin_notices', 'spbwc_woocommerce_missing_notice' );
function spbwc_woocommerce_missing_notice() {
    if ( ! current_user_can( 'activate_plugins' ) ) {
        return;
    }
    if ( ! function_exists( 'is_plugin_active' ) ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    if ( is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
        return;
    }

    $wc_installed = file_exists( WP_PLUGIN_DIR . '/woocommerce/woocommerce.php' );
    if ( $wc_installed ) {
        $action_url   = wp_nonce_url(
            self_admin_url( 'plugins.php?action=activate&plugin=woocommerce/woocommerce.php' ),
            'activate-plugin_woocommerce/woocommerce.php'
        );
        $action_label = esc_html__( 'Activate WooCommerce', 'storelly-product-builder-for-woocommerce' );
    } else {
        $action_url   = self_admin_url( 'plugin-install.php?s=woocommerce&tab=search&type=term' );
        $action_label = esc_html__( 'Install WooCommerce', 'storelly-product-builder-for-woocommerce' );
    }
    ?>
    <div class="notice notice-error">
        <p>
            <strong><?php esc_html_e( 'Storelly Product Builder', 'storelly-product-builder-for-woocommerce' ); ?></strong>
            <?php esc_html_e( 'requires WooCommerce to be installed and active. Most features stay disabled until WooCommerce is enabled.', 'storelly-product-builder-for-woocommerce' ); ?>
        </p>
        <p>
            <a class="button button-primary" href="<?php echo esc_url( $action_url ); ?>"><?php echo esc_html( $action_label ); ?></a>
        </p>
    </div>
    <?php
}

require_once(SPBWC_PB_PLUGIN_DIR .  'includes/class-script-hook.php');
require_once(SPBWC_PB_PLUGIN_DIR .  'includes/class-export-pdf.php');
require_once(SPBWC_PB_PLUGIN_DIR .  'includes/class-util.php');
require_once(SPBWC_PB_PLUGIN_DIR .  'includes/class-image.php');
require_once(SPBWC_PB_PLUGIN_DIR .  'includes/class-io.php');
require_once(SPBWC_PB_PLUGIN_DIR .  'includes/class-install.php');
require_once(SPBWC_PB_PLUGIN_DIR .  'includes/class-product-builder-backend.php');
require_once(SPBWC_PB_PLUGIN_DIR .  'includes/class-product-builder-frontend.php');
require_once(SPBWC_PB_PLUGIN_DIR .  'includes/class-admin-options.php');
require_once(SPBWC_PB_PLUGIN_DIR .  'includes/admin/class-admin-menu.php');
require_once(SPBWC_PB_PLUGIN_DIR .  'includes/class-i18n-notice.php');
require_once(SPBWC_PB_PLUGIN_DIR .  'includes/class-onboarding.php');
require_once(SPBWC_PB_PLUGIN_DIR .  'includes/class-upsell-notice.php');
require_once(SPBWC_PB_PLUGIN_DIR .  'includes/class-review-notice.php');
require_once(SPBWC_PB_PLUGIN_DIR .  'includes/class-demo-seeder.php');
require_once(SPBWC_PB_PLUGIN_DIR .  'includes/class-frontend-options.php');
require_once(SPBWC_PB_PLUGIN_DIR .  'includes/class-http.php');
require_once(SPBWC_PB_PLUGIN_DIR .  'includes/class-productbuilder-api.php');
require_once(SPBWC_PB_PLUGIN_DIR .  'includes/class-cloud-connect.php');

/* Order print-PDF generation — decoupled from launcher "API sync" and queued via
 * Action Scheduler. Gated by the dedicated "Enable cloud PDF rendering" opt-in
 * (enable_cloud2print_api). See docs/SPEC_CUSTOM_ORDER.md Part C (M1). */
require_once(SPBWC_PB_PLUGIN_DIR .  'includes/class-order-pdf.php');
if ( class_exists( 'SPBWC_Order_PDF' ) ) {
    SPBWC_Order_PDF::init();
}

/* Buyer-facing design preview download on My Account order detail — preview
 * images only; full print files stay admin-only. See docs/SPEC_CUSTOM_ORDER.md
 * Part C (M3). Nonce + order-ownership checked; streamed then deleted. */
require_once(SPBWC_PB_PLUGIN_DIR .  'includes/class-buyer-downloads.php');
if ( class_exists( 'SPBWC_Buyer_Downloads' ) ) {
    SPBWC_Buyer_Downloads::init();
}

/* Saved designs — buyer can persist a configured design to their account and
 * reload it into the cart. CPT spbwc_saved_design + My Account tab; every reuse
 * clones the design folder (copy-on-write). See docs/SPEC_CUSTOM_ORDER.md (M4). */
require_once(SPBWC_PB_PLUGIN_DIR .  'includes/class-saved-designs.php');
if ( class_exists( 'SPBWC_Saved_Designs' ) ) {
    SPBWC_Saved_Designs::init();
}

/* Storelly-native Custom Order Detail workspace — the primary place to manage a
 * single custom order (artwork, print files, options, history, customer activity,
 * production, files). WC order edit becomes secondary. See
 * docs/SPEC_CUSTOM_ORDER_DETAIL.md. Rendered by spbwc_orders_manager when ?view= set. */
require_once(SPBWC_PB_PLUGIN_DIR .  'includes/class-custom-order-detail.php');
if ( class_exists( 'SPBWC_Custom_Order_Detail' ) ) {
    SPBWC_Custom_Order_Detail::init();
}

require_once(SPBWC_PB_PLUGIN_DIR .  'includes/class-request-quote.php');

/* B2B Quote redesign (CPT `spbwc_quote`) — see docs/SPEC_QUOTE_USER_FLOW_UX.md
 * Part C. M1 = headless CPT + post statuses + SPBWC_Quote model. Admin reply
 * (M2), storefront writer (M3), and buyer My-Account (M4) build on this. The
 * legacy WC-order quote flow in class-request-quote.php stays until M7 migrates
 * existing quotes into the CPT. */
require_once(SPBWC_PB_PLUGIN_DIR .  'includes/quote/class-quote-model.php');
require_once(SPBWC_PB_PLUGIN_DIR .  'includes/quote/class-quote-post-type.php');
require_once(SPBWC_PB_PLUGIN_DIR .  'includes/quote/class-quotes-list-table.php');
require_once(SPBWC_PB_PLUGIN_DIR .  'includes/quote/class-quote-admin.php');
require_once(SPBWC_PB_PLUGIN_DIR .  'includes/quote/class-quote-emails.php');
require_once(SPBWC_PB_PLUGIN_DIR .  'includes/quote/class-quote-scheduler.php');
require_once(SPBWC_PB_PLUGIN_DIR .  'includes/quote/class-quote-migrator.php');
require_once(SPBWC_PB_PLUGIN_DIR .  'includes/quote/class-quote-template.php');
require_once(SPBWC_PB_PLUGIN_DIR .  'includes/quote/class-quote-pdf.php');
require_once(SPBWC_PB_PLUGIN_DIR .  'includes/quote/class-quote-bucket.php');
require_once(SPBWC_PB_PLUGIN_DIR .  'includes/quote/import/class-quote-source-adapter.php');
require_once(SPBWC_PB_PLUGIN_DIR .  'includes/quote/import/class-quote-adapter-woo-orders.php');
require_once(SPBWC_PB_PLUGIN_DIR .  'includes/quote/class-quote-import.php');
if ( class_exists( 'SPBWC_Quote_Post_Type' ) ) {
    SPBWC_Quote_Post_Type::instance()->init();
}
if ( class_exists( 'SPBWC_Quote_Import' ) ) {
    SPBWC_Quote_Import::init();
}
if ( class_exists( 'SPBWC_Quote_Bucket' ) ) {
    ( new SPBWC_Quote_Bucket() )->init();
}
if ( class_exists( 'SPBWC_Quote_Template' ) ) {
    SPBWC_Quote_Template::init();
}
if ( class_exists( 'SPBWC_Quote_PDF' ) ) {
    SPBWC_Quote_PDF::init();
}
if ( is_admin() && class_exists( 'SPBWC_Quote_Admin' ) ) {
    SPBWC_Quote_Admin::instance()->init();
}
if ( class_exists( 'SPBWC_Quote_Emails' ) ) {
    SPBWC_Quote_Emails::init();
}
if ( class_exists( 'SPBWC_Quote_Scheduler' ) ) {
    SPBWC_Quote_Scheduler::init();
}
if ( class_exists( 'SPBWC_Quote_Migrator' ) ) {
    SPBWC_Quote_Migrator::init();
}

/* B2B Client (Company Accounts) — see docs/SPEC_B2B_CLIENT.md. M1 = company
 * core (CPT spbwc_company + SPBWC_Company model + user link), merchant upgrade
 * flow + B2B Companies hub, My-Account Brand Store profile, and the public
 * /store/<slug> storefront. Tier pricing (M2), reorder (M3), per-company price
 * overrides (M4) and team procurement (M5) build on this. Fully local — no
 * external service, no phone-home. */
require_once(SPBWC_PB_PLUGIN_DIR .  'includes/b2b/class-b2b-company.php');
require_once(SPBWC_PB_PLUGIN_DIR .  'includes/b2b/class-b2b-company-post-type.php');
require_once(SPBWC_PB_PLUGIN_DIR .  'includes/b2b/class-b2b-assets.php');
require_once(SPBWC_PB_PLUGIN_DIR .  'includes/b2b/class-b2b-storefront.php');
require_once(SPBWC_PB_PLUGIN_DIR .  'includes/b2b/class-b2b-account.php');
require_once(SPBWC_PB_PLUGIN_DIR .  'includes/b2b/class-b2b-admin.php');
/* M2 — tier pricing: discount engine (cart + display) + admin tier ladder. */
require_once(SPBWC_PB_PLUGIN_DIR .  'includes/b2b/class-b2b-pricing.php');
require_once(SPBWC_PB_PLUGIN_DIR .  'includes/b2b/class-b2b-pricing-admin.php');
/* M3 — reorder: My-Account Reorders tab + one-click reorder (reuse clone). */
require_once(SPBWC_PB_PLUGIN_DIR .  'includes/b2b/class-b2b-reorders.php');
/* M4 — per-company product price overrides (table + cascade in pricing). */
require_once(SPBWC_PB_PLUGIN_DIR .  'includes/b2b/class-b2b-price-rules.php');
/* Account Credit — Wallet/Net-Terms/Rebate ledger (one table, one balance) +
 * storefront surface (My-Account statement + "Pay with Company Account" gateway). */
require_once(SPBWC_PB_PLUGIN_DIR .  'includes/b2b/class-b2b-ledger.php');
require_once(SPBWC_PB_PLUGIN_DIR .  'includes/b2b/class-b2b-credit.php');
require_once(SPBWC_PB_PLUGIN_DIR .  'includes/b2b/class-b2b-rebate.php');
/* M5 — team procurement: team management + approval gate/queue. */
require_once(SPBWC_PB_PLUGIN_DIR .  'includes/b2b/class-b2b-team.php');
require_once(SPBWC_PB_PLUGIN_DIR .  'includes/b2b/class-b2b-procurement.php');
/* B2B notifications routed through the standard WC email pipeline (E2). */
require_once(SPBWC_PB_PLUGIN_DIR .  'includes/b2b/class-b2b-emails.php');
/* Sample B2B client seeder ("Netbase JSC") — installs a full demo on activate. */
require_once(SPBWC_PB_PLUGIN_DIR .  'includes/b2b/class-b2b-sample.php');
if ( class_exists( 'SPBWC_B2B_Company_Post_Type' ) ) {
    SPBWC_B2B_Company_Post_Type::instance()->init();
}
if ( class_exists( 'SPBWC_B2B_Assets' ) ) {
    SPBWC_B2B_Assets::init();
}
if ( class_exists( 'SPBWC_B2B_Storefront' ) ) {
    SPBWC_B2B_Storefront::init();
}
if ( class_exists( 'SPBWC_B2B_Account' ) ) {
    SPBWC_B2B_Account::init();
}
if ( class_exists( 'SPBWC_B2B_Pricing' ) ) {
    SPBWC_B2B_Pricing::init();
}
if ( class_exists( 'SPBWC_B2B_Reorders' ) ) {
    SPBWC_B2B_Reorders::init();
}
if ( class_exists( 'SPBWC_B2B_Price_Rules' ) ) {
    SPBWC_B2B_Price_Rules::init();
}
if ( class_exists( 'SPBWC_B2B_Ledger' ) ) {
    SPBWC_B2B_Ledger::init();
}
if ( class_exists( 'SPBWC_B2B_Credit' ) ) {
    SPBWC_B2B_Credit::init();
}
if ( class_exists( 'SPBWC_B2B_Rebate' ) ) {
    SPBWC_B2B_Rebate::init();
}
if ( class_exists( 'SPBWC_B2B_Team' ) ) {
    SPBWC_B2B_Team::init();
}
if ( class_exists( 'SPBWC_B2B_Procurement' ) ) {
    SPBWC_B2B_Procurement::init();
}
if ( class_exists( 'SPBWC_B2B_Emails' ) ) {
    SPBWC_B2B_Emails::init();
}
if ( class_exists( 'SPBWC_B2B_Sample' ) ) {
    SPBWC_B2B_Sample::init();
}

/* Email subsystem: delivery log, installer/migrator (nbdl_ rename + log table),
 * Custom Order emails, and the aggregated Storelly › Emails dashboard. Local only. */
require_once(SPBWC_PB_PLUGIN_DIR .  'includes/email/class-email-log.php');
require_once(SPBWC_PB_PLUGIN_DIR .  'includes/email/class-email-install.php');
require_once(SPBWC_PB_PLUGIN_DIR .  'includes/email/class-order-emails.php');
require_once(SPBWC_PB_PLUGIN_DIR .  'includes/email/class-email-admin.php');
if ( class_exists( 'SPBWC_Email_Log' ) ) {
    SPBWC_Email_Log::init();
}
if ( class_exists( 'SPBWC_Email_Install' ) ) {
    SPBWC_Email_Install::init();
}
if ( class_exists( 'SPBWC_Order_Emails' ) ) {
    SPBWC_Order_Emails::init();
}
if ( is_admin() && class_exists( 'SPBWC_Email_Admin' ) ) {
    SPBWC_Email_Admin::init();
}

if ( is_admin() && class_exists( 'SPBWC_B2B_Admin' ) ) {
    SPBWC_B2B_Admin::instance()->init();
}
if ( is_admin() && class_exists( 'SPBWC_B2B_Pricing_Admin' ) ) {
    SPBWC_B2B_Pricing_Admin::instance()->init();
}

require_once(SPBWC_PB_PLUGIN_DIR .  'includes/class-global-import.php');
require_once(SPBWC_PB_PLUGIN_DIR .  'includes/class-global-import-admin.php');
require_once(SPBWC_PB_PLUGIN_DIR .  'includes/class-global-import-controller.php');

/* Visual Builder — separate admin entry point that re-presents the existing
 * product-builder data (views + nbpb_* fields + pb_config) under its own menu.
 * Read-only at M6.1 (listing + create picker); edit screen lands in M6.2.
 * Does NOT modify classic Pricing Options editor, data schema, or AJAX. */
require_once(SPBWC_PB_PLUGIN_DIR .  'includes/visual-builder/class-visual-builder-admin.php');

/* Setup Wizard › Import Woo Variations — one-time seeder that converts
 * existing WooCommerce variable products into Storelly pricing options.
 * Read-only scanner + transactional mapper + AJAX controller w/ undo. */
require_once(SPBWC_PB_PLUGIN_DIR .  'includes/setup-wizard/class-woo-seed-scanner.php');
require_once(SPBWC_PB_PLUGIN_DIR .  'includes/setup-wizard/class-woo-seed-mapper.php');
require_once(SPBWC_PB_PLUGIN_DIR .  'includes/setup-wizard/class-woo-seed-controller.php');
require_once(SPBWC_PB_PLUGIN_DIR .  'includes/setup-wizard/class-woo-prepare.php');

require_once(SPBWC_PB_PLUGIN_DIR .  'includes/class-media-group.php');
require_once(SPBWC_PB_PLUGIN_DIR .  'includes/class-printcart-import-adapter.php');
require_once(SPBWC_PB_PLUGIN_DIR .  'includes/class-printcart-import-schema.php');
require_once(SPBWC_PB_PLUGIN_DIR .  'includes/class-license-manager.php');
require_once(SPBWC_PB_PLUGIN_DIR .  'includes/class-product-exporter.php');

/* Bundled printing-option template library — read-only catalog shipped under
 * storage/print-templates/. Admin browses, applies (fork) into the options
 * table; global JSON files stay untouched. */
require_once(SPBWC_PB_PLUGIN_DIR .  'includes/templates/class-template-catalog.php');
require_once(SPBWC_PB_PLUGIN_DIR .  'includes/templates/class-template-applier.php');
require_once(SPBWC_PB_PLUGIN_DIR .  'includes/templates/class-template-library-admin.php');
require_once(SPBWC_PB_PLUGIN_DIR .  'includes/templates/class-template-ajax.php');
require_once(SPBWC_PB_PLUGIN_DIR .  'includes/templates/class-template-preview-render.php');

/* Designer marketplace module — adapted from pc-designer "launcher".
 * Bridge loads first so its constant aliases and helper stubs are
 * available when the launcher classes parse. Marketplace classes are
 * always parsed (cheap; coexistence-guarded), but the launcher only
 * attaches WP/WC hooks when get_option('spbwc_marketplace_enabled') === 'yes'. */
require_once(SPBWC_PB_PLUGIN_DIR .  'includes/marketplace-bridge.php');
require_once(SPBWC_PB_PLUGIN_DIR .  'includes/marketplace/class-marketplace-io.php');
require_once(SPBWC_PB_PLUGIN_DIR .  'includes/marketplace/class-marketplace-design-store.php');
require_once(SPBWC_PB_PLUGIN_DIR .  'includes/marketplace/class-marketplace-settings-adapter.php');
require_once(SPBWC_PB_PLUGIN_DIR .  'includes/settings/launcher.php');
require_once(SPBWC_PB_PLUGIN_DIR .  'includes/launcher/util.php');
require_once(SPBWC_PB_PLUGIN_DIR .  'includes/launcher/class.designer.php');
require_once(SPBWC_PB_PLUGIN_DIR .  'includes/launcher/class.withdraw.php');
require_once(SPBWC_PB_PLUGIN_DIR .  'includes/launcher/class.design.php');
require_once(SPBWC_PB_PLUGIN_DIR .  'includes/launcher/class.generate.preview.process.php');
require_once(SPBWC_PB_PLUGIN_DIR .  'includes/launcher/class.launcher.php');
if ( class_exists( 'SPBWC_Marketplace' ) ) {
    SPBWC_Marketplace::get_instance()->init();
}

/* Marketplace admin (PHP rewrite of the old React SPA — see PR B).
 * Loaded after the launcher so it can rely on its REST routes, helper
 * functions, and the spbwc_marketplace_is_enabled() gate. */
require_once(SPBWC_PB_PLUGIN_DIR .  'includes/marketplace/admin/class-marketplace-admin.php');
require_once(SPBWC_PB_PLUGIN_DIR .  'includes/marketplace/admin/class-marketplace-admin-ajax.php');
require_once(SPBWC_PB_PLUGIN_DIR .  'includes/marketplace/admin/class-designers-list-table.php');
require_once(SPBWC_PB_PLUGIN_DIR .  'includes/marketplace/admin/class-designs-list-table.php');
require_once(SPBWC_PB_PLUGIN_DIR .  'includes/marketplace/admin/class-withdraws-list-table.php');
if ( class_exists( 'SPBWC_Marketplace_Admin' ) ) {
    SPBWC_Marketplace_Admin::get_instance()->init();
}

/* Template Library module — admin submenu, idempotent column migration, AJAX. */
if ( class_exists( 'SPBWC_Template_Library_Admin' ) ) {
    SPBWC_Template_Library_Admin::instance()->init();
}
if ( class_exists( 'SPBWC_Template_Ajax' ) ) {
    SPBWC_Template_Ajax::instance()->init();
}
if ( class_exists( 'SPBWC_Template_Preview_Render' ) ) {
    SPBWC_Template_Preview_Render::instance()->init();
}

/* Setup Wizard › Import Woo Variations — AJAX endpoints + wizard asset
 * enqueue. Tied to the existing Setup Wizard menu (no new menu item). */
if ( class_exists( 'SPBWC_Woo_Seed_Controller' ) ) {
    SPBWC_Woo_Seed_Controller::instance()->init();
}


register_activation_hook(__FILE__, array('SPBWC_Storelly_Product_Builder_API', 'spbwc_generate_key'));

add_action( 'before_woocommerce_init', function() {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
} );

// Before convert array to string, after sanitize_text_field to text
function spbwc_sanitize_recursive($data) {
    if (is_array($data)) {
        foreach ($data as $key => $value) {
            $data[$key] = spbwc_sanitize_recursive($value);
        }
    } elseif (is_string($data)) {
        $data = sanitize_text_field($data);
    }
    return $data;
}

if ( class_exists( 'SPBWC_Media_Group' ) ) {
    SPBWC_Media_Group::instance()->spbwc_init();
}

$storelly_product_builder = new SPBWC_Storelly_Product_Builder_Backend();
$storelly_product_builder->spbwc_init();

