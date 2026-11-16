<?php
/**
 * Standalone regression test for the 1.9.1 security fix: unauthenticated visitors
 * must not overwrite product template designs through
 * wp_ajax_nopriv_spbwc_save_product_builder_design.
 *
 * Run: php tests/test-save-design-auth.php   (no WordPress needed; WP functions stubbed)
 */
define('ABSPATH', __DIR__ . '/');
$tmp = sys_get_temp_dir() . '/spbwc-test-' . getmypid();
@mkdir($tmp . '/designs', 0777, true);
define('SPBWC_PB_CUSTOMER_DIR', $tmp . '/designs');

class SPBWC_Test_Response extends Exception { public $payload; public $status; }
class SPBWC_Test_Saved extends Exception { public $path; }

$GLOBALS['caps'] = array();
$GLOBALS['session'] = array();
$GLOBALS['db_updates'] = 0;

function wp_unslash($v) { return $v; }
function sanitize_text_field($v) { return trim((string) $v); }
function absint($v) { return abs((int) $v); }
function __($t, $d = null) { return $t; }
function wp_verify_nonce($n, $a) { return 'public-nonce' === $n; }
function do_action() {}
function add_action() {}
function is_admin() { return false; }
function add_filter() {}
function current_user_can($c) { return in_array($c, $GLOBALS['caps'], true); }
function wp_generate_password($len) { return substr(str_shuffle('abcdefghijklmnopqrstuvwxyz0123456789'), 0, $len); }
function wp_handle_upload() {}
function wp_mkdir_p($p) { $e = new SPBWC_Test_Saved('saved'); $e->path = $p; throw $e; }
function wp_send_json_error($payload, $status = null) { $e = new SPBWC_Test_Response('json'); $e->payload = $payload; $e->status = $status; throw $e; }
function wp_send_json($payload) { $e = new SPBWC_Test_Response('json'); $e->payload = $payload; $e->status = 200; throw $e; }

class SPBWC_Test_Session {
    public function get($k) { return isset($GLOBALS['session'][$k]) ? $GLOBALS['session'][$k] : null; }
    public function set($k, $v) { $GLOBALS['session'][$k] = $v; }
    public function has_session() { return true; }
}
class SPBWC_Test_WC { public $session; public $cart = null; public function __construct() { $this->session = new SPBWC_Test_Session(); } }
function WC() { static $wc; if (!$wc) { $wc = new SPBWC_Test_WC(); } return $wc; }

require dirname(__DIR__) . '/includes/class-product-builder-frontend.php';
$handler = (new ReflectionClass('SPBWC_Storelly_Product_Builder_Frontend'))->newInstanceWithoutConstructor();

function run_save($handler, array $post) {
    $_POST = $post + array('nonce' => 'public-nonce');
    $_FILES = array();
    try {
        $handler->spbwc_save_product_builder_design();
    } catch (SPBWC_Test_Response $r) {
        return array('status' => $r->status, 'payload' => $r->payload);
    } catch (SPBWC_Test_Saved $s) {
        return array('status' => 'saved', 'folder' => basename($s->path));
    }
    return array('status' => 'none');
}

$fail = 0;
function check($name, $cond) { global $fail; echo ($cond ? 'PASS ' : 'FAIL ') . $name . PHP_EOL; if (!$cond) { $fail++; } }

// Product template folder that already exists on disk.
mkdir(SPBWC_PB_CUSTOMER_DIR . '/tpl1234567890');

// 1. Guest asks to write a product template -> 403.
$GLOBALS['caps'] = array();
$r = run_save($handler, array('is_creating_task' => '1', 'oid' => '5', 'pcpb_item_pb_key' => 'tpl1234567890'));
check('guest is_creating_task is refused with 403', 403 === $r['status']);

// 2. Guest posts the template key without is_creating_task -> saved into a NEW folder.
$r = run_save($handler, array('pcpb_item_pb_key' => 'tpl1234567890'));
check('guest cannot overwrite an existing template folder', 'saved' === $r['status'] && 'tpl1234567890' !== $r['folder']);

// 3. Same via prcpb_folder.
$r = run_save($handler, array('prcpb_folder' => 'tpl1234567890'));
check('guest cannot overwrite via prcpb_folder', 'saved' === $r['status'] && 'tpl1234567890' !== $r['folder']);

// 4. Path tricks are refused.
$r = run_save($handler, array('pcpb_item_pb_key' => '../../wp-config'));
check('path traversal key refused', 400 === $r['status']);

// 5. Guest with no key gets a fresh folder (normal buyer flow).
$r = run_save($handler, array());
check('guest new design is saved', 'saved' === $r['status'] && '' !== $r['folder']);

// 6. Buyer re-saves a folder their own session created -> same folder.
mkdir(SPBWC_PB_CUSTOMER_DIR . '/mine12345');
$GLOBALS['session']['spbwc_design_folders'] = array('mine12345');
$r = run_save($handler, array('pcpb_item_pb_key' => 'mine12345'));
check('owner re-save keeps the folder', 'saved' === $r['status'] && 'mine12345' === $r['folder']);

// 7. Admin can write templates.
$GLOBALS['caps'] = array('manage_woocommerce');
$r = run_save($handler, array('is_creating_task' => '1', 'oid' => '5', 'pcpb_item_pb_key' => 'tpl1234567890'));
check('admin template save allowed', 'saved' === $r['status'] && 'tpl1234567890' === $r['folder']);

// Cleanup.
array_map('rmdir', glob(SPBWC_PB_CUSTOMER_DIR . '/*'));
@rmdir(SPBWC_PB_CUSTOMER_DIR); @rmdir($tmp);
echo $fail ? "FAILED: $fail\n" : "ALL PASS\n";
exit($fail ? 1 : 0);
