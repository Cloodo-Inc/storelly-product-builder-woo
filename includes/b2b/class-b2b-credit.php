<?php
/**
 * B2B Account Credit — storefront surface (M2 Wallet).
 *
 * Three things wired to the SPBWC_B2B_Ledger engine:
 *   1. A My-Account "Company Credit" endpoint — balance, available credit and a
 *      statement, visible to every member of a company.
 *   2. A WooCommerce payment gateway "Pay with Company Account" — offered only
 *      when the buyer belongs to an active company whose available credit covers
 *      the cart. Paying posts an `order_charge` (debit) atomically.
 *   3. Order-lifecycle hooks that reverse the charge if the order is cancelled,
 *      so a debit never strands.
 *
 * Available credit = wallet funds + unused credit line = max(0, balance + limit).
 * In M2 the limit is usually 0 (pure prepaid wallet); M3 (Net Terms) populates it.
 *
 * Fully local — no external service. See docs/SPEC_B2B_ACCOUNT_CREDIT.md (M2).
 *
 * @package Storelly
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'SPBWC_B2B_Credit' ) ) {

    class SPBWC_B2B_Credit {

        const ENDPOINT   = 'company-account';
        const FLUSH_FLAG = 'spbwc_b2b_credit_flushed';

        /** Order meta: the company charged + the amount + reversal guard. */
        const ORDER_COMPANY = '_spbwc_credit_company';
        const ORDER_CHARGED = '_spbwc_credit_charged';
        const ORDER_REVERSED = '_spbwc_credit_reversed';

        public static function init() {
            add_action( 'init', array( __CLASS__, 'add_endpoint' ) );
            add_filter( 'woocommerce_account_menu_items', array( __CLASS__, 'add_menu_item' ) );
            add_action( 'woocommerce_account_' . self::ENDPOINT . '_endpoint', array( __CLASS__, 'render_endpoint' ) );

            // Payment gateway (defined after WooCommerce is loaded).
            add_filter( 'woocommerce_payment_gateways', array( __CLASS__, 'register_gateway' ) );

            // Reverse the charge if the order is cancelled/failed so the debit never strands.
            add_action( 'woocommerce_order_status_cancelled', array( __CLASS__, 'reverse_charge' ) );
            add_action( 'woocommerce_order_status_failed', array( __CLASS__, 'reverse_charge' ) );
            // M6 — credit back partial/full refunds against the company account.
            add_action( 'woocommerce_order_refunded', array( __CLASS__, 'reverse_refund' ), 10, 2 );

            // M4 — route over-credit orders into the existing approval queue, and
            // charge approved net-terms orders to the company account.
            add_filter( 'spbwc_order_needs_approval', array( __CLASS__, 'gate_over_credit' ), 10, 4 );
            add_action( 'spbwc_b2b_procurement_approved', array( __CLASS__, 'charge_approved_order' ), 10, 3 );
        }

        /* ── M4: credit-aware approval ────────────────────────────── */

        /**
         * Force approval when a net-terms company's order would exceed its
         * available credit. Approvers (owner/admin) are never gated by the caller.
         *
         * @param bool  $needs      Whether approval is already required.
         * @param float $total      Order/cart total.
         * @param int   $user_id    Buyer.
         * @param int   $company_id Company.
         * @return bool
         */
        public static function gate_over_credit( $needs, $total, $user_id, $company_id ) {
            if ( $needs ) {
                return true;
            }
            $limit = self::credit_limit( $company_id );
            if ( $limit <= 0 ) {
                return false; // Prepaid-wallet companies fall back to other gateways.
            }
            $available = SPBWC_B2B_Ledger::get_available_credit( $company_id, $limit );
            return (float) $total > $available;
        }

        /**
         * Charge an approved procurement order to the company account (overdraft
         * allowed — the approver authorised it). Only for net-terms companies, so
         * approved B2B orders become company receivables instead of stranding.
         *
         * @param int $order_id   Created order.
         * @param int $request_id Procurement request.
         * @param int $company_id Company.
         */
        public static function charge_approved_order( $order_id, $request_id, $company_id ) {
            if ( self::credit_limit( $company_id ) <= 0 ) {
                return; // No credit line configured → leave the order to normal payment.
            }
            $order = wc_get_order( $order_id );
            if ( ! $order ) {
                return;
            }
            $res = self::charge_order( $order, true );
            if ( ! is_wp_error( $res ) ) {
                $order->add_order_note( __( 'Charged to company account (approved over credit limit).', 'storelly-product-builder-for-woocommerce' ) );
            }
        }

        /* ── Credit-limit resolver (company meta) ─────────────────── */

        /**
         * The company's credit line (Net Terms). 0 = prepaid wallet only.
         *
         * @param int $company_id Company.
         * @return float
         */
        public static function credit_limit( $company_id ) {
            $limit = (float) get_post_meta( $company_id, SPBWC_Company::META_CREDIT_LIMIT, true );
            return $limit > 0 ? $limit : 0.0;
        }

        /**
         * Net-terms window in days, derived from the company's payment terms.
         * prepaid → 0 (wallet, no due date); net15/net30 → 15/30; custom → filter.
         *
         * @param int $company_id Company.
         * @return int
         */
        public static function terms_days( $company_id ) {
            $terms = (string) get_post_meta( $company_id, SPBWC_Company::META_PAYMENT_TERMS, true );
            $map   = array( 'prepaid' => 0, 'net15' => 15, 'net30' => 30 );
            $days  = isset( $map[ $terms ] ) ? $map[ $terms ] : 30; // 'custom'/unknown → 30.
            /**
             * Filter the net-terms window (days) for a company.
             *
             * @param int    $days       Days until an invoice is due.
             * @param int    $company_id Company.
             * @param string $terms      Payment-terms slug.
             */
            return (int) apply_filters( 'spbwc_b2b_credit_terms_days', $days, $company_id, $terms );
        }

        /**
         * Spendable amount for the current (or given) user's company.
         *
         * @param int $user_id 0 = current user.
         * @return array{company:int,available:float,balance:float} or company 0 if none.
         */
        public static function context( $user_id = 0 ) {
            $company_id = SPBWC_Company::get_user_company_id( $user_id );
            if ( ! $company_id || ! SPBWC_Company::is_active( $company_id ) ) {
                return array( 'company' => 0, 'available' => 0.0, 'balance' => 0.0 );
            }
            $limit = self::credit_limit( $company_id );
            return array(
                'company'   => (int) $company_id,
                'balance'   => SPBWC_B2B_Ledger::get_balance( $company_id ),
                'available' => SPBWC_B2B_Ledger::get_available_credit( $company_id, $limit ),
            );
        }

        /* ── My-Account endpoint ──────────────────────────────────── */

        public static function add_endpoint() {
            add_rewrite_endpoint( self::ENDPOINT, EP_ROOT | EP_PAGES );
            if ( 'yes' !== get_option( self::FLUSH_FLAG ) ) {
                flush_rewrite_rules( false );
                update_option( self::FLUSH_FLAG, 'yes' );
            }
        }

        /**
         * Insert "Company Credit" before logout, only for company members.
         *
         * @param array $items Menu items.
         * @return array
         */
        public static function add_menu_item( $items ) {
            if ( ! SPBWC_Company::get_user_company_id() ) {
                return $items;
            }
            $logout = isset( $items['customer-logout'] ) ? $items['customer-logout'] : null;
            if ( null !== $logout ) {
                unset( $items['customer-logout'] );
            }
            $items[ self::ENDPOINT ] = __( 'Company Credit', 'storelly-product-builder-for-woocommerce' );
            if ( null !== $logout ) {
                $items['customer-logout'] = $logout;
            }
            return $items;
        }

        public static function render_endpoint() {
            if ( ! is_user_logged_in() ) {
                wc_get_template( 'myaccount/form-login.php' );
                return;
            }
            $company_id = SPBWC_Company::get_user_company_id();
            if ( ! $company_id ) {
                echo '<p>' . esc_html__( 'You are not part of a B2B company.', 'storelly-product-builder-for-woocommerce' ) . '</p>';
                return;
            }
            if ( class_exists( 'SPBWC_B2B_Assets' ) ) {
                SPBWC_B2B_Assets::storefront();
            }

            $limit       = self::credit_limit( $company_id );
            $balance     = SPBWC_B2B_Ledger::get_balance( $company_id );
            $wallet      = SPBWC_B2B_Ledger::get_wallet( $company_id );
            $outstanding = SPBWC_B2B_Ledger::get_outstanding( $company_id );
            $available   = SPBWC_B2B_Ledger::get_available_credit( $company_id, $limit );

            // Summary cards.
            echo '<div class="spbwc-credit-cards">';
            self::summary_card( __( 'Available credit', 'storelly-product-builder-for-woocommerce' ), wc_price( $available ), 'primary' );
            self::summary_card( __( 'Wallet balance', 'storelly-product-builder-for-woocommerce' ), wc_price( $wallet ), '' );
            if ( $limit > 0 ) {
                self::summary_card( __( 'Outstanding', 'storelly-product-builder-for-woocommerce' ), wc_price( $outstanding ), 'warn' );
                self::summary_card( __( 'Credit limit', 'storelly-product-builder-for-woocommerce' ), wc_price( $limit ), '' );
            }
            echo '</div>';

            echo '<p class="description">' . esc_html__( 'Top-ups and payments are recorded by the store. Contact the store to add funds or settle a balance.', 'storelly-product-builder-for-woocommerce' ) . '</p>';

            // Aging of outstanding net-terms invoices (only when there is debt).
            if ( $outstanding > 0 ) {
                $aging   = SPBWC_B2B_Ledger::get_aging( $company_id );
                $buckets = array(
                    'current' => __( 'Current', 'storelly-product-builder-for-woocommerce' ),
                    'd30'     => __( '1–30 days', 'storelly-product-builder-for-woocommerce' ),
                    'd60'     => __( '31–60 days', 'storelly-product-builder-for-woocommerce' ),
                    'd90'     => __( '60+ days', 'storelly-product-builder-for-woocommerce' ),
                );
                echo '<h3>' . esc_html__( 'Outstanding by age', 'storelly-product-builder-for-woocommerce' ) . '</h3>';
                echo '<div class="spbwc-credit-cards">';
                foreach ( $buckets as $key => $label ) {
                    $amount = isset( $aging[ $key ] ) ? (float) $aging[ $key ] : 0;
                    $tone   = ( 'current' !== $key && $amount > 0 ) ? 'warn' : '';
                    self::summary_card( $label, wc_price( $amount ), $tone );
                }
                echo '</div>';
            }

            // Statement.
            $rows = SPBWC_B2B_Ledger::get_statement( $company_id, 50 );
            echo '<h3>' . esc_html__( 'Account statement', 'storelly-product-builder-for-woocommerce' ) . '</h3>';
            if ( empty( $rows ) ) {
                echo '<p>' . esc_html__( 'No transactions yet.', 'storelly-product-builder-for-woocommerce' ) . '</p>';
                return;
            }
            echo '<table class="woocommerce-orders-table shop_table spbwc-credit-statement"><thead><tr>';
            echo '<th>' . esc_html__( 'Date', 'storelly-product-builder-for-woocommerce' ) . '</th>';
            echo '<th>' . esc_html__( 'Type', 'storelly-product-builder-for-woocommerce' ) . '</th>';
            echo '<th>' . esc_html__( 'Reference', 'storelly-product-builder-for-woocommerce' ) . '</th>';
            echo '<th style="text-align:right">' . esc_html__( 'Debit', 'storelly-product-builder-for-woocommerce' ) . '</th>';
            echo '<th style="text-align:right">' . esc_html__( 'Credit', 'storelly-product-builder-for-woocommerce' ) . '</th>';
            echo '</tr></thead><tbody>';
            foreach ( $rows as $r ) {
                $ref = ( 'order' === $r->ref_type && $r->ref_id ) ? '#' . (int) $r->ref_id : '—';
                echo '<tr>';
                echo '<td>' . esc_html( mysql2date( get_option( 'date_format' ), $r->effective_date ) ) . '</td>';
                echo '<td>' . esc_html( SPBWC_B2B_Ledger::txn_label( $r->txn_type ) ) . '</td>';
                echo '<td>' . esc_html( $ref ) . '</td>';
                echo '<td style="text-align:right">' . ( (float) $r->debit > 0 ? wp_kses_post( wc_price( $r->debit ) ) : '' ) . '</td>';
                echo '<td style="text-align:right">' . ( (float) $r->credit > 0 ? wp_kses_post( wc_price( $r->credit ) ) : '' ) . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }

        protected static function summary_card( $label, $value_html, $tone ) {
            $cls = 'spbwc-credit-card' . ( $tone ? ' spbwc-credit-card--' . $tone : '' );
            echo '<div class="' . esc_attr( $cls ) . '"><span class="spbwc-credit-card__label">' . esc_html( $label ) . '</span><span class="spbwc-credit-card__value">' . wp_kses_post( $value_html ) . '</span></div>';
        }

        /* ── Payment gateway ──────────────────────────────────────── */

        /**
         * Register the gateway class with WooCommerce.
         *
         * @param array $gateways Registered gateway class names.
         * @return array
         */
        public static function register_gateway( $gateways ) {
            if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
                return $gateways;
            }
            require_once __DIR__ . '/class-b2b-credit-gateway.php';
            $gateways[] = 'SPBWC_Gateway_Company_Account';
            return $gateways;
        }

        /**
         * Atomically charge an order against its buyer's company credit. Called by
         * the gateway at process_payment time, and (with overdraft) when an
         * over-limit order is approved by a company admin.
         *
         * @param WC_Order $order           Order.
         * @param bool     $allow_overdraft Skip the available-credit check (the
         *                                   approver has authorised exceeding the line).
         * @return true|WP_Error
         */
        public static function charge_order( $order, $allow_overdraft = false ) {
            // Idempotency: never charge the same order twice.
            if ( $order->get_meta( self::ORDER_CHARGED ) ) {
                return true;
            }
            $user_id = $order->get_customer_id();
            $ctx     = self::context( $user_id );
            if ( ! $ctx['company'] ) {
                return new WP_Error( 'no_company', __( 'Your account is not linked to an active company.', 'storelly-product-builder-for-woocommerce' ) );
            }
            $amount = (float) $order->get_total();
            $limit  = self::credit_limit( $ctx['company'] );

            // Only the portion drawn on the credit line (negative balance) carries a
            // due date; wallet-funded spend has no invoice. Stamp due_date when the
            // company runs net terms so aging can track it.
            $days     = self::terms_days( $ctx['company'] );
            $due_date = ( $days > 0 && $limit > 0 )
                ? gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) + $days * DAY_IN_SECONDS ) // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
                : '';

            $row = SPBWC_B2B_Ledger::post_charge( $ctx['company'], $amount, array(
                'credit_limit'    => $limit,
                'allow_overdraft' => (bool) $allow_overdraft,
                'ref_type'        => 'order',
                'ref_id'          => $order->get_id(),
                'due_date'        => $due_date,
                'note'         => sprintf(
                    /* translators: %s: order number. */
                    __( 'Order %s', 'storelly-product-builder-for-woocommerce' ),
                    $order->get_order_number()
                ),
            ) );
            if ( is_wp_error( $row ) ) {
                return $row;
            }

            $order->update_meta_data( self::ORDER_COMPANY, $ctx['company'] );
            $order->update_meta_data( self::ORDER_CHARGED, $amount );
            $order->save();
            return true;
        }

        /**
         * Reverse the full remaining charge when an order is cancelled/failed.
         *
         * @param int $order_id Order id.
         */
        public static function reverse_charge( $order_id ) {
            $order = wc_get_order( $order_id );
            if ( ! $order ) {
                return;
            }
            $charged = (float) $order->get_meta( self::ORDER_CHARGED );
            self::apply_reversal(
                $order,
                $charged, // target: everything charged is credited back
                sprintf(
                    /* translators: %s: order number. */
                    __( 'Reversal — order %s cancelled', 'storelly-product-builder-for-woocommerce' ),
                    $order->get_order_number()
                )
            );
        }

        /**
         * Credit back refunds (partial or full) against the company account, so a
         * refunded order does not keep the company in debt for money returned.
         *
         * @param int $order_id  Order id.
         * @param int $refund_id Refund id (unused; we read the cumulative total).
         */
        public static function reverse_refund( $order_id, $refund_id = 0 ) {
            $order = wc_get_order( $order_id );
            if ( ! $order ) {
                return;
            }
            $charged  = (float) $order->get_meta( self::ORDER_CHARGED );
            $refunded = (float) $order->get_total_refunded();
            // Never credit back more than was charged.
            self::apply_reversal(
                $order,
                min( $refunded, $charged ),
                sprintf(
                    /* translators: %s: order number. */
                    __( 'Reversal — order %s refunded', 'storelly-product-builder-for-woocommerce' ),
                    $order->get_order_number()
                )
            );
        }

        /**
         * Post the delta needed to bring the order's reversed-total up to
         * $target_reversed (capped at the charged amount). Idempotent: ORDER_REVERSED
         * tracks the cumulative amount already credited back.
         *
         * @param WC_Order $order           Order.
         * @param float    $target_reversed Desired cumulative reversed amount.
         * @param string   $note            Ledger note.
         */
        protected static function apply_reversal( $order, $target_reversed, $note ) {
            $company = (int) $order->get_meta( self::ORDER_COMPANY );
            $charged = (float) $order->get_meta( self::ORDER_CHARGED );
            if ( ! $company || $charged <= 0 ) {
                return;
            }
            $already = (float) $order->get_meta( self::ORDER_REVERSED );
            $target  = min( (float) $target_reversed, $charged );
            $delta   = round( $target - $already, wc_get_rounding_precision() );
            if ( $delta <= 0 ) {
                return; // Nothing new to reverse.
            }
            SPBWC_B2B_Ledger::post_refund( $company, $delta, array(
                'ref_type' => 'order',
                'ref_id'   => $order->get_id(),
                'note'     => $note,
            ) );
            $order->update_meta_data( self::ORDER_REVERSED, $already + $delta );
            $order->save();
        }
    }
}
