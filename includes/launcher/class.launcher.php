<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class SPBWC_Marketplace{
    protected static $instance;
    public $query_vars = array(
        'my_store'    => 'my-store'
    );
    public static function get_instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    public function __construct(){
    }
    public function init(){
        /* Settings */
        add_action( 'nbdesigner_include_settings', array( $this, 'include_settings' ) );
        add_filter( 'nbdesigner_settings_tabs', array( $this, 'settings_tabs' ), 20, 1 );
        add_filter( 'nbdesigner_settings_blocks', array( $this, 'settings_blocks' ), 20, 1 );
        add_filter( 'nbdesigner_settings_options', array( $this, 'settings_options' ), 20, 1 );
        add_filter( 'nbdesigner_default_settings', array( $this, 'default_settings' ), 20, 1 );
        add_filter( 'nbd_multicheckbox_settings', array( $this, 'multicheckbox_settings' ), 20, 1 );
        
        add_filter( 'woocommerce_hidden_order_itemmeta', array( $this, 'hidden_order_itemmeta' ) );

        /* Init database */
        add_action( 'nbd_create_tables', array( $this, 'create_tables' ) );
        
        if( nbdesigner_get_option( 'spbwc_marketplace_enabled', 'no' ) == 'yes' ){
            add_action( 'nbd_menu', array( $this, 'add_sub_menu' ), 191 );
            add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ), 30, 1 );
            add_action( 'plugins_loaded', array( $this, 'add_designer_role' ) );
            add_action( 'rest_api_init', array( $this, 'register_rest_routes' ), 10 );
            $this->ajax();

            add_filter( 'nbd_admin_pages', array( $this, 'admin_pages' ), 20, 1 );
        
            add_action( 'woocommerce_before_my_account', array( $this, 'show_link_become_designer' ), 1 );
            add_filter( 'get_avatar_url', array( $this, 'get_avatar_url' ), 100, 3 );

            add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'update_designer_balance_and_order' ), 20 );
            add_action( 'woocommerce_order_status_changed', array( $this, 'on_order_status_change' ), 10, 4 );

            add_filter( 'woocommerce_email_classes', array( $this, 'add_emails_classes' ) );
            add_filter( 'woocommerce_email_actions' , array( $this, 'add_email_actions' ) );

            add_action( 'wp_enqueue_scripts', array( $this, 'frontend_enqueue_scripts' ) );
            add_action( 'wp_footer', array( $this, 'print_launcher_popup' ) );

            add_action( 'nbd_after_option_product_design', array( $this, 'upload_solid_design_option' ), 20, 2 );

            add_action( 'woocommerce_before_single_product_summary', array( $this, 'add_hook_change_image_id' ), 1 );
            add_action( 'woocommerce_after_single_product_summary', array( $this, 'remove_hook_change_image_id' ), 1 );
            add_action( 'woocommerce_single_product_summary', array( $this, 'show_design_author' ), 6 );
            add_action( 'woocommerce_before_add_to_cart_button', array( $this, 'nbdl_before_add_to_cart_button' ) );
            add_filter( 'woocommerce_add_cart_item_data', array( $this, 'add_cart_item_data' ), 20, 1 );
            add_filter( 'nbo_artwork_action', array( $this, 'nbo_artwork_action' ), 20, 2 );
            add_filter( 'nbd_conditional_show_design_btn', array( $this, 'hide_design_btn' ), 20, 1 );
            add_filter( 'nbo_field_class', array( $this, 'nbo_field_class' ), 20, 2 );

            add_filter( 'woocommerce_cart_item_permalink', array( $this, 'cart_item_permalink' ), 100, 3 );
            add_filter( 'woocommerce_cart_item_thumbnail', array( $this, 'cart_item_thumbnail' ), 100, 3 );

            add_filter( 'woocommerce_order_item_permalink', array( $this, 'order_item_permalink' ), 100, 3 );
            add_filter( 'woocommerce_admin_order_item_thumbnail', array( $this, 'admin_order_item_thumbnail' ), 60, 3 );
            add_action( 'woocommerce_after_order_itemmeta', array( $this, 'print_download_design_button' ), 30, 3 );

            /* Storefronts */
            add_action( 'init', array( $this, 'add_endpoints' ) );
            add_filter( 'query_vars', array( $this, 'add_query_vars' ), 0 );
            add_filter( 'the_title', array( $this, 'endpoint_title' ) );
            add_filter( 'woocommerce_account_menu_items', array( $this, 'new_menu_items' ) );
            foreach ( $this->query_vars as $key => $var ){
                add_action( 'woocommerce_account_' . $var . '_endpoint', array( $this, 'page_'.$key . '_content' ), 10, 1 );
            }

            if( nbdesigner_get_option( 'spbwc_marketplace_auto_generate_color_preview', 'no' ) == 'yes' ){
                add_filter( 'nbo_product_options', array( $this, 'override_product_image_by_design_preview' ), 10, 2 );
            }

            add_shortcode( 'spbwc_designers', array( $this,'spbwc_designers_func' ) );
        }
        add_action( 'delete_user', array( $this, 're_assign_templates' ), 10, 2 );
    }
    public function add_endpoints() {
        foreach ( $this->query_vars as $var ){
            add_rewrite_endpoint($var, EP_ROOT | EP_PAGES);
        }
    }
    public function add_query_vars($vars) {
        foreach ( $this->query_vars as $var ){
            $vars[] = $var;
        }
        return $vars;
    }
    public function endpoint_title($title) {
        global $wp_query;
        foreach ( $this->query_vars as $var ){
            $is_endpoint = isset($wp_query->query_vars[$var]);
            if ($is_endpoint && !is_admin() && is_main_query() && in_the_loop() && is_account_page()) {
                switch ( $var ) {
                    case 'my-store':
                        $title = esc_html__('My store', 'storelly-product-builder-for-woocommerce');
                        break;
                }
                remove_filter('the_title', array($this, 'endpoint_title'));
            }
        }
        return $title;
    }
    public function new_menu_items($items) {
        $user_id = get_current_user_id();
        if( !spbwc_marketplace_is_designer_enabled( $user_id ) ){
            return $items;
        }

        // Remove the logout menu item.
        $logout = $items['customer-logout'];
        unset($items['customer-logout']);

        // Insert your custom endpoint.
        $items['my-store'] = esc_html__('Design store', 'storelly-product-builder-for-woocommerce');

        // Insert back the logout item.
        $items['customer-logout'] = $logout;

        return $items;
    }
    public function page_my_store_content(){
        $user_id    = get_current_user_id();

        if( !spbwc_marketplace_is_designer_enabled( $user_id ) ){
            return;
        }

        $tabs           = array( 'dashboard', 'withdraw', 'settings', 'designs' );
        $tab            = ( isset( $_GET['tab'] ) && in_array( $_GET['tab'], $tabs ) ) ? sanitize_text_field($_GET['tab']) : 'dashboard';
        $designer_id    = get_current_user_id();
        $data = array(
            'tab'           => $tab,
            'designer_id'   => $designer_id
        );
        $function   = "get_store_{$tab}_data";
        $data       = $this->$function( $data );

        ob_start();
        nbdesigner_get_template("launcher/store/tabs.php", $data);
        nbdesigner_get_template("launcher/store/{$tab}.php", $data);
        $content = ob_get_clean();
        echo wp_kses_post( $content );
    }
    public function get_store_dashboard_data( $data ){
        $designer_id        = $data['designer_id'];
        $data['designs']    = spbwc_marketplace_get_design_status_count( $designer_id );
        $data['sales']      = spbwc_marketplace_get_sale_status_count( $designer_id );

        $labels         = array();
        $design_counts  = array();
        $sale_counts    = array();
        $start_date     = new DateTime( 'first day of this month' );
        $end_date       = new DateTime();
        $design_data    = spbwc_marketplace_get_design_report( 'day', $start_date->format( 'Y-m-d' ), $end_date->format( 'Y-m-d' ), $designer_id );
        $sale_data      = spbwc_marketplace_get_sale_report( 'day', $start_date->format( 'Y-m-d' ), $end_date->format( 'Y-m-d' ), $designer_id );

        for ( $i = $start_date; $i <= $end_date; $i->modify( '+1 day' ) ){
            $date                     = $i->format( 'Y-m-d' );
            $labels[ $date ]          = $date;
            $design_counts[ $date ]   = 0;
            $sale_counts[ $date ]     = 0;
        }

        foreach ( $design_data as $row ) {
            $date                   = gmdate( 'Y-m-d', strtotime( $row->created_date ) );
            $design_counts[ $date ] = (int) $row->total;
        }

        foreach ( $sale_data as $row ) {
            $date                   = gmdate( 'Y-m-d', strtotime( $row->created_date ) );
            $sale_counts[ $date ]   = (int) $row->total;
        }

        $data['report'] = array(
            'labels'   => array_values( $labels ),
            'datasets' => array(
                array(
                    'label'           => esc_html__( 'Created designs', 'storelly-product-builder-for-woocommerce' ),
                    'borderColor'     => '#3498db',
                    'fill'            => false,
                    'data'            => array_values( $design_counts ),
                    'tooltipLabel'    => esc_html__( 'Total', 'storelly-product-builder-for-woocommerce' )
                ),
                array(
                    'label'           => esc_html__( 'Sold designs', 'storelly-product-builder-for-woocommerce' ),
                    'borderColor'     => '#1abc9c',
                    'fill'            => false,
                    'data'            => array_values( $sale_counts ),
                    'tooltipLabel'    => esc_html__( 'Total', 'storelly-product-builder-for-woocommerce' )
                )
            )
        );

        return $data;
    }
    public function get_store_withdraw_data( $data ){
        $data['balance']                = spbwc_marketplace_get_designer_balance( $data['designer_id'], false );
        $data['balance_display']        = wc_price( $data['balance'] );
        $data['min_withdraw']           = wc_price( nbdesigner_get_option( 'spbwc_marketplace_minimum_withdraw', 0 ) );
        $data['has_withdraw_balance']   = SPBWC_Withdraw()->has_withdraw_balance( $data['designer_id'] );
        $data['has_pending_request']    = SPBWC_Withdraw()->has_pending_request( $data['designer_id'] );
        $data['pending_requests']       = SPBWC_Withdraw()->get_withdraw_requests( $data['designer_id'] );
        $data['approved_requests']      = SPBWC_Withdraw()->get_withdraw_requests( $data['designer_id'], 1 );
        $data['cancelled_requests']     = SPBWC_Withdraw()->get_withdraw_requests( $data['designer_id'], 2 );
        return $data;
    }
    public function get_store_settings_data( $data ){
        global $current_user;

        $designer                           = new SPBWC_Designer( $data['designer_id'] );
        $data['user_info']                  = $designer->get_store_info();
        $data['user_info']['gravatar_url']  = $designer->get_avatar();
        $data['banner_width']               = absint( nbdesigner_get_option( 'spbwc_marketplace_banner_width', 1050 ) );
        $data['banner_height']              = absint( nbdesigner_get_option( 'spbwc_marketplace_banner_height', 200 ) );

        return $data;
    }
    public function get_store_designs_data( $data ){
        global $wp;
        
        $limit              = 20;
        $counts             = spbwc_marketplace_get_design_status_count( $data['designer_id'] );
        $current_page       = absint( $wp->query_vars['my-store'] );
        $current_page       = $current_page ? $current_page : 1;
        $offset             = ( $current_page - 1 ) * $limit;
        $max_page           = ceil( $counts['all'] / $limit );
        $dashboard_url      = wc_get_endpoint_url( 'my-store', '', wc_get_page_permalink( 'myaccount' ) );
        $designs            = spbwc_marketplace_get_designs( '', $limit, $offset, $data['designer_id'] );
        if( $max_page <= 1 ){
            $next_page = $prev_page = '';
        }else{
            $prev_page = 1 != $current_page ? add_query_arg( array( 'tab' => 'designs' ), wc_get_endpoint_url( 'my-store', $current_page - 1, wc_get_page_permalink( 'myaccount' ) ) ) : '';
            $next_page = $max_page != $current_page ? add_query_arg( array( 'tab' => 'designs' ), wc_get_endpoint_url( 'my-store', $current_page + 1, wc_get_page_permalink( 'myaccount' ) ) ) : '';
        }
        
        $data['designs']    = array();
        foreach( $designs as $key => $design ){
            $data['designs'][$key] = array(
                'id'            => $design->id,
                'user'          => spbwc_marketplace_get_designer_data( $design->user_id ),
                'product'       => nbdl_get_product_data( $design->product_id, $design->variation_id ),
                'previews'      => spbwc_marketplace_get_design_preview( $design->folder ),
                'date'          => $design->created_date,
                'folder'        => $design->folder,
                'type'          => $design->type,
                'status'        => (int) $design->publish
            );
        }

        $data['counts']         = $counts;
        $data['next_page']      = $next_page;
        $data['prev_page']      = $prev_page;
        $data['current_page']   = $current_page;

        return $data;
    }
    public function create_tables(){
        global $wpdb;
        $collate = '';
        if ( $wpdb->has_cap( 'collation' ) ) {
            $collate = $wpdb->get_charset_collate();
        } 
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        $tables = "CREATE TABLE `{$wpdb->prefix}storelly_marketplace_withdraw` (
               `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
               `user_id` bigint(20) unsigned NOT NULL,
               `amount` float(11) NOT NULL,
               `date` timestamp NOT NULL,
               `status` int(1) NOT NULL,
               `method` varchar(30) NULL,
               `note` text NULL,
               `ip` varchar(50) NULL,
              PRIMARY KEY  (id)
            ) $collate;
            CREATE TABLE `{$wpdb->prefix}storelly_marketplace_balance` (
               `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
               `user_id` bigint(20) unsigned NOT NULL,
               `transaction_id` bigint(20) unsigned NOT NULL,
               `transaction_type` varchar(30) NOT NULL,
               `note` text NULL,
               `debit` float(11) NOT NULL,
               `credit` float(11) NOT NULL,
               `status` varchar(30) DEFAULT NULL,
               `transaction_date` timestamp NOT NULL,
               `balance_date` timestamp NOT NULL,
              PRIMARY KEY  (id)
            ) $collate;
            CREATE TABLE `{$wpdb->prefix}storelly_marketplace_orders` (
               `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
               `design_id` varchar(30) NOT NULL,
               `transaction_id` bigint(20) unsigned NOT NULL,
               `qty` bigint(20) unsigned NOT NULL,
               `status` varchar(30) DEFAULT NULL,
               `transaction_date` timestamp NOT NULL,
              PRIMARY KEY  (id)
            ) $collate;";
        
        @dbDelta( $tables );
    }
    public function ajax(){
        $ajax_events = array(
            'nbdl_update_designer_status'   => true,
            'nbdl_get_product_info'         => true,
            'nbdl_get_related_products'     => true,
            'nbdl_submit_product'           => true
        );
        foreach ( $ajax_events as $ajax_event => $nopriv ) {
            add_action( 'wp_ajax_' . $ajax_event, array( $this, $ajax_event ) );
            if ( $nopriv ) {
                add_action( 'wp_ajax_nopriv_' . $ajax_event, array( $this, $ajax_event ) );
            }
        }
    }
    public function admin_pages( $pages ){
        $pages[] = 'pc-designer_page_nbd_designers';
        return $pages;
    }
    public function include_settings(){
        require_once(NBDESIGNER_PLUGIN_DIR . 'includes/settings/launcher.php');
    }
    public function settings_tabs( $tabs ){
        $tabs['designers'] = '<span class="dashicons dashicons-groups"></span> '. esc_html__('Designers', 'storelly-product-builder-for-woocommerce');
        return $tabs;
    }
    public function settings_blocks( $blocks ){
        $blocks['designers'] = array(
            'general-designers' => esc_html__('General', 'storelly-product-builder-for-woocommerce'),
            'for-admin'         => esc_html__('Admin', 'storelly-product-builder-for-woocommerce'),
            'for-designer'      => esc_html__('Designer', 'storelly-product-builder-for-woocommerce'),
            'design'            => esc_html__('Design', 'storelly-product-builder-for-woocommerce')
        );
        return $blocks;
    }
    public function settings_options( $options ){
        $launcher_options               = Nbdesigner_Launcher::get_options();
        $options['general-designers']   = $launcher_options['general'];
        $options['for-admin']           = $launcher_options['admin'];
        $options['for-designer']        = $launcher_options['designer'];
        $options['design']              = $launcher_options['design'];
        return $options;
    }
    public function default_settings( $settings ){
        $settings['spbwc_marketplace_enabled']                   = 'no';
        $settings['spbwc_marketplace_commission_type']                         = 'percentage';
        $settings['spbwc_marketplace_default_commission']                      = 0;
        $settings['spbwc_marketplace_default_commission2']                     = '0|0';
        $settings['spbwc_marketplace_banner_width']                   = 1050;
        $settings['spbwc_marketplace_banner_height']                  = 200;
        $settings['spbwc_marketplace_minimum_withdraw']                        = 0;
        $settings['storelly_marketplace_withdraw_threshold']                      = 0;
        $settings['spbwc_marketplace_order_status_for_withdraw_wc-completed']  = 1;
        $settings['spbwc_marketplace_order_status_for_withdraw_wc-processing'] = 0;
        $settings['spbwc_marketplace_order_status_for_withdraw_wc-on-hold']    = 0;
        $settings['spbwc_marketplace_auto_generate_color_preview']     = 'no';
        
        return $settings;
    }
    public function multicheckbox_settings( $settings ){
        $settings['spbwc_marketplace_order_status_for_withdraw_wc-completed']  = 1;
        $settings['spbwc_marketplace_order_status_for_withdraw_wc-processing'] = 0;
        $settings['spbwc_marketplace_order_status_for_withdraw_wc-on-hold']    = 0;
        
        return $settings;
    }
    public function register_rest_routes(){
        require_once( NBDESIGNER_PLUGIN_DIR . 'includes/launcher/api/designer.php' );
        require_once( NBDESIGNER_PLUGIN_DIR . 'includes/launcher/api/withdraw.php' );
        require_once( NBDESIGNER_PLUGIN_DIR . 'includes/launcher/api/design.php' );
        require_once( NBDESIGNER_PLUGIN_DIR . 'includes/launcher/api/report.php' );
        $designer_api   = new SPBWC_Designer_API();
        $withdraw_api   = new SPBWC_Withdraw_API();
        $design_api     = new SPBWC_Design_API();
        $report_api     = new SPBWC_Report_API();
        $designer_api->register_rest_routes();
        $withdraw_api->register_rest_routes();
        $design_api->register_rest_routes();
        $report_api->register_rest_routes();
    }
    public function add_sub_menu() {
        // React admin page removed in PR B. Marketplace admin is now registered
        // by SPBWC_Marketplace_Admin (see includes/marketplace/admin/) and renders
        // under the storelly Overview submenu instead of the legacy nbdesigner
        // parent menu. This method is kept as a no-op for back-compat with the
        // nbd_menu action; it can be removed once any third-party code dropping
        // submenus into the legacy slot has been audited.
    }
    public function manage_designers(){
        // No-op. React admin removed in PR B; see SPBWC_Marketplace_Admin.
    }
    public function add_emails_classes( $emails ){
        $emails['SPBWC_Email_Designer_Enabled']        = include( NBDESIGNER_PLUGIN_DIR . 'includes/launcher/emails/designer_enabled.php' );
        $emails['SPBWC_Email_Designer_Disabled']       = include( NBDESIGNER_PLUGIN_DIR . 'includes/launcher/emails/designer_disabled.php' );
        $emails['SPBWC_Email_Withdraw_Request']        = include( NBDESIGNER_PLUGIN_DIR . 'includes/launcher/emails/withdraw_request.php' );
        $emails['SPBWC_Email_Withdraw_Approved']       = include( NBDESIGNER_PLUGIN_DIR . 'includes/launcher/emails/withdraw_approved.php' );
        $emails['SPBWC_Email_Withdraw_Cancelled']      = include( NBDESIGNER_PLUGIN_DIR . 'includes/launcher/emails/withdraw_cancelled.php' );
        return $emails;
    }
    public function add_email_actions( $actions ){
        $actions[] = 'spbwc_marketplace_designer_enabled';
        $actions[] = 'spbwc_marketplace_designer_disabled';
        $actions[] = 'spbwc_marketplace_after_withdraw_request';
        $actions[] = 'spbwc_marketplace_withdraw_request_approved';
        $actions[] = 'spbwc_marketplace_withdraw_request_cancelled';
        return $actions;
    }
    public function admin_enqueue_scripts( $hook ) {
        // React admin dist removed in PR B. The new PHP admin
        // (SPBWC_Marketplace_Admin) enqueues its own assets on its own
        // page-hook gate, so this method intentionally does nothing.
    }
    public function frontend_enqueue_scripts(){
        wp_register_style( 'nbd_launcher', NBDESIGNER_CSS_URL . 'launcher.css', array(), NBDESIGNER_VERSION );
        wp_register_script( 'nbd_launcher', NBDESIGNER_JS_URL . 'launcher.js', array('jquery', 'selectWoo'), NBDESIGNER_VERSION );

        if ( is_account_page() ) {
            wp_enqueue_style( 'nbd_launcher' );
            wp_enqueue_script( 'nbd_launcher' );

            wp_localize_script( 'nbd_launcher', 'nbdl', array(
                'ajax_url'                  => admin_url('admin-ajax.php'),
                'nonce'                     => wp_create_nonce( 'nbd_launcher_nonce' ),
                'create_design_url'         => add_query_arg(array('task'  => 'create','rd' => 'my_store_design'), getUrlPageNBD('create')),
                'msg_alert_missing_file'    => esc_html__('Please choose all necessary files!', 'storelly-product-builder-for-woocommerce'),
                'max_preview_dimension'     => apply_filters( 'nbdl_max_preview_dimension', 1000 )
            ));
        }

        nbdesigner_get_template("gallery/search-bar.php", array());
    }
    public function upload_solid_design_option( $post_id, $option ){
        $enable_upload_solid_design = isset( $option['upload_solid_design'] ) ? $option['upload_solid_design'] : 0;
        ?>
        <div  id="nbd-upload_solid_design" class="nbdesigner-opt-inner" >
            <label for="_nbdesigner_option[upload_solid_design]" class="nbdesigner-option-label">
                <?php esc_html_e('Allow designer upload and sell solid design', 'storelly-product-builder-for-woocommerce'); ?>
            </label>
            <input type="hidden" value="0" name="_nbdesigner_option[upload_solid_design]"/>
            <input type="checkbox" value="1" name="_nbdesigner_option[upload_solid_design]" id="_nbdesigner_option[upload_solid_design]" <?php checked( $enable_upload_solid_design ); ?> class="short" />
        </div>
        <?php
    }
    public function print_launcher_popup(){
        if ( is_account_page() ) {

            $products           = nbd_get_products_has_design( true );
            $template_tags      = get_terms( array( 'taxonomy' => 'template_tag', 'hide_empty' => 0 ) );
            $tags               = array();
            if ( ! empty( $template_tags ) && ! is_wp_error( $template_tags ) ){
                foreach( $template_tags as $tag ){
                    $tags[] = array(
                        'term_id'   =>  $tag->term_id,
                        'name'      =>  $tag->name
                    );
                }
            }

            ob_start();
            nbdesigner_get_template("launcher/store/popup.php", array(
                'products'  => $products,
                'tags'      => $tags
            ));
            $content = ob_get_clean();
            echo wp_kses_post( $content );
        }
    }
    public function add_designer_role(){
        $capabilities = array(
            0 => 'spbwc_sell_design',
            1 => 'spbwc_become_designer'
        );
        $capabilities = apply_filters( 'nbd_designer_cap', $capabilities );
        $desinger_role = get_role( 'storelly_designer' );
        if( null === $desinger_role ){
            add_role( 'storelly_designer', esc_html__( 'Designer', 'storelly-product-builder-for-woocommerce' ), array(
                'spbwc_sell_design'   => true,
                'spbwc_become_designer'   => true,
                'upload_files'      => true
            ) );
        }
        $admin_role = get_role( 'administrator' );
        if (null != $admin_role) {
            foreach( $capabilities as $cap ){
                $admin_role->add_cap( $cap );
            }
        }
        $shop_manager_role = get_role( 'shop_manager' );
        if (null != $shop_manager_role) {
            foreach( $capabilities as $cap ){
                $shop_manager_role->add_cap( $cap );
            }
        }
    }
    public function get_avatar_url( $url, $id_or_email, $args ){
        if ( is_numeric( $id_or_email ) ) {
            $user = get_user_by( 'id', $id_or_email );
        } elseif ( is_object( $id_or_email ) ) {
            if ( $id_or_email->user_id != '0' ) {
                $user = get_user_by( 'id', $id_or_email->user_id );
            } else {
                return $url;
            }
        } else {
            $user = get_user_by( 'email', $id_or_email );
        }
        
        if ( ! $user ) {
            return $url;
        }
        
        $designer       = new SPBWC_Designer( $user->ID );
        $gravatar_id    = $designer->get_avatar_id();
        
        if ( ! $gravatar_id ) {
            return $url;
        }
        
        $avatar_url = wp_get_attachment_thumb_url( $gravatar_id );

        if ( empty( $avatar_url ) ) {
            return $url;
        }

        return esc_url( $avatar_url );
    }
    public function show_link_become_designer(){
        $user_id    = get_current_user_id();
        $link       = wc_get_endpoint_url( 'artist-info', $user_id, wc_get_page_permalink( 'myaccount' ) );
        if( !nbdl_is_user_designer( $user_id ) ):
        ?>
        <div class="nbdl-become-designer">
            <p><?php esc_html_e( 'You want to sell your designs to earn commission?', 'storelly-product-builder-for-woocommerce' ); ?></p>
            <a class="button" href="<?php echo esc_url( $link ); ?>"><?php esc_html_e( 'Become designer', 'storelly-product-builder-for-woocommerce' ); ?></a>
        </div>
        <?php
        endif;
    }
    public function nbdl_update_designer_status(){
        // Permission: only admins can toggle designer status.
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'storelly-product-builder-for-woocommerce' ) ), 403 );
        }
        $nonce = isset( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid nonce.', 'storelly-product-builder-for-woocommerce' ) ), 403 );
        }
        $user_id    = isset( $_POST['id'] ) ? absint( sanitize_text_field( wp_unslash( $_POST['id'] ) ) ) : 0;
        $type       = isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : '';
        $value      = isset( $_POST['value'] ) ? sanitize_text_field( wp_unslash( $_POST['value'] ) ) : '';
        $result     = array(
            'flag'  => 0
        );
        
        if( $user_id == 0 || $type == '' ){
            echo json_encode( $result );
            wp_die();
        }
        
        if( $type == 'enabled' ){
            $res = update_user_meta( $user_id, 'spbwc_marketplace_sell', $value );
            $result['message'] = $value == 'on' ? esc_html__( 'Designer has been enabled', 'storelly-product-builder-for-woocommerce' ) : esc_html__( 'Designer has been disabled', 'storelly-product-builder-for-woocommerce' );
        }
        if( $type == 'featured' ){
            $res = update_user_meta( $user_id, 'spbwc_marketplace_featured', $value );
            $result['message'] = $value == 'on' ? esc_html__( 'Featured designer has been enabled', 'storelly-product-builder-for-woocommerce' ) : esc_html__( 'Featured designer has been disabled', 'storelly-product-builder-for-woocommerce' );
        }
        
        if( $res !== false ){
            $result['flag'] = 1;
        }
        
        echo json_encode( $result );
        wp_die();
    }
    public function update_designer_balance_and_order( $order_id ){
        global $wpdb;
        $order  = wc_get_order( $order_id );

        if ( !empty( $order->post_parent ) ){
            return;
        }
        
        $designers      = array();
        $designs        = array();
        $order_status   = $order->get_status();
        $_designers     = nbd_get_designers_by( $order );
        if ( stripos( $order_status, 'wc-' ) === false ) {
            $order_status = 'wc-' . $order_status;
        }
        foreach( $_designers as $designer_id => $_designer ){
            foreach( $_designer as $design_id => $items ){
                if( !isset( $designers[$designer_id] ) ) $designers[$designer_id] = array();
                $designers[$designer_id] = array_merge( $designers[$designer_id], $items );
                $designs[$design_id] = count( $items );
            }
        }

        foreach( $designers as $designer_id => $items ){
            $designer_earning   = $this->get_designer_earning( $order, $designer_id, $items );
            $threshold_day      = absint( nbdesigner_get_option( 'storelly_marketplace_withdraw_threshold', 0 ) );
            
            $wpdb->insert( $wpdb->prefix . 'storelly_marketplace_balance',
                array(
                    'user_id'           => $designer_id,
                    'transaction_id'    => $order_id,
                    'transaction_type'  => 'new_order',
                    'note'              => 'New order',
                    'debit'             => $designer_earning,
                    'credit'            => 0,
                    'status'            => $order_status,
                    'transaction_date'  => current_time( 'mysql' ),
                    'balance_date'      => gmdate( 'Y-m-d h:i:s', strtotime( current_time( 'mysql' ) . ' + '.$threshold_day.' days' ) )
                ),
                array(
                    '%d',
                    '%d',
                    '%s',
                    '%s',
                    '%f',
                    '%f',
                    '%s',
                    '%s',
                    '%s',
                )
            );
        }

        foreach( $designs as $design_id => $qty ){
            $wpdb->insert( $wpdb->prefix . 'storelly_marketplace_orders',
                array(
                    'design_id'         => $design_id,
                    'transaction_id'    => $order_id,
                    'qty'               => $qty,
                    'status'            => $order_status,
                    'transaction_date'  => current_time( 'mysql' )
                ),
                array(
                    '%s',
                    '%d',
                    '%d',
                    '%s',
                    '%s'
                )
            );
        }
    }
    public function get_designer_earning( $order, $designer_id, $items ){
        $earning    = 0;
        $commission = $this->get_designer_commision( $designer_id );
        foreach ( $items as $item ) {
            $refund     = $order->get_total_refunded_for_item( $item->get_id() );
            if ( !$refund ) {
                $total  = $item->get_total();
                $earning += $commission['flat'] + $commission['percentage'] * $total / 100;
            }
        }
        return $earning;
    }
    public function get_designer_commision( $designer_id ){
        $designer           = new SPBWC_Designer( $designer_id );
        $commission_type    = $designer->get_artist_commission_type();
        if( $commission_type != 'combine' ){
            $commission_value = (float)$designer->get_artist_commission();
            $commission = array(
                'flat'          => $commission_type == 'flat' ? $commission_value : 0,
                'percentage'    => $commission_type == 'percentage' ? $commission_value : 0
            );
        }else{
            $commission_value   = $designer->get_artist_commission2();
            $commission = array(
                'flat'          => isset( $commission_value[1] ) ? (float)$commission_value[1] : 0,
                'percentage'    => isset( $commission_value[0] ) ? (float)$commission_value[0] : 0
            );
        }
        return $commission;
    }
    public function hidden_order_itemmeta( $order_items ){
        $order_items[] = '_spbwc_marketplace_design_id';
        return $order_items;
    }
    function on_order_status_change( $order_id, $old_status, $new_status, $order ) {
        global $wpdb;

        if ( stripos( $new_status, 'wc-' ) === false ) {
            $new_status = 'wc-' . $new_status;
        }

        $wpdb->update( $wpdb->prefix . 'storelly_marketplace_orders',
            array( 'status' => $new_status ),
            array( 'transaction_id' => $order_id ),
            array( '%s' ),
            array( '%d' )
        );

        $wpdb->update( $wpdb->prefix . 'storelly_marketplace_balance',
            array( 'status' => $new_status ),
            array( 'transaction_id' => $order_id, 'transaction_type' => 'new_order' ),
            array( '%s' ),
            array( '%d', '%s' )
        );
    }
    public function nbdl_get_product_info(){
        if (!wp_verify_nonce( $_POST['nonce'], 'nbd_launcher_nonce' ) && NBDESIGNER_ENABLE_NONCE) {
            die('Security error');
        }

        $product_id = absint( $_POST['product_id'] );
        $data       = array(
            'flag'  => 1
        );

        if( $product_id == 0 ){
            $data['message']    = esc_html__('Please select a product before upload design!', 'storelly-product-builder-for-woocommerce');
            $data['flag']       = 0;
            echo json_encode( $data );
            wp_die();
        }

        $data['setting']    = maybe_unserialize( get_post_meta( $product_id, '_designer_setting', true ) );
        foreach( $data['setting'] as $key => $side ){
            $data['setting'][$key]['img_src']       = is_numeric( $side['img_src'] ) ? wp_get_attachment_url( $side['img_src'] ) : $side['img_src'];
            $data['setting'][$key]['img_overlay']   = is_numeric( $side['img_overlay'] ) ? wp_get_attachment_url( $side['img_overlay'] ) : $side['img_overlay'];
        }

        $guideline_files    = maybe_unserialize( get_post_meta( $product_id, '_nbdg_files', true ) );
        if( $guideline_files ){
            $data['guidelines'] = $guideline_files;
        }

        if( isset( $_POST['task'] ) && $_POST['task'] == 'edit' ){
            $design_id  = wc_clean( $_POST['design_id'] );
            $design     = nbd_get_design( $design_id );
            if( !empty( $design ) ){
                $design_previews            = Nbdesigner_IO::get_list_images( NBDESIGNER_CUSTOMER_DIR . '/' . $design['resource'], 1 );
                $design['side_previews']    = array();
                foreach( $design_previews as $design_preview ){
                    $filename   = pathinfo( $design_preview, PATHINFO_FILENAME );
                    $arr        = explode( '_', $filename );
                    if( isset( $arr[1] ) ){
                        $index      = $arr[1];
                        $design['side_previews'][$index] = Nbdesigner_IO::convert_path_to_url( $design_preview );
                    }
                }

                $data['design']     = $design;
            }
        }

        echo json_encode( $data );
        wp_die();
    }
    public function nbdl_get_related_products(){
        if (!wp_verify_nonce( $_POST['nonce'], 'nbd_launcher_nonce' ) && NBDESIGNER_ENABLE_NONCE) {
            die('Security error');
        }

        $product_id         = absint( $_POST['product_id'] );
        $products           = nbd_get_products_has_design( true );
        $cats               = $this->get_product_categories( $product_id );
        $data['products']   = array();
        $related_number     = apply_filters( 'nbdl_number_of_related_product', 20 );
        $count              = 0;

        foreach( $products as $key => $product ){
            if( $product['allow_upload_solid'] != 0 && $product['product_id'] != $product_id ){
                $product_cats = $this->get_product_categories( $product['product_id'] );
                if( count( array_intersect( $cats, $product_cats ) ) ){
                    $count++;
                    $products[$key]['seleted']  = true;
                    $data['products'][]         = $product;
                    if( $count >= $related_number ) break;
                }
            }
        }

        if( $count < $related_number ){
            foreach( $products as $key => $product ){
                if( $product['allow_upload_solid'] != 0 && !isset( $products[$key]['seleted'] )  && $product['product_id'] != $product_id ){
                    $count++;
                    $data['products'][] = $product;
                    if( $count >= $related_number ) break;
                }
            }
        }

        foreach( $data['products'] as $key => $product ){
            $setting                = maybe_unserialize( get_post_meta( $product['product_id'], '_designer_setting', true ) );
            $side                   = $setting[0];
            $side['img_src']        = is_numeric( $side['img_src'] ) ? wp_get_attachment_url( $side['img_src'] ) : $side['img_src'];
            $side['img_overlay']    = is_numeric( $side['img_overlay'] ) ? wp_get_attachment_url( $side['img_overlay'] ) : $side['img_overlay'];
            $data['products'][$key]['setting']  = $side;
        }

        echo json_encode( $data );
        wp_die();
    }
    public function get_product_categories( $product_id ){
        $terms  = get_the_terms( $product_id, 'product_cat' );
        $cats   =  array();
        foreach ($terms as $term) {
            $cats[] = $term->term_id;
        }
        return $cats;
    }
    public function nbdl_submit_product(){
        if (!wp_verify_nonce( $_POST['nonce'], 'nbd_launcher_nonce' ) && NBDESIGNER_ENABLE_NONCE) {
            die('Security error');
        }

        $result = array(
            'flag'      => 1,
            'message'   => '',
            'templates' => array()
        );
        $products               = array();
        $product_id             = absint( $_POST['product_id'] );
        $related_product_ids    = wc_clean( $_POST['related_product_ids'] );
        $tags                   = wc_clean( $_POST['tags'] );
        $name                   = stripslashes( wc_clean( $_POST['name'] ) );
        $task                   = isset( $_POST['task'] ) ? wc_clean( $_POST['task'] ) : 'new';
        $folder                 = isset( $_POST['folder'] ) ? wc_clean( $_POST['folder'] ) : substr(md5(uniqid()),0,5).wp_rand(1,100).time();
        $path                   = NBDESIGNER_CUSTOMER_DIR . '/' . $folder;
        $max_upload_size        = absint( nbdesigner_get_option( 'nbdesigner_maxsize_upload_file', nbd_get_max_upload_default() ) );
        $max_size_in_byte       = $max_upload_size * 1024 * 1024;
        $content_designs        = array();

        if( !empty( $related_product_ids ) ){
            $products = explode( ',', $related_product_ids );
        }
        $products[]     = $product_id;

        if ( wp_mkdir_p( $path ) ) {
            foreach( $_FILES as $key => $file ){
                if( $file['error'] ){
                    $result['flag']     = 0;
                    $result['message']  = esc_html__('Upload file error!', 'storelly-product-builder-for-woocommerce');
                    break;
                }else{
                    if( $file['size'] > $max_size_in_byte ){
                        $result['flag']     = 0;
                        $result['message']  = esc_html__('File size too big', 'storelly-product-builder-for-woocommerce');
                        break;
                    }
                    
                    $type           = $file["type"];
                    $ext            = pathinfo( $file["name"], PATHINFO_EXTENSION );
                    $image_type     = array( 'image/jpeg', 'image/jpg', 'image/png' );

                    if( $key == 'product_preview' ){
                        if( !in_array( $type, $image_type ) ){
                            $result['flag']     = 0;
                            $result['message']  = esc_html__('Only alllow image for product preview.', 'storelly-product-builder-for-woocommerce');
                            break;
                        }
                        $thumb_id = nbd_upload_media( $file );
                        if( !is_numeric( $thumb_id ) ){
                            $result['flag']     = 0;
                            $result['message']  = esc_html__('Fail to create design thumbnail.', 'storelly-product-builder-for-woocommerce');
                            break;
                        }
                    } elseif ( $key == 'design' ){
                        if( $ext != 'zip' ) {
                            $result['flag']     = 0;
                            $result['message']  = esc_html__('Only alllow zip extension for design file.', 'storelly-product-builder-for-woocommerce');
                            break;
                        }
                        $full_name = $path . '/design.' . $ext;
                    } else {
                        if( !in_array( $type, $image_type ) ){
                            $result['flag']     = 0;
                            $result['message']  = esc_html__('Only alllow image for content design preview.', 'storelly-product-builder-for-woocommerce');
                            break;
                        }

                        $arr                        = explode('__', $key);
                        $index                      = $arr[1];
                        $full_name                  = $path . '/side_' . $index . '.' . $ext;
                        $content_designs[$index]    = $full_name;
                    }
                    if( $key != 'product_preview' ){
                        if ( !printcart_move_uploaded_file( $file["tmp_name"], $full_name ) ) {
                            $result['flag']     = 0;
                            $result['message']  = esc_html__('Upload file error!', 'storelly-product-builder-for-woocommerce');
                            break;
                        }
                    }
                }
            }

            if( $task == 'edit' ){
                $design_id  = wc_clean( $_POST['design_id'] );
                $design     = nbd_get_design( $design_id );
                if( !empty( $design ) ){
                    $resource_folder = isset( $_POST['design'] ) ? wc_clean( $_POST['design'] ) : '';
                    if( $resource_folder != '' ){
                        $resource_path  = NBDESIGNER_CUSTOMER_DIR . '/' . $resource_folder;
                        $folder         = $resource_folder;
                    }

                    if( isset( $_POST['product_preview'] ) && absint( $_POST['product_preview'] ) > 0 ){
                        $thumb_id = absint( $_POST['product_preview']);
                    }

                    if( $resource_folder == '' ){
                        $design_previews        = Nbdesigner_IO::get_list_images( NBDESIGNER_CUSTOMER_DIR . '/' . $design['resource'], 1 );

                        foreach( $_POST as $key => $post ){
                            if( false !== strpos( $key, 'side_previews' ) ){
                                $arr    = explode('__', $key);
                                $index  = $arr[1];
                                foreach( $design_previews as $design_preview ){
                                    $path_parts = pathinfo( $design_preview );
                                    if( $path_parts['filename'] == 'side_' . $index ){
                                        $dst = NBDESIGNER_CUSTOMER_DIR . '/' . $folder . '/' . $path_parts['basename'];
                                        if( copy( $design_preview, $dst ) ){
                                            $content_designs[$index]    = $dst;
                                        }else{
                                            $result['flag']     = 0;
                                            $result['message']  = esc_html__('Fail to edit exist design preview image!', 'storelly-product-builder-for-woocommerce');
                                            break;
                                        }
                                    }
                                }
                                if( $result['flag'] == 0 ) break;
                            }
                        }
                    }else{
                        foreach( $content_designs as $key => $content_design ){
                            $basename   = pathinfo( $content_design, PATHINFO_BASENAME );
                            $dst        = $resource_path . '/' . $basename;
                            if( !copy( $content_design, $dst ) ){
                                $result['flag']     = 0;
                                $result['message']  = esc_html__('Fail to edit exist design preview image!', 'storelly-product-builder-for-woocommerce');
                                break;
                            }
                        }

                        $design_previews = Nbdesigner_IO::get_list_images( $resource_path, 1 );

                        if( $result['flag'] == 1 ){
                            foreach( $design_previews as $design_preview ){
                                $filename   = pathinfo( $design_preview, PATHINFO_FILENAME );
                                $arr        = explode('_', $filename);
                                if( isset( $arr[1] ) ){
                                    $index      = $arr[1];
                                    $content_designs[$index]    = $design_preview;
                                }
                            }
                        }
                    }
                }else{
                    $result['flag']     = 0;
                    $result['message']  = esc_html__('Design does not exist!', 'storelly-product-builder-for-woocommerce');
                }
            }

            if( $result['flag'] == 1 ){
                $user_id                = wp_get_current_user()->ID;
                $publish                = nbd_check_publish_design_permission( $user_id ) ? 1 : 0;
                $approved               = array();
                $need_generate_preview  = nbdesigner_get_option( 'spbwc_marketplace_auto_generate_color_preview', 'no' ) == 'yes' ? $publish : 0;

                foreach( $products as $pid ){
                    $setting = maybe_unserialize( get_post_meta( $pid, '_designer_setting', true ) );
                    $p_folder = $this->create_side_preview( $setting, $content_designs );
                    if( $p_folder != false ){
                        $data = array(
                            'product_id'    => $pid,
                            'variation_id'  => 0,
                            'folder'        => $p_folder,
                            'user_id'       => $task == 'edit' ? $design['user_id'] : $user_id,
                            'created_date'  => current_time( 'mysql' ),
                            'publish'       => $publish,
                            'private'       => 0,
                            'priority'      => 0,
                            'type'          => 'solid',
                            'resource'      => $folder
                        );

                        if( $pid == $product_id && isset( $thumb_id ) ){
                            $data['thumbnail'] = $thumb_id;
                        }

                        if( !empty( $tags ) && !is_null( $tags ) ){
                            $data['tags'] = $tags;
                        }

                        if( !empty( $name ) ){
                            $data['name'] = $name;
                        }

                        if( $task == 'edit' && $pid == $product_id ){
                            $res = $this->update_solid_template( $design_id, $data );

                            if( $res && $need_generate_preview ){
                                $approved[] = $design_id;
                            }
                        }else{
                            $res = $this->insert_solid_template( $data );

                            if( $res && $need_generate_preview ){
                                $last_design = $this->get_last_template();
                                if( $last_design ){
                                    $approved[] = $last_design->id;
                                }
                            }
                        }
                        if( $res ){
                            $p_path             = NBDESIGNER_CUSTOMER_DIR . '/' . $p_folder;
                            $p_preview_path     = $p_path . '/preview';
                            $images             = Nbdesigner_IO::get_list_images( $p_preview_path, 1 );
                            ksort( $images );
                            $result['templates'][$pid] = array();
                            foreach( $images as $image ){
                                $result['templates'][$pid][] = Nbdesigner_IO::wp_convert_path_to_url( $image );
                            }
                        }
                    }
                }

                if( $need_generate_preview && count( $approved ) > 0 ){
                    spbwc_marketplace_generate_color_product_design( $approved );
                }
            }
        }else{
            $result['flag']     = 0;
            $result['message']  = esc_html__('Can not create design folder!', 'storelly-product-builder-for-woocommerce');
        }

        echo json_encode( $result );
        wp_die();
    }
    public function insert_solid_template( $data ){
        global $wpdb;
        $table_name = $wpdb->prefix . 'storelly_marketplace_designs';
        return $wpdb->insert( $table_name, $data );
    }
    public function update_solid_template( $id, $data ){
        global $wpdb;
        $table_name = $wpdb->prefix . 'storelly_marketplace_designs';
        return $wpdb->update("{$wpdb->prefix}storelly_marketplace_designs", $data, array( 'id' => $id) );
    }
    public function get_last_template(){
        global $wpdb;
        $table_name = $wpdb->prefix . 'storelly_marketplace_designs';
        $sql        = "SELECT * FROM {$wpdb->prefix}storelly_marketplace_designs ORDER BY created_date DESC";
        $data       = $wpdb->get_row( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name from $wpdb->prefix; no user input
        return $data;
    }
    public function calc_design_position( $design_width, $design_height, $area_width, $area_height ){
        $position = array(
            'left'      => 0,
            'top'       => 0,
            'width'     => $area_width,
            'height'    => $area_height,
            'ratio'     => 1
        );
        if( $area_width /  $area_height > $design_width / $design_height ){
            $ratio              = $area_height / $design_height;
            $new_width          = $design_width * $ratio;
            $position['left']   = ( $area_width - $new_width ) / 2;
            $position['width']  = $new_width;
            $position['ratio']  = $ratio;
        }else{
            $ratio              = $area_width / $design_width;
            $new_height         = $design_height * $ratio;
            $position['top']    = ( $area_height - $new_height ) / 2;
            $position['height'] = $new_height;
            $position['ratio']  = $ratio;
        }
        return $position;
    }
    private function create_side_preview( $setting, $content_designs ){
        $preview_width  = absint( apply_filters( 'nbdl_solid_design_preview_width', 500 ) );
        $scale          = $preview_width / 500;
        $folder         = substr( md5( uniqid() ), 0, 5 ).wp_rand( 1,100 ).time();
        $path           = NBDESIGNER_CUSTOMER_DIR . '/' . $folder;
        $preview_path   = $path . '/preview';

        if( wp_mkdir_p( $path ) ){
            if( wp_mkdir_p( $preview_path ) ){
                foreach( $content_designs as $key => $design ){
                    if( isset( $setting[ $key ] ) ){
                        $side       = $setting[ $key ];
                        $bg         = is_numeric( $side['img_src'] ) ? get_attached_file( $side['img_src'] ) : $side['img_src'];
                        $overlay    = is_numeric( $side['img_overlay'] ) ? get_attached_file( $side['img_overlay'] ) : $side['img_overlay'];
                        $bg_width   = $side["img_src_width"] * $scale;
                        $bg_height  = $side["img_src_height"] * $scale;
                        $ds_width   = $side["area_design_width"] * $scale;
                        $ds_height  = $side["area_design_height"] * $scale;

                        $image = imagecreatetruecolor( $bg_width, $bg_height );
                        imagesavealpha( $image, true );
                        $color = imagecolorallocatealpha( $image, 255, 255, 255, 127 );
                        imagefill( $image, 0, 0, $color );

                        list( $width, $height ) = getimagesize( $design );
                        $position   = $this->calc_design_position( $width, $height, $ds_width, $ds_height );
                        $ds_ext     = strtolower( pathinfo( $design, PATHINFO_EXTENSION ) );
                        if( $ds_ext == 'png' ){
                            $image_design = NBD_Image::crop_and_resize_png_image( $design, $position['width'],  $position['height'] );
                        }else{
                            $image_design = NBD_Image::crop_and_resize_jpg_image( $design, $position['width'],  $position['height'] );
                        }
                        $ds_left    = ( $side["area_design_left"] - $side["img_src_left"] ) * $scale + $position['left'];
                        $ds_top     = ( $side["area_design_top"] - $side["img_src_top"] ) * $scale + $position['top'];

                        if( $side["bg_type"] == 'image'){
                            $bg_ext     = strtolower( pathinfo( $bg, PATHINFO_EXTENSION ) );
                            if( $bg_ext == 'png' ){
                                $image_product = NBD_Image::nbdesigner_resize_imagepng($bg, $bg_width, $bg_height);
                            }else{
                                $image_product = NBD_Image::nbdesigner_resize_imagejpg($bg, $bg_width, $bg_height);
                            }
                            imagecopy( $image, $image_product, 0, 0, 0, 0, $bg_width, $bg_height );
                        }elseif( $side["bg_type"] == 'color' ){
                            $_color = hex_code_to_rgb( $side["bg_color_value"] );
                            $color  = imagecolorallocate( $image, $_color[0], $_color[1], $_color[2] );
                            imagefilledrectangle( $image, 0, 0, $bg_width, $bg_height, $color );
                        }

                        imagecopy( $image, $image_design, $ds_left, $ds_top, 0, 0, $position['width'], $position['height'] );

                        if( $side["show_overlay"] == '1' ){
                            $overlay_ext     = pathinfo( $overlay, PATHINFO_EXTENSION );
                            if( $overlay_ext == "png" ){
                                $image_overlay = NBD_Image::nbdesigner_resize_imagepng( $overlay, $bg_width, $bg_height );
                            }else if($over_ext == "jpg" || $over_ext == "jpeg"){
                                $image_overlay = NBD_Image::nbdesigner_resize_imagejpg( $overlay, $bg_width, $bg_height );
                            }
                            imagecopy( $image, $image_overlay, 0, 0, 0, 0, $bg_width, $bg_height );
                        }

                        imagepng( $image, $preview_path. '/frame_' . $key . '.png');
                    }
                }
                return $folder;
            }else{
                return false;
            }
        }else{
            return false;
        }
    }
    public function add_hook_change_image_id(){
        add_filter( 'woocommerce_product_get_image_id', array( $this, 'product_get_image_id' ), 10, 1 );
    }
    public function remove_hook_change_image_id(){
        remove_filter( 'woocommerce_product_get_image_id', array( $this, 'product_get_image_id' ), 10 );
    }
    public function product_get_image_id( $value ){
        if( $this->is_solid_design() ){
            $product_id     = get_the_ID();
            $design_code    = wc_clean( $_GET['design_id'] );
            $design_id      = nbd_decode_design_id( $design_code );
            $design         = nbd_get_design( $design_id, $product_id );
            if( is_array( $design ) && isset( $design['thumbnail'] ) ){
                return absint( $design['thumbnail'] );
            }
        }
        return $value;
    }
    public function nbdl_before_add_to_cart_button(){
        if( $this->is_solid_design() ){
            $design_code    = wc_clean( $_GET['design_id'] );
            $design_id      = nbd_decode_design_id( $design_code );
            ?>
            <input type="hidden" name="nbd_solid_design_id" value="<?php echo esc_attr( $design_id ); ?>"/>
            <?php
        }
    }
    public function is_solid_design(){
        if( is_singular( 'product' ) ){
            if( isset( $_GET['design_id'] ) ){
                $design_code    = wc_clean( $_GET['design_id'] );
                $design_id      = nbd_decode_design_id( $design_code );
                if( $design_id ) return true;
            }
        }
        return false;
    }
    public function show_design_author(){
        $html = '';
        if( $this->is_solid_design() ){
            $product_id     = get_the_ID();
            $design_code    = wc_clean( $_GET['design_id'] );
            $design_id      = nbd_decode_design_id( $design_code );
            $design         = nbd_get_design( $design_id, $product_id );
            if( is_array( $design ) && isset( $design['user_id'] ) && absint( $design['user_id'] ) != 0 ){
                $designer   = new SPBWC_Designer( $design['user_id'] );
                $infos      = $designer->to_array();
                $name       = isset( $infos['artist_name'] ) && $infos['artist_name'] != '' ? $infos['artist_name'] : $infos['first_name'] . ' ' . $infos['last_name'];
                $store_url  = add_query_arg( array('id' => $design['user_id']), getUrlPageNBD( 'designer' ) );
                $html       = '<h2 class="nbdl-author">' . esc_html__( "Designed by", 'storelly-product-builder-for-woocommerce' ) . ' <a href="' . $store_url . '" target="_blank">' . $name . '</a></h2>';
            }
        }
        echo wp_kses_post( $html );
    }
    public function add_cart_item_data( $cart_item_data ){
        $post_data = $_POST;
        if( isset( $post_data['nbd_solid_design_id'] ) ){
            $design = nbd_get_design( absint( $post_data['nbd_solid_design_id'] ) );
            if( is_array( $design ) && isset( $design['folder'] ) ){
                $cart_item_data['nbd_design_id'] = $design['folder'];
            }
        }
        return $cart_item_data;
    }
    public function nbo_artwork_action( $action, $field ){
        if( $this->is_solid_design() ){
            $action_val = 'n';
            foreach( $field['general']['attributes']["options"] as $k => $option ){
                if( $option['action'] == $action_val ){
                    $action = $k;
                }
            }
        }
        return $action;
    }
    public function hide_design_btn( $check ){
        if( $this->is_solid_design() ) return false;
        return $check;
    }
    public function nbo_field_class( $class, $field ){
        if( $this->is_solid_design() ){
            if( isset($field['nbe_type']) && $field['nbe_type'] == 'actions' && $field['general']['enabled'] == 'y' ){
                $class .= ' nbo-hidden';
            }
        }
        return $class;
    }
    public function cart_item_permalink( $permalink, $cart_item, $cart_item_key ){
        if( $permalink != '' && isset( $cart_item['nbd_design_id'] ) ){
            $design = nbd_get_design_by_folder( $cart_item['nbd_design_id'] );
            if( is_array( $design ) && isset( $design['type'] ) && $design['type'] == 'solid' ) {
                $permalink = add_query_arg(array(
                    'design_id' => nbd_encode_design_id( $design['id'] )
                ), $permalink);
            }
        }
        return $permalink;
    }
    public function cart_item_thumbnail( $image, $cart_item, $cart_item_key ){
        if( isset( $cart_item['nbd_design_id'] ) ){
            $design = nbd_get_design_by_folder( $cart_item['nbd_design_id'] );
            if( is_array( $design ) && isset( $design['type'] ) && $design['type'] == 'solid' && isset( $design['thumbnail'] ) ) {
                $thumbnail  = absint( $design['thumbnail'] );
                $image      = wp_get_attachment_image( $thumbnail, 'woocommerce_thumbnail', false );
            }
        }
        return $image;
    }
    public function order_item_permalink( $permalink, $item, $order ){
        if( $permalink != '' && isset( $item["item_meta"]['_spbwc_marketplace_design_id'] ) ){
            $design = nbd_get_design_by_folder( $item["item_meta"]['_spbwc_marketplace_design_id'] );
            if( is_array( $design ) && isset( $design['type'] ) && $design['type'] == 'solid' ) {
                $permalink = add_query_arg(array(
                    'design_id' => nbd_encode_design_id( $design['id'] )
                ), $permalink);
            }
        }
        return $permalink;
    }
    public function admin_order_item_thumbnail( $image = "", $item_id = "", $item = "" ){
        $order = nbd_get_order_object();
        $item_meta = function_exists( 'wc_get_order_item_meta' ) ? wc_get_order_item_meta( $item_id, '', FALSE ) : $order->get_item_meta( $item_id ); 
        if( isset( $item_meta['_spbwc_marketplace_design_id'] ) ){
            $design = nbd_get_design_by_folder( $item_meta['_spbwc_marketplace_design_id'][0] );
            if( is_array( $design ) && isset( $design['type'] ) && $design['type'] == 'solid' && isset( $design['thumbnail'] ) ) {
                $thumbnail  = absint( $design['thumbnail'] );
                $image      = wp_get_attachment_image( $thumbnail, 'woocommerce_thumbnail', false );
            }
        }
        return $image;
    }
    public function print_download_design_button( $item_id, $item, $product ){
        global $post;
        if( ! $item->is_type('line_item') ) return;
        $design_id = wc_get_order_item_meta( $item_id, '_spbwc_marketplace_design_id', true );
        if( $design_id ){
            $design = nbd_get_design_by_folder( $design_id );
            if( is_array( $design ) && isset( $design['type'] ) && $design['type'] == 'solid' && isset( $design['resource'] ) ) {
                $link_download_design = NBDESIGNER_CUSTOMER_URL . '/' . $design['resource'] . '/design.zip';
                $html = '<a class="button" download href="'. esc_url( $link_download_design ) .'">'. esc_html__('Download design resource', 'storelly-product-builder-for-woocommerce') .'</a>';
                echo wp_kses( $html, array( 'a' => array( 'class' => array(), 'download' => array(), 'href' => array() ) ) );
            }
        }
    }
    public function override_product_image_by_design_preview( $options, $product_id ){
        if( $this->is_solid_design() ){
            $design_code    = wc_clean( $_GET['design_id'] );
            $design_id      = nbd_decode_design_id( $design_code );

            foreach ($options['fields'] as $key => $field){
                if( isset( $field['nbd_type'] ) && $field['nbd_type'] == 'color' && $field['appearance']['change_image_product'] == 'y' ){
                    if( isset( $field['general']['attributes']['bg_type'] ) && $field['general']['attributes']['bg_type'] == 'c' ){
                        if( isset( $field['general']['attributes']['options'] ) && count( $field['general']['attributes']['options'] ) > 0 ){
                            foreach ($field['general']['attributes']['options'] as $op_index => $option ){
                                $kolor          = str_replace( '#', '', $option['bg_color'] );
                                $preview_path   = NBDESIGNER_DATA_DIR . '/previews/' . $product_id . '/' . $design_id . '/0_' . $kolor . '.png';
                                if( file_exists( $preview_path ) ){
                                    $image_link = NBDESIGNER_DATA_URL . '/previews/' . $product_id . '/' . $design_id . '/0_' . $kolor . '.png';
                                    list( $width, $height ) = getimagesize( $preview_path );
                                    $options['fields'][$key]['general']['attributes']['options'][$op_index] = array_replace_recursive($options['fields'][$key]['general']['attributes']['options'][$op_index], array(
                                        'imagep'        => 'y',
                                        'image_link'    => $image_link,
                                        'image_title'   => esc_html__( 'Design', 'storelly-product-builder-for-woocommerce' ),
                                        'image_alt'     => esc_html__( 'Design', 'storelly-product-builder-for-woocommerce' ),
                                        'image_srcset'  => $image_link,
                                        'image_sizes'   => sprintf( '(max-width: %1$dpx) 100vw, %1$dpx', $width ),
                                        'image_caption' => '',
                                        'full_src'      => $image_link,
                                        'full_src_w'    => $width,
                                        'full_src_h'    => $height
                                    ));
                                }
                            }
                        }
                    }
                }
            }
        }
        return $options;
    }
    public function re_assign_templates( $user_id, $reassign ){
        global $wpdb;

        $args   = array(
            'role__in'   => array( 'administrator', 'shop_manager' ),
            'number'     => 10,
            'offset'     => 0,
            'orderby'    => 'registered',
            'order'      => 'ASC',
            'status'     => 'all',
            'featured'   => '',
            'meta_query' => array(),
        );
        
        $user_query     = new WP_User_Query( $args );
        $users          = $user_query->get_results();

        if( $users ){
            $new_user_id = $users[0]->ID;
            $wpdb->update( 
                $wpdb->prefix . 'storelly_marketplace_designs',
                array(
                    'user_id'   => $new_user_id
                ),
                array(
                    'user_id'   => $user_id
                )
            );
        }else{
            $wpdb->update( 
                $wpdb->prefix . 'storelly_marketplace_designs',
                array(
                    'publish'   => 0
                ),
                array(
                    'user_id'   => $user_id
                )
            );
        }
    }
    public function spbwc_designers_func( $atts ){
        $atts = shortcode_atts( array(
            'number'    => 4
        ), $atts );

        $designers  = array();
        $_designers = spbwc_marketplace_get_designers( array(
            'number'    => $atts['number'],
            'status'    => 'approved',
            'orderby'   => 'ID',
            'order'     => 'DESC',
            'featured'  => 'yes'
        ), true );

        foreach( $_designers['designers'] as $designer ){
            $designers[] = $designer->to_array();
        }

        ob_start();
        nbdesigner_get_template('launcher/featured-designers-shortcode.php', array(
            'designers' => $designers
        ));
        return ob_get_clean();
    }
    public function i18n(){
        return array(
            'actions'                       => esc_html__( 'Actions', 'storelly-product-builder-for-woocommerce' ),
            'add_note'                      => esc_html__( 'Add Note', 'storelly-product-builder-for-woocommerce' ),
            'all'                           => esc_html__( 'All', 'storelly-product-builder-for-woocommerce' ),
            'all_user'                      => esc_html__( 'All user', 'storelly-product-builder-for-woocommerce' ),
            'amount'                        => esc_html__( 'Amount', 'storelly-product-builder-for-woocommerce' ),
            'approved'                      => esc_html__( 'Approved', 'storelly-product-builder-for-woocommerce' ),
            'approve_request'               => esc_html__( 'Approve Request', 'storelly-product-builder-for-woocommerce' ),
            'auto_publish_new_design'       => esc_html__( 'Auto publish new design', 'storelly-product-builder-for-woocommerce' ),
            'artist_name'                   => esc_html__( 'Artist name', 'storelly-product-builder-for-woocommerce' ),
            'at_a_glance'                   => esc_html__( 'At a Glance', 'storelly-product-builder-for-woocommerce' ),
            'apply'                         => esc_html__( 'Apply', 'storelly-product-builder-for-woocommerce' ),
            'artist_name'                   => esc_html__( 'Artist name', 'storelly-product-builder-for-woocommerce' ),
            'address'                       => esc_html__( 'Address', 'storelly-product-builder-for-woocommerce' ),
            'awaiting_approval'             => esc_html__( 'awaiting approval', 'storelly-product-builder-for-woocommerce' ),
            'bulk_actions'                  => esc_html__( 'Bulk actions', 'storelly-product-builder-for-woocommerce' ),
            'cancel'                        => esc_html__( 'Cancel', 'storelly-product-builder-for-woocommerce' ),
            'cancel_request'                => esc_html__( 'Cancel request', 'storelly-product-builder-for-woocommerce' ),
            'cancelled'                     => esc_html__( 'Cancelled', 'storelly-product-builder-for-woocommerce' ),
            'combine'                       => esc_html__( 'Combine', 'storelly-product-builder-for-woocommerce' ),
            'combine_text'                  => esc_html__( '% + ', 'storelly-product-builder-for-woocommerce' ),
            'confirm_delete_withdraw'       => esc_html__( 'Do you want to delete this withdraw?', 'storelly-product-builder-for-woocommerce' ),
            'confirm_delete_design'         => esc_html__( 'Do you want to delete this design?', 'storelly-product-builder-for-woocommerce' ),
            'change_designer_avatar'        => esc_html__( 'Change Designer Avatar', 'storelly-product-builder-for-woocommerce' ),
            'change_banner'                 => esc_html__( 'Change banner', 'storelly-product-builder-for-woocommerce' ),
            'created_this_month'            => esc_html__( 'created this month', 'storelly-product-builder-for-woocommerce' ),
            'created_this_period'           => esc_html__( 'created this period', 'storelly-product-builder-for-woocommerce' ),
            'current_balance'               => esc_html__( 'Current balance', 'storelly-product-builder-for-woocommerce' ),
            'custom'                        => esc_html__( 'Custom', 'storelly-product-builder-for-woocommerce' ),
            'dashboard'                     => esc_html__( 'Dashboard', 'storelly-product-builder-for-woocommerce' ),
            'date'                          => esc_html__( 'Date', 'storelly-product-builder-for-woocommerce' ),
            'delete'                        => esc_html__( 'Delete', 'storelly-product-builder-for-woocommerce' ),
            'designer'                      => esc_html__( 'Designer', 'storelly-product-builder-for-woocommerce' ),
            'designers'                     => esc_html__( 'Designers', 'storelly-product-builder-for-woocommerce' ),
            'designs'                       => esc_html__( 'Designs', 'storelly-product-builder-for-woocommerce' ),
            'designer_commission_type'      => esc_html__( 'Designer Commission Type', 'storelly-product-builder-for-woocommerce' ),
            'designer_commission'           => esc_html__( 'Designer Commission', 'storelly-product-builder-for-woocommerce' ),
            'designer_launcher'             => esc_html__( 'Design Launcher', 'storelly-product-builder-for-woocommerce' ),
            'designs_sold'                  => esc_html__( 'Designs sold', 'storelly-product-builder-for-woocommerce' ),
            'disabled'                      => esc_html__( 'Disabled', 'storelly-product-builder-for-woocommerce' ),
            'download_resource'             => esc_html__( 'Download resource', 'storelly-product-builder-for-woocommerce' ),
            'email'                         => esc_html__( 'E-mail', 'storelly-product-builder-for-woocommerce' ),
            'edit'                          => esc_html__( 'Edit', 'storelly-product-builder-for-woocommerce' ),
            'editable'                      => esc_html__( 'Editable', 'storelly-product-builder-for-woocommerce' ),
            'enabled'                       => esc_html__( 'Enabled', 'storelly-product-builder-for-woocommerce' ),
            'enable_selling_designs'        => esc_html__( 'Enable Selling Designs', 'storelly-product-builder-for-woocommerce' ),
            'error_title'                   => esc_html__( 'Error!', 'storelly-product-builder-for-woocommerce' ),
            'flat'                          => esc_html__( 'Flat', 'storelly-product-builder-for-woocommerce' ),
            'featured'                      => esc_html__( 'Featured', 'storelly-product-builder-for-woocommerce' ),
            'filter'                        => esc_html__( 'Filter', 'storelly-product-builder-for-woocommerce' ),
            'flickr'                        => esc_html__( 'Flickr', 'storelly-product-builder-for-woocommerce' ),
            'facebook'                      => esc_html__( 'Facebook', 'storelly-product-builder-for-woocommerce' ),
            'filter_by_user'                => esc_html__( 'Filter by registered customer', 'storelly-product-builder-for-woocommerce' ),
            'filter_by_product'             => esc_html__( 'Filter by product', 'storelly-product-builder-for-woocommerce' ),
            'from'                          => esc_html__( 'From', 'storelly-product-builder-for-woocommerce' ),
            'item'                          => esc_html__( 'item', 'storelly-product-builder-for-woocommerce' ),
            'items'                         => esc_html__( 'items', 'storelly-product-builder-for-woocommerce' ),
            'instagram'                     => esc_html__( 'Instagram', 'storelly-product-builder-for-woocommerce' ),
            'last_month'                    => esc_html__( 'Last month', 'storelly-product-builder-for-woocommerce' ),
            'linkedin'                      => esc_html__( 'LinkedIn', 'storelly-product-builder-for-woocommerce' ),
            'make_mesigner_featured'        => esc_html__( 'Make Designer Featured', 'storelly-product-builder-for-woocommerce' ),
            'manage_designers'              => esc_html__( 'Manage Designers', 'storelly-product-builder-for-woocommerce' ),
            'message'                       => esc_html__( 'Message', 'storelly-product-builder-for-woocommerce' ),
            'no_name'                       => esc_html__( '( no name )', 'storelly-product-builder-for-woocommerce' ),
            'note'                          => esc_html__( 'Note', 'storelly-product-builder-for-woocommerce' ),
            'no_designer_found'             => esc_html__( 'No designer found.', 'storelly-product-builder-for-woocommerce' ),
            'no_transaction_found'          => esc_html__( 'No transaction found.', 'storelly-product-builder-for-woocommerce' ),
            'no_design_found'               => esc_html__( 'No design found.', 'storelly-product-builder-for-woocommerce' ),
            'of'                            => esc_html__( 'of', 'storelly-product-builder-for-woocommerce' ),
            'others'                        => esc_html__( 'Others', 'storelly-product-builder-for-woocommerce' ),
            'overview'                      => esc_html__( 'Overview', 'storelly-product-builder-for-woocommerce' ),
            'pending'                       => esc_html__( 'Pending', 'storelly-product-builder-for-woocommerce' ),
            'pending_request'               => esc_html__( 'Pending request', 'storelly-product-builder-for-woocommerce' ),
            'percentage'                    => esc_html__( 'Percentage', 'storelly-product-builder-for-woocommerce' ),
            'payment'                       => esc_html__( 'Payment', 'storelly-product-builder-for-woocommerce' ),
            'payment_info'                  => esc_html__( 'Payment information', 'storelly-product-builder-for-woocommerce' ),
            'paypal_email'                  => esc_html__( 'PayPal Email', 'storelly-product-builder-for-woocommerce' ),
            'phone_number'                  => esc_html__( 'Phone Number', 'storelly-product-builder-for-woocommerce' ),
            'publish'                       => esc_html__( 'Publish', 'storelly-product-builder-for-woocommerce' ),
            'publish_designs'               => esc_html__( 'Publish designs', 'storelly-product-builder-for-woocommerce' ),
            'preview'                       => esc_html__( 'Preview', 'storelly-product-builder-for-woocommerce' ),
            'private'                       => esc_html__( 'Private', 'storelly-product-builder-for-woocommerce' ),
            'product'                       => esc_html__( 'Product', 'storelly-product-builder-for-woocommerce' ),
            'registered'                    => esc_html__( 'Registered', 'storelly-product-builder-for-woocommerce' ),
            'revenue'                       => esc_html__( 'Revenue', 'storelly-product-builder-for-woocommerce' ),
            'registered_since'              => esc_html__( 'Registered since', 'storelly-product-builder-for-woocommerce' ),
            're_generate_preview'           => esc_html__( 'Re-generate design preview', 'storelly-product-builder-for-woocommerce' ),
            'save_changes'                  => esc_html__( 'Save Changes', 'storelly-product-builder-for-woocommerce' ),
            'select_image'                  => esc_html__( 'Select Image', 'storelly-product-builder-for-woocommerce' ),
            'select_crop_image'             => esc_html__( 'Select & Crop Image', 'storelly-product-builder-for-woocommerce' ),
            'select_bulk_action'            => esc_html__( 'Select bulk action', 'storelly-product-builder-for-woocommerce' ),
            'send_email'                    => esc_html__( 'Send Email', 'storelly-product-builder-for-woocommerce' ),
            'signup_this_month'             => esc_html__( 'signup this month', 'storelly-product-builder-for-woocommerce' ),
            'signup_this_period'            => esc_html__( 'signup this period', 'storelly-product-builder-for-woocommerce' ),
            'show'                          => esc_html__( 'Show', 'storelly-product-builder-for-woocommerce' ),
            'social_information'            => esc_html__( 'Social information', 'storelly-product-builder-for-woocommerce' ),
            'sold_this_month'               => esc_html__( 'sold this month', 'storelly-product-builder-for-woocommerce' ),
            'sold_this_period'              => esc_html__( 'sold this period', 'storelly-product-builder-for-woocommerce' ),
            'solid'                         => esc_html__( 'Solid', 'storelly-product-builder-for-woocommerce' ),
            'status'                        => esc_html__( 'Status', 'storelly-product-builder-for-woocommerce' ),
            'success_title'                 => esc_html__( 'Success!', 'storelly-product-builder-for-woocommerce' ),
            'subject'                       => esc_html__( 'Subject', 'storelly-product-builder-for-woocommerce' ),
            'this_month'                    => esc_html__( 'This month', 'storelly-product-builder-for-woocommerce' ),
            'to'                            => esc_html__( 'To', 'storelly-product-builder-for-woocommerce' ),
            'toggle_panel'                  => esc_html__( 'Toggle panel', 'storelly-product-builder-for-woocommerce' ),
            'total_designs'                 => esc_html__( 'Total designs', 'storelly-product-builder-for-woocommerce' ),
            'total_earning'                 => esc_html__( 'Total earning', 'storelly-product-builder-for-woocommerce' ),
            'twitter'                       => esc_html__( 'Twitter', 'storelly-product-builder-for-woocommerce' ),
            'upload_image'                  => esc_html__( 'Upload Image', 'storelly-product-builder-for-woocommerce' ),
            'update_note'                   => esc_html__( 'Update Note', 'storelly-product-builder-for-woocommerce' ),
            'view'                          => esc_html__( 'View', 'storelly-product-builder-for-woocommerce' ),
            'view_gallery'                  => esc_html__( 'View gallery', 'storelly-product-builder-for-woocommerce' ),
            'withdraw'                      => esc_html__( 'Withdraw', 'storelly-product-builder-for-woocommerce' ),
            'withdrawals'                   => esc_html__( 'Withdrawals', 'storelly-product-builder-for-woocommerce' ),
            'year'                          => esc_html__( 'Year', 'storelly-product-builder-for-woocommerce' ),
            'youtube'                       => esc_html__( 'Youtube', 'storelly-product-builder-for-woocommerce' )
        );
    }
}
function SPBWC_Marketplace(){
    return SPBWC_Marketplace::get_instance();
}
SPBWC_Marketplace()->init();