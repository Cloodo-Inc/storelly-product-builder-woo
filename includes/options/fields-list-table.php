<?php if (!defined('ABSPATH'))
    exit;
if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}
class SPBWC_Storelly_Options_List_Table extends WP_List_Table
{

    public function __construct()
    {
        parent::__construct(array(
            'singular' => esc_html__('Printing option', 'storelly-product-builder-for-woocommerce'),
            'plural' => esc_html__('Printing options', 'storelly-product-builder-for-woocommerce'),
            'ajax' => false
        ));
    }

    public function spbwc_prepare_items()
    {
        $columns  = $this->get_columns();
        $hidden   = array();
        $sortable = $this->spbwc_get_sortable_columns();
        $this->_column_headers = array($columns, $hidden, $sortable);
        /** Process bulk action */
        $this->spbwc_process_bulk_action();
        $per_page     = $this->get_items_per_page('options_per_page', 10);
        $current_page = $this->get_pagenum();
        $total_items  = self::spbwc_record_count();
        $this->set_pagination_args(array(
            'total_items' => $total_items,
            'per_page'    => $per_page,
        ));
        $this->items = self::spbwc_get_options($per_page, $current_page);
    }

    public function get_columns()
    {
        $columns = array(
            'cb'          => '<input type="checkbox" />',
            'title'       => esc_html__('Title', 'storelly-product-builder-for-woocommerce'),
            'status'      => esc_html__('Status', 'storelly-product-builder-for-woocommerce'),
            'field_count' => esc_html__('Fields', 'storelly-product-builder-for-woocommerce'),
            'categories'  => esc_html__('Categories', 'storelly-product-builder-for-woocommerce'),
            'product_ids' => esc_html__('Products', 'storelly-product-builder-for-woocommerce'),
            'date'        => esc_html__('Date', 'storelly-product-builder-for-woocommerce'),
        );
        return $columns;
    }

    public function spbwc_get_sortable_columns()
    {
        return array(
            'title' => array('title', false),
            'date'  => array('modified', true),
        );
    }

    /**
     * Get current action from request (compatible with WP_List_Table).
     *
     * Note: Nonce verification is performed in spbwc_process_bulk_action() where the action is actually processed.
     *
     * @return string|false Current action or false if none.
     */
    public function spbwc_current_action()
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading action value only; nonce verification performed in spbwc_process_bulk_action().
        if (isset($_REQUEST['filter_action']) && !empty($_REQUEST['filter_action'])) {
            return false;
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading action value only; nonce verification performed in spbwc_process_bulk_action().
        if (isset($_REQUEST['action']) && -1 != $_REQUEST['action']) {
            return sanitize_text_field(wp_unslash($_REQUEST['action'])); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading action value only; nonce verification performed in spbwc_process_bulk_action().
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading action value only; nonce verification performed in spbwc_process_bulk_action().
        if (isset($_REQUEST['action2']) && -1 != $_REQUEST['action2']) {
            return sanitize_text_field(wp_unslash($_REQUEST['action2'])); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading action value only; nonce verification performed in spbwc_process_bulk_action().
        }
        return false;
    }

    /**
     * Return counts grouped by published status, optionally scoped by search term.
     * Returns: array{ all: int, published: int, draft: int }
     *
     * @return array
     */
    public static function spbwc_count_all_statuses()
    {
        global $wpdb; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Global variable $wpdb.
        $table_name = $wpdb->prefix . 'storelly_product_builder_options';

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display filter, no state mutation.
        $search = isset($_REQUEST['s']) ? sanitize_text_field(wp_unslash($_REQUEST['s'])) : '';

        $where = 'WHERE 1 = 1';
        if ($search) {
            $where .= $wpdb->prepare(' AND title LIKE %s', '%' . $wpdb->esc_like($search) . '%');
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Admin-only grouped count query.
        $rows = $wpdb->get_results(
            "SELECT published, COUNT(*) AS cnt FROM {$table_name} {$where} GROUP BY published",
            ARRAY_A
        );

        $counts = array('all' => 0, 'published' => 0, 'draft' => 0);
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $n = (int) $row['cnt'];
                $counts['all'] += $n;
                if (1 === (int) $row['published']) {
                    $counts['published'] += $n;
                } else {
                    $counts['draft'] += $n;
                }
            }
        }
        return $counts;
    }

