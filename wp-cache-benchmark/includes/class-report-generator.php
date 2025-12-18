<?php
if (!defined('ABSPATH')) {
    exit;
}

class WP_Cache_Benchmark_Report_Generator {
    
    private $result;
    private $metrics;
    private $query_stats;
    private $logs;
    private $bottlenecks = array();
    private $suggestions = array();
    
    public function __construct($result, $metrics, $query_stats, $logs) {
        $this->result = $result;
        $this->metrics = $metrics;
        $this->query_stats = $query_stats;
        $this->logs = $logs;
    }
    
    public function generate() {
        $this->analyze_performance();
        $this->identify_bottlenecks();
        $this->generate_suggestions();
        
        return array(
            'summary' => $this->generate_summary(),
            'performance_grades' => $this->calculate_grades(),
            'bottlenecks' => $this->bottlenecks,
            'suggestions' => $this->suggestions,
            'detailed_analysis' => $this->generate_detailed_analysis(),
            'action_items' => $this->generate_action_items()
        );
    }
    
    private function analyze_performance() {
        $this->analyze_response_times();
        $this->analyze_memory_usage();
        $this->analyze_database_queries();
        $this->analyze_cache_performance();
        $this->analyze_slow_operations();
    }
    
    private function analyze_response_times() {
        $avg_response = $this->result->avg_response_time ?? 0;
        $max_response = $this->result->max_response_time ?? 0;
        
        if ($avg_response > 500) {
            $this->bottlenecks[] = array(
                'category' => 'Response Time',
                'severity' => 'critical',
                'description' => sprintf('Average response time (%.2fms) is critically slow', $avg_response),
                'impact' => 'Users will experience significant delays, potentially leading to abandonment',
                'value' => $avg_response,
                'threshold' => 500
            );
        } elseif ($avg_response > 200) {
            $this->bottlenecks[] = array(
                'category' => 'Response Time',
                'severity' => 'warning',
                'description' => sprintf('Average response time (%.2fms) is slower than recommended', $avg_response),
                'impact' => 'Page load times may affect user experience and SEO',
                'value' => $avg_response,
                'threshold' => 200
            );
        }
        
        $variance = $max_response - ($this->result->min_response_time ?? 0);
        if ($variance > $avg_response * 2) {
            $this->bottlenecks[] = array(
                'category' => 'Response Time Variance',
                'severity' => 'warning',
                'description' => sprintf('High variance in response times (%.2fms spread)', $variance),
                'impact' => 'Inconsistent user experience, possible caching issues',
                'value' => $variance,
                'threshold' => $avg_response * 2
            );
        }
    }
    
    private function analyze_memory_usage() {
        $peak_memory = $this->result->peak_memory_usage ?? 0;
        $avg_memory = $this->result->avg_memory_usage ?? 0;
        
        $memory_limit = $this->get_memory_limit();
        $memory_percent = ($peak_memory / $memory_limit) * 100;
        
        if ($memory_percent > 80) {
            $this->bottlenecks[] = array(
                'category' => 'Memory Usage',
                'severity' => 'critical',
                'description' => sprintf('Peak memory usage (%.0f%%) approaching PHP limit', $memory_percent),
                'impact' => 'Risk of fatal memory exhaustion errors under load',
                'value' => $memory_percent,
                'threshold' => 80
            );
        } elseif ($memory_percent > 60) {
            $this->bottlenecks[] = array(
                'category' => 'Memory Usage',
                'severity' => 'warning',
                'description' => sprintf('Memory usage (%.0f%%) is higher than optimal', $memory_percent),
                'impact' => 'May cause issues during traffic spikes',
                'value' => $memory_percent,
                'threshold' => 60
            );
        }
    }
    
