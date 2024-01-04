<?php if (!defined('ABSPATH')) exit;
if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}
class Storelly_Options_List_Table extends WP_List_Table {
    public function __construct() {
        parent::__construct(array(
            'singular'  => esc_html__('Printing option', 'pc-product-builder'),
            'plural'    => esc_html__('Printing options', 'pc-product-builder'),
            'ajax'      => false
        ));
    }
    public function prepare_items() {
        $columns    = $this->get_columns();
        $hidden     = array();
        $sortable   = $this->get_sortable_columns();
        $this->_column_headers = array($columns, $hidden, $sortable);
        /** Process bulk action */
        $this->process_bulk_action();
        $per_page       = $this->get_items_per_page('options_per_page', 10);
        $current_page   = $this->get_pagenum();
        $total_items    = self::record_count();
        $this->set_pagination_args(array(
            'total_items'   => $total_items,
            'per_page'      => $per_page
        ));
        $this->items = self::get_options($per_page, $current_page);
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
    public function get_sortable_columns() {
        $sortable_columns = array(
            'priority' => array('priority', true)
        );
        return $sortable_columns;
    }
    public static function record_count() {
        global $wpdb;
        $sql = "SELECT COUNT(*) FROM {$wpdb->prefix}storelly_product_builder_options";
        return $wpdb->get_var($sql);
    }
    public function get_options($per_page = 10, $page_number = 1) {
        global $wpdb;
        $sql  = "SELECT * FROM {$wpdb->prefix}storelly_product_builder_options";
        $sql .= " ORDER BY modified DESC LIMIT $per_page";
        $sql .= ' OFFSET ' . ($page_number - 1) * $per_page;
        $result = $wpdb->get_results($sql, 'ARRAY_A');
        return $result;
    }
    public function process_bulk_action() {
        if ('delete' === $this->current_action()) {
            $nonce = esc_attr($_REQUEST['_wpnonce']);
            if (!wp_verify_nonce($nonce, 'nbd_options_nonce')) {
                die('Go get a life script kiddies');
            }
            $this->delete_option(absint($_GET['id']));
            wp_redirect(esc_url_raw(add_query_arg(array('paged' => $this->get_pagenum()), admin_url('admin.php?page=nbd_printing_options'))));
            exit;
        }
        if ('copy' === $this->current_action()) {
            $nonce = esc_attr($_REQUEST['_wpnonce']);
            if (!wp_verify_nonce($nonce, 'nbd_options_nonce')) {
                die('Go get a life script kiddies');
            }
            $this->copy_options(absint($_GET['id']));
            wp_redirect(esc_url_raw(admin_url('admin.php?page=nbd_printing_options')));
            exit;
        }
        if ((isset($_POST['action']) && $_POST['action'] == 'bulk-publish') || (isset($_POST['action2']) && $_POST['action2'] == 'bulk-publish')) {
            if (isset($_POST['bulk-delete'])) {
                $bulk_ids = esc_sql($_POST['bulk-delete']);
                foreach ($bulk_ids as $id) {
                    $this->publish_option($id);
                }
            }
            wp_redirect(esc_url_raw(add_query_arg('', '')));
        }
        if ((isset($_POST['action']) && $_POST['action'] == 'bulk-unpublish') || (isset($_POST['action2']) && $_POST['action2'] == 'bulk-unpublish')) {
            if (isset($_POST['bulk-delete'])) {
                $bulk_ids = esc_sql($_POST['bulk-delete']);
                foreach ($bulk_ids as $id) {
                    $this->unpublish_option($id);
                }
            }
            wp_redirect(esc_url_raw(add_query_arg('', '')));
        }
        if ((isset($_POST['action']) && $_POST['action'] == 'bulk-delete') || (isset($_POST['action2']) && $_POST['action2'] == 'bulk-delete')) {
            if (isset($_POST['bulk-delete'])) {
                $bulk_ids = esc_sql($_POST['bulk-delete']);
                foreach ($bulk_ids as $id) {
                    $this->delete_option($id);
                }
            }
            wp_redirect(esc_url_raw(add_query_arg('', '')));
        }
    }
    public function delete_option($id) {
        global $wpdb;
        $sql = "DELETE FROM {$wpdb->prefix}storelly_product_builder_options";
        $sql .= " WHERE id = " . esc_sql($id);
        $result = $wpdb->query($sql);
        if ($result) $this->clear_transients();
    }
    public function unpublish_option($id) {
        global $wpdb;
        $result = $wpdb->update($wpdb->prefix . 'storelly_product_builder_options', array(
            'published' => 0
        ), array('id' => esc_sql($id)));
        if ($result) $this->clear_transients();
    }
    public function publish_option($id) {
        global $wpdb;
        $result = $wpdb->update($wpdb->prefix . 'storelly_product_builder_options', array(
            'published' => 1
        ), array('id' => esc_sql($id)));
        if ($result) $this->clear_transients();
    }
    public function copy_options($id) {
        global $wpdb;
        $sql    = "SELECT * FROM {$wpdb->prefix}storelly_product_builder_options";
        $sql   .= " WHERE id = " . esc_sql($id);
        $result = $wpdb->get_results($sql, 'ARRAY_A');
        if (count($result[0])) {
            $res            = $result[0];
            $modified_date  = new DateTime();
            $arr            = array(
                'title'         => $res['title'],
                'product_ids'   => $res['product_ids'],
                'modified'      => $modified_date->format('Y-m-d H:i:s'),
                'fields'        => $res['fields'],
                'builder'       => $res['builder'],
                'created'       => $modified_date->format('Y-m-d H:i:s'),
                'created_by'    => wp_get_current_user()->ID
            );
            $in_res = $wpdb->insert("{$wpdb->prefix}storelly_product_builder_options", $arr);
            if ($in_res) {
                $this->clear_transients();
                return $in_res;
            }
        }
        return false;
    }
    private function clear_transients() {
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
        $actions    = array(
            'edit' => sprintf('<a href="?page=%s&action=%s&id=%s&paged=%s&_wpnonce=%s">' . esc_html__('Edit', 'pc-product-builder') . '</a>', esc_attr($_REQUEST['page']), 'edit', absint($item['id']), $this->get_pagenum(), $_nonce),
            'copy' => sprintf('<a href="?page=%s&action=%s&id=%s&paged=%s&_wpnonce=%s">' . esc_html__('Copy', 'pc-product-builder') . '</a>', esc_attr($_REQUEST['page']), 'copy', absint($item['id']), $this->get_pagenum(), $_nonce)
        );
        return $title . $this->row_actions($actions);
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
    function save_option() {
        ob_start();
        var_dump($_POST);
        error_log(ob_get_clean());
    }
}
