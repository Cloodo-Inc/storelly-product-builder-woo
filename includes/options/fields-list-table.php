<?php if (!defined('ABSPATH')) exit;
if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}
class SPBWC_Storelly_Options_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct(array(
            'singular'  => esc_html__('Printing option', 'pc-product-builder'),
            'plural'    => esc_html__('Printing options', 'pc-product-builder'),
            'ajax'      => false
        ));
    }
    public function spbwc_prepare_items() {
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
            'title'         => esc_html__('Title', 'pc-product-builder'),
            'product_ids'   => esc_html__('Products', 'pc-product-builder'),
            'date'          => esc_html__('Date', 'pc-product-builder')
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
        global $wpdb;
        $table_name = $wpdb->prefix . 'storelly_product_builder_options';
        $result = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name"));
        return $result;
    }
    public function spbwc_get_options($per_page = 10, $page_number = 1) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'storelly_product_builder_options';
        $number_page = ($page_number - 1) * $per_page;
        $result = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_name ORDER BY modified DESC LIMIT %d OFFSET %d", $per_page, $number_page), 'ARRAY_A');
        return $result;
    } 
    public function spbwc_process_bulk_action() {
        if (!current_user_can('manage_options')) {
                  return;
        }
        $current_action = $this->spbwc_current_action();
        $nonce = isset($_REQUEST['_wpnonce']) ? sanitize_text_field(wp_unslash($_REQUEST['_wpnonce'])) : '';
        
        $is_bulk_action = in_array($current_action, array('bulk-publish', 'bulk-unpublish', 'bulk-delete'));
        if ($current_action === 'delete' || $current_action === 'copy') {
            if (!wp_verify_nonce($nonce, 'spbwc_options_nonce')) { 
                        wp_die(esc_html__('Security error.', 'pc-product-builder'));
            }
        } else if ($is_bulk_action) {
            if (!wp_verify_nonce($nonce, 'bulk-' . $this->get_plural())) {
                wp_die(esc_html__('Security error.', 'pc-product-builder'));
            }
        }
        if ('delete' === $current_action) {
                  $this->spbwc_delete_option(absint($_GET['id'])); 
                  wp_redirect(esc_url_raw(add_query_arg(array('paged' => $this->get_pagenum()), admin_url('admin.php?page=spbwc-product-builder-options'))));
                  exit;
        }
        if ('copy' === $current_action) {
                  $this->spbwc_copy_options(absint($_GET['id'])); 
                  wp_redirect(esc_url_raw(admin_url('admin.php?page=spbwc-product-builder-options')));
                  exit;
        }
        if ($is_bulk_action) {
          if (isset($_POST['bulk-delete'])) {
           $bulk_ids = array_map('absint', (array) wp_unslash($_POST['bulk-delete'])); 
           foreach ($bulk_ids as $id) {
            if ($current_action == 'bulk-publish') {
                    $this->spbwc_publish_option($id); 
            } elseif ($current_action == 'bulk-unpublish') {
                    $this->spbwc_unpublish_option($id); 
            } elseif ($current_action == 'bulk-delete') {
                    $this->spbwc_delete_option($id); 
            }
           }
          }
          wp_redirect(esc_url_raw(add_query_arg('', '')));
          exit;
        }
    }
   public function spbwc_delete_option($id) { // Added prefix
         global $wpdb;
    if ( ! current_user_can( 'manage_options' ) ) return; 
         $table_name = $wpdb->prefix . 'storelly_product_builder_options';
         $result = $wpdb->delete($table_name, array('id' => absint($id)), array('%d'));
         if ($result) $this->spbwc_clear_transients();
    }
    public function spbwc_unpublish_option($id) { // Added prefix
        global $wpdb;
        if ( ! current_user_can( 'manage_options' ) ) return; 
        $result = $wpdb->update($wpdb->prefix . 'storelly_product_builder_options', array(
        'published' => 0
        ), array('id' => absint($id)), array('%d'), array('%d'));
        if ($result) $this->spbwc_clear_transients();
    }
    public function spbwc_publish_option($id) { // Added prefix
        global $wpdb;
        if ( ! current_user_can( 'manage_options' ) ) return;
        $result = $wpdb->update($wpdb->prefix . 'storelly_product_builder_options', array(
           'published' => 1
        ), array('id' => absint($id)), array('%d'), array('%d'));
        if ($result) $this->spbwc_clear_transients();
    }
    public function spbwc_copy_options($id) { // Added prefix
        global $wpdb; 
        if ( ! current_user_can( 'manage_options' ) ) return false;
        $table_name = $wpdb->prefix . 'storelly_product_builder_options';
        $result = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table_name} WHERE `id` = %d", absint($id)), 'ARRAY_A');
        
        if (count($result)) {
            $res            = $result[0];
            $modified_date  = new DateTime();
            $current_user_id = get_current_user_id();
            
            $arr            = array(
                'title'         => $res['title'] . ' (' . esc_html__('Copy', 'pc-product-builder') . ')',
                'product_ids'   => $res['product_ids'],
                'published'     => 0,
                'modified'      => $modified_date->format('Y-m-d H:i:s'),
                'modified_by'   => $current_user_id,
                'fields'        => $res['fields'],
                'builder'       => $res['builder'],
                'created'       => $modified_date->format('Y-m-d H:i:s'),
                'created_by'    => $current_user_id
            ); 
            $in_res = $wpdb->insert("{$wpdb->prefix}storelly_product_builder_options", $arr, array('%s', '%s', '%d', '%s', '%d', '%s', '%s', '%d'));
            if ($in_res) {
                $this->spbwc_clear_transients();
                return $in_res;
            }
        }
        return false;
    }
    private function spbwc_clear_transients() {
        global $wpdb;
        $sql = "DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_nbo_product_%' OR option_name LIKE '_transient_timeout_nbo_product_%'";
        $wpdb->query($sql);
    }
    public function get_bulk_actions() {
        $actions = array(
            'bulk-delete'       => esc_html__('Delete', 'pc-product-builder'),
            'bulk-publish'      => esc_html__('Publish', 'pc-product-builder'),
            'bulk-unpublish'    => esc_html__('Unpublish', 'pc-product-builder'),
        );
        return $actions;
    }
    public function no_items() {
        esc_html_e('No options avaliable.', 'pc-product-builder');
    }
    function column_title($item) {
        $title      = $item['title'];
        $_nonce     = wp_create_nonce('nbd_options_nonce');
        $page       = sanitize_text_field( $_REQUEST['page'] );
        $actions    = array(
            'edit' => sprintf('<a href="?page=%s&action=%s&id=%s&paged=%s&_wpnonce=%s">' . esc_html__('Edit', 'pc-product-builder') . '</a>', esc_attr($page), 'edit', absint($item['id']), $this->get_pagenum(), $_nonce),
            'copy' => sprintf('<a href="?page=%s&action=%s&id=%s&paged=%s&_wpnonce=%s">' . esc_html__('Copy', 'pc-product-builder') . '</a>', esc_attr($page), 'copy', absint($item['id']), $this->get_pagenum(), $_nonce)
        );
        return $title . $this->spbwc_row_actions($actions);
    }
    function column_published($item) {
        return $item['published'] == 1 ? esc_html__('Publish', 'pc-product-builder') : esc_html__('Unpublish', 'pc-product-builder');
    }
    function column_date($item) {
        return (!empty($item['modified']) && $item['modified'] != '0000-00-00 00:00:00') ? $item['modified'] : $item['created'];
    }
    function column_product_ids($item) {
        $return = esc_html__('None', 'pc-product-builder');
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
    function column_cb($item) {
        return sprintf('<input type="checkbox" name="bulk-delete[]" value="%s" />', $item['id']);
    }
    function extra_tablenav($which) {
    }
}
