<?php
/**
 * "Pay with Company Account" WooCommerce gateway (M2).
 *
 * Loaded lazily by SPBWC_B2B_Credit::register_gateway (only after WooCommerce has
 * defined WC_Payment_Gateway). Offered solely to buyers who belong to an active
 * B2B company whose available credit covers the cart. Selecting it posts an
 * `order_charge` debit to the company ledger and completes payment.
 *
 * @package Storelly
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( class_exists( 'WC_Payment_Gateway' ) && ! class_exists( 'SPBWC_Gateway_Company_Account' ) ) {

    class SPBWC_Gateway_Company_Account extends WC_Payment_Gateway {

        public function __construct() {
            $this->id                 = 'spbwc_company_account';
            $this->method_title       = __( 'Company Account Credit', 'storelly-product-builder-for-woocommerce' );
            $this->method_description = __( 'Let B2B company members pay from their company wallet / credit line. Balances are managed under Storelly › B2B Companies.', 'storelly-product-builder-for-woocommerce' );
            $this->has_fields         = false;

            $this->init_form_fields();
            $this->init_settings();

            $this->enabled     = $this->get_option( 'enabled', 'yes' );
            $this->title       = $this->get_option( 'title', __( 'Company Account', 'storelly-product-builder-for-woocommerce' ) );
            $this->description  = $this->get_option( 'description', __( 'Pay using your company credit balance.', 'storelly-product-builder-for-woocommerce' ) );

            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
        }

        public function init_form_fields() {
            $this->form_fields = array(
                'enabled'     => array(
                    'title'   => __( 'Enable/Disable', 'storelly-product-builder-for-woocommerce' ),
                    'type'    => 'checkbox',
                    'label'   => __( 'Enable Company Account Credit', 'storelly-product-builder-for-woocommerce' ),
                    'default' => 'yes',
                ),
                'title'       => array(
                    'title'   => __( 'Title', 'storelly-product-builder-for-woocommerce' ),
                    'type'    => 'text',
                    'default' => __( 'Company Account', 'storelly-product-builder-for-woocommerce' ),
                ),
                'description' => array(
                    'title'   => __( 'Description', 'storelly-product-builder-for-woocommerce' ),
                    'type'    => 'textarea',
                    'default' => __( 'Pay using your company credit balance.', 'storelly-product-builder-for-woocommerce' ),
                ),
            );
        }

        /**
         * Only available to a logged-in member of an active company whose available
         * credit covers the current cart total.
         */
        public function is_available() {
            if ( 'yes' !== $this->enabled || ! is_user_logged_in() || ! class_exists( 'SPBWC_B2B_Credit' ) ) {
                return false;
            }
            $ctx = SPBWC_B2B_Credit::context();
            if ( ! $ctx['company'] ) {
                return false;
            }
            // During checkout, require the available credit to cover the cart.
            $total = ( WC()->cart && WC()->cart->total ) ? (float) WC()->cart->total : 0;
            if ( $total > 0 && $ctx['available'] + 0.0001 < $total ) {
                return false;
            }
            return true;
        }

        /**
         * Charge the company ledger and complete payment.
         *
         * @param int $order_id Order id.
         * @return array
         */
        public function process_payment( $order_id ) {
            $order = wc_get_order( $order_id );
            if ( ! $order ) {
                return array( 'result' => 'failure' );
            }

            $charged = SPBWC_B2B_Credit::charge_order( $order );
            if ( is_wp_error( $charged ) ) {
                wc_add_notice( $charged->get_error_message(), 'error' );
                return array( 'result' => 'failure' );
            }

            // Funds reserved on the ledger — mark the order paid.
            $order->payment_complete();
            $order->add_order_note( __( 'Paid from company account credit.', 'storelly-product-builder-for-woocommerce' ) );

            if ( WC()->cart ) {
                WC()->cart->empty_cart();
            }

            return array(
                'result'   => 'success',
                'redirect' => $this->get_return_url( $order ),
            );
        }
    }
}
