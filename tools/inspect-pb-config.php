<?php
/**
 * Dump pb_config nesting for every nbpb_com field in BAG to verify the
 * Visual Builder per-view image bindings survived the round-trip.
 */
global $wpdb;
$d = maybe_unserialize( $wpdb->get_var( $wpdb->prepare(
    "SELECT fields FROM {$wpdb->prefix}storelly_product_builder_options WHERE id=%d", 8 ) ) );

$out = array( 'fields' => array() );
foreach ( $d['fields'] as $fi => $f ) {
    $row = array(
        'idx'        => $fi,
        'title'      => is_array( $f['general']['title'] ?? null )
                          ? ( $f['general']['title']['value'] ?? '?' )
                          : ( $f['general']['title'] ?? '?' ),
        'nbpb_type'  => $f['nbpb_type'] ?? null,
        'has_pb_config' => isset( $f['general']['pb_config'] ),
    );
    if ( isset( $f['general']['pb_config'] ) ) {
        $pb = $f['general']['pb_config'];
        $row['pb_config_type']  = gettype( $pb );
        $row['pb_config_count'] = is_array( $pb ) ? count( $pb ) : null;
        $row['pb_config_sample'] = array();
        if ( is_array( $pb ) ) {
            // Look at first attr's first sattr's views — the layered image bindings.
            $first_attr_key = array_key_first( $pb );
            if ( null !== $first_attr_key && is_array( $pb[ $first_attr_key ] ) ) {
                $first_sattr_key = array_key_first( $pb[ $first_attr_key ] );
                if ( null !== $first_sattr_key && is_array( $pb[ $first_attr_key ][ $first_sattr_key ] ) ) {
                    $node = $pb[ $first_attr_key ][ $first_sattr_key ];
                    $row['pb_config_sample'] = array(
                        'attr_key'         => $first_attr_key,
                        'sattr_key'        => $first_sattr_key,
                        'node_keys'        => array_keys( $node ),
                        'views_count'      => isset( $node['views'] ) && is_array( $node['views'] )
                                                ? count( $node['views'] ) : 0,
                        'first_view'       => isset( $node['views'][0] ) ? $node['views'][0] : null,
                    );
                }
            }
        }
    }
    $out['fields'][] = $row;
}
$json = json_encode( $out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
echo $json;
file_put_contents( '/tmp/spbwc_pbconfig.json', $json );
