<?php
if (!defined('ABSPATH')) {
    exit;
}

class WP_Cache_Benchmark_Engine {
    
    private $resource_monitor;
    private $profile_manager;
    private $query_tracker;
    private $result_id;
    
    const DURATION_QUICK = 'quick';
    const DURATION_2MIN = '2min';
    const DURATION_5MIN = '5min';
    const DURATION_UNTIL_STOP = 'until_stop';
    
    const CHUNK_SIZE = 5;
    const CHUNK_TIME_LIMIT = 2;
    
    private $duration_configs = array(
        'quick' => array(
            'posts' => 100,
            'iterations' => 10,
            'max_time' => 60,
            'label' => 'Quick',
            'api_reads' => 50,
            'option_reloads' => 20,
            'cron_writes' => 5
        ),
        '2min' => array(
            'posts' => 1000,
            'iterations' => 50,
            'max_time' => 120,
            'label' => '2 Minutes',
            'api_reads' => 500,
            'option_reloads' => 100,
            'cron_writes' => 25
        ),
        '5min' => array(
            'posts' => 2500,
            'iterations' => 100,
            'max_time' => 300,
            'label' => '5 Minutes',
            'api_reads' => 1000,
            'option_reloads' => 200,
            'cron_writes' => 50
        ),
        'until_stop' => array(
            'posts' => 5000,
            'iterations' => 500,
            'max_time' => 600,
            'label' => 'Until Stopped (max 10 min)',
            'api_reads' => 2000,
            'option_reloads' => 500,
            'cron_writes' => 100
        )
    );
    
    public function __construct() {
        $this->resource_monitor = new WP_Cache_Benchmark_Resource_Monitor();
        $this->profile_manager = new WP_Cache_Benchmark_Profile_Manager();
        $this->query_tracker = WP_Cache_Benchmark_Query_Tracker::instance();
    }
    
    public function get_duration_configs() {
        return $this->duration_configs;
    }
    
    public function start_job($profile_id = null, $duration = 'quick', $name = null, $options = array()) {
        $config = isset($this->duration_configs[$duration]) ? $this->duration_configs[$duration] : $this->duration_configs['quick'];
        
        $total_work = $config['iterations'];
        if (!empty($options['create_posts'])) $total_work += $config['posts'];
        if (!empty($options['read_api'])) $total_work += $config['api_reads'];
        if (!empty($options['reload_options'])) $total_work += $config['option_reloads'];
        if (!empty($options['simulate_cron'])) $total_work += $config['cron_writes'];
        
        $profile = null;
        if ($profile_id) {
            $profile = $this->profile_manager->get_profile($profile_id);
        }
        
        $result_name = $name ?: ($profile ? $profile->name . ' Benchmark' : 'Benchmark - ' . $config['label']);
        
        $job_config = array(
            'duration' => $duration,
            'config' => $config,
            'options' => $options,
            'profile_id' => $profile_id,
            'start_time' => time(),
            'max_end_time' => time() + $config['max_time'],
            'phases' => $this->build_phases($config, $options),
            'current_phase_index' => 0,
            'total_completed' => 0,
            'created_post_ids' => array()
        );
        
        $this->result_id = WP_Cache_Benchmark_Database::save_result(array(
            'profile_id' => $profile_id,
            'test_type' => 'standard',
            'name' => $result_name,
            'status' => 'running',
            'iterations' => $config['iterations'],
            'total_iterations' => $total_work,
            'current_phase' => 'init',
            'job_config' => $job_config
        ));
        
        $this->log($this->result_id, 'info', 'Benchmark started with duration: ' . $config['label']);
        $this->log($this->result_id, 'info', 'Total work units: ' . $total_work);
        
        if ($profile && !empty($profile->plugins)) {
            $this->profile_manager->activate_profile_plugins($profile->plugins);
            $this->log($this->result_id, 'info', 'Activated profile plugins: ' . $profile->name);
        }
        
        return $this->result_id;
    }
    
