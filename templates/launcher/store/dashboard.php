<?php
    if (!defined('ABSPATH')) exit;
?>
<div class="nbdl-dashboard">
    <div class="nbdl-dashboard-column left">
        <div class="nbdl-dashboard-inner">
            <div class="nbdl-dashboard-head">
                <?php esc_html_e('Created designs', 'storelly-product-builder-for-woocommerce'); ?>
            </div>
            <div>
                <div class="nbdl-dashboard-box">
                    <span><?php esc_html_e('Total', 'storelly-product-builder-for-woocommerce'); ?></span> <span><?php echo( $designs['all'] ); ?></span>
                </div>
                <div class="nbdl-dashboard-box">
                    <span><?php esc_html_e('Approved', 'storelly-product-builder-for-woocommerce'); ?></span> <span><?php echo( $designs['approved'] ); ?></span>
                </div>
                <div class="nbdl-dashboard-box">
                    <span><?php esc_html_e('Pending', 'storelly-product-builder-for-woocommerce'); ?></span> <span><?php echo( $designs['pending'] ); ?></span>
                </div>
            </div>
        </div>
        <div class="nbdl-dashboard-inner">
            <div class="nbdl-dashboard-head">
                <?php esc_html_e('Design sold items', 'storelly-product-builder-for-woocommerce'); ?>
            </div>
            <div>
                <div class="nbdl-dashboard-box">
                    <span><?php esc_html_e('Total', 'storelly-product-builder-for-woocommerce'); ?></span> <span><?php echo( $sales ); ?></span>
                </div>
            </div>
        </div>
    </div>
    <div class="nbdl-dashboard-column right">
        <div class="nbdl-dashboard-inner">
            <div class="nbdl-dashboard-head">
                <?php esc_html_e('Designs and Sales this Month', 'storelly-product-builder-for-woocommerce'); ?>
            </div>
            <div>
                <canvas id="nbdl-chart"></canvas>
            </div>
        </div>
    </div>
</div>

<?php do_action( 'pc_footer', 'launcher-dashboard' ); ?>

<!-- No inline scripts or styles unless dynamic. -->
<script type="text/javascript">
    jQuery(document).ready(function(){
        var ctx = document.getElementById('nbdl-chart').getContext('2d');
        var nbdlChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode( $report['labels'] ); ?>,
                datasets: <?php echo json_encode( $report['datasets'] ); ?>
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    xAxes: [{
                        type: 'time',
                        scaleLabel: {
                            display: false
                        },
                        gridLines: {
                            display: false
                        },
                        ticks: {
                            fontColor: '#aaa',
                            fontSize: 11
                        }
                    }],
                    yAxes: [{
                        scaleLabel: {
                            display: false
                        },
                        ticks: {
                            fontColor: '#aaa'
                        }
                    }]
                },
                legend: {
                    position: 'top',
                    onClick: false
                },
                elements: {
                    line: {
                        tension: 0,
                        borderWidth: 4
                    },
                    point: {
                        radius: 5,
                        borderWidth: 3,
                        backgroundColor: '#fff',
                        borderColor: '#fff'
                    }
                },
                tooltips: {
                    displayColors: false,
                    callbacks: {
                        label: function (tooltipItems, data) {
                            let label = data.datasets[tooltipItems.datasetIndex].label || '';
                            let customLabel = data.datasets[tooltipItems.datasetIndex].tooltipLabel || '';
                            let prefix = data.datasets[tooltipItems.datasetIndex].tooltipPrefix || '';

                            let tooltipLabel = customLabel ? customLabel + ': ' : label + ': ';

                            tooltipLabel += prefix + tooltipItems.yLabel;

                            return tooltipLabel;
                        }
                    }
                }
            }
        });
    });
</script>