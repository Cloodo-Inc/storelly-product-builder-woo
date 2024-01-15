<?php
if (!defined('ABSPATH')) exit;

if (!class_exists('STORELLY_HTTP')) {

    class STORELLY_HTTP {
        public static $api_url = STORELLY_API_URL . '/api/v1';

        public function __construct() {
        }

        public static function get_basic_auth() {
            $api_settings = get_option('storelly_connect_api_keys');

            $unauth_token = isset($printcart_account['unauth_token']) ? $printcart_account['unauth_token'] : '';

            return array(
                'X-STORLY: ' . $unauth_token,
            );
        }

        public static function get_header_unauth_token() {
            $printcart_account = get_option('printcart_w2p_account');

            $unauth_token = isset($printcart_account['unauth_token']) ? $printcart_account['unauth_token'] : '';

            return array(
                'X-PrintCart-Unauth-Token' => $unauth_token
            );
        }

        public static function response($response, $format = true) {
            $body = wp_remote_retrieve_body($response);

            return json_decode($body, $format);
        }

        public static function fetchData($url) {
            $headers  = self::get_basic_auth();

            $response =  wp_remote_get($url, array(
                'headers'    => $headers,
            ));

            return self::response($response);
        }
        public static function postData($url, $object) {
            $headers  = self::get_basic_auth();

            $response =  wp_remote_post($url, array(
                'headers'    => $headers,
                'body'  => $object,
                'timeout' => 60,
            ));

            return self::response($response);
        }
        public static function postDataWithoutAuth($url, $object) {
            $response =  wp_remote_post($url, array(
                'body'  => $object,
                'timeout' => 60,
            ));

            return self::response($response);
        }
        public static function fetchDataWithAuth($url, $auth) {

            $response =  wp_remote_get($url, array(
                'headers'    => $auth,
            ));

            return self::response($response);
        }
    }
}
