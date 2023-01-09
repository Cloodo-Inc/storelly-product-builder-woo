<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

if (!function_exists('printcart_get_page_id')) {
    function printcart_get_page_id($page) {
        $page = get_option('printcart_' . $page . '_page_id');
        return $page ? absint($page) : -1;
    }
}
function printcartGetUrlPage($page) {
    switch ($page) {
        case 'product_builder':
            $post = printcart_get_page_id('product_builder');
            break;
        default:
            $post = printcart_get_page_id($page);
            break;
    }
    return get_post($post) ? get_page_link($post) : '#';
}
function printcart_get_max_input_var() {
    return abs(intval(ini_get('max_input_vars')));
}
function printcart_get_image_thumbnail($id, $size = 'thumbnail') {
    if (absint($id) != 0) {
        $image = wp_get_attachment_image_src($id, $size);
        if (!$image) {
            $image_url = wp_get_attachment_url($id);
        } else {
            $image_url = $image[0];
        }
    } else {
        $image_url = PRINTCART_PB_ASSETS_URL . 'images/placeholder.png';
    }
    return $image_url;
}
