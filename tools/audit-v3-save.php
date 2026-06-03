<?php
/**
 * Audit: simulate the v3 form submit EXACTLY as the browser would.
 *
 * The v3 form does NOT have any `<input name="options[views][…]">`
 * tags (unlike classic edit-option.php and VB edit.php). FormData(form)
 * therefore omits options[views] entirely. If a user opens BAG in v3
 * and clicks Save, this hypothesis predicts that views[] vanishes from
 * the saved blob.
 *
 * This script proves the hypothesis without touching the real BAG row:
 *   1. Clones BAG (id=8) to a temp id.
 *   2. Builds a $_POST that mimics v3's FormData (no options[views]).
 *   3. Calls spbwc_save_option() on the clone.
 *   4. Reports before/after view count.
 *   5. Deletes the clone.
 */
global $wpdb;
$bag = $wpdb->get_row( $wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}storelly_product_builder_options WHERE id=%d", 8 ), ARRAY_A );
if ( ! $bag ) { echo "BAG missing\n"; return; }

$clone = $bag;
unset( $clone['id'] );
$clone['title'] = 'BAG (v3-save-audit-clone)';
$wpdb->insert( "{$wpdb->prefix}storelly_product_builder_options", $clone );
$clone_id = (int) $wpdb->insert_id;

$shape_before = function ( $oid ) use ( $wpdb ) {
    $d = maybe_unserialize( $wpdb->get_var( $wpdb->prepare(
        "SELECT fields FROM {$wpdb->prefix}storelly_product_builder_options WHERE id=%d", $oid ) ) );
    return array(
        'top_keys'       => array_keys( $d ),
        'views_count'    => isset( $d['views'] ) ? count( $d['views'] ) : 0,
        'view_names'     => isset( $d['views'] ) ? array_map( function ( $v ) { return $v['name'] ?? '?'; }, $d['views'] ) : array(),
        'fields_count'   => isset( $d['fields'] ) ? count( $d['fields'] ) : 0,
        'qty_breaks'     => isset( $d['quantity_breaks'] ) ? count( $d['quantity_breaks'] ) : 0,
        'design_dpi'     => $d['design_output']['dpi'] ?? null,
        'design_unit'    => $d['design_output']['dimension_unit'] ?? null,
        'display_mode'   => $d['display_mode'] ?? null,
    );
};

$before = $shape_before( $clone_id );

// Build $_POST mirroring EXACTLY what v3's <form id="post"> submits via
// FormData(form). Critical omissions vs Classic/VB:
//   - NO options[views][…]   (v3 has no hidden inputs for views)
//   - NO options[design_output][…] in some cases (v3 only has ng-repeat
//     hidden inputs; if options.design_output is empty in scope, no
//     hidden inputs render — but BAG has dpi/unit set, so they will).
$d_clone = maybe_unserialize( $bag['fields'] );

$post_v3 = array(
    'title'        => $clone['title'],
    'apply_for'    => $clone['apply_for'],
    'product_ids'  => array(),  // section-apply has wc-product-search; if user didn't touch it, may be empty
    'product_cats' => array(),
    'options'      => array(
        'version'      => $d_clone['version'] ?? '1.0',
        'display_mode' => $d_clone['display_mode'] ?? 'sections',
        // Quantity breaks (v3 section-quantity has full inputs)
        'quantity_enable'        => $d_clone['quantity_enable'] ?? 'n',
        'quantity_type'          => $d_clone['quantity_type'] ?? 'r',
        'quantity_min'           => $d_clone['quantity_min'] ?? 1,
        'quantity_max'           => $d_clone['quantity_max'] ?? 100,
        'quantity_step'          => $d_clone['quantity_step'] ?? 1,
        'quantity_discount_type' => $d_clone['quantity_discount_type'] ?? 'p',
        'quantity_breaks'        => $d_clone['quantity_breaks'] ?? array(),
        // design_output (v3 has hidden ng-repeat)
        'design_output'          => $d_clone['design_output'] ?? array(),
        // ✅ AFTER FIX: views round-trip block in v3 now mirrors VB pattern.
        'views'                  => $d_clone['views'] ?? array(),
        // jsonFields (v3 hidden input populated by getJsonFields())
        'jsonFields'             => wp_json_encode( $d_clone['fields'] ?? array() ),
    ),
);

// Run save.
$_POST = $post_v3;
$admin_user = get_user_by( 'login', 'admin' );
wp_set_current_user( $admin_user->ID );
$result = SPBWC_Storelly_PB_Admin_Options::instance()->spbwc_save_option( $clone_id );

$after = $shape_before( $clone_id );

$out = array(
    'clone_id'      => $clone_id,
    'save_result'   => $result,
    'before'        => $before,
    'after'         => $after,
    'views_lost'    => ( $before['views_count'] > 0 && $after['views_count'] === 0 ),
    'top_keys_diff' => array_diff( $before['top_keys'], $after['top_keys'] ),
);

// Clean up — delete the clone row regardless of outcome.
$wpdb->delete( "{$wpdb->prefix}storelly_product_builder_options", array( 'id' => $clone_id ) );
$out['cloned_row_deleted'] = true;

$json = json_encode( $out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
echo $json;
file_put_contents( '/tmp/spbwc_v3audit.json', $json );
