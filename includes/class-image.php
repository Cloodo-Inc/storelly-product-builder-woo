<?php
if (!defined('ABSPATH')) {
    exit;
}
if (!class_exists('STORELLY_IMAGE')) {
    class STORELLY_IMAGE {
        public static function resize_imagepng($file, $w, $h, $path = '') {
            list($width, $height)   = getimagesize($file);
            if ($path != '') $h    = round($w / $width * $height);
            $src = imagecreatefrompng($file);
            $dst = imagecreatetruecolor($w, $h);
            imagesavealpha($dst, true);
            $color = imagecolorallocatealpha($dst, 255, 255, 255, 127);
            imagefill($dst, 0, 0, $color);
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $w, $h, $width, $height);
            imagedestroy($src);
            if ($path == '') {
                return $dst;
            } else {
                imagepng($dst, $path);
                imagedestroy($dst);
            }
        }
    }
}