    private function build_phases($config, $options) {
        $phases = array();
        
        $phases[] = array(
            'type' => 'iterations',
            'total' => $config['iterations'],
            'completed' => 0,
            'label' => 'Benchmark Iterations'
        );
        
        if (!empty($options['create_posts']) && $config['posts'] > 0) {
            $phases[] = array(
                'type' => 'create_posts',
                'total' => $config['posts'],
                'completed' => 0,
                'label' => 'Post Creation Test'
            );
        }
        
        if (!empty($options['read_api']) && $config['api_reads'] > 0) {
            $phases[] = array(
                'type' => 'read_api',
                'total' => $config['api_reads'],
                'completed' => 0,
                'label' => 'API Read Test'
            );
        }
        
        if (!empty($options['reload_options']) && $config['option_reloads'] > 0) {
            $phases[] = array(
                'type' => 'reload_options',
                'total' => $config['option_reloads'],
                'completed' => 0,
                'label' => 'Option Reload Test'
            );
        }
        
        if (!empty($options['simulate_cron']) && $config['cron_writes'] > 0) {
            $phases[] = array(
                'type' => 'simulate_cron',
                'total' => $config['cron_writes'],
                'completed' => 0,
                'label' => 'Cron Simulation Test'
            );
        }
        
        return $phases;
    }
    
    public function process_chunk($job_id) {
        $result = WP_Cache_Benchmark_Database::get_result($job_id);
        if (!$result) {
            return array('status' => 'error', 'message' => 'Job not found');
        }
        
        if ($result->status === 'completed' || $result->status === 'failed' || $result->status === 'stopped') {
            return array('status' => $result->status, 'message' => 'Job already finished');
        }
        
        if (WP_Cache_Benchmark_Database::is_stop_requested($job_id)) {
            return $this->finalize_job($job_id, 'stopped');
        }
        
        $job_config = maybe_unserialize($result->job_config);
        if (!$job_config || !is_array($job_config)) {
            return array('status' => 'error', 'message' => 'Invalid job config');
        }
        
        if (!isset($job_config['created_post_ids'])) $job_config['created_post_ids'] = array();
        
        if (time() > $job_config['max_end_time']) {
            return $this->finalize_job($job_id, 'completed');
        }
        
        $this->result_id = $job_id;
        $this->resource_monitor->start();
        $this->query_tracker->reset();
        $this->query_tracker->start_tracking();
        
        $phases = $job_config['phases'];
        $phase_index = $job_config['current_phase_index'];
        $total_completed = isset($job_config['total_completed']) ? intval($job_config['total_completed']) : 0;
        
        if ($phase_index >= count($phases)) {
            $this->query_tracker->stop_tracking();
            $this->resource_monitor->stop();
            return $this->finalize_job($job_id);
        }
        
        $current_phase = $phases[$phase_index];
        $chunk_start = microtime(true);
        $processed = 0;
        
        WP_Cache_Benchmark_Database::update_result($job_id, array(
            'current_phase' => $current_phase['label'],
            'last_heartbeat' => current_time('mysql')
        ));
        
        while ($processed < self::CHUNK_SIZE && 
               (microtime(true) - $chunk_start) < self::CHUNK_TIME_LIMIT &&
               $current_phase['completed'] < $current_phase['total']) {
            
            $work_result = $this->do_work($current_phase['type'], $current_phase['completed'], $job_config);
            
            $current_phase['completed']++;
            $processed++;
            
            if ($work_result) {
                if (isset($work_result['post_id'])) {
                    $job_config['created_post_ids'][] = $work_result['post_id'];
                }
                
                $total_completed++;
                $phases[$phase_index] = $current_phase;
                
                WP_Cache_Benchmark_Database::save_metric(array(
                    'result_id' => $job_id,
                    'iteration' => $total_completed,
                    'response_time' => isset($work_result['response_time']) ? floatval($work_result['response_time']) : 0,
                    'memory_usage' => isset($work_result['memory_usage']) ? intval($work_result['memory_usage']) : 0,
                    'db_queries' => isset($work_result['db_queries']) ? intval($work_result['db_queries']) : 0,
                    'cpu_usage' => isset($work_result['cpu_usage']) ? floatval($work_result['cpu_usage']) : 0,
                    'ram_usage' => isset($work_result['ram_usage']) ? intval($work_result['ram_usage']) : 0,
                    'disk_read' => isset($work_result['disk_read']) ? floatval($work_result['disk_read']) : 0,
                    'disk_write' => isset($work_result['disk_write']) ? floatval($work_result['disk_write']) : 0,
                    'cache_hits' => isset($work_result['cache_hits']) ? intval($work_result['cache_hits']) : 0,
                    'cache_misses' => isset($work_result['cache_misses']) ? intval($work_result['cache_misses']) : 0
                ));
            }
            
            if (WP_Cache_Benchmark_Database::is_stop_requested($job_id)) {
                break;
            }
        }
        
        $phases[$phase_index] = $current_phase;
        $job_config['phases'] = $phases;
        $job_config['total_completed'] = $total_completed;
        
        if ($current_phase['completed'] >= $current_phase['total']) {
            $this->log($job_id, 'success', $current_phase['label'] . ' completed');
            $job_config['current_phase_index']++;
        }
        
        $this->query_tracker->stop_tracking();
        $this->resource_monitor->stop();
        
        $this->log($job_id, 'info', sprintf(
            'Heartbeat: %s - %d/%d (%.1f%%)',
            $current_phase['label'],
            $current_phase['completed'],
            $current_phase['total'],
            ($current_phase['completed'] / max(1, $current_phase['total'])) * 100
        ), array(
            'phase' => $current_phase['label'],
            'phase_progress' => $current_phase['completed'],
            'phase_total' => $current_phase['total']
        ));
        
        $serialized_config = maybe_serialize($job_config);
        
        WP_Cache_Benchmark_Database::update_result($job_id, array(
            'current_iteration' => $total_completed,
            'job_config' => $serialized_config,
            'last_heartbeat' => current_time('mysql')
        ));
        
        if ($job_config['current_phase_index'] >= count($phases)) {
            return $this->finalize_job($job_id);
        }
        
        if (WP_Cache_Benchmark_Database::is_stop_requested($job_id)) {
            return $this->finalize_job($job_id, 'stopped');
        }
        
        return array(
            'status' => 'running',
            'current_phase' => $current_phase['label'],
            'phase_progress' => $current_phase['completed'],
            'phase_total' => $current_phase['total'],
            'total_completed' => $total_completed,
            'phases_remaining' => count($phases) - $job_config['current_phase_index']
        );
    }
    
