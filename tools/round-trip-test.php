<?php
/**
 * Round-trip test harness — simulates the v3 / classic / VB form save by
 * stuffing $_POST and invoking SPBWC_Storelly_PB_Admin_Options::spbwc_save_option().
 *
 * Each scenario:
 *   1. Captures the current row "shape" (counts of fields, views, attrs,
 *      quantity breaks, design output, display mode, blob md5).
 *   2. Builds a fake POST payload that mirrors what the browser would send.
 *   3. Calls the save handler.
 *   4. Captures the shape again and compares.
 *
 * Run via:
 *   docker exec wp_app wp eval-file \
 *     /var/www/html/wp-content/plugins/storelly-product-builder-woo/tools/round-trip-test.php \
 *     --allow-root --path=/var/www/html
 *
 * Output is also dumped to /tmp/spbwc_roundtrip_report.json so callers can
 * read it without fighting wrapper layers that eat stdout.
 *
 * NOTE: This is a developer-only tool. NOT shipped with the plugin to
 * end users.
 *
 * @package Storelly_Product_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
    // Allow direct invocation through wp-cli (which boots WP first).
    if ( ! defined( 'WPINC' ) ) {
        fwrite( STDERR, "ERROR: must be run via `wp eval-file`\n" );
        exit( 1 );
    }
}

global $wpdb;

/* -----------------------------------------------------------------------
 * Helpers.
 * --------------------------------------------------------------------- */

/**
 * Snapshot the current shape of a row.
 */
function spbwc_rt_shape( $oid ) {
    global $wpdb;
    $row = $wpdb->get_row( $wpdb->prepare(
        "SELECT id, title, apply_for, product_ids, product_cats, modified, fields
         FROM {$wpdb->prefix}storelly_product_builder_options WHERE id=%d",
        $oid
    ), ARRAY_A );
    if ( ! $row ) {
        return array( '_missing' => true );
    }
    $d = maybe_unserialize( $row['fields'] );
    $nbpb_titles  = array();
    $price_titles = array();
    $attrs        = 0;
    $first_price_attr = null;
    foreach ( ( isset( $d['fields'] ) && is_array( $d['fields'] ) ? $d['fields'] : array() ) as $f ) {
        $t_raw = isset( $f['general']['title'] ) ? $f['general']['title'] : '?';
        $t     = is_array( $t_raw ) ? ( isset( $t_raw['value'] ) ? $t_raw['value'] : '?' ) : $t_raw;
        if ( ! empty( $f['nbpb_type'] ) ) {
            $nbpb_titles[] = $t;
        } else {
            $price_titles[] = $t;
        }
        $opts = isset( $f['general']['attributes']['options'] ) && is_array( $f['general']['attributes']['options'] )
            ? $f['general']['attributes']['options'] : array();
        $attrs += count( $opts );
        if ( null === $first_price_attr && empty( $f['nbpb_type'] ) && ! empty( $opts ) ) {
            $first_price_attr = isset( $opts[0]['name'] ) ? $opts[0]['name'] : null;
        }
    }
    return array(
        'id'              => (int) $row['id'],
        'row_title'       => $row['title'],
        'apply_for'       => $row['apply_for'],
        'modified'        => $row['modified'],
        'views'           => isset( $d['views'] ) ? count( $d['views'] ) : 0,
        'view_names'      => isset( $d['views'] ) ? array_map(
            function ( $v ) { return isset( $v['name'] ) ? $v['name'] : '?'; },
            $d['views']
        ) : array(),
        'total_fields'    => isset( $d['fields'] ) ? count( $d['fields'] ) : 0,
        'nbpb_fields'     => count( $nbpb_titles ),
        'nbpb_titles'     => $nbpb_titles,
        'pricing_fields'  => count( $price_titles ),
        'pricing_titles'  => $price_titles,
        'total_attrs'     => $attrs,
        'first_price_attr_name' => $first_price_attr,
        'quantity_breaks' => isset( $d['quantity_breaks'] ) ? count( $d['quantity_breaks'] ) : 0,
        'design_dpi'      => isset( $d['design_output']['dpi'] ) ? (int) $d['design_output']['dpi'] : null,
        'design_unit'     => isset( $d['design_output']['dimension_unit'] ) ? $d['design_output']['dimension_unit'] : null,
        'display_mode'    => isset( $d['display_mode'] ) ? $d['display_mode'] : null,
        'blob_md5'        => md5( $row['fields'] ),
    );
}

