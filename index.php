<?php
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';

$sample_profiles = [
    ['id' => 1, 'name' => 'No Cache (Baseline)', 'description' => 'Test without any caching plugins', 'plugins' => [], 'plugin_count' => 0],
    ['id' => 2, 'name' => 'W3 Total Cache', 'description' => 'Testing with W3 Total Cache enabled', 'plugins' => ['w3-total-cache/w3-total-cache.php'], 'plugin_count' => 1],
    ['id' => 3, 'name' => 'WP Super Cache', 'description' => 'Testing with WP Super Cache enabled', 'plugins' => ['wp-super-cache/wp-cache.php'], 'plugin_count' => 1],
    ['id' => 4, 'name' => 'Redis Object Cache', 'description' => 'Testing with Redis Object Cache', 'plugins' => ['redis-cache/redis-cache.php'], 'plugin_count' => 1],
    ['id' => 5, 'name' => 'Full Stack Cache', 'description' => 'W3TC + Redis + CDN', 'plugins' => ['w3-total-cache/w3-total-cache.php', 'redis-cache/redis-cache.php'], 'plugin_count' => 2],
];

$sample_results = [
    ['id' => 1, 'name' => 'Baseline - No Cache', 'test_type' => 'standard', 'status' => 'completed', 'iterations' => 20, 'avg_response_time' => 245.32, 'min_response_time' => 189.12, 'max_response_time' => 398.45, 'peak_memory' => 67108864, 'cache_hit_rate' => 0, 'cache_hits' => 0, 'cache_misses' => 1580, 'avg_cpu' => 42.3, 'avg_db_queries' => 156, 'created_at' => '2024-12-17 10:30:00'],
    ['id' => 2, 'name' => 'W3 Total Cache Test', 'test_type' => 'standard', 'status' => 'completed', 'iterations' => 20, 'avg_response_time' => 89.45, 'min_response_time' => 52.33, 'max_response_time' => 178.92, 'peak_memory' => 52428800, 'cache_hit_rate' => 78.5, 'cache_hits' => 1240, 'cache_misses' => 340, 'avg_cpu' => 28.7, 'avg_db_queries' => 42, 'created_at' => '2024-12-17 11:15:00'],
    ['id' => 3, 'name' => 'Redis Cache Test', 'test_type' => 'standard', 'status' => 'completed', 'iterations' => 20, 'avg_response_time' => 67.89, 'min_response_time' => 41.22, 'max_response_time' => 142.56, 'peak_memory' => 48234496, 'cache_hit_rate' => 92.3, 'cache_hits' => 1458, 'cache_misses' => 122, 'avg_cpu' => 22.1, 'avg_db_queries' => 18, 'created_at' => '2024-12-17 14:00:00'],
    ['id' => 4, 'name' => 'Full Stack Stress Test', 'test_type' => 'stress', 'status' => 'completed', 'iterations' => 60, 'avg_response_time' => 156.78, 'min_response_time' => 78.45, 'max_response_time' => 892.34, 'peak_memory' => 134217728, 'cache_hit_rate' => 85.2, 'cache_hits' => 4521, 'cache_misses' => 789, 'avg_cpu' => 67.8, 'avg_db_queries' => 89, 'created_at' => '2024-12-17 16:45:00'],
    ['id' => 5, 'name' => 'WP Super Cache Test', 'test_type' => 'standard', 'status' => 'completed', 'iterations' => 20, 'avg_response_time' => 112.56, 'min_response_time' => 67.89, 'max_response_time' => 234.12, 'peak_memory' => 58720256, 'cache_hit_rate' => 71.4, 'cache_hits' => 1128, 'cache_misses' => 452, 'avg_cpu' => 31.5, 'avg_db_queries' => 56, 'created_at' => '2024-12-18 09:20:00'],
];

$sample_plugins = [
    ['file' => 'w3-total-cache/w3-total-cache.php', 'name' => 'W3 Total Cache', 'version' => '2.5.1', 'is_cache' => true, 'is_active' => true],
    ['file' => 'wp-super-cache/wp-cache.php', 'name' => 'WP Super Cache', 'version' => '1.9.4', 'is_cache' => true, 'is_active' => false],
    ['file' => 'redis-cache/redis-cache.php', 'name' => 'Redis Object Cache', 'version' => '2.4.3', 'is_cache' => true, 'is_active' => true],
    ['file' => 'litespeed-cache/litespeed-cache.php', 'name' => 'LiteSpeed Cache', 'version' => '6.0.0.1', 'is_cache' => true, 'is_active' => false],
    ['file' => 'autoptimize/autoptimize.php', 'name' => 'Autoptimize', 'version' => '3.1.10', 'is_cache' => true, 'is_active' => false],
    ['file' => 'woocommerce/woocommerce.php', 'name' => 'WooCommerce', 'version' => '8.4.0', 'is_cache' => false, 'is_active' => true],
    ['file' => 'elementor/elementor.php', 'name' => 'Elementor', 'version' => '3.18.3', 'is_cache' => false, 'is_active' => true],
    ['file' => 'contact-form-7/wp-contact-form-7.php', 'name' => 'Contact Form 7', 'version' => '5.8.4', 'is_cache' => false, 'is_active' => true],
    ['file' => 'yoast-seo/yoast-seo.php', 'name' => 'Yoast SEO', 'version' => '21.7', 'is_cache' => false, 'is_active' => true],
    ['file' => 'akismet/akismet.php', 'name' => 'Akismet Anti-Spam', 'version' => '5.3', 'is_cache' => false, 'is_active' => false],
];

function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB'];
    for ($i = 0; $bytes >= 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    return round($bytes, $precision) . ' ' . $units[$i];
}

function getServerStats() {
    $cpu = function_exists('sys_getloadavg') ? sys_getloadavg()[0] * 10 : rand(15, 45);
    $memory = memory_get_usage(true);
    $memoryPercent = min(100, ($memory / (256 * 1024 * 1024)) * 100);
    
    return [
        'cpu' => min(100, $cpu),
        'memory' => $memory,
        'memory_percent' => $memoryPercent
    ];
}

