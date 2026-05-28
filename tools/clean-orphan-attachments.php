<?php
/**
 * Clean orphaned attachments created by the interrupted restore import.
 *
 * The first restore run (killed mid-way) downloaded ~147 images into the media
 * library before being stopped, creating duplicates like black-1.jpg. None are
 * referenced by any option row (the import never wrote its row).
 *
 * Safety: only deletes attachments that are
 *   (a) created in the kill window [2026-05-28 04:49:00 .. 2026-05-28 05:00:00], AND
 *   (b) NOT referenced by any wp_storelly_product_builder_options.fields blob, AND
 *   (c) NOT used as a WooCommerce product thumbnail / gallery image.
 *
 * Pass --dry-run (default) to only report. Pass --delete to actually remove.
 *
 * Run:
 *   docker exec wp_app php .../tools/clean-orphan-attachments.php --dry-run
 *   docker exec wp_app php .../tools/clean-orphan-attachments.php --delete
 */

if (!defined('ABSPATH')) {
    require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/wp-load.php';
}

$do_delete = in_array('--delete', $argv, true);
$win_start = '2026-05-28 04:49:00';
$win_end   = '2026-05-28 05:00:00';

global $wpdb;

// Candidate attachments in the kill window.
$candidates = $wpdb->get_results($wpdb->prepare(
    "SELECT ID, post_title, post_date FROM {$wpdb->posts}
     WHERE post_type = 'attachment' AND post_date >= %s AND post_date < %s
     ORDER BY ID",
    $win_start, $win_end
), ARRAY_A);

echo "Window {$win_start} .. {$win_end}\n";
echo "Candidate attachments: " . count($candidates) . "\n";

// Gather every attachment ID referenced by any option row's fields blob.
$referenced = array();
$rows = $wpdb->get_results("SELECT id, fields FROM {$wpdb->prefix}storelly_product_builder_options", ARRAY_A);
foreach ($rows as $r) {
    $blob = @unserialize($r['fields']);
    if (!is_array($blob)) { continue; }
    array_walk_recursive($blob, function ($v) use (&$referenced) {
        if (is_numeric($v) && (int) $v > 0) { $referenced[(int) $v] = true; }
    });
}
echo "Distinct attachment IDs referenced by option rows: " . count($referenced) . "\n";

// Also protect WooCommerce product images (_thumbnail_id + _product_image_gallery).
$wc_protected = array();
$thumbs = $wpdb->get_col("SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_thumbnail_id'");
foreach ($thumbs as $t) { if ((int) $t > 0) { $wc_protected[(int) $t] = true; } }
$galleries = $wpdb->get_col("SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_product_image_gallery'");
foreach ($galleries as $g) {
    foreach (explode(',', (string) $g) as $gid) { if ((int) $gid > 0) { $wc_protected[(int) $gid] = true; } }
}

$to_delete = array();
$kept = array();
foreach ($candidates as $c) {
    $id = (int) $c['ID'];
    if (isset($referenced[$id]) || isset($wc_protected[$id])) {
        $kept[] = $id;
    } else {
        $to_delete[] = $id;
    }
}

echo "Protected (referenced/used): " . count($kept) . "\n";
echo "Orphaned -> deletable: " . count($to_delete) . "\n";
if ($kept) { echo "  Kept IDs: " . implode(',', $kept) . "\n"; }

if (!$do_delete) {
    echo "\n[DRY RUN] No changes made. Re-run with --delete to remove the "
        . count($to_delete) . " orphaned attachments.\n";
    echo "Sample deletable IDs: " . implode(',', array_slice($to_delete, 0, 20)) . (count($to_delete) > 20 ? ' ...' : '') . "\n";
    exit(0);
}

$deleted = 0;
foreach ($to_delete as $id) {
    $res = wp_delete_attachment($id, true); // true = bypass trash, delete files
    if ($res) { $deleted++; }
}
echo "\nDeleted {$deleted} / " . count($to_delete) . " orphaned attachments (files removed).\n";
echo "Done.\n";
