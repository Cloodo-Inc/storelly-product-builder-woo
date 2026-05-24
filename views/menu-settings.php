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

    <!-- ── Page hero ── -->
    <header class="spbwc-page-hero">
        <div class="spbwc-page-hero__grid">
            <div class="spbwc-page-hero__body">
                <div class="spbwc-page-hero__eyebrow">
                    <span class="dashicons dashicons-admin-plugins" aria-hidden="true"></span>
                    <?php esc_html_e( 'Storelly Product Builder', 'storelly-product-builder-for-woocommerce' ); ?>
                </div>
                <h1 class="spbwc-page-hero__title">
                    <span class="dashicons dashicons-admin-settings" aria-hidden="true"></span>
                    <?php esc_html_e( 'Settings', 'storelly-product-builder-for-woocommerce' ); ?>
                </h1>
                <p class="spbwc-page-hero__subtitle">
                    <?php esc_html_e( 'Configure display, pricing, cart and API integration settings for Storelly Product Builder.', 'storelly-product-builder-for-woocommerce' ); ?>
                </p>
            </div>
            <div class="spbwc-page-hero__actions">
                <a href="https://storelly.com/docs" target="_blank" rel="noopener noreferrer"
                   class="spbwc-cta-btn spbwc-cta-btn--ghost">
                    <span class="dashicons dashicons-book-alt" aria-hidden="true"></span>
                    <?php esc_html_e( 'Documentation', 'storelly-product-builder-for-woocommerce' ); ?>
                </a>
            </div>
        </div>
    </header>

    <?php if ($message && $status) : ?>
    <div id="message" class="notice notice-<?php echo esc_attr($status === 'updated' ? 'success' : 'error'); ?> is-dismissible"><p><strong><?php echo esc_html($message); ?></strong></p></div>
    <?php endif; ?>

        <!-- Tab navigation — switching is JS-powered (no reload) -->
        <h2 class="nav-tab-wrapper spbwc-settings-tabs" id="spbwc-settings-nav">
            <a href="<?php echo esc_url(admin_url('admin.php?page=' . SPBWC_PB_OPTIONS_SLUG . '&tab=general')); ?>"
               data-tab="general" class="nav-tab <?php echo $spbwc_settings_tab === 'general' ? 'nav-tab-active' : ''; ?>">
                <span class="dashicons dashicons-admin-generic" aria-hidden="true"></span>
                <?php esc_html_e('General', 'storelly-product-builder-for-woocommerce'); ?>
            </a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=' . SPBWC_PB_OPTIONS_SLUG . '&tab=display')); ?>"
               data-tab="display" class="nav-tab <?php echo $spbwc_settings_tab === 'display' ? 'nav-tab-active' : ''; ?>">
                <span class="dashicons dashicons-visibility" aria-hidden="true"></span>
                <?php esc_html_e('Display', 'storelly-product-builder-for-woocommerce'); ?>
            </a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=' . SPBWC_PB_OPTIONS_SLUG . '&tab=pricing')); ?>"
               data-tab="pricing" class="nav-tab <?php echo $spbwc_settings_tab === 'pricing' ? 'nav-tab-active' : ''; ?>">
                <span class="dashicons dashicons-tag" aria-hidden="true"></span>
                <?php esc_html_e('Pricing', 'storelly-product-builder-for-woocommerce'); ?>
            </a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=' . SPBWC_PB_OPTIONS_SLUG . '&tab=catalog')); ?>"
               data-tab="catalog" class="nav-tab <?php echo $spbwc_settings_tab === 'catalog' ? 'nav-tab-active' : ''; ?>">
                <span class="dashicons dashicons-store" aria-hidden="true"></span>
                <?php esc_html_e('Catalog', 'storelly-product-builder-for-woocommerce'); ?>
            </a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=' . SPBWC_PB_OPTIONS_SLUG . '&tab=cart')); ?>"
               data-tab="cart" class="nav-tab <?php echo $spbwc_settings_tab === 'cart' ? 'nav-tab-active' : ''; ?>">
                <span class="dashicons dashicons-cart" aria-hidden="true"></span>
                <?php esc_html_e('Cart &amp; Order', 'storelly-product-builder-for-woocommerce'); ?>
            </a>
        </h2>

        <form class="storelly-form" method="post" id="spbwc-settings-form"
              action="<?php echo esc_url(admin_url('admin.php?page=' . SPBWC_PB_OPTIONS_SLUG . '&tab=' . $spbwc_settings_tab)); ?>"
              enctype="multipart/form-data">

            <!-- ━━━ GENERAL ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ -->
            <div class="spbwc-tab-panel" id="tab-general"<?php echo ($spbwc_settings_tab !== 'general') ? ' style="display:none;"' : ''; ?>>

                <!-- Storelly Integration + API Keys — single block -->
                <div class="spbwc-block">
                    <div class="spbwc-block__head">
                        <h3 class="spbwc-block__title">
                            <span class="dashicons dashicons-admin-generic" aria-hidden="true"></span>
                            <?php esc_html_e('Storelly Integration', 'storelly-product-builder-for-woocommerce'); ?>
                        </h3>
                    </div>

                    <!-- Integration toggles -->
                    <div class="spbwc-setting-rows">
                        <!-- Enable cloud PDF -->
                        <div class="spbwc-setting-row">
                            <div class="spbwc-setting-row__label">
                                <?php esc_html_e('Enable cloud PDF rendering', 'storelly-product-builder-for-woocommerce'); ?>
                            </div>
                            <div class="spbwc-setting-row__control">
                                <div class="spbwc-radio-group">
                                    <label class="spbwc-radio-group__option">
                                        <input type="radio" name="storelly_enable_cloud2print_api" value="yes" <?php echo esc_attr($stt_yes_cloud2print_api); ?> />
                                        <span class="spbwc-radio-group__lbl"><?php esc_html_e('Yes', 'storelly-product-builder-for-woocommerce'); ?></span>
                                    </label>
                                    <label class="spbwc-radio-group__option">
                                        <input type="radio" name="storelly_enable_cloud2print_api" value="no" <?php echo esc_attr($stt_no_cloud2print_api); ?> />
                                        <span class="spbwc-radio-group__lbl"><?php esc_html_e('No', 'storelly-product-builder-for-woocommerce'); ?></span>
                                    </label>
                                </div>
                            </div>
                            <p class="spbwc-setting-row__hint"><?php esc_html_e('Activates the Storelly cloud engine to generate print-ready PDFs from customer orders. Required if you offer downloadable print files or send jobs to a print provider via Storelly.', 'storelly-product-builder-for-woocommerce'); ?></p>
                        </div>
                        <!-- API sync opt-in -->
                        <?php
                        $spbwc_stt_yes_api_sync = isset($storelly_pb_settings['enable_api_sync']) && $storelly_pb_settings['enable_api_sync'] == 'yes' ? 'checked' : '';
                        $spbwc_stt_no_api_sync = isset($storelly_pb_settings['enable_api_sync']) && $storelly_pb_settings['enable_api_sync'] == 'no' ? 'checked' : '';
                        if (empty($spbwc_stt_yes_api_sync) && empty($spbwc_stt_no_api_sync)) {
                            $spbwc_stt_no_api_sync = 'checked';
                        }
                        ?>
                        <div class="spbwc-setting-row">
                            <div class="spbwc-setting-row__label">
                                <?php esc_html_e('Enable Dashboard API sync', 'storelly-product-builder-for-woocommerce'); ?>
                            </div>
                            <div class="spbwc-setting-row__control">
                                <div class="spbwc-radio-group">
                                    <label class="spbwc-radio-group__option">
                                        <input type="radio" name="storelly_enable_api_sync" value="yes" <?php echo esc_attr($spbwc_stt_yes_api_sync); ?> />
                                        <span class="spbwc-radio-group__lbl"><?php esc_html_e('Yes', 'storelly-product-builder-for-woocommerce'); ?></span>
                                    </label>
                                    <label class="spbwc-radio-group__option">
                                        <input type="radio" name="storelly_enable_api_sync" value="no" <?php echo esc_attr($spbwc_stt_no_api_sync); ?> />
                                        <span class="spbwc-radio-group__lbl"><?php esc_html_e('No', 'storelly-product-builder-for-woocommerce'); ?></span>
                                    </label>
                                </div>
                            </div>
                            <p class="spbwc-setting-row__hint"><?php esc_html_e('Opt-in to sync order data with Storelly Dashboard for centralised print job management. Disabled by default — no data leaves your store until you turn this on.', 'storelly-product-builder-for-woocommerce'); ?></p>
                        </div>
                    </div>

                    <!-- API Keys sub-section (same block, visual divider) -->
                    <div class="spbwc-block__sub-head">
                        <span class="dashicons dashicons-admin-network" aria-hidden="true"></span>
                        <?php esc_html_e('API Keys', 'storelly-product-builder-for-woocommerce'); ?>
                    </div>
                    <div class="spbwc-block__body">
                        <div class="spbwc-form-row">
                            <label class="spbwc-form-label"><?php esc_html_e('SID', 'storelly-product-builder-for-woocommerce'); ?></label>
                            <input type="text" name="storelly_consumer_key" class="spbwc-input" placeholder="ck_xxxxx" value="<?php echo esc_attr($sid); ?>" style="max-width:380px;" />
                            <span class="spbwc-form-hint"><?php esc_html_e('Enter your Storelly SID API Key', 'storelly-product-builder-for-woocommerce'); ?></span>
                        </div>
                        <div class="spbwc-form-row">
                            <label class="spbwc-form-label"><?php esc_html_e('Secret', 'storelly-product-builder-for-woocommerce'); ?></label>
                            <input type="text" name="storelly_consumer_secret" class="spbwc-input" placeholder="cs_xxxxx" value="<?php echo esc_attr($secret); ?>" style="max-width:380px;" />
                            <span class="spbwc-form-hint"><?php esc_html_e('Enter your Storelly Secret API Key', 'storelly-product-builder-for-woocommerce'); ?></span>
                        </div>
                        <div class="spbwc-form-row">
                            <label class="spbwc-form-label"><?php esc_html_e('Unauth token', 'storelly-product-builder-for-woocommerce'); ?></label>
                            <input type="text" class="spbwc-input spbwc-input-readonly" value="<?php echo esc_attr($unauth_token); ?>" readonly style="max-width:380px;" />
                            <span class="spbwc-form-hint"><?php esc_html_e('Auto-generated when you enter SID and Secret', 'storelly-product-builder-for-woocommerce'); ?></span>
                        </div>
                        <?php if ($storelly_username) : ?>
                        <div class="spbwc-form-row">
                            <label class="spbwc-form-label"><?php esc_html_e('Username', 'storelly-product-builder-for-woocommerce'); ?></label>
                            <input type="text" class="spbwc-input spbwc-input-readonly" value="<?php echo esc_attr($storelly_username); ?>" readonly style="max-width:380px;" />
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

                <!-- Printing Options block -->
                <div class="spbwc-block">
                    <div class="spbwc-block__head">
                        <h3 class="spbwc-block__title">
                            <span class="dashicons dashicons-edit" aria-hidden="true"></span>
                            <?php esc_html_e('Printing Options', 'storelly-product-builder-for-woocommerce'); ?>
                        </h3>
                    </div>
                    <div class="spbwc-setting-rows">

                        <!-- Number of decimals -->
                        <div class="spbwc-setting-row">
                            <div class="spbwc-setting-row__label">
                                <label for="spbwc_number_of_decimals"><?php esc_html_e('Number of decimals', 'storelly-product-builder-for-woocommerce'); ?></label>
                            </div>
                            <div class="spbwc-setting-row__control">
                                <input id="spbwc_number_of_decimals" type="number" name="spbwc_number_of_decimals"
                                    value="<?php echo esc_attr($po['number_of_decimals']); ?>" min="0" max="6"
                                    style="width:72px;" class="small-text" />
                            </div>
                            <p class="spbwc-setting-row__hint"><?php esc_html_e('How many decimal places appear in option prices on the product page (e.g. +$2 vs +$2.00). Defaults to your WooCommerce global setting.', 'storelly-product-builder-for-woocommerce'); ?></p>
                        </div>

                        <!-- Rich snippet price -->
                        <div class="spbwc-setting-row">
                            <div class="spbwc-setting-row__label">
                                <?php esc_html_e('Enable rich snippet price', 'storelly-product-builder-for-woocommerce'); ?>
                            </div>
                            <div class="spbwc-setting-row__control">
                                <div class="spbwc-radio-group">
                                    <label class="spbwc-radio-group__option">
                                        <input type="radio" name="spbwc_enable_rich_snippet_price" value="yes" <?php checked($po['enable_rich_snippet_price'], 'yes'); ?> />
                                        <span class="spbwc-radio-group__lbl"><?php esc_html_e('Yes', 'storelly-product-builder-for-woocommerce'); ?></span>
                                    </label>
                                    <label class="spbwc-radio-group__option">
                                        <input type="radio" name="spbwc_enable_rich_snippet_price" value="no" <?php checked($po['enable_rich_snippet_price'], 'no'); ?> />
                                        <span class="spbwc-radio-group__lbl"><?php esc_html_e('No', 'storelly-product-builder-for-woocommerce'); ?></span>
                                    </label>
                                </div>
                            </div>
                            <p class="spbwc-setting-row__hint"><?php esc_html_e('When your base product starts at $0, search engines may display "$0" in results. Enable this so structured data reflects the true configurable price — improves SEO click-through rate.', 'storelly-product-builder-for-woocommerce'); ?></p>
                        </div>

                        <!-- Options display style -->
                        <div class="spbwc-setting-row">
                            <div class="spbwc-setting-row__label">
                                <?php esc_html_e('Options display style', 'storelly-product-builder-for-woocommerce'); ?>
                            </div>
                            <div class="spbwc-setting-row__control">
                                <div class="spbwc-radio-group">
                                    <label class="spbwc-radio-group__option">
                                        <input type="radio" name="spbwc_option_display" value="1" <?php checked($po['option_display'], '1'); ?> />
                                        <span class="spbwc-radio-group__lbl"><?php esc_html_e('Sections', 'storelly-product-builder-for-woocommerce'); ?></span>
                                    </label>
                                    <label class="spbwc-radio-group__option">
                                        <input type="radio" name="spbwc_option_display" value="2" <?php checked($po['option_display'], '2'); ?> />
                                        <span class="spbwc-radio-group__lbl"><?php esc_html_e('Table', 'storelly-product-builder-for-woocommerce'); ?></span>
                                    </label>
                                </div>
                            </div>
                            <p class="spbwc-setting-row__hint"><?php esc_html_e('Sections: options stacked vertically as a form — best for 5+ distinct choices. Table: compact grid — best for simpler products with fewer options.', 'storelly-product-builder-for-woocommerce'); ?></p>
                        </div>

                        <!-- Hide Add to cart until form filled -->
                        <div class="spbwc-setting-row">
                            <div class="spbwc-setting-row__label">
                                <?php esc_html_e('Hide Add to cart until all required options are selected', 'storelly-product-builder-for-woocommerce'); ?>
                            </div>
                            <div class="spbwc-setting-row__control">
                                <div class="spbwc-radio-group">
                                    <label class="spbwc-radio-group__option">
                                        <input type="radio" name="spbwc_hide_add_cart_until_form_filled" value="yes" <?php checked($po['hide_add_cart_until_form_filled'], 'yes'); ?> />
                                        <span class="spbwc-radio-group__lbl"><?php esc_html_e('Yes', 'storelly-product-builder-for-woocommerce'); ?></span>
                                    </label>
                                    <label class="spbwc-radio-group__option">
                                        <input type="radio" name="spbwc_hide_add_cart_until_form_filled" value="no" <?php checked($po['hide_add_cart_until_form_filled'], 'no'); ?> />
                                        <span class="spbwc-radio-group__lbl"><?php esc_html_e('No', 'storelly-product-builder-for-woocommerce'); ?></span>
                                    </label>
                                </div>
                            </div>
                            <p class="spbwc-setting-row__hint"><?php esc_html_e('Hides the "Add to cart" button until every required option has a value. Reduces incomplete or incorrect orders — strongly recommended for print-on-demand products.', 'storelly-product-builder-for-woocommerce'); ?></p>
                        </div>

                        <!-- Display product options on -->
                        <div class="spbwc-setting-row">
                            <div class="spbwc-setting-row__label">
                                <?php esc_html_e('Display product options on', 'storelly-product-builder-for-woocommerce'); ?>
                            </div>
                            <div class="spbwc-setting-row__control">
                                <div class="spbwc-radio-group">
                                    <label class="spbwc-radio-group__option">
                                        <input type="radio" name="spbwc_display_product_option" value="1" <?php checked($po['display_product_option'], '1'); ?> />
                                        <span class="spbwc-radio-group__lbl"><?php esc_html_e('Popup', 'storelly-product-builder-for-woocommerce'); ?></span>
                                    </label>
                                    <label class="spbwc-radio-group__option">
                                        <input type="radio" name="spbwc_display_product_option" value="2" <?php checked($po['display_product_option'], '2'); ?> />
                                        <span class="spbwc-radio-group__lbl"><?php esc_html_e('Product Tab', 'storelly-product-builder-for-woocommerce'); ?></span>
                                    </label>
                                </div>
                            </div>
                            <p class="spbwc-setting-row__hint"><?php esc_html_e('Popup: options open in a modal overlay on top of the product image. Product Tab: options appear inline below the product description as a dedicated tab.', 'storelly-product-builder-for-woocommerce'); ?></p>
                        </div>

                        <!-- jQuery selector for qty buttons -->
                        <div class="spbwc-setting-row">
                            <div class="spbwc-setting-row__label">
                                <label for="spbwc_selector_increase_qty_btn"><?php esc_html_e('Quantity button CSS selector', 'storelly-product-builder-for-woocommerce'); ?></label>
                            </div>
                            <div class="spbwc-setting-row__control">
                                <input id="spbwc_selector_increase_qty_btn" type="text" name="spbwc_selector_increase_qty_btn"
                                    class="spbwc-input" style="max-width:260px;"
                                    placeholder=".quantity-plus, .quantity-minus"
                                    value="<?php echo esc_attr($po['selector_increase_qty_btn']); ?>" />
                            </div>
                            <p class="spbwc-setting-row__hint"><?php esc_html_e('Advanced — only needed if your theme uses custom +/− quantity buttons. Enter their CSS selector (e.g. .qty-plus, .qty-minus) so volume pricing recalculates correctly when quantity changes.', 'storelly-product-builder-for-woocommerce'); ?></p>
                        </div>

                    </div>
                </div>
            </div>
            <!-- ━━━ DISPLAY ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ -->
            <div class="spbwc-tab-panel" id="tab-display"<?php echo ($spbwc_settings_tab !== 'display') ? ' style="display:none;"' : ''; ?>>
                <div class="spbwc-block">
                    <div class="spbwc-block__head">
                        <h3 class="spbwc-block__title">
                            <span class="dashicons dashicons-visibility" aria-hidden="true"></span>
                            <?php esc_html_e('Display', 'storelly-product-builder-for-woocommerce'); ?>
                        </h3>
                    </div>
                    <div class="spbwc-setting-rows">
                        <!-- Hide selection summary -->
                        <div class="spbwc-setting-row">
                            <div class="spbwc-setting-row__label">
                                <?php esc_html_e('Hide selection summary', 'storelly-product-builder-for-woocommerce'); ?>
                            </div>
                            <div class="spbwc-setting-row__control">
                                <div class="spbwc-radio-group">
                                    <label class="spbwc-radio-group__option">
                                        <input type="radio" name="spbwc_hide_summary_options" value="yes" <?php checked($po['hide_summary_options'], 'yes'); ?> />
                                        <span class="spbwc-radio-group__lbl"><?php esc_html_e('Yes', 'storelly-product-builder-for-woocommerce'); ?></span>
                                    </label>
                                    <label class="spbwc-radio-group__option">
                                        <input type="radio" name="spbwc_hide_summary_options" value="no" <?php checked($po['hide_summary_options'], 'no'); ?> />
                                        <span class="spbwc-radio-group__lbl"><?php esc_html_e('No', 'storelly-product-builder-for-woocommerce'); ?></span>
                                    </label>
                                </div>
                            </div>
                            <p class="spbwc-setting-row__hint"><?php esc_html_e('The summary panel shows buyers a recap of every option they selected before adding to cart. Keep visible to reduce confusion; hide for very simple, self-explanatory products.', 'storelly-product-builder-for-woocommerce'); ?></p>
                        </div>
                        <!-- Float summary panel -->
                        <div class="spbwc-setting-row">
                            <div class="spbwc-setting-row__label">
                                <?php esc_html_e('Float summary panel', 'storelly-product-builder-for-woocommerce'); ?>
                            </div>
                            <div class="spbwc-setting-row__control">
                                <div class="spbwc-radio-group">
                                    <label class="spbwc-radio-group__option">
                                        <input type="radio" name="spbwc_float_summary_options" value="yes" <?php checked($po['float_summary_options'], 'yes'); ?> />
                                        <span class="spbwc-radio-group__lbl"><?php esc_html_e('Yes', 'storelly-product-builder-for-woocommerce'); ?></span>
                                    </label>
                                    <label class="spbwc-radio-group__option">
                                        <input type="radio" name="spbwc_float_summary_options" value="no" <?php checked($po['float_summary_options'], 'no'); ?> />
                                        <span class="spbwc-radio-group__lbl"><?php esc_html_e('No', 'storelly-product-builder-for-woocommerce'); ?></span>
                                    </label>
                                </div>
                            </div>
                            <p class="spbwc-setting-row__hint"><?php esc_html_e('When Yes, the summary sidebar scrolls with the buyer as they configure options — keeping their selections always visible. Recommended for products with long option forms.', 'storelly-product-builder-for-woocommerce'); ?></p>
                        </div>
                        <!-- Hide swatch caption labels -->
                        <div class="spbwc-setting-row">
                            <div class="spbwc-setting-row__label">
                                <?php esc_html_e('Hide swatch caption labels', 'storelly-product-builder-for-woocommerce'); ?>
                            </div>
                            <div class="spbwc-setting-row__control">
                                <div class="spbwc-radio-group">
                                    <label class="spbwc-radio-group__option">
                                        <input type="radio" name="spbwc_hide_option_swatch_label" value="yes" <?php checked($po['hide_option_swatch_label'], 'yes'); ?> />
                                        <span class="spbwc-radio-group__lbl"><?php esc_html_e('Yes', 'storelly-product-builder-for-woocommerce'); ?></span>
                                    </label>
                                    <label class="spbwc-radio-group__option">
                                        <input type="radio" name="spbwc_hide_option_swatch_label" value="no" <?php checked($po['hide_option_swatch_label'], 'no'); ?> />
                                        <span class="spbwc-radio-group__lbl"><?php esc_html_e('No', 'storelly-product-builder-for-woocommerce'); ?></span>
                                    </label>
                                </div>
                            </div>
                            <p class="spbwc-setting-row__hint"><?php esc_html_e('Swatch buttons (colour/image squares) display a text caption underneath. Hide captions if your swatch images are self-explanatory — gives a cleaner, more visual layout.', 'storelly-product-builder-for-woocommerce'); ?></p>
                        </div>
                        <!-- Tooltip position -->
                        <div class="spbwc-setting-row">
                            <div class="spbwc-setting-row__label">
                                <?php esc_html_e('Tooltip position', 'storelly-product-builder-for-woocommerce'); ?>
                            </div>
                            <div class="spbwc-setting-row__control">
                                <div class="spbwc-radio-group">
                                    <?php foreach ( array( 'top' => __('Top','storelly-product-builder-for-woocommerce'), 'right' => __('Right','storelly-product-builder-for-woocommerce'), 'bottom' => __('Bottom','storelly-product-builder-for-woocommerce'), 'left' => __('Left','storelly-product-builder-for-woocommerce') ) as $val => $lbl ) : ?>
                                    <label class="spbwc-radio-group__option">
                                        <input type="radio" name="spbwc_tooltip_position" value="<?php echo esc_attr($val); ?>" <?php checked($po['tooltip_position'], $val); ?> />
                                        <span class="spbwc-radio-group__lbl"><?php echo esc_html($lbl); ?></span>
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <p class="spbwc-setting-row__hint"><?php esc_html_e('When an option has a "?" help icon, a tooltip appears on hover. Controls which side it pops out — pick based on your layout to avoid the tooltip being clipped off-screen.', 'storelly-product-builder-for-woocommerce'); ?></p>
                        </div>
                        <!-- Dropdown sub-list direction -->
                        <div class="spbwc-setting-row">
                            <div class="spbwc-setting-row__label">
                                <?php esc_html_e('Dropdown sub-list direction', 'storelly-product-builder-for-woocommerce'); ?>
                            </div>
                            <div class="spbwc-setting-row__control">
                                <div class="spbwc-radio-group">
                                    <label class="spbwc-radio-group__option">
                                        <input type="radio" name="spbwc_ad_sublist_position" value="b" <?php checked($po['ad_sublist_position'], 'b'); ?> />
                                        <span class="spbwc-radio-group__lbl"><?php esc_html_e('Below', 'storelly-product-builder-for-woocommerce'); ?></span>
                                    </label>
                                    <label class="spbwc-radio-group__option">
                                        <input type="radio" name="spbwc_ad_sublist_position" value="r" <?php checked($po['ad_sublist_position'], 'r'); ?> />
                                        <span class="spbwc-radio-group__lbl"><?php esc_html_e('Right', 'storelly-product-builder-for-woocommerce'); ?></span>
                                    </label>
                                </div>
                            </div>
                            <p class="spbwc-setting-row__hint"><?php esc_html_e('For advanced dropdown fields with nested sub-options: "Below" opens the child list directly under the parent row; "Right" opens it as a side flyout. Choose based on your available page width.', 'storelly-product-builder-for-woocommerce'); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ━━━ PRICING ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ -->
            <div class="spbwc-tab-panel" id="tab-pricing"<?php echo ($spbwc_settings_tab !== 'pricing') ? ' style="display:none;"' : ''; ?>>
                <div class="spbwc-block">
                    <div class="spbwc-block__head">
                        <h3 class="spbwc-block__title">
                            <span class="dashicons dashicons-tag" aria-hidden="true"></span>
                            <?php esc_html_e('Pricing', 'storelly-product-builder-for-woocommerce'); ?>
                        </h3>
                    </div>
                    <div class="spbwc-setting-rows">
                        <!-- Hide volume pricing table -->
                        <div class="spbwc-setting-row">
                            <div class="spbwc-setting-row__label">
                                <?php esc_html_e('Hide volume pricing table', 'storelly-product-builder-for-woocommerce'); ?>
                            </div>
                            <div class="spbwc-setting-row__control">
                                <div class="spbwc-radio-group">
                                    <label class="spbwc-radio-group__option">
                                        <input type="radio" name="spbwc_hide_table_pricing" value="yes" <?php checked($po['hide_table_pricing'], 'yes'); ?> />
                                        <span class="spbwc-radio-group__lbl"><?php esc_html_e('Yes', 'storelly-product-builder-for-woocommerce'); ?></span>
                                    </label>
                                    <label class="spbwc-radio-group__option">
                                        <input type="radio" name="spbwc_hide_table_pricing" value="no" <?php checked($po['hide_table_pricing'], 'no'); ?> />
                                        <span class="spbwc-radio-group__lbl"><?php esc_html_e('No', 'storelly-product-builder-for-woocommerce'); ?></span>
                                    </label>
                                </div>
                            </div>
                            <p class="spbwc-setting-row__hint"><?php esc_html_e('Volume pricing tables show tiered discounts (e.g. 10 pcs = $5 each, 50 pcs = $4 each) to motivate larger orders. Hide if you do not use quantity-based pricing on this store.', 'storelly-product-builder-for-woocommerce'); ?></p>
                        </div>
                        <!-- Volume pricing table style -->
                        <div class="spbwc-setting-row">
                            <div class="spbwc-setting-row__label">
                                <?php esc_html_e('Volume pricing table style', 'storelly-product-builder-for-woocommerce'); ?>
                            </div>
                            <div class="spbwc-setting-row__control">
                                <div class="spbwc-radio-group">
                                    <label class="spbwc-radio-group__option">
                                        <input type="radio" name="spbwc_table_pricing_type" value="1" <?php checked($po['table_pricing_type'], '1'); ?> />
                                        <span class="spbwc-radio-group__lbl"><?php esc_html_e('Quantity range', 'storelly-product-builder-for-woocommerce'); ?></span>
                                    </label>
                                    <label class="spbwc-radio-group__option">
                                        <input type="radio" name="spbwc_table_pricing_type" value="2" <?php checked($po['table_pricing_type'], '2'); ?> />
                                        <span class="spbwc-radio-group__lbl"><?php esc_html_e('Quantity breaks', 'storelly-product-builder-for-woocommerce'); ?></span>
                                    </label>
                                </div>
                            </div>
                            <p class="spbwc-setting-row__hint"><?php esc_html_e('Quantity range: shows a span (e.g. "10–49 units → $5 each"). Quantity breaks: shows a minimum threshold (e.g. "10+ units → $5 each"). Both display the per-unit discounted price.', 'storelly-product-builder-for-woocommerce'); ?></p>
                        </div>
                        <!-- Live-update displayed product price -->
                        <div class="spbwc-setting-row">
                            <div class="spbwc-setting-row__label">
                                <?php esc_html_e('Live-update displayed product price', 'storelly-product-builder-for-woocommerce'); ?>
                            </div>
                            <div class="spbwc-setting-row__control">
                                <div class="spbwc-radio-group">
                                    <label class="spbwc-radio-group__option">
                                        <input type="radio" name="spbwc_change_base_price_html" value="yes" <?php checked($po['change_base_price_html'], 'yes'); ?> />
                                        <span class="spbwc-radio-group__lbl"><?php esc_html_e('Yes', 'storelly-product-builder-for-woocommerce'); ?></span>
                                    </label>
                                    <label class="spbwc-radio-group__option">
                                        <input type="radio" name="spbwc_change_base_price_html" value="no" <?php checked($po['change_base_price_html'], 'no'); ?> />
                                        <span class="spbwc-radio-group__lbl"><?php esc_html_e('No', 'storelly-product-builder-for-woocommerce'); ?></span>
                                    </label>
                                </div>
                            </div>
                            <p class="spbwc-setting-row__hint"><?php esc_html_e('Yes: the product\'s main price display updates live as buyers pick options — they always see the full total. No: only the add-on price shows separately below the base price.', 'storelly-product-builder-for-woocommerce'); ?></p>
                        </div>
                        <!-- Hide zero-value option prices -->
                        <div class="spbwc-setting-row">
                            <div class="spbwc-setting-row__label">
                                <?php esc_html_e('Hide zero-value option prices', 'storelly-product-builder-for-woocommerce'); ?>
                            </div>
                            <div class="spbwc-setting-row__control">
                                <div class="spbwc-radio-group">
                                    <label class="spbwc-radio-group__option">
                                        <input type="radio" name="spbwc_hide_zero_price" value="yes" <?php checked($po['hide_zero_price'], 'yes'); ?> />
                                        <span class="spbwc-radio-group__lbl"><?php esc_html_e('Yes', 'storelly-product-builder-for-woocommerce'); ?></span>
                                    </label>
                                    <label class="spbwc-radio-group__option">
                                        <input type="radio" name="spbwc_hide_zero_price" value="no" <?php checked($po['hide_zero_price'], 'no'); ?> />
                                        <span class="spbwc-radio-group__lbl"><?php esc_html_e('No', 'storelly-product-builder-for-woocommerce'); ?></span>
                                    </label>
                                </div>
                            </div>
                            <p class="spbwc-setting-row__hint"><?php esc_html_e('When an option adds +$0.00, that line still appears in the price breakdown. Enable this to hide zero-value lines and keep the summary clean — especially useful when many options are included by default.', 'storelly-product-builder-for-woocommerce'); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ━━━ CATALOG ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ -->
            <div class="spbwc-tab-panel" id="tab-catalog"<?php echo ($spbwc_settings_tab !== 'catalog') ? ' style="display:none;"' : ''; ?>>
                <div class="spbwc-block">
                    <div class="spbwc-block__head">
                        <h3 class="spbwc-block__title">
                            <span class="dashicons dashicons-store" aria-hidden="true"></span>
                            <?php esc_html_e('Catalog', 'storelly-product-builder-for-woocommerce'); ?>
                        </h3>
                    </div>
                    <div class="spbwc-setting-rows">
                        <!-- Force "Select options" on product cards -->
                        <div class="spbwc-setting-row">
                            <div class="spbwc-setting-row__label">
                                <?php esc_html_e('Force "Select options" on product cards', 'storelly-product-builder-for-woocommerce'); ?>
                            </div>
                            <div class="spbwc-setting-row__control">
                                <div class="spbwc-radio-group">
                                    <label class="spbwc-radio-group__option">
                                        <input type="radio" name="spbwc_force_select_options" value="yes" <?php checked($po['force_select_options'], 'yes'); ?> />
                                        <span class="spbwc-radio-group__lbl"><?php esc_html_e('Yes', 'storelly-product-builder-for-woocommerce'); ?></span>
                                    </label>
                                    <label class="spbwc-radio-group__option">
                                        <input type="radio" name="spbwc_force_select_options" value="no" <?php checked($po['force_select_options'], 'no'); ?> />
                                        <span class="spbwc-radio-group__lbl"><?php esc_html_e('No', 'storelly-product-builder-for-woocommerce'); ?></span>
                                    </label>
                                </div>
                            </div>
                            <p class="spbwc-setting-row__hint"><?php esc_html_e('Replaces "Add to cart" with "Select options" on shop/category listing pages for products that have required options. Prevents buyers from bypassing the option form directly from the grid view.', 'storelly-product-builder-for-woocommerce'); ?></p>
                        </div>
                        <!-- Show option swatches on shop grid -->
                        <div class="spbwc-setting-row">
                            <div class="spbwc-setting-row__label">
                                <?php esc_html_e('Show option swatches on shop grid', 'storelly-product-builder-for-woocommerce'); ?>
                            </div>
                            <div class="spbwc-setting-row__control">
                                <div class="spbwc-radio-group">
                                    <label class="spbwc-radio-group__option">
                                        <input type="radio" name="spbwc_show_options_in_archive_pages" value="yes" <?php checked($po['show_options_in_archive_pages'], 'yes'); ?> />
                                        <span class="spbwc-radio-group__lbl"><?php esc_html_e('Yes', 'storelly-product-builder-for-woocommerce'); ?></span>
                                    </label>
                                    <label class="spbwc-radio-group__option">
                                        <input type="radio" name="spbwc_show_options_in_archive_pages" value="no" <?php checked($po['show_options_in_archive_pages'], 'no'); ?> />
                                        <span class="spbwc-radio-group__lbl"><?php esc_html_e('No', 'storelly-product-builder-for-woocommerce'); ?></span>
                                    </label>
                                </div>
                            </div>
                            <p class="spbwc-setting-row__hint"><?php esc_html_e('Renders option swatches (colour/material pickers) directly on product cards in the shop grid. Buyers can pre-select variants before clicking through to the product page — improves engagement and conversion.', 'storelly-product-builder-for-woocommerce'); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ━━━ CART & ORDER ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ -->
            <div class="spbwc-tab-panel" id="tab-cart"<?php echo ($spbwc_settings_tab !== 'cart') ? ' style="display:none;"' : ''; ?>>
                <div class="spbwc-block">
                    <div class="spbwc-block__head">
                        <h3 class="spbwc-block__title">
                            <span class="dashicons dashicons-cart" aria-hidden="true"></span>
                            <?php esc_html_e('Cart &amp; Order', 'storelly-product-builder-for-woocommerce'); ?>
                        </h3>
                    </div>
                    <div class="spbwc-setting-rows">
                        <!-- AJAX add to cart -->
                        <div class="spbwc-setting-row">
                            <div class="spbwc-setting-row__label">
                                <?php esc_html_e('AJAX add to cart', 'storelly-product-builder-for-woocommerce'); ?>
                            </div>
                            <div class="spbwc-setting-row__control">
                                <div class="spbwc-radio-group">
                                    <label class="spbwc-radio-group__option">
                                        <input type="radio" name="spbwc_enable_ajax_cart" value="yes" <?php checked($po['enable_ajax_cart'], 'yes'); ?> />
                                        <span class="spbwc-radio-group__lbl"><?php esc_html_e('Yes', 'storelly-product-builder-for-woocommerce'); ?></span>
                                    </label>
                                    <label class="spbwc-radio-group__option">
                                        <input type="radio" name="spbwc_enable_ajax_cart" value="no" <?php checked($po['enable_ajax_cart'], 'no'); ?> />
                                        <span class="spbwc-radio-group__lbl"><?php esc_html_e('No', 'storelly-product-builder-for-woocommerce'); ?></span>
                                    </label>
                                </div>
                            </div>
                            <p class="spbwc-setting-row__hint"><?php esc_html_e('Adds to cart without a full page reload — faster, smoother buyer experience. Disable only if your theme or another plugin conflicts with the cart counter when using AJAX.', 'storelly-product-builder-for-woocommerce'); ?></p>
                        </div>
                        <!-- Disable persistent cart storage -->
                        <div class="spbwc-setting-row">
                            <div class="spbwc-setting-row__label">
                                <?php esc_html_e('Disable persistent cart storage', 'storelly-product-builder-for-woocommerce'); ?>
                            </div>
                            <div class="spbwc-setting-row__control">
                                <div class="spbwc-radio-group">
                                    <label class="spbwc-radio-group__option">
                                        <input type="radio" name="spbwc_turn_off_persistent_cart" value="yes" <?php checked($po['turn_off_persistent_cart'], 'yes'); ?> />
                                        <span class="spbwc-radio-group__lbl"><?php esc_html_e('Yes', 'storelly-product-builder-for-woocommerce'); ?></span>
                                    </label>
                                    <label class="spbwc-radio-group__option">
                                        <input type="radio" name="spbwc_turn_off_persistent_cart" value="no" <?php checked($po['turn_off_persistent_cart'], 'no'); ?> />
                                        <span class="spbwc-radio-group__lbl"><?php esc_html_e('No', 'storelly-product-builder-for-woocommerce'); ?></span>
                                    </label>
                                </div>
                            </div>
                            <p class="spbwc-setting-row__hint"><?php esc_html_e('WooCommerce saves cart contents to the database for logged-in users. For products with hundreds of custom options, this cart data can grow very large. Enable to skip database storage and reduce load — recommended for complex print products.', 'storelly-product-builder-for-woocommerce'); ?></p>
                        </div>
                        <!-- Show "Clear cart" button -->
                        <div class="spbwc-setting-row">
                            <div class="spbwc-setting-row__label">
                                <?php esc_html_e('Show "Clear cart" button', 'storelly-product-builder-for-woocommerce'); ?>
                            </div>
                            <div class="spbwc-setting-row__control">
                                <div class="spbwc-radio-group">
                                    <label class="spbwc-radio-group__option">
                                        <input type="radio" name="spbwc_enable_clear_cart_button" value="yes" <?php checked($po['enable_clear_cart_button'], 'yes'); ?> />
                                        <span class="spbwc-radio-group__lbl"><?php esc_html_e('Yes', 'storelly-product-builder-for-woocommerce'); ?></span>
                                    </label>
                                    <label class="spbwc-radio-group__option">
                                        <input type="radio" name="spbwc_enable_clear_cart_button" value="no" <?php checked($po['enable_clear_cart_button'], 'no'); ?> />
                                        <span class="spbwc-radio-group__lbl"><?php esc_html_e('No', 'storelly-product-builder-for-woocommerce'); ?></span>
                                    </label>
                                </div>
                            </div>
                            <p class="spbwc-setting-row__hint"><?php esc_html_e('Adds a one-click "Clear cart" button to the cart page so buyers can start fresh without removing items one by one. Especially helpful for print-on-demand stores where customers frequently rebuild their order from scratch.', 'storelly-product-builder-for-woocommerce'); ?></p>
                        </div>
                        <!-- Hide option details in cart -->
                        <div class="spbwc-setting-row">
                            <div class="spbwc-setting-row__label">
                                <?php esc_html_e('Hide option details in cart', 'storelly-product-builder-for-woocommerce'); ?>
                            </div>
                            <div class="spbwc-setting-row__control">
                                <div class="spbwc-radio-group">
                                    <label class="spbwc-radio-group__option">
                                        <input type="radio" name="spbwc_hide_options_in_cart" value="yes" <?php checked($po['hide_options_in_cart'], 'yes'); ?> />
                                        <span class="spbwc-radio-group__lbl"><?php esc_html_e('Yes', 'storelly-product-builder-for-woocommerce'); ?></span>
                                    </label>
                                    <label class="spbwc-radio-group__option">
                                        <input type="radio" name="spbwc_hide_options_in_cart" value="no" <?php checked($po['hide_options_in_cart'], 'no'); ?> />
                                        <span class="spbwc-radio-group__lbl"><?php esc_html_e('No', 'storelly-product-builder-for-woocommerce'); ?></span>
                                    </label>
                                </div>
                            </div>
                            <p class="spbwc-setting-row__hint"><?php esc_html_e('By default, the cart shows each chosen option beneath the product name (e.g. "Paper: Matte, Size: A4"). Hide when option names are technical or internal and not meaningful to the buyer — keeps cart line items clean and readable.', 'storelly-product-builder-for-woocommerce'); ?></p>
                        </div>
                        <!-- Hide option add-on prices in cart -->
                        <div class="spbwc-setting-row">
                            <div class="spbwc-setting-row__label">
                                <?php esc_html_e('Hide option add-on prices in cart', 'storelly-product-builder-for-woocommerce'); ?>
                            </div>
                            <div class="spbwc-setting-row__control">
                                <div class="spbwc-radio-group">
                                    <label class="spbwc-radio-group__option">
                                        <input type="radio" name="spbwc_hide_option_price_in_cart" value="yes" <?php checked($po['hide_option_price_in_cart'], 'yes'); ?> />
                                        <span class="spbwc-radio-group__lbl"><?php esc_html_e('Yes', 'storelly-product-builder-for-woocommerce'); ?></span>
                                    </label>
                                    <label class="spbwc-radio-group__option">
                                        <input type="radio" name="spbwc_hide_option_price_in_cart" value="no" <?php checked($po['hide_option_price_in_cart'], 'no'); ?> />
                                        <span class="spbwc-radio-group__lbl"><?php esc_html_e('No', 'storelly-product-builder-for-woocommerce'); ?></span>
                                    </label>
                                </div>
                            </div>
                            <p class="spbwc-setting-row__hint"><?php esc_html_e('Hides the per-option price breakdown in the cart (e.g. "+$2.00 for Matte finish" disappears). The line item total and order total still include all costs — only the individual add-on amounts are hidden. Useful when showing a breakdown could confuse buyers or reveal internal pricing logic.', 'storelly-product-builder-for-woocommerce'); ?></p>
                        </div>
                        <!-- Hide option add-on prices in orders & emails -->
                        <div class="spbwc-setting-row">
                            <div class="spbwc-setting-row__label">
                                <?php esc_html_e('Hide option add-on prices in orders &amp; emails', 'storelly-product-builder-for-woocommerce'); ?>
                            </div>
                            <div class="spbwc-setting-row__control">
                                <div class="spbwc-radio-group">
                                    <label class="spbwc-radio-group__option">
                                        <input type="radio" name="spbwc_hide_option_price_in_order" value="yes" <?php checked($po['hide_option_price_in_order'], 'yes'); ?> />
                                        <span class="spbwc-radio-group__lbl"><?php esc_html_e('Yes', 'storelly-product-builder-for-woocommerce'); ?></span>
                                    </label>
                                    <label class="spbwc-radio-group__option">
                                        <input type="radio" name="spbwc_hide_option_price_in_order" value="no" <?php checked($po['hide_option_price_in_order'], 'no'); ?> />
                                        <span class="spbwc-radio-group__lbl"><?php esc_html_e('No', 'storelly-product-builder-for-woocommerce'); ?></span>
                                    </label>
                                </div>
                            </div>
                            <p class="spbwc-setting-row__hint"><?php esc_html_e('Hides per-option pricing from order confirmation pages, customer emails, and PDF invoices. The order total remains accurate — only the breakdown by option is hidden. Recommended for quote-based workflows or when add-on pricing is internal-only and should not appear on customer-facing documents.', 'storelly-product-builder-for-woocommerce'); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="spbwc-block" style="margin-top:var(--nbd-space-4);">
                <div class="spbwc-block__foot">
                    <button name="save" class="spbwc-cta-btn spbwc-cta-btn--solid" type="submit" value="Save changes">
                        <span class="dashicons dashicons-saved" aria-hidden="true"></span>
                        <?php esc_html_e('Save changes', 'storelly-product-builder-for-woocommerce'); ?>
                    </button>
                    <input type="hidden" name="_action_storelly_settings" value="submit">
                    <?php wp_nonce_field( 'spbwc_settings_action', 'spbwc_settings_nonce' ); ?>
                </div>
            </div>
        </form>
