<?php
if (!defined('ABSPATH')) {
    exit;
}

class SPBWC_Global_Import_Controller {
    protected static $instance;
    private $adapter;
    private const DEMO_DATA_URL = 'https://app.storelly.com/product-data/data/data.json';

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        $this->adapter = new SPBWC_Printcart_Import_Adapter();
    }

    public function init() {
        add_action('wp_ajax_spbwc_global_import_upload', array($this, 'handle_upload'));
        add_action('wp_ajax_spbwc_global_import_list', array($this, 'handle_list'));
        add_action('wp_ajax_spbwc_global_import_row_ids', array($this, 'handle_row_ids'));
        add_action('wp_ajax_spbwc_global_import_run', array($this, 'handle_import'));
        add_action('wp_ajax_spbwc_global_import_log', array($this, 'handle_log'));
        add_action('wp_ajax_spbwc_global_import_issues', array($this, 'handle_issues_list'));
        add_action('wp_ajax_spbwc_global_import_reimport', array($this, 'handle_reimport'));
        add_action('wp_ajax_spbwc_global_import_health', array($this, 'handle_health'));
    }

    private function filesystem() {
        global $wp_filesystem;
        if (!$wp_filesystem) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }
        return $wp_filesystem;
    }

    private function base_upload_dir() {
        $upload_dir = wp_upload_dir();
        return trailingslashit($upload_dir['basedir']) . 'global-import-uploads/';
    }

    private function session_dir() {
        return $this->base_upload_dir() . 'sessions/';
    }

    private function log_dir() {
        return $this->base_upload_dir() . 'logs/';
    }

    private function ensure_dirs() {
        $fs = $this->filesystem();
        if ($fs) {
            $fs->mkdir($this->base_upload_dir());
            $fs->mkdir($this->session_dir());
            $fs->mkdir($this->log_dir());
        }
    }

    private function get_sessions() {
        $sessions = get_option('spbwc_global_import_sessions', array());
        return is_array($sessions) ? $sessions : array();
    }

    private function save_session($import_id, $data) {
        $sessions = $this->get_sessions();
        $sessions[$import_id] = $data;
        update_option('spbwc_global_import_sessions', $sessions);
    }

    private function get_session($import_id) {
        $sessions = $this->get_sessions();
        return $sessions[$import_id] ?? null;
    }

    private function demo_session_id() {
        return 'printcart-demo';
    }

    private function demo_data_path() {
        return SPBWC_PB_PLUGIN_DIR . 'storage/printcart/demo_datas.json';
    }

    private function ensure_demo_session() {
        $import_id = $this->demo_session_id();
        $rows = $this->fetch_demo_rows();
        if (empty($rows)) {
            return null;
        }
        $rows = $this->normalize_rows($rows);
        $this->ensure_dirs();
        $session_path = $this->session_dir() . $import_id . '.json';
        $fs = $this->filesystem();
        if ($fs) {
            $fs->put_contents($session_path, wp_json_encode($rows));
        }
        $session = array(
            'import_id' => $import_id,
            'file_path' => self::DEMO_DATA_URL,
            'file_name' => basename(self::DEMO_DATA_URL),
            'file_ext' => 'json',
            'session_path' => $session_path,
            'created_at' => current_time('mysql'),
            'total' => count($rows),
            'is_demo' => true
        );
        $this->save_session($import_id, $session);
        return $session;
    }

    public function handle_upload() {
        check_ajax_referer('spbwc_global_import', 'nonce');
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('Permission denied', 'storelly-product-builder-for-woocommerce'), 403);
        }
        if (empty($_FILES['file'])) {
            wp_send_json_error(__('No file uploaded', 'storelly-product-builder-for-woocommerce'));
        }
        $this->ensure_dirs();
        $file = $_FILES['file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = array('csv', 'json', 'xlsx');
        if (!in_array($ext, $allowed, true)) {
            wp_send_json_error(__('Unsupported file type', 'storelly-product-builder-for-woocommerce'));
        }
        $overrides = array('test_form' => false);
        $uploaded = wp_handle_upload($file, $overrides);
        if (isset($uploaded['error'])) {
            wp_send_json_error($uploaded['error']);
        }
        $import_id = wp_generate_uuid4();
        $rows = $this->parse_file($uploaded['file'], $ext);
        $rows = $this->normalize_rows($rows);
        $session_path = $this->session_dir() . $import_id . '.json';
        $fs = $this->filesystem();
        if ($fs) {
            $fs->put_contents($session_path, wp_json_encode($rows));
        }
        $session = array(
            'import_id' => $import_id,
            'file_path' => $uploaded['file'],
            'file_name' => basename($uploaded['file']),
            'file_ext' => $ext,
            'session_path' => $session_path,
            'created_at' => current_time('mysql'),
            'total' => count($rows)
        );
        $this->save_session($import_id, $session);
        wp_send_json_success(array(
            'import_id' => $import_id,
            'total' => count($rows),
            'preview' => array_slice($rows, 0, 12)
        ));
    }

    private function get_filtered_rows($session_path, $search, $category, $stock, $price_min, $price_max, $sort_by, $sort_dir) {
        $rows = $this->read_session_rows($session_path);
        $rows = array_values(array_filter($rows, function($row) use ($search, $category, $stock, $price_min, $price_max) {
            $name = isset($row['name']) ? (string) $row['name'] : '';
            $sku = isset($row['sku']) ? (string) $row['sku'] : '';
            $categories = isset($row['categories']) && is_array($row['categories']) ? $row['categories'] : array();
            $stock_status = isset($row['stock_status']) ? (string) $row['stock_status'] : '';
            $price = isset($row['price']) ? (float) $row['price'] : 0;
            if ($search && stripos($name, $search) === false && stripos($sku, $search) === false) {
                return false;
            }
            if ($category && !in_array($category, $categories, true)) {
                return false;
            }
            if ($stock && $stock_status !== $stock) {
                return false;
            }
            if ($price_min !== null && $price < $price_min) {
                return false;
            }
            if ($price_max !== null && $price > $price_max) {
                return false;
            }
            return true;
        }));
        usort($rows, function($a, $b) use ($sort_by, $sort_dir) {
            $va = $a[$sort_by] ?? '';
            $vb = $b[$sort_by] ?? '';
            if ($va == $vb) {
                return 0;
            }
            if ($sort_dir === 'desc') {
                return ($va < $vb) ? 1 : -1;
            }
            return ($va < $vb) ? -1 : 1;
        });
        return $rows;
    }

    public function handle_list() {
        check_ajax_referer('spbwc_global_import', 'nonce');
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('Permission denied', 'storelly-product-builder-for-woocommerce'), 403);
        }
        $import_id = sanitize_text_field($_POST['import_id'] ?? '');
        if ($import_id === '') {
            $import_id = $this->demo_session_id();
        }
        if ($import_id === $this->demo_session_id()) {
            $session = $this->ensure_demo_session();
        } else {
            $session = $this->get_session($import_id);
        }
        if (!$session) {
            wp_send_json_error(__('Session not found', 'storelly-product-builder-for-woocommerce'));
        }
        $page = max(1, absint($_POST['page'] ?? 1));
        $per_page = max(1, absint($_POST['per_page'] ?? 12));
        $search = sanitize_text_field($_POST['search'] ?? '');
        $category = sanitize_text_field($_POST['category'] ?? '');
        $stock = sanitize_text_field($_POST['stock'] ?? '');
        $price_min = isset($_POST['price_min']) ? floatval($_POST['price_min']) : null;
        $price_max = isset($_POST['price_max']) ? floatval($_POST['price_max']) : null;
        $sort_by = sanitize_text_field($_POST['sort_by'] ?? 'name');
        $sort_dir = sanitize_text_field($_POST['sort_dir'] ?? 'asc');
        $rows = $this->get_filtered_rows(
            $session['session_path'],
            $search,
            $category,
            $stock,
            $price_min,
            $price_max,
            $sort_by,
            $sort_dir
        );
        $total = count($rows);
        $offset = ($page - 1) * $per_page;
        $paged = array_slice($rows, $offset, $per_page);
        wp_send_json_success(array(
            'items' => $paged,
            'total' => $total,
            'page' => $page,
            'per_page' => $per_page
        ));
    }

    public function handle_row_ids() {
        check_ajax_referer('spbwc_global_import', 'nonce');
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('Permission denied', 'storelly-product-builder-for-woocommerce'), 403);
        }
        $import_id = sanitize_text_field($_POST['import_id'] ?? '');
        if ($import_id === '') {
            $import_id = $this->demo_session_id();
        }
        if ($import_id === $this->demo_session_id()) {
            $session = $this->ensure_demo_session();
        } else {
            $session = $this->get_session($import_id);
        }
        if (!$session) {
            wp_send_json_error(__('Session not found', 'storelly-product-builder-for-woocommerce'));
        }
        $search = sanitize_text_field($_POST['search'] ?? '');
        $category = sanitize_text_field($_POST['category'] ?? '');
        $stock = sanitize_text_field($_POST['stock'] ?? '');
        $price_min = isset($_POST['price_min']) ? floatval($_POST['price_min']) : null;
        $price_max = isset($_POST['price_max']) ? floatval($_POST['price_max']) : null;
        $rows = $this->get_filtered_rows(
            $session['session_path'],
            $search,
            $category,
            $stock,
            $price_min,
            $price_max,
            'name',
            'asc'
        );
        $row_ids = array_values(
            array_filter(
                array_map(
                    static function($row) {
                        return isset($row['row_id']) ? (string) $row['row_id'] : '';
                    },
                    $rows
                ),
                static function($row_id) {
                    return '' !== $row_id;
                }
            )
        );
        wp_send_json_success(
            array(
                'row_ids' => $row_ids,
                'total' => count($row_ids),
            )
        );
    }

    public function handle_import() {
        check_ajax_referer('spbwc_global_import', 'nonce');
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('Permission denied', 'storelly-product-builder-for-woocommerce'), 403);
        }
        $import_id = sanitize_text_field($_POST['import_id'] ?? '');
        if ($import_id === '') {
            $import_id = $this->demo_session_id();
        }
        $run_id = sanitize_text_field($_POST['run_id'] ?? '');
        $job_id = '' !== $run_id ? $run_id : $import_id;

        $this->adapter->set_logger(function($message) use ($job_id) {
            $this->log_line($job_id, $message);
        });

        $row_ids_raw = isset($_POST['row_ids']) ? wp_unslash($_POST['row_ids']) : array();

        if (is_array($row_ids_raw)) {
            $row_ids = array_map('sanitize_text_field', $row_ids_raw);
        } elseif (is_string($row_ids_raw) && '' !== $row_ids_raw) {
            $row_ids = array_map('sanitize_text_field', explode(',', $row_ids_raw));
        } else {
            $row_ids = array();
        }
        $row_ids = array_values(
            array_unique(
                array_filter(
                    $row_ids,
                    static function($row_id) {
                        return '' !== $row_id;
                    }
                )
            )
        );
        if (empty($row_ids)) {
            wp_send_json_error(__('No rows selected', 'storelly-product-builder-for-woocommerce'));
        }
        @set_time_limit(0);
        wp_defer_term_counting(true);
        wp_defer_comment_counting(true);
        $batch = max(1, absint($_POST['batch'] ?? 20));
        if ($import_id === $this->demo_session_id()) {
            $session = $this->ensure_demo_session();
        } else {
            $session = $this->get_session($import_id);
        }
        if (!$session) {
            wp_send_json_error(__('Session not found', 'storelly-product-builder-for-woocommerce'));
        }
        $rows = $this->read_session_rows($session['session_path']);
        $rows_map = array();
        foreach ($rows as $row) {
            $rows_map[$row['row_id']] = $row;
        }
        $processed = array();
        $errors = array();
        $debug_lines = array();
        $payload = array();
        $count = 0;
        foreach ($row_ids as $row_id) {
            if ($count >= $batch) {
                break;
            }
            $count++;
            if (!isset($rows_map[$row_id])) {
                $errors[] = __('Row not found', 'storelly-product-builder-for-woocommerce');
                continue;
            }
            $row = $rows_map[$row_id];
            $this->log_line($job_id, 'IMPORTING product: ' . ($row['name'] ? $row['name'] : $row_id));
            try {
                $result = $this->import_row($row);
            } catch (Throwable $throwable) {

                $result = array(
                    'success' => false,
                    'message' => $throwable->getMessage(),
                    'warnings' => array(),
                    'debug' => array(
                        'Row ' . $row_id . ': exception during import - ' . $throwable->getMessage(),
                    ),
                );
            }
            if (!empty($result['debug']) && is_array($result['debug'])) {
                $debug_lines = array_merge($debug_lines, $result['debug']);
            }
            if ($result['success']) {
                $processed[] = $result['data'];
                $payload[] = $this->build_product_payload($result['data']['product_id']);
                if (!empty($result['warnings']) && is_array($result['warnings'])) {
                    $errors = array_merge($errors, $result['warnings']);
                }
            } else {
                $errors[] = $result['message'];
            }
        }
        $export_result = array(
            'success' => true,
            'filename' => '',
        );
        if (!empty($payload)) {
            $export_result = SPBWC_Global_Import::instance()->export_product_data($payload, $session['file_name'], $job_id);
            if (!$export_result['success']) {
                $errors[] = isset($export_result['error']) ? $export_result['error'] : __('Failed to export import session', 'storelly-product-builder-for-woocommerce');
            }
        }
        $this->append_log($job_id, $processed, $errors, $debug_lines);
        wp_defer_term_counting(false);
        wp_defer_comment_counting(false);
        wp_send_json_success(array(
            'processed' => $processed,
            'errors' => $errors,
            'attempted' => $count,
            'export' => $export_result,
        ));
    }

    public function handle_exports() {
        check_ajax_referer('spbwc_global_import', 'nonce');
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('Permission denied', 'storelly-product-builder-for-woocommerce'), 403);
        }
        $global_import = SPBWC_Global_Import::instance();
        $files = $global_import->get_export_files();
        $files = array_map(function($file) {
            $file['download_url'] = add_query_arg(array(
                'page' => 'storelly-product-builder-for-woocommerce-options/global-import',
                'spbwc_export_action' => 'download',
                'filename' => $file['filename']
            ), admin_url('admin.php'));
            return $file;
        }, $files);
        wp_send_json_success(array('files' => $files));
    }

    public function handle_export_delete() {
        check_ajax_referer('spbwc_global_import', 'nonce');
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('Permission denied', 'storelly-product-builder-for-woocommerce'), 403);
        }
        $filename = sanitize_file_name($_POST['filename'] ?? '');
        $global_import = SPBWC_Global_Import::instance();
        $deleted = $global_import->delete_export_file($filename);
        wp_send_json_success(array('deleted' => $deleted));
    }

    public function handle_log() {
        check_ajax_referer('spbwc_global_import', 'nonce');
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('Permission denied', 'storelly-product-builder-for-woocommerce'), 403);
        }
        $import_id = sanitize_text_field($_POST['import_id'] ?? '');
        $run_id = sanitize_text_field($_POST['run_id'] ?? '');
        $offset = max(0, absint($_POST['offset'] ?? 0));
        $log_id = '' !== $run_id ? $run_id : $import_id;
        $log_path = $this->log_dir() . $log_id . '.log';
        $fs = $this->filesystem();
        if (!$fs || !$fs->exists($log_path)) {
            wp_send_json_success(array('lines' => array(), 'offset' => $offset));
        }
        $content = $fs->get_contents($log_path);
        $lines = array_filter(explode("\n", $content));
        $slice = array_slice($lines, $offset);
        wp_send_json_success(array('lines' => $slice, 'offset' => count($lines)));
    }

    /**
     * List products that were imported with problems (partial imports) so the admin can review
     * and re-import them.
     */
    public function handle_issues_list() {
        check_ajax_referer('spbwc_global_import', 'nonce');
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('Permission denied', 'storelly-product-builder-for-woocommerce'), 403);
        }
        $query = new WP_Query(array(
            'post_type'      => 'product',
            'post_status'    => 'any',
            'posts_per_page' => 200,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- bounded admin-only lookup of products flagged during import.
            'meta_query'     => array(
                array(
                    'key'   => '_spbwc_import_status',
                    'value' => 'partial',
                ),
            ),
        ));
        $items = array();
        foreach ($query->posts as $pid) {
            $warnings = json_decode((string) get_post_meta($pid, '_spbwc_import_warnings', true), true);
            $source   = json_decode((string) get_post_meta($pid, '_spbwc_import_source', true), true);
            $items[] = array(
                'product_id'   => (int) $pid,
                'name'         => html_entity_decode(get_the_title($pid), ENT_QUOTES),
                'status'       => (string) get_post_meta($pid, '_spbwc_import_status', true),
                'warnings'     => is_array($warnings) ? $warnings : array(),
                'date'         => (string) get_post_meta($pid, '_spbwc_import_date', true),
                'edit_url'     => get_edit_post_link($pid, 'raw'),
                'can_reimport' => is_array($source) && !empty($source['settings']),
            );
        }
        wp_send_json_success(array('items' => $items));
    }

    /**
     * Re-import a single product from its recorded source, overwriting the existing product in
     * place (same product ID) so order references stay valid.
     */
    public function handle_reimport() {
        check_ajax_referer('spbwc_global_import', 'nonce');
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('Permission denied', 'storelly-product-builder-for-woocommerce'), 403);
        }
        $product_id = absint($_POST['product_id'] ?? 0);
        if (!$product_id) {
            wp_send_json_error(__('Invalid product', 'storelly-product-builder-for-woocommerce'));
        }
        $source = json_decode((string) get_post_meta($product_id, '_spbwc_import_source', true), true);
        if (!is_array($source) || empty($source['settings'])) {
            wp_send_json_error(__('No import source recorded for this product — cannot re-import.', 'storelly-product-builder-for-woocommerce'));
        }
        $run_id = 'reimport-' . $product_id . '-' . time();
        @set_time_limit(0); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, Generic.PHP.DiscouragedFunctions -- best-effort lift of the time limit for a long media/JSON re-download.
        $this->adapter->set_logger(function ($message) use ($run_id) {
            $this->log_line($run_id, $message);
        });
        $product = wc_get_product($product_id);
        $row = array(
            'row_id'     => isset($source['row_id']) ? (string) $source['row_id'] : (string) $product_id,
            'name'       => isset($source['name']) ? (string) $source['name'] : ($product ? $product->get_name() : ''),
            'sku'        => $product ? $product->get_sku() : '',
            'price'      => 0,
            'image'      => isset($source['image']) ? (string) $source['image'] : '',
            'categories' => array(),
            'raw'        => array(
                'settings'      => (string) $source['settings'],
                'print_options' => isset($source['print_options']) ? (string) $source['print_options'] : '',
                'templates'     => isset($source['templates']) ? (string) $source['templates'] : '',
                'external_id'   => isset($source['external_id']) ? (string) $source['external_id'] : '',
                'image'         => isset($source['image']) ? (string) $source['image'] : '',
            ),
        );
        $this->log_line($run_id, 'RE-IMPORT product #' . $product_id . ': ' . $row['name']);
        try {
            $result = $this->import_row($row, true, $product_id);
        } catch (Throwable $throwable) {
            $this->log_line($run_id, 'ERROR ' . $throwable->getMessage());
            wp_send_json_error($throwable->getMessage());
        }
        if (empty($result['success'])) {
            $this->log_line($run_id, 'ERROR ' . $result['message']);
            wp_send_json_error($result['message']);
        }
        if (!empty($result['warnings'])) {
            foreach ($result['warnings'] as $warning) {
                $this->log_line($run_id, 'WARNING ' . $warning);
            }
        }
        $this->log_line($run_id, 'BATCH_COMPLETE ' . wp_json_encode(array('product_id' => $product_id)));
        $warnings = json_decode((string) get_post_meta($product_id, '_spbwc_import_warnings', true), true);
        wp_send_json_success(array(
            'product_id' => $product_id,
            'run_id'     => $run_id,
            'status'     => (string) get_post_meta($product_id, '_spbwc_import_status', true),
            'warnings'   => is_array($warnings) ? $warnings : array(),
        ));
    }

    /**
     * Report PHP environment capability so the admin is warned when their server is too weak for
     * heavy imports (large JSON pricing options, many media files).
     */
    public function handle_health() {
        check_ajax_referer('spbwc_global_import', 'nonce');
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('Permission denied', 'storelly-product-builder-for-woocommerce'), 403);
        }
        wp_send_json_success($this->get_environment_health());
    }

    private function get_environment_health() {
        $checks = array();

        $max_exec = (int) ini_get('max_execution_time');
        $exec_ok  = (0 === $max_exec || $max_exec >= 120);
        $checks[] = array(
            'key'         => 'max_execution_time',
            'label'       => __('Max execution time', 'storelly-product-builder-for-woocommerce'),
            'value'       => 0 === $max_exec ? __('unlimited', 'storelly-product-builder-for-woocommerce') : $max_exec . 's',
            'recommended' => '≥ 120s',
            'ok'          => $exec_ok,
        );

        $mem    = wp_convert_hr_to_bytes(ini_get('memory_limit'));
        $mem_ok = ($mem <= 0 || $mem >= 256 * MB_IN_BYTES);
        $checks[] = array(
            'key'         => 'memory_limit',
            'label'       => __('Memory limit', 'storelly-product-builder-for-woocommerce'),
            'value'       => $mem <= 0 ? __('unlimited', 'storelly-product-builder-for-woocommerce') : size_format($mem),
            'recommended' => '≥ 256 MB',
            'ok'          => $mem_ok,
        );

        $post_max    = wp_convert_hr_to_bytes(ini_get('post_max_size'));
        $post_max_ok = ($post_max <= 0 || $post_max >= 64 * MB_IN_BYTES);
        $checks[] = array(
            'key'         => 'post_max_size',
            'label'       => __('Post max size', 'storelly-product-builder-for-woocommerce'),
            'value'       => $post_max <= 0 ? __('unlimited', 'storelly-product-builder-for-woocommerce') : size_format($post_max),
            'recommended' => '≥ 64 MB',
            'ok'          => $post_max_ok,
        );

        $upload_max    = wp_convert_hr_to_bytes(ini_get('upload_max_filesize'));
        $upload_max_ok = ($upload_max <= 0 || $upload_max >= 64 * MB_IN_BYTES);
        $checks[] = array(
            'key'         => 'upload_max_filesize',
            'label'       => __('Upload max filesize', 'storelly-product-builder-for-woocommerce'),
            'value'       => $upload_max <= 0 ? __('unlimited', 'storelly-product-builder-for-woocommerce') : size_format($upload_max),
            'recommended' => '≥ 64 MB',
            'ok'          => $upload_max_ok,
        );

        $zip_ok   = class_exists('ZipArchive');
        $checks[] = array(
            'key'         => 'zip',
            'label'       => __('ZipArchive (design templates)', 'storelly-product-builder-for-woocommerce'),
            'value'       => $zip_ok ? __('available', 'storelly-product-builder-for-woocommerce') : __('missing', 'storelly-product-builder-for-woocommerce'),
            'recommended' => __('available', 'storelly-product-builder-for-woocommerce'),
            'ok'          => $zip_ok,
        );

        $disabled          = array_map('trim', explode(',', (string) ini_get('disable_functions')));
        $set_time_limit_ok = function_exists('set_time_limit') && !in_array('set_time_limit', $disabled, true);
        $checks[] = array(
            'key'         => 'set_time_limit',
            'label'       => __('set_time_limit()', 'storelly-product-builder-for-woocommerce'),
            'value'       => $set_time_limit_ok ? __('available', 'storelly-product-builder-for-woocommerce') : __('disabled', 'storelly-product-builder-for-woocommerce'),
            'recommended' => __('available', 'storelly-product-builder-for-woocommerce'),
            'ok'          => $set_time_limit_ok,
        );

        $conn = $this->adapter->fetch_remote(self::DEMO_DATA_URL, true, 10);

        $warnings = array();
        foreach ($checks as $check) {
            if (!$check['ok']) {
                $warnings[] = sprintf(
                    /* translators: 1: setting label, 2: current value, 3: recommended value */
                    __('%1$s is %2$s (recommended %3$s).', 'storelly-product-builder-for-woocommerce'),
                    $check['label'],
                    $check['value'],
                    $check['recommended']
                );
            }
        }

        $recommended_batch = 5;
        if (!$exec_ok || $mem > 0 && $mem < 256 * MB_IN_BYTES) {
            $recommended_batch = 2;
        } elseif (0 === $max_exec && ($mem <= 0 || $mem >= 512 * MB_IN_BYTES)) {
            $recommended_batch = 10;
        }

        return array(
            'checks'            => $checks,
            'warnings'          => $warnings,
            'connectivity'      => array(
                'ok'      => (bool) $conn['ok'],
                'message' => $conn['ok'] ? __('Connected to import server.', 'storelly-product-builder-for-woocommerce') : $conn['message'],
            ),
            'recommended_batch' => $recommended_batch,
            'healthy'           => empty($warnings) && $conn['ok'],
        );
    }

    private function parse_file($path, $ext) {
        if ($ext === 'csv') {
            return $this->parse_csv($path);
        }
        if ($ext === 'json') {
            return $this->parse_json($path);
        }
        if ($ext === 'xlsx') {
            return $this->parse_xlsx($path);
        }
        return array();
    }

    private function parse_csv($path) {
        $rows = array();
        $fs = $this->filesystem();
        $content = $fs ? $fs->get_contents($path) : '';
        $handle = fopen('php://temp', 'r+'); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- in-memory stream for fgetcsv parsing
        if ($content !== '') {
            fwrite($handle, $content); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- in-memory stream for fgetcsv parsing
            rewind($handle);
        }
        if (!$handle) return $rows;
        $header = null;
        while (($data = fgetcsv($handle, 0, ',')) !== false) {
            if (!$header) {
                $header = $data;
                continue;
            }
            $row = array();
            foreach ($header as $i => $key) {
                $row[$this->normalize_header($key, $i)] = $data[$i] ?? '';
            }
            $rows[] = $row;
        }
        fclose($handle); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- in-memory stream for fgetcsv parsing
        return $rows;
    }

    private function parse_json($path) {
        $fs = $this->filesystem();
        $contents = $fs ? $fs->get_contents($path) : '';
        return $this->parse_json_content($contents);
    }

    private function fetch_demo_rows() {
        $response = wp_remote_get(self::DEMO_DATA_URL, array('timeout' => 20));
        if (is_wp_error($response)) {
            return array();
        }
        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 300) {
            return array();
        }
        $body = (string) wp_remote_retrieve_body($response);
        return $this->parse_json_content($body);
    }

    private function parse_json_content($contents) {
        $json = json_decode($contents, true);
        if (isset($json['products']) && is_array($json['products'])) {
            return $json['products'];
        }
        if (is_array($json)) {
            $list = array();
            foreach ($json as $key => $val) {
                if (is_array($val)) {
                    $val['external_id'] = $key;
                    $list[] = $val;
                }
            }
            return $list ? $list : $json;
        }
        return array();
    }

    private function parse_xlsx($path) {
        $rows = array();
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) return $rows;
        $shared = array();
        $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedXml) {
            $sx = simplexml_load_string($sharedXml);
            if ($sx && isset($sx->si)) {
                foreach ($sx->si as $si) {
                    $shared[] = (string)$si->t;
                }
            }
        }
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        if (!$sheetXml) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if (strpos($name, 'xl/worksheets/sheet') === 0) {
                    $sheetXml = $zip->getFromName($name);
                    break;
                }
            }
        }
        $zip->close();
        if (!$sheetXml) return $rows;
        $sx = simplexml_load_string($sheetXml);
        if (!$sx || !isset($sx->sheetData->row)) return $rows;
        $header = null;
        foreach ($sx->sheetData->row as $row) {
            $cells = array();
            foreach ($row->c as $c) {
                $v = (string)$c->v;
                $t = (string)$c['t'];
                if ($t === 's') {
                    $v = $shared[(int)$v] ?? $v;
                }
                $cells[] = $v;
            }
            if (!$header) {
                $header = $cells;
                continue;
            }
            $assoc = array();
            foreach ($header as $i => $key) {
                $assoc[$this->normalize_header($key, $i)] = $cells[$i] ?? '';
            }
            $rows[] = $assoc;
        }
        return $rows;
    }

    private function normalize_header($key, $index) {
        $key = trim((string)$key);
        if ($key === '') {
            return 'col_' . $index;
        }
        $key = strtolower($key);
        $key = preg_replace('/\s+/', '_', $key);
        return preg_replace('/[^a-z0-9_]/', '', $key);
    }

    private function normalize_rows($rows) {
        $list = array();
        $i = 0;
        foreach ($rows as $row) {
            $i++;
            $name = $row['name'] ?? $row['product_name'] ?? '';
            $sku = $row['sku'] ?? $row['product_sku'] ?? '';
            $price = isset($row['price']) ? floatval($row['price']) : (isset($row['regular_price']) ? floatval($row['regular_price']) : 0);
            $stock = $row['stock_status'] ?? $row['stock'] ?? 'instock';
            $categories = $this->string_to_array($row['categories'] ?? '');
            $image = $row['image'] ?? $row['image_url'] ?? '';
            $list[] = array(
                'row_id' => (string)$i,
                'name' => $name,
                'sku' => $sku,
                'price' => $price,
                'stock_status' => $stock,
                'categories' => $categories,
                'image' => $image,
                'raw' => $row
            );
        }
        return $list;
    }

    private function string_to_array($value) {
        if (is_array($value)) return $value;
        $value = trim((string)$value);
        if ($value === '') return array();
        $json = json_decode($value, true);
        if (is_array($json)) return $json;
        return array_map('trim', explode(',', $value));
    }

    private function read_session_rows($session_path) {
        $fs = $this->filesystem();
        if (!$fs || !$fs->exists($session_path)) return array();
        $json = $fs->get_contents($session_path);
        $rows = json_decode($json, true);
        return is_array($rows) ? $rows : array();
    }

    /**
     * Unserialize import blobs with classes disabled when supported (PHP 7+).
     *
     * @param string $str Serialized string.
     * @return mixed Decoded value or false on failure / non-serialized input.
     */
    private function unserialize_import_payload($str) {
        if (!is_string($str) || '' === $str) {
            return false;
        }
        if (function_exists('is_serialized') && !is_serialized($str)) {
            return false;
        }
        if (version_compare(PHP_VERSION, '7.0.0', '>=')) {
            return @unserialize($str, array('allowed_classes' => false)); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize -- Decoding trusted legacy Storelly dashboard export payloads during Global Import only.
        }
        return @unserialize($str); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize -- Decoding trusted legacy Storelly dashboard export payloads during Global Import only.
    }

    /**
     * Fix the first broken s:N:"..." segment where N does not match the closing quote (common with old UTF-8 / DB exports).
     *
     * @param string $s Serialized blob.
     * @return string Same string if nothing was fixed, otherwise a corrected blob.
     */
    private function repair_next_broken_serialized_length($s) {
        if (!is_string($s) || '' === $s) {
            return $s;
        }
        $len = strlen($s);
        $pos = 0;
        $max_candidates = 200;
        while ($pos < $len) {
            $spos = strpos($s, 's:', $pos);
            if (false === $spos) {
                return $s;
            }
            $i = $spos + 2;
            $num_str = '';
            while ($i < $len && ctype_digit($s[$i])) {
                $num_str .= $s[$i];
                $i++;
            }
            if ('' === $num_str || $i >= $len || ':' !== $s[$i]) {
                $pos = $spos + 2;
                continue;
            }
            $i++;
            if ($i >= $len || '"' !== $s[$i]) {
                $pos = $spos + 2;
                continue;
            }
            $i++;
            $q_start = $i;
            $declared = (int) $num_str;
            $after_declared = $q_start + $declared;
            // A genuine s:N:"..."; segment always closes with a quote immediately followed by ';'.
            // Requiring the ';' prevents treating a literal '"' inside the string content (e.g. an
            // HTML attribute quote) as a valid boundary when the declared length is wrong.
            if ($after_declared + 1 < $len && '"' === $s[$after_declared] && ';' === $s[$after_declared + 1]) {
                $pos = $after_declared + 2;
                continue;
            }
            $head = substr($s, 0, $spos);
            $candidates = array();
            for ($j = $q_start; $j < $len - 1; $j++) {
                if ('"' === $s[$j] && ';' === $s[$j + 1]) {
                    $candidates[] = $j;
                }
            }
            usort(
                $candidates,
                static function ($a, $b) use ($q_start, $declared) {
                    $da = abs($a - $q_start - $declared);
                    $db = abs($b - $q_start - $declared);
                    if ($da === $db) {
                        return $a - $b;
                    }
                    return ($da < $db) ? -1 : 1;
                }
            );
            $candidates = array_slice($candidates, 0, $max_candidates);
            foreach ($candidates as $j) {
                $body = substr($s, $q_start, $j - $q_start);
                $tail = substr($s, $j + 2);
                $trial = $head . 's:' . strlen($body) . ':"' . $body . '";' . $tail;
                $un = $this->unserialize_import_payload($trial);
                if (is_array($un) || is_object($un)) {
                    return $trial;
                }
            }
            return $s;
        }
        return $s;
    }

    private function decode_remote_payload_to_array($payload, &$debug_lines, $payload_type) {
        if (is_array($payload)) {
            return $payload;
        }
        if (is_object($payload)) {
            return (array) $payload;
        }
        $payload = is_string($payload) ? $payload : '';
        $payload = preg_replace('/^\xEF\xBB\xBF/', '', $payload);
        $payload = trim($payload);
        if ('' === $payload) {
            $debug_lines[] = sprintf('%s payload is empty.', $payload_type);
            return array();
        }
        $unserialized = maybe_unserialize($payload);
        if (is_array($unserialized)) {
            $debug_lines[] = sprintf('%s payload decoded via maybe_unserialize.', $payload_type);
            return $unserialized;
        }
        if (is_object($unserialized)) {
            $debug_lines[] = sprintf('%s payload decoded as object via maybe_unserialize.', $payload_type);
            return (array) $unserialized;
        }
        $json = json_decode($payload, true);
        if (is_array($json)) {
            $debug_lines[] = sprintf('%s payload decoded via json_decode.', $payload_type);
            return $json;
        }
        if (is_string($json)) {
            $json_unserialized = maybe_unserialize($json);
            if (is_array($json_unserialized)) {
                $debug_lines[] = sprintf('%s payload decoded via json + maybe_unserialize.', $payload_type);
                return $json_unserialized;
            }
            if (is_object($json_unserialized)) {
                $debug_lines[] = sprintf('%s payload decoded as object via json + maybe_unserialize.', $payload_type);
                return (array) $json_unserialized;
            }
        }
        $current = $payload;
        for ($pass = 0; $pass < 80; $pass++) {
            $repaired = $this->unserialize_import_payload($current);
            if (is_array($repaired)) {
                $debug_lines[] = sprintf(
                    '%s payload decoded via legacy serialize repair (%d pass(es)).',
                    $payload_type,
                    $pass
                );
                return $repaired;
            }
            if (is_object($repaired)) {
                $debug_lines[] = sprintf(
                    '%s payload decoded as object via legacy serialize repair (%d pass(es)).',
                    $payload_type,
                    $pass
                );
                return (array) $repaired;
            }
            $next = $this->repair_next_broken_serialized_length($current);
            if ($next === $current) {
                break;
            }
            $current = $next;
        }
        $deep = $this->deep_unserialize($payload);
        if (is_array($deep)) {
            $debug_lines[] = sprintf('%s payload decoded via deep_unserialize.', $payload_type);
            return $deep;
        }
        if (is_object($deep)) {
            $debug_lines[] = sprintf('%s payload decoded as object via deep_unserialize.', $payload_type);
            return (array) $deep;
        }
        if (function_exists('mb_convert_encoding')) {
            $latin = mb_convert_encoding($payload, 'UTF-8', 'ISO-8859-1');
            if ($latin !== $payload) {
                $latin_try = maybe_unserialize($latin);
                if (is_array($latin_try)) {
                    $debug_lines[] = sprintf('%s payload decoded after ISO-8859-1 to UTF-8 reassignment.', $payload_type);
                    return $latin_try;
                }
                if (is_object($latin_try)) {
                    $debug_lines[] = sprintf('%s payload decoded as object after ISO-8859-1 to UTF-8 reassignment.', $payload_type);
                    return (array) $latin_try;
                }
            }
        }
        $debug_lines[] = sprintf('%s payload decode failed. Sample: %s', $payload_type, substr($payload, 0, 140));
        return array();
    }

    private function deep_unserialize($value) {
        if (is_string($value)) {
            $un = @unserialize($value); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize -- Needed to decode nested serialized strings from legacy export format.
            if (false !== $un && $un !== $value) {
                return $this->deep_unserialize($un);
            }
            $un2 = maybe_unserialize($value);
            if ($un2 !== $value && (is_array($un2) || is_object($un2))) {
                return $this->deep_unserialize($un2);
            }
            return $value;
        }
        if (is_object($value)) {
            $value = (array) $value;
        }
        if (is_array($value)) {
            foreach ($value as $k => $v) {
                $value[$k] = $this->deep_unserialize($v);
            }
        }
        return $value;
    }

    private function import_print_options_for_product($product_id, $row_id, $raw, &$warnings, &$debug_lines) {
        if (empty($raw['print_options'])) {
            $debug_lines[] = 'Row ' . $row_id . ': print options URL not provided.';
            return;
        }
        $print_options_url = (string) $raw['print_options'];
        $debug_lines[] = 'Row ' . $row_id . ': fetching print options from ' . $print_options_url;
        $path = wp_parse_url($print_options_url, PHP_URL_PATH);
        $ext = $path ? strtolower(pathinfo($path, PATHINFO_EXTENSION)) : '';
        $po_fetch = $this->adapter->fetch_remote($print_options_url, 'json' === $ext);
        if (!$po_fetch['ok']) {
            $warnings[] = sprintf(
                /* translators: 1: row identifier, 2: WooCommerce product ID, 3: failure reason */
                __('Row %1$s: print options for product #%2$d skipped (%3$s)', 'storelly-product-builder-for-woocommerce'),
                $row_id,
                $product_id,
                $po_fetch['message']
            );
            $debug_lines[] = 'Row ' . $row_id . ': print options fetch ' . $po_fetch['reason'];
            return;
        }
        $print_options_str = $po_fetch['body'];
        if ('json' === $ext) {
            $debug_lines[] = 'Row ' . $row_id . ': detected print options JSON payload.';
            $print_options_data = $this->decode_remote_payload_to_array($print_options_str, $debug_lines, 'print_options');
        } elseif ('txt' === $ext) {
            $debug_lines[] = 'Row ' . $row_id . ': detected print options TXT (serialized) payload.';
            $print_options_data = $this->deep_unserialize($print_options_str);
            if (!is_array($print_options_data)) {
                $debug_lines[] = 'Row ' . $row_id . ': TXT deep_unserialize failed, falling back to auto decode.';
                $print_options_data = $this->decode_remote_payload_to_array($print_options_str, $debug_lines, 'print_options');
            } else {
                $debug_lines[] = 'Row ' . $row_id . ': TXT payload decoded successfully via deep_unserialize.';
            }
        } else {
            $debug_lines[] = 'Row ' . $row_id . ': print options extension not detected, using auto decode.';
            $print_options_data = $this->decode_remote_payload_to_array($print_options_str, $debug_lines, 'print_options');
        }
        if (empty($print_options_data)) {
            $warnings[] = sprintf(
                /* translators: 1: row identifier from the import payload, 2: WooCommerce product ID */
                __('Row %1$s: print options payload is invalid for product #%2$d', 'storelly-product-builder-for-woocommerce'),
                $row_id,
                $product_id
            );
            return;
        }
        $print_option_result = $this->adapter->create_or_update_print_option($product_id, $print_options_data);
        if (!is_array($print_option_result) || empty($print_option_result['success'])) {
            $warnings[] = sprintf(
                /* translators: 1: row identifier, 2: WooCommerce product ID, 3: error message returned by the print-options save call */
                __('Row %1$s: failed to save print options for product #%2$d. %3$s', 'storelly-product-builder-for-woocommerce'),
                $row_id,
                $product_id,
                isset($print_option_result['message']) ? $print_option_result['message'] : __('Unknown error', 'storelly-product-builder-for-woocommerce')
            );
            return;
        }
        $debug_lines[] = sprintf(
            'Row %1$s: print options saved for product #%2$d (option_id=%3$d).',
            $row_id,
            $product_id,
            isset($print_option_result['option_id']) ? (int) $print_option_result['option_id'] : 0
        );
    }

    private function import_row($row, $overwrite = false, $target_id = 0) {
        $raw = $row['raw'];
        $sku = $row['sku'];
        $external_id = $raw['external_id'] ?? $raw['id'] ?? '';
        $warnings = array();
        $debug_lines = array();
        $existing_id = $target_id > 0 ? (int) $target_id : ($sku ? wc_get_product_id_by_sku($sku) : 0);
        if (!$existing_id && $external_id) {
            $existing = get_posts(array(
                'post_type' => 'product',
                'meta_key' => '_spbwc_external_id',
                'meta_value' => $external_id,
                'fields' => 'ids',
                'posts_per_page' => 1
            ));
            if (!empty($existing)) {
                $existing_id = $existing[0];
            }
        }
        if (!empty($raw['settings'])) {
            $settings_fetch = $this->adapter->fetch_remote($raw['settings']);
            if (!$settings_fetch['ok']) {
                $debug_lines[] = 'Row ' . $row['row_id'] . ': settings fetch ' . $settings_fetch['reason'] . ' - ' . $settings_fetch['message'];
            }
            $data = $this->decode_remote_payload_to_array($settings_fetch['body'], $debug_lines, 'settings');
            if (empty($data)) {
                return array(
                    'success' => false,
                    'message' => sprintf(
                        /* translators: 1: row identifier from the import payload, 2: failure reason */
                        __('Row %1$s: product settings could not be loaded (%2$s)', 'storelly-product-builder-for-woocommerce'),
                        $row['row_id'],
                        $settings_fetch['ok'] ? __('payload is invalid', 'storelly-product-builder-for-woocommerce') : $settings_fetch['message']
                    ),
                    'warnings' => $warnings,
                    'debug' => $debug_lines,
                );
            }
            if ($existing_id) {
                $product_id = $existing_id;
                if ($overwrite) {
                    $this->adapter->update_product($product_id, $data);
                }
            } else {
                $product_id = $this->adapter->add_product($data);
            }
            if ($overwrite && $product_id) {
                $this->adapter->delete_print_options_for_product($product_id);
            }
            $this->import_print_options_for_product($product_id, $row['row_id'], $raw, $warnings, $debug_lines);
            if (!empty($raw['templates'])) {
                $templates_fetch = $this->adapter->fetch_remote($raw['templates'], true);
                if ($templates_fetch['ok']) {
                    $templates = json_decode($templates_fetch['body'], true);
                    if (is_array($templates)) {
                        $this->adapter->add_templates($templates, $external_id ?: $row['row_id'], $product_id);
                    }
                } else {
                    $warnings[] = sprintf(
                        /* translators: 1: row identifier, 2: failure reason */
                        __('Row %1$s: design templates skipped (%2$s)', 'storelly-product-builder-for-woocommerce'),
                        $row['row_id'],
                        $templates_fetch['message']
                    );
                    $debug_lines[] = 'Row ' . $row['row_id'] . ': templates fetch ' . $templates_fetch['reason'];
                }
            }
        } else {
            $product = $existing_id ? wc_get_product($existing_id) : new WC_Product();
            $product->set_name($row['name']);
            $product->set_status('publish');
            if (!empty($raw['description'])) $product->set_description($raw['description']);
            if (!empty($raw['short_description'])) $product->set_short_description($raw['short_description']);
            if (!empty($raw['sale_price'])) $product->set_sale_price($raw['sale_price']);
            if (!empty($raw['regular_price'])) $product->set_regular_price($raw['regular_price']);
            if (!empty($row['price']) && empty($raw['regular_price'])) $product->set_regular_price($row['price']);
            if (!empty($raw['stock_status'])) $product->set_stock_status($raw['stock_status']);
            if (isset($raw['stock_quantity'])) $product->set_stock_quantity(intval($raw['stock_quantity']));
            if ($sku) $product->set_sku($sku);
            $product_id = $product->save();
            if ($overwrite && $product_id) {
                $this->adapter->delete_print_options_for_product($product_id);
            }
            $this->import_print_options_for_product($product_id, $row['row_id'], $raw, $warnings, $debug_lines);
        }
        if ($external_id) {
            update_post_meta($product_id, '_spbwc_external_id', $external_id);
        }
        if (!empty($row['categories'])) {
            $term_ids = array();
            foreach ($row['categories'] as $cat) {
                if (!$cat) continue;
                $term = term_exists($cat, 'product_cat');
                if (!$term) {
                    $term = wp_insert_term($cat, 'product_cat');
                }
                if (!is_wp_error($term)) {
                    $term_ids[] = (int)$term['term_id'];
                }
            }
            if ($term_ids) {
                wp_set_object_terms($product_id, $term_ids, 'product_cat');
            }
        }
        if (!empty($raw['tags'])) {
            $tags = $this->string_to_array($raw['tags']);
            if ($tags) {
                wp_set_object_terms($product_id, $tags, 'product_tag');
            }
        }
        if (!empty($row['image'])) {
            $image_id = $this->adapter->add_attachment_from_url($row['image']);
            if ($image_id) {
                set_post_thumbnail($product_id, $image_id);
            }
        }
        if (!empty($raw['gallery'])) {
            $gallery_urls = $this->string_to_array($raw['gallery']);
            $gallery_ids = array();
            foreach ($gallery_urls as $url) {
                $gid = $this->adapter->add_attachment_from_url($url);
                if ($gid) $gallery_ids[] = $gid;
            }
            if ($gallery_ids) {
                update_post_meta($product_id, '_product_image_gallery', implode(',', $gallery_ids));
            }
        }
        if (!empty($raw['meta'])) {
            $meta = is_array($raw['meta']) ? $raw['meta'] : json_decode($raw['meta'], true);
            if (is_array($meta)) {
                foreach ($meta as $k => $v) {
                    update_post_meta($product_id, sanitize_key($k), $v);
                }
            }
        }
        $this->record_import_status($product_id, $row, $raw, $external_id, $warnings);
        do_action('spbwc_global_import_product_saved', $product_id, $row);
        return array(
            'success' => true,
            'data' => array(
                'row_id' => $row['row_id'],
                'product_id' => $product_id,
                'sku' => $sku
            ),
            'warnings' => $warnings,
            'debug' => $debug_lines,
        );
    }

    /**
     * Store the import outcome and the original source URLs on the product so the admin can see
     * which products imported with problems and re-import (overwrite) them from source later.
     *
     * @param int    $product_id  WooCommerce product ID.
     * @param array  $row         Normalized row.
     * @param array  $raw         Raw row payload.
     * @param string $external_id Source external identifier.
     * @param array  $warnings    Warnings collected during this row's import.
     */
    private function record_import_status($product_id, $row, $raw, $external_id, $warnings) {
        if (!$product_id) {
            return;
        }
        $source = array(
            'settings'      => isset($raw['settings']) ? (string) $raw['settings'] : '',
            'print_options' => isset($raw['print_options']) ? (string) $raw['print_options'] : '',
            'templates'     => isset($raw['templates']) ? (string) $raw['templates'] : '',
            'image'         => isset($row['image']) ? (string) $row['image'] : '',
            'external_id'   => (string) $external_id,
            'row_id'        => (string) $row['row_id'],
            'name'          => isset($row['name']) ? (string) $row['name'] : '',
        );
        update_post_meta($product_id, '_spbwc_import_source', wp_json_encode($source));
        update_post_meta($product_id, '_spbwc_import_status', empty($warnings) ? 'ok' : 'partial');
        update_post_meta($product_id, '_spbwc_import_warnings', wp_json_encode(array_values($warnings)));
        update_post_meta($product_id, '_spbwc_import_date', current_time('mysql'));
    }

    private function build_product_payload($product_id) {
        $product = wc_get_product($product_id);
        if (!$product) return array();
        $data = array(
            'id' => $product->get_id(),
            'name' => $product->get_name(),
            'sku' => $product->get_sku(),
            'type' => $product->get_type(),
            'status' => $product->get_status(),
            'price' => $product->get_price(),
            'regular_price' => $product->get_regular_price(),
            'sale_price' => $product->get_sale_price(),
            'stock_status' => $product->get_stock_status(),
            'stock_quantity' => $product->get_stock_quantity(),
            'description' => $product->get_description(),
            'short_description' => $product->get_short_description(),
            'categories' => wp_get_post_terms($product_id, 'product_cat', array('fields' => 'names')),
            'tags' => wp_get_post_terms($product_id, 'product_tag', array('fields' => 'names')),
            'images' => array(
                'featured' => wp_get_attachment_url($product->get_image_id()),
                'gallery' => array_map('wp_get_attachment_url', $product->get_gallery_image_ids())
            ),
            'attributes' => array(),
            'variations' => array(),
            'meta' => get_post_meta($product_id)
        );
        foreach ($product->get_attributes() as $attr) {
            $data['attributes'][] = array(
                'name' => $attr->get_name(),
                'visible' => $attr->get_visible(),
                'variation' => $attr->get_variation(),
                'options' => $attr->get_options()
            );
        }
        if ($product->is_type('variable')) {
            foreach ($product->get_children() as $variation_id) {
                $variation = wc_get_product($variation_id);
                if (!$variation) continue;
                $data['variations'][] = array(
                    'id' => $variation_id,
                    'sku' => $variation->get_sku(),
                    'price' => $variation->get_price(),
                    'regular_price' => $variation->get_regular_price(),
                    'sale_price' => $variation->get_sale_price(),
                    'stock_status' => $variation->get_stock_status(),
                    'stock_quantity' => $variation->get_stock_quantity(),
                    'attributes' => $variation->get_attributes(),
                    'image' => wp_get_attachment_url($variation->get_image_id())
                );
            }
        }
        return $data;
    }

    private function append_log($import_id, $processed, $errors, $debug_lines = array()) {
        $this->ensure_dirs();
        $fs = $this->filesystem();
        if (!$fs) return;
        $path = $this->log_dir() . $import_id . '.log';
        $lines = array();
        foreach ($processed as $p) {
            $lines[] = 'BATCH_COMPLETE ' . wp_json_encode($p);
        }
        foreach ($debug_lines as $debug) {
            $lines[] = 'DEBUG ' . $debug;
        }
        foreach ($errors as $e) {
            $lines[] = 'ERROR ' . $e;
        }
        $content = implode("\n", $lines) . "\n";
        
        // Append to file
        if ($fs->exists($path)) {
            $existing = $fs->get_contents($path);
            $content = $existing . $content;
        }
        $fs->put_contents($path, $content);
    }

    private function log_line($import_id, $message) {
        $this->ensure_dirs();
        $fs = $this->filesystem();
        if (!$fs) return;
        $path = $this->log_dir() . $import_id . '.log';
        $line = gmdate('[H:i:s] ') . $message . "\n";
        
        $content = '';
        if ($fs->exists($path)) {
            $content = $fs->get_contents($path);
        }
        $content .= $line;
        $fs->put_contents($path, $content);
    }
}


function spbwc_global_import_controller_init() {
    SPBWC_Global_Import_Controller::instance()->init();
}
add_action('init', 'spbwc_global_import_controller_init');
