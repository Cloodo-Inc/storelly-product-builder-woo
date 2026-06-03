<?php
/**
 * Custom Quotes admin workspace (B2B quote redesign, M2).
 *
 * Registers the "Custom Quotes" submenu and renders two screens against the
 * `spbwc_quote` CPT:
 *   - list  : SPBWC_Quotes_List_Table (status tabs, search, bulk actions)
 *   - detail: request recap + merchant pricing-reply builder (D2) + terms +
 *             activity timeline, with Save draft / Send pricing reply /
 *             Send counter-offer / Withdraw actions.
 *
 * Built on the Storelly design tokens + shared component library; quote-only
 * styling lives in static/css/quotes-admin.css.
 *
 * @package Storelly
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'SPBWC_Quote_Admin' ) ) {

    class SPBWC_Quote_Admin {

        const PAGE_SLUG  = 'storelly-product-builder-for-woocommerce-custom-quotes';
        const CAPABILITY = 'manage_woocommerce';

        /** @var SPBWC_Quote_Admin|null */
        protected static $instance;

        /** @var string Notice code set during action handling. */
        protected $notice = '';

        public static function instance() {
            if ( is_null( self::$instance ) ) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        public function init() {
            add_action( 'admin_menu', array( $this, 'register_menu' ), 20 );
        }

        public function register_menu() {
            $menu_title = esc_html__( 'Quotes', 'storelly-product-builder-for-woocommerce' );
            $new        = self::count_new_quotes();
            if ( $new > 0 ) {
                $menu_title .= ' <span class="awaiting-mod"><span class="pending-count">' . esc_html( number_format_i18n( $new ) ) . '</span></span>';
            }
            add_submenu_page(
                SPBWC_PB_OVERVIEW_SLUG,
                esc_html__( 'Quotes', 'storelly-product-builder-for-woocommerce' ),
                $menu_title,
                self::CAPABILITY,
                self::PAGE_SLUG,
                array( $this, 'render' )
            );
        }

        /** Number of quotes awaiting a merchant reply (new). */
        public static function count_new_quotes() {
            $counts = (array) wp_count_posts( SPBWC_Quote::POST_TYPE );
            return isset( $counts[ SPBWC_Quote::STATUS_NEW ] ) ? (int) $counts[ SPBWC_Quote::STATUS_NEW ] : 0;
        }

        /* ── URL + pill helpers ───────────────────────────────────── */

        /**
         * Build a URL to the workspace page.
         *
         * @param array $args Extra query args.
         * @return string
         */
        public static function page_url( $args = array() ) {
            $args = array_merge( array( 'page' => self::PAGE_SLUG ), $args );
            return add_query_arg( $args, admin_url( 'admin.php' ) );
        }

        /**
         * Render a colored status pill for a quote status.
         *
         * @param string $status Status slug.
         * @return string HTML.
         */
        public static function status_pill( $status ) {
            $map = array(
                SPBWC_Quote::STATUS_NEW         => 'warn',
                SPBWC_Quote::STATUS_REVIEW      => 'neutral',
                SPBWC_Quote::STATUS_SENT        => 'info',
                SPBWC_Quote::STATUS_NEGOTIATING => 'warn',
                SPBWC_Quote::STATUS_SUPERSEDED  => 'off',
                SPBWC_Quote::STATUS_ACCEPTED    => 'success',
                SPBWC_Quote::STATUS_CONVERTED   => 'success',
                SPBWC_Quote::STATUS_DECLINED    => 'danger',
                SPBWC_Quote::STATUS_EXPIRED     => 'off',
                SPBWC_Quote::STATUS_WITHDRAWN   => 'off',
            );
            $statuses = SPBWC_Quote::statuses();
            $variant  = isset( $map[ $status ] ) ? $map[ $status ] : 'neutral';
            $label    = isset( $statuses[ $status ] ) ? $statuses[ $status ] : $status;
            return '<span class="spbwc-pill spbwc-pill--' . esc_attr( $variant ) . '">' . esc_html( $label ) . '</span>';
        }

        /* ── Routing ──────────────────────────────────────────────── */

        public function render() {
            if ( ! current_user_can( self::CAPABILITY ) ) {
                wp_die( esc_html__( 'You do not have permission to access this page.', 'storelly-product-builder-for-woocommerce' ) );
            }
            $this->maybe_handle_actions();

            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only routing arg.
            $quote_id = isset( $_GET['quote'] ) ? absint( wp_unslash( $_GET['quote'] ) ) : 0;
            $post     = $quote_id ? get_post( $quote_id ) : null;
            if ( $post && SPBWC_Quote::POST_TYPE === $post->post_type ) {
                $this->render_detail( $post );
            } else {
                $this->render_list();
            }
        }

        /* ── Action handling ──────────────────────────────────────── */

        protected function maybe_handle_actions() {
            if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
                $this->maybe_handle_bulk();
                return;
            }
            if ( ! isset( $_POST['spbwc_quote_do'] ) ) {
                return;
            }
            check_admin_referer( 'spbwc_quote_reply', 'spbwc_quote_reply_nonce' );
            if ( ! current_user_can( self::CAPABILITY ) ) {
                return;
            }
            $quote_id = isset( $_POST['quote_id'] ) ? absint( wp_unslash( $_POST['quote_id'] ) ) : 0;
            $post     = $quote_id ? get_post( $quote_id ) : null;
            if ( ! $post || SPBWC_Quote::POST_TYPE !== $post->post_type ) {
                return;
            }
            $do = sanitize_key( wp_unslash( $_POST['spbwc_quote_do'] ) );

            if ( 'withdraw' === $do ) {
                $res = SPBWC_Quote::set_status( $quote_id, SPBWC_Quote::STATUS_WITHDRAWN, __( 'Quote withdrawn by merchant.', 'storelly-product-builder-for-woocommerce' ) );
                $this->redirect_detail( $quote_id, is_wp_error( $res ) ? 'error' : 'withdrawn' );
                return;
            }

            // Persist the pricing reply (lines + terms) for save / send / counter.
            $this->save_reply( $quote_id );

            if ( 'send' === $do || 'counter' === $do ) {
                $from = $post->post_status;
                $note = ( SPBWC_Quote::STATUS_NEGOTIATING === $from )
                    ? __( 'Revised quote sent to customer.', 'storelly-product-builder-for-woocommerce' )
                    : __( 'Quote sent to customer.', 'storelly-product-builder-for-woocommerce' );
                if ( SPBWC_Quote::STATUS_NEGOTIATING === $from ) {
                    $rev = (int) get_post_meta( $quote_id, SPBWC_Quote::META_REVISION, true );
                    update_post_meta( $quote_id, SPBWC_Quote::META_REVISION, $rev + 1 );
                }
                $res = SPBWC_Quote::set_status( $quote_id, SPBWC_Quote::STATUS_SENT, $note );
                if ( ! is_wp_error( $res ) ) {
                    do_action( 'spbwc_quote_sent_notification', $quote_id );
                }
                $this->redirect_detail( $quote_id, is_wp_error( $res ) ? 'error' : 'sent' );
                return;
            }

            $this->redirect_detail( $quote_id, 'saved' );
        }

        /**
         * Persist posted line items + terms onto the quote.
         *
         * @param int $quote_id Quote post ID.
         */
        protected function save_reply( $quote_id ) {
            // phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verified in maybe_handle_actions(); each value sanitized below.
            $labels = isset( $_POST['line_label'] ) ? (array) wp_unslash( $_POST['line_label'] ) : array();
            $descs  = isset( $_POST['line_desc'] ) ? (array) wp_unslash( $_POST['line_desc'] ) : array();
            $qtys   = isset( $_POST['line_qty'] ) ? (array) wp_unslash( $_POST['line_qty'] ) : array();
            $prices = isset( $_POST['line_price'] ) ? (array) wp_unslash( $_POST['line_price'] ) : array();
            // phpcs:enable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

            $rows  = array();
            $count = max( count( $labels ), count( $qtys ), count( $prices ) );
            for ( $i = 0; $i < $count; $i++ ) {
                $rows[] = array(
                    'label'      => isset( $labels[ $i ] ) ? sanitize_text_field( $labels[ $i ] ) : '',
                    'desc'       => isset( $descs[ $i ] ) ? sanitize_textarea_field( $descs[ $i ] ) : '',
                    'qty'        => isset( $qtys[ $i ] ) ? (float) $qtys[ $i ] : 0,
                    'unit_price' => isset( $prices[ $i ] ) ? (float) $prices[ $i ] : 0,
                );
            }
            SPBWC_Quote::set_lines( $quote_id, $rows );

            // Apply manual discount + tax on top of the recomputed subtotal.
            // phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verified in maybe_handle_actions(); each value sanitized below.
            $discount = isset( $_POST['quote_discount'] ) ? (float) wp_unslash( $_POST['quote_discount'] ) : 0;
            $tax      = isset( $_POST['quote_tax'] ) ? (float) wp_unslash( $_POST['quote_tax'] ) : 0;
            $valid    = isset( $_POST['quote_valid_until'] ) ? sanitize_text_field( wp_unslash( $_POST['quote_valid_until'] ) ) : '';
            $terms    = isset( $_POST['quote_payment_terms'] ) ? sanitize_key( wp_unslash( $_POST['quote_payment_terms'] ) ) : 'prepay';
            $note     = isset( $_POST['quote_customer_note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['quote_customer_note'] ) ) : '';
            // phpcs:enable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

            $totals             = SPBWC_Quote::get_totals( $quote_id );
            $subtotal           = isset( $totals['subtotal'] ) ? (float) $totals['subtotal'] : 0;
            $totals['discount'] = round( $discount, 2 );
            $totals['tax']      = round( $tax, 2 );
            $totals['total']    = round( $subtotal - $discount + $tax, 2 );
            update_post_meta( $quote_id, SPBWC_Quote::META_TOTALS, $totals );

            // Validate the date is YYYY-MM-DD before storing.
            if ( '' !== $valid && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $valid ) ) {
                $valid = '';
            }
            update_post_meta( $quote_id, SPBWC_Quote::META_VALID_UNTIL, $valid );
            update_post_meta( $quote_id, SPBWC_Quote::META_PAYMENT_TERMS, $terms ? $terms : 'prepay' );
            update_post_meta( $quote_id, SPBWC_Quote::META_CUSTOMER_NOTE, $note );
        }

        protected function maybe_handle_bulk() {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Verified below before any mutation.
            $action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';
            if ( '-1' === $action || '' === $action ) {
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Verified below before any mutation.
                $action = isset( $_REQUEST['action2'] ) ? sanitize_key( wp_unslash( $_REQUEST['action2'] ) ) : '';
            }
            if ( ! in_array( $action, array( 'mark_review', 'mark_expired', 'withdraw' ), true ) ) {
                return;
            }
            check_admin_referer( 'bulk-quotes' );
            if ( ! current_user_can( self::CAPABILITY ) ) {
                return;
            }
            $ids = isset( $_REQUEST['quote_ids'] ) ? array_map( 'absint', (array) wp_unslash( $_REQUEST['quote_ids'] ) ) : array();
            $map = array(
                'mark_review'  => SPBWC_Quote::STATUS_REVIEW,
                'mark_expired' => SPBWC_Quote::STATUS_EXPIRED,
                'withdraw'     => SPBWC_Quote::STATUS_WITHDRAWN,
            );
            $target = $map[ $action ];
            foreach ( $ids as $id ) {
                SPBWC_Quote::set_status( $id, $target );
            }
            wp_safe_redirect( self::page_url( array( 'msg' => 'bulk' ) ) );
            exit;
        }

        protected function redirect_detail( $quote_id, $msg ) {
            wp_safe_redirect( self::page_url( array( 'quote' => $quote_id, 'msg' => $msg ) ) );
            exit;
        }

        protected function notice_html() {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only notice flag after redirect.
            $msg = isset( $_GET['msg'] ) ? sanitize_key( wp_unslash( $_GET['msg'] ) ) : '';
            if ( '' === $msg ) {
                return;
            }
            $map = array(
                'saved'     => array( 'success', __( 'Quote saved.', 'storelly-product-builder-for-woocommerce' ) ),
                'sent'      => array( 'success', __( 'Quote sent to the customer.', 'storelly-product-builder-for-woocommerce' ) ),
                'withdrawn' => array( 'success', __( 'Quote withdrawn.', 'storelly-product-builder-for-woocommerce' ) ),
                'bulk'      => array( 'success', __( 'Quotes updated.', 'storelly-product-builder-for-woocommerce' ) ),
                'error'     => array( 'error', __( 'Action could not be completed.', 'storelly-product-builder-for-woocommerce' ) ),
            );
            if ( ! isset( $map[ $msg ] ) ) {
                return;
            }
            printf(
                '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
                esc_attr( $map[ $msg ][0] ),
                esc_html( $map[ $msg ][1] )
            );
        }

        /* ── List screen ──────────────────────────────────────────── */

        protected function render_hero( $title, $subtitle ) {
            ?>
            <header class="spbwc-page-hero">
                <div class="spbwc-page-hero__grid">
                    <div class="spbwc-page-hero__body">
                        <div class="spbwc-page-hero__eyebrow">
                            <span class="dashicons dashicons-admin-plugins" aria-hidden="true"></span>
                            <?php esc_html_e( 'Storelly Product Builder', 'storelly-product-builder-for-woocommerce' ); ?>
                        </div>
                        <h1 class="spbwc-page-hero__title">
                            <span class="dashicons dashicons-money-alt" aria-hidden="true"></span>
                            <?php echo esc_html( $title ); ?>
                        </h1>
                        <p class="spbwc-page-hero__subtitle"><?php echo esc_html( $subtitle ); ?></p>
                    </div>
                </div>
            </header>
            <?php
        }

        protected function render_list() {
            $table = new SPBWC_Quotes_List_Table();
            $table->prepare_items();
            ?>
            <div class="wrap spbwc-settings-wrap">
                <?php
                $this->render_hero(
                    __( 'Quotes', 'storelly-product-builder-for-woocommerce' ),
                    __( 'Review quote requests, reply with pricing, and track them through to conversion.', 'storelly-product-builder-for-woocommerce' )
                );
                $this->notice_html();
                $this->render_kpis();
                ?>
                <div class="spbwc-block spbwc-quotes-listwrap">
                    <div class="spbwc-list-toolbar">
                        <?php $table->views(); ?>
                        <form method="get" role="search" class="spbwc-quotes-searchform">
                            <input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>" />
                            <?php
                            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Preserve status tab on search (read-only).
                            $status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
                            if ( $status ) {
                                echo '<input type="hidden" name="status" value="' . esc_attr( $status ) . '" />';
                            }
                            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only search value.
                            $search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
                            ?>
                            <div class="spbwc-search-bar">
                                <span class="spbwc-search-bar__icon" aria-hidden="true"><span class="dashicons dashicons-search"></span></span>
                                <input class="spbwc-search-bar__input" type="search" name="s"
                                    value="<?php echo esc_attr( $search ); ?>"
                                    placeholder="<?php esc_attr_e( 'Search quotes…', 'storelly-product-builder-for-woocommerce' ); ?>" />
                                <button class="spbwc-search-bar__btn" type="submit"><?php esc_html_e( 'Search', 'storelly-product-builder-for-woocommerce' ); ?></button>
                            </div>
                        </form>
                    </div>
                    <form method="post">
                        <?php
                        if ( empty( $table->items ) ) {
                            $this->render_empty_state();
                        } else {
                            $table->display();
                        }
                        ?>
                    </form>
                </div>
            </div>
            <?php
        }

        /**
         * KPI summary cards above the list (new / awaiting + $ outstanding /
         * accepted in the last 30 days).
         */
        protected function render_kpis() {
            $new = self::count_new_quotes();

            // Awaiting buyer response + outstanding value.
            $sent_ids    = get_posts(
                array(
                    'post_type'   => SPBWC_Quote::POST_TYPE,
                    'post_status' => SPBWC_Quote::STATUS_SENT,
                    'numberposts' => -1,
                    'fields'      => 'ids',
                )
            );
            $outstanding = 0.0;
            $currency    = get_woocommerce_currency();
            foreach ( (array) $sent_ids as $sid ) {
                $t = SPBWC_Quote::get_totals( $sid );
                $outstanding += isset( $t['total'] ) ? (float) $t['total'] : 0;
            }

            // Accepted (incl. converted) in the last 30 days.
            $accepted = get_posts(
                array(
                    'post_type'   => SPBWC_Quote::POST_TYPE,
                    'post_status' => array( SPBWC_Quote::STATUS_ACCEPTED, SPBWC_Quote::STATUS_CONVERTED ),
                    'numberposts' => -1,
                    'fields'      => 'ids',
                    'date_query'  => array( array( 'after' => '30 days ago', 'column' => 'post_modified' ) ),
                )
            );

            $cards = array(
                array( 'icon' => 'email-alt', 'tone' => 'warn', 'value' => number_format_i18n( $new ), 'label' => __( 'New requests', 'storelly-product-builder-for-woocommerce' ) ),
                array( 'icon' => 'clock', 'tone' => 'info', 'value' => number_format_i18n( count( (array) $sent_ids ) ), 'label' => __( 'Awaiting response', 'storelly-product-builder-for-woocommerce' ), 'sub' => wp_strip_all_tags( wc_price( $outstanding, array( 'currency' => $currency ) ) ) . ' ' . __( 'outstanding', 'storelly-product-builder-for-woocommerce' ) ),
                array( 'icon' => 'yes-alt', 'tone' => 'ok', 'value' => number_format_i18n( count( (array) $accepted ) ), 'label' => __( 'Accepted (30 days)', 'storelly-product-builder-for-woocommerce' ) ),
            );
            ?>
            <div class="spbwc-q-kpis">
                <?php foreach ( $cards as $c ) : ?>
                    <div class="spbwc-q-kpi spbwc-q-kpi--<?php echo esc_attr( $c['tone'] ); ?>">
                        <span class="spbwc-q-kpi__icon dashicons dashicons-<?php echo esc_attr( $c['icon'] ); ?>" aria-hidden="true"></span>
                        <span class="spbwc-q-kpi__value"><?php echo esc_html( $c['value'] ); ?></span>
                        <span class="spbwc-q-kpi__label"><?php echo esc_html( $c['label'] ); ?></span>
                        <?php if ( ! empty( $c['sub'] ) ) : ?>
                            <span class="spbwc-q-kpi__sub"><?php echo esc_html( $c['sub'] ); ?></span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php
        }

        protected function render_empty_state() {
            ?>
            <div class="spbwc-empty-state">
                <div class="spbwc-empty-state__icon">
                    <span class="dashicons dashicons-money-alt" aria-hidden="true"></span>
                </div>
                <p class="spbwc-empty-state__title"><?php esc_html_e( 'No quotes yet', 'storelly-product-builder-for-woocommerce' ); ?></p>
                <p class="spbwc-empty-state__text"><?php esc_html_e( 'When customers request a quote it will appear here, ready for you to price and send.', 'storelly-product-builder-for-woocommerce' ); ?></p>
            </div>
            <?php
        }

        /* ── Detail screen ────────────────────────────────────────── */

        protected function render_detail( $post ) {
            $quote_id = (int) $post->ID;
            $status   = $post->post_status;
            $number   = get_post_meta( $quote_id, SPBWC_Quote::META_NUMBER, true );
            $request  = get_post_meta( $quote_id, SPBWC_Quote::META_REQUEST, true );
            $request  = is_array( $request ) ? $request : array();
            $lines    = SPBWC_Quote::get_lines( $quote_id );
            $totals   = SPBWC_Quote::get_totals( $quote_id );
            $valid    = (string) get_post_meta( $quote_id, SPBWC_Quote::META_VALID_UNTIL, true );
            $terms    = (string) get_post_meta( $quote_id, SPBWC_Quote::META_PAYMENT_TERMS, true );
            $note     = (string) get_post_meta( $quote_id, SPBWC_Quote::META_CUSTOMER_NOTE, true );
            $currency = isset( $totals['currency'] ) && $totals['currency'] ? $totals['currency'] : get_woocommerce_currency();
            $editable = in_array( $status, array( SPBWC_Quote::STATUS_NEW, SPBWC_Quote::STATUS_REVIEW, SPBWC_Quote::STATUS_NEGOTIATING ), true );
            if ( empty( $lines ) ) {
                // Seed an empty editable row so the merchant has somewhere to type.
                $lines = array( array( 'label' => '', 'desc' => '', 'qty' => 1, 'unit_price' => 0, 'line_total' => 0 ) );
            }
            ?>
            <div class="wrap spbwc-settings-wrap">
                <?php
                /* translators: %s: quote number e.g. Q-2026-0001 */
                $this->render_hero( sprintf( __( 'Quote %s', 'storelly-product-builder-for-woocommerce' ), $number ? $number : '#' . $quote_id ), __( 'Price the request and send it back to the customer.', 'storelly-product-builder-for-woocommerce' ) );
                $this->notice_html();
                ?>
                <div class="spbwc-q-detailbar">
                    <a class="spbwc-q-back" href="<?php echo esc_url( self::page_url() ); ?>">
                        <span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span>
                        <?php esc_html_e( 'Back to all quotes', 'storelly-product-builder-for-woocommerce' ); ?>
                    </a>
                    <?php
                    $preview_url = add_query_arg(
                        array(
                            'spbwc_preview' => 1,
                            '_wpnonce'      => wp_create_nonce( 'spbwc_quote_preview_' . $quote_id ),
                        ),
                        wc_get_endpoint_url( 'view-quote', $quote_id, wc_get_page_permalink( 'myaccount' ) )
                    );
                    ?>
                    <a class="spbwc-cta-btn spbwc-cta-btn--ghost spbwc-cta-btn--sm" href="<?php echo esc_url( $preview_url ); ?>" target="_blank" rel="noopener">
                        <span class="dashicons dashicons-visibility" aria-hidden="true"></span>
                        <?php esc_html_e( 'Preview customer view', 'storelly-product-builder-for-woocommerce' ); ?>
                    </a>
                </div>

                <form method="post" id="spbwc-quote-reply-form">
                    <?php wp_nonce_field( 'spbwc_quote_reply', 'spbwc_quote_reply_nonce' ); ?>
                    <input type="hidden" name="quote_id" value="<?php echo esc_attr( $quote_id ); ?>" />

                    <div class="spbwc-q-detail">
                        <div class="spbwc-q-detail__main">

                            <!-- Customer request recap -->
                            <div class="spbwc-block">
                                <div class="spbwc-block__head">
                                    <h3 class="spbwc-block__title">
                                        <span class="dashicons dashicons-feedback" aria-hidden="true"></span>
                                        <?php esc_html_e( 'Customer request', 'storelly-product-builder-for-woocommerce' ); ?>
                                    </h3>
                                </div>
                                <div class="spbwc-block__body">
                                    <div class="spbwc-q-recap">
                                        <?php $this->render_recap_rows( $request ); ?>
                                    </div>
                                </div>
                            </div>

                            <?php $this->render_change_request( $quote_id, $status ); ?>

                            <!-- Pricing reply (D2) -->
                            <div class="spbwc-block">
                                <div class="spbwc-block__head">
                                    <h3 class="spbwc-block__title">
                                        <span class="dashicons dashicons-money-alt" aria-hidden="true"></span>
                                        <?php esc_html_e( 'Pricing reply', 'storelly-product-builder-for-woocommerce' ); ?>
                                    </h3>
                                </div>
                                <div class="spbwc-block__body">
                                    <table class="spbwc-q-lines" id="spbwc-q-lines">
                                        <thead>
                                            <tr>
                                                <th><?php esc_html_e( 'Item', 'storelly-product-builder-for-woocommerce' ); ?></th>
                                                <th class="spbwc-q-num"><?php esc_html_e( 'Qty', 'storelly-product-builder-for-woocommerce' ); ?></th>
                                                <th class="spbwc-q-num"><?php esc_html_e( 'Unit price', 'storelly-product-builder-for-woocommerce' ); ?></th>
                                                <th class="spbwc-q-num"><?php esc_html_e( 'Total', 'storelly-product-builder-for-woocommerce' ); ?></th>
                                                <th class="spbwc-q-act">&nbsp;</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ( $lines as $line ) : ?>
                                                <?php $this->render_line_row( $line ); ?>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                    <p>
                                        <button type="button" class="spbwc-cta-btn spbwc-cta-btn--ghost spbwc-cta-btn--sm" id="spbwc-q-add-line">
                                            <span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
                                            <?php esc_html_e( 'Add line item', 'storelly-product-builder-for-woocommerce' ); ?>
                                        </button>
                                    </p>

                                    <div class="spbwc-q-totals">
                                        <div class="spbwc-q-totals__row">
                                            <span><?php esc_html_e( 'Subtotal', 'storelly-product-builder-for-woocommerce' ); ?></span>
                                            <span class="spbwc-q-line-total" id="spbwc-q-subtotal">0</span>
                                        </div>
                                        <div class="spbwc-q-totals__row">
                                            <label for="spbwc-q-discount"><?php esc_html_e( 'Discount', 'storelly-product-builder-for-woocommerce' ); ?></label>
                                            <input type="number" step="0.01" min="0" id="spbwc-q-discount" name="quote_discount" value="<?php echo esc_attr( isset( $totals['discount'] ) ? $totals['discount'] : 0 ); ?>" class="spbwc-q-line-input spbwc-q-line-input--num" />
                                        </div>
                                        <div class="spbwc-q-totals__row">
                                            <label for="spbwc-q-tax"><?php esc_html_e( 'Tax', 'storelly-product-builder-for-woocommerce' ); ?></label>
                                            <input type="number" step="0.01" min="0" id="spbwc-q-tax" name="quote_tax" value="<?php echo esc_attr( isset( $totals['tax'] ) ? $totals['tax'] : 0 ); ?>" class="spbwc-q-line-input spbwc-q-line-input--num" />
                                        </div>
                                        <div class="spbwc-q-totals__row spbwc-q-totals__row--grand">
                                            <span><?php esc_html_e( 'Total', 'storelly-product-builder-for-woocommerce' ); ?></span>
                                            <span class="spbwc-q-line-total" id="spbwc-q-grand">0</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Terms -->
                            <div class="spbwc-block">
                                <div class="spbwc-block__head">
                                    <h3 class="spbwc-block__title">
                                        <span class="dashicons dashicons-clipboard" aria-hidden="true"></span>
                                        <?php esc_html_e( 'Quote terms', 'storelly-product-builder-for-woocommerce' ); ?>
                                    </h3>
                                </div>
                                <div class="spbwc-block__body">
                                    <div class="spbwc-q-terms">
                                        <div class="spbwc-q-field">
                                            <label for="spbwc-q-validity"><?php esc_html_e( 'Validity', 'storelly-product-builder-for-woocommerce' ); ?></label>
                                            <select id="spbwc-q-validity-preset" class="spbwc-input">
                                                <option value=""><?php esc_html_e( 'Custom date…', 'storelly-product-builder-for-woocommerce' ); ?></option>
                                                <option value="7"><?php esc_html_e( '7 days', 'storelly-product-builder-for-woocommerce' ); ?></option>
                                                <option value="14"><?php esc_html_e( '14 days', 'storelly-product-builder-for-woocommerce' ); ?></option>
                                                <option value="30"><?php esc_html_e( '30 days', 'storelly-product-builder-for-woocommerce' ); ?></option>
                                                <option value="60"><?php esc_html_e( '60 days', 'storelly-product-builder-for-woocommerce' ); ?></option>
                                            </select>
                                            <input type="date" id="spbwc-q-validity" name="quote_valid_until" value="<?php echo esc_attr( $valid ); ?>" class="spbwc-input" />
                                        </div>
                                        <div class="spbwc-q-field">
                                            <label for="spbwc-q-terms"><?php esc_html_e( 'Payment terms', 'storelly-product-builder-for-woocommerce' ); ?></label>
                                            <select id="spbwc-q-terms" name="quote_payment_terms" class="spbwc-input">
                                                <option value="prepay" <?php selected( $terms ? $terms : 'prepay', 'prepay' ); ?>><?php esc_html_e( 'Pre-payment', 'storelly-product-builder-for-woocommerce' ); ?></option>
                                            </select>
                                            <span class="spbwc-setting-row__hint"><?php esc_html_e( 'Net terms & deposits arrive in a later release.', 'storelly-product-builder-for-woocommerce' ); ?></span>
                                        </div>
                                        <div class="spbwc-q-field" style="grid-column:1/-1;">
                                            <label for="spbwc-q-note"><?php esc_html_e( 'Note to customer', 'storelly-product-builder-for-woocommerce' ); ?></label>
                                            <textarea id="spbwc-q-note" name="quote_customer_note" rows="3" class="spbwc-input"><?php echo esc_textarea( $note ); ?></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Action bar -->
                            <div class="spbwc-block">
                                <div class="spbwc-block__body">
                                    <div class="spbwc-q-actionbar">
                                        <?php if ( $editable ) : ?>
                                            <button type="submit" name="spbwc_quote_do" value="save" class="spbwc-cta-btn spbwc-cta-btn--ghost">
                                                <span class="dashicons dashicons-saved" aria-hidden="true"></span>
                                                <?php esc_html_e( 'Save draft', 'storelly-product-builder-for-woocommerce' ); ?>
                                            </button>
                                            <?php if ( SPBWC_Quote::STATUS_NEGOTIATING === $status ) : ?>
                                                <button type="submit" name="spbwc_quote_do" value="counter" class="spbwc-cta-btn spbwc-cta-btn--solid">
                                                    <span class="dashicons dashicons-update" aria-hidden="true"></span>
                                                    <?php esc_html_e( 'Send counter-offer', 'storelly-product-builder-for-woocommerce' ); ?>
                                                </button>
                                            <?php else : ?>
                                                <button type="submit" name="spbwc_quote_do" value="send" class="spbwc-cta-btn spbwc-cta-btn--solid">
                                                    <span class="dashicons dashicons-email-alt" aria-hidden="true"></span>
                                                    <?php esc_html_e( 'Send pricing reply', 'storelly-product-builder-for-woocommerce' ); ?>
                                                </button>
                                            <?php endif; ?>
                                            <span class="spbwc-q-spacer"></span>
                                            <button type="submit" name="spbwc_quote_do" value="withdraw" class="spbwc-cta-btn spbwc-cta-btn--ghost">
                                                <?php esc_html_e( 'Withdraw', 'storelly-product-builder-for-woocommerce' ); ?>
                                            </button>
                                        <?php else : ?>
                                            <p class="spbwc-setting-row__hint" style="margin:0;border:0;">
                                                <?php esc_html_e( 'This quote is locked — its current status does not allow further edits.', 'storelly-product-builder-for-woocommerce' ); ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Sidebar -->
                        <div class="spbwc-q-detail__side">
                            <div class="spbwc-block">
                                <div class="spbwc-block__head">
                                    <h3 class="spbwc-block__title">
                                        <span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
                                        <?php esc_html_e( 'Overview', 'storelly-product-builder-for-woocommerce' ); ?>
                                    </h3>
                                </div>
                                <div class="spbwc-block__body">
                                    <div class="spbwc-q-meta">
                                        <div class="spbwc-q-meta__row">
                                            <span class="spbwc-q-meta__label"><?php esc_html_e( 'Status', 'storelly-product-builder-for-woocommerce' ); ?></span>
                                            <span class="spbwc-q-meta__value"><?php echo self::status_pill( $status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- status_pill returns escaped markup. ?></span>
                                        </div>
                                        <div class="spbwc-q-meta__row">
                                            <span class="spbwc-q-meta__label"><?php esc_html_e( 'Customer', 'storelly-product-builder-for-woocommerce' ); ?></span>
                                            <span class="spbwc-q-meta__value"><?php echo esc_html( SPBWC_Quotes_List_Table::derive_customer( $request, (int) $post->post_author ) ); ?></span>
                                        </div>
                                        <?php if ( ! empty( $request['email'] ) ) : ?>
                                            <div class="spbwc-q-meta__row">
                                                <span class="spbwc-q-meta__label"><?php esc_html_e( 'Email', 'storelly-product-builder-for-woocommerce' ); ?></span>
                                                <span class="spbwc-q-meta__value"><?php echo esc_html( $request['email'] ); ?></span>
                                            </div>
                                        <?php endif; ?>
                                        <div class="spbwc-q-meta__row">
                                            <span class="spbwc-q-meta__label"><?php esc_html_e( 'Created', 'storelly-product-builder-for-woocommerce' ); ?></span>
                                            <span class="spbwc-q-meta__value"><?php echo esc_html( get_the_date( get_option( 'date_format' ), $post ) ); ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="spbwc-block">
                                <div class="spbwc-block__head">
                                    <h3 class="spbwc-block__title">
                                        <span class="dashicons dashicons-backup" aria-hidden="true"></span>
                                        <?php esc_html_e( 'Activity', 'storelly-product-builder-for-woocommerce' ); ?>
                                    </h3>
                                </div>
                                <div class="spbwc-block__body">
                                    <?php $this->render_timeline( $quote_id ); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <?php
            $this->render_detail_script( $currency );
        }

        /**
         * Show the buyer's change request (asks + details) when the quote is
         * being negotiated, so the merchant knows what to revise.
         *
         * @param int    $quote_id Quote post ID.
         * @param string $status   Current status.
         */
        protected function render_change_request( $quote_id, $status ) {
            if ( SPBWC_Quote::STATUS_NEGOTIATING !== $status ) {
                return;
            }
            $cr = get_post_meta( $quote_id, '_spbwc_quote_change_request', true );
            if ( ! is_array( $cr ) ) {
                return;
            }
            $asks    = isset( $cr['asks'] ) && is_array( $cr['asks'] ) ? $cr['asks'] : array();
            $details = isset( $cr['details'] ) ? (string) $cr['details'] : '';
            $labels  = SPBWC_Quote::change_ask_labels();
            ?>
            <div class="spbwc-block spbwc-block--warning">
                <div class="spbwc-block__head">
                    <h3 class="spbwc-block__title">
                        <span class="dashicons dashicons-update" aria-hidden="true"></span>
                        <?php esc_html_e( 'Customer requested changes', 'storelly-product-builder-for-woocommerce' ); ?>
                    </h3>
                </div>
                <div class="spbwc-block__body">
                    <?php if ( ! empty( $asks ) ) : ?>
                        <ul class="spbwc-q-asks">
                            <?php foreach ( $asks as $ask ) : ?>
                                <li><span class="dashicons dashicons-yes" aria-hidden="true"></span> <?php echo esc_html( isset( $labels[ $ask ] ) ? $labels[ $ask ] : ucfirst( str_replace( '_', ' ', $ask ) ) ); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    <?php if ( '' !== $details ) : ?>
                        <p class="spbwc-q-asks__details"><?php echo nl2br( esc_html( $details ) ); ?></p>
                    <?php endif; ?>
                    <?php if ( empty( $asks ) && '' === $details ) : ?>
                        <p class="spbwc-admin-table__muted"><?php esc_html_e( 'The customer asked for changes without specifics.', 'storelly-product-builder-for-woocommerce' ); ?></p>
                    <?php endif; ?>
                    <p class="spbwc-setting-row__hint" style="margin:8px 0 0;border:0;">
                        <?php esc_html_e( 'Revise the pricing below and use “Send counter-offer”.', 'storelly-product-builder-for-woocommerce' ); ?>
                    </p>
                </div>
            </div>
            <?php
        }

        /**
         * Render the known request fields as recap rows.
         *
         * @param array $request Request meta.
         */
        protected function render_recap_rows( array $request ) {
            $labels = array(
                'company'      => __( 'Company', 'storelly-product-builder-for-woocommerce' ),
                'first_name'   => __( 'First name', 'storelly-product-builder-for-woocommerce' ),
                'last_name'    => __( 'Last name', 'storelly-product-builder-for-woocommerce' ),
                'email'        => __( 'Email', 'storelly-product-builder-for-woocommerce' ),
                'phone'        => __( 'Phone', 'storelly-product-builder-for-woocommerce' ),
                'product_name' => __( 'Product', 'storelly-product-builder-for-woocommerce' ),
                'quantity'     => __( 'Quantity', 'storelly-product-builder-for-woocommerce' ),
                'message'      => __( 'Message', 'storelly-product-builder-for-woocommerce' ),
            );
            $shown = false;
            foreach ( $labels as $key => $label ) {
                if ( empty( $request[ $key ] ) ) {
                    continue;
                }
                $shown = true;
                echo '<div class="spbwc-q-recap__label">' . esc_html( $label ) . '</div>';
                echo '<div class="spbwc-q-recap__value">' . esc_html( (string) $request[ $key ] ) . '</div>';
            }
            // Any extra custom fields submitted via the form builder.
            if ( ! empty( $request['fields'] ) && is_array( $request['fields'] ) ) {
                foreach ( $request['fields'] as $k => $v ) {
                    if ( '' === (string) $v || isset( $labels[ $k ] ) ) {
                        continue;
                    }
                    $shown = true;
                    echo '<div class="spbwc-q-recap__label">' . esc_html( ucwords( str_replace( '_', ' ', (string) $k ) ) ) . '</div>';
                    echo '<div class="spbwc-q-recap__value">' . esc_html( (string) $v ) . '</div>';
                }
            }
            if ( ! $shown ) {
                echo '<div class="spbwc-q-recap__value">' . esc_html__( 'No request details captured.', 'storelly-product-builder-for-woocommerce' ) . '</div>';
            }
        }

        /**
         * Render one editable line-item row.
         *
         * @param array $line Line data.
         */
        protected function render_line_row( array $line ) {
            $qty   = isset( $line['qty'] ) ? (float) $line['qty'] : 0;
            $unit  = isset( $line['unit_price'] ) ? (float) $line['unit_price'] : 0;
            $total = isset( $line['line_total'] ) ? (float) $line['line_total'] : round( $qty * $unit, 2 );
            ?>
            <tr class="spbwc-q-line">
                <td>
                    <input type="text" name="line_label[]" class="spbwc-q-line-input" value="<?php echo esc_attr( isset( $line['label'] ) ? $line['label'] : '' ); ?>" placeholder="<?php esc_attr_e( 'Description', 'storelly-product-builder-for-woocommerce' ); ?>" />
                </td>
                <td class="spbwc-q-num">
                    <input type="number" step="1" min="0" name="line_qty[]" class="spbwc-q-line-input spbwc-q-line-input--num spbwc-q-qty" value="<?php echo esc_attr( $qty ); ?>" />
                </td>
                <td class="spbwc-q-num">
                    <input type="number" step="0.01" min="0" name="line_price[]" class="spbwc-q-line-input spbwc-q-line-input--num spbwc-q-price" value="<?php echo esc_attr( $unit ); ?>" />
                </td>
                <td class="spbwc-q-num">
                    <span class="spbwc-q-line-total spbwc-q-rowtotal"><?php echo esc_html( number_format_i18n( $total, 2 ) ); ?></span>
                </td>
                <td class="spbwc-q-act">
                    <button type="button" class="spbwc-cta-btn spbwc-cta-btn--ghost spbwc-cta-btn--sm spbwc-q-remove-line" aria-label="<?php esc_attr_e( 'Remove line', 'storelly-product-builder-for-woocommerce' ); ?>">
                        <span class="dashicons dashicons-trash" aria-hidden="true"></span>
                    </button>
                </td>
            </tr>
            <?php
        }

        protected function render_timeline( $quote_id ) {
            $events = SPBWC_Quote::get_timeline( $quote_id );
            if ( empty( $events ) ) {
                echo '<p class="spbwc-admin-table__muted">' . esc_html__( 'No activity yet.', 'storelly-product-builder-for-woocommerce' ) . '</p>';
                return;
            }
            echo '<div class="spbwc-q-timeline">';
            foreach ( $events as $event ) {
                echo '<div class="spbwc-q-timeline__item">';
                echo '<span class="spbwc-q-timeline__dot" aria-hidden="true"></span>';
                echo '<div class="spbwc-q-timeline__body">' . esc_html( $event->comment_content ) . '</div>';
                echo '<div class="spbwc-q-timeline__time">' . esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $event->comment_date ) ) . '</div>';
                echo '</div>';
            }
            echo '</div>';
        }

        /**
         * Inline JS: line add/remove, live totals, validity preset.
         *
         * @param string $currency Currency code for display.
         */
        protected function render_detail_script( $currency ) {
            $symbol = function_exists( 'get_woocommerce_currency_symbol' ) ? html_entity_decode( get_woocommerce_currency_symbol( $currency ) ) : '';
            ?>
            <script>
            (function () {
                'use strict';
                var sym = <?php echo wp_json_encode( $symbol ); ?>;
                var table = document.getElementById('spbwc-q-lines');
                if (!table) { return; }
                var tbody = table.querySelector('tbody');

                function money(n) { return sym + n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }

                function recalc() {
                    var subtotal = 0;
                    tbody.querySelectorAll('tr.spbwc-q-line').forEach(function (tr) {
                        var qty = parseFloat(tr.querySelector('.spbwc-q-qty').value) || 0;
                        var price = parseFloat(tr.querySelector('.spbwc-q-price').value) || 0;
                        var lt = Math.round(qty * price * 100) / 100;
                        tr.querySelector('.spbwc-q-rowtotal').textContent = lt.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                        subtotal += lt;
                    });
                    var discount = parseFloat(document.getElementById('spbwc-q-discount').value) || 0;
                    var tax = parseFloat(document.getElementById('spbwc-q-tax').value) || 0;
                    document.getElementById('spbwc-q-subtotal').textContent = money(subtotal);
                    document.getElementById('spbwc-q-grand').textContent = money(Math.round((subtotal - discount + tax) * 100) / 100);
                }

                function rowTemplate() {
                    var tr = document.createElement('tr');
                    tr.className = 'spbwc-q-line';
                    tr.innerHTML =
                        '<td><input type="text" name="line_label[]" class="spbwc-q-line-input" value="" /></td>' +
                        '<td class="spbwc-q-num"><input type="number" step="1" min="0" name="line_qty[]" class="spbwc-q-line-input spbwc-q-line-input--num spbwc-q-qty" value="1" /></td>' +
                        '<td class="spbwc-q-num"><input type="number" step="0.01" min="0" name="line_price[]" class="spbwc-q-line-input spbwc-q-line-input--num spbwc-q-price" value="0" /></td>' +
                        '<td class="spbwc-q-num"><span class="spbwc-q-line-total spbwc-q-rowtotal">0.00</span></td>' +
                        '<td class="spbwc-q-act"><button type="button" class="spbwc-cta-btn spbwc-cta-btn--ghost spbwc-cta-btn--sm spbwc-q-remove-line"><span class="dashicons dashicons-trash"></span></button></td>';
                    return tr;
                }

                document.getElementById('spbwc-q-add-line').addEventListener('click', function () {
                    tbody.appendChild(rowTemplate());
                });
                table.addEventListener('click', function (e) {
                    var btn = e.target.closest('.spbwc-q-remove-line');
                    if (!btn) { return; }
                    var rows = tbody.querySelectorAll('tr.spbwc-q-line');
                    if (rows.length > 1) { btn.closest('tr').remove(); }
                    else { btn.closest('tr').querySelectorAll('input').forEach(function (i) { i.value = i.type === 'number' ? '0' : ''; }); }
                    recalc();
                });
                table.addEventListener('input', recalc);
                document.getElementById('spbwc-q-discount').addEventListener('input', recalc);
                document.getElementById('spbwc-q-tax').addEventListener('input', recalc);

                var preset = document.getElementById('spbwc-q-validity-preset');
                if (preset) {
                    preset.addEventListener('change', function () {
                        var d = parseInt(this.value, 10);
                        if (!d) { return; }
                        var dt = new Date();
                        dt.setDate(dt.getDate() + d);
                        document.getElementById('spbwc-q-validity').value = dt.toISOString().slice(0, 10);
                    });
                }

                recalc();
            }());
            </script>
            <?php
        }
    }
}
