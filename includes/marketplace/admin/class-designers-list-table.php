<?php
/**
 * Designers list table.
 *
 * @package Storelly
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

if ( ! class_exists( 'SPBWC_Designers_List_Table' ) ) {

    class SPBWC_Designers_List_Table extends WP_List_Table {

        public function __construct() {
            parent::__construct( array(
                'singular' => 'designer',
                'plural'   => 'designers',
                'ajax'     => false,
            ) );
        }

        public function get_columns() {
            return array(
                'cb'            => '<input type="checkbox" />',
                'username'      => esc_html__( 'Username', 'storelly-product-builder-for-woocommerce' ),
                'display_name'  => esc_html__( 'Display name', 'storelly-product-builder-for-woocommerce' ),
                'email'         => esc_html__( 'Email', 'storelly-product-builder-for-woocommerce' ),
                'status'        => esc_html__( 'Status', 'storelly-product-builder-for-woocommerce' ),
                'featured'      => esc_html__( 'Featured', 'storelly-product-builder-for-woocommerce' ),
                'designs_count' => esc_html__( 'Designs', 'storelly-product-builder-for-woocommerce' ),
                'balance'       => esc_html__( 'Balance', 'storelly-product-builder-for-woocommerce' ),
                'joined_date'   => esc_html__( 'Joined', 'storelly-product-builder-for-woocommerce' ),
            );
        }

        protected function get_bulk_actions() {
            return array(
                'enable'     => esc_html__( 'Enable', 'storelly-product-builder-for-woocommerce' ),
                'disable'    => esc_html__( 'Disable', 'storelly-product-builder-for-woocommerce' ),
                'feature'    => esc_html__( 'Mark featured', 'storelly-product-builder-for-woocommerce' ),
                'unfeature'  => esc_html__( 'Unmark featured', 'storelly-product-builder-for-woocommerce' ),
            );
        }

        protected function column_cb( $item ) {
            return sprintf( '<input type="checkbox" name="designer_ids[]" value="%d" />', (int) $item['id'] );
        }

        protected function column_username( $item ) {
            $user_id   = (int) $item['id'];
            $edit_url  = SPBWC_Marketplace_Admin::get_instance()->get_tab_url( 'designers', array( 'action' => 'edit', 'id' => $user_id ) );
            $row_actions = array(
                'edit'   => '<a href="' . esc_url( $edit_url ) . '">' . esc_html__( 'Edit', 'storelly-product-builder-for-woocommerce' ) . '</a>',
                'toggle' => '<a href="#" class="spbwc-toggle-designer" data-action="' . ( ! empty( $item['sell_design'] ) ? 'disable' : 'enable' ) . '" data-id="' . $user_id . '">' . ( ! empty( $item['sell_design'] ) ? esc_html__( 'Disable', 'storelly-product-builder-for-woocommerce' ) : esc_html__( 'Enable', 'storelly-product-builder-for-woocommerce' ) ) . '</a>',
            );
            $username = isset( $item['username'] ) ? $item['username'] : '';
            return sprintf( '<strong><a href="%s">%s</a></strong> %s', esc_url( $edit_url ), esc_html( $username ), $this->row_actions( $row_actions ) );
        }

        protected function column_display_name( $item ) {
            $name = isset( $item['display_name'] ) ? $item['display_name'] : '';
            if ( ! empty( $item['artist_name'] ) ) {
                $name = $item['artist_name'] . ' (' . $name . ')';
            }
            return esc_html( $name );
        }

        protected function column_email( $item ) {
            $email = isset( $item['email'] ) ? $item['email'] : '';
            return '<a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a>';
        }

        protected function column_status( $item ) {
            $is_on = ! empty( $item['sell_design'] );
            return $is_on
                ? '<span class="spbwc-pill spbwc-pill--ok">' . esc_html__( 'Enabled', 'storelly-product-builder-for-woocommerce' ) . '</span>'
                : '<span class="spbwc-pill spbwc-pill--off">' . esc_html__( 'Disabled', 'storelly-product-builder-for-woocommerce' ) . '</span>';
        }

        protected function column_featured( $item ) {
            $is_on = ! empty( $item['feature_designer'] );
            $label = $is_on ? esc_html__( 'Yes', 'storelly-product-builder-for-woocommerce' ) : esc_html__( 'No', 'storelly-product-builder-for-woocommerce' );
            $next  = $is_on ? 'unfeature' : 'feature';
            return sprintf(
                '<a href="#" class="spbwc-toggle-designer" data-action="%s" data-id="%d">%s</a>',
                esc_attr( $next ),
                (int) $item['id'],
                $label
            );
        }

        protected function column_designs_count( $item ) {
            return isset( $item['designs_count'] ) ? (int) $item['designs_count'] : 0;
        }

        protected function column_balance( $item ) {
            if ( function_exists( 'wc_price' ) && isset( $item['balance'] ) ) {
                return wp_kses_post( wc_price( (float) $item['balance'] ) );
            }
            return isset( $item['balance'] ) ? esc_html( $item['balance'] ) : '';
        }

        protected function column_joined_date( $item ) {
            $date = isset( $item['registered'] ) ? $item['registered'] : '';
            return esc_html( mysql2date( get_option( 'date_format' ), $date ) );
        }

        public function prepare_items() {
            $per_page     = 20;
            $current_page = $this->get_pagenum();

            $status   = isset( $_REQUEST['filter_status'] ) ? sanitize_key( wp_unslash( $_REQUEST['filter_status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
            $featured = isset( $_REQUEST['filter_featured'] ) ? sanitize_key( wp_unslash( $_REQUEST['filter_featured'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
            $search   = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification

            $args = array(
                'limit'  => $per_page,
                'offset' => ( $current_page - 1 ) * $per_page,
            );
            if ( '' !== $status ) {
                $args['status'] = $status; // 'enabled' | 'disabled'
            }
            if ( '' !== $featured ) {
                $args['featured'] = $featured; // 'yes' | 'no'
            }
            if ( '' !== $search ) {
                $args['search'] = $search;
            }

            $result = function_exists( 'spbwc_marketplace_get_designers' ) ? spbwc_marketplace_get_designers( $args, true ) : array( 'total' => 0, 'rows' => array() );
            $items  = array();
            if ( ! empty( $result['rows'] ) && is_array( $result['rows'] ) ) {
                foreach ( $result['rows'] as $row ) {
                    $row = (array) $row;
                    $items[] = array(
                        'id'                => isset( $row['id'] ) ? (int) $row['id'] : ( isset( $row['ID'] ) ? (int) $row['ID'] : 0 ),
                        'username'          => isset( $row['username'] ) ? $row['username'] : ( isset( $row['user_login'] ) ? $row['user_login'] : '' ),
                        'display_name'      => isset( $row['display_name'] ) ? $row['display_name'] : '',
                        'artist_name'       => isset( $row['artist_name'] ) ? $row['artist_name'] : '',
                        'email'             => isset( $row['email'] ) ? $row['email'] : ( isset( $row['user_email'] ) ? $row['user_email'] : '' ),
                        'sell_design'       => isset( $row['sell_design'] ) ? $row['sell_design'] : '',
                        'feature_designer'  => isset( $row['feature_designer'] ) ? $row['feature_designer'] : '',
                        'designs_count'     => isset( $row['designs_count'] ) ? $row['designs_count'] : ( isset( $row['count_designs'] ) ? $row['count_designs'] : 0 ),
                        'balance'           => isset( $row['balance'] ) ? $row['balance'] : 0,
                        'registered'        => isset( $row['registered'] ) ? $row['registered'] : ( isset( $row['user_registered'] ) ? $row['user_registered'] : '' ),
                    );
                }
            }

            $this->_column_headers = array( $this->get_columns(), array(), array() );
            $this->items = $items;

            $this->set_pagination_args( array(
                'total_items' => isset( $result['total'] ) ? (int) $result['total'] : count( $items ),
                'per_page'    => $per_page,
            ) );
        }

        public function no_items() {
            esc_html_e( 'No designers found.', 'storelly-product-builder-for-woocommerce' );
        }
    }
}
