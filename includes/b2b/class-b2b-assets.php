<?php
/**
 * B2B stylesheet loader — wires the B2B pages to the Storelly design tokens.
 *
 * Storefront pages depend on `_tokens-storefront.css` (the --nbd-mb-* / --nbd-color-*
 * set); admin pages depend on `_tokens.css` (the --st-* / --nbd-st-* set). This
 * helper registers both token files and the B2B stylesheets with the correct
 * dependency so var() references always resolve, and exposes idempotent enqueue
 * helpers used by every B2B surface.
 *
 * @package Storelly
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'SPBWC_B2B_Assets' ) ) {

    class SPBWC_B2B_Assets {

        public static function init() {
            add_action( 'admin_enqueue_scripts', array( __CLASS__, 'maybe_admin' ) );
        }

        /**
         * Enqueue the storefront B2B stylesheets: the shared storefront component
         * library (quote-storefront.css = .spbwc-rfq-* ; custom-order.css =
         * .spbwc-saved-designs and .spbwc-co-chip + the My-Account frame) plus the
         * slim B2B layer (brand header + atoms), all on the storefront token set.
         */
        public static function storefront() {
            if ( ! wp_style_is( 'spbwc-tokens-storefront', 'registered' ) ) {
                wp_register_style( 'spbwc-tokens-storefront', SPBWC_PB_CSS_URL . '_tokens-storefront.css', array(), SPBWC_PB_VERSION );
            }
            // Shared storefront component libraries.
            if ( ! wp_style_is( 'spbwc-quote-storefront', 'registered' ) ) {
                wp_register_style( 'spbwc-quote-storefront', SPBWC_PB_CSS_URL . 'quote-storefront.css', array( 'spbwc-tokens-storefront' ), SPBWC_PB_VERSION );
            }
            if ( ! wp_style_is( 'spbwc-custom-order', 'registered' ) ) {
                wp_register_style( 'spbwc-custom-order', SPBWC_PB_CSS_URL . 'custom-order.css', array( 'spbwc-tokens-storefront' ), SPBWC_PB_VERSION );
            }
            $is_rtl = function_exists( 'is_rtl' ) && is_rtl();
            $file   = $is_rtl && file_exists( SPBWC_PB_ASSETS_DIR . 'css/b2b-rtl.css' ) ? 'b2b-rtl.css' : 'b2b.css';
            if ( ! wp_style_is( 'spbwc-b2b', 'registered' ) ) {
                wp_register_style( 'spbwc-b2b', SPBWC_PB_CSS_URL . $file, array( 'spbwc-tokens-storefront', 'spbwc-quote-storefront', 'spbwc-custom-order' ), SPBWC_PB_VERSION );
            }
            wp_enqueue_style( 'spbwc-tokens-storefront' );
            wp_enqueue_style( 'spbwc-quote-storefront' );
            wp_enqueue_style( 'spbwc-custom-order' );
            wp_enqueue_style( 'spbwc-b2b' );
        }

        /** Enqueue the admin B2B stylesheet on our own admin pages only. */
        public static function maybe_admin() {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only page check.
            $page  = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
            $ours  = array();
            if ( class_exists( 'SPBWC_B2B_Admin' ) ) {
                $ours[] = SPBWC_B2B_Admin::PAGE_SLUG;
            }
            if ( class_exists( 'SPBWC_B2B_Pricing_Admin' ) ) {
                $ours[] = SPBWC_B2B_Pricing_Admin::PAGE_SLUG;
            }
            if ( ! in_array( $page, $ours, true ) ) {
                return;
            }
            if ( ! wp_style_is( 'spbwc-tokens', 'registered' ) ) {
                wp_register_style( 'spbwc-tokens', SPBWC_PB_CSS_URL . '_tokens.css', array(), SPBWC_PB_VERSION );
            }
            // Shared admin component library is enqueued globally on Storelly pages
            // (spbwc-admin-ui). Add the quote workspace stylesheet so the B2B detail
            // can reuse its .spbwc-q-* patterns (2-col detail, KPI, recap, timeline).
            if ( ! wp_style_is( 'spbwc-quotes-admin', 'registered' ) ) {
                wp_register_style( 'spbwc-quotes-admin', SPBWC_PB_CSS_URL . 'quotes-admin.css', array( 'spbwc-tokens' ), SPBWC_PB_VERSION );
            }
            wp_enqueue_style( 'spbwc-admin-ui' );
            wp_enqueue_style( 'spbwc-quotes-admin' );
            wp_enqueue_style( 'spbwc-b2b-admin', SPBWC_PB_CSS_URL . 'b2b-admin.css', array( 'spbwc-tokens', 'spbwc-admin-ui', 'dashicons' ), SPBWC_PB_VERSION );

            // Customer-picker JS only on the B2B Companies hub.
            if ( class_exists( 'SPBWC_B2B_Admin' ) && SPBWC_B2B_Admin::PAGE_SLUG === $page ) {
                wp_enqueue_script( 'spbwc-b2b-admin', SPBWC_PB_JS_URL . 'b2b-admin.js', array(), SPBWC_PB_VERSION, true );
                wp_localize_script( 'spbwc-b2b-admin', 'spbwcB2BAdmin', array(
                    'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                    'nonce'   => wp_create_nonce( 'spbwc_b2b_picker' ),
                    'i18n'    => array(
                        'empty'    => __( 'No matching customers.', 'storelly-product-builder-for-woocommerce' ),
                        'searching' => __( 'Searching…', 'storelly-product-builder-for-woocommerce' ),
                    ),
                ) );
            }

            // The B2B Pricing page adds/removes tier rows client-side.
            if ( class_exists( 'SPBWC_B2B_Pricing_Admin' ) && SPBWC_B2B_Pricing_Admin::PAGE_SLUG === $page ) {
                wp_enqueue_script( 'spbwc-b2b-pricing-admin', SPBWC_PB_JS_URL . 'b2b-pricing-admin.js', array(), SPBWC_PB_VERSION, true );
            }
        }
    }
}
