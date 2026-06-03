<?php if (!defined('ABSPATH')) exit; ?>
<?php
$link = add_query_arg(array(
    'paged'    => isset($_GET['paged']) ? sanitize_text_field( wp_unslash( $_GET['paged'] ) ) : 1 // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin query arg used to build navigation link.
), admin_url('admin.php?page=' . SPBWC_PB_BUILDER_SLUG));
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variable.
$link_update = add_query_arg(array(
    'action'    => 'update',
    'id'        => $options['id'],
), admin_url('admin.php?page=' . SPBWC_PB_BUILDER_SLUG));
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variable.
$link_unpublish = add_query_arg(array(
    'id'        => isset($options['id']) ? absint( $options['id'] ) : 0,
    'action'    => 'unpublish',
    '_wpnonce'  => wp_create_nonce('spbwc_unpublish_option_action'),
), $link);
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variable.
$link_create_option = add_query_arg(
    array(
        'action'    => 'create',
        'paged'     => 1,
        'id'        => 0
    ),
    admin_url('admin.php?page=' . SPBWC_PB_BUILDER_SLUG)
);
wp_enqueue_media();

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variable.
$link_create_pre_builder = add_query_arg(array(
    'oid'   => isset($options['id']) ? absint( $options['id'] ) : 0,
    'paged' => isset($_GET['paged']) ? sanitize_text_field( wp_unslash( $_GET['paged'] ) ) : 1, // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin query arg used to build action link.
    'rd'      => 'print_option',
    '_wpnonce' => wp_create_nonce( 'spbwc_builder_preview_action' ),
), SPBWC_Storelly_PB_Util::spbwc_get_url_page('product_builder'));

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variable.
$current_id   = isset($options['id']) ? $options['id'] : 0;
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variable.
$is_new_option = ( $current_id == 0 );
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variable.
$applied_count = isset($options['product_ids']) && is_array($options['product_ids']) ? count($options['product_ids']) : 0;
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variable.
$field_count   = isset($options['fields']) && is_array($options['fields']) ? count($options['fields']) : 0;
?>
<div class="wrap spbwc-edit-v2" ng-app="optionApp" ng-cloak>
    <div ng-controller="optionCtrl">

        <!-- ──────────────── Breadcrumb back-bar ────────────────
             Hidden when the merchant arrived from a product metabox — the
             dispatcher already renders a product-context breadcrumb above this
             wrap (with "Back to product"). See docs/SPEC_LINKED_PRODUCT_UX.md §4. -->
        <?php if ( empty( $product_id ) ) : ?>
        <nav class="v2-backbar" aria-label="<?php esc_attr_e('Breadcrumb', 'storelly-product-builder-for-woocommerce'); ?>">
            <a href="<?php echo esc_url($link); ?>">
                <span class="dashicons dashicons-arrow-left-alt" aria-hidden="true"></span>
                <?php esc_html_e('Pricing Options', 'storelly-product-builder-for-woocommerce'); ?>
            </a>
            <span class="v2-backbar__sep">/</span>
            <span class="v2-backbar__current">
                <?php if ($is_new_option) {
                    esc_html_e('Create New Option', 'storelly-product-builder-for-woocommerce');
                } else {
                    printf(
                        /* translators: %s: option title */
                        esc_html__('Edit · %s', 'storelly-product-builder-for-woocommerce'),
                        esc_html($options['title'] ? $options['title'] : '#' . $current_id)
                    );
                } ?>
            </span>
        </nav>
        <?php endif; ?>

        <!-- ──────────────── Hero ──────────────── -->
        <header class="spbwc-page-hero">
            <div class="spbwc-page-hero__grid">
                <div class="spbwc-page-hero__body">
                    <div class="spbwc-page-hero__eyebrow">
                        <span class="dashicons dashicons-admin-plugins" aria-hidden="true"></span>
                        <?php esc_html_e('Pricing Option', 'storelly-product-builder-for-woocommerce'); ?>
                    </div>
                    <h1 class="spbwc-page-hero__title">
                        <span class="dashicons dashicons-<?php echo $is_new_option ? 'plus-alt2' : 'edit'; ?>" aria-hidden="true"></span>
                        <?php if ($is_new_option) {
                            esc_html_e('Create New Option', 'storelly-product-builder-for-woocommerce');
                        } else {
                            esc_html_e('Edit Pricing Option', 'storelly-product-builder-for-woocommerce');
                        } ?>
                    </h1>
                    <p class="spbwc-page-hero__subtitle">
                        <?php if ($is_new_option) {
                            esc_html_e('Build a new printing option group with fields, pricing rules and product assignments.', 'storelly-product-builder-for-woocommerce');
                        } else {
                            esc_html_e('Define the printing attributes (paper, size, finishing, qty…) customers configure for this product.', 'storelly-product-builder-for-woocommerce');
                        } ?>
                    </p>
                </div>
                <div class="spbwc-page-hero__actions">
                    <a href="<?php echo esc_url($link_create_option); ?>" class="spbwc-cta-btn spbwc-cta-btn--ghost">
                        <span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
                        <?php esc_html_e('Add new', 'storelly-product-builder-for-woocommerce'); ?>
                    </a>
                </div>
            </div>
        </header>

        <!-- ──────────────── Flash message ──────────────── -->
        <?php if (isset($message['flag'])) :
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variable.
            $rendered_message = SPBWC_Storelly_PB_Util::spbwc_custom_notices($message['flag'], $message['content']);
            echo wp_kses_post($rendered_message);
        endif; ?>

        <!-- ──────────────── Main form ──────────────── -->
        <form name="nboForm" action="" method="post" id="post" data-spbwc-edit-form>
            <?php wp_nonce_field('spbwc_save_option_action', '_wpnonce'); ?>
            <input type="hidden" name="options[version]" value="<?php echo esc_attr(SPBWC_PB_VERSION); ?>" />
            <input type="hidden" name="option_id" value="<?php echo esc_attr((string) $current_id); ?>" />

            <!-- Tab strip -->
            <div class="v2-tabs" role="tablist">
                <button type="button" class="v2-tab is-active" data-v2-tab="builder" role="tab" aria-selected="true">
                    <span class="dashicons dashicons-admin-generic" aria-hidden="true"></span>
                    <?php esc_html_e('Builder', 'storelly-product-builder-for-woocommerce'); ?>
                    <span class="v2-tab__count">{{ options.fields.length }}</span>
                </button>
                <button type="button" class="v2-tab" data-v2-tab="apply" role="tab" aria-selected="false">
                    <span class="dashicons dashicons-cart" aria-hidden="true"></span>
                    <?php esc_html_e('Apply to products', 'storelly-product-builder-for-woocommerce'); ?>
                    <span class="v2-tab__count"><?php echo esc_html((string) $applied_count); ?></span>
                </button>
                <button type="button" class="v2-tab" data-v2-tab="designer" role="tab" aria-selected="false">
                    <span class="dashicons dashicons-art" aria-hidden="true"></span>
                    <?php esc_html_e('Designer', 'storelly-product-builder-for-woocommerce'); ?>
                    <span class="v2-tab__count">{{ options.views.length || 0 }}</span>
                </button>
                <button type="button" class="v2-tab" data-v2-tab="output" role="tab" aria-selected="false">
                    <span class="dashicons dashicons-pdf" aria-hidden="true"></span>
                    <?php esc_html_e('Output (PDF)', 'storelly-product-builder-for-woocommerce'); ?>
                </button>
                <button type="button" class="v2-tab" data-v2-tab="iox" role="tab" aria-selected="false">
                    <span class="dashicons dashicons-migrate" aria-hidden="true"></span>
                    <?php esc_html_e('Import / Export', 'storelly-product-builder-for-woocommerce'); ?>
                </button>
            </div>

            <!-- ════════════════════════════════════════════════════════
                 TAB PANEL: BUILDER (default)
                 ════════════════════════════════════════════════════════ -->
            <div class="v2-tab-panel is-active" data-v2-panel="builder">

                <!-- Option metadata -->
                <div class="v2-card">
                    <div class="v2-card__head v2-card__head--brand">
                        <h2 class="v2-card__title">
                            <span class="v2-card__title-icon">i</span>
                            <?php esc_html_e('Option metadata', 'storelly-product-builder-for-woocommerce'); ?>
                        </h2>
                        <?php if ($options['published'] == 1) : ?>
                            <span class="v2-pill v2-pill--active"><span class="v2-pill__dot"></span><?php esc_html_e('Published', 'storelly-product-builder-for-woocommerce'); ?></span>
                        <?php else : ?>
                            <span class="v2-pill v2-pill--draft"><?php esc_html_e('Draft', 'storelly-product-builder-for-woocommerce'); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="v2-card__body">
                        <div class="v2-form-row">
                            <label class="v2-form-row__label" for="title">
                                <?php esc_html_e('Option title', 'storelly-product-builder-for-woocommerce'); ?>
                                <span style="color:var(--nbd-color-danger)">*</span>
                            </label>
                            <input id="title" class="v2-input" required="required"
                                   ng-model="options.title" type="text" name="title"
                                   value="<?php echo esc_attr($options['title']); ?>" autocomplete="off" />
                            <span style="color: var(--nbd-color-danger); font-size: var(--text-sm);" ng-show="nboForm.title.$invalid">
                                * <small><i><?php esc_html_e('required', 'storelly-product-builder-for-woocommerce'); ?></i></small>
                            </span>
                        </div>
                        <div class="v2-meta-grid">
                            <div class="v2-meta-item">
                                <span class="v2-meta-item__label"><?php esc_html_e('Created', 'storelly-product-builder-for-woocommerce'); ?></span>
                                <span class="v2-meta-item__value"><?php echo esc_html($options['created'] ? $options['created'] : '—'); ?></span>
                            </div>
                            <div class="v2-meta-item">
                                <span class="v2-meta-item__label"><?php esc_html_e('Modified', 'storelly-product-builder-for-woocommerce'); ?></span>
                                <span class="v2-meta-item__value"><?php echo esc_html($options['modified'] ? $options['modified'] : '—'); ?></span>
                            </div>
                            <div class="v2-meta-item">
                                <span class="v2-meta-item__label"><?php esc_html_e('Option ID', 'storelly-product-builder-for-woocommerce'); ?></span>
                                <span class="v2-meta-item__value">#<?php echo esc_html((string) $current_id); ?></span>
                            </div>
                        </div>

                        <!-- Frontend display mode -->
                        <div class="v2-form-row" style="margin-top: var(--nbd-space-4);">
                            <label class="v2-form-row__label">
                                <?php esc_html_e('Frontend display mode', 'storelly-product-builder-for-woocommerce'); ?>
                            </label>
                            <p class="v2-form-row__help"><?php esc_html_e('Controls how the option group renders on the product page. Customers see one of these three layouts.', 'storelly-product-builder-for-woocommerce'); ?></p>
                            <div class="v2-radio-cards v2-radio-cards--mode">
                                <label class="v2-radio-card" ng-class="{'is-active': options.display_mode === 'sections'}">
                                    <input type="radio" ng-model="options.display_mode" value="sections" name="options[display_mode]" />
                                    <span class="v2-radio-card__visual v2-radio-card__visual--sections" aria-hidden="true">
                                        <span></span><span></span><span></span>
                                    </span>
                                    <strong><?php esc_html_e('Sections', 'storelly-product-builder-for-woocommerce'); ?></strong>
                                    <small><?php esc_html_e('Vertical list, all fields visible. Best for ≤ 6 fields.', 'storelly-product-builder-for-woocommerce'); ?></small>
                                </label>
                                <label class="v2-radio-card" ng-class="{'is-active': options.display_mode === 'matrix'}">
                                    <input type="radio" ng-model="options.display_mode" value="matrix" name="options[display_mode]" />
                                    <span class="v2-radio-card__visual v2-radio-card__visual--matrix" aria-hidden="true">
                                        <span></span><span></span><span></span><span></span>
                                        <span></span><span></span><span></span><span></span>
                                    </span>
                                    <strong><?php esc_html_e('Matrix', 'storelly-product-builder-for-woocommerce'); ?></strong>
                                    <small><?php esc_html_e('2D grid (paper × sides). Pick a combo cell.', 'storelly-product-builder-for-woocommerce'); ?></small>
                                </label>
                                <label class="v2-radio-card" ng-class="{'is-active': options.display_mode === 'stepper'}">
                                    <input type="radio" ng-model="options.display_mode" value="stepper" name="options[display_mode]" />
                                    <span class="v2-radio-card__visual v2-radio-card__visual--stepper" aria-hidden="true">
                                        <span class="dot is-active"></span><span class="line"></span>
                                        <span class="dot"></span><span class="line"></span>
                                        <span class="dot"></span>
                                    </span>
                                    <strong><?php esc_html_e('Stepper', 'storelly-product-builder-for-woocommerce'); ?></strong>
                                    <small><?php esc_html_e('Wizard one-at-a-time. Best for &gt; 5 fields.', 'storelly-product-builder-for-woocommerce'); ?></small>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quantity Breaks card — option-level qty config + tiered pricing -->
                <div class="v2-card v2-qty-card">
                    <div class="v2-card__head v2-card__head--brand">
                        <h2 class="v2-card__title">
                            <span class="v2-card__title-icon">🔢</span>
                            <?php esc_html_e('Quantity & bulk pricing', 'storelly-product-builder-for-woocommerce'); ?>
                        </h2>
                        <label class="v2-switch v2-card__sub" style="margin-left:auto;">
                            <input type="checkbox" ng-model="options.quantity_enable" ng-true-value="'y'" ng-false-value="'n'" name="options[quantity_enable]" />
                            <span class="v2-switch__track"></span>
                            <span class="v2-switch__label" ng-bind="options.quantity_enable === 'y' ? '<?php echo esc_js(__('Enabled', 'storelly-product-builder-for-woocommerce')); ?>' : '<?php echo esc_js(__('Disabled', 'storelly-product-builder-for-woocommerce')); ?>'"></span>
                        </label>
                    </div>
                    <div class="v2-card__body" ng-show="options.quantity_enable === 'y'">
                        <p class="v2-form-row__help" style="margin:0 0 var(--nbd-space-3);">
                            <?php esc_html_e('Define quantity tiers customers can pick from. Each tier can have its own discount (fixed amount or percent off).', 'storelly-product-builder-for-woocommerce'); ?>
                        </p>

                        <!-- Mode + discount type row -->
                        <div class="v2-qty-grid">
                            <div class="v2-form-row">
                                <label class="v2-form-row__label"><?php esc_html_e('Display as', 'storelly-product-builder-for-woocommerce'); ?></label>
                                <div class="v2-radio-cards">
                                    <label class="v2-radio-card" ng-class="{'is-active': options.quantity_type === 'r'}">
                                        <input type="radio" ng-model="options.quantity_type" value="r" name="options[quantity_type]" />
                                        <strong><?php esc_html_e('Tier cards', 'storelly-product-builder-for-woocommerce'); ?></strong>
                                        <small><?php esc_html_e('100 / 250 / 500 cards w/ save%', 'storelly-product-builder-for-woocommerce'); ?></small>
                                    </label>
                                    <label class="v2-radio-card" ng-class="{'is-active': options.quantity_type === 'd'}">
                                        <input type="radio" ng-model="options.quantity_type" value="d" name="options[quantity_type]" />
                                        <strong><?php esc_html_e('Dropdown', 'storelly-product-builder-for-woocommerce'); ?></strong>
                                        <small><?php esc_html_e('Compact pick-one list', 'storelly-product-builder-for-woocommerce'); ?></small>
                                    </label>
                                    <label class="v2-radio-card" ng-class="{'is-active': options.quantity_type === 's'}">
                                        <input type="radio" ng-model="options.quantity_type" value="s" name="options[quantity_type]" />
                                        <strong><?php esc_html_e('Stepper', 'storelly-product-builder-for-woocommerce'); ?></strong>
                                        <small><?php esc_html_e('Free input with min/max/step', 'storelly-product-builder-for-woocommerce'); ?></small>
                                    </label>
                                </div>
                            </div>

                            <div class="v2-form-row">
                                <label class="v2-form-row__label"><?php esc_html_e('Discount type', 'storelly-product-builder-for-woocommerce'); ?></label>
                                <select class="v2-select" ng-model="options.quantity_discount_type" name="options[quantity_discount_type]" style="max-width:200px;">
                                    <option value="p"><?php esc_html_e('Percent off (%)', 'storelly-product-builder-for-woocommerce'); ?></option>
                                    <option value="f"><?php esc_html_e('Fixed amount off ($)', 'storelly-product-builder-for-woocommerce'); ?></option>
                                </select>
                            </div>
                        </div>

                        <!-- Min/Max/Step row -->
                        <div class="v2-qty-grid v2-qty-grid--minmax" ng-show="options.quantity_type === 's'">
                            <div class="v2-form-row">
                                <label class="v2-form-row__label"><?php esc_html_e('Min quantity', 'storelly-product-builder-for-woocommerce'); ?></label>
                                <input class="v2-input" type="number" string-to-number min="1" ng-model="options.quantity_min" name="options[quantity_min]" style="max-width:120px;" />
                            </div>
                            <div class="v2-form-row">
                                <label class="v2-form-row__label"><?php esc_html_e('Max quantity', 'storelly-product-builder-for-woocommerce'); ?></label>
                                <input class="v2-input" type="number" string-to-number min="1" ng-model="options.quantity_max" name="options[quantity_max]" style="max-width:120px;" />
                            </div>
                            <div class="v2-form-row">
                                <label class="v2-form-row__label"><?php esc_html_e('Step', 'storelly-product-builder-for-woocommerce'); ?></label>
                                <input class="v2-input" type="number" string-to-number min="1" ng-model="options.quantity_step" name="options[quantity_step]" style="max-width:120px;" />
                            </div>
                        </div>

                        <!-- Breaks list -->
                        <div class="v2-section-head">
                            <span class="v2-section-head__icon">📊</span>
                            <?php esc_html_e('Quantity tiers', 'storelly-product-builder-for-woocommerce'); ?>
                            <span style="margin-left:auto; text-transform:none; letter-spacing:0; font-weight:var(--font-normal); color:var(--nbd-st-text-mute);">
                                {{ options.quantity_breaks.length || 0 }} tier{{ options.quantity_breaks.length === 1 ? '' : 's' }}
                            </span>
                        </div>

                        <div class="v2-qty-breaks">
                            <div class="v2-qty-break v2-qty-break--head">
                                <div><?php esc_html_e('Quantity', 'storelly-product-builder-for-woocommerce'); ?></div>
                                <div><?php esc_html_e('Discount', 'storelly-product-builder-for-woocommerce'); ?></div>
                                <div><?php esc_html_e('Default', 'storelly-product-builder-for-woocommerce'); ?></div>
                                <div></div>
                            </div>
                            <div class="v2-qty-break" ng-repeat="b in options.quantity_breaks track by $index">
                                <div>
                                    <input class="v2-input" type="number" string-to-number min="1"
                                           ng-model="b.val"
                                           name="options[quantity_breaks][{{$index}}][val]"
                                           placeholder="<?php esc_attr_e('e.g. 100', 'storelly-product-builder-for-woocommerce'); ?>" />
                                </div>
                                <div>
                                    <span class="v2-qty-break__prefix" ng-show="options.quantity_discount_type === 'f'">$</span>
                                    <input class="v2-input" type="text"
                                           ng-model="b.dis"
                                           name="options[quantity_breaks][{{$index}}][dis]"
                                           placeholder="<?php esc_attr_e('0', 'storelly-product-builder-for-woocommerce'); ?>" />
                                    <span class="v2-qty-break__suffix" ng-show="options.quantity_discount_type === 'p'">%</span>
                                </div>
                                <div class="v2-qty-break__default">
                                    <label>
                                        <input type="radio"
                                               name="options[quantity_breaks_default]"
                                               value="{{$index}}"
                                               ng-checked="b.default === 'on'"
                                               ng-click="set_default_quantity_break($index)" />
                                        <input type="hidden"
                                               name="options[quantity_breaks][{{$index}}][default]"
                                               ng-value="b.default" />
                                        <?php esc_html_e('Default', 'storelly-product-builder-for-woocommerce'); ?>
                                    </label>
                                </div>
                                <button type="button" class="v2-qty-break__remove" ng-click="remove_quantity_break($index)" title="<?php esc_attr_e('Remove tier', 'storelly-product-builder-for-woocommerce'); ?>">✕</button>
                            </div>
                            <div class="v2-qty-break-empty" ng-show="!options.quantity_breaks || options.quantity_breaks.length === 0">
                                <span class="v2-qty-break-empty__icon" aria-hidden="true">∅</span>
                                <span><?php esc_html_e('No quantity tiers yet. Add at least one to enable bulk pricing.', 'storelly-product-builder-for-woocommerce'); ?></span>
                            </div>
                        </div>

                        <button type="button" class="v2-btn v2-btn--secondary" ng-click="add_quantity_break()" style="margin-top: var(--nbd-space-3);">
                            <span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
                            <?php esc_html_e('Add quantity tier', 'storelly-product-builder-for-woocommerce'); ?>
                        </button>
                    </div>
                </div>

                <!-- Field type palette -->
                <div class="v2-palette">
                    <div class="v2-palette__head">
                        <p class="v2-palette__title"><?php esc_html_e('Add field to this option', 'storelly-product-builder-for-woocommerce'); ?></p>
                        <p class="v2-palette__sub"><?php esc_html_e('Pick the field type that matches how the customer will choose. Hover a chip to see what it does.', 'storelly-product-builder-for-woocommerce'); ?></p>
                    </div>
                    <div class="v2-palette__grid">

                        <button type="button" class="v2-chip" ng-click="add_field_preset('m')"
                                data-spbwc-help="<?php esc_attr_e('Pick-one selector with image or color swatches (e.g. paper, finish, color).', 'storelly-product-builder-for-woocommerce'); ?>"
                                data-spbwc-example="<?php esc_attr_e('Example: Paper · Matte 250gsm · Soft-touch 350gsm · Glossy 300gsm', 'storelly-product-builder-for-woocommerce'); ?>">
                            <span class="v2-chip__icon v2-chip__icon--m">M</span>
                            <span class="v2-chip__label">
                                <strong><?php esc_html_e('Multi-choice', 'storelly-product-builder-for-woocommerce'); ?></strong>
                                <small><?php esc_html_e('Pick-one with swatches', 'storelly-product-builder-for-woocommerce'); ?></small>
                            </span>
                        </button>

                        <button type="button" class="v2-chip" ng-click="add_field_preset('n')"
                                data-spbwc-help="<?php esc_attr_e('Numeric input with a min/max range and step. For option-wide bulk pricing tiers, use the Quantity & bulk pricing card above.', 'storelly-product-builder-for-woocommerce'); ?>"
                                data-spbwc-example="<?php esc_attr_e('Example: Number of pages · min 1, max 500, step 1', 'storelly-product-builder-for-woocommerce'); ?>">
                            <span class="v2-chip__icon v2-chip__icon--n">N</span>
                            <span class="v2-chip__label">
                                <strong><?php esc_html_e('Number', 'storelly-product-builder-for-woocommerce'); ?></strong>
                                <small><?php esc_html_e('Numeric stepper (min/max/step)', 'storelly-product-builder-for-woocommerce'); ?></small>
                            </span>
                        </button>

                        <button type="button" class="v2-chip" ng-click="add_field_preset('t')"
                                data-spbwc-help="<?php esc_attr_e('Single-line text the customer types — name, message, slogan. Optional price-per-character.', 'storelly-product-builder-for-woocommerce'); ?>"
                                data-spbwc-example="<?php esc_attr_e('Example: Custom name printed on the card (max 30 chars)', 'storelly-product-builder-for-woocommerce'); ?>">
                            <span class="v2-chip__icon v2-chip__icon--t">T</span>
                            <span class="v2-chip__label">
                                <strong><?php esc_html_e('Text input', 'storelly-product-builder-for-woocommerce'); ?></strong>
                                <small><?php esc_html_e('Single-line free text', 'storelly-product-builder-for-woocommerce'); ?></small>
                            </span>
                        </button>

                        <button type="button" class="v2-chip" ng-click="add_field_preset('a')"
                                data-spbwc-help="<?php esc_attr_e('Multi-line text for longer instructions or messages, with min/max characters.', 'storelly-product-builder-for-woocommerce'); ?>"
                                data-spbwc-example="<?php esc_attr_e('Example: Special instructions for the printer (notes, deadlines)', 'storelly-product-builder-for-woocommerce'); ?>">
                            <span class="v2-chip__icon v2-chip__icon--a">A</span>
                            <span class="v2-chip__label">
                                <strong><?php esc_html_e('Textarea', 'storelly-product-builder-for-woocommerce'); ?></strong>
                                <small><?php esc_html_e('Multi-line message', 'storelly-product-builder-for-woocommerce'); ?></small>
                            </span>
                        </button>

                        <button type="button" class="v2-chip" ng-click="add_field_preset('u')"
                                data-spbwc-help="<?php esc_attr_e('Customer uploads their print-ready artwork. Choose accepted formats (PDF, AI, PNG…) and max size.', 'storelly-product-builder-for-woocommerce'); ?>"
                                data-spbwc-example="<?php esc_attr_e('Example: Upload print file · PDF/AI/PNG up to 25 MB', 'storelly-product-builder-for-woocommerce'); ?>">
                            <span class="v2-chip__icon v2-chip__icon--u">U</span>
                            <span class="v2-chip__label">
                                <strong><?php esc_html_e('File upload', 'storelly-product-builder-for-woocommerce'); ?></strong>
                                <small><?php esc_html_e('Customer artwork', 'storelly-product-builder-for-woocommerce'); ?></small>
                            </span>
                        </button>

                        <span class="v2-palette__divider" aria-hidden="true"></span>

                        <button type="button" class="v2-chip" ng-click="add_field('nbpb_com', 'nbpb_com')"
                                data-spbwc-help="<?php esc_attr_e('Pre-built design canvas component (background, frame, shape holder) for the online Designer.', 'storelly-product-builder-for-woocommerce'); ?>"
                                data-spbwc-example="<?php esc_attr_e('Example: Background frame on the business-card designer canvas', 'storelly-product-builder-for-woocommerce'); ?>">
                            <span class="v2-chip__icon v2-chip__icon--com">⚛</span>
                            <span class="v2-chip__label">
                                <strong><?php esc_html_e('Designer Component', 'storelly-product-builder-for-woocommerce'); ?></strong>
                                <small><?php esc_html_e('For online designer', 'storelly-product-builder-for-woocommerce'); ?></small>
                            </span>
                        </button>

                        <button type="button" class="v2-chip" ng-click="add_field('nbpb_text', 'nbpb_text')"
                                data-spbwc-help="<?php esc_attr_e('Editable text block on the designer canvas. Choose default text, allowed fonts and colors.', 'storelly-product-builder-for-woocommerce'); ?>"
                                data-spbwc-example="<?php esc_attr_e('Example: Customer name text block on the canvas', 'storelly-product-builder-for-woocommerce'); ?>">
                            <span class="v2-chip__icon v2-chip__icon--dt">Tx</span>
                            <span class="v2-chip__label">
                                <strong><?php esc_html_e('Designer Text', 'storelly-product-builder-for-woocommerce'); ?></strong>
                                <small><?php esc_html_e('Editable text on canvas', 'storelly-product-builder-for-woocommerce'); ?></small>
                            </span>
                        </button>

                        <button type="button" class="v2-chip" ng-click="add_field('nbpb_image', 'nbpb_image')"
                                data-spbwc-help="<?php esc_attr_e('Image placeholder on the designer canvas — customer uploads or picks from a gallery.', 'storelly-product-builder-for-woocommerce'); ?>"
                                data-spbwc-example="<?php esc_attr_e('Example: Logo placeholder positioned on the front of the card', 'storelly-product-builder-for-woocommerce'); ?>">
                            <span class="v2-chip__icon v2-chip__icon--di">Im</span>
                            <span class="v2-chip__label">
                                <strong><?php esc_html_e('Designer Image', 'storelly-product-builder-for-woocommerce'); ?></strong>
                                <small><?php esc_html_e('Image placeholder', 'storelly-product-builder-for-woocommerce'); ?></small>
                            </span>
                        </button>
                    </div>

                    <!-- Inline help drawer (populated on hover via JS) -->
                    <div class="v2-palette__hint" id="v2-palette-hint" hidden>
                        <span class="v2-palette__hint-icon" aria-hidden="true">💡</span>
                        <div>
                            <p class="v2-palette__hint-text" data-help-target></p>
                            <p class="v2-palette__hint-example" data-example-target></p>
                        </div>
                    </div>
                </div>

                <!-- Max input vars warning (kept for safety) -->
                <div id="notice-max-input-vars" ng-show="current_input_vars > max_input_vars">
                    <h2>
                        <span class="dashicons dashicons-warning" aria-hidden="true"></span>
                        <?php esc_html_e('Notice', 'storelly-product-builder-for-woocommerce'); ?>
                    </h2>
                    <div class="inside">
                        <p>
                            <?php esc_html_e('PHP max input vars:', 'storelly-product-builder-for-woocommerce'); ?>
                            <?php echo esc_html($max_input_vars); ?>
                        </p>
                        <p>
                            <?php esc_html_e('Current input vars:', 'storelly-product-builder-for-woocommerce'); ?>
                            <span>{{current_input_vars}}</span>
                        </p>
                        <p><?php esc_html_e('Please increase "PHP max input vars"!', 'storelly-product-builder-for-woocommerce'); ?></p>
                    </div>
                </div>

                <!-- 2-col Builder | Preview -->
                <div class="v2-builder">

                    <!-- LEFT — Builder field list -->
                    <section>
                        <div class="v2-hint">
                            <span class="v2-hint__icon">💡</span>
                            <div>
                                <strong><?php esc_html_e('How fields work:', 'storelly-product-builder-for-woocommerce'); ?></strong>
                                <?php esc_html_e('Each field becomes a configurable option on the product page. Customers pick / type / upload — pricing recalculates live. Drag the handle to reorder, click the title to expand.', 'storelly-product-builder-for-woocommerce'); ?>
                            </div>
                        </div>

                        <!-- Onboarding: quick-start preset patterns (visible when option is brand new or empty) -->
                        <div class="v2-onboarding" ng-show="!options.fields || options.fields.length <= 1 && (!options.fields[0] || !options.fields[0].general || !options.fields[0].general.title || !options.fields[0].general.title.value || options.fields[0].general.title.value === '<?php echo esc_js(__('Option name', 'storelly-product-builder-for-woocommerce')); ?>')">
                            <h3 class="v2-onboarding__title"><?php esc_html_e('Start with a common print field', 'storelly-product-builder-for-woocommerce'); ?></h3>
                            <p class="v2-onboarding__sub"><?php esc_html_e('Pick a pattern below — we will pre-fill the field type so you only need to add attributes.', 'storelly-product-builder-for-woocommerce'); ?></p>
                            <div class="v2-onboarding__grid">
                                <button type="button" class="v2-onboarding__card" ng-click="add_field_preset('m')">
                                    <span class="v2-onboarding__card-icon" style="background: var(--st-pill-featured-bg); color: var(--st-pill-featured-text);">📐</span>
                                    <strong><?php esc_html_e('Paper / Material', 'storelly-product-builder-for-woocommerce'); ?></strong>
                                    <small><?php esc_html_e('Swatch picker · Matte / Glossy / Linen…', 'storelly-product-builder-for-woocommerce'); ?></small>
                                </button>
                                <button type="button" class="v2-onboarding__card" ng-click="add_field_preset('m')">
                                    <span class="v2-onboarding__card-icon" style="background: var(--st-brand-tint); color: var(--st-brand-pressed);">📏</span>
                                    <strong><?php esc_html_e('Size variant', 'storelly-product-builder-for-woocommerce'); ?></strong>
                                    <small><?php esc_html_e('Dropdown · Standard / Square / Mini', 'storelly-product-builder-for-woocommerce'); ?></small>
                                </button>
                                <button type="button" class="v2-onboarding__card" ng-click="add_field_preset('n')">
                                    <span class="v2-onboarding__card-icon" style="background: var(--st-pill-active-bg); color: var(--st-pill-active-text);">🔢</span>
                                    <strong><?php esc_html_e('Number', 'storelly-product-builder-for-woocommerce'); ?></strong>
                                    <small><?php esc_html_e('Numeric stepper · min / max / step', 'storelly-product-builder-for-woocommerce'); ?></small>
                                </button>
                                <button type="button" class="v2-onboarding__card" ng-click="add_field_preset('u')">
                                    <span class="v2-onboarding__card-icon v2-onboarding__card-icon--upload">⬆</span>
                                    <strong><?php esc_html_e('Upload print file', 'storelly-product-builder-for-woocommerce'); ?></strong>
                                    <small><?php esc_html_e('File upload · PDF / AI / PNG', 'storelly-product-builder-for-woocommerce'); ?></small>
                                </button>
                            </div>
                        </div>

                        <div class="pcpb-fields-builder">
                            <?php include_once('field.php'); ?>
                        </div>

                        <div ng-repeat="view in options.views">
                            <input ng-hide="true" ng-model="view.name" name="options[views][{{$index}}][name]" />
                            <input ng-hide="true" ng-model="view.base" name="options[views][{{$index}}][base]" />
                            <input ng-hide="true" ng-model="view.base_width" name="options[views][{{$index}}][base_width]" />
                            <input ng-hide="true" ng-model="view.base_height" name="options[views][{{$index}}][base_height]" />
                        </div>
                    </section>

                    <!-- RIGHT — Live preview (sticky, full height, mobile default) -->
                    <aside class="v2-preview" data-device="mobile">
                        <header class="v2-preview__head">
                            <h2 class="v2-preview__title">
                                <span class="v2-card__title-icon" aria-hidden="true">
                                    <span class="dashicons dashicons-visibility" style="font-size:14px;line-height:24px;"></span>
                                </span>
                                <?php esc_html_e('Live preview', 'storelly-product-builder-for-woocommerce'); ?>
                            </h2>
                            <div class="v2-device" role="tablist">
                                <button type="button" class="v2-device__btn is-active" data-v2-device="mobile" role="tab" aria-selected="true">
                                    <span class="dashicons dashicons-smartphone" aria-hidden="true"></span>
                                    <?php esc_html_e('Mobile', 'storelly-product-builder-for-woocommerce'); ?>
                                </button>
                                <button type="button" class="v2-device__btn" data-v2-device="desktop" role="tab" aria-selected="false">
                                    <span class="dashicons dashicons-desktop" aria-hidden="true"></span>
                                    <?php esc_html_e('Desktop', 'storelly-product-builder-for-woocommerce'); ?>
                                </button>
                            </div>
                        </header>
                        <div class="v2-preview__body">

                            <!-- ═══════════════ MOBILE viewport ═══════════════ -->
                            <div class="v2-mobile">
                                <div class="v2-mobile__notch" aria-hidden="true"></div>
                                <div class="v2-mobile__screen">
                                    <div class="v2-prv-img"><?php esc_html_e('Product mockup', 'storelly-product-builder-for-woocommerce'); ?></div>
                                    <h3 class="v2-prv-name">{{ options.title || '<?php echo esc_js(__('Untitled option', 'storelly-product-builder-for-woocommerce')); ?>' }}</h3>
                                    <div class="v2-prv-price">${{ preview_total().total }}</div>

                                    <!-- Empty state -->
                                    <div class="v2-prv-empty" ng-show="!options.fields || options.fields.length === 0">
                                        <div class="v2-prv-empty__icon">📋</div>
                                        <div><?php esc_html_e('No fields yet. Add a field using the palette above.', 'storelly-product-builder-for-woocommerce'); ?></div>
                                    </div>

                                    <!-- Field sections (interactive) -->
                                    <div class="v2-prv-section"
                                         ng-repeat="field in options.fields"
                                         ng-show="field.general.enabled.value !== 'n' && field.general.published.value !== 'n'">
                                        <div class="v2-prv-section__head">
                                            <span class="v2-prv-section__label">
                                                {{ field.general.title.value }}
                                                <span ng-show="field.general.required.value === 'y'" style="color:var(--nbd-color-danger)">*</span>
                                            </span>
                                            <span class="v2-prv-section__chosen" ng-show="field.general.data_type.value === 'm' && preview_selected_attr(field)">
                                                <strong>{{ preview_selected_attr(field).name }}</strong>
                                                <span ng-show="preview_attr_price_label(preview_selected_attr(field))" style="color:var(--nbd-color-success); margin-left:4px;">{{ preview_attr_price_label(preview_selected_attr(field)) }}</span>
                                            </span>
                                        </div>

                                        <!-- Multi-choice → swatches (clickable) -->
                                        <div class="v2-prv-swatches" ng-if="field.general.data_type.value === 'm'">
                                            <div ng-repeat="attr in field.general.attributes.options | limitTo: 6"
                                                 class="v2-prv-swatch"
                                                 ng-class="{'v2-prv-swatch--active': preview_is_selected(field.id, $index)}"
                                                 ng-click="preview_select_attr(field.id, $index)">
                                                <div class="v2-prv-swatch__color"
                                                     ng-style="attr.image_url ? { 'background-image': 'url(' + attr.image_url + ')', 'background-size': 'cover' } : { 'background': attr.color || '#e5e7eb' }"></div>
                                                {{ attr.name }}
                                                <small ng-show="preview_attr_price_label(attr)" class="v2-prv-swatch__price">{{ preview_attr_price_label(attr) }}</small>
                                            </div>
                                        </div>

                                        <!-- Text input -->
                                        <input ng-if="field.general.data_type.value === 'i' && field.general.input_type.value === 't'"
                                               class="v2-prv-input" type="text" placeholder="<?php esc_attr_e('Customer types here…', 'storelly-product-builder-for-woocommerce'); ?>" />

                                        <!-- Number input -->
                                        <input ng-if="field.general.data_type.value === 'i' && field.general.input_type.value === 'n'"
                                               class="v2-prv-input" type="number"
                                               ng-attr-min="{{ field.general.input_option.value.min }}"
                                               ng-attr-max="{{ field.general.input_option.value.max }}"
                                               ng-attr-step="{{ field.general.input_option.value.step }}"
                                               placeholder="{{ field.general.input_option.value.default || 0 }}" />

                                        <!-- Number range -->
                                        <input ng-if="field.general.data_type.value === 'i' && field.general.input_type.value === 'r'"
                                               class="v2-prv-input" type="range"
                                               ng-attr-min="{{ field.general.input_option.value.min }}"
                                               ng-attr-max="{{ field.general.input_option.value.max }}"
                                               ng-attr-step="{{ field.general.input_option.value.step }}" />

                                        <!-- Textarea -->
                                        <textarea ng-if="field.general.data_type.value === 'i' && field.general.input_type.value === 'a'"
                                                  class="v2-prv-input v2-prv-textarea" placeholder="<?php esc_attr_e('Customer types here…', 'storelly-product-builder-for-woocommerce'); ?>"></textarea>

                                        <!-- Upload -->
                                        <div ng-if="field.general.data_type.value === 'i' && field.general.input_type.value === 'u'"
                                             class="v2-prv-upload">
                                            ⬆ <?php esc_html_e('Upload your file', 'storelly-product-builder-for-woocommerce'); ?>
                                        </div>
                                    </div>

                                    <!-- Quantity breaks (when enabled) -->
                                    <div class="v2-prv-section" ng-show="options.quantity_enable === 'y' && options.quantity_breaks.length > 0">
                                        <div class="v2-prv-section__head">
                                            <span class="v2-prv-section__label"><?php esc_html_e('Quantity', 'storelly-product-builder-for-woocommerce'); ?></span>
                                            <span class="v2-prv-section__chosen" ng-show="options.quantity_breaks[preview.qty_index]">
                                                <strong>{{ options.quantity_breaks[preview.qty_index].val }}</strong>
                                            </span>
                                        </div>
                                        <div class="v2-prv-qty-breaks" ng-if="options.quantity_type === 'r'">
                                            <button type="button"
                                                    class="v2-prv-qty-break"
                                                    ng-repeat="b in options.quantity_breaks track by $index"
                                                    ng-class="{'is-active': preview.qty_index === $index}"
                                                    ng-click="preview.qty_index = $index">
                                                <strong>{{ b.val }}</strong>
                                                <small ng-show="b.dis">−{{ b.dis }}{{ options.quantity_discount_type === 'p' ? '%' : '$' }}</small>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Price summary -->
                                    <div class="v2-prv-summary" ng-show="options.fields.length > 0">
                                        <div class="v2-prv-summary__row">
                                            <span><?php esc_html_e('Base', 'storelly-product-builder-for-woocommerce'); ?></span>
                                            <span>${{ preview_total().base }}</span>
                                        </div>
                                        <div class="v2-prv-summary__row" ng-show="preview_total().options !== '0.00'">
                                            <span><?php esc_html_e('+ Options', 'storelly-product-builder-for-woocommerce'); ?></span>
                                            <span>+${{ preview_total().options }}</span>
                                        </div>
                                        <div class="v2-prv-summary__row" ng-show="preview_total().has_discount">
                                            <span><?php esc_html_e('Qty discount', 'storelly-product-builder-for-woocommerce'); ?></span>
                                            <span style="color:var(--nbd-color-success)">−${{ preview_total().qty_discount }}</span>
                                        </div>
                                        <div class="v2-prv-summary__row v2-prv-summary__row--total">
                                            <span><?php esc_html_e('Total', 'storelly-product-builder-for-woocommerce'); ?></span>
                                            <span class="v2-prv-summary__total-value">${{ preview_total().total }}</span>
                                        </div>
                                    </div>

                                    <button type="button" class="v2-prv-cta"><?php esc_html_e('Add to cart', 'storelly-product-builder-for-woocommerce'); ?> · ${{ preview_total().total }}</button>
                                </div>
                            </div>

                            <!-- ═══════════════ DESKTOP viewport ═══════════════ -->
                            <div class="v2-desktop">
                                <div class="v2-desktop__grid">
                                    <!-- LEFT: gallery + name + price -->
                                    <div class="v2-desktop__gallery">
                                        <div class="v2-desktop__hero"><?php esc_html_e('Product mockup', 'storelly-product-builder-for-woocommerce'); ?></div>
                                        <div class="v2-desktop__thumbs" aria-hidden="true">
                                            <span></span><span></span><span></span><span></span>
                                        </div>
                                        <h3 class="v2-desktop__name">{{ options.title || '<?php echo esc_js(__('Untitled option', 'storelly-product-builder-for-woocommerce')); ?>' }}</h3>
                                        <div class="v2-desktop__price">${{ preview_total().total }}</div>
                                        <p class="v2-desktop__desc"><?php esc_html_e('Sample product on the customer-facing page. Click swatches on the right to test interactive selection.', 'storelly-product-builder-for-woocommerce'); ?></p>
                                    </div>

                                    <!-- RIGHT: options panel -->
                                    <div class="v2-desktop__options">
                                        <!-- Empty state -->
                                        <div class="v2-prv-empty" ng-show="!options.fields || options.fields.length === 0">
                                            <div class="v2-prv-empty__icon">📋</div>
                                            <div><?php esc_html_e('No fields yet. Add a field using the palette.', 'storelly-product-builder-for-woocommerce'); ?></div>
                                        </div>

                                        <div class="v2-prv-section"
                                             ng-repeat="field in options.fields"
                                             ng-show="field.general.enabled.value !== 'n' && field.general.published.value !== 'n'">
                                            <div class="v2-prv-section__head">
                                                <span class="v2-prv-section__label">
                                                    {{ field.general.title.value }}
                                                    <span ng-show="field.general.required.value === 'y'" style="color:var(--nbd-color-danger)">*</span>
                                                </span>
                                                <span class="v2-prv-section__chosen" ng-show="field.general.data_type.value === 'm' && preview_selected_attr(field)">
                                                    <strong>{{ preview_selected_attr(field).name }}</strong>
                                                </span>
                                            </div>

                                            <!-- Multi-choice → desktop swatches (4-column grid) -->
                                            <div class="v2-prv-swatches v2-prv-swatches--desktop" ng-if="field.general.data_type.value === 'm'">
                                                <div ng-repeat="attr in field.general.attributes.options | limitTo: 8"
                                                     class="v2-prv-swatch"
                                                     ng-class="{'v2-prv-swatch--active': preview_is_selected(field.id, $index)}"
                                                     ng-click="preview_select_attr(field.id, $index)">
                                                    <div class="v2-prv-swatch__color"
                                                         ng-style="attr.image_url ? { 'background-image': 'url(' + attr.image_url + ')', 'background-size': 'cover' } : { 'background': attr.color || '#e5e7eb' }"></div>
                                                    {{ attr.name }}
                                                    <small ng-show="preview_attr_price_label(attr)" class="v2-prv-swatch__price">{{ preview_attr_price_label(attr) }}</small>
                                                </div>
                                            </div>

                                            <input ng-if="field.general.data_type.value === 'i' && field.general.input_type.value === 't'"
                                                   class="v2-prv-input" type="text" placeholder="<?php esc_attr_e('Customer types here…', 'storelly-product-builder-for-woocommerce'); ?>" />
                                            <input ng-if="field.general.data_type.value === 'i' && field.general.input_type.value === 'n'"
                                                   class="v2-prv-input" type="number"
                                                   ng-attr-min="{{ field.general.input_option.value.min }}"
                                                   ng-attr-max="{{ field.general.input_option.value.max }}"
                                                   ng-attr-step="{{ field.general.input_option.value.step }}"
                                                   placeholder="{{ field.general.input_option.value.default || 0 }}" />
                                            <input ng-if="field.general.data_type.value === 'i' && field.general.input_type.value === 'r'"
                                                   class="v2-prv-input" type="range"
                                                   ng-attr-min="{{ field.general.input_option.value.min }}"
                                                   ng-attr-max="{{ field.general.input_option.value.max }}"
                                                   ng-attr-step="{{ field.general.input_option.value.step }}" />
                                            <textarea ng-if="field.general.data_type.value === 'i' && field.general.input_type.value === 'a'"
                                                      class="v2-prv-input v2-prv-textarea" placeholder="<?php esc_attr_e('Customer types here…', 'storelly-product-builder-for-woocommerce'); ?>"></textarea>
                                            <div ng-if="field.general.data_type.value === 'i' && field.general.input_type.value === 'u'"
                                                 class="v2-prv-upload">⬆ <?php esc_html_e('Upload your file', 'storelly-product-builder-for-woocommerce'); ?></div>
                                        </div>

                                        <!-- Quantity breaks -->
                                        <div class="v2-prv-section" ng-show="options.quantity_enable === 'y' && options.quantity_breaks.length > 0">
                                            <div class="v2-prv-section__head">
                                                <span class="v2-prv-section__label"><?php esc_html_e('Quantity', 'storelly-product-builder-for-woocommerce'); ?></span>
                                            </div>
                                            <div class="v2-prv-qty-breaks" ng-if="options.quantity_type === 'r'">
                                                <button type="button"
                                                        class="v2-prv-qty-break"
                                                        ng-repeat="b in options.quantity_breaks track by $index"
                                                        ng-class="{'is-active': preview.qty_index === $index}"
                                                        ng-click="preview.qty_index = $index">
                                                    <strong>{{ b.val }}</strong>
                                                    <small ng-show="b.dis">−{{ b.dis }}{{ options.quantity_discount_type === 'p' ? '%' : '$' }}</small>
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Price summary (compact) -->
                                        <div class="v2-prv-summary" ng-show="options.fields.length > 0">
                                            <div class="v2-prv-summary__row">
                                                <span><?php esc_html_e('Base', 'storelly-product-builder-for-woocommerce'); ?></span>
                                                <span>${{ preview_total().base }}</span>
                                            </div>
                                            <div class="v2-prv-summary__row" ng-show="preview_total().options !== '0.00'">
                                                <span><?php esc_html_e('+ Options', 'storelly-product-builder-for-woocommerce'); ?></span>
                                                <span>+${{ preview_total().options }}</span>
                                            </div>
                                            <div class="v2-prv-summary__row" ng-show="preview_total().has_discount">
                                                <span><?php esc_html_e('Qty discount', 'storelly-product-builder-for-woocommerce'); ?></span>
                                                <span style="color:var(--nbd-color-success)">−${{ preview_total().qty_discount }}</span>
                                            </div>
                                            <div class="v2-prv-summary__row v2-prv-summary__row--total">
                                                <span><?php esc_html_e('Total', 'storelly-product-builder-for-woocommerce'); ?></span>
                                                <span class="v2-prv-summary__total-value">${{ preview_total().total }}</span>
                                            </div>
                                        </div>

                                        <button type="button" class="v2-prv-cta"><?php esc_html_e('Add to cart', 'storelly-product-builder-for-woocommerce'); ?> · ${{ preview_total().total }}</button>
                                    </div>
                                </div>

                                <!-- Calculator panel — set base price -->
                                <div class="v2-desktop__calc">
                                    <label>
                                        <?php esc_html_e('Mock base price ($)', 'storelly-product-builder-for-woocommerce'); ?>
                                        <input class="v2-input" type="number" min="0" step="0.01" ng-model="preview.base_price" style="max-width:120px;" />
                                    </label>
                                    <span class="v2-form-row__help" style="margin:0;"><?php esc_html_e('Preview-only value to test pricing math. Real product price is set in WooCommerce.', 'storelly-product-builder-for-woocommerce'); ?></span>
                                </div>
                            </div>
                        </div>
                    </aside>
                </div>
            </div>

            <!-- ════════════════════════════════════════════════════════
                 TAB PANEL: APPLY TO PRODUCTS  (with Category mode)
                 ════════════════════════════════════════════════════════ -->
            <?php
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variable.
            $apply_for = isset($options['apply_for']) ? $options['apply_for'] : 'p';
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variable.
            $applied_cats = isset($options['product_cats']) && is_array($options['product_cats']) ? $options['product_cats'] : array();
            ?>
            <div class="v2-tab-panel" data-v2-panel="apply">
                <div class="v2-card">
                    <div class="v2-card__head v2-card__head--brand">
                        <h2 class="v2-card__title">
                            <span class="v2-card__title-icon">📦</span>
                            <?php esc_html_e('Apply this option to…', 'storelly-product-builder-for-woocommerce'); ?>
                        </h2>
                        <span class="v2-card__sub" data-spbwc-apply-count></span>
                    </div>
                    <div class="v2-card__body">
                        <?php if ( ! empty( $product_id ) ) : ?>
                        <!-- Context note when opened from a product metabox: the pointer
                             (_spbwc_option_id) is the source of truth; detach via Unlink. -->
                        <div class="spbwc-lp-ctx-note">
                            <span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
                            <span>
                                <?php
                                printf(
                                    /* translators: %s: product name the option was opened from. */
                                    esc_html__( 'Opened from %s. This product is linked through its Storelly product box. To detach just this product, use “Unlink” there — removing it from the list below is not required.', 'storelly-product-builder-for-woocommerce' ),
                                    '<strong>' . esc_html( get_the_title( $product_id ) ) . '</strong>'
                                );
                                ?>
                            </span>
                        </div>
                        <?php endif; ?>
                        <!-- Mode toggle: by individual products vs by category -->
                        <div class="v2-form-row">
                            <label class="v2-form-row__label"><?php esc_html_e('Selection mode', 'storelly-product-builder-for-woocommerce'); ?></label>
                            <p class="v2-form-row__help"><?php esc_html_e('Apply to specific products one-by-one, or apply to every product in chosen WooCommerce categories.', 'storelly-product-builder-for-woocommerce'); ?></p>
                            <div class="v2-segmented" role="radiogroup" aria-label="<?php esc_attr_e('Selection mode', 'storelly-product-builder-for-woocommerce'); ?>" data-spbwc-apply-mode>
                                <button type="button" class="v2-segmented__btn<?php echo $apply_for === 'c' ? '' : ' is-active'; ?>" data-apply-mode="p">
                                    <span class="dashicons dashicons-products" aria-hidden="true"></span>
                                    <?php esc_html_e('Specific products', 'storelly-product-builder-for-woocommerce'); ?>
                                </button>
                                <button type="button" class="v2-segmented__btn<?php echo $apply_for === 'c' ? ' is-active' : ''; ?>" data-apply-mode="c">
                                    <span class="dashicons dashicons-category" aria-hidden="true"></span>
                                    <?php esc_html_e('Product categories', 'storelly-product-builder-for-woocommerce'); ?>
                                </button>
                            </div>
                            <input type="hidden" name="apply_for" id="apply_for" value="<?php echo esc_attr($apply_for); ?>" />
                        </div>

                        <!-- Products picker — visible when mode = p -->
                        <div class="v2-form-row" id="nbo-products-wrap" data-spbwc-apply-products<?php echo $apply_for === 'c' ? ' hidden' : ''; ?>>
                            <label class="v2-form-row__label" for="product_ids">
                                <?php esc_html_e('Select products', 'storelly-product-builder-for-woocommerce'); ?>
                            </label>
                            <p class="v2-form-row__help"><?php esc_html_e('Search by product name. The option will appear on selected product pages.', 'storelly-product-builder-for-woocommerce'); ?></p>
                            <select name="product_ids[]" id="product_ids" class="wc-product-search"
                                    multiple="multiple" style="width: 100%;"
                                    data-placeholder="<?php esc_attr_e('Search for a product&hellip;', 'storelly-product-builder-for-woocommerce'); ?>"
                                    data-action="woocommerce_json_search_products">
                                <?php
                                foreach ($options['product_ids'] as $product_id) {
                                    $product = wc_get_product($product_id);
                                    if (is_object($product)) {
                                        echo '<option value="' . esc_attr($product_id) . '"' . wp_kses_post(selected(TRUE, TRUE, FALSE)) . '>' . wp_kses_post($product->get_formatted_name()) . '</option>';
                                    }
                                }
                                ?>
                            </select>
                        </div>

                        <!-- Categories picker — visible when mode = c -->
                        <div class="v2-form-row" data-spbwc-apply-cats<?php echo $apply_for === 'c' ? '' : ' hidden'; ?>>
                            <label class="v2-form-row__label" for="product_cats">
                                <?php esc_html_e('Select categories', 'storelly-product-builder-for-woocommerce'); ?>
                            </label>
                            <p class="v2-form-row__help"><?php esc_html_e('Every product in the chosen categories will automatically inherit this pricing option.', 'storelly-product-builder-for-woocommerce'); ?></p>
                            <select name="product_cats[]" id="product_cats" multiple="multiple" style="width: 100%;"
                                    data-placeholder="<?php esc_attr_e('Pick one or more categories&hellip;', 'storelly-product-builder-for-woocommerce'); ?>">
                                <?php
                                $product_cats_terms = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) );
                                if ( ! is_wp_error( $product_cats_terms ) && is_array( $product_cats_terms ) ) {
                                    foreach ( $product_cats_terms as $term ) {
                                        echo '<option value="' . esc_attr( $term->term_id ) . '"' . selected( in_array( $term->term_id, $applied_cats, true ), true, false ) . '>' . esc_html( $term->name ) . ' (' . (int) $term->count . ')</option>';
                                    }
                                }
                                ?>
                            </select>
                        </div>

                        <!-- Stat strip -->
                        <div class="v2-apply-stats" data-spbwc-apply-stats>
                            <div class="v2-apply-stat" data-mode="p">
                                <span class="v2-apply-stat__icon" aria-hidden="true">📦</span>
                                <div>
                                    <strong data-spbwc-apply-count-products><?php echo esc_html((string) $applied_count); ?></strong>
                                    <small><?php esc_html_e('individual products mapped', 'storelly-product-builder-for-woocommerce'); ?></small>
                                </div>
                            </div>
                            <div class="v2-apply-stat" data-mode="c">
                                <span class="v2-apply-stat__icon" aria-hidden="true">📁</span>
                                <div>
                                    <strong data-spbwc-apply-count-cats><?php echo esc_html((string) count($applied_cats)); ?></strong>
                                    <small><?php esc_html_e('categories mapped', 'storelly-product-builder-for-woocommerce'); ?></small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ════════════════════════════════════════════════════════
                 TAB PANEL: DESIGNER
                 ════════════════════════════════════════════════════════ -->
            <div class="v2-tab-panel" data-v2-panel="designer">
                <div class="v2-card">
                    <div class="v2-card__head v2-card__head--brand">
                        <h2 class="v2-card__title">
                            <span class="v2-card__title-icon">🎨</span>
                            <?php esc_html_e('Online Designer canvas', 'storelly-product-builder-for-woocommerce'); ?>
                        </h2>
                        <span class="v2-card__sub"><?php esc_html_e('Views customers can decorate', 'storelly-product-builder-for-woocommerce'); ?></span>
                    </div>
                    <div class="v2-card__body">
                        <p class="v2-form-row__help" style="margin:0 0 var(--nbd-space-3);">
                            <?php esc_html_e('Each view is a separate canvas (Front / Back / Inside …) with its own base image. Designer Component / Text / Image fields placed on the canvas will be customizable by customers.', 'storelly-product-builder-for-woocommerce'); ?>
                        </p>

                        <!-- Empty state when no views yet -->
                        <div class="v2-designer-empty" ng-show="!options.views || options.views.length === 0">
                            <span class="v2-designer-empty__icon" aria-hidden="true">🖼</span>
                            <h3 class="v2-designer-empty__title"><?php esc_html_e('No canvas views yet', 'storelly-product-builder-for-woocommerce'); ?></h3>
                            <p class="v2-designer-empty__body">
                                <?php esc_html_e('Add a view to start configuring the online designer. Each view holds the canvas base image and any designer fields you place on it.', 'storelly-product-builder-for-woocommerce'); ?>
                            </p>
                            <button type="button" class="v2-btn v2-btn--primary" ng-click="addView()">
                                <span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
                                <?php esc_html_e('Add first view', 'storelly-product-builder-for-woocommerce'); ?>
                            </button>
                        </div>

                        <!-- View grid -->
                        <div class="v2-views-grid" ng-show="options.views && options.views.length > 0">
                            <div class="v2-view-card" ng-repeat="view in options.views track by $index">
                                <div class="v2-view-card__thumb" ng-click="set_view_base($index)">
                                    <img ng-if="view.base_url" ng-src="{{view.base_url}}" alt="{{view.name}}" />
                                    <div ng-if="!view.base_url" class="v2-view-card__placeholder">
                                        <span aria-hidden="true">🖼</span>
                                        <small><?php esc_html_e('Click to upload base image', 'storelly-product-builder-for-woocommerce'); ?></small>
                                    </div>
                                    <button type="button" class="v2-view-card__edit-btn"
                                            ng-click="$event.stopPropagation(); set_view_base($index);"
                                            title="<?php esc_attr_e('Change base image', 'storelly-product-builder-for-woocommerce'); ?>">
                                        <span class="dashicons dashicons-edit" aria-hidden="true"></span>
                                    </button>
                                </div>
                                <div class="v2-view-card__body">
                                    <input class="v2-input v2-view-card__name"
                                           type="text"
                                           ng-model="view.name"
                                           name="options[views][{{$index}}][name]"
                                           placeholder="<?php esc_attr_e('View name (e.g. Front)', 'storelly-product-builder-for-woocommerce'); ?>" />
                                    <div class="v2-view-card__dims">
                                        <label>
                                            <span><?php esc_html_e('W', 'storelly-product-builder-for-woocommerce'); ?></span>
                                            <input class="v2-input" type="number" string-to-number min="0" step="1"
                                                   ng-model="view.base_width"
                                                   name="options[views][{{$index}}][base_width]" />
                                        </label>
                                        <span class="v2-view-card__times" aria-hidden="true">×</span>
                                        <label>
                                            <span><?php esc_html_e('H', 'storelly-product-builder-for-woocommerce'); ?></span>
                                            <input class="v2-input" type="number" string-to-number min="0" step="1"
                                                   ng-model="view.base_height"
                                                   name="options[views][{{$index}}][base_height]" />
                                        </label>
                                    </div>
                                    <!--
                                        AngularJS `ng-model` on type=hidden does not reliably
                                        update the DOM `value` attribute when the model is set
                                        programmatically (e.g. by set_view_base() after media
                                        library select). Without the DOM value, standard form
                                        submission posts an empty string and the saved
                                        `options[views][N][base]` ends up empty even though
                                        the admin uploaded an image. Use `{{ }}` interpolation
                                        on `value` so AngularJS keeps the attribute in sync.
                                    -->
                                    <input type="hidden" name="options[views][{{$index}}][base]" value="{{view.base}}" />
                                </div>
                                <button type="button" class="v2-view-card__remove"
                                        ng-click="removeView($index)"
                                        ng-show="options.views.length > 1"
                                        title="<?php esc_attr_e('Remove view', 'storelly-product-builder-for-woocommerce'); ?>">✕</button>
                            </div>

                            <!-- Add view tile -->
                            <button type="button" class="v2-view-card v2-view-card--add" ng-click="addView()">
                                <span class="v2-view-card__plus" aria-hidden="true">+</span>
                                <strong><?php esc_html_e('Add view', 'storelly-product-builder-for-woocommerce'); ?></strong>
                                <small><?php esc_html_e('Back / Inside / 3rd angle…', 'storelly-product-builder-for-woocommerce'); ?></small>
                            </button>
                        </div>

                        <!-- Helper: link to add designer fields -->
                        <div class="v2-designer-actions" ng-show="options.views && options.views.length > 0">
                            <div class="v2-section-head">
                                <span class="v2-section-head__icon">🧩</span>
                                <?php esc_html_e('Add designer fields to your canvas', 'storelly-product-builder-for-woocommerce'); ?>
                            </div>
                            <p class="v2-form-row__help" style="margin: 0 0 var(--nbd-space-3);">
                                <?php esc_html_e('Designer fields appear in the Builder tab. Click below to add one — then switch to Builder to position it on the canvas.', 'storelly-product-builder-for-woocommerce'); ?>
                            </p>
                            <div class="v2-palette__grid">
                                <button type="button" class="v2-chip" ng-click="add_field('nbpb_com', 'nbpb_com')">
                                    <span class="v2-chip__icon v2-chip__icon--com">⚛</span>
                                    <span class="v2-chip__label">
                                        <strong><?php esc_html_e('Designer Component', 'storelly-product-builder-for-woocommerce'); ?></strong>
                                        <small><?php esc_html_e('Background / shape holder', 'storelly-product-builder-for-woocommerce'); ?></small>
                                    </span>
                                </button>
                                <button type="button" class="v2-chip" ng-click="add_field('nbpb_text', 'nbpb_text')">
                                    <span class="v2-chip__icon v2-chip__icon--dt">Tx</span>
                                    <span class="v2-chip__label">
                                        <strong><?php esc_html_e('Designer Text', 'storelly-product-builder-for-woocommerce'); ?></strong>
                                        <small><?php esc_html_e('Editable text block', 'storelly-product-builder-for-woocommerce'); ?></small>
                                    </span>
                                </button>
                                <button type="button" class="v2-chip" ng-click="add_field('nbpb_image', 'nbpb_image')">
                                    <span class="v2-chip__icon v2-chip__icon--di">Im</span>
                                    <span class="v2-chip__label">
                                        <strong><?php esc_html_e('Designer Image', 'storelly-product-builder-for-woocommerce'); ?></strong>
                                        <small><?php esc_html_e('Image placeholder', 'storelly-product-builder-for-woocommerce'); ?></small>
                                    </span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ════════════════════════════════════════════════════════
                 TAB PANEL: OUTPUT (PDF)
                 ════════════════════════════════════════════════════════ -->
            <div class="v2-tab-panel" data-v2-panel="output">
                <div class="v2-card">
                    <div class="v2-card__head v2-card__head--brand">
                        <h2 class="v2-card__title">
                            <span class="v2-card__title-icon">📄</span>
                            <?php esc_html_e('Output design file (PDF)', 'storelly-product-builder-for-woocommerce'); ?>
                        </h2>
                    </div>
                    <div class="v2-card__body">
                        <div class="v2-form-row">
                            <label class="v2-form-row__label" for="dpi">
                                <?php esc_html_e('Output resolution — DPI', 'storelly-product-builder-for-woocommerce'); ?>
                            </label>
                            <p class="v2-form-row__help"><?php esc_html_e('Recommended: 300 for print, 72 for web preview.', 'storelly-product-builder-for-woocommerce'); ?></p>
                            <input name="options[design_output][dpi]" type="text" string-to-number
                                   id="dpi" class="v2-input" style="max-width:180px"
                                   ng-model="options.design_output.dpi" autocomplete="off" />
                        </div>
                        <div class="v2-form-row">
                            <label class="v2-form-row__label" for="dimension_unit">
                                <?php esc_html_e('Dimensions unit', 'storelly-product-builder-for-woocommerce'); ?>
                            </label>
                            <select id="dimension_unit" class="v2-select" style="max-width:180px"
                                    name="options[design_output][dimension_unit]"
                                    ng-model="options.design_output.dimension_unit">
                                <option value="cm">cm</option>
                                <option value="in">in</option>
                                <option value="mm">mm</option>
                                <option value="px">px</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ════════════════════════════════════════════════════════
                 TAB PANEL: IMPORT / EXPORT
                 ════════════════════════════════════════════════════════ -->
            <div class="v2-tab-panel" data-v2-panel="iox">
                <div class="v2-card">
                    <div class="v2-card__head v2-card__head--brand">
                        <h2 class="v2-card__title">
                            <span class="v2-card__title-icon">🔌</span>
                            <?php esc_html_e('Import / Export configuration', 'storelly-product-builder-for-woocommerce'); ?>
                        </h2>
                    </div>
                    <div class="v2-card__body">
                        <p style="margin: 0 0 var(--nbd-space-3); font-size: var(--text-base); color: var(--nbd-st-text-soft);">
                            <?php esc_html_e('Export this option as JSON to share or back up, or import a previously exported config.', 'storelly-product-builder-for-woocommerce'); ?>
                        </p>
                        <div style="display:flex; gap: var(--nbd-space-2); flex-wrap:wrap;">
                            <a ng-click="import()" class="v2-btn v2-btn--secondary">
                                <span class="dashicons dashicons-migrate" style="transform: rotate(180deg);" aria-hidden="true"></span>
                                <?php esc_html_e('Import', 'storelly-product-builder-for-woocommerce'); ?>
                            </a>
                            <a ng-click="export()" class="v2-btn v2-btn--secondary">
                                <span class="dashicons dashicons-migrate" aria-hidden="true"></span>
                                <?php esc_html_e('Export', 'storelly-product-builder-for-woocommerce'); ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ──────────────── Sticky save bar ──────────────── -->
            <div class="v2-savebar">
                <div class="v2-savebar__left">
                    <span class="v2-savebar__status<?php echo $is_new_option ? ' is-dirty' : ' is-clean'; ?>">
                        <?php if (!$is_new_option) :
                            /* translators: %s: modified time */
                            printf(esc_html__('Last saved %s', 'storelly-product-builder-for-woocommerce'), '<strong>' . esc_html($options['modified']) . '</strong>');
                        else :
                            esc_html_e('Unsaved draft', 'storelly-product-builder-for-woocommerce');
                        endif; ?>
                    </span>
                    <span class="v2-savebar__hint" aria-hidden="true">
                        <kbd>Ctrl</kbd>+<kbd>S</kbd>
                    </span>
                </div>
                <div class="v2-savebar__right">
                    <?php if (!$is_new_option && $options['published'] == 1) : ?>
                        <a class="v2-btn v2-btn--danger-link" href="<?php echo esc_url($link_unpublish); ?>"
                           onclick="return confirm('<?php echo esc_js(__('Move this option to trash?', 'storelly-product-builder-for-woocommerce')); ?>');">
                            <span class="dashicons dashicons-trash" aria-hidden="true"></span>
                            <?php esc_html_e('Trash', 'storelly-product-builder-for-woocommerce'); ?>
                        </a>
                    <?php endif; ?>
                    <!-- Open this option in the live product builder page.
                         Shown only when at least one Designer field exists. -->
                    <a href="<?php echo esc_url($link_create_pre_builder); ?>" target="_blank" rel="noopener"
                       class="v2-btn v2-btn--secondary v2-savebar__prebuilder"
                       ng-show="has_product_builder_field">
                        <span class="dashicons dashicons-external" aria-hidden="true"></span>
                        <?php esc_html_e('Pre-builder', 'storelly-product-builder-for-woocommerce'); ?>
                    </a>
                    <?php
                    // When launched from a product metabox, Cancel/Save return to
                    // that product. See docs/SPEC_LINKED_PRODUCT_UX.md §4.
                    $spbwc_cancel_link = ! empty( $product_id )
                        ? add_query_arg( array( 'post' => (int) $product_id, 'action' => 'edit' ), admin_url( 'post.php' ) )
                        : $link;
                    if ( ! empty( $product_id ) ) {
                        $spbwc_save_label = esc_attr__( 'Save & back to product', 'storelly-product-builder-for-woocommerce' );
                    } else {
                        $spbwc_save_label = $is_new_option
                            ? esc_attr__( 'Publish', 'storelly-product-builder-for-woocommerce' )
                            : esc_attr__( 'Update', 'storelly-product-builder-for-woocommerce' );
                    }
                    ?>
                    <a href="<?php echo esc_url($spbwc_cancel_link); ?>" class="v2-btn v2-btn--secondary">
                        <?php echo ! empty( $product_id ) ? esc_html__('Discard & back', 'storelly-product-builder-for-woocommerce') : esc_html__('Cancel', 'storelly-product-builder-for-woocommerce'); ?>
                    </a>
                    <input ng-disabled="!nboForm.$valid"
                           name="save" type="submit"
                           class="v2-btn v2-btn--primary v2-savebar__primary"
                           id="publish"
                           ng-click="updateJsonFields($event)"
                           accesskey="p"
                           value="<?php echo esc_attr( $spbwc_save_label ); ?>" />
                </div>
            </div>
        </form>

        <?php include_once('preview.php'); // legacy widget — hidden via CSS, kept for backward compat ?>
    </div>
