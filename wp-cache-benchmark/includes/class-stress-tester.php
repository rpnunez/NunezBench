<?php
if (!defined('ABSPATH')) {
    exit;
}

class WP_Cache_Benchmark_Stress_Tester {
    
    private $resource_monitor;
    private $profile_manager;
    private $result_id;
    private $test_post_ids = array();
    private $test_files = array();
    private $cleanup_scheduled = false;
    
    const TEST_POST_COUNT = 100;
    const CRON_FILE_SIZE = 1048576;
    const CRON_INTERVAL = 1;
    
    public function __construct() {
        $this->resource_monitor = new WP_Cache_Benchmark_Resource_Monitor();
        $this->profile_manager = new WP_Cache_Benchmark_Profile_Manager();
    }
    
    public function run($profile_id = null, $options = array()) {
        $defaults = array(
            'create_posts' => true,
            'read_api' => true,
            'reload_options' => true,
            'simulate_cron' => true,
            'duration' => 60,
            'name' => null
        );
        
        $options = wp_parse_args($options, $defaults);
        
        $profile = null;
        if ($profile_id) {
            $profile = $this->profile_manager->get_profile($profile_id);
            if ($profile && !empty($profile->plugins)) {
                $this->profile_manager->activate_profile_plugins($profile->plugins);
            }
        }
        
        $result_name = $options['name'] ?: ($profile ? $profile->name . ' Stress Test' : 'Stress Test');
        
        $this->result_id = WP_Cache_Benchmark_Database::save_result(array(
            'profile_id' => $profile_id,
            'test_type' => 'stress',
            'name' => $result_name,
            'status' => 'running',
            'iterations' => $options['duration']
        ));
        
        $this->resource_monitor->start();
        
        $metrics = array();
        $start_time = time();
        $iteration = 0;
        
        if ($options['create_posts']) {
            $create_metrics = $this->test_create_posts();
            $metrics['post_creation'] = $create_metrics;
            
            WP_Cache_Benchmark_Database::save_metric(array(
                'result_id' => $this->result_id,
                'iteration' => ++$iteration,
                'response_time' => $create_metrics['total_time'],
                'memory_usage' => $create_metrics['peak_memory'],
                'db_queries' => $create_metrics['total_queries']
            ));
        }
        
        if ($options['read_api'] && !empty($this->test_post_ids)) {
            $api_metrics = $this->test_read_api();
            $metrics['api_reads'] = $api_metrics;
            
            WP_Cache_Benchmark_Database::save_metric(array(
                'result_id' => $this->result_id,
                'iteration' => ++$iteration,
                'response_time' => $api_metrics['total_time'],
                'memory_usage' => $api_metrics['peak_memory'],
                'db_queries' => $api_metrics['total_queries']
            ));
        }
        
        if ($options['reload_options']) {
            $options_metrics = $this->test_reload_options();
            $metrics['options_reload'] = $options_metrics;
            
            WP_Cache_Benchmark_Database::save_metric(array(
                'result_id' => $this->result_id,
                'iteration' => ++$iteration,
                'response_time' => $options_metrics['total_time'],
                'memory_usage' => $options_metrics['peak_memory'],
                'db_queries' => $options_metrics['total_queries'],
                'cache_hits' => $options_metrics['cache_hits'],
                'cache_misses' => $options_metrics['cache_misses']
            ));
        }
        
        if ($options['simulate_cron']) {
            $remaining_time = max(10, $options['duration'] - (time() - $start_time));
            $cron_metrics = $this->test_simulate_cron($remaining_time);
            $metrics['cron_simulation'] = $cron_metrics;
            
            WP_Cache_Benchmark_Database::save_metric(array(
                'result_id' => $this->result_id,
                'iteration' => ++$iteration,
                'response_time' => $cron_metrics['total_time'],
                'memory_usage' => $cron_metrics['peak_memory'],
                'disk_write' => $cron_metrics['total_bytes_written']
            ));
        }
        
        $this->resource_monitor->stop();
        
        $resource_summary = $this->resource_monitor->get_summary();
        
        $total_time = 0;
        $peak_memory = 0;
        $total_queries = 0;
        
        foreach ($metrics as $test => $data) {
            $total_time += $data['total_time'];
            $peak_memory = max($peak_memory, $data['peak_memory']);
            if (isset($data['total_queries'])) {
                $total_queries += $data['total_queries'];
            }
        }
        
        WP_Cache_Benchmark_Database::update_result($this->result_id, array(
            'status' => 'completed',
            'avg_response_time' => $total_time,
            'peak_memory_usage' => $peak_memory,
            'total_db_queries' => $total_queries,
            'avg_cpu_usage' => $resource_summary['avg_cpu'],
            'avg_disk_io' => $resource_summary['avg_disk_io'],
            'raw_data' => maybe_serialize(array(
                'metrics' => $metrics,
                'resource_timeline' => $this->resource_monitor->get_timeline(),
                'test_options' => $options
            )),
            'completed_at' => current_time('mysql')
        ));
        
        $this->cleanup();
        
        if ($profile_id) {
            $this->profile_manager->restore_original_state();
        }
        
        return $this->result_id;
    }
    
