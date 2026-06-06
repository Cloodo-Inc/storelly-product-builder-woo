<?php
/**
 * Uninstall cleanup for Storelly Product Builder for WooCommerce.
 *
 * Runs only on real plugin deletion (not deactivation). Drops feature-owned
 * custom tables that have an explicit drop helper. Kept conservative: only data
 * the plugin solely owns is removed.
 *
 * @package Storelly
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// B2B Account Credit ledger.
$spbwc_ledger = __DIR__ . '/includes/b2b/class-b2b-ledger.php';
if ( file_exists( $spbwc_ledger ) ) {
    require_once $spbwc_ledger;
    if ( class_exists( 'SPBWC_B2B_Ledger' ) ) {
        SPBWC_B2B_Ledger::drop_table();
    }
}

// Cloud connection secrets + opt-in flags (SPEC_M5_CLOUD_CONSENT §5.5). Removing
// the plugin must not leave API credentials behind. The stable store UUID
// (spbwc_store_uuid) is intentionally KEPT so a later reinstall re-links to the
// same Storelly store instead of creating a duplicate.
delete_option( 'spbwc_connect_api_keys' ); // consumer key/secret, unauth_token, store_id, username
delete_option( 'spbwc_cloud_consent' );    // GDPR consent log
delete_option( 'spbwc_license_data' );      // cached license status

$spbwc_settings = get_option( 'spbwc_pb_settings', array() );
if ( is_array( $spbwc_settings ) ) {
    unset( $spbwc_settings['enable_api_sync'], $spbwc_settings['enable_cloud2print_api'] );
    update_option( 'spbwc_pb_settings', $spbwc_settings );
}
