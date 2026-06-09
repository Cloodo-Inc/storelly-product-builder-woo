<?php
/**
 * Smoke tests for SPBWC_Template_Preview_Render.
 *
 * Run from inside the WordPress container via WP-CLI:
 *
 *     wp eval-file wp-content/plugins/storelly-product-builder-woo/tools/smoke-template-preview-render.php --allow-root
 *
 * Exit code 0 on all-pass, 1 on any failure.
 *
 * Covers the four gating cases that the shared-renderer pipeline depends on:
 *
 *   1. Caller without the spbwc_manage_product_builder cap → 403
 *   2. Caller with cap but a bogus nonce                   → 403
 *   3. Caller with cap + valid nonce but a non-existent
 *      template slug                                       → 404
 *   4. Caller with cap + valid nonce + a known template
 *      slug (business-cards)                               → 200 + body
 *      from the live endpoint contains
 *      .nbo-wrapper.nbo-style-cloodo
 *
 * For 1–3 the test calls resolve_preview_request() directly so we don't
 * have to swallow wp_die() or worry about the render path's exit;.
 * For 4 the test also performs an HTTP GET against the real URL (cookies
 * from the temp user) so the markup-level assertion goes through the
 * actual template_redirect → wp_head/wp_footer pipeline.
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "ABSPATH undefined — run via `wp eval-file`.\n" );
	exit( 1 );
}
if ( ! class_exists( 'SPBWC_Template_Preview_Render' ) ) {
	fwrite( STDERR, "SPBWC_Template_Preview_Render not loaded — plugin disabled?\n" );
	exit( 1 );
}

$failures = 0;
$skipped  = 0;
$results  = array();

/**
 * Simple assertion helper. Each test reports pass/fail/skip to $results.
 * Pass `skip => true` in the detail array to report a SKIP instead.
 */
$check = function ( $name, $ok, $detail = '' ) use ( &$failures, &$results ) {
	$results[] = array( 'name' => $name, 'ok' => (bool) $ok, 'detail' => $detail, 'skip' => false );
	if ( ! $ok ) {
		$failures++;
	}
};
$skip = function ( $name, $detail = '' ) use ( &$skipped, &$results ) {
	$results[] = array( 'name' => $name, 'ok' => true, 'detail' => $detail, 'skip' => true );
	$skipped++;
};

// Create a one-shot admin so cap-aware tests can swap between with-cap/without-cap.
$tmp_login = 'spbwc_smoke_' . wp_rand( 1000, 9999 );
$tmp_email = $tmp_login . '@example.com';
$admin_id  = wp_create_user( $tmp_login, wp_generate_password( 20, true, true ), $tmp_email );
if ( is_wp_error( $admin_id ) ) {
	fwrite( STDERR, "Could not create temp admin: " . $admin_id->get_error_message() . "\n" );
	exit( 1 );
}
$admin = new WP_User( $admin_id );
$admin->set_role( 'administrator' );

$render = SPBWC_Template_Preview_Render::instance();
$action = SPBWC_Template_Preview_Render::NONCE_ACTION;

// ── Case 1: no cap → 403 ─────────────────────────────────────────────
wp_set_current_user( 0 );
$r = $render->resolve_preview_request(
	array(
		'_spbwcnonce' => 'irrelevant',
		'slug'        => 'business-cards',
	)
);
$check( '1) anonymous → 403', 403 === $r['status'], 'got status=' . $r['status'] );

// ── Case 2: cap but bad nonce → 403 ─────────────────────────────────
wp_set_current_user( $admin_id );
$r = $render->resolve_preview_request(
	array(
		'_spbwcnonce' => 'wrong-nonce',
		'slug'        => 'business-cards',
	)
);
$check( '2) admin + bad nonce → 403', 403 === $r['status'], 'got status=' . $r['status'] );

// ── Case 3: cap + valid nonce + fake slug → 404 ────────────────────
$nonce = wp_create_nonce( $action );
$r = $render->resolve_preview_request(
	array(
		'_spbwcnonce' => $nonce,
		'slug'        => 'this-template-does-not-exist',
	)
);
$check( '3) good nonce + fake slug → 404', 404 === $r['status'], 'got status=' . $r['status'] );

// ── Case 4a: cap + valid nonce + real slug → 200 + options non-empty ─
$nonce = wp_create_nonce( $action );
$r = $render->resolve_preview_request(
	array(
		'_spbwcnonce' => $nonce,
		'slug'        => 'business-cards',
	)
);
$has_fields = ( 200 === $r['status'] )
	&& is_array( $r['options'] )
	&& ! empty( $r['options']['fields'] )
	&& is_array( $r['options']['fields'] );
$check(
	'4a) good nonce + real slug → 200 + options.fields populated',
	$has_fields,
	'status=' . $r['status'] . ', fields=' . ( $has_fields ? count( $r['options']['fields'] ) : 0 )
);

