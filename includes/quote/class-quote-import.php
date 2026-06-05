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

// Source adapters (the base + Woo-orders are loaded by the main plugin file;
// these third-party ones are pulled in here so no loader edit is needed).
require_once __DIR__ . '/import/class-quote-adapter-qfw.php';
require_once __DIR__ . '/import/class-quote-adapter-yith-raq.php';
require_once __DIR__ . '/import/class-quote-form-adapter.php';
require_once __DIR__ . '/import/class-quote-adapter-flamingo.php';
require_once __DIR__ . '/import/class-quote-adapter-fluentforms.php';

if ( ! class_exists( 'SPBWC_Quote_Import' ) ) {

    class SPBWC_Quote_Import {

        const HOOK     = 'spbwc_quote_import_batch';
        const PAGE     = 'spbwc-quote-import';
        const BATCH    = 25;
        const META_REF = '_spbwc_imported_from';

        /** @var SPBWC_Quote_Source_Adapter[]|null */
        protected static $adapters = null;

        public static function init() {
            add_action( self::HOOK, array( __CLASS__, 'run_batch' ), 10, 2 );
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
            if ( class_exists( 'SPBWC_Quote_Adapter_Qfw' ) ) {
                $built_in[] = new SPBWC_Quote_Adapter_Qfw();
            }
            if ( class_exists( 'SPBWC_Quote_Adapter_Yith_Raq' ) ) {
                $built_in[] = new SPBWC_Quote_Adapter_Yith_Raq();
            }
            if ( class_exists( 'SPBWC_Quote_Adapter_Flamingo' ) ) {
                $built_in[] = new SPBWC_Quote_Adapter_Flamingo();
            }
            if ( class_exists( 'SPBWC_Quote_Adapter_Fluentforms' ) ) {
                $built_in[] = new SPBWC_Quote_Adapter_Fluentforms();
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

        /**
         * Set of already-imported source refs for an adapter (prefix stripped),
         * so a form adapter can skip imported entries without one query per row.
         *
         * @param string $adapter_id Adapter id.
         * @return array ref => true
         */
        public static function imported_refs( $adapter_id ) {
            $prefix = $adapter_id . ':';
            $quotes = get_posts(
                array(
                    'post_type'   => SPBWC_Quote::POST_TYPE,
                    'post_status' => self::quote_statuses(),
                    'fields'      => 'ids',
                    'numberposts' => -1,
                    // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- One-time import scan.
                    'meta_query'  => array(
                        array(
                            'key'     => self::META_REF,
                            'value'   => $prefix,
                            'compare' => 'LIKE',
                        ),
                    ),
                )
            );
            $set = array();
            foreach ( (array) $quotes as $qid ) {
                $ref = (string) get_post_meta( $qid, self::META_REF, true );
                if ( 0 === strpos( $ref, $prefix ) ) {
                    $set[ substr( $ref, strlen( $prefix ) ) ] = true;
                }
            }
            return $set;
        }

        /* ── Field mapping store (contact-form sources, M3) ──────────── */

        const MAPS_OPTION = 'spbwc_quote_import_maps';

        /** Saved field mapping (target => form field key) for one form. */
        public static function get_mapping( $adapter_id, $form_id ) {
            $maps = get_option( self::MAPS_OPTION, array() );
            $key  = $adapter_id . ':' . $form_id;
            return ( is_array( $maps ) && ! empty( $maps[ $key ] ) && is_array( $maps[ $key ] ) ) ? $maps[ $key ] : array();
        }

        /** Persist a sanitized field mapping for one form. */
        public static function save_mapping( $adapter_id, $form_id, array $map ) {
            $maps  = get_option( self::MAPS_OPTION, array() );
            $maps  = is_array( $maps ) ? $maps : array();
            $clean = array();
            foreach ( SPBWC_Quote_Form_Adapter::TARGETS as $target ) {
                if ( ! empty( $map[ $target ] ) ) {
                    $clean[ $target ] = sanitize_text_field( $map[ $target ] );
                }
            }
            $maps[ $adapter_id . ':' . $form_id ] = $clean;
            update_option( self::MAPS_OPTION, $maps, false );
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
        public static function run_batch( $adapter_id, $form_id = '' ) {
            $adapter = self::get_adapter( $adapter_id );
            if ( ! $adapter || ! $adapter->is_available() ) {
                return 0;
            }
            // Form sources can be scoped to a single form.
            if ( $adapter instanceof SPBWC_Quote_Form_Adapter ) {
                $adapter->scope_form = (string) $form_id;
            }
            $rows = $adapter->fetch_batch( self::BATCH );
            $done = 0;
            foreach ( (array) $rows as $row ) {
                if ( self::import_one( $adapter, $row ) ) {
                    $done++;
                }
            }
            if ( count( (array) $rows ) >= self::BATCH && function_exists( 'as_schedule_single_action' ) ) {
                as_schedule_single_action( time() + 20, self::HOOK, array( $adapter_id, (string) $form_id ), 'spbwc-quote' );
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
            $form_id    = isset( $_POST['form_id'] ) ? sanitize_text_field( wp_unslash( $_POST['form_id'] ) ) : '';
            $adapter    = self::get_adapter( $adapter_id );
            $imported   = 0;
            $remaining  = 0;

            // Contact-form sources post a field mapping with the chosen form.
            if ( $adapter instanceof SPBWC_Quote_Form_Adapter && '' !== $form_id ) {
                // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized per element in save_mapping().
                $raw_map = isset( $_POST['mapping'] ) ? (array) wp_unslash( $_POST['mapping'] ) : array();
                self::save_mapping( $adapter_id, $form_id, $raw_map );
            }

            if ( $adapter && $adapter->is_available() ) {
                // Run one batch synchronously for instant feedback, queue the rest.
                $imported  = self::run_batch( $adapter_id, $form_id );
                if ( $adapter instanceof SPBWC_Quote_Form_Adapter ) {
                    $adapter->scope_form = $form_id;
                }
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
            $imported  = isset( $_GET['imported'] ) ? absint( wp_unslash( $_GET['imported'] ) ) : -1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only notice flag.
            $remaining = isset( $_GET['remaining'] ) ? absint( wp_unslash( $_GET['remaining'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only notice flag.
            $workspace = SPBWC_Quote_Admin::page_url();

            // Partition available sources: simple order-based vs field-mapped forms.
            $order_sources = array();
            $form_adapters = array();
            foreach ( self::adapters() as $adapter ) {
                if ( ! $adapter->is_available() ) {
                    continue;
                }
                if ( $adapter instanceof SPBWC_Quote_Form_Adapter ) {
                    $form_adapters[] = $adapter;
                } else {
                    $order_sources[] = array(
                        'id'          => $adapter->id(),
                        'label'       => $adapter->label(),
                        'description' => $adapter->description(),
                        'count'       => (int) $adapter->count_importable(),
                    );
                }
            }
            $has_any = ! empty( $order_sources ) || ! empty( $form_adapters );
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

                    <?php if ( ! $has_any ) : ?>
                        <div class="spbwc-empty-state">
                            <span class="dashicons dashicons-search" aria-hidden="true"></span>
                            <p><?php esc_html_e( 'No importable sources detected on this site yet.', 'storelly-product-builder-for-woocommerce' ); ?></p>
                            <p class="spbwc-empty-state__hint"><?php esc_html_e( 'Storelly can import from WooCommerce unpaid orders and popular quote / contact-form plugins. Install or activate one, then return here.', 'storelly-product-builder-for-woocommerce' ); ?></p>
                        </div>
                    <?php else : ?>
                        <?php if ( ! empty( $order_sources ) ) : ?>
                            <ul class="spbwc-import-list">
                                <?php foreach ( $order_sources as $source ) : ?>
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

                        <?php foreach ( $form_adapters as $form_adapter ) : ?>
                            <?php self::render_form_source( $form_adapter ); ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php
        }

        /**
         * Render a contact-form source: per form, a field-mapping editor
         * (quote field → form field, pre-filled by heuristic) + an Import button.
         *
         * @param SPBWC_Quote_Form_Adapter $adapter Form adapter.
         */
        protected static function render_form_source( $adapter ) {
            $forms = $adapter->forms();
            if ( empty( $forms ) ) {
                return;
            }
            $labels = array(
                'name'    => __( 'Name', 'storelly-product-builder-for-woocommerce' ),
                'email'   => __( 'Email', 'storelly-product-builder-for-woocommerce' ),
                'phone'   => __( 'Phone', 'storelly-product-builder-for-woocommerce' ),
                'company' => __( 'Company', 'storelly-product-builder-for-woocommerce' ),
                'message' => __( 'Message', 'storelly-product-builder-for-woocommerce' ),
            );
            ?>
            <div class="spbwc-import-formsrc">
                <div class="spbwc-import-formsrc__head">
                    <span class="spbwc-import-formsrc__name"><?php echo esc_html( $adapter->label() ); ?></span>
                    <?php if ( $adapter->description() ) : ?>
                        <span class="spbwc-import-source__desc"><?php echo esc_html( $adapter->description() ); ?></span>
                    <?php endif; ?>
                </div>
                <?php
                foreach ( $forms as $form ) :
                    $fid    = (string) $form['id'];
                    $fields = isset( $form['fields'] ) ? (array) $form['fields'] : array();
                    $saved  = self::get_mapping( $adapter->id(), $fid );
                    $map    = ! empty( $saved ) ? $saved : SPBWC_Quote_Form_Adapter::auto_map( $fields );
                    $adapter->scope_form = $fid;
                    $count               = (int) $adapter->count_importable();
                    $adapter->scope_form = '';
                    ?>
                    <form class="spbwc-import-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                        <?php wp_nonce_field( 'spbwc_quote_import' ); ?>
                        <input type="hidden" name="action" value="spbwc_quote_import" />
                        <input type="hidden" name="adapter" value="<?php echo esc_attr( $adapter->id() ); ?>" />
                        <input type="hidden" name="form_id" value="<?php echo esc_attr( $fid ); ?>" />
                        <div class="spbwc-import-form__head">
                            <span class="spbwc-import-form__title"><?php echo esc_html( $form['title'] ); ?></span>
                            <span class="spbwc-import-form__count">
                                <?php
                                printf(
                                    /* translators: %s: number of form entries. */
                                    esc_html( _n( '%s entry', '%s entries', $count, 'storelly-product-builder-for-woocommerce' ) ),
                                    esc_html( number_format_i18n( $count ) )
                                );
                                ?>
                            </span>
                        </div>
                        <div class="spbwc-import-map">
                            <?php foreach ( $labels as $target => $label ) : ?>
                                <label class="spbwc-import-map__row">
                                    <span class="spbwc-import-map__target"><?php echo esc_html( $label ); ?></span>
                                    <select name="mapping[<?php echo esc_attr( $target ); ?>]" class="spbwc-input spbwc-input--sm">
                                        <option value=""><?php esc_html_e( '— not mapped —', 'storelly-product-builder-for-woocommerce' ); ?></option>
                                        <?php foreach ( $fields as $field ) : ?>
                                            <?php $fkey = isset( $field['key'] ) ? (string) $field['key'] : ''; ?>
                                            <option value="<?php echo esc_attr( $fkey ); ?>" <?php selected( isset( $map[ $target ] ) ? $map[ $target ] : '', $fkey ); ?>>
                                                <?php echo esc_html( isset( $field['label'] ) && '' !== $field['label'] ? $field['label'] : $fkey ); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <div class="spbwc-import-form__foot">
                            <button type="submit" class="spbwc-cta-btn spbwc-cta-btn--solid" <?php disabled( 0, $count ); ?>>
                                <span class="dashicons dashicons-download" aria-hidden="true"></span>
                                <?php esc_html_e( 'Save mapping & import', 'storelly-product-builder-for-woocommerce' ); ?>
                            </button>
                        </div>
                    </form>
                <?php endforeach; ?>
            </div>
            <?php
        }
    }
}
