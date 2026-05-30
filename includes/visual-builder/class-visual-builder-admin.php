<?php
/**
 * Visual Builder admin — new entry point that re-presents the existing product
 * builder data (views, nbpb_* fields, pb_config) under its own admin menu.
 *
 * Design rule (M6.1):
 *   - READ-ONLY data access at this stage. No DB writes, no schema changes, no
 *     new AJAX endpoints, no Angular controller. Just a listing + create-picker
 *     screen that link into the classic editor.
 *   - The classic Pricing Options editor (views/options/edit-option.php) is NOT
 *     touched by this menu — it keeps working unchanged.
 *   - "Has visual" is derived from existing data:
 *       * options.views[] non-empty, OR
 *       * any field with non-empty nbpb_type
 *     No new "visual_builder_enable" column. No new binding rows.
 *   - Target (product / category) is read from the option's existing
 *     product_ids / product_cats / apply_for columns.
 *
 * Edit screen is intentionally a stub here — it lands in M6.2.
 *
 * @package Storelly_Product_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'SPBWC_Visual_Builder_Admin' ) ) {

    class SPBWC_Visual_Builder_Admin {

        const CSS_HANDLE = 'spbwc-visual-builder';
        const VIEWS_REL  = 'views/visual-builder/';

        /**
         * Dispatcher. Called by the submenu callback registered in
         * class-admin-options.php (which now delegates to this class).
         */
        public static function render() {
            if ( ! current_user_can( 'spbwc_manage_product_builder' ) ) {
                wp_die( esc_html__( 'You do not have permission to access this page.', 'storelly-product-builder-for-woocommerce' ) );
            }

            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only routing flag, no state mutation.
            $action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';

            switch ( $action ) {
                case 'create':
                    self::render_create_picker();
                    break;
                case 'edit':
                    self::render_edit_stub();
                    break;
                default:
                    self::render_list();
                    break;
            }
        }

        /**
         * Listing screen — options that already carry visual content.
         */
        public static function render_list() {
            $options = self::get_visual_options();
            include SPBWC_PB_PLUGIN_DIR . self::VIEWS_REL . 'list.php';
        }

        /**
         * Create picker — pick an existing pricing option to attach a visual to.
         */
        public static function render_create_picker() {
            $candidates = self::get_unbound_options();
            include SPBWC_PB_PLUGIN_DIR . self::VIEWS_REL . 'create-picker.php';
        }

        /**
         * Edit screen stub — final implementation in M6.2.
         */
        public static function render_edit_stub() {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only routing target id.
            $oid = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
            $back_url    = self::url();
            $classic_url = add_query_arg(
                array(
                    'page'   => SPBWC_PB_BUILDER_SLUG,
                    'action' => 'update',
                    'id'     => $oid,
                ),
                admin_url( 'admin.php' )
            );
            ?>
            <div class="wrap spbwc-vb spbwc-vb--edit-stub">
                <nav class="spbwc-vb__backbar" aria-label="<?php esc_attr_e( 'Breadcrumb', 'storelly-product-builder-for-woocommerce' ); ?>">
                    <a href="<?php echo esc_url( $back_url ); ?>">
                        <span class="dashicons dashicons-arrow-left-alt" aria-hidden="true"></span>
                        <?php esc_html_e( 'Visual Builder', 'storelly-product-builder-for-woocommerce' ); ?>
                    </a>
                    <span class="spbwc-vb__backbar-sep">/</span>
                    <span class="spbwc-vb__backbar-current">
                        <?php
                        /* translators: %d: option id */
                        printf( esc_html__( 'Edit · #%d', 'storelly-product-builder-for-woocommerce' ), $oid );
                        ?>
                    </span>
                </nav>
                <div class="spbwc-vb__stub">
                    <span class="spbwc-vb__stub-icon" aria-hidden="true">🛠️</span>
                    <h2 class="spbwc-vb__stub-title">
                        <?php esc_html_e( 'Edit screen lands in M6.2', 'storelly-product-builder-for-woocommerce' ); ?>
                    </h2>
                    <p class="spbwc-vb__stub-body">
                        <?php esc_html_e( 'The dedicated Visual Builder editor is being prepared. It will mount the existing product-builder UI here without changing data or save behaviour. For now, edit this option in the classic Pricing Options editor.', 'storelly-product-builder-for-woocommerce' ); ?>
                    </p>
                    <p class="spbwc-vb__stub-actions">
                        <a class="button button-primary" href="<?php echo esc_url( $classic_url ); ?>">
                            <span class="dashicons dashicons-edit" aria-hidden="true"></span>
                            <?php esc_html_e( 'Open in classic editor', 'storelly-product-builder-for-woocommerce' ); ?>
                        </a>
                        <a class="button" href="<?php echo esc_url( $back_url ); ?>">
                            <?php esc_html_e( '← Back to Visual Builder', 'storelly-product-builder-for-woocommerce' ); ?>
                        </a>
                    </p>
                </div>
            </div>
            <?php
        }

        /**
         * Query options that have visual content.
         * Filter is applied in PHP after row fetch because the predicate spans a
         * serialized blob in the `fields` column.
         *
         * @return array<int, array<string, mixed>>
         */
        public static function get_visual_options() {
            $rows = self::fetch_all_options();
            $out  = array();
            foreach ( $rows as $row ) {
                if ( self::option_has_visual( $row ) ) {
                    $row['vb_meta'] = self::derive_meta( $row );
                    $out[]          = $row;
                }
            }
            return $out;
        }

        /**
         * Query options that do NOT yet have visual content — eligible for binding.
         *
         * @return array<int, array<string, mixed>>
         */
        public static function get_unbound_options() {
            $rows = self::fetch_all_options( true /* published_only */ );
            $out  = array();
            foreach ( $rows as $row ) {
                if ( ! self::option_has_visual( $row ) ) {
                    $out[] = $row;
                }
            }
            return $out;
        }

        /**
         * Raw fetch — kept narrow (columns we actually need) to avoid hauling
         * the full serialized blob into memory for hundreds of rows.
         *
         * Caches its result per request to avoid repeating the query when the
         * dispatcher reads twice in the same hit.
         *
         * @param bool $published_only Restrict to published rows.
         * @return array<int, array<string, mixed>>
         */
        protected static function fetch_all_options( $published_only = false ) {
            static $cache = array();
            $key = $published_only ? 'pub' : 'all';
            if ( isset( $cache[ $key ] ) ) {
                return $cache[ $key ];
            }
            global $wpdb; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Global $wpdb.
            $table_name = $wpdb->prefix . 'storelly_product_builder_options';
            $where      = $published_only ? 'WHERE published = 1' : '';
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Admin-only read; $table_name from $wpdb->prefix; $where is a static literal.
            $rows = $wpdb->get_results(
                "SELECT id, title, fields, product_ids, product_cats, apply_for, modified, published
                 FROM {$table_name}
                 {$where}
                 ORDER BY modified DESC",
                'ARRAY_A'
            );
            $cache[ $key ] = is_array( $rows ) ? $rows : array();
            return $cache[ $key ];
        }

        /**
         * Decide whether an option row carries visual content.
         * Predicate: views[] non-empty OR any field with nbpb_type.
         *
         * @param array<string, mixed> $row Row from wp_storelly_product_builder_options.
         * @return bool
         */
        public static function option_has_visual( $row ) {
            $data = self::safe_unserialize( isset( $row['fields'] ) ? $row['fields'] : '' );
            if ( ! is_array( $data ) ) {
                return false;
            }
            if ( ! empty( $data['views'] ) && is_array( $data['views'] ) && count( $data['views'] ) > 0 ) {
                return true;
            }
            if ( ! empty( $data['fields'] ) && is_array( $data['fields'] ) ) {
                foreach ( $data['fields'] as $f ) {
                    if ( ! empty( $f['nbpb_type'] ) ) {
                        return true;
                    }
                }
            }
            return false;
        }

        /**
         * Derive presentation metadata for a Visual card.
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
                        if ( ! empty( $v['base_url'] ) ) {
                            $meta['thumb_url'] = $v['base_url'];
                            break;
                        }
                    }
                }
                if ( ! empty( $data['fields'] ) && is_array( $data['fields'] ) ) {
                    foreach ( $data['fields'] as $f ) {
                        if ( empty( $f['nbpb_type'] ) ) {
                            continue;
                        }
                        $meta['component_count']++;
                        // Prefer component_icon as thumb when no view base is set.
                        if (
                            $meta['thumb_url'] === $placeholder
                            && ! empty( $f['general']['component_icon_url'] )
                        ) {
                            $meta['thumb_url'] = $f['general']['component_icon_url'];
                        }
                    }
                }
            }

            // Target — read from existing columns, no new schema.
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
         * Tolerant unserializer — the `fields` column is written with serialize();
         * this also accepts JSON or array passthrough for safety.
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
         * Registered via add_action('admin_enqueue_scripts', …) below.
         *
         * @param string $hook WP admin hook suffix.
         * @return void
         */
        public static function enqueue( $hook ) {
            // Detect by ?page= rather than hook suffix — robust across WP versions
            // and when the menu title changes.
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
