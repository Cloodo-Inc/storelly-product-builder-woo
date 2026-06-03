<?php
/**
 * Deep-inspect BAG (id=8) blob — dumps every sub-key path so we can see
 * what changed after the round-trip harness ran.
 */
global $wpdb;
$row = $wpdb->get_var( $wpdb->prepare(
    "SELECT fields FROM {$wpdb->prefix}storelly_product_builder_options WHERE id=%d", 8 ) );
$d = maybe_unserialize( $row );

$out = array(
    'top_level_keys'  => array_keys( $d ),
    'views_detail'    => array(),
    'fields_detail'   => array(),
);

if ( isset( $d['views'] ) ) {
    foreach ( $d['views'] as $vi => $v ) {
        $out['views_detail'][ $vi ] = array(
            'keys' => array_keys( $v ),
            'name' => isset( $v['name'] ) ? $v['name'] : null,
            'base' => isset( $v['base'] ) ? $v['base'] : null,
            'base_url' => isset( $v['base_url'] ) ? ( substr( $v['base_url'], 0, 80 ) . '…' ) : null,
        );
    }
}

if ( isset( $d['fields'] ) ) {
    foreach ( $d['fields'] as $fi => $f ) {
        $detail = array(
            'top_keys'   => array_keys( $f ),
            'nbpb_type'  => $f['nbpb_type'] ?? null,
            'title'      => is_array( $f['general']['title'] ?? null )
                            ? ( $f['general']['title']['value'] ?? '?' )
                            : ( $f['general']['title'] ?? '?' ),
            'general_keys' => array_keys( $f['general'] ?? array() ),
        );
        // Inspect attributes.options[0] deeply.
        $opts = $f['general']['attributes']['options'] ?? array();
        $detail['attr_count'] = count( $opts );
        if ( ! empty( $opts ) ) {
            $detail['attr0_keys']      = array_keys( $opts[0] );
            $detail['attr0_name']      = $opts[0]['name'] ?? null;
            $detail['attr0_has_pb']    = isset( $opts[0]['pb_config'] );
            if ( isset( $opts[0]['pb_config'] ) ) {
                $detail['attr0_pb_type'] = gettype( $opts[0]['pb_config'] );
                if ( is_array( $opts[0]['pb_config'] ) ) {
                    $detail['attr0_pb_count'] = count( $opts[0]['pb_config'] );
                    $detail['attr0_pb_sample_keys'] = array_keys( $opts[0]['pb_config'] );
                }
            }
        }
        $out['fields_detail'][ $fi ] = $detail;
    }
}

$json = json_encode( $out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
echo $json;
file_put_contents( '/tmp/spbwc_blob_inspect.json', $json );
