<?php
/**
 * Plugin Name: WP Cache Benchmark
 * Plugin URI: https://example.com/wp-cache-benchmark
 * Description: A comprehensive WordPress cache benchmarking plugin that helps users scientifically test and compare the performance of different cache configurations.
 * Version: 1.0.0
 * Author: Cache Benchmark Team
 * Author URI: https://example.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-cache-benchmark
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

define('WP_CACHE_BENCHMARK_VERSION', '1.0.0');
define('WP_CACHE_BENCHMARK_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WP_CACHE_BENCHMARK_PLUGIN_URL', plugin_dir_url(__FILE__));

class WP_Cache_Benchmark {
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->includes();
        $this->init_hooks();
    }
    
    private function includes() {
        require_once WP_CACHE_BENCHMARK_PLUGIN_DIR . 'includes/class-database.php';
        require_once WP_CACHE_BENCHMARK_PLUGIN_DIR . 'includes/class-profile-manager.php';
        require_once WP_CACHE_BENCHMARK_PLUGIN_DIR . 'includes/class-query-tracker.php';
        require_once WP_CACHE_BENCHMARK_PLUGIN_DIR . 'includes/class-benchmark-logger.php';
        require_once WP_CACHE_BENCHMARK_PLUGIN_DIR . 'includes/class-report-generator.php';
        require_once WP_CACHE_BENCHMARK_PLUGIN_DIR . 'includes/class-benchmark-engine.php';
        require_once WP_CACHE_BENCHMARK_PLUGIN_DIR . 'includes/class-resource-monitor.php';
        require_once WP_CACHE_BENCHMARK_PLUGIN_DIR . 'includes/class-stress-tester.php';
        require_once WP_CACHE_BENCHMARK_PLUGIN_DIR . 'includes/class-comparison-engine.php';
        require_once WP_CACHE_BENCHMARK_PLUGIN_DIR . 'includes/class-export-handler.php';
        require_once WP_CACHE_BENCHMARK_PLUGIN_DIR . 'includes/class-admin-ui.php';
        require_once WP_CACHE_BENCHMARK_PLUGIN_DIR . 'includes/class-ajax-handler.php';
    }
    
    private function init_hooks() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('rest_api_init', array($this, 'register_rest_routes'));
    }
    
    public static function activate() {
        require_once WP_CACHE_BENCHMARK_PLUGIN_DIR . 'includes/class-database.php';
        WP_Cache_Benchmark_Database::create_tables();
        flush_rewrite_rules();
    }
    
    public static function deactivate() {
        flush_rewrite_rules();
    }
    
    public function add_admin_menu() {
        add_menu_page(
            __('Cache Benchmark', 'wp-cache-benchmark'),
            __('Cache Benchmark', 'wp-cache-benchmark'),
            'manage_options',
            'wp-cache-benchmark',
            array('WP_Cache_Benchmark_Admin_UI', 'render_dashboard'),
            'dashicons-performance',
            80
        );
        
        add_submenu_page(
            'wp-cache-benchmark',
            __('Dashboard', 'wp-cache-benchmark'),
            __('Dashboard', 'wp-cache-benchmark'),
            'manage_options',
            'wp-cache-benchmark',
            array('WP_Cache_Benchmark_Admin_UI', 'render_dashboard')
        );
        
        add_submenu_page(
            'wp-cache-benchmark',
            __('Profiles', 'wp-cache-benchmark'),
            __('Profiles', 'wp-cache-benchmark'),
            'manage_options',
            'wp-cache-benchmark-profiles',
            array('WP_Cache_Benchmark_Admin_UI', 'render_profiles')
        );
        
        add_submenu_page(
            'wp-cache-benchmark',
            __('Run Benchmark', 'wp-cache-benchmark'),
            __('Run Benchmark', 'wp-cache-benchmark'),
            'manage_options',
            'wp-cache-benchmark-run',
            array('WP_Cache_Benchmark_Admin_UI', 'render_run_benchmark')
        );
        
        add_submenu_page(
            'wp-cache-benchmark',
            __('Stress Test', 'wp-cache-benchmark'),
            __('Stress Test', 'wp-cache-benchmark'),
            'manage_options',
            'wp-cache-benchmark-stress',
            array('WP_Cache_Benchmark_Admin_UI', 'render_stress_test')
        );
        
        add_submenu_page(
            'wp-cache-benchmark',
            __('Compare', 'wp-cache-benchmark'),
            __('Compare', 'wp-cache-benchmark'),
            'manage_options',
            'wp-cache-benchmark-compare',
            array('WP_Cache_Benchmark_Admin_UI', 'render_compare')
        );
        
        add_submenu_page(
            'wp-cache-benchmark',
            __('Results', 'wp-cache-benchmark'),
            __('Results', 'wp-cache-benchmark'),
            'manage_options',
            'wp-cache-benchmark-results',
            array('WP_Cache_Benchmark_Admin_UI', 'render_results')
        );
    }
    
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'wp-cache-benchmark') === false) {
            return;
        }
        
        wp_enqueue_style(
            'wp-cache-benchmark-admin',
            WP_CACHE_BENCHMARK_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            WP_CACHE_BENCHMARK_VERSION
        );
        
        wp_enqueue_script(
            'chartjs',
            WP_CACHE_BENCHMARK_PLUGIN_URL . 'assets/js/chart.min.js',
            array(),
            '4.4.1',
            true
        );
        
        wp_enqueue_script(
            'wp-cache-benchmark-admin',
            WP_CACHE_BENCHMARK_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery', 'chartjs'),
            WP_CACHE_BENCHMARK_VERSION,
            true
        );
        
        wp_localize_script('wp-cache-benchmark-admin', 'wpCacheBenchmark', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'restUrl' => rest_url('wp-cache-benchmark/v1/'),
            'nonce' => wp_create_nonce('wp_cache_benchmark_nonce'),
            'strings' => array(
                'confirmDelete' => __('Are you sure you want to delete this?', 'wp-cache-benchmark'),
                'benchmarkRunning' => __('Benchmark is running...', 'wp-cache-benchmark'),
                'benchmarkComplete' => __('Benchmark completed!', 'wp-cache-benchmark'),
                'error' => __('An error occurred. Please try again.', 'wp-cache-benchmark'),
            )
        ));
    }
    
    public function register_rest_routes() {
        register_rest_route('wp-cache-benchmark/v1', '/benchmark/run', array(
            'methods' => 'POST',
            'callback' => array('WP_Cache_Benchmark_Ajax_Handler', 'run_benchmark'),
            'permission_callback' => function() {
                return current_user_can('manage_options');
            }
        ));
        
        register_rest_route('wp-cache-benchmark/v1', '/stress-test/run', array(
            'methods' => 'POST',
            'callback' => array('WP_Cache_Benchmark_Ajax_Handler', 'run_stress_test'),
            'permission_callback' => function() {
                return current_user_can('manage_options');
            }
        ));
        
        register_rest_route('wp-cache-benchmark/v1', '/monitor/status', array(
            'methods' => 'GET',
            'callback' => array('WP_Cache_Benchmark_Ajax_Handler', 'get_monitor_status'),
            'permission_callback' => function() {
                return current_user_can('manage_options');
            }
        ));
    }
}

function wp_cache_benchmark() {
    return WP_Cache_Benchmark::get_instance();
}

register_activation_hook(__FILE__, array('WP_Cache_Benchmark', 'activate'));
register_deactivation_hook(__FILE__, array('WP_Cache_Benchmark', 'deactivate'));

add_action('plugins_loaded', 'wp_cache_benchmark');
