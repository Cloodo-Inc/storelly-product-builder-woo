<?php
/**
 * B2B Volume Rebate (M5).
 *
 * A period-end (monthly) cashback: each company with a configured rebate %
 * earns that percent of its members' completed-order net spend back into the
 * company wallet, as a `rebate` credit on the shared ledger. Run end-of-period
 * (not instantly) so the refund window has passed — avoiding claw-backs.
 *
 * Scheduled via Action Scheduler (the store cron already used elsewhere). The
 * accrual is idempotent per company per period (META_REBATE_LAST guard).
 *
 * Local-only — no external service. See docs/SPEC_B2B_ACCOUNT_CREDIT.md (M5).
 *
 * @package Storelly
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'SPBWC_B2B_Rebate' ) ) {

    class SPBWC_B2B_Rebate {

        const HOOK  = 'spbwc_b2b_rebate_run';
        const GROUP = 'spbwc-b2b';

        public static function init() {
            add_action( self::HOOK, array( __CLASS__, 'run' ) );
            add_action( 'init', array( __CLASS__, 'maybe_schedule' ) );
        }

        /** Ensure the recurring monthly accrual is scheduled. */
        public static function maybe_schedule() {
            if ( ! function_exists( 'as_schedule_recurring_action' ) || ! function_exists( 'as_has_scheduled_action' ) ) {
                return;
            }
            if ( as_has_scheduled_action( self::HOOK, array(), self::GROUP ) ) {
                return;
            }
            // First pass ~a day out; then monthly.
            as_schedule_recurring_action( time() + DAY_IN_SECONDS, MONTH_IN_SECONDS, self::HOOK, array(), self::GROUP );
        }

        /** Accrue rebate for every eligible company for the previous calendar month. */
        public static function run() {
            $now        = current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
            $first_this = strtotime( gmdate( 'Y-m-01 00:00:00', $now ) );
            $end        = $first_this - 1;                                  // 23:59:59 last day of prev month
            $start      = strtotime( gmdate( 'Y-m-01 00:00:00', $first_this - DAY_IN_SECONDS ) );
            $label      = gmdate( 'Y-m', $start );

            foreach ( self::companies_with_rebate() as $company_id ) {
                self::accrue_for_company( $company_id, $start, $end, $label );
            }
        }

        /** @return int[] Company ids with a positive rebate %. */
        public static function companies_with_rebate() {
            $ids = get_posts( array(
                'post_type'   => SPBWC_Company::POST_TYPE,
                'post_status' => 'publish',
                'numberposts' => -1,
                'fields'      => 'ids',
                'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
                    array(
                        'key'     => SPBWC_Company::META_REBATE_PCT,
                        'value'   => 0,
                        'compare' => '>',
                        'type'    => 'DECIMAL(12,4)',
                    ),
                ),
            ) );
            return array_map( 'absint', (array) $ids );
        }

        /**
         * Accrue one company's rebate for a period. Idempotent: a period is
         * processed at most once (guarded by META_REBATE_LAST).
         *
         * @param int    $company_id Company.
         * @param int    $start      Period start (unix ts).
         * @param int    $end        Period end (unix ts).
         * @param string $label      Period label, e.g. '2026-05'.
         * @return float Rebate amount posted (0 if none / already processed).
         */
        public static function accrue_for_company( $company_id, $start, $end, $label ) {
            $company_id = absint( $company_id );
            $pct        = (float) get_post_meta( $company_id, SPBWC_Company::META_REBATE_PCT, true );
            if ( $company_id <= 0 || $pct <= 0 ) {
                return 0.0;
            }
            if ( (string) get_post_meta( $company_id, SPBWC_Company::META_REBATE_LAST, true ) === (string) $label ) {
                return 0.0; // Already processed this period.
            }

            $members = SPBWC_Company::get_members( $company_id );
            $net     = 0.0;
            foreach ( (array) $members as $m ) {
                $orders = wc_get_orders( array(
                    'customer_id'    => (int) $m->ID,
                    'status'         => array( 'completed' ),
                    'date_completed' => $start . '...' . $end,
                    'limit'          => -1,
                    'return'         => 'objects',
                ) );
                foreach ( (array) $orders as $order ) {
                    $net += (float) $order->get_total() - (float) $order->get_total_refunded();
                }
            }

            $rebate = $net > 0 ? round( $net * $pct / 100, wc_get_rounding_precision() ) : 0.0;
            if ( $rebate > 0 && class_exists( 'SPBWC_B2B_Ledger' ) ) {
                SPBWC_B2B_Ledger::post_rebate( $company_id, $rebate, array(
                    'ref_type' => 'rebate_run',
                    'note'     => sprintf(
                        /* translators: 1: period label, 2: rebate percent, 3: net spend. */
                        __( 'Volume rebate %1$s — %2$s%% of %3$s net spend', 'storelly-product-builder-for-woocommerce' ),
                        $label,
                        rtrim( rtrim( number_format( $pct, 2 ), '0' ), '.' ),
                        wp_strip_all_tags( wc_price( $net ) )
                    ),
                ) );
            }

            // Mark the period processed (even at 0) so it never double-accrues.
            update_post_meta( $company_id, SPBWC_Company::META_REBATE_LAST, (string) $label );
            return (float) $rebate;
        }
    }
}
