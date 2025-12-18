<?php
if (!defined('ABSPATH')) {
    exit;
}

class WP_Cache_Benchmark_Comparison_Engine {
    
    private $results = array();
    private $baseline_id = null;
    
    public function load_results($result_ids) {
        if (!is_array($result_ids) || count($result_ids) < 2 || count($result_ids) > 5) {
            return new WP_Error('invalid_count', __('Please select between 2 and 5 benchmarks to compare.', 'wp-cache-benchmark'));
        }
        
        $this->results = array();
        
        foreach ($result_ids as $id) {
            $result = WP_Cache_Benchmark_Database::get_result($id);
            if ($result && $result->status === 'completed') {
                $result->metrics = WP_Cache_Benchmark_Database::get_metrics($id);
                $result->raw_data = maybe_unserialize($result->raw_data);
                $this->results[$id] = $result;
            }
        }
        
        if (count($this->results) < 2) {
            return new WP_Error('insufficient_results', __('At least 2 completed benchmarks are required for comparison.', 'wp-cache-benchmark'));
        }
        
        $this->baseline_id = reset($result_ids);
        
        return true;
    }
    
    public function set_baseline($result_id) {
        if (isset($this->results[$result_id])) {
            $this->baseline_id = $result_id;
            return true;
        }
        return false;
    }
    
    public function get_comparison_data() {
        if (empty($this->results)) {
            return null;
        }
        
        $comparison = array(
            'summary' => $this->get_summary_comparison(),
            'response_times' => $this->get_response_time_comparison(),
            'memory_usage' => $this->get_memory_comparison(),
            'cpu_usage' => $this->get_cpu_comparison(),
            'cache_performance' => $this->get_cache_comparison(),
            'database_queries' => $this->get_database_comparison(),
            'charts' => $this->get_chart_data()
        );
        
        return $comparison;
    }
    
    private function get_summary_comparison() {
        $summary = array();
        $baseline = isset($this->results[$this->baseline_id]) ? $this->results[$this->baseline_id] : null;
        
        foreach ($this->results as $id => $result) {
            $is_baseline = $id === $this->baseline_id;
            
            $item = array(
                'id' => $id,
                'name' => $result->name,
                'test_type' => $result->test_type,
                'is_baseline' => $is_baseline,
                'created_at' => $result->created_at,
                'metrics' => array(
                    'avg_response_time' => array(
                        'value' => round($result->avg_response_time, 2),
                        'unit' => 'ms',
                        'diff' => null,
                        'diff_percent' => null,
                        'is_improvement' => null
                    ),
                    'min_response_time' => array(
                        'value' => round($result->min_response_time, 2),
                        'unit' => 'ms'
                    ),
                    'max_response_time' => array(
                        'value' => round($result->max_response_time, 2),
                        'unit' => 'ms'
                    ),
                    'avg_memory_usage' => array(
                        'value' => $this->format_bytes($result->avg_memory_usage),
                        'raw' => $result->avg_memory_usage,
                        'diff' => null,
                        'diff_percent' => null,
                        'is_improvement' => null
                    ),
                    'peak_memory_usage' => array(
                        'value' => $this->format_bytes($result->peak_memory_usage),
                        'raw' => $result->peak_memory_usage
                    ),
                    'cache_hit_rate' => array(
                        'value' => round($result->cache_hit_rate, 1),
                        'unit' => '%',
                        'diff' => null,
                        'is_improvement' => null
                    ),
                    'avg_db_queries' => array(
                        'value' => round($result->avg_db_queries, 1),
                        'diff' => null,
                        'diff_percent' => null,
                        'is_improvement' => null
                    ),
                    'avg_cpu_usage' => array(
                        'value' => round($result->avg_cpu_usage, 1),
                        'unit' => '%',
                        'diff' => null,
                        'is_improvement' => null
                    )
                )
            );
            
            if (!$is_baseline && $baseline) {
                $item['metrics']['avg_response_time']['diff'] = round($result->avg_response_time - $baseline->avg_response_time, 2);
                $item['metrics']['avg_response_time']['diff_percent'] = $baseline->avg_response_time > 0 ? 
                    round((($result->avg_response_time - $baseline->avg_response_time) / $baseline->avg_response_time) * 100, 1) : 0;
                $item['metrics']['avg_response_time']['is_improvement'] = $result->avg_response_time < $baseline->avg_response_time;
                
                $item['metrics']['avg_memory_usage']['diff'] = $result->avg_memory_usage - $baseline->avg_memory_usage;
                $item['metrics']['avg_memory_usage']['diff_percent'] = $baseline->avg_memory_usage > 0 ?
                    round((($result->avg_memory_usage - $baseline->avg_memory_usage) / $baseline->avg_memory_usage) * 100, 1) : 0;
                $item['metrics']['avg_memory_usage']['is_improvement'] = $result->avg_memory_usage < $baseline->avg_memory_usage;
                
                $item['metrics']['cache_hit_rate']['diff'] = round($result->cache_hit_rate - $baseline->cache_hit_rate, 1);
                $item['metrics']['cache_hit_rate']['is_improvement'] = $result->cache_hit_rate > $baseline->cache_hit_rate;
                
                $item['metrics']['avg_db_queries']['diff'] = round($result->avg_db_queries - $baseline->avg_db_queries, 1);
                $item['metrics']['avg_db_queries']['diff_percent'] = $baseline->avg_db_queries > 0 ?
                    round((($result->avg_db_queries - $baseline->avg_db_queries) / $baseline->avg_db_queries) * 100, 1) : 0;
                $item['metrics']['avg_db_queries']['is_improvement'] = $result->avg_db_queries < $baseline->avg_db_queries;
                
                $item['metrics']['avg_cpu_usage']['diff'] = round($result->avg_cpu_usage - $baseline->avg_cpu_usage, 1);
                $item['metrics']['avg_cpu_usage']['is_improvement'] = $result->avg_cpu_usage < $baseline->avg_cpu_usage;
            }
            
            $summary[] = $item;
        }
        
        return $summary;
    }
    
