<?php
if (!defined('ABSPATH')) {
    exit;
}

class WP_Cache_Benchmark_Query_Tracker {
    
    private static $instance = null;
    private $queries = array();
    private $slow_query_threshold = 0.05;
    private $tracking_enabled = false;
    private $start_time;
    private $original_save_queries = false;
    private $queries_snapshot = 0;
    
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->start_time = microtime(true);
    }
    
    public function start_tracking() {
        global $wpdb;
        
        $this->tracking_enabled = true;
        $this->queries = array();
        $this->start_time = microtime(true);
        
        $this->original_save_queries = isset($wpdb->save_queries) ? $wpdb->save_queries : false;
        $wpdb->save_queries = true;
        
        $this->queries_snapshot = isset($wpdb->queries) ? count($wpdb->queries) : 0;
        
        add_filter('query', array($this, 'track_query_start'), 1);
    }
    
    public function stop_tracking() {
        global $wpdb;
        
        $this->tracking_enabled = false;
        remove_filter('query', array($this, 'track_query_start'), 1);
        
        $this->capture_wpdb_queries();
        
        if (isset($this->original_save_queries)) {
            $wpdb->save_queries = $this->original_save_queries;
        }
    }
    
    private function capture_wpdb_queries() {
        global $wpdb;
        
        if (!isset($wpdb->queries) || !is_array($wpdb->queries)) {
            return;
        }
        
        $queries_to_process = array_slice($wpdb->queries, $this->queries_snapshot);
        
        foreach ($queries_to_process as $query_data) {
            if (!is_array($query_data) || count($query_data) < 3) {
                continue;
            }
            
            list($query, $elapsed_time, $caller) = $query_data;
            
            $caller_info = $this->parse_caller_string($caller);
            
            $this->queries[] = array(
                'query' => $this->sanitize_query($query),
                'query_type' => $this->get_query_type($query),
                'duration' => $elapsed_time * 1000,
                'is_slow' => $elapsed_time >= $this->slow_query_threshold,
                'caller' => $caller_info,
                'timestamp' => microtime(true) - $this->start_time,
                'table' => $this->extract_table_name($query)
            );
        }
    }
    
    private function parse_caller_string($caller_string) {
        $caller = array(
            'plugin' => 'WordPress Core',
            'file' => '',
            'line' => 0,
            'function' => ''
        );
        
        if (empty($caller_string)) {
            return $caller;
        }
        
        $parts = explode(', ', $caller_string);
        
        foreach ($parts as $part) {
            if (preg_match('/([^(]+)\((\d+)\)/', $part, $matches)) {
                $caller['file'] = $matches[1];
                $caller['line'] = intval($matches[2]);
            } elseif (strpos($part, '->') !== false || strpos($part, '::') !== false) {
                $caller['function'] = $part;
            }
        }
        
        if (!empty($caller['file'])) {
            $plugins_dir = defined('WP_PLUGIN_DIR') ? WP_PLUGIN_DIR : ABSPATH . 'wp-content/plugins';
            $themes_dir = defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR . '/themes' : ABSPATH . 'wp-content/themes';
            
            if (strpos($caller['file'], $plugins_dir) !== false) {
                $relative_path = str_replace($plugins_dir . '/', '', $caller['file']);
                $parts = explode('/', $relative_path);
                $caller['plugin'] = $this->get_plugin_name($parts[0]);
            } elseif (strpos($caller['file'], $themes_dir) !== false) {
                $relative_path = str_replace($themes_dir . '/', '', $caller['file']);
                $parts = explode('/', $relative_path);
                $caller['plugin'] = 'Theme: ' . $parts[0];
            }
        }
        
        return $caller;
    }
    
    public function track_query_start($query) {
        return $query;
    }
    
    public function log_query($query, $duration, $backtrace = null) {
        if (!$this->tracking_enabled) {
            return;
        }
        
        if ($backtrace === null) {
            $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 15);
        }
        
        $caller_info = $this->parse_backtrace($backtrace);
        
        $this->queries[] = array(
            'query' => $this->sanitize_query($query),
            'query_type' => $this->get_query_type($query),
            'duration' => $duration,
            'is_slow' => $duration >= ($this->slow_query_threshold * 1000),
            'caller' => $caller_info,
            'timestamp' => microtime(true) - $this->start_time,
            'table' => $this->extract_table_name($query)
        );
    }
    
    private function parse_backtrace($backtrace) {
        $caller = array(
            'plugin' => 'WordPress Core',
            'file' => '',
            'line' => 0,
            'function' => ''
        );
        
        $plugins_dir = defined('WP_PLUGIN_DIR') ? WP_PLUGIN_DIR : ABSPATH . 'wp-content/plugins';
        $themes_dir = defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR . '/themes' : ABSPATH . 'wp-content/themes';
        
        foreach ($backtrace as $frame) {
            if (!isset($frame['file'])) {
                continue;
            }
            
            $file = $frame['file'];
            
            if (strpos($file, 'class-query-tracker.php') !== false) {
                continue;
            }
            if (strpos($file, 'class-benchmark-engine.php') !== false) {
                continue;
            }
            if (strpos($file, 'class-stress-tester.php') !== false) {
                continue;
            }
            
            if (strpos($file, $plugins_dir) !== false) {
                $relative_path = str_replace($plugins_dir . '/', '', $file);
                $parts = explode('/', $relative_path);
                $plugin_folder = $parts[0];
                
                $caller['plugin'] = $this->get_plugin_name($plugin_folder);
                $caller['file'] = $relative_path;
                $caller['line'] = isset($frame['line']) ? $frame['line'] : 0;
                $caller['function'] = isset($frame['function']) ? $frame['function'] : '';
                break;
            }
            
            if (strpos($file, $themes_dir) !== false) {
                $relative_path = str_replace($themes_dir . '/', '', $file);
                $parts = explode('/', $relative_path);
                $theme_folder = $parts[0];
                
                $caller['plugin'] = 'Theme: ' . $theme_folder;
                $caller['file'] = $relative_path;
                $caller['line'] = isset($frame['line']) ? $frame['line'] : 0;
                $caller['function'] = isset($frame['function']) ? $frame['function'] : '';
                break;
            }
            
            if (strpos($file, ABSPATH) !== false) {
                $relative_path = str_replace(ABSPATH, '', $file);
                
                if (strpos($relative_path, 'wp-includes') === 0 || strpos($relative_path, 'wp-admin') === 0) {
                    $caller['plugin'] = 'WordPress Core';
                    $caller['file'] = $relative_path;
                    $caller['line'] = isset($frame['line']) ? $frame['line'] : 0;
                    $caller['function'] = isset($frame['function']) ? $frame['function'] : '';
                }
            }
        }
        
        return $caller;
    }
    
    private function get_plugin_name($folder) {
        static $plugin_names = array();
        
        if (isset($plugin_names[$folder])) {
            return $plugin_names[$folder];
        }
        
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        
        $plugins = get_plugins();
        
        foreach ($plugins as $plugin_file => $plugin_data) {
            if (strpos($plugin_file, $folder . '/') === 0) {
                $plugin_names[$folder] = $plugin_data['Name'];
                return $plugin_data['Name'];
            }
        }
        
        $plugin_names[$folder] = ucwords(str_replace(array('-', '_'), ' ', $folder));
        return $plugin_names[$folder];
    }
    
    private function sanitize_query($query) {
        $query = preg_replace('/\s+/', ' ', trim($query));
        
        if (strlen($query) > 500) {
            $query = substr($query, 0, 500) . '...';
        }
        
        return $query;
    }
    
    private function get_query_type($query) {
        $query = strtoupper(trim($query));
        
        if (strpos($query, 'SELECT') === 0) return 'SELECT';
        if (strpos($query, 'INSERT') === 0) return 'INSERT';
        if (strpos($query, 'UPDATE') === 0) return 'UPDATE';
        if (strpos($query, 'DELETE') === 0) return 'DELETE';
        if (strpos($query, 'REPLACE') === 0) return 'REPLACE';
        if (strpos($query, 'CREATE') === 0) return 'CREATE';
        if (strpos($query, 'ALTER') === 0) return 'ALTER';
        if (strpos($query, 'DROP') === 0) return 'DROP';
        if (strpos($query, 'SHOW') === 0) return 'SHOW';
        if (strpos($query, 'DESCRIBE') === 0) return 'DESCRIBE';
        
        return 'OTHER';
    }
    
    private function extract_table_name($query) {
        $patterns = array(
            '/FROM\s+[`"\']?(\w+)[`"\']?/i',
            '/INTO\s+[`"\']?(\w+)[`"\']?/i',
            '/UPDATE\s+[`"\']?(\w+)[`"\']?/i',
            '/TABLE\s+[`"\']?(\w+)[`"\']?/i'
        );
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $query, $matches)) {
                return $matches[1];
            }
        }
        
        return 'unknown';
    }
    
    public function get_queries() {
        return $this->queries;
    }
    
    public function get_slow_queries() {
        return array_filter($this->queries, function($q) {
            return isset($q['is_slow']) && $q['is_slow'];
        });
    }
    
    public function get_query_stats() {
        $total_queries = count($this->queries);
        $slow_queries = count($this->get_slow_queries());
        $total_time = 0;
        $by_type = array();
        $by_plugin = array();
        $by_table = array();
        
        foreach ($this->queries as $query) {
            $duration = isset($query['duration']) ? $query['duration'] : 0;
            $total_time += $duration;
            
            $type = isset($query['query_type']) ? $query['query_type'] : 'OTHER';
            if (!isset($by_type[$type])) {
                $by_type[$type] = array('count' => 0, 'time' => 0);
            }
            $by_type[$type]['count']++;
            $by_type[$type]['time'] += $duration;
            
            $plugin = isset($query['caller']['plugin']) ? $query['caller']['plugin'] : 'Unknown';
            if (!isset($by_plugin[$plugin])) {
                $by_plugin[$plugin] = array('count' => 0, 'time' => 0, 'slow' => 0);
            }
            $by_plugin[$plugin]['count']++;
            $by_plugin[$plugin]['time'] += $duration;
            if (isset($query['is_slow']) && $query['is_slow']) {
                $by_plugin[$plugin]['slow']++;
            }
            
            $table = isset($query['table']) ? $query['table'] : 'unknown';
            if (!isset($by_table[$table])) {
                $by_table[$table] = array('count' => 0, 'time' => 0);
            }
            $by_table[$table]['count']++;
            $by_table[$table]['time'] += $duration;
        }
        
        arsort($by_plugin);
        arsort($by_table);
        
        return array(
            'total_queries' => $total_queries,
            'slow_queries' => $slow_queries,
            'total_time' => $total_time,
            'avg_time' => $total_queries > 0 ? $total_time / $total_queries : 0,
            'by_type' => $by_type,
            'by_plugin' => $by_plugin,
            'by_table' => $by_table
        );
    }
    
    public function get_top_slow_queries($limit = 10) {
        $queries = $this->queries;
        
        usort($queries, function($a, $b) {
            $a_duration = isset($a['duration']) ? $a['duration'] : 0;
            $b_duration = isset($b['duration']) ? $b['duration'] : 0;
            return $b_duration <=> $a_duration;
        });
        
        return array_slice($queries, 0, $limit);
    }
    
    public function reset() {
        $this->queries = array();
        $this->start_time = microtime(true);
    }
    
    public function set_slow_threshold($threshold_seconds) {
        $this->slow_query_threshold = $threshold_seconds;
    }
}
