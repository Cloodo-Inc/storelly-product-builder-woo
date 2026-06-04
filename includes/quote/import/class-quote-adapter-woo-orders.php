<?php
/**
 * Import adapter: WooCommerce unpaid orders → quotes (Quote Import & Sync, M1).
 *
 * Every WooCommerce store has unpaid manual orders (status pending / on-hold)
 * that merchants already use as de-facto quotes/invoices — "create order, send
 * the customer payment page". This adapter turns each into a spbwc_quote so they
 * surface in the Storelly workspace. Universal: no third-party plugin required.
 *
 * Non-destructive: the source order is kept; we only stamp it with
 * `_spbwc_imported_to_quote` for dedupe. HPOS-safe via wc_get_orders.
 *
 * @package Storelly
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'SPBWC_Quote_Adapter_Woo_Orders' ) ) {

    class SPBWC_Quote_Adapter_Woo_Orders extends SPBWC_Quote_Source_Adapter {

        const DONE_META = '_spbwc_imported_to_quote';

        public function id() {
            return 'woo_orders';
        }

        public function label() {
            return __( 'WooCommerce unpaid orders', 'storelly-product-builder-for-woocommerce' );
        }

        public function description() {
            return __( 'Pending / on-hold orders you use as quotes or unpaid invoices.', 'storelly-product-builder-for-woocommerce' );
        }

        public function is_available() {
            return function_exists( 'wc_get_orders' );
        }

        /** Statuses that read as "quote / unpaid invoice". */
        protected function statuses() {
            return array( 'pending', 'on-hold' );
        }

        /**
         * Query un-imported candidate orders.
         *
         * @param int $limit -1 for all (count), N for a batch.
         * @return WC_Order[]|int[]
         */
        protected function query( $limit, $ids_only = false ) {
            if ( ! function_exists( 'wc_get_orders' ) ) {
                return array();
            }
            return (array) wc_get_orders(
                array(
                    'type'       => 'shop_order',
                    'status'     => $this->statuses(),
                    'limit'      => $limit,
                    'orderby'    => 'date',
                    'order'      => 'ASC',
                    'return'     => $ids_only ? 'ids' : 'objects',
                    // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Admin-triggered import scan, not a request-time query.
                    'meta_query' => array(
                        'relation' => 'AND',
                        // Not already imported by us.
                        array(
                            'key'     => self::DONE_META,
                            'compare' => 'NOT EXISTS',
                        ),
                        // Not an order WE spawned from a quote (Accept → order).
                        array(
                            'key'     => '_spbwc_source_quote',
                            'compare' => 'NOT EXISTS',
                        ),
                        // Not a legacy quote-order (the M7 migrator owns those).
                        array(
                            'key'     => '_spbwc_quote_request',
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
