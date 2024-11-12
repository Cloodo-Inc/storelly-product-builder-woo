<?php if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly 

$link_css_pdf = get_home_url().'/assets/css/views/normalize.css';
?>
<html>

<head>
    <meta charset="utf-8" />
    <meta http-equiv="Content-type" content="text/html; charset=utf-8">
    <title><?php esc_html_e('NBStorelly', 'pc-product-builder'); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=1, minimum-scale=0.5, maximum-scale=1.0" />
    <link rel="stylesheet" type="text/css" href="<?php echo esc_url($link_css_pdf);?>" /> 
   
    <?php echo esc_url($font_css['google_font_link']);?>
    <style type="text/css">
        @page {
            margin: 0;
            padding: 0;
            size: <?php echo esc_attr($page_settings['width']); ?> <?php echo esc_attr($page_settings['height']); ?>;
        }

        body {
            width: <?php echo esc_attr($page_settings['width']); ?>;
            height: <?php echo esc_attr($page_settings['height']); ?>;
            position: relative;
            font-size: 0;
            font-family: sans-serif;
        }

        svg {
            position: absolute;
            width: <?php echo esc_attr($page_settings['design_width']); ?>;
            height: <?php echo esc_attr($page_settings['design_height']); ?>;
            top: <?php echo esc_attr($page_settings['design_top']); ?>;
            left: <?php echo esc_attr($page_settings['design_left']); ?>;
            z-index: 2;
            max-width: 100%;
            max-height: 100%;
        }

        #background {
            z-index: 1;
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
        }
    </style>
</head>

<body>
    <?php if ($page_settings['include_bg']) : ?>


        
        <img id="background" src="<?php echo esc_url($page_settings['bg_src']); ?>" />
    <?php endif; ?>
    <?php echo esc_html($svg_string);?> 
    
</body>

</html>