<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Decta Transaction Reports') }}
        </h2>
    </x-slot>

    <!-- Flatpickr CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/material_blue.css">

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <!-- Dashboard Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
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
                                    <dd id="total-transactions" class="text-2xl font-semibold text-gray-900">
                                        {{ number_format($summary['total_transactions'] ?? 0) }}
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

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
                                    <dd id="match-rate" class="text-2xl font-semibold text-gray-900">
                                        {{ $summary['match_rate'] ?? 0 }}%
                                    </dd>
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
                                    <dd id="total-amount" class="text-2xl font-semibold text-gray-900">
                                        €{{ number_format($summary['total_amount'] ?? 0, 2) }}
                                    </dd>
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
                                    <dd id="unique-merchants" class="text-2xl font-semibold text-gray-900">
                                        {{ number_format($summary['unique_merchants'] ?? 0) }}
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Report Generation Form -->
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                <div class="bg-gradient-to-r from-blue-500 to-blue-600 text-white px-6 py-4 border-b border-gray-200">
                    <h3 class="text-xl font-semibold flex items-center">
                        <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                        Generate Reports
                    </h3>
                </div>

                <div class="p-6">
                    <form id="reportForm" class="space-y-6">
                        @csrf

                        <!-- Date Range Selection -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="date_from" class="block text-sm font-medium text-gray-700 mb-2">
                                    From Date
                                </label>
                                <input type="text"
                                       id="date_from"
                                       name="date_from"
                                       class="flatpickr mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                       placeholder="Select start date">
                            </div>
                            <div>
                                <label for="date_to" class="block text-sm font-medium text-gray-700 mb-2">
                                    To Date
                                </label>
                                <input type="text"
                                       id="date_to"
                                       name="date_to"
                                       class="flatpickr mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                       placeholder="Select end date">
                            </div>
                        </div>

                        <!-- Quick Date Presets -->
                        <div class="flex flex-wrap gap-2">
                            <button type="button" class="date-preset px-3 py-1 text-sm bg-gray-100 hover:bg-gray-200 rounded-md transition-colors" data-days="1">Today</button>
                            <button type="button" class="date-preset px-3 py-1 text-sm bg-gray-100 hover:bg-gray-200 rounded-md transition-colors" data-days="7">Last 7 Days</button>
                            <button type="button" class="date-preset px-3 py-1 text-sm bg-gray-100 hover:bg-gray-200 rounded-md transition-colors" data-days="30">Last 30 Days</button>
                            <button type="button" class="date-preset px-3 py-1 text-sm bg-gray-100 hover:bg-gray-200 rounded-md transition-colors" data-days="90">Last 90 Days</button>
                            <button type="button" class="date-preset px-3 py-1 text-sm bg-gray-100 hover:bg-gray-200 rounded-md transition-colors" data-type="this_month">This Month</button>
                            <button type="button" class="date-preset px-3 py-1 text-sm bg-gray-100 hover:bg-gray-200 rounded-md transition-colors" data-type="last_month">Last Month</button>
                        </div>

                        <!-- Report Type and Filters -->
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            <div>
                                <label for="report_type" class="block text-sm font-medium text-gray-700 mb-2">
                                    Report Type
                                </label>
                                <select id="report_type"
                                        name="report_type"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                    <option value="transactions">Transaction Details</option>
                                    <option value="daily_summary">Daily Summary</option>
                                    <option value="merchant_breakdown">Merchant Breakdown</option>
                                    <option value="matching">Matching Analysis</option>
                                    <option value="settlements">Settlement Report</option>
                                </select>
                            </div>

                            <div>
                                <label for="merchant_id" class="block text-sm font-medium text-gray-700 mb-2">
                                    Merchant ID (Optional)
                                </label>
                                <input type="text"
                                       id="merchant_id"
                                       name="merchant_id"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                       placeholder="Filter by merchant">
                            </div>

                            <div>
                                <label for="currency" class="block text-sm font-medium text-gray-700 mb-2">
                                    Currency (Optional)
                                </label>
                                <select id="currency"
                                        name="currency"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                    <option value="">All Currencies</option>
                                    <option value="EUR">EUR</option>
                                    <option value="USD">USD</option>
                                    <option value="GBP">GBP</option>
                                    <option value="CHF">CHF</option>
                                </select>
                            </div>
                        </div>

                        <!-- Additional Filters -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label for="status" class="block text-sm font-medium text-gray-700 mb-2">
                                    Status
                                </label>
                                <select id="status"
                                        name="status"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                    <option value="">All Statuses</option>
                                    <option value="matched">Matched</option>
                                    <option value="pending">Pending</option>
                                    <option value="failed">Failed</option>
                                </select>
                            </div>

                            <div>
                                <label for="amount_min" class="block text-sm font-medium text-gray-700 mb-2">
                                    Min Amount (€)
                                </label>
                                <input type="number"
                                       id="amount_min"
                                       name="amount_min"
                                       step="0.01"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                       placeholder="0.00">
                            </div>

                            <div>
                                <label for="amount_max" class="block text-sm font-medium text-gray-700 mb-2">
                                    Max Amount (€)
                                </label>
                                <input type="number"
                                       id="amount_max"
                                       name="amount_max"
                                       step="0.01"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                       placeholder="1000.00">
                            </div>
                        </div>

                        <!-- Export Options -->
                        <div class="flex items-center space-x-4">
                            <label class="block text-sm font-medium text-gray-700">Export Format:</label>
                            <div class="flex space-x-4">
                                <label class="inline-flex items-center">
                                    <input type="radio" name="export_format" value="json" class="form-radio" checked>
                                    <span class="ml-2 text-sm text-gray-700">View Online</span>
                                </label>
                                <label class="inline-flex items-center">
                                    <input type="radio" name="export_format" value="csv" class="form-radio">
                                    <span class="ml-2 text-sm text-gray-700">CSV Download</span>
                                </label>
                                <label class="inline-flex items-center">
                                    <input type="radio" name="export_format" value="excel" class="form-radio">
                                    <span class="ml-2 text-sm text-gray-700">Excel Download</span>
                                </label>
                            </div>
                        </div>

                        <!-- Generate Button -->
                        <div class="flex justify-center">
                            <button type="submit"
                                    id="generateBtn"
                                    class="inline-flex items-center px-6 py-3 bg-indigo-600 border border-transparent rounded-md font-semibold text-sm text-white uppercase tracking-wider hover:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:border-indigo-900 focus:ring focus:ring-indigo-300 disabled:opacity-50 transition ease-in-out duration-150">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                </svg>
                                <span class="generate-text">Generate Report</span>
                                <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white hidden loading-spinner" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Results Container -->
            <div id="resultsContainer" class="hidden bg-white overflow-hidden shadow-xl sm:rounded-lg">
                <div class="bg-green-500 text-white px-6 py-4 border-b border-gray-200">
                    <h3 class="text-xl font-semibold flex items-center">
                        <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        Report Results
                    </h3>
                </div>
                <div class="p-6">
                    <div id="reportResults"></div>
                </div>
            </div>

            <!-- Error Container -->
            <div id="errorContainer" class="hidden bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded relative" role="alert">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-red-800">Error generating report</h3>
                        <div class="mt-2 text-sm text-red-700">
                            <span id="errorMessage"></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Flatpickr JS -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize Flatpickr
            const dateFromPicker = flatpickr("#date_from", {
                altInput: true,
                altFormat: "F j, Y",
                dateFormat: "Y-m-d",
                maxDate: "today",
                onChange: function(selectedDates) {
                    if (selectedDates.length > 0) {
                        dateToPicker.set('minDate', selectedDates[0]);
                    }
                }
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
                    } else if (type === 'last_month') {
                        fromDate = new Date(today.getFullYear(), today.getMonth() - 1, 1);
                        toDate = new Date(today.getFullYear(), today.getMonth(), 0);
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

            // Form submission
            document.getElementById('reportForm').addEventListener('submit', function(e) {
                e.preventDefault();
                generateReport();
            });

            function generateReport() {
                const form = document.getElementById('reportForm');
                const formData = new FormData(form);
                const generateBtn = document.getElementById('generateBtn');
                const generateText = generateBtn.querySelector('.generate-text');
                const loadingSpinner = generateBtn.querySelector('.loading-spinner');

                // Show loading state
                generateBtn.disabled = true;
                generateText.textContent = 'Generating...';
                loadingSpinner.classList.remove('hidden');

                // Hide previous results/errors
                document.getElementById('resultsContainer').classList.add('hidden');
                document.getElementById('errorContainer').classList.add('hidden');

                // Convert FormData to URLSearchParams for the request
                const params = new URLSearchParams();
                for (let [key, value] of formData.entries()) {
                    if (value) params.append(key, value);
                }

                fetch('{{ route("decta.reports.generate") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: params
                })
                    .then(response => {
                        if (response.headers.get('content-type')?.includes('application/json')) {
                            return response.json();
                        } else {
                            // Handle file downloads (CSV/Excel)
                            const filename = response.headers.get('content-disposition')?.split('filename=')[1]?.replace(/"/g, '') || 'export.csv';
                            return response.blob().then(blob => {
                                const url = window.URL.createObjectURL(blob);
                                const a = document.createElement('a');
                                a.href = url;
                                a.download = filename;
                                document.body.appendChild(a);
                                a.click();
                                window.URL.revokeObjectURL(url);
                                document.body.removeChild(a);
                                return { success: true, download: true };
                            });
                        }
                    })
                    .then(data => {
                        if (data.success) {
                            if (!data.download) {
                                displayResults(data.data, formData.get('report_type'));
                            }
                        } else {
                            showError(data.message || 'An error occurred while generating the report.');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showError('Network error occurred. Please try again.');
                    })
                    .finally(() => {
                        // Reset button state
                        generateBtn.disabled = false;
                        generateText.textContent = 'Generate Report';
                        loadingSpinner.classList.add('hidden');
                    });
            }

            function displayResults(data, reportType) {
                const resultsContainer = document.getElementById('resultsContainer');
                const reportResults = document.getElementById('reportResults');

                let html = '';

                if (data.length === 0) {
                    html = '<p class="text-gray-500 text-center py-8">No data found for the selected criteria.</p>';
                } else {
                    html = generateTableHTML(data, reportType);
                }

                reportResults.innerHTML = html;
                resultsContainer.classList.remove('hidden');
                resultsContainer.scrollIntoView({ behavior: 'smooth' });
            }

            function generateTableHTML(data, reportType) {
                if (data.length === 0) return '';

                let headers = [];
                let rowFormatter = null;

                switch (reportType) {
                    case 'transactions':
                        headers = ['Payment ID', 'Date', 'Amount', 'Currency', 'Merchant', 'Status', 'Matched'];
                        rowFormatter = (row) => [
                            row.payment_id,
                            new Date(row.transaction_date).toLocaleDateString(),
                            `€${row.amount.toFixed(2)}`,
                            row.currency,
                            row.merchant_name || '-',
                            `<span class="px-2 py-1 text-xs rounded-full ${getStatusColor(row.status)}">${row.status}</span>`,
                            row.is_matched ? '<span class="text-green-600">✓</span>' : '<span class="text-red-600">✗</span>'
                        ];
                        break;
                    case 'daily_summary':
                        headers = ['Date', 'Transactions', 'Amount', 'Matched', 'Match Rate'];
                        rowFormatter = (row) => [
                            new Date(row.date).toLocaleDateString(),
                            row.total_transactions.toLocaleString(),
                            `€${row.total_amount.toFixed(2)}`,
                            row.matched_count.toLocaleString(),
                            `${row.match_rate}%`
                        ];
                        break;
                    case 'merchant_breakdown':
                        headers = ['Merchant ID', 'Name', 'Transactions', 'Amount', 'Match Rate'];
                        rowFormatter = (row) => [
                            row.merchant_id,
                            row.merchant_name || '-',
                            row.total_transactions.toLocaleString(),
                            `€${row.total_amount.toFixed(2)}`,
                            `${row.match_rate}%`
                        ];
                        break;
                    default:
                        // Generic table for other report types
                        headers = Object.keys(data[0]);
                        rowFormatter = (row) => Object.values(row);
                }

                let tableHTML = `
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    ${headers.map(header => `<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">${header}</th>`).join('')}
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                `;

                data.slice(0, 100).forEach((row, index) => { // Limit to 100 rows for performance
                    const cells = rowFormatter(row);
                    tableHTML += `<tr class="${index % 2 === 0 ? 'bg-white' : 'bg-gray-50'}">`;
                    cells.forEach(cell => {
                        tableHTML += `<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${cell}</td>`;
                    });
                    tableHTML += '</tr>';
                });

                tableHTML += `
                            </tbody>
                        </table>
                    </div>
                `;

                if (data.length > 100) {
                    tableHTML += `<p class="text-sm text-gray-500 mt-4 text-center">Showing first 100 results of ${data.length} total. Export to see all data.</p>`;
                }

                return tableHTML;
            }

            function getStatusColor(status) {
                switch (status) {
                    case 'matched':
                        return 'bg-green-100 text-green-800';
                    case 'pending':
                        return 'bg-yellow-100 text-yellow-800';
                    case 'failed':
                        return 'bg-red-100 text-red-800';
                    default:
                        return 'bg-gray-100 text-gray-800';
                }
            }

            function showError(message) {
                document.getElementById('errorMessage').textContent = message;
                document.getElementById('errorContainer').classList.remove('hidden');
                document.getElementById('errorContainer').scrollIntoView({ behavior: 'smooth' });
            }

            // Real-time dashboard updates (optional)
            function updateDashboard() {
                fetch('{{ route("decta.reports.dashboard") }}')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            const summary = data.data.summary;
                            document.getElementById('total-transactions').textContent = summary.total_transactions.toLocaleString();
                            document.getElementById('match-rate').textContent = summary.match_rate + '%';
                            document.getElementById('total-amount').textContent = '€' + summary.total_amount.toFixed(2);
                            document.getElementById('unique-merchants').textContent = summary.unique_merchants.toLocaleString();
                        }
                    })
                    .catch(error => console.error('Dashboard update error:', error));
            }

            // Update dashboard every 30 seconds
            setInterval(updateDashboard, 30000);
        });
    </script>
</x-app-layout>