    private function analyze_database_queries() {
        $total_queries = $this->query_stats['total_queries'] ?? 0;
        $slow_queries = $this->query_stats['slow_queries'] ?? 0;
        $avg_query_time = $this->query_stats['avg_time'] ?? 0;
        
        $iterations = $this->result->iterations ?? 1;
        $queries_per_request = $total_queries / max(1, $iterations);
        
        if ($queries_per_request > 100) {
            $this->bottlenecks[] = array(
                'category' => 'Database Queries',
                'severity' => 'critical',
                'description' => sprintf('Excessive database queries per request (%.0f)', $queries_per_request),
                'impact' => 'Database overload, slow page loads, potential server crashes',
                'value' => $queries_per_request,
                'threshold' => 100
            );
        } elseif ($queries_per_request > 50) {
            $this->bottlenecks[] = array(
                'category' => 'Database Queries',
                'severity' => 'warning',
                'description' => sprintf('High number of database queries per request (%.0f)', $queries_per_request),
                'impact' => 'Increased load on database server',
                'value' => $queries_per_request,
                'threshold' => 50
            );
        }
        
        if ($slow_queries > 0) {
            $slow_percent = ($slow_queries / max(1, $total_queries)) * 100;
            $this->bottlenecks[] = array(
                'category' => 'Slow Queries',
                'severity' => $slow_percent > 10 ? 'critical' : 'warning',
                'description' => sprintf('%d slow queries detected (%.1f%%)', $slow_queries, $slow_percent),
                'impact' => 'Database bottleneck affecting overall performance',
                'value' => $slow_queries,
                'threshold' => 0
            );
        }
        
        if (isset($this->query_stats['by_plugin'])) {
            foreach ($this->query_stats['by_plugin'] as $plugin => $stats) {
                if ($stats['count'] > $total_queries * 0.3 && $stats['count'] > 20) {
                    $this->bottlenecks[] = array(
                        'category' => 'Plugin Database Usage',
                        'severity' => 'warning',
                        'description' => sprintf('%s is responsible for %d queries (%.0f%%)', 
                            $plugin, $stats['count'], ($stats['count'] / $total_queries) * 100),
                        'impact' => 'Single plugin consuming excessive database resources',
                        'value' => $stats['count'],
                        'threshold' => $total_queries * 0.3
                    );
                }
            }
        }
    }
    
    private function analyze_cache_performance() {
        $hit_rate = $this->result->cache_hit_rate ?? 0;
        
        if ($hit_rate < 50 && $hit_rate > 0) {
            $this->bottlenecks[] = array(
                'category' => 'Cache Performance',
                'severity' => 'critical',
                'description' => sprintf('Low cache hit rate (%.1f%%)', $hit_rate),
                'impact' => 'Most requests hitting database instead of cache',
                'value' => $hit_rate,
                'threshold' => 50
            );
        } elseif ($hit_rate < 80 && $hit_rate > 0) {
            $this->bottlenecks[] = array(
                'category' => 'Cache Performance',
                'severity' => 'warning',
                'description' => sprintf('Cache hit rate could be improved (%.1f%%)', $hit_rate),
                'impact' => 'Suboptimal use of caching layer',
                'value' => $hit_rate,
                'threshold' => 80
            );
        }
    }
    
    private function analyze_slow_operations() {
        $slow_ops = array_filter($this->logs, function($log) {
            return isset($log['type']) && $log['type'] === 'slow';
        });
        
        if (count($slow_ops) > 10) {
            $this->bottlenecks[] = array(
                'category' => 'Slow Operations',
                'severity' => count($slow_ops) > 50 ? 'critical' : 'warning',
                'description' => sprintf('%d slow operations detected during benchmark', count($slow_ops)),
                'impact' => 'Multiple performance bottlenecks throughout the system',
                'value' => count($slow_ops),
                'threshold' => 10
            );
        }
    }
    
    private function identify_bottlenecks() {
        usort($this->bottlenecks, function($a, $b) {
            $severity_order = array('critical' => 0, 'warning' => 1, 'info' => 2);
            return $severity_order[$a['severity']] <=> $severity_order[$b['severity']];
        });
    }
    
    private function generate_suggestions() {
        foreach ($this->bottlenecks as $bottleneck) {
            $suggestion = $this->get_suggestion_for_bottleneck($bottleneck);
            if ($suggestion) {
                $this->suggestions[] = $suggestion;
            }
        }
        
        $this->add_general_suggestions();
    }
    
