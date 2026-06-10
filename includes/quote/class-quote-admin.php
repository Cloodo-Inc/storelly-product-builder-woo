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
            add_action( 'wp_ajax_spbwc_save_quote_template', array( $this, 'ajax_save_template' ) );
            add_action( 'wp_ajax_spbwc_delete_quote_template', array( $this, 'ajax_delete_template' ) );
            add_action( 'wp_ajax_spbwc_quote_action', array( $this, 'ajax_quote_action' ) );
            // Sample-quote seeding for the empty state (M6).
            require_once __DIR__ . '/class-quote-sample.php';
            if ( class_exists( 'SPBWC_Quote_Sample' ) ) {
                SPBWC_Quote_Sample::init();
            }
        }

        /** AJAX: save the posted pricing reply as a named template. */
        public function ajax_save_template() {
            check_ajax_referer( 'spbwc_quote_template', 'nonce' );
            if ( ! current_user_can( self::CAPABILITY ) ) {
                wp_send_json_error( array( 'message' => esc_html__( 'Permission denied.', 'storelly-product-builder-for-woocommerce' ) ) );
            }
            $name   = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
            // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized in SPBWC_Quote_Template::sanitize_lines().
            $labels = isset( $_POST['line_label'] ) ? (array) wp_unslash( $_POST['line_label'] ) : array();
            $descs  = isset( $_POST['line_desc'] ) ? (array) wp_unslash( $_POST['line_desc'] ) : array();
            $qtys   = isset( $_POST['line_qty'] ) ? (array) wp_unslash( $_POST['line_qty'] ) : array();
            $prices = isset( $_POST['line_price'] ) ? (array) wp_unslash( $_POST['line_price'] ) : array();
            // phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $rows  = array();
            $count = max( count( $labels ), count( $qtys ), count( $prices ) );
            for ( $i = 0; $i < $count; $i++ ) {
                $rows[] = array(
                    'label'      => isset( $labels[ $i ] ) ? $labels[ $i ] : '',
                    'desc'       => isset( $descs[ $i ] ) ? $descs[ $i ] : '',
                    'qty'        => isset( $qtys[ $i ] ) ? $qtys[ $i ] : 0,
                    'unit_price' => isset( $prices[ $i ] ) ? $prices[ $i ] : 0,
                );
            }
            $terms = array(
                'valid_days'    => isset( $_POST['valid_days'] ) ? (int) $_POST['valid_days'] : 0,
                'payment_terms' => isset( $_POST['payment_terms'] ) ? sanitize_key( wp_unslash( $_POST['payment_terms'] ) ) : 'prepay',
                'note'          => isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '',
            );
            $res = SPBWC_Quote_Template::create( $name, $rows, $terms );
            if ( is_wp_error( $res ) ) {
                wp_send_json_error( array( 'message' => $res->get_error_message() ) );
            }
            wp_send_json_success( array( 'templates' => SPBWC_Quote_Template::get_all() ) );
        }

        /** AJAX: delete a template. */
        public function ajax_delete_template() {
            check_ajax_referer( 'spbwc_quote_template', 'nonce' );
            if ( ! current_user_can( self::CAPABILITY ) ) {
                wp_send_json_error( array( 'message' => esc_html__( 'Permission denied.', 'storelly-product-builder-for-woocommerce' ) ) );
            }
            $tid = isset( $_POST['template_id'] ) ? absint( wp_unslash( $_POST['template_id'] ) ) : 0;
            SPBWC_Quote_Template::delete( $tid );
            wp_send_json_success( array( 'templates' => SPBWC_Quote_Template::get_all() ) );
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

            $res = $this->do_quote_action( $quote_id, $do, $post );
            $this->redirect_detail( $quote_id, is_wp_error( $res ) ? 'error' : $res );
        }

        /**
         * Run a quote action (save / send / counter / withdraw) and persist it.
         *
         * Shared core used by both the no-JS POST fallback (maybe_handle_actions)
         * and the AJAX handler (ajax_quote_action) so behaviour stays identical.
         *
         * @param int     $quote_id Quote post ID.
         * @param string  $do       One of save|send|counter|withdraw.
         * @param WP_Post $post     Quote post captured before the mutation.
         * @return string|WP_Error  Result code ('saved'|'sent'|'withdrawn') or error.
         */
        protected function do_quote_action( $quote_id, $do, $post ) {
            if ( ! in_array( $do, array( 'save', 'send', 'counter', 'withdraw' ), true ) ) {
                return new WP_Error( 'spbwc_quote_bad_action', __( 'Unknown action.', 'storelly-product-builder-for-woocommerce' ) );
            }

            if ( 'withdraw' === $do ) {
                $res = SPBWC_Quote::set_status( $quote_id, SPBWC_Quote::STATUS_WITHDRAWN, __( 'Quote withdrawn by merchant.', 'storelly-product-builder-for-woocommerce' ) );
                return is_wp_error( $res ) ? $res : 'withdrawn';
            }

            // Persist the pricing reply (lines + terms) for save / send / counter.
            $this->save_reply( $quote_id );

            if ( 'send' === $do || 'counter' === $do ) {
                $from = $post->post_status;
                $note = ( SPBWC_Quote::STATUS_NEGOTIATING === $from )
                    ? __( 'Revised quote sent to customer.', 'storelly-product-builder-for-woocommerce' )
                    : __( 'Quote sent to customer.', 'storelly-product-builder-for-woocommerce' );
                // Snapshot this priced version for the revision history / diff (P3.9).
                SPBWC_Quote::push_version( $quote_id );
                $res = SPBWC_Quote::set_status( $quote_id, SPBWC_Quote::STATUS_SENT, $note );
                if ( ! is_wp_error( $res ) ) {
                    do_action( 'spbwc_quote_sent_notification', $quote_id );
                }
                return is_wp_error( $res ) ? $res : 'sent';
            }

            return 'saved';
        }

        /**
         * AJAX: run a quote action without a full page reload.
         *
         * Mirrors maybe_handle_actions() but returns JSON fragments so the JS
         * can update the status pill, activity timeline and editable state in
         * place. The plain POST form remains the no-JS fallback.
         */
        public function ajax_quote_action() {
            check_ajax_referer( 'spbwc_quote_action', 'nonce' );
            if ( ! current_user_can( self::CAPABILITY ) ) {
                wp_send_json_error( array( 'message' => esc_html__( 'Permission denied.', 'storelly-product-builder-for-woocommerce' ) ) );
            }
            $quote_id = isset( $_POST['quote_id'] ) ? absint( wp_unslash( $_POST['quote_id'] ) ) : 0;
            $post     = $quote_id ? get_post( $quote_id ) : null;
            if ( ! $post || SPBWC_Quote::POST_TYPE !== $post->post_type ) {
                wp_send_json_error( array( 'message' => esc_html__( 'Quote not found.', 'storelly-product-builder-for-woocommerce' ) ) );
            }
            $do = isset( $_POST['spbwc_quote_do'] ) ? sanitize_key( wp_unslash( $_POST['spbwc_quote_do'] ) ) : '';

            $res = $this->do_quote_action( $quote_id, $do, $post );
            if ( is_wp_error( $res ) ) {
                wp_send_json_error( array( 'message' => $res->get_error_message() ) );
            }

            $fresh    = get_post( $quote_id );
            $status   = $fresh ? $fresh->post_status : $post->post_status;
            $editable = in_array( $status, array( SPBWC_Quote::STATUS_NEW, SPBWC_Quote::STATUS_REVIEW, SPBWC_Quote::STATUS_NEGOTIATING ), true );

            ob_start();
            $this->render_timeline( $quote_id );
            $activity_html = ob_get_clean();

            wp_send_json_success(
                array(
                    'msg'              => $res,
                    'message'          => $this->action_message( $res ),
                    'status'           => $status,
                    'status_pill_html' => self::status_pill( $status ),
                    'activity_html'    => $activity_html,
                    'editable'         => $editable,
                )
            );
        }

        /**
         * Human-readable confirmation for an action result code.
         *
         * @param string $code Result code from do_quote_action().
         * @return string
         */
        protected function action_message( $code ) {
            $map = array(
                'saved'     => __( 'Quote saved.', 'storelly-product-builder-for-woocommerce' ),
                'sent'      => __( 'Quote sent to the customer.', 'storelly-product-builder-for-woocommerce' ),
                'withdrawn' => __( 'Quote withdrawn.', 'storelly-product-builder-for-woocommerce' ),
            );
            return isset( $map[ $code ] ) ? $map[ $code ] : '';
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
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only post-redirect flag.
                $sample_flag = isset( $_GET['spbwc_sample'] ) ? sanitize_key( wp_unslash( $_GET['spbwc_sample'] ) ) : '';
                if ( 'added' === $sample_flag ) {
                    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Sample quote added. Open it to try pricing and sending a reply.', 'storelly-product-builder-for-woocommerce' ) . '</p></div>';
                } elseif ( 'removed' === $sample_flag ) {
                    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Sample quotes removed.', 'storelly-product-builder-for-woocommerce' ) . '</p></div>';
                }
                $this->render_kpis();
                ?>
                <div class="spbwc-block spbwc-quotes-listwrap">
                    <?php $this->render_sample_banner(); ?>
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
            // Is there anything to import from existing tools?
            $import_total = 0;
            $import_url   = '';
            if ( class_exists( 'SPBWC_Quote_Import' ) ) {
                foreach ( SPBWC_Quote_Import::scan() as $source ) {
                    $import_total += (int) $source['count'];
                }
                $import_url = SPBWC_Quote_Import::tab_url();
            }
            $getquote_url = add_query_arg(
                array( 'page' => SPBWC_PB_QUOTES_SLUG, 'tab' => 'get-quote' ),
                admin_url( 'admin.php' )
            );
            ?>
            <div class="spbwc-empty-state">
                <div class="spbwc-empty-state__icon">
                    <span class="dashicons dashicons-money-alt" aria-hidden="true"></span>
                </div>
                <p class="spbwc-empty-state__title"><?php esc_html_e( 'No quotes yet', 'storelly-product-builder-for-woocommerce' ); ?></p>
                <p class="spbwc-empty-state__text"><?php esc_html_e( 'When customers request a quote it will appear here, ready for you to price and send. Get started:', 'storelly-product-builder-for-woocommerce' ); ?></p>

                <div class="spbwc-empty-state__actions">
                    <?php if ( $import_total > 0 && $import_url ) : ?>
                        <a class="spbwc-cta-btn spbwc-cta-btn--solid" href="<?php echo esc_url( $import_url ); ?>">
                            <span class="dashicons dashicons-download" aria-hidden="true"></span>
                            <?php
                            printf(
                                /* translators: %s: number of importable quotes. */
                                esc_html( _n( 'Import %s existing quote', 'Import %s existing quotes', $import_total, 'storelly-product-builder-for-woocommerce' ) ),
                                esc_html( number_format_i18n( $import_total ) )
                            );
                            ?>
                        </a>
                    <?php elseif ( $import_url ) : ?>
                        <a class="spbwc-cta-btn" href="<?php echo esc_url( $import_url ); ?>">
                            <span class="dashicons dashicons-download" aria-hidden="true"></span>
                            <?php esc_html_e( 'Import from another plugin', 'storelly-product-builder-for-woocommerce' ); ?>
                        </a>
                    <?php endif; ?>

                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
                        <?php wp_nonce_field( 'spbwc_quote_sample' ); ?>
                        <input type="hidden" name="action" value="spbwc_quote_seed_sample" />
                        <button type="submit" class="spbwc-cta-btn<?php echo ( $import_total > 0 ) ? '' : ' spbwc-cta-btn--solid'; ?>">
                            <span class="dashicons dashicons-lightbulb" aria-hidden="true"></span>
                            <?php esc_html_e( 'Add a sample quote', 'storelly-product-builder-for-woocommerce' ); ?>
                        </button>
                    </form>

                    <a class="spbwc-cta-btn spbwc-cta-btn--ghost" href="<?php echo esc_url( $getquote_url ); ?>">
                        <span class="dashicons dashicons-admin-settings" aria-hidden="true"></span>
                        <?php esc_html_e( 'Set up the Get Quote button', 'storelly-product-builder-for-woocommerce' ); ?>
                    </a>
                </div>
            </div>
            <?php
        }

        /** Banner shown above the list while sample quotes are present (M6). */
        protected function render_sample_banner() {
            if ( ! class_exists( 'SPBWC_Quote_Sample' ) || SPBWC_Quote_Sample::count() < 1 ) {
                return;
            }
            ?>
            <div class="spbwc-sample-banner">
                <span class="dashicons dashicons-lightbulb" aria-hidden="true"></span>
                <span class="spbwc-sample-banner__text"><?php esc_html_e( 'Some of these are sample quotes to show you the workflow.', 'storelly-product-builder-for-woocommerce' ); ?></span>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <?php wp_nonce_field( 'spbwc_quote_sample' ); ?>
                    <input type="hidden" name="action" value="spbwc_quote_remove_samples" />
                    <button type="submit" class="spbwc-sample-banner__remove"><?php esc_html_e( 'Remove samples', 'storelly-product-builder-for-woocommerce' ); ?></button>
                </form>
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
            $templates = class_exists( 'SPBWC_Quote_Template' ) ? SPBWC_Quote_Template::get_all() : array();
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
                    <div class="spbwc-q-detailbar__tools" role="group" aria-label="<?php esc_attr_e( 'Quote document actions', 'storelly-product-builder-for-woocommerce' ); ?>">
                        <a class="spbwc-cta-btn spbwc-cta-btn--ghost spbwc-cta-btn--sm" href="<?php echo esc_url( $preview_url ); ?>" target="_blank" rel="noopener">
                            <span class="dashicons dashicons-visibility" aria-hidden="true"></span>
                            <?php esc_html_e( 'Preview customer view', 'storelly-product-builder-for-woocommerce' ); ?>
                        </a>
                        <?php if ( class_exists( 'SPBWC_Quote_PDF' ) ) : ?>
                            <a class="spbwc-cta-btn spbwc-cta-btn--ghost spbwc-cta-btn--sm" href="<?php echo esc_url( SPBWC_Quote_PDF::print_url( $quote_id ) ); ?>" target="_blank" rel="noopener">
                                <span class="dashicons dashicons-pdf" aria-hidden="true"></span>
                                <?php esc_html_e( 'Download / Print', 'storelly-product-builder-for-woocommerce' ); ?>
                            </a>
                        <?php endif; ?>
                    </div>
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
                                    <?php if ( $editable ) : ?>
                                        <div class="spbwc-q-tmpl-bar">
                                            <select id="spbwc-q-tmpl-select" class="spbwc-input spbwc-input--sm">
                                                <option value=""><?php esc_html_e( 'Load template…', 'storelly-product-builder-for-woocommerce' ); ?></option>
                                                <?php foreach ( $templates as $tpl ) : ?>
                                                    <option value="<?php echo esc_attr( $tpl['id'] ); ?>"><?php echo esc_html( $tpl['name'] ); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="button" class="spbwc-cta-btn spbwc-cta-btn--ghost spbwc-cta-btn--sm" id="spbwc-q-tmpl-apply"><?php esc_html_e( 'Apply', 'storelly-product-builder-for-woocommerce' ); ?></button>
                                            <button type="button" class="spbwc-cta-btn spbwc-cta-btn--ghost spbwc-cta-btn--sm" id="spbwc-q-tmpl-delete" title="<?php esc_attr_e( 'Delete selected template', 'storelly-product-builder-for-woocommerce' ); ?>"><span class="dashicons dashicons-trash" aria-hidden="true"></span></button>
                                            <span class="spbwc-q-spacer"></span>
                                            <button type="button" class="spbwc-cta-btn spbwc-cta-btn--ghost spbwc-cta-btn--sm" id="spbwc-q-tmpl-save"><span class="dashicons dashicons-saved" aria-hidden="true"></span> <?php esc_html_e( 'Save as template', 'storelly-product-builder-for-woocommerce' ); ?></button>
                                        </div>
                                    <?php endif; ?>
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
                                                <?php foreach ( SPBWC_Quote::payment_terms_options() as $pt_key => $pt_label ) : ?>
                                                    <option value="<?php echo esc_attr( $pt_key ); ?>" <?php selected( $terms ? $terms : 'prepay', $pt_key ); ?>><?php echo esc_html( $pt_label ); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <span class="spbwc-setting-row__hint"><?php esc_html_e( 'How the customer pays once they accept. Net terms create an unpaid invoice; the deposit option charges 50% now.', 'storelly-product-builder-for-woocommerce' ); ?></span>
                                        </div>
                                        <div class="spbwc-q-field spbwc-q-field--full">
                                            <label for="spbwc-q-note"><?php esc_html_e( 'Note to customer', 'storelly-product-builder-for-woocommerce' ); ?></label>
                                            <textarea id="spbwc-q-note" name="quote_customer_note" rows="3" class="spbwc-input"><?php echo esc_textarea( $note ); ?></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Action bar -->
                            <div class="spbwc-block spbwc-q-actionblock">
                                <div class="spbwc-block__body">
                                    <div class="spbwc-q-actionbar" id="spbwc-q-actionbar">
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
                                            <span class="spbwc-q-savestate" id="spbwc-q-savestate" role="status" aria-live="polite"></span>
                                            <span class="spbwc-q-spacer"></span>
                                            <button type="submit" name="spbwc_quote_do" value="withdraw" class="spbwc-cta-btn spbwc-cta-btn--ghost spbwc-cta-btn--danger">
                                                <?php esc_html_e( 'Withdraw', 'storelly-product-builder-for-woocommerce' ); ?>
                                            </button>
                                        <?php else : ?>
                                            <p class="spbwc-setting-row__hint spbwc-q-hint--flush">
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
                                            <span class="spbwc-q-meta__value" id="spbwc-q-status-pill"><?php echo self::status_pill( $status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- status_pill returns escaped markup. ?></span>
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
                                <div class="spbwc-block__body" id="spbwc-q-activity">
                                    <?php $this->render_timeline( $quote_id ); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <?php
            $this->render_detail_script(
                $currency,
                $templates,
                array(
                    'quote_id' => $quote_id,
                    'status'   => $status,
                    'editable' => $editable,
                    'email'    => isset( $request['email'] ) ? (string) $request['email'] : '',
                    'is_new'   => ( SPBWC_Quote::STATUS_NEW === $status ),
                )
            );
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
                    <p class="spbwc-setting-row__hint spbwc-q-hint--flush spbwc-q-hint--top">
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
            // Multi-item quote cart (P4.3): a list of products instead of one.
            if ( ! empty( $request['items'] ) && is_array( $request['items'] ) ) {
                $shown = true;
                echo '<div class="spbwc-q-recap__label">' . esc_html__( 'Products', 'storelly-product-builder-for-woocommerce' ) . '</div>';
                echo '<div class="spbwc-q-recap__value"><ul class="spbwc-q-recap__items">';
                foreach ( $request['items'] as $item ) {
                    if ( empty( $item['name'] ) ) {
                        continue;
                    }
                    $qty = isset( $item['qty'] ) ? (int) $item['qty'] : 1;
                    echo '<li>' . esc_html( (string) $item['name'] ) . ' <span class="spbwc-q-recap__qty">&times;' . esc_html( (string) $qty ) . '</span></li>';
                }
                echo '</ul></div>';
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
            // Uploaded attachments (QF3): download links for the merchant.
            if ( ! empty( $request['attachments'] ) && is_array( $request['attachments'] ) ) {
                $shown = true;
                echo '<div class="spbwc-q-recap__label">' . esc_html__( 'Attachments', 'storelly-product-builder-for-woocommerce' ) . '</div>';
                echo '<div class="spbwc-q-recap__value"><ul class="spbwc-q-recap__files">';
                foreach ( $request['attachments'] as $att ) {
                    if ( empty( $att['name'] ) ) {
                        continue;
                    }
                    $url     = ! empty( $att['url'] ) ? $att['url'] : '';
                    $missing = ! empty( $att['file'] ) && ! file_exists( SPBWC_PB_UPLOAD_DIR . '/' . ltrim( (string) $att['file'], '/' ) );
                    $size    = isset( $att['size'] ) ? size_format( (int) $att['size'] ) : '';
                    echo '<li>';
                    if ( $url && ! $missing ) {
                        echo '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener"><span class="dashicons dashicons-media-default" aria-hidden="true"></span> ' . esc_html( (string) $att['name'] ) . '</a>';
                    } else {
                        echo '<span class="spbwc-q-recap__file-missing"><span class="dashicons dashicons-media-default" aria-hidden="true"></span> ' . esc_html( (string) $att['name'] ) . ' — ' . esc_html__( 'file removed', 'storelly-product-builder-for-woocommerce' ) . '</span>';
                    }
                    if ( $size && ! $missing ) {
                        echo ' <span class="spbwc-q-recap__file-size">(' . esc_html( $size ) . ')</span>';
                    }
                    echo '</li>';
                }
                echo '</ul></div>';
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
        protected function render_detail_script( $currency, $templates = array(), $ctx = array() ) {
            $symbol = function_exists( 'get_woocommerce_currency_symbol' ) ? html_entity_decode( get_woocommerce_currency_symbol( $currency ) ) : '';
            $ctx    = wp_parse_args(
                $ctx,
                array( 'quote_id' => 0, 'status' => '', 'editable' => false, 'email' => '', 'is_new' => false )
            );
            ?>
            <script>
            (function () {
                'use strict';
                var sym = <?php echo wp_json_encode( $symbol ); ?>;
                var TPL = <?php echo wp_json_encode( array_values( $templates ) ); ?>;
                var TPL_NONCE = <?php echo wp_json_encode( wp_create_nonce( 'spbwc_quote_template' ) ); ?>;
                var TPL_AJAX = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
                var QQ = {
                    ajax:     TPL_AJAX,
                    nonce:    <?php echo wp_json_encode( wp_create_nonce( 'spbwc_quote_action' ) ); ?>,
                    quoteId:  <?php echo (int) $ctx['quote_id']; ?>,
                    editable: <?php echo $ctx['editable'] ? 'true' : 'false'; ?>,
                    isNew:    <?php echo $ctx['is_new'] ? 'true' : 'false'; ?>,
                    email:    <?php echo wp_json_encode( $ctx['email'] ); ?>,
                    i18n: {
                        saved:        <?php echo wp_json_encode( __( 'Saved', 'storelly-product-builder-for-woocommerce' ) ); ?>,
                        unsaved:      <?php echo wp_json_encode( __( 'Unsaved changes', 'storelly-product-builder-for-woocommerce' ) ); ?>,
                        saving:       <?php echo wp_json_encode( __( 'Saving…', 'storelly-product-builder-for-woocommerce' ) ); ?>,
                        sending:      <?php echo wp_json_encode( __( 'Sending…', 'storelly-product-builder-for-woocommerce' ) ); ?>,
                        reqFailed:    <?php echo wp_json_encode( __( 'Request failed. Please try again.', 'storelly-product-builder-for-woocommerce' ) ); ?>,
                        leaveWarn:    <?php echo wp_json_encode( __( 'You have unsaved changes. Leave without saving?', 'storelly-product-builder-for-woocommerce' ) ); ?>,
                        lockedHint:   <?php echo wp_json_encode( __( 'This quote is locked — its current status does not allow further edits.', 'storelly-product-builder-for-woocommerce' ) ); ?>,
                        cancel:       <?php echo wp_json_encode( __( 'Cancel', 'storelly-product-builder-for-woocommerce' ) ); ?>,
                        confirmSendTitle:   <?php echo wp_json_encode( __( 'Send this pricing reply?', 'storelly-product-builder-for-woocommerce' ) ); ?>,
                        confirmSendBody:    <?php echo wp_json_encode( __( 'The customer will be emailed this quote and it will be locked for further edits.', 'storelly-product-builder-for-woocommerce' ) ); ?>,
                        confirmSendCta:     <?php echo wp_json_encode( __( 'Send pricing reply', 'storelly-product-builder-for-woocommerce' ) ); ?>,
                        confirmCounterCta:  <?php echo wp_json_encode( __( 'Send counter-offer', 'storelly-product-builder-for-woocommerce' ) ); ?>,
                        confirmWithdrawTitle: <?php echo wp_json_encode( __( 'Withdraw this quote?', 'storelly-product-builder-for-woocommerce' ) ); ?>,
                        confirmWithdrawBody:  <?php echo wp_json_encode( __( 'The quote will be closed and can no longer be edited or sent.', 'storelly-product-builder-for-woocommerce' ) ); ?>,
                        confirmWithdrawCta:   <?php echo wp_json_encode( __( 'Withdraw', 'storelly-product-builder-for-woocommerce' ) ); ?>,
                        recipientLabel:       <?php echo wp_json_encode( __( 'Recipient', 'storelly-product-builder-for-woocommerce' ) ); ?>,
                        totalLabel:           <?php echo wp_json_encode( __( 'Quote total', 'storelly-product-builder-for-woocommerce' ) ); ?>,
                        guardZero:    <?php echo wp_json_encode( __( 'The quote total is 0. Add priced line items before sending.', 'storelly-product-builder-for-woocommerce' ) ); ?>,
                        guardEmpty:   <?php echo wp_json_encode( __( 'Every line needs a name before you can send the quote.', 'storelly-product-builder-for-woocommerce' ) ); ?>
                    }
                };
                var TPL_I18N = {
                    saveTitle:       <?php echo wp_json_encode( __( 'Save as template', 'storelly-product-builder-for-woocommerce' ) ); ?>,
                    nameLabel:       <?php echo wp_json_encode( __( 'Template name', 'storelly-product-builder-for-woocommerce' ) ); ?>,
                    namePlaceholder: <?php echo wp_json_encode( __( 'e.g. Banner — standard + rush', 'storelly-product-builder-for-woocommerce' ) ); ?>,
                    saveOk:          <?php echo wp_json_encode( __( 'Save template', 'storelly-product-builder-for-woocommerce' ) ); ?>,
                    saved:           <?php echo wp_json_encode( __( 'Template saved.', 'storelly-product-builder-for-woocommerce' ) ); ?>,
                    deleteTitle:     <?php echo wp_json_encode( __( 'Delete template', 'storelly-product-builder-for-woocommerce' ) ); ?>,
                    deleteBody:      <?php echo wp_json_encode( __( 'Delete this template? This cannot be undone.', 'storelly-product-builder-for-woocommerce' ) ); ?>,
                    deleteOk:        <?php echo wp_json_encode( __( 'Delete', 'storelly-product-builder-for-woocommerce' ) ); ?>,
                    deleted:         <?php echo wp_json_encode( __( 'Template deleted.', 'storelly-product-builder-for-woocommerce' ) ); ?>,
                    failed:          <?php echo wp_json_encode( __( 'Something went wrong. Please try again.', 'storelly-product-builder-for-woocommerce' ) ); ?>,
                    requestFailed:   <?php echo wp_json_encode( __( 'Request failed. Check your connection.', 'storelly-product-builder-for-woocommerce' ) ); ?>
                };
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
                function setValidityDays(d) {
                    d = parseInt(d, 10);
                    if (!d) { return; }
                    var dt = new Date();
                    dt.setDate(dt.getDate() + d);
                    var iso = dt.toISOString().slice(0, 10);
                    var v = document.getElementById('spbwc-q-validity');
                    if (v) { v.value = iso; }
                    if (preset) { preset.value = String(d); }
                }
                if (preset) {
                    preset.addEventListener('change', function () { setValidityDays(this.value); });
                }

                /* ── Templates (P3.12) ───────────────────────────────── */
                function addRow(label, qty, price) {
                    var tr = rowTemplate();
                    tr.querySelector('input[name="line_label[]"]').value = label || '';
                    tr.querySelector('.spbwc-q-qty').value = (qty != null ? qty : 1);
                    tr.querySelector('.spbwc-q-price').value = (price != null ? price : 0);
                    tbody.appendChild(tr);
                }
                function tplById(id) {
                    for (var i = 0; i < TPL.length; i++) { if (String(TPL[i].id) === String(id)) { return TPL[i]; } }
                    return null;
                }
                var sel = document.getElementById('spbwc-q-tmpl-select');
                var applyBtn = document.getElementById('spbwc-q-tmpl-apply');
                var saveBtn = document.getElementById('spbwc-q-tmpl-save');
                var delBtn = document.getElementById('spbwc-q-tmpl-delete');

                if (applyBtn) {
                    applyBtn.addEventListener('click', function () {
                        var t = tplById(sel && sel.value);
                        if (!t) { return; }
                        tbody.innerHTML = '';
                        (t.lines || []).forEach(function (l) { addRow(l.label, l.qty, l.unit_price); });
                        if (!tbody.children.length) { addRow('', 1, 0); }
                        var terms = t.terms || {};
                        if (terms.payment_terms) { var pt = document.getElementById('spbwc-q-terms'); if (pt) { pt.value = terms.payment_terms; } }
                        if (terms.note != null) { var nt = document.getElementById('spbwc-q-note'); if (nt) { nt.value = terms.note; } }
                        if (terms.valid_days) { setValidityDays(terms.valid_days); }
                        recalc();
                    });
                }
                function refreshTemplates(list) {
                    TPL = list || [];
                    if (!sel) { return; }
                    var cur = sel.value;
                    sel.innerHTML = '<option value="">' + (sel.getAttribute('data-placeholder') || 'Load template…') + '</option>';
                    TPL.forEach(function (t) {
                        var o = document.createElement('option');
                        o.value = t.id; o.textContent = t.name;
                        sel.appendChild(o);
                    });
                    if (tplById(cur)) { sel.value = cur; }
                }
                if (sel) { sel.setAttribute('data-placeholder', sel.options.length ? sel.options[0].textContent : 'Load template…'); }
                function tplPost(action, extra, cb) {
                    var fd = new FormData();
                    fd.append('action', action);
                    fd.append('nonce', TPL_NONCE);
                    Object.keys(extra || {}).forEach(function (k) { fd.append(k, extra[k]); });
                    fetch(TPL_AJAX, { method: 'POST', credentials: 'same-origin', body: fd })
                        .then(function (r) { return r.json(); })
                        .then(function (res) { if (res && res.success) { cb(res.data); } else { tplToast((res && res.data && res.data.message) || TPL_I18N.failed, 'error'); } })
                        .catch(function () { tplToast(TPL_I18N.requestFailed, 'error'); });
                }
                function tplToast(message, tone) {
                    if (window.spbwcDialog) { window.spbwcDialog.toast({ message: message, tone: tone || 'info' }); }
                    else { window.alert(message); }
                }
                if (saveBtn) {
                    saveBtn.addEventListener('click', function () {
                        var ask = window.spbwcDialog
                            ? window.spbwcDialog.prompt({ title: TPL_I18N.saveTitle, message: TPL_I18N.nameLabel, okText: TPL_I18N.saveOk, placeholder: TPL_I18N.namePlaceholder, required: true })
                            : Promise.resolve(window.prompt(TPL_I18N.nameLabel));
                        ask.then(function (name) {
                            if (!name) { return; }
                            var fd = new FormData();
                            fd.append('action', 'spbwc_save_quote_template');
                            fd.append('nonce', TPL_NONCE);
                            fd.append('name', name);
                            tbody.querySelectorAll('tr.spbwc-q-line').forEach(function (tr) {
                                fd.append('line_label[]', tr.querySelector('input[name="line_label[]"]').value);
                                fd.append('line_qty[]', tr.querySelector('.spbwc-q-qty').value);
                                fd.append('line_price[]', tr.querySelector('.spbwc-q-price').value);
                            });
                            fd.append('valid_days', (preset && preset.value) ? preset.value : 0);
                            var pt = document.getElementById('spbwc-q-terms');
                            fd.append('payment_terms', pt ? pt.value : 'prepay');
                            var nt = document.getElementById('spbwc-q-note');
                            fd.append('note', nt ? nt.value : '');
                            fetch(TPL_AJAX, { method: 'POST', credentials: 'same-origin', body: fd })
                                .then(function (r) { return r.json(); })
                                .then(function (res) { if (res && res.success) { refreshTemplates(res.data.templates); tplToast(TPL_I18N.saved, 'success'); } else { tplToast((res && res.data && res.data.message) || TPL_I18N.failed, 'error'); } })
                                .catch(function () { tplToast(TPL_I18N.requestFailed, 'error'); });
                        });
                    });
                }
                if (delBtn) {
                    delBtn.addEventListener('click', function () {
                        if (!sel || !sel.value) { return; }
                        var ask = window.spbwcDialog
                            ? window.spbwcDialog.confirm({ title: TPL_I18N.deleteTitle, message: TPL_I18N.deleteBody, tone: 'danger', okText: TPL_I18N.deleteOk })
                            : Promise.resolve(window.confirm(TPL_I18N.deleteBody));
                        ask.then(function (ok) {
                            if (!ok) { return; }
                            tplPost('spbwc_delete_quote_template', { template_id: sel.value }, function (data) { refreshTemplates(data.templates); tplToast(TPL_I18N.deleted, 'success'); });
                        });
                    });
                }

                /* ── AJAX actions + UX (dirty-state, confirm, guards, Ctrl+S) ── */
                var qForm     = document.getElementById('spbwc-quote-reply-form');
                var qBar      = document.getElementById('spbwc-q-actionbar');
                var saveState = document.getElementById('spbwc-q-savestate');
                var dirty = false, busy = false;

                function qToast(msg, tone) {
                    if (window.spbwcDialog) { window.spbwcDialog.toast({ message: msg, tone: tone || 'info' }); }
                    else { window.alert(msg); }
                }
                function setSaveState(kind, text) {
                    if (!saveState) { return; }
                    saveState.className = 'spbwc-q-savestate' + (kind ? ' spbwc-q-savestate--' + kind : '');
                    saveState.textContent = text != null ? text
                        : (kind === 'saved' ? QQ.i18n.saved : (kind === 'unsaved' ? QQ.i18n.unsaved : ''));
                }
                function markDirty() {
                    if (busy || !QQ.editable) { return; }
                    dirty = true;
                    setSaveState('unsaved');
                }
                if (qForm) {
                    qForm.addEventListener('input', markDirty);
                    qForm.addEventListener('change', markDirty);
                }
                window.addEventListener('beforeunload', function (e) {
                    if (dirty && QQ.editable) { e.preventDefault(); e.returnValue = ''; return ''; }
                });

                function grandTotalValue() {
                    var subtotal = 0;
                    tbody.querySelectorAll('tr.spbwc-q-line').forEach(function (tr) {
                        var qty = parseFloat(tr.querySelector('.spbwc-q-qty').value) || 0;
                        var price = parseFloat(tr.querySelector('.spbwc-q-price').value) || 0;
                        subtotal += qty * price;
                    });
                    var discount = parseFloat(document.getElementById('spbwc-q-discount').value) || 0;
                    var tax = parseFloat(document.getElementById('spbwc-q-tax').value) || 0;
                    return Math.round((subtotal - discount + tax) * 100) / 100;
                }
                function guardSend() {
                    var missingLabel = false;
                    tbody.querySelectorAll('tr.spbwc-q-line').forEach(function (tr) {
                        var label = tr.querySelector('input[name="line_label[]"]');
                        var qty = parseFloat(tr.querySelector('.spbwc-q-qty').value) || 0;
                        var price = parseFloat(tr.querySelector('.spbwc-q-price').value) || 0;
                        if ((qty || price) && label && !label.value.trim()) { missingLabel = true; }
                    });
                    if (missingLabel) { return QQ.i18n.guardEmpty; }
                    if (grandTotalValue() <= 0) { return QQ.i18n.guardZero; }
                    return '';
                }

                function lockForm() {
                    QQ.editable = false;
                    dirty = false;
                    if (qForm) {
                        qForm.querySelectorAll('input, select, textarea, button').forEach(function (node) { node.disabled = true; });
                    }
                    if (qBar) {
                        var p = document.createElement('p');
                        p.className = 'spbwc-setting-row__hint spbwc-q-hint--flush';
                        p.textContent = QQ.i18n.lockedHint;
                        qBar.innerHTML = '';
                        qBar.appendChild(p);
                    }
                }

                function submitAction(action, btn) {
                    if (busy || !qForm) { return; }
                    busy = true;
                    if (btn) { btn.classList.add('is-busy'); btn.disabled = true; }
                    setSaveState('busy', action === 'save' ? QQ.i18n.saving : QQ.i18n.sending);

                    var fd = new FormData(qForm);
                    fd.append('action', 'spbwc_quote_action');
                    fd.append('nonce', QQ.nonce);
                    fd.append('spbwc_quote_do', action);

                    fetch(QQ.ajax, { method: 'POST', credentials: 'same-origin', body: fd })
                        .then(function (r) { return r.json(); })
                        .then(function (res) {
                            busy = false;
                            if (btn) { btn.classList.remove('is-busy'); btn.disabled = false; }
                            if (!res || !res.success) {
                                qToast((res && res.data && res.data.message) || QQ.i18n.reqFailed, 'error');
                                setSaveState(dirty ? 'unsaved' : '');
                                return;
                            }
                            var d = res.data || {};
                            if (d.message) { qToast(d.message, 'success'); }
                            var pill = document.getElementById('spbwc-q-status-pill');
                            if (pill && d.status_pill_html) { pill.innerHTML = d.status_pill_html; }
                            var act = document.getElementById('spbwc-q-activity');
                            if (act && d.activity_html) { act.innerHTML = d.activity_html; }
                            if (d.editable) { dirty = false; setSaveState('saved'); }
                            else { lockForm(); }
                        })
                        .catch(function () {
                            busy = false;
                            if (btn) { btn.classList.remove('is-busy'); btn.disabled = false; }
                            qToast(QQ.i18n.reqFailed, 'error');
                            setSaveState(dirty ? 'unsaved' : '');
                        });
                }

                function confirmThen(title, body, okText, tone, cb) {
                    var ask = window.spbwcDialog
                        ? window.spbwcDialog.confirm({ title: title, message: body, okText: okText, tone: tone === 'danger' ? 'danger' : undefined })
                        : Promise.resolve(window.confirm(title));
                    ask.then(function (ok) { if (ok) { cb(); } });
                }

                if (qForm) {
                    qForm.addEventListener('submit', function (e) {
                        e.preventDefault();
                        if (!QQ.editable) { return; }
                        var btn = e.submitter || document.activeElement;
                        var action = (btn && btn.name === 'spbwc_quote_do') ? btn.value : 'save';

                        if (action === 'send' || action === 'counter') {
                            var problem = guardSend();
                            if (problem) { qToast(problem, 'warning'); return; }
                            var body = QQ.i18n.confirmSendBody + '\n\n' + QQ.i18n.totalLabel + ': ' + money(grandTotalValue())
                                + (QQ.email ? '\n' + QQ.i18n.recipientLabel + ': ' + QQ.email : '');
                            var okText = (action === 'counter') ? QQ.i18n.confirmCounterCta : QQ.i18n.confirmSendCta;
                            confirmThen(QQ.i18n.confirmSendTitle, body, okText, 'primary', function () { submitAction(action, btn); });
                        } else if (action === 'withdraw') {
                            confirmThen(QQ.i18n.confirmWithdrawTitle, QQ.i18n.confirmWithdrawBody, QQ.i18n.confirmWithdrawCta, 'danger', function () { submitAction('withdraw', btn); });
                        } else {
                            submitAction('save', btn);
                        }
                    });
                }

                document.addEventListener('keydown', function (e) {
                    if ((e.ctrlKey || e.metaKey) && (e.key === 's' || e.key === 'S')) {
                        if (!QQ.editable) { return; }
                        e.preventDefault();
                        submitAction('save', qBar ? qBar.querySelector('button[value="save"]') : null);
                    }
                });

                // Default 14-day validity for a brand-new quote with no date yet.
                if (QQ.isNew && QQ.editable) {
                    var vEl = document.getElementById('spbwc-q-validity');
                    if (vEl && !vEl.value) { setValidityDays(14); }
                }

                recalc();
            }());
            </script>
            <?php
        }
    }
}
