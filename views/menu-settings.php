<?php
if (!defined('ABSPATH')) exit; // Exit if accessed directly  

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variable.
$storelly_pb_settings = get_option('spbwc_pb_settings', array());
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variable.
$po = array(
    'number_of_decimals'              => get_option('spbwc_number_of_decimals', function_exists('wc_get_price_decimals') ? wc_get_price_decimals() : 2),
    'enable_rich_snippet_price'       => get_option('spbwc_enable_rich_snippet_price', 'no'),
    'option_display'                  => get_option('spbwc_option_display', '1'),
    'hide_add_cart_until_form_filled' => get_option('spbwc_hide_add_cart_until_form_filled', 'no'),
    'hide_summary_options'            => get_option('spbwc_hide_summary_options', 'no'),
    'float_summary_options'           => get_option('spbwc_float_summary_options', 'no'),
    'hide_table_pricing'              => get_option('spbwc_hide_table_pricing', 'no'),
    'table_pricing_type'              => get_option('spbwc_table_pricing_type', '1'),
    'hide_option_swatch_label'        => get_option('spbwc_hide_option_swatch_label', 'yes'),
    'change_base_price_html'           => get_option('spbwc_change_base_price_html', 'no'),
    'hide_zero_price'                 => get_option('spbwc_hide_zero_price', 'no'),
    'tooltip_position'                => get_option('spbwc_tooltip_position', 'top'),
    'ad_sublist_position'             => get_option('spbwc_ad_sublist_position', 'b'),
    'selector_increase_qty_btn'       => get_option('spbwc_selector_increase_qty_btn', ''),
    'display_product_option'         => get_option('spbwc_display_product_option', '1'),
    'force_select_options'            => get_option('spbwc_force_select_options', 'no'),
    'show_options_in_archive_pages'   => get_option('spbwc_show_options_in_archive_pages', 'no'),
    'enable_ajax_cart'                => get_option('spbwc_enable_ajax_cart', 'no'),
    'turn_off_persistent_cart'        => get_option('spbwc_turn_off_persistent_cart', 'no'),
    'enable_clear_cart_button'       => get_option('spbwc_enable_clear_cart_button', 'no'),
    'hide_options_in_cart'            => get_option('spbwc_hide_options_in_cart', 'no'),
    'hide_option_price_in_cart'      => get_option('spbwc_hide_option_price_in_cart', 'no'),
    'hide_option_price_in_order'     => get_option('spbwc_hide_option_price_in_order', 'no'),
);
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variable.
$api_key_raw = maybe_unserialize(get_option('spbwc_connect_api_keys'));
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variable.
$api_key = is_array($api_key_raw) ? $api_key_raw : array();

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variable.
$sid = isset($api_key['consumer_key']) ? $api_key['consumer_key'] : '';
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variable.
$secret = isset($api_key['consumer_secret']) ? $api_key['consumer_secret'] : '';
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variable.
$unauth_token = isset($api_key['unauth_token']) ? $api_key['unauth_token'] : '';
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variable.
$storelly_username = isset($api_key['username']) ? $api_key['username'] : '';
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variable.
$api_log = isset($api_key['log']) ? $api_key['log'] : esc_html__('no logs', 'storelly-product-builder-for-woocommerce');
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variable.
$url_new_product = get_home_url() . '/wp-admin/post-new.php?post_type=product';
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variable.
$stt_yes_cloud2print_api = isset($storelly_pb_settings['enable_cloud2print_api']) && $storelly_pb_settings['enable_cloud2print_api'] == 'yes' ? 'checked' : '';
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variable.
$stt_no_cloud2print_api = isset($storelly_pb_settings['enable_cloud2print_api']) && $storelly_pb_settings['enable_cloud2print_api'] == 'no' ? 'checked' : '';
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variable.
$spbwc_valid_tabs = array('general', 'display', 'pricing', 'catalog', 'cart');
$spbwc_settings_tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'general';
if ( ! in_array($spbwc_settings_tab, $spbwc_valid_tabs, true) ) {
    $spbwc_settings_tab = 'general';
}
?>

