<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}
if (!class_exists('SPBWC_Storelly_IO')) {
    class SPBWC_Storelly_IO {
        public function __construct() {
        }
        public static function spbwc_delete_folder($path) {
            if (is_dir($path) === true) {
                $files = array_diff(scandir($path), array('.', '..'));
                foreach ($files as $file) {
                    self::spbwc_delete_folder(realpath($path) . '/' . $file);
                }
                return rmdir($path); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Direct filesystem operation needed for recursive directory deletion.
            } else if (is_file($path) === true) {
                return wp_delete_file($path);
            }
            return false;
        }
        public static function spbwc_get_list_images($path, $level = 100) {
            $list       = array();
            $_list      = self::spbwc_get_list_files($path, $level);
            $list       = preg_grep('/\.(jpg|jpeg|png|gif)(?:[\?\#].*)?$/i', $_list);
            return $list;
        }
        public static function spbwc_get_list_files($folder = '', $levels = 100) {
            if (empty($folder)) return false;
            if (!$levels) return false;
            $files = array();
            if ($dir = @opendir($folder)) {
                while (($file = readdir($dir)) !== false) {
                    if (in_array($file, array('.', '..')))
                        continue;
                    if (is_dir($folder . '/' . $file)) {
                        $files2 = self::spbwc_get_list_files($folder . '/' . $file, $levels - 1);
                        if ($files2)
                            $files = array_merge($files, $files2);
                        else
                            $files[] = $folder . '/' . $file . '/';
                    } else {
                        $files[] = $folder . '/' . $file;
                    }
                }
            }
            @closedir($dir);
            return $files;
        }
        public static function spbwc_copy_dir($src, $dst) {
            if (file_exists($dst)) self::spbwc_delete_folder($dst);
            if (is_dir($src)) {
                wp_mkdir_p($dst);
                $files = scandir($src);
                foreach ($files as $file) {
                    if ($file != "." && $file != "..") self::spbwc_copy_dir("$src/$file", "$dst/$file");
                }
            } else if (file_exists($src)) copy($src, $dst);
        }
        public static function spbwc_mkdir($dir) {
            if (!file_exists($dir)) {
                wp_mkdir_p($dir);
            }
        }
        public static function spbwc_convert_url_to_path($url) {
            $upload_dir = wp_upload_dir();
            $base_url   = $upload_dir['baseurl'];
            $basedir    = $upload_dir['basedir'];

            // Check if the URL is within the uploads directory.
            if ( strpos( $url, $base_url ) !== false ) {
                return str_replace( $base_url, $basedir, $url );
            }

            // Fallback for content directory.
            $content_url = content_url();
            $content_dir = WP_CONTENT_DIR; // Still allowed if defined, but prefer functions.
            if ( defined( 'WP_CONTENT_DIR' ) && strpos( $url, $content_url ) !== false ) {
                return str_replace( $content_url, WP_CONTENT_DIR, $url );
            }

            // Final fallback (use with caution).
            $site_url = site_url();
            $site_path = ABSPATH; 
            if ( strpos( $url, $site_url ) !== false ) {
                 // Try to map based on site URL to ABSPATH. This is approximate and may not work for all setups (subdirectories etc).
                 // Better to rely on uploads/content.
                 return str_replace( $site_url, $site_path, $url );
            }

            return $url;
        }

        public static function spbwc_convert_path_to_url($path = '') {
            $path = wp_normalize_path($path);
            $upload_dir = wp_upload_dir();
            $basedir    = wp_normalize_path($upload_dir['basedir']);
            $baseurl    = $upload_dir['baseurl'];

            if ( strpos( $path, $basedir ) !== false ) {
                return str_replace( $basedir, $baseurl, $path );
            }
            
            if ( defined( 'WP_CONTENT_DIR' ) ) {
                $content_dir = wp_normalize_path( WP_CONTENT_DIR );
                $content_url = content_url();
                if ( strpos( $path, $content_dir ) !== false ) {
                    return str_replace( $content_dir, $content_url, $path );
                }
            }

            // Fallback to site URL for ABSPATH
            $abspath = wp_normalize_path( ABSPATH );
            $site_url = site_url();
            if ( strpos( $path, $abspath ) !== false ) {
                return str_replace( $abspath, $site_url, $path );
            }
            
            return esc_url_raw($path);
        }
        public static function spbwc_get_list_files_by_type($path, $type, $level = 100) {
            $list       = array();
            $_list      = self::spbwc_get_list_files($path, $level);
            $list       = preg_grep('/\.(' . $type . ')(?:[\?\#].*)?$/i', $_list);
            return $list;
        }
        public static function spbwc_check_file_type($file_name, $arr_mime) {
            $check      = false;
            $filetype   = explode('.', $file_name);
            $file_exten = $filetype[count($filetype) - 1];
            if (in_array(strtolower($file_exten), $arr_mime)) $check = true;
            return $check;
        }
        /**
         * Get local file contents using WP_Filesystem
         *
         * @param string $path Local file path.
         * @return string|false File contents or false on failure.
         */
        public static function spbwc_get_local_file_contents($path) {
            if ( ! function_exists( 'WP_Filesystem' ) ) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
            }
            global $wp_filesystem;
            if ( ! $wp_filesystem ) {
                WP_Filesystem();
            }
            if ( ! $wp_filesystem ) {
                return false; // Unable to initialize filesystem
            }
            return $wp_filesystem->get_contents( $path );
        }
    }
}
