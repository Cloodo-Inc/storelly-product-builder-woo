<?php
/**
 * B2B Team Procurement — approval gate + queue (M5).
 *
 * A gated requester (role `requester`/`viewer`) whose cart exceeds their per-order
 * limit or the company approval threshold cannot check out directly: instead they
 * "Submit for approval", which snapshots the cart into a procurement request. An
 * approver (owner/admin/approver) reviews it in the My-Account Approval Queue and
 * approves (creates a real WooCommerce order for the requester, designs preserved
 * via cloned folders) or rejects it.
 *
 * Stored as a dedicated headless CPT `spbwc_procurement` (its own status meta) so
 * it never pollutes the merchant Quotes list. Reuses the comment-timeline pattern.
 * See docs/SPEC_B2B_CLIENT.md §8.
 *
 * @package Storelly
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'SPBWC_B2B_Procurement' ) ) {

    class SPBWC_B2B_Procurement {

        const POST_TYPE     = 'spbwc_procurement';
        const ENDPOINT      = 'approval';
        const FLUSH_FLAG    = 'spbwc_b2b_approval_flushed';
        const TIMELINE_TYPE = 'spbwc_procurement_event';

        const META_COMPANY   = '_spbwc_proc_company_id';
        const META_REQUESTER = '_spbwc_proc_requester_id';
        const META_SNAPSHOT  = '_spbwc_proc_snapshot';
        const META_TOTAL     = '_spbwc_proc_total';
        const META_STATUS    = '_spbwc_proc_status';
        const META_ORDER     = '_spbwc_proc_order_id';

        const STATUS_PENDING  = 'pending';
        const STATUS_APPROVED = 'approved';
        const STATUS_REJECTED = 'rejected';

        public static function init() {
            add_action( 'init', array( __CLASS__, 'register_post_type' ) );
            add_action( 'init', array( __CLASS__, 'add_endpoint' ) );
            add_filter( 'woocommerce_account_menu_items', array( __CLASS__, 'add_menu_item' ) );
            add_action( 'woocommerce_account_' . self::ENDPOINT . '_endpoint', array( __CLASS__, 'render_endpoint' ) );
            // Cart gate: offer the submit button + enforce the block.
            add_action( 'woocommerce_proceed_to_checkout', array( __CLASS__, 'cart_gate_notice' ), 5 );
            add_action( 'woocommerce_checkout_process', array( __CLASS__, 'block_gated_checkout' ) );
            // Action handlers (submit from cart, approve/reject from queue).
            add_action( 'wp_loaded', array( __CLASS__, 'handle_actions' ), 50 );
        }

        public static function register_post_type() {
            register_post_type(
                self::POST_TYPE,
                array(
                    'public'              => false,
                    'show_ui'             => false,
                    'show_in_menu'        => false,
                    'exclude_from_search' => true,
                    'publicly_queryable'  => false,
                    'rewrite'             => false,
                    'query_var'           => false,
                    'supports'            => array( 'title', 'author' ),
                    'capability_type'     => 'post',
                    'map_meta_cap'        => true,
                )
            );
        }

        public static function add_endpoint() {
            add_rewrite_endpoint( self::ENDPOINT, EP_ROOT | EP_PAGES );
            if ( 'yes' !== get_option( self::FLUSH_FLAG ) ) {
                flush_rewrite_rules( false );
                update_option( self::FLUSH_FLAG, 'yes' );
            }
        }

        /** Show "Approvals" before logout, only for approvers. */
        public static function add_menu_item( $items ) {
            if ( ! SPBWC_Company::user_can_approve() ) {
                return $items;
            }
            $logout = isset( $items['customer-logout'] ) ? $items['customer-logout'] : null;
            if ( null !== $logout ) {
                unset( $items['customer-logout'] );
            }
            $pending = self::get_pending_count( SPBWC_Company::get_user_company_id() );
            $label   = __( 'Approvals', 'storelly-product-builder-for-woocommerce' );
            if ( $pending > 0 ) {
                $label .= ' (' . number_format_i18n( $pending ) . ')';
            }
            $items[ self::ENDPOINT ] = $label;
            if ( null !== $logout ) {
                $items['customer-logout'] = $logout;
            }
            return $items;
        }

        /* ── Cart gate ────────────────────────────────────────────── */

        /**
         * Is the current cart gated for the current user?
         *
         * @return bool
         */
        protected static function cart_is_gated() {
            if ( ! function_exists( 'WC' ) || ! WC()->cart || ! is_user_logged_in() ) {
                return false;
            }
            $total = (float) WC()->cart->get_cart_contents_total();
            return SPBWC_Company::order_needs_approval( $total );
        }

        /** Notice + "Submit for approval" button on the cart page. */
        public static function cart_gate_notice() {
            if ( ! self::cart_is_gated() ) {
                return;
            }
            SPBWC_B2B_Assets::storefront();
            $url = wp_nonce_url(
                add_query_arg( 'spbwc_submit_approval', '1', wc_get_cart_url() ),
                'spbwc_submit_approval'
            );
            echo '<div class="spbwc-proc-gate woocommerce-info">';
            echo esc_html__( 'This order is above your spending limit and needs manager approval before it can be placed.', 'storelly-product-builder-for-woocommerce' );
            echo ' <a class="button" href="' . esc_url( $url ) . '">' . esc_html__( 'Submit for approval', 'storelly-product-builder-for-woocommerce' ) . '</a>';
            echo '</div>';
        }

        /** Block direct checkout for a gated requester. */
        public static function block_gated_checkout() {
            if ( self::cart_is_gated() ) {
                wc_add_notice(
                    __( 'This order needs manager approval. Please use "Submit for approval" on the cart page.', 'storelly-product-builder-for-woocommerce' ),
                    'error'
                );
            }
        }

        /* ── Action dispatch ──────────────────────────────────────── */

        public static function handle_actions() {
            if ( isset( $_GET['spbwc_submit_approval'] ) ) {
                self::handle_submit();
                return;
            }
            if ( isset( $_POST['spbwc_proc_decision'] ) ) {
                self::handle_decision();
            }
        }

        /** Snapshot the cart into a procurement request. */
        protected static function handle_submit() {
            $nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
            if ( ! wp_verify_nonce( $nonce, 'spbwc_submit_approval' ) || ! is_user_logged_in() ) {
                return;
            }
            if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
                return;
            }
            if ( function_exists( 'wc_load_cart' ) ) {
                wc_load_cart();
            }
            $user_id    = get_current_user_id();
            $company_id = SPBWC_Company::get_user_company_id( $user_id );
            if ( ! $company_id || WC()->cart->is_empty() ) {
                self::redirect_account( 'error' );
            }

            $snapshot = array();
            foreach ( WC()->cart->get_cart() as $ci ) {
                $snapshot[] = array(
                    'product_id'   => (int) $ci['product_id'],
                    'variation_id' => (int) $ci['variation_id'],
                    'quantity'     => (int) $ci['quantity'],
                    'pcpb_meta'    => isset( $ci['pcpb_meta'] ) ? $ci['pcpb_meta'] : null,
                    'line_total'   => isset( $ci['line_total'] ) ? (float) $ci['line_total'] : 0,
                );
            }
            $total = (float) WC()->cart->get_cart_contents_total();

            $post_id = wp_insert_post(
                array(
                    'post_type'      => self::POST_TYPE,
                    'post_status'    => 'publish',
                    'post_author'    => $user_id,
                    'post_title'     => sprintf(
                        /* translators: %s: formatted total. */
                        __( 'Approval request — %s', 'storelly-product-builder-for-woocommerce' ),
                        wp_strip_all_tags( wc_price( $total ) )
                    ),
                    'comment_status' => 'closed',
                ),
                true
            );
            if ( is_wp_error( $post_id ) ) {
                self::redirect_account( 'error' );
            }
            update_post_meta( $post_id, self::META_COMPANY, $company_id );
            update_post_meta( $post_id, self::META_REQUESTER, $user_id );
            update_post_meta( $post_id, self::META_SNAPSHOT, $snapshot );
            update_post_meta( $post_id, self::META_TOTAL, $total );
            update_post_meta( $post_id, self::META_STATUS, self::STATUS_PENDING );
            self::add_event( $post_id, __( 'Submitted for approval.', 'storelly-product-builder-for-woocommerce' ), $user_id );
            self::invalidate_pending_count( $company_id );

            // Clear the cart so it can't be checked out directly.
            WC()->cart->empty_cart();
            self::notify_approvers( $post_id );
            self::redirect_account( 'submitted' );
        }

        /** Approve / reject a request. */
        protected static function handle_decision() {
            $id       = isset( $_POST['request_id'] ) ? absint( wp_unslash( $_POST['request_id'] ) ) : 0;
            $decision = isset( $_POST['spbwc_proc_decision'] ) ? sanitize_key( wp_unslash( $_POST['spbwc_proc_decision'] ) ) : '';
            $comment  = isset( $_POST['comment'] ) ? sanitize_textarea_field( wp_unslash( $_POST['comment'] ) ) : '';
            $nonce    = isset( $_POST['_spbwc_proc_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_spbwc_proc_nonce'] ) ) : '';
            if ( ! $id || ! wp_verify_nonce( $nonce, 'spbwc_proc_' . $id ) ) {
                return;
            }
            if ( ! is_user_logged_in() || ! SPBWC_Company::user_can_approve() ) {
                wp_die( esc_html__( 'You do not have permission.', 'storelly-product-builder-for-woocommerce' ), 403 );
            }
            $company_id = (int) get_post_meta( $id, self::META_COMPANY, true );
            // Scope: approver must belong to the SAME company as the request.
            if ( SPBWC_Company::get_user_company_id() !== $company_id ) {
                wp_die( esc_html__( 'You do not have permission.', 'storelly-product-builder-for-woocommerce' ), 403 );
            }
            if ( self::STATUS_PENDING !== (string) get_post_meta( $id, self::META_STATUS, true ) ) {
                self::redirect_account( 'error' ); // Already decided (idempotent).
            }
            // The decision below moves this request out of "pending"; drop the cache.
            self::invalidate_pending_count( $company_id );

            if ( 'approve' === $decision ) {
                $order_id = self::create_order( $id );
                if ( $order_id ) {
                    update_post_meta( $id, self::META_STATUS, self::STATUS_APPROVED );
                    update_post_meta( $id, self::META_ORDER, $order_id );
                    /**
                     * Fires after a procurement request is approved and its order
                     * created. The Account Credit module uses this to charge a
                     * net-terms company's account for the approved order.
                     *
                     * @param int $order_id   Created WooCommerce order.
                     * @param int $id         Procurement request post id.
                     * @param int $company_id Company.
                     */
                    do_action( 'spbwc_b2b_procurement_approved', $order_id, $id, $company_id );
                    self::add_event(
                        $id,
                        sprintf(
                            /* translators: 1: order number, 2: comment. */
                            __( 'Approved → order #%1$s. %2$s', 'storelly-product-builder-for-woocommerce' ),
                            $order_id,
                            $comment
                        )
                    );
                    self::notify_requester( $id, 'approved', $order_id );
                    self::redirect_account( 'approved' );
                }
                self::redirect_account( 'error' );
            } elseif ( 'reject' === $decision ) {
                update_post_meta( $id, self::META_STATUS, self::STATUS_REJECTED );
                self::add_event(
                    $id,
                    sprintf(
                        /* translators: %s: comment. */
                        __( 'Rejected. %s', 'storelly-product-builder-for-woocommerce' ),
                        $comment
                    )
                );
                self::notify_requester( $id, 'rejected', 0 );
                self::redirect_account( 'rejected' );
            }
        }

        /**
         * Build a WooCommerce order for the requester from the snapshot, cloning
         * each design folder so the order owns an independent copy.
         *
         * @param int $id Request post ID.
         * @return int Order ID, or 0.
         */
        protected static function create_order( $id ) {
            $requester = (int) get_post_meta( $id, self::META_REQUESTER, true );
            $snapshot  = get_post_meta( $id, self::META_SNAPSHOT, true );
            if ( ! $requester || ! is_array( $snapshot ) || empty( $snapshot ) ) {
                return 0;
            }
            $order = wc_create_order( array( 'customer_id' => $requester ) );
            if ( is_wp_error( $order ) ) {
                return 0;
            }
            foreach ( $snapshot as $row ) {
                $pid     = ! empty( $row['variation_id'] ) ? (int) $row['variation_id'] : (int) $row['product_id'];
                $product = wc_get_product( $pid );
                if ( ! $product ) {
                    continue;
                }
                $qty     = max( 1, (int) $row['quantity'] );
                $item_id = $order->add_product( $product, $qty );
                if ( ! $item_id ) {
                    continue;
                }
                $pm = is_array( $row['pcpb_meta'] ) ? $row['pcpb_meta'] : array();
                if ( ! empty( $pm['pcpb'] ) ) {
                    // Clone the folder so the order owns its artwork independently.
                    $clone = SPBWC_Storelly_IO::spbwc_clone_design_folder( (string) $pm['pcpb'] );
                    wc_add_order_item_meta( $item_id, '_pcpb_folder', $clone ? $clone : $pm['pcpb'] );
                }
                if ( isset( $pm['field'] ) ) {
                    wc_add_order_item_meta( $item_id, '_pcpb_field', $pm['field'] );
                }
                if ( isset( $pm['options'] ) ) {
                    wc_add_order_item_meta( $item_id, '_pcpb_options', $pm['options'] );
                }
                if ( isset( $pm['option_price'] ) ) {
                    wc_add_order_item_meta( $item_id, '_pcpb_option_price', $pm['option_price'] );
                }
                if ( isset( $pm['original_price'] ) ) {
                    wc_add_order_item_meta( $item_id, '_pcpb_original_price', $pm['original_price'] );
                }
            }
            $order->set_status( 'on-hold', __( 'Approved B2B procurement request.', 'storelly-product-builder-for-woocommerce' ) );
            $order->calculate_totals();
            $order->save();
            return $order->get_id();
        }

        /* ── Queue (My-Account) ───────────────────────────────────── */

        public static function render_endpoint() {
            if ( ! is_user_logged_in() || ! SPBWC_Company::user_can_approve() ) {
                echo '<p>' . esc_html__( 'You do not have approval access.', 'storelly-product-builder-for-woocommerce' ) . '</p>';
                return;
            }
            SPBWC_B2B_Assets::storefront();
            self::print_notice();

            $company_id = SPBWC_Company::get_user_company_id();
            $pending    = self::get_pending_for_company( $company_id );
            $use_shell  = class_exists( 'SPBWC_Account_Shell' );

            if ( $use_shell ) {
                SPBWC_Account_Shell::open(
                    array(
                        'eyebrow'  => __( 'Company workspace', 'storelly-product-builder-for-woocommerce' ),
                        'title'    => __( 'Approval Queue', 'storelly-product-builder-for-woocommerce' ),
                        'subtitle' => __( 'Review and approve or reject team orders that exceed a spending limit.', 'storelly-product-builder-for-woocommerce' ),
                    )
                );
            }
            echo '<div class="spbwc-proc">';
            if ( ! $use_shell ) {
                echo '<h2>' . esc_html__( 'Approval Queue', 'storelly-product-builder-for-woocommerce' ) . '</h2>';
            }

            // Stats.
            $pending_total = 0.0;
            foreach ( $pending as $r ) {
                $pending_total += (float) get_post_meta( $r->ID, self::META_TOTAL, true );
            }
            echo '<div class="spbwc-rfq-stats">';
            echo '<div class="spbwc-rfq-stat spbwc-rfq-stat--accent"><span class="spbwc-rfq-stat__value">' . esc_html( number_format_i18n( count( $pending ) ) ) . '</span><span class="spbwc-rfq-stat__label">' . esc_html__( 'Awaiting approval', 'storelly-product-builder-for-woocommerce' ) . '</span></div>';
            echo '<div class="spbwc-rfq-stat"><span class="spbwc-rfq-stat__value">' . wp_kses_post( wc_price( $pending_total ) ) . '</span><span class="spbwc-rfq-stat__label">' . esc_html__( 'Value pending', 'storelly-product-builder-for-woocommerce' ) . '</span></div>';
            echo '</div>';

            if ( empty( $pending ) ) {
                if ( $use_shell ) {
                    SPBWC_Account_Shell::empty_state(
                        array(
                            'icon'  => '<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>',
                            'title' => __( 'You are all caught up', 'storelly-product-builder-for-woocommerce' ),
                            'text'  => __( 'There are no team orders awaiting your approval right now.', 'storelly-product-builder-for-woocommerce' ),
                        )
                    );
                } else {
                    echo '<div class="spbwc-store__empty"><span class="dashicons dashicons-yes-alt spbwc-icon-xl" aria-hidden="true"></span><p>' . esc_html__( 'No requests awaiting approval.', 'storelly-product-builder-for-woocommerce' ) . '</p></div>';
                }
                echo '</div>';
                if ( $use_shell ) {
                    SPBWC_Account_Shell::close();
                }
                return;
            }
            foreach ( $pending as $req ) {
                $requester = get_userdata( (int) get_post_meta( $req->ID, self::META_REQUESTER, true ) );
                $total     = (float) get_post_meta( $req->ID, self::META_TOTAL, true );
                $snapshot  = (array) get_post_meta( $req->ID, self::META_SNAPSHOT, true );
                $nonce     = wp_create_nonce( 'spbwc_proc_' . $req->ID );
                $r_role    = $requester ? SPBWC_Company::get_user_role( $requester->ID ) : '';
                $r_limit   = $requester ? SPBWC_Company::get_order_limit( $requester->ID ) : 0;
                $when      = human_time_diff( get_post_time( 'U', true, $req ), time() );

                echo '<div class="spbwc-rfq-card spbwc-proc__card">';
                // Header: amount + requester.
                echo '<div class="spbwc-proc__head spbwc-row spbwc-row--between">';
                echo '<span class="spbwc-row spbwc-row--sm">' . self::avatar( $requester ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- avatar escapes.
                echo '<span><strong>' . esc_html( $requester ? $requester->display_name : '—' ) . '</strong> ';
                if ( '' !== $r_role ) {
                    echo '<span class="spbwc-role-chip spbwc-role-chip--' . esc_attr( $r_role ) . '">' . esc_html( SPBWC_Company::roles()[ $r_role ] ) . '</span>';
                }
                echo '<br /><small class="spbwc-muted">' . esc_html(
                    sprintf(
                        /* translators: 1: count, 2: relative time. */
                        __( '%1$d item(s) · submitted %2$s ago', 'storelly-product-builder-for-woocommerce' ),
                        count( $snapshot ),
                        $when
                    )
                ) . '</small></span></span>';
                echo '<span class="spbwc-amount">' . wp_kses_post( wc_price( $total ) ) . '</span>';
                echo '</div>';

                echo '<ul class="spbwc-proc__items">';
                foreach ( $snapshot as $row ) {
                    $p = wc_get_product( ! empty( $row['variation_id'] ) ? $row['variation_id'] : $row['product_id'] );
                    echo '<li>' . esc_html( ( $p ? $p->get_name() : '#' . (int) $row['product_id'] ) . ' × ' . (int) $row['quantity'] ) . '</li>';
                }
                echo '</ul>';

                // Budget context.
                if ( $r_limit > 0 ) {
                    $pct = min( 100, (int) round( $total / $r_limit * 100 ) );
                    $mod = $pct >= 100 ? ' spbwc-meter--danger' : ( $pct >= 80 ? ' spbwc-meter--warn' : '' );
                    echo '<div class="spbwc-row spbwc-row--sm spbwc-proc__budget">';
                    echo '<span class="' . esc_attr( 'spbwc-meter' . $mod ) . '"><span class="spbwc-meter__fill" style="width:' . esc_attr( $pct ) . '%"></span></span>';
                    echo '<span class="spbwc-meter__label">' . esc_html( sprintf(
                        /* translators: 1: order total, 2: per-order limit. */
                        __( '%1$s of %2$s per-order limit', 'storelly-product-builder-for-woocommerce' ),
                        wp_strip_all_tags( wc_price( $total ) ),
                        wp_strip_all_tags( wc_price( $r_limit ) )
                    ) ) . '</span></div>';
                }

                echo '<form method="post" class="spbwc-proc__actions" action="' . esc_url( wc_get_endpoint_url( self::ENDPOINT, '', wc_get_page_permalink( 'myaccount' ) ) ) . '">';
                echo '<input type="hidden" name="request_id" value="' . esc_attr( $req->ID ) . '" />';
                echo '<input type="hidden" name="_spbwc_proc_nonce" value="' . esc_attr( $nonce ) . '" />';
                echo '<textarea name="comment" rows="2" placeholder="' . esc_attr__( 'Optional comment…', 'storelly-product-builder-for-woocommerce' ) . '"></textarea>';
                echo '<div class="spbwc-proc__btns">';
                echo '<button type="submit" name="spbwc_proc_decision" value="approve" class="spbwc-rfq-btn" style="width:auto;">' . esc_html__( 'Approve', 'storelly-product-builder-for-woocommerce' ) . '</button> ';
                echo '<button type="submit" name="spbwc_proc_decision" value="reject" class="spbwc-rfq-btn spbwc-rfq-btn--danger" style="width:auto;">' . esc_html__( 'Reject', 'storelly-product-builder-for-woocommerce' ) . '</button>';
                echo '</div></form>';
                echo '</div>';
            }
            echo '</div>';
            if ( $use_shell ) {
                SPBWC_Account_Shell::close();
            }
        }

        /* ── Helpers ──────────────────────────────────────────────── */

        /** Circular initials avatar for a user. */
        protected static function avatar( $user ) {
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
            return '<span class="spbwc-avatar">' . esc_html( '' !== $initials ? strtoupper( $initials ) : '?' ) . '</span>';
        }

        /**
         * @param int $company_id Company.
         * @return WP_Post[] Pending requests.
         */
        public static function get_pending_for_company( $company_id ) {
            $company_id = absint( $company_id );
            if ( ! $company_id ) {
                return array();
            }
            return get_posts(
                array(
                    'post_type'   => self::POST_TYPE,
                    'post_status' => 'publish',
                    'numberposts' => 100,
                    'orderby'     => 'date',
                    'order'       => 'DESC',
                    'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
                        array(
                            'key'   => self::META_COMPANY,
                            'value' => $company_id,
                        ),
                        array(
                            'key'   => self::META_STATUS,
                            'value' => self::STATUS_PENDING,
                        ),
                    ),
                )
            );
        }

        /**
         * Cached count of pending approvals for a company.
         *
         * The My-Account menu badge only needs the number, not the full post set,
         * so this caches the integer per-company for 60s to avoid a 100-row
         * get_posts() on every account page-load. Invalidated immediately by
         * invalidate_pending_count() when a request is submitted or decided.
         *
         * @param int $company_id Company.
         * @return int
         */
        public static function get_pending_count( $company_id ) {
            $company_id = absint( $company_id );
            if ( ! $company_id ) {
                return 0;
            }
            $key    = 'spbwc_proc_pending_' . $company_id;
            $cached = get_transient( $key );
            if ( false !== $cached ) {
                return (int) $cached;
            }
            $count = count( self::get_pending_for_company( $company_id ) );
            set_transient( $key, $count, MINUTE_IN_SECONDS );
            return $count;
        }

        /**
         * Drop the cached pending-count for a company.
         *
         * @param int $company_id Company.
         */
        public static function invalidate_pending_count( $company_id ) {
            $company_id = absint( $company_id );
            if ( $company_id ) {
                delete_transient( 'spbwc_proc_pending_' . $company_id );
            }
        }

        protected static function add_event( $post_id, $message, $user_id = 0 ) {
            wp_insert_comment(
                array(
                    'comment_post_ID'  => absint( $post_id ),
                    'comment_content'  => wp_kses_post( $message ),
                    'comment_type'     => self::TIMELINE_TYPE,
                    'user_id'          => $user_id ? absint( $user_id ) : get_current_user_id(),
                    'comment_approved' => 1,
                )
            );
        }

        protected static function notify_approvers( $request_id ) {
            // Routed through the WC email pipeline (SPBWC_Email_B2B_Approval_Needed).
            do_action( 'spbwc_b2b_approval_needed_notification', $request_id );
        }

        protected static function notify_requester( $request_id, $outcome, $order_id ) {
            // Routed through the WC email pipeline (SPBWC_Email_B2B_Approval_Outcome).
            do_action( 'spbwc_b2b_approval_outcome_notification', $request_id, $outcome, (int) $order_id );
        }

        protected static function redirect_account( $code ) {
            $url = add_query_arg( 'spbwc_proc_msg', $code, wc_get_endpoint_url( self::ENDPOINT, '', wc_get_page_permalink( 'myaccount' ) ) );
            wp_safe_redirect( $url );
            exit;
        }

        protected static function print_notice() {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only flash.
            if ( ! isset( $_GET['spbwc_proc_msg'] ) ) {
                return;
            }
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only flash.
            $code = sanitize_key( wp_unslash( $_GET['spbwc_proc_msg'] ) );
            $map  = array(
                'submitted' => array( 'message', __( 'Submitted for approval. Your manager has been notified.', 'storelly-product-builder-for-woocommerce' ) ),
                'approved'  => array( 'message', __( 'Request approved and order created.', 'storelly-product-builder-for-woocommerce' ) ),
                'rejected'  => array( 'message', __( 'Request rejected.', 'storelly-product-builder-for-woocommerce' ) ),
                'error'     => array( 'error', __( 'Something went wrong. Please try again.', 'storelly-product-builder-for-woocommerce' ) ),
            );
            if ( ! isset( $map[ $code ] ) ) {
                return;
            }
            $cls = 'error' === $map[ $code ][0] ? 'woocommerce-error' : 'woocommerce-message';
            echo '<div class="' . esc_attr( $cls ) . '" role="alert">' . esc_html( $map[ $code ][1] ) . '</div>';
        }
    }
}
