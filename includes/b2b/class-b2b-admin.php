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
            add_action( 'wp_ajax_spbwc_b2b_search_customers', array( $this, 'ajax_search_customers' ) );
            add_action( 'wp_ajax_spbwc_b2b_credit_txn', array( $this, 'ajax_credit_txn' ) );
            add_action( 'wp_ajax_spbwc_b2b_save_profile', array( $this, 'ajax_save_profile' ) );
        }

        /**
         * AJAX: search WooCommerce customers NOT already in a company, for the
         * "Upgrade a customer" picker on the hub.
         */
        public function ajax_search_customers() {
            check_ajax_referer( 'spbwc_b2b_picker', 'nonce' );
            if ( ! current_user_can( self::CAPABILITY ) ) {
                wp_send_json_error( array( 'message' => esc_html__( 'Permission denied.', 'storelly-product-builder-for-woocommerce' ) ), 403 );
            }
            $term = isset( $_POST['term'] ) ? sanitize_text_field( wp_unslash( $_POST['term'] ) ) : '';
            $q    = new WP_User_Query(
                array(
                    'search'         => '*' . esc_attr( $term ) . '*',
                    'search_columns' => array( 'user_login', 'user_email', 'user_nicename', 'display_name' ),
                    'number'         => 12,
                    'fields'         => array( 'ID', 'display_name', 'user_email' ),
                    // Exclude users already linked to a company.
                    'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
                        array(
                            'key'     => SPBWC_Company::USER_COMPANY_ID,
                            'compare' => 'NOT EXISTS',
                        ),
                    ),
                )
            );
            $out = array();
            foreach ( $q->get_results() as $u ) {
                $out[] = array(
                    'id'    => (int) $u->ID,
                    'name'  => $u->display_name,
                    'email' => $u->user_email,
                    'url'   => self::page_url( array( 'action' => 'upgrade', 'user' => (int) $u->ID ) ),
                );
            }
            wp_send_json_success( array( 'results' => $out ) );
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
                    $terms_cst = isset( $_POST['payment_terms_custom'] ) ? sanitize_text_field( wp_unslash( $_POST['payment_terms_custom'] ) ) : '';
                    $climit    = isset( $_POST['credit_limit'] ) ? max( 0, (float) wp_unslash( $_POST['credit_limit'] ) ) : 0;
                    $rebate    = isset( $_POST['rebate_pct'] ) ? min( 100, max( 0, (float) wp_unslash( $_POST['rebate_pct'] ) ) ) : 0;
                    $tier      = isset( $_POST['tier'] ) ? sanitize_key( wp_unslash( $_POST['tier'] ) ) : '';

                    // Snapshot old values so we can record exactly what changed.
                    $old_seats  = (int) get_post_meta( $company_id, SPBWC_Company::META_SEATS, true );
                    $old_thresh = (float) get_post_meta( $company_id, SPBWC_Company::META_APPROVAL_THRESHOLD, true );
                    $old_terms  = (string) get_post_meta( $company_id, SPBWC_Company::META_PAYMENT_TERMS, true );
                    $old_climit = (float) get_post_meta( $company_id, SPBWC_Company::META_CREDIT_LIMIT, true );
                    $old_rebate = (float) get_post_meta( $company_id, SPBWC_Company::META_REBATE_PCT, true );
                    $old_tier   = (string) get_post_meta( $company_id, SPBWC_Company::META_TIER, true );

                    update_post_meta( $company_id, SPBWC_Company::META_SEATS, $seats );
                    update_post_meta( $company_id, SPBWC_Company::META_APPROVAL_THRESHOLD, $threshold );
                    update_post_meta( $company_id, SPBWC_Company::META_PAYMENT_TERMS, $terms );
                    // Free-text label for the "Custom" term; cleared when not on custom.
                    if ( 'custom' === $terms && '' !== $terms_cst ) {
                        update_post_meta( $company_id, SPBWC_Company::META_PAYMENT_TERMS_CUSTOM, $terms_cst );
                    } else {
                        delete_post_meta( $company_id, SPBWC_Company::META_PAYMENT_TERMS_CUSTOM );
                    }
                    update_post_meta( $company_id, SPBWC_Company::META_CREDIT_LIMIT, $climit );
                    update_post_meta( $company_id, SPBWC_Company::META_REBATE_PCT, $rebate );

                    // Timeline: tier change gets its own line; other settings roll up
                    // into one "settings updated" entry listing the changed fields.
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
                    $changed = array();
                    if ( $seats !== $old_seats ) {
                        $changed[] = __( 'team seats', 'storelly-product-builder-for-woocommerce' );
                    }
                    if ( abs( $threshold - $old_thresh ) > 0.001 ) {
                        $changed[] = __( 'approval threshold', 'storelly-product-builder-for-woocommerce' );
                    }
                    if ( $terms !== $old_terms ) {
                        $changed[] = __( 'payment terms', 'storelly-product-builder-for-woocommerce' );
                    }
                    if ( abs( $climit - $old_climit ) > 0.001 ) {
                        $changed[] = __( 'credit limit', 'storelly-product-builder-for-woocommerce' );
                    }
                    if ( abs( $rebate - $old_rebate ) > 0.001 ) {
                        $changed[] = __( 'volume rebate', 'storelly-product-builder-for-woocommerce' );
                    }
                    if ( ! empty( $changed ) ) {
                        SPBWC_Company::add_timeline_event(
                            $company_id,
                            sprintf(
                                /* translators: %s: comma-separated list of changed setting names. */
                                __( 'Account settings updated: %s.', 'storelly-product-builder-for-woocommerce' ),
                                implode( ', ', $changed )
                            )
                        );
                    }
                    $this->notice = 'saved';
                    break;
                case 'save_profile':
                    $this->handle_save_profile( $company_id );
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
                        $prod = wc_get_product( $pid );
                        SPBWC_Company::add_timeline_event(
                            $company_id,
                            sprintf(
                                /* translators: %s: product name. */
                                __( 'Custom price bound for %s.', 'storelly-product-builder-for-woocommerce' ),
                                $prod ? $prod->get_name() : ( '#' . $pid )
                            )
                        );
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
                        $prod = wc_get_product( $pid );
                        SPBWC_Company::add_timeline_event(
                            $company_id,
                            sprintf(
                                /* translators: %s: product name. */
                                __( 'Custom price removed for %s.', 'storelly-product-builder-for-woocommerce' ),
                                $prod ? $prod->get_name() : ( '#' . $pid )
                            )
                        );
                        $this->notice = 'saved';
                    }
                    break;
                case 'credit_topup':
                case 'credit_payment':
                case 'credit_adjust':
                    $amount = isset( $_POST['amount'] ) ? (float) wp_unslash( $_POST['amount'] ) : 0;
                    $note   = isset( $_POST['note'] ) ? sanitize_text_field( wp_unslash( $_POST['note'] ) ) : '';
                    $dir    = isset( $_POST['direction'] ) ? sanitize_key( wp_unslash( $_POST['direction'] ) ) : 'credit';
                    $this->notice = self::process_credit_txn( $company_id, $do, $amount, $note, $dir ) ? 'saved' : 'error';
                    break;
            }
        }

        /**
         * Apply a manual credit transaction (top-up / payment / adjustment) to the
         * company ledger and record a timeline entry. Shared by the POST handler and
         * the AJAX endpoint so both paths behave identically.
         *
         * @param int    $company_id Company.
         * @param string $do         credit_topup | credit_payment | credit_adjust.
         * @param float  $amount     Positive amount.
         * @param string $note       Optional note.
         * @param string $dir        Adjustment direction: credit | debit.
         * @return bool Whether the transaction was applied.
         */
        protected static function process_credit_txn( $company_id, $do, $amount, $note, $dir = 'credit' ) {
            if ( ! class_exists( 'SPBWC_B2B_Ledger' ) || $amount <= 0 ) {
                return false;
            }
            // Format amounts with wc_price (respects the store currency, decimals and
            // symbol position) so timeline entries read correctly in any locale.
            $money = wp_strip_all_tags( wc_price( $amount ) );
            if ( 'credit_topup' === $do ) {
                SPBWC_B2B_Ledger::post_topup( $company_id, $amount, array( 'note' => $note, 'ref_type' => 'manual' ) );
                /* translators: %s: formatted amount in the store currency. */
                $msg = sprintf( __( 'Wallet top-up of %s recorded.', 'storelly-product-builder-for-woocommerce' ), $money );
            } elseif ( 'credit_payment' === $do ) {
                SPBWC_B2B_Ledger::post_payment( $company_id, $amount, array( 'note' => $note, 'ref_type' => 'manual' ) );
                /* translators: %s: formatted amount in the store currency. */
                $msg = sprintf( __( 'Payment of %s recorded against net terms.', 'storelly-product-builder-for-woocommerce' ), $money );
            } elseif ( 'credit_adjust' === $do ) {
                // Adjustment: signed. Positive = credit, negative = debit.
                $entry = ( 'debit' === $dir ) ? array( 'debit' => $amount ) : array( 'credit' => $amount );
                SPBWC_B2B_Ledger::record( $company_id, array_merge( $entry, array(
                    'txn_type' => SPBWC_B2B_Ledger::TXN_ADJUSTMENT,
                    'ref_type' => 'manual',
                    'note'     => $note,
                ) ) );
                $sign = ( 'debit' === $dir ) ? '−' : '+';
                /* translators: 1: +/− sign, 2: formatted amount in the store currency. */
                $msg = sprintf( __( 'Manual adjustment %1$s%2$s posted.', 'storelly-product-builder-for-woocommerce' ), $sign, $money );
            } else {
                return false;
            }
            if ( '' !== $note ) {
                $msg .= ' — ' . $note;
            }
            SPBWC_Company::add_timeline_event( $company_id, $msg );
            return true;
        }

        /**
         * AJAX: apply a credit transaction and return refreshed balances + a freshly
         * rendered statement row, so the Account-credit tab updates without a reload.
         */
        public function ajax_credit_txn() {
            $company_id = isset( $_POST['company'] ) ? absint( wp_unslash( $_POST['company'] ) ) : 0;
            $nonce      = isset( $_POST['_spbwc_b2b_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_spbwc_b2b_nonce'] ) ) : '';
            if ( ! $company_id || ! wp_verify_nonce( $nonce, 'spbwc_b2b_' . $company_id ) ) {
                wp_send_json_error( array( 'message' => esc_html__( 'Security check failed — please reload.', 'storelly-product-builder-for-woocommerce' ) ), 400 );
            }
            if ( ! current_user_can( self::CAPABILITY ) ) {
                wp_send_json_error( array( 'message' => esc_html__( 'Permission denied.', 'storelly-product-builder-for-woocommerce' ) ), 403 );
            }
            $do     = isset( $_POST['spbwc_b2b_do'] ) ? sanitize_key( wp_unslash( $_POST['spbwc_b2b_do'] ) ) : '';
            $amount = isset( $_POST['amount'] ) ? (float) wp_unslash( $_POST['amount'] ) : 0;
            $note   = isset( $_POST['note'] ) ? sanitize_text_field( wp_unslash( $_POST['note'] ) ) : '';
            $dir    = isset( $_POST['direction'] ) ? sanitize_key( wp_unslash( $_POST['direction'] ) ) : 'credit';

            if ( ! in_array( $do, array( 'credit_topup', 'credit_payment', 'credit_adjust' ), true ) || $amount <= 0 ) {
                wp_send_json_error( array( 'message' => esc_html__( 'Enter an amount greater than zero.', 'storelly-product-builder-for-woocommerce' ) ), 400 );
            }
            if ( ! self::process_credit_txn( $company_id, $do, $amount, $note, $dir ) ) {
                wp_send_json_error( array( 'message' => esc_html__( 'The transaction could not be recorded.', 'storelly-product-builder-for-woocommerce' ) ), 500 );
            }

            $limit       = class_exists( 'SPBWC_B2B_Credit' ) ? SPBWC_B2B_Credit::credit_limit( $company_id ) : (float) get_post_meta( $company_id, SPBWC_Company::META_CREDIT_LIMIT, true );
            $available   = SPBWC_B2B_Ledger::get_available_credit( $company_id, $limit );
            $wallet      = SPBWC_B2B_Ledger::get_wallet( $company_id );
            $outstanding = SPBWC_B2B_Ledger::get_outstanding( $company_id );

            wp_send_json_success( array(
                'kpis'    => array(
                    'available'   => wc_price( $available ),
                    'wallet'      => wc_price( $wallet ),
                    'outstanding' => wc_price( $outstanding ),
                    'limit'       => wc_price( $limit ),
                ),
                'row'     => self::statement_row_html( $company_id ),
                'message' => esc_html__( 'Transaction recorded.', 'storelly-product-builder-for-woocommerce' ),
            ) );
        }

        /** Render the latest statement row as a <tr> (used to prepend after AJAX). */
        protected static function statement_row_html( $company_id ) {
            $rows = SPBWC_B2B_Ledger::get_statement( $company_id, 1 );
            if ( empty( $rows ) ) {
                return '';
            }
            $r   = $rows[0];
            $ref = ( 'order' === $r->ref_type && $r->ref_id ) ? '#' . (int) $r->ref_id : '—';
            $html  = '<tr>';
            $html .= '<td>' . esc_html( mysql2date( get_option( 'date_format' ), $r->effective_date ) ) . '</td>';
            $html .= '<td>' . esc_html( SPBWC_B2B_Ledger::txn_label( $r->txn_type ) ) . '</td>';
            $html .= '<td>' . esc_html( $ref ) . '</td>';
            $html .= '<td>' . esc_html( (string) $r->note ) . '</td>';
            $html .= '<td class="spbwc-stmt-amt spbwc-stmt-amt--debit">' . ( (float) $r->debit > 0 ? wp_kses_post( wc_price( $r->debit ) ) : '' ) . '</td>';
            $html .= '<td class="spbwc-stmt-amt spbwc-stmt-amt--credit">' . ( (float) $r->credit > 0 ? wp_kses_post( wc_price( $r->credit ) ) : '' ) . '</td>';
            $html .= '</tr>';
            return $html;
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

        /**
         * Persist the full Brand Store profile from the admin Company-profile tab.
         * Same meta keys (and sanitisation contract) as the owner's My-Account form,
         * so either side can edit. Nonce + capability are already verified by the
         * caller in maybe_handle_actions().
         *
         * @param int $company_id Company.
         */
        protected function handle_save_profile( $company_id ) {
            $get = function ( $key ) {
                return isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified by caller.
            };

            // Title + branding.
            $title = $get( 'company_name' );
            if ( '' !== $title ) {
                wp_update_post( array( 'ID' => $company_id, 'post_title' => $title ) );
            }
            update_post_meta( $company_id, SPBWC_Company::META_TAGLINE, $get( 'tagline' ) );
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified by caller.
            $desc = isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '';
            update_post_meta( $company_id, SPBWC_Company::META_DESCRIPTION, $desc );
            update_post_meta( $company_id, SPBWC_Company::META_BRAND_PRIMARY, SPBWC_Company::sanitize_hex( $get( 'brand_primary' ) ) );
            update_post_meta( $company_id, SPBWC_Company::META_BRAND_SECONDARY, SPBWC_Company::sanitize_hex( $get( 'brand_secondary' ) ) );

            // Corporate profile.
            update_post_meta( $company_id, SPBWC_Company::META_PROFILE, array(
                'legal_name' => $get( 'legal_name' ),
                'dba'        => $get( 'dba' ),
                'industry'   => $get( 'industry' ),
                'employees'  => $get( 'employees' ),
                'tax_id'     => $get( 'tax_id' ),
            ) );

            // Address.
            update_post_meta( $company_id, SPBWC_Company::META_ADDRESSES, array(
                'street'   => $get( 'addr_street' ),
                'city'     => $get( 'addr_city' ),
                'state'    => $get( 'addr_state' ),
                'postcode' => $get( 'addr_postcode' ),
                'country'  => $get( 'addr_country' ),
            ) );

            // Contact.
            update_post_meta( $company_id, SPBWC_Company::META_CONTACT, array(
                'name'    => $get( 'contact_name' ),
                'title'   => $get( 'contact_title' ),
                'email'   => sanitize_email( $get( 'contact_email' ) ),
                'phone'   => $get( 'contact_phone' ),
                'website' => esc_url_raw( $get( 'contact_website' ) ),
            ) );

            // Uploads (logo / banner).
            self::handle_profile_upload( $company_id, 'logo', SPBWC_Company::META_LOGO );
            self::handle_profile_upload( $company_id, 'banner', SPBWC_Company::META_BANNER );

            // Auto-promote incomplete → active once the profile is complete.
            if ( SPBWC_Company::STATUS_INCOMPLETE === SPBWC_Company::get_status( $company_id )
                && SPBWC_Company::profile_is_complete( $company_id ) ) {
                SPBWC_Company::set_status( $company_id, SPBWC_Company::STATUS_ACTIVE, __( 'Profile completed — company activated.', 'storelly-product-builder-for-woocommerce' ) );
            }

            SPBWC_Company::add_timeline_event( $company_id, __( 'Company profile / Brand Store updated by merchant.', 'storelly-product-builder-for-woocommerce' ) );
        }

        /**
         * Handle a single image upload into the media library and store its ID.
         *
         * @param int    $company_id Company.
         * @param string $field      $_FILES key.
         * @param string $meta_key   Where to store the attachment ID.
         */
        protected static function handle_profile_upload( $company_id, $field, $meta_key ) {
            if ( empty( $_FILES[ $field ]['name'] ) ) {
                return;
            }
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';

            $allowed = array( 'image/png', 'image/jpeg', 'image/svg+xml' );
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- WP core validates the upload; type checked below.
            $type = isset( $_FILES[ $field ]['type'] ) ? sanitize_text_field( wp_unslash( $_FILES[ $field ]['type'] ) ) : '';
            if ( ! in_array( $type, $allowed, true ) ) {
                return;
            }
            $attachment_id = media_handle_upload( $field, 0 );
            if ( ! is_wp_error( $attachment_id ) && $attachment_id ) {
                update_post_meta( $company_id, $meta_key, (int) $attachment_id );
            }
        }

        /**
         * AJAX: save the Brand Store profile (incl. logo/banner uploads via
         * FormData) and return refreshed completion state + preview markup, so the
         * Company-profile tab saves without a reload.
         */
        public function ajax_save_profile() {
            $company_id = isset( $_POST['company'] ) ? absint( wp_unslash( $_POST['company'] ) ) : 0;
            $nonce      = isset( $_POST['_spbwc_b2b_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_spbwc_b2b_nonce'] ) ) : '';
            if ( ! $company_id || ! wp_verify_nonce( $nonce, 'spbwc_b2b_' . $company_id ) ) {
                wp_send_json_error( array( 'message' => esc_html__( 'Security check failed — please reload.', 'storelly-product-builder-for-woocommerce' ) ), 400 );
            }
            if ( ! current_user_can( self::CAPABILITY ) ) {
                wp_send_json_error( array( 'message' => esc_html__( 'Permission denied.', 'storelly-product-builder-for-woocommerce' ) ), 403 );
            }
            if ( SPBWC_Company::POST_TYPE !== get_post_type( $company_id ) ) {
                wp_send_json_error( array( 'message' => esc_html__( 'Company not found.', 'storelly-product-builder-for-woocommerce' ) ), 404 );
            }

            $this->handle_save_profile( $company_id );

            $complete  = SPBWC_Company::profile_is_complete( $company_id );
            $logo_id   = (int) get_post_meta( $company_id, SPBWC_Company::META_LOGO, true );
            $banner_id = (int) get_post_meta( $company_id, SPBWC_Company::META_BANNER, true );
            $icon      = '<span class="spbwc-b2b-upload__icon dashicons dashicons-format-image" aria-hidden="true"></span>';

            wp_send_json_success( array(
                'complete' => $complete,
                'pill'     => '<span class="spbwc-pill js-spbwc-profile-pill spbwc-pill--' . ( $complete ? 'success' : 'warn' ) . '">'
                    . ( $complete ? esc_html__( 'Profile complete', 'storelly-product-builder-for-woocommerce' ) : esc_html__( 'Profile incomplete', 'storelly-product-builder-for-woocommerce' ) ) . '</span>',
                'logo'     => $logo_id ? wp_get_attachment_image( $logo_id, 'thumbnail', false, array( 'class' => 'spbwc-b2b-upload__preview' ) ) : $icon,
                'banner'   => $banner_id ? wp_get_attachment_image( $banner_id, 'medium', false, array( 'class' => 'spbwc-b2b-upload__preview' ) ) : $icon,
                'title'    => get_the_title( $company_id ),
                'message'  => esc_html__( 'Brand Store saved.', 'storelly-product-builder-for-woocommerce' ),
            ) );
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
            $terms_cst = isset( $_POST['payment_terms_custom'] ) ? sanitize_text_field( wp_unslash( $_POST['payment_terms_custom'] ) ) : '';
            $tier  = isset( $_POST['tier'] ) ? sanitize_key( wp_unslash( $_POST['tier'] ) ) : '';
            $status = ( isset( $_POST['activate'] ) && '1' === $_POST['activate'] ) ? SPBWC_Company::STATUS_ACTIVE : SPBWC_Company::STATUS_PENDING;

            $result = SPBWC_Company::create(
                $name,
                $user_id,
                array(
                    'seats'                => $seats,
                    'payment_terms'        => $terms,
                    'payment_terms_custom' => $terms_cst,
                    'tier'                 => $tier,
                    'status'               => $status,
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
            return '<span class="' . esc_attr( 'spbwc-meter' . $mod ) . '"><span class="spbwc-meter__fill" style="width:' . esc_attr( $pct ) . '%"></span></span>';
        }

        protected function render_list() {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter.
            $tab = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : 'all';

            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only search filter.
            $search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';

            $actions = '<button type="button" class="spbwc-cta-btn spbwc-cta-btn--solid js-spbwc-open-picker"><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span> ' . esc_html__( 'Upgrade a customer', 'storelly-product-builder-for-woocommerce' ) . '</button>';
            if ( class_exists( 'SPBWC_B2B_Pricing_Admin' ) ) {
                $actions .= ' <a class="spbwc-cta-btn spbwc-cta-btn--ghost" href="' . esc_url( SPBWC_B2B_Pricing_Admin::page_url() ) . '"><span class="dashicons dashicons-tag" aria-hidden="true"></span> ' . esc_html__( 'Manage tiers', 'storelly-product-builder-for-woocommerce' ) . '</a>';
            }
            $this->render_hero(
                __( 'B2B Companies', 'storelly-product-builder-for-woocommerce' ),
                __( 'Branded accounts, tier pricing and team procurement.', 'storelly-product-builder-for-woocommerce' ),
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
            echo '</ul>';
            // Search by company name.
            echo '<form method="get" class="spbwc-search-bar" style="margin-left:auto;">';
            echo '<input type="hidden" name="page" value="' . esc_attr( self::PAGE_SLUG ) . '" />';
            if ( 'all' !== $tab ) {
                echo '<input type="hidden" name="status" value="' . esc_attr( $tab ) . '" />';
            }
            echo '<span class="spbwc-search-bar__icon" aria-hidden="true"><span class="dashicons dashicons-search"></span></span>';
            echo '<input class="spbwc-search-bar__input" type="search" name="s" value="' . esc_attr( $search ) . '" placeholder="' . esc_attr__( 'Search companies…', 'storelly-product-builder-for-woocommerce' ) . '" />';
            echo '<button class="spbwc-search-bar__btn" type="submit">' . esc_html__( 'Search', 'storelly-product-builder-for-woocommerce' ) . '</button>';
            echo '</form>';
            echo '</div>';

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
            if ( '' !== $search ) {
                $args['s'] = $search;
            }
            $companies = get_posts( $args );

            if ( empty( $companies ) ) {
                $msg = '' !== $search
                    ? __( 'No companies match your search.', 'storelly-product-builder-for-woocommerce' )
                    : __( 'Upgrade a customer to create their first company account.', 'storelly-product-builder-for-woocommerce' );
                echo '<div class="spbwc-empty-state"><div class="spbwc-empty-state__icon"><span class="dashicons dashicons-groups" aria-hidden="true"></span></div>';
                echo '<p class="spbwc-empty-state__title">' . esc_html__( 'No B2B companies', 'storelly-product-builder-for-woocommerce' ) . '</p>';
                echo '<p class="spbwc-empty-state__text">' . esc_html( $msg ) . '</p>';
                echo '<button type="button" class="spbwc-cta-btn spbwc-cta-btn--solid js-spbwc-open-picker"><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span> ' . esc_html__( 'Upgrade a customer', 'storelly-product-builder-for-woocommerce' ) . '</button>';
                echo '</div></div>';
                $this->render_picker_modal();
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

                echo '<tr class="spbwc-row-link" data-href="' . esc_url( $detail ) . '">';
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
                echo '<td>' . ( '' !== $tier && class_exists( 'SPBWC_B2B_Pricing' ) ? '<span class="spbwc-role-chip spbwc-role-chip--admin">' . esc_html( SPBWC_B2B_Pricing::tier_label( $tier ) ) . '</span>' : '<span class="spbwc-role-chip spbwc-role-chip--viewer">' . esc_html__( 'No tier', 'storelly-product-builder-for-woocommerce' ) . '</span>' ) . '</td>';
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
            $this->render_picker_modal();
        }

        /** Customer-picker modal — search a customer and jump to the upgrade form. */
        protected function render_picker_modal() {
            echo '<div class="spbwc-modal" id="spbwc-b2b-picker" hidden>';
            echo '<div class="spbwc-modal__backdrop js-spbwc-close-picker"></div>';
            echo '<div class="spbwc-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="spbwc-b2b-picker-title">';
            echo '<div class="spbwc-modal__head"><h2 id="spbwc-b2b-picker-title">' . esc_html__( 'Upgrade a customer to B2B', 'storelly-product-builder-for-woocommerce' ) . '</h2>';
            echo '<button type="button" class="spbwc-modal__close js-spbwc-close-picker" aria-label="' . esc_attr__( 'Close', 'storelly-product-builder-for-woocommerce' ) . '">&times;</button></div>';
            echo '<div class="spbwc-modal__body">';
            echo '<div class="spbwc-search-bar"><span class="spbwc-search-bar__icon" aria-hidden="true"><span class="dashicons dashicons-search"></span></span>';
            echo '<input class="spbwc-search-bar__input js-spbwc-picker-input" type="search" placeholder="' . esc_attr__( 'Search by name or email…', 'storelly-product-builder-for-woocommerce' ) . '" autocomplete="off" /></div>';
            echo '<ul class="spbwc-picker__results js-spbwc-picker-results"></ul>';
            echo '<p class="spbwc-picker__hint description">' . esc_html__( 'Only customers not already in a company are shown.', 'storelly-product-builder-for-woocommerce' ) . '</p>';
            echo '</div></div></div>';
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

            echo '<div class="spbwc-setting-row"><label class="spbwc-setting-row__label" for="spbwc-terms">' . esc_html__( 'Payment terms', 'storelly-product-builder-for-woocommerce' ) . '</label><select class="spbwc-input js-spbwc-terms-select" name="payment_terms" id="spbwc-terms" style="max-width:220px">';
            foreach ( self::payment_terms() as $slug => $label ) {
                echo '<option value="' . esc_attr( $slug ) . '">' . esc_html( $label ) . '</option>';
            }
            echo '</select>';
            echo '<input class="spbwc-input js-spbwc-terms-custom" type="text" name="payment_terms_custom" value="" placeholder="' . esc_attr__( 'Custom label, e.g. Net 45', 'storelly-product-builder-for-woocommerce' ) . '" style="max-width:220px;margin-top:6px;display:none" />';
            echo '<span class="spbwc-setting-row__hint">' . esc_html__( 'Display label only; WooCommerce + your gateway handle payment.', 'storelly-product-builder-for-woocommerce' ) . '</span></div>';

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
            $active = isset( $_GET['detail_tab'] ) ? sanitize_key( wp_unslash( $_GET['detail_tab'] ) ) : 'profile';

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

            // Tabs. Every panel is rendered server-side; static/js/b2b-admin.js
            // shows the active one and switches instantly on click — no reload.
            // Without JS the CSS leaves all panels visible (graceful fallback).
            // `detail_tab` seeds the initial panel (used by post-save redirects).
            $tabs = array(
                'profile'  => __( 'Company profile', 'storelly-product-builder-for-woocommerce' ),
                'overview' => __( 'Overview', 'storelly-product-builder-for-woocommerce' ),
                'members'  => __( 'Members', 'storelly-product-builder-for-woocommerce' ),
                'pricing'  => __( 'Pricing & products', 'storelly-product-builder-for-woocommerce' ),
                'credit'   => __( 'Account credit', 'storelly-product-builder-for-woocommerce' ),
                'activity' => __( 'Activity', 'storelly-product-builder-for-woocommerce' ),
            );
            if ( ! isset( $tabs[ $active ] ) ) {
                $active = 'profile';
            }

            echo '<div class="spbwc-b2b-detail">';
            echo '<nav class="spbwc-tabs" role="tablist" aria-label="' . esc_attr__( 'Company sections', 'storelly-product-builder-for-woocommerce' ) . '">';
            foreach ( $tabs as $slug => $label ) {
                $on = ( $active === $slug );
                echo '<button type="button" class="spbwc-tab' . ( $on ? ' is-active' : '' ) . '"'
                    . ' data-tab="' . esc_attr( $slug ) . '" id="spbwc-b2b-tabbtn-' . esc_attr( $slug ) . '"'
                    . ' role="tab" aria-controls="spbwc-b2b-panel-' . esc_attr( $slug ) . '"'
                    . ' aria-selected="' . ( $on ? 'true' : 'false' ) . '" tabindex="' . ( $on ? '0' : '-1' ) . '">'
                    . esc_html( $label );
                if ( 'members' === $slug ) {
                    echo ' <span class="count">' . esc_html( count( $members ) ) . '</span>';
                } elseif ( 'pricing' === $slug && ! empty( $rules ) ) {
                    echo ' <span class="count">' . esc_html( count( $rules ) ) . '</span>';
                }
                echo '</button>';
            }
            echo '</nav>';

            $panels = array(
                'profile'  => function () use ( $company_id, $nonce ) {
                    $this->render_profile_tab( $company_id, $nonce );
                },
                'overview' => function () use ( $company_id, $nonce, $seats ) {
                    $this->render_overview_tab( $company_id, $nonce, $seats );
                },
                'members'  => function () use ( $company_id, $members, $seats ) {
                    $this->render_members_tab( $company_id, $members, $seats );
                },
                'pricing'  => function () use ( $company_id, $nonce ) {
                    $this->render_price_rules( $company_id, $nonce );
                },
                'credit'   => function () use ( $company_id, $nonce ) {
                    $this->render_credit_tab( $company_id, $nonce );
                },
                'activity' => function () use ( $company_id ) {
                    $this->render_activity_tab( $company_id );
                },
            );
            foreach ( $panels as $slug => $render ) {
                echo '<div class="spbwc-b2b-panel' . ( $active === $slug ? ' is-active' : '' ) . '"'
                    . ' id="spbwc-b2b-panel-' . esc_attr( $slug ) . '" role="tabpanel"'
                    . ' aria-labelledby="spbwc-b2b-tabbtn-' . esc_attr( $slug ) . '">';
                $render();
                echo '</div>';
            }
            echo '</div>';
        }

        /**
         * Company-profile tab: the full Brand Store editor, admin-side.
         *
         * Mirrors the owner's My-Account → Brand Store form (same meta keys), so a
         * WordPress administrator can edit every branding, corporate, address and
         * contact field — including logo / banner uploads — on the company's behalf.
         *
         * @param int    $company_id Company.
         * @param string $nonce      Action nonce (spbwc_b2b_<id>).
         */
        protected function render_profile_tab( $company_id, $nonce ) {
            $tagline   = (string) get_post_meta( $company_id, SPBWC_Company::META_TAGLINE, true );
            $desc      = (string) get_post_meta( $company_id, SPBWC_Company::META_DESCRIPTION, true );
            $primary   = (string) get_post_meta( $company_id, SPBWC_Company::META_BRAND_PRIMARY, true );
            $secondary = (string) get_post_meta( $company_id, SPBWC_Company::META_BRAND_SECONDARY, true );
            $logo_id   = (int) get_post_meta( $company_id, SPBWC_Company::META_LOGO, true );
            $banner_id = (int) get_post_meta( $company_id, SPBWC_Company::META_BANNER, true );
            $profile   = (array) get_post_meta( $company_id, SPBWC_Company::META_PROFILE, true );
            $address   = (array) get_post_meta( $company_id, SPBWC_Company::META_ADDRESSES, true );
            $contact   = (array) get_post_meta( $company_id, SPBWC_Company::META_CONTACT, true );
            $complete  = SPBWC_Company::profile_is_complete( $company_id );

            $g = function ( $arr, $key ) {
                return isset( $arr[ $key ] ) ? (string) $arr[ $key ] : '';
            };

            // Local field renderers (admin design tokens — reuse .spbwc-setting-row).
            $text = function ( $name, $label, $value, $hint = '', $required = false, $type = 'text' ) {
                echo '<div class="spbwc-setting-row"><label class="spbwc-setting-row__label" for="spbwc-pf-' . esc_attr( $name ) . '">' . esc_html( $label ) . ( $required ? ' <span class="spbwc-req">*</span>' : '' ) . '</label>';
                echo '<input class="spbwc-input" type="' . esc_attr( $type ) . '" id="spbwc-pf-' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '"' . ( $required ? ' required' : '' ) . ' />';
                if ( '' !== $hint ) {
                    echo '<span class="spbwc-setting-row__hint">' . esc_html( $hint ) . '</span>';
                }
                echo '</div>';
            };

            echo '<form method="post" enctype="multipart/form-data" class="spbwc-b2b-profile" action="' . esc_url( self::page_url( array( 'company' => $company_id, 'detail_tab' => 'profile' ) ) ) . '">';
            echo '<input type="hidden" name="spbwc_b2b_do" value="save_profile" /><input type="hidden" name="company" value="' . esc_attr( $company_id ) . '" /><input type="hidden" name="_spbwc_b2b_nonce" value="' . esc_attr( $nonce ) . '" />';

            /* ── Branding ─────────────────────────────────────────── */
            echo '<div class="spbwc-block"><div class="spbwc-block__head"><h3 class="spbwc-block__title"><span class="dashicons dashicons-art" aria-hidden="true"></span> ' . esc_html__( 'Branding', 'storelly-product-builder-for-woocommerce' ) . '</h3>';
            echo '<span class="spbwc-pill js-spbwc-profile-pill spbwc-pill--' . ( $complete ? 'success">' . esc_html__( 'Profile complete', 'storelly-product-builder-for-woocommerce' ) : 'warn">' . esc_html__( 'Profile incomplete', 'storelly-product-builder-for-woocommerce' ) ) . '</span></div>';
            echo '<div class="spbwc-block__body"><div class="spbwc-setting-rows">';
            $text( 'company_name', __( 'Store name', 'storelly-product-builder-for-woocommerce' ), get_the_title( $company_id ), __( 'Public name shown on the Brand Store and in the team account.', 'storelly-product-builder-for-woocommerce' ), true );
            $text( 'tagline', __( 'Tagline', 'storelly-product-builder-for-woocommerce' ), $tagline, __( 'A short one-line strapline under the store name.', 'storelly-product-builder-for-woocommerce' ) );
            // Uploads.
            echo '<div class="spbwc-setting-row"><label class="spbwc-setting-row__label">' . esc_html__( 'Logo & banner', 'storelly-product-builder-for-woocommerce' ) . '</label><div class="spbwc-b2b-uploads">';
            $this->profile_upload_field( 'logo', __( 'Logo', 'storelly-product-builder-for-woocommerce' ), __( 'Square · min 400×400 · PNG/SVG', 'storelly-product-builder-for-woocommerce' ), $logo_id, 'image/png,image/jpeg,image/svg+xml', 'thumbnail' );
            $this->profile_upload_field( 'banner', __( 'Banner', 'storelly-product-builder-for-woocommerce' ), __( '≈1920×400 · PNG/JPG', 'storelly-product-builder-for-woocommerce' ), $banner_id, 'image/png,image/jpeg', 'medium' );
            echo '</div></div>';
            // Colours.
            echo '<div class="spbwc-setting-row"><label class="spbwc-setting-row__label">' . esc_html__( 'Brand colours', 'storelly-product-builder-for-woocommerce' ) . '</label><div class="spbwc-b2b-colours">';
            $this->profile_color_field( 'brand_primary', __( 'Primary', 'storelly-product-builder-for-woocommerce' ), $primary );
            $this->profile_color_field( 'brand_secondary', __( 'Secondary', 'storelly-product-builder-for-woocommerce' ), $secondary );
            echo '</div><span class="spbwc-setting-row__hint">' . esc_html__( 'Used for the public store header gradient and accents.', 'storelly-product-builder-for-woocommerce' ) . '</span></div>';
            // Description.
            echo '<div class="spbwc-setting-row"><label class="spbwc-setting-row__label" for="spbwc-pf-description">' . esc_html__( 'Brand description', 'storelly-product-builder-for-woocommerce' ) . '</label><textarea class="spbwc-input" id="spbwc-pf-description" name="description" rows="3">' . esc_textarea( $desc ) . '</textarea><span class="spbwc-setting-row__hint">' . esc_html__( 'Short "about" paragraph shown on the public Brand Store.', 'storelly-product-builder-for-woocommerce' ) . '</span></div>';
            echo '</div></div></div>';

            /* ── Corporate profile ────────────────────────────────── */
            echo '<div class="spbwc-block"><div class="spbwc-block__head"><h3 class="spbwc-block__title"><span class="dashicons dashicons-building" aria-hidden="true"></span> ' . esc_html__( 'Corporate profile', 'storelly-product-builder-for-woocommerce' ) . '</h3>';
            echo '<p class="spbwc-block__subtitle">' . esc_html__( 'Legal identity used on quotes, invoices and approvals.', 'storelly-product-builder-for-woocommerce' ) . '</p></div>';
            echo '<div class="spbwc-block__body"><div class="spbwc-setting-rows">';
            $text( 'legal_name', __( 'Legal company name', 'storelly-product-builder-for-woocommerce' ), $g( $profile, 'legal_name' ), __( 'Registered company name — required to complete the profile.', 'storelly-product-builder-for-woocommerce' ), true );
            $text( 'dba', __( 'Trade / DBA name', 'storelly-product-builder-for-woocommerce' ), $g( $profile, 'dba' ) );
            $text( 'industry', __( 'Industry', 'storelly-product-builder-for-woocommerce' ), $g( $profile, 'industry' ) );
            $text( 'employees', __( 'Number of employees', 'storelly-product-builder-for-woocommerce' ), $g( $profile, 'employees' ) );
            $text( 'tax_id', __( 'VAT / Tax ID', 'storelly-product-builder-for-woocommerce' ), $g( $profile, 'tax_id' ), __( 'Shown on invoices where applicable.', 'storelly-product-builder-for-woocommerce' ) );
            echo '</div></div></div>';

            /* ── Address ──────────────────────────────────────────── */
            echo '<div class="spbwc-block"><div class="spbwc-block__head"><h3 class="spbwc-block__title"><span class="dashicons dashicons-location" aria-hidden="true"></span> ' . esc_html__( 'Address', 'storelly-product-builder-for-woocommerce' ) . '</h3></div>';
            echo '<div class="spbwc-block__body"><div class="spbwc-setting-rows">';
            $text( 'addr_street', __( 'Street address', 'storelly-product-builder-for-woocommerce' ), $g( $address, 'street' ) );
            $text( 'addr_city', __( 'City', 'storelly-product-builder-for-woocommerce' ), $g( $address, 'city' ) );
            $text( 'addr_state', __( 'State / Province', 'storelly-product-builder-for-woocommerce' ), $g( $address, 'state' ) );
            $text( 'addr_postcode', __( 'Postcode', 'storelly-product-builder-for-woocommerce' ), $g( $address, 'postcode' ) );
            $text( 'addr_country', __( 'Country', 'storelly-product-builder-for-woocommerce' ), $g( $address, 'country' ) );
            echo '</div></div></div>';

            /* ── Official contact ─────────────────────────────────── */
            echo '<div class="spbwc-block"><div class="spbwc-block__head"><h3 class="spbwc-block__title"><span class="dashicons dashicons-id" aria-hidden="true"></span> ' . esc_html__( 'Official contact', 'storelly-product-builder-for-woocommerce' ) . '</h3></div>';
            echo '<div class="spbwc-block__body"><div class="spbwc-setting-rows">';
            $text( 'contact_name', __( 'Primary contact name', 'storelly-product-builder-for-woocommerce' ), $g( $contact, 'name' ) );
            $text( 'contact_title', __( 'Title / Role', 'storelly-product-builder-for-woocommerce' ), $g( $contact, 'title' ) );
            $text( 'contact_email', __( 'Business email', 'storelly-product-builder-for-woocommerce' ), $g( $contact, 'email' ), '', false, 'email' );
            $text( 'contact_phone', __( 'Business phone', 'storelly-product-builder-for-woocommerce' ), $g( $contact, 'phone' ) );
            $text( 'contact_website', __( 'Website', 'storelly-product-builder-for-woocommerce' ), $g( $contact, 'website' ), '', false, 'url' );
            echo '</div><div class="spbwc-block__foot spbwc-block__foot--split"><span class="description">' . esc_html__( 'Edited as the merchant on the company\'s behalf. The owner can also edit these from My Account → Brand Store.', 'storelly-product-builder-for-woocommerce' ) . '</span><span class="spbwc-row spbwc-row--tight"><span class="spbwc-profile-feedback" role="status" aria-live="polite"></span><button type="submit" class="spbwc-cta-btn spbwc-cta-btn--solid"><span class="dashicons dashicons-saved" aria-hidden="true"></span> ' . esc_html__( 'Save Brand Store', 'storelly-product-builder-for-woocommerce' ) . '</button></span></div></div></div>';
            echo '</form>';
        }

        /** Admin upload field with current-image preview (profile tab). */
        protected function profile_upload_field( $name, $label, $hint, $attachment_id, $accept, $size ) {
            echo '<label class="spbwc-b2b-upload"><span class="spbwc-b2b-upload__label">' . esc_html( $label ) . '</span>';
            echo '<span class="spbwc-b2b-upload__box">';
            if ( $attachment_id ) {
                echo wp_get_attachment_image( (int) $attachment_id, $size, false, array( 'class' => 'spbwc-b2b-upload__preview' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core markup.
            } else {
                echo '<span class="spbwc-b2b-upload__icon dashicons dashicons-format-image" aria-hidden="true"></span>';
            }
            echo '<span class="spbwc-b2b-upload__hint">' . esc_html( $hint ) . '</span>';
            echo '<input type="file" name="' . esc_attr( $name ) . '" accept="' . esc_attr( $accept ) . '" />';
            echo '</span></label>';
        }

        /** Admin colour swatch + hex field (profile tab). */
        protected function profile_color_field( $name, $label, $value ) {
            $value = '' !== $value ? $value : ( 'brand_secondary' === $name ? '#1e40af' : '#2563eb' );
            echo '<span class="spbwc-b2b-colour"><span class="spbwc-b2b-colour__label">' . esc_html( $label ) . '</span>';
            echo '<span class="spbwc-b2b-colour__field"><input type="color" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" aria-label="' . esc_attr( $label ) . '" /><code>' . esc_html( $value ) . '</code></span></span>';
        }

        /** Overview tab: account settings, split into two logically grouped cards. */
        protected function render_overview_tab( $company_id, $nonce, $seats ) {
            $terms        = (string) get_post_meta( $company_id, SPBWC_Company::META_PAYMENT_TERMS, true );
            $terms_custom = (string) get_post_meta( $company_id, SPBWC_Company::META_PAYMENT_TERMS_CUSTOM, true );
            $thresh       = (float) get_post_meta( $company_id, SPBWC_Company::META_APPROVAL_THRESHOLD, true );
            $climit       = (float) get_post_meta( $company_id, SPBWC_Company::META_CREDIT_LIMIT, true );
            $rebate_pct   = (float) get_post_meta( $company_id, SPBWC_Company::META_REBATE_PCT, true );
            $current_tier = (string) get_post_meta( $company_id, SPBWC_Company::META_TIER, true );
            $tiers        = class_exists( 'SPBWC_B2B_Pricing' ) ? SPBWC_B2B_Pricing::get_tiers() : array();
            $sym          = get_woocommerce_currency_symbol();

            echo '<form method="post" action="' . esc_url( self::page_url( array( 'company' => $company_id, 'detail_tab' => 'overview' ) ) ) . '">';
            echo '<input type="hidden" name="spbwc_b2b_do" value="save" /><input type="hidden" name="company" value="' . esc_attr( $company_id ) . '" /><input type="hidden" name="_spbwc_b2b_nonce" value="' . esc_attr( $nonce ) . '" />';

            /* ── Card 1: Pricing & team ───────────────────────────── */
            echo '<div class="spbwc-block"><div class="spbwc-block__head"><h3 class="spbwc-block__title"><span class="dashicons dashicons-groups" aria-hidden="true"></span> ' . esc_html__( 'Pricing & team', 'storelly-product-builder-for-woocommerce' ) . '</h3>';
            echo '<p class="spbwc-block__subtitle">' . esc_html__( 'How this company is priced and how large its buying team can be.', 'storelly-product-builder-for-woocommerce' ) . '</p></div>';
            echo '<div class="spbwc-block__body"><div class="spbwc-setting-rows">';

            echo '<div class="spbwc-setting-row"><label class="spbwc-setting-row__label">' . esc_html__( 'Pricing tier', 'storelly-product-builder-for-woocommerce' ) . '</label><select class="spbwc-input" name="tier" style="max-width:260px"><option value="">' . esc_html__( '— No tier —', 'storelly-product-builder-for-woocommerce' ) . '</option>';
            foreach ( $tiers as $tslug => $t ) {
                $tlabel = isset( $t['label'] ) ? $t['label'] : $tslug;
                $tpct   = isset( $t['discount_pct'] ) ? (float) $t['discount_pct'] : 0;
                echo '<option value="' . esc_attr( $tslug ) . '"' . selected( $current_tier, $tslug, false ) . '>' . esc_html( $tlabel . ' (' . rtrim( rtrim( number_format( $tpct, 1 ), '0' ), '.' ) . '%)' ) . '</option>';
            }
            echo '</select>';
            $hint = empty( $tiers ) && class_exists( 'SPBWC_B2B_Pricing_Admin' )
                ? '<span class="spbwc-setting-row__hint"><a href="' . esc_url( SPBWC_B2B_Pricing_Admin::page_url() ) . '">' . esc_html__( 'Create a tier first', 'storelly-product-builder-for-woocommerce' ) . '</a></span>'
                : '<span class="spbwc-setting-row__hint">' . esc_html__( 'Sets the baseline % discount on every product for this company. Per-product overrides live in the Pricing & products tab.', 'storelly-product-builder-for-woocommerce' ) . '</span>';
            echo $hint . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.

            echo '<div class="spbwc-setting-row"><label class="spbwc-setting-row__label">' . esc_html__( 'Team seats', 'storelly-product-builder-for-woocommerce' ) . '</label><input class="spbwc-input" type="number" name="seats" min="1" value="' . esc_attr( $seats ) . '" style="max-width:120px" /><span class="spbwc-setting-row__hint">' . esc_html__( 'Maximum members the owner can invite to this company (buyers + approvers).', 'storelly-product-builder-for-woocommerce' ) . '</span></div>';
            echo '<div class="spbwc-setting-row"><label class="spbwc-setting-row__label">' . esc_html__( 'Approval threshold', 'storelly-product-builder-for-woocommerce' ) . '</label><input class="spbwc-input" type="number" name="approval_threshold" min="0" step="0.01" value="' . esc_attr( $thresh ) . '" style="max-width:160px" /><span class="spbwc-setting-row__hint">' . esc_html( $sym ) . ' · ' . esc_html__( 'Orders above this amount are held for an approver. 0 = no approval needed.', 'storelly-product-builder-for-woocommerce' ) . '</span></div>';
            echo '</div></div></div>';

            /* ── Card 2: Credit & payment terms ───────────────────── */
            echo '<div class="spbwc-block"><div class="spbwc-block__head"><h3 class="spbwc-block__title"><span class="dashicons dashicons-money-alt" aria-hidden="true"></span> ' . esc_html__( 'Credit & payment terms', 'storelly-product-builder-for-woocommerce' ) . '</h3>';
            echo '<p class="spbwc-block__subtitle">' . esc_html__( 'How this company pays — prepaid wallet, net terms ceiling and loyalty rebate.', 'storelly-product-builder-for-woocommerce' ) . '</p></div>';
            echo '<div class="spbwc-block__body"><div class="spbwc-setting-rows">';

            echo '<div class="spbwc-setting-row"><label class="spbwc-setting-row__label">' . esc_html__( 'Payment terms', 'storelly-product-builder-for-woocommerce' ) . '</label><select class="spbwc-input js-spbwc-terms-select" name="payment_terms" style="max-width:220px">';
            foreach ( self::payment_terms() as $slug => $label ) {
                echo '<option value="' . esc_attr( $slug ) . '"' . selected( $terms, $slug, false ) . '>' . esc_html( $label ) . '</option>';
            }
            echo '</select>';
            echo '<input class="spbwc-input js-spbwc-terms-custom" type="text" name="payment_terms_custom" value="' . esc_attr( $terms_custom ) . '" placeholder="' . esc_attr__( 'Custom label, e.g. Net 45', 'storelly-product-builder-for-woocommerce' ) . '" style="max-width:220px;margin-top:6px;' . ( 'custom' === $terms ? '' : 'display:none' ) . '" />';
            echo '<span class="spbwc-setting-row__hint">' . esc_html__( 'When the company may settle invoices. Net terms draw on the credit limit below.', 'storelly-product-builder-for-woocommerce' ) . '</span></div>';
            echo '<div class="spbwc-setting-row"><label class="spbwc-setting-row__label">' . esc_html__( 'Credit limit', 'storelly-product-builder-for-woocommerce' ) . '</label><input class="spbwc-input" type="number" name="credit_limit" min="0" step="0.01" value="' . esc_attr( $climit ) . '" style="max-width:160px" /><span class="spbwc-setting-row__hint">' . esc_html( $sym ) . ' · ' . esc_html__( 'Net-terms ceiling — how much the company may owe at once. 0 = prepaid wallet only. Manage balances in the Account credit tab.', 'storelly-product-builder-for-woocommerce' ) . '</span></div>';
            echo '<div class="spbwc-setting-row"><label class="spbwc-setting-row__label">' . esc_html__( 'Volume rebate', 'storelly-product-builder-for-woocommerce' ) . '</label><input class="spbwc-input" type="number" name="rebate_pct" min="0" max="100" step="0.1" value="' . esc_attr( $rebate_pct ) . '" style="max-width:120px" /><span class="spbwc-setting-row__hint">% · ' . esc_html__( 'Monthly cashback on completed orders, credited back to the wallet. 0 = off.', 'storelly-product-builder-for-woocommerce' ) . '</span></div>';
            echo '</div></div><div class="spbwc-block__foot"><button type="submit" class="spbwc-cta-btn spbwc-cta-btn--solid">' . esc_html__( 'Save changes', 'storelly-product-builder-for-woocommerce' ) . '</button></div></div>';
            echo '</form>';
        }

        /** Members tab: rich roster list. */
        protected function render_members_tab( $company_id, $members, $seats ) {
            $roles    = SPBWC_Company::roles();
            $owner_id = (int) get_post_field( 'post_author', $company_id );
            $count    = count( $members );

            echo '<div class="spbwc-block">';
            echo '<div class="spbwc-block__head"><h3 class="spbwc-block__title"><span class="dashicons dashicons-groups" aria-hidden="true"></span>' . esc_html__( 'Members', 'storelly-product-builder-for-woocommerce' ) . '</h3>';
            /* translators: 1: used seats, 2: total seats. */
            echo '<span class="spbwc-block__badge spbwc-badge--neutral">' . esc_html( sprintf( __( '%1$d / %2$d seats', 'storelly-product-builder-for-woocommerce' ), $count, $seats ) ) . '</span></div>';
            echo '<div class="spbwc-block__body spbwc-block__body--flush"><ul class="spbwc-list spbwc-member-list">';

            // Column header row for alignment cues.
            echo '<li class="spbwc-member-list__head" aria-hidden="true">';
            echo '<span class="spbwc-member__id">' . esc_html__( 'Member', 'storelly-product-builder-for-woocommerce' ) . '</span>';
            echo '<span class="spbwc-member__role">' . esc_html__( 'Role', 'storelly-product-builder-for-woocommerce' ) . '</span>';
            echo '<span class="spbwc-member__limit">' . esc_html__( 'Order limit', 'storelly-product-builder-for-woocommerce' ) . '</span>';
            echo '</li>';

            foreach ( $members as $m ) {
                $role     = SPBWC_Company::get_user_role( $m->ID );
                $lim      = SPBWC_Company::get_order_limit( $m->ID );
                $is_owner = ( (int) $m->ID === $owner_id );
                echo '<li class="spbwc-list__item spbwc-member">' . self::avatar( $m, 'spbwc-avatar--lg' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- avatar escapes.
                echo '<span class="spbwc-member__id">';
                echo '<span class="spbwc-member__name">' . esc_html( $m->display_name );
                if ( $is_owner ) {
                    echo ' <span class="spbwc-role-chip spbwc-role-chip--owner">' . esc_html__( 'Owner', 'storelly-product-builder-for-woocommerce' ) . '</span>';
                }
                echo '</span><span class="spbwc-member__email">' . esc_html( $m->user_email ) . '</span></span>';
                echo '<span class="spbwc-member__role"><span class="spbwc-role-chip spbwc-role-chip--' . esc_attr( $role ) . '">' . esc_html( isset( $roles[ $role ] ) ? $roles[ $role ] : $role ) . '</span></span>';
                echo '<span class="spbwc-member__limit">';
                if ( $lim > 0 ) {
                    echo '<span class="spbwc-member__limit-val">' . wp_kses_post( wc_price( $lim ) ) . '</span><span class="spbwc-member__limit-unit">' . esc_html__( 'per order', 'storelly-product-builder-for-woocommerce' ) . '</span>';
                } else {
                    echo '<span class="spbwc-member__limit-none">' . esc_html__( 'No limit', 'storelly-product-builder-for-woocommerce' ) . '</span>';
                }
                echo '</span>';
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

            // Action forms: top-up, record payment, adjustment. Amounts are entered
            // in the store currency (WooCommerce setting) — no hardcoded unit; the
            // currency code is shown as a hint so it adapts to any locale.
            $cur_code = get_woocommerce_currency();
            echo '<div class="spbwc-block"><div class="spbwc-block__head"><h3 class="spbwc-block__title">' . esc_html__( 'Record a transaction', 'storelly-product-builder-for-woocommerce' ) . '</h3></div><div class="spbwc-block__body">';

            $form = function ( $do, $button, $hint ) use ( $company_id, $nonce, $cur_code ) {
                echo '<form method="post" class="spbwc-b2b-bind" action="' . esc_url( self::page_url( array( 'company' => $company_id, 'detail_tab' => 'credit' ) ) ) . '" style="margin-bottom:12px">';
                echo '<input type="hidden" name="spbwc_b2b_do" value="' . esc_attr( $do ) . '" /><input type="hidden" name="company" value="' . esc_attr( $company_id ) . '" /><input type="hidden" name="_spbwc_b2b_nonce" value="' . esc_attr( $nonce ) . '" />';
                echo '<label><span class="spbwc-bind-label">' . esc_html__( 'Amount', 'storelly-product-builder-for-woocommerce' ) . ' <span class="spbwc-cur-code">' . esc_html( $cur_code ) . '</span></span><input class="spbwc-input" type="number" name="amount" min="0" step="0.01" required style="width:130px" /></label>';
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
                echo '<td class="spbwc-stmt-amt spbwc-stmt-amt--debit">' . ( (float) $r->debit > 0 ? wp_kses_post( wc_price( $r->debit ) ) : '' ) . '</td>';
                echo '<td class="spbwc-stmt-amt spbwc-stmt-amt--credit">' . ( (float) $r->credit > 0 ? wp_kses_post( wc_price( $r->credit ) ) : '' ) . '</td>';
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
                    $override = wp_kses_post( wc_price( (float) $rule->value ) ) . ' ' . esc_html__( 'fixed', 'storelly-product-builder-for-woocommerce' );
                    $eff      = (float) $rule->value;
                } else {
                    $override = esc_html( rtrim( rtrim( number_format( (float) $rule->value, 1 ), '0' ), '.' ) . '% ' ) . esc_html__( 'off', 'storelly-product-builder-for-woocommerce' );
                    $eff      = $base * ( ( 100 - (float) $rule->value ) / 100 );
                }
                echo '<tr>';
                echo '<td>' . esc_html( $product ? $product->get_name() : '#' . (int) $rule->product_id ) . '</td>';
                echo '<td>' . wp_kses_post( wc_price( $base ) ) . '</td>';
                echo '<td>' . $override . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from wc_price (kses) + esc_html parts.
                echo '<td><strong>' . wp_kses_post( wc_price( $eff ) ) . '</strong></td>';
                echo '<td>' . esc_html( (int) $rule->min_qty > 0 ? (int) $rule->min_qty : '—' ) . '</td>';
                echo '<td>' . esc_html( ! empty( $rule->valid_until ) ? mysql2date( get_option( 'date_format' ), $rule->valid_until ) : '—' ) . '</td>';
                echo '<td><form method="post" action="' . esc_url( self::page_url( array( 'company' => $company_id, 'detail_tab' => 'pricing' ) ) ) . '">';
                echo '<input type="hidden" name="spbwc_b2b_do" value="unbind_price" />';
                echo '<input type="hidden" name="company" value="' . esc_attr( $company_id ) . '" />';
                echo '<input type="hidden" name="product_id" value="' . esc_attr( $rule->product_id ) . '" />';
                echo '<input type="hidden" name="_spbwc_b2b_nonce" value="' . esc_attr( $nonce ) . '" />';
                echo '<button type="submit" class="spbwc-cta-btn spbwc-cta-btn--ghost spbwc-cta-btn--sm" style="color:var(--nbd-color-danger);border-color:var(--nbd-color-danger)">' . esc_html__( 'Remove', 'storelly-product-builder-for-woocommerce' ) . '</button>';
                echo '</form></td></tr>';
            }
            echo '</tbody></table>';

            // Bind form (labelled).
            echo '<form method="post" class="spbwc-b2b-bind" action="' . esc_url( self::page_url( array( 'company' => $company_id, 'detail_tab' => 'pricing' ) ) ) . '">';
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
