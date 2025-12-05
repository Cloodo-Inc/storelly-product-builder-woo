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
            $upload_dir     = wp_upload_dir();
            $basedir        = $upload_dir['basedir'];
            $arr            = explode('/', $basedir);
            $upload         = $arr[count($arr) - 1];
            if (is_multisite() && !is_main_site()) $upload = $arr[count($arr) - 3] . '/' . $arr[count($arr) - 2] . '/' . $arr[count($arr) - 1];
            $arr_url = explode('/' . $upload, $url);
            if (isset($arr_url[1])) {
                if (count($arr_url) == 2) {
                    return $basedir . $arr_url[1];
                } else {
                    return $basedir . $arr_url[1] . '/' . $upload . $arr_url[2];
                }
            } else {
                $path = str_replace(
                    site_url(),
                    wp_normalize_path(untrailingslashit(ABSPATH)),
                    wp_normalize_path($url)
                );
                return $path;
            }
        }
        public static function spbwc_convert_path_to_url($path = '') {
            $url = str_replace(
                wp_normalize_path(untrailingslashit(ABSPATH)),
                site_url(),
                wp_normalize_path($path)
            );
            return esc_url_raw($url);
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
    }
}
