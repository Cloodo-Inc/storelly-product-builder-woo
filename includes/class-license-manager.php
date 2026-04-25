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

        /** Transient key for cached package list from the license API. */
        const TRANSIENT_PACKAGES = 'spbwc_license_packages';

        /** Transient key for Overview page stats from the plugin API. */
        const TRANSIENT_OVERVIEW_STATS = 'spbwc_overview_stats';

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
         * Drop license-related transients and object cache so the next load fetches fresh API data.
         * Call only after a successful sync so failed requests keep previous cached data.
         */
        public static function invalidate_license_caches() {
            delete_transient( self::TRANSIENT_PACKAGES );
            delete_transient( self::TRANSIENT_OVERVIEW_STATS );
            wp_cache_delete( 'spbwc_current_license', self::CACHE_GROUP );
        }

        /**
         * Fetch real-time license status from the Storelly Dashboard API.
         * Returns true on success, WP_Error on failure.
         *
         * @return true|WP_Error
         */
        public static function sync_from_api() {
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
            self::invalidate_license_caches();

            return true;
        }

        /**
         * Fetch available packages from the Storelly Dashboard API.
         * Results are cached in a transient (1 hour).
         *
         * @return array|WP_Error
         */
        public static function get_packages() {
            $cached = get_transient( self::TRANSIENT_PACKAGES );
            if ( false !== $cached ) {
                return $cached;
            }

            $url  = SPBWC_API_URL . '/api/v1/license/packages';
            $resp = SPBWC_Storelly_HTTP::spbwc_fetch_data( $url );

            if ( is_wp_error( $resp ) || empty( $resp['success'] ) || ! isset( $resp['packages'] ) ) {
                // Do not use multi-tier marketing fallbacks; show a single temporary Free tier until the API responds.
                return self::temporary_free_packages();
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

            set_transient( self::TRANSIENT_PACKAGES, $packages, HOUR_IN_SECONDS );
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
            $cached = get_transient( self::TRANSIENT_OVERVIEW_STATS );
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

            set_transient( self::TRANSIENT_OVERVIEW_STATS, $stats, 5 * MINUTE_IN_SECONDS );
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
         * Single temporary Free package when the packages API is unreachable (not the old multi-tier demo list).
         *
         * @return array<int, array<string, mixed>>
         */
        public static function temporary_free_packages() {
            $defaults = self::free_license_defaults();

            return array(
                array(
                    'id'                   => 0,
                    'name'                 => $defaults['package_name'],
                    'slug'                 => $defaults['package_slug'],
                    'description'          => __( 'Temporary Free plan while the license server is unavailable. Reconnect to load live plans.', 'storelly-product-builder-for-woocommerce' ),
                    'price'                => 0,
                    'currency'             => 'USD',
                    'billing_cycle'        => '—',
                    'features'             => array_map( 'strval', (array) $defaults['features'] ),
                    'max_products'         => absint( $defaults['max_products'] ),
                    'max_orders'           => absint( $defaults['max_orders'] ),
                    'max_pricing_options'  => absint( $defaults['max_pricing_options'] ),
                    'is_free'              => true,
                ),
            );
        }
    }
}
