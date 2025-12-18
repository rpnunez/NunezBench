(function($) {
    'use strict';

    var WCB = {
        charts: {},
        refreshInterval: null,
        pollInterval: null,
        currentJobId: null,
        lastLogId: 0,
        lastIteration: 0,
        benchmarkStartTime: null,
        
        init: function() {
            this.bindEvents();
            this.initServerMonitor();
            this.initResultCharts();
        },
        
        bindEvents: function() {
            $('#wcb-profile-form').on('submit', this.handleProfileSubmit.bind(this));
            $('.wcb-delete-profile').on('click', this.handleProfileDelete.bind(this));
            $('#wcb-benchmark-form').on('submit', this.handleBenchmarkSubmit.bind(this));
            $('#stop-benchmark').on('click', this.handleStopBenchmark.bind(this));
            $('#wcb-stress-form').on('submit', this.handleStressSubmit.bind(this));
            $('#wcb-compare-form').on('submit', this.handleCompareSubmit.bind(this));
            $('.wcb-delete-result').on('click', this.handleResultDelete.bind(this));
            $('.wcb-tab').on('click', this.handleTabClick.bind(this));
            $('#plugin-search').on('input', this.handlePluginSearch.bind(this));
            $('#show-cache-only').on('change', this.handleCacheFilter.bind(this));
            $('#select-all-results').on('change', this.handleSelectAll.bind(this));
            $('#compare-selected').on('click', this.handleCompareSelected.bind(this));
            $('#delete-selected').on('click', this.handleDeleteSelected.bind(this));
            $('#export-json').on('click', this.handleExportJson.bind(this));
            $('#export-csv').on('click', this.handleExportCsv.bind(this));
        },
        
        initServerMonitor: function() {
            if ($('#cpu-progress').length === 0) return;
            
            this.updateServerStatus();
            this.refreshInterval = setInterval(this.updateServerStatus.bind(this), 5000);
        },
        
        updateServerStatus: function() {
            $.ajax({
                url: wpCacheBenchmark.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wcb_get_server_status',
                    nonce: wpCacheBenchmark.nonce
                },
                success: function(response) {
                    if (response.success) {
                        var data = response.data;
                        $('#cpu-progress').css('width', data.cpu + '%');
                        $('#cpu-value').text(data.cpu.toFixed(1) + '%');
                        $('#memory-progress').css('width', data.ram_percent + '%');
                        $('#memory-value').text(data.ram_percent.toFixed(1) + '%');
                    }
                }
            });
        },
        
        handleProfileSubmit: function(e) {
            e.preventDefault();
            
            var $form = $(e.currentTarget);
            var $button = $form.find('button[type="submit"]');
            
            $button.prop('disabled', true).text('Saving...');
            
            var formData = new FormData(e.currentTarget);
            formData.append('action', 'wcb_save_profile');
            formData.append('nonce', wpCacheBenchmark.nonce);
            
            $.ajax({
                url: wpCacheBenchmark.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.data.message);
                        $button.prop('disabled', false).text('Save Profile');
                    }
                },
                error: function() {
                    alert(wpCacheBenchmark.strings.error);
                    $button.prop('disabled', false).text('Save Profile');
                }
            });
        },
        
        handleProfileDelete: function(e) {
            e.preventDefault();
            
            if (!confirm(wpCacheBenchmark.strings.confirmDelete)) return;
            
            var $button = $(e.currentTarget);
            var profileId = $button.data('id');
            
            $.ajax({
                url: wpCacheBenchmark.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wcb_delete_profile',
                    nonce: wpCacheBenchmark.nonce,
                    profile_id: profileId
                },
                success: function(response) {
                    if (response.success) {
                        $button.closest('.wcb-profile-item').fadeOut(function() {
                            $(this).remove();
                        });
                    } else {
                        alert(response.data.message);
                    }
                }
            });
        },
        
        handleBenchmarkSubmit: function(e) {
            e.preventDefault();
            
            var $button = $('#start-benchmark');
            var $stopButton = $('#stop-benchmark');
            var $config = $('.wcb-benchmark-config');
            var $progress = $('.wcb-benchmark-progress');
            var $log = $('.wcb-benchmark-log');
            
            $button.prop('disabled', true).hide();
            $stopButton.show().prop('disabled', false);
            $config.find('input, select').prop('disabled', true);
            $progress.show();
            $log.show();
            
            var duration = $('input[name="duration"]:checked').val();
            var iterations = this.getDurationIterations(duration);
            this.initLiveChart(iterations);
            this.clearLog();
            this.benchmarkStartTime = Date.now();
            this.lastLogId = 0;
            this.lastIteration = 0;
            
            var self = this;
            
            $.ajax({
                url: wpCacheBenchmark.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wcb_start_benchmark',
                    nonce: wpCacheBenchmark.nonce,
                    profile_id: $('#profile-select').val(),
                    duration: duration,
                    name: $('#benchmark-name').val(),
                    create_posts: $('input[name="create_posts"]').is(':checked') ? '1' : '0',
                    read_api: $('input[name="read_api"]').is(':checked') ? '1' : '0',
                    reload_options: $('input[name="reload_options"]').is(':checked') ? '1' : '0',
                    simulate_cron: $('input[name="simulate_cron"]').is(':checked') ? '1' : '0'
                },
                success: function(response) {
                    if (response.success) {
                        self.currentJobId = response.data.job_id;
                        self.addLogEntry('Benchmark started (Job #' + response.data.job_id + ')', 'info');
                        self.startPolling();
                    } else {
                        alert(response.data.message);
                        self.addLogEntry('Failed to start benchmark: ' + response.data.message, 'error');
                        self.resetBenchmarkUI();
                    }
                },
                error: function() {
                    alert(wpCacheBenchmark.strings.error);
                    self.addLogEntry('Failed to start benchmark', 'error');
                    self.resetBenchmarkUI();
                }
            });
        },
        
        handleStopBenchmark: function(e) {
            e.preventDefault();
            
            var $stopButton = $('#stop-benchmark');
            $stopButton.prop('disabled', true).text('Stopping...');
            
            var self = this;
            
            $.ajax({
                url: wpCacheBenchmark.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wcb_stop_benchmark',
                    nonce: wpCacheBenchmark.nonce,
                    job_id: this.currentJobId
                },
                success: function(response) {
                    self.addLogEntry('Stop requested...', 'warning');
                },
                error: function() {
                    self.addLogEntry('Failed to stop benchmark', 'error');
                    $stopButton.prop('disabled', false).text('Stop');
                }
            });
        },
        
        startPolling: function() {
            var self = this;
            
            if (this.pollInterval) {
                clearInterval(this.pollInterval);
            }
            
            this.pollInterval = setInterval(function() {
                self.pollBenchmark();
            }, 1500);
            
            this.pollBenchmark();
        },
        
        stopPolling: function() {
            if (this.pollInterval) {
                clearInterval(this.pollInterval);
                this.pollInterval = null;
            }
        },
        
        pollBenchmark: function() {
            var self = this;
            
            $.ajax({
                url: wpCacheBenchmark.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wcb_poll_benchmark',
                    nonce: wpCacheBenchmark.nonce,
                    job_id: this.currentJobId,
                    last_log_id: this.lastLogId,
                    last_iteration: this.lastIteration
                },
                success: function(response) {
                    if (response.success) {
                        var data = response.data;
                        
                        if (data.logs && data.logs.length > 0) {
                            self.appendLogs(data.logs);
                            self.lastLogId = data.last_log_id;
                        }
                        
                        if (data.metrics && data.metrics.length > 0) {
                            self.updateLiveChart(data.metrics);
                            self.lastIteration = data.last_iteration;
                        }
                        
                        var progress = data.progress || 0;
                        $('#benchmark-progress').css('width', progress + '%');
                        $('#progress-percent').text(progress.toFixed(1) + '%');
                        $('#progress-text').text(data.current_phase || 'Running...');
                        
                        if (data.status === 'completed' || data.status === 'stopped' || data.status === 'failed') {
                            self.stopPolling();
                            self.onBenchmarkComplete(data);
                        }
                    } else {
                        self.addLogEntry('Poll error: ' + response.data.message, 'error');
                    }
                },
                error: function() {
                    self.addLogEntry('Connection error during polling', 'warning');
                }
            });
        },
        
        appendLogs: function(logs) {
            var $log = $('#benchmark-log');
            var self = this;
            
            logs.forEach(function(log) {
                var elapsed = ((new Date(log.timestamp).getTime() - self.benchmarkStartTime) / 1000);
                if (elapsed < 0) elapsed = 0;
                var timeStr = elapsed.toFixed(1) + 's';
                
                var html = '<div class="wcb-log-entry wcb-log-' + log.type + '">' +
                           '<span class="wcb-log-time">' + timeStr + '</span>' +
                           '<span class="wcb-log-message">' + self.escapeHtml(log.message) + '</span>' +
                           '</div>';
                $log.append(html);
            });
            
            $log.scrollTop($log[0].scrollHeight);
            $('#log-count').text($log.find('.wcb-log-entry').length);
        },
        
        updateLiveChart: function(metrics) {
            if (!this.charts.live) return;
            
            var self = this;
            
            metrics.forEach(function(metric) {
                self.charts.live.data.labels.push(metric.iteration);
                self.charts.live.data.datasets[0].data.push(metric.response_time);
            });
            
            this.charts.live.update('none');
        },
        
        onBenchmarkComplete: function(data) {
            var $results = $('.wcb-benchmark-results');
            var $report = $('.wcb-benchmark-report');
            
            if (data.result) {
                this.displayFinalResults(data.result, data.all_metrics, $results);
            }
            
            if (data.report) {
                this.displaySimpleReport(data.report, $report);
            }
            
            $('#progress-text').text(data.status === 'stopped' ? 'Benchmark stopped' : 'Benchmark complete!');
            $('#benchmark-progress').css('width', '100%');
            $('#progress-percent').text('100%');
            
            this.addLogEntry('Benchmark ' + data.status + '!', data.status === 'completed' ? 'success' : 'warning');
            this.resetBenchmarkUI();
        },
        
        displayFinalResults: function(result, metrics, $container) {
            var html = '<div class="wcb-metrics-grid">';
            html += this.createMetricCard('Average Response', (result.avg_response_time || 0).toFixed(2) + ' ms');
            html += this.createMetricCard('Min Response', (result.min_response_time || 0).toFixed(2) + ' ms');
            html += this.createMetricCard('Max Response', (result.max_response_time || 0).toFixed(2) + ' ms');
            html += this.createMetricCard('Avg Memory', this.formatBytes(result.avg_memory_usage || 0));
            html += this.createMetricCard('Peak Memory', this.formatBytes(result.peak_memory_usage || 0));
            html += this.createMetricCard('Cache Hit Rate', (result.cache_hit_rate || 0).toFixed(1) + '%');
            html += '</div>';
            
            html += '<div class="wcb-form-actions">';
            html += '<a href="' + window.location.origin + window.location.pathname + '?page=wp-cache-benchmark-results&id=' + result.id + '" class="button button-primary">View Full Results</a>';
            html += '</div>';
            
            $container.find('#benchmark-results-content').html(html);
            $container.show();
            
            if (metrics && metrics.length > 0 && this.charts.live) {
                var labels = [];
                var responseData = [];
                
                metrics.forEach(function(metric) {
                    labels.push(metric.iteration);
                    responseData.push(metric.response_time);
                });
                
                this.charts.live.data.labels = labels;
                this.charts.live.data.datasets[0].data = responseData;
                this.charts.live.update();
            }
        },
        
        displaySimpleReport: function(report, $container) {
            var html = '<div class="wcb-report-summary">';
            html += '<div class="wcb-report-score">';
            html += '<span class="score-value">' + (report.score || 0) + '</span>';
            html += '<span class="score-label">Performance Score</span>';
            html += '</div>';
            html += '<div class="wcb-report-status">';
            html += '<h3>Grade: ' + (report.grade || 'N/A') + '</h3>';
            html += '</div>';
            html += '</div>';
            
            if (report.bottlenecks && report.bottlenecks.length > 0) {
                html += '<h3>Bottlenecks</h3><ul>';
                report.bottlenecks.forEach(function(b) {
                    html += '<li><strong>' + b.severity + ':</strong> ' + b.message + '</li>';
                });
                html += '</ul>';
            }
            
            if (report.suggestions && report.suggestions.length > 0) {
                html += '<h3>Suggestions</h3><ul>';
                report.suggestions.forEach(function(s) {
                    html += '<li>' + s + '</li>';
                });
                html += '</ul>';
            }
            
            $container.find('#benchmark-report-content').html(html);
            $container.show();
        },
        
        resetBenchmarkUI: function() {
            var $button = $('#start-benchmark');
            var $stopButton = $('#stop-benchmark');
            var $config = $('.wcb-benchmark-config');
            
            $button.prop('disabled', false).show();
            $stopButton.hide().text('Stop Benchmark');
            $config.find('input, select').prop('disabled', false);
            this.currentJobId = null;
        },
        
        getDurationIterations: function(duration) {
            var map = {
                'quick': 10,
                '2min': 50,
                '5min': 100,
                'until_stop': 500
            };
            return map[duration] || 10;
        },
        
        clearLog: function() {
            $('#benchmark-log').html('');
            $('#log-count').text('0');
        },
        
        addLogEntry: function(message, type) {
            type = type || 'info';
            var time = ((Date.now() - (this.benchmarkStartTime || Date.now())) / 1000).toFixed(1) + 's';
            
            if (!this.benchmarkStartTime) {
                this.benchmarkStartTime = Date.now();
            }
            
            var html = '<div class="wcb-log-entry wcb-log-' + type + '">' +
                       '<span class="wcb-log-time">' + time + '</span>' +
                       '<span class="wcb-log-message">' + this.escapeHtml(message) + '</span>' +
                       '</div>';
            
            var $log = $('#benchmark-log');
            $log.append(html);
            $log.scrollTop($log[0].scrollHeight);
            
            var count = $log.find('.wcb-log-entry').length;
            $('#log-count').text(count);
        },
        
        escapeHtml: function(text) {
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },
        
        displayLogs: function(logs) {
            if (!logs || logs.length === 0) return;
            
            var $log = $('#benchmark-log');
            $log.html('');
            
            logs.forEach(function(log) {
                var html = '<div class="wcb-log-entry wcb-log-' + log.type + '">' +
                           '<span class="wcb-log-time">' + log.formatted_time + '</span>' +
                           '<span class="wcb-log-message">' + WCB.escapeHtml(log.message) + '</span>' +
                           '</div>';
                $log.append(html);
            });
            
            $log.scrollTop($log[0].scrollHeight);
            $('#log-count').text(logs.length);
        },
        
        displayReport: function(report, $container) {
            if (!report) return;
            
            var html = '<div class="wcb-report-summary">';
            html += '<div class="wcb-report-score">';
            html += '<span class="score-value">' + report.summary.overall_score + '</span>';
            html += '<span class="score-label">Performance Score</span>';
            html += '</div>';
            html += '<div class="wcb-report-status">';
            html += '<h3>' + report.summary.status + '</h3>';
            html += '<p>' + report.summary.message + '</p>';
            html += '<p><strong>Critical Issues:</strong> ' + report.summary.critical_issues + 
                    ' | <strong>Warnings:</strong> ' + report.summary.warnings + '</p>';
            html += '</div>';
            html += '</div>';
            
            if (report.performance_grades) {
                html += '<h3>Performance Grades</h3>';
                html += '<div class="wcb-grades-grid">';
                var gradeLabels = {
                    'response_time': 'Response Time',
                    'memory_usage': 'Memory Usage',
                    'database_queries': 'Database Queries',
                    'cache_hit_rate': 'Cache Hit Rate'
                };
                for (var key in report.performance_grades) {
                    var grade = report.performance_grades[key];
                    html += '<div class="wcb-grade-card">';
                    html += '<span class="wcb-grade-value" style="color: ' + grade.color + '">' + grade.grade + '</span>';
                    html += '<span class="wcb-grade-label">' + (gradeLabels[key] || key) + '</span>';
                    html += '</div>';
                }
                html += '</div>';
            }
            
            if (report.bottlenecks && report.bottlenecks.length > 0) {
                html += '<h3>Identified Bottlenecks</h3>';
                html += '<div class="wcb-bottleneck-list">';
                report.bottlenecks.forEach(function(bottleneck) {
                    var icon = bottleneck.severity === 'critical' ? '&#9888;' : '&#9888;';
                    html += '<div class="wcb-bottleneck-item ' + bottleneck.severity + '">';
                    html += '<span class="wcb-bottleneck-icon">' + icon + '</span>';
                    html += '<div class="wcb-bottleneck-content">';
                    html += '<h4>' + bottleneck.category + '</h4>';
                    html += '<p>' + bottleneck.description + '</p>';
                    html += '<p><em>Impact: ' + bottleneck.impact + '</em></p>';
                    html += '</div></div>';
                });
                html += '</div>';
            }
            
            if (report.suggestions && report.suggestions.length > 0) {
                html += '<h3>Recommendations</h3>';
                html += '<div class="wcb-suggestions-list">';
                report.suggestions.forEach(function(suggestion) {
                    html += '<div class="wcb-suggestion-item">';
                    html += '<h4>' + suggestion.title + '</h4>';
                    html += '<ul>';
                    suggestion.actions.forEach(function(action) {
                        html += '<li>' + action + '</li>';
                    });
                    html += '</ul></div>';
                });
                html += '</div>';
            }
            
            $container.find('#benchmark-report-content').html(html);
            $container.show();
        },
        
        initLiveChart: function(iterations) {
            var ctx = document.getElementById('live-chart');
            if (!ctx) return;
            
            if (this.charts.live) {
                this.charts.live.destroy();
            }
            
            this.charts.live = new Chart(ctx, {
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
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Response Time (ms)'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Iteration'
                            }
                        }
                    }
                }
            });
        },
        
        displayBenchmarkResults: function(data, $container) {
            var result = data.result;
            var metrics = data.metrics;
            
            var html = '<div class="wcb-metrics-grid">';
            html += this.createMetricCard('Average Response', result.avg_response_time.toFixed(2) + ' ms');
            html += this.createMetricCard('Min Response', result.min_response_time.toFixed(2) + ' ms');
            html += this.createMetricCard('Max Response', result.max_response_time.toFixed(2) + ' ms');
            html += this.createMetricCard('Avg Memory', this.formatBytes(result.avg_memory_usage));
            html += this.createMetricCard('Peak Memory', this.formatBytes(result.peak_memory_usage));
            html += this.createMetricCard('Cache Hit Rate', (result.cache_hit_rate || 0).toFixed(1) + '%');
            html += '</div>';
            
            html += '<div class="wcb-form-actions">';
            html += '<a href="' + window.location.origin + window.location.pathname + '?page=wp-cache-benchmark-results&id=' + result.id + '" class="button button-primary">View Full Results</a>';
            html += '</div>';
            
            $container.find('#benchmark-results-content').html(html);
            $container.show();
            
            if (metrics && metrics.length > 0 && this.charts.live) {
                var labels = [];
                var responseData = [];
                
                metrics.forEach(function(metric, index) {
                    labels.push(index + 1);
                    responseData.push(metric.response_time);
                });
                
                this.charts.live.data.labels = labels;
                this.charts.live.data.datasets[0].data = responseData;
                this.charts.live.update();
            }
        },
        
        createMetricCard: function(label, value) {
            return '<div class="wcb-card wcb-metric-card">' +
                   '<h3>' + label + '</h3>' +
                   '<div class="wcb-metric-value">' + value + '</div>' +
                   '</div>';
        },
        
        handleStressSubmit: function(e) {
            e.preventDefault();
            
            var $form = $(e.currentTarget);
            var $button = $('#start-stress-test');
            var $config = $('.wcb-stress-config');
            var $progress = $('.wcb-stress-progress');
            
            $button.prop('disabled', true);
            $config.find('input, select').prop('disabled', true);
            $progress.show();
            
            var phases = ['posts', 'api', 'options', 'cron', 'cleanup'];
            var currentPhase = 0;
            
            function updatePhase() {
                if (currentPhase < phases.length) {
                    $('#phase-' + phases[currentPhase]).addClass('active').find('.wcb-phase-status').text('Running...');
                    if (currentPhase > 0) {
                        $('#phase-' + phases[currentPhase - 1]).removeClass('active').addClass('completed').find('.wcb-phase-status').text('Done');
                    }
                    currentPhase++;
                    setTimeout(updatePhase, 3000);
                }
            }
            
            updatePhase();
            
            var formData = {
                action: 'wcb_run_stress_test',
                nonce: wpCacheBenchmark.nonce,
                profile_id: $('#stress-profile').val(),
                duration: $('#stress-duration').val(),
                name: $('#stress-name').val(),
                create_posts: $('input[name="create_posts"]').is(':checked') ? '1' : '0',
                read_api: $('input[name="read_api"]').is(':checked') ? '1' : '0',
                reload_options: $('input[name="reload_options"]').is(':checked') ? '1' : '0',
                simulate_cron: $('input[name="simulate_cron"]').is(':checked') ? '1' : '0'
            };
            
            $.ajax({
                url: wpCacheBenchmark.ajaxUrl,
                type: 'POST',
                data: formData,
                timeout: 600000,
                success: function(response) {
                    phases.forEach(function(phase) {
                        $('#phase-' + phase).removeClass('active').addClass('completed').find('.wcb-phase-status').text('Done');
                    });
                    
                    if (response.success) {
                        alert('Stress test completed! View results for details.');
                        window.location.href = window.location.origin + window.location.pathname + '?page=wp-cache-benchmark-results&id=' + response.data.result_id;
                    } else {
                        alert(response.data.message);
                    }
                    $button.prop('disabled', false);
                    $config.find('input, select').prop('disabled', false);
                },
                error: function() {
                    alert(wpCacheBenchmark.strings.error);
                    $button.prop('disabled', false);
                    $config.find('input, select').prop('disabled', false);
                }
            });
        },
        
        handleCompareSubmit: function(e) {
            e.preventDefault();
            
            var selectedIds = [];
            $('input[name="result_ids[]"]:checked').each(function() {
                selectedIds.push($(this).val());
            });
            
            if (selectedIds.length < 2 || selectedIds.length > 5) {
                alert('Please select between 2 and 5 benchmarks to compare.');
                return;
            }
            
            var $button = $('#compare-benchmarks');
            var $results = $('.wcb-comparison-results');
            
            $button.prop('disabled', true).text('Comparing...');
            
            $.ajax({
                url: wpCacheBenchmark.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wcb_get_comparison',
                    nonce: wpCacheBenchmark.nonce,
                    result_ids: selectedIds
                },
                success: function(response) {
                    if (response.success) {
                        WCB.displayComparison(response.data);
                        $results.show();
                        $('html, body').animate({
                            scrollTop: $results.offset().top - 50
                        }, 500);
                    } else {
                        alert(response.data.message);
                    }
                    $button.prop('disabled', false).text('Compare Selected');
                },
                error: function() {
                    alert(wpCacheBenchmark.strings.error);
                    $button.prop('disabled', false).text('Compare Selected');
                }
            });
        },
        
        displayComparison: function(data) {
            this.renderSummaryTable(data.summary);
            this.renderResponseChart(data.charts.response_time_line);
            this.renderMemoryChart(data.charts.memory_line);
            this.renderCpuChart(data.charts.cpu_line);
            this.renderCacheCharts(data.cache_performance);
            this.renderDatabaseChart(data.charts.queries_bar);
            
            this.currentComparisonData = data;
        },
        
        renderSummaryTable: function(summary) {
            var html = '<table class="wcb-comparison-table">';
            html += '<thead><tr><th>Metric</th>';
            
            summary.forEach(function(item) {
                html += '<th' + (item.is_baseline ? ' class="baseline"' : '') + '>' + item.name;
                if (item.is_baseline) html += ' <small>(Baseline)</small>';
                html += '</th>';
            });
            html += '</tr></thead><tbody>';
            
            var metrics = [
                { key: 'avg_response_time', label: 'Avg Response Time', suffix: ' ms' },
                { key: 'min_response_time', label: 'Min Response Time', suffix: ' ms' },
                { key: 'max_response_time', label: 'Max Response Time', suffix: ' ms' },
                { key: 'avg_memory_usage', label: 'Avg Memory Usage', suffix: '' },
                { key: 'peak_memory_usage', label: 'Peak Memory Usage', suffix: '' },
                { key: 'cache_hit_rate', label: 'Cache Hit Rate', suffix: '%' },
                { key: 'avg_db_queries', label: 'Avg DB Queries', suffix: '' },
                { key: 'avg_cpu_usage', label: 'Avg CPU Usage', suffix: '%' }
            ];
            
            metrics.forEach(function(metric) {
                html += '<tr><td><strong>' + metric.label + '</strong></td>';
                
                summary.forEach(function(item) {
                    var m = item.metrics[metric.key];
                    var value = m.value + metric.suffix;
                    var diffHtml = '';
                    
                    if (m.diff_percent !== null && m.diff_percent !== undefined) {
                        var diffClass = m.is_improvement ? 'improvement' : 'regression';
                        var diffSign = m.diff_percent >= 0 ? '+' : '';
                        diffHtml = '<span class="wcb-diff ' + diffClass + '">' + diffSign + m.diff_percent + '%</span>';
                    }
                    
                    html += '<td' + (item.is_baseline ? ' class="baseline"' : '') + '>' + value + diffHtml + '</td>';
                });
                
                html += '</tr>';
            });
            
            html += '</tbody></table>';
            
            $('#comparison-summary-table').html(html);
        },
        
        renderResponseChart: function(chartData) {
            var ctx = document.getElementById('response-time-chart');
            if (!ctx) return;
            
            if (this.charts.response) {
                this.charts.response.destroy();
            }
            
            this.charts.response = new Chart(ctx, {
                type: 'line',
                data: chartData,
                options: {
                    responsive: true,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Response Time per Iteration'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: { display: true, text: 'Response Time (ms)' }
                        },
                        x: {
                            title: { display: true, text: 'Iteration' }
                        }
                    }
                }
            });
        },
        
        renderMemoryChart: function(chartData) {
            var ctx = document.getElementById('memory-chart');
            if (!ctx) return;
            
            if (this.charts.memory) {
                this.charts.memory.destroy();
            }
            
            var formattedData = JSON.parse(JSON.stringify(chartData));
            formattedData.datasets.forEach(function(dataset) {
                dataset.data = dataset.data.map(function(val) {
                    return val / 1024 / 1024;
                });
            });
            
            this.charts.memory = new Chart(ctx, {
                type: 'line',
                data: formattedData,
                options: {
                    responsive: true,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Memory Usage per Iteration'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: { display: true, text: 'Memory (MB)' }
                        },
                        x: {
                            title: { display: true, text: 'Iteration' }
                        }
                    }
                }
            });
        },
        
        renderCpuChart: function(chartData) {
            var ctx = document.getElementById('cpu-chart');
            if (!ctx) return;
            
            if (this.charts.cpu) {
                this.charts.cpu.destroy();
            }
            
            this.charts.cpu = new Chart(ctx, {
                type: 'line',
                data: chartData,
                options: {
                    responsive: true,
                    plugins: {
                        title: {
                            display: true,
                            text: 'CPU Usage per Iteration'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            title: { display: true, text: 'CPU Usage (%)' }
                        },
                        x: {
                            title: { display: true, text: 'Iteration' }
                        }
                    }
                }
            });
        },
        
        renderCacheCharts: function(cacheData) {
            var $container = $('#cache-charts');
            $container.empty();
            
            var self = this;
            var index = 0;
            
            Object.keys(cacheData).forEach(function(id) {
                var data = cacheData[id];
                var canvasId = 'cache-pie-' + id;
                
                $container.append('<div class="wcb-chart-container"><h4>' + data.name + '</h4><canvas id="' + canvasId + '" height="200"></canvas></div>');
                
                var ctx = document.getElementById(canvasId);
                if (ctx) {
                    new Chart(ctx, {
                        type: 'doughnut',
                        data: {
                            labels: data.labels,
                            datasets: data.datasets
                        },
                        options: {
                            responsive: true,
                            plugins: {
                                legend: {
                                    position: 'bottom'
                                }
                            }
                        }
                    });
                }
                index++;
            });
        },
        
        renderDatabaseChart: function(chartData) {
            var ctx = document.getElementById('database-chart');
            if (!ctx) return;
            
            if (this.charts.database) {
                this.charts.database.destroy();
            }
            
            this.charts.database = new Chart(ctx, {
                type: 'bar',
                data: chartData,
                options: {
                    responsive: true,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Average Database Queries'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: { display: true, text: 'Queries' }
                        }
                    }
                }
            });
        },
        
        handleTabClick: function(e) {
            var $tab = $(e.currentTarget);
            var tabId = $tab.data('tab');
            
            $('.wcb-tab').removeClass('active');
            $tab.addClass('active');
            
            $('.wcb-tab-content').removeClass('active');
            $('#tab-' + tabId).addClass('active');
        },
        
        handleResultDelete: function(e) {
            e.preventDefault();
            
            if (!confirm(wpCacheBenchmark.strings.confirmDelete)) return;
            
            var $button = $(e.currentTarget);
            var resultId = $button.data('id');
            
            $.ajax({
                url: wpCacheBenchmark.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wcb_delete_result',
                    nonce: wpCacheBenchmark.nonce,
                    result_id: resultId
                },
                success: function(response) {
                    if (response.success) {
                        $button.closest('tr').fadeOut(function() {
                            $(this).remove();
                        });
                    } else {
                        alert(response.data.message);
                    }
                }
            });
        },
        
        handlePluginSearch: function(e) {
            var search = $(e.currentTarget).val().toLowerCase();
            
            $('.wcb-plugin-item').each(function() {
                var pluginName = $(this).data('plugin');
                $(this).toggle(pluginName.indexOf(search) !== -1);
            });
        },
        
        handleCacheFilter: function(e) {
            var showCacheOnly = $(e.currentTarget).is(':checked');
            
            if (showCacheOnly) {
                $('.wcb-plugin-item').hide();
                $('.wcb-plugin-item.wcb-cache-plugin').show();
            } else {
                $('.wcb-plugin-item').show();
            }
        },
        
        handleSelectAll: function(e) {
            var isChecked = $(e.currentTarget).is(':checked');
            $('.result-checkbox').prop('checked', isChecked);
        },
        
        handleCompareSelected: function(e) {
            e.preventDefault();
            
            var selectedIds = [];
            $('.result-checkbox:checked').each(function() {
                selectedIds.push($(this).val());
            });
            
            if (selectedIds.length < 2 || selectedIds.length > 5) {
                alert('Please select between 2 and 5 results to compare.');
                return;
            }
            
            window.location.href = window.location.origin + window.location.pathname + '?page=wp-cache-benchmark-compare&ids=' + selectedIds.join(',');
        },
        
        handleDeleteSelected: function(e) {
            e.preventDefault();
            
            var selectedIds = [];
            $('.result-checkbox:checked').each(function() {
                selectedIds.push($(this).val());
            });
            
            if (selectedIds.length === 0) {
                alert('Please select at least one result to delete.');
                return;
            }
            
            if (!confirm('Are you sure you want to delete ' + selectedIds.length + ' result(s)?')) {
                return;
            }
            
            var promises = selectedIds.map(function(id) {
                return $.ajax({
                    url: wpCacheBenchmark.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'wcb_delete_result',
                        nonce: wpCacheBenchmark.nonce,
                        result_id: id
                    }
                });
            });
            
            $.when.apply($, promises).done(function() {
                location.reload();
            });
        },
        
        handleExportJson: function(e) {
            e.preventDefault();
            
            if (!this.currentComparisonData) {
                alert('No comparison data available.');
                return;
            }
            
            var selectedIds = [];
            $('input[name="result_ids[]"]:checked').each(function() {
                selectedIds.push($(this).val());
            });
            
            window.location.href = wpCacheBenchmark.ajaxUrl + '?action=wcb_export&ids=' + selectedIds.join(',') + '&format=json&_wpnonce=' + wpCacheBenchmark.nonce;
        },
        
        handleExportCsv: function(e) {
            e.preventDefault();
            
            if (!this.currentComparisonData) {
                alert('No comparison data available.');
                return;
            }
            
            var selectedIds = [];
            $('input[name="result_ids[]"]:checked').each(function() {
                selectedIds.push($(this).val());
            });
            
            window.location.href = wpCacheBenchmark.ajaxUrl + '?action=wcb_export&ids=' + selectedIds.join(',') + '&format=csv&_wpnonce=' + wpCacheBenchmark.nonce;
        },
        
        initResultCharts: function() {
            if (typeof wcbResultMetrics === 'undefined' || typeof wcbResultData === 'undefined') {
                return;
            }
            
            var metrics = wcbResultMetrics;
            var result = wcbResultData;
            
            if (metrics.length === 0) return;
            
            var labels = [];
            var responseData = [];
            var memoryData = [];
            var cpuData = [];
            
            metrics.forEach(function(metric, index) {
                labels.push(index + 1);
                responseData.push(metric.response_time);
                memoryData.push(metric.memory_usage / 1024 / 1024);
                cpuData.push(metric.cpu_usage || 0);
            });
            
            var responseCtx = document.getElementById('result-response-chart');
            if (responseCtx) {
                new Chart(responseCtx, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Response Time (ms)',
                            data: responseData,
                            borderColor: 'rgba(54, 162, 235, 1)',
                            backgroundColor: 'rgba(54, 162, 235, 0.1)',
                            fill: true,
                            tension: 0.3
                        }]
                    },
                    options: {
                        responsive: true,
                        scales: {
                            y: { beginAtZero: true }
                        }
                    }
                });
            }
            
            var memoryCtx = document.getElementById('result-memory-chart');
            if (memoryCtx) {
                new Chart(memoryCtx, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Memory Usage (MB)',
                            data: memoryData,
                            borderColor: 'rgba(255, 99, 132, 1)',
                            backgroundColor: 'rgba(255, 99, 132, 0.1)',
                            fill: true,
                            tension: 0.3
                        }]
                    },
                    options: {
                        responsive: true,
                        scales: {
                            y: { beginAtZero: true }
                        }
                    }
                });
            }
            
            var cpuCtx = document.getElementById('result-cpu-chart');
            if (cpuCtx) {
                new Chart(cpuCtx, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'CPU Usage (%)',
                            data: cpuData,
                            borderColor: 'rgba(75, 192, 192, 1)',
                            backgroundColor: 'rgba(75, 192, 192, 0.1)',
                            fill: true,
                            tension: 0.3
                        }]
                    },
                    options: {
                        responsive: true,
                        scales: {
                            y: { beginAtZero: true, max: 100 }
                        }
                    }
                });
            }
            
            var cacheCtx = document.getElementById('result-cache-chart');
            if (cacheCtx) {
                new Chart(cacheCtx, {
                    type: 'doughnut',
                    data: {
                        labels: ['Cache Hits', 'Cache Misses'],
                        datasets: [{
                            data: [result.cache_hits || 0, result.cache_misses || 0],
                            backgroundColor: [
                                'rgba(75, 192, 192, 0.8)',
                                'rgba(255, 99, 132, 0.8)'
                            ]
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: {
                                position: 'bottom'
                            }
                        }
                    }
                });
            }
        },
        
        formatBytes: function(bytes, decimals) {
            if (bytes === 0) return '0 Bytes';
            
            var k = 1024;
            var dm = decimals || 2;
            var sizes = ['Bytes', 'KB', 'MB', 'GB'];
            var i = Math.floor(Math.log(bytes) / Math.log(k));
            
            return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
        }
    };
    
    $(document).ready(function() {
        WCB.init();
    });
    
})(jQuery);
