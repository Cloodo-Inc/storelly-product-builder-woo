<?php
/**
 * Email delivery log (E6b).
 *
 * Records every Storelly email the plugin sends (id, recipient, subject, status,
 * time) into a dedicated table so the merchant gets a real audit trail in the
 * Storelly › Emails dashboard. Capture is wired to WooCommerce's own
 * `woocommerce_email_sent` action and filtered to our `spbwc_` emails only, so
 * it never logs unrelated site mail.
 *
 * Local only — no external service.
 *
 * @package Storelly
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'SPBWC_Email_Log' ) ) {

    class SPBWC_Email_Log {

        const STATUS_SENT   = 'sent';
        const STATUS_FAILED = 'failed';
        const STATUS_TEST   = 'test';

        /** Keep log rows for this many days. */
        const RETENTION_DAYS = 90;

        public static function init() {
            // WooCommerce fires this after every WC_Email::send().
            add_action( 'woocommerce_email_sent', array( __CLASS__, 'capture' ), 10, 3 );
        }

        /** Fully-qualified table name. */
        public static function table() {
            global $wpdb;
            return $wpdb->prefix . 'spbwc_email_log';
        }

        /**
         * CREATE TABLE statement string (used by the installer via dbDelta).
         *
         * @return string
         */
        public static function schema() {
            global $wpdb;
            $table   = self::table();
            $collate = $wpdb->get_charset_collate();
            return "CREATE TABLE {$table} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                email_id varchar(100) NOT NULL DEFAULT '',
                recipient text NOT NULL,
                subject text NOT NULL,
                status varchar(20) NOT NULL DEFAULT 'sent',
                sent_at datetime NOT NULL,
                PRIMARY KEY  (id),
                KEY email_id (email_id),
                KEY sent_at (sent_at)
            ) {$collate};";
        }

        /**
         * Capture a WooCommerce email send. Only Storelly (`spbwc_`) emails.
         *
         * @param bool     $return  Whether wp_mail() reported success.
         * @param string   $email_id Email ID.
         * @param WC_Email $email   Email object.
         */
        public static function capture( $return, $email_id, $email ) {
            if ( 0 !== strpos( (string) $email_id, 'spbwc_' ) ) {
                return;
            }
            $recipient = is_object( $email ) && method_exists( $email, 'get_recipient' ) ? $email->get_recipient() : '';
            $subject   = is_object( $email ) && method_exists( $email, 'get_subject' ) ? $email->get_subject() : '';
            self::record( $email_id, $recipient, $subject, $return ? self::STATUS_SENT : self::STATUS_FAILED );
        }

        /**
         * Insert a log row.
         *
         * @param string $email_id  Email ID.
         * @param string $recipient Recipient(s).
         * @param string $subject   Subject line.
         * @param string $status    sent|failed|test.
         */
        public static function record( $email_id, $recipient, $subject, $status = self::STATUS_SENT ) {
            global $wpdb;
            $table = self::table();
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom audit table.
            $wpdb->insert(
                $table,
                array(
                    'email_id'  => substr( sanitize_text_field( $email_id ), 0, 100 ),
                    'recipient' => sanitize_text_field( $recipient ),
                    'subject'   => sanitize_text_field( $subject ),
                    'status'    => in_array( $status, array( self::STATUS_SENT, self::STATUS_FAILED, self::STATUS_TEST ), true ) ? $status : self::STATUS_SENT,
                    'sent_at'   => current_time( 'mysql' ),
                ),
                array( '%s', '%s', '%s', '%s', '%s' )
            );
            // Occasionally prune old rows (cheap, no cron dependency).
            if ( $wpdb->insert_id && 0 === ( (int) $wpdb->insert_id % 50 ) ) {
                self::prune();
            }
        }

        /** Delete rows older than the retention window. */
        public static function prune() {
            global $wpdb;
            $table  = self::table();
            $cutoff = gmdate( 'Y-m-d H:i:s', time() - ( self::RETENTION_DAYS * DAY_IN_SECONDS ) );
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is a constant; value is prepared.
            $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE sent_at < %s", $cutoff ) );
        }

        /**
         * Fetch recent log rows for the dashboard.
         *
         * @param array $args { limit, offset, email_id, status }.
         * @return array[] Rows.
         */
        public static function get_rows( $args = array() ) {
            global $wpdb;
            $table  = self::table();
            $limit  = isset( $args['limit'] ) ? max( 1, min( 200, (int) $args['limit'] ) ) : 50;
            $offset = isset( $args['offset'] ) ? max( 0, (int) $args['offset'] ) : 0;

            // Build the optional WHERE as its own fully-prepared fragment so the
            // final statement never mixes a variable clause with positional args.
            $where = '';
            if ( ! empty( $args['email_id'] ) && ! empty( $args['status'] ) ) {
                $where = $wpdb->prepare( ' WHERE email_id = %s AND status = %s', sanitize_text_field( $args['email_id'] ), sanitize_text_field( $args['status'] ) );
            } elseif ( ! empty( $args['email_id'] ) ) {
                $where = $wpdb->prepare( ' WHERE email_id = %s', sanitize_text_field( $args['email_id'] ) );
            } elseif ( ! empty( $args['status'] ) ) {
                $where = $wpdb->prepare( ' WHERE status = %s', sanitize_text_field( $args['status'] ) );
            }

            // $limit/$offset are sanitized ints; table name is constant-derived; $where is pre-prepared.
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
            return $wpdb->get_results( "SELECT * FROM {$table}{$where} ORDER BY id DESC LIMIT {$limit} OFFSET {$offset}", ARRAY_A );
        }

        /** Total number of log rows. */
        public static function count() {
            global $wpdb;
            $table = self::table();
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name constant; no user input.
            return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
        }
    }
}