</div>
<script>
(function () {
    'use strict';

    /* ── i18n ─────────────────────────────────────────────────── */
    var i18n = {
        saving:  <?php echo wp_json_encode( __( 'Saving…', 'storelly-product-builder-for-woocommerce' ) ); ?>,
        saved:   <?php echo wp_json_encode( __( 'Settings saved successfully.', 'storelly-product-builder-for-woocommerce' ) ); ?>,
        failed:  <?php echo wp_json_encode( __( 'Save failed. Please try again.', 'storelly-product-builder-for-woocommerce' ) ); ?>,
        network: <?php echo wp_json_encode( __( 'Network error. Please check your connection.', 'storelly-product-builder-for-woocommerce' ) ); ?>
    };

    /* ── Toast ────────────────────────────────────────────────── */
    function spbwcToast( type, msg ) {
        var prev = document.getElementById( 'spbwc-toast' );
        if ( prev ) { clearTimeout( prev._t ); prev.remove(); }

        var el = document.createElement( 'div' );
        el.id = 'spbwc-toast';
        el.className = 'spbwc-toast spbwc-toast--' + type;
        el.innerHTML =
            '<span class="dashicons ' +
            ( type === 'success' ? 'dashicons-yes-alt' : 'dashicons-warning' ) +
            ' spbwc-toast__icon" aria-hidden="true"></span>' +
            '<span>' + msg + '</span>' +
            '<button class="spbwc-toast__close" aria-label="Close">×</button>';

        document.body.appendChild( el );
        el.querySelector( '.spbwc-toast__close' ).onclick = function () { spbwcDismiss( el ); };
        requestAnimationFrame( function () {
            requestAnimationFrame( function () { el.classList.add( 'is-visible' ); } );
        } );
        el._t = setTimeout( function () { spbwcDismiss( el ); }, 4500 );
    }

    function spbwcDismiss( el ) {
        el.classList.remove( 'is-visible' );
        setTimeout( function () { if ( el.parentNode ) { el.remove(); } }, 400 );
    }

    /* ── AJAX save ────────────────────────────────────────────── */
    function spbwcAjaxSave( form, btn ) {
        var origHTML = btn.innerHTML;
        btn.classList.add( 'is-saving' );
        btn.innerHTML = '<span class="dashicons dashicons-update"></span> ' + i18n.saving;

        fetch( form.getAttribute( 'action' ) || window.location.href, {
            method: 'POST',
            body: new FormData( form ),
            credentials: 'same-origin'
        } )
        .then( function ( r ) { return r.text(); } )
        .then( function ( html ) {
            btn.classList.remove( 'is-saving' );
            btn.innerHTML = origHTML;
            var doc = ( new DOMParser() ).parseFromString( html, 'text/html' );
            if ( doc.querySelector( '.notice-error, .error' ) ) {
                var errEl = doc.querySelector( '.notice-error p, .error p' );
                spbwcToast( 'error', errEl ? errEl.textContent.trim() : i18n.failed );
            } else {
                spbwcToast( 'success', i18n.saved );
            }
        } )
        .catch( function () {
            btn.classList.remove( 'is-saving' );
            btn.innerHTML = origHTML;
            spbwcToast( 'error', i18n.network );
        } );
    }

    /* ── Attach AJAX save ─────────────────────────────────────── */
    var settingsForm = document.getElementById( 'spbwc-settings-form' );
    if ( settingsForm ) {
        settingsForm.addEventListener( 'submit', function ( e ) {
            e.preventDefault();
            spbwcAjaxSave( settingsForm, settingsForm.querySelector( 'button[type="submit"]' ) );
        } );
    }

    /* ── Tab switching ────────────────────────────────────────── */
    var nav = document.getElementById( 'spbwc-settings-nav' );
    if ( nav ) {
        nav.addEventListener( 'click', function ( e ) {
            var link = e.target.closest( '[data-tab]' );
            if ( ! link ) { return; }
            e.preventDefault();
            var target = link.getAttribute( 'data-tab' );

            nav.querySelectorAll( '.nav-tab' ).forEach( function ( t ) {
                t.classList.toggle( 'nav-tab-active', t === link );
            } );
            document.querySelectorAll( '.spbwc-tab-panel' ).forEach( function ( p ) {
                p.style.display = ( p.id === 'tab-' + target ) ? '' : 'none';
            } );
            if ( settingsForm ) {
                var action = settingsForm.getAttribute( 'action' );
                settingsForm.setAttribute( 'action', action.replace( /([?&]tab=)[^&]+/, '$1' + target ) );
            }
            if ( history.replaceState ) {
                var url = new URL( location.href );
                url.searchParams.set( 'tab', target );
                history.replaceState( null, '', url.toString() );
            }
        } );
    }

}());
</script>
