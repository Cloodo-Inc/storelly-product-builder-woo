<?php
/**
 * Registers the Custom Order WC_Email classes + their trigger actions (E6a).
 *
 *  - `spbwc_order_received_notification`  ← fired here when a design-bearing
 *    order is placed (local, both classic + blocks checkout).
 *  - `spbwc_order_pdf_ready_notification` ← fired by SPBWC_Order_PDF::generate()
 *    once the proof PDF has rendered (cloud).
 *
 * @package Storelly
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'SPBWC_Order_Emails' ) ) {

    class SPBWC_Order_Emails {

        const RECEIVED_FLAG = '_spbwc_order_received_email';

        public static function init() {
            add_filter( 'woocommerce_email_classes', array( __CLASS__, 'register_classes' ) );
            add_filter( 'woocommerce_email_actions', array( __CLASS__, 'register_actions' ) );
            // Fire the local "received" email for design-bearing orders (both checkout flows).
            add_action( 'woocommerce_checkout_order_processed', array( __CLASS__, 'on_classic_checkout' ), 20, 1 );
            add_action( 'woocommerce_store_api_checkout_order_processed', array( __CLASS__, 'on_blocks_checkout' ), 20, 1 );
        }

        public static function register_actions( $actions ) {
            $actions[] = 'spbwc_order_received_notification';
            $actions[] = 'spbwc_order_pdf_ready_notification';
            return $actions;
        }

        public static function register_classes( $emails ) {
            require_once SPBWC_PB_PLUGIN_DIR . 'includes/email/class-order-email-types.php';
            if ( class_exists( 'SPBWC_Email_Order_Received' ) ) {
                $emails['SPBWC_Email_Order_Received'] = new SPBWC_Email_Order_Received();
                $emails['SPBWC_Email_Order_Proof']    = new SPBWC_Email_Order_Proof();
            }
            return $emails;
        }

        /** @param int $order_id */
        public static function on_classic_checkout( $order_id ) {
            self::maybe_notify_received( wc_get_order( $order_id ) );
        }

        /** @param WC_Order $order */
        public static function on_blocks_checkout( $order ) {
            self::maybe_notify_received( $order );
        }

        /**
         * Fire the received email once per order, only when it carries a design.
         *
         * @param WC_Order|false $order Order.
         */
        protected static function maybe_notify_received( $order ) {
            if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
                return;
            }
            if ( $order->get_meta( self::RECEIVED_FLAG ) ) {
                return;
            }
            if ( ! self::order_has_design( $order ) ) {
                return;
            }
            $order->update_meta_data( self::RECEIVED_FLAG, current_time( 'mysql' ) );
            $order->save();
            do_action( 'spbwc_order_received_notification', $order->get_id() );
        }

        /**
         * True when at least one line item references a Storelly design folder.
         *
         * @param WC_Order $order Order.
         * @return bool
         */
        public static function order_has_design( $order ) {
            foreach ( $order->get_items() as $item ) {
                if ( is_callable( array( $item, 'get_meta' ) ) && '' !== (string) $item->get_meta( '_pcpb_folder' ) ) {
                    return true;
                }
            }
            return false;
        }
    }
}
