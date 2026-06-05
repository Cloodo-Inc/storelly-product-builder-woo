<?php
/**
 * Sample quote seeding (Quote Import & Sync, M6).
 *
 * Solves the empty-state problem: on a brand-new install the merchant can add a
 * clearly-labelled "Sample" quote with one click to see the whole workflow
 * (price → send → accept), then remove it just as easily. Sample quotes carry a
 * `_spbwc_sample` flag so they are visually badged and bulk-removable, and never
 * count toward anything billable.
 *
 * @package Storelly
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'SPBWC_Quote_Sample' ) ) {

    class SPBWC_Quote_Sample {

        const META_FLAG = '_spbwc_sample';

        public static function init() {
            add_action( 'admin_post_spbwc_quote_seed_sample', array( __CLASS__, 'handle_seed' ) );
            add_action( 'admin_post_spbwc_quote_remove_samples', array( __CLASS__, 'handle_remove' ) );
        }

        /** All quote statuses (custom statuses are excluded from WP_Query 'any'). */
        protected static function statuses() {
            return array_keys( SPBWC_Quote::statuses() );
        }

        /** Number of sample quotes currently present. */
        public static function count() {
            return count( self::ids() );
        }

        /** @return int[] Sample quote ids. */
        public static function ids() {
            return get_posts(
                array(
                    'post_type'   => SPBWC_Quote::POST_TYPE,
                    'post_status' => self::statuses(),
                    'fields'      => 'ids',
                    'numberposts' => -1,
                    // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Admin-only sample lookup.
                    'meta_key'    => self::META_FLAG,
                    // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query
                    'meta_value'  => '1',
                )
            );
        }

        /**
         * Seed one realistic sample quote.
         *
         * @return int|false New quote id.
         */
        public static function create() {
            if ( ! class_exists( 'SPBWC_Quote' ) ) {
                return false;
            }
            $request = array(
                'first_name' => __( 'Sample', 'storelly-product-builder-for-woocommerce' ),
                'last_name'  => __( 'Customer', 'storelly-product-builder-for-woocommerce' ),
                'email'      => 'sample-customer@example.com',
                'company'    => __( 'Acme Co. (sample)', 'storelly-product-builder-for-woocommerce' ),
                'message'    => __( 'This is a sample quote request so you can see how the workflow looks. Price the line items, send the reply, then remove the sample when you are done.', 'storelly-product-builder-for-woocommerce' ),
            );
            $quote_id = SPBWC_Quote::create( $request, 0 );
            if ( is_wp_error( $quote_id ) || ! $quote_id ) {
                return false;
            }
            SPBWC_Quote::set_lines(
                $quote_id,
                array(
                    array(
                        'label'      => __( 'Business cards — 350gsm matte, double-sided', 'storelly-product-builder-for-woocommerce' ),
                        'desc'       => '',
                        'qty'        => 1000,
                        'unit_price' => 0,
                    ),
                    array(
                        'label'      => __( 'A5 flyers — 150gsm gloss', 'storelly-product-builder-for-woocommerce' ),
                        'desc'       => '',
                        'qty'        => 500,
                        'unit_price' => 0,
                    ),
                )
            );
            update_post_meta( $quote_id, self::META_FLAG, '1' );
            return $quote_id;
        }

        /** Delete every sample quote. @return int Removed count. */
        public static function remove_all() {
            $ids = self::ids();
            foreach ( $ids as $id ) {
                wp_delete_post( (int) $id, true );
            }
            return count( $ids );
        }

        /* ── admin-post handlers ──────────────────────────────────── */

        protected static function guard() {
            if ( ! current_user_can( SPBWC_Quote_Admin::CAPABILITY ) ) {
                wp_die( esc_html__( 'You are not allowed to do this.', 'storelly-product-builder-for-woocommerce' ) );
            }
        }

        public static function handle_seed() {
            self::guard();
            check_admin_referer( 'spbwc_quote_sample' );
            self::create();
            wp_safe_redirect( SPBWC_Quote_Admin::page_url( array( 'spbwc_sample' => 'added' ) ) );
            exit;
        }

        public static function handle_remove() {
            self::guard();
            check_admin_referer( 'spbwc_quote_sample' );
            self::remove_all();
            wp_safe_redirect( SPBWC_Quote_Admin::page_url( array( 'spbwc_sample' => 'removed' ) ) );
            exit;
        }
    }
}
