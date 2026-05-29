<?php 
if (!defined('ABSPATH')) exit; 
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables used in local scope.
?>
<div nbo-adv-dropdown class="nbd-option-field pcpb-field-xlabel-wrap <?php echo esc_attr( $class ); ?>" data-id="<?php echo esc_attr( $field['id'] ); ?>" ng-if="nbd_fields['<?php echo esc_attr( $field['id'] ); ?>'].enable">
    <?php include( $currentDir .'/options-builder/field-header.php' ); ?>
    <div class="pcpb-field-content">
        <div class="nbd-xlabel-wrapper nbo-clearfix">
            <?php 
                foreach ($field['general']['attributes']["options"] as $key => $attr): 
                    $image_url = SPBWC_Storelly_PB_Util::spbwc_get_image_thumbnail( $attr['image'] );
                    $enable_subattr = isset($attr['enable_subattr']) ? $attr['enable_subattr'] : 0;
                    $attr['sub_attributes'] = isset( $attr['sub_attributes'] ) ? $attr['sub_attributes'] : array();
                    $show_subattr = ($enable_subattr == 'on' && count($attr['sub_attributes']) > 0) ? true : false;
                    $field['general']['attributes']["options"][$key]['show_subattr'] = $show_subattr;
            ?>
            <div class="nbd-xlabel-wrap">
                <div class="nbd-xlabel-value">
                    <div class="nbd-xlabel-value-inner" title="<?php echo esc_attr( $attr['name'] ); ?>">
                        <input ng-change="check_valid();updateMapOptions('<?php echo esc_attr( $field['id'] ); ?>')" value="<?php echo esc_attr( $key ); ?>" ng-model="nbd_fields['<?php echo esc_attr( $field['id'] ); ?>'].value" name="pcpb-field[<?php echo esc_attr( $field['id'] ); ?>]<?php if($show_subattr) echo esc_attr('[value]'); ?>" 
                               type="radio" id='pcpb-field-<?php echo esc_attr( $field['id'].'-'.$key ); ?>' 
                            <?php 
                                if( isset($form_values[$field['id']]) ){
                                    $fvalue = (is_array($form_values[$field['id']]) && isset($form_values[$field['id']]['value'])) ? $form_values[$field['id']]['value'] : $form_values[$field['id']];
                                    checked( $fvalue, $key ); 
                                }else{
                                    checked( isset($attr['selected']) ? $attr['selected'] : 'off', 'on' ); 
                                }
                            ?> />
                        <label class="nbd-xlabel" style="<?php if( $attr['preview_type'] == 'i' ){echo esc_attr('background: url('.$image_url . ') 0% 0% / cover');}else{echo esc_attr('background: '.$attr['color']);}?>" 
                             for='pcpb-field-<?php echo esc_attr( $field['id'].'-'.$key ); ?>' 
                             nbo-disabled="!status_fields['<?php echo esc_attr( $field['id'] ); ?>'][<?php echo esc_attr( $key ); ?>].enable" nbo-disabled-type="class" >
                            <?php if(isset($attr['des']) && $attr['des'] != ''): ?>
                            <span class="nbd-help-tip" data-tip="<?php echo esc_attr( $attr['des'] ); ?>"></span>
                            <?php endif; ?>
                            <?php if( isset($attr['selected']) && $attr['selected'] == 'on'  ): ?>
                            <span class="nbo-recomand" title="<?php esc_html_e('Recommended', 'storelly-product-builder-for-woocommerce'); ?>">
                                <svg class="octicon octicon-bookmark" viewBox="0 0 10 16" version="1.1" width="10" height="16" aria-hidden="true"><path fill-rule="evenodd" d="M9 0H1C.27 0 0 .27 0 1v15l5-3.09L10 16V1c0-.73-.27-1-1-1zm-.78 4.25L6.36 5.61l.72 2.16c.06.22-.02.28-.2.17L5 6.6 3.12 7.94c-.19.11-.25.05-.2-.17l.72-2.16-1.86-1.36c-.17-.16-.14-.23.09-.23l2.3-.03.7-2.16h.25l.7 2.16 2.3.03c.23 0 .27.08.09.23h.01z"></path></svg>
                            </span>
                            <?php endif; ?>
                        </label>
                    </div>
                </div>
                <label for='pcpb-field-<?php echo esc_attr( $field['id'].'-'.$key ); ?>'>
                    <b><?php echo esc_html( $attr['name'] ); ?></b>
                </label>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="nbo-invalid-option" 
            ng-class="nbd_fields['<?php echo esc_attr( $field['id'] ); ?>'].valid === false ? 'active' : ''"
            ng-if="nbd_fields['<?php echo esc_attr( $field['id'] ); ?>'].valid === false"><span ng-bind="nbd_fields['<?php echo esc_attr( $field['id'] ); ?>'].invalidOption"></span> <?php esc_html_e('is not available', 'storelly-product-builder-for-woocommerce'); ?>
        </div>
    </div>
</div>