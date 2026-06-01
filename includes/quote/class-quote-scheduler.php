<?php
/**
 * Quote lifecycle scheduler (M6).
 *
 * A daily Action Scheduler job that expires sent quotes past their validity
 * date and sends expiry reminders at fixed days-remaining thresholds. Action
 * Scheduler ships with WooCommerce; on this dev box WP-Cron is disabled and a
 * Windows task drives the queue (see project memory localhost_wpcron_fix).
 *
 * @package Storelly
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'SPBWC_Quote_Scheduler' ) ) {

    class SPBWC_Quote_Scheduler {

        const HOOK = 'spbwc_quote_daily_sweep';

        /** Send a reminder when this many days remain before expiry. */
        const REMINDER_THRESHOLDS = array( 7, 3, 1 );

        public static function init() {
            add_action( 'init', array( __CLASS__, 'maybe_schedule' ) );
            add_action( self::HOOK, array( __CLASS__, 'run_sweep' ) );
        }

        /** Ensure the recurring daily sweep is scheduled. */
        public static function maybe_schedule() {
            if ( ! function_exists( 'as_schedule_recurring_action' ) || ! function_exists( 'as_next_scheduled_action' ) ) {
                return;
            }
            if ( false === as_next_scheduled_action( self::HOOK ) ) {
                as_schedule_recurring_action( time() + HOUR_IN_SECONDS, DAY_IN_SECONDS, self::HOOK, array(), 'spbwc-quote' );
            }
        }

        /**
         * Expire past-due sent quotes and fire expiry reminders.
         */
        public static function run_sweep() {
            $quotes = get_posts(
                array(
                    'post_type'      => SPBWC_Quote::POST_TYPE,
                    'post_status'    => SPBWC_Quote::STATUS_SENT,
                    'numberposts'    => -1,
                    'fields'         => 'ids',
                    // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Scheduled batch job, not a request-time query.
                    'meta_key'       => SPBWC_Quote::META_VALID_UNTIL,
                )
            );
            if ( empty( $quotes ) ) {
                return;
            }
            $now       = current_time( 'timestamp' );
            $today_mid = strtotime( current_time( 'Y-m-d' ) . ' 00:00:00' );
            foreach ( $quotes as $quote_id ) {
                $valid = (string) get_post_meta( $quote_id, SPBWC_Quote::META_VALID_UNTIL, true );
                if ( '' === $valid ) {
                    continue;
                }
                $expiry_eod = strtotime( $valid . ' 23:59:59' );
                if ( false === $expiry_eod ) {
                    continue;
                }
                if ( $expiry_eod < $now ) {
                    SPBWC_Quote::set_status( $quote_id, SPBWC_Quote::STATUS_EXPIRED, __( 'Quote expired (validity date passed).', 'storelly-product-builder-for-woocommerce' ) );
                    continue;
                }
                // Whole calendar days remaining (midnight to midnight) so the
                // reminder thresholds match regardless of the current time of day.
                $expiry_mid = strtotime( $valid . ' 00:00:00' );
                $days_left  = (int) round( ( $expiry_mid - $today_mid ) / DAY_IN_SECONDS );
                self::maybe_remind( $quote_id, $days_left );
            }
        }

        /**
         * Fire a one-time reminder when days_left crosses a threshold.
         *
         * @param int $quote_id  Quote post ID.
         * @param int $days_left Days remaining before expiry.
         */
        protected static function maybe_remind( $quote_id, $days_left ) {
            if ( ! in_array( $days_left, self::REMINDER_THRESHOLDS, true ) ) {
                return;
            }
            $sent = get_post_meta( $quote_id, '_spbwc_quote_reminders_sent', true );
            $sent = is_array( $sent ) ? $sent : array();
            if ( in_array( $days_left, $sent, true ) ) {
                return;
            }
            $sent[] = $days_left;
            update_post_meta( $quote_id, '_spbwc_quote_reminders_sent', $sent );
            do_action( 'spbwc_quote_reminder_notification', $quote_id, $days_left );
        }
    }
}
