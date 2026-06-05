<?php
/**
 * Email subsystem installer + migrations.
 *
 * Version-gated so it is safe to run on every admin request (and on activation):
 *  - creates the email-log table (E6b) via dbDelta,
 *  - migrates the legacy `nbdl_email_*` Marketplace email settings to the new
 *    `spbwc_email_*` IDs so renaming the email IDs (E5) never loses a merchant's
 *    saved enable/subject/heading config.
 *
 * Local only — no external service.
 *
 * @package Storelly
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'SPBWC_Email_Install' ) ) {

    class SPBWC_Email_Install {

        const DB_VERSION  = '1.0';
        const VERSION_KEY = 'spbwc_email_db_version';

        /** Legacy → new WC_Email ID map (E5). */
        public static function id_map() {
            return array(
                'nbdl_email_designer_enable'   => 'spbwc_email_designer_enable',
                'nbdl_email_designer_disable'  => 'spbwc_email_designer_disable',
                'nbdl_email__withdraw_request' => 'spbwc_email_withdraw_request',
                'nbdl_email_withdraw_approved' => 'spbwc_email_withdraw_approved',
                'nbdl_email_withdraw_cancelled' => 'spbwc_email_withdraw_cancelled',
            );
        }

        public static function init() {
            add_action( 'admin_init', array( __CLASS__, 'maybe_install' ) );
        }

        /** Run install/upgrade once per DB version bump. */
        public static function maybe_install() {
            if ( get_option( self::VERSION_KEY ) === self::DB_VERSION ) {
                return;
            }
            self::create_table();
            self::migrate_nbdl_settings();
            update_option( self::VERSION_KEY, self::DB_VERSION, false );
        }

        /** Create / update the email-log table. */
        public static function create_table() {
            if ( ! class_exists( 'SPBWC_Email_Log' ) ) {
                require_once SPBWC_PB_PLUGIN_DIR . 'includes/email/class-email-log.php';
            }
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            dbDelta( SPBWC_Email_Log::schema() );
        }

        /**
         * Copy each legacy email's saved settings to its new ID so the rename is
         * invisible to merchants. WC stores per-email settings under the option
         * key `woocommerce_<id>_settings`.
         */
        public static function migrate_nbdl_settings() {
            foreach ( self::id_map() as $old => $new ) {
                $new_key = 'woocommerce_' . $new . '_settings';
                if ( false !== get_option( $new_key, false ) ) {
                    continue; // Already migrated / merchant already configured the new ID.
                }
                $old_val = get_option( 'woocommerce_' . $old . '_settings', false );
                if ( false !== $old_val ) {
                    update_option( $new_key, $old_val );
                }
            }
        }
    }
}
