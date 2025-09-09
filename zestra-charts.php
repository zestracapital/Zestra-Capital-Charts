<?php
// Register Dynamic and Static chart shortcodes
define('ZC_CHARTS_VERSION','1.0.1');
add_shortcode('economic_chart_dynamic','zcCharts_dynamic_shortcode');
add_shortcode('economic_chart_static','zcCharts_static_shortcode');

function zcCharts_dynamic_shortcode($atts){
    $atts = shortcode_atts([
        'title'=>'Economic Dashboard',
        'height'=>'600px',
        'theme'=>'light'
    ],$atts,'economic_chart_dynamic');
    ob_start();
    ?>
    <div class="zci-compare-builder" data-type="line" data-range="1y" data-theme="<?php echo esc_attr($atts['theme']); ?>" style="height:<?php echo esc_attr($atts['height']); ?>;">
        <!-- Dynamic Chart Controls -->
    </div>
    <?php
    return ob_get_clean();
}

function zcCharts_static_shortcode($atts){
    $atts = shortcode_atts([
        'indicators'=>'',
        'title'=>'',
        'height'=>'400px',
        'type'=>'line'
    ],$atts,'economic_chart_static');
    ob_start();
    ?>
    <canvas class="zci-static-chart" data-indicators="<?php echo esc_attr($atts['indicators']); ?>" data-type="<?php echo esc_attr($atts['type']); ?>" style="height:<?php echo esc_attr($atts['height']); ?>;width:100%;"></canvas>
    <?php
    return ob_get_clean();
}
