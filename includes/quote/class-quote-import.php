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
            add_action( 'admin_post_spbwc_quote_import', array( __CLASS__, 'handle_import' ) );
            add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
        }

        /** Load the tokenized quote stylesheet on the Quote Settings page. */
        public static function enqueue_assets() {
            $slug = defined( 'SPBWC_PB_QUOTES_SLUG' ) ? SPBWC_PB_QUOTES_SLUG : 'storelly-product-builder-for-woocommerce-quotes';
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only page check for asset loading.
            if ( ! isset( $_GET['page'] ) || $slug !== sanitize_key( wp_unslash( $_GET['page'] ) ) ) {
                return;
            }
            $css = SPBWC_PB_PLUGIN_DIR . 'static/css/quotes-admin.css';
            wp_enqueue_style(
                'spbwc-quotes-admin',
                SPBWC_PB_CSS_URL . 'quotes-admin.css',
                array(),
                file_exists( $css ) ? filemtime( $css ) : SPBWC_PB_VERSION
            );
            wp_enqueue_style( 'dashicons' );
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

        /* ── Admin (renders inside the Quote Settings "Import" tab) ──── */

        /** URL of the Import tab on the Quote Settings screen. */
        public static function tab_url( $args = array() ) {
            $base = defined( 'SPBWC_PB_QUOTES_SLUG' ) ? SPBWC_PB_QUOTES_SLUG : 'storelly-product-builder-for-woocommerce-quotes';
            return add_query_arg(
                array_merge( array( 'page' => $base, 'tab' => 'import' ), $args ),
                admin_url( 'admin.php' )
            );
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
            wp_safe_redirect( self::tab_url( array( 'imported' => $imported, 'remaining' => $remaining ) ) );
            exit;
        }

        /**
         * Render the "Import" tab body (called from the Quote Settings page).
         * Uses the shared admin design-token components only — no bespoke CSS.
         */
        public static function render_tab() {
            if ( ! current_user_can( SPBWC_Quote_Admin::CAPABILITY ) ) {
                return;
            }
            $sources   = self::scan();
            $imported  = isset( $_GET['imported'] ) ? absint( wp_unslash( $_GET['imported'] ) ) : -1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only notice flag.
            $remaining = isset( $_GET['remaining'] ) ? absint( wp_unslash( $_GET['remaining'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only notice flag.
            $workspace = SPBWC_Quote_Admin::page_url();
            ?>
            <div class="spbwc-block">
                <div class="spbwc-block__head">
                    <h3 class="spbwc-block__title">
                        <span class="dashicons dashicons-download" aria-hidden="true"></span>
                        <?php esc_html_e( 'Import existing quotes', 'storelly-product-builder-for-woocommerce' ); ?>
                    </h3>
                </div>
                <div class="spbwc-block__body">
                    <p class="spbwc-block__intro">
                        <?php esc_html_e( 'Already taking quotes elsewhere? Bring them into Storelly so every request lives in one workspace. Importing is non-destructive — your original records are untouched — and safe to re-run; nothing is imported twice.', 'storelly-product-builder-for-woocommerce' ); ?>
                    </p>

                    <?php if ( $imported >= 0 ) : ?>
                        <div class="spbwc-import-result" role="status">
                            <span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
                            <span>
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
                                <a href="<?php echo esc_url( $workspace ); ?>"><?php esc_html_e( 'View quotes →', 'storelly-product-builder-for-woocommerce' ); ?></a>
                            </span>
                        </div>
                    <?php endif; ?>

                    <?php if ( empty( $sources ) ) : ?>
                        <div class="spbwc-empty-state">
                            <span class="dashicons dashicons-search" aria-hidden="true"></span>
                            <p><?php esc_html_e( 'No importable sources detected on this site yet.', 'storelly-product-builder-for-woocommerce' ); ?></p>
                            <p class="spbwc-empty-state__hint"><?php esc_html_e( 'Storelly can import from WooCommerce unpaid orders and popular quote / contact-form plugins. Install or activate one, then return here.', 'storelly-product-builder-for-woocommerce' ); ?></p>
                        </div>
                    <?php else : ?>
                        <ul class="spbwc-import-list">
                            <?php foreach ( $sources as $source ) : ?>
                                <li class="spbwc-import-source">
                                    <div class="spbwc-import-source__info">
                                        <span class="spbwc-import-source__name"><?php echo esc_html( $source['label'] ); ?></span>
                                        <?php if ( $source['description'] ) : ?>
                                            <span class="spbwc-import-source__desc"><?php echo esc_html( $source['description'] ); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <span class="spbwc-import-source__count">
                                        <strong><?php echo esc_html( number_format_i18n( $source['count'] ) ); ?></strong>
                                        <?php esc_html_e( 'found', 'storelly-product-builder-for-woocommerce' ); ?>
                                    </span>
                                    <div class="spbwc-import-source__action">
                                        <?php if ( $source['count'] > 0 ) : ?>
                                            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                                                <?php wp_nonce_field( 'spbwc_quote_import' ); ?>
                                                <input type="hidden" name="action" value="spbwc_quote_import" />
                                                <input type="hidden" name="adapter" value="<?php echo esc_attr( $source['id'] ); ?>" />
                                                <button type="submit" class="spbwc-cta-btn spbwc-cta-btn--solid">
                                                    <span class="dashicons dashicons-download" aria-hidden="true"></span>
                                                    <?php esc_html_e( 'Import', 'storelly-product-builder-for-woocommerce' ); ?>
                                                </button>
                                            </form>
                                        <?php else : ?>
                                            <span class="spbwc-pill spbwc-pill--neutral"><?php esc_html_e( 'Nothing to import', 'storelly-product-builder-for-woocommerce' ); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
            <?php
        }
    }
}
