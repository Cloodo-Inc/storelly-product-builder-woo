<?php
if (!defined('ABSPATH')) exit;

class SPBWC_Marketplace_Design{
    protected static $instance;

    public static function get_instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function init(){
        add_action( 'template_redirect', array( $this, 'handle_designs' ) );
    }

    public function handle_designs(){
        $request_data       = wp_unslash( $_REQUEST );
        $need_redirect      = false;

        if(isset( $request_data['action'] ) && $request_data['action'] == 'spbwc_marketplace_delete_design' ){
            $this->delete_design();
            $need_redirect = true;
        }
        if( $need_redirect ){
            wp_redirect( add_query_arg( array( 'tab' => 'designs' ), wc_get_endpoint_url( 'my-store', '', wc_get_page_permalink( 'myaccount' ) ) ) );
        }
    }

    public function delete_design(){
        global $wpdb, $current_user;

        if (!wp_verify_nonce($_REQUEST['_wpnonce'], 'spbwc_marketplace_delete_design') && NBDESIGNER_ENABLE_NONCE) {
            die('Security error');
        }

        $get_data   = wp_unslash( $_GET );
        $row_id     = absint( $get_data['id'] );

        $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}storelly_marketplace_designs WHERE id = %d AND user_id = %d", $row_id, $current_user->ID ) );
    }
}

function SPBWC_Marketplace_Design(){
    return SPBWC_Marketplace_Design::get_instance();
}
SPBWC_Marketplace_Design()->init();