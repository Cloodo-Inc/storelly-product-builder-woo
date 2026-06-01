<?php
/**
 * Custom Quotes list table (B2B quote redesign, M2).
 *
 * Lists `spbwc_quote` CPT posts with status tabs, search, pagination and bulk
 * actions. Bulk processing + per-row actions are handled by SPBWC_Quote_Admin.
 *
 * @package Storelly
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

if ( ! class_exists( 'SPBWC_Quotes_List_Table' ) ) {

    class SPBWC_Quotes_List_Table extends WP_List_Table {

        public function __construct() {
            parent::__construct(
                array(
                    'singular' => 'quote',
                    'plural'   => 'quotes',
                    'ajax'     => false,
                )
            );
        }

        public function get_columns() {
            return array(
                'cb'       => '<input type="checkbox" />',
                'number'   => esc_html__( 'Quote #', 'storelly-product-builder-for-woocommerce' ),
                'customer' => esc_html__( 'Customer', 'storelly-product-builder-for-woocommerce' ),
                'summary'  => esc_html__( 'Request', 'storelly-product-builder-for-woocommerce' ),
                'value'    => esc_html__( 'Est. value', 'storelly-product-builder-for-woocommerce' ),
                'status'   => esc_html__( 'Status', 'storelly-product-builder-for-woocommerce' ),
                'updated'  => esc_html__( 'Updated', 'storelly-product-builder-for-woocommerce' ),
                'expires'  => esc_html__( 'Expires', 'storelly-product-builder-for-woocommerce' ),
            );
        }

        protected function get_bulk_actions() {
            return array(
                'mark_review' => esc_html__( 'Mark in review', 'storelly-product-builder-for-woocommerce' ),
                'mark_expired' => esc_html__( 'Mark expired', 'storelly-product-builder-for-woocommerce' ),
                'withdraw'    => esc_html__( 'Withdraw', 'storelly-product-builder-for-woocommerce' ),
            );
        }

        /**
         * Status tabs with counts.
         */
        protected function get_views() {
            $counts  = (array) wp_count_posts( SPBWC_Quote::POST_TYPE );
            $current = $this->current_status();
            $base    = SPBWC_Quote_Admin::page_url();
            $total   = 0;
            foreach ( SPBWC_Quote::statuses() as $slug => $label ) {
                $total += isset( $counts[ $slug ] ) ? (int) $counts[ $slug ] : 0;
            }
            $views = array();
            $views['all'] = sprintf(
                '<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
                esc_url( $base ),
                '' === $current ? 'current' : '',
                esc_html__( 'All', 'storelly-product-builder-for-woocommerce' ),
                $total
            );
            foreach ( SPBWC_Quote::statuses() as $slug => $label ) {
                $n = isset( $counts[ $slug ] ) ? (int) $counts[ $slug ] : 0;
                if ( $n < 1 && $slug !== $current ) {
                    continue;
                }
                $views[ $slug ] = sprintf(
                    '<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
                    esc_url( add_query_arg( 'status', $slug, $base ) ),
                    $slug === $current ? 'current' : '',
                    esc_html( $label ),
                    $n
                );
            }
            return $views;
        }

        protected function current_status() {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only status filter.
            $status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
            return array_key_exists( $status, SPBWC_Quote::statuses() ) ? $status : '';
        }

        protected function column_cb( $item ) {
            return sprintf( '<input type="checkbox" name="quote_ids[]" value="%d" />', (int) $item['id'] );
        }

        protected function column_number( $item ) {
            $url   = SPBWC_Quote_Admin::page_url( array( 'quote' => $item['id'] ) );
            $title = $item['number'] ? $item['number'] : '#' . $item['id'];
            $sub   = $item['summary'] ? $item['summary'] : '';
            $html  = '<div class="spbwc-q-rowtitle">';
            $html .= '<a class="spbwc-q-rowtitle__num" href="' . esc_url( $url ) . '">' . esc_html( $title ) . '</a>';
            if ( $sub ) {
                $html .= '<span class="spbwc-q-rowtitle__sub">' . esc_html( wp_trim_words( $sub, 8, '…' ) ) . '</span>';
            }
            $html .= '</div>';
            $actions = array(
                'view' => '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Open', 'storelly-product-builder-for-woocommerce' ) . '</a>',
            );
            return $html . $this->row_actions( $actions );
        }

        protected function column_customer( $item ) {
            return esc_html( $item['customer'] ? $item['customer'] : __( 'Guest', 'storelly-product-builder-for-woocommerce' ) );
        }

        protected function column_summary( $item ) {
            return '<span class="spbwc-admin-table__muted">' . esc_html( $item['summary'] ? $item['summary'] : '—' ) . '</span>';
        }

        protected function column_value( $item ) {
            if ( $item['total'] > 0 ) {
                return wp_kses_post( wc_price( $item['total'], array( 'currency' => $item['currency'] ) ) );
            }
            return '<span class="spbwc-admin-table__muted">' . esc_html__( '—', 'storelly-product-builder-for-woocommerce' ) . '</span>';
        }

        protected function column_status( $item ) {
            return SPBWC_Quote_Admin::status_pill( $item['status'] );
        }

        protected function column_updated( $item ) {
            return '<span class="spbwc-admin-table__muted">' . esc_html( $item['updated'] ) . '</span>';
        }

        protected function column_expires( $item ) {
            if ( '' === $item['valid_until'] ) {
                return '<span class="spbwc-admin-table__muted">' . esc_html__( '—', 'storelly-product-builder-for-woocommerce' ) . '</span>';
            }
            $ts   = strtotime( $item['valid_until'] . ' 23:59:59' );
            $now  = current_time( 'timestamp' );
            $days = (int) floor( ( $ts - $now ) / DAY_IN_SECONDS );
            $date = esc_html( date_i18n( get_option( 'date_format' ), $ts ) );
            if ( $ts < $now ) {
                return '<span class="spbwc-q-expires--soon">' . esc_html__( 'Expired', 'storelly-product-builder-for-woocommerce' ) . '</span>';
            }
            if ( $days <= 3 ) {
                return '<span class="spbwc-q-expires--soon">' . $date . '</span>';
            }
            return '<span class="spbwc-admin-table__muted">' . $date . '</span>';
        }

        public function prepare_items() {
            $per_page = 20;
            $paged    = $this->get_pagenum();
            $status   = $this->current_status();
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only search filter.
            $search   = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';

            $query = new WP_Query(
                array(
                    'post_type'      => SPBWC_Quote::POST_TYPE,
                    'post_status'    => $status ? $status : array_keys( SPBWC_Quote::statuses() ),
                    'posts_per_page' => $per_page,
                    'paged'          => $paged,
                    's'              => $search,
                    'orderby'        => 'modified',
                    'order'          => 'DESC',
                    'no_found_rows'  => false,
                )
            );

            $items = array();
            foreach ( $query->posts as $post ) {
                $request = get_post_meta( $post->ID, SPBWC_Quote::META_REQUEST, true );
                $request = is_array( $request ) ? $request : array();
                $totals  = SPBWC_Quote::get_totals( $post->ID );
                $items[] = array(
                    'id'          => $post->ID,
                    'number'      => get_post_meta( $post->ID, SPBWC_Quote::META_NUMBER, true ),
                    'status'      => $post->post_status,
                    'customer'    => self::derive_customer( $request, (int) $post->post_author ),
                    'summary'     => self::derive_summary( $request ),
                    'total'       => isset( $totals['total'] ) ? (float) $totals['total'] : 0,
                    'currency'    => isset( $totals['currency'] ) && $totals['currency'] ? $totals['currency'] : get_woocommerce_currency(),
                    'updated'     => get_the_modified_date( get_option( 'date_format' ), $post ),
                    'valid_until' => (string) get_post_meta( $post->ID, SPBWC_Quote::META_VALID_UNTIL, true ),
                );
            }

            $this->_column_headers = array( $this->get_columns(), array(), array() );
            $this->items           = $items;
            $this->set_pagination_args(
                array(
                    'total_items' => (int) $query->found_posts,
                    'per_page'    => $per_page,
                )
            );
        }

        /**
         * Build a customer display string from the request payload.
         *
         * @param array $request Request meta.
         * @param int   $author  Post author user ID.
         * @return string
         */
        public static function derive_customer( array $request, $author = 0 ) {
            if ( ! empty( $request['company'] ) ) {
                return (string) $request['company'];
            }
            $name = trim(
                ( isset( $request['first_name'] ) ? $request['first_name'] : '' ) . ' ' .
                ( isset( $request['last_name'] ) ? $request['last_name'] : '' )
            );
            if ( '' !== $name ) {
                return $name;
            }
            if ( $author > 0 ) {
                $user = get_userdata( $author );
                if ( $user ) {
                    return $user->display_name;
                }
            }
            return '';
        }

        /**
         * Build a one-line request summary.
         *
         * @param array $request Request meta.
         * @return string
         */
        public static function derive_summary( array $request ) {
            if ( ! empty( $request['product_name'] ) ) {
                $qty = isset( $request['quantity'] ) ? (int) $request['quantity'] : 0;
                return $qty > 0
                    ? sprintf( '%s × %d', $request['product_name'], $qty )
                    : (string) $request['product_name'];
            }
            if ( ! empty( $request['message'] ) ) {
                return (string) $request['message'];
            }
            return '';
        }

        public function no_items() {
            // Rendered as a styled empty state by the controller; keep a plain fallback.
            esc_html_e( 'No quotes yet.', 'storelly-product-builder-for-woocommerce' );
        }
    }
}
