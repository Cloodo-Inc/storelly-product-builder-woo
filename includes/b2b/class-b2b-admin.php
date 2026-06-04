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
         * @param string $status Status slug.
         * @return int
         */
        public static function count_by_status( $status ) {
            $ids = get_posts(
                array(
                    'post_type'   => SPBWC_Company::POST_TYPE,
                    'post_status' => 'publish',
                    'numberposts' => -1,
                    'fields'      => 'ids',
                    'meta_key'    => SPBWC_Company::META_STATUS, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
                    'meta_value'  => $status,                    // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
                )
            );
            return count( $ids );
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

            echo '<div class="wrap spbwc-b2b-admin">';
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
                    $tier      = isset( $_POST['tier'] ) ? sanitize_key( wp_unslash( $_POST['tier'] ) ) : '';
                    update_post_meta( $company_id, SPBWC_Company::META_SEATS, $seats );
                    update_post_meta( $company_id, SPBWC_Company::META_APPROVAL_THRESHOLD, $threshold );
                    update_post_meta( $company_id, SPBWC_Company::META_PAYMENT_TERMS, $terms );
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
            $status = ( isset( $_POST['activate'] ) && '1' === $_POST['activate'] ) ? SPBWC_Company::STATUS_ACTIVE : SPBWC_Company::STATUS_PENDING;

            $result = SPBWC_Company::create(
                $name,
                $user_id,
                array(
                    'seats'         => $seats,
                    'payment_terms' => $terms,
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

        protected function render_list() {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter.
            $tab = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : 'all';

            echo '<h1 class="wp-heading-inline">' . esc_html__( 'B2B Companies', 'storelly-product-builder-for-woocommerce' ) . '</h1>';
            echo '<hr class="wp-header-end" />';
            echo '<p class="description">' . esc_html__( 'Upgrade a customer to B2B from Users → hover a row → "Upgrade to B2B".', 'storelly-product-builder-for-woocommerce' ) . '</p>';

            // Status tabs with counts.
            $tabs = array_merge( array( 'all' => __( 'All', 'storelly-product-builder-for-woocommerce' ) ), SPBWC_Company::statuses() );
            echo '<ul class="subsubsub">';
            $i = 0;
            foreach ( $tabs as $slug => $label ) {
                $count = ( 'all' === $slug ) ? self::count_all() : self::count_by_status( $slug );
                $url   = self::page_url( 'all' === $slug ? array() : array( 'status' => $slug ) );
                $cls   = ( $tab === $slug ) ? ' class="current"' : '';
                echo '<li>' . ( $i ? ' | ' : '' ) . '<a href="' . esc_url( $url ) . '"' . $cls . '>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $cls is a static literal.
                    . esc_html( $label ) . ' <span class="count">(' . esc_html( number_format_i18n( $count ) ) . ')</span></a></li>';
                $i++;
            }
            echo '</ul>';

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

            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead><tr>';
            echo '<th>' . esc_html__( 'Company', 'storelly-product-builder-for-woocommerce' ) . '</th>';
            echo '<th>' . esc_html__( 'Owner', 'storelly-product-builder-for-woocommerce' ) . '</th>';
            echo '<th>' . esc_html__( 'Status', 'storelly-product-builder-for-woocommerce' ) . '</th>';
            echo '<th>' . esc_html__( 'Team', 'storelly-product-builder-for-woocommerce' ) . '</th>';
            echo '<th>' . esc_html__( 'Store', 'storelly-product-builder-for-woocommerce' ) . '</th>';
            echo '</tr></thead><tbody>';

            if ( empty( $companies ) ) {
                echo '<tr><td colspan="5">' . esc_html__( 'No companies yet.', 'storelly-product-builder-for-woocommerce' ) . '</td></tr>';
            }
            foreach ( $companies as $company ) {
                $owner   = get_userdata( $company->post_author );
                $status  = SPBWC_Company::get_status( $company->ID );
                $members = SPBWC_Company::count_members( $company->ID );
                $seats   = SPBWC_Company::get_seats( $company->ID );
                $store   = SPBWC_Company::store_url( $company->ID );
                echo '<tr>';
                echo '<td><a href="' . esc_url( self::page_url( array( 'company' => $company->ID ) ) ) . '"><strong>' . esc_html( get_the_title( $company ) ) . '</strong></a></td>';
                echo '<td>' . esc_html( $owner ? $owner->user_email : '—' ) . '</td>';
                echo '<td>' . self::status_pill( $status ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pill is escaped internally.
                echo '<td>' . esc_html( $members . ' / ' . $seats ) . '</td>';
                echo '<td><a href="' . esc_url( $store ) . '" target="_blank" rel="noopener">' . esc_html__( 'View →', 'storelly-product-builder-for-woocommerce' ) . '</a></td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }

        protected static function count_all() {
            $ids = get_posts(
                array(
                    'post_type'   => SPBWC_Company::POST_TYPE,
                    'post_status' => 'publish',
                    'numberposts' => -1,
                    'fields'      => 'ids',
                )
            );
            return count( $ids );
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

            echo '<h1>' . esc_html__( 'Upgrade to B2B', 'storelly-product-builder-for-woocommerce' ) . '</h1>';
            echo '<p class="description">'
                . sprintf(
                    /* translators: %s: customer email. */
                    esc_html__( 'Convert %s into a B2B company account. They become the company owner and can edit their Brand Store, invite a team, and access B2B pricing.', 'storelly-product-builder-for-woocommerce' ),
                    '<strong>' . esc_html( $user->user_email ) . '</strong>'
                )
                . '</p>';

            echo '<form method="post" action="' . esc_url( self::page_url() ) . '" class="spbwc-b2b-form">';
            echo '<input type="hidden" name="spbwc_b2b_do" value="upgrade" />';
            echo '<input type="hidden" name="user" value="' . esc_attr( $user_id ) . '" />';
            wp_nonce_field( 'spbwc_b2b_upgrade_' . $user_id, '_spbwc_b2b_nonce' );

            echo '<table class="form-table" role="presentation"><tbody>';
            echo '<tr><th><label for="spbwc-company-name">' . esc_html__( 'Company name', 'storelly-product-builder-for-woocommerce' ) . ' *</label></th>';
            echo '<td><input name="company_name" id="spbwc-company-name" type="text" class="regular-text" required value="' . esc_attr( $prefill ) . '" /></td></tr>';

            echo '<tr><th><label for="spbwc-seats">' . esc_html__( 'Team seats', 'storelly-product-builder-for-woocommerce' ) . '</label></th>';
            echo '<td><input name="seats" id="spbwc-seats" type="number" min="1" value="' . esc_attr( SPBWC_Company::default_seats() ) . '" class="small-text" /></td></tr>';

            echo '<tr><th><label for="spbwc-terms">' . esc_html__( 'Payment terms', 'storelly-product-builder-for-woocommerce' ) . '</label></th><td>';
            echo '<select name="payment_terms" id="spbwc-terms">';
            foreach ( self::payment_terms() as $slug => $label ) {
                echo '<option value="' . esc_attr( $slug ) . '">' . esc_html( $label ) . '</option>';
            }
            echo '</select> <span class="description">' . esc_html__( 'Label shown to the company; WooCommerce + your gateway handle actual payment.', 'storelly-product-builder-for-woocommerce' ) . '</span></td></tr>';

            echo '<tr><th>' . esc_html__( 'Activate now', 'storelly-product-builder-for-woocommerce' ) . '</th>';
            echo '<td><label><input type="checkbox" name="activate" value="1" checked /> ' . esc_html__( 'Activate immediately (uncheck to leave pending approval)', 'storelly-product-builder-for-woocommerce' ) . '</label></td></tr>';
            echo '</tbody></table>';

            echo '<p class="submit"><button type="submit" class="button button-primary">' . esc_html__( 'Create company', 'storelly-product-builder-for-woocommerce' ) . '</button> ';
            echo '<a href="' . esc_url( self::page_url() ) . '" class="button">' . esc_html__( 'Cancel', 'storelly-product-builder-for-woocommerce' ) . '</a></p>';
            echo '</form>';
        }

        /* ── Screen: detail ───────────────────────────────────────── */

        protected function render_detail( $company_id ) {
            $status  = SPBWC_Company::get_status( $company_id );
            $owner   = get_userdata( get_post_field( 'post_author', $company_id ) );
            $store   = SPBWC_Company::store_url( $company_id );
            $members = SPBWC_Company::get_members( $company_id );
            $seats   = SPBWC_Company::get_seats( $company_id );
            $terms   = (string) get_post_meta( $company_id, SPBWC_Company::META_PAYMENT_TERMS, true );
            $thresh  = (float) get_post_meta( $company_id, SPBWC_Company::META_APPROVAL_THRESHOLD, true );
            $nonce   = wp_create_nonce( 'spbwc_b2b_' . $company_id );

            echo '<p><a href="' . esc_url( self::page_url() ) . '">&larr; ' . esc_html__( 'All companies', 'storelly-product-builder-for-woocommerce' ) . '</a></p>';
            echo '<h1>' . esc_html( get_the_title( $company_id ) ) . ' ' . self::status_pill( $status ) . '</h1>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pill escaped internally.
            echo '<p class="description">' . esc_html__( 'Owner:', 'storelly-product-builder-for-woocommerce' ) . ' ' . esc_html( $owner ? $owner->user_email : '—' )
                . ' &middot; <a href="' . esc_url( $store ) . '" target="_blank" rel="noopener">' . esc_html( $store ) . '</a></p>';

            // Status actions.
            echo '<div class="spbwc-b2b-actions" style="margin:12px 0;">';
            if ( SPBWC_Company::STATUS_PENDING === $status ) {
                echo $this->action_button( $company_id, 'approve', __( 'Approve & activate', 'storelly-product-builder-for-woocommerce' ), 'button-primary', $nonce ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- builder escapes.
            }
            if ( SPBWC_Company::STATUS_ACTIVE === $status ) {
                echo $this->action_button( $company_id, 'suspend', __( 'Suspend', 'storelly-product-builder-for-woocommerce' ), '', $nonce ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            }
            if ( SPBWC_Company::STATUS_SUSPENDED === $status ) {
                echo $this->action_button( $company_id, 'reactivate', __( 'Reactivate', 'storelly-product-builder-for-woocommerce' ), 'button-primary', $nonce ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            }
            echo '</div>';

            // Settings form.
            echo '<h2>' . esc_html__( 'Settings', 'storelly-product-builder-for-woocommerce' ) . '</h2>';
            echo '<form method="post" action="' . esc_url( self::page_url( array( 'company' => $company_id ) ) ) . '">';
            echo '<input type="hidden" name="spbwc_b2b_do" value="save" />';
            echo '<input type="hidden" name="company" value="' . esc_attr( $company_id ) . '" />';
            echo '<input type="hidden" name="_spbwc_b2b_nonce" value="' . esc_attr( $nonce ) . '" />';
            echo '<table class="form-table" role="presentation"><tbody>';
            // Pricing tier (M2).
            $current_tier = (string) get_post_meta( $company_id, SPBWC_Company::META_TIER, true );
            $tiers        = class_exists( 'SPBWC_B2B_Pricing' ) ? SPBWC_B2B_Pricing::get_tiers() : array();
            echo '<tr><th>' . esc_html__( 'Pricing tier', 'storelly-product-builder-for-woocommerce' ) . '</th><td><select name="tier">';
            echo '<option value="">' . esc_html__( '— No tier —', 'storelly-product-builder-for-woocommerce' ) . '</option>';
            foreach ( $tiers as $tslug => $tier ) {
                $tlabel = isset( $tier['label'] ) ? $tier['label'] : $tslug;
                $tpct   = isset( $tier['discount_pct'] ) ? (float) $tier['discount_pct'] : 0;
                echo '<option value="' . esc_attr( $tslug ) . '"' . selected( $current_tier, $tslug, false ) . '>'
                    . esc_html( $tlabel . ' (' . rtrim( rtrim( number_format( $tpct, 1 ), '0' ), '.' ) . '%)' ) . '</option>';
            }
            echo '</select>';
            if ( empty( $tiers ) ) {
                echo ' <span class="description">' . wp_kses_post(
                    sprintf(
                        /* translators: %s: B2B Pricing page URL. */
                        __( 'No tiers yet. <a href="%s">Create a pricing tier</a> first.', 'storelly-product-builder-for-woocommerce' ),
                        esc_url( class_exists( 'SPBWC_B2B_Pricing_Admin' ) ? SPBWC_B2B_Pricing_Admin::page_url() : '' )
                    )
                ) . '</span>';
            }
            echo '</td></tr>';
            echo '<tr><th>' . esc_html__( 'Team seats', 'storelly-product-builder-for-woocommerce' ) . '</th>';
            echo '<td><input type="number" name="seats" min="1" value="' . esc_attr( $seats ) . '" class="small-text" /></td></tr>';
            echo '<tr><th>' . esc_html__( 'Approval threshold', 'storelly-product-builder-for-woocommerce' ) . '</th>';
            echo '<td>' . esc_html( get_woocommerce_currency_symbol() ) . ' <input type="number" name="approval_threshold" min="0" step="0.01" value="' . esc_attr( $thresh ) . '" class="small-text" /> <span class="description">' . esc_html__( 'Orders above this need approval (Team Procurement, M5).', 'storelly-product-builder-for-woocommerce' ) . '</span></td></tr>';
            echo '<tr><th>' . esc_html__( 'Payment terms', 'storelly-product-builder-for-woocommerce' ) . '</th><td><select name="payment_terms">';
            foreach ( self::payment_terms() as $slug => $label ) {
                echo '<option value="' . esc_attr( $slug ) . '"' . selected( $terms, $slug, false ) . '>' . esc_html( $label ) . '</option>';
            }
            echo '</select></td></tr>';
            echo '</tbody></table>';
            echo '<p class="submit"><button type="submit" class="button button-primary">' . esc_html__( 'Save changes', 'storelly-product-builder-for-woocommerce' ) . '</button></p>';
            echo '</form>';

            // Members.
            echo '<h2>' . esc_html__( 'Members', 'storelly-product-builder-for-woocommerce' ) . ' (' . esc_html( count( $members ) . ' / ' . $seats ) . ')</h2>';
            echo '<table class="wp-list-table widefat fixed striped"><thead><tr>';
            echo '<th>' . esc_html__( 'Member', 'storelly-product-builder-for-woocommerce' ) . '</th>';
            echo '<th>' . esc_html__( 'Email', 'storelly-product-builder-for-woocommerce' ) . '</th>';
            echo '<th>' . esc_html__( 'Role', 'storelly-product-builder-for-woocommerce' ) . '</th>';
            echo '</tr></thead><tbody>';
            $roles = SPBWC_Company::roles();
            foreach ( $members as $m ) {
                $role = SPBWC_Company::get_user_role( $m->ID );
                echo '<tr><td>' . esc_html( $m->display_name ) . '</td><td>' . esc_html( $m->user_email ) . '</td>';
                echo '<td>' . esc_html( isset( $roles[ $role ] ) ? $roles[ $role ] : $role ) . '</td></tr>';
            }
            echo '</tbody></table>';
            echo '<p class="description">' . esc_html__( 'Team invitations and roles are managed by the company owner in My Account (Team Procurement).', 'storelly-product-builder-for-woocommerce' ) . '</p>';

            $this->render_price_rules( $company_id, $nonce );
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

            echo '<h2>' . esc_html__( 'Per-company product pricing', 'storelly-product-builder-for-woocommerce' ) . '</h2>';
            echo '<p class="description">' . esc_html(
                sprintf(
                    /* translators: %s: tier discount percent. */
                    __( 'Bind specific products to this company at a custom price. These override the tier baseline (%s%% off). Bound products are added to the Brand Store.', 'storelly-product-builder-for-woocommerce' ),
                    rtrim( rtrim( number_format( (float) $tier_pct, 1 ), '0' ), '.' )
                )
            ) . '</p>';

            echo '<table class="wp-list-table widefat fixed striped"><thead><tr>';
            echo '<th>' . esc_html__( 'Product', 'storelly-product-builder-for-woocommerce' ) . '</th>';
            echo '<th>' . esc_html__( 'Base price', 'storelly-product-builder-for-woocommerce' ) . '</th>';
            echo '<th>' . esc_html__( 'Override', 'storelly-product-builder-for-woocommerce' ) . '</th>';
            echo '<th>' . esc_html__( 'Effective', 'storelly-product-builder-for-woocommerce' ) . '</th>';
            echo '<th>' . esc_html__( 'Min qty', 'storelly-product-builder-for-woocommerce' ) . '</th>';
            echo '<th>' . esc_html__( 'Valid until', 'storelly-product-builder-for-woocommerce' ) . '</th>';
            echo '<th></th></tr></thead><tbody>';

            if ( empty( $rules ) ) {
                echo '<tr><td colspan="7">' . esc_html__( 'No bound products yet.', 'storelly-product-builder-for-woocommerce' ) . '</td></tr>';
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
                echo '<button type="submit" class="button-link delete">' . esc_html__( 'Remove', 'storelly-product-builder-for-woocommerce' ) . '</button>';
                echo '</form></td></tr>';
            }
            echo '</tbody></table>';

            // Bind form.
            echo '<form method="post" class="spbwc-b2b-bind" action="' . esc_url( self::page_url( array( 'company' => $company_id ) ) ) . '" style="margin-top:10px;">';
            echo '<input type="hidden" name="spbwc_b2b_do" value="bind_price" />';
            echo '<input type="hidden" name="company" value="' . esc_attr( $company_id ) . '" />';
            echo '<input type="hidden" name="_spbwc_b2b_nonce" value="' . esc_attr( $nonce ) . '" />';
            echo '<input type="number" name="product_id" min="1" placeholder="' . esc_attr__( 'Product ID', 'storelly-product-builder-for-woocommerce' ) . '" required class="small-text" /> ';
            echo '<select name="override_type">';
            echo '<option value="pct">' . esc_html__( '% off', 'storelly-product-builder-for-woocommerce' ) . '</option>';
            echo '<option value="fixed">' . esc_html__( 'Fixed price', 'storelly-product-builder-for-woocommerce' ) . '</option>';
            echo '</select> ';
            echo '<input type="number" name="value" min="0" step="0.01" placeholder="' . esc_attr__( 'Value', 'storelly-product-builder-for-woocommerce' ) . '" required class="small-text" /> ';
            echo '<input type="number" name="min_qty" min="0" placeholder="' . esc_attr__( 'Min qty', 'storelly-product-builder-for-woocommerce' ) . '" class="small-text" /> ';
            echo '<input type="date" name="valid_until" /> ';
            echo '<button type="submit" class="button">' . esc_html__( 'Bind product', 'storelly-product-builder-for-woocommerce' ) . '</button>';
            echo ' <span class="description">' . esc_html__( 'Tip: the product ID is shown in the Products list URL.', 'storelly-product-builder-for-woocommerce' ) . '</span>';
            echo '</form>';
        }

        /**
         * @param int    $company_id Company.
         * @param string $do         Action key.
         * @param string $label      Button label.
         * @param string $class      Extra button class.
         * @param string $nonce      Nonce.
         * @return string Form HTML.
         */
        protected function action_button( $company_id, $do, $label, $class, $nonce ) {
            $html  = '<form method="post" style="display:inline-block;margin-right:8px;" action="' . esc_url( self::page_url( array( 'company' => $company_id ) ) ) . '">';
            $html .= '<input type="hidden" name="spbwc_b2b_do" value="' . esc_attr( $do ) . '" />';
            $html .= '<input type="hidden" name="company" value="' . esc_attr( $company_id ) . '" />';
            $html .= '<input type="hidden" name="_spbwc_b2b_nonce" value="' . esc_attr( $nonce ) . '" />';
            $html .= '<button type="submit" class="button ' . esc_attr( $class ) . '">' . esc_html( $label ) . '</button>';
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
