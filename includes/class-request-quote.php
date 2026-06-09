<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'SPBWC_Request_Quote' ) ) {
    class SPBWC_Request_Quote {
        protected static $instance;

        public static function instance() {
            if ( is_null( self::$instance ) ) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        public function init() {
            add_action( 'init', array( $this, 'register_quote_order_statuses' ) );
            add_filter( 'wc_order_statuses', array( $this, 'add_quote_statuses_to_order_statuses' ) );

            add_action( 'init', array( $this, 'add_account_endpoints' ) );
            add_filter( 'woocommerce_account_menu_items', array( $this, 'add_quotes_account_menu' ) );
            // Keep the menu-badge count cache fresh: drop it whenever a quote changes status.
            add_action( 'spbwc_quote_status_changed', array( __CLASS__, 'invalidate_awaiting_count' ), 10, 3 );
            add_action( 'woocommerce_account_quotes_endpoint', array( $this, 'render_quotes_endpoint' ) );
            add_action( 'woocommerce_account_view-quote_endpoint', array( $this, 'render_view_quote_endpoint' ) );
            add_action( 'wp_loaded', array( $this, 'handle_quote_action' ) );

            add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
            add_action( 'woocommerce_after_add_to_cart_button', array( $this, 'render_quote_button' ), 25 );
            add_action( 'woocommerce_single_product_summary', array( $this, 'render_quote_button_standalone' ), 31 );
            add_action( 'wp_footer', array( $this, 'render_quote_popup' ) );
            add_action( 'wp_footer', array( $this, 'render_quote_badge' ), 20 );

            // D3 display modes — hide cart/price for "replace" / "quote_only".
            add_filter( 'woocommerce_is_purchasable', array( $this, 'filter_is_purchasable' ), 10, 2 );
            add_filter( 'woocommerce_get_price_html', array( $this, 'filter_price_html' ), 99, 2 );
            add_filter( 'woocommerce_loop_add_to_cart_link', array( $this, 'filter_loop_add_to_cart' ), 10, 2 );
            add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'filter_add_to_cart_validation' ), 10, 2 );
            add_filter( 'woocommerce_structured_data_product', array( $this, 'filter_structured_data' ), 10, 2 );

            add_action( 'wp_ajax_spbwc_submit_quote', array( $this, 'ajax_submit_quote' ) );
            add_action( 'wp_ajax_nopriv_spbwc_submit_quote', array( $this, 'ajax_submit_quote' ) );

            // Remove uploaded attachment files when a quote is permanently deleted.
            add_action( 'before_delete_post', array( $this, 'cleanup_quote_attachments' ) );

            // Daily GC for orphaned guest attachment folders (QF9).
            add_action( 'spbwc_quote_attachment_gc', array( $this, 'gc_orphan_attachments' ) );
            if ( ! wp_next_scheduled( 'spbwc_quote_attachment_gc' ) ) {
                wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'spbwc_quote_attachment_gc' );
            }
        }

        /** Subdir (under the quote-attachments folder) used by the in-flight upload. */
        protected $quote_upload_subdir = '';

        public function get_form_fields() {
            $fields = get_option( 'spbwc_quote_form_fields', array() );
            if ( empty( $fields ) || ! is_array( $fields ) ) {
                $fields = array(
                    'first_name' => array(
                        'name'        => 'first_name',
                        'type'        => 'text',
                        'label'       => 'First Name',
                        'placeholder' => '',
                        'validation'  => '',
                        'required'    => '1',
                        'enabled'     => '1',
                    ),
                    'last_name'  => array(
                        'name'        => 'last_name',
                        'type'        => 'text',
                        'label'       => 'Last Name',
                        'placeholder' => '',
                        'validation'  => '',
                        'required'    => '1',
                        'enabled'     => '1',
                    ),
                    'email'      => array(
                        'name'        => 'email',
                        'type'        => 'email',
                        'label'       => 'Email',
                        'placeholder' => '',
                        'validation'  => 'email',
                        'required'    => '1',
                        'enabled'     => '1',
                    ),
                    'message'    => array(
                        'name'        => 'message',
                        'type'        => 'textarea',
                        'label'       => 'Message',
                        'placeholder' => '',
                        'validation'  => '',
                        'required'    => '0',
                        'enabled'     => '1',
                    ),
                );
            }
            return $fields;
        }

        public function is_product_quote_enabled( $product_id ) {
            $settings = get_option( 'spbwc_quote_settings', array() );
            if ( ! isset( $settings['enable_quote'] ) || 'yes' !== $settings['enable_quote'] ) {
                return false;
            }
            return '1' === get_post_meta( $product_id, '_spbwc_enable_quote', true );
        }

        /**
         * Request flow: 'single' (per-product popup, default) or 'cart'
         * (multi-product quote bucket). QF5.
         *
         * @return string single|cart
         */
        public static function quote_mode() {
            $s = get_option( 'spbwc_quote_settings', array() );
            return ( isset( $s['quote_mode'] ) && 'cart' === $s['quote_mode'] ) ? 'cart' : 'single';
        }

        /** Whether the standalone [spbwc_quote_request] page is enabled. */
        public static function quote_page_enabled() {
            $s = get_option( 'spbwc_quote_settings', array() );
            return isset( $s['enable_quote_page'] ) && 'yes' === $s['enable_quote_page'];
        }

        /**
         * Resolve the effective D3 display mode for a product.
         *
         * @param int $product_id Product ID.
         * @return string off|both|replace|quote_only
         */
        public function get_display_mode( $product_id ) {
            if ( ! $this->is_product_quote_enabled( $product_id ) ) {
                return 'off';
            }
            $modes = array( 'both', 'replace', 'quote_only' );
            $per   = get_post_meta( $product_id, '_spbwc_quote_display_mode', true );
            if ( in_array( $per, $modes, true ) ) {
                return $per;
            }
            $settings = get_option( 'spbwc_quote_settings', array() );
            $global   = isset( $settings['display_mode'] ) ? $settings['display_mode'] : 'both';
            return in_array( $global, $modes, true ) ? $global : 'both';
        }

        /** Shared Get Quote trigger markup. */
        protected function quote_button_markup() {
            echo '<p class="spbwc-rfq-trigger"><button type="button" class="button alt" id="spbwc-open-quote-popup" aria-haspopup="dialog">' . esc_html__( 'Get Quote', 'storelly-product-builder-for-woocommerce' ) . '</button></p>';
        }

        /** "Both" mode — button rendered inside the add-to-cart form. */
        public function render_quote_button() {
            if ( ! is_product() || 'cart' === self::quote_mode() ) {
                return;
            }
            global $product; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- WooCommerce global variable.
            if ( ! $product || 'both' !== $this->get_display_mode( $product->get_id() ) ) {
                return;
            }
            $this->quote_button_markup();
        }

        /**
         * "Replace" / "quote_only" modes — the add-to-cart form is removed, so
         * the button is rendered standalone in the product summary.
         */
        public function render_quote_button_standalone() {
            if ( ! is_product() || 'cart' === self::quote_mode() ) {
                return;
            }
            global $product; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- WooCommerce global variable.
            if ( ! $product ) {
                return;
            }
            $mode = $this->get_display_mode( $product->get_id() );
            if ( 'replace' !== $mode && 'quote_only' !== $mode ) {
                return;
            }
            $this->quote_button_markup();
        }

        /**
         * Site-wide floating "Request a Quote" badge (M6). Rendered in the
         * footer on every page when enabled. On a quote-enabled product page
         * it opens the existing modal (no navigation); elsewhere it links to
         * the configured URL, falling back to the shop page.
         */
        public function render_quote_badge() {
            $settings = get_option( 'spbwc_quote_settings', array() );
            // Badge is published by default (M9.3): an absent sub-setting means
            // "on", matching the Quote Settings form default. An explicit 'no'
            // still hides it, so a merchant who turned it off stays in control.
            // We still require the quote feature itself to be enabled — the
            // badge never silently turns the whole quote system on.
            $badge_on = ! isset( $settings['enable_quote_badge'] ) || 'yes' === $settings['enable_quote_badge'];
            if ( ! is_array( $settings )
                || ! isset( $settings['enable_quote'] ) || 'yes' !== $settings['enable_quote']
                || ! $badge_on ) {
                return;
            }

            $position = isset( $settings['quote_badge_position'] ) ? $settings['quote_badge_position'] : 'bottom-right';
            if ( ! in_array( $position, array( 'bottom-right', 'bottom-left', 'top-right', 'top-left' ), true ) ) {
                $position = 'bottom-right';
            }
            $label = ( isset( $settings['quote_badge_label'] ) && '' !== $settings['quote_badge_label'] )
                ? $settings['quote_badge_label']
                : __( 'Request a Quote', 'storelly-product-builder-for-woocommerce' );

            // If the per-product modal is present on this page, open it instead.
            $open_modal = false;
            if ( function_exists( 'is_product' ) && is_product() ) {
                $open_modal = $this->is_product_quote_enabled( get_queried_object_id() );
            }

            $classes = 'spbwc-rfq-badge spbwc-rfq-badge--' . $position;

            if ( $open_modal ) {
                echo '<button type="button" class="' . esc_attr( $classes ) . '" id="spbwc-rfq-badge" aria-haspopup="dialog">'
                    . '<span class="spbwc-rfq-badge__label">' . esc_html( $label ) . '</span></button>';
                // Reuse the existing modal opener so we don't duplicate logic.
                echo '<script>(function(){var b=document.getElementById("spbwc-rfq-badge");if(!b){return;}b.addEventListener("click",function(){var o=document.getElementById("spbwc-open-quote-popup");if(o){o.click();}});})();</script>';
                return;
            }

            $url = isset( $settings['quote_badge_url'] ) && '' !== $settings['quote_badge_url'] ? $settings['quote_badge_url'] : '';
            if ( ! $url && function_exists( 'wc_get_page_permalink' ) ) {
                $url = wc_get_page_permalink( 'shop' );
            }
            if ( ! $url ) {
                $url = home_url( '/' );
            }
            echo '<a class="' . esc_attr( $classes ) . '" id="spbwc-rfq-badge" href="' . esc_url( $url ) . '">'
                . '<span class="spbwc-rfq-badge__label">' . esc_html( $label ) . '</span></a>';
        }

        /* ── D3 filters ───────────────────────────────────────────── */

        /**
         * Hide Add to cart for replace / quote_only modes.
         *
         * @param bool       $purchasable Current value.
         * @param WC_Product $product     Product.
         * @return bool
         */
        public function filter_is_purchasable( $purchasable, $product ) {
            if ( ! $purchasable || ! is_object( $product ) ) {
                return $purchasable;
            }
            $mode = $this->get_display_mode( $product->get_id() );
            return ( 'replace' === $mode || 'quote_only' === $mode ) ? false : $purchasable;
        }

        /**
         * Replace the price with "Price on request" for quote_only mode.
         *
         * @param string     $html    Price HTML.
         * @param WC_Product $product Product.
         * @return string
         */
        public function filter_price_html( $html, $product ) {
            if ( ! is_object( $product ) ) {
                return $html;
            }
            if ( 'quote_only' === $this->get_display_mode( $product->get_id() ) ) {
                return '<span class="spbwc-rfq-price-onrequest">' . esc_html__( 'Price on request', 'storelly-product-builder-for-woocommerce' ) . '</span>';
            }
            return $html;
        }

        /**
         * Swap the loop add-to-cart link for a "View product" link when the
         * cart is hidden.
         *
         * @param string     $html    Link HTML.
         * @param WC_Product $product Product.
         * @return string
         */
        public function filter_loop_add_to_cart( $html, $product ) {
            if ( ! is_object( $product ) ) {
                return $html;
            }
            $mode = $this->get_display_mode( $product->get_id() );
            if ( 'replace' === $mode || 'quote_only' === $mode ) {
                return '<a href="' . esc_url( get_permalink( $product->get_id() ) ) . '" class="button spbwc-rfq-loop-view">' . esc_html__( 'View product', 'storelly-product-builder-for-woocommerce' ) . '</a>';
            }
            return $html;
        }

        /**
         * Block direct add-to-cart (e.g. via ?add-to-cart= URL) when the cart
         * is hidden for this product.
         *
         * @param bool $valid      Whether adding is valid.
         * @param int  $product_id Product ID.
         * @return bool
         */
        public function filter_add_to_cart_validation( $valid, $product_id ) {
            $mode = $this->get_display_mode( (int) $product_id );
            if ( 'replace' === $mode || 'quote_only' === $mode ) {
                if ( function_exists( 'wc_add_notice' ) ) {
                    wc_add_notice( __( 'This product is available by quote only.', 'storelly-product-builder-for-woocommerce' ), 'error' );
                }
                return false;
            }
            return $valid;
        }

        /**
         * Strip price/offers from product structured data for quote_only mode
         * so search engines do not show a misleading price.
         *
         * @param array      $markup  Structured data.
         * @param WC_Product $product Product.
         * @return array
         */
        public function filter_structured_data( $markup, $product ) {
            if ( is_object( $product ) && 'quote_only' === $this->get_display_mode( $product->get_id() ) && isset( $markup['offers'] ) ) {
                unset( $markup['offers'] );
            }
            return $markup;
        }

        /**
         * Enqueue the storefront modal stylesheet + script on quote-enabled
         * single product pages.
         */
        public function enqueue_assets() {
            $is_account = function_exists( 'is_account_page' ) && is_account_page();
            $is_quote_product = false;
            if ( function_exists( 'is_product' ) && is_product() ) {
                global $product; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- WooCommerce global.
                $is_quote_product = $product && $this->is_product_quote_enabled( $product->get_id() );
            }
            // Site-wide floating badge (M6) needs the stylesheet on every page.
            $settings  = get_option( 'spbwc_quote_settings', array() );
            $badge_on  = is_array( $settings )
                && isset( $settings['enable_quote'] ) && 'yes' === $settings['enable_quote']
                && isset( $settings['enable_quote_badge'] ) && 'yes' === $settings['enable_quote_badge'];
            if ( ! $is_account && ! $is_quote_product && ! $badge_on ) {
                return;
            }
            // The My Account quote views only need the stylesheet.
            // Shared Printcart storefront tokens load first; quote-storefront.css consumes
            // the --nbd-* variables (with literal fallbacks) so Quote matches the other
            // user pages.
            wp_enqueue_style( 'spbwc-tokens-storefront', SPBWC_PB_CSS_URL . '_tokens-storefront.css', array(), SPBWC_PB_VERSION );
            $spbwc_rfq_css = SPBWC_PB_PLUGIN_DIR . 'static/css/quote-storefront.css';
            wp_enqueue_style( 'spbwc-quote-storefront', SPBWC_PB_CSS_URL . 'quote-storefront.css', array( 'spbwc-tokens-storefront' ), file_exists( $spbwc_rfq_css ) ? filemtime( $spbwc_rfq_css ) : SPBWC_PB_VERSION );
            if ( ! $is_quote_product ) {
                return;
            }
            wp_enqueue_script( 'spbwc-quote-storefront', SPBWC_PB_JS_URL . 'quote-storefront.js', array(), SPBWC_PB_VERSION, true );
            wp_localize_script(
                'spbwc-quote-storefront',
                'spbwcRfq',
                array(
                    'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
                    'action'      => 'spbwc_submit_quote',
                    'nonce'       => wp_create_nonce( 'spbwc_submit_quote_action' ),
                    'myQuotesUrl' => is_user_logged_in() ? wc_get_endpoint_url( 'quotes', '', wc_get_page_permalink( 'myaccount' ) ) : '',
                    'i18n'        => array(
                        'sending'      => __( 'Sending…', 'storelly-product-builder-for-woocommerce' ),
                        'submit'       => __( 'Submit request', 'storelly-product-builder-for-woocommerce' ),
                        'failed'       => __( 'Something went wrong. Please review the form.', 'storelly-product-builder-for-woocommerce' ),
                        'network'      => __( 'Request failed. Please try again.', 'storelly-product-builder-for-woocommerce' ),
                        'trackQuote'   => __( 'Track your quote', 'storelly-product-builder-for-woocommerce' ),
                        'required'     => __( 'This field is required.', 'storelly-product-builder-for-woocommerce' ),
                        'invalidEmail' => __( 'Please enter a valid email.', 'storelly-product-builder-for-woocommerce' ),
                        'invalidPhone' => __( 'Please enter a valid phone number.', 'storelly-product-builder-for-woocommerce' ),
                        'fixErrors'    => __( 'Please correct the highlighted fields.', 'storelly-product-builder-for-woocommerce' ),
                        'guestHint'    => __( 'We have emailed you a copy — watch your inbox for our reply with pricing.', 'storelly-product-builder-for-woocommerce' ),
                    ),
                )
            );
        }

        public function render_quote_popup() {
            if ( ! is_product() || 'cart' === self::quote_mode() ) {
                return;
            }
            global $product; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- WooCommerce global variable.
            if ( ! $product || ! $this->is_product_quote_enabled( $product->get_id() ) ) {
                return;
            }
            $fields  = $this->get_form_fields();
            $img     = $product->get_image( array( 52, 52 ) );
            ?>
            <div id="spbwc-quote-modal" class="spbwc-rfq spbwc-rfq-overlay" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="spbwc-rfq-title">
                <div class="spbwc-rfq-modal">
                    <div class="spbwc-rfq-head">
                        <h3 class="spbwc-rfq-title" id="spbwc-rfq-title"><?php esc_html_e( 'Request a Quote', 'storelly-product-builder-for-woocommerce' ); ?></h3>
                        <button type="button" class="spbwc-rfq-close" aria-label="<?php esc_attr_e( 'Close', 'storelly-product-builder-for-woocommerce' ); ?>">&times;</button>
                    </div>
                    <div class="spbwc-rfq-body">
                        <div class="spbwc-rfq-product">
                            <?php echo wp_kses_post( $img ); ?>
                            <div>
                                <div class="spbwc-rfq-product__name"><?php echo esc_html( $product->get_name() ); ?></div>
                                <?php $spbwc_price_html = $product->get_price_html(); ?>
                                <?php if ( $spbwc_price_html ) : ?>
                                    <div class="spbwc-rfq-product__price"><?php echo wp_kses_post( $spbwc_price_html ); ?></div>
                                <?php endif; ?>
                                <div class="spbwc-rfq-product__meta"><?php esc_html_e( 'Tell us what you need and we will send you a price.', 'storelly-product-builder-for-woocommerce' ); ?></div>
                            </div>
                        </div>

                        <div class="spbwc-rfq-alert" role="alert"></div>

                        <form id="spbwc-quote-form" novalidate>
                            <input type="hidden" name="product_id" value="<?php echo esc_attr( $product->get_id() ); ?>" />

                            <div class="spbwc-rfq-field">
                                <label for="spbwc_quote_quantity"><?php esc_html_e( 'Quantity', 'storelly-product-builder-for-woocommerce' ); ?></label>
                                <div class="spbwc-rfq-stepper">
                                    <button type="button" class="spbwc-rfq-stepper__btn" data-step="-1" aria-label="<?php esc_attr_e( 'Decrease quantity', 'storelly-product-builder-for-woocommerce' ); ?>">&minus;</button>
                                    <input class="spbwc-rfq-qty" id="spbwc_quote_quantity" type="number" min="1" step="1" name="quantity" value="1" />
                                    <button type="button" class="spbwc-rfq-stepper__btn" data-step="1" aria-label="<?php esc_attr_e( 'Increase quantity', 'storelly-product-builder-for-woocommerce' ); ?>">+</button>
                                </div>
                            </div>

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
                                $ph       = isset( $field['placeholder'] ) ? $field['placeholder'] : '';
                                ?>
                                <?php if ( 'file' === $type ) : ?>
                                    <?php
                                    $multiple  = isset( $field['allow_multiple'] ) && '1' === (string) $field['allow_multiple'];
                                    $max_files = isset( $field['max_files'] ) ? max( 1, (int) $field['max_files'] ) : 5;
                                    $max_size  = isset( $field['max_size'] ) ? (float) $field['max_size'] : 0;
                                    $allow_str = isset( $field['allow_type'] ) ? trim( (string) $field['allow_type'] ) : '';
                                    $exts      = '' !== $allow_str ? array_filter( array_map( 'trim', explode( ',', $allow_str ) ) ) : array();
                                    $accept    = '';
                                    if ( ! empty( $exts ) ) {
                                        $accept = implode( ',', array_map( static function ( $e ) { return '.' . ltrim( $e, '.' ); }, $exts ) );
                                    }
                                    $input_name = 'quote_files[' . $name . ']' . ( $multiple ? '[]' : '' );
                                    $hint_bits  = array();
                                    if ( ! empty( $exts ) ) {
                                        $hint_bits[] = strtoupper( implode( ', ', $exts ) );
                                    }
                                    if ( $max_size > 0 ) {
                                        /* translators: %s: maximum file size in megabytes */
                                        $hint_bits[] = sprintf( esc_html__( 'up to %s MB each', 'storelly-product-builder-for-woocommerce' ), (string) ( 0 + $max_size ) );
                                    }
                                    ?>
                                    <div class="spbwc-rfq-field spbwc-rfq-field--file" data-field="<?php echo esc_attr( $name ); ?>" data-multiple="<?php echo $multiple ? '1' : '0'; ?>" data-max-files="<?php echo esc_attr( (string) ( $multiple ? $max_files : 1 ) ); ?>" data-max-size="<?php echo esc_attr( (string) $max_size ); ?>">
                                        <label for="spbwc_quote_<?php echo esc_attr( $name ); ?>">
                                            <?php echo esc_html( isset( $field['label'] ) ? $field['label'] : ucfirst( $name ) ); ?>
                                            <?php if ( $required ) : ?><span class="spbwc-rfq-req" aria-hidden="true">*</span><?php endif; ?>
                                        </label>
                                        <div class="spbwc-rfq-drop">
                                            <input class="spbwc-rfq-file" id="spbwc_quote_<?php echo esc_attr( $name ); ?>" type="file" name="<?php echo esc_attr( $input_name ); ?>" <?php echo $multiple ? 'multiple' : ''; ?> <?php echo $required ? 'required' : ''; ?> <?php echo $accept ? 'accept="' . esc_attr( $accept ) . '"' : ''; ?> />
                                            <span class="spbwc-rfq-drop__text"><?php echo wp_kses_post( __( 'Drag files here or <span class="spbwc-rfq-drop__browse">browse</span>', 'storelly-product-builder-for-woocommerce' ) ); ?></span>
                                            <?php if ( ! empty( $hint_bits ) ) : ?>
                                                <span class="spbwc-rfq-drop__hint"><?php echo esc_html( implode( ' · ', $hint_bits ) ); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <ul class="spbwc-rfq-files" aria-live="polite"></ul>
                                        <span class="spbwc-rfq-error"></span>
                                    </div>
                                <?php else : ?>
                                    <div class="spbwc-rfq-field">
                                        <label for="spbwc_quote_<?php echo esc_attr( $name ); ?>">
                                            <?php echo esc_html( isset( $field['label'] ) ? $field['label'] : ucfirst( $name ) ); ?>
                                            <?php if ( $required ) : ?><span class="spbwc-rfq-req" aria-hidden="true">*</span><?php endif; ?>
                                        </label>
                                        <?php if ( 'textarea' === $type ) : ?>
                                            <textarea id="spbwc_quote_<?php echo esc_attr( $name ); ?>" name="quote_fields[<?php echo esc_attr( $name ); ?>]" <?php echo $required ? 'required' : ''; ?> placeholder="<?php echo esc_attr( $ph ); ?>"></textarea>
                                        <?php else : ?>
                                            <input id="spbwc_quote_<?php echo esc_attr( $name ); ?>" type="<?php echo esc_attr( in_array( $type, array( 'text', 'email', 'tel', 'number' ), true ) ? $type : 'text' ); ?>" name="quote_fields[<?php echo esc_attr( $name ); ?>]" <?php echo $required ? 'required' : ''; ?> placeholder="<?php echo esc_attr( $ph ); ?>" />
                                        <?php endif; ?>
                                        <span class="spbwc-rfq-error"></span>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>

                            <div class="spbwc-rfq-foot">
                                <button type="submit" class="spbwc-rfq-submit"><?php esc_html_e( 'Submit request', 'storelly-product-builder-for-woocommerce' ); ?></button>
                            </div>
                        </form>

                        <div class="spbwc-rfq-success">
                            <div class="spbwc-rfq-success__icon" aria-hidden="true">&#10003;</div>
                            <p class="spbwc-rfq-success__title"><?php esc_html_e( 'Request sent', 'storelly-product-builder-for-woocommerce' ); ?></p>
                            <p class="spbwc-rfq-success__text"><?php esc_html_e( 'Thanks! We have received your request and will get back to you shortly.', 'storelly-product-builder-for-woocommerce' ); ?></p>
                            <p class="spbwc-rfq-success__actions">
                                <a class="spbwc-rfq-success__cta" href="#" style="display:none;"></a>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            <?php
        }

        public function ajax_submit_quote() {
            $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
            if ( ! $nonce || ! wp_verify_nonce( $nonce, 'spbwc_submit_quote_action' ) ) {
                wp_send_json_error( array( 'message' => esc_html__( 'Security check failed.', 'storelly-product-builder-for-woocommerce' ) ) );
            }
            $product_id = isset( $_POST['product_id'] ) ? absint( wp_unslash( $_POST['product_id'] ) ) : 0;
            $quantity   = isset( $_POST['quantity'] ) ? max( 1, absint( wp_unslash( $_POST['quantity'] ) ) ) : 1;
            $product    = wc_get_product( $product_id );
            if ( ! $product || ! $this->is_product_quote_enabled( $product_id ) ) {
                wp_send_json_error( array( 'message' => esc_html__( 'Quote is not available for this product.', 'storelly-product-builder-for-woocommerce' ) ) );
            }

            // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verified above; each element sanitized in the loop below.
            $raw_fields = isset( $_POST['quote_fields'] ) ? (array) wp_unslash( $_POST['quote_fields'] ) : array();
            $fields     = array();
            foreach ( $raw_fields as $k => $v ) {
                $key = sanitize_key( $k );
                $fields[ $key ] = ( 'message' === $key ) ? sanitize_textarea_field( $v ) : sanitize_text_field( $v );
            }

            // Validate against the configured form schema; collect per-field errors.
            $schema = $this->get_form_fields();
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
                    /* translators: %s: form field label of the missing required input */
                    $errors[ $name ] = sprintf( esc_html__( '%s is required.', 'storelly-product-builder-for-woocommerce' ), $label );
                } elseif ( 'email' === $validation && '' !== $value && ! is_email( $value ) ) {
                    /* translators: %s: form field label of the field that failed email validation */
                    $errors[ $name ] = sprintf( esc_html__( '%s is not a valid email.', 'storelly-product-builder-for-woocommerce' ), $label );
                } elseif ( 'phone' === $validation && '' !== $value && ! preg_match( '/^[0-9\-\+\(\)\s]{6,20}$/', $value ) ) {
                    /* translators: %s: form field label of the field that failed phone validation */
                    $errors[ $name ] = sprintf( esc_html__( '%s is not a valid phone.', 'storelly-product-builder-for-woocommerce' ), $label );
                }
            }
            if ( ! empty( $errors ) ) {
                wp_send_json_error(
                    array(
                        'message' => esc_html__( 'Please correct the highlighted fields.', 'storelly-product-builder-for-woocommerce' ),
                        'errors'  => $errors,
                    )
                );
            }

            // Process file-upload fields only after the text fields validate, so a
            // failed submission never leaves orphaned uploads behind.
            $upload      = $this->handle_quote_uploads( $schema );
            $attachments = $upload['attachments'];
            if ( ! empty( $upload['errors'] ) ) {
                wp_send_json_error(
                    array(
                        'message' => esc_html__( 'Please correct the highlighted fields.', 'storelly-product-builder-for-woocommerce' ),
                        'errors'  => $upload['errors'],
                    )
                );
            }

            // Build the canonical request payload for the quote CPT. Known
            // contact keys are promoted to the top level; the rest are kept
            // under "fields" so the form builder stays fully customizable.
            $known   = array( 'first_name', 'last_name', 'email', 'phone', 'company', 'message' );
            $request = array(
                'product_id'   => $product_id,
                'product_name' => $product->get_name(),
                'quantity'     => $quantity,
                'fields'       => array(),
            );
            foreach ( $fields as $k => $v ) {
                if ( in_array( $k, $known, true ) ) {
                    $request[ $k ] = $v;
                } else {
                    $request['fields'][ $k ] = $v;
                }
            }
            if ( empty( $request['email'] ) && is_user_logged_in() ) {
                $current = wp_get_current_user();
                if ( $current instanceof WP_User ) {
                    $request['email'] = $current->user_email;
                }
            }

            if ( ! empty( $attachments ) ) {
                $request['attachments'] = $attachments;
            }

            $quote_id = SPBWC_Quote::create( $request, get_current_user_id() );
            if ( is_wp_error( $quote_id ) || ! $quote_id ) {
                // Don't leave uploaded files orphaned if the quote never persisted.
                foreach ( $attachments as $a ) {
                    if ( ! empty( $a['file'] ) ) {
                        $this->delete_attachment_file( $a['file'] );
                        $this->remove_attachment_dir( dirname( ltrim( (string) $a['file'], '/' ) ) );
                    }
                }
                wp_send_json_error( array( 'message' => esc_html__( 'Could not create your quote request. Please try again.', 'storelly-product-builder-for-woocommerce' ) ) );
            }

            // Pre-seed a line item from the requested product so the merchant
            // only needs to fill in the price when replying.
            SPBWC_Quote::set_lines(
                $quote_id,
                array(
                    array(
                        'label'      => $product->get_name(),
                        'desc'       => '',
                        'qty'        => $quantity,
                        'unit_price' => 0,
                    ),
                )
            );
            update_post_meta( $quote_id, '_spbwc_quote_product_id', $product_id );

            do_action( 'spbwc_quote_new_notification', $quote_id );
            if ( ! empty( $request['email'] ) ) {
                do_action( 'spbwc_quote_ack_notification', $quote_id );
            }

            $settings        = get_option( 'spbwc_quote_settings', array() );
            $success_message = ( isset( $settings['success_message'] ) && '' !== $settings['success_message'] )
                ? $settings['success_message']
                : __( 'Your quote request has been sent successfully.', 'storelly-product-builder-for-woocommerce' );

            wp_send_json_success(
                array(
                    'message'  => $success_message,
                    'quote_id' => $quote_id,
                )
            );
        }

        /* ── File attachments (QF1–QF4) ───────────────────────────── */

        /**
         * Process every enabled `file` field in the form schema.
         *
         * Files are stored under a single per-request folder so a failed
         * submission can be wiped wholesale. The caller has already verified the
         * nonce in ajax_submit_quote().
         *
         * @param array $schema Form-field schema (from get_form_fields()).
         * @return array{attachments:array,errors:array<string,string>}
         */
        public function handle_quote_uploads( $schema ) {
            $attachments = array();
            $errors      = array();

            // Anything to do?
            $has_file_field = false;
            foreach ( $schema as $f ) {
                if ( isset( $f['type'], $f['enabled'] ) && 'file' === $f['type'] && '1' === (string) $f['enabled'] ) {
                    $has_file_field = true;
                    break;
                }
            }
            if ( ! $has_file_field ) {
                return array( 'attachments' => array(), 'errors' => array() );
            }

            $max_total = (int) apply_filters( 'spbwc_quote_max_upload_bytes', 25 * 1024 * 1024 );
            $total     = 0;
            $subdir    = 'q-' . substr( md5( uniqid( '', true ) ), 0, 16 );

            foreach ( $schema as $f ) {
                if ( ! isset( $f['type'], $f['enabled'] ) || 'file' !== $f['type'] || '1' !== (string) $f['enabled'] ) {
                    continue;
                }
                $name = isset( $f['name'] ) ? sanitize_key( $f['name'] ) : '';
                if ( '' === $name ) {
                    continue;
                }
                $label    = isset( $f['label'] ) ? $f['label'] : $name;
                $required = isset( $f['required'] ) && '1' === (string) $f['required'];
                $files    = $this->normalize_field_files( $name );

                if ( empty( $files ) ) {
                    if ( $required ) {
                        /* translators: %s: form field label of the missing required upload */
                        $errors[ $name ] = sprintf( esc_html__( '%s is required.', 'storelly-product-builder-for-woocommerce' ), $label );
                    }
                    continue;
                }

                $multiple  = isset( $f['allow_multiple'] ) && '1' === (string) $f['allow_multiple'];
                $max_files = isset( $f['max_files'] ) ? max( 1, (int) $f['max_files'] ) : 5;
                if ( ! $multiple ) {
                    $files = array_slice( $files, 0, 1 );
                } elseif ( count( $files ) > $max_files ) {
                    /* translators: 1: field label, 2: maximum number of files */
                    $errors[ $name ] = sprintf( esc_html__( '%1$s allows at most %2$d files.', 'storelly-product-builder-for-woocommerce' ), $label, $max_files );
                    continue;
                }

                $cfg = array(
                    'allow_type' => isset( $f['allow_type'] ) ? (string) $f['allow_type'] : '',
                    'max_size'   => isset( $f['max_size'] ) ? (float) $f['max_size'] : 0,
                );

                foreach ( $files as $file ) {
                    $total += isset( $file['size'] ) ? (int) $file['size'] : 0;
                    if ( $total > $max_total ) {
                        $errors[ $name ] = esc_html__( 'The total size of all attachments is too large.', 'storelly-product-builder-for-woocommerce' );
                        break;
                    }
                    $stored = $this->store_quote_file( $file, $cfg, $subdir );
                    if ( is_wp_error( $stored ) ) {
                        /* translators: 1: field label, 2: reason the file was rejected */
                        $errors[ $name ] = sprintf( esc_html__( '%1$s: %2$s', 'storelly-product-builder-for-woocommerce' ), $label, $stored->get_error_message() );
                        continue;
                    }
                    $stored['field']  = $name;
                    $attachments[]    = $stored;
                }
            }

            // Roll back any stored files if the field set produced errors.
            if ( ! empty( $errors ) ) {
                foreach ( $attachments as $a ) {
                    if ( ! empty( $a['file'] ) ) {
                        $this->delete_attachment_file( $a['file'] );
                    }
                }
                $this->remove_attachment_dir( 'quote-attachments/' . $subdir );
                return array( 'attachments' => array(), 'errors' => $errors );
            }

            return array( 'attachments' => $attachments, 'errors' => array() );
        }

        /**
         * Normalize $_FILES['quote_files'][<field>] (single or multiple) into a
         * flat list of per-file arrays.
         *
         * @param string $field Sanitized field key.
         * @return array<int,array{name:string,type:string,tmp_name:string,error:int,size:int}>
         */
        protected function normalize_field_files( $field ) {
            $out = array();
            // phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_submit_quote(); raw paths validated via is_uploaded_file() + wp_check_filetype_and_ext() before use.
            if ( empty( $_FILES['quote_files'] ) || ! isset( $_FILES['quote_files']['name'][ $field ] ) ) {
                // phpcs:enable WordPress.Security.NonceVerification.Missing
                return $out;
            }
            $bucket = $_FILES['quote_files']; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- File array; entries validated by the uploader below.
            $names  = $bucket['name'][ $field ];
            if ( is_array( $names ) ) {
                foreach ( $names as $i => $n ) {
                    if ( '' === $n ) {
                        continue;
                    }
                    $out[] = array(
                        'name'     => sanitize_file_name( wp_basename( (string) $n ) ),
                        'type'     => isset( $bucket['type'][ $field ][ $i ] ) ? (string) $bucket['type'][ $field ][ $i ] : '',
                        'tmp_name' => isset( $bucket['tmp_name'][ $field ][ $i ] ) ? (string) $bucket['tmp_name'][ $field ][ $i ] : '',
                        'error'    => isset( $bucket['error'][ $field ][ $i ] ) ? (int) $bucket['error'][ $field ][ $i ] : UPLOAD_ERR_NO_FILE,
                        'size'     => isset( $bucket['size'][ $field ][ $i ] ) ? (int) $bucket['size'][ $field ][ $i ] : 0,
                    );
                }
            } elseif ( '' !== $names ) {
                $out[] = array(
                    'name'     => sanitize_file_name( wp_basename( (string) $names ) ),
                    'type'     => isset( $bucket['type'][ $field ] ) ? (string) $bucket['type'][ $field ] : '',
                    'tmp_name' => isset( $bucket['tmp_name'][ $field ] ) ? (string) $bucket['tmp_name'][ $field ] : '',
                    'error'    => isset( $bucket['error'][ $field ] ) ? (int) $bucket['error'][ $field ] : UPLOAD_ERR_NO_FILE,
                    'size'     => isset( $bucket['size'][ $field ] ) ? (int) $bucket['size'][ $field ] : 0,
                );
            }
            return $out;
        }

        /**
         * Validate and move one uploaded file into the quote-attachments folder.
         *
         * Mirrors the hardened option-upload path: a fixed safe extension
         * whitelist (never executables) intersected with the merchant's
         * allow_type, real MIME sniffing via wp_check_filetype_and_ext(), and a
         * server-enforced size cap.
         *
         * @param array  $file   Single normalized file array.
         * @param array  $cfg    Field config (allow_type, max_size).
         * @param string $subdir Per-request subfolder name.
         * @return array|WP_Error Stored attachment descriptor or error.
         */
        protected function store_quote_file( $file, $cfg, $subdir ) {
            if ( UPLOAD_ERR_OK !== (int) $file['error'] ) {
                return new WP_Error( 'spbwc_upload', esc_html__( 'upload failed.', 'storelly-product-builder-for-woocommerce' ) );
            }
            $tmp = isset( $file['tmp_name'] ) ? $file['tmp_name'] : '';
            if ( '' === $tmp || ! is_uploaded_file( $tmp ) ) {
                return new WP_Error( 'spbwc_upload', esc_html__( 'invalid upload.', 'storelly-product-builder-for-woocommerce' ) );
            }
            $clean = isset( $file['name'] ) ? $file['name'] : '';
            if ( '' === $clean ) {
                return new WP_Error( 'spbwc_upload', esc_html__( 'invalid file name.', 'storelly-product-builder-for-woocommerce' ) );
            }

            // Safe server-side whitelist (shared with the option-upload path).
            $safe = apply_filters(
                'spbwc_upload_file_allowed_extensions',
                array( 'json', 'svg', 'png', 'jpg', 'jpeg', 'gif', 'pdf', 'webp', 'bmp', 'tiff', 'tif', 'ai', 'eps', 'psd' )
            );
            $allowed = $safe;
            if ( isset( $cfg['allow_type'] ) && '' !== trim( (string) $cfg['allow_type'] ) ) {
                $merchant = array_filter( array_map(
                    static function ( $t ) { return strtolower( trim( $t ) ); },
                    explode( ',', (string) $cfg['allow_type'] )
                ) );
                if ( in_array( 'jpg', $merchant, true ) || in_array( 'jpeg', $merchant, true ) ) {
                    $merchant[] = 'jpg';
                    $merchant[] = 'jpeg';
                }
                if ( in_array( 'tiff', $merchant, true ) || in_array( 'tif', $merchant, true ) ) {
                    $merchant[] = 'tiff';
                    $merchant[] = 'tif';
                }
                $allowed = array_values( array_intersect( $safe, $merchant ) );
            }
            if ( empty( $allowed ) ) {
                return new WP_Error( 'spbwc_type', esc_html__( 'file type not allowed.', 'storelly-product-builder-for-woocommerce' ) );
            }

            add_filter( 'upload_mimes', array( $this, 'widen_quote_mimes' ) );
            $check = wp_check_filetype_and_ext( $tmp, $clean );
            $ext   = isset( $check['ext'] ) ? $check['ext'] : '';
            $type  = isset( $check['type'] ) ? $check['type'] : '';
            if ( '' === $ext || '' === $type || ! in_array( $ext, $allowed, true ) ) {
                remove_filter( 'upload_mimes', array( $this, 'widen_quote_mimes' ) );
                return new WP_Error( 'spbwc_type', esc_html__( 'file type not allowed.', 'storelly-product-builder-for-woocommerce' ) );
            }

            $size = isset( $file['size'] ) ? (int) $file['size'] : 0;
            if ( isset( $cfg['max_size'] ) && (float) $cfg['max_size'] > 0 ) {
                if ( $size > (float) $cfg['max_size'] * 1024 * 1024 ) {
                    remove_filter( 'upload_mimes', array( $this, 'widen_quote_mimes' ) );
                    return new WP_Error( 'spbwc_size', esc_html__( 'file is too large.', 'storelly-product-builder-for-woocommerce' ) );
                }
            }

            $rel_dir  = 'quote-attachments/' . $subdir;
            $abs_dir  = SPBWC_PB_UPLOAD_DIR . '/' . $rel_dir;
            if ( ! wp_mkdir_p( $abs_dir ) ) {
                remove_filter( 'upload_mimes', array( $this, 'widen_quote_mimes' ) );
                return new WP_Error( 'spbwc_dir', esc_html__( 'could not store the file.', 'storelly-product-builder-for-woocommerce' ) );
            }
            $this->guard_dir( $abs_dir );

            $new_name = (string) time() . substr( md5( (string) wp_rand( 1111, 9999 ) ), 0, 8 ) . '.' . $ext;

            if ( ! function_exists( 'wp_handle_upload' ) ) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
            }
            $this->quote_upload_subdir = $subdir;
            add_filter( 'upload_dir', array( $this, 'quote_upload_dir' ) );
            // wp_handle_upload() takes $file by reference — must be a variable.
            $file_arr = array(
                'name'     => $new_name,
                'type'     => $type,
                'tmp_name' => $tmp,
                'error'    => UPLOAD_ERR_OK,
                'size'     => $size,
            );
            $movefile = wp_handle_upload( $file_arr, array( 'test_form' => false ) );
            remove_filter( 'upload_dir', array( $this, 'quote_upload_dir' ) );
            remove_filter( 'upload_mimes', array( $this, 'widen_quote_mimes' ) );

            if ( ! $movefile || isset( $movefile['error'] ) ) {
                return new WP_Error( 'spbwc_move', esc_html__( 'could not store the file.', 'storelly-product-builder-for-woocommerce' ) );
            }

            return array(
                'field' => '',
                'name'  => $clean,
                'file'  => $rel_dir . '/' . $new_name,
                'url'   => SPBWC_PB_UPLOAD_URL . '/' . $rel_dir . '/' . $new_name,
                'size'  => $size,
                'type'  => $type,
            );
        }

        /** upload_dir override → quote-attachments/<subdir>. */
        public function quote_upload_dir( $dirs ) {
            $sub            = '/storelly-product-builder/uploads/quote-attachments/' . $this->quote_upload_subdir;
            $dirs['subdir'] = $sub;
            $dirs['path']   = $dirs['basedir'] . $sub;
            $dirs['url']    = $dirs['baseurl'] . $sub;
            return $dirs;
        }

        /** Print/design mimes allowed only around a quote upload (non-executable). */
        public function widen_quote_mimes( $mimes ) {
            $mimes['pdf']      = 'application/pdf';
            $mimes['ai']       = 'application/postscript';
            $mimes['eps']      = 'application/postscript';
            $mimes['psd']      = 'image/vnd.adobe.photoshop';
            $mimes['tiff|tif'] = 'image/tiff';
            $mimes['webp']     = 'image/webp';
            $mimes['bmp']      = 'image/bmp';
            return $mimes;
        }

        /** Drop an index.html into an attachment folder to block directory listing. */
        protected function guard_dir( $abs_dir ) {
            $index = trailingslashit( $abs_dir ) . 'index.html';
            if ( ! file_exists( $index ) ) {
                // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Tiny local guard file; WP_Filesystem is overkill here.
                @file_put_contents( $index, '' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
            }
        }

        /**
         * Resolve + delete one stored attachment file, guarding the path so we
         * only ever touch our own quote-attachments folder.
         *
         * @param string $rel Path relative to SPBWC_PB_UPLOAD_DIR.
         */
        protected function delete_attachment_file( $rel ) {
            $rel = ltrim( (string) $rel, '/' );
            if ( 0 !== strpos( $rel, 'quote-attachments/' ) || false !== strpos( $rel, '..' ) ) {
                return;
            }
            $full = SPBWC_PB_UPLOAD_DIR . '/' . $rel;
            if ( file_exists( $full ) ) {
                wp_delete_file( $full );
            }
        }

        /**
         * Remove an attachment folder (index guard + the now-empty directory).
         *
         * @param string $rel_dir Folder relative to SPBWC_PB_UPLOAD_DIR.
         */
        protected function remove_attachment_dir( $rel_dir ) {
            $rel_dir = ltrim( (string) $rel_dir, '/' );
            if ( 0 !== strpos( $rel_dir, 'quote-attachments/' ) || false !== strpos( $rel_dir, '..' ) ) {
                return;
            }
            $abs   = SPBWC_PB_UPLOAD_DIR . '/' . $rel_dir;
            $index = trailingslashit( $abs ) . 'index.html';
            if ( file_exists( $index ) ) {
                wp_delete_file( $index );
            }
            if ( is_dir( $abs ) ) {
                @rmdir( $abs ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Best-effort cleanup of our own empty folder.
            }
        }

        /**
         * Delete a quote's uploaded attachments when the quote is permanently
         * removed (before_delete_post).
         *
         * @param int $post_id Post being deleted.
         */
        public function cleanup_quote_attachments( $post_id ) {
            $post = get_post( $post_id );
            if ( ! $post || SPBWC_Quote::POST_TYPE !== $post->post_type ) {
                return;
            }
            $request = get_post_meta( $post_id, SPBWC_Quote::META_REQUEST, true );
            if ( ! is_array( $request ) || empty( $request['attachments'] ) || ! is_array( $request['attachments'] ) ) {
                return;
            }
            $dirs = array();
            foreach ( $request['attachments'] as $a ) {
                if ( empty( $a['file'] ) ) {
                    continue;
                }
                $this->delete_attachment_file( $a['file'] );
                $rel_dir = dirname( ltrim( (string) $a['file'], '/' ) );
                if ( 0 === strpos( $rel_dir, 'quote-attachments/' ) ) {
                    $dirs[ $rel_dir ] = true;
                }
            }
            foreach ( array_keys( $dirs ) as $rel_dir ) {
                $this->remove_attachment_dir( $rel_dir );
            }
        }

        /**
         * Daily GC: delete attachment folders not referenced by any quote and
         * older than the retention window (QF9). Guards against orphans left by
         * an interrupted upload; referenced folders are always kept.
         */
        public function gc_orphan_attachments() {
            $base = SPBWC_PB_UPLOAD_DIR . '/quote-attachments';
            if ( ! is_dir( $base ) ) {
                return;
            }
            $retain = (int) apply_filters( 'spbwc_quote_attachment_retention_days', 30 );
            $cutoff = time() - max( 1, $retain ) * DAY_IN_SECONDS;

            // Collect folders still referenced by a quote's request payload.
            $referenced = array();
            $quotes     = get_posts(
                array(
                    'post_type'      => SPBWC_Quote::POST_TYPE,
                    'post_status'    => array_keys( SPBWC_Quote::statuses() ),
                    'posts_per_page' => -1,
                    'fields'         => 'ids',
                    'no_found_rows'  => true,
                )
            );
            foreach ( (array) $quotes as $qid ) {
                $req = get_post_meta( $qid, SPBWC_Quote::META_REQUEST, true );
                if ( ! is_array( $req ) || empty( $req['attachments'] ) || ! is_array( $req['attachments'] ) ) {
                    continue;
                }
                foreach ( $req['attachments'] as $a ) {
                    if ( ! empty( $a['file'] ) ) {
                        $referenced[ dirname( ltrim( (string) $a['file'], '/' ) ) ] = true;
                    }
                }
            }

            $entries = scandir( $base );
            if ( ! is_array( $entries ) ) {
                return;
            }
            foreach ( $entries as $entry ) {
                if ( '.' === $entry || '..' === $entry ) {
                    continue;
                }
                $dir = $base . '/' . $entry;
                if ( ! is_dir( $dir ) ) {
                    continue;
                }
                $rel = 'quote-attachments/' . $entry;
                if ( isset( $referenced[ $rel ] ) ) {
                    continue;
                }
                $mtime = @filemtime( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
                if ( $mtime && $mtime > $cutoff ) {
                    continue; // Too new — may be an in-flight upload.
                }
                $files = scandir( $dir );
                if ( is_array( $files ) ) {
                    foreach ( $files as $f ) {
                        if ( '.' === $f || '..' === $f ) {
                            continue;
                        }
                        wp_delete_file( $dir . '/' . $f );
                    }
                }
                @rmdir( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Best-effort cleanup of our own empty folder.
            }
        }

        public function register_quote_order_statuses() {
            register_post_status(
                'wc-spbwc-quote-new',
                array(
                    'label'                     => __( 'New Quote Request', 'storelly-product-builder-for-woocommerce' ),
                    'public'                    => true,
                    'exclude_from_search'       => false,
                    'show_in_admin_all_list'    => true,
                    'show_in_admin_status_list' => true,
                    /* translators: %s: number of new quote requests in the WC admin filter list */
                    'label_count'               => _n_noop( 'New Quote Request <span class="count">(%s)</span>', 'New Quote Requests <span class="count">(%s)</span>', 'storelly-product-builder-for-woocommerce' ),
                )
            );
            register_post_status(
                'wc-spbwc-quote-accepted',
                array(
                    'label'                     => __( 'Accepted Quote', 'storelly-product-builder-for-woocommerce' ),
                    'public'                    => true,
                    'exclude_from_search'       => false,
                    'show_in_admin_all_list'    => true,
                    'show_in_admin_status_list' => true,
                    /* translators: %s: number of accepted quotes in the WC admin filter list */
                    'label_count'               => _n_noop( 'Accepted Quote <span class="count">(%s)</span>', 'Accepted Quotes <span class="count">(%s)</span>', 'storelly-product-builder-for-woocommerce' ),
                )
            );
            register_post_status(
                'wc-spbwc-quote-rejected',
                array(
                    'label'                     => __( 'Rejected Quote', 'storelly-product-builder-for-woocommerce' ),
                    'public'                    => true,
                    'exclude_from_search'       => false,
                    'show_in_admin_all_list'    => true,
                    'show_in_admin_status_list' => true,
                    /* translators: %s: number of rejected quotes in the WC admin filter list */
                    'label_count'               => _n_noop( 'Rejected Quote <span class="count">(%s)</span>', 'Rejected Quotes <span class="count">(%s)</span>', 'storelly-product-builder-for-woocommerce' ),
                )
            );
        }

        public function add_quote_statuses_to_order_statuses( $statuses ) {
            $statuses['wc-spbwc-quote-new']      = __( 'New Quote Request', 'storelly-product-builder-for-woocommerce' );
            $statuses['wc-spbwc-quote-accepted'] = __( 'Accepted Quote', 'storelly-product-builder-for-woocommerce' );
            $statuses['wc-spbwc-quote-rejected'] = __( 'Rejected Quote', 'storelly-product-builder-for-woocommerce' );
            return $statuses;
        }

        public function add_account_endpoints() {
            add_rewrite_endpoint( 'quotes', EP_ROOT | EP_PAGES );
            add_rewrite_endpoint( 'view-quote', EP_ROOT | EP_PAGES );
            // Self-healing one-time flush so the endpoints resolve right after
            // activation without re-saving permalinks, and without depending on
            // another endpoint class flushing the whole rule set for us.
            if ( 'yes' !== get_option( 'spbwc_quotes_endpoint_flushed' ) ) {
                flush_rewrite_rules( false );
                update_option( 'spbwc_quotes_endpoint_flushed', 'yes' );
            }
        }

        public function add_quotes_account_menu( $items ) {
            $logout = isset( $items['customer-logout'] ) ? $items['customer-logout'] : null;
            if ( isset( $items['customer-logout'] ) ) {
                unset( $items['customer-logout'] );
            }
            $label = __( 'Quotes', 'storelly-product-builder-for-woocommerce' );
            if ( is_user_logged_in() ) {
                $count = self::get_awaiting_count( get_current_user_id() );
                if ( $count > 0 ) {
                    /* translators: %d: number of quotes awaiting the customer's response */
                    $label .= ' ' . sprintf( __( '(%d)', 'storelly-product-builder-for-woocommerce' ), $count );
                }
            }
            $items['quotes'] = $label;
            if ( null !== $logout ) {
                $items['customer-logout'] = $logout;
            }
            return $items;
        }

        /**
         * Number of quotes awaiting the buyer's response (status = sent).
         *
         * Cached per-user for 60s so the My-Account menu badge does not run a
         * get_posts() on every account page-load. Invalidated immediately by
         * invalidate_awaiting_count() when any quote authored by the user changes
         * status (hooked to spbwc_quote_status_changed), so the badge stays fresh.
         *
         * @param int $user_id Buyer user ID.
         * @return int
         */
        public static function get_awaiting_count( $user_id ) {
            $user_id = absint( $user_id );
            if ( ! $user_id ) {
                return 0;
            }
            $key    = 'spbwc_q_await_' . $user_id;
            $cached = get_transient( $key );
            if ( false !== $cached ) {
                return (int) $cached;
            }
            $awaiting = get_posts(
                array(
                    'post_type'      => SPBWC_Quote::POST_TYPE,
                    'post_status'    => SPBWC_Quote::STATUS_SENT,
                    'author'         => $user_id,
                    'posts_per_page' => 20,
                    'fields'         => 'ids',
                    'no_found_rows'  => true,
                )
            );
            $count = is_array( $awaiting ) ? count( $awaiting ) : 0;
            set_transient( $key, $count, MINUTE_IN_SECONDS );
            return $count;
        }

        /**
         * Drop the cached awaiting-count for a quote's author on any status change.
         *
         * @param int    $post_id Quote post ID.
         * @param string $from    Previous status (unused).
         * @param string $to      New status (unused).
         */
        public static function invalidate_awaiting_count( $post_id, $from = '', $to = '' ) {
            $author = (int) get_post_field( 'post_author', absint( $post_id ) );
            if ( $author ) {
                delete_transient( 'spbwc_q_await_' . $author );
            }
        }

        /**
         * Buyer-facing status pill markup (storefront).
         *
         * @param string $status Quote status slug.
         * @return string HTML.
         */
        protected function buyer_pill( $status ) {
            $map = array(
                SPBWC_Quote::STATUS_NEW         => 'warn',
                SPBWC_Quote::STATUS_REVIEW      => 'info',
                SPBWC_Quote::STATUS_SENT        => 'info',
                SPBWC_Quote::STATUS_NEGOTIATING => 'warn',
                SPBWC_Quote::STATUS_ACCEPTED    => 'ok',
                SPBWC_Quote::STATUS_CONVERTED   => 'ok',
                SPBWC_Quote::STATUS_DECLINED    => 'danger',
            );
            $statuses = SPBWC_Quote::statuses();
            $variant  = isset( $map[ $status ] ) ? $map[ $status ] : '';
            $label    = isset( $statuses[ $status ] ) ? $statuses[ $status ] : $status;
            return '<span class="spbwc-rfq-pill' . ( $variant ? ' spbwc-rfq-pill--' . esc_attr( $variant ) : '' ) . '">' . esc_html( $label ) . '</span>';
        }

        /**
         * Quotes belonging to the current user are stored as spbwc_quote posts
         * authored by the user. List them in My Account.
         */
        public function render_quotes_endpoint() {
            if ( ! is_user_logged_in() ) {
                wc_get_template( 'myaccount/form-login.php' );
                return;
            }
            $all = get_posts(
                array(
                    'post_type'      => SPBWC_Quote::POST_TYPE,
                    'post_status'    => array_keys( SPBWC_Quote::statuses() ),
                    'author'         => get_current_user_id(),
                    'numberposts'    => -1,
                    'orderby'        => 'modified',
                    'order'          => 'DESC',
                )
            );

            $use_shell = class_exists( 'SPBWC_Account_Shell' );
            if ( $use_shell ) {
                SPBWC_Account_Shell::open(
                    array(
                        'eyebrow'  => __( 'Custom quotes', 'storelly-product-builder-for-woocommerce' ),
                        'title'    => __( 'My Quotes', 'storelly-product-builder-for-woocommerce' ),
                        'subtitle' => __( 'Track your quote requests and respond to merchant offers.', 'storelly-product-builder-for-woocommerce' ),
                    )
                );
            } else {
                echo '<h2>' . esc_html__( 'My Quotes', 'storelly-product-builder-for-woocommerce' ) . '</h2>';
            }
            echo '<div class="spbwc-rfq spbwc-rfq-account">';
            if ( empty( $all ) ) {
                if ( $use_shell ) {
                    $shop_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );
                    SPBWC_Account_Shell::empty_state(
                        array(
                            'icon'      => '📝',
                            'title'     => __( 'No quotes yet', 'storelly-product-builder-for-woocommerce' ),
                            'text'      => __( 'Open a product and choose "Request a quote" to ask for custom pricing. Your requests will appear here.', 'storelly-product-builder-for-woocommerce' ),
                            'cta_label' => __( 'Browse products', 'storelly-product-builder-for-woocommerce' ),
                            'cta_url'   => $shop_url,
                        )
                    );
                } else {
                    echo '<p>' . esc_html__( 'You have not requested any quotes yet.', 'storelly-product-builder-for-woocommerce' ) . '</p>';
                }
                echo '</div>';
                if ( $use_shell ) {
                    SPBWC_Account_Shell::close();
                }
                return;
            }

            // Aggregate stats + per-filter counts.
            $awaiting = 0;
            $converted = 0;
            $declined = 0;
            $quoted_value = 0.0;
            $currency = '';
            foreach ( $all as $q ) {
                $t = SPBWC_Quote::get_totals( $q->ID );
                if ( '' === $currency && ! empty( $t['currency'] ) ) {
                    $currency = $t['currency'];
                }
                if ( SPBWC_Quote::STATUS_SENT === $q->post_status ) {
                    $awaiting++;
                }
                if ( SPBWC_Quote::STATUS_CONVERTED === $q->post_status ) {
                    $converted++;
                }
                if ( SPBWC_Quote::STATUS_DECLINED === $q->post_status ) {
                    $declined++;
                }
                if ( in_array( $q->post_status, array( SPBWC_Quote::STATUS_SENT, SPBWC_Quote::STATUS_ACCEPTED, SPBWC_Quote::STATUS_CONVERTED ), true ) ) {
                    $quoted_value += isset( $t['total'] ) ? (float) $t['total'] : 0;
                }
            }
            $currency = $currency ? $currency : get_woocommerce_currency();
            $cur      = array( 'currency' => $currency );

            // Stat cards.
            echo '<div class="spbwc-rfq-stats">';
            echo '<div class="spbwc-rfq-stat"><span class="spbwc-rfq-stat__value">' . esc_html( number_format_i18n( count( $all ) ) ) . '</span><span class="spbwc-rfq-stat__label">' . esc_html__( 'Total quotes', 'storelly-product-builder-for-woocommerce' ) . '</span></div>';
            echo '<div class="spbwc-rfq-stat spbwc-rfq-stat--accent"><span class="spbwc-rfq-stat__value">' . esc_html( number_format_i18n( $awaiting ) ) . '</span><span class="spbwc-rfq-stat__label">' . esc_html__( 'Awaiting your action', 'storelly-product-builder-for-woocommerce' ) . '</span></div>';
            echo '<div class="spbwc-rfq-stat"><span class="spbwc-rfq-stat__value">' . wp_kses_post( wc_price( $quoted_value, $cur ) ) . '</span><span class="spbwc-rfq-stat__label">' . esc_html__( 'Quoted value', 'storelly-product-builder-for-woocommerce' ) . '</span></div>';
            echo '<div class="spbwc-rfq-stat"><span class="spbwc-rfq-stat__value">' . esc_html( number_format_i18n( $converted ) ) . '</span><span class="spbwc-rfq-stat__label">' . esc_html__( 'Converted to orders', 'storelly-product-builder-for-woocommerce' ) . '</span></div>';
            echo '</div>';

            // Status filter tabs.
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter.
            $filter   = isset( $_GET['quote_status'] ) ? sanitize_key( wp_unslash( $_GET['quote_status'] ) ) : '';
            $base_url = wc_get_endpoint_url( 'quotes', '', wc_get_page_permalink( 'myaccount' ) );
            $tabs     = array(
                ''          => array( __( 'All', 'storelly-product-builder-for-woocommerce' ), count( $all ) ),
                'awaiting'  => array( __( 'Awaiting response', 'storelly-product-builder-for-woocommerce' ), $awaiting ),
                'converted' => array( __( 'Converted', 'storelly-product-builder-for-woocommerce' ), $converted ),
                'declined'  => array( __( 'Declined', 'storelly-product-builder-for-woocommerce' ), $declined ),
            );
            echo '<div class="spbwc-rfq-filter">';
            foreach ( $tabs as $key => $info ) {
                if ( '' !== $key && $key !== $filter && 0 === $info[1] ) {
                    continue;
                }
                $url = '' === $key ? $base_url : add_query_arg( 'quote_status', $key, $base_url );
                echo '<a class="spbwc-rfq-filter__tab' . ( $key === $filter ? ' is-active' : '' ) . '" href="' . esc_url( $url ) . '">' . esc_html( $info[0] ) . ' <span class="spbwc-rfq-filter__count">' . esc_html( number_format_i18n( $info[1] ) ) . '</span></a>';
            }
            echo '</div>';

            // Apply filter.
            $status_filter = array(
                'awaiting'  => array( SPBWC_Quote::STATUS_SENT ),
                'converted' => array( SPBWC_Quote::STATUS_CONVERTED ),
                'declined'  => array( SPBWC_Quote::STATUS_DECLINED ),
            );
            $quotes = $all;
            if ( isset( $status_filter[ $filter ] ) ) {
                $allowed = $status_filter[ $filter ];
                $quotes  = array_values(
                    array_filter(
                        $all,
                        function ( $q ) use ( $allowed ) {
                            return in_array( $q->post_status, $allowed, true );
                        }
                    )
                );
            }
            if ( empty( $quotes ) ) {
                echo '<p class="spbwc-rfq-empty">' . esc_html__( 'No quotes in this view.', 'storelly-product-builder-for-woocommerce' ) . '</p></div>';
                if ( $use_shell ) {
                    SPBWC_Account_Shell::close();
                }
                return;
            }

            echo '<table class="shop_table shop_table_responsive spbwc-rfq-table"><thead><tr>';
            echo '<th>' . esc_html__( 'Quote', 'storelly-product-builder-for-woocommerce' ) . '</th>';
            echo '<th>' . esc_html__( 'Date', 'storelly-product-builder-for-woocommerce' ) . '</th>';
            echo '<th>' . esc_html__( 'Status', 'storelly-product-builder-for-woocommerce' ) . '</th>';
            echo '<th class="spbwc-rfq-num">' . esc_html__( 'Total', 'storelly-product-builder-for-woocommerce' ) . '</th>';
            echo '<th></th>';
            echo '</tr></thead><tbody>';
            foreach ( $quotes as $quote ) {
                $totals   = SPBWC_Quote::get_totals( $quote->ID );
                $number   = get_post_meta( $quote->ID, SPBWC_Quote::META_NUMBER, true );
                $view_url = wc_get_endpoint_url( 'view-quote', $quote->ID, wc_get_page_permalink( 'myaccount' ) );
                $total    = isset( $totals['total'] ) ? (float) $totals['total'] : 0;
                echo '<tr>';
                echo '<td><a href="' . esc_url( $view_url ) . '">' . esc_html( $number ? $number : '#' . $quote->ID ) . '</a></td>';
                echo '<td>' . esc_html( get_the_date( get_option( 'date_format' ), $quote ) ) . '</td>';
                echo '<td>' . $this->buyer_pill( $quote->post_status ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- buyer_pill returns escaped markup.
                echo '<td class="spbwc-rfq-num">' . ( $total > 0 ? wp_kses_post( wc_price( $total, array( 'currency' => isset( $totals['currency'] ) ? $totals['currency'] : '' ) ) ) : '—' ) . '</td>';
                echo '<td><a class="spbwc-rfq-btn spbwc-rfq-btn--ghost" href="' . esc_url( $view_url ) . '">' . esc_html__( 'View', 'storelly-product-builder-for-woocommerce' ) . '</a></td>';
                echo '</tr>';
            }
            echo '</tbody></table></div>';
            if ( $use_shell ) {
                SPBWC_Account_Shell::close();
            }
        }

        public function render_view_quote_endpoint() {
            global $wp;
            if ( ! is_user_logged_in() ) {
                wc_get_template( 'myaccount/form-login.php' );
                return;
            }
            $quote_id = isset( $wp->query_vars['view-quote'] ) ? absint( $wp->query_vars['view-quote'] ) : 0;
            $post     = $quote_id ? get_post( $quote_id ) : null;
            $is_quote = $post && SPBWC_Quote::POST_TYPE === $post->post_type;
            $is_owner = $is_quote && (int) $post->post_author === get_current_user_id();

            // Merchant preview: a manager can view the buyer-facing page (read-only)
            // via a nonce-signed link from the admin detail.
            $is_preview = false;
            if ( $is_quote && ! $is_owner && current_user_can( 'manage_woocommerce' ) && isset( $_GET['spbwc_preview'], $_GET['_wpnonce'] ) ) {
                $pn = sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) );
                if ( wp_verify_nonce( $pn, 'spbwc_quote_preview_' . $quote_id ) ) {
                    $is_preview = true;
                }
            }
            if ( ! $is_quote || ( ! $is_owner && ! $is_preview ) ) {
                echo '<p>' . esc_html__( 'Quote not found.', 'storelly-product-builder-for-woocommerce' ) . '</p>';
                return;
            }
            $status   = $post->post_status;
            $number   = get_post_meta( $quote_id, SPBWC_Quote::META_NUMBER, true );
            $lines    = SPBWC_Quote::get_lines( $quote_id );
            $totals   = SPBWC_Quote::get_totals( $quote_id );
            $valid    = (string) get_post_meta( $quote_id, SPBWC_Quote::META_VALID_UNTIL, true );
            $note     = (string) get_post_meta( $quote_id, SPBWC_Quote::META_CUSTOMER_NOTE, true );
            $currency = isset( $totals['currency'] ) && $totals['currency'] ? $totals['currency'] : get_woocommerce_currency();
            $cur      = array( 'currency' => $currency );

            echo '<div class="spbwc-rfq spbwc-rfq-account">';
            $this->render_view_notice();

            echo '<div class="spbwc-rfq-acc-head">';
            /* translators: %s: quote number e.g. Q-2026-0001 */
            echo '<h2>' . esc_html( sprintf( __( 'Quote %s', 'storelly-product-builder-for-woocommerce' ), $number ? $number : '#' . $quote_id ) ) . '</h2>';
            echo $this->buyer_pill( $status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- buyer_pill returns escaped markup.
            if ( ! $is_preview && class_exists( 'SPBWC_Quote_PDF' ) ) {
                echo '<a class="spbwc-rfq-btn spbwc-rfq-btn--ghost spbwc-rfq-print" href="' . esc_url( SPBWC_Quote_PDF::print_url( $quote_id ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'Download / Print', 'storelly-product-builder-for-woocommerce' ) . '</a>';
            }
            echo '</div>';

            if ( $is_preview ) {
                echo '<div class="spbwc-rfq-banner spbwc-rfq-banner--info">' . esc_html__( 'Preview — this is what the customer sees. Actions are disabled here.', 'storelly-product-builder-for-woocommerce' ) . '</div>';
            }

            // Validity countdown (only meaningful while awaiting the buyer).
            if ( SPBWC_Quote::STATUS_SENT === $status && '' !== $valid ) {
                $ts   = strtotime( $valid . ' 23:59:59' );
                $days = (int) ceil( ( $ts - current_time( 'timestamp' ) ) / DAY_IN_SECONDS );
                if ( $days >= 0 ) {
                    $days_label = sprintf(
                        /* translators: %d: number of days a quote remains valid */
                        _n( '%d day', '%d days', $days, 'storelly-product-builder-for-woocommerce' ),
                        $days
                    );
                    echo '<p class="spbwc-rfq-countdown">' . sprintf(
                        /* translators: 1: number of days, 2: expiry date */
                        esc_html__( 'Valid for %1$s — expires %2$s', 'storelly-product-builder-for-woocommerce' ),
                        '<strong>' . esc_html( $days_label ) . '</strong>',
                        esc_html( date_i18n( get_option( 'date_format' ), $ts ) )
                    ) . '</p>';
                }
            }

            // Revision diff (P3.9) — what changed since the previous quote.
            $this->render_revision_diff( $quote_id, $cur );

            // Priced line items.
            if ( ! empty( $lines ) ) {
                echo '<div class="spbwc-rfq-summary"><h3>' . esc_html__( "What's included", 'storelly-product-builder-for-woocommerce' ) . '</h3>';
                echo '<table class="shop_table spbwc-rfq-table"><thead><tr>';
                echo '<th>' . esc_html__( 'Item', 'storelly-product-builder-for-woocommerce' ) . '</th>';
                echo '<th class="spbwc-rfq-num">' . esc_html__( 'Qty', 'storelly-product-builder-for-woocommerce' ) . '</th>';
                echo '<th class="spbwc-rfq-num">' . esc_html__( 'Unit', 'storelly-product-builder-for-woocommerce' ) . '</th>';
                echo '<th class="spbwc-rfq-num">' . esc_html__( 'Total', 'storelly-product-builder-for-woocommerce' ) . '</th>';
                echo '</tr></thead><tbody>';
                foreach ( $lines as $line ) {
                    echo '<tr>';
                    echo '<td>' . esc_html( isset( $line['label'] ) ? $line['label'] : '' ) . '</td>';
                    echo '<td class="spbwc-rfq-num">' . esc_html( isset( $line['qty'] ) ? (string) ( 0 + $line['qty'] ) : '' ) . '</td>';
                    echo '<td class="spbwc-rfq-num">' . wp_kses_post( wc_price( isset( $line['unit_price'] ) ? (float) $line['unit_price'] : 0, $cur ) ) . '</td>';
                    echo '<td class="spbwc-rfq-num">' . wp_kses_post( wc_price( isset( $line['line_total'] ) ? (float) $line['line_total'] : 0, $cur ) ) . '</td>';
                    echo '</tr>';
                }
                echo '</tbody></table>';

                echo '<div class="spbwc-rfq-totals">';
                echo '<div class="spbwc-rfq-totals__row"><span>' . esc_html__( 'Subtotal', 'storelly-product-builder-for-woocommerce' ) . '</span><span>' . wp_kses_post( wc_price( isset( $totals['subtotal'] ) ? (float) $totals['subtotal'] : 0, $cur ) ) . '</span></div>';
                if ( ! empty( $totals['discount'] ) ) {
                    echo '<div class="spbwc-rfq-totals__row"><span>' . esc_html__( 'Discount', 'storelly-product-builder-for-woocommerce' ) . '</span><span>-' . wp_kses_post( wc_price( (float) $totals['discount'], $cur ) ) . '</span></div>';
                }
                if ( ! empty( $totals['tax'] ) ) {
                    echo '<div class="spbwc-rfq-totals__row"><span>' . esc_html__( 'Tax', 'storelly-product-builder-for-woocommerce' ) . '</span><span>' . wp_kses_post( wc_price( (float) $totals['tax'], $cur ) ) . '</span></div>';
                }
                echo '<div class="spbwc-rfq-totals__row spbwc-rfq-totals__row--grand"><span>' . esc_html__( 'Total', 'storelly-product-builder-for-woocommerce' ) . '</span><span>' . wp_kses_post( wc_price( isset( $totals['total'] ) ? (float) $totals['total'] : 0, $cur ) ) . '</span></div>';
                echo '</div>'; // .spbwc-rfq-totals
                echo '</div>'; // .spbwc-rfq-summary
            }

            if ( '' !== $note ) {
                echo '<div class="spbwc-rfq-card"><h3>' . esc_html__( 'Note from us', 'storelly-product-builder-for-woocommerce' ) . '</h3><p>' . nl2br( esc_html( $note ) ) . '</p></div>';
            }

            // Action area depends on status. In merchant preview, never show the
            // buyer's decision forms.
            if ( $is_preview ) {
                if ( SPBWC_Quote::STATUS_SENT === $status ) {
                    echo '<div class="spbwc-rfq-banner">' . esc_html__( 'The customer sees Accept / Request changes / Decline buttons here.', 'storelly-product-builder-for-woocommerce' ) . '</div>';
                }
            } elseif ( SPBWC_Quote::STATUS_SENT === $status ) {
                $this->render_buyer_actions( $quote_id );
            } elseif ( SPBWC_Quote::STATUS_NEGOTIATING === $status ) {
                echo '<div class="spbwc-rfq-banner spbwc-rfq-banner--info">' . esc_html__( 'Your change request has been sent. We will get back to you with a revised quote.', 'storelly-product-builder-for-woocommerce' ) . '</div>';
            } elseif ( SPBWC_Quote::STATUS_CONVERTED === $status ) {
                $order_id  = (int) get_post_meta( $quote_id, SPBWC_Quote::META_ORDER_ID, true );
                $order     = $order_id ? wc_get_order( $order_id ) : false;
                $pay_terms = (string) get_post_meta( $quote_id, SPBWC_Quote::META_PAYMENT_TERMS, true );
                $net       = SPBWC_Quote::term_net_days( $pay_terms );
                echo '<div class="spbwc-rfq-banner spbwc-rfq-banner--ok">' . esc_html__( 'You accepted this quote — an order has been created.', 'storelly-product-builder-for-woocommerce' ) . '</div>';
                if ( $net > 0 ) {
                    $due = (string) get_post_meta( $quote_id, '_spbwc_quote_due_date', true );
                    echo '<div class="spbwc-rfq-banner spbwc-rfq-banner--info">' . sprintf(
                        /* translators: 1: net days, 2: due date */
                        esc_html__( 'On account — Net-%1$d. We will invoice you; payment is due %2$s.', 'storelly-product-builder-for-woocommerce' ),
                        (int) $net,
                        esc_html( $due ? date_i18n( get_option( 'date_format' ), strtotime( $due ) ) : '' )
                    ) . '</div>';
                } elseif ( 'deposit_50' === $pay_terms ) {
                    $balance = (float) get_post_meta( $quote_id, '_spbwc_quote_balance', true );
                    echo '<div class="spbwc-rfq-banner spbwc-rfq-banner--info">' . sprintf(
                        /* translators: %s: balance amount */
                        esc_html__( 'A 50%% deposit is due now; the balance of %s is due on shipment.', 'storelly-product-builder-for-woocommerce' ),
                        wp_kses_post( wc_price( $balance, $cur ) )
                    ) . '</div>';
                }
                if ( $order && $order->needs_payment() ) {
                    $pay_label = ( 'deposit_50' === $pay_terms ) ? __( 'Pay deposit', 'storelly-product-builder-for-woocommerce' ) : __( 'Pay now', 'storelly-product-builder-for-woocommerce' );
                    echo '<p><a class="spbwc-rfq-paybtn" href="' . esc_url( $order->get_checkout_payment_url() ) . '">' . esc_html( $pay_label ) . '</a></p>';
                } elseif ( $order ) {
                    echo '<p><a class="spbwc-rfq-btn spbwc-rfq-btn--ghost" href="' . esc_url( $order->get_view_order_url() ) . '">' . esc_html__( 'View order', 'storelly-product-builder-for-woocommerce' ) . '</a></p>';
                }
            } elseif ( SPBWC_Quote::STATUS_DECLINED === $status ) {
                echo '<div class="spbwc-rfq-banner spbwc-rfq-banner--danger">' . esc_html__( 'You declined this quote.', 'storelly-product-builder-for-woocommerce' ) . '</div>';
            } else {
                echo '<div class="spbwc-rfq-banner">' . esc_html( $this->buyer_status_message( $status ) ) . '</div>';
            }
            echo '</div>';
        }

        protected function buyer_status_message( $status ) {
            switch ( $status ) {
                case SPBWC_Quote::STATUS_NEW:
                case SPBWC_Quote::STATUS_REVIEW:
                    return __( 'We have received your request and are preparing a price.', 'storelly-product-builder-for-woocommerce' );
                case SPBWC_Quote::STATUS_ACCEPTED:
                    return __( 'You accepted this quote — your order is being prepared.', 'storelly-product-builder-for-woocommerce' );
                case SPBWC_Quote::STATUS_EXPIRED:
                    return __( 'This quote has expired. Please request a new one if you are still interested.', 'storelly-product-builder-for-woocommerce' );
                case SPBWC_Quote::STATUS_WITHDRAWN:
                    return __( 'This quote was withdrawn.', 'storelly-product-builder-for-woocommerce' );
                default:
                    return __( 'This quote is no longer active.', 'storelly-product-builder-for-woocommerce' );
            }
        }

        /**
         * Render a "what changed" card comparing the two most recent quote
         * versions (P3.9). No-op when there is only one version.
         *
         * @param int   $quote_id Quote post ID.
         * @param array $cur      Currency args for wc_price.
         */
        protected function render_revision_diff( $quote_id, $cur ) {
            $versions = SPBWC_Quote::get_versions( $quote_id );
            if ( count( $versions ) < 2 ) {
                return;
            }
            $diff = SPBWC_Quote::diff_versions( $versions[ count( $versions ) - 2 ], $versions[ count( $versions ) - 1 ] );
            if ( empty( $diff['added'] ) && empty( $diff['removed'] ) && empty( $diff['changed'] ) && $diff['total_old'] === $diff['total_new'] ) {
                return;
            }
            echo '<div class="spbwc-rfq-card spbwc-rfq-diff">';
            echo '<h3>' . esc_html__( 'What changed since the last quote', 'storelly-product-builder-for-woocommerce' ) . '</h3>';
            echo '<ul class="spbwc-rfq-diff__list">';
            foreach ( $diff['added'] as $l ) {
                echo '<li class="spbwc-rfq-diff__add"><span class="spbwc-rfq-pill spbwc-rfq-pill--ok">' . esc_html__( 'Added', 'storelly-product-builder-for-woocommerce' ) . '</span> ' . esc_html( isset( $l['label'] ) ? $l['label'] : '' ) . '</li>';
            }
            foreach ( $diff['removed'] as $l ) {
                echo '<li class="spbwc-rfq-diff__rm"><span class="spbwc-rfq-pill spbwc-rfq-pill--danger">' . esc_html__( 'Removed', 'storelly-product-builder-for-woocommerce' ) . '</span> ' . esc_html( isset( $l['label'] ) ? $l['label'] : '' ) . '</li>';
            }
            foreach ( $diff['changed'] as $c ) {
                $o = $c['old'];
                $n = $c['new'];
                $detail = sprintf(
                    /* translators: 1: label, 2: old qty, 3: new qty, 4: old unit price, 5: new unit price */
                    esc_html__( '%1$s: qty %2$s → %3$s, unit %4$s → %5$s', 'storelly-product-builder-for-woocommerce' ),
                    esc_html( $c['label'] ),
                    esc_html( (string) ( 0 + ( isset( $o['qty'] ) ? $o['qty'] : 0 ) ) ),
                    esc_html( (string) ( 0 + ( isset( $n['qty'] ) ? $n['qty'] : 0 ) ) ),
                    wp_kses_post( wc_price( isset( $o['unit_price'] ) ? (float) $o['unit_price'] : 0, $cur ) ),
                    wp_kses_post( wc_price( isset( $n['unit_price'] ) ? (float) $n['unit_price'] : 0, $cur ) )
                );
                echo '<li class="spbwc-rfq-diff__chg"><span class="spbwc-rfq-pill spbwc-rfq-pill--warn">' . esc_html__( 'Changed', 'storelly-product-builder-for-woocommerce' ) . '</span> ' . $detail . '</li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- parts escaped above.
            }
            echo '</ul>';
            if ( $diff['total_old'] !== $diff['total_new'] ) {
                echo '<p class="spbwc-rfq-diff__total">' . esc_html__( 'Total:', 'storelly-product-builder-for-woocommerce' ) . ' <del>' . wp_kses_post( wc_price( $diff['total_old'], $cur ) ) . '</del> → <strong>' . wp_kses_post( wc_price( $diff['total_new'], $cur ) ) . '</strong></p>';
            }
            echo '</div>';
        }

        /**
         * Render the Accept / Request-changes / Decline forms.
         *
         * @param int $quote_id Quote post ID.
         */
        protected function render_buyer_actions( $quote_id ) {
            $action_url = wc_get_endpoint_url( 'view-quote', $quote_id, wc_get_page_permalink( 'myaccount' ) );
            $totals     = SPBWC_Quote::get_totals( $quote_id );
            $accept_cur = array( 'currency' => isset( $totals['currency'] ) && $totals['currency'] ? $totals['currency'] : get_woocommerce_currency() );
            $settings   = get_option( 'spbwc_quote_settings', array() );
            $terms_url  = isset( $settings['terms_url'] ) ? $settings['terms_url'] : '';
            $tos_label  = $terms_url
                ? sprintf(
                    /* translators: %s: link to the terms & conditions */
                    __( 'I agree to the quote %s.', 'storelly-product-builder-for-woocommerce' ),
                    '<a href="' . esc_url( $terms_url ) . '" target="_blank" rel="noopener">' . esc_html__( 'terms &amp; conditions', 'storelly-product-builder-for-woocommerce' ) . '</a>'
                )
                : esc_html__( 'I agree to the quote terms.', 'storelly-product-builder-for-woocommerce' );
            $pay_terms  = (string) get_post_meta( $quote_id, SPBWC_Quote::META_PAYMENT_TERMS, true );
            $accept_net = SPBWC_Quote::term_net_days( $pay_terms );
            $decline_reasons = array(
                'price'     => __( 'Price too high', 'storelly-product-builder-for-woocommerce' ),
                'timeline'  => __( "Timeline doesn't work", 'storelly-product-builder-for-woocommerce' ),
                'vendor'    => __( 'Going with another vendor', 'storelly-product-builder-for-woocommerce' ),
                'cancelled' => __( 'Project cancelled', 'storelly-product-builder-for-woocommerce' ),
                'budget'    => __( 'Budget changed', 'storelly-product-builder-for-woocommerce' ),
                'other'     => __( 'Other', 'storelly-product-builder-for-woocommerce' ),
            );
            $change_asks = array(
                'qty'       => __( 'Reduce quantity', 'storelly-product-builder-for-woocommerce' ),
                'material'  => __( 'Different material', 'storelly-product-builder-for-woocommerce' ),
                'dimension' => __( 'Different dimensions', 'storelly-product-builder-for-woocommerce' ),
                'rush'      => __( 'Skip rush production', 'storelly-product-builder-for-woocommerce' ),
                'shipping'  => __( 'Change shipping method', 'storelly-product-builder-for-woocommerce' ),
                'terms'     => __( 'Adjust payment terms', 'storelly-product-builder-for-woocommerce' ),
                'other'     => __( 'Other', 'storelly-product-builder-for-woocommerce' ),
            );
            ?>
            <div class="spbwc-rfq-actions">
                <div class="spbwc-rfq-card">
                    <h3><?php esc_html_e( 'Accept this quote', 'storelly-product-builder-for-woocommerce' ); ?></h3>
                    <form method="post" action="<?php echo esc_url( $action_url ); ?>">
                        <?php wp_nonce_field( 'spbwc_quote_buyer_' . $quote_id, 'spbwc_quote_buyer_nonce' ); ?>
                        <input type="hidden" name="quote_id" value="<?php echo esc_attr( $quote_id ); ?>" />
                        <input type="hidden" name="spbwc_quote_buyer_action" value="accept" />
                        <div class="spbwc-rfq-accept-total">
                            <span><?php echo ( 'deposit_50' === $pay_terms ) ? esc_html__( 'Deposit due now', 'storelly-product-builder-for-woocommerce' ) : esc_html__( 'Total', 'storelly-product-builder-for-woocommerce' ); ?></span>
                            <strong><?php echo wp_kses_post( wc_price( ( 'deposit_50' === $pay_terms ) ? round( ( isset( $totals['total'] ) ? (float) $totals['total'] : 0 ) / 2, 2 ) : ( isset( $totals['total'] ) ? (float) $totals['total'] : 0 ), $accept_cur ) ); ?></strong>
                        </div>
                        <?php if ( $accept_net > 0 ) : ?>
                            <p class="spbwc-rfq-terms-note"><?php
                                /* translators: %d: net days */
                                printf( esc_html__( 'Net-%d — you will be invoiced; no payment is taken now.', 'storelly-product-builder-for-woocommerce' ), (int) $accept_net );
                            ?></p>
                        <?php elseif ( 'deposit_50' === $pay_terms ) : ?>
                            <p class="spbwc-rfq-terms-note"><?php
                                /* translators: %s: full total */
                                printf( esc_html__( '50%% deposit of %s now; balance on shipment.', 'storelly-product-builder-for-woocommerce' ), wp_kses_post( wc_price( isset( $totals['total'] ) ? (float) $totals['total'] : 0, $accept_cur ) ) );
                            ?></p>
                        <?php endif; ?>
                        <label for="spbwc-rfq-po"><?php esc_html_e( 'PO number (optional)', 'storelly-product-builder-for-woocommerce' ); ?></label>
                        <input type="text" id="spbwc-rfq-po" name="po_number" value="" />
                        <label class="spbwc-rfq-check">
                            <input type="checkbox" name="tos_accepted" value="1" required />
                            <span><?php echo wp_kses_post( $tos_label ); ?></span>
                        </label>
                        <label class="spbwc-rfq-check">
                            <input type="checkbox" name="updates_optin" value="1" />
                            <span><?php esc_html_e( 'Email me production / order updates.', 'storelly-product-builder-for-woocommerce' ); ?></span>
                        </label>
                        <p style="margin-top:10px;"><button type="submit" class="spbwc-rfq-btn"><?php esc_html_e( 'Accept &amp; create order', 'storelly-product-builder-for-woocommerce' ); ?></button></p>
                    </form>
                </div>

                <div class="spbwc-rfq-card">
                    <h3><?php esc_html_e( 'Request changes', 'storelly-product-builder-for-woocommerce' ); ?></h3>
                    <form method="post" action="<?php echo esc_url( $action_url ); ?>">
                        <?php wp_nonce_field( 'spbwc_quote_buyer_' . $quote_id, 'spbwc_quote_buyer_nonce' ); ?>
                        <input type="hidden" name="quote_id" value="<?php echo esc_attr( $quote_id ); ?>" />
                        <input type="hidden" name="spbwc_quote_buyer_action" value="request_changes" />
                        <div class="spbwc-rfq-checks">
                            <?php foreach ( $change_asks as $key => $label ) : ?>
                                <label><input type="checkbox" name="asks[]" value="<?php echo esc_attr( $key ); ?>" /> <?php echo esc_html( $label ); ?></label>
                            <?php endforeach; ?>
                        </div>
                        <label for="spbwc-rfq-asks"><?php esc_html_e( 'Details', 'storelly-product-builder-for-woocommerce' ); ?></label>
                        <textarea id="spbwc-rfq-asks" name="details" rows="2"></textarea>
                        <p style="margin-top:10px;"><button type="submit" class="spbwc-rfq-btn spbwc-rfq-btn--ghost"><?php esc_html_e( 'Send change request', 'storelly-product-builder-for-woocommerce' ); ?></button></p>
                    </form>
                </div>

                <div class="spbwc-rfq-card">
                    <h3><?php esc_html_e( 'Decline', 'storelly-product-builder-for-woocommerce' ); ?></h3>
                    <form method="post" action="<?php echo esc_url( $action_url ); ?>">
                        <?php wp_nonce_field( 'spbwc_quote_buyer_' . $quote_id, 'spbwc_quote_buyer_nonce' ); ?>
                        <input type="hidden" name="quote_id" value="<?php echo esc_attr( $quote_id ); ?>" />
                        <input type="hidden" name="spbwc_quote_buyer_action" value="decline" />
                        <label for="spbwc-rfq-reason"><?php esc_html_e( 'Reason', 'storelly-product-builder-for-woocommerce' ); ?></label>
                        <select id="spbwc-rfq-reason" name="reason">
                            <?php foreach ( $decline_reasons as $key => $label ) : ?>
                                <option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p style="margin-top:10px;"><button type="submit" class="spbwc-rfq-btn spbwc-rfq-btn--danger"><?php esc_html_e( 'Decline quote', 'storelly-product-builder-for-woocommerce' ); ?></button></p>
                    </form>
                </div>
            </div>
            <?php
        }

        protected function render_view_notice() {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only notice flag after redirect.
            $msg = isset( $_GET['msg'] ) ? sanitize_key( wp_unslash( $_GET['msg'] ) ) : '';
            $map = array(
                'accepted' => array( 'ok', __( 'Quote accepted. Your order is ready below.', 'storelly-product-builder-for-woocommerce' ) ),
                'declined' => array( 'danger', __( 'You have declined this quote.', 'storelly-product-builder-for-woocommerce' ) ),
                'changes'  => array( 'info', __( 'Your change request has been sent.', 'storelly-product-builder-for-woocommerce' ) ),
                'tos'      => array( 'danger', __( 'Please agree to the quote terms before accepting.', 'storelly-product-builder-for-woocommerce' ) ),
            );
            if ( isset( $map[ $msg ] ) ) {
                echo '<div class="spbwc-rfq-banner spbwc-rfq-banner--' . esc_attr( $map[ $msg ][0] ) . '">' . esc_html( $map[ $msg ][1] ) . '</div>';
            }
        }

        /**
         * Process buyer Accept / Decline / Request-changes (POST from the
         * My Account quote detail). Operates on the spbwc_quote CPT.
         */
        public function handle_quote_action() {
            if ( ! is_user_logged_in() || ! isset( $_POST['spbwc_quote_buyer_action'] ) ) {
                return;
            }
            $quote_id = isset( $_POST['quote_id'] ) ? absint( wp_unslash( $_POST['quote_id'] ) ) : 0;
            if ( ! $quote_id || ! isset( $_POST['spbwc_quote_buyer_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['spbwc_quote_buyer_nonce'] ) ), 'spbwc_quote_buyer_' . $quote_id ) ) {
                return;
            }
            $post = get_post( $quote_id );
            if ( ! $post || SPBWC_Quote::POST_TYPE !== $post->post_type || (int) $post->post_author !== get_current_user_id() ) {
                return;
            }
            // Buyer actions are only valid on a sent quote.
            if ( SPBWC_Quote::STATUS_SENT !== $post->post_status ) {
                $this->redirect_view_quote( $quote_id, '' );
            }
            $action = sanitize_key( wp_unslash( $_POST['spbwc_quote_buyer_action'] ) );

            if ( 'accept' === $action ) {
                // Terms acceptance is mandatory before a quote becomes an order.
                if ( empty( $_POST['tos_accepted'] ) ) {
                    $this->redirect_view_quote( $quote_id, 'tos' );
                }
                $po     = isset( $_POST['po_number'] ) ? sanitize_text_field( wp_unslash( $_POST['po_number'] ) ) : '';
                $optin  = ! empty( $_POST['updates_optin'] ) ? 'yes' : 'no';
                update_post_meta( $quote_id, '_spbwc_quote_tos_accepted_at', time() );
                update_post_meta( $quote_id, '_spbwc_quote_updates_optin', $optin );
                SPBWC_Quote::set_status( $quote_id, SPBWC_Quote::STATUS_ACCEPTED, __( 'Customer accepted the quote.', 'storelly-product-builder-for-woocommerce' ) );
                $order_id = $this->spawn_order_from_quote( $quote_id, $po );
                if ( $order_id ) {
                    $order = wc_get_order( $order_id );
                    if ( $order ) {
                        $order->update_meta_data( '_spbwc_order_updates_optin', $optin );
                        $order->save();
                    }
                    SPBWC_Quote::set_status( $quote_id, SPBWC_Quote::STATUS_CONVERTED, sprintf( /* translators: %d: order ID */ __( 'Order #%d created from accepted quote.', 'storelly-product-builder-for-woocommerce' ), $order_id ) );
                }
                do_action( 'spbwc_quote_accepted_notification', $quote_id );
                if ( $order_id ) {
                    do_action( 'spbwc_quote_converted_notification', $quote_id );
                }
                $this->redirect_view_quote( $quote_id, 'accepted' );
            } elseif ( 'decline' === $action ) {
                $reason = isset( $_POST['reason'] ) ? sanitize_key( wp_unslash( $_POST['reason'] ) ) : 'other';
                update_post_meta( $quote_id, SPBWC_Quote::META_DECLINE_REASON, $reason );
                SPBWC_Quote::set_status( $quote_id, SPBWC_Quote::STATUS_DECLINED, __( 'Customer declined the quote.', 'storelly-product-builder-for-woocommerce' ) );
                do_action( 'spbwc_quote_declined_notification', $quote_id );
                $this->redirect_view_quote( $quote_id, 'declined' );
            } elseif ( 'request_changes' === $action ) {
                $asks    = isset( $_POST['asks'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['asks'] ) ) : array();
                $details = isset( $_POST['details'] ) ? sanitize_textarea_field( wp_unslash( $_POST['details'] ) ) : '';
                update_post_meta( $quote_id, '_spbwc_quote_change_request', array( 'asks' => $asks, 'details' => $details ) );
                SPBWC_Quote::set_status( $quote_id, SPBWC_Quote::STATUS_NEGOTIATING, __( 'Customer requested changes.', 'storelly-product-builder-for-woocommerce' ) );
                $this->redirect_view_quote( $quote_id, 'changes' );
            }
        }

        protected function redirect_view_quote( $quote_id, $msg ) {
            $url = wc_get_endpoint_url( 'view-quote', $quote_id, wc_get_page_permalink( 'myaccount' ) );
            if ( $msg ) {
                $url = add_query_arg( 'msg', $msg, $url );
            }
            wp_safe_redirect( $url );
            exit;
        }

        /**
         * Create a payable WooCommerce order from an accepted quote's line items.
         * Each quote line is added as a tax-exempt fee so the buyer pays exactly
         * the quoted total. The order is linked back to the quote both ways.
         *
         * @param int    $quote_id Quote post ID.
         * @param string $po       Optional PO number.
         * @return int|false Order ID on success.
         */
        protected function spawn_order_from_quote( $quote_id, $po = '' ) {
            $lines  = SPBWC_Quote::get_lines( $quote_id );
            $totals = SPBWC_Quote::get_totals( $quote_id );
            $terms  = (string) get_post_meta( $quote_id, SPBWC_Quote::META_PAYMENT_TERMS, true );
            $terms  = $terms ? $terms : 'prepay';
            $grand  = isset( $totals['total'] ) ? (float) $totals['total'] : 0;
            $cur    = array( 'currency' => isset( $totals['currency'] ) && $totals['currency'] ? $totals['currency'] : get_woocommerce_currency() );
            $order  = wc_create_order( array( 'customer_id' => (int) get_post_field( 'post_author', $quote_id ) ) );
            if ( is_wp_error( $order ) || ! $order ) {
                return false;
            }
            $plain = function ( $amount ) use ( $cur ) {
                return html_entity_decode( wp_strip_all_tags( wc_price( $amount, $cur ) ) );
            };

            if ( 'deposit_50' === $terms ) {
                // Charge a 50% deposit now; the balance is tracked for a later invoice.
                $deposit = round( $grand / 2, 2 );
                $balance = round( $grand - $deposit, 2 );
                $fee     = new WC_Order_Item_Fee();
                /* translators: %s: full quote total */
                $fee->set_name( sprintf( __( 'Deposit (50%% of %s)', 'storelly-product-builder-for-woocommerce' ), $plain( $grand ) ) );
                $fee->set_amount( $deposit );
                $fee->set_total( $deposit );
                $fee->set_tax_status( 'none' );
                $order->add_item( $fee );
                $order->update_meta_data( '_spbwc_quote_deposit', $deposit );
                $order->update_meta_data( '_spbwc_quote_balance', $balance );
                update_post_meta( $quote_id, '_spbwc_quote_balance', $balance );
                /* translators: 1: deposit amount, 2: balance amount */
                $order->add_order_note( sprintf( __( '50%% deposit %1$s now; balance %2$s due on shipment.', 'storelly-product-builder-for-woocommerce' ), $plain( $deposit ), $plain( $balance ) ) );
            } else {
                // Full order: one fee per quote line, plus discount/tax.
                foreach ( $lines as $line ) {
                    $amount = isset( $line['line_total'] ) ? (float) $line['line_total'] : 0;
                    $label  = isset( $line['label'] ) ? $line['label'] : '';
                    $qty    = isset( $line['qty'] ) ? (float) $line['qty'] : 0;
                    if ( '' === $label && 0.0 === $amount ) {
                        continue;
                    }
                    $name = ( $qty > 1 ) ? sprintf( '%s × %s', $label, 0 + $qty ) : $label;
                    $fee  = new WC_Order_Item_Fee();
                    $fee->set_name( $name ? $name : __( 'Quote item', 'storelly-product-builder-for-woocommerce' ) );
                    $fee->set_amount( $amount );
                    $fee->set_total( $amount );
                    $fee->set_tax_status( 'none' );
                    $order->add_item( $fee );
                }
                if ( ! empty( $totals['discount'] ) ) {
                    $fee = new WC_Order_Item_Fee();
                    $fee->set_name( __( 'Discount', 'storelly-product-builder-for-woocommerce' ) );
                    $fee->set_amount( -1 * (float) $totals['discount'] );
                    $fee->set_total( -1 * (float) $totals['discount'] );
                    $fee->set_tax_status( 'none' );
                    $order->add_item( $fee );
                }
                if ( ! empty( $totals['tax'] ) ) {
                    $fee = new WC_Order_Item_Fee();
                    $fee->set_name( __( 'Tax', 'storelly-product-builder-for-woocommerce' ) );
                    $fee->set_amount( (float) $totals['tax'] );
                    $fee->set_total( (float) $totals['tax'] );
                    $fee->set_tax_status( 'none' );
                    $order->add_item( $fee );
                }
            }

            if ( '' !== $po ) {
                $order->update_meta_data( '_spbwc_quote_po_number', $po );
                update_post_meta( $quote_id, SPBWC_Quote::META_PO_NUMBER, $po );
            }
            $order->update_meta_data( '_spbwc_source_quote', $quote_id );
            $order->update_meta_data( '_spbwc_quote_payment_terms', $terms );

            // Net terms → an unpaid invoice held until the due date.
            $net = SPBWC_Quote::term_net_days( $terms );
            if ( $net > 0 ) {
                $due = gmdate( 'Y-m-d', strtotime( '+' . $net . ' days', current_time( 'timestamp' ) ) );
                $order->update_meta_data( '_spbwc_quote_due_date', $due );
                update_post_meta( $quote_id, '_spbwc_quote_due_date', $due );
                $order->set_status( 'on-hold' );
                /* translators: 1: net days, 2: due date */
                $order->add_order_note( sprintf( __( 'Net-%1$d invoice — payment due %2$s.', 'storelly-product-builder-for-woocommerce' ), $net, $due ) );
            } else {
                $order->set_status( 'pending' );
            }
            $order->calculate_totals( false );
            $order->save();
            update_post_meta( $quote_id, SPBWC_Quote::META_ORDER_ID, $order->get_id() );
            return $order->get_id();
        }

        /**
         * Lightweight admin notification on a buyer quote decision.
         * Replaced by WC_Email subclasses in M6.
         *
         * @param int    $quote_id Quote post ID.
         * @param string $event    accepted|declined|changes.
         */
    }
}

$spbwc_request_quote = SPBWC_Request_Quote::instance();
$spbwc_request_quote->init();

