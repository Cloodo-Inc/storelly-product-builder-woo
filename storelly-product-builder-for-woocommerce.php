<?php
 if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly 
/**
 * @package Storelly 
 */
/*

Plugin Name:            Storelly Product Builder for WooCommerce
Plugin URI:             https://storelly.com/product-builder
Description:            Create product builder for Woocommerce products
Version:                1.4.6
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
define('SPBWC_PB_VERSION',                  '1.4.6');
define('SPBWC_PB_NUMBER_VERSION',           127);
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
        $message .= esc_html__('WooCommerce is not active. Please activate WooCommerce before using', 'storelly-product-builder-for-woocommerce');
        $message .= ' <b>' . esc_html__('Product Builder Integration', 'storelly-product-builder-for-woocommerce') . '</b>';
        $message .= '</p></div>';
        wp_die(wp_kses_post($message));
    }
    SPBWC_Storelly_Product_Builder_Backend::spbwc_plugin_activation();
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
require_once(SPBWC_PB_PLUGIN_DIR .  'includes/class-i18n-notice.php');
require_once(SPBWC_PB_PLUGIN_DIR .  'includes/class-frontend-options.php');
require_once(SPBWC_PB_PLUGIN_DIR .  'includes/class-http.php');
require_once(SPBWC_PB_PLUGIN_DIR .  'includes/class-productbuilder-api.php');
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
if ( class_exists( 'SPBWC_Quote_Post_Type' ) ) {
    SPBWC_Quote_Post_Type::instance()->init();
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

