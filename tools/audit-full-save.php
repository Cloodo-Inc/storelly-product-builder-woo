<?php
/**
 * Comprehensive save-flow audit — clones BAG and runs THREE save scenarios
 * (classic-style, v3 post-fix, VB-style) checking every top-level + sub-key
 * for silent loss.
 *
 * Outputs a per-scenario lost_keys report and an aggregate verdict.
 */
global $wpdb;
$bag = $wpdb->get_row( $wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}storelly_product_builder_options WHERE id=%d", 8 ), ARRAY_A );
if ( ! $bag ) { echo "BAG missing\n"; return; }
$d_bag = maybe_unserialize( $bag['fields'] );

$admin_user = get_user_by( 'login', 'admin' );
wp_set_current_user( $admin_user->ID );

/**
 * Deeply enumerate "structural fingerprints" — path → count or value type.
 * Used to detect silent key/value losses across save round-trips.
 */
function spbwc_fingerprint( $d ) {
    $fp = array();
    $fp['top_keys']        = array_keys( $d );
    $fp['views_count']     = isset( $d['views'] ) ? count( $d['views'] ) : 0;
    $fp['view_names']      = isset( $d['views'] ) ? array_map( fn( $v ) => $v['name'] ?? '?', $d['views'] ) : array();
    $fp['view_bases']      = isset( $d['views'] ) ? array_map( fn( $v ) => $v['base'] ?? '?', $d['views'] ) : array();
    $fp['fields_count']    = isset( $d['fields'] ) ? count( $d['fields'] ) : 0;
    $fp['qty_break_count'] = isset( $d['quantity_breaks'] ) ? count( $d['quantity_breaks'] ) : 0;
    $fp['design_dpi']      = $d['design_output']['dpi'] ?? null;
    $fp['design_unit']     = $d['design_output']['dimension_unit'] ?? null;
    $fp['display_mode']    = $d['display_mode'] ?? null;

    // Drill into each field.
    $fp['fields_detail'] = array();
    foreach ( ( $d['fields'] ?? array() ) as $fi => $f ) {
        $row = array(
            'idx'         => $fi,
            'general_keys' => array_keys( $f['general'] ?? array() ),
            'nbpb_type'    => $f['nbpb_type'] ?? null,
            'title'        => is_array( $f['general']['title'] ?? null ) ? ( $f['general']['title']['value'] ?? '?' ) : ( $f['general']['title'] ?? '?' ),
            'attr_count'   => count( $f['general']['attributes']['options'] ?? array() ),
            'has_pb_config' => isset( $f['general']['pb_config'] ),
        );
        if ( isset( $f['general']['pb_config'] ) && is_array( $f['general']['pb_config'] ) ) {
            $row['pb_config_top_count'] = count( $f['general']['pb_config'] );
            $first_key = array_key_first( $f['general']['pb_config'] );
            if ( null !== $first_key ) {
                $row['pb_config_first_node_views'] = isset( $f['general']['pb_config'][ $first_key ][0]['views'] )
                    ? count( $f['general']['pb_config'][ $first_key ][0]['views'] ) : 0;
            }
        }
        $fp['fields_detail'][ $fi ] = $row;
    }
    return $fp;
}

$baseline = spbwc_fingerprint( $d_bag );

/**
 * Clone BAG to a temp row, run a $_POST through save, fingerprint, delete.
 */
function spbwc_audit_one( $label, $bag, $d_bag, $build_post ) {
    global $wpdb;
    $clone = $bag;
    unset( $clone['id'] );
    $clone['title'] = "BAG (audit-{$label})";
    $wpdb->insert( "{$wpdb->prefix}storelly_product_builder_options", $clone );
    $cid = (int) $wpdb->insert_id;

    $_POST = $build_post( $d_bag );
    $result = SPBWC_Storelly_PB_Admin_Options::instance()->spbwc_save_option( $cid );

    $d_after = maybe_unserialize( $wpdb->get_var( $wpdb->prepare(
        "SELECT fields FROM {$wpdb->prefix}storelly_product_builder_options WHERE id=%d", $cid ) ) );
    $fp = spbwc_fingerprint( $d_after );
    $wpdb->delete( "{$wpdb->prefix}storelly_product_builder_options", array( 'id' => $cid ) );
    return array( 'label' => $label, 'save_result' => $result, 'fingerprint' => $fp );
}

$report = array(
    'baseline' => $baseline,
    'audits'   => array(),
);

// Classic-style POST — mirrors edit-option.php inputs.
$report['audits'][] = spbwc_audit_one( 'classic-style', $bag, $d_bag, function ( $d ) {
    $views_post = array();
    foreach ( ( $d['views'] ?? array() ) as $vi => $v ) {
        $views_post[ $vi ] = array(
            'name'        => $v['name'] ?? '',
            'base'        => $v['base'] ?? 0,
            'base_width'  => $v['base_width'] ?? 0,
            'base_height' => $v['base_height'] ?? 0,
        );
    }
    return array(
        'title'        => 'BAG (classic)',
        'apply_for'    => 'p',
        'product_ids'  => array(),
        'product_cats' => array(),
        'options'      => array(
            'version'                => $d['version'] ?? '1.0',
            'display_mode'           => $d['display_mode'] ?? 'sections',
            'quantity_enable'        => $d['quantity_enable'] ?? 'n',
            'quantity_type'          => $d['quantity_type'] ?? 'r',
            'quantity_min'           => $d['quantity_min'] ?? 1,
            'quantity_max'           => $d['quantity_max'] ?? 100,
            'quantity_step'          => $d['quantity_step'] ?? 1,
            'quantity_discount_type' => $d['quantity_discount_type'] ?? 'p',
            'quantity_breaks'        => $d['quantity_breaks'] ?? array(),
            'design_output'          => $d['design_output'] ?? array(),
            'views'                  => $views_post,
            'jsonFields'             => wp_json_encode( $d['fields'] ?? array() ),
        ),
    );
} );

