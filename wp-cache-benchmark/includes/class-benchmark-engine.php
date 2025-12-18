<?php
if (!defined('ABSPATH')) {
    exit;
}

class WP_Cache_Benchmark_Engine {
    
    private $resource_monitor;
    private $profile_manager;
    private $result_id;
    private $metrics = array();
    
    public function __construct() {
        $this->resource_monitor = new WP_Cache_Benchmark_Resource_Monitor();
        $this->profile_manager = new WP_Cache_Benchmark_Profile_Manager();
    }
    
    public function run($profile_id = null, $iterations = 10, $name = null) {
        $profile = null;
        if ($profile_id) {
            $profile = $this->profile_manager->get_profile($profile_id);
            if ($profile && !empty($profile->plugins)) {
                $this->profile_manager->activate_profile_plugins($profile->plugins);
            }
        }
        
        $result_name = $name ?: ($profile ? $profile->name . ' Benchmark' : 'Quick Benchmark');
        
        $this->result_id = WP_Cache_Benchmark_Database::save_result(array(
            'profile_id' => $profile_id,
            'test_type' => 'standard',
            'name' => $result_name,
            'status' => 'running',
            'iterations' => $iterations
        ));
        
        $this->metrics = array();
        $response_times = array();
        $memory_usages = array();
        $db_queries = array();
        $cache_stats = array('hits' => 0, 'misses' => 0);
        
        $this->resource_monitor->start();
        
        for ($i = 1; $i <= $iterations; $i++) {
            $iteration_data = $this->run_iteration($i);
            $this->metrics[] = $iteration_data;
            
            $response_times[] = $iteration_data['response_time'];
            $memory_usages[] = $iteration_data['memory_usage'];
            $db_queries[] = $iteration_data['db_queries'];
            $cache_stats['hits'] += $iteration_data['cache_hits'];
            $cache_stats['misses'] += $iteration_data['cache_misses'];
            
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
            
            usleep(100000);
        }
        
        $this->resource_monitor->stop();
        
        $total_cache = $cache_stats['hits'] + $cache_stats['misses'];
        $cache_hit_rate = $total_cache > 0 ? ($cache_stats['hits'] / $total_cache) * 100 : 0;
        
        $resource_summary = $this->resource_monitor->get_summary();
        
        WP_Cache_Benchmark_Database::update_result($this->result_id, array(
            'status' => 'completed',
            'avg_response_time' => array_sum($response_times) / count($response_times),
            'min_response_time' => min($response_times),
            'max_response_time' => max($response_times),
            'avg_memory_usage' => array_sum($memory_usages) / count($memory_usages),
            'peak_memory_usage' => max($memory_usages),
            'avg_db_queries' => array_sum($db_queries) / count($db_queries),
            'total_db_queries' => array_sum($db_queries),
            'cache_hits' => $cache_stats['hits'],
            'cache_misses' => $cache_stats['misses'],
            'cache_hit_rate' => $cache_hit_rate,
            'avg_cpu_usage' => $resource_summary['avg_cpu'],
            'avg_disk_io' => $resource_summary['avg_disk_io'],
            'raw_data' => maybe_serialize(array(
                'metrics' => $this->metrics,
                'resource_timeline' => $this->resource_monitor->get_timeline()
            )),
            'completed_at' => current_time('mysql')
        ));
        
        if ($profile_id) {
            $this->profile_manager->restore_original_state();
        }
        
        return $this->result_id;
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
        
        $wpdb->get_results("SELECT * FROM {$wpdb->options} LIMIT 100");
        
        get_option('siteurl');
        get_option('blogname');
        get_option('blogdescription');
        get_option('admin_email');
        get_option('posts_per_page');
        get_option('date_format');
        get_option('time_format');
        get_option('timezone_string');
        get_option('active_plugins');
        get_option('template');
        get_option('stylesheet');
        
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
        
        $sidebar_widgets = get_option('sidebars_widgets');
        
        wp_get_nav_menu_items(get_nav_menu_locations());
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
    
    public function get_result() {
        return WP_Cache_Benchmark_Database::get_result($this->result_id);
    }
    
    public function get_metrics() {
        return $this->metrics;
    }
}