/**
 * Build a $_POST payload mirroring what v3 / classic / VB form submits.
 * Defaults to no-op (echoes current row).
 *
 * @param int   $oid       Option id.
 * @param array $overrides Optional partial overrides, deep-merged.
 */
function spbwc_rt_build_post( $oid, $overrides = array() ) {
    global $wpdb;
    $row = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}storelly_product_builder_options WHERE id=%d",
        $oid
    ), ARRAY_A );
    if ( ! $row ) {
        return null;
    }
    $d            = maybe_unserialize( $row['fields'] );
    $product_ids  = maybe_unserialize( $row['product_ids'] );
    $product_cats = maybe_unserialize( $row['product_cats'] );

    // Mirror the v3 form fields: name="title", name="apply_for", etc.
    $post = array(
        'title'        => isset( $overrides['title'] ) ? $overrides['title'] : $row['title'],
        'apply_for'    => isset( $overrides['apply_for'] ) ? $overrides['apply_for'] : $row['apply_for'],
        'product_ids'  => isset( $overrides['product_ids'] ) ? $overrides['product_ids'] : (array) $product_ids,
        'product_cats' => isset( $overrides['product_cats'] ) ? $overrides['product_cats'] : (array) $product_cats,
        'options'      => array(
            'version'      => isset( $d['version'] ) ? $d['version'] : '1.0',
            'title'        => isset( $overrides['title'] ) ? $overrides['title'] : $row['title'],
            'display_mode' => isset( $overrides['display_mode'] ) ? $overrides['display_mode']
                                : ( isset( $d['display_mode'] ) ? $d['display_mode'] : 'sections' ),
            // Quantity block.
            'quantity_enable'        => isset( $d['quantity_enable'] ) ? $d['quantity_enable'] : 'n',
            'quantity_type'          => isset( $d['quantity_type'] ) ? $d['quantity_type'] : 'r',
            'quantity_min'           => isset( $d['quantity_min'] ) ? $d['quantity_min'] : 1,
            'quantity_max'           => isset( $d['quantity_max'] ) ? $d['quantity_max'] : 100,
            'quantity_step'          => isset( $d['quantity_step'] ) ? $d['quantity_step'] : 1,
            'quantity_discount_type' => isset( $d['quantity_discount_type'] ) ? $d['quantity_discount_type'] : 'p',
            'quantity_breaks'        => isset( $d['quantity_breaks'] ) ? $d['quantity_breaks'] : array(),
            // Design output (Visual Builder territory but kept in round-trip).
            'design_output'          => isset( $d['design_output'] ) ? $d['design_output']
                                            : array( 'dpi' => 300, 'dimension_unit' => 'px' ),
            // Round-trip hatch: jsonFields is what AngularJS getJsonFields()
            // shoves into the hidden input on save. The browser does
            // JSON.stringify(scope.options.fields) after cleansing wrapper
            // .value sub-keys. PHP-side we just JSON-encode the current
            // fields[] verbatim — the save handler will json_decode it
            // back to the same shape.
            'jsonFields'             => wp_json_encode( isset( $d['fields'] ) ? $d['fields'] : array() ),
            // Views also round-trip via the form.
            'views'                  => isset( $d['views'] ) ? $d['views'] : array(),
        ),
    );

    // Allow nested overrides for options[*].
    if ( isset( $overrides['options'] ) && is_array( $overrides['options'] ) ) {
        foreach ( $overrides['options'] as $k => $v ) {
            $post['options'][ $k ] = $v;
        }
    }

    // Caller can also pass `_fields_override` to mutate the JSON-encoded
    // fields[] directly (e.g. rename an attribute).
    if ( isset( $overrides['_fields_override'] ) && is_array( $overrides['_fields_override'] ) ) {
        $post['options']['jsonFields'] = wp_json_encode( $overrides['_fields_override'] );
    }

    return $post;
}

/**
 * Invoke the save handler with a stuffed $_POST.
 */
function spbwc_rt_save( $oid, $post ) {
    $_POST = $post;
    $admin = SPBWC_Storelly_PB_Admin_Options::instance();
    return $admin->spbwc_save_option( $oid );
}

/**
 * Compare two shapes; return diff with PASS/FAIL.
 */
