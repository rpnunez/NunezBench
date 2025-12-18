<?php
if (!defined('ABSPATH')) {
    exit;
}

class WP_Cache_Benchmark_Engine {
    
    private $resource_monitor;
    private $profile_manager;
    private $query_tracker;
    private $logger;
    private $result_id;
    private $metrics = array();
    private $should_stop = false;
    
    const DURATION_QUICK = 'quick';
    const DURATION_2MIN = '2min';
    const DURATION_5MIN = '5min';
    const DURATION_UNTIL_STOP = 'until_stop';
    
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
        $this->logger = WP_Cache_Benchmark_Logger::instance();
    }
    
    public function get_duration_configs() {
        return $this->duration_configs;
    }
    
    public function run($profile_id = null, $duration = 'quick', $name = null, $options = array()) {
        $config = isset($this->duration_configs[$duration]) ? $this->duration_configs[$duration] : $this->duration_configs['quick'];
        
        $this->logger->reset();
        $this->query_tracker->reset();
        $this->query_tracker->start_tracking();
        $this->should_stop = false;
        
        $this->logger->log('Benchmark started with duration: ' . $config['label'], 'info');
        
        $profile = null;
        if ($profile_id) {
            $profile = $this->profile_manager->get_profile($profile_id);
            if ($profile && !empty($profile->plugins)) {
                $this->profile_manager->activate_profile_plugins($profile->plugins);
                $this->logger->log('Activated profile plugins: ' . $profile->name, 'info');
            }
        }
        
        $result_name = $name ?: ($profile ? $profile->name . ' Benchmark' : 'Benchmark - ' . $config['label']);
        
        $this->result_id = WP_Cache_Benchmark_Database::save_result(array(
            'profile_id' => $profile_id,
            'test_type' => 'standard',
            'name' => $result_name,
            'status' => 'running',
            'iterations' => $config['iterations']
        ));
        
        $this->metrics = array();
        $response_times = array();
        $memory_usages = array();
        $db_queries = array();
        $cache_stats = array('hits' => 0, 'misses' => 0);
        
        $this->resource_monitor->start();
        $start_time = time();
        $max_end_time = $start_time + $config['max_time'];
        
        $this->logger->log_phase_start('Benchmark Iterations');
        
        $iterations = $config['iterations'];
        $i = 0;
        
        while ($i < $iterations && time() < $max_end_time && !$this->should_stop) {
            $i++;
            
            $iteration_start = microtime(true);
            $iteration_data = $this->run_iteration($i);
            $this->metrics[] = $iteration_data;
            
            $response_times[] = $iteration_data['response_time'];
            $memory_usages[] = $iteration_data['memory_usage'];
            $db_queries[] = $iteration_data['db_queries'];
            $cache_stats['hits'] += $iteration_data['cache_hits'];
            $cache_stats['misses'] += $iteration_data['cache_misses'];
            
            if ($this->logger->is_slow('query', $iteration_data['response_time'])) {
                $this->logger->log(
                    sprintf('Slow iteration #%d: %.2fms', $i, $iteration_data['response_time']),
                    'slow',
                    $iteration_data
                );
            }
            
            WP_Cache_Benchmark_Database::save_metric(array(
                'result_id' => $this->result_id,
                'iteration' => $i,
                'response_time' => $iteration_data['response_time'],
                'memory_usage' => $iteration_data['memory_usage'],
                'db_queries' => $iteration_data['db_queries'],
                'cpu_usage' => $iteration_data['cpu_usage'],
                'ram_usage' => $iteration_data['ram_usage'],
                'disk_read' => $iteration_data['disk_read'],
                'disk_write' => $iteration_data['disk_write'],
                'cache_hits' => $iteration_data['cache_hits'],
                'cache_misses' => $iteration_data['cache_misses']
            ));
            
            $this->logger->heartbeat($i, $iterations, array(
                'response_time' => $iteration_data['response_time'],
                'memory' => size_format($iteration_data['memory_usage']),
                'queries' => $iteration_data['db_queries']
            ));
            
            usleep(50000);
        }
        
        $phase_duration = (microtime(true) - $start_time) * 1000;
        $this->logger->log_phase_end('Benchmark Iterations', $phase_duration, array(
            'iterations_completed' => $i,
            'avg_response_time' => array_sum($response_times) / count($response_times)
        ));
        
        if (!empty($options['create_posts']) && $config['posts'] > 0) {
            $this->run_post_creation_test($config['posts'], $max_end_time);
        }
        
        if (!empty($options['read_api']) && $config['api_reads'] > 0) {
            $this->run_api_read_test($config['api_reads'], $max_end_time);
        }
        
        if (!empty($options['reload_options']) && $config['option_reloads'] > 0) {
            $this->run_option_reload_test($config['option_reloads'], $max_end_time);
        }
        
        if (!empty($options['simulate_cron']) && $config['cron_writes'] > 0) {
            $this->run_cron_simulation_test($config['cron_writes'], $max_end_time);
        }
        
        $this->resource_monitor->stop();
        $this->query_tracker->stop_tracking();
        
        $total_cache = $cache_stats['hits'] + $cache_stats['misses'];
        $cache_hit_rate = $total_cache > 0 ? ($cache_stats['hits'] / $total_cache) * 100 : 0;
        
        $resource_summary = $this->resource_monitor->get_summary();
        $query_stats = $this->query_tracker->get_query_stats();
        
        $avg_response = count($response_times) > 0 ? array_sum($response_times) / count($response_times) : 0;
        $avg_memory = count($memory_usages) > 0 ? array_sum($memory_usages) / count($memory_usages) : 0;
        $avg_queries = count($db_queries) > 0 ? array_sum($db_queries) / count($db_queries) : 0;
        
        $report_generator = new WP_Cache_Benchmark_Report_Generator(
            (object) array(
                'avg_response_time' => $avg_response,
                'min_response_time' => count($response_times) > 0 ? min($response_times) : 0,
                'max_response_time' => count($response_times) > 0 ? max($response_times) : 0,
                'avg_memory_usage' => $avg_memory,
                'peak_memory_usage' => count($memory_usages) > 0 ? max($memory_usages) : 0,
                'avg_db_queries' => $avg_queries,
                'cache_hit_rate' => $cache_hit_rate,
                'iterations' => count($response_times)
            ),
            $this->metrics,
            $query_stats,
            $this->logger->get_logs()
        );
        
        $report = $report_generator->generate();
        
        WP_Cache_Benchmark_Database::update_result($this->result_id, array(
            'status' => 'completed',
            'avg_response_time' => $avg_response,
            'min_response_time' => count($response_times) > 0 ? min($response_times) : 0,
            'max_response_time' => count($response_times) > 0 ? max($response_times) : 0,
            'avg_memory_usage' => $avg_memory,
            'peak_memory_usage' => count($memory_usages) > 0 ? max($memory_usages) : 0,
            'avg_db_queries' => $avg_queries,
            'total_db_queries' => array_sum($db_queries),
            'cache_hits' => $cache_stats['hits'],
            'cache_misses' => $cache_stats['misses'],
            'cache_hit_rate' => $cache_hit_rate,
            'avg_cpu_usage' => $resource_summary['avg_cpu'],
            'avg_disk_io' => $resource_summary['avg_disk_io'],
            'raw_data' => maybe_serialize(array(
                'metrics' => $this->metrics,
                'resource_timeline' => $this->resource_monitor->get_timeline(),
                'query_stats' => $query_stats,
                'logs' => $this->logger->export_logs(),
                'report' => $report,
                'duration_config' => $config
            )),
            'completed_at' => current_time('mysql')
        ));
        
        if ($profile_id) {
            $this->profile_manager->restore_original_state();
        }
        
        $this->logger->log('Benchmark completed successfully', 'success');
        
        return array(
            'result_id' => $this->result_id,
            'report' => $report,
            'logs' => $this->logger->get_logs(),
            'query_stats' => $query_stats
        );
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
        
        $start = microtime(true);
        $wpdb->get_results("SELECT * FROM {$wpdb->options} LIMIT 100");
        $duration = (microtime(true) - $start) * 1000;
        $this->query_tracker->log_query("SELECT * FROM {$wpdb->options} LIMIT 100", $duration, debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5));
        
        $options = array('siteurl', 'blogname', 'blogdescription', 'admin_email', 'posts_per_page',
            'date_format', 'time_format', 'timezone_string', 'active_plugins', 'template', 'stylesheet');
        
        foreach ($options as $option) {
            $start = microtime(true);
            get_option($option);
            $duration = (microtime(true) - $start) * 1000;
            if ($duration > 5) {
                $this->logger->log_cache_operation('get_option', $option, $duration < 1, $duration);
            }
        }
        
        $start = microtime(true);
        $posts = get_posts(array(
            'numberposts' => 10,
            'post_type' => 'post',
            'post_status' => 'publish'
        ));
        $duration = (microtime(true) - $start) * 1000;
        
        if ($this->logger->is_slow('query', $duration)) {
            $this->logger->log(sprintf('[SLOW] get_posts took %.2fms', $duration), 'slow');
        }
        
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
        
        $sidebar_widgets = get_option('sidebars_widgets');
        
        wp_get_nav_menu_items(get_nav_menu_locations());
    }
    
    private function run_post_creation_test($post_count, $max_end_time) {
        $this->logger->log_phase_start('Post Creation Test');
        $phase_start = microtime(true);
        
        $created_ids = array();
        $slow_creations = 0;
        $total_creation_time = 0;
        
        for ($i = 0; $i < $post_count && time() < $max_end_time && !$this->should_stop; $i++) {
            $post_start = microtime(true);
            
            $post_id = wp_insert_post(array(
                'post_title' => 'Benchmark Test Post ' . ($i + 1) . ' - ' . wp_generate_uuid4(),
                'post_content' => $this->generate_random_content(),
                'post_status' => 'publish',
                'post_type' => 'post',
                'post_author' => get_current_user_id() ?: 1
            ));
            
            $duration = (microtime(true) - $post_start) * 1000;
            $total_creation_time += $duration;
            
            if ($post_id && !is_wp_error($post_id)) {
                $created_ids[] = $post_id;
                
                for ($m = 1; $m <= 5; $m++) {
                    update_post_meta($post_id, 'benchmark_meta_' . $m, wp_generate_uuid4());
                }
                
                $is_slow = $this->logger->is_slow('post_creation', $duration);
                if ($is_slow) {
                    $slow_creations++;
                }
                
                $this->logger->log_post_creation($post_id, $duration, $is_slow);
            }
            
            if ($i % 10 === 0) {
                $this->logger->heartbeat($i, $post_count, array(
                    'posts_created' => count($created_ids),
                    'slow_creations' => $slow_creations
                ));
            }
        }
        
        $phase_duration = (microtime(true) - $phase_start) * 1000;
        $this->logger->log_phase_end('Post Creation Test', $phase_duration, array(
            'posts_created' => count($created_ids),
            'slow_creations' => $slow_creations,
            'avg_creation_time' => count($created_ids) > 0 ? $total_creation_time / count($created_ids) : 0
        ));
        
        $this->logger->log_phase_start('Cleanup');
        $cleanup_start = microtime(true);
        
        foreach ($created_ids as $post_id) {
            wp_delete_post($post_id, true);
        }
        
        $cleanup_duration = (microtime(true) - $cleanup_start) * 1000;
        $this->logger->log_phase_end('Cleanup', $cleanup_duration, array(
            'posts_deleted' => count($created_ids)
        ));
    }
    
    private function run_api_read_test($read_count, $max_end_time) {
        $this->logger->log_phase_start('API Read Test');
        $phase_start = microtime(true);
        
        $slow_reads = 0;
        $total_read_time = 0;
        $successful_reads = 0;
        
        $posts = get_posts(array(
            'numberposts' => min(100, $read_count),
            'post_type' => 'post',
            'post_status' => 'publish'
        ));
        
        if (empty($posts)) {
            $this->logger->log('No posts available for API read test, creating temporary posts', 'warning');
            $temp_posts = array();
            for ($i = 0; $i < 10 && time() < $max_end_time && !$this->should_stop; $i++) {
                $post_id = wp_insert_post(array(
                    'post_title' => 'Temp API Test Post ' . ($i + 1),
                    'post_content' => 'Test content for API reading.',
                    'post_status' => 'publish',
                    'post_type' => 'post'
                ));
                if ($post_id && !is_wp_error($post_id)) {
                    $temp_posts[] = $post_id;
                }
            }
            $posts = get_posts(array('include' => $temp_posts, 'post_type' => 'post'));
        }
        
        for ($i = 0; $i < $read_count && time() < $max_end_time && !$this->should_stop; $i++) {
            $post = $posts[$i % count($posts)];
            
            $read_start = microtime(true);
            
            $post_data = get_post($post->ID);
            $meta = get_post_meta($post->ID);
            $author = get_the_author_meta('display_name', $post->post_author);
            $categories = get_the_category($post->ID);
            $tags = get_the_tags($post->ID);
            $comments = get_comments(array('post_id' => $post->ID, 'number' => 5));
            
            $duration = (microtime(true) - $read_start) * 1000;
            $total_read_time += $duration;
            $successful_reads++;
            
            $is_slow = $this->logger->is_slow('query', $duration);
            if ($is_slow) {
                $slow_reads++;
                $this->logger->log(sprintf('[SLOW] API read for post #%d took %.2fms', $post->ID, $duration), 'slow');
            }
            
            if ($i % 25 === 0) {
                $this->logger->heartbeat($i, $read_count, array(
                    'reads_completed' => $successful_reads,
                    'slow_reads' => $slow_reads
                ));
            }
        }
        
        if (!empty($temp_posts)) {
            foreach ($temp_posts as $post_id) {
                wp_delete_post($post_id, true);
            }
        }
        
        $phase_duration = (microtime(true) - $phase_start) * 1000;
        $this->logger->log_phase_end('API Read Test', $phase_duration, array(
            'reads_completed' => $successful_reads,
            'slow_reads' => $slow_reads,
            'avg_read_time' => $successful_reads > 0 ? $total_read_time / $successful_reads : 0
        ));
    }
    
    private function run_option_reload_test($reload_count, $max_end_time) {
        $this->logger->log_phase_start('Option Reload Test');
        $phase_start = microtime(true);
        
        $slow_reloads = 0;
        $total_reload_time = 0;
        $cache_flushes = 0;
        
        $options_to_reload = array(
            'siteurl', 'blogname', 'blogdescription', 'admin_email', 'posts_per_page',
            'date_format', 'time_format', 'timezone_string', 'active_plugins', 'template',
            'stylesheet', 'sidebars_widgets', 'widget_block', 'theme_mods_' . get_option('stylesheet'),
            'rewrite_rules', 'cron', 'wp_user_roles', 'users_can_register', 'show_on_front'
        );
        
        for ($i = 0; $i < $reload_count && time() < $max_end_time && !$this->should_stop; $i++) {
            $reload_start = microtime(true);
            
            wp_cache_delete('alloptions', 'options');
            $cache_flushes++;
            
            foreach ($options_to_reload as $option_name) {
                wp_cache_delete($option_name, 'options');
                get_option($option_name);
            }
            
            $duration = (microtime(true) - $reload_start) * 1000;
            $total_reload_time += $duration;
            
            $is_slow = $this->logger->is_slow('query', $duration);
            if ($is_slow) {
                $slow_reloads++;
                $this->logger->log(sprintf('[SLOW] Option reload cycle #%d took %.2fms', $i + 1, $duration), 'slow');
            }
            
            if ($i % 10 === 0) {
                $this->logger->heartbeat($i, $reload_count, array(
                    'reloads_completed' => $i + 1,
                    'cache_flushes' => $cache_flushes,
                    'slow_reloads' => $slow_reloads
                ));
            }
            
            usleep(10000);
        }
        
        $phase_duration = (microtime(true) - $phase_start) * 1000;
        $this->logger->log_phase_end('Option Reload Test', $phase_duration, array(
            'reloads_completed' => $reload_count,
            'cache_flushes' => $cache_flushes,
            'slow_reloads' => $slow_reloads,
            'avg_reload_time' => $reload_count > 0 ? $total_reload_time / $reload_count : 0
        ));
    }
    
    private function run_cron_simulation_test($write_count, $max_end_time) {
        $this->logger->log_phase_start('Cron Simulation Test');
        $phase_start = microtime(true);
        
        $slow_writes = 0;
        $total_write_time = 0;
        $total_bytes_written = 0;
        $files_created = array();
        
        $upload_dir = wp_upload_dir();
        $test_dir = $upload_dir['basedir'] . '/benchmark-cron-test';
        
        if (!file_exists($test_dir)) {
            wp_mkdir_p($test_dir);
        }
        
        $file_size = 1024 * 1024;
        $random_content = str_repeat(wp_generate_uuid4(), $file_size / 36 + 1);
        $random_content = substr($random_content, 0, $file_size);
        
        for ($i = 0; $i < $write_count && time() < $max_end_time && !$this->should_stop; $i++) {
            $write_start = microtime(true);
            
            $filename = $test_dir . '/cron-test-' . $i . '-' . time() . '.tmp';
            
            $bytes_written = file_put_contents($filename, $random_content);
            
            if ($bytes_written !== false) {
                $files_created[] = $filename;
                $total_bytes_written += $bytes_written;
            }
            
            $duration = (microtime(true) - $write_start) * 1000;
            $total_write_time += $duration;
            
            $is_slow = $duration > 100;
            if ($is_slow) {
                $slow_writes++;
                $this->logger->log(sprintf('[SLOW] Cron file write #%d took %.2fms (1MB)', $i + 1, $duration), 'slow');
            }
            
            if ($i % 5 === 0) {
                $this->logger->heartbeat($i, $write_count, array(
                    'files_written' => count($files_created),
                    'bytes_written' => size_format($total_bytes_written),
                    'slow_writes' => $slow_writes
                ));
            }
        }
        
        $this->logger->log_phase_start('Cron Cleanup');
        $cleanup_start = microtime(true);
        
        foreach ($files_created as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
        
        if (file_exists($test_dir) && is_dir($test_dir)) {
            $remaining_files = glob($test_dir . '/*');
            if (empty($remaining_files)) {
                rmdir($test_dir);
            }
        }
        
        $cleanup_duration = (microtime(true) - $cleanup_start) * 1000;
        $this->logger->log_phase_end('Cron Cleanup', $cleanup_duration, array(
            'files_deleted' => count($files_created)
        ));
        
        $phase_duration = (microtime(true) - $phase_start) * 1000;
        $this->logger->log_phase_end('Cron Simulation Test', $phase_duration, array(
            'files_written' => count($files_created),
            'total_bytes' => size_format($total_bytes_written),
            'slow_writes' => $slow_writes,
            'avg_write_time' => count($files_created) > 0 ? $total_write_time / count($files_created) : 0
        ));
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
    
    public function stop() {
        $this->should_stop = true;
    }
    
    public function get_result() {
        return WP_Cache_Benchmark_Database::get_result($this->result_id);
    }
    
    public function get_metrics() {
        return $this->metrics;
    }
    
    public function get_logs() {
        return $this->logger->get_logs();
    }
    
    public function get_query_stats() {
        return $this->query_tracker->get_query_stats();
    }
}
