<?php
if (!defined('ABSPATH')) exit;

if (!class_exists('STORELLY_HTTP')) {

    class STORELLY_HTTP {
        public static $api_url = SPBWC_API_URL . '/api/v1';

        public function __construct() {
        }

        public static function spbwc_get_basic_auth() {
            $api_settings = get_option('storelly_connect_api_keys');

            $unauth_token = isset($storelly_account['unauth_token']) ? $storelly_account['unauth_token'] : '';

            return array(
                'X-STORLY: ' . $unauth_token,
            );
        }

        public static function spbwc_get_header_unauth_token() {
            $storelly_account = get_option('storelly_w2p_account');

            $unauth_token = isset($storelly_account['unauth_token']) ? $storelly_account['unauth_token'] : '';

            return array(
                'X-Storelly-Unauth-Token' => $unauth_token
            );
        }

        public static function spbwc_response($response, $format = true) {
            $body = wp_remote_retrieve_body($response);

            return json_decode($body, $format);
        }

        public static function spbwc_fetch_data($url) {
            $headers  = self::spbwc_get_basic_auth();

            $response =  wp_remote_get($url, array(
                'headers'    => $headers,
            ));

            return self::spbwc_response($response);
        }
        public static function spbwc_post_data($url, $object) {
            $headers  = self::spbwc_get_basic_auth();

            $response =  wp_remote_post($url, array(
                'headers'    => $headers,
                'body'  => $object,
                'timeout' => 60,
            ));

            return self::spbwc_response($response);
        }
        public static function spbwc_post_data_without_auth($url, $object) {
            $response =  wp_remote_post($url, array(
                'body'  => $object,
                'timeout' => 60,
            ));

            return self::spbwc_response($response);
        }
        public static function spbwc_fetch_data_with_auth($url, $auth) {

            $response =  wp_remote_get($url, array(
                'headers'    => $auth,
            ));

            return self::spbwc_response($response);
        }
    }
}
