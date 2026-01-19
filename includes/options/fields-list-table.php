<?php if (!defined('ABSPATH')) exit;
if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}
class SPBWC_Storelly_Options_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct(array(
            'singular'  => esc_html__('Printing option', 'storelly-product-builder-for-woocommerce'),
            'plural'    => esc_html__('Printing options', 'storelly-product-builder-for-woocommerce'),
            'ajax'      => false
        ));
    }
    public function prepare_items() {
        $columns    = $this->get_columns();
        $hidden     = array();
        $sortable   = $this->spbwc_get_sortable_columns();
        $this->_column_headers = array($columns, $hidden, $sortable);
        /** Process bulk action */
        $this->spbwc_process_bulk_action();
        $per_page       = $this->get_items_per_page('options_per_page', 10);
        $current_page   = $this->get_pagenum();
        $total_items    = self::spbwc_record_count();
        $this->set_pagination_args(array(
            'total_items'   => $total_items,
            'per_page'      => $per_page
        ));
        $this->items = self::spbwc_get_options($per_page, $current_page);
    }
    public function get_columns() {
        $columns = array(
            'cb'            => '<input type="checkbox" />',
            'title'         => esc_html__('Title', 'storelly-product-builder-for-woocommerce'),
            'product_ids'   => esc_html__('Products', 'storelly-product-builder-for-woocommerce'),
            'date'          => esc_html__('Date', 'storelly-product-builder-for-woocommerce')
        );
        return $columns;
    }
    public function spbwc_get_sortable_columns() {
        $sortable_columns = array(
            'priority' => array('priority', true)
        );
        return $sortable_columns;
    }
    public static function spbwc_record_count() {
        global $wpdb; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Global variable $wpdb.
        $table_name = $wpdb->prefix . 'storelly_product_builder_options';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Admin-only count query, caching not needed.
        $result = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table_name} WHERE 1 = %d", 1 ) );
        return $result;
    }
    public function spbwc_get_options($per_page = 10, $page_number = 1) {
        global $wpdb; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Global variable $wpdb.
        $table_name = $wpdb->prefix . 'storelly_product_builder_options';
        $number_page = ($page_number - 1) * $per_page;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Admin-only paginated query, caching not applicable.
        $result = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table_name} ORDER BY modified DESC LIMIT %d OFFSET %d", $per_page, $number_page), 'ARRAY_A');
        return $result;
    } 
    public function spbwc_process_bulk_action() {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        // Reason: This function handles bulk actions and single item actions for a WP_List_Table.
        // The nonce is checked conditionally based on the action being performed.
        // Input is sanitized and validated within the conditions.
        if (!current_user_can('manage_options')) {
            return;
        }

        $action = $this->current_action();

        if (!$action || $action === -1) {
            return;
        }

        $nonce = isset($_REQUEST['_wpnonce']) ? sanitize_text_field(wp_unslash($_REQUEST['_wpnonce'])) : '';

        // Verify nonce for the specific action.
        $nonce_verified = false;
        if (in_array($action, array('delete', 'copy'), true)) {
            if (wp_verify_nonce($nonce, 'spbwc_options_nonce')) {
                $nonce_verified = true;
            }
        } elseif (in_array($action, array('bulk-delete', 'bulk-publish', 'bulk-unpublish'), true)) {
            if (wp_verify_nonce($nonce, 'bulk-' . $this->_args['plural'])) {
                $nonce_verified = true;
            }
        }

        if (!$nonce_verified) {
            wp_die(esc_html__('Security check failed. Please try again.', 'storelly-product-builder-for-woocommerce'));
        }

        // Process single actions.
        if ('delete' === $action || 'copy' === $action) {
            if (!isset($_GET['id'])) {
                wp_die(esc_html__('Invalid request. Missing item ID.', 'storelly-product-builder-for-woocommerce'));
            }
            $id = absint($_GET['id']);
            if ('delete' === $action) {
                $this->spbwc_delete_option($id);
            }
            if ('copy' === $action) {
                $this->spbwc_copy_options($id);
            }
            wp_safe_redirect(remove_query_arg(array('action', 'id', '_wpnonce')));
            exit;
        }

        // Process bulk actions.
        if (in_array($action, array('bulk-delete', 'bulk-publish', 'bulk-unpublish'), true)) {
            $bulk_ids = isset($_POST['option_id']) ? array_map('absint', (array) wp_unslash($_POST['option_id'])) : array();

            if (empty($bulk_ids)) {
                wp_safe_redirect(remove_query_arg(array('action', '_wpnonce')));
                exit;
            }

            foreach ($bulk_ids as $id) {
                switch ($action) {
                    case 'bulk-publish':
                        $this->spbwc_publish_option($id);
                        break;
                    case 'bulk-unpublish':
                        $this->spbwc_unpublish_option($id);
                        break;
                    case 'bulk-delete':
                        $this->spbwc_delete_option($id);
                        break;
                }
            }
            wp_safe_redirect(remove_query_arg(array('action', '_wpnonce')));
            exit;
        }
        // phpcs:enable
    }
   public function spbwc_delete_option($id) { // Added prefix
         global $wpdb; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Global variable $wpdb.
    if ( ! current_user_can( 'manage_options' ) ) return; 
         $table_name = $wpdb->prefix . 'storelly_product_builder_options';
         // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin-only delete operation.
         $result = $wpdb->delete($table_name, array('id' => absint($id)), array('%d'));
         if ($result) $this->spbwc_clear_transients();
    }
    public function spbwc_unpublish_option($id) { // Added prefix
        global $wpdb; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Global variable $wpdb.
        if ( ! current_user_can( 'manage_options' ) ) return; 
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin-only update operation.
        $result = $wpdb->update($wpdb->prefix . 'storelly_product_builder_options', array(
        'published' => 0
        ), array('id' => absint($id)), array('%d'), array('%d'));
        if ($result) $this->spbwc_clear_transients();
    }
    public function spbwc_publish_option($id) { // Added prefix
        global $wpdb; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Global variable $wpdb.
        if ( ! current_user_can( 'manage_options' ) ) return;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin-only update operation.
        $result = $wpdb->update($wpdb->prefix . 'storelly_product_builder_options', array(
           'published' => 1
        ), array('id' => absint($id)), array('%d'), array('%d'));
        if ($result) $this->spbwc_clear_transients();
    }
    public function spbwc_copy_options($id) { // Added prefix
        global $wpdb; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Global variable $wpdb. 
        if ( ! current_user_can( 'manage_options' ) ) return false;
        $table_name = $wpdb->prefix . 'storelly_product_builder_options';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Admin-only copy query.
        $result = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table_name} WHERE `id` = %d", absint($id)), 'ARRAY_A');
        
        if (count($result)) {
            $res            = $result[0];
            $modified_date  = new DateTime();
            $current_user_id = get_current_user_id();
            
            $arr            = array(
                'title'         => $res['title'] . ' (' . esc_html__('Copy', 'storelly-product-builder-for-woocommerce') . ')',
                'product_ids'   => $res['product_ids'],
                'published'     => 0,
                'modified'      => $modified_date->format('Y-m-d H:i:s'),
                'modified_by'   => $current_user_id,
                'fields'        => $res['fields'],
                'builder'       => $res['builder'],
                'created'       => $modified_date->format('Y-m-d H:i:s'),
                'created_by'    => $current_user_id
            ); 
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Admin-only insert operation.
            $in_res = $wpdb->insert("{$wpdb->prefix}storelly_product_builder_options", $arr, array('%s', '%s', '%d', '%s', '%d', '%s', '%s', '%d'));
            if ($in_res) {
                $this->spbwc_clear_transients();
                return $in_res;
            }
        }
        return false;
    }
    private function spbwc_clear_transients() {
        global $wpdb; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Global variable $wpdb.
        // Delete plugin transients safely.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Clearing transients requires direct query.
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                '_transient_nbo_product_%',
                '_transient_timeout_nbo_product_%'
            )
        );
    }
    public function get_bulk_actions() {
        $actions = array(
            'bulk-delete'       => esc_html__('Delete', 'storelly-product-builder-for-woocommerce'),
            'bulk-publish'      => esc_html__('Publish', 'storelly-product-builder-for-woocommerce'),
            'bulk-unpublish'    => esc_html__('Unpublish', 'storelly-product-builder-for-woocommerce'),
        );
        return $actions;
    }
    public function no_items() {
        esc_html_e('No options avaliable.', 'storelly-product-builder-for-woocommerce');
    }
    function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="option_id[]" value="%s" />', $item['id']
        );
    }
    function column_title($item) {
        $title = $item['title'];
        $page  = isset($_REQUEST['page']) ? sanitize_text_field(wp_unslash($_REQUEST['page'])) : '';

        // Create nonces for each action.
        $edit_nonce = wp_create_nonce('spbwc_options_nonce');
        $copy_nonce = wp_create_nonce('spbwc_options_nonce');
        $delete_nonce = wp_create_nonce('spbwc_options_nonce');

        $paged = $this->get_pagenum();

        $actions = array(
            'edit'   => sprintf(
                '<a href="?page=%s&action=%s&id=%s&paged=%s&_wpnonce=%s">%s</a>',
                esc_attr($page),
                'edit',
                absint($item['id']),
                esc_attr($paged),
                esc_attr($edit_nonce),
                esc_html__('Edit', 'storelly-product-builder-for-woocommerce')
            ),
            'copy'   => sprintf(
                '<a href="?page=%s&action=%s&id=%s&paged=%s&_wpnonce=%s">%s</a>',
                esc_attr($page),
                'copy',
                absint($item['id']),
                esc_attr($paged),
                esc_attr($copy_nonce),
                esc_html__('Copy', 'storelly-product-builder-for-woocommerce')
            ),
            'delete' => sprintf(
                '<a href="?page=%s&action=%s&id=%s&paged=%s&_wpnonce=%s" onclick="return confirm(\'%s\')">%s</a>',
                esc_attr($page),
                'delete',
                absint($item['id']),
                esc_attr($paged),
                esc_attr($delete_nonce),
                esc_js(esc_html__('Are you sure you want to delete this item?', 'storelly-product-builder-for-woocommerce')),
                esc_html__('Delete', 'storelly-product-builder-for-woocommerce')
            ),
        );

        return $title . $this->row_actions($actions);
    }
    
    function column_published($item) {
        return $item['published'] == 1 ? esc_html__('Publish', 'storelly-product-builder-for-woocommerce') : esc_html__('Unpublish', 'storelly-product-builder-for-woocommerce');
    }
    function column_date($item) {
        return (!empty($item['modified']) && $item['modified'] != '0000-00-00 00:00:00') ? $item['modified'] : $item['created'];
    }
    function column_product_ids($item) {
        $return = esc_html__('None', 'storelly-product-builder-for-woocommerce');
        if (!$item['product_ids']) return $return;
        $products = unserialize($item['product_ids']);
            if (count($products)) {
                $links = array();
                foreach ($products as $pid) {
                    $title      = get_the_title($pid);
                    $links[]    = '<a title="' . esc_attr($title) . '" href="' . esc_url(admin_url('post.php?action=edit&post=' . $pid)) . '" rel="tag">' . $title . '</a>';
                }
                $return = implode(' , ', $links);
            }
        return $return;
    }
    function column_default($item, $column_name) {
        return $item[$column_name];
    }
    function extra_tablenav($which) {
    }
}
