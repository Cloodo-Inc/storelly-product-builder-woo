<?php
/**
 * Marketplace admin — Report dashboard.
 *
 * @package Storelly
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$designer_counts = function_exists( 'spbwc_marketplace_get_designer_status_count' ) ? spbwc_marketplace_get_designer_status_count() : array();
$design_counts   = function_exists( 'spbwc_marketplace_get_design_status_count' )   ? spbwc_marketplace_get_design_status_count()   : array();
$sale_counts     = function_exists( 'spbwc_marketplace_get_sale_status_count' )     ? spbwc_marketplace_get_sale_status_count()     : array();
$withdraw_counts = function_exists( 'spbwc_marketplace_get_withdraw_status_count' ) ? spbwc_marketplace_get_withdraw_status_count() : array();

$design_report = function_exists( 'spbwc_marketplace_get_design_report' ) ? spbwc_marketplace_get_design_report( 'day' ) : array();
$sale_report   = function_exists( 'spbwc_marketplace_get_sale_report' )   ? spbwc_marketplace_get_sale_report( 'day' )   : array();

$total_designers = isset( $designer_counts['all'] ) ? (int) $designer_counts['all'] : 0;
$total_designs   = isset( $design_counts['all'] )   ? (int) $design_counts['all']   : 0;
$total_sales     = isset( $sale_counts['all'] )     ? (int) $sale_counts['all']     : 0;
$pending_withdr  = isset( $withdraw_counts['pending'] ) ? (int) $withdraw_counts['pending'] : 0;
?>
<div class="spbwc-mp-cards">
    <div class="spbwc-mp-card">
        <span class="spbwc-mp-card__label"><?php esc_html_e( 'Designers', 'storelly-product-builder-for-woocommerce' ); ?></span>
        <span class="spbwc-mp-card__value"><?php echo esc_html( number_format_i18n( $total_designers ) ); ?></span>
    </div>
    <div class="spbwc-mp-card">
        <span class="spbwc-mp-card__label"><?php esc_html_e( 'Designs', 'storelly-product-builder-for-woocommerce' ); ?></span>
        <span class="spbwc-mp-card__value"><?php echo esc_html( number_format_i18n( $total_designs ) ); ?></span>
    </div>
    <div class="spbwc-mp-card">
        <span class="spbwc-mp-card__label"><?php esc_html_e( 'Sales', 'storelly-product-builder-for-woocommerce' ); ?></span>
        <span class="spbwc-mp-card__value"><?php echo esc_html( number_format_i18n( $total_sales ) ); ?></span>
    </div>
    <div class="spbwc-mp-card">
        <span class="spbwc-mp-card__label"><?php esc_html_e( 'Pending withdraws', 'storelly-product-builder-for-woocommerce' ); ?></span>
        <span class="spbwc-mp-card__value"><?php echo esc_html( number_format_i18n( $pending_withdr ) ); ?></span>
    </div>
</div>

<div class="spbwc-mp-charts">
    <div class="spbwc-mp-chart">
        <h3><?php esc_html_e( 'Designs created', 'storelly-product-builder-for-woocommerce' ); ?></h3>
        <canvas id="spbwc-mp-chart-designs" height="120"></canvas>
    </div>
    <div class="spbwc-mp-chart">
        <h3><?php esc_html_e( 'Sales', 'storelly-product-builder-for-woocommerce' ); ?></h3>
        <canvas id="spbwc-mp-chart-sales" height="120"></canvas>
    </div>
</div>

<script type="application/json" id="spbwc-mp-chart-data"><?php echo wp_json_encode( array(
    'designs' => $design_report,
    'sales'   => $sale_report,
) ); ?></script>
