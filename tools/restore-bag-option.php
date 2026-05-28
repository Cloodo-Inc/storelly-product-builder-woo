<?php
/**
 * Restore the BAG option (product 129) from the original Printcart options.json.
 *
 * The current row id=8 lost 2 fields (STRAP FABRIC + Text), all HANDLES prices,
 * and many metadata wrappers — caused by historical max_input_vars=1000, the
 * AngularJS ngModel unknownOption marker recursion (fixed in this commit), and
 * V2 admin templates that don't render every config row.
 *
 * This script re-runs the existing Printcart import adapter on the source JSON.
 * The adapter:
 *  - downloads & remaps every image URL to a Storelly attachment ID
 *  - collapses {title, value, type} config wrappers to plain values
 *  - preserves all 6 fields, prices, sub_attributes, pb_config
 *
 * A NEW row is inserted (cannot UPDATE — adapter is insert-only). The product's
 * _spbwc_option_id post meta is repointed to the new row. The old row 8 is left
 * intact as a backup; the user can drop it after verification.
 *
 * Run inside the wp_app container, bootstrapping WordPress:
 *   docker exec -e SCRIPT_FILENAME=/var/www/html/index.php wp_app \
 *     php -d auto_prepend_file=/var/www/html/wp-load.php \
 *     /var/www/html/wp-content/plugins/storelly-product-builder-woo/tools/restore-bag-option.php
 */

if (!defined('ABSPATH')) {
    // Allow direct CLI invocation by manually bootstrapping WordPress.
    $wp_load = dirname(dirname(dirname(dirname(__DIR__)))) . '/wp-load.php';
    if (!file_exists($wp_load)) {
        fwrite(STDERR, "wp-load.php not found at {$wp_load}\n");
        exit(1);
    }
    require_once $wp_load;
}

$source_json_path = isset($argv[1]) ? $argv[1] : '/tmp/options.json';
$target_product_id = isset($argv[2]) ? (int) $argv[2] : 129;
$preserve_old_row_id = 8;

if (!file_exists($source_json_path)) {
    fwrite(STDERR, "Source JSON not found at: {$source_json_path}\n");
    exit(1);
}

if ($target_product_id <= 0 || !get_post($target_product_id)) {
    fwrite(STDERR, "Invalid target product ID: {$target_product_id}\n");
    exit(1);
}

$raw_json = file_get_contents($source_json_path);
$data     = json_decode($raw_json, true);

if (!is_array($data)) {
    fwrite(STDERR, "Failed to decode JSON: " . json_last_error_msg() . "\n");
    exit(1);
}

echo "Source: {$source_json_path}\n";
echo "Target product: {$target_product_id}\n";
echo "Source fields: " . count($data['fields']) . "\n";
echo "Source views:  " . (isset($data['views']) ? count($data['views']) : 0) . "\n\n";

if (!class_exists('SPBWC_Printcart_Import_Adapter')) {
    fwrite(STDERR, "SPBWC_Printcart_Import_Adapter class not loaded — plugin not active?\n");
    exit(1);
}

echo "Running importer (this may take a while — media downloads)...\n";
$adapter = new SPBWC_Printcart_Import_Adapter();
$result  = $adapter->create_or_update_print_option($target_product_id, $data);

echo "\nImporter result:\n";
echo "  success: " . ($result['success'] ? 'true' : 'false') . "\n";
echo "  option_id: " . $result['option_id'] . "\n";
if (!empty($result['message'])) {
    echo "  message: " . $result['message'] . "\n";
}

if (!$result['success'] || empty($result['option_id'])) {
    fwrite(STDERR, "\nImport failed — leaving DB unchanged.\n");
    exit(1);
}

$new_option_id = (int) $result['option_id'];

// Verify the new row has all fields restored.
global $wpdb;
$row = $wpdb->get_row(
    $wpdb->prepare(
        "SELECT id, title, fields FROM {$wpdb->prefix}storelly_product_builder_options WHERE id = %d",
        $new_option_id
    ),
    ARRAY_A
);

if (!$row) {
    fwrite(STDERR, "Could not read back new row id={$new_option_id}\n");
    exit(1);
}

$saved = unserialize($row['fields']);
$fields_count = isset($saved['fields']) && is_array($saved['fields']) ? count($saved['fields']) : 0;
$views_count  = isset($saved['views']) && is_array($saved['views']) ? count($saved['views']) : 0;

echo "\nNew row id={$new_option_id} title=\"{$row['title']}\"\n";
echo "  fields stored: {$fields_count}\n";
echo "  views stored:  {$views_count}\n";

if (isset($saved['fields'])) {
    foreach ($saved['fields'] as $idx => $field) {
        $title = '';
        if (isset($field['general']['title'])) {
            $title = is_array($field['general']['title'])
                ? (isset($field['general']['title']['value']) ? $field['general']['title']['value'] : '')
                : $field['general']['title'];
        }
        $attrs = isset($field['general']['attributes']['options']) ? count($field['general']['attributes']['options']) : 0;
        $type  = isset($field['nbpb_type']) ? $field['nbpb_type'] : '?';
        echo "    [{$idx}] id={$field['id']} type={$type} attrs={$attrs} title=\"{$title}\"\n";
    }
}

echo "\nProduct {$target_product_id} _spbwc_option_id now: " . get_post_meta($target_product_id, '_spbwc_option_id', true) . "\n";
echo "Old row id={$preserve_old_row_id} left intact as backup.\n";
echo "\nDone.\n";