$stats = getServerStats();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WP Cache Benchmark - Demo</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <style>
        :root {
            --primary: #2271b1;
            --primary-dark: #135e96;
            --success: #2e7d32;
            --warning: #e65100;
            --danger: #c62828;
            --bg: #f0f0f1;
            --card-bg: #fff;
            --border: #c3c4c7;
            --text: #1d2327;
            --text-muted: #646970;
        }
        
        * { box-sizing: border-box; margin: 0; padding: 0; }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg);
            color: var(--text);
            line-height: 1.5;
        }
        
        .admin-wrap {
            display: flex;
            min-height: 100vh;
        }
        
        .sidebar {
            width: 260px;
            background: #1d2327;
            color: #fff;
            padding: 20px 0;
            flex-shrink: 0;
        }
        
        .sidebar-logo {
            padding: 0 20px 20px;
            border-bottom: 1px solid #3c434a;
            margin-bottom: 10px;
        }
        
        .sidebar-logo h1 {
            font-size: 18px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .sidebar-logo span {
            background: var(--primary);
            padding: 6px 10px;
            border-radius: 6px;
            font-size: 20px;
        }
        
        .sidebar-nav a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 20px;
            color: #a7aaad;
            text-decoration: none;
            transition: all 0.2s;
            border-left: 3px solid transparent;
        }
        
        .sidebar-nav a:hover {
            background: #2c3338;
            color: #fff;
        }
        
        .sidebar-nav a.active {
            background: #2c3338;
            color: #fff;
            border-left-color: var(--primary);
        }
        
        .sidebar-nav .icon {
            font-size: 18px;
            width: 24px;
            text-align: center;
        }
        
        .main-content {
            flex: 1;
            padding: 30px;
            max-width: 1400px;
        }
        
        .page-title {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 25px;
            color: var(--text);
        }
        
        .card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        }
        
        .card-title {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 1px solid #eee;
        }
        
        .grid { display: grid; gap: 24px; }
        .grid-2 { grid-template-columns: repeat(2, 1fr); }
        .grid-3 { grid-template-columns: repeat(3, 1fr); }
        .grid-4 { grid-template-columns: repeat(4, 1fr); }
        
        @media (max-width: 1200px) {
            .grid-4 { grid-template-columns: repeat(2, 1fr); }
            .grid-3 { grid-template-columns: repeat(2, 1fr); }
        }
        
        @media (max-width: 768px) {
            .sidebar { display: none; }
            .grid-2, .grid-3, .grid-4 { grid-template-columns: 1fr; }
        }
        
        .stat-card {
            text-align: center;
            padding: 20px;
            background: #f9f9f9;
            border-radius: 8px;
        }
        
        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: var(--primary);
        }
        
        .stat-label {
            font-size: 13px;
            color: var(--text-muted);
            margin-top: 5px;
        }
        
        .progress-bar {
            height: 24px;
            background: #e0e0e0;
            border-radius: 12px;
            overflow: hidden;
            flex: 1;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary), var(--primary-dark));
            border-radius: 12px;
            transition: width 0.5s ease;
        }
        
        .metric-row {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .metric-label {
            min-width: 120px;
            font-weight: 500;
        }
        
        .metric-value {
            min-width: 60px;
            text-align: right;
            font-weight: 600;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
        }
        
        .btn-primary {
            background: var(--primary);
            color: #fff;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
        }
        
        .btn-secondary {
            background: #f0f0f0;
            color: var(--text);
        }
        
        .btn-secondary:hover {
            background: #e0e0e0;
        }
        
        .btn-hero {
            padding: 16px 32px;
            font-size: 16px;
        }
        
        .action-buttons {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        .badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .badge-standard { background: #e3f2fd; color: #1565c0; }
        .badge-stress { background: #fff3e0; color: #e65100; }
        .badge-cache { background: #e8f5e9; color: #2e7d32; }
        .badge-active { background: #e3f2fd; color: #1565c0; }
        .badge-completed { background: #e8f5e9; color: #2e7d32; }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        th {
            background: #f9f9f9;
            font-weight: 600;
            font-size: 13px;
        }
        
        tr:hover {
            background: #f9f9f9;
        }
        
        .form-row {
            margin-bottom: 20px;
        }
        
        .form-row label {
            display: block;
            font-weight: 600;
            margin-bottom: 6px;
        }
        
        .form-row input[type="text"],
        .form-row input[type="number"],
        .form-row select,
        .form-row textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 14px;
        }
        
        .form-description {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 5px;
        }
        
        .plugin-grid {
            max-height: 350px;
            overflow-y: auto;
            border: 1px solid #ddd;
            border-radius: 6px;
            padding: 10px;
        }
        
        .plugin-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px;
            border-bottom: 1px solid #eee;
            transition: background 0.2s;
        }
        
        .plugin-item:last-child { border-bottom: none; }
        .plugin-item:hover { background: #f9f9f9; }
        .plugin-item.cache-plugin { background: #f0fff0; }
        
        .plugin-item label {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            flex: 1;
        }
        
        .plugin-name { font-weight: 500; }
        .plugin-version { font-size: 11px; color: #999; }
        
        .checkbox-group {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        .checkbox-group label {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            font-weight: normal;
        }
        
        .warning-box {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .warning-box strong { color: #856404; }
        
        .tabs {
            display: flex;
            gap: 5px;
            border-bottom: 2px solid #ddd;
            margin-bottom: 20px;
        }
        
        .tab {
            padding: 12px 24px;
            border: none;
            background: #f0f0f0;
            cursor: pointer;
            border-radius: 6px 6px 0 0;
            font-weight: 500;
            transition: all 0.2s;
        }
        
        .tab:hover { background: #e0e0e0; }
        .tab.active { background: var(--primary); color: #fff; }
        
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        
        .comparison-table th.baseline,
        .comparison-table td.baseline {
            background: #e7f3ff;
        }
        
        .diff { font-size: 11px; margin-left: 5px; }
        .diff.improvement { color: var(--success); }
        .diff.regression { color: var(--danger); }
        
        .profile-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px;
            background: #f9f9f9;
            border-radius: 6px;
            border-left: 4px solid var(--primary);
            margin-bottom: 12px;
        }
        
        .profile-info h3 { margin: 0 0 5px; font-size: 16px; }
        .profile-info p { margin: 0 0 5px; color: var(--text-muted); font-size: 14px; }
        .plugin-count { font-size: 12px; color: #999; }
        .profile-actions { display: flex; gap: 8px; }
        
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .metric-card {
            background: #f9f9f9;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
        }
        
        .metric-card h4 {
            font-size: 13px;
            color: var(--text-muted);
            margin: 0 0 8px;
            font-weight: 500;
        }
        
        .metric-card .value {
            font-size: 28px;
            font-weight: 700;
            color: var(--primary);
        }
        
        .metric-card .unit {
            font-size: 14px;
            font-weight: 400;
            color: var(--text-muted);
        }
        
        .metric-card .details {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 8px;
        }
        
        .chart-container {
            background: #f9f9f9;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .chart-container h4 {
            margin: 0 0 15px;
            font-size: 14px;
        }
        
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }
        
        @media (max-width: 900px) {
            .charts-grid { grid-template-columns: 1fr; }
        }
        
        .export-buttons {
            display: flex;
            gap: 10px;
        }
        
        .result-select-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 12px;
            max-height: 400px;
            overflow-y: auto;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        
        .result-select-item {
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            transition: all 0.2s;
        }
        
        .result-select-item:hover { border-color: var(--primary); }
        
        .result-select-item label {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            cursor: pointer;
        }
        
        .result-info { display: flex; flex-direction: column; }
        .result-name { font-weight: 600; }
        .result-meta { font-size: 11px; color: var(--text-muted); }
        
        .cache-charts {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }
        
        .stress-phases {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 30px;
        }
        
        .phase {
            flex: 1;
            min-width: 140px;
            padding: 15px;
            background: #f9f9f9;
            border-radius: 6px;
            text-align: center;
            border: 2px solid transparent;
        }
        
        .phase.active { border-color: var(--primary); background: #e7f3ff; }
        .phase.completed { border-color: var(--success); background: #e8f5e9; }
        
        .phase-icon { font-size: 24px; display: block; margin-bottom: 5px; }
        .phase-name { display: block; font-weight: 600; font-size: 12px; margin-bottom: 5px; }
        .phase-status { display: block; font-size: 11px; color: var(--text-muted); }
        
        .duration-options {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
        }
        
        @media (max-width: 1000px) {
            .duration-options { grid-template-columns: repeat(2, 1fr); }
        }
        
        .duration-option {
            cursor: pointer;
        }
        
        .duration-option input[type="radio"] {
            position: absolute;
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .duration-card {
            display: flex;
            flex-direction: column;
            padding: 15px;
            border: 2px solid #ddd;
            border-radius: 8px;
            text-align: center;
            transition: all 0.2s;
            background: #fff;
        }
        
        .duration-option input[type="radio"]:checked + .duration-card {
            border-color: var(--primary);
            background: #e7f3ff;
            box-shadow: 0 2px 8px rgba(34, 113, 177, 0.2);
        }
        
        .duration-option:hover .duration-card { border-color: var(--primary); }
        
        .duration-card strong { font-size: 16px; margin-bottom: 5px; }
        .duration-desc { font-size: 12px; color: var(--text-muted); }
        
        .checkbox-group {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .checkbox-group label {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }
        
        .btn-danger {
            background: #dc3545;
            border-color: #dc3545;
            color: #fff;
        }
        
        .btn-danger:hover {
            background: #c82333;
            border-color: #bd2130;
        }
        
        .log-count {
            background: var(--primary);
            color: #fff;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: normal;
        }
        
        .log-filters {
            display: flex;
            gap: 15px;
            margin-bottom: 10px;
            padding: 10px;
            background: #f9f9f9;
            border-radius: 4px;
        }
        
        .log-filters label {
            display: flex;
            align-items: center;
            gap: 5px;
            font-weight: normal;
            font-size: 12px;
            cursor: pointer;
        }
        
        .log-container {
            max-height: 400px;
            overflow-y: auto;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: #1d2327;
            padding: 10px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
        }
        
        .log-entry {
            display: flex;
            gap: 10px;
            padding: 4px 8px;
            border-radius: 2px;
            margin-bottom: 2px;
        }
        
        .log-time { color: #888; min-width: 60px; }
        .log-message { flex: 1; word-break: break-word; }
        
        .log-info .log-message { color: #a8d4ff; }
        .log-success .log-message { color: #7ee787; }
        .log-warning .log-message { color: #f0ad4e; }
        .log-error .log-message { color: #f85149; }
        .log-slow .log-message { color: #ff7b72; font-weight: 600; }
        
        .report-summary {
            display: grid;
            grid-template-columns: 1fr 3fr;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .report-score {
            text-align: center;
            padding: 20px;
            border-radius: 8px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
        }
        
        .score-value { font-size: 48px; font-weight: 700; display: block; }
        .score-label { font-size: 14px; opacity: 0.9; }
        
        .report-status {
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 20px;
            background: #f9f9f9;
            border-radius: 8px;
        }
        
        .report-status h3 { margin: 0 0 10px; font-size: 20px; }
        .report-status p { margin: 0 0 5px; color: var(--text-muted); }
        
        .grades-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-top: 10px;
        }
        
        .grade-card {
            text-align: center;
            padding: 20px;
            border-radius: 8px;
            background: #f9f9f9;
        }
        
        .grade-value { font-size: 36px; font-weight: 700; display: block; margin-bottom: 5px; }
        .grade-label { font-size: 12px; color: var(--text-muted); }
        
        .bottleneck-list, .suggestions-list { margin-top: 10px; }
        
        .bottleneck-item {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 8px;
            background: #f9f9f9;
            border-left: 4px solid #ddd;
        }
        
        .bottleneck-item.critical { border-left-color: #dc3545; background: #fff5f5; }
        .bottleneck-item.warning { border-left-color: #ffc107; background: #fffbf0; }
        
        .bottleneck-icon { font-size: 24px; min-width: 30px; }
        .bottleneck-content h4 { margin: 0 0 5px; font-size: 14px; }
        .bottleneck-content p { margin: 0; font-size: 13px; color: var(--text-muted); }
        
        .suggestion-item {
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 8px;
            background: #e8f5e9;
            border-left: 4px solid #4caf50;
        }
        
        .suggestion-item h4 { margin: 0 0 10px; color: #2e7d32; }
        .suggestion-item ul { margin: 0; padding-left: 20px; }
        .suggestion-item li { margin-bottom: 5px; font-size: 13px; }
    </style>
</head>
<body>
    <div class="admin-wrap">
        <aside class="sidebar">
            <div class="sidebar-logo">
                <h1><span>&#9889;</span> Cache Benchmark</h1>
            </div>
            <nav class="sidebar-nav">
                <a href="?page=dashboard" class="<?php echo $page === 'dashboard' ? 'active' : ''; ?>">
                    <span class="icon">&#128200;</span> Dashboard
                </a>
                <a href="?page=profiles" class="<?php echo $page === 'profiles' ? 'active' : ''; ?>">
                    <span class="icon">&#128221;</span> Profiles
                </a>
                <a href="?page=benchmark" class="<?php echo $page === 'benchmark' ? 'active' : ''; ?>">
                    <span class="icon">&#9889;</span> Run Benchmark
                </a>
                <a href="?page=stress" class="<?php echo $page === 'stress' ? 'active' : ''; ?>">
                    <span class="icon">&#128293;</span> Stress Test
                </a>
                <a href="?page=compare" class="<?php echo $page === 'compare' ? 'active' : ''; ?>">
                    <span class="icon">&#128202;</span> Compare
                </a>
                <a href="?page=results" class="<?php echo $page === 'results' ? 'active' : ''; ?>">
                    <span class="icon">&#128203;</span> Results
                </a>
            </nav>
        </aside>
        
        <main class="main-content">
            <?php if ($page === 'dashboard'): ?>
                <h1 class="page-title">Cache Benchmark Dashboard</h1>
                
                <div class="grid grid-3">
                    <div class="card">
                        <h2 class="card-title">Quick Stats</h2>
                        <div class="grid grid-2">
                            <div class="stat-card">
                                <div class="stat-value"><?php echo count($sample_results); ?></div>
                                <div class="stat-label">Total Benchmarks</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-value"><?php echo count($sample_profiles); ?></div>
                                <div class="stat-label">Saved Profiles</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-value"><?php echo formatBytes(memory_get_usage(true)); ?></div>
                                <div class="stat-label">Current Memory</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-value">12</div>
                                <div class="stat-label">Active Plugins</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card">
                        <h2 class="card-title">Server Status</h2>
                        <div class="metric-row">
                            <span class="metric-label">CPU Usage</span>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?php echo $stats['cpu']; ?>%"></div>
                            </div>
                            <span class="metric-value"><?php echo number_format($stats['cpu'], 1); ?>%</span>
                        </div>
                        <div class="metric-row">
                            <span class="metric-label">Memory Usage</span>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?php echo $stats['memory_percent']; ?>%"></div>
                            </div>
                            <span class="metric-value"><?php echo number_format($stats['memory_percent'], 1); ?>%</span>
                        </div>
                    </div>
                    
                    <div class="card">
                        <h2 class="card-title">Quick Actions</h2>
                        <div class="action-buttons">
                            <a href="?page=benchmark" class="btn btn-primary btn-hero">&#9889; Run Benchmark</a>
                            <a href="?page=stress" class="btn btn-secondary btn-hero">&#128293; Stress Test</a>
                            <a href="?page=profiles" class="btn btn-secondary btn-hero">&#9881; Manage Profiles</a>
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <h2 class="card-title">Recent Results</h2>
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Avg Response</th>
                                <th>Cache Hit Rate</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($sample_results, 0, 5) as $result): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($result['name']); ?></td>
                                <td><span class="badge badge-<?php echo $result['test_type']; ?>"><?php echo ucfirst($result['test_type']); ?></span></td>
                                <td><?php echo number_format($result['avg_response_time'], 2); ?> ms</td>
                                <td><?php echo number_format($result['cache_hit_rate'], 1); ?>%</td>
                                <td><?php echo date('M d, Y', strtotime($result['created_at'])); ?></td>
                                <td><a href="?page=results&id=<?php echo $result['id']; ?>" class="btn btn-secondary">View</a></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
            <?php elseif ($page === 'profiles'): ?>
                <h1 class="page-title">Cache Profiles</h1>
                
                <div class="grid grid-2">
                    <div class="card">
                        <h2 class="card-title">Create New Profile</h2>
                        <form>
                            <div class="form-row">
                                <label>Profile Name</label>
                                <input type="text" placeholder="My Cache Configuration">
                            </div>
                            <div class="form-row">
                                <label>Description</label>
                                <textarea rows="3" placeholder="Describe this profile configuration..."></textarea>
                            </div>
                            <div class="form-row">
                                <label>Select Plugins to Test</label>
                                <p class="form-description">Choose which plugins should be active during the benchmark. Cache-related plugins are highlighted.</p>
                                <div class="plugin-grid">
                                    <?php foreach ($sample_plugins as $plugin): ?>
                                    <div class="plugin-item <?php echo $plugin['is_cache'] ? 'cache-plugin' : ''; ?>">
                                        <label>
                                            <input type="checkbox" <?php echo $plugin['is_active'] ? 'checked' : ''; ?>>
                                            <span class="plugin-name"><?php echo htmlspecialchars($plugin['name']); ?></span>
                                            <?php if ($plugin['is_cache']): ?>
                                                <span class="badge badge-cache">Cache</span>
                                            <?php endif; ?>
                                            <?php if ($plugin['is_active']): ?>
                                                <span class="badge badge-active">Active</span>
                                            <?php endif; ?>
                                        </label>
                                        <span class="plugin-version">v<?php echo $plugin['version']; ?></span>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <button type="button" class="btn btn-primary">Create Profile</button>
                        </form>
                    </div>
                    
                    <div class="card">
                        <h2 class="card-title">Saved Profiles</h2>
                        <?php foreach ($sample_profiles as $profile): ?>
                        <div class="profile-item">
                            <div class="profile-info">
                                <h3><?php echo htmlspecialchars($profile['name']); ?></h3>
                                <p><?php echo htmlspecialchars($profile['description']); ?></p>
                                <span class="plugin-count"><?php echo $profile['plugin_count']; ?> plugin(s) selected</span>
                            </div>
                            <div class="profile-actions">
                                <a href="?page=benchmark&profile=<?php echo $profile['id']; ?>" class="btn btn-primary">Run Test</a>
                                <button class="btn btn-secondary">Edit</button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
            <?php elseif ($page === 'benchmark'): ?>
                <h1 class="page-title">Run Benchmark</h1>
                
                <div class="card">
                    <h2 class="card-title">Benchmark Configuration</h2>
                    <form>
                        <div class="form-row">
                            <label>Benchmark Name</label>
                            <input type="text" placeholder="My Benchmark Test">
                        </div>
                        <div class="form-row">
                            <label>Cache Profile</label>
                            <select>
                                <option>-- Current Configuration --</option>
                                <?php foreach ($sample_profiles as $profile): ?>
                                <option value="<?php echo $profile['id']; ?>"><?php echo htmlspecialchars($profile['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="form-description">Select a profile to test specific plugin configurations, or use current setup.</p>
                        </div>
                        <div class="form-row">
                            <label>Benchmark Duration</label>
                            <div class="duration-options">
                                <label class="duration-option">
                                    <input type="radio" name="duration" value="quick" checked>
                                    <span class="duration-card">
                                        <strong>Quick</strong>
                                        <span class="duration-desc">~1 min, 100 posts, 10 iterations</span>
                                    </span>
                                </label>
                                <label class="duration-option">
                                    <input type="radio" name="duration" value="2min">
                                    <span class="duration-card">
                                        <strong>2 Minutes</strong>
                                        <span class="duration-desc">1,000 posts, 50 iterations</span>
                                    </span>
                                </label>
                                <label class="duration-option">
                                    <input type="radio" name="duration" value="5min">
                                    <span class="duration-card">
                                        <strong>5 Minutes</strong>
                                        <span class="duration-desc">2,500 posts, 100 iterations</span>
                                    </span>
                                </label>
                                <label class="duration-option">
                                    <input type="radio" name="duration" value="until_stop">
                                    <span class="duration-card">
                                        <strong>Until I Stop It</strong>
                                        <span class="duration-desc">Max 10 min, 5,000 posts</span>
                                    </span>
                                </label>
                            </div>
                        </div>
                        <div class="form-row">
                            <label>Test Components</label>
                            <p class="description" style="font-size: 12px; color: #666; margin-bottom: 10px;">Select which tests to run. Each test scales based on the selected duration.</p>
                            <div class="checkbox-group">
                                <label><input type="checkbox" name="create_posts" checked> Create posts with metadata</label>
                                <label><input type="checkbox" name="read_api" checked> Read posts via API</label>
                                <label><input type="checkbox" name="reload_options" checked> Reload options with cache flush</label>
                                <label><input type="checkbox" name="simulate_cron" checked> Simulate cron (1MB file writes)</label>
                            </div>
                        </div>
                        <div style="display: flex; gap: 10px;">
                            <button type="button" class="btn btn-primary btn-hero" id="startBenchmark">&#9889; Start Benchmark</button>
                            <button type="button" class="btn btn-danger btn-hero" id="stopBenchmark" style="display: none;">&#9632; Stop Benchmark</button>
                        </div>
                    </form>
                </div>
                
                <div class="card" id="benchmarkProgress" style="display: none;">
                    <h2 class="card-title">Benchmark Progress</h2>
                    <div style="margin-bottom: 30px;">
                        <div class="progress-bar" style="height: 30px; border-radius: 15px;">
                            <div class="progress-fill" id="progressBar" style="width: 0%"></div>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-top: 10px;">
                            <span id="progressText">Running benchmark...</span>
                            <span id="progressPercent">0%</span>
                        </div>
                    </div>
                    <div class="metrics-grid">
                        <div class="metric-card">
                            <h4>Current Iteration</h4>
                            <div class="value" id="liveIteration">-</div>
                        </div>
                        <div class="metric-card">
                            <h4>Response Time</h4>
                            <div class="value" id="liveResponse">- <span class="unit">ms</span></div>
                        </div>
                        <div class="metric-card">
                            <h4>Memory Usage</h4>
                            <div class="value" id="liveMemory">-</div>
                        </div>
                        <div class="metric-card">
                            <h4>CPU Usage</h4>
                            <div class="value" id="liveCpu">- <span class="unit">%</span></div>
                        </div>
                    </div>
                    <div class="chart-container">
                        <canvas id="liveChart" height="200"></canvas>
                    </div>
                </div>
                
                <div class="card" id="benchmarkLog" style="display: none;">
                    <h2 class="card-title" style="display: flex; align-items: center; gap: 10px;">
                        Benchmark Log
                        <span class="log-count" id="logCount">0</span>
                    </h2>
                    <div class="log-filters">
                        <label><input type="checkbox" checked> Info</label>
                        <label><input type="checkbox" checked> Slow</label>
                        <label><input type="checkbox" checked> Warnings</label>
                        <label><input type="checkbox" checked> Success</label>
                    </div>
                    <div class="log-container" id="logContainer">
                        <div class="log-entry log-info">
                            <span class="log-time">0.0s</span>
                            <span class="log-message">Waiting for benchmark to start...</span>
                        </div>
                    </div>
                </div>
                
                <div class="card" id="benchmarkReport" style="display: none;">
                    <h2 class="card-title">Performance Report</h2>
                    <div class="report-summary">
                        <div class="report-score">
                            <span class="score-value" id="reportScore">85</span>
                            <span class="score-label">Performance Score</span>
                        </div>
                        <div class="report-status">
                            <h3 id="reportStatus">Good</h3>
                            <p id="reportMessage">Your site has good performance with room for improvement.</p>
                            <p><strong>Critical Issues:</strong> <span id="criticalCount">1</span> | <strong>Warnings:</strong> <span id="warningCount">3</span></p>
                        </div>
                    </div>
                    <h3>Performance Grades</h3>
                    <div class="grades-grid">
                        <div class="grade-card">
                            <span class="grade-value" style="color: #22c55e;">A</span>
                            <span class="grade-label">Response Time</span>
                        </div>
                        <div class="grade-card">
                            <span class="grade-value" style="color: #84cc16;">B</span>
                            <span class="grade-label">Memory Usage</span>
                        </div>
                        <div class="grade-card">
                            <span class="grade-value" style="color: #eab308;">C</span>
                            <span class="grade-label">Database Queries</span>
                        </div>
                        <div class="grade-card">
                            <span class="grade-value" style="color: #22c55e;">A</span>
                            <span class="grade-label">Cache Hit Rate</span>
                        </div>
                    </div>
                    <h3 style="margin-top: 20px;">Identified Bottlenecks</h3>
                    <div class="bottleneck-list">
                        <div class="bottleneck-item warning">
                            <span class="bottleneck-icon">&#9888;</span>
                            <div class="bottleneck-content">
                                <h4>Database Queries</h4>
                                <p>High number of database queries per request (65)</p>
                                <p><em>Impact: Increased load on database server</em></p>
                            </div>
                        </div>
                    </div>
                    <h3 style="margin-top: 20px;">Recommendations</h3>
                    <div class="suggestions-list">
                        <div class="suggestion-item">
                            <h4>Reduce Database Load</h4>
                            <ul>
                                <li>Install a database query caching plugin</li>
                                <li>Use object caching (Redis/Memcached)</li>
                                <li>Review and deactivate query-heavy plugins</li>
                            </ul>
                        </div>
                    </div>
                </div>
                
            <?php elseif ($page === 'stress'): ?>
                <h1 class="page-title">Stress Test</h1>
                
                <div class="card">
                    <h2 class="card-title">Stress Test Configuration</h2>
                    <div class="warning-box">
                        <p><strong>Warning:</strong> Stress tests are intensive and may temporarily affect site performance. All test data will be automatically cleaned up after the test.</p>
                    </div>
                    <form>
                        <div class="form-row">
                            <label>Test Name</label>
                            <input type="text" placeholder="My Stress Test">
                        </div>
                        <div class="form-row">
                            <label>Cache Profile</label>
                            <select>
                                <option>-- Current Configuration --</option>
                                <?php foreach ($sample_profiles as $profile): ?>
                                <option value="<?php echo $profile['id']; ?>"><?php echo htmlspecialchars($profile['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-row">
                            <label>Test Duration (seconds)</label>
                            <input type="number" value="60" min="30" max="300" step="30">
                        </div>
                        <div class="form-row">
                            <label>Test Components</label>
                            <div class="checkbox-group">
                                <label><input type="checkbox" checked> Create 100 posts with metadata</label>
                                <label><input type="checkbox" checked> Read posts via API</label>
                                <label><input type="checkbox" checked> Reload options with cache flush</label>
                                <label><input type="checkbox" checked> Simulate cron (1MB file writes)</label>
                            </div>
                        </div>
                        <button type="button" class="btn btn-primary btn-hero" id="startStress">&#128293; Start Stress Test</button>
                    </form>
                </div>
                
                <div class="card" id="stressProgress" style="display: none;">
                    <h2 class="card-title">Stress Test Progress</h2>
                    <div class="stress-phases">
                        <div class="phase" id="phase-posts">
                            <span class="phase-icon">&#128221;</span>
                            <span class="phase-name">Creating Posts</span>
                            <span class="phase-status">Pending</span>
                        </div>
                        <div class="phase" id="phase-api">
                            <span class="phase-icon">&#128279;</span>
                            <span class="phase-name">API Reads</span>
                            <span class="phase-status">Pending</span>
                        </div>
                        <div class="phase" id="phase-options">
                            <span class="phase-icon">&#9881;</span>
                            <span class="phase-name">Options Reload</span>
                            <span class="phase-status">Pending</span>
                        </div>
                        <div class="phase" id="phase-cron">
                            <span class="phase-icon">&#128190;</span>
                            <span class="phase-name">Cron Simulation</span>
                            <span class="phase-status">Pending</span>
                        </div>
                        <div class="phase" id="phase-cleanup">
                            <span class="phase-icon">&#128465;</span>
                            <span class="phase-name">Cleanup</span>
                            <span class="phase-status">Pending</span>
                        </div>
                    </div>
                    <div class="chart-container">
                        <canvas id="stressChart" height="250"></canvas>
                    </div>
                </div>
                
            <?php elseif ($page === 'compare'): ?>
                <h1 class="page-title">Compare Benchmarks</h1>
                
                <div class="card">
                    <h2 class="card-title">Select Benchmarks to Compare</h2>
                    <p class="form-description" style="margin-bottom: 15px;">Select 2-5 benchmark results to compare side by side.</p>
                    
                    <div class="result-select-grid">
                        <?php foreach ($sample_results as $result): ?>
                        <div class="result-select-item">
                            <label>
                                <input type="checkbox" name="result_ids[]" value="<?php echo $result['id']; ?>" 
                                       <?php echo in_array($result['id'], [1, 2, 3]) ? 'checked' : ''; ?>>
                                <div class="result-info">
                                    <span class="result-name"><?php echo htmlspecialchars($result['name']); ?></span>
                                    <span class="result-meta">
                                        <?php echo ucfirst($result['test_type']); ?> | 
                                        <?php echo number_format($result['avg_response_time'], 2); ?> ms | 
                                        <?php echo date('M d, Y', strtotime($result['created_at'])); ?>
                                    </span>
                                </div>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <button type="button" class="btn btn-primary btn-hero" id="compareBtn">&#128202; Compare Selected</button>
                </div>
                
                <div class="card" id="comparisonResults">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h2 class="card-title" style="margin: 0; border: none; padding: 0;">Comparison Results</h2>
                        <div class="export-buttons">
                            <button class="btn btn-secondary">&#128229; JSON</button>
                            <button class="btn btn-secondary">&#128229; CSV</button>
                        </div>
                    </div>
                    
                    <div class="tabs">
                        <button class="tab active" data-tab="summary">Summary</button>
                        <button class="tab" data-tab="response">Response Time</button>
                        <button class="tab" data-tab="memory">Memory</button>
                        <button class="tab" data-tab="cpu">CPU</button>
                        <button class="tab" data-tab="cache">Cache</button>
                    </div>
                    
                    <div class="tab-content active" id="tab-summary">
                        <table class="comparison-table">
                            <thead>
                                <tr>
                                    <th>Metric</th>
                                    <th class="baseline">Baseline (No Cache)</th>
                                    <th>W3 Total Cache</th>
                                    <th>Redis Cache</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><strong>Avg Response Time</strong></td>
                                    <td class="baseline">245.32 ms</td>
                                    <td>89.45 ms <span class="diff improvement">-63.5%</span></td>
                                    <td>67.89 ms <span class="diff improvement">-72.3%</span></td>
                                </tr>
                                <tr>
                                    <td><strong>Peak Memory</strong></td>
                                    <td class="baseline">64 MB</td>
                                    <td>50 MB <span class="diff improvement">-21.9%</span></td>
                                    <td>46 MB <span class="diff improvement">-28.1%</span></td>
                                </tr>
                                <tr>
                                    <td><strong>Cache Hit Rate</strong></td>
                                    <td class="baseline">0%</td>
                                    <td>78.5% <span class="diff improvement">+78.5%</span></td>
                                    <td>92.3% <span class="diff improvement">+92.3%</span></td>
                                </tr>
                                <tr>
                                    <td><strong>Avg DB Queries</strong></td>
                                    <td class="baseline">156</td>
                                    <td>42 <span class="diff improvement">-73.1%</span></td>
                                    <td>18 <span class="diff improvement">-88.5%</span></td>
                                </tr>
                                <tr>
                                    <td><strong>Avg CPU Usage</strong></td>
                                    <td class="baseline">42.3%</td>
                                    <td>28.7% <span class="diff improvement">-32.2%</span></td>
                                    <td>22.1% <span class="diff improvement">-47.8%</span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="tab-content" id="tab-response">
                        <div class="chart-container">
                            <h4>Response Time per Iteration</h4>
                            <canvas id="responseChart" height="300"></canvas>
                        </div>
                    </div>
                    
                    <div class="tab-content" id="tab-memory">
                        <div class="chart-container">
                            <h4>Memory Usage per Iteration</h4>
                            <canvas id="memoryChart" height="300"></canvas>
                        </div>
                    </div>
                    
                    <div class="tab-content" id="tab-cpu">
                        <div class="chart-container">
                            <h4>CPU Usage Over Time</h4>
                            <canvas id="cpuChart" height="300"></canvas>
                        </div>
                    </div>
                    
                    <div class="tab-content" id="tab-cache">
                        <div class="cache-charts">
                            <div class="chart-container">
                                <h4>Baseline (No Cache)</h4>
                                <canvas id="cacheChart1" height="200"></canvas>
                            </div>
                            <div class="chart-container">
                                <h4>W3 Total Cache</h4>
                                <canvas id="cacheChart2" height="200"></canvas>
                            </div>
                            <div class="chart-container">
                                <h4>Redis Cache</h4>
                                <canvas id="cacheChart3" height="200"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                
            <?php elseif ($page === 'results'): ?>
                <?php if (isset($_GET['id'])): 
                    $result = $sample_results[array_search($_GET['id'], array_column($sample_results, 'id'))] ?? $sample_results[0];
                ?>
                <h1 class="page-title"><?php echo htmlspecialchars($result['name']); ?></h1>
                <a href="?page=results" class="btn btn-secondary" style="margin-bottom: 20px;">&#8592; Back to Results</a>
                
                <div class="card">
                    <h2 class="card-title">Summary</h2>
                    <div class="grid grid-4">
                        <div class="stat-card">
                            <div class="stat-value"><?php echo ucfirst($result['test_type']); ?></div>
                            <div class="stat-label">Test Type</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value" style="color: #2e7d32;">Completed</div>
                            <div class="stat-label">Status</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value"><?php echo $result['iterations']; ?></div>
                            <div class="stat-label">Iterations</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value">45s</div>
                            <div class="stat-label">Duration</div>
                        </div>
                    </div>
                </div>
                
                <div class="metrics-grid">
                    <div class="card metric-card">
                        <h4>Response Time</h4>
                        <div class="value"><?php echo number_format($result['avg_response_time'], 2); ?> <span class="unit">ms avg</span></div>
                        <div class="details">Min: <?php echo number_format($result['min_response_time'], 2); ?> ms | Max: <?php echo number_format($result['max_response_time'], 2); ?> ms</div>
                    </div>
                    <div class="card metric-card">
                        <h4>Memory Usage</h4>
                        <div class="value"><?php echo formatBytes($result['peak_memory'] * 0.8); ?> <span class="unit">avg</span></div>
                        <div class="details">Peak: <?php echo formatBytes($result['peak_memory']); ?></div>
                    </div>
                    <div class="card metric-card">
                        <h4>Cache Performance</h4>
                        <div class="value"><?php echo number_format($result['cache_hit_rate'], 1); ?><span class="unit">% hit rate</span></div>
                        <div class="details">Hits: <?php echo number_format($result['cache_hits']); ?> | Misses: <?php echo number_format($result['cache_misses']); ?></div>
                    </div>
                    <div class="card metric-card">
                        <h4>Database</h4>
                        <div class="value"><?php echo number_format($result['avg_db_queries'], 1); ?> <span class="unit">queries/iter</span></div>
                        <div class="details">Total: <?php echo number_format($result['avg_db_queries'] * $result['iterations']); ?></div>
                    </div>
                    <div class="card metric-card">
                        <h4>CPU Usage</h4>
                        <div class="value"><?php echo number_format($result['avg_cpu'], 1); ?><span class="unit">%</span></div>
                    </div>
                </div>
                
                <div class="card">
                    <h2 class="card-title">Performance Charts</h2>
                    <div class="charts-grid">
                        <div class="chart-container">
                            <h4>Response Time per Iteration</h4>
                            <canvas id="resultResponseChart" height="250"></canvas>
                        </div>
                        <div class="chart-container">
                            <h4>Memory Usage per Iteration</h4>
                            <canvas id="resultMemoryChart" height="250"></canvas>
                        </div>
                        <div class="chart-container">
                            <h4>CPU Usage Over Time</h4>
                            <canvas id="resultCpuChart" height="250"></canvas>
                        </div>
                        <div class="chart-container">
                            <h4>Cache Hit/Miss Distribution</h4>
                            <canvas id="resultCacheChart" height="250"></canvas>
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <h2 class="card-title">Export Data</h2>
                    <div class="export-buttons">
                        <button class="btn btn-secondary">&#128229; Export JSON</button>
                        <button class="btn btn-secondary">&#128229; Export CSV</button>
                    </div>
                </div>
                
                <?php else: ?>
                <h1 class="page-title">Benchmark Results</h1>
                
                <div class="card">
                    <table>
                        <thead>
                            <tr>
                                <th><input type="checkbox"></th>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Iterations</th>
                                <th>Avg Response</th>
                                <th>Peak Memory</th>
                                <th>Cache Hit Rate</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sample_results as $result): ?>
                            <tr>
                                <td><input type="checkbox"></td>
                                <td><?php echo htmlspecialchars($result['name']); ?></td>
                                <td><span class="badge badge-<?php echo $result['test_type']; ?>"><?php echo ucfirst($result['test_type']); ?></span></td>
                                <td><span class="badge badge-completed">Completed</span></td>
                                <td><?php echo $result['iterations']; ?></td>
                                <td><?php echo number_format($result['avg_response_time'], 2); ?> ms</td>
                                <td><?php echo formatBytes($result['peak_memory']); ?></td>
                                <td><?php echo number_format($result['cache_hit_rate'], 1); ?>%</td>
                                <td><?php echo date('M d, Y H:i', strtotime($result['created_at'])); ?></td>
                                <td>
                                    <a href="?page=results&id=<?php echo $result['id']; ?>" class="btn btn-secondary">View</a>
                                    <button class="btn btn-secondary">Delete</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div style="margin-top: 20px; display: flex; gap: 10px;">
                        <button class="btn btn-secondary">Compare Selected</button>
                        <button class="btn btn-secondary">Delete Selected</button>
                    </div>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </main>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.tab').forEach(function(tab) {
                tab.addEventListener('click', function() {
                    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                    this.classList.add('active');
                    document.getElementById('tab-' + this.dataset.tab).classList.add('active');
                    
                    if (this.dataset.tab === 'response') initResponseChart();
                    if (this.dataset.tab === 'memory') initMemoryChart();
                    if (this.dataset.tab === 'cpu') initCpuChart();
                    if (this.dataset.tab === 'cache') initCacheCharts();
                });
            });
            
            var startBenchmark = document.getElementById('startBenchmark');
            if (startBenchmark) {
                startBenchmark.addEventListener('click', function() {
                    document.getElementById('benchmarkProgress').style.display = 'block';
                    document.getElementById('benchmarkLog').style.display = 'block';
                    document.getElementById('startBenchmark').style.display = 'none';
                    document.getElementById('stopBenchmark').style.display = 'inline-block';
                    simulateBenchmark();
                });
            }
            
            var stopBenchmark = document.getElementById('stopBenchmark');
            if (stopBenchmark) {
                stopBenchmark.addEventListener('click', function() {
                    document.getElementById('stopBenchmark').style.display = 'none';
                    document.getElementById('startBenchmark').style.display = 'inline-block';
                    document.getElementById('progressText').textContent = 'Benchmark stopped by user';
                });
            }
            
            var startStress = document.getElementById('startStress');
            if (startStress) {
                startStress.addEventListener('click', function() {
                    document.getElementById('stressProgress').style.display = 'block';
                    simulateStressTest();
                });
            }
            
            initResultCharts();
        });
        
        function simulateBenchmark() {
            var iterations = 10;
            var current = 0;
            var responseData = [];
            var labels = [];
            var logCount = 0;
            var startTime = Date.now();
            
            var logContainer = document.getElementById('logContainer');
            logContainer.innerHTML = '';
            
            function addLog(message, type) {
                type = type || 'info';
                logCount++;
                var elapsed = ((Date.now() - startTime) / 1000).toFixed(1);
                var html = '<div class="log-entry log-' + type + '">' +
                           '<span class="log-time">' + elapsed + 's</span>' +
                           '<span class="log-message">' + message + '</span>' +
                           '</div>';
                logContainer.innerHTML += html;
                logContainer.scrollTop = logContainer.scrollHeight;
                document.getElementById('logCount').textContent = logCount;
            }
            
            addLog('Benchmark started with Quick duration', 'info');
            addLog('Creating test posts...', 'info');
            
            var ctx = document.getElementById('liveChart');
            var chart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'Response Time (ms)',
                        data: [],
                        borderColor: 'rgba(54, 162, 235, 1)',
                        backgroundColor: 'rgba(54, 162, 235, 0.1)',
                        fill: true,
                        tension: 0.3
                    }]
                },
                options: {
                    responsive: true,
                    animation: false,
                    scales: {
                        y: { beginAtZero: true }
                    }
                }
            });
            
            var interval = setInterval(function() {
                current++;
                var responseTime = 80 + Math.random() * 60;
                var memory = 45 + Math.random() * 10;
                var cpu = 20 + Math.random() * 20;
                
                labels.push(current);
                responseData.push(responseTime);
                
                chart.data.labels = labels;
                chart.data.datasets[0].data = responseData;
                chart.update();
                
                document.getElementById('liveIteration').textContent = current + '/' + iterations;
                document.getElementById('liveResponse').innerHTML = responseTime.toFixed(2) + ' <span class="unit">ms</span>';
                document.getElementById('liveMemory').textContent = memory.toFixed(1) + ' MB';
                document.getElementById('liveCpu').innerHTML = cpu.toFixed(1) + '<span class="unit">%</span>';
                document.getElementById('progressBar').style.width = (current / iterations * 100) + '%';
                document.getElementById('progressPercent').textContent = Math.round(current / iterations * 100) + '%';
                
                addLog('[Iteration ' + current + '] Response: ' + responseTime.toFixed(2) + 'ms, Memory: ' + memory.toFixed(1) + 'MB', 'info');
                
                if (responseTime > 120) {
                    addLog('SLOW: Query execution took ' + responseTime.toFixed(0) + 'ms in wp-includes/post.php:1234', 'slow');
                }
                
                if (current === 5) {
                    addLog('Heartbeat: 50% complete, avg response: ' + (responseData.reduce((a,b) => a+b, 0) / responseData.length).toFixed(2) + 'ms', 'success');
                }
                
                if (current >= iterations) {
                    clearInterval(interval);
                    document.getElementById('progressText').textContent = 'Benchmark completed!';
                    document.getElementById('stopBenchmark').style.display = 'none';
                    document.getElementById('startBenchmark').style.display = 'inline-block';
                    
                    addLog('Cleaning up test data...', 'info');
                    addLog('Benchmark completed successfully!', 'success');
                    
                    document.getElementById('benchmarkReport').style.display = 'block';
                }
            }, 500);
        }
        
        function simulateStressTest() {
            var phases = ['posts', 'api', 'options', 'cron', 'cleanup'];
            var currentPhase = 0;
            
            var ctx = document.getElementById('stressChart');
            var chart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [
                        { label: 'CPU %', data: [], borderColor: 'rgba(255, 99, 132, 1)', fill: false },
                        { label: 'Memory MB', data: [], borderColor: 'rgba(54, 162, 235, 1)', fill: false }
                    ]
                },
                options: { responsive: true, animation: false }
            });
            
            var time = 0;
            var interval = setInterval(function() {
                time++;
                chart.data.labels.push(time + 's');
                chart.data.datasets[0].data.push(30 + Math.random() * 40);
                chart.data.datasets[1].data.push(50 + Math.random() * 30);
                chart.update();
                
                if (time % 5 === 0 && currentPhase < phases.length) {
                    if (currentPhase > 0) {
                        document.getElementById('phase-' + phases[currentPhase - 1]).classList.remove('active');
                        document.getElementById('phase-' + phases[currentPhase - 1]).classList.add('completed');
                        document.querySelector('#phase-' + phases[currentPhase - 1] + ' .phase-status').textContent = 'Done';
                    }
                    document.getElementById('phase-' + phases[currentPhase]).classList.add('active');
                    document.querySelector('#phase-' + phases[currentPhase] + ' .phase-status').textContent = 'Running...';
                    currentPhase++;
                }
                
                if (time >= 25) {
                    clearInterval(interval);
                    phases.forEach(function(p) {
                        document.getElementById('phase-' + p).classList.remove('active');
                        document.getElementById('phase-' + p).classList.add('completed');
                        document.querySelector('#phase-' + p + ' .phase-status').textContent = 'Done';
                    });
                }
            }, 400);
        }
        
        function initResponseChart() {
            var ctx = document.getElementById('responseChart');
            if (!ctx || ctx.chart) return;
            
            ctx.chart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: Array.from({length: 20}, (_, i) => i + 1),
                    datasets: [
                        { label: 'Baseline', data: Array.from({length: 20}, () => 200 + Math.random() * 100), borderColor: 'rgba(255, 99, 132, 1)', fill: false },
                        { label: 'W3 Total Cache', data: Array.from({length: 20}, () => 70 + Math.random() * 40), borderColor: 'rgba(54, 162, 235, 1)', fill: false },
                        { label: 'Redis Cache', data: Array.from({length: 20}, () => 50 + Math.random() * 30), borderColor: 'rgba(75, 192, 192, 1)', fill: false }
                    ]
                },
                options: { responsive: true, scales: { y: { beginAtZero: true, title: { display: true, text: 'Response Time (ms)' } } } }
            });
        }
        
        function initMemoryChart() {
            var ctx = document.getElementById('memoryChart');
            if (!ctx || ctx.chart) return;
            
            ctx.chart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: Array.from({length: 20}, (_, i) => i + 1),
                    datasets: [
                        { label: 'Baseline', data: Array.from({length: 20}, () => 55 + Math.random() * 15), borderColor: 'rgba(255, 99, 132, 1)', fill: false },
                        { label: 'W3 Total Cache', data: Array.from({length: 20}, () => 45 + Math.random() * 10), borderColor: 'rgba(54, 162, 235, 1)', fill: false },
                        { label: 'Redis Cache', data: Array.from({length: 20}, () => 40 + Math.random() * 10), borderColor: 'rgba(75, 192, 192, 1)', fill: false }
                    ]
                },
                options: { responsive: true, scales: { y: { beginAtZero: true, title: { display: true, text: 'Memory (MB)' } } } }
            });
        }
        
        function initCpuChart() {
            var ctx = document.getElementById('cpuChart');
            if (!ctx || ctx.chart) return;
            
            ctx.chart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: Array.from({length: 20}, (_, i) => i + 1),
                    datasets: [
                        { label: 'Baseline', data: Array.from({length: 20}, () => 35 + Math.random() * 15), borderColor: 'rgba(255, 99, 132, 1)', fill: false },
                        { label: 'W3 Total Cache', data: Array.from({length: 20}, () => 25 + Math.random() * 10), borderColor: 'rgba(54, 162, 235, 1)', fill: false },
                        { label: 'Redis Cache', data: Array.from({length: 20}, () => 18 + Math.random() * 8), borderColor: 'rgba(75, 192, 192, 1)', fill: false }
                    ]
                },
                options: { responsive: true, scales: { y: { beginAtZero: true, max: 100, title: { display: true, text: 'CPU (%)' } } } }
            });
        }
        
        function initCacheCharts() {
            var cacheData = [
                { id: 'cacheChart1', hits: 0, misses: 1580 },
                { id: 'cacheChart2', hits: 1240, misses: 340 },
                { id: 'cacheChart3', hits: 1458, misses: 122 }
            ];
            
            cacheData.forEach(function(data) {
                var ctx = document.getElementById(data.id);
                if (!ctx || ctx.chart) return;
                
                ctx.chart = new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: ['Cache Hits', 'Cache Misses'],
                        datasets: [{
                            data: [data.hits, data.misses],
                            backgroundColor: ['rgba(75, 192, 192, 0.8)', 'rgba(255, 99, 132, 0.8)']
                        }]
                    },
                    options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
                });
            });
        }
        
        function initResultCharts() {
            var resultResponseCtx = document.getElementById('resultResponseChart');
            if (resultResponseCtx) {
                new Chart(resultResponseCtx, {
                    type: 'line',
                    data: {
                        labels: Array.from({length: 20}, (_, i) => i + 1),
                        datasets: [{
                            label: 'Response Time (ms)',
                            data: Array.from({length: 20}, () => 60 + Math.random() * 40),
                            borderColor: 'rgba(54, 162, 235, 1)',
                            backgroundColor: 'rgba(54, 162, 235, 0.1)',
                            fill: true
                        }]
                    },
                    options: { responsive: true }
                });
            }
            
            var resultMemoryCtx = document.getElementById('resultMemoryChart');
            if (resultMemoryCtx) {
                new Chart(resultMemoryCtx, {
                    type: 'line',
                    data: {
                        labels: Array.from({length: 20}, (_, i) => i + 1),
                        datasets: [{
                            label: 'Memory (MB)',
                            data: Array.from({length: 20}, () => 40 + Math.random() * 15),
                            borderColor: 'rgba(255, 99, 132, 1)',
                            backgroundColor: 'rgba(255, 99, 132, 0.1)',
                            fill: true
                        }]
                    },
                    options: { responsive: true }
                });
            }
            
            var resultCpuCtx = document.getElementById('resultCpuChart');
            if (resultCpuCtx) {
                new Chart(resultCpuCtx, {
                    type: 'line',
                    data: {
                        labels: Array.from({length: 20}, (_, i) => i + 1),
                        datasets: [{
                            label: 'CPU (%)',
                            data: Array.from({length: 20}, () => 15 + Math.random() * 20),
                            borderColor: 'rgba(75, 192, 192, 1)',
                            backgroundColor: 'rgba(75, 192, 192, 0.1)',
                            fill: true
                        }]
                    },
                    options: { responsive: true, scales: { y: { max: 100 } } }
                });
            }
            
            var resultCacheCtx = document.getElementById('resultCacheChart');
            if (resultCacheCtx) {
                new Chart(resultCacheCtx, {
                    type: 'doughnut',
                    data: {
                        labels: ['Cache Hits', 'Cache Misses'],
                        datasets: [{
                            data: [1458, 122],
                            backgroundColor: ['rgba(75, 192, 192, 0.8)', 'rgba(255, 99, 132, 0.8)']
                        }]
                    },
                    options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
                });
            }
        }
    </script>
</body>
</html>
