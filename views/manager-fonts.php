<?php if (!defined('ABSPATH')) exit; // Exit if accessed directly ?>
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
                    <?php esc_html_e( 'Select Google Fonts available in the design editor. Remove unused fonts to keep the editor fast.', 'storelly-product-builder-for-woocommerce' ); ?>
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

    <!-- ── AngularJS app root ── -->
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
