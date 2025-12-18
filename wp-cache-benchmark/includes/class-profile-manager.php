<?php
if (!defined('ABSPATH')) {
    exit;
}

class WP_Cache_Benchmark_Profile_Manager {
    
    private $original_active_plugins = array();
    
    public function get_all_plugins() {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        
        $all_plugins = get_plugins();
        $active_plugins = get_option('active_plugins', array());
        
        $plugins = array();
        foreach ($all_plugins as $plugin_file => $plugin_data) {
            if (strpos($plugin_file, 'wp-cache-benchmark') !== false) {
                continue;
            }
            
            $plugins[] = array(
                'file' => $plugin_file,
                'name' => $plugin_data['Name'],
                'version' => $plugin_data['Version'],
                'description' => $plugin_data['Description'],
                'author' => $plugin_data['Author'],
                'is_active' => in_array($plugin_file, $active_plugins),
                'is_cache_plugin' => $this->is_cache_plugin($plugin_data['Name'], $plugin_file)
            );
        }
        
        usort($plugins, function($a, $b) {
            if ($a['is_cache_plugin'] !== $b['is_cache_plugin']) {
                return $a['is_cache_plugin'] ? -1 : 1;
            }
            return strcasecmp($a['name'], $b['name']);
        });
        
        return $plugins;
    }
    
    private function is_cache_plugin($name, $file) {
        $cache_keywords = array(
            'cache', 'caching', 'redis', 'memcached', 'varnish', 'cdn',
            'w3 total', 'wp super cache', 'wp fastest', 'litespeed',
            'autoptimize', 'wp rocket', 'breeze', 'swift performance',
            'hummingbird', 'comet cache', 'cache enabler', 'wp optimize'
        );
        
        $name_lower = strtolower($name);
        $file_lower = strtolower($file);
        
        foreach ($cache_keywords as $keyword) {
            if (strpos($name_lower, $keyword) !== false || strpos($file_lower, $keyword) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    public function save_original_state() {
        $this->original_active_plugins = get_option('active_plugins', array());
        update_option('wp_cache_benchmark_original_plugins', $this->original_active_plugins);
        return $this->original_active_plugins;
    }
    
    public function restore_original_state() {
        $original = get_option('wp_cache_benchmark_original_plugins', array());
        
        if (empty($original)) {
            return false;
        }
        
        $current_active = get_option('active_plugins', array());
        
        $to_deactivate = array_diff($current_active, $original);
        foreach ($to_deactivate as $plugin) {
            if (strpos($plugin, 'wp-cache-benchmark') === false) {
                deactivate_plugins($plugin, true);
            }
        }
        
        $to_activate = array_diff($original, $current_active);
        foreach ($to_activate as $plugin) {
            activate_plugin($plugin, '', false, true);
        }
        
        delete_option('wp_cache_benchmark_original_plugins');
        
        return true;
    }
    
    public function activate_profile_plugins($plugins) {
        if (!is_array($plugins)) {
            $plugins = maybe_unserialize($plugins);
        }
        
        if (empty($plugins)) {
            return false;
        }
        
        $this->save_original_state();
        
        $current_active = get_option('active_plugins', array());
        
        foreach ($current_active as $plugin) {
            if (strpos($plugin, 'wp-cache-benchmark') === false && !in_array($plugin, $plugins)) {
                deactivate_plugins($plugin, true);
            }
        }
        
        foreach ($plugins as $plugin) {
            if (!is_plugin_active($plugin)) {
                activate_plugin($plugin, '', false, true);
            }
        }
        
        return true;
    }
    
    public function get_cache_plugins() {
        $all_plugins = $this->get_all_plugins();
        return array_filter($all_plugins, function($plugin) {
            return $plugin['is_cache_plugin'];
        });
    }
    
    public function create_profile($name, $description, $plugins, $settings = array()) {
        return WP_Cache_Benchmark_Database::save_profile(array(
            'name' => $name,
            'description' => $description,
            'plugins' => $plugins,
            'settings' => $settings
        ));
    }
    
    public function update_profile($id, $name, $description, $plugins, $settings = array()) {
        return WP_Cache_Benchmark_Database::save_profile(array(
            'id' => $id,
            'name' => $name,
            'description' => $description,
            'plugins' => $plugins,
            'settings' => $settings
        ));
    }
    
    public function delete_profile($id) {
        return WP_Cache_Benchmark_Database::delete_profile($id);
    }
    
    public function get_profiles() {
        return WP_Cache_Benchmark_Database::get_profiles();
    }
    
    public function get_profile($id) {
        $profile = WP_Cache_Benchmark_Database::get_profile($id);
        if ($profile) {
            $profile->plugins = maybe_unserialize($profile->plugins);
            $profile->settings = maybe_unserialize($profile->settings);
        }
        return $profile;
    }
}
