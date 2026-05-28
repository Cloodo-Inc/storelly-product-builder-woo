<?php
/**
 * Write the restored BAG blob (restore_fields.json) into row id=8.
 *
 * The JSON was assembled by build_restore.js: row-8's correct fields 0-3 +
 * views + top-level, with HANDLES prices patched, HANDLES Leather/Black overlay
 * fixed, and STRAP FABRIC (field 4) + Text (field 5) rebuilt from the Printcart
 * source with images remapped to existing attachments.
 *
 * Run (WordPress bootstrapped):
 *   docker exec wp_app php /var/www/html/wp-content/plugins/storelly-product-builder-woo/tools/write-restored-row.php /tmp/restore_fields.json 8
 */

if (!defined('ABSPATH')) {
    $wp_load = dirname(dirname(dirname(dirname(__DIR__)))) . '/wp-load.php';
    if (!file_exists($wp_load)) { fwrite(STDERR, "wp-load.php not found at {$wp_load}\n"); exit(1); }
    require_once $wp_load;
}

$json_path = isset($argv[1]) ? $argv[1] : '/tmp/restore_fields.json';
$row_id    = isset($argv[2]) ? (int) $argv[2] : 8;

if (!file_exists($json_path)) { fwrite(STDERR, "JSON not found: {$json_path}\n"); exit(1); }
$blob = json_decode(file_get_contents($json_path), true);
if (!is_array($blob) || empty($blob['fields'])) { fwrite(STDERR, "Invalid restore JSON\n"); exit(1); }

global $wpdb;
$table = $wpdb->prefix . 'storelly_product_builder_options';

// Confirm the target row exists.
$exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE id = %d", $row_id));
if (!$exists) { fwrite(STDERR, "Row id={$row_id} not found\n"); exit(1); }

$serialized = serialize($blob);
$now = (new DateTime())->format('Y-m-d H:i:s');

$updated = $wpdb->update(
    $table,
    array('fields' => $serialized, 'modified' => $now),
    array('id' => $row_id),
    array('%s', '%s'),
    array('%d')
);

if ($updated === false) { fwrite(STDERR, "UPDATE failed: {$wpdb->last_error}\n"); exit(1); }

// Flush caches so the admin/frontend re-read the row.
$product_ids = array();
$pid_raw = $wpdb->get_var($wpdb->prepare("SELECT product_ids FROM {$table} WHERE id = %d", $row_id));
$pids = maybe_unserialize($pid_raw);
if (is_array($pids)) { foreach ($pids as $pid) { $product_ids[] = (int) $pid; } }

if (class_exists('SPBWC_Storelly_Admin_Options') && method_exists('SPBWC_Storelly_Admin_Options', 'instance')) {
    // best-effort: clear via known transients/object cache below
}
foreach ($product_ids as $pid) {
    delete_transient('spbwc_product_builder_' . $pid);
}
wp_cache_flush();

echo "Row {$row_id} updated. fields bytes=" . strlen($serialized) . " (was rows modified={$updated})\n";
echo "Products affected: " . implode(',', $product_ids) . "\n";

// Verify read-back.
$back = $wpdb->get_var($wpdb->prepare("SELECT fields FROM {$table} WHERE id = %d", $row_id));
$re   = unserialize($back);
echo "Verify fields count: " . count($re['fields']) . "\n";
foreach ($re['fields'] as $i => $f) {
    $t = is_array($f['general']['title']) ? $f['general']['title']['value'] : $f['general']['title'];
    echo "  [{$i}] {$f['nbpb_type']} \"{$t}\"\n";
}
echo "design_output: " . json_encode($re['design_output']) . "\n";
echo "Done.\n";
