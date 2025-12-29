<?php if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly 

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variable.
$link_css_pdf = get_home_url().'/assets/css/views/normalize.css';
?>
<html>

<head>
    <meta charset="utf-8" />
    <meta http-equiv="Content-type" content="text/html; charset=utf-8">
    <title><?php esc_html_e('NBStorelly', 'storelly-product-builder-for-woocommerce'); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=1, minimum-scale=0.5, maximum-scale=1.0" />

   
  

</head>

<body>
    <?php if ($page_settings['include_bg']) : ?>


        
        <img id="background" src="<?php echo esc_url($page_settings['bg_src']); ?>" />
    <?php endif; ?>
    <?php echo esc_html($svg_string);?> 
    
</body>

</html>