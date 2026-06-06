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
