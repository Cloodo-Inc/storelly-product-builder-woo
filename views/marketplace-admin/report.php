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
<!-- ── KPI stat cards ── -->
<div class="spbwc-stat-grid" style="margin-bottom: 28px;">
    <article class="spbwc-stat-card spbwc-stat-card--brand">
        <div class="spbwc-stat-card__head">
            <div class="spbwc-stat-card__icon">
                <span class="dashicons dashicons-businessman" aria-hidden="true"></span>
            </div>
            <div class="spbwc-stat-card__label"><?php esc_html_e( 'Designers', 'storelly-product-builder-for-woocommerce' ); ?></div>
        </div>
        <div class="spbwc-stat-card__value"><?php echo esc_html( number_format_i18n( $total_designers ) ); ?></div>
        <p class="spbwc-stat-card__hint"><?php esc_html_e( 'Total registered designers', 'storelly-product-builder-for-woocommerce' ); ?></p>
    </article>

    <article class="spbwc-stat-card spbwc-stat-card--success">
        <div class="spbwc-stat-card__head">
            <div class="spbwc-stat-card__icon">
                <span class="dashicons dashicons-art" aria-hidden="true"></span>
            </div>
            <div class="spbwc-stat-card__label"><?php esc_html_e( 'Designs', 'storelly-product-builder-for-woocommerce' ); ?></div>
        </div>
        <div class="spbwc-stat-card__value"><?php echo esc_html( number_format_i18n( $total_designs ) ); ?></div>
        <p class="spbwc-stat-card__hint"><?php esc_html_e( 'Total submitted designs', 'storelly-product-builder-for-woocommerce' ); ?></p>
    </article>

    <article class="spbwc-stat-card spbwc-stat-card--accent">
        <div class="spbwc-stat-card__head">
            <div class="spbwc-stat-card__icon">
                <span class="dashicons dashicons-cart" aria-hidden="true"></span>
            </div>
            <div class="spbwc-stat-card__label"><?php esc_html_e( 'Sales', 'storelly-product-builder-for-woocommerce' ); ?></div>
        </div>
        <div class="spbwc-stat-card__value"><?php echo esc_html( number_format_i18n( $total_sales ) ); ?></div>
        <p class="spbwc-stat-card__hint"><?php esc_html_e( 'Total design sales', 'storelly-product-builder-for-woocommerce' ); ?></p>
    </article>

    <article class="spbwc-stat-card spbwc-stat-card--warning">
        <div class="spbwc-stat-card__head">
            <div class="spbwc-stat-card__icon">
                <span class="dashicons dashicons-money-alt" aria-hidden="true"></span>
            </div>
            <div class="spbwc-stat-card__label"><?php esc_html_e( 'Pending Withdraws', 'storelly-product-builder-for-woocommerce' ); ?></div>
        </div>
        <div class="spbwc-stat-card__value"><?php echo esc_html( number_format_i18n( $pending_withdr ) ); ?></div>
        <p class="spbwc-stat-card__hint"><?php esc_html_e( 'Awaiting review &amp; approval', 'storelly-product-builder-for-woocommerce' ); ?></p>
    </article>
</div>

<!-- ── Charts ── -->
<div class="spbwc-mp-charts">
    <div class="spbwc-block spbwc-block--brand" style="flex:1; min-width:0;">
        <header class="spbwc-block__head">
            <h3 class="spbwc-block__title">
                <span class="dashicons dashicons-chart-line" aria-hidden="true"></span>
                <?php esc_html_e( 'Designs Created', 'storelly-product-builder-for-woocommerce' ); ?>
            </h3>
        </header>
        <div class="spbwc-block__body">
            <canvas id="spbwc-mp-chart-designs" height="120"></canvas>
        </div>
    </div>
    <div class="spbwc-block spbwc-block--success" style="flex:1; min-width:0;">
        <header class="spbwc-block__head">
            <h3 class="spbwc-block__title">
                <span class="dashicons dashicons-chart-bar" aria-hidden="true"></span>
                <?php esc_html_e( 'Sales', 'storelly-product-builder-for-woocommerce' ); ?>
            </h3>
        </header>
        <div class="spbwc-block__body">
            <canvas id="spbwc-mp-chart-sales" height="120"></canvas>
        </div>
    </div>
</div>

<script type="application/json" id="spbwc-mp-chart-data"><?php echo wp_json_encode( array(
    'designs' => $design_report,
    'sales'   => $sale_report,
) ); ?></script>
