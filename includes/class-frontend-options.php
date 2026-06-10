<?php
if (!defined('ABSPATH')) {
    exit;
}
if (!class_exists('STORELLY_FRONTEND_OPTIONS')) {
    class STORELLY_FRONTEND_OPTIONS {
        protected static $instance;
        public $is_edit_mode = FALSE;
        /** Holds the cart key when editing a product in the cart **/
        public $cart_edit_key = NULL;
        public $appid;
        public $new_add_to_cart_key = FALSE;
        /** Edit option in cart helper **/
        public function __construct() {

            if ( isset( $_REQUEST['pcpb_cart_item_key'] ) ) {
                $nonce_value = isset( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ) : '';
                if ( $nonce_value && wp_verify_nonce( $nonce_value, 'nbo-edit' ) ) {
                    $cart_key = sanitize_text_field( wp_unslash( $_REQUEST['pcpb_cart_item_key'] ) );
                    if ( '' !== $cart_key ) {
                        $this->is_edit_mode  = true;
                        $this->cart_edit_key = $cart_key;
                    }
                }
            }
            $this->appid = "nbo-app-" . time() . rand(1, 1000); // phpcs:ignore WordPress.WP.AlternativeFunctions.rand_rand -- Using PHP rand() as wp_rand() may not be defined in non-WP contexts.
        }
        public static function instance() {
            if (is_null(self::$instance)) {
                self::$instance = new self();
            }
            return self::$instance;
        }
        public function init() {
            add_filter('upload_mimes', [__CLASS__, 'storelly_allow_uploads']);

            // Verify nonce for add-to-cart requests that include builder fields.
            add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'spbwc_verify_add_to_cart_nonce' ), 1, 6 );
            
            add_action('woocommerce_before_add_to_cart_button', array($this, 'show_option_fields'));

            // handle customer input as order item meta
            add_filter('woocommerce_get_item_data', array($this, 'get_item_data'), 10, 2);
            // Add item data to the cart
            add_filter('woocommerce_add_cart_item_data', array($this, 'add_cart_item_data'), 10, 4);
            // Alters add to cart text when editing a product
            add_filter('woocommerce_product_single_add_to_cart_text', array($this, 'add_to_cart_text'), 9999, 1);
            // Remove product from cart when editing a product
            add_filter('woocommerce_add_to_cart_validation', array($this, 'remove_previous_product_from_cart'), 99999, 6);
            // Alters the cart item key when editing a product
            add_action('woocommerce_add_to_cart', array($this, 'add_to_cart'), 10, 6);
            // Change quantity value when editing a cart item
            add_filter('woocommerce_quantity_input_args', array($this, 'quantity_input_args'), 9999, 2);
            // Calculate totals on remove from cart/update
            add_action('woocommerce_cart_loaded_from_session', array($this, 're_calculate_price'), 1, 1);
            // Add meta to order
            add_action('woocommerce_checkout_create_order_line_item', array($this, 'order_line_item'), 50, 3);
            // Change option independent quantity prices to cart fee
            add_action('woocommerce_cart_calculate_fees', array($this, 'add_cart_fee'), 1, 1);
            // Gets saved option when using the order again function
            add_filter('woocommerce_order_again_cart_item_data', array($this, 'order_again_cart_item_data'), 50, 3);
            // Alter the product thumbnail in cart
            add_filter('woocommerce_cart_item_thumbnail', array($this, 'cart_item_thumbnail'), 50, 2);
            // Remove item quantity in checkout
            add_filter('woocommerce_checkout_cart_item_quantity', array($this, 'remove_cart_item_quantity'), 10, 3);
            // Adds edit link on product title in cart and item quantity
            add_filter('woocommerce_cart_item_name', array($this, 'cart_item_name'), 50, 3);
            // persist the cart item data, and set the item price (when needed) first, before any other plugins
            add_filter('woocommerce_get_cart_item_from_session', array($this, 'get_cart_item_from_session'), 1, 2);
            // on add to cart set the price when needed, and do it first, before any other plugins
            add_filter('woocommerce_add_cart_item', array($this, 'set_product_prices'), 1, 1);

            add_action('wp_enqueue_scripts', array($this , 'spbwc_frontend_enqueue_scripts'));
            
        }

        /**
         * Verify nonce for add-to-cart submissions that include builder fields.
         *
         * @param bool  $passed       Whether add to cart is allowed.
         * @param int   $product_id   Product ID.
         * @param int   $quantity     Quantity.
         * @param int   $variation_id Variation ID.
         * @param array $variations   Variation data.
         * @param array $cart_item_data Cart item data.
         * @return bool
         */
        public function spbwc_verify_add_to_cart_nonce( $passed, $product_id, $quantity, $variation_id = 0, $variations = array(), $cart_item_data = array() ) {
            $needs_nonce = isset( $_POST['pcpb-field'] ) || isset( $_POST['pcpb-add-to-cart'] ) || ( ! empty( $_FILES ) && isset( $_FILES['pcpb-field'] ) );
            if ( ! $needs_nonce ) {
                return $passed;
            }

            $nonce_value = isset( $_POST['spbwc_add_to_cart_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['spbwc_add_to_cart_nonce'] ) ) : '';
            if ( ! $nonce_value || ! wp_verify_nonce( $nonce_value, 'spbwc_add_to_cart_action' ) ) {
                wc_add_notice( esc_html__( 'Security check failed.', 'storelly-product-builder-for-woocommerce' ), 'error' );
                return false;
            }

            return $passed;
        }
        public static function storelly_allow_uploads($mimes) {
            $mimes['json'] = 'application/json';
            $mimes['svg'] = 'image/svg+xml';
            return $mimes;
        }
        
        public static function get_option($id) {
            return SPBWC_Storelly_PB_Admin_Options::instance()->spbwc_get_option($id);
        }
        public static function get_product_option($product_id) {
            return SPBWC_Storelly_PB_Admin_Options::instance()->spbwc_get_product_option($product_id);
        }
        public static function recursive_stripslashes($fields) {
            $valid_fields = array();
            foreach ($fields as $key => $field) {
                if (is_array($field)) {
                    $valid_fields[$key] = self::recursive_stripslashes($field);
                } else if (!is_null($field)) {
                    $valid_fields[$key] = stripslashes($field);
                }
            }
            return $valid_fields;
        }
        /**
         * Collapse admin-builder "config descriptor" objects to their runtime scalar value.
         *
         * Options applied from a bundled Template Library template are stored as the admin
         * Angular model, where each general/appearance leaf is a descriptor array
         * { title, value, type, ... } instead of a flat value. Both the PHP render loop in
         * templates/single-product/option-builder.php and the buyer core (option-builder.js)
         * expect flat values (e.g. general.enabled === 'y'); a descriptor-shaped option
         * therefore renders no fields at all. Idempotent — already-flat values pass through
         * unchanged. Root fix lives in the template import; this is the buyer-side safety net.
         *
         * @param mixed $value Field subtree (general/appearance) or a leaf.
         * @return mixed
         */
        private static function spbwc_collapse_field_config_objects($value) {
            if (
                is_array($value)
                && array_key_exists('value', $value)
                && array_key_exists('type', $value)
                && array_key_exists('title', $value)
            ) {
                return $value['value'];
            }
            if (is_array($value)) {
                foreach ($value as $key => $sub) {
                    $value[$key] = self::spbwc_collapse_field_config_objects($sub);
                }
            }
            return $value;
        }
        /**
         * Normalize an option "fields" array to runtime shape by collapsing descriptor
         * objects in each field's general/appearance subtree.
         *
         * @param array $fields Unserialized fields array.
         * @return array
         */
        private static function spbwc_flatten_option_fields($fields) {
            if (!is_array($fields)) {
                return $fields;
            }
            foreach ($fields as $idx => $field) {
                if (!is_array($field)) {
                    continue;
                }
                if (isset($field['general']) && is_array($field['general'])) {
                    $fields[$idx]['general'] = self::spbwc_collapse_field_config_objects($field['general']);
                }
                if (isset($field['appearance']) && is_array($field['appearance'])) {
                    $fields[$idx]['appearance'] = self::spbwc_collapse_field_config_objects($field['appearance']);
                }
            }
            return $fields;
        }
        public function spbwc_frontend_enqueue_scripts(){
            // Resolve the product reliably. At wp_enqueue_scripts the global $post
            // is not yet the product on block themes / page builders, so
            // get_the_ID() returns 0 and the buyer app would never enqueue (the
            // markup still prints via woocommerce_before_add_to_cart_button, so
            // only the static Customize button shows). get_queried_object_id() is
            // reliable on a single-product request regardless of theme.
            $product_id = ( function_exists( 'is_product' ) && is_product() ) ? get_queried_object_id() : get_the_ID();
            $option_id = $this->get_product_option($product_id);
             if ($option_id) {
                $_options = $this->get_option($option_id);
                if ($_options) {
                    $options = unserialize($_options['fields']);
                    if (!isset($options['fields'])) {
                        $options['fields'] = array();
                    }
                    $options['fields'] = $this->recursive_stripslashes($options['fields']);
                    // Safety net: collapse descriptor-shaped fields (from Template Library imports) to runtime shape.
                    $options['fields'] = self::spbwc_flatten_option_fields($options['fields']);
                    foreach ($options['fields'] as $key => $field) {
                        if (!isset($field['general']['attributes'])) {
                            $field['general']['attributes'] = array();
                            $field['general']['attributes']['options'] = array();
                            $options['fields'][$key]['general']['attributes'] = array();
                            $options['fields'][$key]['general']['attributes']['options'] = array();
                        }
                        if ($field['appearance']['change_image_product'] == 'y') {
                            foreach ($field['general']['attributes']['options'] as $op_index => $option) {
                                $option['product_image'] = isset($option['product_image']) ? $option['product_image'] : 0;
                                $attachment_id = absint($option['product_image']);
                                if ($attachment_id != 0) {
                                    $image_link         = wp_get_attachment_url($attachment_id);
                                    $attachment_object  = get_post($attachment_id);
                                    $full_src           = wp_get_attachment_image_src($attachment_id, 'large');
                                    $image_title        = get_the_title($attachment_id);
                                    $image_alt          = trim(wp_strip_all_tags(get_post_meta($attachment_id, '_wp_attachment_image_alt', TRUE)));
                                    $image_srcset       = function_exists('wp_get_attachment_image_srcset') ? wp_get_attachment_image_srcset($attachment_id, 'shop_single') : FALSE;
                                    $image_sizes        = function_exists('wp_get_attachment_image_sizes') ? wp_get_attachment_image_sizes($attachment_id, 'shop_single') : FALSE;
                                    $image_caption      = $attachment_object->post_excerpt;
                                    $options['fields'][$key]['general']['attributes']['options'][$op_index] = array_replace_recursive($options['fields'][$key]['general']['attributes']['options'][$op_index], array(
                                        'imagep'        => 'y',
                                        'image_link'    => $image_link,
                                        'image_title'   => $image_title,
                                        'image_alt'     => $image_alt,
                                        'image_srcset'  => $image_srcset,
                                        'image_sizes'   => $image_sizes,
                                        'image_caption' => $image_caption,
                                        'full_src'      => $full_src[0],
                                        'full_src_w'    => $full_src[1],
                                        'full_src_h'    => $full_src[2]
                                    ));
                                } else {
                                    $options['fields'][$key]['general']['attributes']['options'][$op_index]['imagep'] = 'n';
                                }
                            }
                        }
                        if (isset($field['nbpb_type']) && $field['nbpb_type'] == 'nbpb_com') {
                            if (isset($field['general']['pb_config'])) {
                                foreach ($field['general']['pb_config'] as $a_index => $attr) {
                                    foreach ($attr as $s_index => $sattr) {
                                        foreach ($sattr['views'] as $v_index => $view) {
                                            $pb_image_obj = wp_get_attachment_url(absint($view['image']));
                                            $options['fields'][$key]['general']['pb_config'][$a_index][$s_index]['views'][$v_index]['image_url'] =  $pb_image_obj ? $pb_image_obj : SPBWC_PB_ASSETS_URL . 'images/placeholder.png';
                                        }
                                    }
                                }
                            } else {
                                $field['general']['pb_config'] = array();
                            }
                            foreach ($field['general']['attributes']['options'] as $op_index => $option) {
                                if( isset($option['enable_subattr']) && $option['enable_subattr'] == 'on' && isset($option['sub_attributes']) && count($option['sub_attributes']) > 0 ){
                                    foreach( $option['sub_attributes'] as $sa_index => $sattr ){
                                        $options['fields'][$key]['general']['attributes']['options'][$op_index]['sub_attributes'][$sa_index]['image_url'] = SPBWC_Storelly_PB_Util::spbwc_get_image_thumbnail( $sattr['image'] );
                                    }
                                }else{
                                    $options['fields'][$key]['general']['attributes']['options'][$op_index]['image_url'] = SPBWC_Storelly_PB_Util::spbwc_get_image_thumbnail( $option['image'] );
                                }
                            };
                            $options['fields'][$key]['general']['component_icon_url'] = SPBWC_Storelly_PB_Util::spbwc_get_image_thumbnail($field['general']['component_icon']);
                        }
                        if (isset($field['general']['attributes']['bg_type']) && $field['general']['attributes']['bg_type'] == 'i') {
                            foreach ($field['general']['attributes']['options'] as $op_index => $option) {
                                if (!isset($option['bg_image']) || !is_array($option['bg_image'])) {
                                    continue;
                                }
                                foreach ($option['bg_image'] as $bg_index => $bg) {
                                    $bg_obj = wp_get_attachment_url(absint($bg));
                                    $options['fields'][$key]['general']['attributes']['options'][$op_index]['bg_image_url'][$bg_index] = $bg_obj ? $bg_obj : SPBWC_PB_ASSETS_URL . 'images/placeholder.png';
                                }
                            };
                        }
                    }
                    if (isset($options['views'])) {
                        foreach ($options['views'] as $vkey => $view) {
                            $view['base'] = isset($view['base']) ? $view['base'] : 0;
                            $options['views'][$vkey]['base'] = $view['base'];
                            $view_bg_obj = wp_get_attachment_url(absint($view['base']));
                            $options['views'][$vkey]['base_url'] = $view_bg_obj ? $view_bg_obj : SPBWC_PB_ASSETS_URL . 'images/placeholder.png';
                        }
                    }
                    $product        = wc_get_product($product_id);
                    $type           = $product->get_type();
                    $variations     = array();
                    $dimensions     = array();
                    $form_values    = array();
                    $cart_item_key  = '';
                    $quantity       = 1;
                    $nbau           = '';
                    $nbdpb_enable   = get_post_meta($product_id, '_storelly_pb_enable', true);

                    $add_to_cart_nonce = isset( $_POST['spbwc_add_to_cart_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['spbwc_add_to_cart_nonce'] ) ) : '';
                    $add_to_cart_valid = ( $add_to_cart_nonce && wp_verify_nonce( $add_to_cart_nonce, 'spbwc_add_to_cart_action' ) );

                    $pcpb_field_sanitized = isset( $_POST['pcpb-field'] ) ? map_deep( wp_unslash( $_POST['pcpb-field'] ), 'sanitize_text_field' ) : array();

                    if ( ! empty( $pcpb_field_sanitized ) && $add_to_cart_valid ) {
                        $form_values = $pcpb_field_sanitized;
                    } elseif ( isset( $_GET['pcpb_cart_item_key'], $_GET['_wpnonce'] ) ) {
                        $edit_nonce = sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) );
                        if ( ! wp_verify_nonce( $edit_nonce, 'nbo-edit' ) ) {
                            $edit_nonce = '';
                        }
                        if ( $edit_nonce ) {
                            $cart_item_key = sanitize_text_field( wp_unslash( $_GET['pcpb_cart_item_key'] ) );
                        }
                        $cart_item = WC()->cart->get_cart_item( $cart_item_key );
                        if ( isset( $cart_item['pcpb_meta'] ) ) {
                            $form_values = $cart_item['pcpb_meta']['field'];
                        }
                    }

                    if ( isset( $_GET['nbo_values'], $_GET['_wpnonce'] ) ) {
                        $order_again_nonce = sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) );
                        $order_again_valid = (
                            wp_verify_nonce( $order_again_nonce, 'woocommerce-order_again' ) ||
                            wp_verify_nonce( $order_again_nonce, 'woocommerce-order-again' ) ||
                            wp_verify_nonce( $order_again_nonce, 'order_again' ) ||
                            wp_verify_nonce( $order_again_nonce, 'order-again' )
                        );
                        if ( ! $order_again_valid ) {
                            $order_again_nonce = '';
                        }
                    }

                    if ( isset( $_GET['nbo_values'] ) && ! empty( $order_again_nonce ) ) {
                        $params        = array();
                        $value_str_raw = wp_unslash( $_GET['nbo_values'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Base64 value is sanitized after decoding and parsing.
                        $value_str     = base64_decode( $value_str_raw );
                        parse_str( $value_str, $params );
                        if ( isset( $params['pcpb-field'] ) ) {
                            $form_values = map_deep( $params['pcpb-field'], 'sanitize_text_field' );
                        }
                        if ( isset( $params['qty'] ) ) {
                            $quantity = absint( $params['qty'] );
                        }
                    }

                    if ($type == 'variable') {
                        $all = get_posts(array(
                            'post_parent' => $product_id,
                            'post_type'   => 'product_variation',
                            'orderby'     => array('menu_order' => 'ASC', 'ID' => 'ASC'),
                            'post_status' => 'publish',
                            'numberposts' => -1,
                        ));
                        foreach ($all as $child) {
                            $vid                = $child->ID;
                            $variation          = wc_get_product($vid);
                            $variations[$vid]   = $variation->get_price('edit');

                            $width = $height = '';
                            $dimensions[$vid]   = array(
                                'width'     => $variation->get_width(),
                                'height'    => $variation->get_length()
                            );
                        }
                    }
                    $nbds_frontend = array(
                        'wc_currency_format_num_decimals'               =>  SPBWC_Storelly_PB_Util::spbwc_get_option_decimals(),
                        'currency_format_num_decimals'                  =>  SPBWC_Storelly_PB_Util::spbwc_get_option_decimals(),
                        'currency_format_symbol'                        =>  html_entity_decode((string) get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8'),
                        'currency_format_decimal_sep'                   =>  stripslashes(wc_get_price_decimal_separator()),
                        'currency_format_thousand_sep'                  =>  stripslashes(wc_get_price_thousand_separator()),
                        'currency_format'                               =>  esc_attr(str_replace(array('%1$s', '%2$s'), array('%s', '%v'), get_woocommerce_price_format())),
                        'nbstorelly_hide_add_cart_until_form_filled'    =>  get_option('spbwc_hide_add_cart_until_form_filled', 'no') === 'yes' ? 'yes' : 'no'
                    );
                    if ( function_exists( 'WC' ) && ! wp_script_is( 'wc-accounting', 'registered' ) ) {
                        wp_register_script(
                            'wc-accounting',
                            WC()->plugin_url() . '/assets/js/accounting/accounting.min.js',
                            array(),
                            defined( 'WC_VERSION' ) ? WC_VERSION : '0.4.2',
                            true
                        );
                    }
                    wp_register_script( 'spbwc-option-builder', SPBWC_PB_JS_URL . 'option-builder.js', array( 'pc-builderjs', 'wc-accounting', 'spbwc-dialog' ), '1.0.0', true );
                    wp_localize_script( 'spbwc-option-builder', 'spbwc_option_builder_variable', array(
                        'ajaxUrl'               => admin_url( 'admin-ajax.php' ),
                        'appid'                 => $this->appid,
                        'nbds_frontend'         => $nbds_frontend,
                        'options'               => $options,
                        'product_id'            => $product_id,
                        'price'                 => $product->get_price(),
                        'type'                  => $type,
                        'quantity'              => $quantity,
                        'variations'            => wp_json_encode((array) $variations),
                        'form_values'           => $form_values,
                        'is_sold_individually'  => $product->is_sold_individually(),
                        'file_too_big'          => __('Sorry, file is too big, max size: ', 'storelly-product-builder-for-woocommerce'),
                        'file_too_small'        => __('Sorry, file is too small, min size: ', 'storelly-product-builder-for-woocommerce'),
                        'file_type'             => __('Sorry, this file type is not permitted for security reasons. Only accept: ', 'storelly-product-builder-for-woocommerce'),
                        'nbo_validation_i18n'  => array(
                            /* translators: %1$s: product option field label */
                            'field_required'     => __( '%1$s: This field is required.', 'storelly-product-builder-for-woocommerce' ),
                            /* translators: 1: product option field label, 2: minimum character count */
                            'field_min_length'   => __( '%1$s: Enter at least %2$s characters.', 'storelly-product-builder-for-woocommerce' ),
                            /* translators: 1: product option field label, 2: maximum character count */
                            'field_max_length'   => __( '%1$s: No more than %2$s characters.', 'storelly-product-builder-for-woocommerce' ),
                            /* translators: 1: product option field label, 2: rejected choice label */
                            'option_unavailable'   => __( '%1$s: The choice "%2$s" is not available for the current product options.', 'storelly-product-builder-for-woocommerce' ),
                            'quantity_invalid'   => __( 'Enter a valid quantity (at least 1).', 'storelly-product-builder-for-woocommerce' ),
                            'form_invalid_generic' => __( 'Please check invalid fields, quantity, or choose a different combination.', 'storelly-product-builder-for-woocommerce' ),
                        ),
                    ));
                    wp_enqueue_script('spbwc-option-builder');
                    // Conditional-logic companion — extends the nboApp module to
                    // honor per-field conditional_depend rules. Depends on
                    // spbwc-option-builder so it loads after the module is defined
                    // and before the jQuery(document).ready bootstrap.
                    wp_register_script( 'spbwc-conditional-logic', SPBWC_PB_JS_URL . 'conditional-logic.js', array( 'spbwc-option-builder' ), SPBWC_PB_VERSION, true );
                    wp_enqueue_script( 'spbwc-conditional-logic' );

                    // Storefront flow enhancements (progress bar + mobile sticky CTA).
                    // filemtime version so edits bust the browser cache automatically.
                    $spbwc_sfe_path = SPBWC_PB_ASSETS_DIR . 'js/storefront-enhance.js';
                    $spbwc_sfe_ver  = file_exists( $spbwc_sfe_path ) ? filemtime( $spbwc_sfe_path ) : SPBWC_PB_VERSION;
                    wp_register_script( 'spbwc-storefront-enhance', SPBWC_PB_JS_URL . 'storefront-enhance.js', array( 'spbwc-option-builder' ), $spbwc_sfe_ver, true );
                    wp_enqueue_script( 'spbwc-storefront-enhance' );

                    // Token-based storefront styling for the options form. Loads
                    // whenever a product has an option (incl. plain option
                    // products that otherwise receive no buyer CSS). Depends on
                    // the design tokens so var(--st-*/--nbd-*) resolve.
                    if ( ! wp_style_is( 'spbwc-tokens', 'registered' ) ) {
                        wp_register_style( 'spbwc-tokens', SPBWC_PB_CSS_URL . '_tokens.css', array(), SPBWC_PB_VERSION );
                    }
                    wp_register_style( 'spbwc-storefront-options', SPBWC_PB_CSS_URL . 'storefront-options.css', array( 'spbwc-tokens' ), SPBWC_PB_VERSION );
                    wp_enqueue_style( 'spbwc-storefront-options' );
                }
            }
        }
        public function show_option_fields() {
            global $product; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- WooCommerce global variable.
            $product_id = $product->get_id();
            $option_id = $this->get_product_option($product_id);
            if ($option_id) {
                $_options = $this->get_option($option_id);
                if ($_options) {
                    $options = unserialize($_options['fields']);
                    if (!isset($options['fields'])) {
                        $options['fields'] = array();
                    }
                    $options['fields'] = $this->recursive_stripslashes($options['fields']);
                    // Safety net: collapse descriptor-shaped fields (from Template Library imports) to runtime shape.
                    $options['fields'] = self::spbwc_flatten_option_fields($options['fields']);
                    foreach ($options['fields'] as $key => $field) {
                        if (!isset($field['general']['attributes'])) {
                            $field['general']['attributes'] = array();
                            $field['general']['attributes']['options'] = array();
                            $options['fields'][$key]['general']['attributes'] = array();
                            $options['fields'][$key]['general']['attributes']['options'] = array();
                        }
                        if ($field['appearance']['change_image_product'] == 'y') {
                            foreach ($field['general']['attributes']['options'] as $op_index => $option) {
                                $option['product_image'] = isset($option['product_image']) ? $option['product_image'] : 0;
                                $attachment_id = absint($option['product_image']);
                                if ($attachment_id != 0) {
                                    $image_link         = wp_get_attachment_url($attachment_id);
                                    $attachment_object  = get_post($attachment_id);
                                    $full_src           = wp_get_attachment_image_src($attachment_id, 'large');
                                    $image_title        = get_the_title($attachment_id);
                                    $image_alt          = trim(wp_strip_all_tags(get_post_meta($attachment_id, '_wp_attachment_image_alt', TRUE)));
                                    $image_srcset       = function_exists('wp_get_attachment_image_srcset') ? wp_get_attachment_image_srcset($attachment_id, 'shop_single') : FALSE;
                                    $image_sizes        = function_exists('wp_get_attachment_image_sizes') ? wp_get_attachment_image_sizes($attachment_id, 'shop_single') : FALSE;
                                    $image_caption      = $attachment_object->post_excerpt;
                                    $options['fields'][$key]['general']['attributes']['options'][$op_index] = array_replace_recursive($options['fields'][$key]['general']['attributes']['options'][$op_index], array(
                                        'imagep'        => 'y',
                                        'image_link'    => $image_link,
                                        'image_title'   => $image_title,
                                        'image_alt'     => $image_alt,
                                        'image_srcset'  => $image_srcset,
                                        'image_sizes'   => $image_sizes,
                                        'image_caption' => $image_caption,
                                        'full_src'      => $full_src[0],
                                        'full_src_w'    => $full_src[1],
                                        'full_src_h'    => $full_src[2]
                                    ));
                                } else {
                                    $options['fields'][$key]['general']['attributes']['options'][$op_index]['imagep'] = 'n';
                                }
                            }
                        }
                        if (isset($field['nbpb_type']) && $field['nbpb_type'] == 'nbpb_com') {
                            if (isset($field['general']['pb_config'])) {
                                foreach ($field['general']['pb_config'] as $a_index => $attr) {
                                    foreach ($attr as $s_index => $sattr) {
                                        foreach ($sattr['views'] as $v_index => $view) {
                                            $pb_image_obj = wp_get_attachment_url(absint($view['image']));
                                            $options['fields'][$key]['general']['pb_config'][$a_index][$s_index]['views'][$v_index]['image_url'] =  $pb_image_obj ? $pb_image_obj : SPBWC_PB_ASSETS_URL . 'images/placeholder.png';
                                        }
                                    }
                                }
                            } else {
                                $field['general']['pb_config'] = array();
                            }
                            foreach ($field['general']['attributes']['options'] as $op_index => $option) {
                                if( isset($option['enable_subattr']) && $option['enable_subattr'] == 'on' && isset($option['sub_attributes']) && count($option['sub_attributes']) > 0 ){
                                    foreach( $option['sub_attributes'] as $sa_index => $sattr ){
                                        $options['fields'][$key]['general']['attributes']['options'][$op_index]['sub_attributes'][$sa_index]['image_url'] = SPBWC_Storelly_PB_Util::spbwc_get_image_thumbnail( $sattr['image'] );
                                    }
                                }else{
                                    $options['fields'][$key]['general']['attributes']['options'][$op_index]['image_url'] = SPBWC_Storelly_PB_Util::spbwc_get_image_thumbnail( $option['image'] );
                                }
                            };
                            $options['fields'][$key]['general']['component_icon_url'] = SPBWC_Storelly_PB_Util::spbwc_get_image_thumbnail($field['general']['component_icon']);
                        }
                        if (isset($field['general']['attributes']['bg_type']) && $field['general']['attributes']['bg_type'] == 'i') {
                            foreach ($field['general']['attributes']['options'] as $op_index => $option) {
                                if (!isset($option['bg_image']) || !is_array($option['bg_image'])) {
                                    continue;
                                }
                                foreach ($option['bg_image'] as $bg_index => $bg) {
                                    $bg_obj = wp_get_attachment_url(absint($bg));
                                    $options['fields'][$key]['general']['attributes']['options'][$op_index]['bg_image_url'][$bg_index] = $bg_obj ? $bg_obj : SPBWC_PB_ASSETS_URL . 'images/placeholder.png';
                                }
                            };
                        }
                    }
                    if (isset($options['views'])) {
                        foreach ($options['views'] as $vkey => $view) {
                            $view['base'] = isset($view['base']) ? $view['base'] : 0;
                            $options['views'][$vkey]['base'] = $view['base'];
                            $view_bg_obj = wp_get_attachment_url(absint($view['base']));
                            $options['views'][$vkey]['base_url'] = $view_bg_obj ? $view_bg_obj : SPBWC_PB_ASSETS_URL . 'images/placeholder.png';
                        }
                    }
                    $product        = wc_get_product($product_id); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Re-assigning global $product for local usage.
                    $type           = $product->get_type();
                    $variations     = array();
                    $dimensions     = array();
                    $form_values    = array();
                    $cart_item_key  = '';
                    $quantity       = 1;
                    $nbau           = '';
                    $nbdpb_enable   = get_post_meta($product_id, '_storelly_pb_enable', true);

                    $add_to_cart_nonce = isset( $_POST['spbwc_add_to_cart_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['spbwc_add_to_cart_nonce'] ) ) : '';
                    $add_to_cart_valid = ( $add_to_cart_nonce && wp_verify_nonce( $add_to_cart_nonce, 'spbwc_add_to_cart_action' ) );

                    if ( isset( $_POST['pcpb-field'] ) && $add_to_cart_valid ) {
                        $form_values = $pcpb_field_sanitized;
                    } elseif ( isset( $_GET['pcpb_cart_item_key'], $_GET['_wpnonce'] ) ) {
                        $edit_nonce = sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) );
                        if ( ! wp_verify_nonce( $edit_nonce, 'nbo-edit' ) ) {
                            $edit_nonce = '';
                        }
                        if ( $edit_nonce ) {
                            $cart_item_key = sanitize_text_field( wp_unslash( $_GET['pcpb_cart_item_key'] ) );
                        }
                        $cart_item         = WC()->cart->get_cart_item( $cart_item_key );
                        if ( isset( $cart_item['pcpb_meta'] ) ) {
                            $form_values = $cart_item['pcpb_meta']['field'];
                        }
                    }

                    if ( isset( $_GET['nbo_values'], $_GET['_wpnonce'] ) ) {
                        $order_again_nonce = sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) );
                        $order_again_valid = (
                            wp_verify_nonce( $order_again_nonce, 'woocommerce-order_again' ) ||
                            wp_verify_nonce( $order_again_nonce, 'woocommerce-order-again' ) ||
                            wp_verify_nonce( $order_again_nonce, 'order_again' ) ||
                            wp_verify_nonce( $order_again_nonce, 'order-again' )
                        );
                        if ( ! $order_again_valid ) {
                            $order_again_nonce = '';
                        }
                    }

                    if ( isset( $_GET['nbo_values'] ) && ! empty( $order_again_nonce ) ) {
                        $params           = array();
                        $value_str_raw    = wp_unslash( $_GET['nbo_values'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Base64 value is sanitized after decoding and parsing.
                        $value_str        = base64_decode( $value_str_raw );
                        parse_str( $value_str, $params );
                        if ( isset( $params['pcpb-field'] ) ) {
                            $form_values = map_deep( $params['pcpb-field'], 'sanitize_text_field' );
                        }
                        if ( isset( $params['qty'] ) ) {
                            $quantity = absint( $params['qty'] );
                        }
                    }

                    if ($type == 'variable') {
                        $all = get_posts(array(
                            'post_parent' => $product_id,
                            'post_type'   => 'product_variation',
                            'orderby'     => array('menu_order' => 'ASC', 'ID' => 'ASC'),
                            'post_status' => 'publish',
                            'numberposts' => -1,
                        ));
                        foreach ($all as $child) {
                            $vid                = $child->ID;
                            $variation          = wc_get_product($vid);
                            $variations[$vid]   = $variation->get_price('edit');

                            $width = $height = '';
                            $dimensions[$vid]   = array(
                                'width'     => $variation->get_width(),
                                'height'    => $variation->get_length()
                            );
                        }
                    }
                    // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obstart_ob_start -- Output buffer opened and closed in same scope for template rendering.
                    ob_start();
                    wp_nonce_field( 'spbwc_add_to_cart_action', 'spbwc_add_to_cart_nonce' );
                    SPBWC_Storelly_PB_Util::spbwc_get_template('single-product/option-builder.php', array(
                        'product_id'            => $product_id,
                        'appid'                 => $this->appid,
                        'options'               => $options,
                        'type'                  => $type,
                        'quantity'              => $quantity,
                        'nbdpb_enable'          => $nbdpb_enable,
                        'price'                 => $product->get_price('edit'),
                        'is_sold_individually'  => $product->is_sold_individually(),
                        'variations'            => wp_json_encode((array) $variations),
                        'dimensions'            => wp_json_encode((array) $dimensions),
                        'form_values'           => $form_values,
                        'cart_item_key'         => $cart_item_key,
                        'nbau'                  => $nbau,
                    ));
                    $options_form = ob_get_clean(); // Buffer closed here - no early return possible.
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output contains required markup rendered from trusted templates.
                    echo $options_form;
                }
            }
        }
        public function add_cart_item_data($cart_item_data, $product_id, $variation_id, $quantity = 1) {
            $option_id = $this->get_product_option($product_id);
            if (!$option_id) {
                return $cart_item_data;
            }

            $needs_nonce = isset( $_POST['pcpb-field'] ) || isset( $_POST['pcpb-add-to-cart'] ) || ( ! empty( $_FILES ) && isset( $_FILES['pcpb-field'] ) );
            if ( $needs_nonce ) {
                $nonce_value = isset( $_POST['spbwc_add_to_cart_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['spbwc_add_to_cart_nonce'] ) ) : '';
                if ( ! $nonce_value || ! wp_verify_nonce( $nonce_value, 'spbwc_add_to_cart_action' ) ) {
                    return $cart_item_data;
                }
            }

            $pcpb_add_to_cart = isset( $_POST['pcpb-add-to-cart'] ) ? sanitize_text_field( wp_unslash( $_POST['pcpb-add-to-cart'] ) ) : '';
            $post_field       = isset( $_POST['pcpb-field'] ) ? map_deep( wp_unslash( $_POST['pcpb-field'] ), 'sanitize_text_field' ) : array();
            $prcpb_folder     = isset( $_POST['prcpb-folder'] ) ? sanitize_text_field( wp_unslash( $_POST['prcpb-folder'] ) ) : '';

            if ( ! empty( $post_field ) || '' !== $pcpb_add_to_cart ) {
                $options        = $this->get_option($option_id);
                $nbd_field      = $post_field;
                if (isset($cart_item_data['pcpb-field'])) {
                    /* Bulk variation */
                    $nbd_field = $cart_item_data['pcpb-field'];
                    unset($cart_item_data['pcpb-field']);
                } else { 
                    if ( ! empty( $_FILES ) && isset( $_FILES['pcpb-field'] ) ) {
                        $files = $_FILES['pcpb-field']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Upload array validated and sanitized inside upload_file().
                        // Map each field's configured upload constraints (allowed
                        // extensions + max size) so the server enforces what the
                        // merchant set, not a fixed list.
                        $upload_cfg_map = array();
                        $decoded_fields = $options['fields'];
                        if ( is_string( $decoded_fields ) ) {
                            if ( SPBWC_Storelly_PB_Util::spbwc_is_base64_string( $decoded_fields ) ) {
                                $decoded_fields = base64_decode( $decoded_fields ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Internal option blob, not user input.
                            }
                            $decoded_fields = maybe_unserialize( $decoded_fields );
                        }
                        if ( is_array( $decoded_fields ) && isset( $decoded_fields['fields'] ) && is_array( $decoded_fields['fields'] ) ) {
                            foreach ( $decoded_fields['fields'] as $f ) {
                                if ( isset( $f['id'], $f['general']['upload_option'] ) ) {
                                    $upload_cfg_map[ $f['id'] ] = $f['general']['upload_option'];
                                }
                            }
                        }
                        foreach ( $files['name'] as $field_id => $file_name ) {
                            if ( ! isset( $nbd_field[ $field_id ] ) ) {
                                $cfg = isset( $upload_cfg_map[ $field_id ] ) ? $upload_cfg_map[ $field_id ] : array();
                                $nbd_upload_field = $this->upload_file( $files, $field_id, $cfg );
                                if ( ! empty( $nbd_upload_field ) ) {
                                    $nbd_field[ $field_id ] = $nbd_upload_field[ $field_id ];
                                }
                            }
                        }
                    }
                }
                $product        = $variation_id ? wc_get_product($variation_id) : wc_get_product($product_id);
                $original_price = (float)$product->get_price('edit');
                $option_price   = $this->option_processing($options, $original_price, $nbd_field, $quantity, null, $product, $prcpb_folder);
                if ( '' !== $prcpb_folder ) {
                    $cart_item_data['pcpb_meta']['pcpb'] = $prcpb_folder;
                    $path   = SPBWC_PB_CUSTOMER_DIR . '/' . $prcpb_folder . '/preview';
                    $images = SPBWC_Storelly_IO::spbwc_get_list_images($path, 1);
                    if (count($images)) {
                        ksort($images);
                        $option_price['cart_image'] = SPBWC_Storelly_IO::spbwc_convert_path_to_url(end($images));
                    }
                }
                $options['fields']                              = base64_encode($options['fields']);
                $cart_item_data['pcpb_meta']['option_price']     = $option_price;
                $cart_item_data['pcpb_meta']['field']            = $nbd_field;
                $cart_item_data['pcpb_meta']['options']          = $options;
                $cart_item_data['pcpb_meta']['original_price']   = $original_price;
                $cart_item_data['pcpb_meta']['price']            = $original_price + $option_price['total_price'] - $option_price['discount_price'];
            }
            return $cart_item_data;
        }

        public function custom_upload_directory_no_date(){
            $user_folder = md5(WC()->session->get_customer_id());
            $upload['subdir'] = '/storelly-product-builder/uploads/' . $user_folder;
            $upload['path'] = $upload['basedir'] . $upload['subdir'];
            $upload['url'] = $upload['baseurl'] . $upload['subdir'];
            return $upload;
        }

        public function upload_file($files, $field_id, $upload_cfg = array()) {
            $nbd_upload_fields = array();
            $user_folder = md5(WC()->session->get_customer_id());

            // Validate file input exists
            if ( ! isset( $files['name'][ $field_id ] ) ) {
                return $nbd_upload_fields;
            }

            $raw_name = $files['name'][$field_id];
            $file_name_clean = sanitize_file_name( wp_basename( $raw_name ) );

            $file_error = isset( $files['error'][ $field_id ] ) ? absint( $files['error'][ $field_id ] ) : UPLOAD_ERR_NO_FILE;
            if ( UPLOAD_ERR_OK !== $file_error ) {
                return $nbd_upload_fields;
            }

            $tmp_name = isset( $files['tmp_name'][ $field_id ] ) ? $files['tmp_name'][ $field_id ] : '';
            if ( '' === $tmp_name || ! is_uploaded_file( $tmp_name ) ) {
                return $nbd_upload_fields;
            }

            // Safe server-side extension whitelist (never accept scripts/executables).
            // Intersected below with the merchant's configured allow_type so the
            // server honors the field config instead of a fixed list.
            $safe_whitelist = apply_filters(
                'spbwc_upload_file_allowed_extensions',
                array( 'json', 'svg', 'png', 'jpg', 'jpeg', 'gif', 'pdf', 'webp', 'bmp', 'tiff', 'tif', 'ai', 'eps', 'psd' )
            );
            $allowed = $safe_whitelist;
            if ( isset( $upload_cfg['allow_type'] ) && '' !== trim( (string) $upload_cfg['allow_type'] ) ) {
                $merchant = array_filter( array_map(
                    static function ( $t ) { return strtolower( trim( $t ) ); },
                    explode( ',', (string) $upload_cfg['allow_type'] )
                ) );
                if ( in_array( 'jpg', $merchant, true ) || in_array( 'jpeg', $merchant, true ) ) {
                    $merchant[] = 'jpg';
                    $merchant[] = 'jpeg';
                }
                if ( in_array( 'tiff', $merchant, true ) || in_array( 'tif', $merchant, true ) ) {
                    $merchant[] = 'tiff';
                    $merchant[] = 'tif';
                }
                $allowed = array_values( array_intersect( $safe_whitelist, $merchant ) );
            }
            if ( empty( $allowed ) ) {
                return $nbd_upload_fields;
            }

            // Temporarily widen mimes so WP's filetype check + uploader accept the
            // configured print/design formats (ai/eps/psd/pdf…). Scoped to this
            // upload only — removed before returning.
            add_filter( 'upload_mimes', array( $this, 'spbwc_widen_upload_mimes' ) );

            $file_check = wp_check_filetype_and_ext( $tmp_name, $file_name_clean );
            $ext  = isset( $file_check['ext'] ) ? $file_check['ext'] : '';
            $type = isset( $file_check['type'] ) ? $file_check['type'] : '';

            if ( '' === $ext || '' === $type || ! in_array( $ext, $allowed, true ) ) {
                remove_filter( 'upload_mimes', array( $this, 'spbwc_widen_upload_mimes' ) );
                return $nbd_upload_fields;
            }

            // Enforce the merchant's max file size (MB) on the server, not just client-side.
            $size_bytes = isset( $files['size'][ $field_id ] ) ? absint( $files['size'][ $field_id ] ) : 0;
            if ( isset( $upload_cfg['max_size'] ) && (float) $upload_cfg['max_size'] > 0 ) {
                $max_bytes = (float) $upload_cfg['max_size'] * 1024 * 1024;
                if ( $size_bytes > $max_bytes ) {
                    remove_filter( 'upload_mimes', array( $this, 'spbwc_widen_upload_mimes' ) );
                    return $nbd_upload_fields;
                }
            }

            $new_name = strtotime( 'now' ) . substr( md5( rand( 1111, 9999 ) ), 0, 8 ) . '.' . $ext; // phpcs:ignore WordPress.WP.AlternativeFunctions.rand_rand
            $mkpath = wp_mkdir_p( SPBWC_PB_UPLOAD_DIR . '/' . $user_folder );

            if ( $mkpath ) {
                add_filter( 'upload_dir', array( $this, 'custom_upload_directory_no_date' ) );
                $file = array(
                    'name'     => $new_name, // Use the new sanitized safe name
                    'type'     => $type,
                    'tmp_name' => $tmp_name,
                    'error'    => $file_error,
                    'size'     => $size_bytes,
                );
                $upload_overrides = array( 'test_form' => false );
                $movefile = wp_handle_upload( $file, $upload_overrides );
                remove_filter( 'upload_dir', array( $this, 'custom_upload_directory_no_date' ) );
                if ( $movefile && ! isset( $movefile['error'] ) ) {
                    $nbd_upload_fields[ $field_id ] = $user_folder . '/' . $new_name;
                }
            }
            remove_filter( 'upload_mimes', array( $this, 'spbwc_widen_upload_mimes' ) );
            return $nbd_upload_fields;
        }
        /**
         * Print/design mimes allowed only during a buyer artwork upload (added +
         * removed around wp_handle_upload). Non-executable formats only.
         */
        public function spbwc_widen_upload_mimes( $mimes ) {
            $mimes['pdf']      = 'application/pdf';
            $mimes['ai']       = 'application/postscript';
            $mimes['eps']      = 'application/postscript';
            $mimes['psd']      = 'image/vnd.adobe.photoshop';
            $mimes['tiff|tif'] = 'image/tiff';
            $mimes['webp']     = 'image/webp';
            $mimes['bmp']      = 'image/bmp';
            return $mimes;
        }
        public function get_field_by_id($option_fields, $field_id) {
            foreach ($option_fields['fields'] as $key => $field) {
                if ($field['id'] == $field_id) return $field;
            }
        }
        public function option_processing($options, $original_price, $fields, $quantity, $cart_item_key = null, $product = null, $design_folder = '') {
            if (SPBWC_Storelly_PB_Util::spbwc_is_base64_string($options['fields'])) {
                $options['fields'] = base64_decode($options['fields']);
            }
            $option_fields  = maybe_unserialize($options['fields']);
            $option_fields  = $this->recursive_stripslashes($option_fields);
            $xfactor        = 1;
            $total_price    = 0;
            $discount_price = 0;
            $_fields        = array();
            $cart_image     = '';
            $cart_item_fee  = 0;
            $line_price     = array(
                'fixed'     => 0,
                'percent'   => 0,
                'xfactor'   => 1
            );
            foreach ($fields as $key => $val) {
                $origin_field = $this->get_field_by_id($option_fields, $key);
                $published    = isset($origin_field['general']['published']) ? $origin_field['general']['published'] : 'y';
                $_fields[$key] = array(
                    'name'          => $origin_field['general']['title'],
                    'val'           => $val,
                    'value_name'    => $val,
                    'published'     => $published
                );
                if ($origin_field['general']['data_type'] == 'i') {
                    $factor = isset($origin_field['general']['price']) ? $origin_field['general']['price'] : 0;
                    if ($origin_field['general']['input_type'] == 'u') {
                        $file_name = explode('/', $val);
                        $_fields[$key]['value_name']    = $file_name[count($file_name) - 1];
                        $_fields[$key]['is_upload']     = 1;
                    }
                } else {
                    $select_val = is_array($val) ? (isset($val['value']) ? $val['value'] : $val[0]) : $val;
                    $option = $origin_field['general']['attributes']['options'][$select_val];
                    $has_subattr = false;
                    if (isset($option['enable_subattr']) && $option['enable_subattr'] == 'on' && isset($option['sub_attributes']) && count($option['sub_attributes']) > 0) {
                        $has_subattr = true;
                    }
                    if (!$has_subattr && isset($val['sub_value'])) {
                        unset($val['sub_value']);
                    }
                    $_fields[$key]['value_name'] = $option['name'];
                    if (is_array($val)) {
                        if ($has_subattr) {
                            $_fields[$key]['value_name'] .= ' - ' . $option['sub_attributes'][$val['sub_value']]['name'];
                        } else {
                            $_fields[$key]['value_name'] = '';
                            foreach ($val as $k => $v) {
                                $_fields[$key]['value_name'] .= ($k == 0 ? '' : ', ') . $origin_field['general']['attributes']['options'][$v]['name'];
                            }
                        }
                    }
                    $factor = floatval($option['price'][0]);

                    if ($has_subattr && isset($val['sub_value'])) {
                        $soption = $option['sub_attributes'][$val['sub_value']];
                        $factor += floatval($soption['price'][0]);
                    }
                    if ($origin_field['appearance']['change_image_product'] == 'y' && isset($option['product_image']) && $option['product_image'] > 0) {
                        $image = wp_get_attachment_image_src($option['product_image'], 'thumbnail');
                        if ($image) {
                            $cart_image = $image[0];
                        } else {
                            $cart_image = wp_get_attachment_url($option['product_image']);
                        }
                    }
                }
                $_fields[$key]['is_pp'] = 0;
                $factor = floatval($factor);
                $_factor = $factor;
                switch ($origin_field['general']['price_type']) {
                    case 'f':
                        $_fields[$key]['price'] = $_factor;
                        $total_price += $factor;
                        break;
                    case 'p':
                        $_fields[$key]['price'] = $original_price * $_factor / 100;
                        $total_price += $original_price * $factor / 100;
                        break;
                    case 'p+':
                        $_fields[$key]['price'] = $factor / 100;
                        $_fields[$key]['_price'] = $_factor / 100;
                        $_fields[$key]['is_pp'] = 1;
                        $xfactor *= (1 + $factor / 100);
                        break;
                    case 'c':
                        $current_val = absint($val);
                        $current_val = $current_val > 0 ? $current_val : 0;
                        $_fields[$key]['price'] = $_factor * $current_val;
                        $total_price += $factor * $current_val;
                        break;
                    case 'cp':
                        $_fields[$key]['price'] = $_factor * absint(strlen($val));
                        $total_price += $factor * absint(strlen($val));
                        break;
                }
            }
            $total_price += (($original_price + $total_price) * ($xfactor - 1));
            foreach ($_fields as $key => $val) {
                if ($_fields[$key]['is_pp'] == 1) {
                    $_fields[$key]['price'] = $_fields[$key]['price'] * ($original_price + $total_price) / ($_fields[$key]['price'] + 1);
                }
            }
            $total_cart_price = ($original_price + $total_price) * $quantity;
            $cart_item_fee = array(
                'value'   => 0,
                'name'    => '',
                'id'      => '',
                'fields'  => array()
            );
            if ($line_price['fixed'] != 0 || $line_price['xfactor'] != 1 || $line_price['percent'] != 0) {
                $_total_cart_price = $total_cart_price;
                if ($line_price['fixed'] != 0) {
                    $total_cart_price += $line_price['fixed'];
                }
                if ($line_price['percent'] != 0) {
                    $total_cart_price += ($original_price * $line_price['percent'] / 100);
                }
                if ($line_price['xfactor'] != 1) {
                    $total_cart_price += ($total_cart_price * ($line_price['xfactor'] - 1));
                    foreach ($_fields as $key => $val) {
                        if ($val['is_pp'] == 1 && isset($val['ind_qty']) && $val['ind_qty'] == 1) {
                            $_fields[$key]['price'] = $val['_price'] * $total_cart_price / ($val['_price'] + 1);
                        }
                    }
                }
                foreach ($_fields as $key => $val) {
                    if (isset($val['ind_qty']) && $val['ind_qty'] == 1) {
                        $cart_item_fee['name'] .= $val['name'] . ', ';
                        $cart_item_fee['fields'][] = array(
                            'name'  => $val['name'] . ': ' . $val['value_name'],
                            'price' => $val['price']
                        );
                    }
                }
                if ($cart_item_fee['name'] != '') {
                    $cart_item_fee['name'] = substr($cart_item_fee['name'], 0, strlen($cart_item_fee['name']) - 2);
                }
                $cart_item_fee['value'] = $total_cart_price - $_total_cart_price;
            }
            // Free design tools — fixed per-layer fee for buyer-added text/image
            // layers. The COUNT is read from the saved design folder
            // (user-layers.json, written server-side from the actual canvas at
            // save time), the PRICE + caps from the resolved option-set config.
            // Clamped to the configured max so a tampered canvas cannot bill
            // beyond the merchant's cap. Added to $total_price before the volume
            // discount so a percentage tier applies to it consistently.
            $spbwc_layer_fee   = 0;
            $spbwc_layer_text  = 0;
            $spbwc_layer_image = 0;
            if ( '' !== (string) $design_folder && $design_folder === basename( $design_folder ) ) {
                $spbwc_ul_path = SPBWC_PB_CUSTOMER_DIR . '/' . $design_folder . '/user-layers.json';
                $spbwc_ul_raw  = SPBWC_Storelly_IO::spbwc_get_local_file_contents( $spbwc_ul_path );
                if ( false !== $spbwc_ul_raw && '' !== $spbwc_ul_raw ) {
                    $spbwc_ul = json_decode( $spbwc_ul_raw, true );
                    if ( is_array( $spbwc_ul ) ) {
                        $spbwc_fdt   = SPBWC_Storelly_PB_Util::spbwc_get_free_design_tools( 0, is_array( $option_fields ) ? $option_fields : null );
                        $spbwc_layer_text  = isset( $spbwc_ul['text'] ) ? max( 0, (int) $spbwc_ul['text'] ) : 0;
                        $spbwc_layer_image = isset( $spbwc_ul['image'] ) ? max( 0, (int) $spbwc_ul['image'] ) : 0;
                        if ( 'y' === $spbwc_fdt['text']['enabled'] ) {
                            if ( $spbwc_fdt['text']['max_layers'] > 0 ) {
                                $spbwc_layer_text = min( $spbwc_layer_text, $spbwc_fdt['text']['max_layers'] );
                            }
                            $spbwc_layer_fee += $spbwc_layer_text * (float) $spbwc_fdt['text']['price_per_layer'];
                        } else {
                            $spbwc_layer_text = 0;
                        }
                        if ( 'y' === $spbwc_fdt['image']['enabled'] ) {
                            if ( $spbwc_fdt['image']['max_layers'] > 0 ) {
                                $spbwc_layer_image = min( $spbwc_layer_image, $spbwc_fdt['image']['max_layers'] );
                            }
                            $spbwc_layer_fee += $spbwc_layer_image * (float) $spbwc_fdt['image']['price_per_layer'];
                        } else {
                            $spbwc_layer_image = 0;
                        }
                    }
                }
            }
            $total_price += $spbwc_layer_fee;
            // Quantity volume-discount engine. `quantity_breaks` and `quantity_discount_type`
            // live INSIDE the serialized option blob (read via $option_fields above), not at
            // the outer $options wrapper (which only carries the DB columns). Each tier is
            // { val, dis }; 'p' = percent of (base+options) per item, 'f' = fixed amount per
            // item. The highest tier where qty >= val wins; empty/zero `dis` rows are skipped.
            // discount_price is per-item; cart code subtracts it from each item's price and
            // shows a "Quantity Discount" meta line (see prepare_meta_data / get_item_data).
            $spbwc_qbreaks = ( is_array( $option_fields ) && isset( $option_fields['quantity_breaks'] ) && is_array( $option_fields['quantity_breaks'] ) ) ? $option_fields['quantity_breaks'] : array();
            if ( ! empty( $spbwc_qbreaks ) && (int) $quantity > 0 ) {
                $spbwc_dtype    = ( is_array( $option_fields ) && isset( $option_fields['quantity_discount_type'] ) ) ? $option_fields['quantity_discount_type'] : 'f';
                $spbwc_tier_val = 0;
                $spbwc_tier_dis = 0;
                foreach ( $spbwc_qbreaks as $spbwc_b ) {
                    if ( ! is_array( $spbwc_b ) ) { continue; }
                    $spbwc_bv = isset( $spbwc_b['val'] ) ? $spbwc_b['val'] : '';
                    $spbwc_bd = isset( $spbwc_b['dis'] ) ? $spbwc_b['dis'] : '';
                    $spbwc_bv = is_array( $spbwc_bv ) ? ( isset( $spbwc_bv['value'] ) ? $spbwc_bv['value'] : '' ) : $spbwc_bv;
                    $spbwc_bd = is_array( $spbwc_bd ) ? ( isset( $spbwc_bd['value'] ) ? $spbwc_bd['value'] : '' ) : $spbwc_bd;
                    if ( '' === (string) $spbwc_bv || '' === (string) $spbwc_bd ) { continue; }
                    $spbwc_bv = (int) $spbwc_bv;
                    $spbwc_bd = (float) $spbwc_bd;
                    if ( $spbwc_bv > 0 && $spbwc_bd > 0 && (int) $quantity >= $spbwc_bv && $spbwc_bv > $spbwc_tier_val ) {
                        $spbwc_tier_val = $spbwc_bv;
                        $spbwc_tier_dis = $spbwc_bd;
                    }
                }
                if ( $spbwc_tier_dis > 0 ) {
                    if ( 'p' === $spbwc_dtype ) {
                        $discount_price = ( $original_price + $total_price ) * $spbwc_tier_dis / 100;
                    } else {
                        $discount_price = $spbwc_tier_dis;
                    }
                }
            }
            return array(
                'total_price'       => $total_price,
                'cart_item_fee'     => $cart_item_fee,
                'discount_price'    => $discount_price,
                'fields'            => $_fields,
                'cart_image'        => $cart_image,
                'original_price'    => $original_price,
                'user_layers_fee'   => $spbwc_layer_fee,
                'user_layers_text'  => $spbwc_layer_text,
                'user_layers_image' => $spbwc_layer_image
            );
        }
        public function add_to_cart_text($var) {
            if ($this->is_edit_mode) {
                return esc_attr__('Update cart', 'storelly-product-builder-for-woocommerce');
            }
            return $var;
        }
        public function remove_previous_product_from_cart($passed, $product_id, $qty, $variation_id = '', $variations = array(), $cart_item_data = array()) {
            if ($this->cart_edit_key) {
                $cart_item_key = $this->cart_edit_key;
                if (isset($this->new_add_to_cart_key)) {
                    if ($this->new_add_to_cart_key == $cart_item_key && isset($_POST['quantity'])) {
                        $nonce_value = isset( $_POST['spbwc_add_to_cart_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['spbwc_add_to_cart_nonce'] ) ) : '';
                        if ( $nonce_value && wp_verify_nonce( $nonce_value, 'spbwc_add_to_cart_action' ) ) {
                            $posted_quantity = absint( wp_unslash( $_POST['quantity'] ) );
                            WC()->cart->set_quantity($this->new_add_to_cart_key, $posted_quantity, TRUE);
                        }
                    } else {
                        $nbd_session = WC()->session->get($cart_item_key . '_nbd');
                        if ($nbd_session) {
                            WC()->session->set('pcpb_session_removed', $nbd_session);
                            WC()->session->__unset($cart_item_key . '_nbd');

                            if (isset(WC()->cart->cart_contents[$cart_item_key]['nbd_design_id'])) {
                                $design_id = WC()->cart->cart_contents[$cart_item_key]['nbd_design_id'];
                                WC()->session->set('pcpb_session_design_id_removed', $design_id);
                            }
                        }
                        WC()->cart->remove_cart_item($cart_item_key);
                        unset(WC()->cart->removed_cart_contents[$cart_item_key]);
                    }
                }
            }
            return $passed;
        }
        public function add_to_cart($cart_item_key = "", $product_id = "", $quantity = "", $variation_id = "", $variation = "", $cart_item_data = "") {
            if ($this->cart_edit_key) {
                $this->new_add_to_cart_key = $cart_item_key;
                $nbd_session = WC()->session->get('pcpb_session_removed');
                if ($nbd_session) {
                    WC()->session->set($cart_item_key . '_nbd', $nbd_session);
                    if (!isset(WC()->cart->cart_contents[$cart_item_key]['nbd_item_meta_ds'])) WC()->cart->cart_contents[$cart_item_key]['nbd_item_meta_ds'] = array();
                    WC()->cart->cart_contents[$cart_item_key]['nbd_item_meta_ds']['nbd'] = $nbd_session;
                    WC()->session->__unset('pcpb_session_removed');

                    $design_id = WC()->session->get('pcpb_session_design_id_removed');
                    if ($design_id) {
                        WC()->cart->cart_contents[$cart_item_key]['nbd_design_id'] = $design_id;
                        WC()->session->__unset('pcpb_session_design_id_removed');
                    }
                }
            } else {
                if (is_array($cart_item_data) && isset($cart_item_data['pcpb_meta'])) {
                    $cart_contents = WC()->cart->cart_contents;
                    if (
                        is_array($cart_contents) &&
                        isset($cart_contents[$cart_item_key]) &&
                        !empty($cart_contents[$cart_item_key]) &&
                        !isset($cart_contents[$cart_item_key]['pcpb_cart_item_key'])
                    ) {
                        WC()->cart->cart_contents[$cart_item_key]['pcpb_cart_item_key'] = $cart_item_key;
                    }
                }
            }
        }
        public function quantity_input_args($args = "", $product = "") {
            if ($this->cart_edit_key) {
                $cart_item_key = $this->cart_edit_key;
                $cart_item = WC()->cart->get_cart_item($cart_item_key);
                if (isset($cart_item["quantity"])) {
                    $args["input_value"] = $cart_item["quantity"];
                }
            }
            return $args;
        }
        public function re_calculate_price($cart) {
            foreach ($cart->cart_contents as $cart_item_key => $cart_item) {
                if (isset($cart_item['pcpb_meta'])) {
                    $variation_id   = $cart_item['variation_id'];
                    $product_id     = $cart_item['product_id'];
                    $product        = $variation_id ? wc_get_product($variation_id) : wc_get_product($product_id);
                    $options        = $cart_item['pcpb_meta']['options'];
                    $fields         = $cart_item['pcpb_meta']['field'];
                    $original_price = (float)$product->get_price('edit');
                    $quantity       = $cart_item['quantity'];
                    $spbwc_cart_folder = isset($cart_item['pcpb_meta']['pcpb']) ? $cart_item['pcpb_meta']['pcpb'] : '';
                    $option_price   = $this->option_processing($options, $original_price, $fields, $quantity, $cart_item_key, $product, $spbwc_cart_folder);
                    if (isset($cart_item['pcpb_meta']['pcpb'])) {
                        $path   = SPBWC_PB_CUSTOMER_DIR . '/' . $cart_item['pcpb_meta']['pcpb'] . '/preview';
                        $images = SPBWC_Storelly_IO::spbwc_get_list_images($path, 1);
                        if (count($images)) {
                            ksort($images);
                            $option_price['cart_image'] = SPBWC_Storelly_IO::spbwc_convert_path_to_url(end($images));
                        }
                    }
                    $adjusted_price = $original_price + $option_price['total_price'] - $option_price['discount_price'];
                    $adjusted_price = $adjusted_price > 0 ? $adjusted_price : 0;
                    WC()->cart->cart_contents[$cart_item_key]['pcpb_meta']['option_price'] = $option_price;
                    $adjusted_price = apply_filters('storelly_adjusted_price', $adjusted_price, $cart_item);
                    WC()->cart->cart_contents[$cart_item_key]['pcpb_meta']['price'] = $adjusted_price;
                    $needed_change  = apply_filters('storelly_need_change_cart_item_price', true, WC()->cart->cart_contents[$cart_item_key]);
                    if ($needed_change) WC()->cart->cart_contents[$cart_item_key]['data']->set_price($adjusted_price);
                }
            }
        }
        public function order_line_item($item, $cart_item_key, $values) {
            if (isset($values['pcpb_meta'])) {
                $num_decimals = SPBWC_Storelly_PB_Util::spbwc_get_option_decimals();
                foreach ($values['pcpb_meta']['option_price']['fields'] as $field) {
                    if (!isset($field['published']) || $field['published'] == 'y') {
                        $price = floatval($field['price']) >= 0 ? '+' . wc_price($field['price'], array('decimals' => $num_decimals)) : wc_price($field['price'], array('decimals' => $num_decimals));
                        if (isset($field['is_upload'])) {
                            if (strpos($field['val'], 'http') !== false) {
                                $file_url = $field['val'];
                            } else {
                                $file_url = SPBWC_Storelly_IO::spbwc_convert_path_to_url(SPBWC_PB_UPLOAD_DIR . '/' . $field['val']);
                            }
                            $field['value_name'] = '<a href="' . $file_url . '">' . $field['value_name'] . '</a>';
                        }
                        $post_fix = '';
                        if (isset($field['ind_qty'])) {
                            $post_fix = '<small>' . esc_html__('( cart fee )', 'storelly-product-builder-for-woocommerce') . '</small>';
                        }
                        if (isset($field['fixed_amount'])) {
                            $post_fix = '<small>' . esc_html__('( for all items )', 'storelly-product-builder-for-woocommerce') . '</small>';
                        }
                        $display_price = $price . $post_fix;
                        $item->add_meta_data($field['name'], $field['value_name'] . '&nbsp;&nbsp;' . $display_price);
                    }
                }
                if (floatval($values['pcpb_meta']['option_price']['discount_price']) > 0) {
                    $item->add_meta_data(esc_html__('Quantity Discount', 'storelly-product-builder-for-woocommerce'), '-' . wc_price($values['pcpb_meta']['option_price']['discount_price'], array('decimals' => $num_decimals)));
                }
                // Free design tools — surface the buyer-added layer fee as a line.
                if (isset($values['pcpb_meta']['option_price']['user_layers_fee']) && floatval($values['pcpb_meta']['option_price']['user_layers_fee']) > 0) {
                    $spbwc_ul_text  = isset($values['pcpb_meta']['option_price']['user_layers_text']) ? (int) $values['pcpb_meta']['option_price']['user_layers_text'] : 0;
                    $spbwc_ul_image = isset($values['pcpb_meta']['option_price']['user_layers_image']) ? (int) $values['pcpb_meta']['option_price']['user_layers_image'] : 0;
                    $spbwc_ul_bits  = array();
                    if ($spbwc_ul_text > 0) {
                        /* translators: %d: number of custom text layers */
                        $spbwc_ul_bits[] = sprintf(_n('%d text', '%d text', $spbwc_ul_text, 'storelly-product-builder-for-woocommerce'), $spbwc_ul_text);
                    }
                    if ($spbwc_ul_image > 0) {
                        /* translators: %d: number of custom image layers */
                        $spbwc_ul_bits[] = sprintf(_n('%d image', '%d images', $spbwc_ul_image, 'storelly-product-builder-for-woocommerce'), $spbwc_ul_image);
                    }
                    $spbwc_ul_label = implode(', ', $spbwc_ul_bits);
                    $item->add_meta_data(
                        esc_html__('Custom text & images', 'storelly-product-builder-for-woocommerce'),
                        ($spbwc_ul_label ? $spbwc_ul_label . '&nbsp;&nbsp;' : '') . '+' . wc_price($values['pcpb_meta']['option_price']['user_layers_fee'], array('decimals' => $num_decimals))
                    );
                }
                $item->add_meta_data('_pcpb_option_price', $values['pcpb_meta']['option_price']);
                $item->add_meta_data('_pcpb_field', $values['pcpb_meta']['field']);
                $item->add_meta_data('_pcpb_folder', $values['pcpb_meta']['pcpb']);
                $item->add_meta_data('_pcpb_options', wp_slash($values['pcpb_meta']['options']));
                $item->add_meta_data('_pcpb_original_price', $values['pcpb_meta']['original_price']);
            }
        }
        public function add_cart_fee($cart_object) {
            if (is_array($cart_object->cart_contents)) {
                foreach ($cart_object->cart_contents as $key => $value) {
                    if (isset($value['pcpb_meta']) && isset($value['pcpb_meta']['option_price']['cart_item_fee']) && $value['pcpb_meta']['option_price']['cart_item_fee']['value'] != 0) {
                        $fees       = array();
                        if (is_object($cart_object) && is_callable(array($cart_object, "get_fees"))) {
                            $fees = $cart_object->get_fees();
                        } else {
                            $fees = $cart_object->fees;
                        }
                        foreach ($value['pcpb_meta']['option_price']['cart_item_fee']['fields'] as $field) {
                            $fee_name       = $field['name'] . ' - ' . strtoupper(substr($key, 0, 7));
                            $product        = $value["data"];
                            $tax_class      = $product->get_tax_class();
                            $tax_status     = $product->get_tax_status();
                            if (get_option('woocommerce_calc_taxes') == "yes" && $tax_status == "taxable") {
                                $tax = TRUE;
                            } else {
                                $tax = FALSE;
                            }
                            $fee_price  = $field['price'];
                            $can_add    = TRUE;
                            if (is_array($fees)) {
                                foreach ($fees as $fee) {
                                    if ($fee->id == sanitize_title($fee_name)) {
                                        $fee->amount = (float) $fee_price;
                                        $can_add     = FALSE;
                                        break;
                                    }
                                }
                            }
                            if ($can_add) {
                                $cart_object->add_fee($fee_name, $fee_price, $tax, $tax_status);
                            }
                        }
                    }
                }
            }
        }
        public function get_item_data($item_data, $cart_item) {
            if (isset($cart_item['pcpb_meta'])) {
                $num_decimals = SPBWC_Storelly_PB_Util::spbwc_get_option_decimals();
                foreach ($cart_item['pcpb_meta']['option_price']['fields'] as $field) {
                    if (!isset($field['published']) || $field['published'] == 'y') {
                        $price = floatval($field['price']) >= 0 ? '+' . wc_price($field['price'], array('decimals' =>  $num_decimals)) : wc_price($field['price'], array('decimals' => $num_decimals));
                        if (round($field['price'], $num_decimals) == 0) {
                            $price = '';
                        }
                        if (isset($field['is_upload'])) {
                            if (strpos($field['val'], 'http') !== false) {
                                $file_url = $field['val'];
                            } else {
                                $file_url = SPBWC_Storelly_IO::spbwc_convert_path_to_url(SPBWC_PB_UPLOAD_DIR . '/' . $field['val']);
                            }
                            $field['value_name'] = '<a href="' . $file_url . '">' . $field['value_name'] . '</a>';
                        }
                        $post_fix = '';
                        if (isset($field['ind_qty'])) {
                            $post_fix = '<small>' . esc_html__('( cart fee )', 'storelly-product-builder-for-woocommerce') . '</small>';
                        }
                        if (isset($field['fixed_amount'])) {
                            $post_fix = '<small>' . esc_html__('( for all items )', 'storelly-product-builder-for-woocommerce') . '</small>';
                        }
                        $item_data[] = array(
                            'name'      => $field['name'],
                            'display'   => $field['value_name'] . '&nbsp;&nbsp;' . $price . $post_fix,
                            'hidden'    => false
                        );
                    }
                }
                // Free design tools — surface the buyer-added text/image fee so the
                // cart/checkout breakdown matches the canvas summary and the order.
                if (isset($cart_item['pcpb_meta']['option_price']['user_layers_fee']) && floatval($cart_item['pcpb_meta']['option_price']['user_layers_fee']) > 0) {
                    $spbwc_ul_text  = isset($cart_item['pcpb_meta']['option_price']['user_layers_text']) ? (int) $cart_item['pcpb_meta']['option_price']['user_layers_text'] : 0;
                    $spbwc_ul_image = isset($cart_item['pcpb_meta']['option_price']['user_layers_image']) ? (int) $cart_item['pcpb_meta']['option_price']['user_layers_image'] : 0;
                    $spbwc_ul_bits  = array();
                    if ($spbwc_ul_text > 0) {
                        /* translators: %d: number of custom text layers */
                        $spbwc_ul_bits[] = sprintf(_n('%d text', '%d text', $spbwc_ul_text, 'storelly-product-builder-for-woocommerce'), $spbwc_ul_text);
                    }
                    if ($spbwc_ul_image > 0) {
                        /* translators: %d: number of custom image layers */
                        $spbwc_ul_bits[] = sprintf(_n('%d image', '%d images', $spbwc_ul_image, 'storelly-product-builder-for-woocommerce'), $spbwc_ul_image);
                    }
                    $spbwc_ul_label = implode(', ', $spbwc_ul_bits);
                    $item_data[] = array(
                        'name'      => esc_html__('Custom text & images', 'storelly-product-builder-for-woocommerce'),
                        'display'   => ($spbwc_ul_label ? $spbwc_ul_label . '&nbsp;&nbsp;' : '') . '+' . wc_price($cart_item['pcpb_meta']['option_price']['user_layers_fee'], array('decimals' => $num_decimals)),
                        'hidden'    => false
                    );
                }
                if (floatval($cart_item['pcpb_meta']['option_price']['discount_price']) > 0) {
                    $item_data[] = array(
                        'name'      => esc_html__('Quantity Discount', 'storelly-product-builder-for-woocommerce'),
                        'display'   => '-' . wc_price($cart_item['pcpb_meta']['option_price']['discount_price'], array('decimals' => $num_decimals)),
                        'hidden'    => false
                    );
                }
            }
            return $item_data;
        }
        public function order_again_cart_item_data($arr, $item, $order) {
            remove_filter('woocommerce_add_to_cart_validation', array($this, 'add_to_cart_validation'), 1, 6);
            $order_items = $order->get_items();
            foreach ($order_items as $order_item_id => $order_item) {
                if ($item->get_id() == $order_item_id) {
                    if ($option_price = wc_get_order_item_meta($order_item_id, '_pcpb_option_price')) {
                        $arr['pcpb_meta']['option_price'] = $option_price;
                    }
                    if ($field = wc_get_order_item_meta($order_item_id, '_pcpb_field')) {
                        $arr['pcpb_meta']['field'] = $field;
                    }
                    if ($options = wc_get_order_item_meta($order_item_id, '_pcpb_options')) {
                        $arr['pcpb_meta']['options'] = $options;
                    }
                    // Clone the design folder so the re-ordered item is an INDEPENDENT instance.
                    // Without this the new cart item either loses its artwork (folder dropped) or
                    // shares the original order's folder — re-editing would then overwrite the
                    // artwork of the past order. The clone gives it its own copy-on-write folder.
                    if ($folder = wc_get_order_item_meta($order_item_id, '_pcpb_folder')) {
                        $cloned     = SPBWC_Storelly_IO::spbwc_clone_design_folder($folder);
                        $new_folder = $cloned ? $cloned : $folder;
                        $arr['pcpb_meta']['pcpb'] = $new_folder;
                        // Re-point the cart preview thumbnail to the clone's own preview images.
                        $preview_path = SPBWC_PB_CUSTOMER_DIR . '/' . $new_folder . '/preview';
                        $images       = SPBWC_Storelly_IO::spbwc_get_list_images($preview_path, 1);
                        if (count($images)) {
                            ksort($images);
                            if (!isset($arr['pcpb_meta']['option_price']) || !is_array($arr['pcpb_meta']['option_price'])) {
                                $arr['pcpb_meta']['option_price'] = array();
                            }
                            $arr['pcpb_meta']['option_price']['cart_image'] = SPBWC_Storelly_IO::spbwc_convert_path_to_url(end($images));
                        }
                    }
                    if ($original_price = wc_get_order_item_meta($order_item_id, '_pcpb_original_price')) {
                        $arr['pcpb_meta']['original_price'] = $original_price;
                        $arr['pcpb_meta']['price'] = $this->format_price($original_price + $option_price['total_price'] - $option_price['discount_price']);
                    }
                }
            }
            return $arr;
        }
        public function format_price($price) {
            $num_decimals = SPBWC_Storelly_PB_Util::spbwc_get_option_decimals();
            $price = round($price, $num_decimals);
            return $price;
        }
        public function cart_item_thumbnail($image = "", $cart_item = array()) {
            if (isset($cart_item['pcpb_meta']) && $cart_item['pcpb_meta']['option_price']['cart_image'] != '') {
                $size = 'shop_thumbnail';
                $dimensions = wc_get_image_size($size);
                $image = '<img src="' . $cart_item['pcpb_meta']['option_price']['cart_image']
                    . '" width="' . esc_attr($dimensions['width'])
                    . '" height="' . esc_attr($dimensions['height'])
                    . '" class="pcpb-thumbnail woocommerce-placeholder wp-post-image" />';
            }
            $image = apply_filters('storelly_cart_item_thumbnail', $image, $cart_item);
            return $image;
        }
        public function remove_cart_item_quantity($quantity_html, $cart_item, $cart_item_key) {
            if (isset($cart_item['pcpb_meta'])) $quantity_html = '';
            return $quantity_html;
        }
        public function cart_item_name($title = "", $cart_item = array(), $cart_item_key = "") {
            if (!(is_cart() || is_checkout())) {
                return $title;
            }
            if (!isset($cart_item['pcpb_meta'])) {
                return $title;
            }
            if (is_checkout()) {
                $title .= ' &times; <strong>' . $cart_item['quantity'] . '</strong>';
            }
            $product = $cart_item['data'];
            $link = add_query_arg(
                array(
                    'pcpb_cart_item_key' => $cart_item_key,
                    'task'              => 'edit'
                ),
                $product->get_permalink($cart_item)
            );
            $link = wp_nonce_url($link, 'nbo-edit');
            $show_edit_link = apply_filters('storelly_show_edit_option_link_in_cart', true, $cart_item);
            if ($show_edit_link) $title .= '<br /><a class="nbo-edit-option-cart" href="' . $link . '" class="nbo-cart-edit-options">' . esc_html__('Edit options', 'storelly-product-builder-for-woocommerce') . '</a><br />';
            return apply_filters('storelly_cart_item_name', $title, $cart_item, $cart_item_key);
        }
        public function get_cart_item_from_session($cart_item, $values) {
            if (isset($values['pcpb_meta'])) {
                $cart_item['pcpb_meta'] = $values['pcpb_meta'];
                $cart_item = $this->set_product_prices($cart_item);
            }
            return $cart_item;
        }
        public function set_product_prices($cart_item) {
            if (isset($cart_item['pcpb_meta'])) {
                $new_price = (float) $cart_item['pcpb_meta']['price'];
                $needed_change = apply_filters('storelly_need_change_cart_item_price', true, $cart_item);
                if ($needed_change) $cart_item['data']->set_price($new_price);
            }
            return $cart_item;
        }
    }
}
$storelly_frontend_options = STORELLY_FRONTEND_OPTIONS::instance();
$storelly_frontend_options->init();
