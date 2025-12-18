<?php
if (!defined('ABSPATH')) {
    exit;
}

class WP_Cache_Benchmark_Ajax_Handler {
    
    public static function init() {
        add_action('wp_ajax_wcb_save_profile', array(__CLASS__, 'save_profile'));
        add_action('wp_ajax_wcb_delete_profile', array(__CLASS__, 'delete_profile'));
        add_action('wp_ajax_wcb_run_benchmark', array(__CLASS__, 'ajax_run_benchmark'));
        add_action('wp_ajax_wcb_run_stress_test', array(__CLASS__, 'ajax_run_stress_test'));
        add_action('wp_ajax_wcb_get_comparison', array(__CLASS__, 'get_comparison'));
        add_action('wp_ajax_wcb_delete_result', array(__CLASS__, 'delete_result'));
        add_action('wp_ajax_wcb_export', array(__CLASS__, 'export'));
        add_action('wp_ajax_wcb_get_server_status', array(__CLASS__, 'get_server_status'));
    }
    
    public static function save_profile() {
        check_ajax_referer('wp_cache_benchmark_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'wp-cache-benchmark')));
        }
        
        $profile_id = isset($_POST['profile_id']) ? intval($_POST['profile_id']) : 0;
        $name = isset($_POST['profile_name']) ? sanitize_text_field($_POST['profile_name']) : '';
        $description = isset($_POST['profile_description']) ? sanitize_textarea_field($_POST['profile_description']) : '';
        $plugins = isset($_POST['plugins']) ? array_map('sanitize_text_field', $_POST['plugins']) : array();
        
        if (empty($name)) {
            wp_send_json_error(array('message' => __('Profile name is required.', 'wp-cache-benchmark')));
        }
        
        $profile_manager = new WP_Cache_Benchmark_Profile_Manager();
        
        if ($profile_id > 0) {
            $result = $profile_manager->update_profile($profile_id, $name, $description, $plugins);
        } else {
            $result = $profile_manager->create_profile($name, $description, $plugins);
        }
        
        if ($result) {
            wp_send_json_success(array(
                'message' => $profile_id > 0 ? __('Profile updated successfully.', 'wp-cache-benchmark') : __('Profile created successfully.', 'wp-cache-benchmark'),
                'profile_id' => $result
            ));
        } else {
            wp_send_json_error(array('message' => __('Failed to save profile.', 'wp-cache-benchmark')));
        }
    }
    
    public static function delete_profile() {
        check_ajax_referer('wp_cache_benchmark_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'wp-cache-benchmark')));
        }
        
        $profile_id = isset($_POST['profile_id']) ? intval($_POST['profile_id']) : 0;
        
        if ($profile_id <= 0) {
            wp_send_json_error(array('message' => __('Invalid profile ID.', 'wp-cache-benchmark')));
        }
        
        $profile_manager = new WP_Cache_Benchmark_Profile_Manager();
        $result = $profile_manager->delete_profile($profile_id);
        
        if ($result) {
            wp_send_json_success(array('message' => __('Profile deleted successfully.', 'wp-cache-benchmark')));
        } else {
            wp_send_json_error(array('message' => __('Failed to delete profile.', 'wp-cache-benchmark')));
        }
    }
    
    public static function ajax_run_benchmark() {
        check_ajax_referer('wp_cache_benchmark_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'wp-cache-benchmark')));
        }
        
        $profile_id = isset($_POST['profile_id']) ? intval($_POST['profile_id']) : 0;
        $duration = isset($_POST['duration']) ? sanitize_text_field($_POST['duration']) : 'quick';
        $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : null;
        
        $test_options = array(
            'create_posts' => isset($_POST['create_posts']) && $_POST['create_posts'] === '1',
            'read_api' => isset($_POST['read_api']) && $_POST['read_api'] === '1',
            'reload_options' => isset($_POST['reload_options']) && $_POST['reload_options'] === '1',
            'simulate_cron' => isset($_POST['simulate_cron']) && $_POST['simulate_cron'] === '1'
        );
        
        $valid_durations = array('quick', '2min', '5min', 'until_stop');
        if (!in_array($duration, $valid_durations)) {
            $duration = 'quick';
        }
        
        set_time_limit(700);
        
        $engine = new WP_Cache_Benchmark_Engine();
        $benchmark_result = $engine->run(
            $profile_id > 0 ? $profile_id : null, 
            $duration, 
            $name,
            $test_options
        );
        
        $result = WP_Cache_Benchmark_Database::get_result($benchmark_result['result_id']);
        $metrics = WP_Cache_Benchmark_Database::get_metrics($benchmark_result['result_id']);
        
        wp_send_json_success(array(
            'message' => __('Benchmark completed successfully.', 'wp-cache-benchmark'),
            'result_id' => $benchmark_result['result_id'],
            'result' => $result,
            'metrics' => $metrics,
            'logs' => $benchmark_result['logs'],
            'query_stats' => $benchmark_result['query_stats'],
            'report' => $benchmark_result['report']
        ));
    }
    
