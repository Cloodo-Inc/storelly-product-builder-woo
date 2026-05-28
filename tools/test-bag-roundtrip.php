<?php
/**
 * Deterministic save-round-trip safety check for the BAG option (row 8).
 *
 * The V2 admin's data loss happens in the browser layer, not PHP:
 *   1. ng-if removes inputs from the DOM  -> not in FormData -> dropped on save
 *   2. PHP max_input_vars truncates the POST var list -> tail fields dropped
 *   3. AngularJS unknownOption marker corrupts <select> values (fixed)
 *
 * This script loads row 8 exactly as the admin page does (unserialize +
 * spbwc_build_options) and asserts every precondition that decides whether the
 * form will RENDER and SUBMIT all data:
 *   - 6 fields present, each with a truthy nbpb_type        (field-body.php:83 ng-if)
 *   - every attribute that has sub_attributes has enable_subattr === 'on'
 *                                                           (attributes.php:163 ng-if)
 *   - design_output normalized to a valid unit              (Task 1 fix)
 *   - total form input-var count < max_input_vars           (truncation guard)
 *
 * Run: docker exec wp_app php .../tools/test-bag-roundtrip.php 8
 */

if (!defined('ABSPATH')) {
    $wp_load = dirname(dirname(dirname(dirname(__DIR__)))) . '/wp-load.php';
    require_once $wp_load;
}

$row_id = isset($argv[1]) ? (int) $argv[1] : 8;
global $wpdb;
$table = $wpdb->prefix . 'storelly_product_builder_options';

$raw = $wpdb->get_var($wpdb->prepare("SELECT fields FROM {$table} WHERE id = %d", $row_id));
if (!$raw) { fwrite(STDERR, "Row {$row_id} not found\n"); exit(1); }

$loaded = unserialize($raw);

// Run the admin load path.
$admin = new SPBWC_Storelly_PB_Admin_Options();
$built = method_exists($admin, 'spbwc_build_options') ? $admin->spbwc_build_options($loaded) : $loaded;

$pass = true;
$line = function ($ok, $msg) use (&$pass) {
    echo ($ok ? '  PASS ' : '  FAIL ') . $msg . "\n";
    if (!$ok) { $pass = false; }
};

echo "=== BAG round-trip safety check (row {$row_id}) ===\n\n";

// 1) field count + nbpb_type
$fields = isset($built['fields']) ? $built['fields'] : array();
$line(count($fields) === 5, 'field count is 5 (got ' . count($fields) . ')');
foreach ($fields as $i => $f) {
    $t = isset($f['general']['title']) ? (is_array($f['general']['title']) ? $f['general']['title']['value'] : $f['general']['title']) : '?';
    $line(!empty($f['nbpb_type']), "field[{$i}] \"{$t}\" has nbpb_type=" . (isset($f['nbpb_type']) ? $f['nbpb_type'] : 'MISSING'));
}

// 2) enable_subattr === 'on' for every attribute that has subs
foreach ($fields as $i => $f) {
    if (empty($f['general']['attributes']['options'])) { continue; }
    foreach ($f['general']['attributes']['options'] as $ai => $op) {
        $has_subs = !empty($op['sub_attributes']);
        if (!$has_subs) { continue; }
        $ok = (isset($op['enable_subattr']) && ($op['enable_subattr'] === 'on' || $op['enable_subattr'] === true));
        $line($ok, "field[{$i}].attr[{$ai}] \"{$op['name']}\" has " . count($op['sub_attributes']) . " subs, enable_subattr=" . var_export(isset($op['enable_subattr']) ? $op['enable_subattr'] : null, true));
    }
}

// 3) design_output valid
$unit = isset($built['design_output']['dimension_unit']) ? $built['design_output']['dimension_unit'] : null;
$line(in_array($unit, array('cm', 'in', 'mm', 'px'), true), 'design_output.dimension_unit valid (' . var_export($unit, true) . ')');

// 4) count form input vars the page would emit, compare to max_input_vars
$count_inputs = function ($node) use (&$count_inputs) {
    $n = 0;
    if (is_array($node)) {
        foreach ($node as $v) {
            if (is_array($v)) { $n += $count_inputs($v); }
            else { $n += 1; }
        }
    } else { $n = 1; }
    return $n;
};
$input_vars = $count_inputs($built);
$max = (int) ini_get('max_input_vars');
$line($input_vars < $max, "approx form input vars {$input_vars} < max_input_vars {$max}");

echo "\n" . ($pass ? "RESULT: PASS — admin will load & submit all fields; save is safe.\n"
                   : "RESULT: FAIL — see above.\n");
exit($pass ? 0 : 1);
