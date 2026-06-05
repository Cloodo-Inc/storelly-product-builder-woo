<?php
/**
 * Import adapter: Addify Request a Quote → quotes (Quote Import M4).
 *
 * Addify stores each quote as a `wc_quote` custom post; quote-form fields are
 * saved as post meta keyed by the field name, and the requesting customer is the
 * post author. We map the contact details defensively (trying the common meta
 * keys, falling back to the author's WP account) and the request message.
 *
 * NOTE: Addify is a commercial plugin with a non-public storage schema. This
 * adapter is gated on the `wc_quote` post type and maps contact details + the
 * message; the quoted line items use an undocumented meta shape, so they are left
 * for the merchant to fill in the pricing reply. Validate + extend the item
 * mapping against a live Addify install.
 *
 * @package Storelly
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'SPBWC_Quote_Adapter_Addify' ) ) {

    class SPBWC_Quote_Adapter_Addify extends SPBWC_Quote_Source_Adapter {

        const POST_TYPE = 'wc_quote';
        const DONE_META = '_spbwc_imported_to_quote';

        public function id() {
            return 'addify';
        }

        public function label() {
            return __( 'Addify Request a Quote', 'storelly-product-builder-for-woocommerce' );
        }

        public function description() {
            return __( 'Quote requests created by the Addify Request a Quote plugin.', 'storelly-product-builder-for-woocommerce' );
        }

        public function is_available() {
            return post_type_exists( self::POST_TYPE );
        }

        protected function statuses() {
            // The quote CPT's statuses are not documented, so accept every
            // registered status (covers custom quote statuses + standard ones).
            return array_values( array_diff( get_post_stati(), array( 'trash', 'auto-draft', 'inherit' ) ) );
        }

        protected function query( $limit, $ids_only = false ) {
            return get_posts(
                array(
                    'post_type'      => self::POST_TYPE,
                    'post_status'    => $this->statuses(),
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

        /** First non-empty value among candidate meta keys. */
        protected function meta_any( $post_id, $keys ) {
            foreach ( $keys as $key ) {
                $val = get_post_meta( $post_id, $key, true );
                if ( '' !== $val && null !== $val && ! is_array( $val ) ) {
                    return (string) $val;
                }
            }
            return '';
        }

        public function map_to_quote( $row ) {
            $post = is_object( $row ) ? $row : get_post( (int) $row );
            if ( ! $post ) {
                return array();
            }
            $pid = $post->ID;

            $request = array(
                'first_name' => $this->meta_any( $pid, array( 'first_name', 'billing_first_name', '_billing_first_name', 'addify_first_name' ) ),
                'last_name'  => $this->meta_any( $pid, array( 'last_name', 'billing_last_name', '_billing_last_name', 'addify_last_name' ) ),
                'email'      => $this->meta_any( $pid, array( 'email', 'billing_email', '_billing_email', 'customer_email', 'addify_email' ) ),
                'phone'      => $this->meta_any( $pid, array( 'phone', 'billing_phone', '_billing_phone', 'addify_phone' ) ),
                'company'    => $this->meta_any( $pid, array( 'company', 'billing_company', '_billing_company' ) ),
                'message'    => $this->meta_any( $pid, array( 'message', 'comments', 'note', 'customer_note' ) ),
            );
            if ( '' === $request['message'] && '' !== (string) $post->post_content ) {
                $request['message'] = wp_strip_all_tags( $post->post_content );
            }

            // Fall back to the requesting customer's WP account.
            $author = (int) $post->post_author;
            if ( $author && ( '' === $request['email'] || ( '' === $request['first_name'] && '' === $request['last_name'] ) ) ) {
                $user = get_userdata( $author );
                if ( $user ) {
                    if ( '' === $request['email'] ) {
                        $request['email'] = $user->user_email;
                    }
                    if ( '' === $request['first_name'] ) {
                        $request['first_name'] = $user->first_name ? $user->first_name : $user->display_name;
                    }
                    if ( '' === $request['last_name'] ) {
                        $request['last_name'] = $user->last_name;
                    }
                }
            }

            return array(
                'request' => $request,
                'user_id' => $author,
                'lines'   => array(), // item meta shape is undocumented — merchant prices in the reply.
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
