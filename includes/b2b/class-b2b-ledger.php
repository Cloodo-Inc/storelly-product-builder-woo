<?php
/**
 * B2B Account Credit ledger (M1).
 *
 * A single double-column ledger that powers all three B2B finance views — Wallet
 * (prepaid balance), Net Terms (negative balance bounded by a credit limit) and
 * Rebate (a credit source). They are not three features but three facets of one
 * company balance:
 *
 *     balance = SUM(credit) - SUM(debit)      // only `posted` rows
 *       balance > 0  -> prepaid wallet
 *       balance < 0  -> outstanding debt (net terms)
 *
 * The engine is ported from the designer marketplace ledger
 * (includes/launcher/class.designer.php::get_balance). To allow a future merge of
 * designer-payout and company-credit into one "wallet core", every row carries an
 * `owner_type`/`owner_id` pair (v1 only writes 'company') and every method takes a
 * generic owner — the eventual convergence is a migration + rename, not a redesign.
 *
 * Rows are append-only: corrections are posted as reversing entries
 * (adjustment/refund) or flipped to `void`, never rewritten — preserving the audit
 * trail. See docs/SPEC_B2B_ACCOUNT_CREDIT.md.
 *
 * @package Storelly
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'SPBWC_B2B_Ledger' ) ) {

    class SPBWC_B2B_Ledger {

        const DB_VERSION_OPTION = 'spbwc_b2b_ledger_db';
        const DB_VERSION        = '1';

        /* ── Owner namespace (convergence-ready) ──────────────────── */
        const OWNER_COMPANY = 'company';

        /* ── Transaction types ────────────────────────────────────── */
        const TXN_TOPUP        = 'topup';        // credit  — company funds the wallet
        const TXN_ORDER_CHARGE = 'order_charge'; // debit   — an order consumes credit
        const TXN_PAYMENT      = 'payment';      // credit  — company settles its debt
        const TXN_REBATE       = 'rebate';       // credit  — period-end cashback
        const TXN_REFUND       = 'refund';       // credit  — order refund reversal
        const TXN_ADJUSTMENT   = 'adjustment';   // either  — manual admin correction

        /* ── Row status (only `posted` counts toward balance) ─────── */
        const STATUS_POSTED  = 'posted';
        const STATUS_PENDING = 'pending'; // e.g. awaiting company-admin approval
        const STATUS_VOID    = 'void';

        /** @return string Fully-qualified table name. */
        public static function table() {
            global $wpdb;
            return $wpdb->prefix . 'spbwc_b2b_ledger';
        }

        public static function init() {
            add_action( 'init', array( __CLASS__, 'maybe_install' ) );
        }

        /* ── Install / uninstall ──────────────────────────────────── */

        /** Create/upgrade the table when the stored db-version is behind. */
        public static function maybe_install() {
            if ( get_option( self::DB_VERSION_OPTION ) === self::DB_VERSION ) {
                return;
            }
            global $wpdb;
            $table           = self::table();
            $charset_collate = $wpdb->get_charset_collate();
            $sql             = "CREATE TABLE {$table} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                owner_type varchar(20) NOT NULL DEFAULT 'company',
                owner_id bigint(20) unsigned NOT NULL,
                txn_type varchar(30) NOT NULL,
                ref_type varchar(30) DEFAULT NULL,
                ref_id bigint(20) unsigned NOT NULL DEFAULT 0,
                debit decimal(18,4) NOT NULL DEFAULT 0,
                credit decimal(18,4) NOT NULL DEFAULT 0,
                currency varchar(10) DEFAULT NULL,
                status varchar(20) NOT NULL DEFAULT 'posted',
                note text NULL,
                created_by bigint(20) unsigned NOT NULL DEFAULT 0,
                effective_date datetime NOT NULL,
                due_date datetime DEFAULT NULL,
                created datetime NOT NULL,
                PRIMARY KEY  (id),
                KEY owner (owner_type, owner_id),
                KEY ref (ref_type, ref_id),
                KEY status (status)
            ) {$charset_collate};";
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            dbDelta( $sql );
            update_option( self::DB_VERSION_OPTION, self::DB_VERSION, false );
        }

        /** Drop the table (called from uninstall). */
        public static function drop_table() {
            global $wpdb;
            $table = self::table();
            $wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
            delete_option( self::DB_VERSION_OPTION );
        }

        /* ── Write (append-only) ──────────────────────────────────── */

        /**
         * Insert one ledger entry. Low-level; prefer the post_* helpers.
         *
         * @param int   $owner_id Company id (v1).
         * @param array $args {
         *   @type string $txn_type   One of the TXN_* constants. Required.
         *   @type float  $debit       Money out (default 0).
         *   @type float  $credit      Money in (default 0).
         *   @type string $ref_type    'order'|'manual'|'rebate_run'… (default '').
         *   @type int    $ref_id      Referenced object id (default 0).
         *   @type string $status      STATUS_* (default posted).
         *   @type string $note        Admin note / description.
         *   @type string $owner_type  Default OWNER_COMPANY.
         *   @type string $currency    Default store currency.
         *   @type int    $created_by  User id (default current user).
         *   @type string $due_date    'Y-m-d H:i:s' or '' (default null).
         *   @type string $effective_date 'Y-m-d H:i:s' (default now).
         * }
         * @return int|false Inserted row id, or false on failure.
         */
        public static function record( $owner_id, array $args ) {
            global $wpdb;

            $owner_id = absint( $owner_id );
            if ( ! $owner_id || empty( $args['txn_type'] ) ) {
                return false;
            }

            $now  = current_time( 'mysql' );
            $data = array(
                'owner_type'     => isset( $args['owner_type'] ) ? sanitize_key( $args['owner_type'] ) : self::OWNER_COMPANY,
                'owner_id'       => $owner_id,
                'txn_type'       => sanitize_key( $args['txn_type'] ),
                'ref_type'       => isset( $args['ref_type'] ) ? sanitize_key( $args['ref_type'] ) : null,
                'ref_id'         => isset( $args['ref_id'] ) ? absint( $args['ref_id'] ) : 0,
                'debit'          => isset( $args['debit'] ) ? self::norm( $args['debit'] ) : 0,
                'credit'         => isset( $args['credit'] ) ? self::norm( $args['credit'] ) : 0,
                'currency'       => isset( $args['currency'] ) ? substr( sanitize_text_field( $args['currency'] ), 0, 10 ) : get_woocommerce_currency(),
                'status'         => isset( $args['status'] ) ? sanitize_key( $args['status'] ) : self::STATUS_POSTED,
                'note'           => isset( $args['note'] ) ? sanitize_textarea_field( $args['note'] ) : null,
                'created_by'     => isset( $args['created_by'] ) ? absint( $args['created_by'] ) : get_current_user_id(),
                'effective_date' => ! empty( $args['effective_date'] ) ? gmdate( 'Y-m-d H:i:s', strtotime( $args['effective_date'] ) ) : $now,
                'due_date'       => ! empty( $args['due_date'] ) ? gmdate( 'Y-m-d H:i:s', strtotime( $args['due_date'] ) ) : null,
                'created'        => $now,
            );
            $format = array( '%s', '%d', '%s', '%s', '%d', '%f', '%f', '%s', '%s', '%s', '%d', '%s', '%s', '%s' );

            $ok = $wpdb->insert( self::table(), $data, $format ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            if ( ! $ok ) {
                return false;
            }
            $id = (int) $wpdb->insert_id;

            do_action( 'spbwc_b2b_ledger_recorded', $id, $owner_id, $data );
            return $id;
        }

        /**
         * Charge an order against the account (debit), guarded against concurrent
         * over-spend by a per-owner MySQL named lock. WordPress tables may be
         * MyISAM (no transactions), so we cannot rely on InnoDB row locks.
         *
         * @param int   $owner_id Company id.
         * @param float $amount   Positive charge amount.
         * @param array $args {
         *   @type float  $credit_limit    Owner's credit line (resolved by caller).
         *   @type bool   $allow_overdraft Skip the available-credit check.
         *   @type int    $ref_id          Order id.
         *   @type string $status          Default posted.
         *   @type string $due_date        Settlement due date (net terms).
         *   @type string $note
         * }
         * @return int|WP_Error Row id, or WP_Error('insufficient_credit').
         */
        public static function post_charge( $owner_id, $amount, array $args = array() ) {
            global $wpdb;

            $owner_id = absint( $owner_id );
            $amount   = self::norm( $amount );
            if ( $amount <= 0 ) {
                return new WP_Error( 'invalid_amount', __( 'Charge amount must be positive.', 'storelly-product-builder-for-woocommerce' ) );
            }

            $lock_key = 'spbwc_b2b_ledger_' . $owner_id;
            $got_lock = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, %d)', $lock_key, 5 ) );

            try {
                $allow_overdraft = ! empty( $args['allow_overdraft'] );
                if ( ! $allow_overdraft ) {
                    $credit_limit = isset( $args['credit_limit'] ) ? self::norm( $args['credit_limit'] ) : 0;
                    $available    = self::get_available_credit( $owner_id, $credit_limit );
                    if ( $amount > $available ) {
                        return new WP_Error(
                            'insufficient_credit',
                            __( 'Order total exceeds the available company credit.', 'storelly-product-builder-for-woocommerce' ),
                            array( 'available' => $available, 'amount' => $amount )
                        );
                    }
                }

                $id = self::record( $owner_id, array(
                    'txn_type'  => self::TXN_ORDER_CHARGE,
                    'debit'     => $amount,
                    'ref_type'  => isset( $args['ref_type'] ) ? $args['ref_type'] : 'order',
                    'ref_id'    => isset( $args['ref_id'] ) ? $args['ref_id'] : 0,
                    'status'    => isset( $args['status'] ) ? $args['status'] : self::STATUS_POSTED,
                    'due_date'  => isset( $args['due_date'] ) ? $args['due_date'] : '',
                    'note'      => isset( $args['note'] ) ? $args['note'] : '',
                ) );
            } finally {
                if ( $got_lock ) {
                    $wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_key ) );
                }
            }

            return $id ? $id : new WP_Error( 'ledger_write_failed', __( 'Could not record the charge.', 'storelly-product-builder-for-woocommerce' ) );
        }

        /** Credit the wallet (admin / company top-up). */
        public static function post_topup( $owner_id, $amount, array $args = array() ) {
            return self::record( $owner_id, array_merge( $args, array(
                'txn_type' => self::TXN_TOPUP,
                'credit'   => self::norm( $amount ),
            ) ) );
        }

        /** Record a settlement of outstanding debt. */
        public static function post_payment( $owner_id, $amount, array $args = array() ) {
            return self::record( $owner_id, array_merge( $args, array(
                'txn_type' => self::TXN_PAYMENT,
                'credit'   => self::norm( $amount ),
            ) ) );
        }

        /** Accrue a period-end rebate into the wallet. */
        public static function post_rebate( $owner_id, $amount, array $args = array() ) {
            return self::record( $owner_id, array_merge( $args, array(
                'txn_type' => self::TXN_REBATE,
                'credit'   => self::norm( $amount ),
            ) ) );
        }

        /** Reverse a previous charge (order refund). */
        public static function post_refund( $owner_id, $amount, array $args = array() ) {
            return self::record( $owner_id, array_merge( $args, array(
                'txn_type' => self::TXN_REFUND,
                'credit'   => self::norm( $amount ),
                'ref_type' => isset( $args['ref_type'] ) ? $args['ref_type'] : 'order',
            ) ) );
        }

        /** Void a row (excludes it from balance). Keeps the audit trail. */
        public static function void( $row_id ) {
            global $wpdb;
            $ok = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                self::table(),
                array( 'status' => self::STATUS_VOID ),
                array( 'id' => absint( $row_id ) ),
                array( '%s' ),
                array( '%d' )
            );
            if ( $ok ) {
                do_action( 'spbwc_b2b_ledger_voided', absint( $row_id ) );
            }
            return (bool) $ok;
        }

        /* ── Read ─────────────────────────────────────────────────── */

        /**
         * Net balance (credit − debit) of posted rows up to a date.
         *
         * @param int    $owner_id   Company id.
         * @param string $on_date    'Y-m-d[ H:i:s]' or '' for now.
         * @param string $owner_type Default OWNER_COMPANY.
         * @return float
         */
        public static function get_balance( $owner_id, $on_date = '', $owner_type = self::OWNER_COMPANY ) {
            global $wpdb;
            $owner_id = absint( $owner_id );
            if ( ! $owner_id ) {
                return 0.0;
            }
            $on_date = $on_date ? gmdate( 'Y-m-d H:i:s', strtotime( $on_date ) ) : current_time( 'mysql' );

            // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery -- table() returns a safe, hardcoded $wpdb->prefix identifier; all values are bound via prepare().
            $sum = $wpdb->get_var( $wpdb->prepare(
                "SELECT COALESCE( SUM(credit) - SUM(debit), 0 )
                 FROM " . self::table() . "
                 WHERE owner_type = %s AND owner_id = %d AND status = %s AND effective_date <= %s",
                $owner_type,
                $owner_id,
                self::STATUS_POSTED,
                $on_date
            ) );
            // phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery

            return round( (float) $sum, wc_get_rounding_precision() );
        }

        /** Outstanding debt = max(0, -balance). */
        public static function get_outstanding( $owner_id, $owner_type = self::OWNER_COMPANY ) {
            return max( 0.0, -self::get_balance( $owner_id, '', $owner_type ) );
        }

        /** Prepaid wallet funds = max(0, balance). */
        public static function get_wallet( $owner_id, $owner_type = self::OWNER_COMPANY ) {
            return max( 0.0, self::get_balance( $owner_id, '', $owner_type ) );
        }

        /**
         * Spendable amount = max(0, balance + credit_limit). Wallet funds and the
         * unused credit line stack into one figure because the balance is a single
         * signed number.
         */
        public static function get_available_credit( $owner_id, $credit_limit = 0, $owner_type = self::OWNER_COMPANY ) {
            $available = self::get_balance( $owner_id, '', $owner_type ) + self::norm( $credit_limit );
            return max( 0.0, round( $available, wc_get_rounding_precision() ) );
        }

        /**
         * Statement rows, newest first.
         *
         * @return array<int,object>
         */
        public static function get_statement( $owner_id, $limit = 50, $offset = 0, $owner_type = self::OWNER_COMPANY ) {
            global $wpdb;
            $owner_id = absint( $owner_id );
            if ( ! $owner_id ) {
                return array();
            }
            // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery -- table() returns a safe, hardcoded $wpdb->prefix identifier; all values are bound via prepare().
            return $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM " . self::table() . "
                 WHERE owner_type = %s AND owner_id = %d AND status <> %s
                 ORDER BY effective_date DESC, id DESC
                 LIMIT %d OFFSET %d",
                $owner_type,
                $owner_id,
                self::STATUS_VOID,
                max( 1, absint( $limit ) ),
                max( 0, absint( $offset ) )
            ) );
            // phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
        }

        /**
         * Accounts-receivable aging: outstanding charges bucketed by how overdue
         * they are (due_date vs now). Buckets: current, d30, d60, d90 (90+).
         *
         * @return array{current:float,d30:float,d60:float,d90:float,total:float}
         */
        public static function get_aging( $owner_id, $owner_type = self::OWNER_COMPANY ) {
            global $wpdb;
            $owner_id = absint( $owner_id );
            $buckets  = array( 'current' => 0.0, 'd30' => 0.0, 'd60' => 0.0, 'd90' => 0.0, 'total' => 0.0 );
            if ( ! $owner_id ) {
                return $buckets;
            }

            // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery -- table() returns a safe, hardcoded $wpdb->prefix identifier; all values are bound via prepare().
            $rows = $wpdb->get_results( $wpdb->prepare(
                "SELECT debit, due_date FROM " . self::table() . "
                 WHERE owner_type = %s AND owner_id = %d AND status = %s
                   AND txn_type = %s AND debit > 0",
                $owner_type,
                $owner_id,
                self::STATUS_POSTED,
                self::TXN_ORDER_CHARGE
            ) );
            // phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery

            $now = current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
            foreach ( (array) $rows as $row ) {
                $amount = (float) $row->debit;
                $buckets['total'] += $amount;
                $days = $row->due_date ? floor( ( $now - strtotime( $row->due_date ) ) / DAY_IN_SECONDS ) : 0;
                if ( $days <= 0 ) {
                    $buckets['current'] += $amount;
                } elseif ( $days <= 30 ) {
                    $buckets['d30'] += $amount;
                } elseif ( $days <= 60 ) {
                    $buckets['d60'] += $amount;
                } else {
                    $buckets['d90'] += $amount;
                }
            }
            return $buckets;
        }

        /* ── Helpers ──────────────────────────────────────────────── */

        /** Normalise a money value to the store's rounding precision. */
        private static function norm( $value ) {
            return round( (float) $value, wc_get_rounding_precision() );
        }

        /** Human label for a transaction type. */
        public static function txn_label( $type ) {
            $labels = array(
                self::TXN_TOPUP        => __( 'Top-up', 'storelly-product-builder-for-woocommerce' ),
                self::TXN_ORDER_CHARGE => __( 'Order charge', 'storelly-product-builder-for-woocommerce' ),
                self::TXN_PAYMENT      => __( 'Payment', 'storelly-product-builder-for-woocommerce' ),
                self::TXN_REBATE       => __( 'Rebate', 'storelly-product-builder-for-woocommerce' ),
                self::TXN_REFUND       => __( 'Refund', 'storelly-product-builder-for-woocommerce' ),
                self::TXN_ADJUSTMENT   => __( 'Adjustment', 'storelly-product-builder-for-woocommerce' ),
            );
            return isset( $labels[ $type ] ) ? $labels[ $type ] : ucfirst( str_replace( '_', ' ', (string) $type ) );
        }
    }
}
