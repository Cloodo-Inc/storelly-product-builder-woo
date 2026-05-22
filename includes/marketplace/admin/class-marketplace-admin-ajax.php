<?php
/**
 * Marketplace admin AJAX handlers.
 *
 * Inline action endpoints used by the PHP admin (replacing the legacy
 * React `nbdl_*` endpoints, which remain available for any third-party
 * consumers). Every handler verifies the `spbwc_marketplace_admin`
 * nonce and the `spbwc_manage_product_builder` capability.
 *
 * @package Storelly
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'SPBWC_Marketplace_Admin_Ajax' ) ) {

    class SPBWC_Marketplace_Admin_Ajax {

        const NONCE_ACTION = 'spbwc_marketplace_admin';
        const CAPABILITY   = 'spbwc_manage_product_builder';

        public static function register() {
            add_action( 'wp_ajax_spbwc_marketplace_toggle_designer_status', array( __CLASS__, 'toggle_designer_status' ) );
            add_action( 'wp_ajax_spbwc_marketplace_approve_withdraw', array( __CLASS__, 'approve_withdraw' ) );
            add_action( 'wp_ajax_spbwc_marketplace_publish_design', array( __CLASS__, 'publish_design' ) );
        }

        protected static function gate() {
            if ( ! current_user_can( self::CAPABILITY ) ) {
                wp_send_json_error( array( 'message' => __( 'Permission denied.', 'storelly-product-builder-for-woocommerce' ) ), 403 );
            }
            check_ajax_referer( self::NONCE_ACTION, '_wpnonce' );
        }

        public static function toggle_designer_status() {
            self::gate();
            $designer_id = isset( $_POST['designer_id'] ) ? absint( wp_unslash( $_POST['designer_id'] ) ) : 0;
            $action      = isset( $_POST['mp_action'] ) ? sanitize_key( wp_unslash( $_POST['mp_action'] ) ) : '';

            if ( $designer_id < 1 || ! in_array( $action, array( 'enable', 'disable', 'feature', 'unfeature' ), true ) ) {
                wp_send_json_error( array( 'message' => __( 'Invalid request.', 'storelly-product-builder-for-woocommerce' ) ), 400 );
            }

            switch ( $action ) {
                case 'enable':
                    update_user_meta( $designer_id, 'spbwc_marketplace_sell', 'on' );
                    do_action( 'spbwc_marketplace_designer_enabled', $designer_id );
                    break;
                case 'disable':
                    delete_user_meta( $designer_id, 'spbwc_marketplace_sell' );
                    do_action( 'spbwc_marketplace_designer_disabled', $designer_id );
                    break;
                case 'feature':
                    update_user_meta( $designer_id, 'spbwc_marketplace_featured', 'on' );
                    break;
                case 'unfeature':
                    delete_user_meta( $designer_id, 'spbwc_marketplace_featured' );
                    break;
            }

            wp_send_json_success( array( 'designer_id' => $designer_id, 'action' => $action ) );
        }

        public static function approve_withdraw() {
            global $wpdb;
            self::gate();

            $withdraw_id = isset( $_POST['withdraw_id'] ) ? absint( wp_unslash( $_POST['withdraw_id'] ) ) : 0;
            $action      = isset( $_POST['mp_action'] ) ? sanitize_key( wp_unslash( $_POST['mp_action'] ) ) : '';
            $note        = isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '';

            if ( $withdraw_id < 1 || ! in_array( $action, array( 'approve', 'cancel' ), true ) ) {
                wp_send_json_error( array( 'message' => __( 'Invalid request.', 'storelly-product-builder-for-woocommerce' ) ), 400 );
            }

            $table = $wpdb->prefix . 'storelly_marketplace_withdraw';
            $row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $withdraw_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL

            if ( ! $row ) {
                wp_send_json_error( array( 'message' => __( 'Withdraw request not found.', 'storelly-product-builder-for-woocommerce' ) ), 404 );
            }

            $new_status = ( 'approve' === $action ) ? 1 : 2;
            $update = array( 'status' => $new_status );
            if ( '' !== $note ) {
                $update['note'] = $note;
            }
            $wpdb->update( $table, $update, array( 'id' => $withdraw_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

            $user_id = (int) $row['user_id'];
            if ( 1 === $new_status ) {
                do_action( 'spbwc_marketplace_withdraw_request_approved', $user_id, $withdraw_id );
            } else {
                do_action( 'spbwc_marketplace_withdraw_request_cancelled', $user_id, $withdraw_id );
            }

            wp_send_json_success( array( 'withdraw_id' => $withdraw_id, 'status' => $new_status ) );
        }

        public static function publish_design() {
            global $wpdb;
            self::gate();

            $design_id = isset( $_POST['design_id'] ) ? absint( wp_unslash( $_POST['design_id'] ) ) : 0;
            $action    = isset( $_POST['mp_action'] ) ? sanitize_key( wp_unslash( $_POST['mp_action'] ) ) : '';

            if ( $design_id < 1 || ! in_array( $action, array( 'publish', 'unpublish', 'delete' ), true ) ) {
                wp_send_json_error( array( 'message' => __( 'Invalid request.', 'storelly-product-builder-for-woocommerce' ) ), 400 );
            }

            $table = $wpdb->prefix . 'storelly_marketplace_designs';
            if ( 'delete' === $action ) {
                $wpdb->delete( $table, array( 'id' => $design_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            } else {
                $publish = ( 'publish' === $action ) ? 1 : 0;
                $wpdb->update( $table, array( 'publish' => $publish ), array( 'id' => $design_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            }

            wp_send_json_success( array( 'design_id' => $design_id, 'action' => $action ) );
        }
    }

    SPBWC_Marketplace_Admin_Ajax::register();
}