function spbwc_rt_compare( $before, $after, $expected_changes = array() ) {
    $cmp_keys = array(
        'views', 'total_fields', 'nbpb_fields', 'pricing_fields',
        'total_attrs', 'quantity_breaks', 'design_dpi', 'design_unit',
        'display_mode', 'view_names', 'nbpb_titles', 'pricing_titles',
        'row_title', 'apply_for', 'first_price_attr_name',
    );
    $diffs = array();
    foreach ( $cmp_keys as $k ) {
        $b = isset( $before[ $k ] ) ? $before[ $k ] : null;
        $a = isset( $after[ $k ] ) ? $after[ $k ] : null;
        if ( is_array( $b ) || is_array( $a ) ) {
            if ( wp_json_encode( $b ) !== wp_json_encode( $a ) ) {
                $diffs[ $k ] = array( 'before' => $b, 'after' => $a );
            }
        } elseif ( $b !== $a ) {
            $diffs[ $k ] = array( 'before' => $b, 'after' => $a );
        }
    }
    // Filter out diffs that were expected.
    $unexpected = array();
    foreach ( $diffs as $k => $diff ) {
        if ( isset( $expected_changes[ $k ] ) ) {
            if ( $expected_changes[ $k ] === $diff['after']
                 || ( is_array( $expected_changes[ $k ] )
                      && wp_json_encode( $expected_changes[ $k ] ) === wp_json_encode( $diff['after'] ) ) ) {
                continue;
            }
        }
        $unexpected[ $k ] = $diff;
    }
    return array(
        'pass'       => empty( $unexpected ),
        'diffs'      => $diffs,
        'unexpected' => $unexpected,
    );
}

/* -----------------------------------------------------------------------
 * Scenario runner.
 * --------------------------------------------------------------------- */

$report = array();
$bag_id = 8;

// Establish a non-zero admin user so spbwc_save_option's cap check passes.
$admin_user = get_user_by( 'login', 'admin' );
if ( $admin_user ) {
    wp_set_current_user( $admin_user->ID );
} else {
    fwrite( STDERR, "WARN: admin user not found, save handler may fail capability check\n" );
}

/* ---------- Scenario A — v3 no-op save ---------- */
$before_a = spbwc_rt_shape( $bag_id );
$post_a   = spbwc_rt_build_post( $bag_id );
$result_a = spbwc_rt_save( $bag_id, $post_a );
$after_a  = spbwc_rt_shape( $bag_id );
$cmp_a    = spbwc_rt_compare( $before_a, $after_a );
$report['A_v3_noop'] = array(
    'description' => 'v3 no-op save → structural identity',
    'save_result' => $result_a,
    'before'      => $before_a,
    'after'       => $after_a,
    'compare'     => $cmp_a,
    'verdict'     => $cmp_a['pass'] ? 'PASS' : 'FAIL',
);

/* ---------- Scenario B — Classic-style mutation ---------- */
// Rename the first pricing field's first attribute name (the "+$0.20" option).
$before_b = spbwc_rt_shape( $bag_id );
$row_b    = $GLOBALS['wpdb']->get_var( $GLOBALS['wpdb']->prepare(
    "SELECT fields FROM {$GLOBALS['wpdb']->prefix}storelly_product_builder_options WHERE id=%d", $bag_id ) );
$d_b = maybe_unserialize( $row_b );
$renamed_original = null;
if ( isset( $d_b['fields'] ) && is_array( $d_b['fields'] ) ) {
    foreach ( $d_b['fields'] as $fi => $f ) {
        if ( ! empty( $f['nbpb_type'] ) ) {
            continue; // skip visual fields
        }
        if ( ! empty( $f['general']['attributes']['options'][0]['name'] ) ) {
            $renamed_original = $d_b['fields'][ $fi ]['general']['attributes']['options'][0]['name'];
            $d_b['fields'][ $fi ]['general']['attributes']['options'][0]['name'] = 'Rename via classic';
            break;
        }
    }
}
$post_b = spbwc_rt_build_post( $bag_id, array(
    '_fields_override' => $d_b['fields'],
) );
$result_b = spbwc_rt_save( $bag_id, $post_b );
$after_b  = spbwc_rt_shape( $bag_id );
$cmp_b = spbwc_rt_compare( $before_b, $after_b, array(
    'first_price_attr_name' => 'Rename via classic',
) );
$report['B_classic_mutate'] = array(
    'description'      => 'Classic-style mutation (rename first pricing attribute) → propagates without losing structure',
    'renamed_from'     => $renamed_original,
    'renamed_to'       => 'Rename via classic',
    'save_result'      => $result_b,
    'before'           => $before_b,
    'after'            => $after_b,
    'compare'          => $cmp_b,
    'verdict'          => $cmp_b['pass'] ? 'PASS' : 'FAIL',
);

