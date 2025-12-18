<?php
if (!defined('ABSPATH')) {
    exit;
}

class WP_Cache_Benchmark_Export_Handler {
    
    public function export_result($result_id, $format = 'json') {
        $result = WP_Cache_Benchmark_Database::get_result($result_id);
        
        if (!$result) {
            return new WP_Error('not_found', __('Result not found.', 'wp-cache-benchmark'));
        }
        
        $result->metrics = WP_Cache_Benchmark_Database::get_metrics($result_id);
        $result->raw_data = maybe_unserialize($result->raw_data);
        
        switch ($format) {
            case 'csv':
                return $this->to_csv($result);
            case 'json':
            default:
                return $this->to_json($result);
        }
    }
    
    public function export_comparison($result_ids, $format = 'json') {
        $comparison_engine = new WP_Cache_Benchmark_Comparison_Engine();
        $load_result = $comparison_engine->load_results($result_ids);
        
        if (is_wp_error($load_result)) {
            return $load_result;
        }
        
        $comparison_data = $comparison_engine->get_comparison_data();
        
        switch ($format) {
            case 'csv':
                return $this->comparison_to_csv($comparison_data);
            case 'json':
            default:
                return $this->comparison_to_json($comparison_data);
        }
    }
    
    private function to_json($result) {
        $data = array(
            'meta' => array(
                'export_date' => current_time('mysql'),
                'plugin_version' => WP_CACHE_BENCHMARK_VERSION,
                'wordpress_version' => get_bloginfo('version'),
                'php_version' => PHP_VERSION
            ),
            'result' => array(
                'id' => $result->id,
                'name' => $result->name,
                'test_type' => $result->test_type,
                'status' => $result->status,
                'iterations' => $result->iterations,
                'started_at' => $result->started_at,
                'completed_at' => $result->completed_at,
                'summary' => array(
                    'avg_response_time' => $result->avg_response_time,
                    'min_response_time' => $result->min_response_time,
                    'max_response_time' => $result->max_response_time,
                    'avg_memory_usage' => $result->avg_memory_usage,
                    'peak_memory_usage' => $result->peak_memory_usage,
                    'avg_db_queries' => $result->avg_db_queries,
                    'total_db_queries' => $result->total_db_queries,
                    'cache_hits' => $result->cache_hits,
                    'cache_misses' => $result->cache_misses,
                    'cache_hit_rate' => $result->cache_hit_rate,
                    'avg_cpu_usage' => $result->avg_cpu_usage,
                    'avg_disk_io' => $result->avg_disk_io
                ),
                'metrics' => array_map(function($metric) {
                    return array(
                        'iteration' => $metric->iteration,
                        'timestamp' => $metric->timestamp,
                        'response_time' => $metric->response_time,
                        'memory_usage' => $metric->memory_usage,
                        'db_queries' => $metric->db_queries,
                        'cpu_usage' => $metric->cpu_usage,
                        'ram_usage' => $metric->ram_usage,
                        'disk_read' => $metric->disk_read,
                        'disk_write' => $metric->disk_write,
                        'cache_hits' => $metric->cache_hits,
                        'cache_misses' => $metric->cache_misses
                    );
                }, $result->metrics)
            )
        );
        
        if (isset($result->raw_data['resource_timeline'])) {
            $data['result']['resource_timeline'] = $result->raw_data['resource_timeline'];
        }
        
        return array(
            'filename' => 'benchmark-' . $result->id . '-' . date('Y-m-d') . '.json',
            'content_type' => 'application/json',
            'content' => json_encode($data, JSON_PRETTY_PRINT)
        );
    }
    
