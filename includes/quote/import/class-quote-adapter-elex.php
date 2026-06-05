<?php
/**
 * Import adapter: ELEX WooCommerce Request a Quote → quotes (Quote Import M4).
 *
 * ELEX stores a quote as a WooCommerce order in a custom status — verified from
 * the plugin source: `wc-quote-requested` (new request), `wc-quote-approved`,
 * `wc-quote-rejected`. We import the open requests (quote-requested); the quoted
 * products are the order's line items, so they map straight across.
 *
 * @package Storelly
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'SPBWC_Quote_Adapter_Elex' ) ) {

    class SPBWC_Quote_Adapter_Elex extends SPBWC_Quote_Source_Adapter {

        const DONE_META = '_spbwc_imported_to_quote';

        public function id() {
            return 'elex';
        }

        public function label() {
            return __( 'ELEX Request a Quote', 'storelly-product-builder-for-woocommerce' );
        }

        public function description() {
            return __( 'Open quote requests created by the ELEX Request a Quote plugin.', 'storelly-product-builder-for-woocommerce' );
        }

        public function is_available() {
            return function_exists( 'wc_get_orders' )
                && function_exists( 'wc_get_order_statuses' )
                && array_key_exists( 'wc-quote-requested', wc_get_order_statuses() );
        }

        protected function query( $limit, $ids_only = false ) {
            if ( ! function_exists( 'wc_get_orders' ) ) {
                return array();
            }
            return (array) wc_get_orders(
                array(
                    'type'       => 'shop_order',
                    'status'     => array( 'quote-requested' ),
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