/* ---------- Scenario C — v3 changes title + display_mode ---------- */
$before_c = spbwc_rt_shape( $bag_id );
$post_c   = spbwc_rt_build_post( $bag_id, array(
    'title'        => 'BAG (round-trip test)',
    'display_mode' => 'matrix',
) );
$result_c = spbwc_rt_save( $bag_id, $post_c );
$after_c  = spbwc_rt_shape( $bag_id );
$cmp_c = spbwc_rt_compare( $before_c, $after_c, array(
    'row_title'    => 'BAG (round-trip test)',
    'display_mode' => 'matrix',
) );
$report['C_v3_title_mode'] = array(
    'description' => 'v3 changes title + display_mode → row_title and display_mode update; everything else unchanged',
    'save_result' => $result_c,
    'before'      => $before_c,
    'after'       => $after_c,
    'compare'     => $cmp_c,
    'verdict'     => $cmp_c['pass'] ? 'PASS' : 'FAIL',
);

/* ---------- Scenario D — VB-style edit (touch views only) ---------- */
// Add a marker name suffix to view 0 → simulates VB renaming a view.
$before_d = spbwc_rt_shape( $bag_id );
$d_d = maybe_unserialize( $GLOBALS['wpdb']->get_var( $GLOBALS['wpdb']->prepare(
    "SELECT fields FROM {$GLOBALS['wpdb']->prefix}storelly_product_builder_options WHERE id=%d", $bag_id ) ) );
$views_d  = isset( $d_d['views'] ) ? $d_d['views'] : array();
$original_v0_name = isset( $views_d[0]['name'] ) ? $views_d[0]['name'] : null;
if ( ! empty( $views_d ) ) {
    $views_d[0]['name'] = $original_v0_name . ' VB';
}
$post_d = spbwc_rt_build_post( $bag_id, array(
    'options' => array( 'views' => $views_d ),
) );
$result_d = spbwc_rt_save( $bag_id, $post_d );
$after_d  = spbwc_rt_shape( $bag_id );
$expected_d_view_names = $after_d['view_names'];
$cmp_d = spbwc_rt_compare( $before_d, $after_d, array(
    'view_names' => $expected_d_view_names, // accepted via passthrough — we check separately below.
) );
$d_view_rename_ok = ( ! empty( $expected_d_view_names[0] ) && $expected_d_view_names[0] === $original_v0_name . ' VB' );
$report['D_vb_view_rename'] = array(
    'description'            => 'VB-style edit (rename view 0) → view_names[0] suffix gains " VB"; fields unchanged',
    'view0_renamed_from'     => $original_v0_name,
    'view0_renamed_to'       => $original_v0_name . ' VB',
    'view0_after_save'       => isset( $after_d['view_names'][0] ) ? $after_d['view_names'][0] : null,
    'save_result'            => $result_d,
    'before'                 => $before_d,
    'after'                  => $after_d,
    'compare'                => $cmp_d,
    'verdict'                => ( $cmp_d['pass'] && $d_view_rename_ok ) ? 'PASS' : 'FAIL',
);

/* ---------- Scenario E — Create new option from v3 ---------- */
$before_count_e = (int) $GLOBALS['wpdb']->get_var(
    "SELECT COUNT(*) FROM {$GLOBALS['wpdb']->prefix}storelly_product_builder_options" );
// Build a minimal new-option POST (id=0 → insert).
$post_e = array(
    'title'        => 'RT_TEST new option',
    'apply_for'    => 'p',
    'product_ids'  => array(),
    'product_cats' => array(),
    'options'      => array(
        'title'        => 'RT_TEST new option',
        'display_mode' => 'sections',
        'quantity_enable' => 'n',
        'design_output'   => array( 'dpi' => 300, 'dimension_unit' => 'px' ),
        'jsonFields'      => wp_json_encode( array(
            array(
                'id'      => 'f' . round( microtime( true ) * 1000 ),
                'general' => array(
                    'title' => array( 'value' => 'Material' ),
                    'attributes' => array(
                        'options' => array(
                            array( 'name' => 'Leather' ),
                            array( 'name' => 'Cotton' ),
                        ),
                    ),
                ),
            ),
        ) ),
        'views'           => array(),
    ),
);
$result_e = spbwc_rt_save( 0, $post_e );
$new_id   = isset( $result_e['id'] ) ? (int) $result_e['id'] : 0;
$after_e  = $new_id > 0 ? spbwc_rt_shape( $new_id ) : array( '_failed' => true );
$after_count_e = (int) $GLOBALS['wpdb']->get_var(
    "SELECT COUNT(*) FROM {$GLOBALS['wpdb']->prefix}storelly_product_builder_options" );