    private function test_create_posts() {
        global $wpdb;
        
        $start_time = microtime(true);
        $queries_before = $wpdb->num_queries;
        $memory_before = memory_get_usage(true);
        $peak_memory = $memory_before;
        
        $post_times = array();
        
        for ($i = 0; $i < self::TEST_POST_COUNT; $i++) {
            $post_start = microtime(true);
            
            $post_id = wp_insert_post(array(
                'post_title' => 'Benchmark Test Post ' . ($i + 1) . ' - ' . wp_generate_uuid4(),
                'post_content' => $this->generate_random_content(),
                'post_status' => 'publish',
                'post_type' => 'post',
                'post_author' => get_current_user_id() ?: 1
            ));
            
            if ($post_id && !is_wp_error($post_id)) {
                $this->test_post_ids[] = $post_id;
                
                for ($m = 1; $m <= 10; $m++) {
                    update_post_meta($post_id, 'benchmark_meta_' . $m, wp_generate_uuid4());
                }
                
                $categories = get_terms(array('taxonomy' => 'category', 'fields' => 'ids', 'number' => 3, 'hide_empty' => false));
                if (!is_wp_error($categories) && !empty($categories)) {
                    wp_set_post_categories($post_id, $categories);
                }
            }
            
            $post_times[] = (microtime(true) - $post_start) * 1000;
            $peak_memory = max($peak_memory, memory_get_usage(true));
        }
        
        $total_time = (microtime(true) - $start_time) * 1000;
        
        return array(
            'posts_created' => count($this->test_post_ids),
            'total_time' => $total_time,
            'avg_post_time' => array_sum($post_times) / count($post_times),
            'min_post_time' => min($post_times),
            'max_post_time' => max($post_times),
            'total_queries' => $wpdb->num_queries - $queries_before,
            'peak_memory' => $peak_memory,
            'memory_delta' => $peak_memory - $memory_before
        );
    }
    
    private function test_read_api() {
        global $wpdb;
        
        $start_time = microtime(true);
        $queries_before = $wpdb->num_queries;
        $peak_memory = memory_get_usage(true);
        
        $read_times = array();
        
        foreach ($this->test_post_ids as $post_id) {
            $read_start = microtime(true);
            
            $post = get_post($post_id);
            $meta = get_post_meta($post_id);
            $categories = wp_get_post_categories($post_id);
            $author = get_the_author_meta('display_name', $post->post_author);
            
            $read_times[] = (microtime(true) - $read_start) * 1000;
            $peak_memory = max($peak_memory, memory_get_usage(true));
        }
        
        $total_time = (microtime(true) - $start_time) * 1000;
        
        return array(
            'posts_read' => count($this->test_post_ids),
            'total_time' => $total_time,
            'avg_read_time' => array_sum($read_times) / count($read_times),
            'min_read_time' => min($read_times),
            'max_read_time' => max($read_times),
            'total_queries' => $wpdb->num_queries - $queries_before,
            'peak_memory' => $peak_memory
        );
    }
    
