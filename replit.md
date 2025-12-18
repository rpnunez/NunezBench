# WP Cache Benchmark - WordPress Plugin

## Overview
A comprehensive WordPress cache benchmarking plugin that helps users scientifically test and compare the performance of different cache configurations. The plugin provides sophisticated benchmarking, stress testing, and comparison tools with interactive visualizations.

## Project Structure
```
wp-cache-benchmark/           # WordPress plugin directory
├── wp-cache-benchmark.php    # Main plugin file
├── includes/
│   ├── class-database.php       # Database operations and table management
│   ├── class-profile-manager.php # Cache profile management and plugin state handling
│   ├── class-benchmark-engine.php # Core benchmarking engine
│   ├── class-resource-monitor.php # Real-time CPU, RAM, disk I/O monitoring
│   ├── class-stress-tester.php   # Stress test implementation
│   ├── class-comparison-engine.php # Benchmark comparison logic
│   ├── class-export-handler.php  # JSON/CSV export functionality
│   ├── class-admin-ui.php        # Admin dashboard rendering
│   └── class-ajax-handler.php    # AJAX request handlers
└── assets/
    ├── css/admin.css            # Admin styles
    └── js/
        ├── admin.js             # Admin JavaScript
        └── chart.min.js         # Chart.js library

index.php                     # Demo interface (standalone showcase)
```

## Features

### Core Features
- **Benchmarking Engine**: Measures response times, memory usage, database queries, and cache hit rates
- **Real-time Monitoring**: Tracks CPU usage, RAM consumption, and disk I/O during tests
- **Cache Profile System**: Create and manage different plugin configurations for testing
- **Plugin Selection**: Display all installed WordPress plugins with checkbox selection
- **Intelligent Plugin State Management**: Activates selected plugins for testing, restores original state afterward

### Stress Test Features
- Create 100 posts with random content and metadata
- Read posts via WP REST API
- Reload WordPress options with forced cache deletions
- Simulate cron jobs writing 1MB files to disk
- Automatic cleanup of all test data

### Comparison System
- Compare 2-5 benchmarks side by side
- Interactive Chart.js visualizations
- Response time distribution charts
- Memory usage over time charts
- CPU usage monitoring
- Cache performance visualization
- Color-coded differences (green for improvements, red for regressions)
- Export to JSON and CSV formats

## Database Tables
- `{prefix}_cache_benchmark_profiles` - Stores cache profile configurations
- `{prefix}_cache_benchmark_results` - Stores benchmark results
- `{prefix}_cache_benchmark_metrics` - Stores per-iteration metrics

## WordPress Hooks
- `admin_menu` - Adds admin menu pages
- `admin_enqueue_scripts` - Loads CSS and JavaScript
- `rest_api_init` - Registers REST API endpoints
- AJAX actions for all interactive features

## Installation (WordPress)
1. Upload the `wp-cache-benchmark` folder to `/wp-content/plugins/`
2. Activate the plugin through the WordPress admin
3. Navigate to "Cache Benchmark" in the admin menu

## Demo Mode
Run `php -S 0.0.0.0:5000` to view the standalone demo interface that showcases all plugin features with sample data.

## Tech Stack
- PHP 7.4+ (WordPress plugin)
- Chart.js 4.4.1 for visualizations
- WordPress Admin UI components
- Custom CSS for styling
- AJAX for real-time updates

## Recent Changes
- 2024-12-18: Initial plugin creation with all core features
