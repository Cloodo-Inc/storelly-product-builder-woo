<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}
if (!class_exists('Printcart_IO')) {
    class Printcart_IO {
        public function __construct() {
        }
        public static function delete_folder($path) {
            if (is_dir($path) === true) {
                $files = array_diff(scandir($path), array('.', '..'));
                foreach ($files as $file) {
                    self::delete_folder(realpath($path) . '/' . $file);
                }
                return rmdir($path);
            } else if (is_file($path) === true) {
                return unlink($path);
            }
            return false;
        }
        public static function get_list_images($path, $level = 100) {
            $list       = array();
            $_list      = self::get_list_files($path, $level);
            $list       = preg_grep('/\.(jpg|jpeg|png|gif)(?:[\?\#].*)?$/i', $_list);
            return $list;
        }
        public static function get_list_files($folder = '', $levels = 100) {
            if (empty($folder)) return false;
            if (!$levels) return false;
            $files = array();
            if ($dir = @opendir($folder)) {
                while (($file = readdir($dir)) !== false) {
                    if (in_array($file, array('.', '..')))
                        continue;
                    if (is_dir($folder . '/' . $file)) {
                        $files2 = self::get_list_files($folder . '/' . $file, $levels - 1);
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
        public static function copy_dir($src, $dst) {
            if (file_exists($dst)) self::delete_folder($dst);
            if (is_dir($src)) {
                wp_mkdir_p($dst);
                $files = scandir($src);
                foreach ($files as $file) {
                    if ($file != "." && $file != "..") self::copy_dir("$src/$file", "$dst/$file");
                }
            } else if (file_exists($src)) copy($src, $dst);
        }
        public static function mkdir($dir) {
            if (!file_exists($dir)) {
                wp_mkdir_p($dir);
            }
        }
        public static function convert_url_to_path($url) {
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
        public static function convert_path_to_url($path = '') {
            $url = str_replace(
                wp_normalize_path(untrailingslashit(ABSPATH)),
                site_url(),
                wp_normalize_path($path)
            );
            return esc_url_raw($url);
        }
        public static function get_list_files_by_type($path, $level = 100, $type) {
            $list       = array();
            $_list      = self::get_list_files($path, $level);
            $list       = preg_grep('/\.(' . $type . ')(?:[\?\#].*)?$/i', $_list);
            return $list;
        }
    }
}
