<?php
/**
 * One-off generator (dev only): build the bundled "bag" demo from the live
 * store (product 129 / option 8) so a fresh install can seed it offline.
 *
 * Output (committed to the repo):
 *   storage/printcart/demo/bag.json          — product meta + option-set fields
 *   storage/printcart/demo/img/<oldId>.webp  — each referenced image, resized
 *
 * Images are kept referenced by their ORIGINAL attachment id inside the fields
 * blob; the runtime seeder (SPBWC_Demo_Seeder) sideloads img/<id>.webp, maps
 * old id -> new attachment id, and rewrites the blob. WebP, max 700px, q80.
 */
if ( ! defined( 'ABSPATH' ) ) { exit( 1 ); }

$OPT = 8; $PID = 129; $MAX = 700; $Q = 80;
$out_dir = SPBWC_PB_PLUGIN_DIR . 'storage/printcart/demo/';
$img_dir = $out_dir . 'img/';
if ( ! is_dir( $img_dir ) ) { wp_mkdir_p( $img_dir ); }

// Keys whose scalar value is an attachment id (must match SPBWC_Demo_Seeder::IMAGE_KEYS):
// `image` = swatch/layer images, `component_icon` = component thumbnail,
// `base` = per-view base canvas. Missing the latter two shipped components/views
// with broken thumbnails on a fresh install.
function spbwc_gen_ids( $node, &$ids ) {
	$image_keys = array( 'image', 'component_icon', 'base' );
	if ( is_array( $node ) ) {
		foreach ( $node as $k => $v ) {
			if ( in_array( (string) $k, $image_keys, true ) && is_scalar( $v ) && (int) $v > 0 ) { $ids[ (int) $v ] = true; }
			else { spbwc_gen_ids( $v, $ids ); }
		}
	}
}

$row    = STORELLY_FRONTEND_OPTIONS::get_option( $OPT );
$fields = maybe_unserialize( $row['fields'] );

$ids = array();
spbwc_gen_ids( $fields, $ids );
$thumb = (int) get_post_thumbnail_id( $PID );
if ( $thumb > 0 ) { $ids[ $thumb ] = true; }
$ids = array_keys( $ids );

$done = 0; $skip = 0; $bytes = 0;
foreach ( $ids as $id ) {
	$src = get_attached_file( $id );
	if ( ! $src || ! file_exists( $src ) ) { $skip++; continue; }
	$dst = $img_dir . $id . '.webp';
	try {
		$im = new Imagick( $src );
		$im->setImageBackgroundColor( new ImagickPixel( 'transparent' ) );
		$w = $im->getImageWidth(); $h = $im->getImageHeight();
		if ( $w > $MAX || $h > $MAX ) {
			$im->resizeImage( min( $MAX, $w ), min( $MAX, $h ), Imagick::FILTER_LANCZOS, 1, true );
		}
		$im->setImageFormat( 'webp' );
		$im->setImageCompressionQuality( $Q );
		$im->stripImage();
		$im->writeImage( $dst );
		$im->clear();
		$bytes += filesize( $dst );
		$done++;
	} catch ( Exception $e ) {
		echo "  ERR id $id: " . $e->getMessage() . "\n";
		$skip++;
	}
}

$product = array(
	'name'          => get_the_title( $PID ),
	'regular_price' => (string) get_post_meta( $PID, '_regular_price', true ),
	'description'   => get_post_field( 'post_content', $PID ),
	'short'         => get_post_field( 'post_excerpt', $PID ),
	'thumb'         => $thumb > 0 ? $thumb : 0,
);

$bundle = array(
	'version'    => 1,
	'slug'       => 'bag',
	'product'    => $product,
	'option_set' => array(
		'title'  => isset( $fields['title'] ) ? $fields['title'] : 'BAG',
		'fields' => $fields, // image refs kept as original attachment ids
	),
	'image_ids'  => array_values( array_map( 'intval', $ids ) ),
);

file_put_contents( $out_dir . 'bag.json', wp_json_encode( $bundle, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ); // phpcs:ignore

printf( "images: %d written, %d skipped, %.2f MB total webp\n", $done, $skip, $bytes / 1048576 );
printf( "bag.json: %.1f KB\n", filesize( $out_dir . 'bag.json' ) / 1024 );
