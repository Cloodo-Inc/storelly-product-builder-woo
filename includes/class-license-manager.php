<?php
/**
 * SPBWC License Manager
 *
 * Handles license validation, caching, and communication with the Storelly
 * Dashboard license API endpoints.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'SPBWC_License_Manager' ) ) {

    class SPBWC_License_Manager {

        /** Cache TTL for license status (15 minutes). */
        const CACHE_TTL = 900;
        const CACHE_GROUP = 'spbwc_license';
        const OPTION_KEY  = 'spbwc_license_data';

        protected static $instance;

        private function __construct() {}

        public static function instance() {
            if ( is_null( self::$instance ) ) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        // ----------------------------------------------------------------
        // Public API
        // ----------------------------------------------------------------

        /**
         * Return a description of the user's current license package.
         * Falls back to "free" if nothing is stored.
         *
         * @return array{
         *   status: string,
         *   package_name: string,
         *   package_slug: string,
         *   expires_at: string|null,
         *   max_products: int,
         *   max_orders: int,
         *   max_pricing_options: int,
         *   features: array
         * }
         */
        public static function get_current_license() {
            $cached = wp_cache_get( 'spbwc_current_license', self::CACHE_GROUP );
            if ( false !== $cached ) {
                return $cached;
            }

            $stored = get_option( self::OPTION_KEY, array() );

            // Apply defaults (Free tier)
            $license = wp_parse_args( $stored, self::free_license_defaults() );

            wp_cache_set( 'spbwc_current_license', $license, self::CACHE_GROUP, self::CACHE_TTL );
            return $license;
        }

        /**
         * Fetch real-time license status from the Storelly Dashboard API.
         * Returns true on success, WP_Error on failure.
         *
         * @return true|WP_Error
         */
        public static function sync_from_api() {
            $api_keys    = get_option( 'spbwc_connect_api_keys', array() );
            $business_id = isset( $api_keys['business_id'] ) ? absint( $api_keys['business_id'] ) : 0;

            if ( ! $business_id ) {
                return new WP_Error( 'no_business', __( 'No business ID configured. Please complete the Storelly connection.', 'storelly-product-builder-for-woocommerce' ) );
            }

            $url  = SPBWC_API_URL . '/api/v1/license/status';
            $resp = SPBWC_Storelly_HTTP::spbwc_fetch_data( $url );

            if ( is_wp_error( $resp ) ) {
                return $resp;
            }

            if ( empty( $resp['success'] ) ) {
                return new WP_Error( 'api_error', isset( $resp['msg'] ) ? $resp['msg'] : __( 'Unknown API error.', 'storelly-product-builder-for-woocommerce' ) );
            }

            $package = isset( $resp['package'] ) ? $resp['package'] : array();
            $lic     = isset( $resp['license'] ) ? $resp['license'] : null;

            $data = array(
                'status'              => isset( $resp['status'] ) ? sanitize_key( $resp['status'] ) : 'free',
                'package_name'        => isset( $package['name'] ) ? sanitize_text_field( $package['name'] ) : 'Free',
                'package_slug'        => isset( $package['slug'] ) ? sanitize_key( $package['slug'] ) : 'free',
                'expires_at'          => $lic && isset( $lic['expires_at'] ) ? sanitize_text_field( $lic['expires_at'] ) : null,
                'max_products'        => isset( $package['max_products'] ) ? absint( $package['max_products'] ) : 5,
                'max_orders'          => isset( $package['max_orders'] ) ? absint( $package['max_orders'] ) : 50,
                'max_pricing_options' => isset( $package['max_pricing_options'] ) ? absint( $package['max_pricing_options'] ) : 3,
                'features'            => isset( $package['features'] ) && is_array( $package['features'] ) ? array_map( 'sanitize_text_field', $package['features'] ) : array(),
                'synced_at'           => current_time( 'mysql' ),
            );

            update_option( self::OPTION_KEY, $data );
            wp_cache_delete( 'spbwc_current_license', self::CACHE_GROUP );

            return true;
        }

        /**
         * Fetch available packages from the Storelly Dashboard API.
         * Results are cached in a transient (1 hour).
         *
         * @return array|WP_Error
         */
        public static function get_packages() {
            $cached = get_transient( 'spbwc_license_packages' );
            if ( false !== $cached ) {
                return $cached;
            }

            $url  = SPBWC_API_URL . '/api/v1/license/packages';
            $resp = SPBWC_Storelly_HTTP::spbwc_fetch_data( $url );

            if ( is_wp_error( $resp ) || empty( $resp['success'] ) || ! isset( $resp['packages'] ) ) {
                // Return built-in fallback packages if API unavailable
                return self::built_in_packages();
            }

            $packages = array_map( function( $pkg ) {
                return array(
                    'id'                   => absint( $pkg['id'] ?? 0 ),
                    'name'                 => sanitize_text_field( $pkg['name'] ?? '' ),
                    'slug'                 => sanitize_key( $pkg['slug'] ?? '' ),
                    'description'          => wp_kses_post( $pkg['description'] ?? '' ),
                    'price'                => floatval( $pkg['price'] ?? 0 ),
                    'currency'             => sanitize_text_field( $pkg['currency'] ?? 'USD' ),
                    'billing_cycle'        => sanitize_text_field( $pkg['billing_cycle'] ?? 'monthly' ),
                    'features'             => array_map( 'sanitize_text_field', (array) ( $pkg['features'] ?? array() ) ),
                    'max_products'         => absint( $pkg['max_products'] ?? 0 ),
                    'max_orders'           => absint( $pkg['max_orders'] ?? 0 ),
                    'max_pricing_options'  => absint( $pkg['max_pricing_options'] ?? 0 ),
                    'is_free'              => ! empty( $pkg['is_free'] ),
                );
            }, $resp['packages'] );

            set_transient( 'spbwc_license_packages', $packages, HOUR_IN_SECONDS );
            return $packages;
        }

        /**
         * Activate a license key via the Storelly Dashboard API.
         *
         * @param  string $license_key  The key entered by the admin.
         * @return array{success: bool, msg: string}
         */
        public static function activate_key( $license_key ) {
            $license_key = sanitize_text_field( trim( $license_key ) );
            if ( empty( $license_key ) ) {
                return array( 'success' => false, 'msg' => __( 'License key is required.', 'storelly-product-builder-for-woocommerce' ) );
            }

            $api_keys    = get_option( 'spbwc_connect_api_keys', array() );
            $business_id = isset( $api_keys['business_id'] ) ? absint( $api_keys['business_id'] ) : 0;

            $url  = SPBWC_API_URL . '/api/v1/license/activate';
            $body = array(
                'license_key' => $license_key,
                'business_id' => $business_id,
            );

            $resp = SPBWC_Storelly_HTTP::spbwc_post_data( $url, $body );

            if ( is_wp_error( $resp ) ) {
                return array( 'success' => false, 'msg' => $resp->get_error_message() );
            }

            if ( ! empty( $resp['success'] ) ) {
                // Persist activated key and re-sync license data
                $api_keys['license_key'] = $license_key;
                update_option( 'spbwc_connect_api_keys', $api_keys );
                self::sync_from_api();
                delete_transient( 'spbwc_license_packages' );
            }

            return array(
                'success' => ! empty( $resp['success'] ),
                'msg'     => isset( $resp['msg'] ) ? $resp['msg'] : __( 'Unknown response.', 'storelly-product-builder-for-woocommerce' ),
                'package' => isset( $resp['package'] ) ? $resp['package'] : '',
            );
        }

        /**
         * Fetch overview stats from the Storelly Dashboard API for the Overview page.
         *
         * @return array|WP_Error
         */
        public static function get_overview_stats() {
            $cached = get_transient( 'spbwc_overview_stats' );
            if ( false !== $cached ) {
                return $cached;
            }

            $api_keys    = get_option( 'spbwc_connect_api_keys', array() );
            $business_id = isset( $api_keys['business_id'] ) ? absint( $api_keys['business_id'] ) : 0;

            if ( ! $business_id ) {
                return new WP_Error( 'no_business', __( 'No business ID configured.', 'storelly-product-builder-for-woocommerce' ) );
            }

            $url  = SPBWC_API_URL . '/api/v1/plugin/overview';
            $resp = SPBWC_Storelly_HTTP::spbwc_fetch_data( $url );

            if ( is_wp_error( $resp ) || empty( $resp['success'] ) ) {
                return new WP_Error( 'api_error', __( 'Could not retrieve overview stats.', 'storelly-product-builder-for-woocommerce' ) );
            }

            $stats = array(
                'total_products'  => absint( $resp['total_products'] ?? 0 ),
                'total_orders'    => absint( $resp['total_orders'] ?? 0 ),
                'total_quotes'    => absint( $resp['total_quotes'] ?? 0 ),
                'license_status'  => sanitize_key( $resp['license_status'] ?? 'free' ),
                'license_package' => sanitize_text_field( $resp['license_package'] ?? 'Free' ),
                'license_expires' => sanitize_text_field( $resp['license_expires'] ?? '' ),
            );

            set_transient( 'spbwc_overview_stats', $stats, 5 * MINUTE_IN_SECONDS );
            return $stats;
        }

        // ----------------------------------------------------------------
        // Helpers / Defaults
        // ----------------------------------------------------------------

        /**
         * Default "Free" license array used when no license is stored.
         */
        public static function free_license_defaults() {
            return array(
                'status'              => 'free',
                'package_name'        => 'Free',
                'package_slug'        => 'free',
                'expires_at'          => null,
                'max_products'        => 5,
                'max_orders'          => 50,
                'max_pricing_options' => 3,
                'features'            => array(
                    'Up to 5 products',
                    'Up to 3 pricing options',
                    'Basic order management',
                    'Community support',
                ),
                'synced_at'           => null,
            );
        }

        /**
         * Built-in fallback packages shown when the API is unreachable.
         */
        public static function built_in_packages() {
            return array(
                array(
                    'id'                   => 0,
                    'name'                 => 'Free',
                    'slug'                 => 'free',
                    'description'          => 'Get started with basic product builder features at no cost.',
                    'price'                => 0,
                    'currency'             => 'USD',
                    'billing_cycle'        => 'monthly',
                    'features'             => array( 'Up to 5 products', 'Up to 3 pricing options', 'Community support' ),
                    'max_products'         => 5,
                    'max_orders'           => 50,
                    'max_pricing_options'  => 3,
                    'is_free'              => true,
                ),
                array(
                    'id'                   => 0,
                    'name'                 => 'Starter',
                    'slug'                 => 'starter',
                    'description'          => 'Perfect for small businesses.',
                    'price'                => 19,
                    'currency'             => 'USD',
                    'billing_cycle'        => 'monthly',
                    'features'             => array( 'Up to 50 products', 'Up to 30 pricing options', 'Email support', 'Remove branding' ),
                    'max_products'         => 50,
                    'max_orders'           => 500,
                    'max_pricing_options'  => 30,
                    'is_free'              => false,
                ),
                array(
                    'id'                   => 0,
                    'name'                 => 'Professional',
                    'slug'                 => 'professional',
                    'description'          => 'Full-featured for established businesses.',
                    'price'                => 49,
                    'currency'             => 'USD',
                    'billing_cycle'        => 'monthly',
                    'features'             => array( 'Unlimited products', 'Unlimited pricing options', 'Priority support', 'API access', 'PDF export' ),
                    'max_products'         => 0,
                    'max_orders'           => 0,
                    'max_pricing_options'  => 0,
                    'is_free'              => false,
                ),
                array(
                    'id'                   => 0,
                    'name'                 => 'Enterprise',
                    'slug'                 => 'enterprise',
                    'description'          => 'Tailored solutions for large teams.',
                    'price'                => 149,
                    'currency'             => 'USD',
                    'billing_cycle'        => 'monthly',
                    'features'             => array( 'Everything in Professional', 'Dedicated account manager', 'SLA guarantee', 'White-label ready' ),
                    'max_products'         => 0,
                    'max_orders'           => 0,
                    'max_pricing_options'  => 0,
                    'is_free'              => false,
                ),
            );
        }
    }
}
