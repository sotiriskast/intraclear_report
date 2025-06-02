<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Decta Dashboard') }}
            </h2>
        </div>
    </x-slot>

    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/date-fns@2.29.3/index.min.js"></script>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
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

            <!-- Key Performance Indicators -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
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

                <!-- Approved Transactions -->
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
                                    <dt class="text-sm font-medium text-gray-500 truncate">Approved</dt>
                                    <dd class="flex items-baseline">
                                        <div id="approvedTransactions" class="text-2xl font-semibold text-gray-900">-</div>
                                        <div id="approvedTransactionsChange" class="ml-2 flex items-baseline text-sm font-semibold">
                                            <!-- Change indicator will be populated -->
                                        </div>
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Declined Transactions -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-red-500 rounded-full flex items-center justify-center">
                                    <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Declined</dt>
                                    <dd class="flex items-baseline">
                                        <div id="declinedTransactions" class="text-2xl font-semibold text-gray-900">-</div>
                                        <div class="ml-2">
                                            <button onclick="showDeclinedTransactions()" class="text-sm text-red-600 hover:text-red-500">
                                                View →
                                            </button>
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
                                <div class="w-8 h-8 bg-purple-500 rounded-full flex items-center justify-center">
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

                <!-- Approval Rate -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-indigo-500 rounded-full flex items-center justify-center">
                                    <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Approval Rate</dt>
                                    <dd class="flex items-baseline">
                                        <div id="approvalRate" class="text-2xl font-semibold text-gray-900">-%</div>
                                        <div id="approvalRateChange" class="ml-2 flex items-baseline text-sm font-semibold">
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
                                    <dt class="text-sm font-medium text-gray-500 truncate">Primary Amount</dt>
                                    <dd class="flex items-baseline">
                                        <div id="totalAmount" class="text-2xl font-semibold text-gray-900">-</div>
                                        <div id="totalAmountChange" class="ml-2 flex items-baseline text-sm font-semibold">
                                            <!-- Change indicator will be populated -->
                                        </div>
                                    </dd>
                                    <dd class="mt-1">
                                        <button onclick="showCurrencyBreakdown()" class="text-xs text-blue-600 hover:text-blue-500" id="currencyBreakdownBtn">
                                            View by currency →
                                        </button>
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Currency Breakdown Modal -->
            <div id="currencyModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
                <div class="relative top-20 mx-auto p-5 border w-11/12 max-w-4xl shadow-lg rounded-md bg-white">
                    <div class="mt-3">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-medium text-gray-900">Amount by Currency</h3>
                            <button onclick="closeCurrencyModal()" class="text-gray-400 hover:text-gray-600">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Currency</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Amount</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Matched Amount</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Approved Amount</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Declined Amount</th>
                                </tr>
                                </thead>
                                <tbody id="currencyBreakdownTableBody" class="bg-white divide-y divide-gray-200">
                                <!-- Will be populated by JavaScript -->
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-4 flex justify-end">
                            <button onclick="closeCurrencyModal()" class="px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-600">
                                Close
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Declined Transactions Modal -->
            <div id="declinedModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
                <div class="relative top-20 mx-auto p-5 border w-11/12 max-w-7xl shadow-lg rounded-md bg-white">
                    <div class="mt-3">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-medium text-gray-900">Declined Transactions</h3>
                            <button onclick="closeDeclinedModal()" class="text-gray-400 hover:text-gray-600">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment ID</th>
                                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Gateway TRX ID</th>
                                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Merchant</th>
                                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reason</th>
                                </tr>
                                </thead>
                                <tbody id="declinedTransactionsTableBody" class="bg-white divide-y divide-gray-200">
                                <!-- Will be populated by JavaScript -->
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-4 flex justify-end">
                            <button onclick="closeDeclinedModal()" class="px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-600">
                                Close
                            </button>
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

                <!-- Approval Rate Trends Chart -->
                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                    <div class="bg-gradient-to-r from-green-500 to-green-600 text-white px-6 py-4">
                        <h3 class="text-lg font-medium flex items-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 8v8m-4-5v5m-4-2v2m-2 4h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                            Approval Rate Trends (7 Days)
                        </h3>
                    </div>
                    <div class="p-6">
                        <canvas id="approvalRateTrendsChart" width="400" height="200"></canvas>
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
        let dashboardData = null;

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
                        dashboardData = data.data;
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
            document.getElementById('approvedTransactions').textContent = summary.approved_transactions.toLocaleString();
            document.getElementById('declinedTransactions').textContent = summary.declined_transactions.toLocaleString();
            document.getElementById('matchRate').textContent = summary.match_rate.toFixed(1) + '%';
            document.getElementById('approvalRate').textContent = summary.approval_rate.toFixed(1) + '%';

            // Handle multi-currency amounts
            if (summary.primary_currency_amount) {
                const primaryAmount = summary.primary_currency_amount;
                document.getElementById('totalAmount').textContent =
                    primaryAmount.amount.toLocaleString('en-US', {
                        style: 'currency',
                        currency: primaryAmount.currency,
                        minimumFractionDigits: 2
                    });
            } else {
                document.getElementById('totalAmount').textContent = 'Multi-currency';
            }

            // Update change indicators
            updateChangeIndicator('totalTransactionsChange', summary.today_transactions - summary.yesterday_transactions);
            updateChangeIndicator('approvedTransactionsChange', summary.today_approved - summary.yesterday_approved);

            // Handle amount change for primary currency
            if (summary.today_primary_amount && summary.yesterday_primary_amount) {
                const todayAmount = summary.today_primary_amount.amount || 0;
                const yesterdayAmount = summary.yesterday_primary_amount.amount || 0;
                updateChangeIndicator('totalAmountChange', todayAmount - yesterdayAmount, summary.primary_currency_amount?.currency);
            } else {
                document.getElementById('totalAmountChange').innerHTML = '<span class="text-gray-500">-</span>';
            }
        }

        function updateChangeIndicator(elementId, change, currency = null) {
            const element = document.getElementById(elementId);
            const formatValue = currency ?
                (val) => val.toLocaleString('en-US', {style: 'currency', currency: currency}) :
                (val) => Math.abs(val).toLocaleString();

            if (change > 0) {
                element.innerHTML = `
                    <svg class="self-center flex-shrink-0 h-5 w-5 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M5.293 7.707a1 1 0 010-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 01-1.414 1.414L10 4.414 6.707 7.707a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                    </svg>
                    <span class="text-green-600">+${formatValue(change)}</span>
                `;
            } else if (change < 0) {
                element.innerHTML = `
                    <svg class="self-center flex-shrink-0 h-5 w-5 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M14.707 12.293a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L10 15.586l3.293-3.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                    </svg>
                    <span class="text-red-600">-${formatValue(change)}</span>
                `;
            } else {
                element.innerHTML = '<span class="text-gray-500">No change</span>';
            }
        }

        // Currency breakdown modal functions
        function showCurrencyBreakdown() {
            if (!dashboardData || !dashboardData.summary.amounts_by_currency) {
                alert('Currency data not available');
                return;
            }

            document.getElementById('currencyModal').classList.remove('hidden');
            updateCurrencyBreakdownTable(dashboardData.summary.amounts_by_currency);
        }

        function closeCurrencyModal() {
            document.getElementById('currencyModal').classList.add('hidden');
        }

        function updateCurrencyBreakdownTable(currencyData) {
            const tbody = document.getElementById('currencyBreakdownTableBody');

            if (!currencyData || currencyData.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" class="px-6 py-4 text-center text-gray-500">No currency data available</td></tr>';
                return;
            }

            tbody.innerHTML = currencyData.map(currency => `
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                        ${currency.currency}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        ${currency.total_amount.toLocaleString('en-US', {
                style: 'currency',
                currency: currency.currency,
                minimumFractionDigits: 2
            })}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        ${currency.matched_amount.toLocaleString('en-US', {
                style: 'currency',
                currency: currency.currency,
                minimumFractionDigits: 2
            })}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        ${currency.approved_amount.toLocaleString('en-US', {
                style: 'currency',
                currency: currency.currency,
                minimumFractionDigits: 2
            })}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        ${currency.declined_amount.toLocaleString('en-US', {
                style: 'currency',
                currency: currency.currency,
                minimumFractionDigits: 2
            })}
                    </td>
                </tr>
            `).join('');
        }

        // Declined transactions modal functions
        function showDeclinedTransactions() {
            document.getElementById('declinedModal').classList.remove('hidden');
            loadDeclinedTransactions();
        }

        function closeDeclinedModal() {
            document.getElementById('declinedModal').classList.add('hidden');
        }

        function loadDeclinedTransactions() {
            fetch('/decta/reports/declined-transactions?limit=50')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateDeclinedTransactionsTable(data.data);
                    } else {
                        console.error('Failed to load declined transactions:', data.message);
                    }
                })
                .catch(error => {
                    console.error('Error loading declined transactions:', error);
                });
        }

        function updateDeclinedTransactionsTable(transactions) {
            const tbody = document.getElementById('declinedTransactionsTableBody');

            if (!transactions || transactions.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" class="px-6 py-4 text-center text-gray-500">No declined transactions found</td></tr>';
                return;
            }

            tbody.innerHTML = transactions.map(transaction => `
                <tr class="hover:bg-gray-50">
                    <td class="px-3 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                        ${transaction.id}
                    </td>
                    <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-900">
                        ${transaction.payment_id}
                    </td>
                    <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-900">
                        ${transaction.gateway_trx_id || '-'}
                    </td>
                    <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-900">
                        ${new Date(transaction.transaction_date).toLocaleDateString()}
                    </td>
                    <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-900">
                        ${transaction.amount.toFixed(2)} ${transaction.currency}
                    </td>
                    <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-900">
                        ${transaction.merchant_name || transaction.merchant_id || '-'}
                    </td>
                    <td class="px-3 py-4 text-sm text-gray-900">
                        ${transaction.error_message || 'No reason provided'}
                    </td>
                </tr>
            `).join('');
        }

        function updateCharts(data) {
            updateTransactionTrendsChart(data.matching_trends);
            updateApprovalRateTrendsChart(data.approval_trends);
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

        function updateApprovalRateTrendsChart(approvalData) {
            const ctx = document.getElementById('approvalRateTrendsChart').getContext('2d');

            if (charts.approvalRateTrends) {
                charts.approvalRateTrends.destroy();
            }

            const labels = approvalData?.map(item => new Date(item.date).toLocaleDateString()) || [];
            const approvedData = approvalData?.map(item => item.approved) || [];
            const declinedData = approvalData?.map(item => item.declined) || [];

            charts.approvalRateTrends = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Approved',
                        data: approvedData,
                        backgroundColor: 'rgba(34, 197, 94, 0.8)',
                        borderColor: 'rgb(34, 197, 94)',
                        borderWidth: 1
                    }, {
                        label: 'Declined',
                        data: declinedData,
                        backgroundColor: 'rgba(239, 68, 68, 0.8)',
                        borderColor: 'rgb(239, 68, 68)',
                        borderWidth: 1
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

            console.log('Merchant data received:', merchantsData); // Debug log

            // Group merchants by normalized name to catch any duplicates that made it through
            const groupedMerchants = groupMerchantsByName(merchantsData);

            container.innerHTML = groupedMerchants.map((merchant, index) => {
                const currencyDisplay = merchant.is_multi_currency
                    ? createMultiCurrencyDisplay(merchant)
                    : createSingleCurrencyDisplay(merchant);

                return `
            <div class="flex items-center justify-between p-3 rounded-lg bg-gray-50 hover:bg-gray-100 transition-colors duration-200 cursor-pointer"
                 onclick="showMerchantDetails('${merchant.merchant_id}', '${merchant.merchant_name.replace(/'/g, "\\'")}', ${JSON.stringify(merchant.currency_breakdown).replace(/"/g, '&quot;')})">
                <div class="flex items-center">
                    <div class="flex-shrink-0 w-8 h-8 bg-indigo-100 text-indigo-800 rounded-full flex items-center justify-center text-sm font-medium">
                        ${index + 1}
                    </div>
                    <div class="ml-3">
                        <div class="text-sm font-medium text-gray-900 flex items-center">
                            ${merchant.merchant_name}
                            ${merchant.is_multi_currency ?
                    `<span class="ml-2 px-2 py-0.5 text-xs bg-blue-100 text-blue-800 rounded-full">${merchant.currency_count} currencies</span>`
                    : ''
                }
                        </div>
                        <div class="text-xs text-gray-500">
                            ${merchant.total_transactions.toLocaleString()} total transactions
                        </div>
                        ${merchant.is_multi_currency ?
                    `<div class="text-xs text-blue-600">Click for currency breakdown →</div>`
                    : ''
                }
                    </div>
                </div>
                <div class="text-right">
                    ${currencyDisplay}
                </div>
            </div>
        `;
            }).join('');
        }
        function mergeMerchants(existing, newMerchant) {
            // Use the merchant with more transactions as primary
            const primary = existing.total_transactions >= newMerchant.total_transactions ? existing : newMerchant;
            const secondary = existing.total_transactions >= newMerchant.total_transactions ? newMerchant : existing;

            // Merge currency breakdowns
            const currencyMap = new Map();

            // Add existing currencies
            if (primary.currency_breakdown) {
                primary.currency_breakdown.forEach(curr => {
                    currencyMap.set(curr.currency, curr);
                });
            }

            // Merge secondary currencies
            if (secondary.currency_breakdown) {
                secondary.currency_breakdown.forEach(curr => {
                    if (currencyMap.has(curr.currency)) {
                        const existing = currencyMap.get(curr.currency);
                        existing.transaction_count += curr.transaction_count;
                        existing.total_amount += curr.total_amount;
                    } else {
                        currencyMap.set(curr.currency, curr);
                    }
                });
            }

            const mergedCurrencies = Array.from(currencyMap.values())
                .sort((a, b) => b.transaction_count - a.transaction_count);

            // Recalculate percentages
            const totalTransactions = mergedCurrencies.reduce((sum, curr) => sum + curr.transaction_count, 0);
            mergedCurrencies.forEach(curr => {
                curr.percentage = totalTransactions > 0 ?
                    Math.round((curr.transaction_count / totalTransactions) * 100 * 10) / 10 : 0;
            });

            return {
                ...primary,
                total_transactions: totalTransactions,
                currency_count: mergedCurrencies.length,
                is_multi_currency: mergedCurrencies.length > 1,
                currency_breakdown: mergedCurrencies,
                dominant_currency: mergedCurrencies[0]?.currency || 'N/A',
                dominant_currency_amount: mergedCurrencies[0]?.total_amount || 0,
                dominant_currency_transactions: mergedCurrencies[0]?.transaction_count || 0
            };
        }

        function normalizeMerchantName(name) {
            if (!name) return 'unknown';
            return name.toLowerCase()
                .trim()
                .replace(/[^a-z0-9]/g, '') // Remove special characters
                .replace(/\s+/g, ''); // Remove spaces
        }
        function groupMerchantsByName(merchants) {
            const grouped = new Map();

            merchants.forEach(merchant => {
                const normalizedName = normalizeMerchantName(merchant.merchant_name);

                if (grouped.has(normalizedName)) {
                    // Merge with existing merchant
                    const existing = grouped.get(normalizedName);
                    const merged = mergeMerchants(existing, merchant);
                    grouped.set(normalizedName, merged);
                } else {
                    grouped.set(normalizedName, merchant);
                }
            });

            // Convert back to array and sort by transaction count
            return Array.from(grouped.values()).sort((a, b) => b.total_transactions - a.total_transactions);
        }
        function createSingleCurrencyDisplay(merchant) {
            const currency = merchant.currency_breakdown && merchant.currency_breakdown[0]
                ? merchant.currency_breakdown[0]
                : {
                    currency: merchant.dominant_currency || 'N/A',
                    total_amount: merchant.dominant_currency_amount || 0
                };

            return `
        <div class="text-sm font-medium text-gray-900">
            ${currency.total_amount.toLocaleString('en-US', {
                style: 'currency',
                currency: currency.currency,
                minimumFractionDigits: 2
            })}
        </div>
        <div class="text-xs text-gray-500">${currency.currency}</div>
    `;
        }

        function createMultiCurrencyDisplay(merchant) {
            const topCurrencies = merchant.currency_breakdown
                ? merchant.currency_breakdown.slice(0, 2)
                : [];

            if (topCurrencies.length === 0) {
                return `
            <div class="text-sm font-medium text-gray-900">
                ${merchant.total_transactions.toLocaleString()} txns
            </div>
            <div class="text-xs text-gray-500">Multi-currency</div>
        `;
            }

            return `
        <div class="text-right">
            ${topCurrencies.map(currency => `
                <div class="text-sm font-medium text-gray-900">
                    ${currency.total_amount.toLocaleString('en-US', {
                style: 'currency',
                currency: currency.currency,
                minimumFractionDigits: 0,
                maximumFractionDigits: 0
            })}
                </div>
            `).join('')}
            ${merchant.currency_count > 2 ?
                `<div class="text-xs text-gray-500">+${merchant.currency_count - 2} more</div>`
                : ''
            }
        </div>
    `;
        }
        function showMerchantDetails(merchantId, merchantName, currencyBreakdown) {
            const modalHTML = `
        <div id="merchantDetailsModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
            <div class="relative top-20 mx-auto p-5 border w-11/12 max-w-5xl shadow-lg rounded-md bg-white">
                <div class="mt-3">
                    <div class="flex items-center justify-between mb-6">
                        <div>
                            <h3 class="text-xl font-medium text-gray-900">${merchantName}</h3>
                            <p class="text-sm text-gray-500 mt-1">Transaction breakdown by currency (Last 30 days)</p>
                        </div>
                        <button onclick="closeMerchantDetailsModal()" class="text-gray-400 hover:text-gray-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>

                    <!-- Summary Cards -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                        <div class="bg-blue-50 p-4 rounded-lg">
                            <div class="text-2xl font-bold text-blue-900">
                                ${currencyBreakdown.reduce((sum, curr) => sum + curr.transaction_count, 0).toLocaleString()}
                            </div>
                            <div class="text-sm text-blue-600">Total Transactions</div>
                        </div>
                        <div class="bg-green-50 p-4 rounded-lg">
                            <div class="text-2xl font-bold text-green-900">${currencyBreakdown.length}</div>
                            <div class="text-sm text-green-600">Currencies Used</div>
                        </div>
                        <div class="bg-purple-50 p-4 rounded-lg">
                            <div class="text-2xl font-bold text-purple-900">
                                ${currencyBreakdown[0]?.currency || 'N/A'}
                            </div>
                            <div class="text-sm text-purple-600">Primary Currency</div>
                        </div>
                    </div>

                    <!-- Currency Breakdown Table -->
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Currency</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Transactions</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Amount</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">% of Total</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Avg per Transaction</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                ${currencyBreakdown.map((currency, index) => {
                const avgAmount = currency.transaction_count > 0 ? currency.total_amount / currency.transaction_count : 0;
                const isTopCurrency = index === 0;

                return `
                                        <tr class="${isTopCurrency ? 'bg-blue-50' : 'hover:bg-gray-50'}">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <span class="text-sm font-medium text-gray-900">${currency.currency}</span>
                                                    ${isTopCurrency ? '<span class="ml-2 px-2 py-1 text-xs bg-blue-200 text-blue-800 rounded-full">Primary</span>' : ''}
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                ${currency.transaction_count.toLocaleString()}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                ${currency.total_amount.toLocaleString('en-US', {
                    style: 'currency',
                    currency: currency.currency,
                    minimumFractionDigits: 2
                })}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <div class="flex items-center">
                                                    <div class="w-16 bg-gray-200 rounded-full h-2 mr-2">
                                                        <div class="bg-blue-600 h-2 rounded-full" style="width: ${currency.percentage}%"></div>
                                                    </div>
                                                    ${currency.percentage}%
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                ${avgAmount.toLocaleString('en-US', {
                    style: 'currency',
                    currency: currency.currency,
                    minimumFractionDigits: 2
                })}
                                            </td>
                                        </tr>
                                    `;
            }).join('')}
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-6 flex justify-end space-x-3">
                        <button onclick="exportMerchantData('${merchantId}', '${merchantName}')"
                                class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition-colors duration-200">
                            Export Data
                        </button>
                        <button onclick="closeMerchantDetailsModal()"
                                class="px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-600 transition-colors duration-200">
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;

            document.body.insertAdjacentHTML('beforeend', modalHTML);
        }

        function exportMerchantData(merchantId, merchantName) {
            // Create CSV export of the merchant's currency breakdown
            const merchant = dashboardData.top_merchants.find(m => m.merchant_id === merchantId);
            if (!merchant) return;

            let csvContent = "data:text/csv;charset=utf-8,";
            csvContent += `Merchant: ${merchantName}\n`;
            csvContent += `Period: Last 30 days\n`;
            csvContent += `Export Date: ${new Date().toLocaleDateString()}\n\n`;
            csvContent += "Currency,Transaction Count,Total Amount,Percentage,Average per Transaction\n";

            merchant.currency_breakdown.forEach(currency => {
                const avgAmount = currency.transaction_count > 0 ? currency.total_amount / currency.transaction_count : 0;
                csvContent += `${currency.currency},${currency.transaction_count},${currency.total_amount.toFixed(2)},${currency.percentage}%,${avgAmount.toFixed(2)}\n`;
            });

            const encodedUri = encodeURI(csvContent);
            const link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", `merchant_${merchantId}_currency_breakdown.csv`);
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
        function displayMerchantCurrencyModal(merchantData) {
            // Create and show modal with detailed currency breakdown
            const modalHTML = `
        <div id="merchantCurrencyModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
            <div class="relative top-20 mx-auto p-5 border w-11/12 max-w-4xl shadow-lg rounded-md bg-white">
                <div class="mt-3">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-medium text-gray-900">
                            Currency Breakdown - ${merchantData.merchant_name}
                        </h3>
                        <button onclick="closeMerchantCurrencyModal()" class="text-gray-400 hover:text-gray-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Currency</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Transactions</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total Amount</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">% of Total Txns</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                ${merchantData.currencies.map(currency => `
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            ${currency.currency}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            ${currency.transaction_count.toLocaleString()}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            ${currency.total_amount.toLocaleString('en-US', {
                style: 'currency',
                currency: currency.currency,
                minimumFractionDigits: 2
            })}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            ${((currency.transaction_count / merchantData.total_transactions) * 100).toFixed(1)}%
                                        </td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-4 flex justify-end">
                        <button onclick="closeMerchantCurrencyModal()" class="px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-600">
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;

            document.body.insertAdjacentHTML('beforeend', modalHTML);
        }
        function closeMerchantDetailsModal() {
            const modal = document.getElementById('merchantDetailsModal');
            if (modal) {
                modal.remove();
            }
        }
        function closeMerchantCurrencyModal() {
            const modal = document.getElementById('merchantCurrencyModal');
            if (modal) {
                modal.remove();
            }
        }
        function showMerchantCurrencyBreakdown(merchantId) {
            // Fetch detailed currency breakdown
            fetch(`/decta/reports/merchant-currency-breakdown/${merchantId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayMerchantCurrencyModal(data.data);
                    }
                })
                .catch(error => {
                    console.error('Error loading merchant currency breakdown:', error);
                });
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
        function debugMerchantGrouping() {
            fetch('/decta/reports/debug-merchants')
                .then(response => response.json())
                .then(data => {
                    console.log('Raw merchant debug data:', data);

                    if (data.debug_data.fintzero_specific) {
                        console.log('Fintzero specific data:', data.debug_data.fintzero_specific);
                        console.log('Number of fintzero entries:', data.debug_data.fintzero_specific.length);
                    }

                    if (data.debug_data.duplicate_groups) {
                        console.log('Duplicate groups found:', Object.keys(data.debug_data.duplicate_groups).length);
                        Object.entries(data.debug_data.duplicate_groups).forEach(([name, merchants]) => {
                            console.log(`Duplicate group "${name}":`, merchants);
                        });
                    }
                })
                .catch(error => {
                    console.error('Debug request failed:', error);
                });
        }

        // Test the merchant grouping
        function testMerchantGrouping() {
            fetch('/decta/reports/test-merchant-grouping')
                .then(response => response.json())
                .then(data => {
                    console.log('Merchant grouping test results:', data);

                    if (data.comparison) {
                        console.log('Original method merchants:', data.comparison.original_method.count);
                        console.log('Improved method merchants:', data.comparison.improved_method.count);

                        // Check for fintzero in both methods
                        const originalFintzero = data.comparison.original_method.merchants.filter(m =>
                            m.name.toLowerCase().includes('fintzero'));
                        const improvedFintzero = data.comparison.improved_method.merchants.filter(m =>
                            m.name.toLowerCase().includes('fintzero'));

                        console.log('Fintzero in original method:', originalFintzero);
                        console.log('Fintzero in improved method:', improvedFintzero);
                    }
                })
                .catch(error => {
                    console.error('Test request failed:', error);
                });
        }

        // Add debug buttons to the dashboard (for development only)
        function addDebugButtons() {
            if (window.location.hostname === 'localhost' || window.location.hostname.includes('dev')) {
                const debugContainer = document.createElement('div');
                debugContainer.className = 'fixed bottom-4 right-4 space-y-2';
                debugContainer.innerHTML = `
            <button onclick="debugMerchantGrouping()"
                    class="block px-3 py-1 bg-red-500 text-white text-xs rounded hover:bg-red-600">
                Debug Merchants
            </button>
            <button onclick="testMerchantGrouping()"
                    class="block px-3 py-1 bg-blue-500 text-white text-xs rounded hover:bg-blue-600">
                Test Grouping
            </button>
        `;
                document.body.appendChild(debugContainer);
            }
        }

        // Initialize debug buttons when page loads
        document.addEventListener('DOMContentLoaded', function() {
            // Add debug buttons for development
            addDebugButtons();
        });

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