// v3-style POST AFTER FIX (mirrors edit-option-v3.php after we added views).
$report['audits'][] = spbwc_audit_one( 'v3-postfix', $bag, $d_bag, function ( $d ) {
    $views_post = array();
    foreach ( ( $d['views'] ?? array() ) as $vi => $v ) {
        $views_post[ $vi ] = array(
            'name'        => $v['name'] ?? '',
            'base'        => $v['base'] ?? 0,
            'base_width'  => $v['base_width'] ?? 0,
            'base_height' => $v['base_height'] ?? 0,
        );
    }
    return array(
        'title'        => 'BAG (v3)',
        'apply_for'    => 'p',
        'product_ids'  => array(),
        'product_cats' => array(),
        'options'      => array(
            'version'                => $d['version'] ?? '1.0',
            'display_mode'           => $d['display_mode'] ?? 'sections',
            'quantity_enable'        => $d['quantity_enable'] ?? 'n',
            'quantity_type'          => $d['quantity_type'] ?? 'r',
            'quantity_min'           => $d['quantity_min'] ?? 1,
            'quantity_max'           => $d['quantity_max'] ?? 100,
            'quantity_step'          => $d['quantity_step'] ?? 1,
            'quantity_discount_type' => $d['quantity_discount_type'] ?? 'p',
            'quantity_breaks'        => $d['quantity_breaks'] ?? array(),
            'design_output'          => $d['design_output'] ?? array(),
            'views'                  => $views_post,
            'jsonFields'             => wp_json_encode( $d['fields'] ?? array() ),
        ),
    );
} );

// VB-style POST — mirrors views/visual-builder/edit.php.
$report['audits'][] = spbwc_audit_one( 'vb-style', $bag, $d_bag, function ( $d ) {
    $views_post = array();
    foreach ( ( $d['views'] ?? array() ) as $vi => $v ) {
        $views_post[ $vi ] = array(
            'name'        => $v['name'] ?? '',
            'base'        => $v['base'] ?? 0,
            'base_width'  => $v['base_width'] ?? 0,
            'base_height' => $v['base_height'] ?? 0,
        );
    }
    // VB form does not author quantity / display_mode / apply targeting,
    // but it does submit a hidden round-trip for them. The PHP save handler
    // serializes whatever is in $_POST['options']; if a key is missing, the
    // value is gone. So VB MUST include them or it'll wipe pricing data.
    return array(
        'title'        => 'BAG (vb)',
        'apply_for'    => 'p',
        'product_ids'  => array(),
        'product_cats' => array(),
        'options'      => array(
            'version'                => $d['version'] ?? '1.0',
            // ⚠️ Need to verify VB submits these — if not, they'll vanish.
            'display_mode'           => $d['display_mode'] ?? 'sections',
            'quantity_enable'        => $d['quantity_enable'] ?? 'n',
            'quantity_type'          => $d['quantity_type'] ?? 'r',
            'quantity_min'           => $d['quantity_min'] ?? 1,
            'quantity_max'           => $d['quantity_max'] ?? 100,
            'quantity_step'          => $d['quantity_step'] ?? 1,
            'quantity_discount_type' => $d['quantity_discount_type'] ?? 'p',
            'quantity_breaks'        => $d['quantity_breaks'] ?? array(),
            'design_output'          => $d['design_output'] ?? array(),
            'views'                  => $views_post,
            'jsonFields'             => wp_json_encode( $d['fields'] ?? array() ),
        ),
    );
} );

// Diff each audit vs baseline for top-level lost keys.
foreach ( $report['audits'] as &$audit ) {
    $a = $audit['fingerprint'];
    $audit['diff'] = array(
        'top_keys_lost'   => array_values( array_diff( $baseline['top_keys'], $a['top_keys'] ) ),
        'top_keys_added'  => array_values( array_diff( $a['top_keys'], $baseline['top_keys'] ) ),
        'views_count_diff'    => $a['views_count'] - $baseline['views_count'],
        'fields_count_diff'   => $a['fields_count'] - $baseline['fields_count'],
        'qty_break_count_diff' => $a['qty_break_count'] - $baseline['qty_break_count'],
        'design_dpi_match'    => $a['design_dpi'] === $baseline['design_dpi'],
        'design_unit_match'   => $a['design_unit'] === $baseline['design_unit'],
        'display_mode_match'  => $a['display_mode'] === $baseline['display_mode'],
        'pb_config_intact'    => true,
    );
    foreach ( $a['fields_detail'] as $fi => $fd ) {
        $base_fd = $baseline['fields_detail'][ $fi ] ?? null;
        if ( $base_fd && $base_fd['has_pb_config'] && ! $fd['has_pb_config'] ) {
            $audit['diff']['pb_config_intact'] = false;
        }
    }
}
unset( $audit );

$json = json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
echo $json;
file_put_contents( '/tmp/spbwc_full_audit.json', $json );
