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
        public static function spbwc_copy_dir($src, $dst, $skip = array()) {
            if (file_exists($dst)) self::spbwc_delete_folder($dst);
            if (is_dir($src)) {
                wp_mkdir_p($dst);
                $files = scandir($src);
                foreach ($files as $file) {
                    if ($file == "." || $file == "..") continue;
                    if (in_array($file, $skip, true)) continue;
                    self::spbwc_copy_dir("$src/$file", "$dst/$file", $skip);
                }
            } else if (file_exists($src)) copy($src, $dst); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_copy -- Direct copy within plugin data dir; WP_Filesystem not required for binary design assets.
        }

        /**
         * Copy-on-write clone of a customer design folder.
         *
         * Every design instance must own its own folder so that re-order, re-edit and
         * saved-design loads never mutate the artwork of an existing order. This deep-copies
         * SPBWC_PB_CUSTOMER_DIR/{src} into a freshly generated folder id and returns that id.
         *
         * Generated sub-folders (customer-pdfs, pdf-templates) are intentionally skipped so the
         * new instance regenerates its own print files instead of inheriting stale ones.
         *
         * @param string $src Source design folder id (basename only).
         * @return string New folder id on success, '' on failure.
         */
        public static function spbwc_clone_design_folder($src) {
            $src = is_string($src) ? trim($src) : '';
            // Reject empty, path-traversal or nested values; only a bare folder id is valid.
            if ('' === $src || $src !== basename($src)) {
                return '';
            }
            $src_path = SPBWC_PB_CUSTOMER_DIR . '/' . $src;
            if (!is_dir($src_path)) {
                return '';
            }
            // Mirror the folder-id convention used when a design is first saved
            // (class-product-builder-frontend.php) so clones are indistinguishable from originals.
            $rand    = wp_rand(1, 100); // phpcs:ignore WordPress.WP.AlternativeFunctions.rand_rand -- wp_rand() is always available in WordPress.
            $new_id  = substr(md5(uniqid('', true)), 0, 5) . $rand . time();
            $dst_path = SPBWC_PB_CUSTOMER_DIR . '/' . $new_id;

            self::spbwc_copy_dir($src_path, $dst_path, array('customer-pdfs', 'pdf-templates', 'download'));

            return is_dir($dst_path) ? $new_id : '';
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
            $content_dir = wp_normalize_path( WP_CONTENT_DIR );
            if ( defined( 'WP_CONTENT_DIR' ) && strpos( $url, $content_url ) !== false ) {
                return str_replace( $content_url, $content_dir, $url );
            }

            // Final fallback (use with caution).
            $site_url = site_url();
            $site_path = wp_normalize_path( ABSPATH ); 
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