    private function get_suggestion_for_bottleneck($bottleneck) {
        $suggestions_map = array(
            'Response Time' => array(
                'title' => 'Optimize Page Load Time',
                'priority' => 'high',
                'actions' => array(
                    'Install and configure a page caching plugin (WP Super Cache, W3 Total Cache, or LiteSpeed Cache)',
                    'Enable browser caching for static assets',
                    'Use a Content Delivery Network (CDN) for static files',
                    'Minimize HTTP requests by combining CSS and JavaScript files',
                    'Optimize images using WebP format and lazy loading'
                )
            ),
            'Response Time Variance' => array(
                'title' => 'Stabilize Response Times',
                'priority' => 'medium',
                'actions' => array(
                    'Review and optimize database queries that run inconsistently',
                    'Check for external API calls that may timeout',
                    'Implement object caching with Redis or Memcached',
                    'Review cron jobs that may be running during requests'
                )
            ),
            'Memory Usage' => array(
                'title' => 'Reduce Memory Consumption',
                'priority' => 'high',
                'actions' => array(
                    'Increase PHP memory limit in php.ini or wp-config.php',
                    'Audit and remove unused plugins',
                    'Optimize database by removing post revisions and transients',
                    'Use a memory-efficient theme',
                    'Consider upgrading to a higher-tier hosting plan'
                )
            ),
            'Database Queries' => array(
                'title' => 'Reduce Database Load',
                'priority' => 'high',
                'actions' => array(
                    'Install a database query caching plugin',
                    'Use object caching (Redis/Memcached)',
                    'Review and deactivate query-heavy plugins',
                    'Optimize autoloaded options in the database',
                    'Add database indexes for frequently queried columns'
                )
            ),
            'Slow Queries' => array(
                'title' => 'Optimize Slow Database Queries',
                'priority' => 'high',
                'actions' => array(
                    'Review the slow queries identified in this report',
                    'Add missing database indexes',
                    'Optimize complex JOINs and subqueries',
                    'Consider using query caching',
                    'Review plugins responsible for slow queries'
                )
            ),
            'Plugin Database Usage' => array(
                'title' => 'Review Plugin Performance',
                'priority' => 'medium',
                'actions' => array(
                    'Consider replacing the identified plugin with a more efficient alternative',
                    'Contact the plugin developer about performance concerns',
                    'Check if all features of the plugin are necessary',
                    'Look for caching options within the plugin settings'
                )
            ),
            'Cache Performance' => array(
                'title' => 'Improve Cache Efficiency',
                'priority' => 'high',
                'actions' => array(
                    'Install and properly configure a caching plugin',
                    'Implement object caching with Redis or Memcached',
                    'Enable opcode caching (OPcache)',
                    'Review cache invalidation rules',
                    'Increase cache TTL for static content'
                )
            ),
            'Slow Operations' => array(
                'title' => 'Address Performance Bottlenecks',
                'priority' => 'medium',
                'actions' => array(
                    'Review the detailed log for specific slow operations',
                    'Optimize or replace slow-performing plugins',
                    'Consider asynchronous processing for heavy operations',
                    'Implement caching at multiple levels'
                )
            )
        );
        
        $category = $bottleneck['category'];
        
        if (isset($suggestions_map[$category])) {
            $suggestion = $suggestions_map[$category];
            $suggestion['bottleneck'] = $bottleneck;
            return $suggestion;
        }
        
        return null;
    }
    
    private function add_general_suggestions() {
        $has_critical = array_filter($this->bottlenecks, function($b) {
            return $b['severity'] === 'critical';
        });
        
        if (empty($has_critical) && count($this->bottlenecks) < 3) {
            $this->suggestions[] = array(
                'title' => 'Good Performance - Consider Further Optimization',
                'priority' => 'low',
                'actions' => array(
                    'Your site is performing well. Consider these enhancements:',
                    'Implement HTTP/2 or HTTP/3 for faster asset loading',
                    'Use preloading and prefetching for critical resources',
                    'Consider a managed WordPress hosting provider',
                    'Set up performance monitoring for ongoing optimization'
                ),
                'bottleneck' => null
            );
        }
    }
    
    private function generate_summary() {
        $critical_count = count(array_filter($this->bottlenecks, function($b) {
            return $b['severity'] === 'critical';
        }));
        
        $warning_count = count(array_filter($this->bottlenecks, function($b) {
            return $b['severity'] === 'warning';
        }));
        
        $overall_score = $this->calculate_overall_score();
        
        if ($overall_score >= 90) {
            $status = 'Excellent';
            $message = 'Your WordPress site is performing excellently.';
        } elseif ($overall_score >= 70) {
            $status = 'Good';
            $message = 'Your site has good performance with room for improvement.';
        } elseif ($overall_score >= 50) {
            $status = 'Needs Improvement';
            $message = 'Your site has performance issues that should be addressed.';
        } else {
            $status = 'Critical';
            $message = 'Your site has critical performance problems requiring immediate attention.';
        }
        
        return array(
            'overall_score' => $overall_score,
            'status' => $status,
            'message' => $message,
            'critical_issues' => $critical_count,
            'warnings' => $warning_count,
            'total_queries' => $this->query_stats['total_queries'] ?? 0,
            'slow_queries' => $this->query_stats['slow_queries'] ?? 0,
            'avg_response_time' => $this->result->avg_response_time ?? 0,
            'cache_hit_rate' => $this->result->cache_hit_rate ?? 0
        );
    }
    