    private function do_work($type, $iteration, &$job_config) {
        switch ($type) {
            case 'iterations':
                return $this->run_iteration($iteration + 1);
            case 'create_posts':
                return $this->create_single_post($iteration + 1);
            case 'read_api':
                return $this->read_single_api($iteration + 1);
            case 'reload_options':
                return $this->reload_single_option($iteration + 1);
            case 'simulate_cron':
                return $this->simulate_single_cron($iteration + 1, $job_config);
            default:
                return null;
        }
    }
    
    private function run_iteration($iteration_num) {
        global $wpdb;
        
        $queries_before = $wpdb->num_queries;
        $memory_before = memory_get_usage(true);
        $cache_before = $this->get_cache_stats();
        
        $start_time = microtime(true);
        $this->simulate_page_load();
        $end_time = microtime(true);
        
        $cache_after = $this->get_cache_stats();
        
        $response_time = ($end_time - $start_time) * 1000;
        $memory_usage = memory_get_usage(true);
        $db_queries = $wpdb->num_queries - $queries_before;
        
        $resources = $this->resource_monitor->get_current();
        
        if ($response_time > 100) {
            $this->log($this->result_id, 'slow', sprintf('Slow iteration #%d: %.2fms', $iteration_num, $response_time));
        }
        
        return array(
            'iteration' => $iteration_num,
            'response_time' => $response_time,
            'memory_usage' => $memory_usage,
            'memory_delta' => $memory_usage - $memory_before,
            'db_queries' => $db_queries,
            'cpu_usage' => $resources['cpu'],
            'ram_usage' => $resources['ram'],
            'disk_read' => $resources['disk_read'],
            'disk_write' => $resources['disk_write'],
            'cache_hits' => max(0, $cache_after['hits'] - $cache_before['hits']),
            'cache_misses' => max(0, $cache_after['misses'] - $cache_before['misses'])
        );
    }
    
