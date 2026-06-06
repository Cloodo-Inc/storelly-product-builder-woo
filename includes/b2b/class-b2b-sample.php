<?php
/**
 * Sample B2B client seeder — "Netbase JSC".
 *
 * Solves the empty-state problem for the whole B2B suite: on activation the
 * merchant gets one fully-populated, clearly-labelled sample company so the
 * entire workflow (brand store → tier price → quote → order → team approval) is
 * visible without manual setup. Everything created here carries a
 * `_spbwc_sample` flag (post meta / user meta / order meta) so it is badge-able
 * and removable in one click, and never counts toward anything billable.
 *
 * Design notes:
 *   - Self-contained: the sample owns its OWN demo users (owner / approver /
 *     requester, all `@example.com`), so it never hijacks the real site admin
 *     and removal touches nothing the merchant created.
 *   - Fully local: brand assets are BUNDLED in the plugin
 *     (static/img/b2b-sample/) and side-loaded from disk — no external request,
 *     no phone-home (CLAUDE.md rule 6).
 *   - Activation-safe: `register_activation_hook` only sets a pending flag;
 *     the real seeding runs on the next authenticated admin load, when
 *     WooCommerce, the CPTs and the media stack are guaranteed ready.
 *   - Idempotent: re-running never duplicates — it resolves existing pieces by
 *     the sample flag / slug.
 *
 * @package Storelly
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'SPBWC_B2B_Sample' ) ) {

    class SPBWC_B2B_Sample {

        /** Sample flag used on every seeded post / user / order. */
        const FLAG = '_spbwc_sample';

        /** Public store slug for the sample company. */
        const SLUG = 'netbase-jsc';

        /** One-time guard: seeded version (int). Bump VERSION to re-seed. */
        const OPTION_SEEDED = 'spbwc_b2b_sample_seeded';

        /** Set by the activation hook; consumed on the next admin load. */
        const OPTION_PENDING = 'spbwc_b2b_seed_pending';

        /** Remembers that WE created the tier ladder (so removal can undo it). */
        const OPTION_MADE_TIERS = 'spbwc_b2b_sample_made_tiers';

        /** Current sample schema version. */
        const VERSION = 1;

        public static function init() {
            add_action( 'admin_init', array( __CLASS__, 'maybe_seed' ) );
            add_action( 'admin_post_spbwc_b2b_seed_sample', array( __CLASS__, 'handle_seed' ) );
            add_action( 'admin_post_spbwc_b2b_remove_sample', array( __CLASS__, 'handle_remove' ) );
        }

        /** Called from the plugin activation hook — just arms the seeder. */
        public static function arm() {
            update_option( self::OPTION_PENDING, 1, false );
        }

        /**
         * Auto-seed once, on the first authenticated admin load after activation.
         * Requires WooCommerce + a capable user so the CPTs / media stack and a
         * valid actor are guaranteed available.
         */
        public static function maybe_seed() {
            if ( ! get_option( self::OPTION_PENDING ) ) {
                return;
            }
            if ( (int) get_option( self::OPTION_SEEDED ) >= self::VERSION ) {
                delete_option( self::OPTION_PENDING );
                return;
            }
            if ( ! class_exists( 'WooCommerce' ) || ! function_exists( 'wc_get_products' ) ) {
                return; // WooCommerce not ready yet — try again next load.
            }
            if ( ! current_user_can( 'manage_woocommerce' ) ) {
                return; // Wait for an admin context (not cron / ajax / front).
            }
            $company_id = self::seed();
            if ( $company_id ) {
                update_option( self::OPTION_SEEDED, self::VERSION, false );
                add_action( 'admin_notices', array( __CLASS__, 'seeded_notice' ) );
            }
            delete_option( self::OPTION_PENDING );
        }

        /* ── Existence helpers ────────────────────────────────────── */

        /** @return int Sample company id, or 0. */
        public static function company_id() {
            $found = get_posts(
                array(
                    'post_type'   => SPBWC_Company::POST_TYPE,
                    'post_status' => 'any',
                    'numberposts' => 1,
                    'fields'      => 'ids',
                    'meta_key'    => self::FLAG,   // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
                    'meta_value'  => '1',          // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
                )
            );
            return ! empty( $found ) ? (int) $found[0] : 0;
        }

        /** Whether the sample is currently installed. */
        public static function exists() {
            return self::company_id() > 0;
        }

        /* ── Seed ─────────────────────────────────────────────────── */

        /**
         * Build the full sample. Idempotent: returns the existing sample company
         * id if already present.
         *
         * @return int|false Company id, or false on failure.
         */
        public static function seed() {
            if ( ! class_exists( 'SPBWC_Company' ) ) {
                return false;
            }
            $existing = self::company_id();
            if ( $existing ) {
                return $existing;
            }

            // Seeding must be silent: suppress every transactional email the demo
            // data would otherwise trigger (order-completed, etc.).
            add_filter( 'pre_wp_mail', '__return_true', 9999 );
            try {
                return self::build();
            } finally {
                remove_filter( 'pre_wp_mail', '__return_true', 9999 );
            }
        }

        /**
         * Build every sample piece. Wrapped by seed() (mail suppression + guard).
         *
         * @return int|false Company id.
         */
        protected static function build() {
            // 1) The sample's own demo users (never the real admin).
            $owner_id = self::ensure_user( 'netbase-owner@example.com', __( 'Jordan Pike', 'storelly-product-builder-for-woocommerce' ) );
            if ( ! $owner_id ) {
                return false;
            }

            // 2) Company shell (owner linked, slug = netbase-jsc).
            $tier = self::ensure_tier();
            $company_id = SPBWC_Company::create(
                'Netbase JSC',
                $owner_id,
                array(
                    'status'             => SPBWC_Company::STATUS_ACTIVE,
                    'tier'               => $tier,
                    'seats'              => 10,
                    'approval_threshold' => 500.0,
                    'payment_terms'      => 'net30',
                )
            );
            if ( is_wp_error( $company_id ) || ! $company_id ) {
                return false;
            }
            update_post_meta( $company_id, self::FLAG, '1' );

            // 3) Branding (bundled assets — no external fetch) + profile.
            self::apply_branding( $company_id );

            // 4) Products: bind a couple of real products with a per-company price
            //    and publish them to the Brand Store allow-list.
            $product_ids = self::pick_products( 3 );
            self::apply_pricing( $company_id, $product_ids );

            // 5) Team: an approver + a requester (owner already linked).
            $approver_id  = self::ensure_user( 'netbase-approver@example.com', __( 'Mara Velez', 'storelly-product-builder-for-woocommerce' ) );
            $requester_id = self::ensure_user( 'netbase-buyer@example.com', __( 'Theo Nguyen', 'storelly-product-builder-for-woocommerce' ) );
            if ( $approver_id ) {
                SPBWC_Company::link_user( $approver_id, $company_id, SPBWC_Company::ROLE_APPROVER );
            }
            if ( $requester_id ) {
                SPBWC_Company::link_user( $requester_id, $company_id, SPBWC_Company::ROLE_REQUESTER );
                update_user_meta( $requester_id, SPBWC_Company::USER_LIMIT_ORDER, 250 );
            }

            // 6) A sample quote (RFQ workflow).
            self::seed_quote( $owner_id );

            // 7) A completed order (history + ledger) for the first product.
            self::seed_order( $owner_id, $product_ids );

            // 8) A pending team-approval request.
            if ( $requester_id ) {
                self::seed_procurement( $company_id, $requester_id, $product_ids );
            }

            do_action( 'spbwc_b2b_sample_seeded', $company_id );
            return (int) $company_id;
        }

        /* ── Pieces ───────────────────────────────────────────────── */

        /**
         * Apply bundled branding + corporate profile to the company.
         *
         * @param int $company_id Company.
         */
        protected static function apply_branding( $company_id ) {
            $dir = defined( 'SPBWC_PB_PLUGIN_DIR' ) ? SPBWC_PB_PLUGIN_DIR : plugin_dir_path( dirname( __DIR__ ) ) . '';
            $logo   = self::sideload( $dir . 'static/img/b2b-sample/netbase-logo.png', 'netbase-jsc-logo.png', $company_id );
            $banner = self::sideload( $dir . 'static/img/b2b-sample/netbase-banner.jpg', 'netbase-jsc-banner.jpg', $company_id );
            if ( $logo ) {
                update_post_meta( $company_id, SPBWC_Company::META_LOGO, $logo );
            }
            if ( $banner ) {
                update_post_meta( $company_id, SPBWC_Company::META_BANNER, $banner );
            }
            update_post_meta( $company_id, SPBWC_Company::META_BRAND_PRIMARY, '#f8921f' );
            update_post_meta( $company_id, SPBWC_Company::META_BRAND_SECONDARY, '#d1651a' );
            update_post_meta( $company_id, SPBWC_Company::META_TAGLINE, __( 'Global digital transformation & IT solutions since 2012', 'storelly-product-builder-for-woocommerce' ) );
            update_post_meta( $company_id, SPBWC_Company::META_DESCRIPTION, __( 'Netbase JSC delivers custom software, e-commerce platforms and IT outsourcing for teams worldwide. This is a sample B2B client so you can explore the brand store, negotiated pricing, team approvals and reorders end to end.', 'storelly-product-builder-for-woocommerce' ) );
            update_post_meta(
                $company_id,
                SPBWC_Company::META_PROFILE,
                array(
                    'legal_name' => 'Netbase JSC',
                    'industry'   => __( 'Software & IT Consulting', 'storelly-product-builder-for-woocommerce' ),
                    'tax_id'     => 'VN-0123456789',
                )
            );
            update_post_meta(
                $company_id,
                SPBWC_Company::META_CONTACT,
                array(
                    'contact_name' => __( 'Jordan Pike', 'storelly-product-builder-for-woocommerce' ),
                    'title'        => __( 'Procurement Lead', 'storelly-product-builder-for-woocommerce' ),
                    'email'        => 'netbase-owner@example.com',
                    'phone'        => '+84 93 786 9689',
                    'website'      => 'https://netbasejsc.com',
                )
            );
            update_post_meta(
                $company_id,
                SPBWC_Company::META_ADDRESSES,
                array(
                    'billing' => array(
                        'address_1' => '91 Nguyen Chi Thanh Street',
                        'city'      => 'Hanoi',
                        'state'     => 'Dong Da',
                        'country'   => 'VN',
                    ),
                )
            );
        }

        /**
         * Bind products with a per-company price + sync the allow-list.
         *
         * @param int   $company_id  Company.
         * @param int[] $product_ids Products to bind.
         */
        protected static function apply_pricing( $company_id, $product_ids ) {
            if ( empty( $product_ids ) || ! class_exists( 'SPBWC_B2B_Price_Rules' ) ) {
                if ( ! empty( $product_ids ) ) {
                    update_post_meta( $company_id, SPBWC_Company::META_ALLOWED_PRODUCTS, array_map( 'absint', $product_ids ) );
                }
                return;
            }
            // First product: a flat negotiated unit price; the rest: 15% off.
            $first = true;
            foreach ( $product_ids as $pid ) {
                if ( $first ) {
                    $product = wc_get_product( $pid );
                    $base    = $product ? (float) $product->get_price() : 0;
                    $fixed   = $base > 0 ? round( $base * 0.8, 2 ) : 0;
                    if ( $fixed > 0 ) {
                        SPBWC_B2B_Price_Rules::save_rule( $company_id, $pid, 'fixed', $fixed, 1 );
                    } else {
                        SPBWC_B2B_Price_Rules::save_rule( $company_id, $pid, 'pct', 15, 1 );
                    }
                    $first = false;
                } else {
                    SPBWC_B2B_Price_Rules::save_rule( $company_id, $pid, 'pct', 15, 1 );
                }
            }
            update_post_meta( $company_id, SPBWC_Company::META_ALLOWED_PRODUCTS, array_map( 'absint', $product_ids ) );
        }

        /**
         * Seed one sample RFQ quote tied to the company name.
         *
         * @param int $author_id Owner user.
         */
        protected static function seed_quote( $author_id ) {
            if ( ! class_exists( 'SPBWC_Quote' ) ) {
                return;
            }
            $quote_id = SPBWC_Quote::create(
                array(
                    'first_name' => __( 'Jordan', 'storelly-product-builder-for-woocommerce' ),
                    'last_name'  => __( 'Pike', 'storelly-product-builder-for-woocommerce' ),
                    'email'      => 'netbase-owner@example.com',
                    'company'    => 'Netbase JSC',
                    'message'    => __( 'Sample quote from the Netbase JSC B2B client — price the lines, send the reply, then remove the sample when you are done.', 'storelly-product-builder-for-woocommerce' ),
                ),
                (int) $author_id
            );
            if ( is_wp_error( $quote_id ) || ! $quote_id ) {
                return;
            }
            SPBWC_Quote::set_lines(
                $quote_id,
                array(
                    array(
                        'label'      => __( 'Branded notebooks — A5, soft-touch cover', 'storelly-product-builder-for-woocommerce' ),
                        'desc'       => '',
                        'qty'        => 250,
                        'unit_price' => 0,
                    ),
                    array(
                        'label'      => __( 'Event roll-up banners — 85×200cm', 'storelly-product-builder-for-woocommerce' ),
                        'desc'       => '',
                        'qty'        => 8,
                        'unit_price' => 0,
                    ),
                )
            );
            update_post_meta( $quote_id, self::FLAG, '1' );
        }

        /**
         * Seed one completed order for the first bound product.
         *
         * @param int   $customer_id Owner user.
         * @param int[] $product_ids Products.
         */
        protected static function seed_order( $customer_id, $product_ids ) {
            if ( empty( $product_ids ) || ! function_exists( 'wc_create_order' ) ) {
                return;
            }
            $product = wc_get_product( $product_ids[0] );
            if ( ! $product ) {
                return;
            }
            $order = wc_create_order( array( 'customer_id' => (int) $customer_id ) );
            if ( is_wp_error( $order ) ) {
                return;
            }
            $order->add_product( $product, 2 );
            $order->set_address(
                array(
                    'first_name' => 'Jordan',
                    'last_name'  => 'Pike',
                    'company'    => 'Netbase JSC',
                    'email'      => 'netbase-owner@example.com',
                    'country'    => 'VN',
                    'city'       => 'Hanoi',
                ),
                'billing'
            );
            $order->calculate_totals();
            $order->update_meta_data( self::FLAG, '1' );
            $order->set_status( 'completed', __( 'Sample B2B order.', 'storelly-product-builder-for-woocommerce' ) );
            $order->save();
        }

        /**
         * Seed one pending team-approval request.
         *
         * @param int   $company_id   Company.
         * @param int   $requester_id Requester user.
         * @param int[] $product_ids  Products.
         */
        protected static function seed_procurement( $company_id, $requester_id, $product_ids ) {
            if ( empty( $product_ids ) || ! class_exists( 'SPBWC_B2B_Procurement' ) ) {
                return;
            }
            $product = wc_get_product( $product_ids[0] );
            if ( ! $product ) {
                return;
            }
            $unit  = (float) $product->get_price();
            $qty   = 4;
            $total = $unit * $qty;
            $post_id = wp_insert_post(
                array(
                    'post_type'      => SPBWC_B2B_Procurement::POST_TYPE,
                    'post_status'    => 'publish',
                    'post_author'    => (int) $requester_id,
                    'post_title'     => sprintf(
                        /* translators: %s: formatted total. */
                        __( 'Approval request — %s', 'storelly-product-builder-for-woocommerce' ),
                        wp_strip_all_tags( wc_price( $total ) )
                    ),
                    'comment_status' => 'closed',
                ),
                true
            );
            if ( is_wp_error( $post_id ) || ! $post_id ) {
                return;
            }
            $snapshot = array(
                array(
                    'product_id'   => (int) $product_ids[0],
                    'variation_id' => 0,
                    'quantity'     => $qty,
                    'line_total'   => $total,
                ),
            );
            update_post_meta( $post_id, SPBWC_B2B_Procurement::META_COMPANY, (int) $company_id );
            update_post_meta( $post_id, SPBWC_B2B_Procurement::META_REQUESTER, (int) $requester_id );
            update_post_meta( $post_id, SPBWC_B2B_Procurement::META_SNAPSHOT, $snapshot );
            update_post_meta( $post_id, SPBWC_B2B_Procurement::META_TOTAL, $total );
            update_post_meta( $post_id, SPBWC_B2B_Procurement::META_STATUS, SPBWC_B2B_Procurement::STATUS_PENDING );
            update_post_meta( $post_id, self::FLAG, '1' );
            if ( method_exists( 'SPBWC_B2B_Procurement', 'invalidate_pending_count' ) ) {
                SPBWC_B2B_Procurement::invalidate_pending_count( $company_id );
            }
        }

        /* ── Primitives ───────────────────────────────────────────── */

        /**
         * Ensure the tier ladder exists (fresh installs have none) and return a
         * tier slug to assign. Remembers if WE created the ladder.
         *
         * @return string Tier slug ('' if pricing class is missing).
         */
        protected static function ensure_tier() {
            if ( ! class_exists( 'SPBWC_B2B_Pricing' ) ) {
                return '';
            }
            $tiers = SPBWC_B2B_Pricing::get_tiers();
            if ( empty( $tiers ) ) {
                update_option(
                    SPBWC_B2B_Pricing::OPTION_TIERS,
                    array(
                        'tier_a' => array( 'label' => __( 'Tier A', 'storelly-product-builder-for-woocommerce' ), 'discount_pct' => 10, 'min_order' => 100, 'terms' => 'net15', 'free_ship_over' => 150 ),
                        'tier_b' => array( 'label' => __( 'Tier B', 'storelly-product-builder-for-woocommerce' ), 'discount_pct' => 15, 'min_order' => 250, 'terms' => 'net30', 'free_ship_over' => 100 ),
                        'tier_c' => array( 'label' => __( 'Tier C', 'storelly-product-builder-for-woocommerce' ), 'discount_pct' => 20, 'min_order' => 500, 'terms' => 'net30', 'free_ship_over' => 0 ),
                    )
                );
                update_option( self::OPTION_MADE_TIERS, 1, false );
                return 'tier_b';
            }
            // Use an existing tier — prefer a mid one, else the first.
            if ( isset( $tiers['tier_b'] ) ) {
                return 'tier_b';
            }
            $keys = array_keys( $tiers );
            return isset( $keys[0] ) ? (string) $keys[0] : '';
        }

        /**
         * Find or create a flagged demo customer.
         *
         * @param string $email Login/email.
         * @param string $name  Display name.
         * @return int User id, or 0.
         */
        protected static function ensure_user( $email, $name ) {
            $email = sanitize_email( $email );
            if ( ! is_email( $email ) ) {
                return 0;
            }
            $user = get_user_by( 'email', $email );
            if ( $user ) {
                return (int) $user->ID;
            }
            $login = sanitize_user( current( explode( '@', $email ) ), true );
            if ( username_exists( $login ) ) {
                $login .= '-' . wp_rand( 100, 999 );
            }
            $uid = wp_insert_user(
                array(
                    'user_login'   => $login,
                    'user_email'   => $email,
                    'user_pass'    => wp_generate_password( 24, true ),
                    'display_name' => $name,
                    'first_name'   => current( explode( ' ', $name ) ),
                    'role'         => 'customer',
                )
            );
            if ( is_wp_error( $uid ) || ! $uid ) {
                return 0;
            }
            update_user_meta( $uid, self::FLAG, '1' );
            return (int) $uid;
        }

        /**
         * Pick up to N published, visible products to bind.
         *
         * @param int $n Count.
         * @return int[]
         */
        protected static function pick_products( $n ) {
            if ( ! function_exists( 'wc_get_products' ) ) {
                return array();
            }
            $products = wc_get_products(
                array(
                    'status'  => 'publish',
                    'limit'   => (int) $n,
                    'orderby' => 'date',
                    'order'   => 'DESC',
                    'return'  => 'ids',
                )
            );
            return is_array( $products ) ? array_map( 'absint', $products ) : array();
        }

        /**
         * Side-load a bundled image file into the media library, flagged sample.
         *
         * @param string $path     Absolute file path inside the plugin.
         * @param string $filename Target filename.
         * @param int    $parent   Parent post id (the company).
         * @return int Attachment id, or 0.
         */
        protected static function sideload( $path, $filename, $parent = 0 ) {
            if ( ! is_string( $path ) || ! file_exists( $path ) ) {
                return 0;
            }
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';

            $tmp = wp_tempnam( $filename );
            if ( ! $tmp || ! @copy( $path, $tmp ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
                if ( $tmp ) {
                    wp_delete_file( $tmp );
                }
                return 0;
            }
            $file = array(
                'name'     => $filename,
                'tmp_name' => $tmp,
                'error'    => 0,
                'size'     => filesize( $tmp ),
            );
            $id = media_handle_sideload( $file, (int) $parent, null, array( 'test_form' => false ) );
            if ( is_wp_error( $id ) ) {
                if ( file_exists( $tmp ) ) {
                    wp_delete_file( $tmp );
                }
                return 0;
            }
            update_post_meta( (int) $id, self::FLAG, '1' );
            return (int) $id;
        }

        /* ── Removal ──────────────────────────────────────────────── */

        /**
         * Remove every sample artefact (company, users, quote, order,
         * procurement, attachments, price rules, and the tier ladder if we
         * created it).
         *
         * @return bool
         */
        public static function remove_all() {
            global $wpdb;

            $company_id = self::company_id();

            // Price rules for the company.
            if ( $company_id && class_exists( 'SPBWC_B2B_Price_Rules' ) ) {
                $table = SPBWC_B2B_Price_Rules::table();
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE company_id = %d", $company_id ) );
            }

            // Flagged posts: company, quotes, orders (legacy CPT), procurement, attachments.
            $post_ids = get_posts(
                array(
                    'post_type'   => 'any',
                    'post_status' => 'any',
                    'numberposts' => -1,
                    'fields'      => 'ids',
                    'meta_key'    => self::FLAG,   // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
                    'meta_value'  => '1',          // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
                )
            );
            foreach ( (array) $post_ids as $pid ) {
                wp_delete_post( (int) $pid, true );
            }

            // HPOS / flagged orders (wc_orders store, not wp_posts).
            if ( function_exists( 'wc_get_orders' ) ) {
                $orders = wc_get_orders(
                    array(
                        'limit'      => -1,
                        'return'     => 'ids',
                        'meta_key'   => self::FLAG,   // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
                        'meta_value' => '1',          // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
                    )
                );
                foreach ( (array) $orders as $oid ) {
                    $order = wc_get_order( $oid );
                    if ( $order ) {
                        $order->delete( true );
                    }
                }
            }

            // Flagged demo users.
            $users = get_users(
                array(
                    'meta_key'   => self::FLAG,   // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
                    'meta_value' => '1',          // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
                    'fields'     => 'ID',
                )
            );
            if ( ! empty( $users ) ) {
                require_once ABSPATH . 'wp-admin/includes/user.php';
                foreach ( $users as $uid ) {
                    wp_delete_user( (int) $uid );
                }
            }

            // Tier ladder we created.
            if ( get_option( self::OPTION_MADE_TIERS ) && class_exists( 'SPBWC_B2B_Pricing' ) ) {
                delete_option( SPBWC_B2B_Pricing::OPTION_TIERS );
                delete_option( self::OPTION_MADE_TIERS );
            }

            delete_option( self::OPTION_SEEDED );
            delete_option( self::OPTION_PENDING );
            return true;
        }

        /* ── Admin-post handlers + notice ─────────────────────────── */

        protected static function guard() {
            if ( ! current_user_can( 'manage_woocommerce' ) ) {
                wp_die( esc_html__( 'You are not allowed to do this.', 'storelly-product-builder-for-woocommerce' ) );
            }
        }

        protected static function back_url( $flag ) {
            $base = admin_url( 'admin.php?page=' . ( class_exists( 'SPBWC_B2B_Admin' ) ? SPBWC_B2B_Admin::PAGE_SLUG : 'spbwc-b2b' ) );
            return add_query_arg( 'spbwc_sample', $flag, $base );
        }

        public static function handle_seed() {
            self::guard();
            check_admin_referer( 'spbwc_b2b_sample' );
            update_option( self::OPTION_SEEDED, 0, false ); // allow a re-seed if removed.
            $id = self::seed();
            wp_safe_redirect( self::back_url( $id ? 'added' : 'error' ) );
            exit;
        }

        public static function handle_remove() {
            self::guard();
            check_admin_referer( 'spbwc_b2b_sample' );
            self::remove_all();
            wp_safe_redirect( self::back_url( 'removed' ) );
            exit;
        }

        /** One-shot admin notice after an auto-seed. */
        public static function seeded_notice() {
            $url = self::back_url( 'added' );
            echo '<div class="notice notice-success is-dismissible"><p>';
            echo esc_html__( 'Storelly installed a sample B2B client (“Netbase JSC”) so you can explore the brand store, pricing, quotes and team approval.', 'storelly-product-builder-for-woocommerce' );
            echo ' <a href="' . esc_url( $url ) . '">' . esc_html__( 'View B2B clients', 'storelly-product-builder-for-woocommerce' ) . '</a>';
            echo '</p></div>';
        }
    }
}