    public static function spbwc_record_count()
    {
        global $wpdb; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Global variable $wpdb.
        $table_name = $wpdb->prefix . 'storelly_product_builder_options';

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display filter, no state mutation.
        $status = isset($_REQUEST['status_filter']) && strlen($_REQUEST['status_filter']) ? sanitize_text_field(wp_unslash($_REQUEST['status_filter'])) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only search filter, no state mutation.
        $search = isset($_REQUEST['s']) ? sanitize_text_field(wp_unslash($_REQUEST['s'])) : '';

        $sql = "SELECT COUNT(*) FROM {$table_name} WHERE 1 = 1";
        if ($status !== '') {
            $sql .= $wpdb->prepare(' AND published = %d', absint($status));
        }
        if ($search) {
            $sql .= $wpdb->prepare(' AND title LIKE %s', '%' . $wpdb->esc_like($search) . '%');
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Admin-only count query; dynamic clauses built with $wpdb->prepare().
        return $wpdb->get_var($sql);
    }

    public function spbwc_get_options($per_page = 10, $page_number = 1)
    {
        global $wpdb; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Global variable $wpdb.
        $table_name = $wpdb->prefix . 'storelly_product_builder_options';
        $offset     = ($page_number - 1) * $per_page;

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display filter, no state mutation.
        $status = isset($_REQUEST['status_filter']) && strlen($_REQUEST['status_filter']) ? sanitize_text_field(wp_unslash($_REQUEST['status_filter'])) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only search filter, no state mutation.
        $search = isset($_REQUEST['s']) ? sanitize_text_field(wp_unslash($_REQUEST['s'])) : '';

        // Validate sort column against whitelist before interpolating into SQL.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only sort preference, no state mutation.
        $orderby_raw  = isset($_REQUEST['orderby']) ? sanitize_text_field(wp_unslash($_REQUEST['orderby'])) : 'modified';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only sort direction, no state mutation.
        $order_raw    = isset($_REQUEST['order']) && 'asc' === strtolower(sanitize_text_field(wp_unslash($_REQUEST['order']))) ? 'ASC' : 'DESC';
        $allowed_cols = array('modified', 'title');
        $orderby      = in_array($orderby_raw, $allowed_cols, true) ? $orderby_raw : 'modified';
        $order        = $order_raw; // already constrained to ASC|DESC above.

        $sql = "SELECT * FROM {$table_name} WHERE 1 = 1";
        if ($status !== '') {
            $sql .= $wpdb->prepare(' AND published = %d', absint($status));
        }
        if ($search) {
            $sql .= $wpdb->prepare(' AND title LIKE %s', '%' . $wpdb->esc_like($search) . '%');
        }
        $sql .= " ORDER BY {$orderby} {$order}";
        $sql .= $wpdb->prepare(' LIMIT %d OFFSET %d', $per_page, $offset);

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Admin-only paginated query; dynamic clauses built with $wpdb->prepare().
        return $wpdb->get_results($sql, 'ARRAY_A');
    }

    public function spbwc_process_bulk_action()
    {
        // 1. SECURITY: Check Permissions first
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to manage these options.', 'storelly-product-builder-for-woocommerce'));
        }

        // 2. CHECK IF ACTION EXISTS
        if (!isset($_REQUEST['action']) && !isset($_REQUEST['action2'])) {
            return;
        }

        // 3. SECURITY: Verify Nonce
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Verification performed below.
        $nonce = isset($_REQUEST['_wpnonce']) ? sanitize_text_field(wp_unslash($_REQUEST['_wpnonce'])) : '';

        $current_action = $this->spbwc_current_action();

        if (!$current_action) {
            return;
        }

        $is_bulk_action = in_array($current_action, array('bulk-publish', 'bulk-unpublish', 'bulk-delete'), true);

        if ($is_bulk_action) {
            $plural = isset($this->_args['plural']) ? $this->_args['plural'] : 'options';
            if (!wp_verify_nonce($nonce, 'bulk-' . $plural)) {
                wp_die(esc_html__('Security error: Invalid bulk nonce.', 'storelly-product-builder-for-woocommerce'));
            }
        } else {
            if (!wp_verify_nonce($nonce, 'spbwc_options_nonce')) {
                wp_die(esc_html__('Security error: Invalid nonce.', 'storelly-product-builder-for-woocommerce'));
            }
        }

        // 4. PROCESS ACTIONS
        $redirect_url = add_query_arg('page', SPBWC_PB_BUILDER_SLUG, admin_url('admin.php'));

        if ('delete' === $current_action) {
            if (!isset($_GET['id'])) {
                wp_die(esc_html__('Invalid request: Missing ID.', 'storelly-product-builder-for-woocommerce'));
            }
            $this->spbwc_delete_option(absint(wp_unslash($_GET['id'])));
            wp_safe_redirect($redirect_url);
            exit;
        }

        if ('copy' === $current_action) {
            if (!isset($_GET['id'])) {
                wp_die(esc_html__('Invalid request: Missing ID.', 'storelly-product-builder-for-woocommerce'));
            }
            $this->spbwc_copy_options(absint(wp_unslash($_GET['id'])));
            wp_safe_redirect($redirect_url);
            exit;
        }

        if ($is_bulk_action) {
            if (isset($_POST['bulk-delete']) && is_array($_POST['bulk-delete'])) {
                $bulk_ids = array_map('absint', wp_unslash($_POST['bulk-delete']));
                foreach ($bulk_ids as $id) {
                    if ('bulk-publish' === $current_action) {
                        $this->spbwc_publish_option($id);
                    } elseif ('bulk-unpublish' === $current_action) {
                        $this->spbwc_unpublish_option($id);
                    } elseif ('bulk-delete' === $current_action) {
                        $this->spbwc_delete_option($id);
                    }
                }
            }
            wp_safe_redirect($redirect_url);
            exit;
        }
    }