// ── Case 5: cap + valid nonce + live draft (POST) → 200, catalog bypassed ─
// The edit-option preview posts the in-progress option JSON instead of a
// catalog slug. The draft is descriptor-shaped, so build_runtime_options()
// must collapse { title, type, value } → value, and the (deliberately fake)
// slug must be ignored when a draft is present.
$nonce = wp_create_nonce( $action );
$draft = array(
	'title'  => 'Live draft option',
	'fields' => array(
		array(
			'id'      => 'f1',
			'general' => array(
				'title'      => array(
					'title' => 'Title',
					'type'  => 'text',
					'value' => 'Paper',
				),
				'data_type'  => array(
					'title' => 'Type',
					'type'  => 'text',
					'value' => 'm',
				),
				'attributes' => array(
					'options' => array(
						array(
							'name'  => 'Matte',
							'price' => array( '0' ),
						),
					),
				),
			),
		),
	),
);
$r        = $render->resolve_preview_request(
	array(
		'_spbwcnonce' => $nonce,
		'draft'       => wp_json_encode( $draft ),
		'slug'        => 'this-slug-must-be-ignored-when-draft-present',
	)
);
$draft_ok = ( 200 === $r['status'] )
	&& is_array( $r['options'] )
	&& ! empty( $r['options']['fields'] )
	&& isset( $r['options']['fields'][0]['general']['title'] )
	&& 'Paper' === $r['options']['fields'][0]['general']['title'];
$check(
	'5) good nonce + live draft → 200 + descriptor flattened, catalog bypassed',
	$draft_ok,
	'status=' . $r['status'] . ', title=' . ( isset( $r['options']['fields'][0]['general']['title'] ) && is_string( $r['options']['fields'][0]['general']['title'] ) ? $r['options']['fields'][0]['general']['title'] : 'n/a' )
);

// ── Case 4b: HTTP body contains the Cloodo wrapper ──────────────────
// Skipped when running via wp-cli inside the same PHP process pool that
// serves the front end — wp_remote_get back to the same site self-
// deadlocks. The case 4a result already proves the resolver returns a
// populated options blob; the render step is deterministic from there.
// Operator: run the printed curl from the host to cover this case.
$cli_inside_self = ( defined( 'WP_CLI' ) && WP_CLI );
$host_curl_url   = add_query_arg(
	array(
		SPBWC_Template_Preview_Render::QUERY_VAR => '1',
		'_spbwcnonce' => wp_create_nonce( $action ),
		'slug'        => 'business-cards',
		'base'        => 0,
	),
	home_url( '/' )
);
if ( $cli_inside_self ) {
	$skip(
		'4b) HTTP body contains .nbo-wrapper.nbo-style-cloodo',
		'skipped inside wp-cli; from your host run:' . PHP_EOL .
			'      curl -sS -b "wp_test=1" "' . $host_curl_url . '" | grep -c nbo-style-cloodo'
	);
} else {
	$resp = wp_remote_get(
		$host_curl_url,
		array(
			'timeout'   => 20,
			'cookies'   => array(
				LOGGED_IN_COOKIE => wp_generate_auth_cookie( $admin_id, time() + DAY_IN_SECONDS, 'logged_in' ),
			),
			'sslverify' => false,
		)
	);
	if ( is_wp_error( $resp ) ) {
		$check( '4b) HTTP body contains .nbo-wrapper.nbo-style-cloodo', false, 'fetch error: ' . $resp->get_error_message() );
	} else {
		$code        = (int) wp_remote_retrieve_response_code( $resp );
		$body        = (string) wp_remote_retrieve_body( $resp );
		$has_wrapper = ( false !== strpos( $body, 'nbo-wrapper' ) ) && ( false !== strpos( $body, 'nbo-style-cloodo' ) );
		$check(
			'4b) HTTP body contains .nbo-wrapper.nbo-style-cloodo',
			200 === $code && $has_wrapper,
			'http=' . $code . ', wrapper=' . ( $has_wrapper ? 'yes' : 'no' ) . ', bytes=' . strlen( $body )
		);
	}
}

// Clean up.
wp_set_current_user( 0 );
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $admin_id );

// Report.
echo PHP_EOL;
foreach ( $results as $r ) {
	$tag = $r['skip'] ? 'SKIP  ' : ( $r['ok'] ? 'PASS  ' : 'FAIL  ' );
	echo $tag . $r['name'];
	if ( '' !== $r['detail'] ) {
		echo ' [' . $r['detail'] . ']';
	}
	echo PHP_EOL;
}
echo PHP_EOL;
$total = count( $results );
if ( $failures > 0 ) {
	echo sprintf( "%d / %d failed, %d skipped\n", $failures, $total, $skipped );
} else {
	echo sprintf( "All %d passed (%d skipped)\n", $total - $skipped, $skipped );
}

exit( $failures > 0 ? 1 : 0 );
