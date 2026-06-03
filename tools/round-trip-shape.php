<?php
/**
 * Round-trip shape capture — prints BAG (id=8) structural counts as JSON.
 * Run via: docker exec wp_app wp eval-file /var/www/html/wp-content/plugins/storelly-product-builder-woo/tools/round-trip-shape.php --allow-root
 */
function spbwc_shape( $oid ) {
    global $wpdb;
    $row = $wpdb->get_row( $wpdb->prepare(
        "SELECT title, modified, fields FROM {$wpdb->prefix}storelly_product_builder_options WHERE id=%d",
        $oid
    ), ARRAY_A );
    if ( ! $row ) {
        return null;
    }
    $d = maybe_unserialize( $row['fields'] );
    $nbpb  = 0;
    $price = 0;
    $attrs = 0;
    $price_titles = array();
    $nbpb_titles  = array();
    foreach ( ( isset( $d['fields'] ) ? $d['fields'] : array() ) as $f ) {
        $t = isset( $f['general']['title']['value'] ) ? $f['general']['title']['value']
            : ( isset( $f['general']['title'] ) && is_string( $f['general']['title'] )
                ? $f['general']['title'] : '?' );
        if ( ! empty( $f['nbpb_type'] ) ) {
            $nbpb++;
            $nbpb_titles[] = $t;
        } else {
            $price++;
            $price_titles[] = $t;
        }
        if ( isset( $f['general']['attributes']['options'] ) ) {
            $attrs += count( $f['general']['attributes']['options'] );
        }
    }
    return array(
        'row_title'       => $row['title'],
        'modified'        => $row['modified'],
        'views'           => isset( $d['views'] ) ? count( $d['views'] ) : 0,
        'view_names'      => array_map(
            function ( $v ) {
                return isset( $v['name'] ) ? $v['name'] : '?';
            },
            isset( $d['views'] ) ? $d['views'] : array()
        ),
        'total_fields'    => isset( $d['fields'] ) ? count( $d['fields'] ) : 0,
        'nbpb_fields'     => $nbpb,
        'nbpb_titles'     => $nbpb_titles,
        'pricing_fields'  => $price,
        'pricing_titles'  => $price_titles,
        'total_attrs'     => $attrs,
        'quantity_breaks' => isset( $d['quantity_breaks'] ) ? count( $d['quantity_breaks'] ) : 0,
        'design_dpi'      => isset( $d['design_output']['dpi'] ) ? $d['design_output']['dpi'] : null,
        'design_unit'     => isset( $d['design_output']['dimension_unit'] ) ? $d['design_output']['dimension_unit'] : null,
        'display_mode'    => isset( $d['display_mode'] ) ? $d['display_mode'] : null,
        'blob_md5'        => md5( $row['fields'] ),
    );
}

$out = json_encode( spbwc_shape( 8 ), JSON_PRETTY_PRINT );
echo $out;
// Also write to a deterministic path so callers can read it without
// fighting wrapper layers eating stdout.
file_put_contents( '/tmp/spbwc_shape.json', $out );
