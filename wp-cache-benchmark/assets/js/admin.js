(function($) {
    'use strict';

    var WCB = {
        charts: {},
        refreshInterval: null,
        
        init: function() {
            this.bindEvents();
            this.initServerMonitor();
            this.initResultCharts();
        },
        
        bindEvents: function() {
            $('#wcb-profile-form').on('submit', this.handleProfileSubmit.bind(this));
            $('.wcb-delete-profile').on('click', this.handleProfileDelete.bind(this));
            $('#wcb-benchmark-form').on('submit', this.handleBenchmarkSubmit.bind(this));
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
            
            var $form = $(e.currentTarget);
            var $button = $('#start-benchmark');
            var $config = $('.wcb-benchmark-config');
            var $progress = $('.wcb-benchmark-progress');
            var $results = $('.wcb-benchmark-results');
            
            $button.prop('disabled', true);
            $config.find('input, select').prop('disabled', true);
            $progress.show();
            
            var iterations = parseInt($('#iterations').val());
            this.initLiveChart(iterations);
            
            $.ajax({
                url: wpCacheBenchmark.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wcb_run_benchmark',
                    nonce: wpCacheBenchmark.nonce,
                    profile_id: $('#profile-select').val(),
                    iterations: iterations,
                    name: $('#benchmark-name').val()
                },
                timeout: 300000,
                success: function(response) {
                    if (response.success) {
                        WCB.displayBenchmarkResults(response.data, $results);
                        $('#progress-text').text(wpCacheBenchmark.strings.benchmarkComplete);
                        $('#benchmark-progress').css('width', '100%');
                        $('#progress-percent').text('100%');
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
