<?php
/**
 * One-off LIVE cleanup: remove duplicate / broken-image "bag" demo products that
 * the pre-fix auto-seeder created (race condition + missing component_icon/base
 * images), then reset the seeder flags so the FIXED plugin can install one clean
 * demo bag again.
 *
 * SAFE BY DEFAULT — runs as a DRY-RUN and changes nothing unless you pass `apply`.
 *
 * Targets ONLY data the demo seeder owns:
 *   - products tagged meta `_spbwc_is_sample = 1`  (set exclusively by SPBWC_Demo_Seeder)
 *   - option-set rows whose template_slug starts with `demo_sample_`
 *   - attachments tagged meta `_spbwc_is_sample = 1`
 * It does NOT touch the B2B sample (that uses a different meta `_spbwc_sample`).
 *
 * Usage (on the LIVE server, from the WordPress root):
 *
 *   wp eval-file _clean-demo-bags.php                 # dry-run: show what WOULD be removed
 *   wp eval-file _clean-demo-bags.php apply           # delete the demo bags + reset flags
 *   wp eval-file _clean-demo-bags.php apply reseed     # ^ then install ONE clean demo (needs the fixed plugin)
 *
 * RECOMMENDED ORDER:
 *   1) Update the plugin to the fixed version first.
 *   2) wp eval-file _clean-demo-bags.php            (review the dry-run list)
 *   3) wp eval-file _clean-demo-bags.php apply reseed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

global $wpdb;

$apply  = isset( $args ) && in_array( 'apply', (array) $args, true );
$reseed = isset( $args ) && in_array( 'reseed', (array) $args, true );
$mode   = $apply ? 'APPLY (deleting)' : 'DRY-RUN (no changes)';

echo "== Storelly demo-bag cleanup — {$mode} ==\n\n";

$opt_table = $wpdb->prefix . 'storelly_product_builder_options';

/* 1) Demo bag products — by sample meta, plus any product referenced by a demo_sample_ option row. */
$by_meta = get_posts(
	array(
		'post_type'   => 'product',
		'post_status' => 'any',
		'numberposts' => -1,
		'fields'      => 'ids',
		'meta_key'    => '_spbwc_is_sample',
		'meta_value'  => '1',
	)
);

$demo_rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, product_ids FROM {$opt_table} WHERE template_slug LIKE %s", 'demo_sample_%' ), ARRAY_A );
$row_ids   = array();
$by_row    = array();
foreach ( $demo_rows as $r ) {
	$row_ids[] = (int) $r['id'];
	$pids = maybe_unserialize( $r['product_ids'] );
	if ( is_array( $pids ) ) {
		foreach ( $pids as $pid ) {
			if ( (int) $pid > 0 && 'product' === get_post_type( (int) $pid ) ) {
				$by_row[] = (int) $pid;
			}
		}
	}
}

$product_ids = array_values( array_unique( array_merge( array_map( 'intval', $by_meta ), $by_row ) ) );

echo "Demo bag products found: " . count( $product_ids ) . "\n";
$opt_ids = array();
foreach ( $product_ids as $pid ) {
	$oid = (int) get_post_meta( $pid, '_spbwc_option_id', true );
	if ( $oid ) {
		$opt_ids[ $oid ] = true;
	}
	printf( "  - #%d [%s] \"%s\"  option_id=%s\n", $pid, get_post_status( $pid ), get_the_title( $pid ), $oid ?: '-' );
	if ( $apply ) {
		wp_delete_post( $pid, true );
	}
}

/* 2) Option-set rows (linked via product meta + any demo_sample_ row). */
$all_opt_ids = array_values( array_unique( array_merge( array_keys( $opt_ids ), $row_ids ) ) );
echo "\nOption-set rows to delete: " . count( $all_opt_ids ) . ( $all_opt_ids ? ' (' . implode( ',', $all_opt_ids ) . ')' : '' ) . "\n";
if ( $apply ) {
	foreach ( $all_opt_ids as $oid ) {
		$wpdb->delete( $opt_table, array( 'id' => (int) $oid ), array( '%d' ) );
	}
}

/* 3) Orphan demo attachments (each duplicate seed sideloaded ~110 images). */
$atts = get_posts(
	array(
		'post_type'   => 'attachment',
		'post_status' => 'any',
		'numberposts' => -1,
		'fields'      => 'ids',
		'meta_key'    => '_spbwc_is_sample',
		'meta_value'  => '1',
	)
);
echo "\nDemo attachments to delete: " . count( $atts ) . "\n";
if ( $apply ) {
	foreach ( $atts as $aid ) {
		wp_delete_attachment( (int) $aid, true );
	}
}

/* 4) Reset seeder flags so a clean re-install is possible. */
$flags = array( 'spbwc_demo_seeded', 'spbwc_demo_autoseeded', 'spbwc_demo_seed_pending', 'spbwc_demo_seed_lock' );
echo "\nSeeder flags to reset: " . implode( ', ', $flags ) . "\n";
if ( $apply ) {
	foreach ( $flags as $f ) {
		delete_option( $f );
	}
}

/* 5) Optional: install ONE clean demo bag right now (CLI has no web timeout). */
if ( $apply && $reseed ) {
	echo "\nRe-seeding one clean demo bag (draft)…\n";
	if ( class_exists( 'SPBWC_Demo_Seeder' ) ) {
		$res = SPBWC_Demo_Seeder::seed( 'draft' );
		if ( is_wp_error( $res ) ) {
			echo "  RESEED FAILED: " . $res->get_error_message() . "\n";
		} else {
			update_option( 'spbwc_demo_autoseeded', 1, false );
			printf( "  OK — product #%d, option #%d (draft). Publish it from Storelly › Overview when ready.\n", $res['product_id'], $res['option_id'] );
		}
	} else {
		echo "  Skipped: SPBWC_Demo_Seeder not loaded (is the plugin active?).\n";
	}
}

echo "\n" . ( $apply
	? "DONE.\n"
	: "Dry-run only — nothing changed. To execute:  wp eval-file _clean-demo-bags.php apply reseed\n" );
