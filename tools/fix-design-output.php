<?php
/**
 * One-off repair: normalize design_output in all option rows.
 *
 * Background: AngularJS's ngModel injected hidden "? string:XX ?" marker
 * <option> elements when dimension_unit did not match a known unit. FormData(form)
 * then submitted the marker text, which got serialized into the fields blob,
 * and re-nested on every subsequent save.
 *
 * Run inside the wp_app container:
 *   docker exec wp_app php /var/www/html/wp-content/plugins/storelly-product-builder-woo/tools/fix-design-output.php
 */

$mysqli = new mysqli('wp_mysql', 'wpuser', 'wppass', 'wordpress');
if ($mysqli->connect_error) {
    fwrite(STDERR, "Connect error: " . $mysqli->connect_error . "\n");
    exit(1);
}

$res = $mysqli->query("SELECT id, title, fields FROM wp_storelly_product_builder_options ORDER BY id");
$allowed = array('cm', 'in', 'mm', 'px');
$fixed = 0;

while ($row = $res->fetch_assoc()) {
    $data = @unserialize($row['fields']);
    if (!is_array($data)) {
        continue;
    }
    $needs_fix = false;
    $before    = null;

    if (!isset($data['design_output']) || !is_array($data['design_output'])) {
        $before = isset($data['design_output']) ? var_export($data['design_output'], true) : '(missing)';
        $data['design_output'] = array('dpi' => 300, 'dimension_unit' => 'px');
        $needs_fix = true;
    } else {
        $u = isset($data['design_output']['dimension_unit']) ? $data['design_output']['dimension_unit'] : '';
        if (!in_array($u, $allowed, true)) {
            $before = 'dimension_unit=' . var_export($u, true);
            $data['design_output']['dimension_unit'] = 'px';
            $needs_fix = true;
        }
        $d = isset($data['design_output']['dpi']) ? (int) $data['design_output']['dpi'] : 0;
        if ($d <= 0) {
            $before = ($before ? $before . ' ; ' : '') . 'dpi=' . var_export($data['design_output']['dpi'] ?? null, true);
            $data['design_output']['dpi'] = 300;
            $needs_fix = true;
        }
    }

    if ($needs_fix) {
        $serial = serialize($data);
        $stmt   = $mysqli->prepare("UPDATE wp_storelly_product_builder_options SET fields = ? WHERE id = ?");
        $stmt->bind_param('si', $serial, $row['id']);
        $stmt->execute();
        echo "Fixed row id={$row['id']} title=\"{$row['title']}\" | before: {$before}\n";
        $fixed++;
    }
}

echo "Total fixed: {$fixed}\n";
