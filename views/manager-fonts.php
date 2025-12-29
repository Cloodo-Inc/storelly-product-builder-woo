<?php if (!defined('ABSPATH')) exit; // Exit if accessed directly  
?>
<h2><?php esc_html_e('Google Fonts', 'storelly-product-builder-for-woocommerce'); ?></h2>
<div class="wrap storelly-container">
    <div class="postbox" ng-app="font-app" ng-controller="fontCtrl" ng-cloak>
        <div class="inside">
            <div class="showbox" style="display: none;">
                <div class="loader">
                    <svg class="circular" viewBox="25 25 50 50">
                        <circle class="path" cx="50" cy="50" r="20" fill="none" stroke-width="2" stroke-miterlimit="10" />
                    </svg>
                </div>
            </div>
            <div class="gg-font-option">
                <input type="text" ng-model="filterFont.name" placeholder="Font name" ng-change="resetCurentPage()">
                <select ng-model="filterFont.category" ng-change="resetCurentPage()">
                    <option value=""><?php esc_html_e('All Categories', 'storelly-product-builder-for-woocommerce'); ?></option>
                    <option value="serif">Serif</option>
                    <option value="sans-serif">Sans Serif</option>
                    <option value="display">Display</option>
                    <option value="handwriting">Handwriting</option>
                    <option value="monospace">Monospace</option>
                </select>
                <select ng-model="filterFont.subset" ng-change="resetCurentPage()">
                    <option value=""><?php esc_html_e('All subsets', 'storelly-product-builder-for-woocommerce'); ?></option>
                    <?php
                    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variable.
                    foreach ($subsets as $key => $subset) :
                    ?>
                        <option value="<?php echo esc_attr($key); ?>" <?php selected($key, $current_subset); ?>><?php echo esc_html($subset['name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <a class="button button-primary" ng-click="updateGoogleFont($event)"><?php esc_html_e('Update', 'storelly-product-builder-for-woocommerce'); ?></a>
            </div>
            <div class="gg-font-preview-wrap">
                <div class="storelly-pagesize-wrap">
                    <b><?php esc_html_e('Total', 'storelly-product-builder-for-woocommerce'); ?> {{fonts.length}} <?php esc_html_e('fonts', 'storelly-product-builder-for-woocommerce'); ?></b>
                    <a class="button" ng-click="selectAll()"><?php esc_html_e('Select All', 'storelly-product-builder-for-woocommerce'); ?></a>
                    <a class="button" ng-click="unselectAll()"><?php esc_html_e('Unselect All', 'storelly-product-builder-for-woocommerce'); ?></a>
                    <div style="display: inline-block; float: right;">
                        <label for='nbd-selected'><?php esc_html_e('Display ', 'storelly-product-builder-for-woocommerce'); ?></label>
                        <select id='nbd-selected' ng-model="filterFont.select" ng-change="resetCurentPage()">
                            <option value=""><?php esc_html_e('All', 'storelly-product-builder-for-woocommerce'); ?></option>
                            <option value="selected"><?php esc_html_e('Selected', 'storelly-product-builder-for-woocommerce'); ?></option>
                            <option value="unselected"><?php esc_html_e('Unselected', 'storelly-product-builder-for-woocommerce'); ?></option>
                        </select>
                        <label for='nbd-page-size'><?php esc_html_e('Display ', 'storelly-product-builder-for-woocommerce'); ?></label>
                        <select id='nbd-page-size' ng-model="filterFont.pageSize" ng-change="resetCurentPage()">
                            <option ng-value="5">4</option>
                            <option ng-value="10">12</option>
                            <option ng-value="20">20</option>
                            <option ng-value="30">36</option>
                            <option ng-value="50">56</option>
                        </select>
                    </div>
                </div>
                <p><small><?php esc_html_e('Click check mark to select/unselect font', 'storelly-product-builder-for-woocommerce'); ?></small></p>
                <p class="storelly-admin-font-warning"><?php esc_html_e('Please remove unused fonts to make the design editor loads faster', 'storelly-product-builder-for-woocommerce'); ?></p>
                <div class="gg-font-preview-wrap-inner">
                    <div class="gg-font-preview" ng-click="selectFont( font, $event )" ng-repeat="font in fonts | startFrom:filterFont.currentPage*filterFont.pageSize | limitTo:filterFont.pageSize">
                        <div class="gg-font-preview-inner-wrap" style="font-family: '{{font.family}}',-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen-Sans, Ubuntu, Cantarell, 'Helvetica Neue', sans-serif">
                            <div class="gg-font-preview-inner">
                                <p class="gg-font-name">{{font.family}}</p>
                                <p font-on-load data-preview="fSubsets[font.subsets[0]]['preview_text']" data-font="font.family"><span class="font-preview" style="display: none;" contenteditable="true">{{fSubsets[font.subsets[0]]['preview_text']}}</span></p>
                                <span title="{{font.selected ? '<?php esc_html_e('Unselect', 'storelly-product-builder-for-woocommerce'); ?>' : '<?php esc_html_e('Select', 'storelly-product-builder-for-woocommerce'); ?>'}}" ng-class="font.selected ? '' : 'uncheck'" class="action dashicons dashicons-yes disable"></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="gg-font-pagination" font-pagination data-filter-font="filterFont" data-total="fonts.length"></div>
        </div>
    </div>
</div>
