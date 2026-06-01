<?php
/**
 * One-time migration of legacy quote orders into the spbwc_quote CPT (M7).
 *
 * Before the B2B redesign, a quote was a WooCommerce order in a custom status
 * (wc-spbwc-quote-new/accepted/rejected) carrying _spbwc_quote_* / _raq_* meta.
 * This batch job (Action Scheduler) converts each such order into a
 * spbwc_quote post so the workspace and My Account views show everything in one
 * place. The legacy order is kept and flagged; the legacy order statuses stay
 * registered (read-only) for back-compat.
 *
 * @package Storelly
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'SPBWC_Quote_Migrator' ) ) {

    class SPBWC_Quote_Migrator {

        const HOOK        = 'spbwc_quote_migrate_batch';
        const DONE_OPTION = 'spbwc_quote_migration_done';
        const BATCH       = 20;

        public static function init() {
            add_action( 'init', array( __CLASS__, 'maybe_schedule' ) );
            add_action( self::HOOK, array( __CLASS__, 'run_batch' ) );
        }

        /** Schedule the first batch once, if there is anything to migrate. */
        public static function maybe_schedule() {
            if ( 'yes' === get_option( self::DONE_OPTION ) ) {
                return;
            }
            if ( ! function_exists( 'as_schedule_single_action' ) || ! function_exists( 'as_next_scheduled_action' ) ) {
                return;
            }
            if ( false === as_next_scheduled_action( self::HOOK ) ) {
                as_schedule_single_action( time() + MINUTE_IN_SECONDS, self::HOOK, array(), 'spbwc-quote' );
            }
        }

        /**
         * Find legacy quote orders not yet migrated (HPOS-safe via wc_get_orders).
         *
         * Discovery is driven by the quote meta key. Status is NOT a reliable
         * filter: the legacy "wc-spbwc-quote-accepted/-rejected" statuses
         * exceeded the 20-char status column and were truncated (…-accep /
         * …-rejec) or dropped to "draft". We therefore pass a broad status list
         * (all registered statuses plus draft and the truncated slugs) and let
         * the meta query narrow it to quote orders.
         *
         * @param int $limit Max orders.
         * @return int[] Order IDs.
         */
        public static function get_legacy_orders( $limit ) {
            if ( ! function_exists( 'wc_get_orders' ) ) {
                return array();
            }
            $statuses = array_values(
                array_unique(
                    array_merge(
                        array_keys( wc_get_order_statuses() ),
                        array( 'draft', 'wc-spbwc-quote-new', 'wc-spbwc-quote-accep', 'wc-spbwc-quote-rejec' )
                    )
                )
            );
            $ids = wc_get_orders(
                array(
                    'type'       => 'shop_order',
                    'limit'      => $limit,
                    'return'     => 'ids',
                    'orderby'    => 'ID',
                    'order'      => 'ASC',
                    'status'     => $statuses,
                    // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- One-time migration batch, not a request-time query.
                    'meta_query' => array(
                        'relation' => 'AND',
                        array(
                            'relation' => 'OR',
                            array(
                                'key'     => '_spbwc_quote_request',
                                'compare' => 'EXISTS',
                            ),
                            array(
                                'key'     => '_raq_request',
                                'compare' => 'EXISTS',
                            ),
                        ),
                        array(
                            'key'     => '_spbwc_migrated_to_quote',
                            'compare' => 'NOT EXISTS',
                        ),
                    ),
                )
            );
            return array_map( 'intval', (array) $ids );
        }

        /** Process one batch, then reschedule if more remain. */
        public static function run_batch() {
            $ids = self::get_legacy_orders( self::BATCH );
            if ( empty( $ids ) ) {
                update_option( self::DONE_OPTION, 'yes', false );
                return;
            }
            foreach ( $ids as $order_id ) {
                self::migrate_order( $order_id );
            }
            if ( count( $ids ) >= self::BATCH && function_exists( 'as_schedule_single_action' ) ) {
                as_schedule_single_action( time() + 30, self::HOOK, array(), 'spbwc-quote' );
            } else {
                update_option( self::DONE_OPTION, 'yes', false );
            }
        }

        /**
         * Convert a single legacy quote order into a spbwc_quote post.
         *
         * @param int $order_id Order ID.
         * @return int|false New quote post ID.
         */
        public static function migrate_order( $order_id ) {
            $order = wc_get_order( $order_id );
            if ( ! $order || $order->get_meta( '_spbwc_migrated_to_quote' ) ) {
                return false;
            }

            $fields  = $order->get_meta( '_spbwc_quote_fields' );
            if ( ! is_array( $fields ) ) {
                $fields = $order->get_meta( '_raq_request' );
            }
            $request = is_array( $fields ) ? $fields : array();

            // Backfill contact details from the legacy _raq_* meta.
            if ( empty( $request['first_name'] ) && empty( $request['last_name'] ) ) {
                $name = trim( (string) $order->get_meta( '_raq_customer_name' ) );
                if ( '' !== $name ) {
                    $parts                  = explode( ' ', $name, 2 );
                    $request['first_name']  = $parts[0];
                    $request['last_name']   = isset( $parts[1] ) ? $parts[1] : '';
                }
            }
            if ( empty( $request['email'] ) ) {
                $email             = $order->get_meta( '_raq_customer_email' );
                $request['email']  = $email ? $email : $order->get_billing_email();
            }
            if ( empty( $request['message'] ) ) {
                $msg                = $order->get_meta( '_spbwc_quote_request' );
                $msg                = $msg ? $msg : $order->get_meta( '_raq_customer_message' );
                $request['message'] = (string) $msg;
            }

            $items = $order->get_items();
            $first = is_array( $items ) ? reset( $items ) : false;
            if ( $first ) {
                if ( empty( $request['product_name'] ) ) {
                    $request['product_name'] = $first->get_name();
                }
                if ( empty( $request['quantity'] ) ) {
                    $request['quantity'] = (int) $first->get_quantity();
                }
            }
            $pid = (int) $order->get_meta( '_spbwc_quote_product_id' );

            $quote_id = SPBWC_Quote::create( $request, (int) $order->get_customer_id() );
            if ( is_wp_error( $quote_id ) || ! $quote_id ) {
                return false;
            }

            // Map the legacy order status onto a quote status (set directly to
            // preserve historical terminal states, bypassing the transition map).
            $status_map = array(
                'spbwc-quote-new'      => SPBWC_Quote::STATUS_NEW,
                'spbwc-quote-accepted' => SPBWC_Quote::STATUS_ACCEPTED,
                'spbwc-quote-accep'    => SPBWC_Quote::STATUS_ACCEPTED, // 20-char-truncated legacy slug.
                'spbwc-quote-rejected' => SPBWC_Quote::STATUS_DECLINED,
                'spbwc-quote-rejec'    => SPBWC_Quote::STATUS_DECLINED, // 20-char-truncated legacy slug.
            );
            $legacy_status = $order->get_status();
            $target        = isset( $status_map[ $legacy_status ] ) ? $status_map[ $legacy_status ] : SPBWC_Quote::STATUS_NEW;
            wp_update_post(
                array(
                    'ID'          => $quote_id,
                    'post_status' => $target,
                    'post_date'   => $order->get_date_created() ? gmdate( 'Y-m-d H:i:s', $order->get_date_created()->getTimestamp() ) : current_time( 'mysql' ),
                )
            );

            // Carry the order line items across.
            $lines = array();
            foreach ( (array) $items as $item ) {
                $qty   = (float) $item->get_quantity();
                $total = (float) $item->get_total();
                $lines[] = array(
                    'label'      => $item->get_name(),
                    'desc'       => '',
                    'qty'        => $qty,
                    'unit_price' => $qty > 0 ? round( $total / $qty, 2 ) : $total,
                );
            }
            if ( ! empty( $lines ) ) {
                SPBWC_Quote::set_lines( $quote_id, $lines );
            }

            if ( $pid ) {
                update_post_meta( $quote_id, '_spbwc_quote_product_id', $pid );
            }
            update_post_meta( $quote_id, '_spbwc_quote_legacy_order', $order_id );
            $order->update_meta_data( '_spbwc_migrated_to_quote', $quote_id );
            $order->save();

            return $quote_id;
        }
    }
}
