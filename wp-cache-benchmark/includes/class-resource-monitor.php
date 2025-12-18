<?php
if (!defined('ABSPATH')) {
    exit;
}

class WP_Cache_Benchmark_Resource_Monitor {
    
    private $is_running = false;
    private $timeline = array();
    private $start_time;
    private $last_cpu_stats;
    private $last_disk_stats;
    
    public function start() {
        $this->is_running = true;
        $this->timeline = array();
        $this->start_time = microtime(true);
        $this->last_cpu_stats = $this->read_cpu_stats();
        $this->last_disk_stats = $this->read_disk_stats();
        
        $this->record_snapshot();
    }
    
    public function stop() {
        $this->is_running = false;
        $this->record_snapshot();
    }
    
    public function record_snapshot() {
        $snapshot = array(
            'timestamp' => microtime(true) - $this->start_time,
            'cpu' => $this->get_cpu_usage(),
            'ram' => $this->get_memory_usage(),
            'ram_percent' => $this->get_memory_percent(),
            'disk_read' => 0,
            'disk_write' => 0,
            'load_avg' => $this->get_load_average()
        );
        
        $disk_io = $this->get_disk_io();
        $snapshot['disk_read'] = $disk_io['read'];
        $snapshot['disk_write'] = $disk_io['write'];
        
        $this->timeline[] = $snapshot;
    }
    
    public function get_current() {
        return array(
            'cpu' => $this->get_cpu_usage(),
            'ram' => $this->get_memory_usage(),
            'ram_percent' => $this->get_memory_percent(),
            'disk_read' => 0,
            'disk_write' => 0,
            'load_avg' => $this->get_load_average(),
            'php_memory' => memory_get_usage(true),
            'php_memory_peak' => memory_get_peak_usage(true)
        );
    }
    
    private function get_cpu_usage() {
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            $cpu_count = $this->get_cpu_count();
            return min(100, ($load[0] / $cpu_count) * 100);
        }
        
        if (PHP_OS_FAMILY === 'Linux' && is_readable('/proc/stat')) {
            $current = $this->read_cpu_stats();
            if ($this->last_cpu_stats && $current) {
                $diff_idle = $current['idle'] - $this->last_cpu_stats['idle'];
                $diff_total = $current['total'] - $this->last_cpu_stats['total'];
                
                $this->last_cpu_stats = $current;
                
                if ($diff_total > 0) {
                    return (1 - $diff_idle / $diff_total) * 100;
                }
            }
            $this->last_cpu_stats = $current;
        }
        
        return 0;
    }
    
    private function read_cpu_stats() {
        if (!is_readable('/proc/stat')) {
            return null;
        }
        
        $stat = file_get_contents('/proc/stat');
        if (preg_match('/^cpu\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)/m', $stat, $matches)) {
            $idle = $matches[4] + $matches[5];
            $total = array_sum(array_slice($matches, 1));
            return array('idle' => $idle, 'total' => $total);
        }
        
        return null;
    }
    
    private function get_cpu_count() {
        if (PHP_OS_FAMILY === 'Linux' && is_readable('/proc/cpuinfo')) {
            $cpuinfo = file_get_contents('/proc/cpuinfo');
            preg_match_all('/^processor/m', $cpuinfo, $matches);
            return count($matches[0]) ?: 1;
        }
        return 1;
    }
    
    private function get_memory_usage() {
        if (PHP_OS_FAMILY === 'Linux' && is_readable('/proc/meminfo')) {
            $meminfo = file_get_contents('/proc/meminfo');
            
            preg_match('/MemTotal:\s+(\d+)/', $meminfo, $total);
            preg_match('/MemAvailable:\s+(\d+)/', $meminfo, $available);
            
            if (isset($total[1]) && isset($available[1])) {
                return ($total[1] - $available[1]) * 1024;
            }
        }
        
        return memory_get_usage(true);
    }
    
    private function get_memory_percent() {
        if (PHP_OS_FAMILY === 'Linux' && is_readable('/proc/meminfo')) {
            $meminfo = file_get_contents('/proc/meminfo');
            
            preg_match('/MemTotal:\s+(\d+)/', $meminfo, $total);
            preg_match('/MemAvailable:\s+(\d+)/', $meminfo, $available);
            
            if (isset($total[1]) && isset($available[1]) && $total[1] > 0) {
                return (($total[1] - $available[1]) / $total[1]) * 100;
            }
        }
        
        $limit = ini_get('memory_limit');
        if ($limit !== '-1') {
            $limit_bytes = $this->parse_size($limit);
            if ($limit_bytes > 0) {
                return (memory_get_usage(true) / $limit_bytes) * 100;
            }
        }
        
        return 0;
    }
    
    private function parse_size($size) {
        $size = trim($size);
        $unit = strtolower(substr($size, -1));
        $value = (int) $size;
        
        switch ($unit) {
            case 'g':
                $value *= 1024 * 1024 * 1024;
                break;
            case 'm':
                $value *= 1024 * 1024;
                break;
            case 'k':
                $value *= 1024;
                break;
        }
        
        return $value;
    }
    
    private function get_disk_io() {
        $current = $this->read_disk_stats();
        
        if ($this->last_disk_stats && $current) {
            $read = max(0, $current['read'] - $this->last_disk_stats['read']);
            $write = max(0, $current['write'] - $this->last_disk_stats['write']);
            $this->last_disk_stats = $current;
            
            return array('read' => $read * 512, 'write' => $write * 512);
        }
        
        $this->last_disk_stats = $current;
        return array('read' => 0, 'write' => 0);
    }
    
    private function read_disk_stats() {
        if (!is_readable('/proc/diskstats')) {
            return null;
        }
        
        $stats = file_get_contents('/proc/diskstats');
        $total_read = 0;
        $total_write = 0;
        
        foreach (explode("\n", $stats) as $line) {
            $parts = preg_split('/\s+/', trim($line));
            if (count($parts) >= 14 && preg_match('/^(sd[a-z]|nvme\d+n\d+|vd[a-z])$/', $parts[2])) {
                $total_read += (int) $parts[5];
                $total_write += (int) $parts[9];
            }
        }
        
        return array('read' => $total_read, 'write' => $total_write);
    }
    
    private function get_load_average() {
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            return array(
                '1min' => $load[0],
                '5min' => $load[1],
                '15min' => $load[2]
            );
        }
        return array('1min' => 0, '5min' => 0, '15min' => 0);
    }
    
    public function get_timeline() {
        return $this->timeline;
    }
    
    public function get_summary() {
        if (empty($this->timeline)) {
            return array(
                'avg_cpu' => 0,
                'max_cpu' => 0,
                'avg_ram' => 0,
                'max_ram' => 0,
                'avg_disk_io' => 0,
                'total_disk_read' => 0,
                'total_disk_write' => 0
            );
        }
        
        $cpu_values = array_column($this->timeline, 'cpu');
        $ram_values = array_column($this->timeline, 'ram');
        $disk_read = array_column($this->timeline, 'disk_read');
        $disk_write = array_column($this->timeline, 'disk_write');
        
        return array(
            'avg_cpu' => array_sum($cpu_values) / count($cpu_values),
            'max_cpu' => max($cpu_values),
            'avg_ram' => array_sum($ram_values) / count($ram_values),
            'max_ram' => max($ram_values),
            'avg_disk_io' => (array_sum($disk_read) + array_sum($disk_write)) / count($this->timeline),
            'total_disk_read' => array_sum($disk_read),
            'total_disk_write' => array_sum($disk_write)
        );
    }
}