    public function spbwc_delete_option($id)
    {
        global $wpdb; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Global variable $wpdb.
        if (!current_user_can('manage_options'))
            return;
        $table_name = $wpdb->prefix . 'storelly_product_builder_options';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin-only delete operation.
        $result = $wpdb->delete($table_name, array('id' => absint($id)), array('%d'));
        if ($result)
            $this->spbwc_clear_transients();
    }

    public function spbwc_unpublish_option($id)
    {
        global $wpdb; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Global variable $wpdb.
        if (!current_user_can('manage_options'))
            return;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin-only update operation.
        $result = $wpdb->update($wpdb->prefix . 'storelly_product_builder_options', array(
            'published' => 0
        ), array('id' => absint($id)), array('%d'), array('%d'));
        if ($result)
            $this->spbwc_clear_transients();
    }

    public function spbwc_publish_option($id)
    {
        global $wpdb; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Global variable $wpdb.
        if (!current_user_can('manage_options'))
            return;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin-only update operation.
        $result = $wpdb->update($wpdb->prefix . 'storelly_product_builder_options', array(
            'published' => 1
        ), array('id' => absint($id)), array('%d'), array('%d'));
        if ($result)
            $this->spbwc_clear_transients();
    }

    public function spbwc_copy_options($id)
    {
        global $wpdb; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Global variable $wpdb.
        if (!current_user_can('manage_options'))
            return false;
        $table_name = $wpdb->prefix . 'storelly_product_builder_options';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Admin-only copy query.
        $result = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table_name} WHERE `id` = %d", absint($id)), 'ARRAY_A');

        if (count($result)) {
            $res              = $result[0];
            $modified_date    = new DateTime();
            $current_user_id  = get_current_user_id();

            $arr = array(
                'title'       => $res['title'] . ' (' . esc_html__('Copy', 'storelly-product-builder-for-woocommerce') . ')',
                'product_ids' => $res['product_ids'],
                'published'   => 0,
                'modified'    => $modified_date->format('Y-m-d H:i:s'),
                'modified_by' => $current_user_id,
                'fields'      => $res['fields'],
                'builder'     => $res['builder'],
                'created'     => $modified_date->format('Y-m-d H:i:s'),
                'created_by'  => $current_user_id,
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

    private function spbwc_clear_transients()
    {
        global $wpdb; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Global variable $wpdb.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Clearing transients requires direct query.
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                '_transient_nbo_product_%',
                '_transient_timeout_nbo_product_%'
            )
        );
    }

    public function get_bulk_actions()
    {
        $actions = array(
            'bulk-delete'    => esc_html__('Delete', 'storelly-product-builder-for-woocommerce'),
            'bulk-publish'   => esc_html__('Publish', 'storelly-product-builder-for-woocommerce'),
            'bulk-unpublish' => esc_html__('Unpublish', 'storelly-product-builder-for-woocommerce'),
        );
        return $actions;
    }

    public function no_items()
    {
        esc_html_e('No options available.', 'storelly-product-builder-for-woocommerce');
    }

    /**
     * Return a consistent color for a given title (based on first character).
     *
     * @param string $title Option title.
     * @return string Hex color string.
     */
    private function spbwc_get_color_for_title($title)
    {
        $palette = array('#3b82f6', '#8b5cf6', '#ec4899', '#f97316', '#10b981', '#0ea5e9', '#f59e0b', '#6366f1');
        $initial = mb_strtoupper(mb_substr($title, 0, 1));
        return $palette[ord($initial) % count($palette)];
    }

    /**
     * Count fields stored in a serialized fields blob.
     *
     * @param string $raw_fields Serialized fields string from DB.
     * @return int
     */
    public static function spbwc_count_fields($raw_fields)
    {
        if (empty($raw_fields)) {
            return 0;
        }
        $data = maybe_unserialize($raw_fields);
        if (isset($data['fields']) && is_array($data['fields'])) {
            return count($data['fields']);
        }
        return 0;
    }

