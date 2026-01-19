<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'SPBWC_Storelly_PB_Admin_Options' ) ) {
	class SPBWC_Storelly_PB_Admin_Options {
		protected static $instance;
		public function __construct() {
			add_action( 'admin_menu', array( $this, 'spbwc_admin_menu' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'spbwc_admin_enqueue_scripts' ) );
			add_action( 'init', array( $this, 'spbwc_ajax' ) );
			add_action( 'init', array( $this, 'spbwc_process_export' ) );
			add_action( 'wp_ajax_spbwc_save_options_order', array( $this, 'spbwc_save_options_order' ) );
			add_action( 'wp_ajax_spbwc_json_search_products', array( $this, 'spbwc_json_search_products' ) );
		}
		public static function spbwc_instance() {
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}
			return self::$instance;
		}
		public function spbwc_admin_menu() {
			$page_hook_suffix = add_menu_page(
				__( 'Product Builder', 'storelly-product-builder-for-woocommerce' ),
				__( 'Product Builder', 'storelly-product-builder-for-woocommerce' ),
				'manage_woocommerce',
				'spbwc-product-builder',
				array( $this, 'spbwc_options_page' ),
				SPBWC_PB_PLUGIN_URL . 'assets/images/menu-icon.svg',
				50
			);
			add_submenu_page(
				'spbwc-product-builder',
				__( 'Printable Options', 'storelly-product-builder-for-woocommerce' ),
				__( 'Printable Options', 'storelly-product-builder-for-woocommerce' ),
				'manage_woocommerce',
				'spbwc-product-builder',
				array( $this, 'spbwc_options_page' )
			);
			add_submenu_page(
				'spbwc-product-builder',
				__( 'Fonts', 'storelly-product-builder-for-woocommerce' ),
				__( 'Fonts', 'storelly-product-builder-for-woocommerce' ),
				'manage_woocommerce',
				'spbwc-manager-fonts',
				array( $this, 'spbwc_manager_fonts' )
			);
			add_submenu_page(
				'spbwc-product-builder',
				__( 'Settings', 'storelly-product-builder-for-woocommerce' ),
				__( 'Settings', 'storelly-product-builder-for-woocommerce' ),
				'manage_woocommerce',
				'spbwc-settings',
				array( $this, 'spbwc_settings' )
			);
		}
		public function spbwc_admin_enqueue_scripts( $hook ) {
			$screen = get_current_screen();
			if ( strpos( $hook, 'spbwc-product-builder' ) !== false || strpos( $hook, 'spbwc-manager-fonts' ) !== false || strpos( $hook, 'spbwc-settings' ) !== false ) {
				wp_enqueue_media();
				// Load select2 from CDN if local file doesn't exist.
				$select2_css_path = SPBWC_PB_PLUGIN_DIR . 'assets/css/select2.min.css';
				$select2_js_path = SPBWC_PB_PLUGIN_DIR . 'assets/js/select2.min.js';
				if ( file_exists( $select2_css_path ) ) {
					wp_enqueue_style( 'spbwc-select2', SPBWC_PB_PLUGIN_URL . 'assets/css/select2.min.css', array(), SPBWC_PB_VERSION );
				} else {
					wp_enqueue_style( 'spbwc-select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', array(), '4.1.0' );
				}
				$admin_css_path = SPBWC_PB_PLUGIN_DIR . 'assets/css/admin-options.css';
				if ( file_exists( $admin_css_path ) ) {
					wp_enqueue_style( 'spbwc-admin', SPBWC_PB_PLUGIN_URL . 'assets/css/admin-options.css', array(), SPBWC_PB_VERSION );
				}
				if ( file_exists( $select2_js_path ) ) {
					wp_enqueue_script( 'spbwc-select2', SPBWC_PB_PLUGIN_URL . 'assets/js/select2.min.js', array( 'jquery' ), SPBWC_PB_VERSION, true );
				} else {
					wp_enqueue_script( 'spbwc-select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', array( 'jquery' ), '4.1.0', true );
				}
				$admin_js_path = SPBWC_PB_PLUGIN_DIR . 'assets/js/admin-options.js';
				if ( file_exists( $admin_js_path ) ) {
					wp_enqueue_script( 'spbwc-admin', SPBWC_PB_PLUGIN_URL . 'assets/js/admin-options.js', array( 'jquery' ), SPBWC_PB_VERSION, true );
					wp_localize_script(
						'spbwc-admin',
						'spbwc_params',
						array(
							'ajax_url'  => admin_url( 'admin-ajax.php' ),
							'nonce'     => wp_create_nonce( 'spbwc-security-nonce' ),
						)
					);
				}
			}
			if ( strpos( $hook, 'spbwc-product-builder' ) !== false ) {
				wp_enqueue_style( 'wp-color-picker' );
				wp_enqueue_script( 'wp-color-picker' );
				wp_enqueue_style( 'spbwc-options-style', SPBWC_PB_PLUGIN_URL . 'assets/css/options.css', array(), SPBWC_PB_VERSION );
				wp_enqueue_script( 'spbwc-options-script', SPBWC_PB_PLUGIN_URL . 'assets/js/options.js', array( 'jquery', 'jquery-ui-sortable', 'wp-color-picker' ), SPBWC_PB_VERSION, true );
				$spbwc_options_params = array(
					'ajax_url'                  => admin_url( 'admin-ajax.php' ),
					'nonce'                     => wp_create_nonce( 'spbwc-options-nonce' ),
					'text_confirm_delete'       => __( 'Are you sure you want to delete this item?', 'storelly-product-builder-for-woocommerce' ),
					'text_confirm_delete_bulk'  => __( 'Are you sure you want to delete these items?', 'storelly-product-builder-for-woocommerce' ),
				);
				wp_localize_script( 'spbwc-options-script', 'spbwc_options_params', $spbwc_options_params );
			}
			if ( strpos( $hook, 'spbwc-manager-fonts' ) !== false ) {
				// Enqueue canvas library for font management interface.
				wp_enqueue_script( 'spbwc-canvas-lib', SPBWC_PB_PLUGIN_URL . 'assets/libs/fabric.2.6.0.min.js', array(), '2.6.0', false );
				// Enqueue FontFaceObserver.
				$fontfaceobserver_path = SPBWC_PB_PLUGIN_DIR . 'assets/libs/fontfaceobserver.js';
				if ( file_exists( $fontfaceobserver_path ) ) {
					wp_enqueue_script( 'fontfaceobserver', SPBWC_PB_PLUGIN_URL . 'assets/libs/fontfaceobserver.js', array(), SPBWC_PB_VERSION, false );
				} else {
					wp_enqueue_script( 'fontfaceobserver', 'https://cdn.jsdelivr.net/npm/fontfaceobserver@2.1.0/fontfaceobserver.standalone.js', array(), '2.1.0', false );
				}
				// Enqueue SweetAlert2.
				$sweetalert_path = SPBWC_PB_PLUGIN_DIR . 'assets/libs/sweetalert.min.js';
				if ( file_exists( $sweetalert_path ) ) {
					wp_enqueue_script( 'sweetalert', SPBWC_PB_PLUGIN_URL . 'assets/libs/sweetalert.min.js', array(), SPBWC_PB_VERSION, false );
				} else {
					wp_enqueue_script( 'sweetalert', 'https://cdn.jsdelivr.net/npm/sweetalert2@11', array(), '11', false );
				}
				wp_enqueue_style( 'spbwc-manager-fonts-style', SPBWC_PB_PLUGIN_URL . 'assets/css/manager-fonts.css', array(), SPBWC_PB_VERSION );
				wp_enqueue_script( 'spbwc-manager-fonts-script', SPBWC_PB_PLUGIN_URL . 'assets/js/manager-fonts.js', array( 'jquery', 'spbwc-canvas-lib', 'fontfaceobserver', 'sweetalert' ), SPBWC_PB_VERSION, true );
				
				// Load fonts data.
				$fonts_json_path = SPBWC_PB_PLUGIN_DIR . 'data/fonts.json';
				$fonts_data = array();
				if ( file_exists( $fonts_json_path ) ) {
					$fonts_json = file_get_contents( $fonts_json_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local JSON file.
					$fonts_data = json_decode( $fonts_json, true );
				}
				if ( empty( $fonts_data ) || ! is_array( $fonts_data ) ) {
					$fonts_data = array( 'items' => array() );
				}
				
				// Load selected fonts.
				$selected_fonts = get_option( 'spbwc_fonts', array() );
				$selected_fonts_array = array();
				if ( is_array( $selected_fonts ) ) {
					foreach ( $selected_fonts as $font_name ) {
						$selected_fonts_array[] = array( 'name' => $font_name );
					}
				}
				
				// Load subsets data.
				$subsets_json_path = SPBWC_PB_PLUGIN_DIR . 'data/google-fonts-ttf.json';
				$f_subsets = array();
				if ( file_exists( $subsets_json_path ) ) {
					$subsets_json = file_get_contents( $subsets_json_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local JSON file.
					$f_subsets = json_decode( $subsets_json, true );
				}
				if ( ! is_array( $f_subsets ) ) {
					$f_subsets = array();
				}
				
				// Localize script with required variables.
				wp_localize_script(
					'spbwc-manager-fonts-script',
					'storelly_manager_fonts_variable',
					array(
						'selected_fonts' => $selected_fonts_array,
						'ggFonts'        => array( 'items' => $fonts_data ),
						'fSubsets'       => $f_subsets,
					)
				);
				wp_localize_script(
					'spbwc-manager-fonts-script',
					'storelly_pb_fonts',
					array(
						'url'      => admin_url( 'admin-ajax.php' ),
						'nonce'    => wp_create_nonce( 'spbwc-security-nonce' ),
						'complete' => __( 'Success!', 'storelly-product-builder-for-woocommerce' ),
					)
				);
			}
			if ( strpos( $hook, 'spbwc-settings' ) !== false ) {
				wp_enqueue_style( 'spbwc-admin-connect-style', SPBWC_PB_PLUGIN_URL . 'assets/css/admin-connect.css', array(), SPBWC_PB_VERSION );
				wp_enqueue_script( 'spbwc-admin-connect-script', SPBWC_PB_PLUGIN_URL . 'assets/js/admin-connect.js', array( 'jquery' ), SPBWC_PB_VERSION, true );
				wp_localize_script(
					'spbwc-admin-connect-script',
					'spbwc_connect_params',
					array(
						'ajax_url' => admin_url( 'admin-ajax.php' ),
						'nonce'    => wp_create_nonce( 'spbwc_connect_action' ),
						'connecting_text' => esc_html__( 'Connecting...', 'storelly-product-builder-for-woocommerce' ),
						'disconnecting_text' => esc_html__( 'Disconnecting...', 'storelly-product-builder-for-woocommerce' ),
						'saving_text' => esc_html__( 'Saving...', 'storelly-product-builder-for-woocommerce' ),
					)
				);
			}
		}
		public function spbwc_ajax() {
			$ajax_events = array(
				'spbwc_download_option_image'         => true,
				'spbwc_get_media_full_size_url'       => true,
				'spbwc_add_google_font'         => true,
				'spbwc_download_order_designs'  => true,
				'spbwc_auto_connect'            => false,
				'spbwc_manual_connect'          => false,
				'spbwc_disconnect'              => false,
				'spbwc_save_general_settings'   => false,
			);
			foreach ( $ajax_events as $ajax_event => $nopriv ) {
				add_action( 'wp_ajax_' . $ajax_event, array( $this, $ajax_event ) );
				if ( $nopriv ) {
					add_action( 'wp_ajax_nopriv_' . $ajax_event, array( $this, $ajax_event ) );
				}
			}
		}
		public function spbwc_auto_connect() {
			check_ajax_referer( 'spbwc_connect_action', 'nonce' );
			if ( ! current_user_can( 'manage_woocommerce' ) ) {
				wp_send_json_error( array( 'message' => esc_html__( 'You do not have permission to perform this action.', 'storelly-product-builder-for-woocommerce' ) ) );
			}
			$result = SPBWC_ProductBuilder_API::spbwc_generate_key();
			if ( is_wp_error( $result ) ) {
				wp_send_json_error( array( 'message' => $result->get_error_message() ) );
			}
			if ( $result ) {
				wp_send_json_success( array( 'message' => esc_html__( 'Connected successfully!', 'storelly-product-builder-for-woocommerce' ) ) );
			} else {
				wp_send_json_error( array( 'message' => esc_html__( 'Could not connect. Please try the manual method.', 'storelly-product-builder-for-woocommerce' ) ) );
			}
		}
		public function spbwc_manual_connect() {
			check_ajax_referer( 'spbwc_connect_action', 'nonce' );
			if ( ! current_user_can( 'manage_woocommerce' ) ) {
				wp_send_json_error( array( 'message' => esc_html__( 'You do not have permission to perform this action.', 'storelly-product-builder-for-woocommerce' ) ) );
			}
			if ( isset( $_POST['token'] ) ) {
				$token = sanitize_text_field( wp_unslash( $_POST['token'] ) );
				update_option( 'spbwc_token', $token );
				wp_send_json_success( array( 'message' => esc_html__( 'Token saved and connected successfully!', 'storelly-product-builder-for-woocommerce' ) ) );
			} else {
				wp_send_json_error( array( 'message' => esc_html__( 'Token is missing.', 'storelly-product-builder-for-woocommerce' ) ) );
			}
		}
		public function spbwc_disconnect() {
			check_ajax_referer( 'spbwc_connect_action', 'nonce' );
			if ( ! current_user_can( 'manage_woocommerce' ) ) {
				wp_send_json_error( array( 'message' => esc_html__( 'You do not have permission to perform this action.', 'storelly-product-builder-for-woocommerce' ) ) );
			}
			delete_option( 'spbwc_license_key' );
			delete_option( 'spbwc_token' );
			wp_send_json_success( array( 'message' => esc_html__( 'Disconnected successfully.', 'storelly-product-builder-for-woocommerce' ) ) );
		}
		public function spbwc_save_general_settings() {
			check_ajax_referer( 'spbwc_connect_action', 'nonce' );
			if ( ! current_user_can( 'manage_woocommerce' ) ) {
				wp_send_json_error( array( 'message' => esc_html__( 'You do not have permission to perform this action.', 'storelly-product-builder-for-woocommerce' ) ) );
			}
			if ( isset( $_POST['disable_auto_connect'] ) ) {
				$value = 'yes' === sanitize_text_field( wp_unslash( $_POST['disable_auto_connect'] ) ) ? 'yes' : 'no';
				update_option( 'spbwc_disable_auto_connect', $value );
			}
			wp_send_json_success( array( 'message' => esc_html__( 'Settings saved successfully.', 'storelly-product-builder-for-woocommerce' ) ) );
		}
		public function spbwc_options_page() {
			// Check user capabilities.
			if ( ! current_user_can( 'manage_woocommerce' ) ) {
				wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'storelly-product-builder-for-woocommerce' ) );
			}
			include_once SPBWC_PB_PLUGIN_DIR . 'includes/options/fields-list-table.php';
			$options_list_table = new SPBWC_Storelly_Options_List_Table();
			$options_list_table->prepare_items();
			$message = '';
			if ( 'delete' === $options_list_table->current_action() ) {
				$message = '<div class="updated below-h2" id="message"><p>' . sprintf( __( 'Items deleted: %d', 'storelly-product-builder-for-woocommerce' ), count( $_REQUEST['id'] ) ) . '</p></div>';
			}
			if ( isset( $_GET['message'] ) && 'created' === $_GET['message'] ) {
				$message = '<div class="updated below-h2" id="message"><p>' . __( 'Option created', 'storelly-product-builder-for-woocommerce' ) . '</p></div>';
			}
			if ( isset( $_GET['message'] ) && 'updated' === $_GET['message'] ) {
				$message = '<div class="updated below-h2" id="message"><p>' . __( 'Option updated', 'storelly-product-builder-for-woocommerce' ) . '</p></div>';
			}
			if ( isset( $_REQUEST['action'] ) && 'edit' === $_REQUEST['action'] ) {
				// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variable.
				$options = $this->spbwc_get_option_data( isset( $_REQUEST['id'] ) ? absint( $_REQUEST['id'] ) : 0 );
				include_once SPBWC_PB_PLUGIN_DIR . 'views/options/edit-option.php';
			} else {
				// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variable.
				$spbwc_options = $options_list_table;
				include_once SPBWC_PB_PLUGIN_DIR . 'views/options/options-list-table.php';
			}
		}
		/**
		 * Get option data for edit page.
		 *
		 * @param int $id Option ID.
		 * @return array Option data.
		 */
		private function spbwc_get_option_data( $id ) {
			if ( ! $id ) {
				return array(
					'id'         => 0,
					'title'      => '',
					'product_ids' => array(),
					'published'  => 0,
					'fields'     => '',
				);
			}
			$option = $this->spbwc_get_option( $id );
			if ( ! $option ) {
				return array(
					'id'         => 0,
					'title'      => '',
					'product_ids' => array(),
					'published'  => 0,
					'fields'     => '',
				);
			}
			$option['product_ids'] = maybe_unserialize( $option['product_ids'] );
			if ( ! is_array( $option['product_ids'] ) ) {
				$option['product_ids'] = array();
			}
			return $option;
		}
		public function spbwc_settings() {
			include_once SPBWC_PB_PLUGIN_DIR . 'views/menu-settings.php';
		}
		public function spbwc_manager_fonts() {
			global $wpdb;
			$message = '';
			if ( isset( $_GET['action'] ) && 'delete_font' === $_GET['action'] ) {
				// phpcs:disable WordPress.Security.NonceVerification.Recommended
				if ( isset( $_GET['id'] ) && isset( $_GET['spbwc_font_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['spbwc_font_nonce'] ) ), 'spbwc_font_action' ) ) {
					$id    = absint( $_GET['id'] );
					$table = $wpdb->prefix . 'spbwc_fonts';
					$wpdb->delete( $table, array( 'id' => $id ) );
					$message = '<div class="updated below-h2" id="message"><p>' . __( 'Font deleted', 'storelly-product-builder-for-woocommerce' ) . '</p></div>';
				}
				// phpcs:enable
			}
			if ( isset( $_GET['action'] ) && 'delete_cat' === $_GET['action'] ) {
				// phpcs:disable WordPress.Security.NonceVerification.Recommended
				if ( isset( $_GET['id'] ) && isset( $_GET['spbwc_font_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['spbwc_font_nonce'] ) ), 'spbwc_font_action' ) ) {
					$id    = absint( $_GET['id'] );
					$table = $wpdb->prefix . 'spbwc_fonts_cat';
					$wpdb->delete( $table, array( 'id' => $id ) );
					$message = '<div class="updated below-h2" id="message"><p>' . __( 'Category deleted', 'storelly-product-builder-for-woocommerce' ) . '</p></div>';
				}
				// phpcs:enable
			}
			if ( isset( $_POST['spbwc_hidden_font'] ) && 'Y' === $_POST['spbwc_hidden_font'] ) {
				if ( ! isset( $_POST['spbwc_update_fonts_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['spbwc_update_fonts_nonce'] ) ), 'spbwc_update_fonts' ) ) {
					wp_die( esc_html__( 'Sorry, you are not allowed to do this action.', 'storelly-product-builder-for-woocommerce' ) );
				}
				$fonts = isset( $_POST['fonts'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['fonts'] ) ) : array();
				update_option( 'spbwc_fonts', $fonts );
				$message = '<div class="updated below-h2" id="message"><p>' . __( 'Updated', 'storelly-product-builder-for-woocommerce' ) . '</p></div>';
			}
			if ( isset( $_POST['spbwc_hidden_font_cat'] ) && 'Y' === $_POST['spbwc_hidden_font_cat'] ) {
				if ( ! isset( $_POST['spbwc_update_fonts_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['spbwc_update_fonts_nonce'] ) ), 'spbwc_update_fonts' ) ) {
					wp_die( esc_html__( 'Sorry, you are not allowed to do this action.', 'storelly-product-builder-for-woocommerce' ) );
				}
				$table = $wpdb->prefix . 'spbwc_fonts_cat';
				$name  = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
				if ( '' !== $name ) {
					$wpdb->insert(
						$table,
						array(
							'name' => $name,
						)
					);
					$message = '<div class="updated below-h2" id="message"><p>' . __( 'Category created', 'storelly-product-builder-for-woocommerce' ) . '</p></div>';
				}
			}
			if ( isset( $_POST['spbwc_hidden_edit_font_cat'] ) && 'Y' === $_POST['spbwc_hidden_edit_font_cat'] ) {
				if ( ! isset( $_POST['spbwc_update_fonts_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['spbwc_update_fonts_nonce'] ) ), 'spbwc_update_fonts' ) ) {
					wp_die( esc_html__( 'Sorry, you are not allowed to do this action.', 'storelly-product-builder-for-woocommerce' ) );
				}
				$table = $wpdb->prefix . 'spbwc_fonts_cat';
				$id    = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
				$name  = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
				if ( '' !== $name && 0 !== $id ) {
					$wpdb->update(
						$table,
						array(
							'name' => $name,
						),
						array( 'id' => $id )
					);
					$message = '<div class="updated below-h2" id="message"><p>' . __( 'Category updated', 'storelly-product-builder-for-woocommerce' ) . '</p></div>';
				}
			}
			// Load subsets data for view.
			$subsets_json_path = SPBWC_PB_PLUGIN_DIR . 'data/google-fonts-ttf.json';
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variable.
			$subsets = array();
			if ( file_exists( $subsets_json_path ) ) {
				$subsets_json = file_get_contents( $subsets_json_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local JSON file.
				$subsets_data = json_decode( $subsets_json, true );
				if ( is_array( $subsets_data ) ) {
					// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variable.
					$subsets = $subsets_data;
				}
			}
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variable.
			$current_subset = isset( $_GET['subset'] ) ? sanitize_text_field( wp_unslash( $_GET['subset'] ) ) : '';
			
			if ( isset( $_GET['action'] ) && 'edit_cat' === $_GET['action'] ) {
				include_once SPBWC_PB_PLUGIN_DIR . 'views/edit-font-category.php';
			} else {
				include_once SPBWC_PB_PLUGIN_DIR . 'views/manager-fonts.php';
			}
		}
		public function spbwc_download_option_image() {
			if ( ! isset( $_POST['spbwc_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['spbwc_nonce'] ) ), 'spbwc-security-nonce' ) ) {
				wp_send_json_error( array( 'mess' => __( 'Nonce is invalid!', 'storelly-product-builder-for-woocommerce' ) ) );
				exit;
			}
			$url = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : '';
			if ( empty( $url ) ) {
				wp_send_json_error( array( 'mess' => __( 'URL is empty!', 'storelly-product-builder-for-woocommerce' ) ) );
				exit;
			}
			$attachment_id = SPBWC_ProductBuilder_Helper::spbwc_get_image_id_by_url( $url );
			if ( $attachment_id ) {
				wp_send_json_success(
					array(
						'mess'          => __( 'Image already exists!', 'storelly-product-builder-for-woocommerce' ),
						'attachment_id' => $attachment_id,
					)
				);
				exit;
			}
			$tmp = download_url( $url );
			if ( is_wp_error( $tmp ) ) {
				wp_send_json_error( array( 'mess' => $tmp->get_error_message() ) );
				exit;
			}
			$file_name = basename( $url );
			$desc      = $file_name;
			$file_type = wp_check_filetype( $file_name );
			if ( empty( $file_type['ext'] ) ) {
				$file_name .= '.jpg';
			}
			$file_data = array(
				'name'     => $file_name,
				'tmp_name' => $tmp,
			);
			$id        = media_handle_sideload( $file_data, 0, $desc );
			if ( is_wp_error( $id ) ) {
				wp_send_json_error( array( 'mess' => $id->get_error_message() ) );
				exit;
			}
			@unlink( $tmp );
			wp_send_json_success(
				array(
					'mess'          => __( 'Image downloaded!', 'storelly-product-builder-for-woocommerce' ),
					'attachment_id' => $id,
				)
			);
			exit;
		}
		public function spbwc_get_media_full_size_url() {
			if ( ! isset( $_POST['spbwc_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['spbwc_nonce'] ) ), 'spbwc-security-nonce' ) ) {
				wp_send_json_error( array( 'mess' => __( 'Nonce is invalid!', 'storelly-product-builder-forwoocommerce' ) ) );
				exit;
			}
			$attachment_id = isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0;
			if ( empty( $attachment_id ) ) {
				wp_send_json_error( array( 'mess' => __( 'Attachment ID is empty!', 'storelly-product-builder-for-woocommerce' ) ) );
				exit;
			}
			$url = wp_get_attachment_image_url( $attachment_id, 'full' );
			if ( ! $url ) {
				wp_send_json_error( array( 'mess' => __( 'Can not get image url!', 'storelly-product-builder-for-woocommerce' ) ) );
				exit;
			}
			wp_send_json_success(
				array(
					'mess' => __( 'Success!', 'storelly-product-builder-for-woocommerce' ),
					'url'  => $url,
				)
			);
			exit;
		}
		public function spbwc_add_google_font() {
			// Check nonce from either POST or AJAX request.
			$nonce = '';
			if ( isset( $_POST['nonce'] ) ) {
				$nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ) );
			} elseif ( isset( $_POST['spbwc_nonce'] ) ) {
				$nonce = sanitize_text_field( wp_unslash( $_POST['spbwc_nonce'] ) );
			}
			if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'spbwc-security-nonce' ) ) {
				wp_send_json_error( array( 'mes' => __( 'Nonce is invalid!', 'storelly-product-builder-for-woocommerce' ) ) );
				exit;
			}
			
			// Handle fonts array from manager-fonts.js.
			if ( isset( $_POST['fonts'] ) ) {
				$fonts_json = sanitize_text_field( wp_unslash( $_POST['fonts'] ) );
				$fonts_array = json_decode( $fonts_json, true );
				if ( is_array( $fonts_array ) ) {
					$font_names = array();
					foreach ( $fonts_array as $font ) {
						if ( isset( $font['name'] ) ) {
							$font_names[] = sanitize_text_field( $font['name'] );
						}
					}
					update_option( 'spbwc_fonts', $font_names );
					wp_send_json_success(
						array(
							'mes' => __( 'Fonts updated successfully!', 'storelly-product-builder-for-woocommerce' ),
						)
					);
					exit;
				}
			}
			
			// Fallback: Handle single font name (backward compatibility).
			$font_name = isset( $_POST['font_name'] ) ? sanitize_text_field( wp_unslash( $_POST['font_name'] ) ) : '';
			if ( empty( $font_name ) ) {
				wp_send_json_error( array( 'mes' => __( 'Font name is empty!', 'storelly-product-builder-for-woocommerce' ) ) );
				exit;
			}
			$fonts = get_option( 'spbwc_fonts' );
			if ( ! is_array( $fonts ) ) {
				$fonts = array();
			}
			if ( in_array( $font_name, $fonts, true ) ) {
				wp_send_json_error( array( 'mes' => __( 'Font already exists!', 'storelly-product-builder-for-woocommerce' ) ) );
				exit;
			}
			$fonts[] = $font_name;
			update_option( 'spbwc_fonts', $fonts );
			wp_send_json_success(
				array(
					'mes' => __( 'Font added!', 'storelly-product-builder-for-woocommerce' ),
				)
			);
			exit;
		}
		public function spbwc_download_order_designs() {
			if ( ! isset( $_POST['spbwc_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['spbwc_nonce'] ) ), 'spbwc-security-nonce' ) ) {
				wp_send_json_error( array( 'mess' => __( 'Nonce is invalid!', 'storelly-product-builder-for-woocommerce' ) ) );
				exit;
			}
			$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
			if ( empty( $order_id ) ) {
				wp_send_json_error( array( 'mess' => __( 'Order ID is empty!', 'storelly-product-builder-for-woocommerce' ) ) );
				exit;
			}
			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				wp_send_json_error( array( 'mess' => __( 'Order not found!', 'storelly-product-builder-for-woocommerce' ) ) );
				exit;
			}
			$designs = array();
			foreach ( $order->get_items() as $item_id => $item ) {
				$design_data = wc_get_order_item_meta( $item_id, '_spbwc_design_data', true );
				if ( $design_data ) {
					$designs[] = $design_data;
				}
			}
			if ( empty( $designs ) ) {
				wp_send_json_error( array( 'mess' => __( 'No designs found in this order!', 'storelly-product-builder-for-woocommerce' ) ) );
				exit;
			}
			$upload_dir = wp_upload_dir();
			$zip_path   = $upload_dir['path'] . '/designs-' . $order_id . '.zip';
			$zip_url    = $upload_dir['url'] . '/designs-' . $order_id . '.zip';
			$zip        = new ZipArchive();
			if ( $zip->open( $zip_path, ZipArchive::CREATE ) !== true ) {
				wp_send_json_error( array( 'mess' => __( 'Can not create zip file!', 'storelly-product-builder-for-woocommerce' ) ) );
				exit;
			}
			foreach ( $designs as $design ) {
				foreach ( $design as $side => $data ) {
					if ( isset( $data['svg'] ) ) {
						$zip->addFromString( $side . '.svg', $data['svg'] );
					}
				}
			}
			$zip->close();
			wp_send_json_success(
				array(
					'mess'    => __( 'Success!', 'storelly-product-builder-for-woocommerce' ),
					'zip_url' => $zip_url,
				)
			);
			exit;
		}
		public function spbwc_process_export() {
			if ( isset( $_POST['export'] ) && isset( $_POST['spbwc_export_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['spbwc_export_nonce'] ) ), 'spbwc_export_action' ) ) {
				$export_options = get_posts(
					array(
						'post_type'      => 'spbwc_option',
						'posts_per_page' => -1,
					)
				);
				$data           = array();
				foreach ( $export_options as $option ) {
					$data[] = array(
						'post_title'   => $option->post_title,
						'post_content' => $option->post_content,
						'post_excerpt' => $option->post_excerpt,
						'meta'         => get_post_meta( $option->ID ),
					);
				}
				$data = wp_json_encode( $data );
				header( 'Content-disposition: attachment; filename=spbwc-options.json' );
				header( 'Content-type: application/json' );
				echo wp_kses_post( $data );
				exit;
			}
		}
		public function spbwc_save_options_order() {
			if ( ! isset( $_POST['spbwc_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['spbwc_nonce'] ) ), 'spbwc-options-nonce' ) ) {
				wp_send_json_error( array( 'mess' => __( 'Nonce is invalid!', 'storelly-product-builder-for-woocommerce' ) ) );
				exit;
			}
			$order = isset( $_POST['order'] ) ? array_map( 'absint', wp_unslash( $_POST['order'] ) ) : array();
			if ( empty( $order ) ) {
				wp_send_json_error( array( 'mess' => __( 'Order is empty!', 'storelly-product-builder-for-woocommerce' ) ) );
				exit;
			}
			foreach ( $order as $i => $id ) {
				wp_update_post(
					array(
						'ID'         => $id,
						'menu_order' => $i,
					)
				);
			}
			wp_send_json_success(
				array(
					'mess' => __( 'Order saved!', 'storelly-product-builder-for-woocommerce' ),
				)
			);
			exit;
		}
		public function spbwc_json_search_products( $x = '', $post_types = array( 'product' ) ) {
			if ( ! isset( $_GET['spbwc_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['spbwc_nonce'] ) ), 'spbwc-security-nonce' ) ) {
				wp_send_json_error( array( 'mess' => __( 'Nonce is invalid!', 'storelly-product-builder-for-woocommerce' ) ) );
				exit;
			}
			ob_start();
			$search_term = isset( $_GET['term'] ) ? sanitize_text_field( wp_unslash( $_GET['term'] ) ) : '';
			if ( empty( $search_term ) ) {
				die();
			}
			$data_store = WC_Data_Store::load( 'product' );
			$ids        = $data_store->search_products( $search_term, '', true, false, 10 );
			if ( ! empty( $ids ) ) {
				$product_objects = array_map( 'wc_get_product', $ids );
				$products        = array();
				foreach ( $product_objects as $product_object ) {
					$products[ $product_object->get_id() ] = rawurldecode( $product_object->get_formatted_name() );
				}
				wp_send_json( $products );
			}
			die();
		}
		/**
		 * Get option by ID.
		 *
		 * @param int $id Option ID.
		 * @return array|false Option data or false if not found.
		 */
		public function spbwc_get_option( $id ) {
			global $wpdb; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Global variable $wpdb.
			$table_name = $wpdb->prefix . 'storelly_product_builder_options';
			
			$cache_key = 'storelly_pb_option_' . $id;
			$cache_group = 'storelly_product_builder';
			$result = wp_cache_get( $cache_key, $cache_group );

			if ( false === $result ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query is cached, table name uses prefix.
				$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table_name} WHERE `id` = %d", absint( $id ) ), 'ARRAY_A' );
				$result = ( ! empty( $rows ) && isset( $rows[0] ) ) ? $rows[0] : 'not_found';
				wp_cache_set( $cache_key, $result, $cache_group );
			}
			
			if ( 'not_found' === $result ) {
				return false;
			}
			
			return $result;
		}
		/**
		 * Get option ID for a product.
		 *
		 * @param int $product_id Product ID.
		 * @return int|false Option ID or false if not found.
		 */
		public function spbwc_get_product_option( $product_id ) {
			global $wpdb; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Global variable $wpdb.
			$product_id = absint( $product_id );
			if ( ! $product_id ) {
				return false;
			}
			
			$cache_key = 'storelly_pb_product_option_' . $product_id;
			$cache_group = 'storelly_product_builder';
			$option_id = wp_cache_get( $cache_key, $cache_group );

			if ( false === $option_id ) {
				$table_name = $wpdb->prefix . 'storelly_product_builder_options';
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query is cached, table name uses prefix.
				$options = $wpdb->get_results( "SELECT id, product_ids, published FROM {$table_name} WHERE published = 1", 'ARRAY_A' );
				
				$option_id = false;
				if ( ! empty( $options ) ) {
					foreach ( $options as $option ) {
						if ( empty( $option['product_ids'] ) ) {
							continue;
						}
						$product_ids = maybe_unserialize( $option['product_ids'] );
						if ( is_array( $product_ids ) && in_array( $product_id, $product_ids, true ) ) {
							$option_id = absint( $option['id'] );
							break;
						}
					}
				}
				
				wp_cache_set( $cache_key, $option_id, $cache_group );
			}
			
			return $option_id ? $option_id : false;
		}
	}
	SPBWC_Storelly_PB_Admin_Options::spbwc_instance();
}