    private function calculate_overall_score() {
        $score = 100;
        
        foreach ($this->bottlenecks as $bottleneck) {
            if ($bottleneck['severity'] === 'critical') {
                $score -= 15;
            } elseif ($bottleneck['severity'] === 'warning') {
                $score -= 5;
            }
        }
        
        return max(0, $score);
    }
    
    private function calculate_grades() {
        return array(
            'response_time' => $this->grade_metric($this->result->avg_response_time ?? 0, 100, 300, 500, true),
            'memory_usage' => $this->grade_metric(
                (($this->result->peak_memory_usage ?? 0) / $this->get_memory_limit()) * 100,
                30, 50, 70, true
            ),
            'database_queries' => $this->grade_metric(
                $this->result->avg_db_queries ?? 0,
                20, 50, 100, true
            ),
            'cache_hit_rate' => $this->grade_metric($this->result->cache_hit_rate ?? 0, 90, 70, 50, false)
        );
    }
    
    private function grade_metric($value, $a_threshold, $b_threshold, $c_threshold, $lower_is_better) {
        if ($lower_is_better) {
            if ($value <= $a_threshold) return array('grade' => 'A', 'color' => '#22c55e');
            if ($value <= $b_threshold) return array('grade' => 'B', 'color' => '#84cc16');
            if ($value <= $c_threshold) return array('grade' => 'C', 'color' => '#eab308');
            return array('grade' => 'F', 'color' => '#ef4444');
        } else {
            if ($value >= $a_threshold) return array('grade' => 'A', 'color' => '#22c55e');
            if ($value >= $b_threshold) return array('grade' => 'B', 'color' => '#84cc16');
            if ($value >= $c_threshold) return array('grade' => 'C', 'color' => '#eab308');
            return array('grade' => 'F', 'color' => '#ef4444');
        }
    }
    
    private function generate_detailed_analysis() {
        return array(
            'query_analysis' => array(
                'by_type' => $this->query_stats['by_type'] ?? array(),
                'by_plugin' => $this->query_stats['by_plugin'] ?? array(),
                'by_table' => $this->query_stats['by_table'] ?? array(),
                'slowest_queries' => array_slice($this->query_stats['slowest'] ?? array(), 0, 10)
            ),
            'memory_analysis' => array(
                'peak' => $this->result->peak_memory_usage ?? 0,
                'average' => $this->result->avg_memory_usage ?? 0,
                'limit' => $this->get_memory_limit(),
                'percent_used' => (($this->result->peak_memory_usage ?? 0) / $this->get_memory_limit()) * 100
            ),
            'timing_analysis' => array(
                'avg' => $this->result->avg_response_time ?? 0,
                'min' => $this->result->min_response_time ?? 0,
                'max' => $this->result->max_response_time ?? 0,
                'variance' => ($this->result->max_response_time ?? 0) - ($this->result->min_response_time ?? 0)
            )
        );
    }
    
    private function generate_action_items() {
        $actions = array();
        
        foreach ($this->suggestions as $suggestion) {
            if ($suggestion['priority'] === 'high') {
                foreach (array_slice($suggestion['actions'], 0, 2) as $action) {
                    $actions[] = array(
                        'priority' => 'high',
                        'action' => $action,
                        'category' => $suggestion['title']
                    );
                }
            }
        }
        
        return $actions;
    }
    
    private function get_memory_limit() {
        $memory_limit = ini_get('memory_limit');
        
        if (preg_match('/^(\d+)(.)$/', $memory_limit, $matches)) {
            $value = (int) $matches[1];
            $unit = strtoupper($matches[2]);
            
            switch ($unit) {
                case 'G':
                    return $value * 1024 * 1024 * 1024;
                case 'M':
                    return $value * 1024 * 1024;
                case 'K':
                    return $value * 1024;
            }
        }
        
        return 128 * 1024 * 1024;
    }
}
