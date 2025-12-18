<?php
if (!defined('ABSPATH')) {
    exit;
}

class WP_Cache_Benchmark_Admin_UI {
    
    public static function render_dashboard() {
        $results = WP_Cache_Benchmark_Database::get_results(array('limit' => 10, 'status' => 'completed'));
        $profiles = WP_Cache_Benchmark_Database::get_profiles();
        
        ?>
        <div class="wrap wp-cache-benchmark">
            <h1><?php echo esc_html__('Cache Benchmark Dashboard', 'wp-cache-benchmark'); ?></h1>
            
            <div class="wcb-dashboard-grid">
                <div class="wcb-card wcb-quick-stats">
                    <h2><?php echo esc_html__('Quick Stats', 'wp-cache-benchmark'); ?></h2>
                    <div class="wcb-stats-grid">
                        <div class="wcb-stat">
                            <span class="wcb-stat-value" id="total-benchmarks"><?php echo count($results); ?></span>
                            <span class="wcb-stat-label"><?php echo esc_html__('Total Benchmarks', 'wp-cache-benchmark'); ?></span>
                        </div>
                        <div class="wcb-stat">
                            <span class="wcb-stat-value" id="total-profiles"><?php echo count($profiles); ?></span>
                            <span class="wcb-stat-label"><?php echo esc_html__('Saved Profiles', 'wp-cache-benchmark'); ?></span>
                        </div>
                        <div class="wcb-stat">
                            <span class="wcb-stat-value" id="current-memory"><?php echo size_format(memory_get_usage(true)); ?></span>
                            <span class="wcb-stat-label"><?php echo esc_html__('Current Memory', 'wp-cache-benchmark'); ?></span>
                        </div>
                        <div class="wcb-stat">
                            <span class="wcb-stat-value" id="active-plugins"><?php echo count(get_option('active_plugins', array())); ?></span>
                            <span class="wcb-stat-label"><?php echo esc_html__('Active Plugins', 'wp-cache-benchmark'); ?></span>
                        </div>
                    </div>
                </div>
                
                <div class="wcb-card wcb-server-status">
                    <h2><?php echo esc_html__('Server Status', 'wp-cache-benchmark'); ?></h2>
                    <div class="wcb-server-metrics">
                        <div class="wcb-metric">
                            <label><?php echo esc_html__('CPU Usage', 'wp-cache-benchmark'); ?></label>
                            <div class="wcb-progress-bar">
                                <div class="wcb-progress" id="cpu-progress" style="width: 0%"></div>
                            </div>
                            <span class="wcb-metric-value" id="cpu-value">0%</span>
                        </div>
                        <div class="wcb-metric">
                            <label><?php echo esc_html__('Memory Usage', 'wp-cache-benchmark'); ?></label>
                            <div class="wcb-progress-bar">
                                <div class="wcb-progress" id="memory-progress" style="width: 0%"></div>
                            </div>
                            <span class="wcb-metric-value" id="memory-value">0%</span>
                        </div>
                    </div>
                </div>
                
                <div class="wcb-card wcb-quick-actions">
                    <h2><?php echo esc_html__('Quick Actions', 'wp-cache-benchmark'); ?></h2>
                    <div class="wcb-action-buttons">
                        <a href="<?php echo admin_url('admin.php?page=wp-cache-benchmark-run'); ?>" class="button button-primary button-hero">
                            <span class="dashicons dashicons-performance"></span>
                            <?php echo esc_html__('Run Benchmark', 'wp-cache-benchmark'); ?>
                        </a>
                        <a href="<?php echo admin_url('admin.php?page=wp-cache-benchmark-stress'); ?>" class="button button-secondary button-hero">
                            <span class="dashicons dashicons-superhero"></span>
                            <?php echo esc_html__('Stress Test', 'wp-cache-benchmark'); ?>
                        </a>
                        <a href="<?php echo admin_url('admin.php?page=wp-cache-benchmark-profiles'); ?>" class="button button-secondary button-hero">
                            <span class="dashicons dashicons-admin-generic"></span>
                            <?php echo esc_html__('Manage Profiles', 'wp-cache-benchmark'); ?>
                        </a>
                    </div>
                </div>
                
                <div class="wcb-card wcb-recent-results">
                    <h2><?php echo esc_html__('Recent Results', 'wp-cache-benchmark'); ?></h2>
                    <?php if (!empty($results)) : ?>
                        <table class="widefat striped">
                            <thead>
                                <tr>
                                    <th><?php echo esc_html__('Name', 'wp-cache-benchmark'); ?></th>
                                    <th><?php echo esc_html__('Type', 'wp-cache-benchmark'); ?></th>
                                    <th><?php echo esc_html__('Avg Response', 'wp-cache-benchmark'); ?></th>
                                    <th><?php echo esc_html__('Cache Hit Rate', 'wp-cache-benchmark'); ?></th>
                                    <th><?php echo esc_html__('Date', 'wp-cache-benchmark'); ?></th>
                                    <th><?php echo esc_html__('Actions', 'wp-cache-benchmark'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($results as $result) : ?>
                                    <tr>
                                        <td><?php echo esc_html($result->name); ?></td>
                                        <td><span class="wcb-badge wcb-badge-<?php echo esc_attr($result->test_type); ?>"><?php echo esc_html(ucfirst($result->test_type)); ?></span></td>
                                        <td><?php echo number_format($result->avg_response_time, 2); ?> ms</td>
                                        <td><?php echo number_format($result->cache_hit_rate, 1); ?>%</td>
                                        <td><?php echo date_i18n(get_option('date_format'), strtotime($result->created_at)); ?></td>
                                        <td>
                                            <a href="<?php echo admin_url('admin.php?page=wp-cache-benchmark-results&id=' . $result->id); ?>" class="button button-small">
                                                <?php echo esc_html__('View', 'wp-cache-benchmark'); ?>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else : ?>
                        <p class="wcb-no-data"><?php echo esc_html__('No benchmark results yet. Run your first benchmark to get started!', 'wp-cache-benchmark'); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    public static function render_profiles() {
        $profile_manager = new WP_Cache_Benchmark_Profile_Manager();
        $profiles = $profile_manager->get_profiles();
        $all_plugins = $profile_manager->get_all_plugins();
        
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : '';
        $profile_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        $editing_profile = null;
        
        if ($action === 'edit' && $profile_id > 0) {
            $editing_profile = $profile_manager->get_profile($profile_id);
        }
        
        ?>
        <div class="wrap wp-cache-benchmark">
            <h1><?php echo esc_html__('Cache Profiles', 'wp-cache-benchmark'); ?></h1>
            
            <div class="wcb-profiles-container">
                <div class="wcb-card wcb-profile-form">
                    <h2><?php echo $editing_profile ? esc_html__('Edit Profile', 'wp-cache-benchmark') : esc_html__('Create New Profile', 'wp-cache-benchmark'); ?></h2>
                    
                    <form id="wcb-profile-form" method="post">
                        <input type="hidden" name="profile_id" value="<?php echo $editing_profile ? $editing_profile->id : 0; ?>">
                        
                        <div class="wcb-form-row">
                            <label for="profile-name"><?php echo esc_html__('Profile Name', 'wp-cache-benchmark'); ?></label>
                            <input type="text" id="profile-name" name="profile_name" class="regular-text" required
                                   value="<?php echo $editing_profile ? esc_attr($editing_profile->name) : ''; ?>">
                        </div>
                        
                        <div class="wcb-form-row">
                            <label for="profile-description"><?php echo esc_html__('Description', 'wp-cache-benchmark'); ?></label>
                            <textarea id="profile-description" name="profile_description" class="large-text" rows="3"><?php echo $editing_profile ? esc_textarea($editing_profile->description) : ''; ?></textarea>
                        </div>
                        
                        <div class="wcb-form-row">
                            <label><?php echo esc_html__('Select Plugins to Test', 'wp-cache-benchmark'); ?></label>
                            <p class="description"><?php echo esc_html__('Choose which plugins should be active during the benchmark. Cache-related plugins are highlighted.', 'wp-cache-benchmark'); ?></p>
                            
                            <div class="wcb-plugin-filter">
                                <input type="text" id="plugin-search" placeholder="<?php echo esc_attr__('Search plugins...', 'wp-cache-benchmark'); ?>">
                                <label><input type="checkbox" id="show-cache-only"> <?php echo esc_html__('Show cache plugins only', 'wp-cache-benchmark'); ?></label>
                            </div>
                            
                            <div class="wcb-plugin-grid">
                                <?php 
                                $selected_plugins = $editing_profile ? (array) $editing_profile->plugins : array();
                                foreach ($all_plugins as $plugin) : 
                                ?>
                                    <div class="wcb-plugin-item <?php echo $plugin['is_cache_plugin'] ? 'wcb-cache-plugin' : ''; ?>" data-plugin="<?php echo esc_attr(strtolower($plugin['name'])); ?>">
                                        <label>
                                            <input type="checkbox" name="plugins[]" value="<?php echo esc_attr($plugin['file']); ?>"
                                                   <?php checked(in_array($plugin['file'], $selected_plugins)); ?>>
                                            <span class="wcb-plugin-name"><?php echo esc_html($plugin['name']); ?></span>
                                            <?php if ($plugin['is_cache_plugin']) : ?>
                                                <span class="wcb-badge wcb-badge-cache"><?php echo esc_html__('Cache', 'wp-cache-benchmark'); ?></span>
                                            <?php endif; ?>
                                            <?php if ($plugin['is_active']) : ?>
                                                <span class="wcb-badge wcb-badge-active"><?php echo esc_html__('Active', 'wp-cache-benchmark'); ?></span>
                                            <?php endif; ?>
                                        </label>
                                        <span class="wcb-plugin-version">v<?php echo esc_html($plugin['version']); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <div class="wcb-form-actions">
                            <button type="submit" class="button button-primary">
                                <?php echo $editing_profile ? esc_html__('Update Profile', 'wp-cache-benchmark') : esc_html__('Create Profile', 'wp-cache-benchmark'); ?>
                            </button>
                            <?php if ($editing_profile) : ?>
                                <a href="<?php echo admin_url('admin.php?page=wp-cache-benchmark-profiles'); ?>" class="button">
                                    <?php echo esc_html__('Cancel', 'wp-cache-benchmark'); ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
                
                <div class="wcb-card wcb-saved-profiles">
                    <h2><?php echo esc_html__('Saved Profiles', 'wp-cache-benchmark'); ?></h2>
                    
                    <?php if (!empty($profiles)) : ?>
                        <div class="wcb-profile-list">
                            <?php foreach ($profiles as $profile) : 
                                $plugins = maybe_unserialize($profile->plugins);
                            ?>
                                <div class="wcb-profile-item" data-id="<?php echo $profile->id; ?>">
                                    <div class="wcb-profile-info">
                                        <h3><?php echo esc_html($profile->name); ?></h3>
                                        <p><?php echo esc_html($profile->description); ?></p>
                                        <span class="wcb-plugin-count">
                                            <?php printf(
                                                esc_html(_n('%d plugin selected', '%d plugins selected', count($plugins), 'wp-cache-benchmark')),
                                                count($plugins)
                                            ); ?>
                                        </span>
                                    </div>
                                    <div class="wcb-profile-actions">
                                        <a href="<?php echo admin_url('admin.php?page=wp-cache-benchmark-run&profile=' . $profile->id); ?>" class="button button-primary">
                                            <?php echo esc_html__('Run Test', 'wp-cache-benchmark'); ?>
                                        </a>
                                        <a href="<?php echo admin_url('admin.php?page=wp-cache-benchmark-profiles&action=edit&id=' . $profile->id); ?>" class="button">
                                            <?php echo esc_html__('Edit', 'wp-cache-benchmark'); ?>
                                        </a>
                                        <button type="button" class="button wcb-delete-profile" data-id="<?php echo $profile->id; ?>">
                                            <?php echo esc_html__('Delete', 'wp-cache-benchmark'); ?>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else : ?>
                        <p class="wcb-no-data"><?php echo esc_html__('No profiles created yet. Create your first profile above!', 'wp-cache-benchmark'); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    public static function render_run_benchmark() {
        $profile_manager = new WP_Cache_Benchmark_Profile_Manager();
        $profiles = $profile_manager->get_profiles();
        $selected_profile = isset($_GET['profile']) ? intval($_GET['profile']) : 0;
        
        ?>
        <div class="wrap wp-cache-benchmark">
            <h1><?php echo esc_html__('Run Benchmark', 'wp-cache-benchmark'); ?></h1>
            
            <div class="wcb-benchmark-container">
                <div class="wcb-card wcb-benchmark-config">
                    <h2><?php echo esc_html__('Benchmark Configuration', 'wp-cache-benchmark'); ?></h2>
                    
                    <form id="wcb-benchmark-form">
                        <div class="wcb-form-row">
                            <label for="benchmark-name"><?php echo esc_html__('Benchmark Name', 'wp-cache-benchmark'); ?></label>
                            <input type="text" id="benchmark-name" name="name" class="regular-text" 
                                   placeholder="<?php echo esc_attr__('My Benchmark Test', 'wp-cache-benchmark'); ?>">
                        </div>
                        
                        <div class="wcb-form-row">
                            <label for="profile-select"><?php echo esc_html__('Cache Profile', 'wp-cache-benchmark'); ?></label>
                            <select id="profile-select" name="profile_id">
                                <option value="0"><?php echo esc_html__('-- Current Configuration --', 'wp-cache-benchmark'); ?></option>
                                <?php foreach ($profiles as $profile) : ?>
                                    <option value="<?php echo $profile->id; ?>" <?php selected($selected_profile, $profile->id); ?>>
                                        <?php echo esc_html($profile->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php echo esc_html__('Select a profile to test specific plugin configurations, or use current setup.', 'wp-cache-benchmark'); ?></p>
                        </div>
                        
                        <div class="wcb-form-row">
                            <label for="benchmark-duration"><?php echo esc_html__('Benchmark Duration', 'wp-cache-benchmark'); ?></label>
                            <div class="wcb-duration-options">
                                <label class="wcb-duration-option">
                                    <input type="radio" name="duration" value="quick" checked>
                                    <span class="wcb-duration-card">
                                        <strong><?php echo esc_html__('Quick', 'wp-cache-benchmark'); ?></strong>
                                        <span class="wcb-duration-desc"><?php echo esc_html__('~1 min, 100 posts, 10 iterations', 'wp-cache-benchmark'); ?></span>
                                    </span>
                                </label>
                                <label class="wcb-duration-option">
                                    <input type="radio" name="duration" value="2min">
                                    <span class="wcb-duration-card">
                                        <strong><?php echo esc_html__('2 Minutes', 'wp-cache-benchmark'); ?></strong>
                                        <span class="wcb-duration-desc"><?php echo esc_html__('1,000 posts, 50 iterations', 'wp-cache-benchmark'); ?></span>
                                    </span>
                                </label>
                                <label class="wcb-duration-option">
                                    <input type="radio" name="duration" value="5min">
                                    <span class="wcb-duration-card">
                                        <strong><?php echo esc_html__('5 Minutes', 'wp-cache-benchmark'); ?></strong>
                                        <span class="wcb-duration-desc"><?php echo esc_html__('2,500 posts, 100 iterations', 'wp-cache-benchmark'); ?></span>
                                    </span>
                                </label>
                                <label class="wcb-duration-option">
                                    <input type="radio" name="duration" value="until_stop">
                                    <span class="wcb-duration-card">
                                        <strong><?php echo esc_html__('Until I Stop It', 'wp-cache-benchmark'); ?></strong>
                                        <span class="wcb-duration-desc"><?php echo esc_html__('Max 10 min, 5,000 posts', 'wp-cache-benchmark'); ?></span>
                                    </span>
                                </label>
                            </div>
                        </div>
                        
                        <div class="wcb-form-row">
                            <label><?php echo esc_html__('Test Components', 'wp-cache-benchmark'); ?></label>
                            <p class="description"><?php echo esc_html__('Select which tests to run. Each test scales based on the selected duration.', 'wp-cache-benchmark'); ?></p>
                            <div class="wcb-checkbox-group">
                                <label>
                                    <input type="checkbox" name="create_posts" value="1" checked>
                                    <?php echo esc_html__('Create posts with metadata', 'wp-cache-benchmark'); ?>
                                    <span class="wcb-test-scale" data-test="posts"></span>
                                </label>
                                <label>
                                    <input type="checkbox" name="read_api" value="1" checked>
                                    <?php echo esc_html__('Read posts via API', 'wp-cache-benchmark'); ?>
                                    <span class="wcb-test-scale" data-test="api"></span>
                                </label>
                                <label>
                                    <input type="checkbox" name="reload_options" value="1" checked>
                                    <?php echo esc_html__('Reload options with cache flush', 'wp-cache-benchmark'); ?>
                                    <span class="wcb-test-scale" data-test="options"></span>
                                </label>
                                <label>
                                    <input type="checkbox" name="simulate_cron" value="1" checked>
                                    <?php echo esc_html__('Simulate cron (1MB file writes)', 'wp-cache-benchmark'); ?>
                                    <span class="wcb-test-scale" data-test="cron"></span>
                                </label>
                            </div>
                        </div>
                        
                        <div class="wcb-form-actions">
                            <button type="submit" class="button button-primary button-hero" id="start-benchmark">
                                <span class="dashicons dashicons-performance"></span>
                                <?php echo esc_html__('Start Benchmark', 'wp-cache-benchmark'); ?>
                            </button>
                            <button type="button" class="button button-secondary button-hero" id="stop-benchmark" style="display: none;">
                                <span class="dashicons dashicons-controls-pause"></span>
                                <?php echo esc_html__('Stop Benchmark', 'wp-cache-benchmark'); ?>
                            </button>
                        </div>
                    </form>
                </div>
                
                <div class="wcb-card wcb-benchmark-progress" style="display: none;">
                    <h2><?php echo esc_html__('Benchmark Progress', 'wp-cache-benchmark'); ?></h2>
                    
                    <div class="wcb-progress-container">
                        <div class="wcb-progress-bar-large">
                            <div class="wcb-progress" id="benchmark-progress"></div>
                        </div>
                        <div class="wcb-progress-info">
                            <span id="progress-text"><?php echo esc_html__('Preparing...', 'wp-cache-benchmark'); ?></span>
                            <span id="progress-percent">0%</span>
                        </div>
                    </div>
                    
                    <div class="wcb-live-metrics">
                        <div class="wcb-live-metric">
                            <span class="wcb-metric-label"><?php echo esc_html__('Current Iteration', 'wp-cache-benchmark'); ?></span>
                            <span class="wcb-metric-value" id="live-iteration">-</span>
                        </div>
                        <div class="wcb-live-metric">
                            <span class="wcb-metric-label"><?php echo esc_html__('Response Time', 'wp-cache-benchmark'); ?></span>
                            <span class="wcb-metric-value" id="live-response">- ms</span>
                        </div>
                        <div class="wcb-live-metric">
                            <span class="wcb-metric-label"><?php echo esc_html__('Memory Usage', 'wp-cache-benchmark'); ?></span>
                            <span class="wcb-metric-value" id="live-memory">-</span>
                        </div>
                        <div class="wcb-live-metric">
                            <span class="wcb-metric-label"><?php echo esc_html__('CPU Usage', 'wp-cache-benchmark'); ?></span>
                            <span class="wcb-metric-value" id="live-cpu">-%</span>
                        </div>
                    </div>
                    
                    <div class="wcb-live-chart">
                        <canvas id="live-chart" height="200"></canvas>
                    </div>
                </div>
                
                <div class="wcb-card wcb-benchmark-log" style="display: none;">
                    <h2>
                        <?php echo esc_html__('Benchmark Log', 'wp-cache-benchmark'); ?>
                        <span class="wcb-log-count" id="log-count">0</span>
                    </h2>
                    <div class="wcb-log-filters">
                        <label><input type="checkbox" id="filter-info" checked> Info</label>
                        <label><input type="checkbox" id="filter-slow" checked> Slow</label>
                        <label><input type="checkbox" id="filter-warning" checked> Warnings</label>
                        <label><input type="checkbox" id="filter-success" checked> Success</label>
                    </div>
                    <div class="wcb-log-container" id="benchmark-log">
                        <div class="wcb-log-entry wcb-log-info">
                            <span class="wcb-log-time">0.0s</span>
                            <span class="wcb-log-message">Waiting for benchmark to start...</span>
                        </div>
                    </div>
                </div>
                
                <div class="wcb-card wcb-benchmark-results" style="display: none;">
                    <h2><?php echo esc_html__('Results', 'wp-cache-benchmark'); ?></h2>
                    <div id="benchmark-results-content"></div>
                </div>
                
                <div class="wcb-card wcb-benchmark-report" style="display: none;">
                    <h2><?php echo esc_html__('Performance Report', 'wp-cache-benchmark'); ?></h2>
                    <div id="benchmark-report-content"></div>
                </div>
            </div>
        </div>
        <?php
    }
    
    public static function render_stress_test() {
        $profile_manager = new WP_Cache_Benchmark_Profile_Manager();
        $profiles = $profile_manager->get_profiles();
        
        ?>
        <div class="wrap wp-cache-benchmark">
            <h1><?php echo esc_html__('Stress Test', 'wp-cache-benchmark'); ?></h1>
            
            <div class="wcb-stress-container">
                <div class="wcb-card wcb-stress-config">
                    <h2><?php echo esc_html__('Stress Test Configuration', 'wp-cache-benchmark'); ?></h2>
                    
                    <div class="wcb-notice wcb-notice-warning">
                        <p><strong><?php echo esc_html__('Warning:', 'wp-cache-benchmark'); ?></strong> 
                        <?php echo esc_html__('Stress tests are intensive and may temporarily affect site performance. All test data will be automatically cleaned up after the test.', 'wp-cache-benchmark'); ?></p>
                    </div>
                    
                    <form id="wcb-stress-form">
                        <div class="wcb-form-row">
                            <label for="stress-name"><?php echo esc_html__('Test Name', 'wp-cache-benchmark'); ?></label>
                            <input type="text" id="stress-name" name="name" class="regular-text" 
                                   placeholder="<?php echo esc_attr__('My Stress Test', 'wp-cache-benchmark'); ?>">
                        </div>
                        
                        <div class="wcb-form-row">
                            <label for="stress-profile"><?php echo esc_html__('Cache Profile', 'wp-cache-benchmark'); ?></label>
                            <select id="stress-profile" name="profile_id">
                                <option value="0"><?php echo esc_html__('-- Current Configuration --', 'wp-cache-benchmark'); ?></option>
                                <?php foreach ($profiles as $profile) : ?>
                                    <option value="<?php echo $profile->id; ?>">
                                        <?php echo esc_html($profile->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="wcb-form-row">
                            <label for="stress-duration"><?php echo esc_html__('Test Duration (seconds)', 'wp-cache-benchmark'); ?></label>
                            <input type="number" id="stress-duration" name="duration" value="60" min="30" max="300" step="30">
                        </div>
                        
                        <div class="wcb-form-row">
                            <label><?php echo esc_html__('Test Components', 'wp-cache-benchmark'); ?></label>
                            <div class="wcb-checkbox-group">
                                <label>
                                    <input type="checkbox" name="create_posts" value="1" checked>
                                    <?php echo esc_html__('Create 100 posts with metadata', 'wp-cache-benchmark'); ?>
                                </label>
                                <label>
                                    <input type="checkbox" name="read_api" value="1" checked>
                                    <?php echo esc_html__('Read posts via API', 'wp-cache-benchmark'); ?>
                                </label>
                                <label>
                                    <input type="checkbox" name="reload_options" value="1" checked>
                                    <?php echo esc_html__('Reload options with cache flush', 'wp-cache-benchmark'); ?>
                                </label>
                                <label>
                                    <input type="checkbox" name="simulate_cron" value="1" checked>
                                    <?php echo esc_html__('Simulate cron (1MB file writes)', 'wp-cache-benchmark'); ?>
                                </label>
                            </div>
                        </div>
                        
                        <div class="wcb-form-actions">
                            <button type="submit" class="button button-primary button-hero" id="start-stress-test">
                                <span class="dashicons dashicons-superhero"></span>
                                <?php echo esc_html__('Start Stress Test', 'wp-cache-benchmark'); ?>
                            </button>
                        </div>
                    </form>
                </div>
                
                <div class="wcb-card wcb-stress-progress" style="display: none;">
                    <h2><?php echo esc_html__('Stress Test Progress', 'wp-cache-benchmark'); ?></h2>
                    
                    <div class="wcb-stress-phases">
                        <div class="wcb-phase" id="phase-posts">
                            <span class="wcb-phase-icon">&#128221;</span>
                            <span class="wcb-phase-name"><?php echo esc_html__('Creating Posts', 'wp-cache-benchmark'); ?></span>
                            <span class="wcb-phase-status"><?php echo esc_html__('Pending', 'wp-cache-benchmark'); ?></span>
                        </div>
                        <div class="wcb-phase" id="phase-api">
                            <span class="wcb-phase-icon">&#128279;</span>
                            <span class="wcb-phase-name"><?php echo esc_html__('API Reads', 'wp-cache-benchmark'); ?></span>
                            <span class="wcb-phase-status"><?php echo esc_html__('Pending', 'wp-cache-benchmark'); ?></span>
                        </div>
                        <div class="wcb-phase" id="phase-options">
                            <span class="wcb-phase-icon">&#9881;</span>
                            <span class="wcb-phase-name"><?php echo esc_html__('Options Reload', 'wp-cache-benchmark'); ?></span>
                            <span class="wcb-phase-status"><?php echo esc_html__('Pending', 'wp-cache-benchmark'); ?></span>
                        </div>
                        <div class="wcb-phase" id="phase-cron">
                            <span class="wcb-phase-icon">&#128190;</span>
                            <span class="wcb-phase-name"><?php echo esc_html__('Cron Simulation', 'wp-cache-benchmark'); ?></span>
                            <span class="wcb-phase-status"><?php echo esc_html__('Pending', 'wp-cache-benchmark'); ?></span>
                        </div>
                        <div class="wcb-phase" id="phase-cleanup">
                            <span class="wcb-phase-icon">&#128465;</span>
                            <span class="wcb-phase-name"><?php echo esc_html__('Cleanup', 'wp-cache-benchmark'); ?></span>
                            <span class="wcb-phase-status"><?php echo esc_html__('Pending', 'wp-cache-benchmark'); ?></span>
                        </div>
                    </div>
                    
                    <div class="wcb-live-chart">
                        <canvas id="stress-chart" height="250"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    public static function render_compare() {
        $results = WP_Cache_Benchmark_Database::get_results(array('limit' => 50, 'status' => 'completed'));
        
        ?>
        <div class="wrap wp-cache-benchmark">
            <h1><?php echo esc_html__('Compare Benchmarks', 'wp-cache-benchmark'); ?></h1>
            
            <div class="wcb-compare-container">
                <div class="wcb-card wcb-compare-selector">
                    <h2><?php echo esc_html__('Select Benchmarks to Compare', 'wp-cache-benchmark'); ?></h2>
                    <p class="description"><?php echo esc_html__('Select 2-5 benchmark results to compare side by side.', 'wp-cache-benchmark'); ?></p>
                    
                    <?php if (!empty($results)) : ?>
                        <form id="wcb-compare-form">
                            <div class="wcb-results-grid">
                                <?php foreach ($results as $result) : ?>
                                    <div class="wcb-result-item">
                                        <label>
                                            <input type="checkbox" name="result_ids[]" value="<?php echo $result->id; ?>">
                                            <div class="wcb-result-info">
                                                <span class="wcb-result-name"><?php echo esc_html($result->name); ?></span>
                                                <span class="wcb-result-meta">
                                                    <?php echo esc_html(ucfirst($result->test_type)); ?> | 
                                                    <?php echo number_format($result->avg_response_time, 2); ?> ms | 
                                                    <?php echo date_i18n(get_option('date_format'), strtotime($result->created_at)); ?>
                                                </span>
                                            </div>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="wcb-form-actions">
                                <button type="submit" class="button button-primary button-hero" id="compare-benchmarks">
                                    <span class="dashicons dashicons-chart-bar"></span>
                                    <?php echo esc_html__('Compare Selected', 'wp-cache-benchmark'); ?>
                                </button>
                            </div>
                        </form>
                    <?php else : ?>
                        <p class="wcb-no-data"><?php echo esc_html__('No completed benchmarks available for comparison. Run some benchmarks first!', 'wp-cache-benchmark'); ?></p>
                    <?php endif; ?>
                </div>
                
                <div class="wcb-card wcb-comparison-results" style="display: none;">
                    <div class="wcb-comparison-header">
                        <h2><?php echo esc_html__('Comparison Results', 'wp-cache-benchmark'); ?></h2>
                        <div class="wcb-export-buttons">
                            <button class="button" id="export-json">
                                <span class="dashicons dashicons-download"></span> JSON
                            </button>
                            <button class="button" id="export-csv">
                                <span class="dashicons dashicons-download"></span> CSV
                            </button>
                        </div>
                    </div>
                    
                    <div class="wcb-comparison-tabs">
                        <button class="wcb-tab active" data-tab="summary"><?php echo esc_html__('Summary', 'wp-cache-benchmark'); ?></button>
                        <button class="wcb-tab" data-tab="response"><?php echo esc_html__('Response Time', 'wp-cache-benchmark'); ?></button>
                        <button class="wcb-tab" data-tab="memory"><?php echo esc_html__('Memory', 'wp-cache-benchmark'); ?></button>
                        <button class="wcb-tab" data-tab="cpu"><?php echo esc_html__('CPU', 'wp-cache-benchmark'); ?></button>
                        <button class="wcb-tab" data-tab="cache"><?php echo esc_html__('Cache', 'wp-cache-benchmark'); ?></button>
                        <button class="wcb-tab" data-tab="database"><?php echo esc_html__('Database', 'wp-cache-benchmark'); ?></button>
                    </div>
                    
                    <div class="wcb-tab-content active" id="tab-summary">
                        <div id="comparison-summary-table"></div>
                    </div>
                    
                    <div class="wcb-tab-content" id="tab-response">
                        <canvas id="response-time-chart" height="300"></canvas>
                        <div id="response-stats"></div>
                    </div>
                    
                    <div class="wcb-tab-content" id="tab-memory">
                        <canvas id="memory-chart" height="300"></canvas>
                        <div id="memory-stats"></div>
                    </div>
                    
                    <div class="wcb-tab-content" id="tab-cpu">
                        <canvas id="cpu-chart" height="300"></canvas>
                        <div id="cpu-stats"></div>
                    </div>
                    
                    <div class="wcb-tab-content" id="tab-cache">
                        <div class="wcb-cache-charts" id="cache-charts"></div>
                    </div>
                    
                    <div class="wcb-tab-content" id="tab-database">
                        <canvas id="database-chart" height="300"></canvas>
                        <div id="database-stats"></div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    public static function render_results() {
        $result_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        
        if ($result_id > 0) {
            self::render_single_result($result_id);
            return;
        }
        
        $results = WP_Cache_Benchmark_Database::get_results(array('limit' => 50));
        
        ?>
        <div class="wrap wp-cache-benchmark">
            <h1><?php echo esc_html__('Benchmark Results', 'wp-cache-benchmark'); ?></h1>
            
            <div class="wcb-card">
                <?php if (!empty($results)) : ?>
                    <table class="widefat striped wcb-results-table">
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="select-all-results"></th>
                                <th><?php echo esc_html__('Name', 'wp-cache-benchmark'); ?></th>
                                <th><?php echo esc_html__('Type', 'wp-cache-benchmark'); ?></th>
                                <th><?php echo esc_html__('Status', 'wp-cache-benchmark'); ?></th>
                                <th><?php echo esc_html__('Iterations', 'wp-cache-benchmark'); ?></th>
                                <th><?php echo esc_html__('Avg Response', 'wp-cache-benchmark'); ?></th>
                                <th><?php echo esc_html__('Peak Memory', 'wp-cache-benchmark'); ?></th>
                                <th><?php echo esc_html__('Cache Hit Rate', 'wp-cache-benchmark'); ?></th>
                                <th><?php echo esc_html__('Date', 'wp-cache-benchmark'); ?></th>
                                <th><?php echo esc_html__('Actions', 'wp-cache-benchmark'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($results as $result) : ?>
                                <tr>
                                    <td><input type="checkbox" class="result-checkbox" value="<?php echo $result->id; ?>"></td>
                                    <td><?php echo esc_html($result->name); ?></td>
                                    <td><span class="wcb-badge wcb-badge-<?php echo esc_attr($result->test_type); ?>"><?php echo esc_html(ucfirst($result->test_type)); ?></span></td>
                                    <td><span class="wcb-status wcb-status-<?php echo esc_attr($result->status); ?>"><?php echo esc_html(ucfirst($result->status)); ?></span></td>
                                    <td><?php echo esc_html($result->iterations); ?></td>
                                    <td><?php echo $result->avg_response_time ? number_format($result->avg_response_time, 2) . ' ms' : '-'; ?></td>
                                    <td><?php echo $result->peak_memory_usage ? size_format($result->peak_memory_usage) : '-'; ?></td>
                                    <td><?php echo $result->cache_hit_rate !== null ? number_format($result->cache_hit_rate, 1) . '%' : '-'; ?></td>
                                    <td><?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($result->created_at)); ?></td>
                                    <td>
                                        <a href="<?php echo admin_url('admin.php?page=wp-cache-benchmark-results&id=' . $result->id); ?>" class="button button-small">
                                            <?php echo esc_html__('View', 'wp-cache-benchmark'); ?>
                                        </a>
                                        <button type="button" class="button button-small wcb-delete-result" data-id="<?php echo $result->id; ?>">
                                            <?php echo esc_html__('Delete', 'wp-cache-benchmark'); ?>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <div class="wcb-bulk-actions">
                        <button class="button" id="compare-selected"><?php echo esc_html__('Compare Selected', 'wp-cache-benchmark'); ?></button>
                        <button class="button" id="delete-selected"><?php echo esc_html__('Delete Selected', 'wp-cache-benchmark'); ?></button>
                    </div>
                <?php else : ?>
                    <p class="wcb-no-data"><?php echo esc_html__('No benchmark results yet.', 'wp-cache-benchmark'); ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    private static function render_single_result($result_id) {
        $result = WP_Cache_Benchmark_Database::get_result($result_id);
        
        if (!$result) {
            echo '<div class="wrap"><div class="notice notice-error"><p>' . esc_html__('Result not found.', 'wp-cache-benchmark') . '</p></div></div>';
            return;
        }
        
        $metrics = WP_Cache_Benchmark_Database::get_metrics($result_id);
        $raw_data = maybe_unserialize($result->raw_data);
        
        ?>
        <div class="wrap wp-cache-benchmark">
            <h1>
                <?php echo esc_html($result->name); ?>
                <a href="<?php echo admin_url('admin.php?page=wp-cache-benchmark-results'); ?>" class="page-title-action"><?php echo esc_html__('Back to Results', 'wp-cache-benchmark'); ?></a>
            </h1>
            
            <div class="wcb-result-detail">
                <div class="wcb-card wcb-result-summary">
                    <h2><?php echo esc_html__('Summary', 'wp-cache-benchmark'); ?></h2>
                    
                    <div class="wcb-summary-grid">
                        <div class="wcb-summary-item">
                            <span class="wcb-summary-label"><?php echo esc_html__('Test Type', 'wp-cache-benchmark'); ?></span>
                            <span class="wcb-summary-value"><?php echo esc_html(ucfirst($result->test_type)); ?></span>
                        </div>
                        <div class="wcb-summary-item">
                            <span class="wcb-summary-label"><?php echo esc_html__('Status', 'wp-cache-benchmark'); ?></span>
                            <span class="wcb-summary-value wcb-status-<?php echo esc_attr($result->status); ?>"><?php echo esc_html(ucfirst($result->status)); ?></span>
                        </div>
                        <div class="wcb-summary-item">
                            <span class="wcb-summary-label"><?php echo esc_html__('Iterations', 'wp-cache-benchmark'); ?></span>
                            <span class="wcb-summary-value"><?php echo esc_html($result->iterations); ?></span>
                        </div>
                        <div class="wcb-summary-item">
                            <span class="wcb-summary-label"><?php echo esc_html__('Duration', 'wp-cache-benchmark'); ?></span>
                            <span class="wcb-summary-value">
                                <?php 
                                if ($result->started_at && $result->completed_at) {
                                    $duration = strtotime($result->completed_at) - strtotime($result->started_at);
                                    echo esc_html(human_readable_duration($duration . ' seconds'));
                                } else {
                                    echo '-';
                                }
                                ?>
                            </span>
                        </div>
                    </div>
                </div>
                
                <div class="wcb-metrics-grid">
                    <div class="wcb-card wcb-metric-card">
                        <h3><?php echo esc_html__('Response Time', 'wp-cache-benchmark'); ?></h3>
                        <div class="wcb-metric-value"><?php echo number_format($result->avg_response_time, 2); ?> <small>ms avg</small></div>
                        <div class="wcb-metric-details">
                            <span>Min: <?php echo number_format($result->min_response_time, 2); ?> ms</span>
                            <span>Max: <?php echo number_format($result->max_response_time, 2); ?> ms</span>
                        </div>
                    </div>
                    
                    <div class="wcb-card wcb-metric-card">
                        <h3><?php echo esc_html__('Memory Usage', 'wp-cache-benchmark'); ?></h3>
                        <div class="wcb-metric-value"><?php echo size_format($result->avg_memory_usage); ?> <small>avg</small></div>
                        <div class="wcb-metric-details">
                            <span>Peak: <?php echo size_format($result->peak_memory_usage); ?></span>
                        </div>
                    </div>
                    
                    <div class="wcb-card wcb-metric-card">
                        <h3><?php echo esc_html__('Cache Performance', 'wp-cache-benchmark'); ?></h3>
                        <div class="wcb-metric-value"><?php echo number_format($result->cache_hit_rate, 1); ?>% <small>hit rate</small></div>
                        <div class="wcb-metric-details">
                            <span>Hits: <?php echo number_format($result->cache_hits); ?></span>
                            <span>Misses: <?php echo number_format($result->cache_misses); ?></span>
                        </div>
                    </div>
                    
                    <div class="wcb-card wcb-metric-card">
                        <h3><?php echo esc_html__('Database', 'wp-cache-benchmark'); ?></h3>
                        <div class="wcb-metric-value"><?php echo number_format($result->avg_db_queries, 1); ?> <small>queries/iter</small></div>
                        <div class="wcb-metric-details">
                            <span>Total: <?php echo number_format($result->total_db_queries); ?></span>
                        </div>
                    </div>
                    
                    <div class="wcb-card wcb-metric-card">
                        <h3><?php echo esc_html__('CPU Usage', 'wp-cache-benchmark'); ?></h3>
                        <div class="wcb-metric-value"><?php echo number_format($result->avg_cpu_usage, 1); ?>%</div>
                    </div>
                    
                    <div class="wcb-card wcb-metric-card">
                        <h3><?php echo esc_html__('Disk I/O', 'wp-cache-benchmark'); ?></h3>
                        <div class="wcb-metric-value"><?php echo size_format($result->avg_disk_io); ?></div>
                    </div>
                </div>
                
                <div class="wcb-card wcb-result-charts">
                    <h2><?php echo esc_html__('Performance Charts', 'wp-cache-benchmark'); ?></h2>
                    
                    <div class="wcb-charts-grid">
                        <div class="wcb-chart-container">
                            <h3><?php echo esc_html__('Response Time per Iteration', 'wp-cache-benchmark'); ?></h3>
                            <canvas id="result-response-chart" height="250"></canvas>
                        </div>
                        
                        <div class="wcb-chart-container">
                            <h3><?php echo esc_html__('Memory Usage per Iteration', 'wp-cache-benchmark'); ?></h3>
                            <canvas id="result-memory-chart" height="250"></canvas>
                        </div>
                        
                        <div class="wcb-chart-container">
                            <h3><?php echo esc_html__('CPU Usage Over Time', 'wp-cache-benchmark'); ?></h3>
                            <canvas id="result-cpu-chart" height="250"></canvas>
                        </div>
                        
                        <div class="wcb-chart-container">
                            <h3><?php echo esc_html__('Cache Hit/Miss Distribution', 'wp-cache-benchmark'); ?></h3>
                            <canvas id="result-cache-chart" height="250"></canvas>
                        </div>
                    </div>
                </div>
                
                <div class="wcb-card wcb-export-section">
                    <h2><?php echo esc_html__('Export Data', 'wp-cache-benchmark'); ?></h2>
                    <div class="wcb-export-buttons">
                        <a href="<?php echo wp_nonce_url(admin_url('admin-ajax.php?action=wcb_export&id=' . $result_id . '&format=json'), 'wcb_export'); ?>" class="button">
                            <span class="dashicons dashicons-download"></span> <?php echo esc_html__('Export JSON', 'wp-cache-benchmark'); ?>
                        </a>
                        <a href="<?php echo wp_nonce_url(admin_url('admin-ajax.php?action=wcb_export&id=' . $result_id . '&format=csv'), 'wcb_export'); ?>" class="button">
                            <span class="dashicons dashicons-download"></span> <?php echo esc_html__('Export CSV', 'wp-cache-benchmark'); ?>
                        </a>
                    </div>
                </div>
            </div>
            
            <script>
                var wcbResultMetrics = <?php echo json_encode($metrics); ?>;
                var wcbResultData = <?php echo json_encode($result); ?>;
            </script>
        </div>
        <?php
    }
}
