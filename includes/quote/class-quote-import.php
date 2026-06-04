<?php
/**
 * Quote Import controller (Quote Import & Sync, M1).
 *
 * Collects source adapters, scans them, and runs idempotent import batches via
 * Action Scheduler (HPOS- and local-cron-safe, mirroring the M7 migrator). Each
 * imported quote stores `_spbwc_imported_from` = "<adapter>:<ref>" and the source
 * row is marked done, so re-running import never duplicates.
 *
 * @package Storelly
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'SPBWC_Quote_Import' ) ) {

    class SPBWC_Quote_Import {

        const HOOK     = 'spbwc_quote_import_batch';
        const PAGE     = 'spbwc-quote-import';
        const BATCH    = 25;
        const META_REF = '_spbwc_imported_from';

        /** @var SPBWC_Quote_Source_Adapter[]|null */
        protected static $adapters = null;

        public static function init() {
            add_action( self::HOOK, array( __CLASS__, 'run_batch' ), 10, 1 );
            add_action( 'admin_menu', array( __CLASS__, 'register_menu' ), 60 );
            add_action( 'admin_post_spbwc_quote_import', array( __CLASS__, 'handle_import' ) );
        }

        /**
         * Registered adapters (built-ins + filter). Built-ins are added as more
         * milestones land; M1 ships the universal WooCommerce-orders adapter.
         *
         * @return SPBWC_Quote_Source_Adapter[]
         */
        public static function adapters() {
            if ( null !== self::$adapters ) {
                return self::$adapters;
            }
            $built_in = array();
            if ( class_exists( 'SPBWC_Quote_Adapter_Woo_Orders' ) ) {
                $built_in[] = new SPBWC_Quote_Adapter_Woo_Orders();
            }
            /**
             * Register additional quote import source adapters.
             *
             * @param SPBWC_Quote_Source_Adapter[] $built_in
             */
            $all = apply_filters( 'spbwc_quote_source_adapters', $built_in );
            self::$adapters = array_filter(
                (array) $all,
                static function ( $a ) {
                    return $a instanceof SPBWC_Quote_Source_Adapter;
                }
            );
            return self::$adapters;
        }

        public static function get_adapter( $id ) {
            foreach ( self::adapters() as $adapter ) {
                if ( $adapter->id() === $id ) {
                    return $adapter;
                }
            }
            return null;
        }

        /**
         * Available sources with their importable counts.
         *
         * @return array[] [ id, label, description, count ]
         */
        public static function scan() {
            $out = array();
            foreach ( self::adapters() as $adapter ) {
                if ( ! $adapter->is_available() ) {
                    continue;
                }
                $out[] = array(
                    'id'          => $adapter->id(),
                    'label'       => $adapter->label(),
                    'description' => $adapter->description(),
                    'count'       => (int) $adapter->count_importable(),
                );
            }
            return $out;
        }

        /**
         * All quote post statuses. Quote statuses are registered with
         * exclude_from_search, so WP_Query's 'any' skips them — we must pass the
         * explicit slug list or dedupe lookups miss every existing quote.
         *
         * @return string[]
         */
        protected static function quote_statuses() {
            return array_keys( SPBWC_Quote::statuses() );
        }

        /** Has a quote already been imported for this source ref? */
        public static function ref_exists( $ref ) {
            $found = get_posts(
                array(
                    'post_type'   => SPBWC_Quote::POST_TYPE,
                    'post_status' => self::quote_statuses(),
                    'fields'      => 'ids',
                    'numberposts' => 1,
                    // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Dedupe lookup during import only.
                    'meta_key'    => self::META_REF,
                    // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query
                    'meta_value'  => $ref,
                )
            );
            return ! empty( $found );
        }

        /**
         * Import one raw row through an adapter.
         *
         * @param SPBWC_Quote_Source_Adapter $adapter Adapter.
         * @param mixed                      $row     Raw row.
         * @return int|false New quote id.
         */
        public static function import_one( $adapter, $row ) {
            $ref = $adapter->id() . ':' . $adapter->source_ref( $row );
            if ( self::ref_exists( $ref ) ) {
                $adapter->mark_imported( $row, 0 );
                return false;
            }
            $map = $adapter->map_to_quote( $row );
            if ( empty( $map ) || empty( $map['request'] ) ) {
                return false;
            }
            $quote_id = SPBWC_Quote::create( $map['request'], isset( $map['user_id'] ) ? (int) $map['user_id'] : 0 );
            if ( is_wp_error( $quote_id ) || ! $quote_id ) {
                return false;
            }
            if ( ! empty( $map['lines'] ) ) {
                SPBWC_Quote::set_lines( $quote_id, $map['lines'] );
            }
            $post_update = array( 'ID' => $quote_id );
            if ( ! empty( $map['status'] ) ) {
                $post_update['post_status'] = $map['status'];
            }
            if ( ! empty( $map['date'] ) ) {
                $post_update['post_date'] = $map['date'];
            }
            if ( count( $post_update ) > 1 ) {
                wp_update_post( $post_update );
            }
            if ( ! empty( $map['product_id'] ) ) {
                update_post_meta( $quote_id, '_spbwc_quote_product_id', (int) $map['product_id'] );
            }
            update_post_meta( $quote_id, self::META_REF, $ref );
            $adapter->mark_imported( $row, $quote_id );
            return $quote_id;
        }

        /**
         * Process one batch for an adapter; reschedule if more remain.
         *
         * @param string $adapter_id Adapter id.
         * @return int Imported this run.
         */
        public static function run_batch( $adapter_id ) {
            $adapter = self::get_adapter( $adapter_id );
            if ( ! $adapter || ! $adapter->is_available() ) {
                return 0;
            }
            $rows = $adapter->fetch_batch( self::BATCH );
            $done = 0;
            foreach ( (array) $rows as $row ) {
                if ( self::import_one( $adapter, $row ) ) {
                    $done++;
                }
            }
            if ( count( (array) $rows ) >= self::BATCH && function_exists( 'as_schedule_single_action' ) ) {
                as_schedule_single_action( time() + 20, self::HOOK, array( $adapter_id ), 'spbwc-quote' );
            }
            return $done;
        }

        /* ── Admin ─────────────────────────────────────────────────── */

        public static function register_menu() {
            add_submenu_page(
                SPBWC_Quote_Admin::PAGE_SLUG,
                __( 'Import quotes', 'storelly-product-builder-for-woocommerce' ),
                __( 'Import', 'storelly-product-builder-for-woocommerce' ),
                SPBWC_Quote_Admin::CAPABILITY,
                self::PAGE,
                array( __CLASS__, 'render_page' )
            );
        }

        public static function page_url() {
            return admin_url( 'admin.php?page=' . self::PAGE );
        }

        public static function handle_import() {
            if ( ! current_user_can( SPBWC_Quote_Admin::CAPABILITY ) ) {
                wp_die( esc_html__( 'You are not allowed to do this.', 'storelly-product-builder-for-woocommerce' ) );
            }
            check_admin_referer( 'spbwc_quote_import' );
            $adapter_id = isset( $_POST['adapter'] ) ? sanitize_key( wp_unslash( $_POST['adapter'] ) ) : '';
            $adapter    = self::get_adapter( $adapter_id );
            $imported   = 0;
            $remaining  = 0;
            if ( $adapter && $adapter->is_available() ) {
                // Run one batch synchronously for instant feedback, queue the rest.
                $imported  = self::run_batch( $adapter_id );
                $remaining = (int) $adapter->count_importable();
            }
            $url = add_query_arg(
                array(
                    'imported'  => $imported,
                    'remaining' => $remaining,
                ),
                self::page_url()
            );
            wp_safe_redirect( $url );
            exit;
        }

        public static function render_page() {
            if ( ! current_user_can( SPBWC_Quote_Admin::CAPABILITY ) ) {
                return;
            }
            $sources   = self::scan();
            $imported  = isset( $_GET['imported'] ) ? absint( wp_unslash( $_GET['imported'] ) ) : -1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only notice flag.
            $remaining = isset( $_GET['remaining'] ) ? absint( wp_unslash( $_GET['remaining'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only notice flag.
            ?>
            <div class="wrap spbwc-quote-import">
                <h1><?php esc_html_e( 'Import quotes', 'storelly-product-builder-for-woocommerce' ); ?></h1>
                <p class="description"><?php esc_html_e( 'Bring existing quotes and unpaid orders into Storelly. Importing is non-destructive — the original records are kept — and safe to re-run.', 'storelly-product-builder-for-woocommerce' ); ?></p>

                <?php if ( $imported >= 0 ) : ?>
                    <div class="notice notice-success is-dismissible"><p>
                        <?php
                        printf(
                            /* translators: %d: number of quotes imported. */
                            esc_html( _n( 'Imported %d quote.', 'Imported %d quotes.', $imported, 'storelly-product-builder-for-woocommerce' ) ),
                            (int) $imported
                        );
                        if ( $remaining > 0 ) {
                            echo ' ';
                            printf(
                                /* translators: %d: number of quotes still importing in the background. */
                                esc_html( _n( '%d more is importing in the background.', '%d more are importing in the background.', $remaining, 'storelly-product-builder-for-woocommerce' ) ),
                                (int) $remaining
                            );
                        }
                        ?>
                    </p></div>
                <?php endif; ?>

                <table class="widefat striped" style="max-width:760px;margin-top:16px;">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Source', 'storelly-product-builder-for-woocommerce' ); ?></th>
                            <th style="width:120px;"><?php esc_html_e( 'Found', 'storelly-product-builder-for-woocommerce' ); ?></th>
                            <th style="width:160px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( empty( $sources ) ) : ?>
                            <tr><td colspan="3"><?php esc_html_e( 'No importable sources detected on this site.', 'storelly-product-builder-for-woocommerce' ); ?></td></tr>
                        <?php else : ?>
                            <?php foreach ( $sources as $source ) : ?>
                                <tr>
                                    <td>
                                        <strong><?php echo esc_html( $source['label'] ); ?></strong>
                                        <?php if ( $source['description'] ) : ?>
                                            <br /><span class="description"><?php echo esc_html( $source['description'] ); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo esc_html( number_format_i18n( $source['count'] ) ); ?></td>
                                    <td>
                                        <?php if ( $source['count'] > 0 ) : ?>
                                            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                                                <?php wp_nonce_field( 'spbwc_quote_import' ); ?>
                                                <input type="hidden" name="action" value="spbwc_quote_import" />
                                                <input type="hidden" name="adapter" value="<?php echo esc_attr( $source['id'] ); ?>" />
                                                <button type="submit" class="button button-primary"><?php esc_html_e( 'Import', 'storelly-product-builder-for-woocommerce' ); ?></button>
                                            </form>
                                        <?php else : ?>
                                            <span class="description"><?php esc_html_e( 'Nothing to import', 'storelly-product-builder-for-woocommerce' ); ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php
        }
    }
}