    private function to_csv($result) {
        $csv_data = array();
        
        $csv_data[] = array(
            'Iteration',
            'Timestamp',
            'Response Time (ms)',
            'Memory Usage (bytes)',
            'DB Queries',
            'CPU Usage (%)',
            'RAM Usage (bytes)',
            'Disk Read (bytes)',
            'Disk Write (bytes)',
            'Cache Hits',
            'Cache Misses'
        );
        
        foreach ($result->metrics as $metric) {
            $csv_data[] = array(
                $metric->iteration,
                $metric->timestamp,
                $metric->response_time,
                $metric->memory_usage,
                $metric->db_queries,
                $metric->cpu_usage,
                $metric->ram_usage,
                $metric->disk_read,
                $metric->disk_write,
                $metric->cache_hits,
                $metric->cache_misses
            );
        }
        
        $csv_data[] = array();
        $csv_data[] = array('Summary');
        $csv_data[] = array('Metric', 'Value');
        $csv_data[] = array('Test Name', $result->name);
        $csv_data[] = array('Test Type', $result->test_type);
        $csv_data[] = array('Iterations', $result->iterations);
        $csv_data[] = array('Avg Response Time (ms)', $result->avg_response_time);
        $csv_data[] = array('Min Response Time (ms)', $result->min_response_time);
        $csv_data[] = array('Max Response Time (ms)', $result->max_response_time);
        $csv_data[] = array('Avg Memory Usage (bytes)', $result->avg_memory_usage);
        $csv_data[] = array('Peak Memory Usage (bytes)', $result->peak_memory_usage);
        $csv_data[] = array('Avg DB Queries', $result->avg_db_queries);
        $csv_data[] = array('Total DB Queries', $result->total_db_queries);
        $csv_data[] = array('Cache Hits', $result->cache_hits);
        $csv_data[] = array('Cache Misses', $result->cache_misses);
        $csv_data[] = array('Cache Hit Rate (%)', $result->cache_hit_rate);
        $csv_data[] = array('Avg CPU Usage (%)', $result->avg_cpu_usage);
        
        $output = fopen('php://temp', 'r+');
        foreach ($csv_data as $row) {
            fputcsv($output, $row);
        }
        rewind($output);
        $csv_content = stream_get_contents($output);
        fclose($output);
        
        return array(
            'filename' => 'benchmark-' . $result->id . '-' . date('Y-m-d') . '.csv',
            'content_type' => 'text/csv',
            'content' => $csv_content
        );
    }
    
    private function comparison_to_json($comparison_data) {
        $data = array(
            'meta' => array(
                'export_date' => current_time('mysql'),
                'plugin_version' => WP_CACHE_BENCHMARK_VERSION,
                'export_type' => 'comparison'
            ),
            'comparison' => $comparison_data
        );
        
        return array(
            'filename' => 'benchmark-comparison-' . date('Y-m-d-His') . '.json',
            'content_type' => 'application/json',
            'content' => json_encode($data, JSON_PRETTY_PRINT)
        );
    }
    
    private function comparison_to_csv($comparison_data) {
        $csv_data = array();
        
        $csv_data[] = array('Benchmark Comparison Report');
        $csv_data[] = array('Generated:', date('Y-m-d H:i:s'));
        $csv_data[] = array();
        
        $csv_data[] = array('Summary Comparison');
        
        $headers = array('Metric');
        foreach ($comparison_data['summary'] as $item) {
            $headers[] = $item['name'] . ($item['is_baseline'] ? ' (Baseline)' : '');
        }
        $csv_data[] = $headers;
        
        $metrics_to_show = array(
            'avg_response_time' => 'Avg Response Time (ms)',
            'min_response_time' => 'Min Response Time (ms)',
            'max_response_time' => 'Max Response Time (ms)',
            'avg_memory_usage' => 'Avg Memory Usage',
            'peak_memory_usage' => 'Peak Memory Usage',
            'cache_hit_rate' => 'Cache Hit Rate (%)',
            'avg_db_queries' => 'Avg DB Queries',
            'avg_cpu_usage' => 'Avg CPU Usage (%)'
        );
        
        foreach ($metrics_to_show as $key => $label) {
            $row = array($label);
            foreach ($comparison_data['summary'] as $item) {
                $value = $item['metrics'][$key]['value'];
                if (isset($item['metrics'][$key]['diff_percent']) && $item['metrics'][$key]['diff_percent'] !== null) {
                    $diff = $item['metrics'][$key]['diff_percent'];
                    $value .= ' (' . ($diff >= 0 ? '+' : '') . $diff . '%)';
                }
                $row[] = $value;
            }
            $csv_data[] = $row;
        }
        
        $output = fopen('php://temp', 'r+');
        foreach ($csv_data as $row) {
            fputcsv($output, $row);
        }
        rewind($output);
        $csv_content = stream_get_contents($output);
        fclose($output);
        
        return array(
            'filename' => 'benchmark-comparison-' . date('Y-m-d-His') . '.csv',
            'content_type' => 'text/csv',
            'content' => $csv_content
        );
    }
    
    public function download($export_data) {
        if (is_wp_error($export_data)) {
            wp_die($export_data->get_error_message());
        }
        
        header('Content-Type: ' . $export_data['content_type']);
        header('Content-Disposition: attachment; filename="' . $export_data['filename'] . '"');
        header('Content-Length: ' . strlen($export_data['content']));
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        echo $export_data['content'];
        exit;
    }
}
