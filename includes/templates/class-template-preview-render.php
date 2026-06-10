<?php
/**
 * Live template preview renderer.
 *
 * Renders a bundled Template Library template using the *exact same*
 * storefront view as a real product page — the same
 * templates/single-product/option-builder.php markup, the same
 * static/css/storefront-options.css styling, and the same
 * option-builder.js / storefront-enhance.js / conditional-logic.js runtime.
 * The admin preview dialog loads this in an <iframe>, so any change to the
 * storefront's markup, CSS, or JS is reflected in the preview automatically
 * (single source of truth — no parallel mockup to drift).
 *
 * Templates have no product, so a synthetic context is localized:
 * product_id = 0, type = simple, and a merchant-supplied "sample base price"
 * (defaults to 0, i.e. the preview shows pure option surcharges).
 *
 * Output is gated to logged-in merchants (spbwc_manage_product_builder) plus
 * a nonce, and is served from a front-end URL via template_redirect so
 * WooCommerce/theme context is fully loaded.
 *
 * @package Storelly
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'SPBWC_Template_Preview_Render' ) ) {

	class SPBWC_Template_Preview_Render {

		protected static $instance = null;

		/** Query var that triggers the standalone preview document. */
		const QUERY_VAR = 'spbwc_tpl_preview';

		/** Nonce action for the preview request. */
		const NONCE_ACTION = 'spbwc_tpl_preview_iframe';

		public static function instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		public function init() {
			add_action( 'template_redirect', array( $this, 'maybe_render_preview' ) );
		}

		/**
		 * Base URL the admin dialog points its <iframe> at. The JS appends
		 * &slug=<slug> and optionally &base=<sample price>.
		 *
		 * @return string
		 */
		public static function preview_url() {
			return add_query_arg(
				array(
					self::QUERY_VAR => '1',
					'_spbwcnonce'   => wp_create_nonce( self::NONCE_ACTION ),
				),
				home_url( '/' )
			);
		}

		/**
		 * If this request is a template preview, render the standalone
		 * document and exit. Otherwise return and let WP continue normally.
		 */
		public function maybe_render_preview() {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Presence check only; nonce verified in resolve_preview_request().
			if ( ! isset( $_GET[ self::QUERY_VAR ] ) ) {
				return;
			}
			// Merge GET (nonce + base/product/slug) with POST (the live option
			// draft, posted into the iframe from the edit-option preview). The
			// nonce + cap are still verified inside resolve_preview_request().
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing -- Sanitization + nonce check handled inside resolve_preview_request().
			$request = array_merge( (array) $_GET, (array) $_POST );
			$resolved = $this->resolve_preview_request( $request );
			if ( 200 !== $resolved['status'] ) {
				wp_die(
					esc_html( $resolved['error'] ),
					'',
					array( 'response' => (int) $resolved['status'] )
				);
			}
			$this->render_document(
				$resolved['options'],
				(float) $resolved['base_price'],
				(int) $resolved['product_id'],
				(string) $resolved['product_name']
			);
			exit;
		}

		/**
		 * Validate a preview request and return the resolved payload (or an
		 * error status + message). Split out from maybe_render_preview() so
		 * the gating logic — cap, nonce, slug lookup — is unit-testable
		 * without having to swallow wp_die() or output buffering exits.
		 *
		 * @param array $input Untrusted input map (typically $_GET).
		 * @return array {
		 *     @type int    $status       HTTP status code (200 on success).
		 *     @type string $error        Empty on success, human-readable on failure.
		 *     @type array  $options      Runtime options blob (only on success).
		 *     @type float  $base_price   Effective base price (only on success).
		 *     @type int    $product_id   Resolved product ID, 0 if none (only on success).
		 *     @type string $product_name Resolved product name (only on success).
		 * }
		 */
		public function resolve_preview_request( $input ) {
			$input = is_array( $input ) ? $input : array();

			if ( ! current_user_can( 'spbwc_manage_product_builder' ) ) {
				return array(
					'status' => 403,
					'error'  => __( 'You do not have permission to preview templates.', 'storelly-product-builder-for-woocommerce' ),
				);
			}

			$nonce = isset( $input['_spbwcnonce'] ) ? sanitize_text_field( wp_unslash( $input['_spbwcnonce'] ) ) : '';
			if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
				return array(
					'status' => 403,
					'error'  => __( 'Security check failed.', 'storelly-product-builder-for-woocommerce' ),
				);
			}

			$slug       = isset( $input['slug'] ) ? sanitize_text_field( wp_unslash( $input['slug'] ) ) : '';
			$base_price = isset( $input['base'] ) ? (float) wp_unslash( $input['base'] ) : 0.0; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Cast to float sanitizes.
			if ( $base_price < 0 ) {
				$base_price = 0.0;
			}

			$product_id   = isset( $input['product_id'] ) ? absint( $input['product_id'] ) : 0;
			$product_name = '';
			if ( $product_id > 0 && function_exists( 'wc_get_product' ) ) {
				$product = wc_get_product( $product_id );
				if ( $product && ! is_wp_error( $product ) ) {
					$pp = (float) $product->get_price( 'edit' );
					if ( $pp >= 0 ) {
						$base_price = $pp;
					}
					$product_name = (string) $product->get_name();
				} else {
					// Bad ID → fall back to sample base price; don't error out.
					$product_id = 0;
				}
			}

			// Live-draft mode: the edit-option preview posts the in-progress
			// (unsaved) option JSON so the merchant sees the EXACT storefront
			// render of what they're building — no catalog slug involved. The
			// draft is the same descriptor-shaped blob bundled templates use,
			// so build_runtime_options() flattens it identically. Trust level
			// matches a saved option: cap-gated + nonce-verified above, and the
			// storefront template escapes its own output.
			$draft_raw = isset( $input['draft'] ) ? wp_unslash( $input['draft'] ) : '';
			if ( is_string( $draft_raw ) && '' !== $draft_raw ) {
				$decoded = json_decode( $draft_raw, true );
				if ( ! is_array( $decoded ) ) {
					return array(
						'status' => 400,
						'error'  => __( 'The preview draft could not be read.', 'storelly-product-builder-for-woocommerce' ),
					);
				}
				return array(
					'status'       => 200,
					'error'        => '',
					'options'      => $this->build_runtime_options( $decoded ),
					'base_price'   => $base_price,
					'product_id'   => $product_id,
					'product_name' => $product_name,
				);
			}

			if ( ! class_exists( 'SPBWC_Template_Catalog' ) ) {
				return array(
					'status' => 500,
					'error'  => __( 'Template catalog unavailable.', 'storelly-product-builder-for-woocommerce' ),
				);
			}
			$catalog = SPBWC_Template_Catalog::instance();
			$data    = $catalog->get_template_data( $slug );
			if ( ! is_array( $data ) ) {
				return array(
					'status' => 404,
					'error'  => __( 'Template not found.', 'storelly-product-builder-for-woocommerce' ),
				);
			}

			return array(
				'status'       => 200,
				'error'        => '',
				'options'      => $this->build_runtime_options( $data ),
				'base_price'   => $base_price,
				'product_id'   => $product_id,
				'product_name' => $product_name,
			);
		}

		/**
		 * Turn a bundled template's decoded JSON into the runtime $options
		 * shape the storefront renderer + option-builder.js consume.
		 *
		 * Mirrors SPBWC_Template_Applier::flatten_field_descriptors() — the
		 * bundled JSON stores each general/appearance leaf as a descriptor
		 * { title, type, value }, while the renderer expects the flat value.
		 *
		 * @param array $data Decoded template JSON.
		 * @return array
		 */
		protected function build_runtime_options( $data ) {
			if ( empty( $data['fields'] ) || ! is_array( $data['fields'] ) ) {
				$data['fields'] = array();
			}
			foreach ( $data['fields'] as $idx => $field ) {
				if ( ! is_array( $field ) ) {
					continue;
				}
				if ( isset( $field['general'] ) && is_array( $field['general'] ) ) {
					$field['general'] = $this->collapse_descriptors( $field['general'] );
				}
				if ( isset( $field['appearance'] ) && is_array( $field['appearance'] ) ) {
					$field['appearance'] = $this->collapse_descriptors( $field['appearance'] );
				}
				// The render loop reads general.attributes.options; guarantee it exists.
				if ( ! isset( $field['general']['attributes'] ) || ! is_array( $field['general']['attributes'] ) ) {
					$field['general']['attributes'] = array( 'options' => array() );
				} elseif ( ! isset( $field['general']['attributes']['options'] ) || ! is_array( $field['general']['attributes']['options'] ) ) {
					$field['general']['attributes']['options'] = array();
				}
				$data['fields'][ $idx ] = $field;
			}
			return $data;
		}

		/**
		 * Recursively replace any { value, type, title } descriptor with its 'value'.
		 *
		 * @param mixed $value Value to collapse.
		 * @return mixed
		 */
		protected function collapse_descriptors( $value ) {
			if ( is_array( $value )
				&& array_key_exists( 'value', $value )
				&& array_key_exists( 'type', $value )
				&& array_key_exists( 'title', $value ) ) {
				return $value['value'];
			}
			if ( is_array( $value ) ) {
				foreach ( $value as $key => $sub ) {
					$value[ $key ] = $this->collapse_descriptors( $sub );
				}
			}
			return $value;
		}

		/**
		 * Enqueue the storefront assets (same handles/files as a product page)
		 * and emit a minimal standalone HTML document hosting the rendered
		 * option-builder.php markup. Theme assets load via wp_head()/wp_footer()
		 * so the preview matches the buyer's environment.
		 *
		 * @param array $options    Runtime options blob (with 'fields').
		 * @param float $base_price Sample base product price.
		 */
		protected function render_document( $options, $base_price, $product_id = 0, $product_name = '' ) {
			$appid = 'nbo-app-preview';

			// Enqueue the storefront stack — identical handles to
			// STORELLY_FRONTEND_OPTIONS::spbwc_frontend_enqueue_scripts().
			$this->enqueue_storefront_assets( $options, $appid, $base_price, $product_id, $product_name );

			// Render the SAME storefront template into a buffer first, so its
			// do_action('spbwc_head', …) enqueues land before wp_head() prints.
			ob_start();
			SPBWC_Storelly_PB_Util::spbwc_get_template(
				'single-product/option-builder.php',
				array(
					'product_id'           => 0,
					'appid'                => $appid,
					'options'              => $options,
					'type'                 => 'simple',
					'quantity'             => 1,
					'nbdpb_enable'         => 0,
					'price'                => $base_price,
					'is_sold_individually' => false,
					'variations'           => wp_json_encode( array() ),
					'dimensions'           => wp_json_encode( array() ),
					'form_values'          => array(),
					'cart_item_key'        => '',
					'nbau'                 => '',
				)
			);
			$markup = ob_get_clean();

			nocache_headers();
			header( 'Content-Type: text/html; charset=' . get_bloginfo( 'charset' ) );
			header( 'X-Robots-Tag: noindex, nofollow', true );

			?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<meta name="robots" content="noindex,nofollow" />
	<?php wp_head(); ?>
	<style>
		html, body.spbwc-tpl-preview-doc {
			margin: 0;
			padding: 0;
			background: #f3f4f7;
		}
		body.spbwc-tpl-preview-doc {
			padding: 22px clamp(12px, 4vw, 40px) 64px;
			-webkit-font-smoothing: antialiased;
		}
		.spbwc-tpl-preview-stage {
			max-width: 760px;
			margin: 0 auto;
		}
		.spbwc-preview-qty {
			display: flex;
			align-items: center;
			gap: 10px;
			margin: 18px 0 10px;
			font-size: 14px;
		}
		.spbwc-preview-qty .qty {
			width: 80px;
			padding: 8px 10px;
			border: 1px solid #d1d5db;
			border-radius: 8px;
			font-size: 15px;
		}
		.spbwc-preview-cart .single_add_to_cart_button {
			display: inline-block;
			padding: 12px 22px;
			border: 0;
			border-radius: 10px;
			background: #111827;
			color: #fff;
			font-size: 15px;
			font-weight: 600;
			cursor: pointer;
		}
		/* Hide anything a theme might inject via wp_head/wp_footer that isn't ours. */
		#wpadminbar { display: none !important; }
		html { margin-top: 0 !important; }
	</style>
</head>
<body <?php body_class( 'spbwc-tpl-preview-doc' ); ?>>
	<div class="spbwc-tpl-preview-stage">
		<?php
		/*
		 * Wrap in a WooCommerce-style `form.cart` with a quantity input — the
		 * option-builder core reads the quantity from `input.qty` inside the
		 * cart form (the live total is price × quantity, so without it the
		 * total computes to 0). The Add-to-cart button mirrors the storefront
		 * so the hero/sticky CTA price injection has a target. Both are inert
		 * in preview (type=button, form action="").
		 */
		?>
		<form class="cart spbwc-preview-cart" action="" method="post">
			<?php
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Markup rendered from the trusted storefront template (same as STORELLY_FRONTEND_OPTIONS::show_option_fields()).
			echo $markup;
			?>
			<div class="quantity spbwc-preview-qty">
				<label for="spbwc-preview-qty-input"><?php esc_html_e( 'Quantity', 'storelly-product-builder-for-woocommerce' ); ?></label>
				<input type="number" id="spbwc-preview-qty-input" class="input-text qty text" name="quantity" value="1" min="1" step="1" inputmode="numeric" />
			</div>
			<button type="button" class="single_add_to_cart_button button alt"><?php esc_html_e( 'Add to cart', 'storelly-product-builder-for-woocommerce' ); ?></button>
		</form>
	</div>
	<?php wp_footer(); ?>
</body>
</html>
			<?php
		}

		/**
		 * Register + enqueue the storefront option-builder stack and localize
		 * the bootstrap data exactly like the product page does.
		 *
		 * @param array  $options    Runtime options blob.
		 * @param string $appid      DOM id the Angular app bootstraps on.
		 * @param float  $base_price Sample base product price.
		 */
		protected function enqueue_storefront_assets( $options, $appid, $base_price, $product_id = 0, $product_name = '' ) {
			// Vendor deps (registered here so the preview doesn't depend on the
			// product-page enqueue path having run on this request).
			if ( function_exists( 'WC' ) && ! wp_script_is( 'wc-accounting', 'registered' ) ) {
				wp_register_script(
					'wc-accounting',
					WC()->plugin_url() . '/assets/js/accounting/accounting.min.js',
					array(),
					defined( 'WC_VERSION' ) ? WC_VERSION : '0.4.2',
					true
				);
			}
			if ( ! wp_script_is( 'pc-builderjs', 'registered' ) ) {
				wp_register_script(
					'pc-builderjs',
					SPBWC_PB_ASSETS_URL . 'libs/builderproductag.min.js',
					array( 'jquery' ),
					'1.6.9',
					true
				);
			}

			$nbds_frontend = array(
				'wc_currency_format_num_decimals' => SPBWC_Storelly_PB_Util::spbwc_get_option_decimals(),
				'currency_format_num_decimals'    => SPBWC_Storelly_PB_Util::spbwc_get_option_decimals(),
				'currency_format_symbol'          => html_entity_decode( (string) get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ),
				'currency_format_decimal_sep'     => stripslashes( wc_get_price_decimal_separator() ),
				'currency_format_thousand_sep'    => stripslashes( wc_get_price_thousand_separator() ),
				'currency_format'                 => esc_attr( str_replace( array( '%1$s', '%2$s' ), array( '%s', '%v' ), get_woocommerce_price_format() ) ),
				'nbstorelly_hide_add_cart_until_form_filled' => 'no',
			);

			wp_register_script( 'spbwc-option-builder', SPBWC_PB_JS_URL . 'option-builder.js', array( 'pc-builderjs', 'wc-accounting', 'spbwc-dialog' ), '1.0.0', true );
			wp_localize_script(
				'spbwc-option-builder',
				'spbwc_option_builder_variable',
				array(
					'ajaxUrl'              => admin_url( 'admin-ajax.php' ),
					'appid'                => $appid,
					'nbds_frontend'        => $nbds_frontend,
					'options'              => $options,
					'product_id'           => 0,
					'price'                => $base_price,
					'type'                 => 'simple',
					'quantity'             => 1,
					'variations'           => wp_json_encode( array() ),
					'form_values'          => array(),
					'is_sold_individually' => false,
					'file_too_big'         => __( 'Sorry, file is too big, max size: ', 'storelly-product-builder-for-woocommerce' ),
					'file_too_small'       => __( 'Sorry, file is too small, min size: ', 'storelly-product-builder-for-woocommerce' ),
					'file_type'            => __( 'Sorry, this file type is not permitted for security reasons. Only accept: ', 'storelly-product-builder-for-woocommerce' ),
					'nbo_validation_i18n'  => array(
						/* translators: %1$s: product option field label */
						'field_required'       => __( '%1$s: This field is required.', 'storelly-product-builder-for-woocommerce' ),
						/* translators: 1: product option field label, 2: minimum character count */
						'field_min_length'     => __( '%1$s: Enter at least %2$s characters.', 'storelly-product-builder-for-woocommerce' ),
						/* translators: 1: product option field label, 2: maximum character count */
						'field_max_length'     => __( '%1$s: No more than %2$s characters.', 'storelly-product-builder-for-woocommerce' ),
						/* translators: 1: product option field label, 2: rejected choice label */
						'option_unavailable'   => __( '%1$s: The choice "%2$s" is not available for the current product options.', 'storelly-product-builder-for-woocommerce' ),
						'quantity_invalid'     => __( 'Enter a valid quantity (at least 1).', 'storelly-product-builder-for-woocommerce' ),
						'form_invalid_generic' => __( 'Please check invalid fields, quantity, or choose a different combination.', 'storelly-product-builder-for-woocommerce' ),
					),
				)
			);
			wp_enqueue_script( 'spbwc-option-builder' );

			wp_register_script( 'spbwc-conditional-logic', SPBWC_PB_JS_URL . 'conditional-logic.js', array( 'spbwc-option-builder' ), SPBWC_PB_VERSION, true );
			wp_enqueue_script( 'spbwc-conditional-logic' );

			wp_register_script( 'spbwc-storefront-enhance', SPBWC_PB_JS_URL . 'storefront-enhance.js', array( 'spbwc-option-builder' ), SPBWC_PB_VERSION, true );
			wp_enqueue_script( 'spbwc-storefront-enhance' );

			// Iframe→admin-dialog bridge — posts body height + live total to
			// the parent so the dialog auto-grows and the subtitle reflects
			// the running estimate. Only active inside the preview document.
			$bridge_path = SPBWC_PB_PLUGIN_DIR . 'static/js/template-preview-bridge.js';
			$bridge_ver  = file_exists( $bridge_path ) ? filemtime( $bridge_path ) : SPBWC_PB_VERSION;
			wp_register_script( 'spbwc-tpl-preview-bridge', SPBWC_PB_JS_URL . 'template-preview-bridge.js', array( 'spbwc-storefront-enhance' ), $bridge_ver, true );
			// Forward the preview context (product name) so the admin dialog can
			// surface it in the subtitle. product_id is intentionally not used
			// inside the iframe — the buyer core reads localized product_id and
			// could try to query the product, which would skew the preview; we
			// only need the real product's *price* (already folded into
			// $base_price) and its display name (posted via the bridge).
			wp_localize_script(
				'spbwc-tpl-preview-bridge',
				'spbwc_tpl_preview_context',
				array(
					'product_id'   => (int) $product_id,
					'product_name' => (string) $product_name,
				)
			);
			wp_enqueue_script( 'spbwc-tpl-preview-bridge' );

			if ( ! wp_style_is( 'spbwc-tokens', 'registered' ) ) {
				wp_register_style( 'spbwc-tokens', SPBWC_PB_CSS_URL . '_tokens.css', array(), SPBWC_PB_VERSION );
			}
			wp_register_style( 'spbwc-storefront-options', SPBWC_PB_CSS_URL . 'storefront-options.css', array( 'spbwc-tokens' ), SPBWC_PB_VERSION );
			wp_enqueue_style( 'spbwc-storefront-options' );
		}
	}
}
