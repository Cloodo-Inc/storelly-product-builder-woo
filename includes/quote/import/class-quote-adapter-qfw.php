<?php
/**
 * Import adapter: Quotes for WooCommerce (WisdmLabs) → quotes (Quote Import M2).
 *
 * QFW stores a quote as a WooCommerce order (WC status "pending") flagged with
 * meta `_qwc_quote` = '1' and a `_quote_status` lifecycle meta
 * (quote-pending → quote-sent → quote-complete → quote-paid). We import the open
 * ones (quote-pending / quote-sent) — requests the merchant has not yet turned
 * into a paid order. Verified against the live free plugin (class Quotes_WC,
 * class-quotes-wc.php / class-qwc-data-tracking.php).
 *
 * The universal Woo-orders adapter excludes `_qwc_quote` orders so each quote is
 * owned by exactly one source.
 *
 * @package Storelly
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'SPBWC_Quote_Adapter_Qfw' ) ) {

    class SPBWC_Quote_Adapter_Qfw extends SPBWC_Quote_Source_Adapter {

        const DONE_META = '_spbwc_imported_to_quote';

        public function id() {
            return 'qfw';
        }

        public function label() {
            return __( 'Quotes for WooCommerce', 'storelly-product-builder-for-woocommerce' );
        }

        public function description() {
            return __( 'Open quote requests (pending / sent) created by the Quotes for WooCommerce plugin.', 'storelly-product-builder-for-woocommerce' );
        }

        public function is_available() {
            return function_exists( 'wc_get_orders' ) && ( class_exists( 'Quotes_WC' ) || class_exists( 'Quotes_For_WC' ) );
        }

        /**
         * @param int  $limit    -1 (count) or N (batch).
         * @param bool $ids_only Return ids.
         * @return WC_Order[]|int[]
         */
        protected function query( $limit, $ids_only = false ) {
            if ( ! function_exists( 'wc_get_orders' ) ) {
                return array();
            }
            return (array) wc_get_orders(
                array(
                    'type'       => 'shop_order',
                    'status'     => array_keys( wc_get_order_statuses() ),
                    'limit'      => $limit,
                    'orderby'    => 'date',
                    'order'      => 'ASC',
                    'return'     => $ids_only ? 'ids' : 'objects',
                    // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Admin-triggered import scan.
                    'meta_query' => array(
                        'relation' => 'AND',
                        array(
                            'key'     => '_qwc_quote',
                            'value'   => '1',
                            'compare' => '=',
                        ),
                        array(
                            'key'     => '_quote_status',
                            'value'   => array( 'quote-pending', 'quote-sent' ),
                            'compare' => 'IN',
                        ),
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
