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
                        <div
                            class="flex flex-col sm:flex-row sm:items-center sm:justify-between space-y-4 sm:space-y-0">
                            <!-- Left Side - Search -->
                            <div class="flex items-center space-x-4">
                                <div class="relative">
                                    <input type="text"
                                           id="searchInput"
                                           placeholder="Search by Payment ID, Merchant, Card Type..."
                                           value="{{ $search }}"
                                           class="pl-10 pr-4 py-2 w-80 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor"
                                             viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
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
{{--                                <button id="exportBtn"--}}
{{--                                        class="bg-orange-500 hover:bg-orange-600 text-white px-4 py-2 rounded-lg text-sm font-medium flex items-center space-x-2">--}}
{{--                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">--}}
{{--                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"--}}
{{--                                              d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>--}}
{{--                                    </svg>--}}
{{--                                    <span>Export</span>--}}
{{--                                </button>--}}
                                <button id="largeExportBtn"
                                        class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-medium flex items-center space-x-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                                    </svg>
                                    <span>Export</span>
                                </button>
{{--                                <!-- NEW: Scheme Export Button -->--}}
{{--                                <button id="schemeExportBtn"--}}
{{--                                        class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium flex items-center space-x-2">--}}
{{--                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">--}}
{{--                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"--}}
{{--                                              d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a4 4 0 01-4-4V5a4 4 0 014-4h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a4 4 0 01-4 4z"></path>--}}
{{--                                    </svg>--}}
{{--                                    <span>Scheme Export</span>--}}
{{--                                </button>--}}
                            </div>
                        </div>
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
                            <div class="text-lg font-semibold text-blue-900"
                                 id="totalCount">{{ number_format($stats['total']) }}</div>
                        </div>
                        <div class="bg-green-50 p-3 rounded-lg">
                            <div class="text-sm text-green-600">Matched</div>
                            <div class="text-lg font-semibold text-green-900"
                                 id="matchedCount">{{ number_format($stats['matched']) }}</div>
                        </div>
                        <div class="bg-red-50 p-3 rounded-lg">
                            <div class="text-sm text-red-600">Unmatched</div>
                            <div class="text-lg font-semibold text-red-900"
                                 id="unmatchedCount">{{ number_format($stats['unmatched']) }}</div>
                        </div>
                        <div class="bg-purple-50 p-3 rounded-lg">
                            <div class="text-sm text-purple-600">Match Rate</div>
                            <div class="text-lg font-semibold text-purple-900" id="matchRate">{{ $stats['match_rate'] }}
                                %
                            </div>
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
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                        <span class="text-sm">Loading...</span>
                    </div>
                </div>

                <!-- Data Table -->
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Payment ID
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Merchant Name
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Merchant ID
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Card Type
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                TR Amount
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                TR CCY
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                TR Type
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Tr Date Time
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Status
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Actions
                            </th>
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
                                    {{ $transaction->gateway_account_id ?: '-' }}
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
                                        <span
                                            class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">Matched</span>
                                    @else
                                        @switch($transaction->status)
                                            @case('pending')
                                                <span
                                                    class="px-2 py-1 text-xs rounded-full bg-yellow-100 text-yellow-800">Pending</span>
                                                @break
                                            @case('failed')
                                                <span class="px-2 py-1 text-xs rounded-full bg-red-100 text-red-800">Failed</span>
                                                @break
                                            @case('processing')
                                                <span class="px-2 py-1 text-xs rounded-full bg-blue-100 text-blue-800">Processing</span>
                                                @break
                                            @default
                                                <span
                                                    class="px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-800">{{ ucfirst($transaction->status) }}</span>
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
                <div id="paginationContainer"
                     class="px-6 py-4 border-t border-gray-200 {{ $transactions->total() > 0 ? '' : 'hidden' }}">
                    <div class="flex items-center justify-between">
                        <div class="text-sm text-gray-700" id="paginationInfo">
                            @if($transactions->total() > 0)
                                Showing {{ $transactions->firstItem() }} to {{ $transactions->lastItem() }}
                                of {{ $transactions->total() }} results
                            @else
                                No results found
                            @endif
                        </div>
                        <div class="flex items-center space-x-2" id="paginationControls">
                            @if($transactions->hasPages())
                                <!-- Previous Page Link -->
                                @if($transactions->onFirstPage())
                                    <span
                                        class="px-3 py-2 text-sm text-gray-400 border border-gray-300 rounded-md cursor-not-allowed">Previous</span>
                                @else
                                    <button onclick="searchTransactions({{ $transactions->currentPage() - 1 }})"
                                            class="px-3 py-2 text-sm text-gray-700 border border-gray-300 rounded-md hover:bg-gray-50">
                                        Previous
                                    </button>
                                @endif

                                <!-- Page Numbers -->
                                @php
                                    $start = max(1, $transactions->currentPage() - 2);
                                    $end = min($transactions->lastPage(), $transactions->currentPage() + 2);
                                @endphp

                                @if($start > 1)
                                    <button onclick="searchTransactions(1)"
                                            class="px-3 py-2 text-sm text-gray-700 border border-gray-300 rounded-md hover:bg-gray-50">
                                        1
                                    </button>
                                    @if($start > 2)
                                        <span class="px-3 py-2 text-sm text-gray-400">...</span>
                                    @endif
                                @endif

                                @for($i = $start; $i <= $end; $i++)
                                    @if($i == $transactions->currentPage())
                                        <span
                                            class="px-3 py-2 text-sm text-white bg-indigo-600 border border-indigo-600 rounded-md">{{ $i }}</span>
                                    @else
                                        <button onclick="searchTransactions({{ $i }})"
                                                class="px-3 py-2 text-sm text-gray-700 border border-gray-300 rounded-md hover:bg-gray-50">{{ $i }}</button>
                                    @endif
                                @endfor

                                @if($end < $transactions->lastPage())
                                    @if($end < $transactions->lastPage() - 1)
                                        <span class="px-3 py-2 text-sm text-gray-400">...</span>
                                    @endif
                                    <button onclick="searchTransactions({{ $transactions->lastPage() }})"
                                            class="px-3 py-2 text-sm text-gray-700 border border-gray-300 rounded-md hover:bg-gray-50">{{ $transactions->lastPage() }}</button>
                                @endif

                                <!-- Next Page Link -->
                                @if($transactions->hasMorePages())
                                    <button onclick="searchTransactions({{ $transactions->currentPage() + 1 }})"
                                            class="px-3 py-2 text-sm text-gray-700 border border-gray-300 rounded-md hover:bg-gray-50">
                                        Next
                                    </button>
                                @else
                                    <span
                                        class="px-3 py-2 text-sm text-gray-400 border border-gray-300 rounded-md cursor-not-allowed">Next</span>
                                @endif
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div id="largeExportModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Complete Transaction Export</h3>
                    <button id="closeLargeExportModal" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <form id="largeExportForm">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Date Range *</label>
                        <div class="space-y-2">
                            <input type="text" id="largeExportFromDate" name="date_from"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                   placeholder="From date" required>
                            <input type="text" id="largeExportToDate" name="date_to"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                   placeholder="To date" required>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Export Format</label>
                        <div class="space-y-2">
                            <label class="flex items-center">
                                <input type="radio" name="format" value="csv" checked class="form-radio">
                                <span class="ml-2 text-sm">CSV (Recommended for large datasets)</span>
                            </label>
                            <label class="flex items-center">
                                <input type="radio" name="format" value="excel" class="form-radio">
                                <span class="ml-2 text-sm">Excel (Limited to ~1M rows)</span>
                            </label>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Filters (Optional)</label>
                        <div class="space-y-2">
                            <input type="text" name="merchant_id" placeholder="Merchant ID"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm">
                            <input type="text" name="currency" placeholder="Currency (e.g., EUR, USD)" maxlength="3"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm">
                            <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm">
                                <option value="">All Statuses</option>
                                <option value="matched">Matched Only</option>
                                <option value="unmatched">Unmatched Only</option>
                                <option value="pending">Pending Only</option>
                                <option value="failed">Failed Only</option>
                            </select>
                        </div>
                    </div>

                    <!-- Export Estimate Section -->
                    <div id="exportEstimate" class="mb-4 p-3 bg-blue-50 border border-blue-200 rounded-md hidden">
                        <h4 class="text-sm font-medium text-blue-900 mb-2">Export Estimate</h4>
                        <div class="text-sm text-blue-800 space-y-1">
                            <div id="estimateRecords">Records: Calculating...</div>
                            <div id="estimateSize">File Size: Calculating...</div>
                            <div id="estimateTime">Processing Time: Calculating...</div>
                        </div>
                        <div id="estimateRecommendations" class="mt-2 text-xs text-blue-700"></div>
                    </div>

                    <!-- Progress Section -->
                    <div id="exportProgress" class="mb-4 hidden">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm font-medium text-gray-700">Exporting...</span>
                            <span class="text-sm text-gray-500" id="progressText">Starting...</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-blue-600 h-2 rounded-full transition-all duration-300" style="width: 0%" id="progressBar"></div>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">This may take several minutes for large datasets. Please do not close this window.</p>
                    </div>

                    <div class="flex justify-end space-x-3">
                        <button type="button" id="getEstimateBtn"
                                class="px-4 py-2 text-sm font-medium text-blue-700 bg-blue-100 border border-blue-300 rounded-md hover:bg-blue-200">
                            Get Estimate
                        </button>
                        <button type="button" id="cancelLargeExport"
                                class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="submit" id="startLargeExportBtn"
                                class="px-4 py-2 text-sm font-medium text-white bg-green-600 border border-transparent rounded-md hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed">
                            Export Complete Dataset
                        </button>
                    </div>
                </form>
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
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M6 18L18 6M6 6l12 12"></path>
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

    <!-- NEW: Scheme Export Modal -->
    <div id="schemeExportModal"
         class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Export Scheme Report</h3>
                    <button id="closeSchemeModal" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <form id="schemeExportForm">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Date Range *</label>
                        <div class="space-y-2">
                            <input type="text" id="schemeFromDate" name="date_from"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                   placeholder="From date" required>
                            <input type="text" id="schemeToDate" name="date_to"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                   placeholder="To date" required>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">Maximum 365 days range allowed</p>
                    </div>

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

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Filters (Optional)</label>
                        <div class="space-y-2">
                            <input type="text" name="merchant_id" placeholder="Merchant ID"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm">
                            <input type="text" name="currency" placeholder="Currency (e.g., EUR, USD)" maxlength="3"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm">
                        </div>
                    </div>

                    <div class="flex justify-end space-x-3">
                        <button type="button" id="cancelSchemeExport"
                                class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="submit"
                                class="px-4 py-2 text-sm font-medium text-white bg-blue-500 border border-transparent rounded-md hover:bg-blue-600">
                            Export Scheme Report
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <script>
        let searchTimeout;
        let currentPage = {{ $transactions->currentPage() }};
        let fromDatePicker, toDatePicker;
        let schemeFromDatePicker, schemeToDatePicker;
        let largeExportFromDatePicker, largeExportToDatePicker;

        document.addEventListener('DOMContentLoaded', function () {
            // Initialize date pickers for large export - FIXED: Assign to global variables
            largeExportFromDatePicker = flatpickr("#largeExportFromDate", {
                altInput: true,
                altFormat: "M j, Y",
                dateFormat: "Y-m-d",
                maxDate: "today",
                onChange: function(selectedDates) {
                    if (selectedDates.length > 0) {
                        largeExportToDatePicker.set('minDate', selectedDates[0]);
                    }
                    // Clear estimate when dates change
                    hideExportEstimate();
                }
            });

            largeExportToDatePicker = flatpickr("#largeExportToDate", {
                altInput: true,
                altFormat: "M j, Y",
                dateFormat: "Y-m-d",
                maxDate: "today",
                onChange: function(selectedDates) {
                    hideExportEstimate();
                }
            });

            // Setup large export modal
            setupLargeExportModal();

            // Initialize main search date pickers
            fromDatePicker = flatpickr("#fromDate", {
                altInput: true,
                altFormat: "M j, Y",
                dateFormat: "Y-m-d",
                maxDate: "today",
                onChange: function (selectedDates) {
                    if (selectedDates.length > 0) {
                        toDatePicker.set('minDate', selectedDates[0]);
                    }
                    triggerSearch();
                }
            });

            toDatePicker = flatpickr("#toDate", {
                altInput: true,
                altFormat: "M j, Y",
                dateFormat: "Y-m-d",
                maxDate: "today",
                onChange: function (selectedDates) {
                    triggerSearch();
                }
            });

            // Initialize Scheme Export Date Pickers
            schemeFromDatePicker = flatpickr("#schemeFromDate", {
                altInput: true,
                altFormat: "M j, Y",
                dateFormat: "Y-m-d",
                maxDate: "today",
                onChange: function (selectedDates) {
                    if (selectedDates.length > 0) {
                        schemeToDatePicker.set('minDate', selectedDates[0]);
                        const maxDate = new Date(selectedDates[0]);
                        maxDate.setDate(maxDate.getDate() + 365);
                        schemeToDatePicker.set('maxDate', maxDate);
                    }
                }
            });

            schemeToDatePicker = flatpickr("#schemeToDate", {
                altInput: true,
                altFormat: "M j, Y",
                dateFormat: "Y-m-d",
                maxDate: "today"
            });

            // Setup event handlers
            const searchInput = document.getElementById('searchInput');
            const perPageSelect = document.getElementById('perPageSelect');
            const clearFiltersBtn = document.getElementById('clearFiltersBtn');

            searchInput.addEventListener('input', function () {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    currentPage = 1;
                    searchTransactions();
                }, 500);
            });

            perPageSelect.addEventListener('change', function () {
                currentPage = 1;
                searchTransactions();
            });

            clearFiltersBtn.addEventListener('click', function () {
                searchInput.value = '';
                fromDatePicker.clear();
                toDatePicker.clear();
                currentPage = 1;
                searchTransactions();
            });

            setupExportModal();
            setupSchemeExportModal();
            updateActiveFilters();
        });
        function setupLargeExportModal() {
            const largeExportBtn = document.getElementById('largeExportBtn');
            const largeExportModal = document.getElementById('largeExportModal');
            const closeLargeExportModal = document.getElementById('closeLargeExportModal');
            const cancelLargeExport = document.getElementById('cancelLargeExport');
            const largeExportForm = document.getElementById('largeExportForm');
            const getEstimateBtn = document.getElementById('getEstimateBtn');

            if (!largeExportBtn || !largeExportModal) {
                console.warn('Large export elements not found');
                return;
            }

            largeExportBtn.addEventListener('click', () => {
                const currentFromDate = document.getElementById('fromDate').value;
                const currentToDate = document.getElementById('toDate').value;

                if (currentFromDate && largeExportFromDatePicker) {
                    largeExportFromDatePicker.setDate(currentFromDate);
                }
                if (currentToDate && largeExportToDatePicker) {
                    largeExportToDatePicker.setDate(currentToDate);
                }

                largeExportModal.classList.remove('hidden');
            });

            [closeLargeExportModal, cancelLargeExport].forEach(btn => {
                if (btn) {
                    btn.addEventListener('click', () => {
                        largeExportModal.classList.add('hidden');
                        resetExportModal();
                    });
                }
            });

            if (getEstimateBtn) {
                getEstimateBtn.addEventListener('click', function() {
                    getExportEstimate();
                });
            }

            if (largeExportForm) {
                largeExportForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    startLargeExport();
                });
            }
        }

        function getExportEstimate() {
            const formData = new FormData(document.getElementById('largeExportForm'));
            const estimateBtn = document.getElementById('getEstimateBtn');

            if (!formData.get('date_from') || !formData.get('date_to')) {
                alert('Please select both start and end dates.');
                return;
            }

            estimateBtn.disabled = true;
            estimateBtn.textContent = 'Calculating...';

            const params = new URLSearchParams();
            for (let [key, value] of formData.entries()) {
                if (value) params.append(key, value);
            }

            // FIXED: Add proper error handling for JSON parsing
            fetch('{{ route("decta.transactions.export-estimate") }}?' + params)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                    }
                    return response.text(); // Get as text first
                })
                .then(text => {
                    if (!text || text.trim() === '') {
                        throw new Error('Empty response from server');
                    }
                    try {
                        const data = JSON.parse(text);
                        if (data.success) {
                            showExportEstimate(data);
                        } else {
                            alert('Failed to get estimate: ' + (data.message || 'Unknown error'));
                        }
                    } catch (jsonError) {
                        console.error('JSON Parse Error:', jsonError);
                        console.error('Response text:', text);
                        throw new Error('Invalid JSON response from server');
                    }
                })
                .catch(error => {
                    console.error('Estimate error:', error);
                    alert('Failed to get export estimate: ' + error.message);
                })
                .finally(() => {
                    estimateBtn.disabled = false;
                    estimateBtn.textContent = 'Get Estimate';
                });
        }

        function startLargeExport() {
            const formData = new FormData(document.getElementById('largeExportForm'));
            const submitBtn = document.getElementById('startLargeExportBtn');
            const progressSection = document.getElementById('exportProgress');
            const progressBar = document.getElementById('progressBar');
            const progressText = document.getElementById('progressText');

            if (!formData.get('date_from') || !formData.get('date_to')) {
                alert('Please select both start and end dates.');
                return;
            }

            progressSection.classList.remove('hidden');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Exporting...';

            // Create and submit form for download
            const downloadForm = document.createElement('form');
            downloadForm.method = 'POST';
            downloadForm.action = '{{ route("decta.transactions.export-large") }}';
            downloadForm.style.display = 'none';

            // Add CSRF token
            const csrfToken = document.createElement('input');
            csrfToken.type = 'hidden';
            csrfToken.name = '_token';
            csrfToken.value = '{{ csrf_token() }}';
            downloadForm.appendChild(csrfToken);

            // Add form data
            for (let [key, value] of formData.entries()) {
                if (value) {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = key;
                    input.value = value;
                    downloadForm.appendChild(input);
                }
            }

            document.body.appendChild(downloadForm);

            // Simulate progress
            let progress = 0;
            const progressInterval = setInterval(() => {
                progress += Math.random() * 10;
                if (progress > 90) {
                    progress = 90;
                    clearInterval(progressInterval);
                    progressText.textContent = 'Finalizing export...';
                } else {
                    progressText.textContent = `Processing... ${Math.round(progress)}%`;
                }
                progressBar.style.width = progress + '%';
            }, 2000);

            // Submit form for download
            downloadForm.submit();

            // Clean up after a delay
            setTimeout(() => {
                clearInterval(progressInterval);
                progressBar.style.width = '100%';
                progressText.textContent = 'Complete!';

                setTimeout(() => {
                    document.getElementById('largeExportModal').classList.add('hidden');
                    resetExportModal();
                }, 2000);

                document.body.removeChild(downloadForm);
            }, 5000);
        }

        function showExportEstimate(data) {
            const estimateSection = document.getElementById('exportEstimate');
            const recordsEl = document.getElementById('estimateRecords');
            const sizeEl = document.getElementById('estimateSize');
            const timeEl = document.getElementById('estimateTime');
            const recommendationsEl = document.getElementById('estimateRecommendations');

            if (recordsEl) recordsEl.textContent = `Records: ${data.estimated_records.toLocaleString()}`;
            if (sizeEl) sizeEl.textContent = `File Size: ~${data.estimated_size_mb} MB`;
            if (timeEl) timeEl.textContent = `Processing Time: ~${data.estimated_time_minutes} minutes`;

            if (data.recommendations && data.recommendations.length > 0 && recommendationsEl) {
                recommendationsEl.innerHTML = data.recommendations.map(rec =>
                    `<div class="flex items-start space-x-1">
                <span class="text-blue-500">•</span>
                <span>${rec}</span>
            </div>`
                ).join('');
            }

            if (data.is_large_dataset && estimateSection) {
                estimateSection.classList.remove('bg-blue-50', 'border-blue-200');
                estimateSection.classList.add('bg-orange-50', 'border-orange-200');

                const title = estimateSection.querySelector('h4');
                if (title) {
                    title.classList.remove('text-blue-900');
                    title.classList.add('text-orange-900');
                    title.textContent = 'Large Dataset Warning';
                }
            }

            if (estimateSection) {
                estimateSection.classList.remove('hidden');
            }
        }

        function hideExportEstimate() {
            const estimateSection = document.getElementById('exportEstimate');
            if (estimateSection) {
                estimateSection.classList.add('hidden');
            }
        }

        function resetExportModal() {
            const elements = [
                'exportProgress',
                'exportEstimate'
            ];

            elements.forEach(id => {
                const el = document.getElementById(id);
                if (el) el.classList.add('hidden');
            });

            const progressBar = document.getElementById('progressBar');
            if (progressBar) progressBar.style.width = '0%';

            const startBtn = document.getElementById('startLargeExportBtn');
            if (startBtn) {
                startBtn.disabled = false;
                startBtn.textContent = 'Export Complete Dataset';
            }

            const form = document.getElementById('largeExportForm');
            if (form) form.reset();
        }


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

            loadingIndicator.classList.remove('hidden');

            const params = new URLSearchParams({
                search: searchInput.value,
                from_date: fromDate.value,
                to_date: toDate.value,
                per_page: perPageSelect.value,
                page: page
            });

            fetch(`{{ route('decta.transactions.search') }}?${params}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                    }
                    return response.text();
                })
                .then(text => {
                    if (!text || text.trim() === '') {
                        throw new Error('Empty response from server');
                    }
                    const data = JSON.parse(text);
                    if (data.success) {
                        updateTable(data.data);
                        updateStats(data.stats);
                        updatePagination(data.pagination);
                        updateActiveFilters();
                    } else {
                        showErrorMessage('Search failed: ' + (data.message || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Search error:', error);
                    showErrorMessage('Search failed: ' + error.message);
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
            switch (type) {
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

            exportForm.addEventListener('submit', function (e) {
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

        function setupSchemeExportModal() {
            const schemeExportBtn = document.getElementById('schemeExportBtn');
            const schemeExportModal = document.getElementById('schemeExportModal');
            const closeSchemeModal = document.getElementById('closeSchemeModal');
            const cancelSchemeExport = document.getElementById('cancelSchemeExport');
            const schemeExportForm = document.getElementById('schemeExportForm');

            schemeExportBtn.addEventListener('click', () => {
                // Pre-fill dates from current search if available
                const currentFromDate = document.getElementById('fromDate').value;
                const currentToDate = document.getElementById('toDate').value;

                if (currentFromDate) {
                    schemeFromDatePicker.setDate(currentFromDate);
                }
                if (currentToDate) {
                    schemeToDatePicker.setDate(currentToDate);
                }

                schemeExportModal.classList.remove('hidden');
            });

            [closeSchemeModal, cancelSchemeExport].forEach(btn => {
                btn.addEventListener('click', () => {
                    schemeExportModal.classList.add('hidden');
                });
            });

            schemeExportForm.addEventListener('submit', function (e) {
                e.preventDefault();

                const formData = new FormData(this);
                const dateFrom = formData.get('date_from');
                const dateTo = formData.get('date_to');

                // Validate dates
                if (!dateFrom || !dateTo) {
                    alert('Please select both start and end dates.');
                    return;
                }

                // Check date range (max 365 days)
                const startDate = new Date(dateFrom);
                const endDate = new Date(dateTo);
                const diffTime = Math.abs(endDate - startDate);
                const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

                if (diffDays > 365) {
                    alert('Date range cannot exceed 365 days.');
                    return;
                }

                // Show loading state
                const submitBtn = this.querySelector('button[type="submit"]');
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<svg class="animate-spin -ml-1 mr-3 h-4 w-4 text-white inline" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>Exporting...';
                submitBtn.disabled = true;

                // Create download form
                const downloadForm = document.createElement('form');
                downloadForm.method = 'POST';
                downloadForm.action = '{{ route("decta.transactions.export-scheme") }}';
                downloadForm.style.display = 'none';

                // Add CSRF token
                const csrfToken = document.createElement('input');
                csrfToken.type = 'hidden';
                csrfToken.name = '_token';
                csrfToken.value = '{{ csrf_token() }}';
                downloadForm.appendChild(csrfToken);

                // Add all form data
                for (let [key, value] of formData.entries()) {
                    if (value) { // Only add non-empty values
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = key;
                        input.value = value;
                        downloadForm.appendChild(input);
                    }
                }

                document.body.appendChild(downloadForm);

                // Submit and handle response
                fetch(downloadForm.action, {
                    method: 'POST',
                    body: new FormData(downloadForm)
                })
                    .then(response => {
                        if (response.ok) {
                            // If successful, trigger download
                            downloadForm.submit();
                            schemeExportModal.classList.add('hidden');
                        } else {
                            return response.json().then(data => {
                                throw new Error(data.message || 'Export failed');
                            });
                        }
                    })
                    .catch(error => {
                        alert('Export failed: ' + error.message);
                    })
                    .finally(() => {
                        // Restore button state
                        submitBtn.innerHTML = originalText;
                        submitBtn.disabled = false;
                        document.body.removeChild(downloadForm);
                    });
            });
        }
    </script>
</x-app-layout>
