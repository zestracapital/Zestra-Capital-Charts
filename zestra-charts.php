<?php
/**
 * Plugin Name: Zestra Capital Charts System
 * Plugin URI: https://client.zestracapital.com
 * Description: Advanced charting system that consumes data from DMT plugin. Uses React + TypeScript for TradingView-like experience.
 * Version: 1.0.0
 * Author: Zestra Capital
 * Author URI: https://zestracapital.com
 * Requires at least: 5.0
 * Requires PHP: 7.2
 * Depends: Zestra Capital Data Management Tool (DMT)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants
define( 'ZCI_CHARTS_VERSION', '1.0.0' );
define( 'ZCI_CHARTS_PLUGIN_FILE', __FILE__ );
define( 'ZCI_CHARTS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ZCI_CHARTS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

class ZCI_Charts_System {

    private static $instance = null;

    public static function instance() {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'init', [ $this, 'init' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_assets' ] );
        add_shortcode( 'economic_chart', [ $this, 'render_chart_shortcode' ] );
        add_shortcode( 'economic_dashboard', [ $this, 'render_dashboard_shortcode' ] );

        // Listen for DMT data updates
        add_action( 'zci_dmt_data_changed', [ $this, 'handle_data_update' ], 10, 2 );
    }

    public function init() {
        // Check if DMT plugin is active
        if ( ! $this->is_dmt_active() ) {
            add_action( 'admin_notices', [ $this, 'dmt_required_notice' ] );
            return;
        }
    }

    private function is_dmt_active() {
        return class_exists( 'ZCI_Data_Management_Tool' );
    }

    public function dmt_required_notice() {
        ?>
        <div class="notice notice-error">
            <p><strong>Zestra Capital Charts:</strong> Requires the Data Management Tool (DMT) plugin to be active.</p>
        </div>
        <?php
    }

    public function enqueue_frontend_assets() {
        // Only load when shortcode is used
        global $post;
        if ( ! is_a( $post, 'WP_Post' ) ) return;

        if ( has_shortcode( $post->post_content, 'economic_chart' ) || has_shortcode( $post->post_content, 'economic_dashboard' ) ) {
            // Chart.js
            wp_enqueue_script( 'chartjs', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.min.js', [], '4.4.1', true );
            wp_enqueue_script( 'chartjs-adapter', 'https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@3.0.0/dist/chartjs-adapter-date-fns.bundle.min.js', ['chartjs'], '3.0.0', true );

            // React (for advanced features)
            wp_enqueue_script( 'react', 'https://unpkg.com/react@18/umd/react.production.min.js', [], '18', true );
            wp_enqueue_script( 'react-dom', 'https://unpkg.com/react-dom@18/umd/react-dom.production.min.js', ['react'], '18', true );

            // Lucide Icons
            wp_enqueue_script( 'lucide-react', 'https://unpkg.com/lucide@latest/dist/umd/lucide.js', [], 'latest', true );

            // Custom Charts JS (will contain your existing Dashboard.tsx logic)
            wp_enqueue_script( 'zci-charts', ZCI_CHARTS_PLUGIN_URL . 'assets/js/charts-system.js', ['chartjs', 'react', 'react-dom'], ZCI_CHARTS_VERSION, true );

            // Charts CSS
            wp_enqueue_style( 'zci-charts', ZCI_CHARTS_PLUGIN_URL . 'assets/css/charts-system.css', [], ZCI_CHARTS_VERSION );

            // Localize script with DMT API endpoints
            wp_localize_script( 'zci-charts', 'zciCharts', [
                'dmt_api_url' => rest_url( 'zci-dmt/v1/' ),
                'nonce' => wp_create_nonce( 'wp_rest' ),
                'plugin_url' => ZCI_CHARTS_PLUGIN_URL
            ]);
        }
    }

    public function render_chart_shortcode( $atts ) {
        $atts = shortcode_atts([
            'indicator' => '',
            'indicators' => '',
            'type' => 'line',
            'theme' => 'light',
            'height' => '400px',
            'width' => '100%',
            'time_range' => '1y',
            'title' => '',
            'show_controls' => 'true',
            'show_legend' => 'true',
            'comparison' => 'false'
        ], $atts, 'economic_chart');

        // Generate unique ID for this chart
        $chart_id = 'zci-chart-' . wp_generate_uuid4();

        // Determine indicators to load
        $indicators_list = '';
        if ( ! empty( $atts['indicators'] ) ) {
            $indicators_list = $atts['indicators'];
        } elseif ( ! empty( $atts['indicator'] ) ) {
            $indicators_list = $atts['indicator'];
        }

        ob_start();
        ?>
        <div id="<?php echo esc_attr( $chart_id ); ?>" 
             class="zci-chart-container"
             data-indicators="<?php echo esc_attr( $indicators_list ); ?>"
             data-type="<?php echo esc_attr( $atts['type'] ); ?>"
             data-theme="<?php echo esc_attr( $atts['theme'] ); ?>"
             data-time-range="<?php echo esc_attr( $atts['time_range'] ); ?>"
             data-title="<?php echo esc_attr( $atts['title'] ); ?>"
             data-show-controls="<?php echo esc_attr( $atts['show_controls'] ); ?>"
             data-show-legend="<?php echo esc_attr( $atts['show_legend'] ); ?>"
             data-comparison="<?php echo esc_attr( $atts['comparison'] ); ?>"
             style="height: <?php echo esc_attr( $atts['height'] ); ?>; width: <?php echo esc_attr( $atts['width'] ); ?>;">

            <div class="zci-chart-loading">
                <div class="zci-spinner"></div>
                <p>Loading economic data...</p>
            </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof ZCIChartsSystem !== 'undefined') {
                ZCIChartsSystem.initChart('<?php echo esc_js( $chart_id ); ?>');
            }
        });
        </script>
        <?php
        return ob_get_clean();
    }

    public function render_dashboard_shortcode( $atts ) {
        $atts = shortcode_atts([
            'theme' => 'light',
            'height' => '600px',
            'title' => 'Economic Dashboard',
            'default_indicators' => 'gdp_us,unemployment_us,cpi_us',
            'show_search' => 'true',
            'show_comparison' => 'true',
            'fullscreen' => 'true'
        ], $atts, 'economic_dashboard');

        $dashboard_id = 'zci-dashboard-' . wp_generate_uuid4();

        ob_start();
        ?>
        <div id="<?php echo esc_attr( $dashboard_id ); ?>" 
             class="zci-dashboard-container"
             data-theme="<?php echo esc_attr( $atts['theme'] ); ?>"
             data-title="<?php echo esc_attr( $atts['title'] ); ?>"
             data-default-indicators="<?php echo esc_attr( $atts['default_indicators'] ); ?>"
             data-show-search="<?php echo esc_attr( $atts['show_search'] ); ?>"
             data-show-comparison="<?php echo esc_attr( $atts['show_comparison'] ); ?>"
             data-fullscreen="<?php echo esc_attr( $atts['fullscreen'] ); ?>"
             style="height: <?php echo esc_attr( $atts['height'] ); ?>;">

            <div class="zci-dashboard-loading">
                <div class="zci-spinner-large"></div>
                <h3>Initializing Economic Dashboard...</h3>
                <p>Loading data from management system...</p>
            </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof ZCIChartsSystem !== 'undefined') {
                ZCIChartsSystem.initDashboard('<?php echo esc_js( $dashboard_id ); ?>');
            }
        });
        </script>
        <?php
        return ob_get_clean();
    }

    public function handle_data_update( $indicator_slug, $action ) {
        // This will be called when DMT updates data
        // We can trigger frontend refresh or caching invalidation
        do_action( 'zci_charts_refresh_data', $indicator_slug, $action );
    }
}

// Initialize Charts System
ZCI_Charts_System::instance();
