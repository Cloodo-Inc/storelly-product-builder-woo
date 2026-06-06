<?php
/**
 * Import adapter: B2BKing → quotes (Quote Import M4; schema source-confirmed M4.1).
 *
 * Confirmed from the B2BKing source: a quote request is one `b2bking_conversation`
 * post (NOT a per-message CPT). Its meta carries the requesting customer's WP
 * login (`b2bking_conversation_user`), the conversation type
 * (`b2bking_conversation_type`, e.g. "message" / "quote") and the opening message
 * (`b2bking_conversation_start_message`). One post = one quote, so dedupe is by
 * post id with no thread grouping.
 *
 * The quoted products live in B2BKing's own cart/quote structure (not a simple
 * meta), so line items are left for the merchant to price in the reply; the
 * customer + opening message are imported.
 *
 * @package Storelly
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'SPBWC_Quote_Adapter_B2bking' ) ) {

    class SPBWC_Quote_Adapter_B2bking extends SPBWC_Quote_Source_Adapter {

        const CPT       = 'b2bking_conversation';
        const META_USER = 'b2bking_conversation_user';
        const META_TYPE = 'b2bking_conversation_type';
        const META_MSG  = 'b2bking_conversation_start_message';
        const DONE_META = '_spbwc_imported_to_quote';

        public function id() {
            return 'b2bking';
        }

        public function label() {
            return __( 'B2BKing', 'storelly-product-builder-for-woocommerce' );
        }

        public function description() {
            return __( 'Quote-request conversations from the B2BKing plugin.', 'storelly-product-builder-for-woocommerce' );
        }

        public function is_available() {
            return ( class_exists( 'B2bking' ) || class_exists( 'B2BKing' ) ) && post_type_exists( self::CPT );
        }

        protected function query( $limit, $ids_only = false ) {
            return get_posts(
                array(
                    'post_type'      => self::CPT,
                    'post_status'    => array( 'publish', 'private', 'draft' ),
                    'posts_per_page' => $limit,
                    'orderby'        => 'date',
                    'order'          => 'ASC',
                    'fields'         => $ids_only ? 'ids' : 'all',
                    // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Admin-triggered import scan.
                    'meta_query'     => array(
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
            return (string) ( is_object( $row ) ? $row->ID : (int) $row );
        }

        public function map_to_quote( $row ) {
            $post = is_object( $row ) ? $row : get_post( (int) $row );
            if ( ! $post ) {
                return array();
            }
            $pid = $post->ID;

            $message = (string) get_post_meta( $pid, self::META_MSG, true );
            if ( '' === $message ) {
                $message = wp_strip_all_tags( (string) $post->post_content );
            }
            $request = array( 'message' => $message );

            // B2BKing stores the customer's WP login, not the user id.
            $login   = (string) get_post_meta( $pid, self::META_USER, true );
            $user    = $login ? get_user_by( 'login', $login ) : false;
            $user_id = 0;
            if ( $user instanceof WP_User ) {
                $user_id               = (int) $user->ID;
                $request['email']      = $user->user_email;
                $request['first_name'] = $user->first_name ? $user->first_name : $user->display_name;
                $request['last_name']  = $user->last_name;
                $company               = get_user_meta( $user_id, 'billing_company', true );
                if ( $company ) {
                    $request['company'] = $company;
                }
                $phone = get_user_meta( $user_id, 'billing_phone', true );
                if ( $phone ) {
                    $request['phone'] = $phone;
                }
            }

            return array(
                'request' => $request,
                'user_id' => $user_id,
                'lines'   => array(), // products live in B2BKing's quote cart — priced in the reply.
                'status'  => SPBWC_Quote::STATUS_NEW,
                'date'    => $post->post_date ? $post->post_date : '',
            );
        }

        public function mark_imported( $row, $quote_id ) {
            $pid = is_object( $row ) ? $row->ID : (int) $row;
            update_post_meta( $pid, self::DONE_META, (int) $quote_id );
        }
    }
}
