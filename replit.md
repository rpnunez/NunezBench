# WP Cache Benchmark - WordPress Plugin

## Overview
A comprehensive WordPress cache benchmarking plugin that helps users scientifically test and compare the performance of different cache configurations. The plugin provides sophisticated benchmarking, stress testing, and comparison tools with interactive visualizations, real-time logging, and actionable performance reports.

## Project Structure
```
wp-cache-benchmark/           # WordPress plugin directory
├── wp-cache-benchmark.php    # Main plugin file
├── includes/
│   ├── class-database.php       # Database operations and table management
│   ├── class-profile-manager.php # Cache profile management and plugin state handling
│   ├── class-query-tracker.php  # MySQL query tracking with plugin/file/line info
│   ├── class-benchmark-logger.php # Real-time benchmark logging with heartbeats
│   ├── class-report-generator.php # Performance report with bottleneck analysis
│   ├── class-benchmark-engine.php # Core benchmarking engine with duration support
│   ├── class-resource-monitor.php # Real-time CPU, RAM, disk I/O monitoring
│   ├── class-stress-tester.php   # Stress test implementation
│   ├── class-comparison-engine.php # Benchmark comparison logic
│   ├── class-export-handler.php  # JSON/CSV export functionality
│   ├── class-admin-ui.php        # Admin dashboard rendering
│   └── class-ajax-handler.php    # AJAX request handlers
└── assets/
    ├── css/admin.css            # Admin styles with log/report components
    └── js/
        ├── admin.js             # Admin JavaScript with real-time updates
        └── chart.min.js         # Chart.js library

index.php                     # Demo interface (standalone showcase)
```

## Features

### Core Features
- **Benchmarking Engine**: Measures response times, memory usage, database queries, and cache hit rates
- **Duration Options**: Quick (~1 min), 2 minutes, 5 minutes, and "Until I Stop It" (max 10 min)
- **Real-time Monitoring**: Tracks CPU usage, RAM consumption, and disk I/O during tests
- **Cache Profile System**: Create and manage different plugin configurations for testing
- **Plugin Selection**: Display all installed WordPress plugins with checkbox selection
- **Intelligent Plugin State Management**: Activates selected plugins for testing, restores original state afterward

### Query Tracking System
- Captures all MySQL queries during benchmarks
- Records plugin, file, and line number for each query
- Measures individual query execution times
- Identifies slow queries (>100ms threshold)
- Provides query context for debugging

### Real-time Benchmark Log
- Live streaming of benchmark events via AJAX
- Heartbeat updates showing progress
- MySQL query details with execution times
- Slow query detection and highlighting
- Filterable log entries by type (info, success, warning, error, slow)

### Performance Reports
- Overall performance score (0-100)
- Performance grades for response time, memory, queries, cache
- Bottleneck identification with severity levels
- Actionable improvement suggestions
- Detailed query statistics

### Benchmark Test Types (Run Benchmark & Stress Test)
Both benchmark and stress test pages now support the same test types with duration-based scaling:

| Test Type | Quick | 2 Minutes | 5 Minutes | Until Stop |
|-----------|-------|-----------|-----------|------------|
| Create posts with metadata | 100 | 1,000 | 2,500 | 5,000 |
| Read posts via API | 50 | 500 | 1,000 | 2,000 |
| Reload options with cache flush | 20 | 100 | 200 | 500 |
| Simulate cron (1MB file writes) | 5 | 25 | 50 | 100 |

All test data is automatically cleaned up after the test completes.

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
- `{prefix}_cache_benchmark_results` - Stores benchmark results with progress tracking (current_iteration, total_iterations, stop_requested, last_heartbeat, job_config)
- `{prefix}_cache_benchmark_metrics` - Stores per-iteration metrics
- `{prefix}_cache_benchmark_logs` - Stores live benchmark logs for incremental streaming

## Async Architecture
The benchmark execution uses an asynchronous chunk-based model:

1. **Start**: `wcb_start_benchmark` creates a job record with configuration and returns job_id
2. **Poll**: `wcb_poll_benchmark` processes a small chunk (~5 iterations, <2s) and returns incremental logs/metrics
3. **Stop**: `wcb_stop_benchmark` sets stop_requested flag for graceful termination

The frontend polls every 1.5 seconds, with each poll both processing work and fetching new data. This enables live UI updates without blocking.

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
- 2024-12-18: Added benchmark duration options (Quick, 2 min, 5 min, Until I Stop It)
- 2024-12-18: Implemented query tracking system with plugin/file/line info
- 2024-12-18: Added real-time benchmark logging with heartbeats and slow query detection
- 2024-12-18: Created performance report generator with bottleneck analysis and actionable suggestions
- 2024-12-18: Updated admin UI with duration selector, log display, and report sections
- 2024-12-18: Updated demo interface to showcase all new features
- 2024-12-18: Added multi-test selection to Run Benchmark page (matching Stress Test options)
- 2024-12-18: Implemented duration-based test scaling for all test types
- 2024-12-18: Added API read test, option reload test, and cron simulation test methods
- 2024-12-18: Fixed plugin activation to create database tables on install
- 2024-12-18: Fixed numeric field casting in AJAX responses for JavaScript compatibility
- 2024-12-18: Refactored to async chunk-based benchmark execution with polling for live UI updates
- 2024-12-18: Added new database fields for job state tracking (current_iteration, stop_requested, job_config)
- 2024-12-18: Created live logs table for incremental streaming
- 2024-12-18: Replaced synchronous AJAX with start/poll/stop endpoints for non-blocking execution
- 2024-12-18: Updated JavaScript to use 1.5s polling loop for real-time progress and log updates
- 2024-12-18: Fixed progress tracking with job_config['total_completed'] counter for accurate iteration counting
- 2024-12-18: Added complete metrics capture (CPU, RAM, disk I/O, db_queries, cache stats) to all work methods
- 2024-12-18: Implemented proper resource_monitor and query_tracker lifecycle (start/reset/stop) per chunk
- 2024-12-18: Database is now single source of truth for all metrics - finalize_job aggregates from metrics table only