    public static function ajax_run_stress_test() {
        check_ajax_referer('wp_cache_benchmark_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'wp-cache-benchmark')));
        }
        
        $profile_id = isset($_POST['profile_id']) ? intval($_POST['profile_id']) : 0;
        $duration = isset($_POST['duration']) ? intval($_POST['duration']) : 60;
        $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : null;
        
        $options = array(
            'create_posts' => isset($_POST['create_posts']) && $_POST['create_posts'] === '1',
            'read_api' => isset($_POST['read_api']) && $_POST['read_api'] === '1',
            'reload_options' => isset($_POST['reload_options']) && $_POST['reload_options'] === '1',
            'simulate_cron' => isset($_POST['simulate_cron']) && $_POST['simulate_cron'] === '1',
            'duration' => max(30, min(300, $duration)),
            'name' => $name
        );
        
        set_time_limit(600);
        
        $stress_tester = new WP_Cache_Benchmark_Stress_Tester();
        $result_id = $stress_tester->run($profile_id > 0 ? $profile_id : null, $options);
        
        $result = WP_Cache_Benchmark_Database::get_result($result_id);
        
        wp_send_json_success(array(
            'message' => __('Stress test completed successfully.', 'wp-cache-benchmark'),
            'result_id' => $result_id,
            'result' => $result
        ));
    }
    
    public static function run_benchmark($request) {
        $profile_id = $request->get_param('profile_id');
        $iterations = $request->get_param('iterations') ?: 10;
        $name = $request->get_param('name');
        
        $engine = new WP_Cache_Benchmark_Engine();
        $result_id = $engine->run($profile_id, $iterations, $name);
        
        return new WP_REST_Response(array(
            'result_id' => $result_id,
            'result' => $engine->get_result()
        ), 200);
    }
    
    public static function run_stress_test($request) {
        $profile_id = $request->get_param('profile_id');
        $options = array(
            'create_posts' => $request->get_param('create_posts') !== false,
            'read_api' => $request->get_param('read_api') !== false,
            'reload_options' => $request->get_param('reload_options') !== false,
            'simulate_cron' => $request->get_param('simulate_cron') !== false,
            'duration' => $request->get_param('duration') ?: 60,
            'name' => $request->get_param('name')
        );
        
        $stress_tester = new WP_Cache_Benchmark_Stress_Tester();
        $result_id = $stress_tester->run($profile_id, $options);
        
        return new WP_REST_Response(array(
            'result_id' => $result_id,
            'result' => $stress_tester->get_result()
        ), 200);
    }
    
    public static function get_monitor_status($request) {
        $resource_monitor = new WP_Cache_Benchmark_Resource_Monitor();
        $current = $resource_monitor->get_current();
        
        return new WP_REST_Response($current, 200);
    }
    
    public static function get_comparison() {
        check_ajax_referer('wp_cache_benchmark_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'wp-cache-benchmark')));
        }
        
        $result_ids = isset($_POST['result_ids']) ? array_map('intval', $_POST['result_ids']) : array();
        
        $comparison_engine = new WP_Cache_Benchmark_Comparison_Engine();
        $load_result = $comparison_engine->load_results($result_ids);
        
        if (is_wp_error($load_result)) {
            wp_send_json_error(array('message' => $load_result->get_error_message()));
        }
        
        $comparison_data = $comparison_engine->get_comparison_data();
        
        wp_send_json_success($comparison_data);
    }
    
    public static function delete_result() {
        check_ajax_referer('wp_cache_benchmark_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'wp-cache-benchmark')));
        }
        
        $result_id = isset($_POST['result_id']) ? intval($_POST['result_id']) : 0;
        
        if ($result_id <= 0) {
            wp_send_json_error(array('message' => __('Invalid result ID.', 'wp-cache-benchmark')));
        }
        
        $result = WP_Cache_Benchmark_Database::delete_result($result_id);
        
        if ($result) {
            wp_send_json_success(array('message' => __('Result deleted successfully.', 'wp-cache-benchmark')));
        } else {
            wp_send_json_error(array('message' => __('Failed to delete result.', 'wp-cache-benchmark')));
        }
    }
    
    public static function export() {
        if (!wp_verify_nonce($_GET['_wpnonce'], 'wcb_export')) {
            wp_die(__('Security check failed.', 'wp-cache-benchmark'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Permission denied.', 'wp-cache-benchmark'));
        }
        
        $export_handler = new WP_Cache_Benchmark_Export_Handler();
        
        if (isset($_GET['id'])) {
            $result_id = intval($_GET['id']);
            $format = isset($_GET['format']) ? sanitize_text_field($_GET['format']) : 'json';
            
            $export_data = $export_handler->export_result($result_id, $format);
            $export_handler->download($export_data);
        } elseif (isset($_GET['ids'])) {
            $result_ids = array_map('intval', explode(',', $_GET['ids']));
            $format = isset($_GET['format']) ? sanitize_text_field($_GET['format']) : 'json';
            
            $export_data = $export_handler->export_comparison($result_ids, $format);
            $export_handler->download($export_data);
        }
        
        exit;
    }
    
    public static function get_server_status() {
        check_ajax_referer('wp_cache_benchmark_nonce', 'nonce');
        
        $resource_monitor = new WP_Cache_Benchmark_Resource_Monitor();
        $current = $resource_monitor->get_current();
        
        wp_send_json_success($current);
    }
}

WP_Cache_Benchmark_Ajax_Handler::init();
