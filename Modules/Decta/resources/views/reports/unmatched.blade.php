<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Unmatched Transactions') }}
            </h2>
            <div class="flex space-x-3">
                <button id="bulkActionsBtn"
                        class="inline-flex items-center px-4 py-2 bg-orange-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-orange-700 disabled:opacity-50 transition ease-in-out duration-150"
                        disabled>
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                    </svg>
                    Bulk Actions
                </button>
                <a href="{{ route('decta.reports.index') }}"
                   class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 transition ease-in-out duration-150">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    Back to Reports
                </a>
            </div>
        </div>
    </x-slot>

    <!-- Flatpickr CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/material_blue.css">

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <!-- Summary Stats -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
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
                                    <dt class="text-sm font-medium text-gray-500 truncate">Total Unmatched</dt>
                                    <dd id="total-unmatched" class="text-2xl font-semibold text-gray-900">0</dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

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
                                    <dd id="total-amount" class="text-2xl font-semibold text-gray-900">€0.00</dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center">
                                    <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">High Priority</dt>
                                    <dd id="high-priority" class="text-2xl font-semibold text-gray-900">0</dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-purple-500 rounded-full flex items-center justify-center">
                                    <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3z"></path>
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Merchants</dt>
                                    <dd id="unique-merchants" class="text-2xl font-semibold text-gray-900">0</dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters Panel -->
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                <div class="bg-gradient-to-r from-gray-500 to-gray-600 text-white px-6 py-4 border-b border-gray-200">
                    <h3 class="text-xl font-semibold flex items-center">
                        <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 100 4m0-4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 100 4m0-4v2m0-6V4"></path>
                        </svg>
                        Filters
                    </h3>
                </div>

                <div class="p-6">
                    <form id="filtersForm" class="space-y-4">
                        <!-- Date Range -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="date_from" class="block text-sm font-medium text-gray-700 mb-2">From Date</label>
                                <input type="text" id="date_from" name="date_from" class="flatpickr mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50" placeholder="Select start date">
                            </div>
                            <div>
                                <label for="date_to" class="block text-sm font-medium text-gray-700 mb-2">To Date</label>
                                <input type="text" id="date_to" name="date_to" class="flatpickr mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50" placeholder="Select end date">
                            </div>
                        </div>

                        <!-- Quick Date Presets -->
                        <div class="flex flex-wrap gap-2">
                            <button type="button" class="date-preset px-3 py-1 text-sm bg-gray-100 hover:bg-gray-200 rounded-md transition-colors" data-days="1">Today</button>
                            <button type="button" class="date-preset px-3 py-1 text-sm bg-gray-100 hover:bg-gray-200 rounded-md transition-colors" data-days="7">Last 7 Days</button>
                            <button type="button" class="date-preset px-3 py-1 text-sm bg-gray-100 hover:bg-gray-200 rounded-md transition-colors" data-days="30">Last 30 Days</button>
                            <button type="button" class="date-preset px-3 py-1 text-sm bg-gray-100 hover:bg-gray-200 rounded-md transition-colors" data-type="this_month">This Month</button>
                        </div>

                        <!-- Other Filters -->
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div>
                                <label for="merchant_id" class="block text-sm font-medium text-gray-700 mb-2">Merchant ID</label>
                                <input type="text" id="merchant_id" name="merchant_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50" placeholder="Filter by merchant">
                            </div>

                            <div>
                                <label for="currency" class="block text-sm font-medium text-gray-700 mb-2">Currency</label>
                                <select id="currency" name="currency" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                    <option value="">All Currencies</option>
                                    <option value="EUR">EUR</option>
                                    <option value="USD">USD</option>
                                    <option value="GBP">GBP</option>
                                    <option value="CHF">CHF</option>
                                </select>
                            </div>

                            <div>
                                <label for="min_amount" class="block text-sm font-medium text-gray-700 mb-2">Min Amount (€)</label>
                                <input type="number" id="min_amount" name="min_amount" step="0.01" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50" placeholder="0.00">
                            </div>

                            <div>
                                <label for="priority" class="block text-sm font-medium text-gray-700 mb-2">Priority</label>
                                <select id="priority" name="priority" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                    <option value="">All Priorities</option>
                                    <option value="high">High Value (>€100)</option>
                                    <option value="has_approval">Has Approval ID</option>
                                    <option value="has_reference">Has Reference</option>
                                </select>
                            </div>
                        </div>

                        <!-- Apply Filters Button -->
                        <div class="flex justify-center">
                            <button type="submit" class="inline-flex items-center px-6 py-3 bg-indigo-600 border border-transparent rounded-md font-semibold text-sm text-white uppercase tracking-wider hover:bg-indigo-700 transition ease-in-out duration-150">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                </svg>
                                Apply Filters
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Transactions Table -->
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                <div class="bg-red-50 px-6 py-4 border-b border-gray-200">
                    <div class="flex justify-between items-center">
                        <h3 class="text-xl font-semibold text-gray-900 flex items-center">
                            <svg class="w-6 h-6 mr-2 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                            </svg>
                            Unmatched Transactions
                            <span id="transactionCount" class="ml-2 px-2 py-1 bg-red-100 text-red-800 text-sm rounded-full">0</span>
                        </h3>

                        <div class="flex items-center space-x-3">
                            <label class="inline-flex items-center">
                                <input type="checkbox" id="selectAll" class="form-checkbox h-4 w-4 text-indigo-600">
                                <span class="ml-2 text-sm text-gray-700">Select All</span>
                            </label>

                            <div class="relative">
                                <select id="bulkActionSelect" class="block appearance-none bg-white border border-gray-300 text-gray-700 py-2 px-4 pr-8 rounded leading-tight focus:outline-none focus:bg-white focus:border-gray-500">
                                    <option value="">Bulk Actions</option>
                                    <option value="retry">Retry Matching</option>
                                    <option value="mark_failed">Mark as Failed</option>
                                    <option value="export">Export Selected</option>
                                </select>
                            </div>

                            <button id="executeBulkAction" class="px-4 py-2 bg-orange-600 text-white rounded hover:bg-orange-700 disabled:opacity-50" disabled>
                                Execute
                            </button>
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table id="transactionsTable" class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <input type="checkbox" id="headerCheckbox" class="form-checkbox h-4 w-4 text-indigo-600">
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Currency</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Merchant</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Attempts</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Priority</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                        </thead>
                        <tbody id="transactionsTableBody" class="bg-white divide-y divide-gray-200">
                        <!-- Content will be loaded via JavaScript -->
                        <tr>
                            <td colspan="9" class="px-6 py-4 text-center text-gray-500">
                                <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-indigo-500 mx-auto mb-2"></div>
                                Loading unmatched transactions...
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="bg-white px-6 py-3 border-t border-gray-200">
                    <div class="flex items-center justify-between">
                        <div class="text-sm text-gray-700">
                            Showing <span id="showingFrom">0</span> to <span id="showingTo">0</span> of <span id="totalRecords">0</span> results
                        </div>
                        <div class="flex space-x-2">
                            <button id="prevPage" class="px-3 py-1 bg-gray-200 text-gray-700 rounded hover:bg-gray-300 disabled:opacity-50" disabled>Previous</button>
                            <span id="currentPage" class="px-3 py-1 bg-indigo-500 text-white rounded">1</span>
                            <button id="nextPage" class="px-3 py-1 bg-gray-200 text-gray-700 rounded hover:bg-gray-300 disabled:opacity-50" disabled>Next</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Manual Match Modal -->
    <div id="manualMatchModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-1/2 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Manual Transaction Matching</h3>
                    <button id="closeModalBtn" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <div id="selectedTransactionInfo" class="mb-4 p-3 bg-gray-100 rounded">
                    <!-- Selected transaction info will be populated here -->
                </div>

                <form id="manualMatchForm" class="space-y-4">
                    <input type="hidden" id="modalTransactionId" name="transaction_id">

                    <div>
                        <label for="gateway_transaction_id" class="block text-sm font-medium text-gray-700">Gateway Transaction ID</label>
                        <input type="number" id="gateway_transaction_id" name="gateway_transaction_id" required
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                    </div>

                    <div>
                        <label for="account_id" class="block text-sm font-medium text-gray-700">Account ID</label>
                        <input type="number" id="account_id" name="account_id" required
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                    </div>

                    <div>
                        <label for="shop_id" class="block text-sm font-medium text-gray-700">Shop ID</label>
                        <input type="number" id="shop_id" name="shop_id" required
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                    </div>

                    <div>
                        <label for="trx_id" class="block text-sm font-medium text-gray-700">TRX ID</label>
                        <input type="text" id="trx_id" name="trx_id" required
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                    </div>

                    <div class="flex justify-end space-x-3 pt-4">
                        <button type="button" id="cancelMatchBtn"
                                class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 transition duration-150">
                            Cancel
                        </button>
                        <button type="submit"
                                class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 transition duration-150">
                            Submit Match
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Flatpickr JS -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <script>
        let currentPage = 1;
        let totalPages = 1;
        let selectedTransactions = new Set();
        let currentFilters = {};

        document.addEventListener('DOMContentLoaded', function() {
            initializeDatePickers();
            loadUnmatchedTransactions();
            setupEventListeners();
        });

        function initializeDatePickers() {
            const dateFromPicker = flatpickr("#date_from", {
                altInput: true,
                altFormat: "F j, Y",
                dateFormat: "Y-m-d",
                maxDate: "today"
            });

            const dateToPicker = flatpickr("#date_to", {
                altInput: true,
                altFormat: "F j, Y",
                dateFormat: "Y-m-d",
                maxDate: "today"
            });

            // Date preset handlers
            document.querySelectorAll('.date-preset').forEach(button => {
                button.addEventListener('click', function() {
                    const days = this.dataset.days;
                    const type = this.dataset.type;

                    let fromDate, toDate;
                    const today = new Date();

                    if (days) {
                        toDate = new Date(today);
                        fromDate = new Date(today);
                        fromDate.setDate(today.getDate() - parseInt(days) + 1);
                    } else if (type === 'this_month') {
                        fromDate = new Date(today.getFullYear(), today.getMonth(), 1);
                        toDate = new Date(today);
                    }

                    if (fromDate && toDate) {
                        dateFromPicker.setDate(fromDate);
                        dateToPicker.setDate(toDate);
                    }

                    // Highlight active preset
                    document.querySelectorAll('.date-preset').forEach(btn => btn.classList.remove('bg-indigo-100', 'text-indigo-800'));
                    this.classList.add('bg-indigo-100', 'text-indigo-800');
                });
            });
        }

        function setupEventListeners() {
            // Filters form
            document.getElementById('filtersForm').addEventListener('submit', function(e) {
                e.preventDefault();
                applyFilters();
            });

            // Bulk actions
            document.getElementById('selectAll').addEventListener('change', toggleSelectAll);
            document.getElementById('headerCheckbox').addEventListener('change', toggleSelectAll);
            document.getElementById('executeBulkAction').addEventListener('click', executeBulkAction);
            document.getElementById('bulkActionSelect').addEventListener('change', updateBulkActionButton);

            // Pagination
            document.getElementById('prevPage').addEventListener('click', () => changePage(currentPage - 1));
            document.getElementById('nextPage').addEventListener('click', () => changePage(currentPage + 1));

            // Modal
            document.getElementById('closeModalBtn').addEventListener('click', closeModal);
            document.getElementById('cancelMatchBtn').addEventListener('click', closeModal);
            document.getElementById('manualMatchForm').addEventListener('submit', handleManualMatch);
        }

        function loadUnmatchedTransactions(filters = {}) {
            const params = new URLSearchParams(filters);
            params.append('limit', '50');
            params.append('offset', (currentPage - 1) * 50);

            fetch(`/decta/reports/unmatched?${params}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayTransactions(data.data);
                        updateSummaryStats(data.data);
                        updatePagination(data.data.length);
                    } else {
                        showError(data.message || 'Failed to load unmatched transactions');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showError('Network error occurred while loading transactions');
                });
        }

        function displayTransactions(data) {
            const tbody = document.getElementById('transactionsTableBody');

            if (data.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="9" class="px-6 py-4 text-center text-gray-500">
                            No unmatched transactions found for the selected criteria.
                        </td>
                    </tr>
                `;
                return;
            }

            tbody.innerHTML = data.map(transaction => {
                const priority = getPriority(transaction);
                const priorityClass = priority.class;
                const priorityText = priority.text;

                return `
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <input type="checkbox" class="transaction-checkbox form-checkbox h-4 w-4 text-indigo-600"
                                   value="${transaction.payment_id}">
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <a href="/decta/reports/transaction/${transaction.payment_id}"
                               class="text-indigo-600 hover:text-indigo-900 font-medium">
                                ${transaction.payment_id}
                            </a>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            ${new Date(transaction.transaction_date).toLocaleDateString()}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            €${transaction.amount.toFixed(2)}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            ${transaction.currency}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <div class="max-w-xs truncate" title="${transaction.merchant_name || transaction.merchant_id}">
                                ${transaction.merchant_name || transaction.merchant_id || 'N/A'}
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <span class="px-2 py-1 bg-gray-100 text-gray-800 text-xs rounded-full">
                                ${transaction.attempts ? transaction.attempts.length : 0}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 py-1 text-xs rounded-full ${priorityClass}">
                                ${priorityText}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm space-x-2">
                            <button onclick="openManualMatchModal('${transaction.payment_id}', ${JSON.stringify(transaction).replace(/"/g, '&quot;')})"
                                    class="text-indigo-600 hover:text-indigo-900">
                                Match
                            </button>
                            <button onclick="retryTransaction('${transaction.payment_id}')"
                                    class="text-green-600 hover:text-green-900">
                                Retry
                            </button>
                        </td>
                    </tr>
                `;
            }).join('');

            // Update checkbox event listeners
            document.querySelectorAll('.transaction-checkbox').forEach(checkbox => {
                checkbox.addEventListener('change', updateSelectedTransactions);
            });

            document.getElementById('transactionCount').textContent = data.length;
        }

        function getPriority(transaction) {
            if (transaction.amount > 100) {
                return { class: 'bg-red-100 text-red-800', text: 'High Value' };
            } else if (transaction.approval_id) {
                return { class: 'bg-yellow-100 text-yellow-800', text: 'Has Approval' };
            } else if (transaction.return_reference) {
                return { class: 'bg-blue-100 text-blue-800', text: 'Has Reference' };
            } else {
                return { class: 'bg-gray-100 text-gray-800', text: 'Normal' };
            }
        }

        function updateSummaryStats(data) {
            const totalUnmatched = data.length;
            const totalAmount = data.reduce((sum, t) => sum + t.amount, 0);
            const highPriority = data.filter(t => t.amount > 100).length;
            const uniqueMerchants = new Set(data.map(t => t.merchant_id).filter(id => id)).size;

            document.getElementById('total-unmatched').textContent = totalUnmatched.toLocaleString();
            document.getElementById('total-amount').textContent = `€${totalAmount.toFixed(2)}`;
            document.getElementById('high-priority').textContent = highPriority.toLocaleString();
            document.getElementById('unique-merchants').textContent = uniqueMerchants.toLocaleString();
        }

        function updatePagination(dataLength) {
            const showingFrom = (currentPage - 1) * 50 + 1;
            const showingTo = Math.min(currentPage * 50, showingFrom + dataLength - 1);

            document.getElementById('showingFrom').textContent = showingFrom;
            document.getElementById('showingTo').textContent = showingTo;
            document.getElementById('currentPage').textContent = currentPage;

            document.getElementById('prevPage').disabled = currentPage <= 1;
            document.getElementById('nextPage').disabled = dataLength < 50;
        }

        function applyFilters() {
            currentPage = 1;
            const formData = new FormData(document.getElementById('filtersForm'));
            currentFilters = Object.fromEntries(formData.entries());

            // Remove empty filters
            Object.keys(currentFilters).forEach(key => {
                if (!currentFilters[key]) {
                    delete currentFilters[key];
                }
            });

            loadUnmatchedTransactions(currentFilters);
        }

        function changePage(page) {
            if (page < 1) return;
            currentPage = page;
            loadUnmatchedTransactions(currentFilters);
        }

        function toggleSelectAll(e) {
            const isChecked = e.target.checked;
            document.querySelectorAll('.transaction-checkbox').forEach(checkbox => {
                checkbox.checked = isChecked;
            });
            updateSelectedTransactions();
        }

        function updateSelectedTransactions() {
            selectedTransactions.clear();
            document.querySelectorAll('.transaction-checkbox:checked').forEach(checkbox => {
                selectedTransactions.add(checkbox.value);
            });

            document.getElementById('bulkActionsBtn').disabled = selectedTransactions.size === 0;
            updateBulkActionButton();
        }

        function updateBulkActionButton() {
            const selectedAction = document.getElementById('bulkActionSelect').value;
            const hasSelection = selectedTransactions.size > 0;
            document.getElementById('executeBulkAction').disabled = !selectedAction || !hasSelection;
        }

        function executeBulkAction() {
            const action = document.getElementById('bulkActionSelect').value;
            const transactionIds = Array.from(selectedTransactions);

            if (!action || transactionIds.length === 0) return;

            if (!confirm(`Are you sure you want to ${action} ${transactionIds.length} transaction(s)?`)) {
                return;
            }

            const data = {
                action: action,
                transaction_ids: transactionIds.map(id => parseInt(id))
            };

            fetch('/decta/dashboard/bulk-operation', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify(data)
            })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        alert(`Bulk operation completed. ${result.affected_count} transactions affected.`);
                        loadUnmatchedTransactions(currentFilters);
                        selectedTransactions.clear();
                        document.getElementById('bulkActionSelect').value = '';
                        updateBulkActionButton();
                    } else {
                        alert('Bulk operation failed: ' + result.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Network error occurred');
                });
        }

        function openManualMatchModal(paymentId, transaction) {
            document.getElementById('modalTransactionId').value = paymentId;
            document.getElementById('selectedTransactionInfo').innerHTML = `
                <div class="text-sm">
                    <strong>Payment ID:</strong> ${paymentId}<br>
                    <strong>Amount:</strong> €${transaction.amount.toFixed(2)} ${transaction.currency}<br>
                    <strong>Merchant:</strong> ${transaction.merchant_name || transaction.merchant_id || 'N/A'}
                </div>
            `;
            document.getElementById('manualMatchModal').classList.remove('hidden');
        }

        function closeModal() {
            document.getElementById('manualMatchModal').classList.add('hidden');
            document.getElementById('manualMatchForm').reset();
        }

        function handleManualMatch(e) {
            e.preventDefault();
            const formData = new FormData(e.target);
            const data = Object.fromEntries(formData.entries());

            fetch('/decta/dashboard/manual-match', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify(data)
            })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        closeModal();
                        loadUnmatchedTransactions(currentFilters);
                        alert('Manual match completed successfully!');
                    } else {
                        alert('Manual match failed: ' + result.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Network error occurred');
                });
        }

        function retryTransaction(paymentId) {
            if (confirm('Are you sure you want to retry matching for this transaction?')) {
                // Implementation for single transaction retry
                executeBulkAction({
                    action: 'retry',
                    transaction_ids: [paymentId]
                });
            }
        }

        function showError(message) {
            const tbody = document.getElementById('transactionsTableBody');
            tbody.innerHTML = `
                <tr>
                    <td colspan="9" class="px-6 py-4 text-center text-red-500">
                        Error: ${message}
                    </td>
                </tr>
            `;
        }
    </script>
</x-app-layout>
