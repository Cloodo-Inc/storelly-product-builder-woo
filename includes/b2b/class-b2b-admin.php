<?php
/**
 * B2B Companies merchant workspace (M1).
 *
 * Registers the "B2B Companies" submenu and the "Upgrade to B2B" entry point on
 * the WordPress Users list. Renders three screens against the `spbwc_company`
 * CPT: a status-tabbed list, a per-company detail, and the upgrade form that
 * converts a regular customer into a company owner. Review-then-decide pattern
 * (approve / suspend pending companies). See docs/SPEC_B2B_CLIENT.md §4.1.
 *
 * @package Storelly
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'SPBWC_B2B_Admin' ) ) {

    class SPBWC_B2B_Admin {

        const PAGE_SLUG  = 'storelly-product-builder-for-woocommerce-b2b-companies';
        const CAPABILITY = 'manage_woocommerce';

        /** @var SPBWC_B2B_Admin|null */
        protected static $instance;

        /** @var string Flash notice code. */
        protected $notice = '';

        public static function instance() {
            if ( is_null( self::$instance ) ) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        public function init() {
            add_action( 'admin_menu', array( $this, 'register_menu' ), 21 );
            add_filter( 'user_row_actions', array( $this, 'user_row_action' ), 10, 2 );
        }

        /* ── Menu ─────────────────────────────────────────────────── */

        public function register_menu() {
            $title   = esc_html__( 'B2B Companies', 'storelly-product-builder-for-woocommerce' );
            $pending = self::count_by_status( SPBWC_Company::STATUS_PENDING );
            if ( $pending > 0 ) {
                $title .= ' <span class="awaiting-mod"><span class="pending-count">' . esc_html( number_format_i18n( $pending ) ) . '</span></span>';
            }
            add_submenu_page(
                SPBWC_PB_OVERVIEW_SLUG,
                esc_html__( 'B2B Companies', 'storelly-product-builder-for-woocommerce' ),
                $title,
                self::CAPABILITY,
                self::PAGE_SLUG,
                array( $this, 'render' )
            );
        }

        /**
         * Add an "Upgrade to B2B" row action on the Users list.
         *
         * @param array   $actions Existing actions.
         * @param WP_User $user    Row user.
         * @return array
         */
        public function user_row_action( $actions, $user ) {
            if ( ! current_user_can( self::CAPABILITY ) || ! ( $user instanceof WP_User ) ) {
                return $actions;
            }
            if ( SPBWC_Company::get_user_company_id( $user->ID ) ) {
                $cid = SPBWC_Company::get_user_company_id( $user->ID );
                $actions['spbwc_b2b'] = '<a href="' . esc_url( self::page_url( array( 'company' => $cid ) ) ) . '">'
                    . esc_html__( 'View B2B company', 'storelly-product-builder-for-woocommerce' ) . '</a>';
                return $actions;
            }
            $actions['spbwc_b2b'] = '<a href="' . esc_url( self::page_url( array( 'action' => 'upgrade', 'user' => $user->ID ) ) ) . '">'
                . esc_html__( 'Upgrade to B2B', 'storelly-product-builder-for-woocommerce' ) . '</a>';
            return $actions;
        }

        /* ── Helpers ──────────────────────────────────────────────── */

        public static function page_url( $args = array() ) {
            $args = array_merge( array( 'page' => self::PAGE_SLUG ), $args );
            return add_query_arg( $args, admin_url( 'admin.php' ) );
        }

        /**
         * Company counts per status + 'all', computed in ONE query and cached for
         * the request (the hub previously ran ~9 full get_posts() per load).
         *
         * @return array<string,int> status slug => count, plus 'all'
         */
        public static function status_counts() {
            static $cache = null;
            if ( null !== $cache ) {
                return $cache;
            }
            global $wpdb;
            $rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                $wpdb->prepare(
                    "SELECT pm.meta_value AS status, COUNT(*) AS n
                     FROM {$wpdb->posts} p
                     INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = %s
                     WHERE p.post_type = %s AND p.post_status = 'publish'
                     GROUP BY pm.meta_value",
                    SPBWC_Company::META_STATUS,
                    SPBWC_Company::POST_TYPE
                )
            );
            $cache = array( 'all' => 0 );
            foreach ( array_keys( SPBWC_Company::statuses() ) as $s ) {
                $cache[ $s ] = 0;
            }
            foreach ( (array) $rows as $r ) {
                $cache[ (string) $r->status ] = (int) $r->n;
                $cache['all']                += (int) $r->n;
            }
            return $cache;
        }

        /**
         * @param string $status Status slug.
         * @return int
         */
        public static function count_by_status( $status ) {
            $c = self::status_counts();
            return isset( $c[ $status ] ) ? $c[ $status ] : 0;
        }

        /**
         * @param string $status Status slug.
         * @return string Pill HTML.
         */
        public static function status_pill( $status ) {
            $map = array(
                SPBWC_Company::STATUS_PENDING    => 'warn',
                SPBWC_Company::STATUS_ACTIVE     => 'success',
                SPBWC_Company::STATUS_SUSPENDED  => 'danger',
                SPBWC_Company::STATUS_INCOMPLETE => 'off',
            );
            $labels  = SPBWC_Company::statuses();
            $variant = isset( $map[ $status ] ) ? $map[ $status ] : 'neutral';
            $label   = isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
            return '<span class="spbwc-pill spbwc-pill--' . esc_attr( $variant ) . '">' . esc_html( $label ) . '</span>';
        }

        /* ── Routing ──────────────────────────────────────────────── */

        public function render() {
            if ( ! current_user_can( self::CAPABILITY ) ) {
                wp_die( esc_html__( 'You do not have permission to access this page.', 'storelly-product-builder-for-woocommerce' ) );
            }
            $this->maybe_handle_actions();

            // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only routing args.
            $action     = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
            $company_id = isset( $_GET['company'] ) ? absint( wp_unslash( $_GET['company'] ) ) : 0;
            $user_id    = isset( $_GET['user'] ) ? absint( wp_unslash( $_GET['user'] ) ) : 0;
            // phpcs:enable WordPress.Security.NonceVerification.Recommended

            echo '<div class="wrap spbwc-settings-wrap spbwc-b2b-admin">';
            $this->print_notice();

            if ( 'upgrade' === $action && $user_id ) {
                $this->render_upgrade_form( $user_id );
            } elseif ( $company_id && SPBWC_Company::POST_TYPE === get_post_type( $company_id ) ) {
                $this->render_detail( $company_id );
            } else {
                $this->render_list();
            }
            echo '</div>';
        }

        /* ── Action handling ──────────────────────────────────────── */

        protected function maybe_handle_actions() {
            if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
                return;
            }
            if ( ! isset( $_POST['spbwc_b2b_do'] ) ) {
                return;
            }
            $do = sanitize_key( wp_unslash( $_POST['spbwc_b2b_do'] ) );

            if ( 'upgrade' === $do ) {
                $this->handle_upgrade();
                return;
            }

            $company_id = isset( $_POST['company'] ) ? absint( wp_unslash( $_POST['company'] ) ) : 0;
            if ( ! $company_id || ! wp_verify_nonce( isset( $_POST['_spbwc_b2b_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_spbwc_b2b_nonce'] ) ) : '', 'spbwc_b2b_' . $company_id ) ) {
                $this->notice = 'error';
                return;
            }
            if ( ! current_user_can( self::CAPABILITY ) ) {
                wp_die( esc_html__( 'Permission denied.', 'storelly-product-builder-for-woocommerce' ) );
            }

            switch ( $do ) {
                case 'approve':
                    SPBWC_Company::set_status( $company_id, SPBWC_Company::STATUS_ACTIVE, __( 'Company approved by merchant.', 'storelly-product-builder-for-woocommerce' ) );
                    $this->notice = 'approved';
                    break;
                case 'suspend':
                    SPBWC_Company::set_status( $company_id, SPBWC_Company::STATUS_SUSPENDED, __( 'Company suspended by merchant.', 'storelly-product-builder-for-woocommerce' ) );
                    $this->notice = 'suspended';
                    break;
                case 'reactivate':
                    SPBWC_Company::set_status( $company_id, SPBWC_Company::STATUS_ACTIVE, __( 'Company reactivated by merchant.', 'storelly-product-builder-for-woocommerce' ) );
                    $this->notice = 'approved';
                    break;
                case 'save':
                    $seats     = isset( $_POST['seats'] ) ? absint( wp_unslash( $_POST['seats'] ) ) : SPBWC_Company::default_seats();
                    $threshold = isset( $_POST['approval_threshold'] ) ? (float) wp_unslash( $_POST['approval_threshold'] ) : 0;
                    $terms     = isset( $_POST['payment_terms'] ) ? sanitize_key( wp_unslash( $_POST['payment_terms'] ) ) : 'prepaid';
                    $climit    = isset( $_POST['credit_limit'] ) ? max( 0, (float) wp_unslash( $_POST['credit_limit'] ) ) : 0;
                    $tier      = isset( $_POST['tier'] ) ? sanitize_key( wp_unslash( $_POST['tier'] ) ) : '';
                    update_post_meta( $company_id, SPBWC_Company::META_SEATS, $seats );
                    update_post_meta( $company_id, SPBWC_Company::META_APPROVAL_THRESHOLD, $threshold );
                    update_post_meta( $company_id, SPBWC_Company::META_PAYMENT_TERMS, $terms );
                    update_post_meta( $company_id, SPBWC_Company::META_CREDIT_LIMIT, $climit );
                    $old_tier = (string) get_post_meta( $company_id, SPBWC_Company::META_TIER, true );
                    if ( $tier !== $old_tier ) {
                        update_post_meta( $company_id, SPBWC_Company::META_TIER, $tier );
                        $tier_label = '' !== $tier && class_exists( 'SPBWC_B2B_Pricing' ) ? SPBWC_B2B_Pricing::tier_label( $tier ) : __( 'none', 'storelly-product-builder-for-woocommerce' );
                        SPBWC_Company::add_timeline_event(
                            $company_id,
                            sprintf(
                                /* translators: %s: tier label. */
                                __( 'Pricing tier changed to %s.', 'storelly-product-builder-for-woocommerce' ),
                                $tier_label
                            )
                        );
                    }
                    $this->notice = 'saved';
                    break;
                case 'bind_price':
                    $pid   = isset( $_POST['product_id'] ) ? absint( wp_unslash( $_POST['product_id'] ) ) : 0;
                    $ptype = isset( $_POST['override_type'] ) ? sanitize_key( wp_unslash( $_POST['override_type'] ) ) : 'pct';
                    $pval  = isset( $_POST['value'] ) ? (float) wp_unslash( $_POST['value'] ) : 0;
                    $pmin  = isset( $_POST['min_qty'] ) ? absint( wp_unslash( $_POST['min_qty'] ) ) : 0;
                    $puntil = isset( $_POST['valid_until'] ) ? sanitize_text_field( wp_unslash( $_POST['valid_until'] ) ) : '';
                    if ( $pid && wc_get_product( $pid ) && class_exists( 'SPBWC_B2B_Price_Rules' ) ) {
                        SPBWC_B2B_Price_Rules::save_rule( $company_id, $pid, $ptype, $pval, $pmin, $puntil );
                        self::add_to_allow_list( $company_id, $pid );
                        $this->notice = 'saved';
                    } else {
                        $this->notice = 'error';
                    }
                    break;
                case 'unbind_price':
                    $pid = isset( $_POST['product_id'] ) ? absint( wp_unslash( $_POST['product_id'] ) ) : 0;
                    if ( $pid && class_exists( 'SPBWC_B2B_Price_Rules' ) ) {
                        SPBWC_B2B_Price_Rules::delete_rule( $company_id, $pid );
                        self::remove_from_allow_list( $company_id, $pid );
                        $this->notice = 'saved';
                    }
                    break;
                case 'credit_topup':
                case 'credit_payment':
                case 'credit_adjust':
                    if ( class_exists( 'SPBWC_B2B_Ledger' ) ) {
                        $amount = isset( $_POST['amount'] ) ? (float) wp_unslash( $_POST['amount'] ) : 0;
                        $note   = isset( $_POST['note'] ) ? sanitize_text_field( wp_unslash( $_POST['note'] ) ) : '';
                        if ( $amount > 0 ) {
                            if ( 'credit_topup' === $do ) {
                                SPBWC_B2B_Ledger::post_topup( $company_id, $amount, array( 'note' => $note, 'ref_type' => 'manual' ) );
                            } elseif ( 'credit_payment' === $do ) {
                                SPBWC_B2B_Ledger::post_payment( $company_id, $amount, array( 'note' => $note, 'ref_type' => 'manual' ) );
                            } else {
                                // Adjustment: signed. Positive = credit, negative = debit.
                                $dir   = isset( $_POST['direction'] ) ? sanitize_key( wp_unslash( $_POST['direction'] ) ) : 'credit';
                                $entry = ( 'debit' === $dir ) ? array( 'debit' => $amount ) : array( 'credit' => $amount );
                                SPBWC_B2B_Ledger::record( $company_id, array_merge( $entry, array(
                                    'txn_type' => SPBWC_B2B_Ledger::TXN_ADJUSTMENT,
                                    'ref_type' => 'manual',
                                    'note'     => $note,
                                ) ) );
                            }
                            $this->notice = 'saved';
                        } else {
                            $this->notice = 'error';
                        }
                    }
                    break;
            }
        }

        /**
         * Add a product to the company's Brand Store allow-list.
         *
         * @param int $company_id Company.
         * @param int $product_id Product.
         */
        protected static function add_to_allow_list( $company_id, $product_id ) {
            $list = get_post_meta( $company_id, SPBWC_Company::META_ALLOWED_PRODUCTS, true );
            $list = is_array( $list ) ? array_map( 'absint', $list ) : array();
            if ( ! in_array( (int) $product_id, $list, true ) ) {
                $list[] = (int) $product_id;
                update_post_meta( $company_id, SPBWC_Company::META_ALLOWED_PRODUCTS, $list );
            }
        }

        /**
         * Remove a product from the company's Brand Store allow-list.
         *
         * @param int $company_id Company.
         * @param int $product_id Product.
         */
        protected static function remove_from_allow_list( $company_id, $product_id ) {
            $list = get_post_meta( $company_id, SPBWC_Company::META_ALLOWED_PRODUCTS, true );
            if ( ! is_array( $list ) ) {
                return;
            }
            $list = array_values( array_diff( array_map( 'absint', $list ), array( (int) $product_id ) ) );
            update_post_meta( $company_id, SPBWC_Company::META_ALLOWED_PRODUCTS, $list );
        }

        /** Create a company from a regular customer (the upgrade). */
        protected function handle_upgrade() {
            $user_id = isset( $_POST['user'] ) ? absint( wp_unslash( $_POST['user'] ) ) : 0;
            $nonce   = isset( $_POST['_spbwc_b2b_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_spbwc_b2b_nonce'] ) ) : '';
            if ( ! $user_id || ! wp_verify_nonce( $nonce, 'spbwc_b2b_upgrade_' . $user_id ) ) {
                $this->notice = 'error';
                return;
            }
            if ( ! current_user_can( self::CAPABILITY ) ) {
                wp_die( esc_html__( 'Permission denied.', 'storelly-product-builder-for-woocommerce' ) );
            }
            $name  = isset( $_POST['company_name'] ) ? sanitize_text_field( wp_unslash( $_POST['company_name'] ) ) : '';
            $seats = isset( $_POST['seats'] ) ? absint( wp_unslash( $_POST['seats'] ) ) : SPBWC_Company::default_seats();
            $terms = isset( $_POST['payment_terms'] ) ? sanitize_key( wp_unslash( $_POST['payment_terms'] ) ) : 'prepaid';
            $tier  = isset( $_POST['tier'] ) ? sanitize_key( wp_unslash( $_POST['tier'] ) ) : '';
            $status = ( isset( $_POST['activate'] ) && '1' === $_POST['activate'] ) ? SPBWC_Company::STATUS_ACTIVE : SPBWC_Company::STATUS_PENDING;

            $result = SPBWC_Company::create(
                $name,
                $user_id,
                array(
                    'seats'         => $seats,
                    'payment_terms' => $terms,
                    'tier'          => $tier,
                    'status'        => $status,
                )
            );
            if ( is_wp_error( $result ) ) {
                $this->notice = 'upgrade_error';
                return;
            }
            // Notify the new owner (local WC-mailer; no external service).
            do_action( 'spbwc_company_upgraded', $result, $user_id );
            wp_safe_redirect( self::page_url( array( 'company' => $result, 'spbwc_msg' => 'created' ) ) );
            exit;
        }

        /* ── Screen: list ─────────────────────────────────────────── */

        /* ── Shared UI helpers (reuse the Storelly component library) ── */

        /**
         * Page hero band (matches the Overview / Quote workspace).
         *
         * @param string $title    Page title.
         * @param string $subtitle Subtitle.
         * @param string $actions  Pre-escaped action-button HTML.
         * @param string $icon     Title dashicon slug.
         */
        protected function render_hero( $title, $subtitle, $actions = '', $icon = 'groups', $suffix = '' ) {
            echo '<header class="spbwc-page-hero"><div class="spbwc-page-hero__grid"><div class="spbwc-page-hero__body">';
            echo '<div class="spbwc-page-hero__eyebrow"><span class="dashicons dashicons-admin-plugins" aria-hidden="true"></span>'
                . esc_html__( 'Storelly · B2B', 'storelly-product-builder-for-woocommerce' ) . '</div>';
            echo '<h1 class="spbwc-page-hero__title"><span class="dashicons dashicons-' . esc_attr( $icon ) . '" aria-hidden="true"></span> ' . esc_html( $title )
                . ( '' !== $suffix ? ' ' . $suffix : '' ) . '</h1>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $suffix is pre-escaped pill HTML.
            echo '<p class="spbwc-page-hero__subtitle">' . esc_html( $subtitle ) . '</p>';
            echo '</div>';
            if ( '' !== $actions ) {
                echo '<div class="spbwc-page-hero__actions">' . $actions . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- caller escapes.
            }
            echo '</div></header>';
        }

        /** Circular initials avatar for a user. */
        protected static function avatar( $user, $extra = '' ) {
            $name     = $user ? $user->display_name : '?';
            $initials = '';
            foreach ( preg_split( '/\s+/', trim( (string) $name ) ) as $p ) {
                if ( '' !== $p ) {
                    $initials .= function_exists( 'mb_substr' ) ? mb_substr( $p, 0, 1 ) : substr( $p, 0, 1 );
                }
                if ( strlen( $initials ) >= 2 ) {
                    break;
                }
            }
            $initials = '' !== $initials ? strtoupper( $initials ) : '?';
            return '<span class="spbwc-avatar ' . esc_attr( $extra ) . '">' . esc_html( $initials ) . '</span>';
        }

        /** Thin usage meter (used / total → coloured bar). */
        protected static function meter( $used, $total ) {
            $pct = $total > 0 ? min( 100, (int) round( $used / $total * 100 ) ) : 0;
            $mod = $pct >= 90 ? ' spbwc-meter--danger' : ( $pct >= 70 ? ' spbwc-meter--warn' : '' );
            return '<span class="spbwc-meter' . $mod . '"><span class="spbwc-meter__fill" style="width:' . esc_attr( $pct ) . '%"></span></span>';
        }

        protected function render_list() {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter.
            $tab = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : 'all';

            $actions = '';
            if ( class_exists( 'SPBWC_B2B_Pricing_Admin' ) ) {
                $actions = '<a class="spbwc-cta-btn spbwc-cta-btn--solid" href="' . esc_url( SPBWC_B2B_Pricing_Admin::page_url() ) . '"><span class="dashicons dashicons-tag" aria-hidden="true"></span> ' . esc_html__( 'Manage tiers', 'storelly-product-builder-for-woocommerce' ) . '</a>';
            }
            $this->render_hero(
                __( 'B2B Companies', 'storelly-product-builder-for-woocommerce' ),
                __( 'Branded accounts, tier pricing and team procurement. Upgrade a customer from Users → row → "Upgrade to B2B".', 'storelly-product-builder-for-woocommerce' ),
                $actions
            );

            // KPI stat cards.
            $cards = array(
                array( 'icon' => 'groups',   'tone' => 'brand',   'value' => self::count_all(), 'label' => __( 'Total companies', 'storelly-product-builder-for-woocommerce' ) ),
                array( 'icon' => 'yes-alt',  'tone' => 'success', 'value' => self::count_by_status( SPBWC_Company::STATUS_ACTIVE ), 'label' => __( 'Active', 'storelly-product-builder-for-woocommerce' ) ),
                array( 'icon' => 'clock',    'tone' => 'warning', 'value' => self::count_by_status( SPBWC_Company::STATUS_PENDING ), 'label' => __( 'Pending approval', 'storelly-product-builder-for-woocommerce' ) ),
                array( 'icon' => 'admin-users', 'tone' => 'accent', 'value' => self::count_by_status( SPBWC_Company::STATUS_INCOMPLETE ), 'label' => __( 'Incomplete profile', 'storelly-product-builder-for-woocommerce' ) ),
            );
            echo '<div class="spbwc-stat-grid">';
            foreach ( $cards as $c ) {
                echo '<div class="spbwc-stat-card spbwc-stat-card--' . esc_attr( $c['tone'] ) . '">';
                echo '<span class="spbwc-stat-card__icon dashicons dashicons-' . esc_attr( $c['icon'] ) . '" aria-hidden="true"></span>';
                echo '<span class="spbwc-stat-card__label">' . esc_html( $c['label'] ) . '</span>';
                echo '<span class="spbwc-stat-card__value">' . esc_html( number_format_i18n( $c['value'] ) ) . '</span>';
                echo '</div>';
            }
            echo '</div>';

            // Block: toolbar (status tabs) + table.
            echo '<div class="spbwc-block spbwc-block--flat">';
            echo '<div class="spbwc-list-toolbar">';
            $tabs = array_merge( array( 'all' => __( 'All', 'storelly-product-builder-for-woocommerce' ) ), SPBWC_Company::statuses() );
            echo '<ul class="subsubsub">';
            $i = 0;
            foreach ( $tabs as $slug => $label ) {
                $count = ( 'all' === $slug ) ? self::count_all() : self::count_by_status( $slug );
                $url   = self::page_url( 'all' === $slug ? array() : array( 'status' => $slug ) );
                $cls   = ( $tab === $slug ) ? ' class="current"' : '';
                echo '<li>' . ( $i ? ' | ' : '' ) . '<a href="' . esc_url( $url ) . '"' . $cls . '>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static literal.
                    . esc_html( $label ) . ' <span class="count">(' . esc_html( number_format_i18n( $count ) ) . ')</span></a></li>';
                $i++;
            }
            echo '</ul></div>';

            $args = array(
                'post_type'   => SPBWC_Company::POST_TYPE,
                'post_status' => 'publish',
                'numberposts' => 100,
                'orderby'     => 'date',
                'order'       => 'DESC',
            );
            if ( 'all' !== $tab && isset( SPBWC_Company::statuses()[ $tab ] ) ) {
                $args['meta_key']   = SPBWC_Company::META_STATUS; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
                $args['meta_value'] = $tab;                       // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
            }
            $companies = get_posts( $args );

            if ( empty( $companies ) ) {
                echo '<div class="spbwc-empty-state"><div class="spbwc-empty-state__icon"><span class="dashicons dashicons-groups" aria-hidden="true"></span></div>';
                echo '<p class="spbwc-empty-state__title">' . esc_html__( 'No B2B companies yet', 'storelly-product-builder-for-woocommerce' ) . '</p>';
                echo '<p class="spbwc-empty-state__text">' . esc_html__( 'Upgrade a customer from the Users list to create their first company account.', 'storelly-product-builder-for-woocommerce' ) . '</p></div></div>';
                return;
            }

            echo '<table class="spbwc-admin-table"><thead><tr>';
            echo '<th>' . esc_html__( 'Company', 'storelly-product-builder-for-woocommerce' ) . '</th>';
            echo '<th>' . esc_html__( 'Owner', 'storelly-product-builder-for-woocommerce' ) . '</th>';
            echo '<th>' . esc_html__( 'Tier', 'storelly-product-builder-for-woocommerce' ) . '</th>';
            echo '<th>' . esc_html__( 'Team', 'storelly-product-builder-for-woocommerce' ) . '</th>';
            echo '<th>' . esc_html__( 'Status', 'storelly-product-builder-for-woocommerce' ) . '</th>';
            echo '<th></th></tr></thead><tbody>';

            foreach ( $companies as $company ) {
                $owner   = get_userdata( $company->post_author );
                $status  = SPBWC_Company::get_status( $company->ID );
                $members = SPBWC_Company::count_members( $company->ID );
                $seats   = SPBWC_Company::get_seats( $company->ID );
                $store   = SPBWC_Company::store_url( $company->ID );
                $logo_id = (int) get_post_meta( $company->ID, SPBWC_Company::META_LOGO, true );
                $tier    = (string) get_post_meta( $company->ID, SPBWC_Company::META_TIER, true );
                $detail  = self::page_url( array( 'company' => $company->ID ) );

                echo '<tr>';
                // Company (logo + name + slug).
                echo '<td><span class="spbwc-row spbwc-row--sm">';
                if ( $logo_id ) {
                    echo '<span class="spbwc-avatar">' . wp_get_attachment_image( $logo_id, array( 32, 32 ) ) . '</span>';
                } else {
                    echo '<span class="spbwc-avatar"><span class="dashicons dashicons-store" aria-hidden="true"></span></span>';
                }
                echo '<a href="' . esc_url( $detail ) . '"><strong>' . esc_html( get_the_title( $company ) ) . '</strong></a></span></td>';
                // Owner.
                echo '<td><span class="spbwc-row spbwc-row--sm">' . self::avatar( $owner, 'spbwc-avatar--sm' ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- avatar escapes.
                    . '<span>' . esc_html( $owner ? $owner->user_email : '—' ) . '</span></span></td>';
                // Tier.
                echo '<td>' . ( '' !== $tier && class_exists( 'SPBWC_B2B_Pricing' ) ? '<span class="spbwc-role-chip spbwc-role-chip--admin">' . esc_html( SPBWC_B2B_Pricing::tier_label( $tier ) ) . '</span>' : '<span class="spbwc-muted">—</span>' ) . '</td>';
                // Team meter.
                echo '<td><span class="spbwc-stack"><span>' . esc_html( $members . ' / ' . $seats ) . '</span>' . self::meter( $members, $seats ) . '</span></td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- meter escapes.
                // Status.
                echo '<td>' . self::status_pill( $status ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pill escapes.
                // Actions.
                echo '<td class="spbwc-stack--end" style="white-space:nowrap;"><a class="spbwc-cta-btn spbwc-cta-btn--sm" href="' . esc_url( $detail ) . '">' . esc_html__( 'Manage', 'storelly-product-builder-for-woocommerce' ) . '</a>';
                if ( '' !== $store ) {
                    echo ' <a class="spbwc-cta-btn spbwc-cta-btn--sm spbwc-cta-btn--ghost" href="' . esc_url( $store ) . '" target="_blank" rel="noopener">' . esc_html__( 'Store ↗', 'storelly-product-builder-for-woocommerce' ) . '</a>';
                }
                echo '</td></tr>';
            }
            echo '</tbody></table></div>';
        }

        protected static function count_all() {
            $c = self::status_counts();
            return isset( $c['all'] ) ? $c['all'] : 0;
        }

        /* ── Screen: upgrade form ─────────────────────────────────── */

        protected function render_upgrade_form( $user_id ) {
            $user = get_userdata( $user_id );
            if ( ! $user ) {
                echo '<p>' . esc_html__( 'User not found.', 'storelly-product-builder-for-woocommerce' ) . '</p>';
                return;
            }
            if ( SPBWC_Company::get_user_company_id( $user_id ) ) {
                echo '<p>' . esc_html__( 'This customer already belongs to a company.', 'storelly-product-builder-for-woocommerce' ) . '</p>';
                return;
            }
            // Prefill company name from the WooCommerce billing company / display name.
            $prefill = '';
            if ( function_exists( 'wc_get_customer_last_order' ) ) {
                $prefill = (string) get_user_meta( $user_id, 'billing_company', true );
            }
            if ( '' === $prefill ) {
                $prefill = $user->display_name;
            }

            $back = '<a class="spbwc-cta-btn spbwc-cta-btn--ghost spbwc-cta-btn--sm" href="' . esc_url( self::page_url() ) . '"><span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span> ' . esc_html__( 'Companies', 'storelly-product-builder-for-woocommerce' ) . '</a>';
            $this->render_hero(
                __( 'Upgrade to B2B', 'storelly-product-builder-for-woocommerce' ),
                __( 'Convert this customer into a company account — owner of their Brand Store, with a team and B2B pricing.', 'storelly-product-builder-for-woocommerce' ),
                $back,
                'businessperson'
            );

            $order_count = function_exists( 'wc_get_customer_order_count' ) ? (int) wc_get_customer_order_count( $user_id ) : 0;
            $spent       = function_exists( 'wc_get_customer_total_spent' ) ? wc_get_customer_total_spent( $user_id ) : 0;

            echo '<form method="post" action="' . esc_url( self::page_url() ) . '" class="spbwc-b2b-form">';
            echo '<input type="hidden" name="spbwc_b2b_do" value="upgrade" />';
            echo '<input type="hidden" name="user" value="' . esc_attr( $user_id ) . '" />';
            wp_nonce_field( 'spbwc_b2b_upgrade_' . $user_id, '_spbwc_b2b_nonce' );

            // Customer recap card.
            echo '<div class="spbwc-block spbwc-block--brand"><div class="spbwc-block__head"><h3 class="spbwc-block__title">' . esc_html__( 'Customer', 'storelly-product-builder-for-woocommerce' ) . '</h3></div><div class="spbwc-block__body">';
            echo '<div class="spbwc-row">';
            echo self::avatar( $user, 'spbwc-avatar--lg' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- avatar escapes.
            echo '<div><strong>' . esc_html( $user->display_name ) . '</strong><br /><span class="spbwc-muted">' . esc_html( $user->user_email ) . '</span></div>';
            echo '<div class="spbwc-row spbwc-row--push">';
            echo '<div class="spbwc-kv"><span class="spbwc-kv__v">' . esc_html( number_format_i18n( $order_count ) ) . '</span><span class="spbwc-kv__l">' . esc_html__( 'Orders', 'storelly-product-builder-for-woocommerce' ) . '</span></div>';
            echo '<div class="spbwc-kv"><span class="spbwc-kv__v">' . wp_kses_post( wc_price( $spent ) ) . '</span><span class="spbwc-kv__l">' . esc_html__( 'Spent', 'storelly-product-builder-for-woocommerce' ) . '</span></div>';
            echo '</div></div></div></div>';

            // Company setup card.
            echo '<div class="spbwc-block"><div class="spbwc-block__head"><h3 class="spbwc-block__title">' . esc_html__( 'Company setup', 'storelly-product-builder-for-woocommerce' ) . '</h3></div><div class="spbwc-block__body"><div class="spbwc-setting-rows">';

            echo '<div class="spbwc-setting-row"><label class="spbwc-setting-row__label" for="spbwc-company-name">' . esc_html__( 'Company name', 'storelly-product-builder-for-woocommerce' ) . ' *</label>';
            echo '<input class="spbwc-input" name="company_name" id="spbwc-company-name" type="text" required value="' . esc_attr( $prefill ) . '" /><span class="spbwc-setting-row__hint">' . esc_html__( 'Prefilled from billing company.', 'storelly-product-builder-for-woocommerce' ) . '</span></div>';

            // Proposed tier (radio pills).
            $tiers = class_exists( 'SPBWC_B2B_Pricing' ) ? SPBWC_B2B_Pricing::get_tiers() : array();
            echo '<div class="spbwc-setting-row"><span class="spbwc-setting-row__label">' . esc_html__( 'Proposed tier', 'storelly-product-builder-for-woocommerce' ) . '</span><div class="spbwc-radio-group">';
            echo '<label class="spbwc-radio-group__lbl"><input type="radio" name="tier" value="" checked /> ' . esc_html__( 'No tier', 'storelly-product-builder-for-woocommerce' ) . '</label>';
            foreach ( $tiers as $tslug => $t ) {
                $tlabel = isset( $t['label'] ) ? $t['label'] : $tslug;
                $tpct   = isset( $t['discount_pct'] ) ? (float) $t['discount_pct'] : 0;
                echo '<label class="spbwc-radio-group__lbl"><input type="radio" name="tier" value="' . esc_attr( $tslug ) . '" /> ' . esc_html( $tlabel . ' · ' . rtrim( rtrim( number_format( $tpct, 1 ), '0' ), '.' ) . '%' ) . '</label>';
            }
            echo '</div><span class="spbwc-setting-row__hint">' . esc_html__( 'Discount applied to this company.', 'storelly-product-builder-for-woocommerce' ) . '</span></div>';

            echo '<div class="spbwc-setting-row"><label class="spbwc-setting-row__label" for="spbwc-seats">' . esc_html__( 'Team seats', 'storelly-product-builder-for-woocommerce' ) . '</label>';
            echo '<input class="spbwc-input" name="seats" id="spbwc-seats" type="number" min="1" value="' . esc_attr( SPBWC_Company::default_seats() ) . '" style="max-width:120px" /></div>';

            echo '<div class="spbwc-setting-row"><label class="spbwc-setting-row__label" for="spbwc-terms">' . esc_html__( 'Payment terms', 'storelly-product-builder-for-woocommerce' ) . '</label><select class="spbwc-input" name="payment_terms" id="spbwc-terms" style="max-width:220px">';
            foreach ( self::payment_terms() as $slug => $label ) {
                echo '<option value="' . esc_attr( $slug ) . '">' . esc_html( $label ) . '</option>';
            }
            echo '</select><span class="spbwc-setting-row__hint">' . esc_html__( 'Display label only; WooCommerce + your gateway handle payment.', 'storelly-product-builder-for-woocommerce' ) . '</span></div>';

            echo '<div class="spbwc-setting-row"><span class="spbwc-setting-row__label">' . esc_html__( 'Activation', 'storelly-product-builder-for-woocommerce' ) . '</span>';
            echo '<label><input type="checkbox" name="activate" value="1" checked /> ' . esc_html__( 'Activate immediately', 'storelly-product-builder-for-woocommerce' ) . '</label><span class="spbwc-setting-row__hint">' . esc_html__( 'Uncheck to leave pending approval.', 'storelly-product-builder-for-woocommerce' ) . '</span></div>';

            echo '</div></div>';
            echo '<div class="spbwc-block__foot"><button type="submit" class="spbwc-cta-btn spbwc-cta-btn--solid"><span class="dashicons dashicons-yes" aria-hidden="true"></span> ' . esc_html__( 'Create company', 'storelly-product-builder-for-woocommerce' ) . '</button> ';
            echo '<a href="' . esc_url( self::page_url() ) . '" class="spbwc-cta-btn spbwc-cta-btn--ghost">' . esc_html__( 'Cancel', 'storelly-product-builder-for-woocommerce' ) . '</a></div></div>';
            echo '</form>';
        }

        /* ── Screen: detail ───────────────────────────────────────── */

        protected function render_detail( $company_id ) {
            $status  = SPBWC_Company::get_status( $company_id );
            $owner   = get_userdata( get_post_field( 'post_author', $company_id ) );
            $store   = SPBWC_Company::store_url( $company_id );
            $members = SPBWC_Company::get_members( $company_id );
            $seats   = SPBWC_Company::get_seats( $company_id );
            $nonce   = wp_create_nonce( 'spbwc_b2b_' . $company_id );
            $tier    = (string) get_post_meta( $company_id, SPBWC_Company::META_TIER, true );
            $tier_lbl = ( '' !== $tier && class_exists( 'SPBWC_B2B_Pricing' ) ) ? SPBWC_B2B_Pricing::tier_label( $tier ) : __( 'No tier', 'storelly-product-builder-for-woocommerce' );
            $rules    = class_exists( 'SPBWC_B2B_Price_Rules' ) ? SPBWC_B2B_Price_Rules::get_rules_for_company( $company_id ) : array();

            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only tab routing.
            $active = isset( $_GET['detail_tab'] ) ? sanitize_key( wp_unslash( $_GET['detail_tab'] ) ) : 'overview';

            // Hero actions: back + view store + status action.
            $actions = '<a class="spbwc-cta-btn spbwc-cta-btn--ghost spbwc-cta-btn--sm" href="' . esc_url( self::page_url() ) . '"><span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span> ' . esc_html__( 'Companies', 'storelly-product-builder-for-woocommerce' ) . '</a>';
            if ( '' !== $store ) {
                $actions .= ' <a class="spbwc-cta-btn spbwc-cta-btn--ghost" href="' . esc_url( $store ) . '" target="_blank" rel="noopener"><span class="dashicons dashicons-store" aria-hidden="true"></span> ' . esc_html__( 'Brand Store ↗', 'storelly-product-builder-for-woocommerce' ) . '</a>';
            }
            if ( SPBWC_Company::STATUS_PENDING === $status ) {
                $actions .= $this->action_button( $company_id, 'approve', __( 'Approve & activate', 'storelly-product-builder-for-woocommerce' ), 'solid', $nonce );
            } elseif ( SPBWC_Company::STATUS_ACTIVE === $status ) {
                $actions .= $this->action_button( $company_id, 'suspend', __( 'Suspend', 'storelly-product-builder-for-woocommerce' ), 'ghost', $nonce );
            } elseif ( SPBWC_Company::STATUS_SUSPENDED === $status ) {
                $actions .= $this->action_button( $company_id, 'reactivate', __( 'Reactivate', 'storelly-product-builder-for-woocommerce' ), 'solid', $nonce );
            }
            $this->render_hero(
                get_the_title( $company_id ),
                $owner ? ( __( 'Owner:', 'storelly-product-builder-for-woocommerce' ) . ' ' . $owner->user_email ) : '',
                $actions,
                'store',
                self::status_pill( $status )
            );

            // KPIs.
            $kpis = array(
                array( 'icon' => 'groups',     'tone' => 'info', 'value' => count( $members ) . ' / ' . $seats, 'label' => __( 'Team members', 'storelly-product-builder-for-woocommerce' ) ),
                array( 'icon' => 'tag',        'tone' => 'ok',   'value' => $tier_lbl, 'label' => __( 'Pricing tier', 'storelly-product-builder-for-woocommerce' ) ),
                array( 'icon' => 'cart',       'tone' => 'info', 'value' => number_format_i18n( count( $rules ) ), 'label' => __( 'Priced products', 'storelly-product-builder-for-woocommerce' ) ),
                array( 'icon' => 'info',       'tone' => 'warn', 'value' => SPBWC_Company::statuses()[ $status ], 'label' => __( 'Status', 'storelly-product-builder-for-woocommerce' ) ),
            );
            echo '<div class="spbwc-q-kpis">';
            foreach ( $kpis as $c ) {
                echo '<div class="spbwc-q-kpi spbwc-q-kpi--' . esc_attr( $c['tone'] ) . '">';
                echo '<span class="spbwc-q-kpi__icon dashicons dashicons-' . esc_attr( $c['icon'] ) . '" aria-hidden="true"></span>';
                echo '<span class="spbwc-q-kpi__value">' . esc_html( $c['value'] ) . '</span>';
                echo '<span class="spbwc-q-kpi__label">' . esc_html( $c['label'] ) . '</span></div>';
            }
            echo '</div>';

            // Tabs nav (server-side).
            $tabs = array(
                'overview' => __( 'Overview', 'storelly-product-builder-for-woocommerce' ),
                'members'  => __( 'Members', 'storelly-product-builder-for-woocommerce' ),
                'pricing'  => __( 'Pricing & products', 'storelly-product-builder-for-woocommerce' ),
                'credit'   => __( 'Account credit', 'storelly-product-builder-for-woocommerce' ),
                'activity' => __( 'Activity', 'storelly-product-builder-for-woocommerce' ),
            );
            echo '<nav class="spbwc-tabs">';
            foreach ( $tabs as $slug => $label ) {
                $cls = ( $active === $slug ) ? ' is-active' : '';
                echo '<a class="spbwc-tab' . $cls . '" href="' . esc_url( self::page_url( array( 'company' => $company_id, 'detail_tab' => $slug ) ) ) . '">' . esc_html( $label ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static literal.
                if ( 'members' === $slug ) {
                    echo ' <span class="count">' . esc_html( count( $members ) ) . '</span>';
                } elseif ( 'pricing' === $slug && ! empty( $rules ) ) {
                    echo ' <span class="count">' . esc_html( count( $rules ) ) . '</span>';
                }
                echo '</a>';
            }
            echo '</nav>';

            switch ( $active ) {
                case 'members':
                    $this->render_members_tab( $company_id, $members, $seats );
                    break;
                case 'pricing':
                    $this->render_price_rules( $company_id, $nonce );
                    break;
                case 'credit':
                    $this->render_credit_tab( $company_id, $nonce );
                    break;
                case 'activity':
                    $this->render_activity_tab( $company_id );
                    break;
                default:
                    $this->render_overview_tab( $company_id, $nonce, $seats );
            }
        }

        /** Overview tab: settings + profile snapshot. */
        protected function render_overview_tab( $company_id, $nonce, $seats ) {
            $terms        = (string) get_post_meta( $company_id, SPBWC_Company::META_PAYMENT_TERMS, true );
            $thresh       = (float) get_post_meta( $company_id, SPBWC_Company::META_APPROVAL_THRESHOLD, true );
            $climit       = (float) get_post_meta( $company_id, SPBWC_Company::META_CREDIT_LIMIT, true );
            $current_tier = (string) get_post_meta( $company_id, SPBWC_Company::META_TIER, true );
            $tiers        = class_exists( 'SPBWC_B2B_Pricing' ) ? SPBWC_B2B_Pricing::get_tiers() : array();
            $profile      = (array) get_post_meta( $company_id, SPBWC_Company::META_PROFILE, true );

            echo '<form method="post" action="' . esc_url( self::page_url( array( 'company' => $company_id ) ) ) . '">';
            echo '<input type="hidden" name="spbwc_b2b_do" value="save" /><input type="hidden" name="company" value="' . esc_attr( $company_id ) . '" /><input type="hidden" name="_spbwc_b2b_nonce" value="' . esc_attr( $nonce ) . '" />';
            echo '<div class="spbwc-block"><div class="spbwc-block__head"><h3 class="spbwc-block__title">' . esc_html__( 'Account settings', 'storelly-product-builder-for-woocommerce' ) . '</h3></div><div class="spbwc-block__body"><div class="spbwc-setting-rows">';

            echo '<div class="spbwc-setting-row"><label class="spbwc-setting-row__label">' . esc_html__( 'Pricing tier', 'storelly-product-builder-for-woocommerce' ) . '</label><select class="spbwc-input" name="tier" style="max-width:260px"><option value="">' . esc_html__( '— No tier —', 'storelly-product-builder-for-woocommerce' ) . '</option>';
            foreach ( $tiers as $tslug => $t ) {
                $tlabel = isset( $t['label'] ) ? $t['label'] : $tslug;
                $tpct   = isset( $t['discount_pct'] ) ? (float) $t['discount_pct'] : 0;
                echo '<option value="' . esc_attr( $tslug ) . '"' . selected( $current_tier, $tslug, false ) . '>' . esc_html( $tlabel . ' (' . rtrim( rtrim( number_format( $tpct, 1 ), '0' ), '.' ) . '%)' ) . '</option>';
            }
            echo '</select>';
            $hint = empty( $tiers ) && class_exists( 'SPBWC_B2B_Pricing_Admin' )
                ? '<span class="spbwc-setting-row__hint"><a href="' . esc_url( SPBWC_B2B_Pricing_Admin::page_url() ) . '">' . esc_html__( 'Create a tier first', 'storelly-product-builder-for-woocommerce' ) . '</a></span>'
                : '<span class="spbwc-setting-row__hint">' . esc_html__( 'Discount applied to this company.', 'storelly-product-builder-for-woocommerce' ) . '</span>';
            echo $hint . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.

            echo '<div class="spbwc-setting-row"><label class="spbwc-setting-row__label">' . esc_html__( 'Team seats', 'storelly-product-builder-for-woocommerce' ) . '</label><input class="spbwc-input" type="number" name="seats" min="1" value="' . esc_attr( $seats ) . '" style="max-width:120px" /></div>';
            echo '<div class="spbwc-setting-row"><label class="spbwc-setting-row__label">' . esc_html__( 'Approval threshold', 'storelly-product-builder-for-woocommerce' ) . '</label><input class="spbwc-input" type="number" name="approval_threshold" min="0" step="0.01" value="' . esc_attr( $thresh ) . '" style="max-width:160px" /><span class="spbwc-setting-row__hint">' . esc_html( get_woocommerce_currency_symbol() ) . ' · ' . esc_html__( 'orders above this need approval.', 'storelly-product-builder-for-woocommerce' ) . '</span></div>';
            echo '<div class="spbwc-setting-row"><label class="spbwc-setting-row__label">' . esc_html__( 'Payment terms', 'storelly-product-builder-for-woocommerce' ) . '</label><select class="spbwc-input" name="payment_terms" style="max-width:220px">';
            foreach ( self::payment_terms() as $slug => $label ) {
                echo '<option value="' . esc_attr( $slug ) . '"' . selected( $terms, $slug, false ) . '>' . esc_html( $label ) . '</option>';
            }
            echo '</select></div>';
            echo '<div class="spbwc-setting-row"><label class="spbwc-setting-row__label">' . esc_html__( 'Credit limit', 'storelly-product-builder-for-woocommerce' ) . '</label><input class="spbwc-input" type="number" name="credit_limit" min="0" step="0.01" value="' . esc_attr( $climit ) . '" style="max-width:160px" /><span class="spbwc-setting-row__hint">' . esc_html( get_woocommerce_currency_symbol() ) . ' · ' . esc_html__( 'net-terms ceiling. 0 = prepaid wallet only.', 'storelly-product-builder-for-woocommerce' ) . '</span></div>';
            echo '</div></div><div class="spbwc-block__foot"><button type="submit" class="spbwc-cta-btn spbwc-cta-btn--solid">' . esc_html__( 'Save changes', 'storelly-product-builder-for-woocommerce' ) . '</button></div></div>';
            echo '</form>';

            // Profile snapshot.
            echo '<div class="spbwc-block"><div class="spbwc-block__head"><h3 class="spbwc-block__title">' . esc_html__( 'Company profile', 'storelly-product-builder-for-woocommerce' ) . '</h3>';
            echo '<span class="spbwc-pill spbwc-pill--' . ( SPBWC_Company::profile_is_complete( $company_id ) ? 'success">' . esc_html__( 'Complete', 'storelly-product-builder-for-woocommerce' ) : 'warn">' . esc_html__( 'Incomplete', 'storelly-product-builder-for-woocommerce' ) ) . '</span>';
            echo '</div><div class="spbwc-block__body"><div class="spbwc-q-recap">';
            $rows = array(
                __( 'Legal name', 'storelly-product-builder-for-woocommerce' )  => isset( $profile['legal_name'] ) ? $profile['legal_name'] : '',
                __( 'Industry', 'storelly-product-builder-for-woocommerce' )    => isset( $profile['industry'] ) ? $profile['industry'] : '',
                __( 'Tax / VAT ID', 'storelly-product-builder-for-woocommerce' ) => isset( $profile['tax_id'] ) ? $profile['tax_id'] : '',
            );
            foreach ( $rows as $label => $val ) {
                echo '<span class="spbwc-q-recap__label">' . esc_html( $label ) . '</span><span class="spbwc-q-recap__value">' . esc_html( '' !== $val ? $val : '—' ) . '</span>';
            }
            echo '</div><p class="description">' . esc_html__( 'The company owner edits the full Brand Store profile from My Account.', 'storelly-product-builder-for-woocommerce' ) . '</p></div></div>';
        }

        /** Members tab: rich roster list. */
        protected function render_members_tab( $company_id, $members, $seats ) {
            $roles    = SPBWC_Company::roles();
            $owner_id = (int) get_post_field( 'post_author', $company_id );
            echo '<div class="spbwc-block"><div class="spbwc-block__head"><h3 class="spbwc-block__title">' . esc_html__( 'Members', 'storelly-product-builder-for-woocommerce' ) . ' (' . esc_html( count( $members ) . ' / ' . $seats ) . ')</h3></div><div class="spbwc-block__body spbwc-block__body--flush"><ul class="spbwc-list">';
            foreach ( $members as $m ) {
                $role = SPBWC_Company::get_user_role( $m->ID );
                $lim  = SPBWC_Company::get_order_limit( $m->ID );
                echo '<li class="spbwc-list__item">' . self::avatar( $m ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- avatar escapes.
                echo '<div class="spbwc-list__item-grow"><strong>' . esc_html( $m->display_name ) . ( (int) $m->ID === $owner_id ? ' <span class="spbwc-role-chip spbwc-role-chip--owner">' . esc_html__( 'Owner', 'storelly-product-builder-for-woocommerce' ) . '</span>' : '' ) . '</strong><br /><span class="spbwc-muted-sm">' . esc_html( $m->user_email ) . '</span></div>';
                echo '<span class="spbwc-role-chip spbwc-role-chip--' . esc_attr( $role ) . '">' . esc_html( isset( $roles[ $role ] ) ? $roles[ $role ] : $role ) . '</span>';
                echo '<span class="spbwc-muted-sm">' . esc_html( $lim > 0 ? get_woocommerce_currency_symbol() . number_format( $lim, 0 ) . '/order' : __( 'No limit', 'storelly-product-builder-for-woocommerce' ) ) . '</span>';
                echo '</li>';
            }
            echo '</ul></div></div>';
            echo '<p class="description">' . esc_html__( 'Team invitations, roles and spending limits are managed by the company owner in My Account → Team.', 'storelly-product-builder-for-woocommerce' ) . '</p>';
        }

        /** Account-credit tab: balance, statement, and top-up / payment / adjustment forms. */
        protected function render_credit_tab( $company_id, $nonce ) {
            if ( ! class_exists( 'SPBWC_B2B_Ledger' ) ) {
                echo '<p class="description">' . esc_html__( 'Ledger engine unavailable.', 'storelly-product-builder-for-woocommerce' ) . '</p>';
                return;
            }
            $limit       = class_exists( 'SPBWC_B2B_Credit' ) ? SPBWC_B2B_Credit::credit_limit( $company_id ) : (float) get_post_meta( $company_id, SPBWC_Company::META_CREDIT_LIMIT, true );
            $balance     = SPBWC_B2B_Ledger::get_balance( $company_id );
            $wallet      = SPBWC_B2B_Ledger::get_wallet( $company_id );
            $outstanding = SPBWC_B2B_Ledger::get_outstanding( $company_id );
            $available   = SPBWC_B2B_Ledger::get_available_credit( $company_id, $limit );

            // KPI strip.
            $cards = array(
                array( 'tone' => 'ok',   'value' => wc_price( $available ),   'label' => __( 'Available credit', 'storelly-product-builder-for-woocommerce' ) ),
                array( 'tone' => 'info', 'value' => wc_price( $wallet ),      'label' => __( 'Wallet balance', 'storelly-product-builder-for-woocommerce' ) ),
                array( 'tone' => 'warn', 'value' => wc_price( $outstanding ), 'label' => __( 'Outstanding', 'storelly-product-builder-for-woocommerce' ) ),
                array( 'tone' => 'info', 'value' => wc_price( $limit ),       'label' => __( 'Credit limit', 'storelly-product-builder-for-woocommerce' ) ),
            );
            echo '<div class="spbwc-q-kpis">';
            foreach ( $cards as $c ) {
                echo '<div class="spbwc-q-kpi spbwc-q-kpi--' . esc_attr( $c['tone'] ) . '"><span class="spbwc-q-kpi__value">' . wp_kses_post( $c['value'] ) . '</span><span class="spbwc-q-kpi__label">' . esc_html( $c['label'] ) . '</span></div>';
            }
            echo '</div>';

            // Action forms: top-up, record payment, adjustment.
            $sym = get_woocommerce_currency_symbol();
            echo '<div class="spbwc-block"><div class="spbwc-block__head"><h3 class="spbwc-block__title">' . esc_html__( 'Record a transaction', 'storelly-product-builder-for-woocommerce' ) . '</h3></div><div class="spbwc-block__body">';

            $form = function ( $do, $button, $hint ) use ( $company_id, $nonce, $sym ) {
                echo '<form method="post" class="spbwc-b2b-bind" action="' . esc_url( self::page_url( array( 'company' => $company_id, 'detail_tab' => 'credit' ) ) ) . '" style="margin-bottom:12px">';
                echo '<input type="hidden" name="spbwc_b2b_do" value="' . esc_attr( $do ) . '" /><input type="hidden" name="company" value="' . esc_attr( $company_id ) . '" /><input type="hidden" name="_spbwc_b2b_nonce" value="' . esc_attr( $nonce ) . '" />';
                echo '<label>' . esc_html__( 'Amount', 'storelly-product-builder-for-woocommerce' ) . ' (' . esc_html( $sym ) . ')<input class="spbwc-input" type="number" name="amount" min="0" step="0.01" required style="width:130px" /></label>';
                if ( 'credit_adjust' === $do ) {
                    echo '<label>' . esc_html__( 'Direction', 'storelly-product-builder-for-woocommerce' ) . '<select class="spbwc-input" name="direction" style="width:130px"><option value="credit">' . esc_html__( 'Credit (+)', 'storelly-product-builder-for-woocommerce' ) . '</option><option value="debit">' . esc_html__( 'Debit (−)', 'storelly-product-builder-for-woocommerce' ) . '</option></select></label>';
                }
                echo '<label>' . esc_html__( 'Note', 'storelly-product-builder-for-woocommerce' ) . '<input class="spbwc-input" type="text" name="note" style="width:240px" /></label>';
                echo '<button type="submit" class="spbwc-cta-btn spbwc-cta-btn--solid">' . esc_html( $button ) . '</button>';
                echo '<span class="spbwc-setting-row__hint" style="flex-basis:100%">' . esc_html( $hint ) . '</span>';
                echo '</form>';
            };
            $form( 'credit_topup', __( 'Add funds (top-up)', 'storelly-product-builder-for-woocommerce' ), __( 'Adds prepaid funds to the company wallet.', 'storelly-product-builder-for-woocommerce' ) );
            $form( 'credit_payment', __( 'Record payment', 'storelly-product-builder-for-woocommerce' ), __( 'Settles outstanding debt (net terms).', 'storelly-product-builder-for-woocommerce' ) );
            $form( 'credit_adjust', __( 'Post adjustment', 'storelly-product-builder-for-woocommerce' ), __( 'Manual correction in either direction.', 'storelly-product-builder-for-woocommerce' ) );
            echo '</div></div>';

            // Statement.
            $rows = SPBWC_B2B_Ledger::get_statement( $company_id, 100 );
            echo '<div class="spbwc-block"><div class="spbwc-block__head"><h3 class="spbwc-block__title">' . esc_html__( 'Statement', 'storelly-product-builder-for-woocommerce' ) . '</h3></div><div class="spbwc-block__body">';
            echo '<table class="spbwc-admin-table"><thead><tr><th>' . esc_html__( 'Date', 'storelly-product-builder-for-woocommerce' ) . '</th><th>' . esc_html__( 'Type', 'storelly-product-builder-for-woocommerce' ) . '</th><th>' . esc_html__( 'Reference', 'storelly-product-builder-for-woocommerce' ) . '</th><th>' . esc_html__( 'Note', 'storelly-product-builder-for-woocommerce' ) . '</th><th style="text-align:right">' . esc_html__( 'Debit', 'storelly-product-builder-for-woocommerce' ) . '</th><th style="text-align:right">' . esc_html__( 'Credit', 'storelly-product-builder-for-woocommerce' ) . '</th></tr></thead><tbody>';
            if ( empty( $rows ) ) {
                echo '<tr><td colspan="6" style="color:var(--nbd-st-text-mute)">' . esc_html__( 'No transactions yet.', 'storelly-product-builder-for-woocommerce' ) . '</td></tr>';
            }
            foreach ( $rows as $r ) {
                $ref = ( 'order' === $r->ref_type && $r->ref_id ) ? '#' . (int) $r->ref_id : '—';
                echo '<tr>';
                echo '<td>' . esc_html( mysql2date( get_option( 'date_format' ), $r->effective_date ) ) . '</td>';
                echo '<td>' . esc_html( SPBWC_B2B_Ledger::txn_label( $r->txn_type ) ) . '</td>';
                echo '<td>' . esc_html( $ref ) . '</td>';
                echo '<td>' . esc_html( (string) $r->note ) . '</td>';
                echo '<td style="text-align:right">' . ( (float) $r->debit > 0 ? wp_kses_post( wc_price( $r->debit ) ) : '' ) . '</td>';
                echo '<td style="text-align:right">' . ( (float) $r->credit > 0 ? wp_kses_post( wc_price( $r->credit ) ) : '' ) . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table></div></div>';
        }

        /** Activity tab: company timeline. */
        protected function render_activity_tab( $company_id ) {
            $events = SPBWC_Company::get_timeline( $company_id );
            echo '<div class="spbwc-block"><div class="spbwc-block__head"><h3 class="spbwc-block__title">' . esc_html__( 'Activity', 'storelly-product-builder-for-woocommerce' ) . '</h3></div><div class="spbwc-block__body">';
            if ( empty( $events ) ) {
                echo '<p class="description">' . esc_html__( 'No activity recorded yet.', 'storelly-product-builder-for-woocommerce' ) . '</p>';
            } else {
                echo '<div class="spbwc-q-timeline">';
                foreach ( array_reverse( $events ) as $ev ) {
                    echo '<div class="spbwc-q-timeline__item"><span class="spbwc-q-timeline__dot"></span>';
                    echo '<div class="spbwc-q-timeline__body">' . esc_html( $ev->comment_content ) . '<span class="spbwc-q-timeline__time"> · ' . esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $ev->comment_date ) ) . '</span></div></div>';
                }
                echo '</div>';
            }
            echo '</div></div>';
        }

        /* ── Per-company product pricing (M4) ─────────────────────── */

        /**
         * @param int    $company_id Company.
         * @param string $nonce      Action nonce.
         */
        protected function render_price_rules( $company_id, $nonce ) {
            if ( ! class_exists( 'SPBWC_B2B_Price_Rules' ) ) {
                return;
            }
            $rules    = SPBWC_B2B_Price_Rules::get_rules_for_company( $company_id );
            $tier_pct = class_exists( 'SPBWC_B2B_Pricing' ) ? SPBWC_B2B_Pricing::tier_pct_for_company( $company_id ) : 0;
            $sym      = get_woocommerce_currency_symbol();

            echo '<div class="spbwc-block"><div class="spbwc-block__head"><h3 class="spbwc-block__title">' . esc_html__( 'Per-company product pricing', 'storelly-product-builder-for-woocommerce' ) . '</h3></div><div class="spbwc-block__body">';
            echo '<p class="description" style="margin-top:0">' . esc_html(
                sprintf(
                    /* translators: %s: tier discount percent. */
                    __( 'Bind products to this company at a custom price — these override the tier baseline (%s%% off). Bound products are added to the Brand Store.', 'storelly-product-builder-for-woocommerce' ),
                    rtrim( rtrim( number_format( (float) $tier_pct, 1 ), '0' ), '.' )
                )
            ) . '</p>';

            echo '<table class="spbwc-admin-table"><thead><tr>';
            echo '<th>' . esc_html__( 'Product', 'storelly-product-builder-for-woocommerce' ) . '</th>';
            echo '<th>' . esc_html__( 'Base price', 'storelly-product-builder-for-woocommerce' ) . '</th>';
            echo '<th>' . esc_html__( 'Override', 'storelly-product-builder-for-woocommerce' ) . '</th>';
            echo '<th>' . esc_html__( 'Effective', 'storelly-product-builder-for-woocommerce' ) . '</th>';
            echo '<th>' . esc_html__( 'Min qty', 'storelly-product-builder-for-woocommerce' ) . '</th>';
            echo '<th>' . esc_html__( 'Valid until', 'storelly-product-builder-for-woocommerce' ) . '</th>';
            echo '<th></th></tr></thead><tbody>';

            if ( empty( $rules ) ) {
                echo '<tr><td colspan="7" style="color:var(--nbd-st-text-mute)">' . esc_html__( 'No bound products yet — add one below.', 'storelly-product-builder-for-woocommerce' ) . '</td></tr>';
            }
            foreach ( $rules as $rule ) {
                $product = wc_get_product( $rule->product_id );
                $base    = $product ? (float) $product->get_price() : 0;
                if ( SPBWC_B2B_Price_Rules::TYPE_FIXED === $rule->override_type ) {
                    $override = $sym . number_format( (float) $rule->value, 2 ) . ' ' . esc_html__( 'fixed', 'storelly-product-builder-for-woocommerce' );
                    $eff      = (float) $rule->value;
                } else {
                    $override = rtrim( rtrim( number_format( (float) $rule->value, 1 ), '0' ), '.' ) . '% ' . esc_html__( 'off', 'storelly-product-builder-for-woocommerce' );
                    $eff      = $base * ( ( 100 - (float) $rule->value ) / 100 );
                }
                echo '<tr>';
                echo '<td>' . esc_html( $product ? $product->get_name() : '#' . (int) $rule->product_id ) . '</td>';
                echo '<td>' . esc_html( $sym . number_format( $base, 2 ) ) . '</td>';
                echo '<td>' . esc_html( $override ) . '</td>';
                echo '<td><strong>' . esc_html( $sym . number_format( $eff, 2 ) ) . '</strong></td>';
                echo '<td>' . esc_html( (int) $rule->min_qty > 0 ? (int) $rule->min_qty : '—' ) . '</td>';
                echo '<td>' . esc_html( ! empty( $rule->valid_until ) ? mysql2date( get_option( 'date_format' ), $rule->valid_until ) : '—' ) . '</td>';
                echo '<td><form method="post" action="' . esc_url( self::page_url( array( 'company' => $company_id ) ) ) . '">';
                echo '<input type="hidden" name="spbwc_b2b_do" value="unbind_price" />';
                echo '<input type="hidden" name="company" value="' . esc_attr( $company_id ) . '" />';
                echo '<input type="hidden" name="product_id" value="' . esc_attr( $rule->product_id ) . '" />';
                echo '<input type="hidden" name="_spbwc_b2b_nonce" value="' . esc_attr( $nonce ) . '" />';
                echo '<button type="submit" class="spbwc-cta-btn spbwc-cta-btn--ghost spbwc-cta-btn--sm" style="color:var(--nbd-color-danger);border-color:var(--nbd-color-danger)">' . esc_html__( 'Remove', 'storelly-product-builder-for-woocommerce' ) . '</button>';
                echo '</form></td></tr>';
            }
            echo '</tbody></table>';

            // Bind form (labelled).
            echo '<form method="post" class="spbwc-b2b-bind" action="' . esc_url( self::page_url( array( 'company' => $company_id ) ) ) . '">';
            echo '<input type="hidden" name="spbwc_b2b_do" value="bind_price" /><input type="hidden" name="company" value="' . esc_attr( $company_id ) . '" /><input type="hidden" name="_spbwc_b2b_nonce" value="' . esc_attr( $nonce ) . '" />';
            echo '<label>' . esc_html__( 'Product ID', 'storelly-product-builder-for-woocommerce' ) . '<input class="spbwc-input" type="number" name="product_id" min="1" required style="width:110px" /></label>';
            echo '<label>' . esc_html__( 'Type', 'storelly-product-builder-for-woocommerce' ) . '<select class="spbwc-input" name="override_type" style="width:120px"><option value="pct">' . esc_html__( '% off', 'storelly-product-builder-for-woocommerce' ) . '</option><option value="fixed">' . esc_html__( 'Fixed price', 'storelly-product-builder-for-woocommerce' ) . '</option></select></label>';
            echo '<label>' . esc_html__( 'Value', 'storelly-product-builder-for-woocommerce' ) . '<input class="spbwc-input" type="number" name="value" min="0" step="0.01" required style="width:110px" /></label>';
            echo '<label>' . esc_html__( 'Min qty', 'storelly-product-builder-for-woocommerce' ) . '<input class="spbwc-input" type="number" name="min_qty" min="0" style="width:90px" /></label>';
            echo '<label>' . esc_html__( 'Valid until', 'storelly-product-builder-for-woocommerce' ) . '<input class="spbwc-input" type="date" name="valid_until" /></label>';
            echo '<button type="submit" class="spbwc-cta-btn spbwc-cta-btn--solid"><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span> ' . esc_html__( 'Bind', 'storelly-product-builder-for-woocommerce' ) . '</button>';
            echo '</form></div></div>';
        }

        /**
         * @param int    $company_id Company.
         * @param string $do         Action key.
         * @param string $label      Button label.
         * @param string $class      Extra button class.
         * @param string $nonce      Nonce.
         * @return string Form HTML.
         */
        protected function action_button( $company_id, $do, $label, $variant, $nonce ) {
            $cls   = 'spbwc-cta-btn spbwc-cta-btn--' . ( 'ghost' === $variant ? 'ghost' : 'solid' );
            $html  = '<form method="post" style="display:inline-block;" action="' . esc_url( self::page_url( array( 'company' => $company_id ) ) ) . '">';
            $html .= '<input type="hidden" name="spbwc_b2b_do" value="' . esc_attr( $do ) . '" />';
            $html .= '<input type="hidden" name="company" value="' . esc_attr( $company_id ) . '" />';
            $html .= '<input type="hidden" name="_spbwc_b2b_nonce" value="' . esc_attr( $nonce ) . '" />';
            $html .= '<button type="submit" class="' . esc_attr( $cls ) . '">' . esc_html( $label ) . '</button>';
            $html .= '</form>';
            return $html;
        }

        /* ── Shared ───────────────────────────────────────────────── */

        /**
         * Payment-term labels (display only in v1 — see spec §10 OQ1).
         *
         * @return array<string,string>
         */
        public static function payment_terms() {
            return array(
                'prepaid' => __( 'Prepaid (credit card)', 'storelly-product-builder-for-woocommerce' ),
                'net15'   => __( 'Net 15', 'storelly-product-builder-for-woocommerce' ),
                'net30'   => __( 'Net 30', 'storelly-product-builder-for-woocommerce' ),
                'custom'  => __( 'Custom', 'storelly-product-builder-for-woocommerce' ),
            );
        }

        protected function print_notice() {
            // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Display-only flash.
            $code = $this->notice;
            if ( '' === $code && isset( $_GET['spbwc_msg'] ) ) {
                $code = sanitize_key( wp_unslash( $_GET['spbwc_msg'] ) );
            }
            // phpcs:enable WordPress.Security.NonceVerification.Recommended
            if ( '' === $code ) {
                return;
            }
            $map = array(
                'created'       => array( 'updated', __( 'Company created.', 'storelly-product-builder-for-woocommerce' ) ),
                'approved'      => array( 'updated', __( 'Company activated.', 'storelly-product-builder-for-woocommerce' ) ),
                'suspended'     => array( 'updated', __( 'Company suspended.', 'storelly-product-builder-for-woocommerce' ) ),
                'saved'         => array( 'updated', __( 'Settings saved.', 'storelly-product-builder-for-woocommerce' ) ),
                'upgrade_error' => array( 'error', __( 'Could not create the company. The customer may already belong to one.', 'storelly-product-builder-for-woocommerce' ) ),
                'error'         => array( 'error', __( 'Something went wrong. Please try again.', 'storelly-product-builder-for-woocommerce' ) ),
            );
            if ( ! isset( $map[ $code ] ) ) {
                return;
            }
            echo '<div class="notice notice-' . esc_attr( $map[ $code ][0] === 'error' ? 'error' : 'success' ) . ' is-dismissible"><p>' . esc_html( $map[ $code ][1] ) . '</p></div>';
        }
    }
}
