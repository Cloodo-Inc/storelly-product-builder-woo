<?php if (!defined('ABSPATH')) exit; // Exit if accessed directly
/**
 * Font Manager view.
 * Variables available: $subsets, $current_subset, $custom_fonts
 */
$custom_fonts = isset( $custom_fonts ) && is_array( $custom_fonts ) ? $custom_fonts : array();
?>
<div class="wrap storelly-container spbwc-fonts-wrap">

    <!-- ── Page hero ── -->
    <header class="spbwc-page-hero">
        <div class="spbwc-page-hero__grid">
            <div class="spbwc-page-hero__body">
                <div class="spbwc-page-hero__eyebrow">
                    <span class="dashicons dashicons-admin-plugins" aria-hidden="true"></span>
                    <?php esc_html_e( 'Storelly Product Builder', 'storelly-product-builder-for-woocommerce' ); ?>
                </div>
                <h1 class="spbwc-page-hero__title">
                    <span class="dashicons dashicons-editor-textcolor" aria-hidden="true"></span>
                    <?php esc_html_e( 'Font Manager', 'storelly-product-builder-for-woocommerce' ); ?>
                </h1>
                <p class="spbwc-page-hero__subtitle">
                    <?php esc_html_e( 'Manage Google Fonts and upload custom font files for the design editor.', 'storelly-product-builder-for-woocommerce' ); ?>
                </p>
            </div>
            <div class="spbwc-page-hero__actions">
                <a href="https://fonts.google.com" target="_blank" rel="noopener noreferrer"
                   class="spbwc-cta-btn spbwc-cta-btn--ghost">
                    <span class="dashicons dashicons-external" aria-hidden="true"></span>
                    <?php esc_html_e( 'Google Fonts', 'storelly-product-builder-for-woocommerce' ); ?>
                </a>
            </div>
        </div>
    </header>

    <!-- ════════════════════════════════════════════════════
         Custom Fonts — standalone section (jQuery, no Angular)
         ════════════════════════════════════════════════════ -->
    <div class="spbwc-custom-fonts" id="spbwc-custom-fonts-section">

        <!-- Section header -->
        <div class="spbwc-custom-fonts__header">
            <div class="spbwc-custom-fonts__title">
                <span class="dashicons dashicons-media-default" aria-hidden="true"></span>
                <span><?php esc_html_e( 'Custom Fonts', 'storelly-product-builder-for-woocommerce' ); ?></span>
                <span class="spbwc-custom-fonts__badge" id="spbwc-custom-count"><?php echo count( $custom_fonts ); ?></span>
            </div>
            <button type="button" class="spbwc-cta-btn spbwc-cta-btn--ghost" id="spbwc-toggle-upload">
                <span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
                <?php esc_html_e( 'Upload Font', 'storelly-product-builder-for-woocommerce' ); ?>
            </button>
        </div>

        <!-- Upload panel (hidden by default) -->
        <div class="spbwc-upload-panel" id="spbwc-upload-panel" hidden>

            <!-- Step 1: Drop zone -->
            <div class="spbwc-drop-zone" id="spbwc-drop-zone">
                <span class="dashicons dashicons-upload spbwc-drop-zone__icon" aria-hidden="true"></span>
                <p class="spbwc-drop-zone__primary">
                    <?php esc_html_e( 'Drag & drop your font file here', 'storelly-product-builder-for-woocommerce' ); ?>
                </p>
                <p class="spbwc-drop-zone__secondary">
                    <?php esc_html_e( 'or', 'storelly-product-builder-for-woocommerce' ); ?>
                    <label class="spbwc-drop-zone__browse" for="spbwc-font-file">
                        <?php esc_html_e( 'browse to upload', 'storelly-product-builder-for-woocommerce' ); ?>
                    </label>
                </p>
                <p class="spbwc-drop-zone__hint">TTF &middot; OTF &middot; WOFF &middot; WOFF2</p>
                <input type="file" id="spbwc-font-file"
                       accept=".ttf,.otf,.woff,.woff2"
                       class="spbwc-file-input"
                       aria-label="<?php esc_attr_e( 'Select font file', 'storelly-product-builder-for-woocommerce' ); ?>">
            </div>

            <!-- Step 2: Preview + form (shown after file selected) -->
            <div class="spbwc-upload-form" id="spbwc-upload-form" hidden>
                <div class="spbwc-upload-preview">
                    <p class="spbwc-upload-preview__label"><?php esc_html_e( 'Preview', 'storelly-product-builder-for-woocommerce' ); ?></p>
                    <p class="spbwc-upload-preview__text" id="spbwc-preview-text">
                        The quick brown fox jumps over the lazy dog
                    </p>
                    <span class="spbwc-upload-preview__file" id="spbwc-selected-filename"></span>
                </div>

                <div class="spbwc-upload-fields">
                    <div class="spbwc-upload-field">
                        <label class="spbwc-upload-field__label" for="spbwc-font-name">
                            <?php esc_html_e( 'Font name', 'storelly-product-builder-for-woocommerce' ); ?>
                        </label>
                        <input type="text" id="spbwc-font-name"
                               class="spbwc-upload-input"
                               placeholder="<?php esc_attr_e( 'e.g. My Custom Font', 'storelly-product-builder-for-woocommerce' ); ?>">
                    </div>
                    <div class="spbwc-upload-field">
                        <label class="spbwc-upload-field__label" for="spbwc-font-category">
                            <?php esc_html_e( 'Category', 'storelly-product-builder-for-woocommerce' ); ?>
                        </label>
                        <select id="spbwc-font-category" class="spbwc-font-select">
                            <option value="sans-serif"><?php esc_html_e( 'Sans Serif', 'storelly-product-builder-for-woocommerce' ); ?></option>
                            <option value="serif">Serif</option>
                            <option value="display">Display</option>
                            <option value="handwriting">Handwriting</option>
                            <option value="monospace">Monospace</option>
                        </select>
                    </div>
                </div>

                <div class="spbwc-upload-actions">
                    <button type="button" class="spbwc-cta-btn spbwc-cta-btn--solid" id="spbwc-submit-font">
                        <span class="dashicons dashicons-upload" aria-hidden="true"></span>
                        <?php esc_html_e( 'Upload Font', 'storelly-product-builder-for-woocommerce' ); ?>
                    </button>
                    <button type="button" class="spbwc-font-action-btn" id="spbwc-cancel-upload">
                        <?php esc_html_e( 'Cancel', 'storelly-product-builder-for-woocommerce' ); ?>
                    </button>
                </div>
            </div>

            <!-- Upload progress bar -->
            <div class="spbwc-upload-progress" id="spbwc-upload-progress" hidden>
                <div class="spbwc-upload-progress__bar" id="spbwc-upload-progress-bar"></div>
                <span class="spbwc-upload-progress__label"><?php esc_html_e( 'Uploading…', 'storelly-product-builder-for-woocommerce' ); ?></span>
            </div>
        </div><!-- .spbwc-upload-panel -->

        <!-- Custom fonts grid -->
        <div class="spbwc-custom-grid" id="spbwc-custom-grid">

            <?php if ( empty( $custom_fonts ) ) : ?>
            <div class="spbwc-custom-empty" id="spbwc-custom-empty">
                <span class="dashicons dashicons-media-default spbwc-custom-empty__icon" aria-hidden="true"></span>
                <p><?php esc_html_e( 'No custom fonts yet. Click "Upload Font" to add your first one.', 'storelly-product-builder-for-woocommerce' ); ?></p>
            </div>
            <?php else : ?>
            <?php foreach ( $custom_fonts as $cf ) :
                if ( empty( $cf['id'] ) || empty( $cf['name'] ) || empty( $cf['url'] ) ) continue;
                $cf_id  = esc_attr( $cf['id'] );
                $cf_url = esc_url( $cf['url'] );
                $cf_fmt = isset( $cf['format'] ) ? esc_attr( $cf['format'] ) : 'woff2';
                $cf_cat = isset( $cf['category'] ) ? esc_html( $cf['category'] ) : 'sans-serif';
            ?>
            <style>
                @font-face {
                    font-family: '<?php echo esc_js( $cf['name'] ); ?>';
                    src: url('<?php echo esc_url( $cf_url ); ?>') format('<?php echo esc_attr( $cf_fmt ); ?>');
                }
            </style>
            <div class="spbwc-custom-card" data-font-id="<?php echo esc_attr( $cf_id ); ?>">
                <div class="spbwc-custom-card__inner">
                    <p class="spbwc-custom-card__name" title="<?php echo esc_attr( $cf['name'] ); ?>">
                        <?php echo esc_html( $cf['name'] ); ?>
                    </p>
                    <p class="spbwc-custom-card__sample"
                       style="font-family: '<?php echo esc_attr( $cf['name'] ); ?>',sans-serif">
                        Abc Xyz 123
                    </p>
                </div>
                <div class="spbwc-custom-card__footer">
                    <span class="spbwc-custom-card__category"><?php echo esc_html( $cf_cat ); ?></span>
                    <button type="button"
                            class="spbwc-custom-card__delete"
                            data-font-id="<?php echo esc_attr( $cf_id ); ?>"
                            aria-label="<?php esc_attr_e( 'Delete font', 'storelly-product-builder-for-woocommerce' ); ?>">
                        <span class="dashicons dashicons-trash" aria-hidden="true"></span>
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>

        </div><!-- .spbwc-custom-grid -->
    </div><!-- .spbwc-custom-fonts -->

    <!-- ── Divider between Custom and Google Fonts ── -->
    <div class="spbwc-section-divider">
        <span class="spbwc-section-divider__label">
            <span class="dashicons dashicons-google" aria-hidden="true"></span>
            <?php esc_html_e( 'Google Fonts', 'storelly-product-builder-for-woocommerce' ); ?>
        </span>
    </div>

    <!-- ════════════════════════════════════════════════════
         Google Fonts — AngularJS app
         ════════════════════════════════════════════════════ -->
    <div class="spbwc-font-manager" ng-app="font-app" ng-controller="fontCtrl" ng-cloak>

        <!-- Loading overlay — JS targets .showbox via jQuery -->
        <div class="showbox" style="display: none;">
            <div class="loader">
                <svg class="circular" viewBox="25 25 50 50">
                    <circle class="path" cx="50" cy="50" r="20" fill="none" stroke-width="2" stroke-miterlimit="10"/>
                </svg>
            </div>
        </div>

        <!-- ── Filter toolbar ── -->
        <div class="spbwc-font-toolbar">
            <div class="spbwc-font-search">
                <span class="dashicons dashicons-search spbwc-font-search__icon" aria-hidden="true"></span>
                <input type="text"
                       class="spbwc-font-search__input"
                       ng-model="filterFont.name"
                       placeholder="<?php esc_attr_e( 'Search font name…', 'storelly-product-builder-for-woocommerce' ); ?>"
                       ng-change="resetCurentPage()">
            </div>

            <div class="spbwc-font-toolbar__filters">
                <select class="spbwc-font-select" ng-model="filterFont.category" ng-change="resetCurentPage()">
                    <option value=""><?php esc_html_e( 'All categories', 'storelly-product-builder-for-woocommerce' ); ?></option>
                    <option value="serif">Serif</option>
                    <option value="sans-serif">Sans Serif</option>
                    <option value="display">Display</option>
                    <option value="handwriting">Handwriting</option>
                    <option value="monospace">Monospace</option>
                </select>

                <select class="spbwc-font-select" ng-model="filterFont.subset" ng-change="resetCurentPage()">
                    <option value=""><?php esc_html_e( 'All subsets', 'storelly-product-builder-for-woocommerce' ); ?></option>
                    <?php
                    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variable.
                    foreach ($subsets as $key => $subset) :
                    ?>
                    <option value="<?php echo esc_attr($key); ?>" <?php selected($key, $current_subset); ?>><?php echo esc_html($subset['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- ── Controls bar ── -->
        <div class="spbwc-font-bar">
            <div class="spbwc-font-bar__left">
                <span class="spbwc-font-count">
                    <strong>{{fonts.length}}</strong>&nbsp;<?php esc_html_e( 'fonts', 'storelly-product-builder-for-woocommerce' ); ?>
                </span>
                <button type="button" class="spbwc-font-action-btn" ng-click="selectAll()">
                    <span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
                    <?php esc_html_e( 'Select all', 'storelly-product-builder-for-woocommerce' ); ?>
                </button>
                <button type="button" class="spbwc-font-action-btn" ng-click="unselectAll()">
                    <span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
                    <?php esc_html_e( 'Clear all', 'storelly-product-builder-for-woocommerce' ); ?>
                </button>
            </div>

            <div class="spbwc-font-bar__right">
                <select class="spbwc-font-select spbwc-font-select--sm" ng-model="filterFont.select" ng-change="resetCurentPage()">
                    <option value=""><?php esc_html_e( 'All fonts', 'storelly-product-builder-for-woocommerce' ); ?></option>
                    <option value="selected"><?php esc_html_e( 'Selected only', 'storelly-product-builder-for-woocommerce' ); ?></option>
                    <option value="unselected"><?php esc_html_e( 'Not selected', 'storelly-product-builder-for-woocommerce' ); ?></option>
                </select>

                <label class="spbwc-font-label" for="nbd-page-size"><?php esc_html_e( 'Per page:', 'storelly-product-builder-for-woocommerce' ); ?></label>
                <select id="nbd-page-size" class="spbwc-font-select spbwc-font-select--sm" ng-model="filterFont.pageSize" ng-change="resetCurentPage()">
                    <option ng-value="5">4</option>
                    <option ng-value="10">12</option>
                    <option ng-value="20">20</option>
                    <option ng-value="30">36</option>
                    <option ng-value="50">56</option>
                </select>

                <button type="button" class="spbwc-cta-btn spbwc-cta-btn--solid spbwc-font-save-btn" ng-click="updateGoogleFont($event)">
                    <span class="dashicons dashicons-saved" aria-hidden="true"></span>
                    <?php esc_html_e( 'Save selection', 'storelly-product-builder-for-woocommerce' ); ?>
                </button>
            </div>
        </div>

        <!-- ── Font grid ── -->
        <div class="spbwc-font-grid">
            <!-- NOTE: .gg-font-preview-inner must stay — fontOnLoad directive targets it by class -->
            <div class="spbwc-font-card"
                 ng-class="{'is-selected': font.selected}"
                 ng-click="selectFont(font, $event)"
                 ng-repeat="font in fonts | startFrom:filterFont.currentPage*filterFont.pageSize | limitTo:filterFont.pageSize">
                <div class="gg-font-preview-inner spbwc-font-card__inner"
                     style="font-family: '{{font.family}}',-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif">
                    <p class="spbwc-font-card__name">{{font.family}}</p>
                    <p class="spbwc-font-card__sample"
                       font-on-load
                       data-preview="fSubsets[font.subsets[0]]['preview_text']"
                       data-font="font.family">
                        <span class="font-preview" style="display: none;" contenteditable="true">{{fSubsets[font.subsets[0]]['preview_text']}}</span>
                    </p>
                    <!-- .action span kept for JS compatibility (fontOnLoad directive) -->
                    <span title="{{font.selected ? '<?php esc_html_e( 'Unselect', 'storelly-product-builder-for-woocommerce' ); ?>' : '<?php esc_html_e( 'Select', 'storelly-product-builder-for-woocommerce' ); ?>'}}"
                          ng-class="font.selected ? '' : 'uncheck'"
                          class="action dashicons dashicons-yes disable"
                          aria-hidden="true"></span>
                </div>
                <div class="spbwc-font-card__footer">
                    <span class="spbwc-font-card__category">{{font.category}}</span>
                    <span class="spbwc-font-card__check" ng-class="{'is-active': font.selected}">
                        <span class="dashicons dashicons-yes" aria-hidden="true"></span>
                    </span>
                </div>
            </div>
        </div>

        <!-- ── Pagination — rendered by fontPagination AngularJS directive ── -->
        <div class="gg-font-pagination" font-pagination data-filter-font="filterFont" data-total="fonts.length"></div>

    </div><!-- .spbwc-font-manager -->
</div><!-- .spbwc-fonts-wrap -->