    private function simulate_page_load() {
        global $wpdb;
        
        $wpdb->get_results("SELECT * FROM {$wpdb->options} LIMIT 100");
        
        $options = array('siteurl', 'blogname', 'blogdescription', 'admin_email', 'posts_per_page',
            'date_format', 'time_format', 'timezone_string', 'active_plugins', 'template', 'stylesheet');
        
        foreach ($options as $option) {
            get_option($option);
        }
        
        $posts = get_posts(array(
            'numberposts' => 10,
            'post_type' => 'post',
            'post_status' => 'publish'
        ));
        
        foreach ($posts as $post) {
            get_post_meta($post->ID);
            get_the_author_meta('display_name', $post->post_author);
            get_the_category($post->ID);
            get_the_tags($post->ID);
        }
        
        get_users(array('number' => 5));
        wp_count_posts();
        wp_count_terms('category');
        wp_count_terms('post_tag');
        get_transient('random_test_transient_' . rand(1, 100));
        get_option('sidebars_widgets');
        wp_get_nav_menu_items(get_nav_menu_locations());
    }
    
    private function create_single_post($index) {
        global $wpdb;
        
        $queries_before = $wpdb->num_queries;
        $memory_before = memory_get_usage(true);
        $cache_before = $this->get_cache_stats();
        
        $start_time = microtime(true);
        
        $post_id = wp_insert_post(array(
            'post_title' => 'Benchmark Test Post ' . $index . ' - ' . wp_generate_uuid4(),
            'post_content' => $this->generate_random_content(),
            'post_status' => 'publish',
            'post_type' => 'post',
            'post_author' => get_current_user_id() ?: 1
        ));
        
        if ($post_id && !is_wp_error($post_id)) {
            for ($m = 1; $m <= 5; $m++) {
                update_post_meta($post_id, 'benchmark_meta_' . $m, wp_generate_uuid4());
            }
        }
        
        $duration = (microtime(true) - $start_time) * 1000;
        $cache_after = $this->get_cache_stats();
        $resources = $this->resource_monitor->get_current();
        
        if ($duration > 500) {
            $this->log($this->result_id, 'slow', sprintf('Slow post creation #%d: %.2fms', $index, $duration));
        }
        
        return array(
            'post_id' => ($post_id && !is_wp_error($post_id)) ? $post_id : null,
            'response_time' => $duration,
            'memory_usage' => memory_get_usage(true),
            'db_queries' => $wpdb->num_queries - $queries_before,
            'cpu_usage' => $resources['cpu'],
            'ram_usage' => $resources['ram'],
            'disk_read' => $resources['disk_read'],
            'disk_write' => $resources['disk_write'],
            'cache_hits' => max(0, $cache_after['hits'] - $cache_before['hits']),
            'cache_misses' => max(0, $cache_after['misses'] - $cache_before['misses'])
        );
    }
    
