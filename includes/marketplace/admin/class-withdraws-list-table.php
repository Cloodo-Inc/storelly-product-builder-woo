<?php
/**
 * Withdraw requests list table.
 *
 * @package Storelly
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

if ( ! class_exists( 'SPBWC_Withdraws_List_Table' ) ) {

    class SPBWC_Withdraws_List_Table extends WP_List_Table {

        public function __construct() {
            parent::__construct( array(
                'singular' => 'withdraw',
                'plural'   => 'withdraws',
                'ajax'     => false,
            ) );
        }

        public function get_columns() {
            return array(
                'cb'             => '<input type="checkbox" />',
                'designer'       => esc_html__( 'Designer', 'storelly-product-builder-for-woocommerce' ),
                'amount'         => esc_html__( 'Amount', 'storelly-product-builder-for-woocommerce' ),
                'method'         => esc_html__( 'Method', 'storelly-product-builder-for-woocommerce' ),
                'note'           => esc_html__( 'Note', 'storelly-product-builder-for-woocommerce' ),
                'status'         => esc_html__( 'Status', 'storelly-product-builder-for-woocommerce' ),
                'ip'             => esc_html__( 'IP', 'storelly-product-builder-for-woocommerce' ),
                'requested_date' => esc_html__( 'Requested', 'storelly-product-builder-for-woocommerce' ),
                'actions'        => esc_html__( 'Actions', 'storelly-product-builder-for-woocommerce' ),
            );
        }

        protected function get_bulk_actions() {
            return array(
                'approve' => esc_html__( 'Approve', 'storelly-product-builder-for-woocommerce' ),
                'cancel'  => esc_html__( 'Cancel', 'storelly-product-builder-for-woocommerce' ),
            );
        }

        protected function column_cb( $item ) {
            return sprintf( '<input type="checkbox" name="withdraw_ids[]" value="%d" />', (int) $item['id'] );
        }

        protected function column_designer( $item ) {
            $user = get_userdata( (int) $item['user_id'] );
            if ( ! $user ) {
                return esc_html__( '—', 'storelly-product-builder-for-woocommerce' );
            }
            $url = SPBWC_Marketplace_Admin::get_instance()->get_tab_url( 'designers', array( 'action' => 'edit', 'id' => (int) $item['user_id'] ) );
            return '<a href="' . esc_url( $url ) . '">' . esc_html( $user->display_name ) . '</a>';
        }

        protected function column_amount( $item ) {
            if ( function_exists( 'wc_price' ) ) {
                return wp_kses_post( wc_price( (float) $item['amount'] ) );
            }
            return esc_html( number_format_i18n( (float) $item['amount'], 2 ) );
        }

        protected function column_method( $item ) {
            return esc_html( $item['method'] );
        }

        protected function column_note( $item ) {
            return esc_html( $item['note'] );
        }

        protected function column_status( $item ) {
            $status = (int) $item['status'];
            $map    = array(
                0 => array( 'spbwc-pill--warn', __( 'Pending', 'storelly-product-builder-for-woocommerce' ) ),
                1 => array( 'spbwc-pill--ok',   __( 'Approved', 'storelly-product-builder-for-woocommerce' ) ),
                2 => array( 'spbwc-pill--off',  __( 'Cancelled', 'storelly-product-builder-for-woocommerce' ) ),
            );
            $entry = isset( $map[ $status ] ) ? $map[ $status ] : $map[0];
            return '<span class="spbwc-pill ' . esc_attr( $entry[0] ) . '">' . esc_html( $entry[1] ) . '</span>';
        }

        protected function column_ip( $item ) {
            return esc_html( $item['ip'] );
        }

        protected function column_requested_date( $item ) {
            return esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $item['date'] ) );
        }

        protected function column_actions( $item ) {
            if ( 0 !== (int) $item['status'] ) {
                return esc_html__( '—', 'storelly-product-builder-for-woocommerce' );
            }
            return sprintf(
                '<a href="#" class="button button-primary spbwc-withdraw-action" data-action="approve" data-id="%1$d">%2$s</a> '
                . '<a href="#" class="button spbwc-withdraw-action" data-action="cancel" data-id="%1$d">%3$s</a>',
                (int) $item['id'],
                esc_html__( 'Approve', 'storelly-product-builder-for-woocommerce' ),
                esc_html__( 'Cancel', 'storelly-product-builder-for-woocommerce' )
            );
        }

        public function prepare_items() {
            global $wpdb;
            $per_page     = 20;
            $current_page = $this->get_pagenum();
            $status       = isset( $_REQUEST['filter_status'] ) && '' !== $_REQUEST['filter_status'] // phpcs:ignore WordPress.Security.NonceVerification
                ? (int) wp_unslash( $_REQUEST['filter_status'] ) // phpcs:ignore WordPress.Security.NonceVerification
                : null;

            $table = $wpdb->prefix . 'storelly_marketplace_withdraw';
            $where = '';
            $params = array();
            if ( null !== $status ) {
                $where  = ' WHERE status = %d';
                $params[] = $status;
            }

            $count_sql = "SELECT COUNT(*) FROM {$table}" . $where;
            $total     = (int) $wpdb->get_var( $params ? $wpdb->prepare( $count_sql, $params ) : $count_sql ); // phpcs:ignore WordPress.DB.PreparedSQL

            $offset = ( $current_page - 1 ) * $per_page;
            $select = "SELECT * FROM {$table}" . $where . ' ORDER BY date DESC LIMIT %d OFFSET %d';
            $params_select = array_merge( $params, array( $per_page, $offset ) );
            $rows = $wpdb->get_results( $wpdb->prepare( $select, $params_select ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL

            $items = array();
            foreach ( (array) $rows as $row ) {
                $items[] = array(
                    'id'      => (int) $row['id'],
                    'user_id' => (int) $row['user_id'],
                    'amount'  => $row['amount'],
                    'date'    => $row['date'],
                    'status'  => (int) $row['status'],
                    'method'  => isset( $row['method'] ) ? $row['method'] : '',
                    'note'    => isset( $row['note'] ) ? $row['note'] : '',
                    'ip'      => isset( $row['ip'] ) ? $row['ip'] : '',
                );
            }

            $this->_column_headers = array( $this->get_columns(), array(), array() );
            $this->items           = $items;
            $this->set_pagination_args( array( 'total_items' => $total, 'per_page' => $per_page ) );
        }

        public function no_items() {
            esc_html_e( 'No withdraw requests found.', 'storelly-product-builder-for-woocommerce' );
        }
    }
}
