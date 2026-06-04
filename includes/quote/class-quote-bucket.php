<?php
/**
 * Multi-item quote cart (P4.3).
 *
 * A session-backed "quote bucket" lets a buyer collect several products into one
 * quote request, separate from the WooCommerce cart so it never touches cart
 * totals/coupons/shipping. Products show an "Add to quote" action; a floating
 * "Quote list (N)" badge opens a modal to review the items + fill the request
 * form + submit, which creates a single spbwc_quote with multiple line items.
 *
 * @package Storelly
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'SPBWC_Quote_Bucket' ) ) {

    class SPBWC_Quote_Bucket {

        const SESSION_KEY = 'spbwc_quote_bucket';

        public function init() {
            add_action( 'wp_ajax_spbwc_bucket_add', array( $this, 'ajax_add' ) );
            add_action( 'wp_ajax_nopriv_spbwc_bucket_add', array( $this, 'ajax_add' ) );
            add_action( 'wp_ajax_spbwc_bucket_remove', array( $this, 'ajax_remove' ) );
            add_action( 'wp_ajax_nopriv_spbwc_bucket_remove', array( $this, 'ajax_remove' ) );
            add_action( 'wp_ajax_spbwc_bucket_submit', array( $this, 'ajax_submit' ) );
            add_action( 'wp_ajax_nopriv_spbwc_bucket_submit', array( $this, 'ajax_submit' ) );
            add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
            add_action( 'woocommerce_after_add_to_cart_button', array( $this, 'render_add_button' ) );
            add_action( 'wp_footer', array( $this, 'render_ui' ) );
        }

        /** Load the bucket script + shared storefront stylesheet site-wide. */
        public function enqueue() {
            if ( is_admin() || ! self::globally_enabled() ) {
                return;
            }
            wp_enqueue_style( 'spbwc-tokens-storefront', SPBWC_PB_CSS_URL . '_tokens-storefront.css', array(), SPBWC_PB_VERSION );
            $css = SPBWC_PB_PLUGIN_DIR . 'static/css/quote-storefront.css';
            wp_enqueue_style( 'spbwc-quote-storefront', SPBWC_PB_CSS_URL . 'quote-storefront.css', array( 'spbwc-tokens-storefront' ), file_exists( $css ) ? filemtime( $css ) : SPBWC_PB_VERSION );
            wp_enqueue_style( 'dashicons' );
            $js = SPBWC_PB_PLUGIN_DIR . 'static/js/quote-bucket.js';
            wp_enqueue_script( 'spbwc-quote-bucket', SPBWC_PB_JS_URL . 'quote-bucket.js', array(), file_exists( $js ) ? filemtime( $js ) : SPBWC_PB_VERSION, true );
            wp_localize_script(
                'spbwc-quote-bucket',
                'spbwcBucket',
                array(
                    'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
                    'nonce'       => wp_create_nonce( 'spbwc_bucket_action' ),
                    'myQuotesUrl' => is_user_logged_in() ? wc_get_endpoint_url( 'quotes', '', wc_get_page_permalink( 'myaccount' ) ) : '',
                    'i18n'        => array(
                        'added'      => __( 'Added to quote list', 'storelly-product-builder-for-woocommerce' ),
                        'empty'      => __( 'Your quote list is empty.', 'storelly-product-builder-for-woocommerce' ),
                        'remove'     => __( 'Remove', 'storelly-product-builder-for-woocommerce' ),
                        'qty'        => __( 'Qty', 'storelly-product-builder-for-woocommerce' ),
                        'sending'    => __( 'Sending…', 'storelly-product-builder-for-woocommerce' ),
                        'submit'     => __( 'Submit request', 'storelly-product-builder-for-woocommerce' ),
                        'network'    => __( 'Request failed. Please try again.', 'storelly-product-builder-for-woocommerce' ),
                        'trackQuote' => __( 'Track your quote', 'storelly-product-builder-for-woocommerce' ),
                    ),
                )
            );
        }

        /** "Add to quote" button under the add-to-cart on quote-enabled products. */
        public function render_add_button() {
            if ( ! self::globally_enabled() || ! function_exists( 'is_product' ) || ! is_product() ) {
                return;
            }
            global $product; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- WooCommerce global.
            if ( ! $product || ! SPBWC_Request_Quote::instance()->is_product_quote_enabled( $product->get_id() ) ) {
                return;
            }
            printf(
                '<button type="button" class="button spbwc-bucket-add" data-product="%1$d">%2$s</button>',
                (int) $product->get_id(),
                esc_html__( 'Add to quote list', 'storelly-product-builder-for-woocommerce' )
            );
        }

        /** True when the global Get Quote toggle is on. */
        public static function globally_enabled() {
            $s = get_option( 'spbwc_quote_settings', array() );
            return isset( $s['enable_quote'] ) && 'yes' === $s['enable_quote'];
        }

        /* ── Session store ────────────────────────────────────────── */

        protected function session() {
            return ( function_exists( 'WC' ) && WC()->session ) ? WC()->session : null;
        }

        /** @return array<int,int> product_id => qty */
        public function get() {
            $s = $this->session();
            if ( ! $s ) {
                return array();
            }
            $b = $s->get( self::SESSION_KEY, array() );
            return is_array( $b ) ? $b : array();
        }

        protected function save( array $bucket ) {
            $s = $this->session();
            if ( $s ) {
                $s->set( self::SESSION_KEY, $bucket );
            }
        }

        public function count() {
            return array_sum( array_map( 'intval', $this->get() ) );
        }

        public function clear() {
            $this->save( array() );
        }

        /**
         * Bucket items expanded with product data.
         *
         * @return array[] [ product_id, name, qty, price_html ]
         */
        public function items() {
            $out = array();
            foreach ( $this->get() as $pid => $qty ) {
                $product = wc_get_product( $pid );
                if ( ! $product ) {
                    continue;
                }
                $out[] = array(
                    'product_id' => (int) $pid,
                    'name'       => $product->get_name(),
                    'qty'        => (int) $qty,
                    'price_html' => $product->get_price_html(),
                );
            }
            return $out;
        }

        /* ── AJAX ─────────────────────────────────────────────────── */

        protected function verify() {
            $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
            if ( ! $nonce || ! wp_verify_nonce( $nonce, 'spbwc_bucket_action' ) ) {
                wp_send_json_error( array( 'message' => esc_html__( 'Security check failed.', 'storelly-product-builder-for-woocommerce' ) ) );
            }
        }

        public function ajax_add() {
            $this->verify();
            $pid = isset( $_POST['product_id'] ) ? absint( wp_unslash( $_POST['product_id'] ) ) : 0;
            $qty = isset( $_POST['quantity'] ) ? max( 1, absint( wp_unslash( $_POST['quantity'] ) ) ) : 1;
            $product = wc_get_product( $pid );
            if ( ! $product ) {
                wp_send_json_error( array( 'message' => esc_html__( 'Product not found.', 'storelly-product-builder-for-woocommerce' ) ) );
            }
            $bucket         = $this->get();
            $bucket[ $pid ] = ( isset( $bucket[ $pid ] ) ? (int) $bucket[ $pid ] : 0 ) + $qty;
            $this->save( $bucket );
            wp_send_json_success( array( 'count' => $this->count(), 'items' => $this->items() ) );
        }

        public function ajax_remove() {
            $this->verify();
            $pid    = isset( $_POST['product_id'] ) ? absint( wp_unslash( $_POST['product_id'] ) ) : 0;
            $bucket = $this->get();
            unset( $bucket[ $pid ] );
            $this->save( $bucket );
            wp_send_json_success( array( 'count' => $this->count(), 'items' => $this->items() ) );
        }

        public function ajax_submit() {
            $this->verify();
            $items = $this->items();
            if ( empty( $items ) ) {
                wp_send_json_error( array( 'message' => esc_html__( 'Your quote list is empty.', 'storelly-product-builder-for-woocommerce' ) ) );
            }

            $rq     = SPBWC_Request_Quote::instance();
            $schema = $rq->get_form_fields();
            // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verified in verify(); each element sanitized in the loop below.
            $raw    = isset( $_POST['quote_fields'] ) ? (array) wp_unslash( $_POST['quote_fields'] ) : array();
            $fields = array();
            foreach ( $raw as $k => $v ) {
                $key            = sanitize_key( $k );
                $fields[ $key ] = ( 'message' === $key ) ? sanitize_textarea_field( $v ) : sanitize_text_field( $v );
            }
            $errors = array();
            foreach ( $schema as $f ) {
                if ( ! isset( $f['enabled'] ) || '1' !== (string) $f['enabled'] ) {
                    continue;
                }
                $name = isset( $f['name'] ) ? sanitize_key( $f['name'] ) : '';
                if ( '' === $name || isset( $errors[ $name ] ) ) {
                    continue;
                }
                $value      = isset( $fields[ $name ] ) ? $fields[ $name ] : '';
                $label      = isset( $f['label'] ) ? $f['label'] : $name;
                $required   = isset( $f['required'] ) && '1' === (string) $f['required'];
                $validation = isset( $f['validation'] ) ? sanitize_key( $f['validation'] ) : '';
                if ( $required && '' === $value ) {
                    /* translators: %s: field label */
                    $errors[ $name ] = sprintf( esc_html__( '%s is required.', 'storelly-product-builder-for-woocommerce' ), $label );
                } elseif ( 'email' === $validation && '' !== $value && ! is_email( $value ) ) {
                    /* translators: %s: field label */
                    $errors[ $name ] = sprintf( esc_html__( '%s is not a valid email.', 'storelly-product-builder-for-woocommerce' ), $label );
                }
            }
            if ( ! empty( $errors ) ) {
                wp_send_json_error( array( 'message' => esc_html__( 'Please correct the highlighted fields.', 'storelly-product-builder-for-woocommerce' ), 'errors' => $errors ) );
            }

            $known   = array( 'first_name', 'last_name', 'email', 'phone', 'company', 'message' );
            $request = array( 'items' => array(), 'fields' => array() );
            foreach ( $items as $it ) {
                $request['items'][] = array(
                    'product_id' => $it['product_id'],
                    'name'       => $it['name'],
                    'qty'        => $it['qty'],
                );
            }
            foreach ( $fields as $k => $v ) {
                if ( in_array( $k, $known, true ) ) {
                    $request[ $k ] = $v;
                } else {
                    $request['fields'][ $k ] = $v;
                }
            }
            if ( empty( $request['email'] ) && is_user_logged_in() ) {
                $u = wp_get_current_user();
                if ( $u instanceof WP_User ) {
                    $request['email'] = $u->user_email;
                }
            }

            $quote_id = SPBWC_Quote::create( $request, get_current_user_id() );
            if ( is_wp_error( $quote_id ) || ! $quote_id ) {
                wp_send_json_error( array( 'message' => esc_html__( 'Could not create your quote request.', 'storelly-product-builder-for-woocommerce' ) ) );
            }
            $lines = array();
            foreach ( $items as $it ) {
                $lines[] = array( 'label' => $it['name'], 'desc' => '', 'qty' => $it['qty'], 'unit_price' => 0 );
            }
            SPBWC_Quote::set_lines( $quote_id, $lines );

            do_action( 'spbwc_quote_new_notification', $quote_id );
            if ( ! empty( $request['email'] ) ) {
                do_action( 'spbwc_quote_ack_notification', $quote_id );
            }
            $this->clear();

            $settings = get_option( 'spbwc_quote_settings', array() );
            $msg      = ( isset( $settings['success_message'] ) && '' !== $settings['success_message'] )
                ? $settings['success_message']
                : __( 'Your quote request has been sent successfully.', 'storelly-product-builder-for-woocommerce' );
            wp_send_json_success( array( 'message' => $msg, 'quote_id' => $quote_id ) );
        }

        /* ── Front-end UI (footer) ────────────────────────────────── */

        public function render_ui() {
            if ( is_admin() || ! self::globally_enabled() ) {
                return;
            }
            $fields = SPBWC_Request_Quote::instance()->get_form_fields();
            $count  = $this->count();
            ?>
            <button type="button" id="spbwc-bucket-fab" class="spbwc-rfq-fab" aria-label="<?php esc_attr_e( 'Open your quote list', 'storelly-product-builder-for-woocommerce' ); ?>"<?php echo $count < 1 ? ' style="display:none;"' : ''; ?>>
                <span class="dashicons dashicons-clipboard" aria-hidden="true"></span>
                <span class="spbwc-rfq-fab__label"><?php esc_html_e( 'Quote list', 'storelly-product-builder-for-woocommerce' ); ?></span>
                <span class="spbwc-rfq-fab__count" id="spbwc-bucket-count"><?php echo esc_html( (string) $count ); ?></span>
            </button>

            <div id="spbwc-bucket-modal" class="spbwc-rfq spbwc-rfq-overlay" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="spbwc-bucket-title">
                <div class="spbwc-rfq-modal">
                    <div class="spbwc-rfq-head">
                        <h3 class="spbwc-rfq-title" id="spbwc-bucket-title"><?php esc_html_e( 'Request a quote', 'storelly-product-builder-for-woocommerce' ); ?></h3>
                        <button type="button" class="spbwc-rfq-close" aria-label="<?php esc_attr_e( 'Close', 'storelly-product-builder-for-woocommerce' ); ?>">&times;</button>
                    </div>
                    <div class="spbwc-rfq-body">
                        <div class="spbwc-rfq-alert" role="alert"></div>
                        <ul class="spbwc-bucket-list" id="spbwc-bucket-list"></ul>
                        <form id="spbwc-bucket-form" novalidate>
                            <?php foreach ( $fields as $field ) : ?>
                                <?php
                                if ( ! isset( $field['enabled'] ) || '1' !== (string) $field['enabled'] ) {
                                    continue;
                                }
                                $name = isset( $field['name'] ) ? sanitize_key( $field['name'] ) : '';
                                if ( '' === $name ) {
                                    continue;
                                }
                                $type     = isset( $field['type'] ) ? sanitize_key( $field['type'] ) : 'text';
                                $required = isset( $field['required'] ) && '1' === (string) $field['required'];
                                ?>
                                <div class="spbwc-rfq-field">
                                    <label for="spbwc_bucket_<?php echo esc_attr( $name ); ?>">
                                        <?php echo esc_html( isset( $field['label'] ) ? $field['label'] : ucfirst( $name ) ); ?>
                                        <?php if ( $required ) : ?><span class="spbwc-rfq-req" aria-hidden="true">*</span><?php endif; ?>
                                    </label>
                                    <?php if ( 'textarea' === $type ) : ?>
                                        <textarea id="spbwc_bucket_<?php echo esc_attr( $name ); ?>" name="quote_fields[<?php echo esc_attr( $name ); ?>]" <?php echo $required ? 'required' : ''; ?>></textarea>
                                    <?php else : ?>
                                        <input id="spbwc_bucket_<?php echo esc_attr( $name ); ?>" type="<?php echo esc_attr( in_array( $type, array( 'text', 'email', 'tel', 'number' ), true ) ? $type : 'text' ); ?>" name="quote_fields[<?php echo esc_attr( $name ); ?>]" <?php echo $required ? 'required' : ''; ?> />
                                    <?php endif; ?>
                                    <span class="spbwc-rfq-error"></span>
                                </div>
                            <?php endforeach; ?>
                            <div class="spbwc-rfq-foot">
                                <button type="submit" class="spbwc-rfq-submit"><?php esc_html_e( 'Submit request', 'storelly-product-builder-for-woocommerce' ); ?></button>
                            </div>
                        </form>
                        <div class="spbwc-rfq-success">
                            <div class="spbwc-rfq-success__icon" aria-hidden="true">&#10003;</div>
                            <p class="spbwc-rfq-success__title"><?php esc_html_e( 'Request sent', 'storelly-product-builder-for-woocommerce' ); ?></p>
                            <p class="spbwc-rfq-success__text"><?php esc_html_e( 'Thanks! We have received your request and will get back to you shortly.', 'storelly-product-builder-for-woocommerce' ); ?></p>
                            <p class="spbwc-rfq-success__actions"><a class="spbwc-rfq-success__cta" href="#" style="display:none;"></a></p>
                        </div>
                    </div>
                </div>
            </div>
            <?php
        }
    }
}
