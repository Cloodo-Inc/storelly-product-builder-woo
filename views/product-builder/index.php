<?php if (!defined('ABSPATH')) exit; ?>
<!DOCTYPE html>
<html>

<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php do_action('storelly_head', 'product-builder'); ?>
    <?php
    $is_nbpb_creating_task = true;
    $is_creating_task = 1;
    include 'js_config.php';
    ?>
</head>

<body>
    <?php
    include(STORELLY_PB_PLUGIN_DIR . 'views/product-builder/wrapper.php');
    function storelly_get_product_builder($id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'storelly_product_builder_options';
        $result = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_name WHERE `id` = %d", $id), 'ARRAY_A'); 
        return count($result[0]) ? $result[0] : false;
    }
    function storelly_recursive_stripslashes($fields) {
        $valid_fields = array();
        foreach ($fields as $key => $field) {
            if (is_array($field)) {
                $valid_fields[$key] = storelly_recursive_stripslashes($field);
            } else if (!is_null($field)) {
                $valid_fields[$key] = stripslashes($field);
            }
        }
        return $valid_fields;
    }
    function storelly_show_option_fields() {
        $product_id = 0;
        $option_id = sanitize_text_field($_GET['oid']);
        if ($option_id) {
            $_options = storelly_get_product_builder($option_id);
            if ($_options) {
                $options = maybe_unserialize($_options['fields']);
                if (!isset($options['fields'])) {
                    $options['fields'] = array();
                }
                $options['fields'] = storelly_recursive_stripslashes($options['fields']);
                foreach ($options['fields'] as $key => $field) {
                    if (!isset($field['general']['attributes'])) {
                        $field['general']['attributes'] = array();
                        $field['general']['attributes']['options'] = array();
                        $options['fields'][$key]['general']['attributes'] = array();
                        $options['fields'][$key]['general']['attributes']['options'] = array();
                    }
                    if ($field['appearance']['change_image_product'] == 'y') {
                        foreach ($field['general']['attributes']['options'] as $op_index => $option) {
                            $option['product_image'] = isset($option['product_image']) ? $option['product_image'] : 0;
                            $attachment_id = absint($option['product_image']);
                            if ($attachment_id != 0) {
                                $image_link         = wp_get_attachment_url($attachment_id);
                                $attachment_object  = get_post($attachment_id);
                                $full_src           = wp_get_attachment_image_src($attachment_id, 'large');
                                $image_title        = get_the_title($attachment_id);
                                $image_alt          = trim(strip_tags(get_post_meta($attachment_id, '_wp_attachment_image_alt', TRUE)));
                                $image_srcset       = function_exists('wp_get_attachment_image_srcset') ? wp_get_attachment_image_srcset($attachment_id, 'shop_single') : FALSE;
                                $image_sizes        = function_exists('wp_get_attachment_image_sizes') ? wp_get_attachment_image_sizes($attachment_id, 'shop_single') : FALSE;
                                $image_caption      = $attachment_object->post_excerpt;
                                $options['fields'][$key]['general']['attributes']['options'][$op_index] = array_replace_recursive($options['fields'][$key]['general']['attributes']['options'][$op_index], array(
                                    'imagep'    =>  'y',
                                    'image_link'    => $image_link,
                                    'image_title'   => $image_title,
                                    'image_alt'     => $image_alt,
                                    'image_srcset'  => $image_srcset,
                                    'image_sizes'   => $image_sizes,
                                    'image_caption' => $image_caption,
                                    'full_src'      => $full_src[0],
                                    'full_src_w'    => $full_src[1],
                                    'full_src_h'    => $full_src[2]

                                ));
                            } else {
                                $options['fields'][$key]['general']['attributes']['options'][$op_index]['imagep'] = 'n';
                            }
                        }
                    }
                    if (isset($field['nbpb_type']) && $field['nbpb_type'] == 'nbpb_com') {
                        if (isset($field['general']['pb_config'])) {
                            foreach ($field['general']['pb_config'] as $a_index => $attr) {
                                foreach ($attr as $s_index => $sattr) {
                                    foreach ($sattr['views'] as $v_index => $view) {
                                        $pb_image_obj = wp_get_attachment_url(absint($view['image']));
                                        $options['fields'][$key]['general']['pb_config'][$a_index][$s_index]['views'][$v_index]['image_url'] =  $pb_image_obj ? $pb_image_obj : STORELLY_PB_ASSETS_URL . 'images/placeholder.png';
                                    }
                                }
                            }
                        } else {
                            $field['general']['pb_config'] = array();
                        }
                        foreach ($field['general']['attributes']['options'] as $op_index => $option) {
                            if (isset($option['enable_subattr']) && $option['enable_subattr'] == 'on' && count($option['sub_attributes']) > 0) {
                                foreach ($option['sub_attributes'] as $sa_index => $sattr) {
                                    $options['fields'][$key]['general']['attributes']['options'][$op_index]['sub_attributes'][$sa_index]['image_url'] = Storelly_PB_Util::storelly_get_image_thumbnail($sattr['image']);
                                }
                            } else {
                                $options['fields'][$key]['general']['attributes']['options'][$op_index]['image_url'] = Storelly_PB_Util::storelly_get_image_thumbnail($option['image']);
                            }
                        };
                        $options['fields'][$key]['general']['component_icon_url'] = Storelly_PB_Util::storelly_get_image_thumbnail($field['general']['component_icon']);
                    }
                    if (isset($field['general']['attributes']['bg_type']) && $field['general']['attributes']['bg_type'] == 'i') {
                        foreach ($field['general']['attributes']['options'] as $op_index => $option) {
                            foreach ($option['bg_image'] as $bg_index => $bg) {
                                $bg_obj = wp_get_attachment_url(absint($bg));
                                $options['fields'][$key]['general']['attributes']['options'][$op_index]['bg_image_url'][$bg_index] = $bg_obj ? $bg_obj : STORELLY_PB_ASSETS_URL . 'images/placeholder.png';
                            }
                        };
                    }
                }
                if (isset($options['views'])) {
                    foreach ($options['views'] as $vkey => $view) {
                        $view['base'] = isset($view['base']) ? $view['base'] : 0;
                        $options['views'][$vkey]['base'] = $view['base'];
                        $view_bg_obj = wp_get_attachment_url(absint($view['base']));
                        $options['views'][$vkey]['base_url'] = $view_bg_obj ? $view_bg_obj : STORELLY_PB_ASSETS_URL . 'images/placeholder.png';
                    }
                }
                $type           = 'simple';
                $variations     = array();
                $dimensions     = array();
                $form_values    = array();
                $cart_item_key  = '';
                $quantity       = 1;
                $width = $height = '';
                ob_start();
                storelly_PB_Util::storelly_get_template('single-product/option-builder.php', array(
                    'product_id'            => $product_id,
                    'options'               => $options,
                    'type'                  => $type,
                    'quantity'              => $quantity,
                    'width'                 => $width,
                    'height'                => $height,
                    'nbdpb_enable'          => 1,
                    'price'                 => 0,
                    'is_sold_individually'  => false,
                    'variations'            => wp_json_encode((array) $variations),
                    'dimensions'            => wp_json_encode((array) $dimensions),
                    'form_values'           => $form_values,
                    'cart_item_key'         => '',
                    'change_base'           => 'no',
                    'tooltip_position'      => 'top',
                    'hide_zero_price'       => 'no'
                ));
                $options_form = ob_get_clean();
                echo ($options_form);
            }
        }
    }
    storelly_show_option_fields();
    ?>
    <?php do_action('storelly_footer', 'product-builder'); ?>
</body>

</html>