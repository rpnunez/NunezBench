<?php
if (!defined('ABSPATH')) {
    exit;
}

class WP_Cache_Benchmark_Logger {
    
    private static $instance = null;
    private $logs = array();
    private $start_time;
    private $heartbeat_interval = 1;
    private $last_heartbeat = 0;
    private $slow_thresholds = array(
        'query' => 50,
        'post_creation' => 500,
        'api_read' => 200,
        'option_load' => 10,
        'file_write' => 100
    );
    
    const LOG_INFO = 'info';
    const LOG_WARNING = 'warning';
    const LOG_ERROR = 'error';
    const LOG_SUCCESS = 'success';
    const LOG_SLOW = 'slow';
    
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->start_time = microtime(true);
    }
    
    public function reset() {
        $this->logs = array();
        $this->start_time = microtime(true);
        $this->last_heartbeat = 0;
    }
    
    public function log($message, $type = self::LOG_INFO, $data = array()) {
        $timestamp = microtime(true) - $this->start_time;
        
        $this->logs[] = array(
            'timestamp' => $timestamp,
            'formatted_time' => $this->format_time($timestamp),
            'message' => $message,
            'type' => $type,
            'data' => $data
        );
    }
    
    public function heartbeat($iteration, $total, $metrics = array()) {
        $current_time = microtime(true);
        
        if ($current_time - $this->last_heartbeat < $this->heartbeat_interval) {
            return;
        }
        
        $this->last_heartbeat = $current_time;
        $elapsed = $current_time - $this->start_time;
        $progress = ($iteration / $total) * 100;
        $eta = $iteration > 0 ? (($elapsed / $iteration) * ($total - $iteration)) : 0;
        
        $this->log(
            sprintf('Heartbeat: Iteration %d/%d (%.1f%%) - ETA: %s', 
                $iteration, $total, $progress, $this->format_time($eta)
            ),
            self::LOG_INFO,
            array_merge($metrics, array(
                'iteration' => $iteration,
                'total' => $total,
                'progress' => $progress,
                'elapsed' => $elapsed,
                'eta' => $eta
            ))
        );
    }
    
    public function log_query($query, $duration, $caller, $is_slow = false) {
        $type = $is_slow ? self::LOG_SLOW : self::LOG_INFO;
        $prefix = $is_slow ? '[SLOW QUERY] ' : '[Query] ';
        
        $short_query = strlen($query) > 100 ? substr($query, 0, 100) . '...' : $query;
        
        $this->log(
            sprintf('%s%.2fms - %s (%s:%d)', 
                $prefix,
                $duration, 
                $short_query,
                basename($caller['file']),
                $caller['line']
            ),
            $type,
            array(
                'query' => $query,
                'duration' => $duration,
                'caller' => $caller,
                'is_slow' => $is_slow
            )
        );
    }
    
    public function log_post_creation($post_id, $duration, $is_slow = false) {
        $type = $is_slow ? self::LOG_SLOW : self::LOG_SUCCESS;
        $prefix = $is_slow ? '[SLOW POST CREATION] ' : '[Post Created] ';
        
        $this->log(
            sprintf('%sPost ID %d created in %.2fms', $prefix, $post_id, $duration),
            $type,
            array(
                'post_id' => $post_id,
                'duration' => $duration,
                'is_slow' => $is_slow
            )
        );
    }
    
    public function log_api_read($endpoint, $duration, $is_slow = false) {
        $type = $is_slow ? self::LOG_SLOW : self::LOG_INFO;
        $prefix = $is_slow ? '[SLOW API READ] ' : '[API Read] ';
        
        $this->log(
            sprintf('%s%s in %.2fms', $prefix, $endpoint, $duration),
            $type,
            array(
                'endpoint' => $endpoint,
                'duration' => $duration,
                'is_slow' => $is_slow
            )
        );
    }
    
    public function log_cache_operation($operation, $key, $hit = true, $duration = 0) {
        $status = $hit ? 'HIT' : 'MISS';
        
        $this->log(
            sprintf('[Cache %s] %s - %s (%.2fms)', $status, $operation, $key, $duration),
            $hit ? self::LOG_SUCCESS : self::LOG_WARNING,
            array(
                'operation' => $operation,
                'key' => $key,
                'hit' => $hit,
                'duration' => $duration
            )
        );
    }
    
    public function log_phase_start($phase_name) {
        $this->log(
            sprintf('=== Starting Phase: %s ===', $phase_name),
            self::LOG_INFO,
            array('phase' => $phase_name, 'event' => 'start')
        );
    }
    
    public function log_phase_end($phase_name, $duration, $stats = array()) {
        $this->log(
            sprintf('=== Phase Complete: %s (%.2fs) ===', $phase_name, $duration / 1000),
            self::LOG_SUCCESS,
            array_merge(array('phase' => $phase_name, 'event' => 'end', 'duration' => $duration), $stats)
        );
    }
    
    public function log_bottleneck($description, $value, $threshold, $suggestion) {
        $this->log(
            sprintf('[BOTTLENECK] %s: %.2f (threshold: %.2f)', $description, $value, $threshold),
            self::LOG_WARNING,
            array(
                'description' => $description,
                'value' => $value,
                'threshold' => $threshold,
                'suggestion' => $suggestion
            )
        );
    }
    
    public function get_logs() {
        return $this->logs;
    }
    
    public function get_recent_logs($since_timestamp = 0) {
        return array_filter($this->logs, function($log) use ($since_timestamp) {
            return $log['timestamp'] > $since_timestamp;
        });
    }
    
    public function get_slow_operations() {
        return array_filter($this->logs, function($log) {
            return $log['type'] === self::LOG_SLOW;
        });
    }
    
    public function get_errors() {
        return array_filter($this->logs, function($log) {
            return $log['type'] === self::LOG_ERROR;
        });
    }
    
    public function get_warnings() {
        return array_filter($this->logs, function($log) {
            return $log['type'] === self::LOG_WARNING || $log['type'] === self::LOG_SLOW;
        });
    }
    
    private function format_time($seconds) {
        if ($seconds < 60) {
            return sprintf('%.1fs', $seconds);
        } elseif ($seconds < 3600) {
            return sprintf('%dm %ds', floor($seconds / 60), $seconds % 60);
        } else {
            return sprintf('%dh %dm', floor($seconds / 3600), floor(($seconds % 3600) / 60));
        }
    }
    
    public function get_slow_threshold($operation) {
        return isset($this->slow_thresholds[$operation]) ? $this->slow_thresholds[$operation] : 100;
    }
    
    public function is_slow($operation, $duration) {
        $threshold = $this->get_slow_threshold($operation);
        return $duration > $threshold;
    }
    
    public function export_logs() {
        return array(
            'logs' => $this->logs,
            'summary' => array(
                'total_logs' => count($this->logs),
                'slow_operations' => count($this->get_slow_operations()),
                'errors' => count($this->get_errors()),
                'warnings' => count($this->get_warnings()),
                'duration' => microtime(true) - $this->start_time
            )
        );
    }
}
