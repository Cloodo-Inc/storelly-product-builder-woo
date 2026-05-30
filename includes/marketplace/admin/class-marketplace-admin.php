<?php
/**
 * Marketplace admin controller.
 *
 * Registers the "Designer Marketplace" submenu, routes its tabs, and
 * enqueues the PHP admin assets. Replaces the legacy React SPA that
 * lived under views/launcher/dist/ (removed in PR B step 1/3).
 *
 * @package Storelly
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'SPBWC_Marketplace_Admin' ) ) {

    class SPBWC_Marketplace_Admin {

        const MENU_SLUG  = 'spbwc-marketplace';
        const CAPABILITY = 'spbwc_manage_product_builder';

        protected static $instance = null;
        protected $hook_suffix     = '';

        public static function get_instance() {
            if ( null === self::$instance ) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        public function init() {
            if ( ! function_exists( 'spbwc_marketplace_is_enabled' ) || ! spbwc_marketplace_is_enabled() ) {
                return;
            }
            add_action( 'admin_menu', array( $this, 'register_menu' ), 200 );
            add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        }

        public function register_menu() {
            $parent_slug = defined( 'SPBWC_PB_OVERVIEW_SLUG' ) ? SPBWC_PB_OVERVIEW_SLUG : 'storelly-product-builder-for-woocommerce-overview';

            $this->hook_suffix = add_submenu_page(
                $parent_slug,
                esc_html__( 'B2B Clients', 'storelly-product-builder-for-woocommerce' ),
                esc_html__( 'B2B Clients', 'storelly-product-builder-for-woocommerce' ),
                self::CAPABILITY,
                self::MENU_SLUG,
                array( $this, 'render' )
            );
        }

        public function enqueue_assets( $hook ) {
            if ( empty( $this->hook_suffix ) || $hook !== $this->hook_suffix ) {
                return;
            }
            wp_enqueue_media();
            wp_register_style( 'spbwc-tokens', SPBWC_PB_CSS_URL . '_tokens.css', array(), SPBWC_PB_VERSION );
            // Register shared admin UI in case this loads without the main enqueue hook.
            wp_register_style( 'spbwc-admin-ui', SPBWC_PB_CSS_URL . 'storelly-admin-ui.css', array( 'spbwc-tokens', 'dashicons' ), SPBWC_PB_VERSION );
            wp_enqueue_style(
                'spbwc-marketplace-admin',
                SPBWC_PB_CSS_URL . 'marketplace-admin.css',
                array( 'spbwc-admin-ui' ),
                SPBWC_PB_VERSION
            );
            wp_register_script(
                'spbwc-marketplace-chartjs',
                SPBWC_PB_ASSETS_URL . 'libs/chartjs/chart.umd.js',
                array(),
                '4.4.0',
                true
            );
            wp_enqueue_script(
                'spbwc-marketplace-admin',
                SPBWC_PB_JS_URL . 'marketplace-admin.js',
                array( 'jquery', 'spbwc-marketplace-chartjs' ),
                SPBWC_PB_VERSION,
                true
            );
            wp_localize_script(
                'spbwc-marketplace-admin',
                'spbwcMarketplaceAdmin',
                array(
                    'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                    'nonce'   => wp_create_nonce( 'spbwc_marketplace_admin' ),
                    'i18n'    => array(
                        'confirmDelete'   => __( 'Are you sure you want to delete this item?', 'storelly-product-builder-for-woocommerce' ),
                        'confirmApprove'  => __( 'Approve this withdraw request?', 'storelly-product-builder-for-woocommerce' ),
                        'confirmCancel'   => __( 'Cancel this withdraw request?', 'storelly-product-builder-for-woocommerce' ),
                        'processing'      => __( 'Processing…', 'storelly-product-builder-for-woocommerce' ),
                        'failed'          => __( 'Action failed. Check the browser console for details.', 'storelly-product-builder-for-woocommerce' ),
                    ),
                )
            );
        }

        public function render() {
            if ( ! current_user_can( self::CAPABILITY ) ) {
                wp_die( esc_html__( 'You do not have permission to access this page.', 'storelly-product-builder-for-woocommerce' ) );
            }
            $tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'designers'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $tab = in_array( $tab, array( 'designers', 'designs', 'withdraws', 'report', 'settings' ), true ) ? $tab : 'designers';

            $is_edit = ( 'designers' === $tab && isset( $_GET['action'] ) && 'edit' === $_GET['action'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

            $view_dir = SPBWC_PB_PLUGIN_DIR . 'views/marketplace-admin/';
            echo '<div class="wrap spbwc-marketplace-admin">';
            include $view_dir . 'header.php';

            if ( $is_edit ) {
                include $view_dir . 'designer-edit.php';
            } else {
                $view_file = $view_dir . $tab . '.php';
                if ( file_exists( $view_file ) ) {
                    include $view_file;
                } else {
                    echo '<p>' . esc_html__( 'Marketplace view not found.', 'storelly-product-builder-for-woocommerce' ) . '</p>';
                }
            }
            echo '</div>';
        }

        public function get_tab_url( $tab, $extra = array() ) {
            $args = array_merge( array( 'page' => self::MENU_SLUG, 'tab' => $tab ), $extra );
            return add_query_arg( $args, admin_url( 'admin.php' ) );
        }
    }
}
