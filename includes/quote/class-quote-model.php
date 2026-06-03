<?php
/**
 * Quote data model (CPT `spbwc_quote`).
 *
 * Storage + behaviour helper for B2B quotes. A quote is a first-class post —
 * NOT a WooCommerce order — so this class owns line-item storage, totals,
 * Q-YYYY-NNNN numbering, the validated status state machine, and the activity
 * timeline (stored as comments). See docs/SPEC_QUOTE_USER_FLOW_UX.md Part C.
 *
 * @package Storelly
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'SPBWC_Quote' ) ) {

    class SPBWC_Quote {

        /** Custom post type slug. */
        const POST_TYPE = 'spbwc_quote';

        /** Activity-timeline comment type. */
        const TIMELINE_COMMENT_TYPE = 'spbwc_quote_event';

        /* ── Status slugs ─────────────────────────────────────────── */
        const STATUS_NEW         = 'spbwc-q-new';
        const STATUS_REVIEW      = 'spbwc-q-review';
        const STATUS_SENT        = 'spbwc-q-sent';
        const STATUS_NEGOTIATING = 'spbwc-q-negotiating';
        const STATUS_SUPERSEDED  = 'spbwc-q-superseded';
        const STATUS_ACCEPTED    = 'spbwc-q-accepted';
        const STATUS_CONVERTED   = 'spbwc-q-converted';
        const STATUS_DECLINED    = 'spbwc-q-declined';
        const STATUS_EXPIRED     = 'spbwc-q-expired';
        const STATUS_WITHDRAWN   = 'spbwc-q-withdrawn';

        /* ── Meta keys ────────────────────────────────────────────── */
        const META_NUMBER       = '_spbwc_quote_number';
        const META_REQUEST      = '_spbwc_quote_request';
        const META_LINES        = '_spbwc_quote_lines';
        const META_TOTALS       = '_spbwc_quote_totals';
        const META_VALID_UNTIL  = '_spbwc_quote_valid_until';
        const META_PAYMENT_TERMS = '_spbwc_quote_payment_terms';
        const META_CUSTOMER_NOTE = '_spbwc_quote_customer_note';
        const META_REVISION     = '_spbwc_quote_revision';
        const META_PARENT       = '_spbwc_quote_parent';
        const META_ORDER_ID     = '_spbwc_quote_order_id';
        const META_PO_NUMBER    = '_spbwc_quote_po_number';
        const META_DECLINE_REASON = '_spbwc_quote_decline_reason';

        /**
         * Human-readable label for each status.
         *
         * @return array<string,string> slug => label
         */
        public static function statuses() {
            return array(
                self::STATUS_NEW         => __( 'New request', 'storelly-product-builder-for-woocommerce' ),
                self::STATUS_REVIEW      => __( 'In review', 'storelly-product-builder-for-woocommerce' ),
                self::STATUS_SENT        => __( 'Quote sent', 'storelly-product-builder-for-woocommerce' ),
                self::STATUS_NEGOTIATING => __( 'Negotiating', 'storelly-product-builder-for-woocommerce' ),
                self::STATUS_SUPERSEDED  => __( 'Superseded', 'storelly-product-builder-for-woocommerce' ),
                self::STATUS_ACCEPTED    => __( 'Accepted', 'storelly-product-builder-for-woocommerce' ),
                self::STATUS_CONVERTED   => __( 'Converted', 'storelly-product-builder-for-woocommerce' ),
                self::STATUS_DECLINED    => __( 'Declined', 'storelly-product-builder-for-woocommerce' ),
                self::STATUS_EXPIRED     => __( 'Expired', 'storelly-product-builder-for-woocommerce' ),
                self::STATUS_WITHDRAWN   => __( 'Withdrawn', 'storelly-product-builder-for-woocommerce' ),
            );
        }

        /**
         * Labels for the buyer "Request changes" asks (shared by the storefront
         * form and the admin detail).
         *
         * @return array<string,string>
         */
        public static function change_ask_labels() {
            return array(
                'qty'       => __( 'Reduce quantity', 'storelly-product-builder-for-woocommerce' ),
                'material'  => __( 'Different material', 'storelly-product-builder-for-woocommerce' ),
                'dimension' => __( 'Different dimensions', 'storelly-product-builder-for-woocommerce' ),
                'rush'      => __( 'Skip rush production', 'storelly-product-builder-for-woocommerce' ),
                'shipping'  => __( 'Change shipping method', 'storelly-product-builder-for-woocommerce' ),
                'terms'     => __( 'Adjust payment terms', 'storelly-product-builder-for-woocommerce' ),
                'other'     => __( 'Other', 'storelly-product-builder-for-woocommerce' ),
            );
        }

        /**
         * Allowed status transitions (state machine, spec §14.2).
         *
         * @return array<string,string[]> from => [allowed to,...]
         */
        public static function transitions() {
            return array(
                self::STATUS_NEW         => array( self::STATUS_REVIEW, self::STATUS_SENT, self::STATUS_WITHDRAWN, self::STATUS_EXPIRED ),
                self::STATUS_REVIEW      => array( self::STATUS_SENT, self::STATUS_WITHDRAWN, self::STATUS_EXPIRED ),
                self::STATUS_SENT        => array( self::STATUS_NEGOTIATING, self::STATUS_ACCEPTED, self::STATUS_DECLINED, self::STATUS_EXPIRED, self::STATUS_WITHDRAWN ),
                self::STATUS_NEGOTIATING => array( self::STATUS_SENT, self::STATUS_SUPERSEDED, self::STATUS_DECLINED, self::STATUS_WITHDRAWN, self::STATUS_EXPIRED ),
                self::STATUS_ACCEPTED    => array( self::STATUS_CONVERTED ),
                // Terminal states — no outgoing transitions.
                self::STATUS_SUPERSEDED  => array(),
                self::STATUS_CONVERTED   => array(),
                self::STATUS_DECLINED    => array(),
                self::STATUS_EXPIRED     => array(),
                self::STATUS_WITHDRAWN   => array(),
            );
        }

        /**
         * Whether a transition from $from to $to is permitted.
         *
         * @param string $from Current status slug.
         * @param string $to   Target status slug.
         * @return bool
         */
        public static function can_transition( $from, $to ) {
            $map = self::transitions();
            return isset( $map[ $from ] ) && in_array( $to, $map[ $from ], true );
        }

        /**
         * Generate the next human quote number (Q-YYYY-NNNN), site-timezone aware.
         *
         * Uses a per-year option counter. Not collision-proof under heavy
         * concurrency, which is acceptable for quote volume.
         *
         * @return string
         */
        public static function generate_number() {
            $year   = (int) current_time( 'Y' );
            $option = 'spbwc_quote_seq_' . $year;
            $next   = (int) get_option( $option, 0 ) + 1;
            update_option( $option, $next, false );
            return sprintf( 'Q-%d-%04d', $year, $next );
        }

        /**
         * Create a new quote post in the NEW status.
         *
         * @param array $request   Sanitized buyer request fields (caller sanitizes).
         * @param int   $author_id Buyer user ID (0 = current user / guest).
         * @return int|WP_Error    Post ID on success.
         */
        public static function create( array $request, $author_id = 0 ) {
            $number  = self::generate_number();
            $post_id = wp_insert_post(
                array(
                    'post_type'      => self::POST_TYPE,
                    'post_status'    => self::STATUS_NEW,
                    'post_title'     => $number,
                    'post_author'    => $author_id ? absint( $author_id ) : get_current_user_id(),
                    'comment_status' => 'closed',
                ),
                true
            );
            if ( is_wp_error( $post_id ) ) {
                return $post_id;
            }
            update_post_meta( $post_id, self::META_NUMBER, $number );
            update_post_meta( $post_id, self::META_REQUEST, $request );
            update_post_meta( $post_id, self::META_REVISION, 1 );
            self::add_timeline_event( $post_id, __( 'Quote request received.', 'storelly-product-builder-for-woocommerce' ) );
            return $post_id;
        }

        /**
         * Transition a quote to a new status, enforcing the state machine.
         *
         * @param int    $post_id Quote post ID.
         * @param string $to      Target status slug.
         * @param string $note    Optional timeline note.
         * @return true|WP_Error
         */
        public static function set_status( $post_id, $to, $note = '' ) {
            $post_id = absint( $post_id );
            $post    = $post_id ? get_post( $post_id ) : null;
            if ( ! $post || self::POST_TYPE !== $post->post_type ) {
                return new WP_Error( 'spbwc_quote_not_found', __( 'Quote not found.', 'storelly-product-builder-for-woocommerce' ) );
            }
            $from = $post->post_status;
            if ( $from === $to ) {
                return true;
            }
            if ( ! self::can_transition( $from, $to ) ) {
                return new WP_Error(
                    'spbwc_quote_bad_transition',
                    sprintf(
                        /* translators: 1: current status slug, 2: target status slug */
                        __( 'Quote cannot move from %1$s to %2$s.', 'storelly-product-builder-for-woocommerce' ),
                        $from,
                        $to
                    )
                );
            }
            $result = wp_update_post(
                array(
                    'ID'          => $post_id,
                    'post_status' => $to,
                ),
                true
            );
            if ( is_wp_error( $result ) ) {
                return $result;
            }
            if ( '' !== $note ) {
                self::add_timeline_event( $post_id, $note );
            }
            do_action( 'spbwc_quote_status_changed', $post_id, $from, $to );
            return true;
        }

        /* ── Line items & totals ──────────────────────────────────── */

        /**
         * Get the merchant-priced line items.
         *
         * @param int $post_id Quote post ID.
         * @return array<int,array{label:string,desc:string,qty:float,unit_price:float,line_total:float}>
         */
        public static function get_lines( $post_id ) {
            $lines = get_post_meta( absint( $post_id ), self::META_LINES, true );
            return is_array( $lines ) ? $lines : array();
        }

        /**
         * Replace line items and recompute totals.
         *
         * @param int   $post_id Quote post ID.
         * @param array $lines   Raw line rows (label, desc, qty, unit_price).
         * @return array         Stored totals.
         */
        public static function set_lines( $post_id, array $lines ) {
            $post_id = absint( $post_id );
            $clean   = array();
            foreach ( $lines as $row ) {
                $qty   = isset( $row['qty'] ) ? (float) $row['qty'] : 0;
                $unit  = isset( $row['unit_price'] ) ? (float) $row['unit_price'] : 0;
                $label = isset( $row['label'] ) ? sanitize_text_field( $row['label'] ) : '';
                if ( '' === $label && $qty <= 0 ) {
                    continue;
                }
                $clean[] = array(
                    'label'      => $label,
                    'desc'       => isset( $row['desc'] ) ? sanitize_textarea_field( $row['desc'] ) : '',
                    'qty'        => $qty,
                    'unit_price' => $unit,
                    'line_total' => round( $qty * $unit, 2 ),
                );
            }
            update_post_meta( $post_id, self::META_LINES, $clean );
            return self::recalculate_totals( $post_id, $clean );
        }

        /**
         * Recompute and store totals from line items.
         *
         * Tax/discount handling lands with the pricing-reply UI (M2); this base
         * version stores subtotal = total. Existing discount/tax values in the
         * stored totals are preserved when present.
         *
         * @param int        $post_id Quote post ID.
         * @param array|null $lines   Lines (fetched if null).
         * @return array              Totals array.
         */
        public static function recalculate_totals( $post_id, $lines = null ) {
            $post_id = absint( $post_id );
            if ( null === $lines ) {
                $lines = self::get_lines( $post_id );
            }
            $subtotal = 0.0;
            foreach ( $lines as $row ) {
                $subtotal += isset( $row['line_total'] ) ? (float) $row['line_total'] : 0;
            }
            $existing = get_post_meta( $post_id, self::META_TOTALS, true );
            $discount = ( is_array( $existing ) && isset( $existing['discount'] ) ) ? (float) $existing['discount'] : 0.0;
            $tax      = ( is_array( $existing ) && isset( $existing['tax'] ) ) ? (float) $existing['tax'] : 0.0;
            $totals   = array(
                'subtotal' => round( $subtotal, 2 ),
                'discount' => round( $discount, 2 ),
                'tax'      => round( $tax, 2 ),
                'total'    => round( $subtotal - $discount + $tax, 2 ),
                'currency' => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '',
            );
            update_post_meta( $post_id, self::META_TOTALS, $totals );
            return $totals;
        }

        /**
         * Get stored totals.
         *
         * @param int $post_id Quote post ID.
         * @return array
         */
        public static function get_totals( $post_id ) {
            $totals = get_post_meta( absint( $post_id ), self::META_TOTALS, true );
            return is_array( $totals ) ? $totals : array(
                'subtotal' => 0,
                'discount' => 0,
                'tax'      => 0,
                'total'    => 0,
                'currency' => '',
            );
        }

        /* ── Activity timeline (comments) ─────────────────────────── */

        /**
         * Append an event to the quote activity timeline.
         *
         * @param int    $post_id Quote post ID.
         * @param string $message Human-readable event text.
         * @param int    $user_id Actor (0 = current user / system).
         * @return int|false Comment ID or false.
         */
        public static function add_timeline_event( $post_id, $message, $user_id = 0 ) {
            $user_id = $user_id ? absint( $user_id ) : get_current_user_id();
            return wp_insert_comment(
                array(
                    'comment_post_ID'  => absint( $post_id ),
                    'comment_content'  => wp_kses_post( $message ),
                    'comment_type'     => self::TIMELINE_COMMENT_TYPE,
                    'user_id'          => $user_id,
                    'comment_approved' => 1,
                )
            );
        }

        /**
         * Fetch timeline events (oldest first).
         *
         * @param int $post_id Quote post ID.
         * @return WP_Comment[]
         */
        public static function get_timeline( $post_id ) {
            return get_comments(
                array(
                    'post_id' => absint( $post_id ),
                    'type'    => self::TIMELINE_COMMENT_TYPE,
                    'orderby' => 'comment_date_gmt',
                    'order'   => 'ASC',
                    'status'  => 'approve',
                )
            );
        }
    }
}
