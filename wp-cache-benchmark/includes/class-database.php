<?php
if (!defined('ABSPATH')) {
    exit;
}

class WP_Cache_Benchmark_Database {
    
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $profiles_table = $wpdb->prefix . 'cache_benchmark_profiles';
        $results_table = $wpdb->prefix . 'cache_benchmark_results';
        $metrics_table = $wpdb->prefix . 'cache_benchmark_metrics';
        
        $sql_profiles = "CREATE TABLE IF NOT EXISTS $profiles_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text,
            plugins longtext NOT NULL,
            settings longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        $sql_results = "CREATE TABLE IF NOT EXISTS $results_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            profile_id bigint(20) unsigned,
            test_type varchar(50) NOT NULL,
            name varchar(255) NOT NULL,
            status varchar(20) DEFAULT 'pending',
            iterations int DEFAULT 10,
            avg_response_time float,
            min_response_time float,
            max_response_time float,
            avg_memory_usage bigint,
            peak_memory_usage bigint,
            avg_db_queries int,
            total_db_queries int,
            cache_hits int DEFAULT 0,
            cache_misses int DEFAULT 0,
            cache_hit_rate float DEFAULT 0,
            avg_cpu_usage float,
            avg_disk_io float,
            raw_data longtext,
            started_at datetime,
            completed_at datetime,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY profile_id (profile_id),
            KEY test_type (test_type),
            KEY status (status)
        ) $charset_collate;";
        
        $sql_metrics = "CREATE TABLE IF NOT EXISTS $metrics_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            result_id bigint(20) unsigned NOT NULL,
            iteration int NOT NULL,
            timestamp datetime NOT NULL,
            response_time float,
            memory_usage bigint,
            db_queries int,
            cpu_usage float,
            ram_usage bigint,
            disk_read float,
            disk_write float,
            cache_hits int DEFAULT 0,
            cache_misses int DEFAULT 0,
            PRIMARY KEY (id),
            KEY result_id (result_id),
            KEY iteration (iteration)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_profiles);
        dbDelta($sql_results);
        dbDelta($sql_metrics);
    }
    
    public static function get_profiles() {
        global $wpdb;
        $table = $wpdb->prefix . 'cache_benchmark_profiles';
        return $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC");
    }
    
    public static function get_profile($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'cache_benchmark_profiles';
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
    }
    
    public static function save_profile($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'cache_benchmark_profiles';
        
        if (isset($data['id']) && $data['id'] > 0) {
            $wpdb->update(
                $table,
                array(
                    'name' => $data['name'],
                    'description' => $data['description'],
                    'plugins' => maybe_serialize($data['plugins']),
                    'settings' => maybe_serialize($data['settings'])
                ),
                array('id' => $data['id']),
                array('%s', '%s', '%s', '%s'),
                array('%d')
            );
            return $data['id'];
        } else {
            $wpdb->insert(
                $table,
                array(
                    'name' => $data['name'],
                    'description' => $data['description'],
                    'plugins' => maybe_serialize($data['plugins']),
                    'settings' => maybe_serialize($data['settings'])
                ),
                array('%s', '%s', '%s', '%s')
            );
            return $wpdb->insert_id;
        }
    }
    
    public static function delete_profile($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'cache_benchmark_profiles';
        return $wpdb->delete($table, array('id' => $id), array('%d'));
    }
    
    public static function get_results($args = array()) {
        global $wpdb;
        $table = $wpdb->prefix . 'cache_benchmark_results';
        
        $defaults = array(
            'limit' => 50,
            'offset' => 0,
            'status' => null,
            'test_type' => null,
            'profile_id' => null
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where = array('1=1');
        $values = array();
        
        if ($args['status']) {
            $where[] = 'status = %s';
            $values[] = $args['status'];
        }
        
        if ($args['test_type']) {
            $where[] = 'test_type = %s';
            $values[] = $args['test_type'];
        }
        
        if ($args['profile_id']) {
            $where[] = 'profile_id = %d';
            $values[] = $args['profile_id'];
        }
        
        $where_sql = implode(' AND ', $where);
        
        $sql = "SELECT * FROM $table WHERE $where_sql ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $values[] = $args['limit'];
        $values[] = $args['offset'];
        
        if (count($values) > 2) {
            return $wpdb->get_results($wpdb->prepare($sql, $values));
        }
        
        return $wpdb->get_results($wpdb->prepare($sql, $args['limit'], $args['offset']));
    }
    
    public static function get_result($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'cache_benchmark_results';
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
    }
    
    public static function save_result($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'cache_benchmark_results';
        
        $wpdb->insert(
            $table,
            array(
                'profile_id' => isset($data['profile_id']) ? $data['profile_id'] : null,
                'test_type' => $data['test_type'],
                'name' => $data['name'],
                'status' => isset($data['status']) ? $data['status'] : 'pending',
                'iterations' => isset($data['iterations']) ? $data['iterations'] : 10,
                'started_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%s', '%d', '%s')
        );
        
        return $wpdb->insert_id;
    }
    
    public static function update_result($id, $data) {
        global $wpdb;
        $table = $wpdb->prefix . 'cache_benchmark_results';
        
        $update_data = array();
        $format = array();
        
        $allowed_fields = array(
            'status', 'avg_response_time', 'min_response_time', 'max_response_time',
            'avg_memory_usage', 'peak_memory_usage', 'avg_db_queries', 'total_db_queries',
            'cache_hits', 'cache_misses', 'cache_hit_rate', 'avg_cpu_usage', 'avg_disk_io',
            'raw_data', 'completed_at'
        );
        
        foreach ($allowed_fields as $field) {
            if (isset($data[$field])) {
                $update_data[$field] = $data[$field];
                if (in_array($field, array('avg_response_time', 'min_response_time', 'max_response_time', 'cache_hit_rate', 'avg_cpu_usage', 'avg_disk_io'))) {
                    $format[] = '%f';
                } elseif (in_array($field, array('avg_memory_usage', 'peak_memory_usage', 'cache_hits', 'cache_misses', 'avg_db_queries', 'total_db_queries'))) {
                    $format[] = '%d';
                } else {
                    $format[] = '%s';
                }
            }
        }
        
        return $wpdb->update($table, $update_data, array('id' => $id), $format, array('%d'));
    }
    
    public static function delete_result($id) {
        global $wpdb;
        
        $metrics_table = $wpdb->prefix . 'cache_benchmark_metrics';
        $wpdb->delete($metrics_table, array('result_id' => $id), array('%d'));
        
        $results_table = $wpdb->prefix . 'cache_benchmark_results';
        return $wpdb->delete($results_table, array('id' => $id), array('%d'));
    }
    
    public static function save_metric($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'cache_benchmark_metrics';
        
        return $wpdb->insert(
            $table,
            array(
                'result_id' => $data['result_id'],
                'iteration' => $data['iteration'],
                'timestamp' => current_time('mysql'),
                'response_time' => isset($data['response_time']) ? $data['response_time'] : null,
                'memory_usage' => isset($data['memory_usage']) ? $data['memory_usage'] : null,
                'db_queries' => isset($data['db_queries']) ? $data['db_queries'] : null,
                'cpu_usage' => isset($data['cpu_usage']) ? $data['cpu_usage'] : null,
                'ram_usage' => isset($data['ram_usage']) ? $data['ram_usage'] : null,
                'disk_read' => isset($data['disk_read']) ? $data['disk_read'] : null,
                'disk_write' => isset($data['disk_write']) ? $data['disk_write'] : null,
                'cache_hits' => isset($data['cache_hits']) ? $data['cache_hits'] : 0,
                'cache_misses' => isset($data['cache_misses']) ? $data['cache_misses'] : 0
            ),
            array('%d', '%d', '%s', '%f', '%d', '%d', '%f', '%d', '%f', '%f', '%d', '%d')
        );
    }
    
    public static function get_metrics($result_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'cache_benchmark_metrics';
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE result_id = %d ORDER BY iteration ASC", $result_id));
    }
}
