@extends('merchantportal::layouts.master')

@section('title', 'Transactions')
@section('page-title', 'Transactions')
@section('page-subtitle', 'Manage and view your transaction history')

@section('breadcrumb')
    <li class="flex items-center">
        <a href="{{ route('merchant.dashboard') }}" class="text-gray-400 hover:text-gray-500">Dashboard</a>
        <i class="fas fa-chevron-right mx-2 text-gray-300"></i>
    </li>
    <li class="text-gray-900">Transactions</li>
@endsection

@section('content')
    <!-- Filters and Search -->
    <div class="bg-white shadow rounded-lg mb-6">
        <div class="p-6">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                <!-- Search -->
                <div class="flex-1 max-w-lg">
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-search text-gray-400"></i>
                        </div>
                        <input type="text"
                               id="search"
                               name="search"
                               class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                               placeholder="Search transactions..."
                               value="{{ request('search') }}">
                    </div>
                </div>

                <!-- Filters -->
                <div class="flex flex-wrap gap-3">
                    <!-- Status Filter -->
                    <select class="block px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                        <option value="">All Statuses</option>
                        <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>Completed</option>
                        <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="failed" {{ request('status') === 'failed' ? 'selected' : '' }}>Failed</option>
                        <option value="refunded" {{ request('status') === 'refunded' ? 'selected' : '' }}>Refunded</option>
                    </select>

                    <!-- Date Range -->
                    <input type="date"
                           class="block px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                           value="{{ request('date_from') }}"
                           placeholder="From Date">

                    <input type="date"
                           class="block px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                           value="{{ request('date_to') }}"
                           placeholder="To Date">

                    <!-- Export Button -->
                    <button type="button"
                            class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <i class="fas fa-download mr-2"></i>
                        Export
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
        <!-- Total Volume -->
        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-dollar-sign text-2xl text-blue-600"></i>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Total Volume</dt>
                            <dd class="text-2xl font-bold text-gray-900">${{ number_format($summary['total_volume'] ?? 0, 2) }}</dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <!-- Total Count -->
        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-hashtag text-2xl text-green-600"></i>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Total Transactions</dt>
                            <dd class="text-2xl font-bold text-gray-900">{{ number_format($summary['total_count'] ?? 0) }}</dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <!-- Success Rate -->
        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-chart-line text-2xl text-yellow-600"></i>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Success Rate</dt>
                            <dd class="text-2xl font-bold text-gray-900">{{ number_format($summary['success_rate'] ?? 0, 1) }}%</dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <!-- Average Amount -->
        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-calculator text-2xl text-purple-600"></i>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Average Amount</dt>
                            <dd class="text-2xl font-bold text-gray-900">${{ number_format($summary['average_amount'] ?? 0, 2) }}</dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Transactions Table -->
    <div class="bg-white shadow overflow-hidden rounded-lg">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg leading-6 font-medium text-gray-900">
                Transaction History
            </h3>
            <p class="mt-1 text-sm text-gray-500">
                A list of all transactions in your account including their status and details.
            </p>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                <tr>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Transaction
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Shop
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Amount
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Status
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Date
                    </th>
                    <th scope="col" class="relative px-6 py-3">
                        <span class="sr-only">Actions</span>
                    </th>
                </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                @forelse($transactions ?? [] as $transaction)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 h-10 w-10">
                                    <div class="h-10 w-10 rounded-full bg-gray-100 flex items-center justify-center">
                                        <i class="fas fa-credit-card text-gray-600"></i>
                                    </div>
                                </div>
                                <div class="ml-4">
                                    <div class="text-sm font-medium text-gray-900">
                                        {{ $transaction->reference ?? 'TX-' . $transaction->id }}
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        {{ $transaction->payment_method ?? 'Card Payment' }}
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900">{{ $transaction->shop->name ?? 'N/A' }}</div>
                            <div class="text-sm text-gray-500">{{ $transaction->shop->domain ?? '' }}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-gray-900">
                                ${{ number_format($transaction->amount ?? 0, 2) }}
                            </div>
                            <div class="text-sm text-gray-500">
                                {{ $transaction->currency ?? 'USD' }}
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @php
                                $statusClasses = [
                                    'completed' => 'bg-green-100 text-green-800',
                                    'pending' => 'bg-yellow-100 text-yellow-800',
                                    'failed' => 'bg-red-100 text-red-800',
                                    'refunded' => 'bg-blue-100 text-blue-800',
                                    'cancelled' => 'bg-gray-100 text-gray-800'
                                ];
                                $status = $transaction->status ?? 'pending';
                                $classes = $statusClasses[$status] ?? 'bg-gray-100 text-gray-800';
                            @endphp
                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full {{ $classes }}">
                                    {{ ucfirst($status) }}
                                </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <div>{{ $transaction->created_at->format('M d, Y') ?? 'N/A' }}</div>
                            <div>{{ $transaction->created_at->format('H:i:s') ?? '' }}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <div class="flex items-center justify-end space-x-2">
                                <a href="{{ route('merchant.transactions.show', $transaction->id) }}"
                                   class="text-blue-600 hover:text-blue-900 transition-colors duration-150">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <button type="button"
                                        class="text-gray-600 hover:text-gray-900 transition-colors duration-150"
                                        onclick="copyToClipboard('{{ $transaction->reference }}')">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center">
                            <div class="text-gray-500">
                                <i class="fas fa-credit-card text-4xl mb-4"></i>
                                <h3 class="text-lg font-medium text-gray-900 mb-2">No transactions found</h3>
                                <p class="text-sm">Try adjusting your search criteria or check back later.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        @if(isset($transactions) && method_exists($transactions, 'links'))
            <div class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
                {{ $transactions->links() }}
            </div>
        @endif
    </div>
@endsection

@push('scripts')
    <script>
        // Copy to clipboard functionality
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                // Show success message
                showNotification('Transaction reference copied to clipboard!', 'success');
            }).catch(function(err) {
                console.error('Failed to copy: ', err);
                showNotification('Failed to copy to clipboard', 'error');
            });
        }

        // Show notification
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 z-50 max-w-sm w-full bg-white border-l-4 p-4 shadow-lg rounded-md transition-all duration-300 ${
                type === 'success' ? 'border-green-400' :
                    type === 'error' ? 'border-red-400' : 'border-blue-400'
            }`;

            notification.innerHTML = `
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas ${
                type === 'success' ? 'fa-check-circle text-green-400' :
                    type === 'error' ? 'fa-exclamation-circle text-red-400' : 'fa-info-circle text-blue-400'
            }"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-gray-700">${message}</p>
                    </div>
                    <div class="ml-auto pl-3">
                        <button class="text-gray-400 hover:text-gray-600" onclick="this.parentElement.parentElement.parentElement.remove()">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            `;

            document.body.appendChild(notification);

            // Auto remove after 3 seconds
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 3000);
        }

        // Search functionality
        document.getElementById('search').addEventListener('input', debounce(function(e) {
            // Implement search functionality here
            console.log('Searching for:', e.target.value);
        }, 300));

        // Debounce function
        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }

        // Filter form submission
        document.querySelectorAll('select, input[type="date"]').forEach(element => {
            element.addEventListener('change', function() {
                // You can implement auto-filtering here
                console.log('Filter changed:', this.name, this.value);
            });
        });

        // Export functionality
        document.querySelector('[data-action="export"]')?.addEventListener('click', function() {
            // Implement export functionality
            showNotification('Export functionality will be implemented soon', 'info');
        });
    </script>
@endpush