    function column_title($item)
    {
        $title   = $item['title'];
        $initial = mb_strtoupper(mb_substr($title, 0, 1));
        $color   = $this->spbwc_get_color_for_title($title);

        $_nonce = wp_create_nonce('spbwc_options_nonce');
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Readonly page slug from request.
        $page = isset($_REQUEST['page']) ? sanitize_text_field(wp_unslash($_REQUEST['page'])) : '';

        $edit_url = esc_url(add_query_arg(array(
            'page'     => $page,
            'action'   => 'edit',
            'id'       => absint($item['id']),
            'paged'    => 1,
            '_wpnonce' => $_nonce,
        ), admin_url('admin.php')));

        $actions = array(
            'edit' => sprintf(
                '<a href="%s">%s</a>',
                esc_url(add_query_arg(array('page' => $page, 'action' => 'edit', 'id' => absint($item['id']), 'paged' => $this->get_pagenum(), '_wpnonce' => $_nonce), admin_url('admin.php'))),
                esc_html__('Edit', 'storelly-product-builder-for-woocommerce')
            ),
            'copy' => sprintf(
                '<a href="%s">%s</a>',
                esc_url(add_query_arg(array('page' => $page, 'action' => 'copy', 'id' => absint($item['id']), 'paged' => $this->get_pagenum(), '_wpnonce' => $_nonce), admin_url('admin.php'))),
                esc_html__('Copy', 'storelly-product-builder-for-woocommerce')
            ),
        );

        $thumb = sprintf(
            '<span class="spbwc-option-thumb" aria-hidden="true" style="background-color:%s">%s</span>',
            esc_attr($color),
            esc_html($initial)
        );

        $title_link = sprintf('<a href="%s" class="spbwc-title-link">%s</a>', $edit_url, esc_html($title));

        return '<div class="spbwc-title-wrap">' . $thumb . '<span class="spbwc-title-text">' . $title_link . '</span></div>'
            . $this->row_actions($actions);
    }

    function column_status($item)
    {
        if (1 == $item['published']) {
            return '<span class="spbwc-badge spbwc-badge--published">'
                . esc_html__('Published', 'storelly-product-builder-for-woocommerce')
                . '</span>';
        }
        return '<span class="spbwc-badge spbwc-badge--draft">'
            . esc_html__('Draft', 'storelly-product-builder-for-woocommerce')
            . '</span>';
    }

    function column_field_count($item)
    {
        $count = self::spbwc_count_fields($item['fields']);
        $label = sprintf(
            /* translators: %d: number of pricing fields in this option group */
            _n('%d field', '%d fields', $count, 'storelly-product-builder-for-woocommerce'),
            $count
        );
        return '<span class="spbwc-field-count">' . esc_html($label) . '</span>';
    }

    function column_categories($item)
    {
        $none = '<span class="spbwc-text-muted">&mdash;</span>';
        if (empty($item['product_cats'])) {
            return $none;
        }
        $cats = maybe_unserialize($item['product_cats']);
        if (!is_array($cats) || empty($cats)) {
            return $none;
        }
        $links = array();
        foreach ($cats as $cat_id) {
            $term = get_term(absint($cat_id), 'product_cat');
            if ($term && !is_wp_error($term)) {
                $links[] = sprintf(
                    '<a href="%s">%s</a>',
                    esc_url(admin_url('term.php?taxonomy=product_cat&tag_ID=' . $term->term_id)),
                    esc_html($term->name)
                );
            }
        }
        return !empty($links) ? implode(', ', $links) : $none;
    }

    function column_published($item)
    {
        return 1 == $item['published']
            ? esc_html__('Publish', 'storelly-product-builder-for-woocommerce')
            : esc_html__('Unpublish', 'storelly-product-builder-for-woocommerce');
    }

    function column_date($item)
    {
        $date = (!empty($item['modified']) && '0000-00-00 00:00:00' !== $item['modified'])
            ? $item['modified']
            : $item['created'];
        return esc_html($date);
    }

    function column_product_ids($item)
    {
        $return = esc_html__('None', 'storelly-product-builder-for-woocommerce');
        if (!$item['product_ids'])
            return $return;
        $products = maybe_unserialize($item['product_ids']);
        if (is_array($products) && count($products)) {
            $links = array();
            foreach ($products as $pid) {
                $title   = get_the_title($pid);
                $links[] = '<a title="' . esc_attr($title) . '" href="' . esc_url(admin_url('post.php?action=edit&post=' . $pid)) . '" rel="tag">' . esc_html($title) . '</a>';
            }
            $return = implode(' , ', $links);
        }
        return $return;
    }

    function column_default($item, $column_name)
    {
        return isset($item[$column_name]) ? esc_html($item[$column_name]) : '';
    }

    function column_cb($item)
    {
        return sprintf('<input type="checkbox" name="bulk-delete[]" value="%s" />', absint($item['id']));
    }

    function extra_tablenav($which) // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
    {
        // Status filtering is handled by the toolbar tabs in the view template (GET-based navigation).
        // No additional tablenav controls needed here.
    }
}