    private function get_response_time_comparison() {
        $data = array();
        
        foreach ($this->results as $id => $result) {
            $response_times = array();
            foreach ($result->metrics as $metric) {
                $response_times[] = $metric->response_time;
            }
            
            sort($response_times);
            $count = count($response_times);
            
            $data[$id] = array(
                'name' => $result->name,
                'values' => $response_times,
                'stats' => array(
                    'min' => $count > 0 ? min($response_times) : 0,
                    'max' => $count > 0 ? max($response_times) : 0,
                    'avg' => $count > 0 ? array_sum($response_times) / $count : 0,
                    'median' => $count > 0 ? $response_times[floor($count / 2)] : 0,
                    'p95' => $count > 0 ? $response_times[floor($count * 0.95)] : 0,
                    'p99' => $count > 0 ? $response_times[floor($count * 0.99)] : 0,
                    'std_dev' => $this->calculate_std_dev($response_times)
                )
            );
        }
        
        return $data;
    }
    
    private function get_memory_comparison() {
        $data = array();
        
        foreach ($this->results as $id => $result) {
            $memory_values = array();
            foreach ($result->metrics as $metric) {
                $memory_values[] = $metric->memory_usage;
            }
            
            $data[$id] = array(
                'name' => $result->name,
                'values' => $memory_values,
                'stats' => array(
                    'min' => count($memory_values) > 0 ? min($memory_values) : 0,
                    'max' => count($memory_values) > 0 ? max($memory_values) : 0,
                    'avg' => count($memory_values) > 0 ? array_sum($memory_values) / count($memory_values) : 0,
                    'peak' => $result->peak_memory_usage
                )
            );
        }
        
        return $data;
    }
    
    private function get_cpu_comparison() {
        $data = array();
        
        foreach ($this->results as $id => $result) {
            $cpu_values = array();
            foreach ($result->metrics as $metric) {
                if ($metric->cpu_usage !== null) {
                    $cpu_values[] = $metric->cpu_usage;
                }
            }
            
            $data[$id] = array(
                'name' => $result->name,
                'values' => $cpu_values,
                'stats' => array(
                    'min' => count($cpu_values) > 0 ? min($cpu_values) : 0,
                    'max' => count($cpu_values) > 0 ? max($cpu_values) : 0,
                    'avg' => $result->avg_cpu_usage
                )
            );
        }
        
        return $data;
    }
    
    private function get_cache_comparison() {
        $data = array();
        
        foreach ($this->results as $id => $result) {
            $data[$id] = array(
                'name' => $result->name,
                'hits' => $result->cache_hits,
                'misses' => $result->cache_misses,
                'hit_rate' => $result->cache_hit_rate,
                'total' => $result->cache_hits + $result->cache_misses
            );
        }
        
        return $data;
    }
    
