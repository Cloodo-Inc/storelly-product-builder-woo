<?php
/**
 * Import adapter: YITH WooCommerce Request a Quote → quotes (Quote Import M2).
 *
 * IMPORTANT — version behaviour:
 *  - YITH RAQ **free** keeps the quote list in the session and emails it; it does
 *    NOT persist requests, so there is nothing to import (is_available() is false
 *    on free — it requires the Premium class).
 *  - YITH RAQ **Premium** persists each submitted quote as a WooCommerce order in
 *    a custom `ywraq-*` status (new / pending / accepted / expired / rejected).
 *    We import the OPEN ones (everything except the terminal accepted / expired /
 *    rejected / converted / paid states), mapping the order the same way as the
 *    other order-based sources.
 *
 * Detection is by the registered `wc-ywraq-*` order statuses (resolved at runtime
 * rather than hardcoded) so it tracks whatever slugs the installed Premium build
 * registers. NOTE: validate against a live YITH Premium install before relying on
 * it in production — this adapter is inert on the free plugin used in dev.
 *
 * @package Storelly
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'SPBWC_Quote_Adapter_Yith_Raq' ) ) {

    class SPBWC_Quote_Adapter_Yith_Raq extends SPBWC_Quote_Source_Adapter {

        const DONE_META = '_spbwc_imported_to_quote';

        public function id() {
            return 'yith_raq';
        }

        public function label() {
            return __( 'YITH Request a Quote', 'storelly-product-builder-for-woocommerce' );
        }

        public function description() {
            return __( 'Open quote requests stored as orders by YITH Request a Quote (Premium).', 'storelly-product-builder-for-woocommerce' );
        }

        public function is_available() {
            // Premium persists quotes as orders; free does not store anything.
            return function_exists( 'wc_get_orders' )
                && ( class_exists( 'YITH_Request_Quote_Premium' ) || class_exists( 'YITH_YWRAQ_Request_Quote_Premium' ) )
                && ! empty( $this->open_statuses() );
        }

        /**
         * Open YITH quote order statuses = every registered wc-ywraq-* status
         * minus the terminal ones.
         *
         * @return string[]
         */
        protected function open_statuses() {
            $terminal = array( 'accepted', 'expired', 'rejected', 'converted', 'paid', 'cancelled' );
            $open     = array();
            foreach ( array_keys( wc_get_order_statuses() ) as $status ) {
                if ( 0 !== strpos( $status, 'wc-ywraq-' ) ) {
                    continue;
                }
                $tail = substr( $status, strlen( 'wc-ywraq-' ) );
                if ( ! in_array( $tail, $terminal, true ) ) {
                    $open[] = $status;
                }
            }
            return $open;
        }

        /**
         * @param int  $limit    -1 (count) or N (batch).
         * @param bool $ids_only Return ids.
         * @return WC_Order[]|int[]
         */
        protected function query( $limit, $ids_only = false ) {
            $statuses = $this->open_statuses();
            if ( ! function_exists( 'wc_get_orders' ) || empty( $statuses ) ) {
                return array();
            }
            return (array) wc_get_orders(
                array(
                    'type'       => 'shop_order',
                    'status'     => $statuses,
                    'limit'      => $limit,
                    'orderby'    => 'date',
                    'order'      => 'ASC',
                    'return'     => $ids_only ? 'ids' : 'objects',
                    // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Admin-triggered import scan.
                    'meta_query' => array(
                        array(
                            'key'     => self::DONE_META,
                            'compare' => 'NOT EXISTS',
                        ),
                    ),
                )
            );
        }

        public function count_importable() {
            return count( $this->query( -1, true ) );
        }

        public function fetch_batch( $limit ) {
            return $this->query( max( 1, (int) $limit ) );
        }

        public function source_ref( $row ) {
            return (string) ( is_object( $row ) ? $row->get_id() : (int) $row );
        }

        public function map_to_quote( $row ) {
            $order = is_object( $row ) ? $row : wc_get_order( (int) $row );
            if ( ! $order ) {
                return array();
            }
            $request = array(
                'first_name' => $order->get_billing_first_name(),
                'last_name'  => $order->get_billing_last_name(),
                'email'      => $order->get_billing_email(),
                'phone'      => $order->get_billing_phone(),
                'company'    => $order->get_billing_company(),
                'message'    => (string) $order->get_customer_note(),
            );
            $lines      = array();
            $product_id = 0;
            foreach ( $order->get_items() as $item ) {
                $qty   = (float) $item->get_quantity();
                $total = (float) $item->get_total();
                if ( ! $product_id && is_callable( array( $item, 'get_product_id' ) ) ) {
                    $product_id = (int) $item->get_product_id();
                }
                $lines[] = array(
                    'label'      => $item->get_name(),
                    'desc'       => '',
                    'qty'        => $qty,
                    'unit_price' => $qty > 0 ? round( $total / $qty, 2 ) : $total,
                );
            }
            return array(
                'request'    => $request,
                'user_id'    => (int) $order->get_customer_id(),
                'lines'      => $lines,
                'status'     => SPBWC_Quote::STATUS_NEW,
                'date'       => $order->get_date_created() ? gmdate( 'Y-m-d H:i:s', $order->get_date_created()->getTimestamp() ) : '',
                'product_id' => $product_id,
            );
        }

        public function mark_imported( $row, $quote_id ) {
            $order = is_object( $row ) ? $row : wc_get_order( (int) $row );
            if ( $order ) {
                $order->update_meta_data( self::DONE_META, (int) $quote_id );
                $order->save();
            }
        }
    }
}