    private function test_reload_options() {
        global $wpdb;
        
        $start_time = microtime(true);
        $queries_before = $wpdb->num_queries;
        $peak_memory = memory_get_usage(true);
        
        $iterations = 50;
        $reload_times = array();
        $cache_hits = 0;
        $cache_misses = 0;
        
        $important_options = array(
            'siteurl', 'home', 'blogname', 'blogdescription', 'admin_email',
            'users_can_register', 'posts_per_page', 'date_format', 'time_format',
            'start_of_week', 'timezone_string', 'active_plugins', 'template',
            'stylesheet', 'sidebars_widgets', 'widget_text', 'widget_categories'
        );
        
        for ($i = 0; $i < $iterations; $i++) {
            wp_cache_flush();
            
            $reload_start = microtime(true);
            
            foreach ($important_options as $option) {
                $value = get_option($option);
                
                if (wp_cache_get($option, 'options')) {
                    $cache_hits++;
                } else {
                    $cache_misses++;
                }
            }
            
            $reload_times[] = (microtime(true) - $reload_start) * 1000;
            $peak_memory = max($peak_memory, memory_get_usage(true));
        }
        
        $total_time = (microtime(true) - $start_time) * 1000;
        
        return array(
            'iterations' => $iterations,
            'options_per_iteration' => count($important_options),
            'total_time' => $total_time,
            'avg_reload_time' => array_sum($reload_times) / count($reload_times),
            'min_reload_time' => min($reload_times),
            'max_reload_time' => max($reload_times),
            'total_queries' => $wpdb->num_queries - $queries_before,
            'peak_memory' => $peak_memory,
            'cache_hits' => $cache_hits,
            'cache_misses' => $cache_misses
        );
    }
    
    private function test_simulate_cron($duration = 10) {
        $start_time = microtime(true);
        $peak_memory = memory_get_usage(true);
        
        $files_written = 0;
        $total_bytes = 0;
        $write_times = array();
        
        $upload_dir = wp_upload_dir();
        $test_dir = $upload_dir['basedir'] . '/cache-benchmark-test/';
        
        if (!file_exists($test_dir)) {
            wp_mkdir_p($test_dir);
        }
        
        $end_time = time() + $duration;
        
        while (time() < $end_time) {
            $write_start = microtime(true);
            
            $file_path = $test_dir . 'cron_test_' . time() . '_' . wp_generate_uuid4() . '.tmp';
            $data = str_repeat(chr(rand(65, 90)), self::CRON_FILE_SIZE);
            
            $bytes_written = file_put_contents($file_path, $data);
            
            if ($bytes_written) {
                $this->test_files[] = $file_path;
                $files_written++;
                $total_bytes += $bytes_written;
            }
            
            $write_times[] = (microtime(true) - $write_start) * 1000;
            $peak_memory = max($peak_memory, memory_get_usage(true));
            
            sleep(self::CRON_INTERVAL);
        }
        
        $total_time = (microtime(true) - $start_time) * 1000;
        
        return array(
            'duration' => $duration,
            'files_written' => $files_written,
            'total_bytes_written' => $total_bytes,
            'total_time' => $total_time,
            'avg_write_time' => count($write_times) > 0 ? array_sum($write_times) / count($write_times) : 0,
            'peak_memory' => $peak_memory
        );
    }
    
    private function generate_random_content() {
        $paragraphs = rand(3, 7);
        $content = '';
        
        $words = array(
            'lorem', 'ipsum', 'dolor', 'sit', 'amet', 'consectetur', 'adipiscing',
            'elit', 'sed', 'do', 'eiusmod', 'tempor', 'incididunt', 'ut', 'labore',
            'et', 'dolore', 'magna', 'aliqua', 'enim', 'ad', 'minim', 'veniam',
            'quis', 'nostrud', 'exercitation', 'ullamco', 'laboris', 'nisi', 'aliquip',
            'ex', 'ea', 'commodo', 'consequat', 'duis', 'aute', 'irure', 'in',
            'reprehenderit', 'voluptate', 'velit', 'esse', 'cillum', 'fugiat', 'nulla',
            'pariatur', 'excepteur', 'sint', 'occaecat', 'cupidatat', 'non', 'proident',
            'sunt', 'culpa', 'qui', 'officia', 'deserunt', 'mollit', 'anim', 'id', 'est'
        );
        
        for ($p = 0; $p < $paragraphs; $p++) {
            $sentences = rand(4, 8);
            $paragraph = '';
            
            for ($s = 0; $s < $sentences; $s++) {
                $word_count = rand(8, 20);
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
    
    public function cleanup() {
        foreach ($this->test_post_ids as $post_id) {
            wp_delete_post($post_id, true);
        }
        $this->test_post_ids = array();
        
        foreach ($this->test_files as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
        $this->test_files = array();
        
        $upload_dir = wp_upload_dir();
        $test_dir = $upload_dir['basedir'] . '/cache-benchmark-test/';
        
        if (is_dir($test_dir)) {
            $files = glob($test_dir . '*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($test_dir);
        }
        
        wp_cache_flush();
    }
    
    public function get_result() {
        return WP_Cache_Benchmark_Database::get_result($this->result_id);
    }
}