    private function get_database_comparison() {
        $data = array();
        
        foreach ($this->results as $id => $result) {
            $query_counts = array();
            foreach ($result->metrics as $metric) {
                if ($metric->db_queries !== null) {
                    $query_counts[] = $metric->db_queries;
                }
            }
            
            $data[$id] = array(
                'name' => $result->name,
                'values' => $query_counts,
                'stats' => array(
                    'min' => count($query_counts) > 0 ? min($query_counts) : 0,
                    'max' => count($query_counts) > 0 ? max($query_counts) : 0,
                    'avg' => $result->avg_db_queries,
                    'total' => $result->total_db_queries
                )
            );
        }
        
        return $data;
    }
    
    private function get_chart_data() {
        $charts = array(
            'response_time_line' => $this->get_line_chart_data('response_time'),
            'response_time_bar' => $this->get_bar_chart_data('response_time'),
            'memory_line' => $this->get_line_chart_data('memory_usage'),
            'memory_bar' => $this->get_bar_chart_data('memory_usage'),
            'cpu_line' => $this->get_line_chart_data('cpu_usage'),
            'cache_pie' => $this->get_cache_pie_data(),
            'queries_bar' => $this->get_bar_chart_data('db_queries')
        );
        
        return $charts;
    }
    
    private function get_line_chart_data($metric_field) {
        $datasets = array();
        $colors = array(
            'rgba(54, 162, 235, 1)',
            'rgba(255, 99, 132, 1)',
            'rgba(75, 192, 192, 1)',
            'rgba(255, 159, 64, 1)',
            'rgba(153, 102, 255, 1)'
        );
        
        $color_index = 0;
        $max_iterations = 0;
        
        foreach ($this->results as $id => $result) {
            $max_iterations = max($max_iterations, count($result->metrics));
        }
        
        foreach ($this->results as $id => $result) {
            $data = array();
            foreach ($result->metrics as $metric) {
                $data[] = $metric->$metric_field;
            }
            
            $color = $colors[$color_index % count($colors)];
            
            $datasets[] = array(
                'label' => $result->name,
                'data' => $data,
                'borderColor' => $color,
                'backgroundColor' => str_replace('1)', '0.1)', $color),
                'fill' => false,
                'tension' => 0.3
            );
            
            $color_index++;
        }
        
        $labels = range(1, $max_iterations);
        
        return array(
            'labels' => $labels,
            'datasets' => $datasets
        );
    }
    
    private function get_bar_chart_data($metric_field) {
        $labels = array();
        $avg_data = array();
        $min_data = array();
        $max_data = array();
        $colors = array(
            'rgba(54, 162, 235, 0.8)',
            'rgba(255, 99, 132, 0.8)',
            'rgba(75, 192, 192, 0.8)',
            'rgba(255, 159, 64, 0.8)',
            'rgba(153, 102, 255, 0.8)'
        );
        
        $color_index = 0;
        
        foreach ($this->results as $id => $result) {
            $labels[] = $result->name;
            
            $values = array();
            foreach ($result->metrics as $metric) {
                if ($metric->$metric_field !== null) {
                    $values[] = $metric->$metric_field;
                }
            }
            
            $avg_data[] = array(
                'value' => count($values) > 0 ? array_sum($values) / count($values) : 0,
                'color' => $colors[$color_index % count($colors)]
            );
            $min_data[] = count($values) > 0 ? min($values) : 0;
            $max_data[] = count($values) > 0 ? max($values) : 0;
            
            $color_index++;
        }
        
        return array(
            'labels' => $labels,
            'datasets' => array(
                array(
                    'label' => 'Average',
                    'data' => array_column($avg_data, 'value'),
                    'backgroundColor' => array_column($avg_data, 'color')
                )
            )
        );
    }
    
    private function get_cache_pie_data() {
        $charts = array();
        
        foreach ($this->results as $id => $result) {
            $charts[$id] = array(
                'name' => $result->name,
                'labels' => array('Cache Hits', 'Cache Misses'),
                'datasets' => array(
                    array(
                        'data' => array($result->cache_hits, $result->cache_misses),
                        'backgroundColor' => array(
                            'rgba(75, 192, 192, 0.8)',
                            'rgba(255, 99, 132, 0.8)'
                        )
                    )
                )
            );
        }
        
        return $charts;
    }
    
    private function calculate_std_dev($values) {
        if (count($values) < 2) {
            return 0;
        }
        
        $mean = array_sum($values) / count($values);
        $variance = 0;
        
        foreach ($values as $value) {
            $variance += pow($value - $mean, 2);
        }
        
        return sqrt($variance / count($values));
    }
    
    private function format_bytes($bytes, $precision = 2) {
        $units = array('B', 'KB', 'MB', 'GB');
        
        for ($i = 0; $bytes >= 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
    
    public function get_results() {
        return $this->results;
    }
}