<div class="spbwc-settings-wrap">
    <?php if ($message && $status) : ?>
    <div id="message" class="notice notice-<?php echo esc_attr($status === 'updated' ? 'success' : 'error'); ?> is-dismissible"><p><strong><?php echo esc_html($message); ?></strong></p></div>
    <?php endif; ?>

    <div class="storelly-box spbwc-settings-box">
        <h2 class="nav-tab-wrapper spbwc-settings-tabs">
            <a href="<?php echo esc_url(admin_url('admin.php?page=' . SPBWC_PB_OPTIONS_SLUG . '&tab=general')); ?>" class="nav-tab <?php echo $spbwc_settings_tab === 'general' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('General', 'storelly-product-builder-for-woocommerce'); ?></a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=' . SPBWC_PB_OPTIONS_SLUG . '&tab=display')); ?>" class="nav-tab <?php echo $spbwc_settings_tab === 'display' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Display', 'storelly-product-builder-for-woocommerce'); ?></a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=' . SPBWC_PB_OPTIONS_SLUG . '&tab=pricing')); ?>" class="nav-tab <?php echo $spbwc_settings_tab === 'pricing' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Pricing', 'storelly-product-builder-for-woocommerce'); ?></a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=' . SPBWC_PB_OPTIONS_SLUG . '&tab=catalog')); ?>" class="nav-tab <?php echo $spbwc_settings_tab === 'catalog' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Catalog', 'storelly-product-builder-for-woocommerce'); ?></a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=' . SPBWC_PB_OPTIONS_SLUG . '&tab=cart')); ?>" class="nav-tab <?php echo $spbwc_settings_tab === 'cart' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Cart & Order', 'storelly-product-builder-for-woocommerce'); ?></a>
        </h2>

        <form class="storelly-form" method="post" action="<?php echo esc_url(admin_url('admin.php?page=' . SPBWC_PB_OPTIONS_SLUG . '&tab=' . $spbwc_settings_tab)); ?>" enctype="multipart/form-data">
            <div class="spbwc-settings-content">

            <?php if ($spbwc_settings_tab === 'general') : ?>
            <div class="spbwc-tab-panel" id="tab-general">
                <!-- Storelly Integration - First-time setup -->
                <div class="spbwc-settings-section spbwc-section-storelly">
                    <h3 class="spbwc-section-title">
                        <span class="dashicons dashicons-admin-generic"></span>
                        <?php esc_html_e('Storelly Integration', 'storelly-product-builder-for-woocommerce'); ?>
                    </h3>
                    <p class="spbwc-section-desc"><?php esc_html_e('Connect your store with Storelly to enable PDF creation and sync. Required for first-time setup.', 'storelly-product-builder-for-woocommerce'); ?></p>
                    <div class="spbwc-settings-grid">
                        <div class="spbwc-settings-card">
                            <h4 class="spbwc-card-title"><?php esc_html_e('API & Sync', 'storelly-product-builder-for-woocommerce'); ?></h4>
                            <table class="form-table pc-table">
                                <tbody>
                                    <tr>
                                        <th scope="row"><label><?php esc_html_e('Enable Storelly cloud API to create PDF', 'storelly-product-builder-for-woocommerce'); ?></label></th>
                                        <td>
                                            <label class="spbwc-radio-inline"><input type="radio" name="storelly_enable_cloud2print_api" value="yes" <?php echo esc_attr($stt_yes_cloud2print_api); ?> /> <?php esc_html_e('Yes', 'storelly-product-builder-for-woocommerce'); ?></label>
                                            <label class="spbwc-radio-inline"><input type="radio" name="storelly_enable_cloud2print_api" value="no" <?php echo esc_attr($stt_no_cloud2print_api); ?> /> <?php esc_html_e('No', 'storelly-product-builder-for-woocommerce'); ?></label>
                                        </td>
                                    </tr>
                                    <?php
                                    $spbwc_stt_yes_api_sync = isset($storelly_pb_settings['enable_api_sync']) && $storelly_pb_settings['enable_api_sync'] == 'yes' ? 'checked' : '';
                                    $spbwc_stt_no_api_sync = isset($storelly_pb_settings['enable_api_sync']) && $storelly_pb_settings['enable_api_sync'] == 'no' ? 'checked' : '';
                                    if (empty($spbwc_stt_yes_api_sync) && empty($spbwc_stt_no_api_sync)) {
                                        $spbwc_stt_no_api_sync = 'checked';
                                    }
                                    ?>
                                    <tr>
                                        <th scope="row"><label><?php esc_html_e('Enable Storelly Dashboard API sync (opt-in)', 'storelly-product-builder-for-woocommerce'); ?></label></th>
                                        <td>
                                            <label class="spbwc-radio-inline"><input type="radio" name="storelly_enable_api_sync" value="yes" <?php echo esc_attr($spbwc_stt_yes_api_sync); ?> /> <?php esc_html_e('Yes', 'storelly-product-builder-for-woocommerce'); ?></label>
                                            <label class="spbwc-radio-inline"><input type="radio" name="storelly_enable_api_sync" value="no" <?php echo esc_attr($spbwc_stt_no_api_sync); ?> /> <?php esc_html_e('No', 'storelly-product-builder-for-woocommerce'); ?></label>
                                            <p class="description"><?php esc_html_e('When enabled, order data will be synchronized with Storelly Dashboard. OFF by default.', 'storelly-product-builder-for-woocommerce'); ?></p>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="spbwc-settings-card spbwc-card-api">
                            <h4 class="spbwc-card-title"><?php esc_html_e('API Keys', 'storelly-product-builder-for-woocommerce'); ?></h4>
                            <div class="spbwc-form-row">
                                <label class="spbwc-form-label"><?php esc_html_e('SID', 'storelly-product-builder-for-woocommerce'); ?></label>
                                <input type="text" name="storelly_consumer_key" class="spbwc-input" placeholder="ck_xxxxx" value="<?php echo esc_attr($sid); ?>" />
                                <span class="spbwc-form-hint"><?php esc_html_e('Enter your Storelly SID API Key', 'storelly-product-builder-for-woocommerce'); ?></span>
                            </div>
                            <div class="spbwc-form-row">
                                <label class="spbwc-form-label"><?php esc_html_e('Secret', 'storelly-product-builder-for-woocommerce'); ?></label>
                                <input type="text" name="storelly_consumer_secret" class="spbwc-input" placeholder="cs_xxxxx" value="<?php echo esc_attr($secret); ?>" />
                                <span class="spbwc-form-hint"><?php esc_html_e('Enter your Storelly Secret API Key', 'storelly-product-builder-for-woocommerce'); ?></span>
                            </div>
                            <div class="spbwc-form-row">
                                <label class="spbwc-form-label"><?php esc_html_e('Unauth token', 'storelly-product-builder-for-woocommerce'); ?></label>
                                <input type="text" class="spbwc-input spbwc-input-readonly" value="<?php echo esc_attr($unauth_token); ?>" readonly />
                                <span class="spbwc-form-hint"><?php esc_html_e('Auto-generated when you enter SID and Secret', 'storelly-product-builder-for-woocommerce'); ?></span>
                            </div>
                            <?php if ($storelly_username) : ?>
                            <div class="spbwc-form-row">
                                <label class="spbwc-form-label"><?php esc_html_e('Username', 'storelly-product-builder-for-woocommerce'); ?></label>
                                <input type="text" class="spbwc-input spbwc-input-readonly" value="<?php echo esc_attr($storelly_username); ?>" readonly />
                            </div>
                            <div class="spbwc-form-actions">
                                <a href="https://app.storelly.com/login?redirect=woocomerce" class="spbwc-btn spbwc-btn-primary" target="_blank" rel="noopener"><?php esc_html_e('Login to Storelly', 'storelly-product-builder-for-woocommerce'); ?></a>
                                <a href="<?php echo esc_url($url_new_product); ?>" class="spbwc-btn spbwc-btn-secondary"><?php esc_html_e('Create your first product', 'storelly-product-builder-for-woocommerce'); ?></a>
                            </div>
                            <?php endif; ?>
                            <div class="spbwc-form-row spbwc-log-row">
                                <label class="spbwc-form-label"><?php esc_html_e('Log', 'storelly-product-builder-for-woocommerce'); ?></label>
                                <div class="spbwc-log-box"><code><?php echo esc_html($api_log); ?></code></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Printing Options -->
                <div class="spbwc-settings-section">
                    <h3 class="spbwc-section-title">
                        <span class="dashicons dashicons-edit"></span>
                        <?php esc_html_e('Printing Options', 'storelly-product-builder-for-woocommerce'); ?>
                    </h3>
                <table class="form-table pc-table">
                    <tbody>
                        <tr valign="top">
                            <th class="titledesc"><label><?php esc_html_e('Number of decimals', 'storelly-product-builder-for-woocommerce'); ?></label></th>
                            <td>
                                <input type="number" name="spbwc_number_of_decimals" value="<?php echo esc_attr($po['number_of_decimals']); ?>" min="0" max="6" style="width: 65px;" />
                                <p class="description"><?php esc_html_e('This sets the number of decimal points shown in displayed option prices.', 'storelly-product-builder-for-woocommerce'); ?></p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th class="titledesc"><label><?php esc_html_e('Enable rich snippet price', 'storelly-product-builder-for-woocommerce'); ?></label></th>
                            <td>
                                <p class="row"><label><input type="radio" name="spbwc_enable_rich_snippet_price" value="yes" <?php checked($po['enable_rich_snippet_price'], 'yes'); ?> /> <?php esc_html_e('Yes', 'storelly-product-builder-for-woocommerce'); ?></label></p>
                                <p class="row"><label><input type="radio" name="spbwc_enable_rich_snippet_price" value="no" <?php checked($po['enable_rich_snippet_price'], 'no'); ?> /> <?php esc_html_e('No', 'storelly-product-builder-for-woocommerce'); ?></label></p>
                                <p class="description"><?php esc_html_e('Enable default rich snippet price for search engine because sometimes base price is zero.', 'storelly-product-builder-for-woocommerce'); ?></p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th class="titledesc"><label><?php esc_html_e('Options display style', 'storelly-product-builder-for-woocommerce'); ?></label></th>
                            <td>
                                <p class="row"><label><input type="radio" name="spbwc_option_display" value="1" <?php checked($po['option_display'], '1'); ?> /> <?php esc_html_e('Sections', 'storelly-product-builder-for-woocommerce'); ?></label></p>
                                <p class="row"><label><input type="radio" name="spbwc_option_display" value="2" <?php checked($po['option_display'], '2'); ?> /> <?php esc_html_e('Table', 'storelly-product-builder-for-woocommerce'); ?></label></p>
                                <p class="description"><?php esc_html_e('This controls how options are displayed on the front-end.', 'storelly-product-builder-for-woocommerce'); ?></p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th class="titledesc"><label><?php esc_html_e('Hide Add to cart button until all required options are chosen', 'storelly-product-builder-for-woocommerce'); ?></label></th>
                            <td>
                                <p class="row"><label><input type="radio" name="spbwc_hide_add_cart_until_form_filled" value="yes" <?php checked($po['hide_add_cart_until_form_filled'], 'yes'); ?> /> <?php esc_html_e('Yes', 'storelly-product-builder-for-woocommerce'); ?></label></p>
                                <p class="row"><label><input type="radio" name="spbwc_hide_add_cart_until_form_filled" value="no" <?php checked($po['hide_add_cart_until_form_filled'], 'no'); ?> /> <?php esc_html_e('No', 'storelly-product-builder-for-woocommerce'); ?></label></p>
                                <p class="description"><?php esc_html_e('Check this to show the add to cart button only when all required options are filled.', 'storelly-product-builder-for-woocommerce'); ?></p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th class="titledesc"><label><?php esc_html_e('Display product options on', 'storelly-product-builder-for-woocommerce'); ?></label></th>
                            <td>
                                <p class="row"><label><input type="radio" name="spbwc_display_product_option" value="1" <?php checked($po['display_product_option'], '1'); ?> /> <?php esc_html_e('Popup', 'storelly-product-builder-for-woocommerce'); ?></label></p>
                                <p class="row"><label><input type="radio" name="spbwc_display_product_option" value="2" <?php checked($po['display_product_option'], '2'); ?> /> <?php esc_html_e('Product Tab', 'storelly-product-builder-for-woocommerce'); ?></label></p>
                                <p class="description"><?php esc_html_e('Display product options on popup or product tab in modern layout.', 'storelly-product-builder-for-woocommerce'); ?></p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th class="titledesc"><label><?php esc_html_e('jQuery selector for increase/decrease quantity button', 'storelly-product-builder-for-woocommerce'); ?></label></th>
                            <td>
                                <input type="text" name="spbwc_selector_increase_qty_btn" class="regular-text" placeholder=".quantity-plus, .quantity-minus" value="<?php echo esc_attr($po['selector_increase_qty_btn']); ?>" />
                                <p class="description"><?php esc_html_e('This is used to recalculate quantity discount price, example: .quantity-plus, .quantity-minus', 'storelly-product-builder-for-woocommerce'); ?></p>
                            </td>
                        </tr>
                    </tbody>
                </table>
                </div>
            </div>
            <?php elseif ($spbwc_settings_tab === 'display') : ?>
            <div class="spbwc-tab-panel" id="tab-display">
                <h3 class="spbwc-tab-title"><?php esc_html_e('Display', 'storelly-product-builder-for-woocommerce'); ?></h3>
                <table class="form-table pc-table">
                    <tbody>
                        <tr valign="top">
                            <th class="titledesc"><label><?php esc_html_e('Hide summary options', 'storelly-product-builder-for-woocommerce'); ?></label></th>
                            <td>
                                <p class="row"><label><input type="radio" name="spbwc_hide_summary_options" value="yes" <?php checked($po['hide_summary_options'], 'yes'); ?> /> <?php esc_html_e('Yes', 'storelly-product-builder-for-woocommerce'); ?></label></p>
                                <p class="row"><label><input type="radio" name="spbwc_hide_summary_options" value="no" <?php checked($po['hide_summary_options'], 'no'); ?> /> <?php esc_html_e('No', 'storelly-product-builder-for-woocommerce'); ?></label></p>
                                <p class="description"><?php esc_html_e('Hide summary options in product detail page.', 'storelly-product-builder-for-woocommerce'); ?></p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th class="titledesc"><label><?php esc_html_e('Float summary options', 'storelly-product-builder-for-woocommerce'); ?></label></th>
                            <td>
                                <p class="row"><label><input type="radio" name="spbwc_float_summary_options" value="yes" <?php checked($po['float_summary_options'], 'yes'); ?> /> <?php esc_html_e('Yes', 'storelly-product-builder-for-woocommerce'); ?></label></p>
                                <p class="row"><label><input type="radio" name="spbwc_float_summary_options" value="no" <?php checked($po['float_summary_options'], 'no'); ?> /> <?php esc_html_e('No', 'storelly-product-builder-for-woocommerce'); ?></label></p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th class="titledesc"><label><?php esc_html_e('Hide option swatch description', 'storelly-product-builder-for-woocommerce'); ?></label></th>
                            <td>
                                <p class="row"><label><input type="radio" name="spbwc_hide_option_swatch_label" value="yes" <?php checked($po['hide_option_swatch_label'], 'yes'); ?> /> <?php esc_html_e('Yes', 'storelly-product-builder-for-woocommerce'); ?></label></p>
                                <p class="row"><label><input type="radio" name="spbwc_hide_option_swatch_label" value="no" <?php checked($po['hide_option_swatch_label'], 'no'); ?> /> <?php esc_html_e('No', 'storelly-product-builder-for-woocommerce'); ?></label></p>
                                <p class="description"><?php esc_html_e('Hide option swatch description in product detail page.', 'storelly-product-builder-for-woocommerce'); ?></p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th class="titledesc"><label><?php esc_html_e('Option description tooltip position', 'storelly-product-builder-for-woocommerce'); ?></label></th>
                            <td>
                                <p class="row"><label><input type="radio" name="spbwc_tooltip_position" value="top" <?php checked($po['tooltip_position'], 'top'); ?> /> <?php esc_html_e('Top', 'storelly-product-builder-for-woocommerce'); ?></label></p>
                                <p class="row"><label><input type="radio" name="spbwc_tooltip_position" value="right" <?php checked($po['tooltip_position'], 'right'); ?> /> <?php esc_html_e('Right', 'storelly-product-builder-for-woocommerce'); ?></label></p>
                                <p class="row"><label><input type="radio" name="spbwc_tooltip_position" value="bottom" <?php checked($po['tooltip_position'], 'bottom'); ?> /> <?php esc_html_e('Bottom', 'storelly-product-builder-for-woocommerce'); ?></label></p>
                                <p class="row"><label><input type="radio" name="spbwc_tooltip_position" value="left" <?php checked($po['tooltip_position'], 'left'); ?> /> <?php esc_html_e('Left', 'storelly-product-builder-for-woocommerce'); ?></label></p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th class="titledesc"><label><?php esc_html_e('Advanced dropdown sub list position', 'storelly-product-builder-for-woocommerce'); ?></label></th>
                            <td>
                                <p class="row"><label><input type="radio" name="spbwc_ad_sublist_position" value="b" <?php checked($po['ad_sublist_position'], 'b'); ?> /> <?php esc_html_e('Below', 'storelly-product-builder-for-woocommerce'); ?></label></p>
                                <p class="row"><label><input type="radio" name="spbwc_ad_sublist_position" value="r" <?php checked($po['ad_sublist_position'], 'r'); ?> /> <?php esc_html_e('Right', 'storelly-product-builder-for-woocommerce'); ?></label></p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <?php elseif ($spbwc_settings_tab === 'pricing') : ?>
            <div class="spbwc-tab-panel" id="tab-pricing">
                <h3 class="spbwc-tab-title"><?php esc_html_e('Pricing', 'storelly-product-builder-for-woocommerce'); ?></h3>
                <table class="form-table pc-table">
                    <tbody>
                        <tr valign="top">
                            <th class="titledesc"><label><?php esc_html_e('Hide table pricing', 'storelly-product-builder-for-woocommerce'); ?></label></th>
                            <td>
                                <p class="row"><label><input type="radio" name="spbwc_hide_table_pricing" value="yes" <?php checked($po['hide_table_pricing'], 'yes'); ?> /> <?php esc_html_e('Yes', 'storelly-product-builder-for-woocommerce'); ?></label></p>
                                <p class="row"><label><input type="radio" name="spbwc_hide_table_pricing" value="no" <?php checked($po['hide_table_pricing'], 'no'); ?> /> <?php esc_html_e('No', 'storelly-product-builder-for-woocommerce'); ?></label></p>
                                <p class="description"><?php esc_html_e('Hide table pricing in product detail page.', 'storelly-product-builder-for-woocommerce'); ?></p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th class="titledesc"><label><?php esc_html_e('Table pricing type', 'storelly-product-builder-for-woocommerce'); ?></label></th>
                            <td>
                                <p class="row"><label><input type="radio" name="spbwc_table_pricing_type" value="1" <?php checked($po['table_pricing_type'], '1'); ?> /> <?php esc_html_e('Quantity range', 'storelly-product-builder-for-woocommerce'); ?></label></p>
                                <p class="row"><label><input type="radio" name="spbwc_table_pricing_type" value="2" <?php checked($po['table_pricing_type'], '2'); ?> /> <?php esc_html_e('Quantity breaks', 'storelly-product-builder-for-woocommerce'); ?></label></p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th class="titledesc"><label><?php esc_html_e('Change original product price', 'storelly-product-builder-for-woocommerce'); ?></label></th>
                            <td>
                                <p class="row"><label><input type="radio" name="spbwc_change_base_price_html" value="yes" <?php checked($po['change_base_price_html'], 'yes'); ?> /> <?php esc_html_e('Yes', 'storelly-product-builder-for-woocommerce'); ?></label></p>
                                <p class="row"><label><input type="radio" name="spbwc_change_base_price_html" value="no" <?php checked($po['change_base_price_html'], 'no'); ?> /> <?php esc_html_e('No', 'storelly-product-builder-for-woocommerce'); ?></label></p>
                                <p class="description"><?php esc_html_e('Overwrite the original product price when options are changing.', 'storelly-product-builder-for-woocommerce'); ?></p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th class="titledesc"><label><?php esc_html_e('Auto hide price if zero', 'storelly-product-builder-for-woocommerce'); ?></label></th>
                            <td>
                                <p class="row"><label><input type="radio" name="spbwc_hide_zero_price" value="yes" <?php checked($po['hide_zero_price'], 'yes'); ?> /> <?php esc_html_e('Yes', 'storelly-product-builder-for-woocommerce'); ?></label></p>
                                <p class="row"><label><input type="radio" name="spbwc_hide_zero_price" value="no" <?php checked($po['hide_zero_price'], 'no'); ?> /> <?php esc_html_e('No', 'storelly-product-builder-for-woocommerce'); ?></label></p>
                                <p class="description"><?php esc_html_e('Hide the option price display if it is zero.', 'storelly-product-builder-for-woocommerce'); ?></p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <?php elseif ($spbwc_settings_tab === 'catalog') : ?>
            <div class="spbwc-tab-panel" id="tab-catalog">
                <h3 class="spbwc-tab-title"><?php esc_html_e('Catalog', 'storelly-product-builder-for-woocommerce'); ?></h3>
                <table class="form-table pc-table">
                    <tbody>
                        <tr valign="top">
                            <th class="titledesc"><label><?php esc_html_e('Force Select Options', 'storelly-product-builder-for-woocommerce'); ?></label></th>
                            <td>
                                <p class="row"><label><input type="radio" name="spbwc_force_select_options" value="yes" <?php checked($po['force_select_options'], 'yes'); ?> /> <?php esc_html_e('Yes', 'storelly-product-builder-for-woocommerce'); ?></label></p>
                                <p class="row"><label><input type="radio" name="spbwc_force_select_options" value="no" <?php checked($po['force_select_options'], 'no'); ?> /> <?php esc_html_e('No', 'storelly-product-builder-for-woocommerce'); ?></label></p>
                                <p class="description"><?php esc_html_e('This changes the add to cart button on shop and archive pages to display select options when the product has extra product options.', 'storelly-product-builder-for-woocommerce'); ?></p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th class="titledesc"><label><?php esc_html_e('Show options in archive shop pages', 'storelly-product-builder-for-woocommerce'); ?></label></th>
                            <td>
                                <p class="row"><label><input type="radio" name="spbwc_show_options_in_archive_pages" value="yes" <?php checked($po['show_options_in_archive_pages'], 'yes'); ?> /> <?php esc_html_e('Yes', 'storelly-product-builder-for-woocommerce'); ?></label></p>
                                <p class="row"><label><input type="radio" name="spbwc_show_options_in_archive_pages" value="no" <?php checked($po['show_options_in_archive_pages'], 'no'); ?> /> <?php esc_html_e('No', 'storelly-product-builder-for-woocommerce'); ?></label></p>
                                <p class="description"><?php esc_html_e('Choose to show options selection in archive shop pages as swatches.', 'storelly-product-builder-for-woocommerce'); ?></p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <?php elseif ($spbwc_settings_tab === 'cart') : ?>
            <div class="spbwc-tab-panel" id="tab-cart">
                <h3 class="spbwc-tab-title"><?php esc_html_e('Cart & Order', 'storelly-product-builder-for-woocommerce'); ?></h3>
                <table class="form-table pc-table">
                    <tbody>
                        <tr valign="top">
                            <th class="titledesc"><label><?php esc_html_e('Ajax cart', 'storelly-product-builder-for-woocommerce'); ?></label></th>
                            <td>
                                <p class="row"><label><input type="radio" name="spbwc_enable_ajax_cart" value="yes" <?php checked($po['enable_ajax_cart'], 'yes'); ?> /> <?php esc_html_e('Yes', 'storelly-product-builder-for-woocommerce'); ?></label></p>
                                <p class="row"><label><input type="radio" name="spbwc_enable_ajax_cart" value="no" <?php checked($po['enable_ajax_cart'], 'no'); ?> /> <?php esc_html_e('No', 'storelly-product-builder-for-woocommerce'); ?></label></p>
                                <p class="description"><?php esc_html_e('Enable ajax add to cart in the product detail page.', 'storelly-product-builder-for-woocommerce'); ?></p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th class="titledesc"><label><?php esc_html_e('Turn off persistent cart', 'storelly-product-builder-for-woocommerce'); ?></label></th>
                            <td>
                                <p class="row"><label><input type="radio" name="spbwc_turn_off_persistent_cart" value="yes" <?php checked($po['turn_off_persistent_cart'], 'yes'); ?> /> <?php esc_html_e('Yes', 'storelly-product-builder-for-woocommerce'); ?></label></p>
                                <p class="row"><label><input type="radio" name="spbwc_turn_off_persistent_cart" value="no" <?php checked($po['turn_off_persistent_cart'], 'no'); ?> /> <?php esc_html_e('No', 'storelly-product-builder-for-woocommerce'); ?></label></p>
                                <p class="description"><?php esc_html_e('Enable this if the product has a lot of options.', 'storelly-product-builder-for-woocommerce'); ?></p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th class="titledesc"><label><?php esc_html_e('Clear cart button', 'storelly-product-builder-for-woocommerce'); ?></label></th>
                            <td>
                                <p class="row"><label><input type="radio" name="spbwc_enable_clear_cart_button" value="yes" <?php checked($po['enable_clear_cart_button'], 'yes'); ?> /> <?php esc_html_e('Yes', 'storelly-product-builder-for-woocommerce'); ?></label></p>
                                <p class="row"><label><input type="radio" name="spbwc_enable_clear_cart_button" value="no" <?php checked($po['enable_clear_cart_button'], 'no'); ?> /> <?php esc_html_e('No', 'storelly-product-builder-for-woocommerce'); ?></label></p>
                                <p class="description"><?php esc_html_e('Enables or disables the clear cart button.', 'storelly-product-builder-for-woocommerce'); ?></p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th class="titledesc"><label><?php esc_html_e('Hide options in cart', 'storelly-product-builder-for-woocommerce'); ?></label></th>
                            <td>
                                <p class="row"><label><input type="radio" name="spbwc_hide_options_in_cart" value="yes" <?php checked($po['hide_options_in_cart'], 'yes'); ?> /> <?php esc_html_e('Yes', 'storelly-product-builder-for-woocommerce'); ?></label></p>
                                <p class="row"><label><input type="radio" name="spbwc_hide_options_in_cart" value="no" <?php checked($po['hide_options_in_cart'], 'no'); ?> /> <?php esc_html_e('No', 'storelly-product-builder-for-woocommerce'); ?></label></p>
                                <p class="description"><?php esc_html_e('Enables or disables the display of options in cart.', 'storelly-product-builder-for-woocommerce'); ?></p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th class="titledesc"><label><?php esc_html_e('Hide option price in the cart', 'storelly-product-builder-for-woocommerce'); ?></label></th>
                            <td>
                                <p class="row"><label><input type="radio" name="spbwc_hide_option_price_in_cart" value="yes" <?php checked($po['hide_option_price_in_cart'], 'yes'); ?> /> <?php esc_html_e('Yes', 'storelly-product-builder-for-woocommerce'); ?></label></p>
                                <p class="row"><label><input type="radio" name="spbwc_hide_option_price_in_cart" value="no" <?php checked($po['hide_option_price_in_cart'], 'no'); ?> /> <?php esc_html_e('No', 'storelly-product-builder-for-woocommerce'); ?></label></p>
                                <p class="description"><?php esc_html_e('Enables or disables the display of option price in the cart.', 'storelly-product-builder-for-woocommerce'); ?></p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th class="titledesc"><label><?php esc_html_e('Hide option price in the order', 'storelly-product-builder-for-woocommerce'); ?></label></th>
                            <td>
                                <p class="row"><label><input type="radio" name="spbwc_hide_option_price_in_order" value="yes" <?php checked($po['hide_option_price_in_order'], 'yes'); ?> /> <?php esc_html_e('Yes', 'storelly-product-builder-for-woocommerce'); ?></label></p>
                                <p class="row"><label><input type="radio" name="spbwc_hide_option_price_in_order" value="no" <?php checked($po['hide_option_price_in_order'], 'no'); ?> /> <?php esc_html_e('No', 'storelly-product-builder-for-woocommerce'); ?></label></p>
                                <p class="description"><?php esc_html_e('Enables or disables the display of option price in the order, email, invoice.', 'storelly-product-builder-for-woocommerce'); ?></p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            </div>
            <div class="spbwc-sticky-save-bar">
                <div class="spbwc-sticky-save-inner">
                    <button name="save" class="spbwc-save-btn" type="submit" value="Save changes">
                        <span class="dashicons dashicons-saved"></span>
                        <?php esc_html_e('Save changes', 'storelly-product-builder-for-woocommerce'); ?>
                    </button>
                    <input type="hidden" name="_action_storelly_settings" value="submit">
                    <?php wp_nonce_field( 'spbwc_settings_action', 'spbwc_settings_nonce' ); ?>
                </div>
            </div>
        </form>
    </div>
</div>
