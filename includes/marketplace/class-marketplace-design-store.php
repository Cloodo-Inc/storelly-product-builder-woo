<?php
/**
 * Marketplace Design Store — CRUD for designer-marketplace design records.
 *
 * This is the storelly-side replacement for pc-designer's
 * `{prefix}nbdesigner_templates` table. We deliberately use a different table
 * name (`{prefix}storelly_marketplace_designs`) so both plugins can coexist
 * safely on the same site without trampling each other's data.
 *
 * The schema mirrors only the columns the marketplace launcher actually
 * reads/writes — not every column of the original templates table.
 *
 * @package Storelly
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'SPBWC_Marketplace_Design_Store' ) ) {

    class SPBWC_Marketplace_Design_Store {

        /**
         * Short table identifier (without WP prefix).
         */
        const TABLE = 'storelly_marketplace_designs';

        /**
         * Hook into the marketplace bridge's `nbd_create_tables` action so the
         * table is created on activation alongside the launcher's own tables.
         */
        public static function init() {
            add_action( 'nbd_create_tables', array( __CLASS__, 'install' ) );
        }

        /**
         * Full table name including the active $wpdb->prefix.
         *
         * @return string
         */
        public static function get_table_name() {
            global $wpdb;
            return $wpdb->prefix . self::TABLE;
        }

        /**
         * Create / upgrade the designs table via dbDelta().
         */
        public static function install() {
            global $wpdb;

            $table_name      = self::get_table_name();
            $charset_collate = $wpdb->get_charset_collate();

            $sql = "CREATE TABLE {$table_name} (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id BIGINT(20) UNSIGNED NOT NULL,
                product_id BIGINT(20) UNSIGNED DEFAULT NULL,
                variation_id BIGINT(20) UNSIGNED DEFAULT NULL,
                folder VARCHAR(190) DEFAULT NULL,
                type VARCHAR(30) DEFAULT NULL,
                publish TINYINT(1) NOT NULL DEFAULT 0,
                name VARCHAR(190) DEFAULT NULL,
                thumbnail VARCHAR(190) DEFAULT NULL,
                colors LONGTEXT,
                resource VARCHAR(190) DEFAULT NULL,
                sales BIGINT(20) UNSIGNED DEFAULT 0,
                hit BIGINT(20) UNSIGNED DEFAULT 0,
                created_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                KEY user_id (user_id),
                KEY product_id (product_id),
                KEY publish (publish)
            ) {$charset_collate};";

            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            dbDelta( $sql );
        }

        /**
         * Fetch a single design row by primary key.
         *
         * @param int $id Design row id.
         * @return array|null Associative row or null if not found.
         */
        public static function get_by_id( $id ) {
            global $wpdb;
            $id    = absint( $id );
            if ( ! $id ) {
                return null;
            }
            $table = self::get_table_name();
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );
            return $row ? $row : null;
        }

        /**
         * List designs belonging to a given user.
         *
         * @param int   $user_id WP user id.
         * @param array $args    Optional filters:
         *                       - publish (int|null): 0/1 filter on publish column.
         *                       - limit (int): max rows (default 50, capped at 500).
         *                       - offset (int).
         *                       - orderby (string): allowed: id, created_date, sales, hit.
         *                       - order (string): ASC | DESC.
         * @return array List of associative rows.
         */
        public static function get_designs_for_user( $user_id, $args = array() ) {
            global $wpdb;
            $user_id = absint( $user_id );
            if ( ! $user_id ) {
                return array();
            }

            $defaults = array(
                'publish' => null,
                'limit'   => 50,
                'offset'  => 0,
                'orderby' => 'created_date',
                'order'   => 'DESC',
            );
            $args = wp_parse_args( $args, $defaults );

            $allowed_orderby = array( 'id', 'created_date', 'sales', 'hit' );
            $orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'created_date';
            $order           = ( 'ASC' === strtoupper( $args['order'] ) ) ? 'ASC' : 'DESC';
            $limit           = max( 1, min( 500, (int) $args['limit'] ) );
            $offset          = max( 0, (int) $args['offset'] );

            $table  = self::get_table_name();
            $params = array( $user_id );
            $where  = 'WHERE user_id = %d';

            if ( null !== $args['publish'] ) {
                $where   .= ' AND publish = %d';
                $params[] = (int) $args['publish'];
            }

            $params[] = $limit;
            $params[] = $offset;

            $sql = "SELECT * FROM {$table} {$where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";

            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );
            return is_array( $rows ) ? $rows : array();
        }

        /**
         * Count user designs grouped by `publish` status. Mirrors the query
         * used in launcher/util.php (count_my_designs_by_status).
         *
         * @param int $user_id WP user id.
         * @return array<int,int> Map of publish_value => count.
         */
        public static function count_by_status( $user_id ) {
            global $wpdb;
            $user_id = absint( $user_id );
            if ( ! $user_id ) {
                return array();
            }
            $table = self::get_table_name();
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT publish, COUNT(*) AS num_designs FROM {$table} WHERE user_id = %d GROUP BY publish",
                    $user_id
                ),
                ARRAY_A
            );
            $out = array();
            if ( is_array( $rows ) ) {
                foreach ( $rows as $row ) {
                    $out[ (int) $row['publish'] ] = (int) $row['num_designs'];
                }
            }
            return $out;
        }

        /**
         * Insert a new design row.
         *
         * @param array $data Column => value map.
         * @return int|false Inserted id, or false on failure.
         */
        public static function insert( $data ) {
            global $wpdb;
            $defaults = array(
                'user_id'      => 0,
                'product_id'   => null,
                'variation_id' => null,
                'folder'       => null,
                'type'         => null,
                'publish'      => 0,
                'name'         => null,
                'thumbnail'    => null,
                'colors'       => null,
                'resource'     => null,
                'sales'        => 0,
                'hit'          => 0,
                'created_date' => current_time( 'mysql' ),
            );
            $data     = wp_parse_args( $data, $defaults );
            $inserted = $wpdb->insert( self::get_table_name(), $data );
            return $inserted ? (int) $wpdb->insert_id : false;
        }

        /**
         * Update a design row by id.
         *
         * @param int   $id   Row id.
         * @param array $data Column => value map.
         * @return int|false Rows affected, or false on failure.
         */
        public static function update( $id, $data ) {
            global $wpdb;
            $id = absint( $id );
            if ( ! $id ) {
                return false;
            }
            return $wpdb->update( self::get_table_name(), $data, array( 'id' => $id ) );
        }

        /**
         * Delete a design row by id (optionally scoped to a user).
         *
         * @param int      $id      Row id.
         * @param int|null $user_id Optional owning user.
         * @return int|false Rows deleted, or false on failure.
         */
        public static function delete( $id, $user_id = null ) {
            global $wpdb;
            $id = absint( $id );
            if ( ! $id ) {
                return false;
            }
            $where = array( 'id' => $id );
            if ( null !== $user_id ) {
                $where['user_id'] = absint( $user_id );
            }
            return $wpdb->delete( self::get_table_name(), $where );
        }
    }

    SPBWC_Marketplace_Design_Store::init();
}
