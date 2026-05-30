<?php
/**
 * Visual Builder admin — new entry point that re-presents the existing product
 * builder data (views, nbpb_* fields, pb_config) under its own admin menu.
 *
 * Design rule (M6.1, post-feedback):
 *   - "Is a Visual" is determined by EXPLICIT PROMOTION — not by deriving from
 *     existing data. An option with stray views/nbpb fields (e.g. a test view
 *     added in the classic Designer tab) will NOT auto-appear here. Confusion
 *     reported on screenshot of "Printing Options for Business Cards — 1 view,
 *     0 components" surfacing unexpectedly is why this gate exists.
 *   - The promoted set is stored as a single WP option `spbwc_vb_promoted`
 *     (array of option IDs). NO new DB column, NO new schema field on the
 *     `wp_storelly_product_builder_options` table, NO change to the serialized
 *     `fields` blob (which would not survive a classic-editor re-save).
 *   - The classic Pricing Options editor remains untouched.
 *   - READ-ONLY against the option data itself — promote/unpromote write only
 *     to the `wp_options` row above. No AJAX, no Angular at this milestone.
 *   - Listing -> "Visuals you have created"
 *   - Create  -> "Pick a pricing option to mark as a Visual"
 *   - Unlink  -> "Remove from Visual Builder; the option itself is preserved"
 *   - Edit    -> stub at M6.1; full implementation lands in M6.2.
 *
 * Target binding (product / category) is still read from the option's existing
 * product_ids / product_cats / apply_for columns — Visual Builder does not
 * own that binding.
 *
 * @package Storelly_Product_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'SPBWC_Visual_Builder_Admin' ) ) {

    class SPBWC_Visual_Builder_Admin {

        const CSS_HANDLE         = 'spbwc-visual-builder';
        const VIEWS_REL          = 'views/visual-builder/';
        // Two WP options drive the listing:
        //   PROMOTED  = ids the admin explicitly promoted via Create Visual.
        //   EXCLUDED  = ids the admin explicitly Unlinked from auto-detection.
        // Listing  = (auto-detected ∪ promoted) − excluded. Auto-detection
        // covers options that already had nbpb_* designer components built in
        // the classic Designer tab — they ARE visuals by existing data, so we
        // surface them automatically. The excluded list lets the admin hide
        // any auto-detected option without deleting it.
        const PROMOTED_OPTION    = 'spbwc_vb_promoted';
        const EXCLUDED_OPTION    = 'spbwc_vb_excluded';
        const NONCE_CREATE       = 'spbwc_vb_create_action';
        const NONCE_CREATE_FIELD = 'spbwc_vb_create_nonce';
        const NONCE_UNLINK       = 'spbwc_vb_unlink_action';
        const NONCE_UNLINK_FIELD = 'spbwc_vb_unlink_nonce';

        /**
         * Dispatcher. Handles POSTs (create / unlink) first, then routes GET to
         * the right view.
         */
        public static function render() {
            if ( ! current_user_can( 'spbwc_manage_product_builder' ) ) {
                wp_die( esc_html__( 'You do not have permission to access this page.', 'storelly-product-builder-for-woocommerce' ) );
            }

            // ── POST handlers first; they redirect on success.
            if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'] ) {
                if ( isset( $_POST[ self::NONCE_CREATE_FIELD ] ) ) {
                    self::handle_create_submit();
                    return; // safety; handler exits.
                }
                if ( isset( $_POST[ self::NONCE_UNLINK_FIELD ] ) ) {
                    self::handle_unlink_submit();
                    return;
                }
                // Save handler — Visual Builder edit screen reuses the classic
                // nonce `spbwc_save_option_action` so the existing save method
                // (SPBWC_Storelly_PB_Admin_Options::spbwc_save_option) accepts
                // our POST verbatim. We only own the routing.
                if ( isset( $_POST['_wpnonce'] ) && isset( $_POST['spbwc_vb_save'] ) ) {
                    self::handle_save_submit();
                    return;
                }
            }

            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only routing flag, no state mutation.
            $action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';

            switch ( $action ) {
                case 'create':
                    self::render_create_picker();
                    break;
                case 'edit':
                    self::render_edit();
                    break;
                default:
                    self::render_list();
                    break;
            }
        }

        /* ────────────────────────────────────────────────────────────────
         * POST handlers
         * ──────────────────────────────────────────────────────────────── */

        /**
         * Create-picker form submit — promotes the selected option and redirects
         * to the edit screen for that id.
         */
        private static function handle_create_submit() {
            check_admin_referer( self::NONCE_CREATE, self::NONCE_CREATE_FIELD );
            if ( ! current_user_can( 'spbwc_manage_product_builder' ) ) {
                wp_die( esc_html__( 'You do not have permission to perform this action.', 'storelly-product-builder-for-woocommerce' ) );
            }
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above via check_admin_referer.
            $oid = isset( $_POST['id'] ) ? absint( wp_unslash( $_POST['id'] ) ) : 0;
            if ( $oid <= 0 ) {
                wp_safe_redirect( self::url( 'create', array( 'vb_notice' => 'pick' ) ) );
                exit;
            }
            // Sanity: the option must actually exist (and be published) before promotion.
            if ( ! self::option_exists( $oid ) ) {
                wp_safe_redirect( self::url( 'create', array( 'vb_notice' => 'notfound' ) ) );
                exit;
            }
            self::promote( $oid );
            // If the option had been previously unlinked (excluded), explicit
            // promote should override that — un-exclude it.
            self::unexclude( $oid );
            wp_safe_redirect( self::url( 'edit', array( 'id' => $oid, 'vb_notice' => 'created' ) ) );
            exit;
        }

        /**
         * Save handler for the Visual Builder edit screen. Reuses the classic
         * nonce so the existing save method validates our POST exactly as it
         * would for the classic editor (same fields, same shape).
         *
         * The classic edit screen POSTs both visible inputs (title, fields[],
         * views[], …) and runs through SPBWC_Storelly_PB_Admin_Options::
         * spbwc_save_option(). We do the same — but redirect back to OUR edit
         * URL so the user stays in Visual Builder.
         */
        private static function handle_save_submit() {
            if ( ! isset( $_POST['_wpnonce'] )
                || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'spbwc_save_option_action' )
            ) {
                wp_die( esc_html__( 'Security check failed.', 'storelly-product-builder-for-woocommerce' ) );
            }
            if ( ! current_user_can( 'spbwc_manage_product_builder' ) ) {
                wp_die( esc_html__( 'You do not have permission to perform this action.', 'storelly-product-builder-for-woocommerce' ) );
            }
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above.
            $oid = isset( $_POST['option_id'] ) ? absint( wp_unslash( $_POST['option_id'] ) ) : 0;
            if ( $oid <= 0 ) {
                // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above.
                $oid = isset( $_GET['id'] ) ? absint( wp_unslash( $_GET['id'] ) ) : 0;
            }

            // Defensive: only allow saving for options currently visible in the
            // VB listing (auto-detected ∪ promoted − excluded). Prevents the
            // Visual Builder save URL from being used as a side channel to edit
            // arbitrary pricing options.
            if ( $oid <= 0 || ! self::is_visible_in_listing( $oid ) ) {
                wp_safe_redirect( self::url( '', array( 'vb_notice' => 'notfound' ) ) );
                exit;
            }

            $admin  = SPBWC_Storelly_PB_Admin_Options::instance();
            $result = $admin->spbwc_save_option( $oid );

            $notice = ( is_array( $result ) && ! empty( $result['status'] ) ) ? 'saved' : 'save_error';
            wp_safe_redirect( self::url( 'edit', array( 'id' => $oid, 'vb_notice' => $notice ) ) );
            exit;
        }

        /**
         * Unlink-from-listing form submit — hides the option from Visual Builder.
         * Works for both explicit-promoted ids and auto-detected ids:
         *   • Remove from PROMOTED (no-op if not there).
         *   • Add to EXCLUDED (covers auto-detected case so it doesn't reappear
         *     next reload).
         * The option itself in wp_storelly_product_builder_options is preserved.
         */
        private static function handle_unlink_submit() {
            check_admin_referer( self::NONCE_UNLINK, self::NONCE_UNLINK_FIELD );
            if ( ! current_user_can( 'spbwc_manage_product_builder' ) ) {
                wp_die( esc_html__( 'You do not have permission to perform this action.', 'storelly-product-builder-for-woocommerce' ) );
            }
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above via check_admin_referer.
            $oid = isset( $_POST['id'] ) ? absint( wp_unslash( $_POST['id'] ) ) : 0;
            if ( $oid > 0 ) {
                self::unpromote( $oid );
                self::exclude( $oid );
            }
            wp_safe_redirect( self::url( '', array( 'vb_notice' => 'unlinked' ) ) );
            exit;
        }

        /* ────────────────────────────────────────────────────────────────
         * Promoted-set storage (single WP option row)
         * ──────────────────────────────────────────────────────────────── */

        /**
         * @return int[] sorted list of promoted option ids, deduped, sanitized.
         */
        public static function get_promoted_ids() {
            $raw = get_option( self::PROMOTED_OPTION, array() );
            if ( ! is_array( $raw ) ) {
                return array();
            }
            $ids = array();
            foreach ( $raw as $v ) {
                $i = absint( $v );
                if ( $i > 0 ) {
                    $ids[ $i ] = true;
                }
            }
            $out = array_keys( $ids );
            sort( $out, SORT_NUMERIC );
            return $out;
        }

        /**
         * Mark an option id as a Visual. Idempotent.
         *
         * @param int $oid Option id.
         * @return bool true on success / already-present, false on invalid id.
         */
        public static function promote( $oid ) {
            $oid = absint( $oid );
            if ( $oid <= 0 ) {
                return false;
            }
            $ids = self::get_promoted_ids();
            if ( in_array( $oid, $ids, true ) ) {
                return true;
            }
            $ids[] = $oid;
            sort( $ids, SORT_NUMERIC );
            update_option( self::PROMOTED_OPTION, $ids, false );
            return true;
        }

        /**
         * Remove an option id from the Visuals set. The option itself is NOT
         * deleted from `wp_storelly_product_builder_options`.
         *
         * @param int $oid Option id.
         * @return bool true on success / not-present, false on invalid id.
         */
        public static function unpromote( $oid ) {
            $oid = absint( $oid );
            if ( $oid <= 0 ) {
                return false;
            }
            $ids = self::get_promoted_ids();
            $new = array();
            foreach ( $ids as $i ) {
                if ( $i !== $oid ) {
                    $new[] = $i;
                }
            }
            if ( count( $new ) === count( $ids ) ) {
                return true; // nothing changed.
            }
            update_option( self::PROMOTED_OPTION, $new, false );
            return true;
        }

        /**
         * @return int[] sorted list of excluded option ids (denylist).
         */
        public static function get_excluded_ids() {
            $raw = get_option( self::EXCLUDED_OPTION, array() );
            if ( ! is_array( $raw ) ) {
                return array();
            }
            $ids = array();
            foreach ( $raw as $v ) {
                $i = absint( $v );
                if ( $i > 0 ) {
                    $ids[ $i ] = true;
                }
            }
            $out = array_keys( $ids );
            sort( $out, SORT_NUMERIC );
            return $out;
        }

        public static function exclude( $oid ) {
            $oid = absint( $oid );
            if ( $oid <= 0 ) {
                return false;
            }
            $ids = self::get_excluded_ids();
            if ( in_array( $oid, $ids, true ) ) {
                return true;
            }
            $ids[] = $oid;
            sort( $ids, SORT_NUMERIC );
            update_option( self::EXCLUDED_OPTION, $ids, false );
            return true;
        }

        public static function unexclude( $oid ) {
            $oid = absint( $oid );
            if ( $oid <= 0 ) {
                return false;
            }
            $ids = self::get_excluded_ids();
            $new = array();
            foreach ( $ids as $i ) {
                if ( $i !== $oid ) {
                    $new[] = $i;
                }
            }
            if ( count( $new ) === count( $ids ) ) {
                return true;
            }
            update_option( self::EXCLUDED_OPTION, $new, false );
            return true;
        }

        /**
         * Predicate: is this option id currently shown in the VB listing?
         * Mirrors get_visual_options() filter: (auto ∪ promoted) − excluded.
         * Used by render_edit() / handle_save_submit() so a card the admin
         * sees is always editable + saveable.
         *
         * @param int $oid Option id.
         * @return bool
         */
        public static function is_visible_in_listing( $oid ) {
            $oid = absint( $oid );
            if ( $oid <= 0 ) {
                return false;
            }
            $excluded_map = array_flip( self::get_excluded_ids() );
            if ( isset( $excluded_map[ $oid ] ) ) {
                return false;
            }
            if ( in_array( $oid, self::get_promoted_ids(), true ) ) {
                return true;
            }
            if ( in_array( $oid, self::get_auto_detected_ids(), true ) ) {
                return true;
            }
            return false;
        }

        /**
         * Cheap existence check used by the create handler.
         *
         * @param int $oid Option id.
         * @return bool
         */
        protected static function option_exists( $oid ) {
            global $wpdb; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Global $wpdb.
            $oid   = absint( $oid );
            $table = $wpdb->prefix . 'storelly_product_builder_options';
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Admin-only existence check, prepared.
            $exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE id = %d LIMIT 1", $oid ) );
            return ! empty( $exists );
        }

        /* ────────────────────────────────────────────────────────────────
         * Screen renderers
         * ──────────────────────────────────────────────────────────────── */

        public static function render_list() {
            $options = self::get_visual_options();
            $notice  = self::current_notice();
            include SPBWC_PB_PLUGIN_DIR . self::VIEWS_REL . 'list.php';
        }

        public static function render_create_picker() {
            $candidates = self::get_unbound_options();
            $notice     = self::current_notice();
            include SPBWC_PB_PLUGIN_DIR . self::VIEWS_REL . 'create-picker.php';
        }

        /**
         * Real edit screen — mounts the existing AngularJS controller
         * (optionApp/optionCtrl) on the same option data as the classic editor,
         * but exposes only the visual surface (views + nbpb fields). All other
         * option data (title, pricing fields, quantity breaks, design output,
         * applied products, …) is round-tripped via hidden inputs / hidden
         * field cards so save preserves it byte-for-byte.
         */
        public static function render_edit() {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only routing target id.
            $oid = isset( $_GET['id'] ) ? absint( wp_unslash( $_GET['id'] ) ) : 0;

            // Allow editing any option that's currently SHOWN in the listing —
            // either auto-detected (has nbpb_*) or explicitly promoted, and not
            // excluded. Mirrors the listing visibility predicate so a card the
            // admin sees is always editable.
            if ( $oid <= 0 || ! self::is_visible_in_listing( $oid ) ) {
                wp_safe_redirect( self::url( '', array( 'vb_notice' => 'notfound' ) ) );
                exit;
            }

            // Load + build option data via the same path classic uses, so the
            // localized payload is byte-for-byte compatible with what
            // optionCtrl expects.
            $admin    = SPBWC_Storelly_PB_Admin_Options::instance();
            $_options = (array) $admin->spbwc_get_option( $oid );
            if ( empty( $_options ) ) {
                wp_safe_redirect( self::url( '', array( 'vb_notice' => 'notfound' ) ) );
                exit;
            }
            // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize -- Mirrors classic spbwc_product_builder_options() path; data is admin-only, written by us via serialize() in spbwc_save_option().
            $raw_options = unserialize( $_options['fields'] );
            if ( ! isset( $raw_options['fields'] ) ) {
                $raw_options['fields'] = array();
            }
            $options                 = $admin->spbwc_build_options( $raw_options );
            $options['id']           = $_options['id'];
            $options['title']        = $_options['title'];
            $options['published']    = $_options['published'];
            $options['created']      = $_options['created'];
            $options['modified']     = $_options['modified'];
            $options['product_ids']  = ( isset( $_options['product_ids'] ) && is_array( maybe_unserialize( $_options['product_ids'] ) ) )
                ? maybe_unserialize( $_options['product_ids'] )
                : array();
            $options['product_cats'] = ( isset( $_options['product_cats'] ) && is_array( maybe_unserialize( $_options['product_cats'] ) ) )
                ? maybe_unserialize( $_options['product_cats'] )
                : array();
            $options['apply_for']    = isset( $_options['apply_for'] ) ? (string) $_options['apply_for'] : 'p';

            $default_field = $admin->spbwc_default_config_field();

            // Media picker title labels for products.
            $options['spbwc_product_labels'] = array();
            if ( ! empty( $options['product_ids'] ) && is_array( $options['product_ids'] ) ) {
                foreach ( $options['product_ids'] as $spbwc_label_pid ) {
                    $spbwc_label_pid = absint( $spbwc_label_pid );
                    if ( $spbwc_label_pid > 0 ) {
                        $options['spbwc_product_labels'][ $spbwc_label_pid ] = get_the_title( $spbwc_label_pid );
                    }
                }
            }

            // Enqueue pattern mirrors the classic dispatcher (class-admin-options.php
            // around line 475). admin-options.js depends on the full jQuery UI set
            // + wpColorPicker + wpdialogs + Snap.svg + TipTip during bootstrap (color
            // pickers on every attribute, datepicker init at line ~2111, etc.). If
            // those are missing the script throws on init and ng-click handlers never
            // bind reliably — Add View / Add Component / color attribute editors all
            // appear dead. Use the same full dependency list as classic.
            $spbwc_admin_options_ver = file_exists( SPBWC_PB_PLUGIN_DIR . 'static/js/admin-options.js' )
                ? filemtime( SPBWC_PB_PLUGIN_DIR . 'static/js/admin-options.js' )
                : SPBWC_PB_VERSION;
            wp_register_script(
                'spbwc_option_field_script',
                SPBWC_PB_JS_URL . 'admin-options.js',
                array(
                    'jquery',
                    'wpdialogs',
                    'jquery-ui-resizable',
                    'jquery-ui-draggable',
                    'jquery-ui-droppable',
                    'jquery-ui-sortable',
                    'jquery-ui-datepicker',
                    'jquery-ui-autocomplete',
                    'wp-color-picker',
                    'spbwc-ag',
                    'wc-enhanced-select',
                    'spbwc-snap-svg',
                    'spbwc-tiptip',
                ),
                $spbwc_admin_options_ver,
                true
            );
            // wpColorPicker also needs its own stylesheet.
            wp_enqueue_style( 'wp-color-picker' );
            wp_localize_script( 'spbwc_option_field_script', 'storelly_option_variable', array(
                'STORELLY_OPTIONS'      => $options,
                'STORELLY_OPTION_FIELD' => $default_field,
                'ajax_url'              => esc_url( admin_url( 'admin-ajax.php' ) ),
                'nbnonce'               => esc_attr( wp_create_nonce( 'spbwc_save_design_action' ) ),
                'max_input_vars'        => (int) SPBWC_Storelly_PB_Util::spbwc_get_max_input_var(),
            ) );
            wp_enqueue_script( 'spbwc_option_field_script' );
            if ( wp_style_is( 'spbwc-options-style', 'registered' ) ) {
                wp_enqueue_style( 'spbwc-options-style' );
            }
            if ( wp_style_is( 'spbwc-options-v2-style', 'registered' ) ) {
                wp_enqueue_style( 'spbwc-options-v2-style' );
            }
            if ( ! wp_script_is( 'spbwc-options-script', 'enqueued' ) ) {
                wp_localize_script( 'spbwc_option_field_script', 'storelly_options', array(
                    'search_products_nonce' => wp_create_nonce( 'search-products' ),
                    'calendar_image'        => SPBWC_PB_ASSETS_URL . 'images/calendar.png',
                    'storelly_options_lang' => $admin->spbwc_storelly_option_i18n(),
                ) );
            }
            // Media picker for upload buttons (set_view_base, set_view_config_image,
            // set_component_icon). Same enqueue as classic.
            wp_enqueue_media();

            // Notice + chrome data for the view file.
            $notice         = self::current_notice();
            $back_url       = self::url();
            $classic_url    = add_query_arg(
                array(
                    'page'   => SPBWC_PB_BUILDER_SLUG,
                    'action' => 'edit',
                    'id'     => $oid,
                ),
                admin_url( 'admin.php' )
            );
            $form_action    = self::url( 'edit', array( 'id' => $oid ) );
            $current_id     = $oid;

            include SPBWC_PB_PLUGIN_DIR . self::VIEWS_REL . 'edit.php';
        }

        /* ────────────────────────────────────────────────────────────────
         * Queries
         * ──────────────────────────────────────────────────────────────── */

        /**
         * Return the rows that should appear in the Visual Builder listing.
         *
         * Showable set = (auto-detected ∪ promoted) − excluded
         *   • auto-detected = options whose serialized `fields` contains any
         *     entry with a non-empty nbpb_type. These are options the admin
         *     already built designer components for in the classic Designer
         *     tab, so they ARE visuals.
         *   • promoted = options the admin explicitly added via Create Visual.
         *   • excluded = options the admin unlinked (denylist; only meaningful
         *     for auto-detected entries — promoted entries are dropped from
         *     promoted on unlink so they don't need the denylist, but we add
         *     to excluded anyway as a safety net).
         *
         * Stale ids are silently dropped by the SELECT IN.
         *
         * @return array<int, array<string, mixed>>
         */
        public static function get_visual_options() {
            $auto     = self::get_auto_detected_ids();
            $promoted = self::get_promoted_ids();
            $excluded = self::get_excluded_ids();

            $union = array_values( array_unique( array_merge( $auto, $promoted ) ) );
            if ( ! empty( $excluded ) ) {
                $excluded_map = array_flip( $excluded );
                $union        = array_values( array_filter( $union, static function ( $id ) use ( $excluded_map ) {
                    return ! isset( $excluded_map[ $id ] );
                } ) );
            }
            if ( empty( $union ) ) {
                return array();
            }
            sort( $union, SORT_NUMERIC );

            global $wpdb; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Global $wpdb.
            $table        = $wpdb->prefix . 'storelly_product_builder_options';
            $placeholders = implode( ',', array_fill( 0, count( $union ), '%d' ) );
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Admin-only read; $table from prefix; $placeholders is a fixed-count list of '%d' literals fed to $wpdb->prepare.
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id, title, fields, product_ids, product_cats, apply_for, modified, published
                     FROM {$table}
                     WHERE id IN ({$placeholders})
                     ORDER BY modified DESC",
                    $union
                ),
                'ARRAY_A'
            );
            if ( ! is_array( $rows ) ) {
                return array();
            }
            foreach ( $rows as &$row ) {
                $row['vb_meta'] = self::derive_meta( $row );
            }
            unset( $row );
            return $rows;
        }

        /**
         * Scan all options for ones that already carry designer components
         * (any field with nbpb_type). Static cache for the request.
         *
         * @return int[] sorted ids
         */
        public static function get_auto_detected_ids() {
            static $cache = null;
            if ( null !== $cache ) {
                return $cache;
            }
            global $wpdb; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Global $wpdb.
            $table = $wpdb->prefix . 'storelly_product_builder_options';
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Admin-only read; $table from prefix; no user input.
            $rows = $wpdb->get_results(
                "SELECT id, fields FROM {$table} WHERE published = 1",
                'ARRAY_A'
            );
            $out = array();
            if ( is_array( $rows ) ) {
                foreach ( $rows as $row ) {
                    $data = self::safe_unserialize( $row['fields'] );
                    if ( ! is_array( $data ) || empty( $data['fields'] ) || ! is_array( $data['fields'] ) ) {
                        continue;
                    }
                    foreach ( $data['fields'] as $f ) {
                        if ( is_array( $f ) && ! empty( $f['nbpb_type'] ) ) {
                            $out[] = absint( $row['id'] );
                            break;
                        }
                    }
                }
            }
            $out = array_values( array_unique( $out ) );
            sort( $out, SORT_NUMERIC );
            $cache = $out;
            return $cache;
        }

        /**
         * Return options eligible for promotion in the Create picker — options
         * that are NOT already showing in the listing (auto-detected ∪ promoted),
         * but excluding the already-excluded ones (so the admin can re-promote
         * an option they previously unlinked).
         *
         * @return array<int, array<string, mixed>>
         */
        public static function get_unbound_options() {
            global $wpdb; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Global $wpdb.
            $table = $wpdb->prefix . 'storelly_product_builder_options';

            $showing = array_values( array_unique( array_merge(
                self::get_auto_detected_ids(),
                self::get_promoted_ids()
            ) ) );
            $excluded_map = array_flip( self::get_excluded_ids() );
            $showing      = array_values( array_filter( $showing, static function ( $id ) use ( $excluded_map ) {
                return ! isset( $excluded_map[ $id ] );
            } ) );

            if ( empty( $showing ) ) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Admin-only read; $table from prefix, no user input.
                $rows = $wpdb->get_results(
                    "SELECT id, title, fields, modified
                     FROM {$table}
                     WHERE published = 1
                     ORDER BY title ASC",
                    'ARRAY_A'
                );
            } else {
                $placeholders = implode( ',', array_fill( 0, count( $showing ), '%d' ) );
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Admin-only read; $table from prefix; $placeholders fixed-count '%d' list fed to $wpdb->prepare.
                $rows = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT id, title, fields, modified
                         FROM {$table}
                         WHERE published = 1
                           AND id NOT IN ({$placeholders})
                         ORDER BY title ASC",
                        $showing
                    ),
                    'ARRAY_A'
                );
            }
            return is_array( $rows ) ? $rows : array();
        }

        /* ────────────────────────────────────────────────────────────────
         * Presentation helpers
         * ──────────────────────────────────────────────────────────────── */

        /**
         * Derive presentation metadata for a Visual card from the raw option row.
         *
         * @param array<string, mixed> $row Row.
         * @return array<string, mixed>
         */
        public static function derive_meta( $row ) {
            $placeholder = SPBWC_PB_ASSETS_URL . 'images/placeholder.png';
            $meta        = array(
                'thumb_url'       => $placeholder,
                'component_count' => 0,
                'view_count'      => 0,
                'target_label'    => __( 'Not assigned to any product', 'storelly-product-builder-for-woocommerce' ),
                'target_type'     => '',
                'target_empty'    => true,
            );

            $data = self::safe_unserialize( isset( $row['fields'] ) ? $row['fields'] : '' );
            if ( is_array( $data ) ) {
                if ( ! empty( $data['views'] ) && is_array( $data['views'] ) ) {
                    $meta['view_count'] = count( $data['views'] );
                    foreach ( $data['views'] as $v ) {
                        if ( is_array( $v ) && ! empty( $v['base_url'] ) ) {
                            $meta['thumb_url'] = $v['base_url'];
                            break;
                        }
                    }
                }
                if ( ! empty( $data['fields'] ) && is_array( $data['fields'] ) ) {
                    foreach ( $data['fields'] as $f ) {
                        if ( ! is_array( $f ) || empty( $f['nbpb_type'] ) ) {
                            continue;
                        }
                        $meta['component_count']++;
                        if (
                            $meta['thumb_url'] === $placeholder
                            && isset( $f['general']['component_icon_url'] )
                            && ! empty( $f['general']['component_icon_url'] )
                        ) {
                            $meta['thumb_url'] = $f['general']['component_icon_url'];
                        }
                    }
                }
            }

            // Target — from existing columns; no new schema.
            $apply_for = isset( $row['apply_for'] ) ? (string) $row['apply_for'] : 'p';
            if ( 'c' === $apply_for && ! empty( $row['product_cats'] ) ) {
                $cats = self::safe_unserialize( $row['product_cats'] );
                if ( is_array( $cats ) && count( $cats ) > 0 ) {
                    $names = array();
                    foreach ( $cats as $cid ) {
                        $term = get_term( absint( $cid ), 'product_cat' );
                        if ( $term && ! is_wp_error( $term ) ) {
                            $names[] = $term->name;
                        }
                    }
                    if ( ! empty( $names ) ) {
                        $shown  = array_slice( $names, 0, 3 );
                        $suffix = count( $names ) > 3 ? '…' : '';
                        $meta['target_label'] = sprintf(
                            /* translators: %s: comma-separated category names */
                            __( 'Categories: %s', 'storelly-product-builder-for-woocommerce' ),
                            implode( ', ', $shown ) . $suffix
                        );
                        $meta['target_type']  = 'c';
                        $meta['target_empty'] = false;
                    }
                }
            } elseif ( ! empty( $row['product_ids'] ) ) {
                $pids = self::safe_unserialize( $row['product_ids'] );
                if ( is_array( $pids ) && count( $pids ) > 0 ) {
                    $names = array();
                    foreach ( array_slice( $pids, 0, 3 ) as $pid ) {
                        $p = get_post( absint( $pid ) );
                        if ( $p ) {
                            $names[] = $p->post_title;
                        }
                    }
                    if ( ! empty( $names ) ) {
                        $suffix = count( $pids ) > 3 ? '…' : '';
                        $meta['target_label'] = sprintf(
                            /* translators: %s: comma-separated product names */
                            __( 'Products: %s', 'storelly-product-builder-for-woocommerce' ),
                            implode( ', ', $names ) . $suffix
                        );
                        $meta['target_type']  = 'p';
                        $meta['target_empty'] = false;
                    }
                }
            }

            return $meta;
        }

        /**
         * Map a `vb_notice` query arg to a notice payload for the views to render.
         *
         * @return array{type:string, text:string}|null
         */
        protected static function current_notice() {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only flash-notice flag, no state mutation.
            $code = isset( $_GET['vb_notice'] ) ? sanitize_key( wp_unslash( $_GET['vb_notice'] ) ) : '';
            if ( '' === $code ) {
                return null;
            }
            switch ( $code ) {
                case 'created':
                    return array(
                        'type' => 'success',
                        'text' => __( 'Visual created. You can now build it.', 'storelly-product-builder-for-woocommerce' ),
                    );
                case 'unlinked':
                    return array(
                        'type' => 'success',
                        'text' => __( 'Visual unlinked. The pricing option was preserved.', 'storelly-product-builder-for-woocommerce' ),
                    );
                case 'pick':
                    return array(
                        'type' => 'warning',
                        'text' => __( 'Please pick a pricing option before continuing.', 'storelly-product-builder-for-woocommerce' ),
                    );
                case 'notfound':
                    return array(
                        'type' => 'error',
                        'text' => __( 'That pricing option no longer exists or is not a Visual yet.', 'storelly-product-builder-for-woocommerce' ),
                    );
                case 'saved':
                    return array(
                        'type' => 'success',
                        'text' => __( 'Visual saved.', 'storelly-product-builder-for-woocommerce' ),
                    );
                case 'save_error':
                    return array(
                        'type' => 'error',
                        'text' => __( 'Could not save the Visual. Please try again.', 'storelly-product-builder-for-woocommerce' ),
                    );
            }
            return null;
        }

        /**
         * Tolerant unserializer — the `fields` column is written with serialize().
         *
         * @param mixed $raw Raw value.
         * @return array<mixed>
         */
        public static function safe_unserialize( $raw ) {
            if ( is_array( $raw ) ) {
                return $raw;
            }
            if ( ! is_string( $raw ) || '' === $raw ) {
                return array();
            }
            $v = maybe_unserialize( $raw );
            if ( is_array( $v ) ) {
                return $v;
            }
            $j = json_decode( $raw, true );
            return is_array( $j ) ? $j : array();
        }

        /**
         * Build admin URL for Visual Builder pages.
         *
         * @param string               $action Optional action segment.
         * @param array<string, mixed> $extra  Optional extra query args.
         * @return string
         */
        public static function url( $action = '', $extra = array() ) {
            $args = array( 'page' => SPBWC_PB_VISUAL_BUILDER_SLUG );
            if ( '' !== $action ) {
                $args['action'] = $action;
            }
            if ( ! empty( $extra ) ) {
                $args = array_merge( $args, $extra );
            }
            return add_query_arg( $args, admin_url( 'admin.php' ) );
        }

        /**
         * Enqueue scoped CSS — only on the Visual Builder admin page.
         *
         * @param string $hook WP admin hook suffix.
         * @return void
         */
        public static function enqueue( $hook ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen detection inside enqueue.
            $page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
            if ( $page !== SPBWC_PB_VISUAL_BUILDER_SLUG ) {
                return;
            }
            $css_path = SPBWC_PB_PLUGIN_DIR . 'static/css/visual-builder.css';
            $ver      = file_exists( $css_path ) ? filemtime( $css_path ) : SPBWC_PB_VERSION;
            wp_enqueue_style(
                self::CSS_HANDLE,
                SPBWC_PB_CSS_URL . 'visual-builder.css',
                array( 'spbwc-admin-ui', 'dashicons' ),
                $ver
            );
        }
    }

    add_action( 'admin_enqueue_scripts', array( 'SPBWC_Visual_Builder_Admin', 'enqueue' ) );
}