    private function read_single_api($index) {
        global $wpdb;
        
        $queries_before = $wpdb->num_queries;
        $memory_before = memory_get_usage(true);
        $cache_before = $this->get_cache_stats();
        
        $start_time = microtime(true);
        
        $posts = get_posts(array(
            'numberposts' => 10,
            'post_type' => 'post',
            'post_status' => 'publish',
            'offset' => ($index % 10) * 10
        ));
        
        foreach ($posts as $post) {
            get_post($post->ID);
            get_post_meta($post->ID);
            get_the_author_meta('display_name', $post->post_author);
            get_the_category($post->ID);
            get_the_tags($post->ID);
            get_comments(array('post_id' => $post->ID, 'number' => 5));
        }
        
        $duration = (microtime(true) - $start_time) * 1000;
        $cache_after = $this->get_cache_stats();
        $resources = $this->resource_monitor->get_current();
        
        if ($duration > 200) {
            $this->log($this->result_id, 'slow', sprintf('Slow API read #%d: %.2fms', $index, $duration));
        }
        
        return array(
            'response_time' => $duration,
            'memory_usage' => memory_get_usage(true),
            'db_queries' => $wpdb->num_queries - $queries_before,
            'cpu_usage' => $resources['cpu'],
            'ram_usage' => $resources['ram'],
            'disk_read' => $resources['disk_read'],
            'disk_write' => $resources['disk_write'],
            'cache_hits' => max(0, $cache_after['hits'] - $cache_before['hits']),
            'cache_misses' => max(0, $cache_after['misses'] - $cache_before['misses'])
        );
    }
    
    private function reload_single_option($index) {
        global $wpdb;
        
        $queries_before = $wpdb->num_queries;
        $memory_before = memory_get_usage(true);
        $cache_before = $this->get_cache_stats();
        
        $start_time = microtime(true);
        
        wp_cache_flush();
        
        $options = array('siteurl', 'blogname', 'blogdescription', 'admin_email', 
            'posts_per_page', 'active_plugins', 'template', 'stylesheet');
        
        foreach ($options as $option) {
            get_option($option);
        }
        
        $duration = (microtime(true) - $start_time) * 1000;
        $cache_after = $this->get_cache_stats();
        $resources = $this->resource_monitor->get_current();
        
        if ($duration > 50) {
            $this->log($this->result_id, 'slow', sprintf('Slow option reload #%d: %.2fms', $index, $duration));
        }
        
        return array(
            'response_time' => $duration,
            'memory_usage' => memory_get_usage(true),
            'db_queries' => $wpdb->num_queries - $queries_before,
            'cpu_usage' => $resources['cpu'],
            'ram_usage' => $resources['ram'],
            'disk_read' => $resources['disk_read'],
            'disk_write' => $resources['disk_write'],
            'cache_hits' => max(0, $cache_after['hits'] - $cache_before['hits']),
            'cache_misses' => max(0, $cache_after['misses'] - $cache_before['misses'])
        );
    }
    
    private function simulate_single_cron($index, &$job_config) {
        global $wpdb;
        
        $queries_before = $wpdb->num_queries;
        $memory_before = memory_get_usage(true);
        $cache_before = $this->get_cache_stats();
        
        $upload_dir = wp_upload_dir();
        $test_dir = $upload_dir['basedir'] . '/benchmark-test';
        
        if (!file_exists($test_dir)) {
            wp_mkdir_p($test_dir);
        }
        
        $start_time = microtime(true);
        
        $file_path = $test_dir . '/test-' . wp_generate_uuid4() . '.txt';
        $content = str_repeat('Benchmark test content. ', 40000);
        file_put_contents($file_path, $content);
        
        $duration = (microtime(true) - $start_time) * 1000;
        $cache_after = $this->get_cache_stats();
        $resources = $this->resource_monitor->get_current();
        
        if (file_exists($file_path)) {
            unlink($file_path);
        }
        
        if ($duration > 100) {
            $this->log($this->result_id, 'slow', sprintf('Slow file write: %.2fms', $duration));
        }
        
        return array(
            'response_time' => $duration,
            'memory_usage' => memory_get_usage(true),
            'db_queries' => $wpdb->num_queries - $queries_before,
            'cpu_usage' => $resources['cpu'],
            'ram_usage' => $resources['ram'],
            'disk_read' => $resources['disk_read'],
            'disk_write' => $resources['disk_write'],
            'cache_hits' => max(0, $cache_after['hits'] - $cache_before['hits']),
            'cache_misses' => max(0, $cache_after['misses'] - $cache_before['misses'])
        );
    }
    
