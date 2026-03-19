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
            add_action( 'woocommerce_account_quotes_endpoint', array( $this, 'render_quotes_endpoint' ) );
            add_action( 'woocommerce_account_view-quote_endpoint', array( $this, 'render_view_quote_endpoint' ) );
            add_action( 'wp_loaded', array( $this, 'handle_quote_action' ) );

            add_action( 'woocommerce_after_add_to_cart_button', array( $this, 'render_quote_button' ), 25 );
            add_action( 'wp_footer', array( $this, 'render_quote_popup' ) );

            add_action( 'wp_ajax_spbwc_submit_quote', array( $this, 'ajax_submit_quote' ) );
            add_action( 'wp_ajax_nopriv_spbwc_submit_quote', array( $this, 'ajax_submit_quote' ) );
        }

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

        public function render_quote_button() {
            if ( ! is_product() ) {
                return;
            }
            global $product; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- WooCommerce global variable.
            if ( ! $product || ! $this->is_product_quote_enabled( $product->get_id() ) ) {
                return;
            }
            echo '<p><button type="button" class="button alt" id="spbwc-open-quote-popup">' . esc_html__( 'Get Quote', 'storelly-product-builder-for-woocommerce' ) . '</button></p>';
        }

        public function render_quote_popup() {
            if ( ! is_product() ) {
                return;
            }
            global $product; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- WooCommerce global variable.
            if ( ! $product || ! $this->is_product_quote_enabled( $product->get_id() ) ) {
                return;
            }
            $fields = $this->get_form_fields();
            ?>
            <div id="spbwc-quote-modal" style="display:none;position:fixed;z-index:99999;inset:0;background:rgba(0,0,0,.6);">
                <div style="max-width:640px;background:#fff;margin:6vh auto;padding:18px;border-radius:6px;max-height:88vh;overflow:auto;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
                        <h3 style="margin:0;"><?php esc_html_e( 'Request a Quote', 'storelly-product-builder-for-woocommerce' ); ?></h3>
                        <button type="button" class="button-link" id="spbwc-close-quote-popup">x</button>
                    </div>
                    <div id="spbwc-quote-message" style="margin-bottom:10px;"></div>
                    <form id="spbwc-quote-form">
                        <input type="hidden" name="action" value="spbwc_submit_quote" />
                        <input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'spbwc_submit_quote_action' ) ); ?>" />
                        <input type="hidden" name="product_id" value="<?php echo esc_attr( $product->get_id() ); ?>" />
                        <p>
                            <label><?php esc_html_e( 'Quantity', 'storelly-product-builder-for-woocommerce' ); ?></label>
                            <input type="number" min="1" step="1" name="quantity" value="1" style="width:120px;" />
                        </p>
                        <?php foreach ( $fields as $field ) : ?>
                            <?php if ( ! isset( $field['enabled'] ) || '1' !== (string) $field['enabled'] ) { continue; } ?>
                            <?php
                            $name = isset( $field['name'] ) ? sanitize_key( $field['name'] ) : '';
                            if ( '' === $name ) {
                                continue;
                            }
                            $type = isset( $field['type'] ) ? sanitize_key( $field['type'] ) : 'text';
                            $required = isset( $field['required'] ) && '1' === (string) $field['required'];
                            ?>
                            <p>
                                <label for="spbwc_quote_<?php echo esc_attr( $name ); ?>">
                                    <?php echo esc_html( isset( $field['label'] ) ? $field['label'] : ucfirst( $name ) ); ?>
                                    <?php if ( $required ) : ?><span style="color:#d63638;">*</span><?php endif; ?>
                                </label>
                                <?php if ( 'textarea' === $type ) : ?>
                                    <textarea id="spbwc_quote_<?php echo esc_attr( $name ); ?>" name="quote_fields[<?php echo esc_attr( $name ); ?>]" <?php echo $required ? 'required' : ''; ?> placeholder="<?php echo esc_attr( isset( $field['placeholder'] ) ? $field['placeholder'] : '' ); ?>" style="width:100%;"></textarea>
                                <?php else : ?>
                                    <input id="spbwc_quote_<?php echo esc_attr( $name ); ?>" type="<?php echo esc_attr( in_array( $type, array( 'text', 'email', 'tel', 'number' ), true ) ? $type : 'text' ); ?>" name="quote_fields[<?php echo esc_attr( $name ); ?>]" <?php echo $required ? 'required' : ''; ?> placeholder="<?php echo esc_attr( isset( $field['placeholder'] ) ? $field['placeholder'] : '' ); ?>" style="width:100%;" />
                                <?php endif; ?>
                            </p>
                        <?php endforeach; ?>
                        <p><button type="submit" class="button button-primary"><?php esc_html_e( 'Submit Quote', 'storelly-product-builder-for-woocommerce' ); ?></button></p>
                    </form>
                </div>
            </div>
            <script>
                (function($){
                    var modal = $('#spbwc-quote-modal');
                    $('#spbwc-open-quote-popup').on('click', function(){ modal.show(); });
                    $('#spbwc-close-quote-popup').on('click', function(){ modal.hide(); });
                    modal.on('click', function(e){ if(e.target === this){ modal.hide(); } });
                    $('#spbwc-quote-form').on('submit', function(e){
                        e.preventDefault();
                        var form = $(this);
                        var msg = $('#spbwc-quote-message');
                        msg.html('<?php echo esc_js( __( 'Sending...', 'storelly-product-builder-for-woocommerce' ) ); ?>');
                        $.post('<?php echo esc_url_raw( admin_url( 'admin-ajax.php' ) ); ?>', form.serialize())
                            .done(function(res){
                                if(res && res.success){
                                    msg.html('<span style="color:green;">'+ (res.data && res.data.message ? res.data.message : 'Success') +'</span>');
                                    if(res.data && res.data.redirect){ window.location.href = res.data.redirect; return; }
                                    form[0].reset();
                                }else{
                                    msg.html('<span style="color:#d63638;">'+ (res && res.data && res.data.message ? res.data.message : 'Failed') +'</span>');
                                }
                            })
                            .fail(function(){
                                msg.html('<span style="color:#d63638;"><?php echo esc_js( __( 'Request failed.', 'storelly-product-builder-for-woocommerce' ) ); ?></span>');
                            });
                    });
                })(jQuery);
            </script>
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

            $raw_fields = isset( $_POST['quote_fields'] ) ? (array) wp_unslash( $_POST['quote_fields'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above.
            $fields     = array();
            foreach ( $raw_fields as $k => $v ) {
                $fields[ sanitize_key( $k ) ] = sanitize_text_field( $v );
            }
            $schema = $this->get_form_fields();
            $errors = array();
            foreach ( $schema as $f ) {
                if ( ! isset( $f['enabled'] ) || '1' !== (string) $f['enabled'] ) {
                    continue;
                }
                $name = isset( $f['name'] ) ? sanitize_key( $f['name'] ) : '';
                if ( '' === $name ) {
                    continue;
                }
                $value = isset( $fields[ $name ] ) ? $fields[ $name ] : '';
                $required = isset( $f['required'] ) && '1' === (string) $f['required'];
                if ( $required && '' === $value ) {
                    $errors[] = sprintf( esc_html__( '%s is required.', 'storelly-product-builder-for-woocommerce' ), isset( $f['label'] ) ? $f['label'] : $name );
                }
                $validation = isset( $f['validation'] ) ? sanitize_key( $f['validation'] ) : '';
                if ( 'email' === $validation && '' !== $value && ! is_email( $value ) ) {
                    $errors[] = sprintf( esc_html__( '%s is not a valid email.', 'storelly-product-builder-for-woocommerce' ), isset( $f['label'] ) ? $f['label'] : $name );
                }
                if ( 'phone' === $validation && '' !== $value && ! preg_match( '/^[0-9\-\+\(\)\s]{6,20}$/', $value ) ) {
                    $errors[] = sprintf( esc_html__( '%s is not a valid phone.', 'storelly-product-builder-for-woocommerce' ), isset( $f['label'] ) ? $f['label'] : $name );
                }
            }
            if ( ! empty( $errors ) ) {
                wp_send_json_error( array( 'message' => implode( ' ', $errors ) ) );
            }

            $customer_id = get_current_user_id();
            $order = wc_create_order(
                array(
                    'status'      => 'spbwc-quote-new',
                    'customer_id' => $customer_id,
                )
            );
            if ( is_wp_error( $order ) || ! $order ) {
                wp_send_json_error( array( 'message' => esc_html__( 'Could not create quote order.', 'storelly-product-builder-for-woocommerce' ) ) );
            }
            $order->add_product( $product, $quantity );
            $customer_name = trim(
                ( isset( $fields['first_name'] ) ? $fields['first_name'] : '' ) . ' ' .
                ( isset( $fields['last_name'] ) ? $fields['last_name'] : '' )
            );
            $customer_email = isset( $fields['email'] ) ? sanitize_email( $fields['email'] ) : '';
            if ( '' === $customer_email && is_user_logged_in() ) {
                $customer = wp_get_current_user();
                $customer_email = $customer instanceof WP_User ? $customer->user_email : '';
            }
            $message = isset( $fields['message'] ) ? sanitize_textarea_field( $fields['message'] ) : '';
            $order->update_meta_data( '_spbwc_quote_request', $message );
            $order->update_meta_data( '_spbwc_quote_fields', $fields );
            $order->update_meta_data( '_spbwc_quote_product_id', $product_id );
            $order->update_meta_data( '_spbwc_quote_quantity', $quantity );
            $order->update_meta_data( '_raq_request', $fields );
            $order->update_meta_data( '_raq_customer_name', $customer_name );
            $order->update_meta_data( '_raq_customer_email', $customer_email );
            $order->update_meta_data( '_raq_customer_message', $message );
            $order->calculate_totals();
            $order->save();

            $this->send_quote_notification_email( $order, 'new' );
            if ( $customer_email ) {
                $this->send_quote_customer_email( $order, 'new', $customer_email );
            }

            $settings = get_option( 'spbwc_quote_settings', array() );
            $success_message = isset( $settings['success_message'] ) ? $settings['success_message'] : __( 'Your quote request has been sent successfully.', 'storelly-product-builder-for-woocommerce' );
            $redirect = '';
            if ( is_user_logged_in() ) {
                $redirect = wc_get_endpoint_url( 'view-quote', $order->get_id(), wc_get_page_permalink( 'myaccount' ) );
            }
            wp_send_json_success(
                array(
                    'message'  => $success_message,
                    'order_id' => $order->get_id(),
                    'redirect' => $redirect,
                )
            );
        }

        protected function send_quote_notification_email( $order, $event = 'new' ) {
            $settings = get_option( 'spbwc_quote_settings', array() );
            $admin_email = isset( $settings['admin_email'] ) ? sanitize_email( $settings['admin_email'] ) : get_option( 'admin_email' );
            if ( ! is_email( $admin_email ) ) {
                return;
            }
            $subject = sprintf( '[Storelly Quote] #%d - %s', $order->get_id(), strtoupper( $event ) );
            $body = "Quote order #" . $order->get_id() . "\n";
            $body .= "Status: " . wc_get_order_status_name( $order->get_status() ) . "\n";
            $body .= "Customer: " . $order->get_meta( '_raq_customer_name' ) . "\n";
            $body .= "Email: " . $order->get_meta( '_raq_customer_email' ) . "\n";
            $body .= "Message: " . $order->get_meta( '_spbwc_quote_request' ) . "\n";
            $body .= "Admin URL: " . $order->get_edit_order_url() . "\n";
            wp_mail( $admin_email, $subject, $body );
        }

        protected function send_quote_customer_email( $order, $event = 'new', $to = '' ) {
            if ( ! is_email( $to ) ) {
                return;
            }
            $subject = sprintf( '[Storelly Quote] #%d - %s', $order->get_id(), strtoupper( $event ) );
            $body = sprintf( "Hello,\nYour quote request #%d is now %s.\n", $order->get_id(), wc_get_order_status_name( $order->get_status() ) );
            $body .= "You can view details here: " . wc_get_endpoint_url( 'view-quote', $order->get_id(), wc_get_page_permalink( 'myaccount' ) );
            wp_mail( $to, $subject, $body );
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
        }

        public function add_quotes_account_menu( $items ) {
            $logout = isset( $items['customer-logout'] ) ? $items['customer-logout'] : null;
            if ( isset( $items['customer-logout'] ) ) {
                unset( $items['customer-logout'] );
            }
            $items['quotes'] = __( 'Quotes', 'storelly-product-builder-for-woocommerce' );
            if ( null !== $logout ) {
                $items['customer-logout'] = $logout;
            }
            return $items;
        }

        public function render_quotes_endpoint() {
            if ( ! is_user_logged_in() ) {
                wc_get_template( 'myaccount/form-login.php' );
                return;
            }
            $orders = wc_get_orders(
                array(
                    'customer_id' => get_current_user_id(),
                    'limit'       => 50,
                    'orderby'     => 'date',
                    'order'       => 'DESC',
                    'meta_key'    => '_spbwc_quote_request',
                    'meta_compare'=> 'EXISTS',
                )
            );
            echo '<h2>' . esc_html__( 'My Quotes', 'storelly-product-builder-for-woocommerce' ) . '</h2>';
            if ( empty( $orders ) ) {
                echo '<p>' . esc_html__( 'No quotes found.', 'storelly-product-builder-for-woocommerce' ) . '</p>';
                return;
            }
            echo '<table class="shop_table shop_table_responsive my_account_orders"><thead><tr>';
            echo '<th>' . esc_html__( 'Quote', 'storelly-product-builder-for-woocommerce' ) . '</th>';
            echo '<th>' . esc_html__( 'Date', 'storelly-product-builder-for-woocommerce' ) . '</th>';
            echo '<th>' . esc_html__( 'Status', 'storelly-product-builder-for-woocommerce' ) . '</th>';
            echo '<th>' . esc_html__( 'Total', 'storelly-product-builder-for-woocommerce' ) . '</th>';
            echo '<th>' . esc_html__( 'Actions', 'storelly-product-builder-for-woocommerce' ) . '</th>';
            echo '</tr></thead><tbody>';
            foreach ( $orders as $order ) {
                $view_url = wc_get_endpoint_url( 'view-quote', $order->get_id(), wc_get_page_permalink( 'myaccount' ) );
                echo '<tr>';
                echo '<td>#' . esc_html( (string) $order->get_id() ) . '</td>';
                echo '<td>' . esc_html( wc_format_datetime( $order->get_date_created() ) ) . '</td>';
                echo '<td>' . esc_html( wc_get_order_status_name( $order->get_status() ) ) . '</td>';
                echo '<td>' . wp_kses_post( $order->get_formatted_order_total() ) . '</td>';
                echo '<td><a class="button" href="' . esc_url( $view_url ) . '">' . esc_html__( 'View', 'storelly-product-builder-for-woocommerce' ) . '</a></td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }

        public function render_view_quote_endpoint() {
            global $wp;
            if ( ! is_user_logged_in() ) {
                wc_get_template( 'myaccount/form-login.php' );
                return;
            }
            $quote_id = isset( $wp->query_vars['view-quote'] ) ? absint( $wp->query_vars['view-quote'] ) : 0;
            $order = $quote_id ? wc_get_order( $quote_id ) : false;
            if ( ! $order || (int) $order->get_customer_id() !== get_current_user_id() ) {
                echo '<p>' . esc_html__( 'Quote not found.', 'storelly-product-builder-for-woocommerce' ) . '</p>';
                return;
            }
            $accept_url = add_query_arg(
                array(
                    'spbwc_quote_action' => 'accept',
                    'quote_id'           => $quote_id,
                    '_wpnonce'           => wp_create_nonce( 'spbwc_quote_action_' . $quote_id ),
                ),
                wc_get_endpoint_url( 'view-quote', $quote_id, wc_get_page_permalink( 'myaccount' ) )
            );
            $reject_url = add_query_arg(
                array(
                    'spbwc_quote_action' => 'reject',
                    'quote_id'           => $quote_id,
                    '_wpnonce'           => wp_create_nonce( 'spbwc_quote_action_' . $quote_id ),
                ),
                wc_get_endpoint_url( 'view-quote', $quote_id, wc_get_page_permalink( 'myaccount' ) )
            );
            echo '<h2>' . esc_html( sprintf( __( 'Quote #%d', 'storelly-product-builder-for-woocommerce' ), $quote_id ) ) . '</h2>';
            echo '<p><strong>' . esc_html__( 'Status:', 'storelly-product-builder-for-woocommerce' ) . '</strong> ' . esc_html( wc_get_order_status_name( $order->get_status() ) ) . '</p>';
            echo '<p><strong>' . esc_html__( 'Message:', 'storelly-product-builder-for-woocommerce' ) . '</strong> ' . esc_html( (string) $order->get_meta( '_spbwc_quote_request' ) ) . '</p>';
            echo '<p><a class="button" href="' . esc_url( $accept_url ) . '">' . esc_html__( 'Accept Quote', 'storelly-product-builder-for-woocommerce' ) . '</a> ';
            echo '<a class="button" href="' . esc_url( $reject_url ) . '">' . esc_html__( 'Reject Quote', 'storelly-product-builder-for-woocommerce' ) . '</a></p>';
            echo '<h3>' . esc_html__( 'Items', 'storelly-product-builder-for-woocommerce' ) . '</h3>';
            echo '<ul>';
            foreach ( $order->get_items() as $item ) {
                echo '<li>' . esc_html( $item->get_name() . ' x ' . $item->get_quantity() ) . '</li>';
            }
            echo '</ul>';
        }

        public function handle_quote_action() {
            if ( ! is_user_logged_in() ) {
                return;
            }
            $action = isset( $_GET['spbwc_quote_action'] ) ? sanitize_key( wp_unslash( $_GET['spbwc_quote_action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce validated below.
            $quote_id = isset( $_GET['quote_id'] ) ? absint( wp_unslash( $_GET['quote_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce validated below.
            if ( ! in_array( $action, array( 'accept', 'reject' ), true ) || ! $quote_id ) {
                return;
            }
            $nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce validated below.
            if ( ! $nonce || ! wp_verify_nonce( $nonce, 'spbwc_quote_action_' . $quote_id ) ) {
                return;
            }
            $order = wc_get_order( $quote_id );
            if ( ! $order || (int) $order->get_customer_id() !== get_current_user_id() ) {
                return;
            }
            $email = sanitize_email( $order->get_meta( '_raq_customer_email' ) );
            if ( 'accept' === $action ) {
                $order->update_status( 'spbwc-quote-accepted' );
                $order->add_order_note( __( 'Customer accepted the quote.', 'storelly-product-builder-for-woocommerce' ) );
                $this->send_quote_notification_email( $order, 'accepted' );
                if ( $email ) {
                    $this->send_quote_customer_email( $order, 'accepted', $email );
                }
            } else {
                $order->update_status( 'spbwc-quote-rejected' );
                $order->add_order_note( __( 'Customer rejected the quote.', 'storelly-product-builder-for-woocommerce' ) );
                $this->send_quote_notification_email( $order, 'rejected' );
                if ( $email ) {
                    $this->send_quote_customer_email( $order, 'rejected', $email );
                }
            }
            $redirect = wc_get_endpoint_url( 'view-quote', $quote_id, wc_get_page_permalink( 'myaccount' ) );
            wp_safe_redirect( $redirect );
            exit;
        }
    }
}

$spbwc_request_quote = SPBWC_Request_Quote::instance();
$spbwc_request_quote->init();

