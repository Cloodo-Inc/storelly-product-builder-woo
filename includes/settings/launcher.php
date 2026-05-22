<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
if( !class_exists('Nbdesigner_Launcher') ) {
    class Nbdesigner_Launcher{
        public static function get_options() {
            return apply_filters('nbdesigner_laucher_settings', array(
                'general' => array(
                    array(
                        'title'         => esc_html__( 'Enable designer store', 'storelly-product-builder-for-woocommerce'),
                        'id'            => 'spbwc_marketplace_enabled',
                        /* translators: %s: URL to the WordPress Permalink admin settings screen */
                        'description'   => sprintf(wp_kses(__( 'Allow customers become designers who create and sell their designs. After "Save options" go to <a target="_blank" href="%s">Permalink</a> choose pretty permalinks and "Save changes". Default permalinks will not work.', 'storelly-product-builder-for-woocommerce'), array( 'a' => array( 'href' => array(), 'target' => array() ))), esc_url(admin_url('options-permalink.php'))),
                        'default'       => 'no',
                        'type'          => 'radio',
                        'options'       => array(
                            'yes'   => esc_html__('Yes', 'storelly-product-builder-for-woocommerce'),
                            'no'    => esc_html__('No', 'storelly-product-builder-for-woocommerce')
                        )
                    )
                ),
                'admin' => array(
                    array(
                        'title'         => esc_html__( 'Commission Type', 'storelly-product-builder-for-woocommerce'),
                        'id'            => 'spbwc_marketplace_commission_type',
                        'description'   => esc_html__('Select a commission type for designer.', 'storelly-product-builder-for-woocommerce'),
                        'default'       => 'percentage',
                        'type'          => 'select',
                        'class'         => 'depend_trigger',
                        'options'       => array(
                            'percentage'    => esc_html__('Percentage', 'storelly-product-builder-for-woocommerce'),
                            'flat'          => esc_html__('Flat', 'storelly-product-builder-for-woocommerce'),
                            'combine'       => esc_html__('Combine', 'storelly-product-builder-for-woocommerce')
                        )
                    ),
                    array(
                        'title'         => esc_html__( 'Default commission', 'storelly-product-builder-for-woocommerce'),
                        'id'            => 'spbwc_marketplace_default_commission',
                        'default'       => 0,
                        'description'   => esc_html__( 'Amount designers get from each sale has their designs.', 'storelly-product-builder-for-woocommerce'),
                        'type'          => 'text',
                        'class'         => 'regular-text',
                        'css'           => 'width: 85px',
                        'depend_on'     => array(
                            'id'        => 'spbwc_marketplace_commission_type',
                            'value'     => 'combine',
                            'operator'  => '#'
                        )
                    ),
                    array(
                        'title'         => esc_html__( 'Default commission', 'storelly-product-builder-for-woocommerce'),
                        'id'            => 'spbwc_marketplace_default_commission2',
                        'description'   => esc_html__( 'Amount designers will get from sales has their designs in both percentage and fixed fee', 'storelly-product-builder-for-woocommerce' ),
                        'css'           => 'width: 85px',
                        'default'       => '0|0',
                        'type'          => 'multivalues',
                        'options'       => array(
                            0           => '',
                            1           => esc_html__( '%  +', 'storelly-product-builder-for-woocommerce')
                        ),
                        'depend_on'     => array(
                            'id'        => 'spbwc_marketplace_commission_type',
                            'value'     => 'combine',
                            'operator'  => '='
                        )
                    ),
                    array(
                        'title'         => esc_html__( 'Minimum Withdraw Limit', 'storelly-product-builder-for-woocommerce'),
                        'id'            => 'spbwc_marketplace_minimum_withdraw',
                        'default'       => 0,
                        'description'   => esc_html__( 'Minimum balance required to make a withdraw request. Leave blank to set no minimum limits.', 'storelly-product-builder-for-woocommerce'),
                        'type'          => 'text',
                        'css'           => 'width: 85px',
                        'class'         => 'regular-text'
                    ),
                    array(
                        'title'         => esc_html__( 'Withdraw Threshold', 'storelly-product-builder-for-woocommerce'),
                        'id'            => 'storelly_marketplace_withdraw_threshold',
                        'default'       => 0,
                        'description'   => esc_html__( 'Days, ( Delay time to active order designer earning )', 'storelly-product-builder-for-woocommerce'),
                        'type'          => 'text',
                        'css'           => 'width: 85px',
                        'class'         => 'regular-text'
                    ),
                    array(
                        'title'         => esc_html__( 'Order Status for Withdraw', 'storelly-product-builder-for-woocommerce'),
                        'id'            => 'nbdesigner_order_status_for_withdraw',
                        'default'       => json_encode(array(
                                'spbwc_marketplace_order_status_for_withdraw_wc-completed'     => 1,
                                'spbwc_marketplace_order_status_for_withdraw_wc-processing'    => 0,
                                'spbwc_marketplace_order_status_for_withdraw_wc-on-hold'       => 0
                            )),
                        'description'   => '',
                        'type'          => 'multicheckbox',
                        'class'         => 'regular-text',
                        'options'       => array(
                            'spbwc_marketplace_order_status_for_withdraw_wc-completed'     => esc_html__('Completed', 'storelly-product-builder-for-woocommerce'),
                            'spbwc_marketplace_order_status_for_withdraw_wc-processing'    => esc_html__('Processing', 'storelly-product-builder-for-woocommerce'),
                            'spbwc_marketplace_order_status_for_withdraw_wc-on-hold'       => esc_html__('On-hold', 'storelly-product-builder-for-woocommerce')
                        ),
                        'css' => 'margin: 0 15px 10px 5px;'
                    )
                ),
                'designer' => array(
                    array(
                        'title'         => esc_html__( 'Designer page banner width', 'storelly-product-builder-for-woocommerce'),
                        'id'            => 'spbwc_marketplace_banner_width',
                        'default'       => 1050,
                        'description'   => '',
                        'type'          => 'number',
                        'class'         => 'regular-text',
                        'css'           => 'width: 85px',
                        'subfix'        => ' px'
                    ),
                    array(
                        'title'         => esc_html__( 'Designer page banner height', 'storelly-product-builder-for-woocommerce'),
                        'id'            => 'spbwc_marketplace_banner_height',
                        'default'       => 200,
                        'description'   => '',
                        'type'          => 'number',
                        'class'         => 'regular-text',
                        'css'           => 'width: 85px',
                        'subfix'        => ' px'
                    )
                ),
                'design' => array(
                    array(
                        'title'         => esc_html__( 'Generate preview for product has print option color automatically', 'storelly-product-builder-for-woocommerce'),
                        'id'            => 'spbwc_marketplace_auto_generate_color_preview',
                        'description'   => esc_html__( 'Beware this option turn on the process which consumes a lot of system resources', 'storelly-product-builder-for-woocommerce'),
                        'default'       => 'no',
                        'type'          => 'radio',
                        'options'       => array(
                            'yes'   => esc_html__('Yes', 'storelly-product-builder-for-woocommerce'),
                            'no'    => esc_html__('No', 'storelly-product-builder-for-woocommerce')
                        )
                    )
                )
            ));
        }
    }
}