    public function finalize_job($job_id, $final_status = 'completed') {
        $result = WP_Cache_Benchmark_Database::get_result($job_id);
        if (!$result) {
            return array('status' => 'error', 'message' => 'Job not found');
        }
        
        $job_config = maybe_unserialize($result->job_config);
        if (!$job_config || !is_array($job_config)) {
            $job_config = array(
                'response_times' => array(),
                'memory_usages' => array(),
                'db_queries' => array(),
                'cache_hits' => 0,
                'cache_misses' => 0,
                'created_post_ids' => array(),
                'profile_id' => null,
                'config' => array()
            );
        }
        
        $this->log($job_id, 'info', 'Finalizing benchmark...');
        
        if (!empty($job_config['created_post_ids'])) {
            $this->log($job_id, 'info', 'Cleaning up ' . count($job_config['created_post_ids']) . ' test posts...');
            foreach ($job_config['created_post_ids'] as $post_id) {
                wp_delete_post($post_id, true);
            }
        }
        
        $db_metrics = WP_Cache_Benchmark_Database::get_metrics($job_id);
        
        $response_times = array();
        $memory_usages = array();
        $db_queries_arr = array();
        $cache_hits = 0;
        $cache_misses = 0;
        
        foreach ($db_metrics as $metric) {
            if ($metric->response_time > 0) {
                $response_times[] = floatval($metric->response_time);
            }
            if ($metric->memory_usage > 0) {
                $memory_usages[] = intval($metric->memory_usage);
            }
            if ($metric->db_queries > 0) {
                $db_queries_arr[] = intval($metric->db_queries);
            }
            $cache_hits += intval($metric->cache_hits);
            $cache_misses += intval($metric->cache_misses);
        }
        
        $avg_response = count($response_times) > 0 ? array_sum($response_times) / count($response_times) : 0;
        $avg_memory = count($memory_usages) > 0 ? array_sum($memory_usages) / count($memory_usages) : 0;
        $avg_queries = count($db_queries_arr) > 0 ? array_sum($db_queries_arr) / count($db_queries_arr) : 0;
        
        $total_cache = $cache_hits + $cache_misses;
        $cache_hit_rate = $total_cache > 0 ? ($cache_hits / $total_cache) * 100 : 0;
        
        $query_stats = $this->query_tracker->get_query_stats();
        
        $report = $this->generate_report($avg_response, $avg_memory, $avg_queries, $cache_hit_rate, count($response_times));
        
        WP_Cache_Benchmark_Database::update_result($job_id, array(
            'status' => $final_status,
            'avg_response_time' => $avg_response,
            'min_response_time' => count($response_times) > 0 ? min($response_times) : 0,
            'max_response_time' => count($response_times) > 0 ? max($response_times) : 0,
            'avg_memory_usage' => $avg_memory,
            'peak_memory_usage' => count($memory_usages) > 0 ? max($memory_usages) : 0,
            'avg_db_queries' => $avg_queries,
            'total_db_queries' => array_sum($db_queries_arr),
            'cache_hits' => $cache_hits,
            'cache_misses' => $cache_misses,
            'cache_hit_rate' => $cache_hit_rate,
            'raw_data' => maybe_serialize(array(
                'query_stats' => $query_stats,
                'report' => $report,
                'duration_config' => $job_config['config']
            )),
            'completed_at' => current_time('mysql')
        ));
        
        if ($job_config['profile_id']) {
            $this->profile_manager->restore_original_state();
        }
        
        $this->log($job_id, 'success', 'Benchmark ' . $final_status . ' successfully');
        
        return array(
            'status' => $final_status,
            'result_id' => $job_id,
            'report' => $report
        );
    }
    