</div>
<div class="nbp-loading-wrap">
    <div class="nbp-loading-spinner">
        <div class="nbp-loading-ball"></div>
        <p id="nbp-processing" style="display: none;font-weight: bold;white-space: nowrap;">
            <?php esc_html_e('Processing', 'storelly-product-builder-for-woocommerce'); ?>
            <span id="nbp-process-loaded"></span> / <span id="nbp-process-total"></span>
        </p>
    </div>
</div>

<!-- Toast region for AJAX feedback -->
<div class="spbwc-edit-v2-toast" id="spbwc-edit-toast" role="status" aria-live="polite" hidden></div>

<script>
/**
 * v2 edit page — tabs, device toggle, AJAX save.
 * Vanilla, no jQuery dependency.
 */
(function () {
    var AJAX_URL = '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';
    var I18N = {
        saving:   '<?php echo esc_js(__('Saving…', 'storelly-product-builder-for-woocommerce')); ?>',
        saved:    '<?php echo esc_js(__('Option saved.', 'storelly-product-builder-for-woocommerce')); ?>',
        invalid:  '<?php echo esc_js(__('Please fill in all required fields before saving.', 'storelly-product-builder-for-woocommerce')); ?>',
        error:    '<?php echo esc_js(__('Could not save. Try again.', 'storelly-product-builder-for-woocommerce')); ?>'
    };

    function bindTabs(root) {
        var tabs = root.querySelectorAll('.v2-tabs .v2-tab');
        tabs.forEach(function (tab) {
            tab.addEventListener('click', function (e) {
                e.preventDefault();
                var target = tab.getAttribute('data-v2-tab');
                tabs.forEach(function (t) {
                    t.classList.remove('is-active');
                    t.setAttribute('aria-selected', 'false');
                });
                tab.classList.add('is-active');
                tab.setAttribute('aria-selected', 'true');
                root.querySelectorAll('.v2-tab-panel').forEach(function (p) {
                    p.classList.toggle('is-active', p.getAttribute('data-v2-panel') === target);
                });
            });
        });
    }

    function bindDeviceToggle(root) {
        root.querySelectorAll('.v2-device .v2-device__btn').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                var panel = btn.closest('.v2-preview');
                if (!panel) return;
                panel.querySelectorAll('.v2-device__btn').forEach(function (b) {
                    b.classList.remove('is-active');
                    b.setAttribute('aria-selected', 'false');
                });
                btn.classList.add('is-active');
                btn.setAttribute('aria-selected', 'true');
                panel.dataset.device = btn.getAttribute('data-v2-device');
            });
        });
    }

    function showToast(msg, kind) {
        var t = document.getElementById('spbwc-edit-toast');
        if (!t) return;
        t.textContent = msg;
        t.className = 'spbwc-edit-v2-toast is-' + (kind || 'info');
        t.hidden = false;
        clearTimeout(showToast._t);
        showToast._t = setTimeout(function () { t.hidden = true; }, 3500);
    }

    /**
     * Hijack the legacy POST submit and route through the AJAX
     * endpoint. The handler reuses spbwc_save_option() server-side
     * so validation/storage stay identical to the classic flow.
     */
    // Dirty state tracker — flipped to true on any user input, cleared
    // after a successful save. Drives the unsaved-warning + autosave.
    var formDirty = false;
    var autosaveTimer = null;
    var saveInFlight = false;
    var AUTOSAVE_DELAY_MS = 60000; // 60s after last edit
    var I18N_EXT = {
        unsaved:   '<?php echo esc_js(__('You have unsaved changes. Leave anyway?', 'storelly-product-builder-for-woocommerce')); ?>',
        autosaved: '<?php echo esc_js(__('Auto-saved', 'storelly-product-builder-for-woocommerce')); ?>'
    };

    function markDirty() {
        if (!formDirty) {
            formDirty = true;
            updateSavebarStatus();
        }
        scheduleAutosave();
    }
    function markClean() {
        formDirty = false;
        if (autosaveTimer) { clearTimeout(autosaveTimer); autosaveTimer = null; }
        updateSavebarStatus();
    }
    function updateSavebarStatus() {
        var status = document.querySelector('.spbwc-edit-v2 .v2-savebar__status');
        if (!status) return;
        if (formDirty) {
            status.classList.add('is-dirty');
            status.classList.remove('is-clean');
        } else {
            status.classList.remove('is-dirty');
            status.classList.add('is-clean');
        }
    }
    function scheduleAutosave() {
        if (autosaveTimer) clearTimeout(autosaveTimer);
        autosaveTimer = setTimeout(function () {
            var form = document.querySelector('.spbwc-edit-v2 [data-spbwc-edit-form]');
            if (!form || !formDirty || saveInFlight) return;
            performSave(form, /* silent */ true);
        }, AUTOSAVE_DELAY_MS);
    }

    /**
     * Centralized save runner — used by submit button, Ctrl+S, and autosave.
     */
    function performSave(form, silent) {
        if (saveInFlight) return Promise.resolve(false);
        if (!form.checkValidity || !form.checkValidity()) {
            if (!silent) showToast(I18N.invalid, 'error');
            return Promise.resolve(false);
        }
        saveInFlight = true;
        var saveBtn = form.querySelector('#publish');
        var statusEl = form.closest('.spbwc-edit-v2').querySelector('.v2-savebar__status');
        var heroKpiModified = form.closest('.spbwc-edit-v2').querySelector('.spbwc-page-hero__kpis .kpi__value[data-kpi="modified"]');

        if (saveBtn && !silent) {
            saveBtn.disabled = true;
            saveBtn.dataset.origLabel = saveBtn.value || saveBtn.textContent || '';
            saveBtn.value = I18N.saving;
        }
        if (statusEl && !silent) {
            statusEl.dataset.orig = statusEl.innerHTML;
            statusEl.textContent = I18N.saving;
        }

        var fd = new FormData(form);
        fd.append('action', 'spbwc_save_option_ajax');
        if (silent) fd.append('autosave', '1');

        return fetch(AJAX_URL, {
            method: 'POST',
            credentials: 'same-origin',
            body: fd
        }).then(function (r) { return r.json(); }).then(function (res) {
            if (!res || !res.success) {
                var m = res && res.data && res.data.msg ? res.data.msg : I18N.error;
                throw new Error(m);
            }
            var data = res.data;
            var hidden = form.querySelector('input[name="option_id"]');
            if (hidden && data.id) { hidden.value = data.id; }
            if (statusEl) {
                var label = silent ? I18N_EXT.autosaved : I18N.saved.replace('.', '');
                statusEl.innerHTML = label + ' · <strong>' + (data.modified || '') + '</strong>';
            }
            if (heroKpiModified && data.modified) {
                heroKpiModified.textContent = data.modified.replace(/^.* /, '');
            }
            if (!silent) showToast(data.msg || I18N.saved, 'success');
            markClean();
            return true;
        }).catch(function (err) {
            if (statusEl && statusEl.dataset.orig) { statusEl.innerHTML = statusEl.dataset.orig; }
            if (!silent) showToast((err && err.message) ? err.message : I18N.error, 'error');
            return false;
        }).finally(function () {
            saveInFlight = false;
            if (saveBtn && !silent) {
                saveBtn.disabled = false;
                if (saveBtn.dataset.origLabel) { saveBtn.value = saveBtn.dataset.origLabel; }
            }
        });
    }

    function bindAjaxSave(root) {
        var form = root.querySelector('[data-spbwc-edit-form]');
        if (!form) return;
        var saveBtn = form.querySelector('#publish');

        // Track dirty state via every interactive child input.
        form.addEventListener('input',  markDirty);
        form.addEventListener('change', markDirty);

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            if (saveBtn && saveBtn.hasAttribute('disabled')) {
                showToast(I18N.invalid, 'error');
                return;
            }
            performSave(form, false);
        });
    }

    /**
     * G1 — Warn user before navigating away with unsaved changes.
     */
    function bindUnsavedWarning() {
        window.addEventListener('beforeunload', function (e) {
            if (!formDirty || saveInFlight) return undefined;
            // Modern browsers ignore the custom message but still show
            // a generic "Leave site?" dialog when returnValue is set.
            e.preventDefault();
            e.returnValue = I18N_EXT.unsaved;
            return I18N_EXT.unsaved;
        });
    }

    /**
     * D1 — Apply tab mode toggle (Products / Categories).
     * Switches the visible picker and writes the chosen mode into the
     * hidden `apply_for` input so the save handler stores it.
     */
    function bindApplyMode(root) {
        var modeBtns = root.querySelectorAll('[data-spbwc-apply-mode] [data-apply-mode]');
        var hidden   = root.querySelector('#apply_for');
        var prodWrap = root.querySelector('[data-spbwc-apply-products]');
        var catsWrap = root.querySelector('[data-spbwc-apply-cats]');
        if (!modeBtns.length || !hidden) return;

        function setMode(m) {
            hidden.value = m;
            modeBtns.forEach(function (b) {
                b.classList.toggle('is-active', b.getAttribute('data-apply-mode') === m);
            });
            if (prodWrap) prodWrap.hidden = (m !== 'p');
            if (catsWrap) catsWrap.hidden = (m !== 'c');
            markDirty();
        }
        modeBtns.forEach(function (b) {
            b.addEventListener('click', function () { setMode(b.getAttribute('data-apply-mode')); });
        });
    }

    /**
     * G3 — Ctrl/Cmd+S → save. Esc → cancel modals (passes through).
     */
    function bindKeyboardShortcuts(root) {
        document.addEventListener('keydown', function (e) {
            var isSave = (e.ctrlKey || e.metaKey) && (e.key === 's' || e.key === 'S');
            if (isSave) {
                e.preventDefault();
                var form = root.querySelector('[data-spbwc-edit-form]');
                if (form) performSave(form, false);
            }
        });
    }

    /**
     * Palette hover → reveal contextual help drawer with use case +
     * an example. Falls back to the first chip when nothing is hovered.
     */
    function bindPaletteHelp(root) {
        var hint = root.querySelector('#v2-palette-hint');
        if (!hint) return;
        var textEl = hint.querySelector('[data-help-target]');
        var exampleEl = hint.querySelector('[data-example-target]');
        var chips = root.querySelectorAll('.v2-palette .v2-chip[data-spbwc-help]');
        if (!chips.length) return;

        function show(chip) {
            var help = chip.getAttribute('data-spbwc-help') || '';
            var ex   = chip.getAttribute('data-spbwc-example') || '';
            if (textEl) textEl.textContent = help;
            if (exampleEl) {
                exampleEl.textContent = ex;
                exampleEl.style.display = ex ? '' : 'none';
            }
            hint.hidden = false;
            chips.forEach(function (c) { c.classList.toggle('is-hovered', c === chip); });
        }
        function reset() {
            chips.forEach(function (c) { c.classList.remove('is-hovered'); });
            hint.hidden = true;
        }

        chips.forEach(function (chip) {
            chip.addEventListener('mouseenter', function () { show(chip); });
            chip.addEventListener('focus',     function () { show(chip); });
            chip.addEventListener('mouseleave', reset);
            chip.addEventListener('blur',       reset);
        });
    }

    /**
     * Inject a "What this field does" help banner inside every
     * .pcpb-field-wrap card body, based on data_type + input_type.
     * Re-runs on Angular digest because new cards may appear after
     * add_field_preset() calls.
     */
    var FIELD_HELP = {
        'm':  {
            title: '<?php echo esc_js(__('Multi-choice field', 'storelly-product-builder-for-woocommerce')); ?>',
            body:  '<?php echo esc_js(__('Customer picks ONE option from a list of attributes (swatches or list). Best for material, finish, color, size variants.', 'storelly-product-builder-for-woocommerce')); ?>',
            example: '<?php echo esc_js(__('Example: Paper → Matte 250gsm, Soft-touch 350gsm, Glossy 300gsm', 'storelly-product-builder-for-woocommerce')); ?>'
        },
        'i:t': {
            title: '<?php echo esc_js(__('Text input field', 'storelly-product-builder-for-woocommerce')); ?>',
            body:  '<?php echo esc_js(__('Customer types a single-line value. Use min/max characters to constrain. Optional price-per-character pricing.', 'storelly-product-builder-for-woocommerce')); ?>',
            example: '<?php echo esc_js(__('Example: Custom name printed on card (max 30 chars)', 'storelly-product-builder-for-woocommerce')); ?>'
        },
        'i:n': {
            title: '<?php echo esc_js(__('Number field', 'storelly-product-builder-for-woocommerce')); ?>',
            body:  '<?php echo esc_js(__('Customer enters a numeric value within a min/max range, adjustable by a stepper. Use for counts, pages, sizes.', 'storelly-product-builder-for-woocommerce')); ?>',
            example: '<?php echo esc_js(__('Example: Number of pages (min 1, max 500, step 1)', 'storelly-product-builder-for-woocommerce')); ?>'
        },
        'i:r': {
            title: '<?php echo esc_js(__('Number range field', 'storelly-product-builder-for-woocommerce')); ?>',
            body:  '<?php echo esc_js(__('Customer picks a numeric value with a slider between a min and max. Uses the same min/max/step bounds as the Number field.', 'storelly-product-builder-for-woocommerce')); ?>',
            example: '<?php echo esc_js(__('Example: Quantity slider from 10 to 1000, step 10', 'storelly-product-builder-for-woocommerce')); ?>'
        },
        'i:a': {
            title: '<?php echo esc_js(__('Textarea field', 'storelly-product-builder-for-woocommerce')); ?>',
            body:  '<?php echo esc_js(__('Customer types a multi-line message. Use for longer instructions or notes to the printer.', 'storelly-product-builder-for-woocommerce')); ?>',
            example: '<?php echo esc_js(__('Example: Special print instructions (deadlines, color preferences)', 'storelly-product-builder-for-woocommerce')); ?>'
        },
        'i:u': {
            title: '<?php echo esc_js(__('File upload field', 'storelly-product-builder-for-woocommerce')); ?>',
            body:  '<?php echo esc_js(__('Customer uploads their print-ready artwork. Restrict accepted formats and max size to keep the production pipeline clean.', 'storelly-product-builder-for-woocommerce')); ?>',
            example: '<?php echo esc_js(__('Example: Upload your print file · PDF / AI / PNG up to 25 MB', 'storelly-product-builder-for-woocommerce')); ?>'
        }
    };

    function fieldHelpKey(card) {
        var dataTypeEl  = card.querySelector('select[ng-model$=".data_type.value"]');
        var inputTypeEl = card.querySelector('select[ng-model$=".input_type.value"]');
        var dt = dataTypeEl ? dataTypeEl.value : null;
        var it = inputTypeEl ? inputTypeEl.value : null;
        if (!dt) {
            // Inspect Angular model via the attribute (initial render before user changes)
            var scopedAttrs = card.querySelectorAll('[ng-model]');
            scopedAttrs.forEach(function (el) {
                var m = el.getAttribute('ng-model');
                if (/data_type\.value$/.test(m) && el.value) dt = el.value;
                if (/input_type\.value$/.test(m) && el.value) it = el.value;
            });
        }
        if (dt === 'm') return 'm';
        if (dt === 'i' && it) return 'i:' + it;
        return null;
    }

    function injectFieldHelp(card) {
        if (!card || card.querySelector('.v2-field-help')) return;
        var key = fieldHelpKey(card);
        var help = key && FIELD_HELP[key];
        if (!help) return;

        var tabGeneral = card.querySelector('.tab-general.pcpb-field-content');
        if (!tabGeneral) return;

        var box = document.createElement('div');
        box.className = 'v2-field-help';
        box.innerHTML =
            '<div class="v2-field-help__icon" aria-hidden="true">💡</div>' +
            '<div>' +
                '<p class="v2-field-help__title">' + help.title + '</p>' +
                '<p class="v2-field-help__body">' + help.body + '</p>' +
                '<p class="v2-field-help__example">' + help.example + '</p>' +
            '</div>';
        tabGeneral.insertBefore(box, tabGeneral.firstChild);
    }

    function rescanFieldHelp(root) {
        root.querySelectorAll('.pcpb-field-wrap').forEach(injectFieldHelp);
    }

    function bindFieldHelp(root) {
        // Initial pass.
        setTimeout(function () { rescanFieldHelp(root); }, 200);
        // Watch for new cards added by add_field_preset (Angular re-renders ng-repeat).
        var container = root.querySelector('.pcpb-fields-builder');
        if (!container || !('MutationObserver' in window)) return;
        var mo = new MutationObserver(function () {
            // Debounce — Angular often fires many mutations per digest.
            clearTimeout(bindFieldHelp._t);
            bindFieldHelp._t = setTimeout(function () {
                rescanFieldHelp(root);
                rescanFieldSections(root);
            }, 80);
        });
        mo.observe(container, { childList: true, subtree: true });
    }

    /**
     * Group field-body property rows into visual sections by injecting
     * .v2-section-head dividers. The legacy template renders all rows
     * linearly; sections make them scannable.
     *
     * Mapping derived from the ng-template id of the ng-include:
     *   - basics:        title, description
     *   - configuration: data_type, input_type, input_option, text_option, upload_option
     *   - display:       enabled, published
     *   - validation:    required
     *   - pricing:       price_type, price
     *   - attributes:    attributes
     */
    /**
     * Consolidated section map.
     * Basics groups title + description.
     * Configuration absorbs the legacy Display + Validation rows since they
     * are all "how this field behaves" settings — keeping them under one
     * roof reduces visual fragmentation.
     * Pricing stays standalone because the financial implication deserves
     * its own clearly-labelled block.
     */
    var FIELD_SECTIONS = [
        { key: 'basics',        icon: 'ℹ',  label: '<?php echo esc_js(__('Basics', 'storelly-product-builder-for-woocommerce')); ?>',
          ids: ['field_body_title', 'field_body_description'] },
        { key: 'configuration', icon: '⚙',  label: '<?php echo esc_js(__('Configuration', 'storelly-product-builder-for-woocommerce')); ?>',
          ids: ['field_body_data_type', 'field_body_input_type', 'field_body_input_option', 'field_body_text_option', 'field_body_upload_option', 'field_body_enabled', 'field_body_published', 'field_body_required'] },
        { key: 'pricing',       icon: '💲', label: '<?php echo esc_js(__('Pricing', 'storelly-product-builder-for-woocommerce')); ?>',
          ids: ['field_body_price_type', 'field_body_price'] },
        { key: 'attributes',    icon: '🎨', label: '<?php echo esc_js(__('Attributes', 'storelly-product-builder-for-woocommerce')); ?>',
          ids: ['field_body_attributes'] },
        { key: 'conditional',   icon: '⚡', label: '<?php echo esc_js(__('Conditional logic', 'storelly-product-builder-for-woocommerce')); ?>',
          ids: ['field_body_conditional_depend'] }
    ];

    /**
     * Wrap each section's property rows into a single styled block so
     * users can visually scan "Basics / Configuration / Display / …"
     * instead of one long stream of rows.
     *
     * We resolve each section's anchor (its first rendered .pcpb-field-info)
     * and slice the contiguous run of sibling rows up to the next anchor.
     * That run gets re-parented into a fresh `.v2-form-section` block.
     */
    /**
     * For a `.pcpb-field-info` descendant, walk up to the direct child of
     * `container`. ng-include wraps each row in its own element, so the
     * info row's actual sibling boundary is at that level — not its parent.
     */
    function topLevelChild(container, node) {
        var cur = node;
        while (cur && cur.parentNode !== container) {
            cur = cur.parentNode;
        }
        return cur;
    }

    function injectFieldSections(card) {
        if (!card || card.dataset.v2Sections === '1') return;
        var general = card.querySelector('.tab-general.pcpb-field-content');
        if (!general) return;

        // Resolve all anchors first.
        var anchors = [];
        FIELD_SECTIONS.forEach(function (sec) {
            var anchor = null;
            for (var j = 0; j < sec.ids.length; j++) {
                var key = sec.ids[j].replace('field_body_', '');
                anchor = general.querySelector('.pcpb-field-info[ng-show*="' + key + '"]');
                if (!anchor) {
                    var modelEl = general.querySelector('[ng-model*=".' + key + '.value"], [ng-model$=".' + key + '"]');
                    if (modelEl) anchor = modelEl.closest('.pcpb-field-info');
                }
                if (!anchor && key === 'attributes') {
                    var attrAnchor = general.querySelector('[ng-repeat*="field.general.attributes.options"]');
                    if (attrAnchor) anchor = attrAnchor.closest('.pcpb-field-info');
                }
                if (anchor) break;
            }
            if (anchor) {
                // Use the top-level child (e.g. ng-include host) as the slice
                // boundary so we can move sibling rows across containers.
                var boundary = topLevelChild(general, anchor);
                if (boundary && anchors.findIndex(function (a) { return a.boundary === boundary; }) === -1) {
                    anchors.push({ sec: sec, node: anchor, boundary: boundary });
                }
            }
        });

        // Sort anchors by their DOM position.
        anchors.sort(function (a, b) {
            return (a.boundary.compareDocumentPosition(b.boundary) & Node.DOCUMENT_POSITION_FOLLOWING) ? -1 : 1;
        });

        anchors.forEach(function (item, idx) {
            var start = item.boundary;
            var stop  = (idx < anchors.length - 1) ? anchors[idx + 1].boundary : null;

            var wrap = document.createElement('div');
            wrap.className = 'v2-form-section v2-form-section--' + item.sec.key;
            wrap.setAttribute('data-section', item.sec.key);

            var head = document.createElement('div');
            head.className = 'v2-form-section__head';
            head.innerHTML =
                '<span class="v2-form-section__icon">' + item.sec.icon + '</span>' +
                '<span class="v2-form-section__label">' + item.sec.label + '</span>';

            var body = document.createElement('div');
            body.className = 'v2-form-section__body';

            wrap.appendChild(head);
            wrap.appendChild(body);

            general.insertBefore(wrap, start);

            // Walk siblings of `general` starting from `start`, move into body
            // until `stop` is hit. This correctly moves whole ng-include hosts
            // (and any interleaved comment nodes) into the section body.
            var node = start;
            while (node && node !== stop) {
                var next = node.nextSibling;
                body.appendChild(node);
                node = next;
            }
        });

        card.dataset.v2Sections = '1';
    }

    function rescanFieldSections(root) {
        root.querySelectorAll('.pcpb-field-wrap').forEach(injectFieldSections);
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.spbwc-edit-v2').forEach(function (root) {
            bindTabs(root);
            bindDeviceToggle(root);
            bindAjaxSave(root);
            bindPaletteHelp(root);
            bindFieldHelp(root);
            bindKeyboardShortcuts(root);
            bindApplyMode(root);
            // Initial section header inject after Angular paints
            setTimeout(function () { rescanFieldSections(root); }, 250);
        });
        // Global (window-level) listeners
        bindUnsavedWarning();
    });
}());
</script>
