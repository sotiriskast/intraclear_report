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
                                        <path
                                            d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zM3 10a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H4a1 1 0 01-1-1v-6zM14 9a1 1 0 00-1 1v6a1 1 0 001 1h2a1 1 0 001-1v-6a1 1 0 00-1-1h-2z"></path>
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
                                        <path fill-rule="evenodd"
                                              d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                              clip-rule="evenodd"></path>
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
                                        <path
                                            d="M8.433 7.418c.155-.103.346-.196.567-.267v1.698a2.305 2.305 0 01-.567-.267C8.07 8.34 8 8.114 8 8c0-.114.07-.34.433-.582zM11 12.849v-1.698c.22.071.412.164.567.267.364.243.433.468.433.582 0 .114-.07.34-.433.582a2.305 2.305 0 01-.567.267z"></path>
                                        <path fill-rule="evenodd"
                                              d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.076 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843.504a1 1 0 101.511-1.31c-.563-.649-1.413-1.076-2.354-1.253V5z"
                                              clip-rule="evenodd"></path>
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Total Amount</dt>
                                    <dd id="total-amount" class="text-2xl font-semibold text-gray-900">
                                        @if(isset($summary['display_currency']) && $summary['display_currency'] === 'Multi')
                                            Multi-Currency
                                        @else
                                            €{{ number_format($summary['total_amount'] ?? 0, 2) }}
                                        @endif
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
                                        <path
                                            d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3z"></path>
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
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                        Generate Reports
                    </h3>
                </div>

                <div class="p-6">
                    <!-- Validation Error Container -->
                    <div id="validationErrors" class="hidden mb-4 bg-red-50 border border-red-200 rounded-md p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd"
                                          d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                                          clip-rule="evenodd"></path>
                                </svg>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-red-800">Please fix the following errors:</h3>
                                <div class="mt-2 text-sm text-red-700">
                                    <ul class="list-disc pl-5 space-y-1" id="validationErrorList">
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    <form id="reportForm" class="space-y-6">
                        @csrf

                        <!-- Date Range Selection -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="date_from" class="block text-sm font-medium text-gray-700 mb-2">
                                    From Date <span class="text-red-500">*</span>
                                </label>
                                <input type="text"
                                       id="date_from"
                                       name="date_from"
                                       class="flatpickr mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                       placeholder="Select start date">
                                <div id="date_from_error" class="hidden mt-1 text-sm text-red-600"></div>
                            </div>
                            <div>
                                <label for="date_to" class="block text-sm font-medium text-gray-700 mb-2">
                                    To Date <span class="text-red-500">*</span>
                                </label>
                                <input type="text"
                                       id="date_to"
                                       name="date_to"
                                       class="flatpickr mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                       placeholder="Select end date">
                                <div id="date_to_error" class="hidden mt-1 text-sm text-red-600"></div>
                            </div>
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
                                    <option value="scheme">Scheme Report</option>
                                    <option value="volume_breakdown">Volume Breakdown by Region</option>
                                    {{--                                    <option value="transactions">Transaction Details</option>--}}
                                    {{--                                    <option value="daily_summary">Daily Summary</option>--}}
                                    {{--                                    <option value="merchant_breakdown">Merchant Breakdown</option>--}}
                                    {{--                                    <option value="matching">Matching Analysis</option>--}}
                                    {{--                                    <option value="settlements">Settlement Report</option>--}}
                                    {{--                                    <option value="declined_transactions">Declined Transactions</option>--}}
                                    {{--                                    <option value="approval_analysis">Approval Analysis</option>--}}
                                </select>
                            </div>

                            <div>
                                <label for="merchant_id" class="block text-sm font-medium text-gray-700 mb-2">
                                    Merchant (Optional)
                                </label>
                                <select id="merchant_id"
                                        name="merchant_id"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                    <option value="">All Merchants</option>
                                    @if(isset($merchants) && count($merchants) > 0)
                                        @foreach($merchants as $merchant)
                                            <option value="{{ $merchant['id'] }}"
                                                    data-account-id="{{ $merchant['account_id'] ?? '' }}">
                                                {{ $merchant['display_name'] }}
                                            </option>
                                        @endforeach
                                    @endif
                                </select>
                                @if(isset($merchants) && count($merchants) > 0)
                                    <div class="mt-1 text-xs text-gray-500">
                                        <span id="merchant-count">{{ count($merchants) }}</span> merchants available
                                    </div>
                                @else
                                    <div class="mt-1 text-xs text-red-500">
                                        No merchants found in database
                                    </div>
                                @endif
                            </div>

                            <div>
                                <label for="currency" class="block text-sm font-medium text-gray-700 mb-2">
                                    Currency (Optional)
                                </label>
                                <select id="currency"
                                        name="currency"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                    <option value="">All Currencies</option>
                                    @if(isset($currency)&&count($currency))
                                        @foreach($currency as $value)
                                            <option value="{{mb_strtoupper($value)}}">{{mb_strtoupper($value)}}</option>
                                        @endforeach
                                    @endif
                                </select>
                            </div>
                        </div>

                        {{--                        <!-- Additional Filters -->--}}
                        {{--                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">--}}
                        {{--                            <div>--}}
                        {{--                                <label for="status" class="block text-sm font-medium text-gray-700 mb-2">--}}
                        {{--                                    Status--}}
                        {{--                                </label>--}}
                        {{--                                <select id="status"--}}
                        {{--                                        name="status"--}}
                        {{--                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">--}}
                        {{--                                    <option value="">All Statuses</option>--}}
                        {{--                                    <option value="matched">Matched</option>--}}
                        {{--                                    <option value="pending">Pending</option>--}}
                        {{--                                    <option value="failed">Failed</option>--}}
                        {{--                                </select>--}}
                        {{--                            </div>--}}

                        {{--                            <div>--}}
                        {{--                                <label for="amount_min" class="block text-sm font-medium text-gray-700 mb-2">--}}
                        {{--                                    Min Amount (€)--}}
                        {{--                                </label>--}}
                        {{--                                <input type="number"--}}
                        {{--                                       id="amount_min"--}}
                        {{--                                       name="amount_min"--}}
                        {{--                                       step="0.01"--}}
                        {{--                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"--}}
                        {{--                                       placeholder="0.00">--}}
                        {{--                            </div>--}}

                        {{--                            <div>--}}
                        {{--                                <label for="amount_max" class="block text-sm font-medium text-gray-700 mb-2">--}}
                        {{--                                    Max Amount (€)--}}
                        {{--                                </label>--}}
                        {{--                                <input type="number"--}}
                        {{--                                       id="amount_max"--}}
                        {{--                                       name="amount_max"--}}
                        {{--                                       step="0.01"--}}
                        {{--                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"--}}
                        {{--                                       placeholder="1000.00">--}}
                        {{--                            </div>--}}
                        {{--                        </div>--}}

                        <!-- Export Options -->
                        <div class="flex items-center space-x-4">
                            <label class="block text-sm font-medium text-gray-700">Export Format:</label>
                            <div class="flex space-x-4">
                                <label class="inline-flex items-center">
                                    <input type="radio" name="export_format" value="json" class="form-radio" checked>
                                    <span class="ml-2 text-sm text-gray-700">View Online</span>
                                </label>
                                {{--                                <label class="inline-flex items-center">--}}
                                {{--                                    <input type="radio" name="export_format" value="csv" class="form-radio">--}}
                                {{--                                    <span class="ml-2 text-sm text-gray-700">CSV Download</span>--}}
                                {{--                                </label>--}}
                                {{--                                <label class="inline-flex items-center">--}}
                                {{--                                    <input type="radio" name="export_format" value="excel" class="form-radio">--}}
                                {{--                                    <span class="ml-2 text-sm text-gray-700">Excel Download</span>--}}
                                {{--                                </label>--}}
                            </div>
                        </div>

                        <!-- Generate Button -->
                        <div class="flex justify-center">
                            <button type="submit"
                                    id="generateBtn"
                                    class="inline-flex items-center px-6 py-3 bg-indigo-600 border border-transparent rounded-md font-semibold text-sm text-white uppercase tracking-wider hover:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:border-indigo-900 focus:ring focus:ring-indigo-300 disabled:opacity-50 transition ease-in-out duration-150">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                </svg>
                                <span class="generate-text">Generate Report</span>
                                <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white hidden loading-spinner"
                                     xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                            stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor"
                                          d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
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
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        Report Results
                    </h3>
                </div>
                <div class="p-6">
                    <div id="reportResults"></div>
                </div>
            </div>

            <!-- Error Container -->
            <div id="errorContainer"
                 class="hidden bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded relative" role="alert">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd"
                                  d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                                  clip-rule="evenodd"></path>
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
        document.addEventListener('DOMContentLoaded', function () {
            // Initialize Flatpickr
            const dateFromPicker = flatpickr("#date_from", {
                altInput: true,
                altFormat: "F j, Y",
                dateFormat: "Y-m-d",
                maxDate: "today",
                onChange: function (selectedDates) {
                    if (selectedDates.length > 0) {
                        dateToPicker.set('minDate', selectedDates[0]);
                        clearFieldError('date_from');
                    }
                }
            });

            const dateToPicker = flatpickr("#date_to", {
                altInput: true,
                altFormat: "F j, Y",
                dateFormat: "Y-m-d",
                maxDate: "today",
                onChange: function (selectedDates) {
                    if (selectedDates.length > 0) {
                        clearFieldError('date_to');
                    }
                }
            });

            // Date preset handlers
            document.querySelectorAll('.date-preset').forEach(button => {
                button.addEventListener('click', function () {
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
                        clearValidationErrors();
                    }

                    // Highlight active preset
                    document.querySelectorAll('.date-preset').forEach(btn => btn.classList.remove('bg-indigo-100', 'text-indigo-800'));
                    this.classList.add('bg-indigo-100', 'text-indigo-800');
                });
            });

            // Form submission with validation
            document.getElementById('reportForm').addEventListener('submit', function (e) {
                e.preventDefault();

                // Clear previous errors
                clearValidationErrors();

                // Validate form
                if (validateForm()) {
                    generateReport();
                }
            });

            function validateForm() {
                const errors = [];
                const dateFrom = document.getElementById('date_from').value;
                const dateTo = document.getElementById('date_to').value;

                // Check if from date is selected
                if (!dateFrom || dateFrom.trim() === '') {
                    errors.push('From Date is required');
                    showFieldError('date_from', 'Please select a start date');
                }

                // Check if to date is selected
                if (!dateTo || dateTo.trim() === '') {
                    errors.push('To Date is required');
                    showFieldError('date_to', 'Please select an end date');
                }

                // Check if from date is not later than to date
                if (dateFrom && dateTo) {
                    const fromDateObj = new Date(dateFrom);
                    const toDateObj = new Date(dateTo);

                    if (fromDateObj > toDateObj) {
                        errors.push('From Date cannot be later than To Date');
                        showFieldError('date_from', 'Start date cannot be after end date');
                        showFieldError('date_to', 'End date cannot be before start date');
                    }
                }

                // Show validation errors if any
                if (errors.length > 0) {
                    showValidationErrors(errors);
                    return false;
                }

                return true;
            }

            function showValidationErrors(errors) {
                const errorContainer = document.getElementById('validationErrors');
                const errorList = document.getElementById('validationErrorList');

                errorList.innerHTML = errors.map(error => `<li>${error}</li>`).join('');
                errorContainer.classList.remove('hidden');

                // Scroll to errors
                errorContainer.scrollIntoView({behavior: 'smooth', block: 'center'});
            }

            function clearValidationErrors() {
                document.getElementById('validationErrors').classList.add('hidden');
                clearFieldError('date_from');
                clearFieldError('date_to');
            }

            function showFieldError(fieldId, message) {
                const field = document.getElementById(fieldId);
                const errorDiv = document.getElementById(fieldId + '_error');

                // Add error styling to field
                field.classList.add('border-red-300', 'focus:border-red-500', 'focus:ring-red-500');
                field.classList.remove('border-gray-300', 'focus:border-indigo-300', 'focus:ring-indigo-200');

                // Show error message
                errorDiv.textContent = message;
                errorDiv.classList.remove('hidden');
            }

            function clearFieldError(fieldId) {
                const field = document.getElementById(fieldId);
                const errorDiv = document.getElementById(fieldId + '_error');

                // Remove error styling from field
                field.classList.remove('border-red-300', 'focus:border-red-500', 'focus:ring-red-500');
                field.classList.add('border-gray-300', 'focus:border-indigo-300', 'focus:ring-indigo-200');

                // Hide error message
                errorDiv.classList.add('hidden');
            }

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
                                return {success: true, download: true};
                            });
                        }
                    })
                    .then(data => {
                        if (data.success) {
                            if (!data.download) {
                                displayResults(data.data, formData.get('report_type'));
                            }
                        } else {
                            console.log(data)
                            showError(formatErrorMessage(data));
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

            function formatErrorMessage(data) {
                if (data.errors) {
                    if (typeof data.errors === 'object') {
                        // Handle Laravel validation errors format
                        const errorMessages = [];
                        for (const [field, messages] of Object.entries(data.errors)) {
                            if (Array.isArray(messages)) {
                                errorMessages.push(...messages);
                            } else {
                                errorMessages.push(messages);
                            }
                        }
                        return errorMessages.length > 0 ? errorMessages.join(' ') : 'Validation failed.';
                    } else {
                        return data.errors;
                    }
                } else if (data.message) {
                    return data.message;
                }
                return 'An error occurred while generating the report.';
            }

            function displayResults(data, reportType) {
                const resultsContainer = document.getElementById('resultsContainer');
                const reportResults = document.getElementById('reportResults');

                let html = '';

                if (data.length === 0 && reportType !== 'volume_breakdown') {
                    html = '<p class="text-gray-500 text-center py-8">No data found for the selected criteria.</p>';
                } else if (reportType === 'volume_breakdown') {
                    html = displayVolumeBreakdown(data);
                } else {
                    html = generateTableHTML(data, reportType);
                }

                reportResults.innerHTML = html;
                resultsContainer.classList.remove('hidden');
                resultsContainer.scrollIntoView({behavior: 'smooth'});
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
                    case 'scheme':
                        headers = ['Card Type', 'Tr Type', 'Tr Ccy', 'Amount', 'Count', 'Fee', 'Merchant Legal Name'];
                        rowFormatter = (row) => [
                            row.card_type || '-',
                            row.transaction_type || '-',
                            row.currency || '-',
                            row.amount ? row.amount.toLocaleString() : '0',
                            row.count ? row.count.toLocaleString() : '0',
                            row.fee ? (row.fee >= 0 ? row.fee.toLocaleString() : `(${Math.abs(row.fee).toLocaleString()})`) : '0',
                            row.merchant_legal_name || '-'
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

                // Add summary for scheme report
                if (reportType === 'scheme') {
                    const summary = calculateSchemeSummary(data);
                    tableHTML = getSchemeSummaryHTML(summary) + tableHTML;
                }

                return tableHTML;
            }

            function calculateSchemeSummary(data) {
                const summary = {
                    totalTransactions: 0,
                    totalAmount: 0,
                    totalFees: 0,
                    uniqueCardTypes: new Set(),
                    uniqueCurrencies: new Set(),
                    uniqueMerchants: new Set(),
                    byCardType: {},
                    byCurrency: {}
                };

                data.forEach(row => {
                    summary.totalTransactions += row.count || 0;
                    summary.totalAmount += row.amount || 0;
                    summary.totalFees += row.fee || 0;

                    if (row.card_type) summary.uniqueCardTypes.add(row.card_type);
                    if (row.currency) summary.uniqueCurrencies.add(row.currency);
                    if (row.merchant_legal_name) summary.uniqueMerchants.add(row.merchant_legal_name);

                    // Group by card type
                    if (!summary.byCardType[row.card_type]) {
                        summary.byCardType[row.card_type] = {count: 0, amount: 0, fee: 0};
                    }
                    summary.byCardType[row.card_type].count += row.count || 0;
                    summary.byCardType[row.card_type].amount += row.amount || 0;
                    summary.byCardType[row.card_type].fee += row.fee || 0;

                    // Group by currency
                    if (!summary.byCurrency[row.currency]) {
                        summary.byCurrency[row.currency] = {count: 0, amount: 0, fee: 0};
                    }
                    summary.byCurrency[row.currency].count += row.count || 0;
                    summary.byCurrency[row.currency].amount += row.amount || 0;
                    summary.byCurrency[row.currency].fee += row.fee || 0;
                });

                return summary;
            }

            function getSchemeSummaryHTML(summary) {
                return `
                    <div class="mb-6 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div class="bg-blue-50 rounded-lg p-4">
                            <div class="text-2xl font-bold text-blue-900">${summary.totalTransactions.toLocaleString()}</div>
                            <div class="text-sm text-blue-600">Total Transactions</div>
                        </div>
                        <div class="bg-green-50 rounded-lg p-4">
                            <div class="text-2xl font-bold text-green-900">${summary.totalAmount.toLocaleString()}</div>
                            <div class="text-sm text-green-600">Total Amount</div>
                        </div>
                        <div class="bg-yellow-50 rounded-lg p-4">
                            <div class="text-2xl font-bold text-yellow-900">${summary.totalFees >= 0 ? summary.totalFees.toLocaleString() : `(${Math.abs(summary.totalFees).toLocaleString()})`}</div>
                            <div class="text-sm text-yellow-600">Total Fees</div>
                        </div>
                        <div class="bg-purple-50 rounded-lg p-4">
                            <div class="text-2xl font-bold text-purple-900">${summary.uniqueMerchants.size}</div>
                            <div class="text-sm text-purple-600">Unique Merchants</div>
                        </div>
                    </div>

                    <div class="mb-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="bg-white rounded-lg border p-4">
                            <h4 class="font-semibold text-gray-900 mb-3">By Card Type</h4>
                            <div class="space-y-2">
                                ${Object.entries(summary.byCardType).map(([cardType, data]) => `
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm font-medium text-gray-700">${cardType}</span>
                                        <div class="text-right">
                                            <div class="text-sm font-semibold">${data.count.toLocaleString()} txns</div>
                                            <div class="text-xs text-gray-500">${data.amount.toLocaleString()} amount</div>
                                        </div>
                                    </div>
                                `).join('')}
                            </div>
                        </div>

                        <div class="bg-white rounded-lg border p-4">
                            <h4 class="font-semibold text-gray-900 mb-3">By Currency</h4>
                            <div class="space-y-2">
                                ${Object.entries(summary.byCurrency).map(([currency, data]) => `
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm font-medium text-gray-700">${currency}</span>
                                        <div class="text-right">
                                            <div class="text-sm font-semibold">${data.count.toLocaleString()} txns</div>
                                            <div class="text-xs text-gray-500">${data.amount.toLocaleString()} amount</div>
                                        </div>
                                    </div>
                                `).join('')}
                            </div>
                        </div>
                    </div>
                `;
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
                document.getElementById('errorContainer').scrollIntoView({behavior: 'smooth'});
            }

            /**
             * Format and display volume breakdown report
             */
            function displayVolumeBreakdown(data) {
                const totals = data.totals;
                const continentBreakdown = data.continent_breakdown;
                const brandSummary = data.brand_summary;
                const typeSummary = data.type_summary;
                const currencyTotals = data.currency_totals;

                // Check if this is a multi-currency report
                const isMultiCurrency = Object.keys(currencyTotals).length > 1;

                return `
        <div class="space-y-8">
            <!-- Executive Summary -->
            <div class="bg-gradient-to-r from-blue-600 to-blue-700 rounded-lg p-6 text-white">
                <h3 class="text-2xl font-bold mb-6 flex items-center">
                    <svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                    </svg>
                    Sales Volume Summary
                </h3>
                ${formatExecutiveSummary(currencyTotals, totals, isMultiCurrency)}
            </div>

            <!-- Regional Breakdown -->
            <div class="bg-white rounded-lg shadow-lg overflow-hidden">
                <div class="bg-gray-50 px-6 py-4 border-b">
                    <h3 class="text-lg font-semibold text-gray-900">Regional Breakdown</h3>
                    <p class="text-sm text-gray-600 mt-1">Sales volume by card issuing region</p>
                </div>
                <div class="p-6">
                    ${formatRegionalBreakdown(currencyTotals, isMultiCurrency)}
                </div>
            </div>

            <!-- Card Brand Analysis -->
            <div class="bg-white rounded-lg shadow-lg overflow-hidden">
                <div class="bg-gray-50 px-6 py-4 border-b">
                    <h3 class="text-lg font-semibold text-gray-900">Card Brand Performance</h3>
                    <p class="text-sm text-gray-600 mt-1">Transaction volume by payment card brand</p>
                </div>
                <div class="p-6">
                    ${formatCardBrandAnalysis(brandSummary, totals, isMultiCurrency)}
                </div>
            </div>

            <!-- Card Type Analysis -->
            <div class="bg-white rounded-lg shadow-lg overflow-hidden">
                <div class="bg-gray-50 px-6 py-4 border-b">
                    <h3 class="text-lg font-semibold text-gray-900">Card Type Analysis</h3>
                    <p class="text-sm text-gray-600 mt-1">Personal vs Commercial card usage</p>
                </div>
                <div class="p-6">
                    ${formatCardTypeAnalysis(typeSummary, totals, isMultiCurrency)}
                </div>
            </div>
            <!-- Detailed Breakdown by Region → Brand → Type -->
            <div class="bg-white rounded-lg shadow-lg overflow-hidden">
                <div class="bg-gray-50 px-6 py-4 border-b">
                    <h3 class="text-lg font-semibold text-gray-900">Detailed Transaction Breakdown</h3>
                    <p class="text-sm text-gray-600 mt-1">Complete hierarchy: Region → Card Brand → Card Type</p>
                </div>
                <div class="p-6">
                    ${formatDetailedBreakdown(continentBreakdown, isMultiCurrency)}
                </div>
            </div>
        </div>
    `;
            }

            function formatCardBrandAnalysis(brandSummary, totals, isMultiCurrency) {
                const totalVolume = totals.total_volume;

                return `
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            ${Object.entries(brandSummary).map(([brand, brandData]) => {
                    const percentage = totalVolume > 0 ? ((brandData.total_amount / totalVolume) * 100).toFixed(1) : '0.0';
                    const europePercentage = brandData.total_amount > 0 ? ((brandData.europe_amount / brandData.total_amount) * 100).toFixed(1) : '0.0';

                    return `
                    <div class="bg-gradient-to-br from-gray-50 to-gray-100 rounded-lg p-6 border border-gray-200">
                        <div class="flex items-center justify-between mb-4">
                            <h4 class="text-lg font-bold text-gray-900">${brand}</h4>
                            <span class="bg-blue-100 text-blue-800 text-xs font-medium px-2.5 py-0.5 rounded">${percentage}%</span>
                        </div>

                        ${isMultiCurrency ?
                        formatMultiCurrencyBrandData(brandData.currencies) :
                        `<div class="text-2xl font-bold text-gray-900 mb-2">${Object.keys(brandData.currencies || {})[0] || 'EUR'} ${formatCurrency(brandData.total_amount)}</div>`
                    }

                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Transactions:</span>
                                <span class="font-medium">${brandData.total_transactions.toLocaleString()}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Europe %:</span>
                                <span class="font-medium text-blue-600">${europePercentage}%</span>
                            </div>
                        </div>
                    </div>
                `;
                }).join('')}
        </div>
    `;
            }

            /**
             * Format card type analysis
             */
            function formatCardTypeAnalysis(typeSummary, totals, isMultiCurrency) {
                const totalVolume = totals.total_volume;

                return `
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            ${Object.entries(typeSummary).map(([type, typeData]) => {
                    const percentage = totalVolume > 0 ? ((typeData.total_amount / totalVolume) * 100).toFixed(1) : '0.0';
                    const europePercentage = typeData.total_amount > 0 ? ((typeData.europe_amount / typeData.total_amount) * 100).toFixed(1) : '0.0';
                    const isCommercial = type.toLowerCase().includes('commercial');

                    return `
                    <div class="bg-gradient-to-br ${isCommercial ? 'from-green-50 to-green-100 border-green-200' : 'from-blue-50 to-blue-100 border-blue-200'} rounded-lg p-6 border">
                        <div class="flex items-center justify-between mb-4">
                            <h4 class="text-xl font-bold ${isCommercial ? 'text-green-900' : 'text-blue-900'}">${type}</h4>
                            <span class="${isCommercial ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800'} text-sm font-medium px-3 py-1 rounded">${percentage}%</span>
                        </div>

                        ${isMultiCurrency ?
                        formatMultiCurrencyTypeData(typeData.currencies, isCommercial) :
                        `<div class="text-3xl font-bold ${isCommercial ? 'text-green-700' : 'text-blue-700'} mb-4">${Object.keys(typeData.currencies || {})[0] || 'EUR'} ${formatCurrency(typeData.total_amount)}</div>`
                    }

                        <div class="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <div class="text-gray-600">Transactions</div>
                                <div class="font-bold text-lg">${typeData.total_transactions.toLocaleString()}</div>
                            </div>
                            <div>
                                <div class="text-gray-600">Europe %</div>
                                <div class="font-bold text-lg ${isCommercial ? 'text-green-600' : 'text-blue-600'}">${europePercentage}%</div>
                            </div>
                        </div>

                        <div class="mt-4 p-3 bg-white bg-opacity-60 rounded text-xs">
                            <strong>MDR Note:</strong> ${isCommercial ? 'Commercial cards typically have higher MDR rates than personal cards.' : 'Personal cards generally have lower MDR rates than commercial cards.'}
                        </div>
                    </div>
                `;
                }).join('')}
        </div>
    `;
            }

            /**
             * Format MDR calculation helper
             */
            function formatMDRCalculationHelper(currencyTotals, isMultiCurrency) {
                return `
        <div class="bg-white rounded-lg p-4 border border-green-300">
            <h4 class="font-semibold text-gray-900 mb-3">Quick Reference for Fee Calculations</h4>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h5 class="font-medium text-gray-800 mb-2">Typical MDR Structure:</h5>
                    <ul class="text-sm space-y-1">
                        <li><span class="font-medium text-blue-600">EU Personal Cards:</span> Lower rates (e.g., 0.2-0.3%)</li>
                        <li><span class="font-medium text-orange-600">Non-EU Personal Cards:</span> Higher rates (e.g., 1.0-2.0%)</li>
                        <li><span class="font-medium text-green-600">EU Commercial Cards:</span> Medium rates (e.g., 0.5-0.8%)</li>
                        <li><span class="font-medium text-red-600">Non-EU Commercial Cards:</span> Highest rates (e.g., 2.0-3.0%)</li>
                    </ul>
                </div>
                <div>
                    <h5 class="font-medium text-gray-800 mb-2">Volume Summary:</h5>
                    ${Object.entries(currencyTotals).map(([currency, amounts]) => `
                        <div class="text-sm mb-2">
                            <div class="font-medium">${currency}:</div>
                            <div class="ml-4 space-y-1">
                                <div>EU: ${formatCurrency(amounts.europe_amount)} <span class="text-gray-500">(${((amounts.europe_amount / amounts.total_amount) * 100).toFixed(1)}%)</span></div>
                                <div>Non-EU: ${formatCurrency(amounts.non_europe_amount)} <span class="text-gray-500">(${((amounts.non_europe_amount / amounts.total_amount) * 100).toFixed(1)}%)</span></div>
                            </div>
                        </div>
                    `).join('')}
                </div>
            </div>
        </div>
    `;
            }

            /**
             * Format detailed breakdown
             */
            function formatDetailedBreakdown(continentBreakdown, isMultiCurrency) {
                return `
        <div class="space-y-8">
            ${Object.entries(continentBreakdown).map(([continent, continentData]) => {
                    const colorClass = continent === 'Europe' ? 'blue' : continent === 'Non-Europe' ? 'orange' : 'gray';

                    return `
                    <div class="border border-${colorClass}-200 rounded-lg overflow-hidden">
                        <div class="bg-${colorClass}-50 px-6 py-4 border-b border-${colorClass}-200">
                            <h4 class="text-lg font-bold text-${colorClass}-900">
                                ${continent}
                                <span class="text-sm font-normal text-${colorClass}-700">
                                    (${continentData.total_transactions.toLocaleString()} transactions)
                                </span>
                            </h4>
                            <div class="text-sm text-${colorClass}-700 mt-1">
                                ${isMultiCurrency ? formatCurrencyTotals(continentData.currencies) : `Total: ${Object.keys(continentData.currencies)[0]} ${formatCurrency(continentData.total_amount)}`}
                            </div>
                        </div>

                        <div class="p-6">
                            ${Object.entries(continentData.card_brands).map(([brand, brandData]) => `
                                <div class="mb-6 last:mb-0">
                                    <h5 class="font-semibold text-gray-900 mb-3">
                                        ${brand}
                                        <span class="text-sm font-normal text-gray-600">
                                            (${isMultiCurrency ? formatCurrencyTotals(brandData.currencies) : `${Object.keys(brandData.currencies)[0]} ${formatCurrency(brandData.total_amount)}`})
                                        </span>
                                    </h5>

                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        ${Object.entries(brandData.card_types).map(([cardType, typeData]) => `
                                            <div class="bg-gray-50 rounded-lg p-4 border">
                                                <div class="font-medium text-gray-900 mb-2">${cardType}</div>
                                                <div class="text-lg font-bold text-gray-700 mb-2">
                                                    ${isMultiCurrency ? formatCurrencyTotals(typeData.currencies) : `${Object.keys(typeData.currencies)[0]} ${formatCurrency(typeData.total_amount)}`}
                                                </div>
                                                <div class="text-sm text-gray-600">
                                                    ${typeData.total_transactions.toLocaleString()} transactions
                                                </div>
                                                ${isMultiCurrency ? `
                                                    <div class="mt-2 text-xs">
                                                        <div class="text-gray-500 mb-1">By currency:</div>
                                                        ${Object.entries(typeData.currencies).map(([currency, amount]) => `
                                                            <span class="inline-block bg-white text-gray-700 text-xs px-2 py-1 rounded mr-1 mb-1 border">
                                                                ${currency}: ${formatCurrency(amount)}
                                                            </span>
                                                        `).join('')}
                                                    </div>
                                                ` : ''}
                                            </div>
                                        `).join('')}
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                `;
                }).join('')}
        </div>
    `;
            }

            function formatRegionalBreakdown(currencyTotals, isMultiCurrency) {
                if (!isMultiCurrency) {
                    const [currency] = Object.keys(currencyTotals);
                    const amounts = currencyTotals[currency];
                    const total = amounts.total_amount;

                    return `
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="bg-blue-50 rounded-lg p-6 border border-blue-200">
                    <h4 class="font-semibold text-blue-900 mb-2">Europe Cards</h4>
                    <div class="text-2xl font-bold text-blue-700">${currency} ${formatCurrency(amounts.europe_amount)}</div>
                    <div class="text-sm text-blue-600 mt-1">${((amounts.europe_amount / total) * 100).toFixed(1)}% of total volume</div>
                    <div class="text-xs text-blue-500 mt-1">Lower MDR rates typically apply</div>
                </div>
                <div class="bg-orange-50 rounded-lg p-6 border border-orange-200">
                    <h4 class="font-semibold text-orange-900 mb-2">Non-Europe Cards</h4>
                    <div class="text-2xl font-bold text-orange-700">${currency} ${formatCurrency(amounts.non_europe_amount)}</div>
                    <div class="text-sm text-orange-600 mt-1">${((amounts.non_europe_amount / total) * 100).toFixed(1)}% of total volume</div>
                    <div class="text-xs text-orange-500 mt-1">Higher MDR rates typically apply</div>
                </div>
                <div class="bg-gray-50 rounded-lg p-6 border border-gray-200">
                    <h4 class="font-semibold text-gray-900 mb-2">Unknown Region</h4>
                    <div class="text-2xl font-bold text-gray-700">${currency} ${formatCurrency(amounts.unknown_amount)}</div>
                    <div class="text-sm text-gray-600 mt-1">${((amounts.unknown_amount / total) * 100).toFixed(1)}% of total volume</div>
                    <div class="text-xs text-gray-500 mt-1">Review required for classification</div>
                </div>
            </div>
        `;
                }

                // Multi-currency regional breakdown
                return `
        <div class="overflow-x-auto">
            <table class="min-w-full">
                <thead>
                    <tr class="border-b-2 border-gray-200">
                        <th class="text-left py-3 px-4 font-semibold text-gray-900">Currency</th>
                        <th class="text-right py-3 px-4 font-semibold text-blue-700">Europe Cards</th>
                        <th class="text-right py-3 px-4 font-semibold text-orange-700">Non-Europe Cards</th>
                        <th class="text-right py-3 px-4 font-semibold text-gray-700">Unknown</th>
                        <th class="text-right py-3 px-4 font-semibold text-gray-900">Total</th>
                    </tr>
                </thead>
                <tbody>
                    ${Object.entries(currencyTotals).map(([currency, amounts]) => `
                        <tr class="border-b border-gray-100 hover:bg-gray-50">
                            <td class="py-4 px-4 font-medium text-gray-900">${currency}</td>
                            <td class="py-4 px-4 text-right">
                                <div class="font-semibold text-blue-700">${formatCurrency(amounts.europe_amount)}</div>
                                <div class="text-xs text-blue-500">${((amounts.europe_amount / amounts.total_amount) * 100).toFixed(1)}%</div>
                            </td>
                            <td class="py-4 px-4 text-right">
                                <div class="font-semibold text-orange-700">${formatCurrency(amounts.non_europe_amount)}</div>
                                <div class="text-xs text-orange-500">${((amounts.non_europe_amount / amounts.total_amount) * 100).toFixed(1)}%</div>
                            </td>
                            <td class="py-4 px-4 text-right">
                                <div class="font-semibold text-gray-700">${formatCurrency(amounts.unknown_amount)}</div>
                                <div class="text-xs text-gray-500">${((amounts.unknown_amount / amounts.total_amount) * 100).toFixed(1)}%</div>
                            </td>
                            <td class="py-4 px-4 text-right">
                                <div class="font-bold text-gray-900">${formatCurrency(amounts.total_amount)}</div>
                                <div class="text-xs text-gray-500">${amounts.total_transactions.toLocaleString()} txns</div>
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>
    `;
            }

            /**
             * Format executive summary with clear totals
             */
            function formatExecutiveSummary(currencyTotals, totals, isMultiCurrency) {
                if (!isMultiCurrency) {
                    const [currency] = Object.keys(currencyTotals);
                    const amounts = currencyTotals[currency];
                    return `
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <div class="text-center">
                    <div class="text-3xl font-bold">${currency} ${formatCurrency(amounts.total_amount)}</div>
                    <div class="text-blue-100 text-sm mt-1">Total Volume</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-semibold">${currency} ${formatCurrency(amounts.europe_amount)}</div>
                    <div class="text-blue-100 text-sm mt-1">Europe Cards</div>
                    <div class="text-xs text-blue-200">${((amounts.europe_amount / amounts.total_amount) * 100).toFixed(1)}%</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-semibold">${currency} ${formatCurrency(amounts.non_europe_amount)}</div>
                    <div class="text-blue-100 text-sm mt-1">Non-Europe Cards</div>
                    <div class="text-xs">${((amounts.non_europe_amount / amounts.total_amount) * 100).toFixed(1)}%</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-semibold">${amounts.total_transactions.toLocaleString()}</div>
                    <div class="text-blue-100 text-sm mt-1">Total Transactions</div>
                </div>
            </div>
        `;
                }

                // Multi-currency summary
                const totalVolume = Object.values(currencyTotals).reduce((sum, curr) => sum + curr.total_amount, 0);
                const totalTransactions = Object.values(currencyTotals).reduce((sum, curr) => sum + curr.total_transactions, 0);

                return `
        <div class="space-y-4">
            <div class="text-center">
                <div class="text-2xl font-bold mb-2">Multi-Currency Portfolio</div>
                <div class="text-lg text-blue-400">${totalTransactions.toLocaleString()} total transactions across ${Object.keys(currencyTotals).length} currencies</div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-${Math.min(Object.keys(currencyTotals).length, 4)} gap-4">
                ${Object.entries(currencyTotals).map(([currency, amounts]) => `
                    <div class="bg-white bg-opacity-20 rounded-lg p-4 text-center">
                        <div class="text-blue-400 text-xl font-bold">${currency}</div>
                        <div class="text-blue-400 text-lg">${formatCurrency(amounts.total_amount)}</div>
                        <div class="text-blue-400 text-xs">${((amounts.total_amount / totalVolume) * 100).toFixed(1)}% of total</div>
                        <div class="text-xs text-blue-400">${amounts.total_transactions.toLocaleString()} txns</div>
                    </div>
                `).join('')}
            </div>
        </div>
    `;
            }

            /**
             * Helper function to get region currency breakdown
             */
            function formatCurrencyTotals(currencies) {
                if (!currencies || Object.keys(currencies).length === 0) return 'No data';

                return Object.entries(currencies)
                    .map(([currency, amount]) => `${currency} ${formatCurrency(amount)}`)
                    .join(', ');
            }


            function formatMultiCurrencyBrandData(currencies) {
                if (!currencies) return '<div class="text-lg text-gray-500">No data</div>';

                // Fix: currencies structure is { 'EUR': { total_amount: 123, europe_amount: 123, ... } }
                const sortedCurrencies = Object.entries(currencies)
                    .map(([currency, data]) => ({
                        currency,
                        total: data.total_amount || 0  // Fix: directly access total_amount, don't reduce
                    }))
                    .filter(({total}) => total > 0)  // Only show currencies with actual amounts
                    .sort((a, b) => b.total - a.total);

                if (sortedCurrencies.length === 0) {
                    return '<div class="text-lg text-gray-500">No amount data</div>';
                }

                return `
        <div class="space-y-1">
            ${sortedCurrencies.map(({currency, total}) => `
                <div class="flex justify-between items-center">
                    <span class="font-medium text-gray-700">${currency}</span>
                    <span class="font-bold text-gray-900">${formatCurrency(total)}</span>
                </div>
            `).join('')}
        </div>
    `;
            }

            /**
             * Helper functions for formatting
             */
            function formatCurrency(amount) {
                if (amount === null || amount === undefined) return '0.00';
                return parseFloat(amount).toLocaleString('en-US', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
            }

            function formatMultiCurrencyTypeData(currencies, isCommercial) {
                if (!currencies) return '<div class="text-lg text-gray-500">No data</div>';

                const colorClass = isCommercial ? 'green' : 'blue';

                // Fix: currencies structure is { 'EUR': { total_amount: 123, europe_amount: 123, ... } }
                const sortedCurrencies = Object.entries(currencies)
                    .map(([currency, data]) => ({
                        currency,
                        total: data.total_amount || 0  // Fix: directly access total_amount, don't reduce
                    }))
                    .filter(({total}) => total > 0)  // Only show currencies with actual amounts
                    .sort((a, b) => b.total - a.total);

                if (sortedCurrencies.length === 0) {
                    return '<div class="text-lg text-gray-500">No amount data</div>';
                }

                return `
        <div class="space-y-2">
            ${sortedCurrencies.map(({currency, total}) => `
                <div class="flex justify-between items-center bg-white bg-opacity-60 rounded px-3 py-2">
                    <span class="font-medium text-${colorClass}-800">${currency}</span>
                    <span class="font-bold text-${colorClass}-900">${formatCurrency(total)}</span>
                </div>
            `).join('')}
        </div>
    `;
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

            document.getElementById('report_type').addEventListener('change', function () {
                const helpTexts = {
                    'volume_breakdown': 'Enhanced Volume Breakdown shows transaction amounts grouped by Continent (Europe/Non-Europe), Card Brand (Visa/Mastercard/etc.), and Card Type (Personal/Commercial). Perfect for calculating different MDR rates for EU vs Non-EU cards and Personal vs Commercial cards.',
                    'scheme': 'Scheme Report groups transactions by card type, transaction type, currency, and merchant to provide an overview of transaction patterns and fee calculations.',
                    // 'transactions': 'Transaction Details provides a detailed view of individual transactions with their matching status.',
                    // 'daily_summary': 'Daily Summary shows aggregated transaction data grouped by date.',
                    // 'merchant_breakdown': 'Merchant Breakdown shows transaction statistics grouped by merchant.',
                    // 'matching': 'Matching Analysis shows statistics about transaction matching success rates.',
                    // 'settlements': 'Settlement Report shows settlement-related transaction data.',
                    // 'declined_transactions': 'Declined Transactions shows all transactions that were declined by the gateway.',
                    // 'approval_analysis': 'Approval Analysis shows approval/decline statistics by merchant.'
                };

                // Remove existing help text
                const existingHelp = document.querySelector('.report-type-help');
                if (existingHelp) {
                    existingHelp.remove();
                }

                // Add new help text
                if (helpTexts[this.value]) {
                    const helpDiv = document.createElement('div');
                    helpDiv.className = 'report-type-help mt-1 text-xs text-blue-600 bg-blue-50 p-2 rounded';
                    helpDiv.textContent = helpTexts[this.value];
                    this.parentElement.appendChild(helpDiv);
                }
            });            // Update dashboard every 30 seconds
            setInterval(updateDashboard, 30000);
        });
    </script>
</x-app-layout>
