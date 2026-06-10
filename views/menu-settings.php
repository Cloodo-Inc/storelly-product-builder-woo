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
$spbwc_valid_tabs = array('pricing-option', 'display', 'pricing', 'catalog', 'cart', 'integration', 'storefront');
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only settings tab selector; validated against whitelist below, no state change.
$spbwc_settings_tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'pricing-option';
if ( ! in_array($spbwc_settings_tab, $spbwc_valid_tabs, true) ) {
    $spbwc_settings_tab = 'pricing-option';
}
?>

<div class="wrap spbwc-settings-wrap">

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
                <?php
                // Preview Welcome guide (Wave 2, item 7 / M10). Cap-gated; opens the
                // Overview in welcome-mode via the ?spbwc-welcome=1 query flag, which
                // is read-only — it does NOT change dismissed/onboarding-complete
                // state (see SPBWC_Onboarding::is_welcome_mode()).
                if ( current_user_can( 'manage_options' ) && defined( 'SPBWC_PB_OVERVIEW_SLUG' ) ) :
                    $spbwc_welcome_preview_url = add_query_arg(
                        array( 'page' => SPBWC_PB_OVERVIEW_SLUG, 'spbwc-welcome' => 1 ),
                        admin_url( 'admin.php' )
                    );
                ?>
                <a href="<?php echo esc_url( $spbwc_welcome_preview_url ); ?>"
                   class="spbwc-cta-btn spbwc-cta-btn--ghost">
                    <span class="dashicons dashicons-visibility" aria-hidden="true"></span>
                    <?php esc_html_e( 'Preview Welcome guide', 'storelly-product-builder-for-woocommerce' ); ?>
                </a>
                <?php endif; ?>
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
            <a href="<?php echo esc_url(admin_url('admin.php?page=' . SPBWC_PB_OPTIONS_SLUG . '&tab=pricing-option')); ?>"
               data-tab="pricing-option" class="nav-tab <?php echo $spbwc_settings_tab === 'pricing-option' ? 'nav-tab-active' : ''; ?>">
                <span class="dashicons dashicons-money-alt" aria-hidden="true"></span>
                <?php esc_html_e('Pricing Options', 'storelly-product-builder-for-woocommerce'); ?>
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
            <a href="<?php echo esc_url(admin_url('admin.php?page=' . SPBWC_PB_OPTIONS_SLUG . '&tab=integration')); ?>"
               data-tab="integration" class="nav-tab <?php echo $spbwc_settings_tab === 'integration' ? 'nav-tab-active' : ''; ?>">
                <span class="dashicons dashicons-admin-network" aria-hidden="true"></span>
                <?php esc_html_e('Integration', 'storelly-product-builder-for-woocommerce'); ?>
            </a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=' . SPBWC_PB_OPTIONS_SLUG . '&tab=storefront')); ?>"
               data-tab="storefront" class="nav-tab <?php echo $spbwc_settings_tab === 'storefront' ? 'nav-tab-active' : ''; ?>">
                <span class="dashicons dashicons-admin-appearance" aria-hidden="true"></span>
                <?php esc_html_e('Storefront', 'storelly-product-builder-for-woocommerce'); ?>
            </a>
        </h2>

        <!-- Standalone form for the reversible Cart-mode switch (kept OUTSIDE the main
             settings form; the buttons in the User Account panel target it via form="…"). -->
        <form method="post" id="spbwc-cart-mode-form"
              action="<?php echo esc_url(admin_url('admin.php?page=' . SPBWC_PB_OPTIONS_SLUG . '&tab=storefront')); ?>">
            <?php wp_nonce_field( 'spbwc_cart_mode_action', 'spbwc_cart_mode_nonce' ); ?>
        </form>

        <form class="storelly-form" method="post" id="spbwc-settings-form"
              action="<?php echo esc_url(admin_url('admin.php?page=' . SPBWC_PB_OPTIONS_SLUG . '&tab=' . $spbwc_settings_tab)); ?>"
              enctype="multipart/form-data">

            <!-- ━━━ PRICING OPTION ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ -->
            <div class="spbwc-tab-panel" id="tab-pricing-option"<?php echo ($spbwc_settings_tab !== 'pricing-option') ? ' style="display:none;"' : ''; ?>>

                <div class="spbwc-block">
                    <div class="spbwc-block__head">
                        <h3 class="spbwc-block__title">
                            <span class="dashicons dashicons-money-alt" aria-hidden="true"></span>
                            <?php esc_html_e('Pricing Options', 'storelly-product-builder-for-woocommerce'); ?>
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
                                    <?php foreach ( array( 'top' => __('Top','storelly-product-builder-for-woocommerce'), 'right' => __('Right','storelly-product-builder-for-woocommerce'), 'bottom' => __('Bottom','storelly-product-builder-for-woocommerce'), 'left' => __('Left','storelly-product-builder-for-woocommerce') ) as $val => $lbl ) : // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- loop vars ?>
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

            <!-- ━━━ INTEGRATION ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ -->
            <div class="spbwc-tab-panel" id="tab-integration"<?php echo ($spbwc_settings_tab !== 'integration') ? ' style="display:none;"' : ''; ?>>

                <?php
                // ── Unified "Storelly Account" component (M5.9, Wave 2 item 4) ──
                // Single home for connecting/disconnecting/linking the store to the
                // Storelly cloud. Reuses SPBWC_Cloud_Connect (connect/disconnect/
                // link_manual). No network call happens until the merchant clicks
                // Enable Cloud — the AJAX path is the only one that phones home.
                // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variable.
                $spbwc_acct_connected = class_exists( 'SPBWC_Cloud_Connect' ) && SPBWC_Cloud_Connect::is_connected();
                // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variable.
                $spbwc_acct_nonce     = wp_create_nonce( 'spbwc_cloud_connect' );
                // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variable.
                $spbwc_acct_privacy   = 'https://storelly.com/privacy';
                // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variable.
                $spbwc_acct_pdf_on    = isset( $storelly_pb_settings['enable_cloud2print_api'] ) && 'yes' === $storelly_pb_settings['enable_cloud2print_api'];
                // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variable.
                $spbwc_acct_sync_on   = isset( $storelly_pb_settings['enable_api_sync'] ) && 'yes' === $storelly_pb_settings['enable_api_sync'];
                ?>
                <div class="spbwc-block<?php echo $spbwc_acct_connected ? ' spbwc-block--success' : ''; ?> spbwc-account-card"
                     id="spbwc-account-card" data-nonce="<?php echo esc_attr( $spbwc_acct_nonce ); ?>">
                    <div class="spbwc-block__head">
                        <h3 class="spbwc-block__title">
                            <span class="dashicons dashicons-cloud" aria-hidden="true"></span>
                            <?php esc_html_e( 'Storelly Account', 'storelly-product-builder-for-woocommerce' ); ?>
                        </h3>
                        <?php if ( $spbwc_acct_connected ) : ?>
                        <span class="spbwc-block__badge">
                            <span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
                            <?php esc_html_e( 'Connected', 'storelly-product-builder-for-woocommerce' ); ?>
                        </span>
                        <?php else : ?>
                        <span class="spbwc-block__badge spbwc-block__badge--muted">
                            <span class="dashicons dashicons-minus" aria-hidden="true"></span>
                            <?php esc_html_e( 'Not connected', 'storelly-product-builder-for-woocommerce' ); ?>
                        </span>
                        <?php endif; ?>
                    </div>

                    <div class="spbwc-setting-rows">
                        <?php if ( $spbwc_acct_connected ) : ?>
                            <!-- ── State: CONNECTED ────────────────────────── -->
                            <p class="spbwc-setting-row__hint" style="margin-top:0;">
                                <?php esc_html_e( 'Your store is connected to Storelly Cloud. Turn Cloud features on or off in the “Cloud features” card below, and compare plans to unlock premium tools.', 'storelly-product-builder-for-woocommerce' ); ?>
                            </p>
                            <div class="spbwc-setting-row">
                                <?php if ( $storelly_username ) : ?>
                                <div class="spbwc-setting-row__label"><?php esc_html_e( 'Account', 'storelly-product-builder-for-woocommerce' ); ?></div>
                                <div class="spbwc-setting-row__control"><?php echo esc_html( $storelly_username ); ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="spbwc-setting-row">
                                <div class="spbwc-setting-row__label"><?php esc_html_e( 'Store URL', 'storelly-product-builder-for-woocommerce' ); ?></div>
                                <div class="spbwc-setting-row__control"><?php echo esc_html( home_url() ); ?></div>
                            </div>
                            <div class="spbwc-setting-row">
                                <div class="spbwc-setting-row__label"><?php esc_html_e( 'Quick links', 'storelly-product-builder-for-woocommerce' ); ?></div>
                                <div class="spbwc-setting-row__control spbwc-action-btns">
                                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . SPBWC_PB_LICENSE_SLUG ) ); ?>" class="spbwc-cta-btn spbwc-cta-btn--solid">
                                        <span class="dashicons dashicons-awards" aria-hidden="true"></span>
                                        <?php esc_html_e( 'Compare plans &amp; upgrade', 'storelly-product-builder-for-woocommerce' ); ?>
                                    </a>
                                    <a href="https://app.storelly.com/login?redirect=woocomerce" target="_blank" rel="noopener noreferrer" class="spbwc-cta-btn spbwc-cta-btn--ghost">
                                        <span class="dashicons dashicons-external" aria-hidden="true"></span>
                                        <?php esc_html_e( 'Open Storelly dashboard', 'storelly-product-builder-for-woocommerce' ); ?>
                                    </a>
                                </div>
                                <p class="spbwc-setting-row__hint"><?php esc_html_e( 'See which Cloud features each plan unlocks, manage billing, or open your dashboard at app.storelly.com.', 'storelly-product-builder-for-woocommerce' ); ?></p>
                            </div>
                            <div class="spbwc-setting-row">
                                <div class="spbwc-setting-row__control">
                                    <button type="button" class="spbwc-cta-btn spbwc-cta-btn--ghost" id="spbwc-account-disconnect">
                                        <span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
                                        <?php esc_html_e( 'Disconnect', 'storelly-product-builder-for-woocommerce' ); ?>
                                    </button>
                                </div>
                                <p class="spbwc-setting-row__hint"><?php esc_html_e( 'Disconnecting stops PDF rendering and order sync. No further data leaves your store. You can reconnect anytime.', 'storelly-product-builder-for-woocommerce' ); ?></p>
                            </div>
                        <?php else : ?>
                            <!-- ── State: NOT CONNECTED ────────────────────── -->
                            <p class="spbwc-setting-row__hint" style="margin-top:0;">
                                <?php esc_html_e( 'The product builder, pricing options, quotes and orders all run locally in your WordPress — free, with no account needed. Connect to add print-ready PDF rendering and a central dashboard at app.storelly.com.', 'storelly-product-builder-for-woocommerce' ); ?>
                            </p>
                            <p class="spbwc-setting-row__hint">
                                <?php
                                printf(
                                    /* translators: %s: link to the privacy policy. */
                                    wp_kses(
                                        /* translators: %s: link to the privacy policy. */
                                        __( 'On connect we share your admin email, store URL and a store ID with Storelly to create your profile. Nothing is sent before you click. <a href="%s" target="_blank" rel="noopener noreferrer">Privacy</a>.', 'storelly-product-builder-for-woocommerce' ),
                                        array( 'a' => array( 'href' => array(), 'target' => array(), 'rel' => array() ) )
                                    ),
                                    esc_url( $spbwc_acct_privacy )
                                );
                                ?>
                            </p>
                            <div class="spbwc-setting-row">
                                <div class="spbwc-setting-row__control spbwc-action-btns">
                                    <button type="button" class="spbwc-cta-btn spbwc-cta-btn--solid" id="spbwc-account-connect">
                                        <span class="dashicons dashicons-cloud" aria-hidden="true"></span>
                                        <?php esc_html_e( 'Connect to Storelly — free', 'storelly-product-builder-for-woocommerce' ); ?>
                                    </button>
                                    <button type="button" class="spbwc-cta-btn spbwc-cta-btn--link" id="spbwc-account-manual-toggle">
                                        <?php esc_html_e( 'Already have an account? Link with Store ID', 'storelly-product-builder-for-woocommerce' ); ?>
                                    </button>
                                </div>
                            </div>
                            <div class="spbwc-setting-row" id="spbwc-account-manual-box" hidden>
                                <div class="spbwc-setting-row__label">
                                    <label for="spbwc-account-store-id"><?php esc_html_e( 'Store ID', 'storelly-product-builder-for-woocommerce' ); ?></label>
                                </div>
                                <div class="spbwc-setting-row__control spbwc-action-btns">
                                    <input type="text" id="spbwc-account-store-id" class="spbwc-input"
                                           placeholder="<?php esc_attr_e( 'Store ID from your Storelly dashboard', 'storelly-product-builder-for-woocommerce' ); ?>" />
                                    <button type="button" class="spbwc-cta-btn" id="spbwc-account-link">
                                        <?php esc_html_e( 'Save', 'storelly-product-builder-for-woocommerce' ); ?>
                                    </button>
                                </div>
                                <p class="spbwc-setting-row__hint"><?php esc_html_e( 'Find the Store ID in your Storelly dashboard, under Settings then API Keys. Use this only if you already created an account.', 'storelly-product-builder-for-woocommerce' ); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- ── Block: Cloud features (what to share with Storelly) ─────── -->
                <div class="spbwc-block">
                    <div class="spbwc-block__head">
                        <h3 class="spbwc-block__title">
                            <span class="dashicons dashicons-cloud" aria-hidden="true"></span>
                            <?php esc_html_e('Cloud features', 'storelly-product-builder-for-woocommerce'); ?>
                        </h3>
                    </div>
                    <div class="spbwc-setting-rows">

                        <?php if ( ! $spbwc_acct_connected ) : ?>
                        <p class="spbwc-setting-row__hint">
                            <span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
                            <?php esc_html_e( 'Connect your Storelly account above first — these switches only take effect once your store is connected.', 'storelly-product-builder-for-woocommerce' ); ?>
                        </p>
                        <?php endif; ?>

                        <!-- Generate print-ready PDFs -->
                        <div class="spbwc-setting-row">
                            <div class="spbwc-setting-row__label">
                                <?php esc_html_e('Generate print-ready PDFs', 'storelly-product-builder-for-woocommerce'); ?>
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

                        <!-- Dashboard API sync opt-in -->
                        <?php
                        $spbwc_stt_yes_api_sync = isset($storelly_pb_settings['enable_api_sync']) && $storelly_pb_settings['enable_api_sync'] == 'yes' ? 'checked' : '';
                        $spbwc_stt_no_api_sync  = isset($storelly_pb_settings['enable_api_sync']) && $storelly_pb_settings['enable_api_sync'] == 'no'  ? 'checked' : '';
                        if ( empty( $spbwc_stt_yes_api_sync ) && empty( $spbwc_stt_no_api_sync ) ) {
                            $spbwc_stt_no_api_sync = 'checked';
                        }
                        ?>
                        <div class="spbwc-setting-row">
                            <div class="spbwc-setting-row__label">
                                <?php esc_html_e('Sync orders to your Storelly dashboard', 'storelly-product-builder-for-woocommerce'); ?>
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
                            <p class="spbwc-setting-row__hint"><?php esc_html_e('Opt-in to sync order data with Storelly Dashboard for centralised print job management. Disabled by default — no data leaves your store until you enable this.', 'storelly-product-builder-for-woocommerce'); ?></p>
                        </div>

                    </div>
                </div>

                <!-- ── Advanced: manual link with API keys & diagnostics (collapsed) ──────── -->
                <?php // Badge follows the same "connected" signal as the Storelly Account
                      // card above (SPBWC_Cloud_Connect::is_connected) so the two never disagree. ?>
                <details class="spbwc-advanced">
                    <summary class="spbwc-advanced__summary">
                        <span class="dashicons dashicons-admin-tools" aria-hidden="true"></span>
                        <?php esc_html_e( 'Advanced — link manually with API keys, login &amp; connection log', 'storelly-product-builder-for-woocommerce' ); ?>
                    </summary>
                <div class="spbwc-block<?php echo $spbwc_acct_connected ? ' spbwc-block--success' : ''; ?>">
                    <div class="spbwc-block__head">
                        <h3 class="spbwc-block__title">
                            <span class="dashicons dashicons-admin-network" aria-hidden="true"></span>
                            <?php esc_html_e('API Keys', 'storelly-product-builder-for-woocommerce'); ?>
                        </h3>
                        <?php if ( $spbwc_acct_connected ) : ?>
                        <span class="spbwc-block__badge">
                            <span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
                            <?php esc_html_e('Connected', 'storelly-product-builder-for-woocommerce'); ?>
                        </span>
                        <?php else : ?>
                        <span class="spbwc-block__badge spbwc-block__badge--muted">
                            <span class="dashicons dashicons-minus" aria-hidden="true"></span>
                            <?php esc_html_e('Not connected', 'storelly-product-builder-for-woocommerce'); ?>
                        </span>
                        <?php endif; ?>
                    </div>

                    <div class="spbwc-setting-rows">

                        <p class="spbwc-setting-row__hint" style="margin-top:0;">
                            <?php esc_html_e( 'Optional / advanced — these raw API fields are only needed if you ALREADY have a Storelly account and want to link this store manually. New users do not need to fill this in: use “Enable Cloud” in the Storelly Account card above and an account is created and connected for you automatically (you will get an email with the login). These fields connect to the Storelly cloud at app.storelly.com.', 'storelly-product-builder-for-woocommerce' ); ?>
                        </p>

                        <!-- SID (Consumer Key) -->
                        <div class="spbwc-setting-row">
                            <div class="spbwc-setting-row__label">
                                <label for="storelly_consumer_key"><?php esc_html_e('SID', 'storelly-product-builder-for-woocommerce'); ?></label>
                            </div>
                            <div class="spbwc-setting-row__control">
                                <input id="storelly_consumer_key" type="text" name="storelly_consumer_key"
                                       class="spbwc-input" placeholder="ck_xxxxx" style="width:300px;"
                                       value="<?php echo esc_attr($sid); ?>" />
                            </div>
                            <p class="spbwc-setting-row__hint"><?php esc_html_e('Your Store ID (Consumer Key). Find it in Storelly Dashboard → Settings → API Keys. Paste the exact value — no extra spaces or line breaks.', 'storelly-product-builder-for-woocommerce'); ?></p>
                        </div>

                        <!-- Secret (Consumer Secret) -->
                        <div class="spbwc-setting-row">
                            <div class="spbwc-setting-row__label">
                                <label for="storelly_consumer_secret"><?php esc_html_e('Secret', 'storelly-product-builder-for-woocommerce'); ?></label>
                            </div>
                            <div class="spbwc-setting-row__control">
                                <input id="storelly_consumer_secret" type="text" name="storelly_consumer_secret"
                                       class="spbwc-input" placeholder="cs_xxxxx" style="width:300px;"
                                       value="<?php echo esc_attr($secret); ?>" />
                            </div>
                            <p class="spbwc-setting-row__hint"><?php esc_html_e('Your Store Secret key. Keep this private — never put it in client-side code or share it publicly. Rotate it in Storelly Dashboard → Settings → API Keys if compromised.', 'storelly-product-builder-for-woocommerce'); ?></p>
                        </div>

                        <!-- Unauth Token (read-only) -->
                        <div class="spbwc-setting-row">
                            <div class="spbwc-setting-row__label">
                                <?php esc_html_e('Unauth Token', 'storelly-product-builder-for-woocommerce'); ?>
                            </div>
                            <div class="spbwc-setting-row__control">
                                <input type="text" class="spbwc-input spbwc-input-readonly" style="width:300px;"
                                       value="<?php echo esc_attr($unauth_token); ?>" readonly />
                            </div>
                            <p class="spbwc-setting-row__hint"><?php esc_html_e('Auto-generated after you save a valid SID + Secret pair. Used for unauthenticated (public) API calls. Read-only — do not edit manually.', 'storelly-product-builder-for-woocommerce'); ?></p>
                        </div>

                        <?php if ( $storelly_username ) : ?>

                        <!-- Username (read-only, only when connected) -->
                        <div class="spbwc-setting-row">
                            <div class="spbwc-setting-row__label">
                                <?php esc_html_e('Username', 'storelly-product-builder-for-woocommerce'); ?>
                            </div>
                            <div class="spbwc-setting-row__control">
                                <input type="text" class="spbwc-input spbwc-input-readonly" style="width:300px;"
                                       value="<?php echo esc_attr($storelly_username); ?>" readonly />
                            </div>
                            <p class="spbwc-setting-row__hint"><?php esc_html_e('Your Storelly account username linked to this store. Auto-populated when the API keys are validated successfully — you do not need to enter this manually.', 'storelly-product-builder-for-woocommerce'); ?></p>
                        </div>

                        <!-- Quick actions (only when connected) -->
                        <div class="spbwc-setting-row">
                            <div class="spbwc-setting-row__label">
                                <?php esc_html_e('Quick actions', 'storelly-product-builder-for-woocommerce'); ?>
                            </div>
                            <div class="spbwc-setting-row__control">
                                <div class="spbwc-action-btns">
                                    <a href="https://app.storelly.com/login?redirect=woocomerce"
                                       class="spbwc-btn spbwc-btn-primary"
                                       target="_blank" rel="noopener noreferrer">
                                        <span class="dashicons dashicons-external" aria-hidden="true"></span>
                                        <?php esc_html_e('Login to Storelly', 'storelly-product-builder-for-woocommerce'); ?>
                                    </a>
                                    <a href="<?php echo esc_url($url_new_product); ?>"
                                       class="spbwc-btn spbwc-btn-secondary">
                                        <span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
                                        <?php esc_html_e('Create first product', 'storelly-product-builder-for-woocommerce'); ?>
                                    </a>
                                </div>
                            </div>
                            <div class="spbwc-setting-row__hint spbwc-hint-cta">
                                <p class="spbwc-hint-cta__item">
                                    <strong><?php esc_html_e('Login to Storelly', 'storelly-product-builder-for-woocommerce'); ?></strong>
                                    <?php esc_html_e('— Open the Storelly Dashboard to manage print jobs, product templates, and customer orders.', 'storelly-product-builder-for-woocommerce'); ?>
                                </p>
                                <p class="spbwc-hint-cta__item">
                                    <strong><?php esc_html_e('Create first product', 'storelly-product-builder-for-woocommerce'); ?></strong>
                                    <?php esc_html_e('— Open WooCommerce Add Product. Attach a Storelly designer template to the product to enable the builder for your customers.', 'storelly-product-builder-for-woocommerce'); ?>
                                </p>
                            </div>
                        </div>

                        <?php endif; ?>

                        <!-- Connection log -->
                        <div class="spbwc-setting-row">
                            <div class="spbwc-setting-row__label">
                                <?php esc_html_e('Log', 'storelly-product-builder-for-woocommerce'); ?>
                            </div>
                            <div class="spbwc-setting-row__control">
                                <div class="spbwc-log-box" style="width:300px;">
                                    <code><?php echo esc_html($api_log); ?></code>
                                </div>
                            </div>
                            <p class="spbwc-setting-row__hint"><?php esc_html_e('Last API response from Storelly. Use this to diagnose connection issues — look for error codes or an empty value if sync is not working.', 'storelly-product-builder-for-woocommerce'); ?></p>
                        </div>

                    </div>
                </div>
                </details>

            </div>

            <!-- ━━━ STOREFRONT ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ -->
            <div class="spbwc-tab-panel" id="tab-storefront"<?php echo ($spbwc_settings_tab !== 'storefront') ? ' style="display:none;"' : ''; ?>>
                <?php
                $spbwc_cart_pid     = function_exists('wc_get_page_id') ? (int) wc_get_page_id('cart') : 0;
                $spbwc_cart_content = $spbwc_cart_pid > 0 ? (string) get_post_field('post_content', $spbwc_cart_pid) : '';
                $spbwc_is_classic   = ('' !== $spbwc_cart_content && false !== strpos($spbwc_cart_content, '[woocommerce_cart]'));
                $spbwc_ep_val = function ($k) use ($storelly_pb_settings) {
                    return (isset($storelly_pb_settings[$k]) && 'no' === $storelly_pb_settings[$k]) ? 'no' : 'yes';
                };
                ?>

                <div class="spbwc-block">
                    <div class="spbwc-block__head">
                        <h3 class="spbwc-block__title">
                            <span class="dashicons dashicons-cart" aria-hidden="true"></span>
                            <?php esc_html_e('Cart compatibility', 'storelly-product-builder-for-woocommerce'); ?>
                        </h3>
                    </div>
                    <div class="spbwc-setting-rows">
                        <div class="spbwc-setting-row">
                            <div class="spbwc-setting-row__label"><?php esc_html_e('Current Cart page', 'storelly-product-builder-for-woocommerce'); ?></div>
                            <div class="spbwc-setting-row__control">
                                <div class="spbwc-cart-mode">
                                    <span class="spbwc-cart-mode__label"><?php esc_html_e('Active:', 'storelly-product-builder-for-woocommerce'); ?></span>
                                    <?php if ($spbwc_is_classic) : ?>
                                        <span class="spbwc-pill spbwc-pill--ok"><?php esc_html_e('Classic shortcode', 'storelly-product-builder-for-woocommerce'); ?></span>
                                        <button type="submit" form="spbwc-cart-mode-form" name="spbwc_cart_action" value="to_block" class="spbwc-cta-btn spbwc-cta-btn--ghost spbwc-cta-btn--sm">
                                            <span class="dashicons dashicons-block-default" aria-hidden="true"></span>
                                            <?php esc_html_e('Switch back to Cart block', 'storelly-product-builder-for-woocommerce'); ?>
                                        </button>
                                    <?php else : ?>
                                        <span class="spbwc-pill spbwc-pill--info"><?php esc_html_e('Cart block', 'storelly-product-builder-for-woocommerce'); ?></span>
                                        <button type="submit" form="spbwc-cart-mode-form" name="spbwc_cart_action" value="to_classic" class="spbwc-cta-btn spbwc-cta-btn--ghost spbwc-cta-btn--sm">
                                            <span class="dashicons dashicons-editor-code" aria-hidden="true"></span>
                                            <?php esc_html_e('Switch to Classic cart', 'storelly-product-builder-for-woocommerce'); ?>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <p class="spbwc-setting-row__hint"><?php esc_html_e('The “Save design” link shows on both modes: classic cart via PHP, the Cart block via the built-in Store API integration. Switching is reversible — the previous Cart page content is backed up and restored on switch-back. Only switch to classic if another plugin needs it.', 'storelly-product-builder-for-woocommerce'); ?></p>
                        </div>
                    </div>
                </div>

                <div class="spbwc-block">
                    <div class="spbwc-block__head">
                        <h3 class="spbwc-block__title">
                            <span class="dashicons dashicons-saved" aria-hidden="true"></span>
                            <?php esc_html_e('Save-design entry points', 'storelly-product-builder-for-woocommerce'); ?>
                        </h3>
                    </div>
                    <div class="spbwc-setting-rows">
                        <?php
                        $spbwc_ep_rows = array(
                            'save_on_cart'    => __('On the cart (each custom item)', 'storelly-product-builder-for-woocommerce'),
                            'save_on_order'   => __('On the My Account order detail', 'storelly-product-builder-for-woocommerce'),
                            'save_on_builder' => __('On the product / builder page', 'storelly-product-builder-for-woocommerce'),
                        );
                        foreach ($spbwc_ep_rows as $spbwc_ep_key => $spbwc_ep_label) :
                            $spbwc_cur = $spbwc_ep_val($spbwc_ep_key);
                            ?>
                            <div class="spbwc-setting-row">
                                <div class="spbwc-setting-row__label"><?php echo esc_html($spbwc_ep_label); ?></div>
                                <div class="spbwc-setting-row__control">
                                    <div class="spbwc-radio-group">
                                        <label class="spbwc-radio-group__option">
                                            <input type="radio" name="storelly_<?php echo esc_attr($spbwc_ep_key); ?>" value="yes" <?php checked($spbwc_cur, 'yes'); ?> />
                                            <span class="spbwc-radio-group__lbl"><?php esc_html_e('Show', 'storelly-product-builder-for-woocommerce'); ?></span>
                                        </label>
                                        <label class="spbwc-radio-group__option">
                                            <input type="radio" name="storelly_<?php echo esc_attr($spbwc_ep_key); ?>" value="no" <?php checked($spbwc_cur, 'no'); ?> />
                                            <span class="spbwc-radio-group__lbl"><?php esc_html_e('Hide', 'storelly-product-builder-for-woocommerce'); ?></span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="spbwc-block spbwc-settings-save-bar">
                <div class="spbwc-block__foot">
                    <button id="spbwc-settings-save" name="save" class="spbwc-cta-btn spbwc-cta-btn--solid" type="submit" value="Save changes">
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
            // Target the button the user actually clicked. Fall back to the
            // dedicated Save button — never the cart-mode submit buttons that
            // also live inside this form (they post to #spbwc-cart-mode-form),
            // which would otherwise swallow the spinner/feedback (the Save
            // button looked "dead").
            var btn = e.submitter || document.getElementById( 'spbwc-settings-save' );
            spbwcAjaxSave( settingsForm, btn );
        } );
    }

    /* ── Storelly Account component (M5.9) ────────────────────── */
    var acctCard = document.getElementById( 'spbwc-account-card' );
    if ( acctCard && window.jQuery ) {
        ( function ( $ ) {
            var nonce = acctCard.getAttribute( 'data-nonce' );
            function send( action, extra, btn ) {
                var data = $.extend( { action: action, nonce: nonce }, extra || {} );
                if ( btn ) { $( btn ).addClass( 'is-loading' ).prop( 'disabled', true ); }
                $.post( window.ajaxurl, data, function ( res ) {
                    if ( res && res.success ) {
                        window.location.reload();
                    } else {
                        spbwcToast( 'error', ( res && res.data && res.data.message ) ? res.data.message : i18n.failed );
                        if ( btn ) { $( btn ).removeClass( 'is-loading' ).prop( 'disabled', false ); }
                    }
                } ).fail( function () {
                    spbwcToast( 'error', i18n.network );
                    if ( btn ) { $( btn ).removeClass( 'is-loading' ).prop( 'disabled', false ); }
                } );
            }
            $( '#spbwc-account-connect' ).on( 'click', function () {
                send( 'spbwc_cloud_connect', {}, this );
            } );
            $( '#spbwc-account-disconnect' ).on( 'click', function () {
                var self = this;
                var msg = <?php echo wp_json_encode( __( 'Disconnect from Storelly Cloud? PDF rendering and order sync will stop.', 'storelly-product-builder-for-woocommerce' ) ); ?>;
                var ask = window.spbwcDialog
                    ? window.spbwcDialog.confirm( { message: msg, tone: 'danger', okText: <?php echo wp_json_encode( __( 'Disconnect', 'storelly-product-builder-for-woocommerce' ) ); ?> } )
                    : Promise.resolve( window.confirm( msg ) );
                ask.then( function ( ok ) {
                    if ( ! ok ) { return; }
                    send( 'spbwc_cloud_disconnect', {}, self );
                } );
            } );
            $( '#spbwc-account-manual-toggle' ).on( 'click', function () {
                var box = document.getElementById( 'spbwc-account-manual-box' );
                if ( box ) { box.hidden = ! box.hidden; }
            } );
            $( '#spbwc-account-link' ).on( 'click', function () {
                var id = $.trim( $( '#spbwc-account-store-id' ).val() || '' );
                if ( ! id ) { return; }
                send( 'spbwc_cloud_link_manual', { store_id: id }, this );
            } );
        }( window.jQuery ) );
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
