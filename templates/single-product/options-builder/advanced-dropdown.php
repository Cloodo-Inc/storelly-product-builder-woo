<?php 
if (!defined('ABSPATH')) exit; 
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables used in local scope.
?>
<div nbo-adv-dropdown class="nbd-option-field pcpb-field-ad-dropdown-wrap <?php echo esc_attr($class); ?>" data-id="<?php echo esc_attr($field['id']); ?>" ng-if="nbd_fields['<?php echo esc_attr($field['id']); ?>'].enable">
    <?php include($currentDir . '/options-builder/field-header.php'); ?>
    <div class="pcpb-field-content">
        <div>
            <select ng-change="check_valid()" name="pcpb-field[<?php echo esc_attr($field['id']); ?>]{{nbd_fields['<?php echo esc_attr($field['id']); ?>'].form_name}}" class="nbo-dropdown" ng-model="nbd_fields['<?php echo esc_attr($field['id']); ?>'].value">
                <?php
                foreach ($field['general']['attributes']["options"] as $key => $attr) :
                    $selected = isset($attr['selected']) ? $attr['selected'] : 'off';
                    $current = 'on';
                    if (isset($form_values[$field['id']])) {
                        $selected = (is_array($form_values[$field['id']]) && isset($form_values[$field['id']]['value'])) ? $form_values[$field['id']]['value'] : $form_values[$field['id']];
                        $current = $key;
                    }
                ?>
                    <option value="<?php echo esc_attr($key); ?>" <?php selected($selected, $selected); ?>>
                        <?php echo esc_html($attr['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div class="nbo-ad-result">
                <span class="nbo-ad-result-name" ng-bind="nbd_fields['<?php echo esc_attr($field['id']); ?>'].value_name"></span>
                <svg xmlns="http://www.w3.org/2000/svg" version="1.1" width="24" height="24" viewBox="0 0 24 24">
                    <path d="M16.594 8.578l1.406 1.406-6 6-6-6 1.406-1.406 4.594 4.594z" />
                </svg>
            </div>
            <div class="nbo-ad-pseudo-list <?php if ($sublist_position == 'r') echo esc_attr('nbo-ad-right'); ?>">
                <?php
                foreach ($field['general']['attributes']["options"] as $key => $attr) :
                    $image_url = SPBWC_Storelly_PB_Util::spbwc_get_image_thumbnail($attr['image']);
                    $enable_subattr = isset($attr['enable_subattr']) ? $attr['enable_subattr'] : 0;
                    $attr['sub_attributes'] = isset($attr['sub_attributes']) ? $attr['sub_attributes'] : array();
                    $show_subattr = ($enable_subattr == 'on' && count($attr['sub_attributes']) > 0) ? true : false;
                    $field['general']['attributes']["options"][$key]['show_subattr'] = $show_subattr;
                ?>
                    <div class="nbo-ad-list-item" ng-click="select_adv_attr('<?php echo esc_attr($field['id']); ?>', '<?php echo esc_attr($key); ?>');updateMapOptions('<?php echo esc_attr($field['id']); ?>')" ng-class="nbd_fields['<?php echo esc_attr($field['id']); ?>'].value == '<?php echo esc_attr($key); ?>' ? 'active' : ''" nbo-disabled="!status_fields['<?php echo esc_attr($field['id']); ?>'][<?php echo esc_attr($key); ?>].enable" nbo-disabled-type="class">
                        <?php if ($attr['preview_type'] == 'i' && $attr['image'] != '0') : ?>
                            <img src="<?php echo esc_url($image_url); ?>" class="nbo-ad-item-thumb" />
                        <?php elseif ($attr['preview_type'] == 'c') : ?>
                            <span class="nbo-ad-item-thumb" style="background: <?php echo esc_attr($attr['color']); ?>"></span>
                        <?php endif; ?>
                        <div class="nbo-ad-item-main <?php if ($show_subattr) echo esc_attr('nbo-shrink'); ?>">
                            <div class="nbo-ad-item-title"><?php echo esc_html($attr['name']); ?></div>
                            <div class="nbo-ad-item-description"><?php echo esc_html($attr['des']); ?></div>
                        </div>
                        <?php if (isset($attr['selected']) && $attr['selected'] == 'on') : ?>
                            <span class="nbo-recomand" title="<?php esc_html_e('Recommended', 'storelly-product-builder-for-woocommerce'); ?>">
                                <svg class="octicon octicon-bookmark" viewBox="0 0 10 16" version="1.1" width="10" height="16" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M9 0H1C.27 0 0 .27 0 1v15l5-3.09L10 16V1c0-.73-.27-1-1-1zm-.78 4.25L6.36 5.61l.72 2.16c.06.22-.02.28-.2.17L5 6.6 3.12 7.94c-.19.11-.25.05-.2-.17l.72-2.16-1.86-1.36c-.17-.16-.14-.23.09-.23l2.3-.03.7-2.16h.25l.7 2.16 2.3.03c.23 0 .27.08.09.23h.01z"></path>
                                </svg>
                            </span>
                        <?php endif; ?>
                        <?php if ($show_subattr) : ?>
                            <span class="nbo-ad-pseudo-sublist-toggle">
                                <svg xmlns="http://www.w3.org/2000/svg" version="1.1" width="24" height="24" viewBox="0 0 24 24">
                                    <path d="M16.594 8.578l1.406 1.406-6 6-6-6 1.406-1.406 4.594 4.594z" />
                                </svg>
                            </span>
                            <div class="nbo-ad-pseudo-sublist">
                                <select ng-change="check_valid()" name="pcpb-field[<?php echo esc_attr($field['id']); ?>][sub_value]" class="nbo-dropdown" ng-model="nbd_fields['<?php echo esc_attr($field['id']); ?>'].sub_value">
                                    <?php foreach ($attr['sub_attributes'] as $skey => $sattr) :
                                        $selectedSub = isset($sattr['selected']) ? $sattr['selected'] : 'off';
                                        $currentSub = 'on';
                                        if (isset($form_values[$field['id']]) && isset($form_values[$field['id']]['sub_value'])) {
                                            $selectedSub = $form_values[$field['id']]['sub_value'];
                                            $currentSub = $skey;
                                        }
                                    ?>
                                        <option value="<?php echo esc_attr($skey); ?>" <?php selected($selectedSub, $currentSub); ?>>
                                            <?php echo esc_html($sattr['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php foreach ($attr['sub_attributes'] as $skey => $sattr) : ?>
                                    <div class="nbo-ad-list-item" ng-click="select_adv_subattr('<?php echo esc_attr($field['id']); ?>', '<?php echo esc_attr($key); ?>', '<?php echo esc_attr($skey); ?>')" ng-class="( nbd_fields['<?php echo esc_attr($field['id']); ?>'].value == '<?php echo esc_attr($key); ?>' && nbd_fields['<?php echo esc_attr($field['id']); ?>'].sub_value == '<?php echo esc_attr($skey); ?>' ) ? 'active' : ''" nbo-disabled="!status_fields['<?php echo esc_attr($field['id']); ?>'][<?php echo esc_attr($key); ?>].sub_attributes[<?php echo esc_attr($skey); ?>]" nbo-disabled-type="class">
                                        <?php
                                        if ($sattr['preview_type'] == 'i' && $sattr['image'] != '0') :
                                            $simage_url = SPBWC_Storelly_PB_Util::spbwc_get_image_thumbnail($sattr['image']);
                                        ?>
                                            <img src="<?php echo esc_url($simage_url); ?>" class="nbo-ad-item-thumb" />
                                        <?php elseif ($sattr['preview_type'] == 'c') : ?>
                                            <span class="nbo-ad-item-thumb" style="background: <?php echo esc_attr($sattr['color']); ?>"></span>
                                        <?php endif; ?>
                                        <div class="nbo-ad-item-main">
                                            <div class="nbo-ad-item-title"><?php echo esc_attr($sattr['name']); ?></div>
                                            <div class="nbo-ad-item-description"><?php echo esc_attr($sattr['des']); ?></div>
                                        </div>
                                        <?php if (isset($sattr['selected']) && $sattr['selected'] == 'on') : ?>
                                            <span class="nbo-recomand" title="<?php esc_html_e('Recommended', 'storelly-product-builder-for-woocommerce'); ?>">
                                                <svg class="octicon octicon-bookmark" viewBox="0 0 10 16" version="1.1" width="10" height="16" aria-hidden="true">
                                                    <path fill-rule="evenodd" d="M9 0H1C.27 0 0 .27 0 1v15l5-3.09L10 16V1c0-.73-.27-1-1-1zm-.78 4.25L6.36 5.61l.72 2.16c.06.22-.02.28-.2.17L5 6.6 3.12 7.94c-.19.11-.25.05-.2-.17l.72-2.16-1.86-1.36c-.17-.16-.14-.23.09-.23l2.3-.03.7-2.16h.25l.7 2.16 2.3.03c.23 0 .27.08.09.23h.01z"></path>
                                                </svg>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="nbo-invalid-option" ng-class="nbd_fields['<?php echo esc_attr($field['id']); ?>'].valid === false ? 'active' : ''" ng-if="nbd_fields['<?php echo esc_attr($field['id']); ?>'].valid === false"><span ng-bind="nbd_fields['<?php echo esc_attr($field['id']); ?>'].invalidOption"></span> <?php esc_html_e('is not available', 'storelly-product-builder-for-woocommerce'); ?>
            </div>
        </div>
    </div>
</div>