$e_pass = ( $new_id > 0 && isset( $after_e['total_fields'] ) && $after_e['total_fields'] === 1
            && $after_e['row_title'] === 'RT_TEST new option' );
$report['E_create_new'] = array(
    'description'    => 'Create new option via v3-style POST → row inserted with single Material field',
    'count_before'   => $before_count_e,
    'count_after'    => $after_count_e,
    'new_id'         => $new_id,
    'save_result'    => $result_e,
    'shape_after'    => $after_e,
    'verdict'        => $e_pass ? 'PASS' : 'FAIL',
);
// Roll back: delete the new row so the dev DB doesn't accumulate junk.
if ( $new_id > 0 ) {
    $GLOBALS['wpdb']->delete(
        "{$GLOBALS['wpdb']->prefix}storelly_product_builder_options",
        array( 'id' => $new_id )
    );
    $report['E_create_new']['rolled_back_id'] = $new_id;
}

/* ---------- Restore BAG to its pre-test title + display_mode ---------- */
// Scenarios B/C/D mutated BAG. Put it back via one final save matching the
// snapshot we took at the top of Scenario A.
$restore_post = spbwc_rt_build_post( $bag_id, array(
    'title'        => $before_a['row_title'],
    'display_mode' => $before_a['display_mode'],
) );
// Also restore the first pricing attribute name we renamed in B.
if ( $renamed_original ) {
    $d_restore = maybe_unserialize( $GLOBALS['wpdb']->get_var( $GLOBALS['wpdb']->prepare(
        "SELECT fields FROM {$GLOBALS['wpdb']->prefix}storelly_product_builder_options WHERE id=%d", $bag_id ) ) );
    if ( isset( $d_restore['fields'] ) ) {
        foreach ( $d_restore['fields'] as $fi => $f ) {
            if ( empty( $f['nbpb_type'] )
                && isset( $d_restore['fields'][ $fi ]['general']['attributes']['options'][0]['name'] )
                && $d_restore['fields'][ $fi ]['general']['attributes']['options'][0]['name'] === 'Rename via classic' ) {
                $d_restore['fields'][ $fi ]['general']['attributes']['options'][0]['name'] = $renamed_original;
                break;
            }
        }
    }
    // Restore view0 name too.
    if ( ! empty( $d_restore['views'] ) && ! empty( $d_restore['views'][0]['name'] ) ) {
        $d_restore['views'][0]['name'] = preg_replace( '/ VB$/', '', $d_restore['views'][0]['name'] );
    }
    $restore_post = spbwc_rt_build_post( $bag_id, array(
        'title'            => $before_a['row_title'],
        'display_mode'     => $before_a['display_mode'],
        '_fields_override' => $d_restore['fields'],
        'options'          => array( 'views' => $d_restore['views'] ),
    ) );
}
spbwc_rt_save( $bag_id, $restore_post );
$final_shape = spbwc_rt_shape( $bag_id );
$report['_restore'] = array(
    'description' => 'Restore BAG to pre-test state',
    'final_shape' => $final_shape,
    'restored_ok' => ( $final_shape['row_title']     === $before_a['row_title']
                       && $final_shape['display_mode'] === $before_a['display_mode']
                       && $final_shape['total_fields'] === $before_a['total_fields']
                       && $final_shape['nbpb_fields']  === $before_a['nbpb_fields']
                       && $final_shape['total_attrs']  === $before_a['total_attrs'] ),
);

/* ---------- Summary ---------- */
$verdicts = array();
foreach ( $report as $key => $r ) {
    if ( isset( $r['verdict'] ) ) {
        $verdicts[ $key ] = $r['verdict'];
    }
}
$all_pass = ! in_array( 'FAIL', $verdicts, true );
$report['_summary'] = array(
    'verdicts' => $verdicts,
    'all_pass' => $all_pass,
);

$json = wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
echo $json;
file_put_contents( '/tmp/spbwc_roundtrip_report.json', $json );
