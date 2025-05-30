<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Transaction Details') }}
        </h2>
    </x-slot>

    <!-- Flatpickr CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/material_blue.css">

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">

                <!-- Header Section with Search and Export -->
                <div class="px-6 py-4 border-b border-gray-200">
                    <div class="flex flex-col space-y-4">

                        <!-- Search Row -->
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between space-y-4 sm:space-y-0">
                            <!-- Left Side - Search -->
                            <div class="flex items-center space-x-4">
                                <div class="relative">
                                    <input type="text"
                                           id="searchInput"
                                           placeholder="Search by Payment ID, Merchant, Card Type..."
                                           value="{{ $search }}"
                                           class="pl-10 pr-4 py-2 w-80 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                        </svg>
                                    </div>
                                </div>

                            </div>

                            <!-- Right Side - Export Options -->
                            <div class="flex items-center space-x-2">
                                <button id="clearFiltersBtn"
                                        class="px-3 py-2 text-sm text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                                    Clear Filters
                                </button>
                                <button id="exportBtn"
                                        class="bg-orange-500 hover:bg-orange-600 text-white px-4 py-2 rounded-lg text-sm font-medium flex items-center space-x-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                    </svg>
                                    <span>Export</span>
                                </button>
                            </div>
                        </div>

                        <!-- Date Range Row -->
                        <div class="flex flex-wrap items-center space-x-4 space-y-2 sm:space-y-0">
                            <div class="flex items-center space-x-2">
                                <label for="fromDate" class="text-sm text-gray-600 font-medium">From:</label>
                                <input type="text"
                                       id="fromDate"
                                       value="{{ $fromDate }}"
                                       class="flatpickr px-3 py-1 border border-gray-300 rounded text-sm w-32"
                                       placeholder="Start date">
                            </div>
                            <div class="flex items-center space-x-2">
                                <label for="toDate" class="text-sm text-gray-600 font-medium">To:</label>
                                <input type="text"
                                       id="toDate"
                                       value="{{ $toDate }}"
                                       class="flatpickr px-3 py-1 border border-gray-300 rounded text-sm w-32"
                                       placeholder="End date">
                            </div>
                        </div>
                    </div>

                    <!-- Statistics Row -->
                    <div class="mt-4 grid grid-cols-1 sm:grid-cols-4 gap-4" id="statsContainer">
                        <div class="bg-blue-50 p-3 rounded-lg">
                            <div class="text-sm text-blue-600">Total Transactions</div>
                            <div class="text-lg font-semibold text-blue-900" id="totalCount">{{ number_format($stats['total']) }}</div>
                        </div>
                        <div class="bg-green-50 p-3 rounded-lg">
                            <div class="text-sm text-green-600">Matched</div>
                            <div class="text-lg font-semibold text-green-900" id="matchedCount">{{ number_format($stats['matched']) }}</div>
                        </div>
                        <div class="bg-red-50 p-3 rounded-lg">
                            <div class="text-sm text-red-600">Unmatched</div>
                            <div class="text-lg font-semibold text-red-900" id="unmatchedCount">{{ number_format($stats['unmatched']) }}</div>
                        </div>
                        <div class="bg-purple-50 p-3 rounded-lg">
                            <div class="text-sm text-purple-600">Match Rate</div>
                            <div class="text-lg font-semibold text-purple-900" id="matchRate">{{ $stats['match_rate'] }}%</div>
                        </div>
                    </div>

                    <!-- Active Filters Display -->
                    <div id="activeFilters" class="mt-3 hidden">
                        <div class="text-sm text-gray-600 mb-2">Active Filters:</div>
                        <div class="flex flex-wrap gap-2" id="filterTags"></div>
                    </div>
                </div>

                <!-- Table Controls -->
                <div class="px-6 py-3 border-b border-gray-200 flex items-center justify-between">
                    <div class="flex items-center space-x-2">
                        <span class="text-sm text-gray-600">Show</span>
                        <select id="perPageSelect" class="border border-gray-300 rounded px-4 py-1 text-sm">
                            <option value="10" {{ $perPage == 10 ? 'selected' : '' }}>10</option>
                            <option value="25" {{ $perPage == 25 ? 'selected' : '' }}>25</option>
                            <option value="50" {{ $perPage == 50 ? 'selected' : '' }}>50</option>
                            <option value="100" {{ $perPage == 100 ? 'selected' : '' }}>100</option>
                        </select>
                        <span class="text-sm text-gray-600">entries</span>
                    </div>

                    <div id="loadingIndicator" class="hidden flex items-center space-x-2 text-blue-600">
                        <svg class="animate-spin h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                        <span class="text-sm">Loading...</span>
                    </div>
                </div>

                <!-- Data Table -->
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Merchant Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Merchant ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Card Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">TR Amount</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">TR CCY</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">TR Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tr Date Time</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                        </thead>
                        <tbody id="transactionTableBody" class="bg-white divide-y divide-gray-200">
                        @forelse($transactions as $transaction)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    {{ $transaction->payment_id }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ $transaction->merchant_name ?: '-' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ $transaction->merchant_id ?: '-' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    @if($transaction->card_type_name)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                {{ $transaction->card_type_name === 'VISA' ? 'bg-blue-100 text-blue-800' :
                                                   ($transaction->card_type_name === 'MC' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800') }}">
                                                {{ $transaction->card_type_name }}
                                            </span>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ $transaction->tr_amount ? number_format($transaction->tr_amount / 100, 2) : '-' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ $transaction->tr_ccy ?: '-' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ $transaction->tr_type ?: '-' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ $transaction->tr_date_time ? $transaction->tr_date_time->format('Y-m-d H:i:s') : '-' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($transaction->is_matched)
                                        <span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">Matched</span>
                                    @else
                                        @switch($transaction->status)
                                            @case('pending')
                                                <span class="px-2 py-1 text-xs rounded-full bg-yellow-100 text-yellow-800">Pending</span>
                                                @break
                                            @case('failed')
                                                <span class="px-2 py-1 text-xs rounded-full bg-red-100 text-red-800">Failed</span>
                                                @break
                                            @case('processing')
                                                <span class="px-2 py-1 text-xs rounded-full bg-blue-100 text-blue-800">Processing</span>
                                                @break
                                            @default
                                                <span class="px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-800">{{ ucfirst($transaction->status) }}</span>
                                        @endswitch
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <a href="{{ route('decta.transactions.show', $transaction->id) }}"
                                       class="bg-orange-500 hover:bg-orange-600 text-white px-3 py-1 rounded text-xs font-medium">
                                        View
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="px-6 py-12 text-center text-gray-500">
                                    No transactions found.
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Dynamic Pagination -->
                <div id="paginationContainer" class="px-6 py-4 border-t border-gray-200 {{ $transactions->total() > 0 ? '' : 'hidden' }}">
                    <div class="flex items-center justify-between">
                        <div class="text-sm text-gray-700" id="paginationInfo">
                            @if($transactions->total() > 0)
                                Showing {{ $transactions->firstItem() }} to {{ $transactions->lastItem() }} of {{ $transactions->total() }} results
                            @else
                                No results found
                            @endif
                        </div>
                        <div class="flex items-center space-x-2" id="paginationControls">
                            @if($transactions->hasPages())
                                <!-- Previous Page Link -->
                                @if($transactions->onFirstPage())
                                    <span class="px-3 py-2 text-sm text-gray-400 border border-gray-300 rounded-md cursor-not-allowed">Previous</span>
                                @else
                                    <button onclick="searchTransactions({{ $transactions->currentPage() - 1 }})"
                                            class="px-3 py-2 text-sm text-gray-700 border border-gray-300 rounded-md hover:bg-gray-50">Previous</button>
                                @endif

                                <!-- Page Numbers -->
                                @php
                                    $start = max(1, $transactions->currentPage() - 2);
                                    $end = min($transactions->lastPage(), $transactions->currentPage() + 2);
                                @endphp

                                @if($start > 1)
                                    <button onclick="searchTransactions(1)" class="px-3 py-2 text-sm text-gray-700 border border-gray-300 rounded-md hover:bg-gray-50">1</button>
                                    @if($start > 2)
                                        <span class="px-3 py-2 text-sm text-gray-400">...</span>
                                    @endif
                                @endif

                                @for($i = $start; $i <= $end; $i++)
                                    @if($i == $transactions->currentPage())
                                        <span class="px-3 py-2 text-sm text-white bg-indigo-600 border border-indigo-600 rounded-md">{{ $i }}</span>
                                    @else
                                        <button onclick="searchTransactions({{ $i }})" class="px-3 py-2 text-sm text-gray-700 border border-gray-300 rounded-md hover:bg-gray-50">{{ $i }}</button>
                                    @endif
                                @endfor

                                @if($end < $transactions->lastPage())
                                    @if($end < $transactions->lastPage() - 1)
                                        <span class="px-3 py-2 text-sm text-gray-400">...</span>
                                    @endif
                                    <button onclick="searchTransactions({{ $transactions->lastPage() }})" class="px-3 py-2 text-sm text-gray-700 border border-gray-300 rounded-md hover:bg-gray-50">{{ $transactions->lastPage() }}</button>
                                @endif

                                <!-- Next Page Link -->
                                @if($transactions->hasMorePages())
                                    <button onclick="searchTransactions({{ $transactions->currentPage() + 1 }})"
                                            class="px-3 py-2 text-sm text-gray-700 border border-gray-300 rounded-md hover:bg-gray-50">Next</button>
                                @else
                                    <span class="px-3 py-2 text-sm text-gray-400 border border-gray-300 rounded-md cursor-not-allowed">Next</span>
                                @endif
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Export Modal -->
    <div id="exportModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Export Transactions</h3>
                    <button id="closeModal" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <form id="exportForm">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Export Format</label>
                        <div class="space-y-2">
                            <label class="flex items-center">
                                <input type="radio" name="format" value="csv" checked class="form-radio">
                                <span class="ml-2 text-sm">CSV</span>
                            </label>
                            <label class="flex items-center">
                                <input type="radio" name="format" value="excel" class="form-radio">
                                <span class="ml-2 text-sm">Excel</span>
                            </label>
                        </div>
                    </div>

                    <div class="flex justify-end space-x-3">
                        <button type="button" id="cancelExport"
                                class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="submit"
                                class="px-4 py-2 text-sm font-medium text-white bg-orange-500 border border-transparent rounded-md hover:bg-orange-600">
                            Export
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Flatpickr JS -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <script>
        let searchTimeout;
        let currentPage = {{ $transactions->currentPage() }};
        let fromDatePicker, toDatePicker;

        document.addEventListener('DOMContentLoaded', function() {
            // Initialize Flatpickr
            fromDatePicker = flatpickr("#fromDate", {
                altInput: true,
                altFormat: "M j, Y",
                dateFormat: "Y-m-d",
                maxDate: "today",
                onChange: function(selectedDates) {
                    if (selectedDates.length > 0) {
                        toDatePicker.set('minDate', selectedDates[0]);
                    }
                    // Trigger search when date changes
                    triggerSearch();
                }
            });

            toDatePicker = flatpickr("#toDate", {
                altInput: true,
                altFormat: "M j, Y",
                dateFormat: "Y-m-d",
                maxDate: "today",
                onChange: function(selectedDates) {
                    // Trigger search when date changes
                    triggerSearch();
                }
            });


            // Search functionality
            const searchInput = document.getElementById('searchInput');
            const perPageSelect = document.getElementById('perPageSelect');
            const clearFiltersBtn = document.getElementById('clearFiltersBtn');

            // Search input handler with debounce
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    currentPage = 1;
                    searchTransactions();
                }, 500);
            });

            // Per page change handler
            perPageSelect.addEventListener('change', function() {
                currentPage = 1;
                searchTransactions();
            });

            // Clear filters handler
            clearFiltersBtn.addEventListener('click', function() {
                searchInput.value = '';
                fromDatePicker.clear();
                toDatePicker.clear();
                currentPage = 1;
                searchTransactions();
            });

            // Export functionality
            setupExportModal();

            // Update active filters display on load
            updateActiveFilters();
        });

        function triggerSearch() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                currentPage = 1;
                searchTransactions();
            }, 300);
        }

        function searchTransactions(page = 1) {
            const searchInput = document.getElementById('searchInput');
            const fromDate = document.getElementById('fromDate');
            const toDate = document.getElementById('toDate');
            const perPageSelect = document.getElementById('perPageSelect');
            const loadingIndicator = document.getElementById('loadingIndicator');

            // Show loading
            loadingIndicator.classList.remove('hidden');

            const params = new URLSearchParams({
                search: searchInput.value,
                from_date: fromDate.value,
                to_date: toDate.value,
                per_page: perPageSelect.value,
                page: page
            });

            fetch(`{{ route('decta.transactions.search') }}?${params}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateTable(data.data);
                        updateStats(data.stats);
                        updatePagination(data.pagination);
                        updateActiveFilters();
                    } else {
                        console.error('Search failed:', data.message);
                        showErrorMessage('Search failed: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Search error:', error);
                    showErrorMessage('An error occurred while searching. Please try again.');
                })
                .finally(() => {
                    loadingIndicator.classList.add('hidden');
                });
        }

        function updateTable(transactions) {
            const tableBody = document.getElementById('transactionTableBody');

            if (transactions.length === 0) {
                tableBody.innerHTML = `
                    <tr>
                        <td colspan="10" class="px-6 py-12 text-center text-gray-500">
                            No transactions found matching your criteria.
                        </td>
                    </tr>
                `;
                return;
            }

            tableBody.innerHTML = transactions.map(transaction => `
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                        ${transaction.payment_id}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        ${transaction.merchant_name || '-'}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        ${transaction.merchant_id || '-'}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        ${transaction.card_type ? getCardTypeBadge(transaction.card_type) : '-'}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        ${transaction.tr_amount ? transaction.tr_amount.toFixed(2) : '-'}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        ${transaction.tr_ccy || '-'}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        ${transaction.tr_type || '-'}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        ${transaction.tr_date_time || '-'}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        ${transaction.status_badge}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                        <a href="/decta/transactions/${transaction.id}"
                           class="bg-orange-500 hover:bg-orange-600 text-white px-3 py-1 rounded text-xs font-medium">
                            View
                        </a>
                    </td>
                </tr>
            `).join('');
        }

        function updateStats(stats) {
            document.getElementById('totalCount').textContent = stats.total.toLocaleString();
            document.getElementById('matchedCount').textContent = stats.matched.toLocaleString();
            document.getElementById('unmatchedCount').textContent = stats.unmatched.toLocaleString();
            document.getElementById('matchRate').textContent = stats.match_rate + '%';
        }

        function updatePagination(pagination) {
            currentPage = pagination.current_page;

            // Update pagination info
            const paginationInfo = document.getElementById('paginationInfo');
            const paginationContainer = document.getElementById('paginationContainer');

            if (paginationInfo) {
                if (pagination.total > 0) {
                    paginationInfo.textContent = `Showing ${pagination.from || 0} to ${pagination.to || 0} of ${pagination.total} results`;
                } else {
                    paginationInfo.textContent = 'No results found';
                }
            }

            // Show pagination container if we have results
            if (pagination.total > 0) {
                paginationContainer.classList.remove('hidden');
            } else {
                paginationContainer.classList.add('hidden');
            }

            // Update pagination controls
            const paginationControls = document.getElementById('paginationControls');
            if (paginationControls && pagination.last_page > 1) {
                paginationControls.innerHTML = generatePaginationControls(pagination);
            } else if (paginationControls) {
                paginationControls.innerHTML = ''; // Clear pagination if only one page
            }
        }

        function generatePaginationControls(pagination) {
            let html = '';
            const currentPage = pagination.current_page;
            const lastPage = pagination.last_page;

            // Previous button
            if (currentPage > 1) {
                html += `<button onclick="searchTransactions(${currentPage - 1})"
                                class="px-3 py-2 text-sm text-gray-700 border border-gray-300 rounded-md hover:bg-gray-50">Previous</button>`;
            } else {
                html += `<span class="px-3 py-2 text-sm text-gray-400 border border-gray-300 rounded-md cursor-not-allowed">Previous</span>`;
            }

            // Page numbers
            const start = Math.max(1, currentPage - 2);
            const end = Math.min(lastPage, currentPage + 2);

            // First page + ellipsis if needed
            if (start > 1) {
                html += `<button onclick="searchTransactions(1)"
                                class="px-3 py-2 text-sm text-gray-700 border border-gray-300 rounded-md hover:bg-gray-50">1</button>`;
                if (start > 2) {
                    html += `<span class="px-3 py-2 text-sm text-gray-400">...</span>`;
                }
            }

            // Page number buttons
            for (let i = start; i <= end; i++) {
                if (i === currentPage) {
                    html += `<span class="px-3 py-2 text-sm text-white bg-indigo-600 border border-indigo-600 rounded-md">${i}</span>`;
                } else {
                    html += `<button onclick="searchTransactions(${i})"
                                    class="px-3 py-2 text-sm text-gray-700 border border-gray-300 rounded-md hover:bg-gray-50">${i}</button>`;
                }
            }

            // Last page + ellipsis if needed
            if (end < lastPage) {
                if (end < lastPage - 1) {
                    html += `<span class="px-3 py-2 text-sm text-gray-400">...</span>`;
                }
                html += `<button onclick="searchTransactions(${lastPage})"
                                class="px-3 py-2 text-sm text-gray-700 border border-gray-300 rounded-md hover:bg-gray-50">${lastPage}</button>`;
            }

            // Next button
            if (currentPage < lastPage) {
                html += `<button onclick="searchTransactions(${currentPage + 1})"
                                class="px-3 py-2 text-sm text-gray-700 border border-gray-300 rounded-md hover:bg-gray-50">Next</button>`;
            } else {
                html += `<span class="px-3 py-2 text-sm text-gray-400 border border-gray-300 rounded-md cursor-not-allowed">Next</span>`;
            }

            return html;
        }

        function updateActiveFilters() {
            const searchInput = document.getElementById('searchInput');
            const fromDate = document.getElementById('fromDate');
            const toDate = document.getElementById('toDate');
            const activeFilters = document.getElementById('activeFilters');
            const filterTags = document.getElementById('filterTags');

            const filters = [];

            if (searchInput.value.trim()) {
                filters.push({
                    type: 'search',
                    label: `Search: "${searchInput.value.trim()}"`,
                    value: searchInput.value.trim()
                });
            }

            if (fromDate.value) {
                filters.push({
                    type: 'from_date',
                    label: `From: ${fromDate.value}`,
                    value: fromDate.value
                });
            }

            if (toDate.value) {
                filters.push({
                    type: 'to_date',
                    label: `To: ${toDate.value}`,
                    value: toDate.value
                });
            }

            if (filters.length > 0) {
                activeFilters.classList.remove('hidden');
                filterTags.innerHTML = filters.map(filter => `
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                        ${filter.label}
                        <button type="button" class="flex-shrink-0 ml-1.5 h-4 w-4 rounded-full inline-flex items-center justify-center text-blue-400 hover:bg-blue-200 hover:text-blue-500 focus:outline-none focus:bg-blue-500 focus:text-white"
                                onclick="removeFilter('${filter.type}')">
                            <svg class="h-2 w-2" stroke="currentColor" fill="none" viewBox="0 0 8 8">
                                <path stroke-linecap="round" stroke-width="1.5" d="m1 1 6 6m0-6-6 6" />
                            </svg>
                        </button>
                    </span>
                `).join('');
            } else {
                activeFilters.classList.add('hidden');
            }
        }

        function removeFilter(type) {
            switch(type) {
                case 'search':
                    document.getElementById('searchInput').value = '';
                    break;
                case 'from_date':
                    fromDatePicker.clear();
                    break;
                case 'to_date':
                    toDatePicker.clear();
                    break;
            }
            currentPage = 1;
            searchTransactions();
        }

        function getCardTypeBadge(cardType) {
            const badgeClass = cardType === 'VISA' ? 'bg-blue-100 text-blue-800' :
                cardType === 'MC' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800';
            return `<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${badgeClass}">${cardType}</span>`;
        }

        function showErrorMessage(message) {
            // You can customize this to show errors in a more elegant way
            // For now, we'll just use console.error, but you could add a toast notification
            console.error(message);

            // Optional: Add a temporary error display
            const tableBody = document.getElementById('transactionTableBody');
            tableBody.innerHTML = `
                <tr>
                    <td colspan="10" class="px-6 py-12 text-center text-red-500">
                        <div class="flex items-center justify-center space-x-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <span>${message}</span>
                        </div>
                    </td>
                </tr>
            `;
        }

        function setupExportModal() {
            const exportBtn = document.getElementById('exportBtn');
            const exportModal = document.getElementById('exportModal');
            const closeModal = document.getElementById('closeModal');
            const cancelExport = document.getElementById('cancelExport');
            const exportForm = document.getElementById('exportForm');

            exportBtn.addEventListener('click', () => {
                exportModal.classList.remove('hidden');
            });

            [closeModal, cancelExport].forEach(btn => {
                btn.addEventListener('click', () => {
                    exportModal.classList.add('hidden');
                });
            });

            exportForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                const format = formData.get('format');

                // Create download form
                const downloadForm = document.createElement('form');
                downloadForm.method = 'POST';
                downloadForm.action = '{{ route("decta.transactions.export") }}';
                downloadForm.style.display = 'none';

                // Add CSRF token
                const csrfToken = document.createElement('input');
                csrfToken.type = 'hidden';
                csrfToken.name = '_token';
                csrfToken.value = '{{ csrf_token() }}';
                downloadForm.appendChild(csrfToken);

                // Add format
                const formatInput = document.createElement('input');
                formatInput.type = 'hidden';
                formatInput.name = 'format';
                formatInput.value = format;
                downloadForm.appendChild(formatInput);

                // Add current filters
                const searchInput = document.getElementById('searchInput');
                const fromDate = document.getElementById('fromDate');
                const toDate = document.getElementById('toDate');

                if (searchInput.value) {
                    const searchInputHidden = document.createElement('input');
                    searchInputHidden.type = 'hidden';
                    searchInputHidden.name = 'search';
                    searchInputHidden.value = searchInput.value;
                    downloadForm.appendChild(searchInputHidden);
                }

                if (fromDate.value) {
                    const fromDateHidden = document.createElement('input');
                    fromDateHidden.type = 'hidden';
                    fromDateHidden.name = 'from_date';
                    fromDateHidden.value = fromDate.value;
                    downloadForm.appendChild(fromDateHidden);
                }

                if (toDate.value) {
                    const toDateHidden = document.createElement('input');
                    toDateHidden.type = 'hidden';
                    toDateHidden.name = 'to_date';
                    toDateHidden.value = toDate.value;
                    downloadForm.appendChild(toDateHidden);
                }

                document.body.appendChild(downloadForm);
                downloadForm.submit();
                document.body.removeChild(downloadForm);

                exportModal.classList.add('hidden');
            });
        }
    </script>
</x-app-layout>