    private function generate_report($avg_response, $avg_memory, $avg_queries, $cache_hit_rate, $iterations) {
        $score = 100;
        $bottlenecks = array();
        $suggestions = array();
        
        if ($avg_response > 500) {
            $score -= 30;
            $bottlenecks[] = array('type' => 'response_time', 'severity' => 'high', 'message' => 'Average response time is very high');
            $suggestions[] = 'Consider enabling object caching (Redis/Memcached)';
        } elseif ($avg_response > 200) {
            $score -= 15;
            $bottlenecks[] = array('type' => 'response_time', 'severity' => 'medium', 'message' => 'Average response time is moderate');
            $suggestions[] = 'Review database queries for optimization opportunities';
        }
        
        if ($avg_queries > 100) {
            $score -= 20;
            $bottlenecks[] = array('type' => 'db_queries', 'severity' => 'high', 'message' => 'Too many database queries per request');
            $suggestions[] = 'Enable persistent object caching to reduce database load';
        } elseif ($avg_queries > 50) {
            $score -= 10;
            $bottlenecks[] = array('type' => 'db_queries', 'severity' => 'medium', 'message' => 'Moderate number of database queries');
        }
        
        if ($cache_hit_rate < 50 && $cache_hit_rate > 0) {
            $score -= 15;
            $bottlenecks[] = array('type' => 'cache', 'severity' => 'medium', 'message' => 'Low cache hit rate');
            $suggestions[] = 'Check cache configuration and expiration settings';
        }
        
        $grade = 'A';
        if ($score < 90) $grade = 'B';
        if ($score < 75) $grade = 'C';
        if ($score < 60) $grade = 'D';
        if ($score < 40) $grade = 'F';
        
        return array(
            'score' => max(0, $score),
            'grade' => $grade,
            'metrics' => array(
                'avg_response_time' => round($avg_response, 2),
                'avg_memory_usage' => $avg_memory,
                'avg_db_queries' => round($avg_queries, 1),
                'cache_hit_rate' => round($cache_hit_rate, 1),
                'iterations' => $iterations
            ),
            'bottlenecks' => $bottlenecks,
            'suggestions' => $suggestions
        );
    }
    
    private function log($result_id, $type, $message, $data = null) {
        WP_Cache_Benchmark_Database::save_log($result_id, $type, $message, $data);
    }
    
    private function generate_random_content() {
        $paragraphs = rand(2, 4);
        $content = '';
        
        $words = array(
            'lorem', 'ipsum', 'dolor', 'sit', 'amet', 'consectetur', 'adipiscing',
            'elit', 'sed', 'do', 'eiusmod', 'tempor', 'incididunt', 'ut', 'labore'
        );
        
        for ($p = 0; $p < $paragraphs; $p++) {
            $sentences = rand(3, 5);
            $paragraph = '';
            
            for ($s = 0; $s < $sentences; $s++) {
                $word_count = rand(6, 12);
                $sentence = array();
                
                for ($w = 0; $w < $word_count; $w++) {
                    $sentence[] = $words[array_rand($words)];
                }
                
                $sentence[0] = ucfirst($sentence[0]);
                $paragraph .= implode(' ', $sentence) . '. ';
            }
            
            $content .= '<p>' . trim($paragraph) . '</p>';
        }
        
        return $content;
    }
    
    private function get_cache_stats() {
        global $wp_object_cache;
        
        $hits = 0;
        $misses = 0;
        
        if (isset($wp_object_cache) && is_object($wp_object_cache)) {
            if (isset($wp_object_cache->cache_hits)) {
                $hits = $wp_object_cache->cache_hits;
            }
            if (isset($wp_object_cache->cache_misses)) {
                $misses = $wp_object_cache->cache_misses;
            }
        }
        
        return array('hits' => $hits, 'misses' => $misses);
    }
    
    public function get_job_status($job_id) {
        $result = WP_Cache_Benchmark_Database::get_result($job_id);
        if (!$result) {
            return null;
        }
        
        $job_config = maybe_unserialize($result->job_config);
        $phases = $job_config ? $job_config['phases'] : array();
        
        return array(
            'status' => $result->status,
            'current_phase' => $result->current_phase,
            'current_iteration' => intval($result->current_iteration),
            'total_iterations' => intval($result->total_iterations),
            'progress' => $result->total_iterations > 0 ? 
                round(($result->current_iteration / $result->total_iterations) * 100, 1) : 0,
            'phases' => $phases,
            'stop_requested' => intval($result->stop_requested) === 1
        );
    }
}
