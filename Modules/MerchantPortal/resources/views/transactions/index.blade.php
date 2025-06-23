@extends('merchantportal::layouts.master')

@section('title', 'Transactions')
@section('page-title', 'Transactions')
@section('page-subtitle', 'Manage and monitor all your payment transactions')

@section('breadcrumb')
    <li>
        <div class="flex items-center">
            <i class="fas fa-chevron-right h-3 w-3 text-gray-400 mx-2"></i>
            <span class="text-sm font-medium text-gray-900">Transactions</span>
        </div>
    </li>
@endsection

@section('page-actions')
    <div class="flex items-center space-x-3">
        <!-- Export Button -->
        <button type="button" onclick="exportTransactions()" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-150">
            <i class="fas fa-download -ml-1 mr-2 h-4 w-4"></i>
            Export
        </button>

        <!-- Refresh Button -->
        <button type="button" onclick="location.reload()" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-150">
            <i class="fas fa-sync-alt -ml-1 mr-2 h-4 w-4"></i>
            Refresh
        </button>
    </div>
@endsection

@section('content')
    <!-- Filters Section -->
    <div class="bg-white shadow-sm rounded-xl ring-1 ring-gray-900/5 mb-6" x-data="{ filtersOpen: {{ request()->hasAny(['date_from', 'date_to', 'status', 'shop_id', 'amount_min', 'amount_max', 'payment_id']) ? 'true' : 'false' }} }">
        <div class="p-6 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                        <i class="fas fa-filter mr-3 text-blue-500"></i>
                        Transaction Filters
                    </h3>
                    <p class="mt-1 text-sm text-gray-500">Filter and search your transactions</p>
                </div>
                <button @click="filtersOpen = !filtersOpen" type="button" class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50 transition-colors duration-150">
                    <span x-text="filtersOpen ? 'Hide Filters' : 'Show Filters'"></span>
                    <i class="fas fa-chevron-down ml-2 h-4 w-4 transform transition-transform duration-200" :class="{ 'rotate-180': filtersOpen }"></i>
                </button>
            </div>
        </div>

        <div x-show="filtersOpen" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 transform -translate-y-2" x-transition:enter-end="opacity-100 transform translate-y-0" x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 transform translate-y-0" x-transition:leave-end="opacity-0 transform -translate-y-2" class="p-6">
            <form method="GET" action="{{ route('merchant.transactions.index') }}" class="space-y-6">
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    <!-- Date Range -->
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Date Range</label>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="relative">
                                <input type="date" name="date_from" value="{{ request('date_from') }}" class="block w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                                <label class="absolute -top-2 left-2 inline-block bg-white px-1 text-xs font-medium text-gray-500">From</label>
                            </div>
                            <div class="relative">
                                <input type="date" name="date_to" value="{{ request('date_to') }}" class="block w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                                <label class="absolute -top-2 left-2 inline-block bg-white px-1 text-xs font-medium text-gray-500">To</label>
                            </div>
                        </div>
                    </div>

                    <!-- Status Filter -->
                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                        <select name="status" id="status" class="block w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                            <option value="">All Statuses</option>
                            <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
                            <option value="processing" {{ request('status') === 'processing' ? 'selected' : '' }}>Processing</option>
                            <option value="matched" {{ request('status') === 'matched' ? 'selected' : '' }}>Completed</option>
                            <option value="failed" {{ request('status') === 'failed' ? 'selected' : '' }}>Failed</option>
                        </select>
                    </div>

                    <!-- Shop Filter -->
                    <div>
                        <label for="shop_id" class="block text-sm font-medium text-gray-700 mb-2">Shop</label>
                        <select name="shop_id" id="shop_id" class="block w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                            <option value="">All Shops</option>
                            @foreach($shops ?? [] as $shop)
                                <option value="{{ $shop->id }}" {{ request('shop_id') == $shop->id ? 'selected' : '' }}>
                                    {{ $shop->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    <!-- Amount Range -->
                    <div class="grid grid-cols-2 gap-3">
                        <div class="relative">
                            <input type="number" name="amount_min" value="{{ request('amount_min') }}" placeholder="0.00" step="0.01" class="block w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                            <label class="absolute -top-2 left-2 inline-block bg-white px-1 text-xs font-medium text-gray-500">Min Amount (€)</label>
                        </div>
                        <div class="relative">
                            <input type="number" name="amount_max" value="{{ request('amount_max') }}" placeholder="1000.00" step="0.01" class="block w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                            <label class="absolute -top-2 left-2 inline-block bg-white px-1 text-xs font-medium text-gray-500">Max Amount (€)</label>
                        </div>
                    </div>

                    <!-- Payment ID Search -->
                    <div class="relative">
                        <input type="text" name="payment_id" value="{{ request('payment_id') }}" placeholder="Search by Payment ID" class="block w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                        <label class="absolute -top-2 left-2 inline-block bg-white px-1 text-xs font-medium text-gray-500">Payment ID</label>
                    </div>

                    <!-- Merchant Name Search -->
                    <div class="relative">
                        <input type="text" name="merchant_name" value="{{ request('merchant_name') }}" placeholder="Search by Merchant Name" class="block w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                        <label class="absolute -top-2 left-2 inline-block bg-white px-1 text-xs font-medium text-gray-500">Merchant Name</label>
                    </div>
                </div>

                <!-- Filter Actions -->
                <div class="flex items-center justify-between pt-4 border-t border-gray-200">
                    <div class="flex items-center space-x-3">
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-150">
                            <i class="fas fa-search -ml-1 mr-2 h-4 w-4"></i>
                            Apply Filters
                        </button>

                        @if(request()->hasAny(['date_from', 'date_to', 'status', 'shop_id', 'amount_min', 'amount_max', 'payment_id', 'merchant_name']))
                            <a href="{{ route('merchant.transactions.index') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-150">
                                <i class="fas fa-times -ml-1 mr-2 h-4 w-4"></i>
                                Clear Filters
                            </a>
                        @endif
                    </div>

                    <div class="text-sm text-gray-500">
                        Showing {{ $transactions->count() }} of {{ $transactions->total() }} transactions
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Transaction Statistics -->
    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4 mb-6">
        <!-- Total Transactions -->
        <div class="bg-white shadow-sm rounded-lg ring-1 ring-gray-900/5 p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-100">
                        <i class="fas fa-credit-card text-blue-600"></i>
                    </div>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500">Total Transactions</p>
                    <p class="text-2xl font-semibold text-gray-900">{{ $transactions->total() }}</p>
                </div>
            </div>
        </div>

        <!-- Total Volume -->
        <div class="bg-white shadow-sm rounded-lg ring-1 ring-gray-900/5 p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-green-100">
                        <i class="fas fa-euro-sign text-green-600"></i>
                    </div>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500">Total Volume</p>
                    <p class="text-2xl font-semibold text-gray-900">€{{ number_format($totalVolume ?? 0, 0) }}</p>
                </div>
            </div>
        </div>

        <!-- Success Rate -->
        <div class="bg-white shadow-sm rounded-lg ring-1 ring-gray-900/5 p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-green-100">
                        <i class="fas fa-check-circle text-green-600"></i>
                    </div>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500">Success Rate</p>
                    <p class="text-2xl font-semibold text-gray-900">{{ number_format($successRate ?? 0, 1) }}%</p>
                </div>
            </div>
        </div>

        <!-- Average Amount -->
        <div class="bg-white shadow-sm rounded-lg ring-1 ring-gray-900/5 p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-purple-100">
                        <i class="fas fa-chart-line text-purple-600"></i>
                    </div>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500">Average Amount</p>
                    <p class="text-2xl font-semibold text-gray-900">€{{ number_format($averageAmount ?? 0, 0) }}</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Transactions Table -->
    <div class="bg-white shadow-sm rounded-xl ring-1 ring-gray-900/5 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-900">Transactions</h3>
                <div class="flex items-center space-x-3">
                    <select onchange="changePerPage(this.value)" class="text-sm border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                        <option value="25" {{ request('per_page') == 25 ? 'selected' : '' }}>25 per page</option>
                        <option value="50" {{ request('per_page') == 50 ? 'selected' : '' }}>50 per page</option>
                        <option value="100" {{ request('per_page') == 100 ? 'selected' : '' }}>100 per page</option>
                    </select>
                </div>
            </div>
        </div>

        @if($transactions->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <a href="{{ request()->fullUrlWithQuery(['sort' => 'payment_id', 'direction' => request('direction') === 'asc' ? 'desc' : 'asc']) }}" class="group inline-flex items-center hover:text-gray-900">
                                Payment ID
                                <span class="ml-2 flex-none rounded text-gray-400 group-hover:text-gray-500">
                                        <i class="fas fa-sort h-3 w-3"></i>
                                    </span>
                            </a>
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Shop
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <a href="{{ request()->fullUrlWithQuery(['sort' => 'tr_amount', 'direction' => request('direction') === 'asc' ? 'desc' : 'asc']) }}" class="group inline-flex items-center hover:text-gray-900">
                                Amount
                                <span class="ml-2 flex-none rounded text-gray-400 group-hover:text-gray-500">
                                        <i class="fas fa-sort h-3 w-3"></i>
                                    </span>
                            </a>
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Status
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <a href="{{ request()->fullUrlWithQuery(['sort' => 'tr_date_time', 'direction' => request('direction') === 'asc' ? 'desc' : 'asc']) }}" class="group inline-flex items-center hover:text-gray-900">
                                Date & Time
                                <span class="ml-2 flex-none rounded text-gray-400 group-hover:text-gray-500">
                                        <i class="fas fa-sort h-3 w-3"></i>
                                    </span>
                            </a>
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Actions
                        </th>
                    </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($transactions as $transaction)
                        <tr class="hover:bg-gray-50 transition-colors duration-150">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">
                                    {{ $transaction->payment_id }}
                                </div>
                                @if($transaction->merchant_transaction_id)
                                    <div class="text-xs text-gray-500">
                                        Merchant: {{ $transaction->merchant_transaction_id }}
                                    </div>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="h-8 w-8 rounded-full bg-gradient-to-r from-blue-500 to-purple-600 flex items-center justify-center mr-3">
                                        <span class="text-white text-xs font-bold">{{ substr($transaction->shop->name ?? 'U', 0, 1) }}</span>
                                    </div>
                                    <div>
                                        <div class="text-sm font-medium text-gray-900">{{ $transaction->shop->name ?? 'Unknown Shop' }}</div>
                                        <div class="text-xs text-gray-500">ID: {{ $transaction->shop_id }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-semibold text-gray-900">
                                    €{{ number_format($transaction->tr_amount / 100, 2) }}
                                </div>
                                <div class="text-xs text-gray-500">
                                    {{ $transaction->tr_currency }}
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                        @switch($transaction->status)
                                            @case('matched')
                                                bg-green-100 text-green-800
                                                @break
                                            @case('failed')
                                                bg-red-100 text-red-800
                                                @break
                                            @case('pending')
                                                bg-yellow-100 text-yellow-800
                                                @break
                                            @case('processing')
                                                bg-blue-100 text-blue-800
                                                @break
                                            @default
                                                bg-gray-100 text-gray-800
                                        @endswitch
                                    ">
                                        <i class="fas fa-{{ $transaction->status === 'matched' ? 'check' : ($transaction->status === 'failed' ? 'times' : 'clock') }} mr-1 h-3 w-3"></i>
                                        {{ ucfirst($transaction->status) }}
                                    </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">
                                    {{ $transaction->tr_date_time->format('M j, Y') }}
                                </div>
                                <div class="text-xs text-gray-500">
                                    {{ $transaction->tr_date_time->format('H:i:s') }}
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <div class="flex items-center space-x-2">
                                    <a href="{{ route('merchant.transactions.show', $transaction->id) }}" class="text-blue-600 hover:text-blue-900 transition-colors duration-150">
                                        <i class="fas fa-eye h-4 w-4"></i>
                                        <span class="sr-only">View</span>
                                    </a>
                                    <button type="button" onclick="copyToClipboard('{{ $transaction->payment_id }}')" class="text-gray-400 hover:text-gray-600 transition-colors duration-150">
                                        <i class="fas fa-copy h-4 w-4"></i>
                                        <span class="sr-only">Copy ID</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="bg-white px-6 py-4 border-t border-gray-200">
                <div class="flex items-center justify-between">
                    <div class="flex-1 flex justify-between sm:hidden">
                        @if($transactions->hasPages())
                            {{ $transactions->appends(request()->query())->links() }}
                        @endif
                    </div>
                    <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                        <div>
                            <p class="text-sm text-gray-700">
                                Showing <span class="font-medium">{{ $transactions->firstItem() }}</span> to
                                <span class="font-medium">{{ $transactions->lastItem() }}</span> of
                                <span class="font-medium">{{ $transactions->total() }}</span> results
                            </p>
                        </div>
                        @if($transactions->hasPages())
                            <div>
                                {{ $transactions->appends(request()->query())->links() }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @else
            <!-- Empty State -->
            <div class="text-center py-12">
                <i class="fas fa-credit-card text-4xl text-gray-300 mb-4"></i>
                <h3 class="text-lg font-medium text-gray-900 mb-2">No transactions found</h3>
                <p class="text-gray-500 mb-6">
                    @if(request()->hasAny(['date_from', 'date_to', 'status', 'shop_id', 'amount_min', 'amount_max', 'payment_id', 'merchant_name']))
                        No transactions match your current filters. Try adjusting your search criteria.
                    @else
                        You haven't received any transactions yet. Once you start processing payments, they'll appear here.
                    @endif
                </p>
                @if(request()->hasAny(['date_from', 'date_to', 'status', 'shop_id', 'amount_min', 'amount_max', 'payment_id', 'merchant_name']))
                    <a href="{{ route('merchant.transactions.index') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-150">
                        <i class="fas fa-times -ml-1 mr-2 h-4 w-4"></i>
                        Clear Filters
                    </a>
                @endif
            </div>
        @endif
    </div>
@endsection

@push('scripts')
    <script>
        function changePerPage(value) {
            const url = new URL(window.location.href);
            url.searchParams.set('per_page', value);
            url.searchParams.delete('page');
            window.location.href = url.toString();
        }

        function exportTransactions() {
            const url = new URL('{{ route("merchant.transactions.index") }}');
            const params = new URLSearchParams(window.location.search);
            params.set('export', 'csv');

            // Create a temporary form to submit the export request
            const form = document.createElement('form');
            form.method = 'GET';
            form.action = url.pathname;

            for (const [key, value] of params) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = key;
                input.value = value;
                form.appendChild(input);
            }

            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
        }

        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                // You could show a toast notification here
                console.log('Copied to clipboard: ' + text);
            }, function(err) {
                console.error('Could not copy text: ', err);
            });
        }

        // Auto-submit form when date inputs change
        document.addEventListener('DOMContentLoaded', function() {
            const dateInputs = document.querySelectorAll('input[type="date"]');
            dateInputs.forEach(input => {
                input.addEventListener('change', function() {
                    // Optional: Auto-submit on date change
                    // this.form.submit();
                });
            });
        });
    </script>
@endpush
