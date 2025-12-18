<?php
if (!defined('ABSPATH')) {
    exit;
}

class WP_Cache_Benchmark_Ajax_Handler {
    
    public static function init() {
        add_action('wp_ajax_wcb_save_profile', array(__CLASS__, 'save_profile'));
        add_action('wp_ajax_wcb_delete_profile', array(__CLASS__, 'delete_profile'));
        add_action('wp_ajax_wcb_start_benchmark', array(__CLASS__, 'start_benchmark'));
        add_action('wp_ajax_wcb_poll_benchmark', array(__CLASS__, 'poll_benchmark'));
        add_action('wp_ajax_wcb_stop_benchmark', array(__CLASS__, 'stop_benchmark'));
        add_action('wp_ajax_wcb_run_stress_test', array(__CLASS__, 'ajax_run_stress_test'));
        add_action('wp_ajax_wcb_get_comparison', array(__CLASS__, 'get_comparison'));
        add_action('wp_ajax_wcb_delete_result', array(__CLASS__, 'delete_result'));
        add_action('wp_ajax_wcb_export', array(__CLASS__, 'export'));
        add_action('wp_ajax_wcb_get_server_status', array(__CLASS__, 'get_server_status'));
        
        add_action('rest_api_init', array(__CLASS__, 'register_rest_routes'));
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
    
    public static function start_benchmark() {
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
        
        $engine = new WP_Cache_Benchmark_Engine();
        $job_id = $engine->start_job(
            $profile_id > 0 ? $profile_id : null, 
            $duration, 
            $name,
            $test_options
        );
        
        if (!$job_id) {
            wp_send_json_error(array('message' => __('Failed to start benchmark.', 'wp-cache-benchmark')));
        }
        
        $status = $engine->get_job_status($job_id);
        
        wp_send_json_success(array(
            'message' => __('Benchmark started successfully.', 'wp-cache-benchmark'),
            'job_id' => $job_id,
            'status' => $status
        ));
    }
    
    public static function poll_benchmark() {
        check_ajax_referer('wp_cache_benchmark_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'wp-cache-benchmark')));
        }
        
        $job_id = isset($_POST['job_id']) ? intval($_POST['job_id']) : 0;
        $last_log_id = isset($_POST['last_log_id']) ? intval($_POST['last_log_id']) : 0;
        $last_iteration = isset($_POST['last_iteration']) ? intval($_POST['last_iteration']) : 0;
        
        if ($job_id <= 0) {
            wp_send_json_error(array('message' => __('Invalid job ID.', 'wp-cache-benchmark')));
        }
        
        $result = WP_Cache_Benchmark_Database::get_result($job_id);
        if (!$result) {
            wp_send_json_error(array('message' => __('Job not found.', 'wp-cache-benchmark')));
        }
        
        $engine = new WP_Cache_Benchmark_Engine();
        
        if ($result->status === 'running') {
            set_time_limit(10);
            $chunk_result = $engine->process_chunk($job_id);
            $result = WP_Cache_Benchmark_Database::get_result($job_id);
        }
        
        $logs = WP_Cache_Benchmark_Database::get_logs_since($job_id, $last_log_id);
        $new_metrics = WP_Cache_Benchmark_Database::get_metrics_since($job_id, $last_iteration);
        
        $max_log_id = $last_log_id;
        foreach ($logs as $log) {
            if ($log->id > $max_log_id) {
                $max_log_id = $log->id;
            }
        }
        
        $max_iteration = $last_iteration;
        foreach ($new_metrics as $metric) {
            if ($metric->iteration > $max_iteration) {
                $max_iteration = $metric->iteration;
            }
        }
        
        $response_data = array(
            'status' => $result->status,
            'current_phase' => $result->current_phase,
            'current_iteration' => intval($result->current_iteration),
            'total_iterations' => intval($result->total_iterations),
            'progress' => $result->total_iterations > 0 ? 
                round(($result->current_iteration / $result->total_iterations) * 100, 1) : 0,
            'logs' => self::format_logs($logs),
            'metrics' => self::format_metrics($new_metrics),
            'last_log_id' => intval($max_log_id),
            'last_iteration' => intval($max_iteration),
            'stop_requested' => intval($result->stop_requested) === 1
        );
        
        if ($result->status === 'completed' || $result->status === 'stopped' || $result->status === 'failed') {
            $result = WP_Cache_Benchmark_Database::get_result($job_id);
            $response_data['result'] = self::format_result($result);
            $response_data['all_metrics'] = self::format_metrics(WP_Cache_Benchmark_Database::get_metrics($job_id));
            
            $raw_data = maybe_unserialize($result->raw_data);
            if ($raw_data && isset($raw_data['report'])) {
                $response_data['report'] = $raw_data['report'];
            }
        }
        
        wp_send_json_success($response_data);
    }
    
    public static function stop_benchmark() {
        check_ajax_referer('wp_cache_benchmark_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'wp-cache-benchmark')));
        }
        
        $job_id = isset($_POST['job_id']) ? intval($_POST['job_id']) : 0;
        
        if ($job_id <= 0) {
            wp_send_json_error(array('message' => __('Invalid job ID.', 'wp-cache-benchmark')));
        }
        
        $result = WP_Cache_Benchmark_Database::request_stop($job_id);
        
        if ($result) {
            WP_Cache_Benchmark_Database::save_log($job_id, 'warning', 'Stop requested by user');
            wp_send_json_success(array('message' => __('Stop requested.', 'wp-cache-benchmark')));
        } else {
            wp_send_json_error(array('message' => __('Failed to request stop.', 'wp-cache-benchmark')));
        }
    }
    
    private static function format_logs($logs) {
        $formatted = array();
        foreach ($logs as $log) {
            $formatted[] = array(
                'id' => intval($log->id),
                'type' => $log->log_type,
                'message' => $log->message,
                'data' => maybe_unserialize($log->data),
                'timestamp' => $log->created_at
            );
        }
        return $formatted;
    }
    
    private static function format_metrics($metrics) {
        $formatted = array();
        foreach ($metrics as $metric) {
            $formatted[] = array(
                'iteration' => intval($metric->iteration),
                'response_time' => floatval($metric->response_time),
                'memory_usage' => intval($metric->memory_usage),
                'db_queries' => intval($metric->db_queries),
                'cpu_usage' => floatval($metric->cpu_usage),
                'cache_hits' => intval($metric->cache_hits),
                'cache_misses' => intval($metric->cache_misses)
            );
        }
        return $formatted;
    }
    
    private static function format_result($result) {
        if (!$result) return null;
        
        return array(
            'id' => intval($result->id),
            'name' => $result->name,
            'status' => $result->status,
            'avg_response_time' => floatval($result->avg_response_time),
            'min_response_time' => floatval($result->min_response_time),
            'max_response_time' => floatval($result->max_response_time),
            'avg_memory_usage' => intval($result->avg_memory_usage),
            'peak_memory_usage' => intval($result->peak_memory_usage),
            'avg_db_queries' => intval($result->avg_db_queries),
            'total_db_queries' => intval($result->total_db_queries),
            'cache_hits' => intval($result->cache_hits),
            'cache_misses' => intval($result->cache_misses),
            'cache_hit_rate' => floatval($result->cache_hit_rate),
            'avg_cpu_usage' => floatval($result->avg_cpu_usage),
            'created_at' => $result->created_at,
            'completed_at' => $result->completed_at
        );
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
        
        if ($result) {
            $result = self::format_result($result);
        }
        
        wp_send_json_success(array(
            'message' => __('Stress test completed successfully.', 'wp-cache-benchmark'),
            'result_id' => $result_id,
            'result' => $result
        ));
    }
    
    public static function get_comparison() {
        check_ajax_referer('wp_cache_benchmark_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'wp-cache-benchmark')));
        }
        
        $result_ids = isset($_POST['result_ids']) ? array_map('intval', $_POST['result_ids']) : array();
        $baseline_id = isset($_POST['baseline_id']) ? intval($_POST['baseline_id']) : 0;
        
        if (count($result_ids) < 2) {
            wp_send_json_error(array('message' => __('Select at least 2 results to compare.', 'wp-cache-benchmark')));
        }
        
        if ($baseline_id <= 0) {
            $baseline_id = $result_ids[0];
        }
        
        $comparison_engine = new WP_Cache_Benchmark_Comparison_Engine();
        $comparison_engine->load_results($result_ids);
        $comparison_engine->set_baseline($baseline_id);
        
        $comparison_data = $comparison_engine->get_comparison_data();
        
        if ($comparison_data) {
            wp_send_json_success($comparison_data);
        } else {
            wp_send_json_error(array('message' => __('Failed to generate comparison.', 'wp-cache-benchmark')));
        }
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
        
        WP_Cache_Benchmark_Database::delete_logs($result_id);
        $result = WP_Cache_Benchmark_Database::delete_result($result_id);
        
        if ($result) {
            wp_send_json_success(array('message' => __('Result deleted successfully.', 'wp-cache-benchmark')));
        } else {
            wp_send_json_error(array('message' => __('Failed to delete result.', 'wp-cache-benchmark')));
        }
    }
    
    public static function export() {
        check_ajax_referer('wp_cache_benchmark_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'wp-cache-benchmark')));
        }
        
        $result_ids = isset($_POST['result_ids']) ? array_map('intval', $_POST['result_ids']) : array();
        $format = isset($_POST['format']) ? sanitize_text_field($_POST['format']) : 'json';
        
        if (empty($result_ids)) {
            wp_send_json_error(array('message' => __('No results selected.', 'wp-cache-benchmark')));
        }
        
        $export_handler = new WP_Cache_Benchmark_Export_Handler();
        
        if ($format === 'csv') {
            $data = $export_handler->export_csv($result_ids);
            $content_type = 'text/csv';
            $filename = 'benchmark-export-' . date('Y-m-d') . '.csv';
        } else {
            $data = $export_handler->export_json($result_ids);
            $content_type = 'application/json';
            $filename = 'benchmark-export-' . date('Y-m-d') . '.json';
        }
        
        wp_send_json_success(array(
            'data' => $data,
            'content_type' => $content_type,
            'filename' => $filename
        ));
    }
    
    public static function get_server_status() {
        check_ajax_referer('wp_cache_benchmark_nonce', 'nonce');
        
        $resource_monitor = new WP_Cache_Benchmark_Resource_Monitor();
        $current = $resource_monitor->get_current();
        
        wp_send_json_success(array(
            'cpu' => floatval($current['cpu']),
            'ram' => intval($current['ram']),
            'ram_percent' => floatval($current['ram_percent']),
            'disk_read' => floatval($current['disk_read']),
            'disk_write' => floatval($current['disk_write'])
        ));
    }
    
    public static function register_rest_routes() {
        register_rest_route('cache-benchmark/v1', '/status', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'rest_get_status'),
            'permission_callback' => function() {
                return current_user_can('manage_options');
            }
        ));
    }
    
    public static function rest_get_status($request) {
        $resource_monitor = new WP_Cache_Benchmark_Resource_Monitor();
        $current = $resource_monitor->get_current();
        
        return new WP_REST_Response($current, 200);
    }
}
