<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Decta Dashboard') }}
            </h2>
            <div class="flex space-x-3">
                <button id="refreshBtn"
                        class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 transition ease-in-out duration-150">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                    </svg>
                    Refresh
                </button>
                <a href="{{ route('decta.reports.index') }}"
                   class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 transition ease-in-out duration-150">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                    </svg>
                    Reports
                </a>
            </div>
        </div>
    </x-slot>

    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/date-fns@2.29.3/index.min.js"></script>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <!-- Key Performance Indicators -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <!-- Total Transactions -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center">
                                    <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zM3 10a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H4a1 1 0 01-1-1v-6zM14 9a1 1 0 00-1 1v6a1 1 0 001 1h2a1 1 0 001-1v-6a1 1 0 00-1-1h-2z"></path>
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Total Transactions</dt>
                                    <dd class="flex items-baseline">
                                        <div id="totalTransactions" class="text-2xl font-semibold text-gray-900">-</div>
                                        <div id="totalTransactionsChange" class="ml-2 flex items-baseline text-sm font-semibold">
                                            <!-- Change indicator will be populated -->
                                        </div>
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Match Rate -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-green-500 rounded-full flex items-center justify-center">
                                    <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Match Rate</dt>
                                    <dd class="flex items-baseline">
                                        <div id="matchRate" class="text-2xl font-semibold text-gray-900">-%</div>
                                        <div id="matchRateChange" class="ml-2 flex items-baseline text-sm font-semibold">
                                            <!-- Change indicator will be populated -->
                                        </div>
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Total Amount -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-yellow-500 rounded-full flex items-center justify-center">
                                    <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M8.433 7.418c.155-.103.346-.196.567-.267v1.698a2.305 2.305 0 01-.567-.267C8.07 8.34 8 8.114 8 8c0-.114.07-.34.433-.582zM11 12.849v-1.698c.22.071.412.164.567.267.364.243.433.468.433.582 0 .114-.07.34-.433.582a2.305 2.305 0 01-.567.267z"></path>
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.076 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843.504a1 1 0 101.511-1.31c-.563-.649-1.413-1.076-2.354-1.253V5z" clip-rule="evenodd"></path>
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Total Amount</dt>
                                    <dd class="flex items-baseline">
                                        <div id="totalAmount" class="text-2xl font-semibold text-gray-900">€-</div>
                                        <div id="totalAmountChange" class="ml-2 flex items-baseline text-sm font-semibold">
                                            <!-- Change indicator will be populated -->
                                        </div>
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Unmatched Transactions -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-red-500 rounded-full flex items-center justify-center">
                                    <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Unmatched</dt>
                                    <dd class="flex items-baseline">
                                        <div id="unmatchedTransactions" class="text-2xl font-semibold text-gray-900">-</div>
                                        <div class="ml-2">
                                            <a href="/decta/reports/unmatched" class="text-sm text-indigo-600 hover:text-indigo-500">
                                                View →
                                            </a>
                                        </div>
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Row -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                <!-- Transaction Trends Chart -->
                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                    <div class="bg-gradient-to-r from-blue-500 to-blue-600 text-white px-6 py-4">
                        <h3 class="text-lg font-medium flex items-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                            </svg>
                            Transaction Trends (7 Days)
                        </h3>
                    </div>
                    <div class="p-6">
                        <canvas id="transactionTrendsChart" width="400" height="200"></canvas>
                    </div>
                </div>

                <!-- Match Rate Trends Chart -->
                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                    <div class="bg-gradient-to-r from-green-500 to-green-600 text-white px-6 py-4">
                        <h3 class="text-lg font-medium flex items-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 8v8m-4-5v5m-4-2v2m-2 4h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                            Match Rate Trends (7 Days)
                        </h3>
                    </div>
                    <div class="p-6">
                        <canvas id="matchRateTrendsChart" width="400" height="200"></canvas>
                    </div>
                </div>
            </div>

            <!-- Additional Analytics Row -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                <!-- Currency Distribution -->
                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                    <div class="bg-gradient-to-r from-purple-500 to-purple-600 text-white px-6 py-4">
                        <h3 class="text-lg font-medium flex items-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"></path>
                            </svg>
                            Currency Distribution
                        </h3>
                    </div>
                    <div class="p-6">
                        <canvas id="currencyChart" width="300" height="200"></canvas>
                    </div>
                </div>

                <!-- Top Merchants -->
                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                    <div class="bg-gradient-to-r from-indigo-500 to-indigo-600 text-white px-6 py-4">
                        <h3 class="text-lg font-medium flex items-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                            </svg>
                            Top Merchants
                        </h3>
                    </div>
                    <div class="p-6">
                        <div id="topMerchantsList" class="space-y-3">
                            <!-- Will be populated by JavaScript -->
                            <div class="text-center text-gray-500">Loading...</div>
                        </div>
                    </div>
                </div>

                <!-- Processing Status -->
                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                    <div class="bg-gradient-to-r from-orange-500 to-orange-600 text-white px-6 py-4">
                        <h3 class="text-lg font-medium flex items-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            Processing Status
                        </h3>
                    </div>
                    <div class="p-6">
                        <canvas id="processingStatusChart" width="300" height="200"></canvas>
                    </div>
                </div>
            </div>

            <!-- Recent Files Table -->
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                <div class="bg-gradient-to-r from-gray-500 to-gray-600 text-white px-6 py-4">
                    <h3 class="text-lg font-medium flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        Recent Files
                    </h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Filename</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Transactions</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Match Rate</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Size</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                        </tr>
                        </thead>
                        <tbody id="recentFilesTableBody" class="bg-white divide-y divide-gray-200">
                        <!-- Will be populated by JavaScript -->
                        <tr>
                            <td colspan="6" class="px-6 py-4 text-center text-gray-500">Loading recent files...</td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- System Health Status -->
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900 flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        System Health
                        <span id="healthStatusBadge" class="ml-3 px-2 py-1 text-xs rounded-full">
                            <!-- Status badge will be populated -->
                        </span>
                    </h3>
                </div>
                <div class="p-6">
                    <div id="healthStatusContent">
                        <!-- Health status content will be populated -->
                        <div class="text-center text-gray-500">Checking system health...</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let charts = {};
        let refreshInterval;

        document.addEventListener('DOMContentLoaded', function() {
            loadDashboardData();
            setupRefreshButton();

            // Auto-refresh every 30 seconds
            refreshInterval = setInterval(loadDashboardData, 30000);
        });

        function setupRefreshButton() {
            document.getElementById('refreshBtn').addEventListener('click', function() {
                loadDashboardData();

                // Add visual feedback
                const btn = this;
                const originalText = btn.innerHTML;
                btn.innerHTML = `
                    <svg class="animate-spin w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                    </svg>
                    Refreshing...
                `;
                btn.disabled = true;

                setTimeout(() => {
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                }, 2000);
            });
        }

        function loadDashboardData() {
            fetch('/decta/reports/dashboard-data')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateKPIs(data.data.summary);
                        updateCharts(data.data);
                        updateRecentFiles(data.data.recent_files);
                        updateHealthStatus(data.data.processing_status);
                    } else {
                        console.error('Failed to load dashboard data:', data.message);
                    }
                })
                .catch(error => {
                    console.error('Error loading dashboard data:', error);
                });
        }

        function updateKPIs(summary) {
            // Update main KPIs
            document.getElementById('totalTransactions').textContent = summary.total_transactions.toLocaleString();
            document.getElementById('matchRate').textContent = summary.match_rate.toFixed(1) + '%';
            document.getElementById('totalAmount').textContent = '€' + summary.total_amount.toLocaleString('en-US', {minimumFractionDigits: 2});
            document.getElementById('unmatchedTransactions').textContent = summary.unmatched_transactions.toLocaleString();

            // Update change indicators (simplified - you can enhance this with actual comparison logic)
            updateChangeIndicator('totalTransactionsChange', summary.today_transactions - summary.yesterday_transactions);
            updateChangeIndicator('totalAmountChange', summary.today_amount - summary.yesterday_amount);
        }

        function updateChangeIndicator(elementId, change) {
            const element = document.getElementById(elementId);
            if (change > 0) {
                element.innerHTML = `
                    <svg class="self-center flex-shrink-0 h-5 w-5 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M5.293 7.707a1 1 0 010-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 01-1.414 1.414L10 4.414 6.707 7.707a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                    </svg>
                    <span class="text-green-600">+${Math.abs(change).toLocaleString()}</span>
                `;
            } else if (change < 0) {
                element.innerHTML = `
                    <svg class="self-center flex-shrink-0 h-5 w-5 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M14.707 12.293a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L10 15.586l3.293-3.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                    </svg>
                    <span class="text-red-600">-${Math.abs(change).toLocaleString()}</span>
                `;
            } else {
                element.innerHTML = '<span class="text-gray-500">No change</span>';
            }
        }

        function updateCharts(data) {
            updateTransactionTrendsChart(data.matching_trends);
            updateMatchRateTrendsChart(data.matching_trends);
            updateCurrencyChart(data.currency_breakdown);
            updateTopMerchants(data.top_merchants);
            updateProcessingStatusChart(data.processing_status);
        }

        function updateTransactionTrendsChart(trendsData) {
            const ctx = document.getElementById('transactionTrendsChart').getContext('2d');

            if (charts.transactionTrends) {
                charts.transactionTrends.destroy();
            }

            const labels = trendsData?.map(item => new Date(item.date).toLocaleDateString()) || [];
            const totalData = trendsData?.map(item => item.total) || [];
            const matchedData = trendsData?.map(item => item.matched) || [];

            charts.transactionTrends = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Total Transactions',
                        data: totalData,
                        borderColor: 'rgb(59, 130, 246)',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        tension: 0.4
                    }, {
                        label: 'Matched Transactions',
                        data: matchedData,
                        borderColor: 'rgb(34, 197, 94)',
                        backgroundColor: 'rgba(34, 197, 94, 0.1)',
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }

        function updateMatchRateTrendsChart(trendsData) {
            const ctx = document.getElementById('matchRateTrendsChart').getContext('2d');

            if (charts.matchRateTrends) {
                charts.matchRateTrends.destroy();
            }

            const labels = trendsData?.map(item => new Date(item.date).toLocaleDateString()) || [];
            const matchRateData = trendsData?.map(item => item.match_rate) || [];

            charts.matchRateTrends = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Match Rate (%)',
                        data: matchRateData,
                        borderColor: 'rgb(34, 197, 94)',
                        backgroundColor: 'rgba(34, 197, 94, 0.2)',
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            ticks: {
                                callback: function(value) {
                                    return value + '%';
                                }
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }

        function updateCurrencyChart(currencyData) {
            const ctx = document.getElementById('currencyChart').getContext('2d');

            if (charts.currency) {
                charts.currency.destroy();
            }

            if (!currencyData || currencyData.length === 0) {
                return;
            }

            const labels = currencyData.map(item => item.currency);
            const data = currencyData.map(item => item.transaction_count);
            const colors = [
                'rgba(59, 130, 246, 0.8)',
                'rgba(34, 197, 94, 0.8)',
                'rgba(251, 191, 36, 0.8)',
                'rgba(239, 68, 68, 0.8)',
                'rgba(139, 92, 246, 0.8)'
            ];

            charts.currency = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: data,
                        backgroundColor: colors,
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }

        function updateTopMerchants(merchantsData) {
            const container = document.getElementById('topMerchantsList');

            if (!merchantsData || merchantsData.length === 0) {
                container.innerHTML = '<div class="text-center text-gray-500">No merchant data available</div>';
                return;
            }

            container.innerHTML = merchantsData.map((merchant, index) => `
                <div class="flex items-center justify-between p-3 rounded-lg bg-gray-50">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 w-8 h-8 bg-indigo-100 text-indigo-800 rounded-full flex items-center justify-center text-sm font-medium">
                            ${index + 1}
                        </div>
                        <div class="ml-3">
                            <div class="text-sm font-medium text-gray-900">
                                ${merchant.merchant_name || merchant.merchant_id}
                            </div>
                            <div class="text-xs text-gray-500">
                                ${merchant.transaction_count} transactions
                            </div>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-sm font-medium text-gray-900">
                            €${merchant.total_amount.toLocaleString('en-US', {minimumFractionDigits: 2})}
                        </div>
                    </div>
                </div>
            `).join('');
        }

        function updateProcessingStatusChart(statusData) {
            const ctx = document.getElementById('processingStatusChart').getContext('2d');

            if (charts.processingStatus) {
                charts.processingStatus.destroy();
            }

            if (!statusData || statusData.length === 0) {
                return;
            }

            const labels = statusData.map(item => item.status.charAt(0).toUpperCase() + item.status.slice(1));
            const data = statusData.map(item => item.count);
            const colors = {
                'Processed': 'rgba(34, 197, 94, 0.8)',
                'Pending': 'rgba(251, 191, 36, 0.8)',
                'Failed': 'rgba(239, 68, 68, 0.8)',
                'Processing': 'rgba(59, 130, 246, 0.8)'
            };

            charts.processingStatus = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: data,
                        backgroundColor: labels.map(label => colors[label] || 'rgba(156, 163, 175, 0.8)'),
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }

        function updateRecentFiles(filesData) {
            const tbody = document.getElementById('recentFilesTableBody');

            if (!filesData || filesData.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="px-6 py-4 text-center text-gray-500">No recent files found</td></tr>';
                return;
            }

            tbody.innerHTML = filesData.map(file => `
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                        ${file.filename}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="px-2 py-1 text-xs rounded-full ${getStatusColor(file.status)}">
                            ${file.status}
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        ${file.transaction_count.toLocaleString()}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        ${file.match_rate.toFixed(1)}%
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        ${formatFileSize(file.file_size)}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        ${new Date(file.created_at).toLocaleDateString()}
                    </td>
                </tr>
            `).join('');
        }

        function updateHealthStatus(statusData) {
            // This would be implemented based on actual health check results
            const badge = document.getElementById('healthStatusBadge');
            const content = document.getElementById('healthStatusContent');

            // Mock health status - replace with actual implementation
            const isHealthy = true;

            if (isHealthy) {
                badge.className = 'ml-3 px-2 py-1 text-xs rounded-full bg-green-100 text-green-800';
                badge.textContent = 'Healthy';
                content.innerHTML = `
                    <div class="flex items-center text-green-600">
                        <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                        </svg>
                        All systems are operating normally
                    </div>
                `;
            } else {
                badge.className = 'ml-3 px-2 py-1 text-xs rounded-full bg-red-100 text-red-800';
                badge.textContent = 'Issues Detected';
                content.innerHTML = `
                    <div class="text-red-600">
                        <div class="flex items-center mb-2">
                            <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                            </svg>
                            System issues detected
                        </div>
                        <ul class="text-sm list-disc list-inside ml-7">
                            <li>Example issue 1</li>
                            <li>Example issue 2</li>
                        </ul>
                    </div>
                `;
            }
        }

        function getStatusColor(status) {
            switch (status.toLowerCase()) {
                case 'processed':
                    return 'bg-green-100 text-green-800';
                case 'processing':
                    return 'bg-blue-100 text-blue-800';
                case 'failed':
                    return 'bg-red-100 text-red-800';
                case 'pending':
                    return 'bg-yellow-100 text-yellow-800';
                default:
                    return 'bg-gray-100 text-gray-800';
            }
        }

        function formatFileSize(bytes) {
            if (!bytes) return 'N/A';
            const units = ['B', 'KB', 'MB', 'GB'];
            let size = bytes;
            let unitIndex = 0;

            while (size >= 1024 && unitIndex < units.length - 1) {
                size /= 1024;
                unitIndex++;
            }

            return size.toFixed(1) + ' ' + units[unitIndex];
        }

        // Cleanup on page unload
        window.addEventListener('beforeunload', function() {
            if (refreshInterval) {
                clearInterval(refreshInterval);
            }

            // Destroy all charts to prevent memory leaks
            Object.values(charts).forEach(chart => {
                if (chart) chart.destroy();
            });
        });
    </script>
</x-app-layout>
