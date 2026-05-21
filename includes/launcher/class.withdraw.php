<?php
if (!defined('ABSPATH')) exit;

class SPBWC_Withdraw{
    protected static $instance;

    public static function get_instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function init(){
        add_action( 'template_redirect', array( $this, 'handle_withdraws' ) );
    }

    function update_status( $row_id, $user_id, $status ) {
        global $wpdb;

        $wpdb->query(
            $wpdb->prepare( "UPDATE {$wpdb->prefix}storelly_marketplace_withdraw SET status = %d WHERE user_id=%d AND id = %d",
                $status,
                $user_id,
                $row_id
            )
        );

        do_action( 'nbdl_withdraw_status_updated', $status, $user_id, $row_id );

    }

    function insert_withdraw( $data = array() ) {
        global $wpdb;

        $data = array(
            'user_id' => $data['user_id'],
            'amount'  => $data['amount'],
            'date'    => current_time( 'mysql' ),
            'status'  => $data['status'],
            'method'  => $data['method'],
            'note'    => $data['notes'],
            'ip'      => $data['ip'],
        );

        $format = array( '%d', '%f', '%s', '%d', '%s', '%s', '%s' );

        return $wpdb->insert( $wpdb->prefix . 'storelly_marketplace_withdraw', $data, $format );
    }

    function has_pending_request( $user_id ) {
        global $wpdb;

        $status = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id
                FROM {$wpdb->prefix}storelly_marketplace_withdraw
                WHERE user_id = %d AND status = 0",
                $user_id
            )
        );

        if ( $status ) {
            return true;
        }

        return false;
    }

    function get_withdraw_requests( $user_id = '', $status = 0, $limit = 10, $offset = 0 ) {
        global $wpdb;

        if ( empty( $user_id ) ) {
            $result = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}storelly_marketplace_withdraw WHERE status = %d ORDER BY date DESC LIMIT %d, %d", $status, $offset, $limit ) );
        } else {
            $result = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}storelly_marketplace_withdraw WHERE user_id = %d AND status = %d ORDER BY date DESC LIMIT %d, %d", $user_id, $status, $offset, $limit ) );
        }

        return $result;
    }

    function get_all_withdraws( $user_id, $limit = 100, $offset = 0 ) {
        global $wpdb;

        if ( empty( $user_id ) ) {
            $result = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}storelly_marketplace_withdraw ORDER BY date DESC LIMIT %d, %d", $offset, $limit ) );
        } else {
            $result = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}storelly_marketplace_withdraw WHERE user_id =%d ORDER BY date DESC LIMIT %d, %d", $user_id, $offset, $limit ) );
        }

        return $result;
    }

    function delete_withdraw( $id ) {
        global $wpdb;

        $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}storelly_marketplace_withdraw WHERE id = %d", $id ) );
    }

    function has_withdraw_balance( $user_id ) {

        $balance        = spbwc_marketplace_get_designer_balance( $user_id, false );
        $withdraw_limit = (float) nbdesigner_get_option( 'spbwc_marketplace_minimum_withdraw', 0 );

        if ( $balance < $withdraw_limit ) {
            return false;
        }

        return true;
    }

    public function handle_withdraws(){
        $request_data       = wp_unslash( $_REQUEST );
        $need_redirect      = false;
        if( isset( $request_data['nbdl_withdraw_submit'] ) ){
            $this->submit_request();
            $need_redirect = true;
        }
        if(isset( $request_data['action'] ) && $request_data['action'] == 'spbwc_marketplace_cancel_withdraw' ){
            $this->cancel_request();
            $need_redirect = true;
        }
        if( $need_redirect ){
            wp_redirect( add_query_arg( array( 'tab' => 'withdraw' ), wc_get_endpoint_url( 'my-store', '', wc_get_page_permalink( 'myaccount' ) ) ) );
        }
    }
    function submit_request(){
        global $current_user;

        if (!wp_verify_nonce($_REQUEST['nbdl_withdraw_nonce'], 'spbwc_marketplace_withdraw') && NBDESIGNER_ENABLE_NONCE) {
            die('Security error');
        }

        if( !current_user_can( 'spbwc_become_designer' ) ){
            wp_die( esc_attr__( 'You have no permission to do this action', 'storelly-product-builder-for-woocommerce' ) );
        }

        $post_data          = wp_unslash( $_POST );
        $designer_id        = $current_user->ID;
        $withdraw_limit     = (float) nbdesigner_get_option( 'spbwc_marketplace_minimum_withdraw', 0 );
        $balance            = spbwc_marketplace_get_designer_balance( $designer_id, false );
        $withdraw_amount    = (float) sanitize_text_field( $post_data['witdraw_amount'] );
        if ( !empty( $withdraw_amount ) && $withdraw_amount <= $balance && $withdraw_amount >= $withdraw_limit ) {
            $data_info = array(
                'user_id' => $designer_id,
                'amount'  => $withdraw_amount,
                'status'  => 0,
                'method'  => '',
                'ip'      => nbd_get_client_ip(),
                'notes'   => '',
            );
            $update = $this->insert_withdraw( $data_info );
            do_action( 'spbwc_marketplace_after_withdraw_request', $current_user, $withdraw_amount, '' );
        }
    }
    function cancel_request(){
        global $current_user;

        if (!wp_verify_nonce($_REQUEST['_wpnonce'], 'spbwc_marketplace_cancel_withdraw') && NBDESIGNER_ENABLE_NONCE) {
            die('Security error');
        }

        if( !current_user_can( 'spbwc_become_designer' ) ){
            wp_die( esc_attr__( 'You have no permission to do this action', 'storelly-product-builder-for-woocommerce' ) );
        }

        $get_data   = wp_unslash( $_GET );
        $row_id     = absint( $get_data['id'] );

        $this->update_status( $row_id, $current_user->ID, 2 );
    }
}

function SPBWC_Withdraw(){
    return SPBWC_Withdraw::get_instance();
}
SPBWC_Withdraw()->init();