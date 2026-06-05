<?php
/**
 * Per-company product price overrides (M4).
 *
 * A merchant can bind specific products to a company at a custom per-unit price:
 * either a percentage off the retail base or a fixed unit price. Stored in a
 * dedicated table and resolved by SPBWC_B2B_Pricing's cascade
 * (per-company fixed > per-company pct > tier % > retail).
 *
 * The table is created idempotently (db-version guard) and dropped on uninstall.
 * See docs/SPEC_B2B_CLIENT.md §3.5 + §5.
 *
 * @package Storelly
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'SPBWC_B2B_Price_Rules' ) ) {

    class SPBWC_B2B_Price_Rules {

        const DB_VERSION_OPTION = 'spbwc_b2b_price_rules_db';
        const DB_VERSION        = '1';

        const TYPE_PCT   = 'pct';
        const TYPE_FIXED = 'fixed';

        /** @return string Fully-qualified table name. */
        public static function table() {
            global $wpdb;
            return $wpdb->prefix . 'spbwc_b2b_price_rules';
        }

        public static function init() {
            add_action( 'init', array( __CLASS__, 'maybe_install' ) );
        }

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
                company_id bigint(20) unsigned NOT NULL,
                product_id bigint(20) unsigned NOT NULL,
                override_type varchar(10) NOT NULL DEFAULT 'pct',
                value decimal(12,4) NOT NULL DEFAULT 0,
                min_qty int(11) NOT NULL DEFAULT 0,
                valid_until datetime DEFAULT NULL,
                status varchar(10) NOT NULL DEFAULT 'active',
                created datetime DEFAULT NULL,
                created_by bigint(20) unsigned NOT NULL DEFAULT 0,
                PRIMARY KEY  (id),
                UNIQUE KEY company_product (company_id, product_id),
                KEY company_id (company_id)
            ) {$charset_collate};";
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            dbDelta( $sql );
            update_option( self::DB_VERSION_OPTION, self::DB_VERSION, false );
        }

        /** Drop the table (called from uninstall.php). */
        public static function drop_table() {
            global $wpdb;
            $table = self::table();
            $wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
            delete_option( self::DB_VERSION_OPTION );
        }

        /* ── CRUD ─────────────────────────────────────────────────── */

        /**
         * Upsert a rule for company + product.
         *
         * @param int    $company_id Company.
         * @param int    $product_id Product/variation.
         * @param string $type       'pct' | 'fixed'.
         * @param float  $value      Percent or unit price.
         * @param int    $min_qty    Minimum qty (0 = none).
         * @param string $valid_until 'Y-m-d' or '' for no expiry.
         * @return bool
         */
        public static function save_rule( $company_id, $product_id, $type, $value, $min_qty = 0, $valid_until = '' ) {
            global $wpdb;
            $company_id = absint( $company_id );
            $product_id = absint( $product_id );
            $type       = ( self::TYPE_FIXED === $type ) ? self::TYPE_FIXED : self::TYPE_PCT;
            $value      = (float) $value;
            if ( self::TYPE_PCT === $type ) {
                $value = max( 0, min( 100, $value ) );
            } else {
                $value = max( 0, $value );
            }
            if ( ! $company_id || ! $product_id ) {
                return false;
            }
            $valid = '';
            if ( '' !== $valid_until ) {
                $ts = strtotime( $valid_until );
                if ( $ts ) {
                    $valid = gmdate( 'Y-m-d H:i:s', $ts );
                }
            }
            $existing = self::get_rule_row( $company_id, $product_id );
            $data     = array(
                'company_id'    => $company_id,
                'product_id'    => $product_id,
                'override_type' => $type,
                'value'         => $value,
                'min_qty'       => absint( $min_qty ),
                'valid_until'   => '' !== $valid ? $valid : null,
                'status'        => 'active',
            );
            if ( $existing ) {
                return false !== $wpdb->update( self::table(), $data, array( 'id' => (int) $existing->id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            }
            $data['created']    = current_time( 'mysql' );
            $data['created_by'] = get_current_user_id();
            return false !== $wpdb->insert( self::table(), $data ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        }

        /**
         * @param int $company_id Company.
         * @param int $product_id Product.
         * @return object|null Raw row.
         */
        public static function get_rule_row( $company_id, $product_id ) {
            $company_id = absint( $company_id );
            $product_id = absint( $product_id );
            $map        = self::company_rule_map( $company_id );
            return isset( $map[ $product_id ] ) ? $map[ $product_id ] : null;
        }

        /**
         * All of a company's rules as a [product_id => row] map, loaded once and
         * cached for the request. This collapses the per-cart-item SELECT that
         * ran on every cart recalculation into a single query per company.
         *
         * @param int $company_id Company.
         * @return array<int,object>
         */
        protected static function company_rule_map( $company_id ) {
            static $cache = array();
            $company_id = absint( $company_id );
            if ( isset( $cache[ $company_id ] ) ) {
                return $cache[ $company_id ];
            }
            global $wpdb;
            $table = self::table();
            $rows  = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                $wpdb->prepare( "SELECT * FROM {$table} WHERE company_id = %d", $company_id )
            );
            $map = array();
            foreach ( (array) $rows as $r ) {
                $map[ (int) $r->product_id ] = $r;
            }
            $cache[ $company_id ] = $map;
            return $map;
        }

        /**
         * Active, in-date rule for company + product (respecting min_qty).
         *
         * @param int $company_id Company.
         * @param int $product_id Product.
         * @param int $qty        Quantity in cart (0 = ignore min_qty, for display).
         * @return object|null
         */
        public static function get_active_rule( $company_id, $product_id, $qty = 0 ) {
            $row = self::get_rule_row( $company_id, $product_id );
            if ( ! $row || 'active' !== $row->status ) {
                return null;
            }
            if ( ! empty( $row->valid_until ) && strtotime( $row->valid_until . ' UTC' ) < time() ) {
                return null;
            }
            if ( $qty > 0 && (int) $row->min_qty > 0 && $qty < (int) $row->min_qty ) {
                return null;
            }
            return $row;
        }

        /**
         * @param int $company_id Company.
         * @return object[] All rules for a company.
         */
        public static function get_rules_for_company( $company_id ) {
            global $wpdb;
            $table = self::table();
            return $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                $wpdb->prepare( "SELECT * FROM {$table} WHERE company_id = %d ORDER BY id ASC", absint( $company_id ) )
            );
        }

        /**
         * @param int $company_id Company.
         * @param int $product_id Product.
         * @return bool
         */
        public static function delete_rule( $company_id, $product_id ) {
            global $wpdb;
            return false !== $wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                self::table(),
                array( 'company_id' => absint( $company_id ), 'product_id' => absint( $product_id ) )
            );
        }

        /* ── Resolution ───────────────────────────────────────────── */

        /**
         * Resolve the per-company base unit price for a product, or null when no
         * rule applies (caller then falls back to tier %).
         *
         * @param int   $company_id Company.
         * @param int   $product_id Product.
         * @param float $base       Retail base unit price.
         * @param int   $qty        Cart quantity (0 = display).
         * @return float|null
         */
        public static function resolve( $company_id, $product_id, $base, $qty = 0 ) {
            $rule = self::get_active_rule( $company_id, $product_id, $qty );
            if ( ! $rule ) {
                return null;
            }
            if ( self::TYPE_FIXED === $rule->override_type ) {
                return max( 0.0, (float) $rule->value );
            }
            $pct = max( 0.0, min( 100.0, (float) $rule->value ) );
            return max( 0.0, (float) $base * ( ( 100.0 - $pct ) / 100.0 ) );
        }
    }
}
