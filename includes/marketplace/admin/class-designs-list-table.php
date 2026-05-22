<?php
/**
 * Designs list table.
 *
 * @package Storelly
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

if ( ! class_exists( 'SPBWC_Designs_List_Table' ) ) {

    class SPBWC_Designs_List_Table extends WP_List_Table {

        public function __construct() {
            parent::__construct( array(
                'singular' => 'design',
                'plural'   => 'designs',
                'ajax'     => false,
            ) );
        }

        public function get_columns() {
            return array(
                'cb'           => '<input type="checkbox" />',
                'preview'      => esc_html__( 'Preview', 'storelly-product-builder-for-woocommerce' ),
                'name'         => esc_html__( 'Name', 'storelly-product-builder-for-woocommerce' ),
                'designer'     => esc_html__( 'Designer', 'storelly-product-builder-for-woocommerce' ),
                'product'      => esc_html__( 'Product', 'storelly-product-builder-for-woocommerce' ),
                'status'       => esc_html__( 'Status', 'storelly-product-builder-for-woocommerce' ),
                'sales'        => esc_html__( 'Sales', 'storelly-product-builder-for-woocommerce' ),
                'hits'         => esc_html__( 'Views', 'storelly-product-builder-for-woocommerce' ),
                'created_date' => esc_html__( 'Created', 'storelly-product-builder-for-woocommerce' ),
            );
        }

        protected function get_bulk_actions() {
            return array(
                'publish'   => esc_html__( 'Publish', 'storelly-product-builder-for-woocommerce' ),
                'unpublish' => esc_html__( 'Unpublish', 'storelly-product-builder-for-woocommerce' ),
                'delete'    => esc_html__( 'Delete', 'storelly-product-builder-for-woocommerce' ),
            );
        }

        protected function column_cb( $item ) {
            return sprintf( '<input type="checkbox" name="design_ids[]" value="%d" />', (int) $item['id'] );
        }

        protected function column_preview( $item ) {
            $url = '';
            if ( ! empty( $item['folder'] ) && function_exists( 'spbwc_marketplace_get_design_preview' ) ) {
                $preview = spbwc_marketplace_get_design_preview( $item['folder'] );
                if ( is_array( $preview ) ) {
                    $url = isset( $preview['thumbnail'] ) ? $preview['thumbnail'] : ( isset( $preview[0] ) ? $preview[0] : '' );
                } else {
                    $url = (string) $preview;
                }
            }
            if ( '' === $url ) {
                return '<span class="spbwc-pill spbwc-pill--off">' . esc_html__( '—', 'storelly-product-builder-for-woocommerce' ) . '</span>';
            }
            return '<img src="' . esc_url( $url ) . '" alt="" class="spbwc-design-thumb" />';
        }

        protected function column_name( $item ) {
            return esc_html( $item['name'] );
        }

        protected function column_designer( $item ) {
            $user = get_userdata( (int) $item['user_id'] );
            if ( ! $user ) {
                return esc_html__( '—', 'storelly-product-builder-for-woocommerce' );
            }
            $url = SPBWC_Marketplace_Admin::get_instance()->get_tab_url( 'designers', array( 'action' => 'edit', 'id' => (int) $item['user_id'] ) );
            return '<a href="' . esc_url( $url ) . '">' . esc_html( $user->display_name ) . '</a>';
        }

        protected function column_product( $item ) {
            $product_id = (int) $item['product_id'];
            if ( $product_id < 1 ) {
                return esc_html__( '—', 'storelly-product-builder-for-woocommerce' );
            }
            $title = get_the_title( $product_id );
            return '<a href="' . esc_url( get_edit_post_link( $product_id ) ) . '">' . esc_html( $title ? $title : '#' . $product_id ) . '</a>';
        }

        protected function column_status( $item ) {
            $published = (int) $item['publish'] === 1;
            $action    = $published ? 'unpublish' : 'publish';
            $label     = $published ? esc_html__( 'Published', 'storelly-product-builder-for-woocommerce' ) : esc_html__( 'Draft', 'storelly-product-builder-for-woocommerce' );
            $class     = $published ? 'spbwc-pill spbwc-pill--ok' : 'spbwc-pill spbwc-pill--off';
            return sprintf(
                '<a href="#" class="spbwc-toggle-design %s" data-action="%s" data-id="%d">%s</a>',
                esc_attr( $class ),
                esc_attr( $action ),
                (int) $item['id'],
                $label
            );
        }

        protected function column_sales( $item ) {
            return (int) ( isset( $item['sales'] ) ? $item['sales'] : 0 );
        }

        protected function column_hits( $item ) {
            return (int) ( isset( $item['hit'] ) ? $item['hit'] : 0 );
        }

        protected function column_created_date( $item ) {
            $date = isset( $item['created_date'] ) ? $item['created_date'] : '';
            return esc_html( mysql2date( get_option( 'date_format' ), $date ) );
        }

        public function prepare_items() {
            $per_page     = 20;
            $current_page = $this->get_pagenum();
            $status       = isset( $_REQUEST['filter_status'] ) ? sanitize_key( wp_unslash( $_REQUEST['filter_status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
            $designer     = isset( $_REQUEST['filter_designer'] ) ? absint( wp_unslash( $_REQUEST['filter_designer'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification

            $rows = function_exists( 'spbwc_marketplace_get_designs' )
                ? spbwc_marketplace_get_designs( $status, $per_page, ( $current_page - 1 ) * $per_page, $designer ? $designer : '' )
                : array();
            $counts = function_exists( 'spbwc_marketplace_get_design_status_count' )
                ? spbwc_marketplace_get_design_status_count( $designer ? $designer : '' )
                : array( 'all' => 0 );
            $total  = isset( $counts['all'] ) ? (int) $counts['all'] : count( (array) $rows );

            $items = array();
            foreach ( (array) $rows as $row ) {
                $row     = (array) $row;
                $items[] = array(
                    'id'           => isset( $row['id'] ) ? (int) $row['id'] : 0,
                    'user_id'      => isset( $row['user_id'] ) ? (int) $row['user_id'] : 0,
                    'product_id'   => isset( $row['product_id'] ) ? (int) $row['product_id'] : 0,
                    'folder'       => isset( $row['folder'] ) ? $row['folder'] : '',
                    'name'         => isset( $row['name'] ) ? $row['name'] : '',
                    'publish'      => isset( $row['publish'] ) ? (int) $row['publish'] : 0,
                    'sales'        => isset( $row['sales'] ) ? $row['sales'] : 0,
                    'hit'          => isset( $row['hit'] ) ? $row['hit'] : 0,
                    'created_date' => isset( $row['created_date'] ) ? $row['created_date'] : '',
                );
            }

            $this->_column_headers = array( $this->get_columns(), array(), array() );
            $this->items           = $items;
            $this->set_pagination_args( array( 'total_items' => $total, 'per_page' => $per_page ) );
        }

        public function no_items() {
            esc_html_e( 'No designs found.', 'storelly-product-builder-for-woocommerce' );
        }
    }
}
