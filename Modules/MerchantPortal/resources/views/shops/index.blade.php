@extends('merchantportal::layouts.master')

@section('title', 'Shops')
@section('page-title', 'Shops')
@section('page-subtitle', 'Manage and monitor all your merchant shops')

@section('breadcrumb')
    <li>
        <div class="flex items-center">
            <i class="fas fa-chevron-right h-3 w-3 text-gray-400 mx-2"></i>
            <span class="text-sm font-medium text-gray-900">Shops</span>
        </div>
    </li>
@endsection

@section('page-actions')
    <div class="flex items-center space-x-3">
        <!-- Refresh Button -->
        <button type="button" onclick="location.reload()" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-150">
            <i class="fas fa-sync-alt -ml-1 mr-2 h-4 w-4"></i>
            Refresh
        </button>
    </div>
@endsection

@section('content')
    <!-- Shop Overview Stats -->
    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4 mb-8">
        <!-- Total Shops -->
        <div class="bg-white shadow-sm rounded-xl ring-1 ring-gray-900/5 p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-r from-blue-500 to-blue-600 shadow-lg">
                        <i class="fas fa-store text-white"></i>
                    </div>
                </div>
                <div class="ml-4 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500">Total Shops</dt>
                        <dd class="text-2xl font-bold text-gray-900">{{ $shops->count() }}</dd>
                    </dl>
                    <div class="mt-2 flex items-center text-sm">
                        <span class="text-green-600 font-medium">
                            {{ $shops->where('status', 'active')->count() }} active
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Total Volume -->
        <div class="bg-white shadow-sm rounded-xl ring-1 ring-gray-900/5 p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-r from-green-500 to-green-600 shadow-lg">
                        <i class="fas fa-euro-sign text-white"></i>
                    </div>
                </div>
                <div class="ml-4 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500">Total Volume</dt>
                        <dd class="text-2xl font-bold text-gray-900">€{{ number_format($totalStats['volume'] ?? 0, 0) }}</dd>
                    </dl>
                    <div class="mt-2 flex items-center text-sm">
                        <span class="text-green-600 font-medium">
                            This month
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Average Success Rate -->
        <div class="bg-white shadow-sm rounded-xl ring-1 ring-gray-900/5 p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-r from-purple-500 to-purple-600 shadow-lg">
                        <i class="fas fa-chart-line text-white"></i>
                    </div>
                </div>
                <div class="ml-4 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500">Avg Success Rate</dt>
                        <dd class="text-2xl font-bold text-gray-900">{{ number_format($totalStats['success_rate'] ?? 0, 1) }}%</dd>
                    </dl>
                    <div class="mt-2 flex items-center text-sm">
                        <div class="flex-1 bg-gray-200 rounded-full h-2 mr-2">
                            <div class="bg-gradient-to-r from-purple-500 to-purple-600 h-2 rounded-full transition-all duration-500" style="width: {{ $totalStats['success_rate'] ?? 0 }}%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Total Reserves -->
        <div class="bg-white shadow-sm rounded-xl ring-1 ring-gray-900/5 p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-r from-yellow-500 to-yellow-600 shadow-lg">
                        <i class="fas fa-piggy-bank text-white"></i>
                    </div>
                </div>
                <div class="ml-4 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500">Total Reserves</dt>
                        <dd class="text-2xl font-bold text-gray-900">€{{ number_format($totalStats['reserves'] ?? 0, 0) }}</dd>
                    </dl>
                    <div class="mt-2 flex items-center text-sm">
                        <span class="text-yellow-600 font-medium">
                            Rolling reserves
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Shops Grid -->
    @if($shops->isNotEmpty())
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2 xl:grid-cols-3">
            @foreach($shops as $shop)
                <div class="group bg-white shadow-sm rounded-xl ring-1 ring-gray-900/5 hover:shadow-lg hover:scale-[1.02] transition-all duration-300 overflow-hidden">
                    <!-- Shop Header -->
                    <div class="p-6 border-b border-gray-200">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-3">
                                <div class="h-12 w-12 rounded-xl bg-gradient-to-r from-blue-500 to-purple-600 flex items-center justify-center shadow-lg">
                                    <span class="text-white text-lg font-bold">{{ substr($shop->owner_name, 0, 1) }}</span>
                                </div>
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-900 group-hover:text-blue-600 transition-colors duration-300">
                                        {{ $shop->owner_name }}
                                    </h3>
                                    <p class="text-sm text-gray-500">ID: {{ $shop->id }}</p>
                                </div>
                            </div>

                            <!-- Status Badge -->
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                {{ $shop->active === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                <i class="fas fa-circle mr-1 h-2 w-2"></i>
                                {{ ucfirst($shop->active) }}
                            </span>
                        </div>
                    </div>

                    <!-- Shop Metrics -->
                    <div class="p-6">
                        <div class="grid grid-cols-2 gap-4">
                            <!-- Transaction Count -->
                            <div class="text-center p-4 bg-gray-50 rounded-lg">
                                <div class="text-2xl font-bold text-gray-900">{{ $shop->transactions_count ?? 0 }}</div>
                                <div class="text-xs text-gray-500 uppercase tracking-wide">Transactions</div>
                            </div>

                            <!-- Total Volume -->
                            <div class="text-center p-4 bg-gray-50 rounded-lg">
                                <div class="text-2xl font-bold text-gray-900">€{{ number_format($shop->total_volume ?? 0, 0) }}</div>
                                <div class="text-xs text-gray-500 uppercase tracking-wide">Volume</div>
                            </div>

                            <!-- Success Rate -->
                            <div class="text-center p-4 bg-gray-50 rounded-lg">
                                <div class="text-2xl font-bold text-gray-900">{{ number_format($shop->success_rate ?? 0, 1) }}%</div>
                                <div class="text-xs text-gray-500 uppercase tracking-wide">Success Rate</div>
                            </div>

                            <!-- Rolling Reserve -->
                            <div class="text-center p-4 bg-gray-50 rounded-lg">
                                <div class="text-2xl font-bold text-gray-900">€{{ number_format($shop->rolling_reserve_amount ?? 0, 0) }}</div>
                                <div class="text-xs text-gray-500 uppercase tracking-wide">Reserve</div>
                            </div>
                        </div>

                        <!-- Performance Indicators -->
                        <div class="mt-6 space-y-3">
                            <!-- Success Rate Bar -->
                            <div>
                                <div class="flex justify-between text-sm mb-1">
                                    <span class="text-gray-600">Success Rate</span>
                                    <span class="font-medium">{{ number_format($shop->success_rate ?? 0, 1) }}%</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-gradient-to-r from-green-400 to-green-600 h-2 rounded-full transition-all duration-500"
                                         style="width: {{ min($shop->success_rate ?? 0, 100) }}%"></div>
                                </div>
                            </div>

                            <!-- Reserve Percentage -->
                            <div>
                                <div class="flex justify-between text-sm mb-1">
                                    <span class="text-gray-600">Reserve Rate</span>
                                    <span class="font-medium">{{ number_format($shop->settings->rolling_reserve_percentage ?? 0, 1) }}%</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-gradient-to-r from-yellow-400 to-yellow-600 h-2 rounded-full transition-all duration-500"
                                         style="width: {{ min($shop->settings->rolling_reserve_percentage ?? 0, 100) }}%"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Additional Info -->
                        <div class="mt-6 pt-4 border-t border-gray-200">
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-gray-500">Last transaction:</span>
                                <span class="font-medium text-gray-900">
                                    {{ $shop->last_transaction_at ? $shop->last_transaction_at->diffForHumans() : 'Never' }}
                                </span>
                            </div>
                            @if($shop->settings)
                                <div class="flex items-center justify-between text-sm mt-2">
                                    <span class="text-gray-500">MDR Rate:</span>
                                    <span class="font-medium text-gray-900">{{ number_format($shop->settings->mdr_percentage ?? 0, 2) }}%</span>
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- Shop Actions -->
                    <div class="px-6 py-4 bg-gray-50 border-t border-gray-200">
                        <div class="flex items-center justify-between">
                            <div class="flex space-x-3">
                                <a href="{{ route('merchant.shops.show', $shop->id) }}"
                                   class="inline-flex items-center text-blue-600 hover:text-blue-900 text-sm font-medium transition-colors duration-150">
                                    <i class="fas fa-eye mr-1 h-3 w-3"></i>
                                    View Details
                                </a>
                                <a href="{{ route('merchant.transactions.index', ['shop_id' => $shop->id]) }}"
                                   class="inline-flex items-center text-green-600 hover:text-green-900 text-sm font-medium transition-colors duration-150">
                                    <i class="fas fa-credit-card mr-1 h-3 w-3"></i>
                                    Transactions
                                </a>
                            </div>

                            <!-- Dropdown Menu -->
                            <div class="relative" x-data="{ open: false }">
                                <button @click="open = !open" type="button"
                                        class="text-gray-400 hover:text-gray-600 transition-colors duration-150 p-1">
                                    <i class="fas fa-ellipsis-v h-4 w-4"></i>
                                </button>

                                <div x-show="open" @click.away="open = false"
                                     x-transition:enter="transition ease-out duration-100"
                                     x-transition:enter-start="transform opacity-0 scale-95"
                                     x-transition:enter-end="transform opacity-100 scale-100"
                                     x-transition:leave="transition ease-in duration-75"
                                     x-transition:leave-start="transform opacity-100 scale-100"
                                     x-transition:leave-end="transform opacity-0 scale-95"
                                     class="absolute right-0 z-10 mt-2 w-48 bg-white rounded-lg shadow-lg ring-1 ring-black ring-opacity-5 divide-y divide-gray-100"
                                     x-cloak>
                                    <div class="py-1">
                                        <a href="{{ route('merchant.shops.show', $shop->id) }}"
                                           class="group flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                            <i class="fas fa-info-circle mr-3 h-4 w-4 text-gray-400"></i>
                                            Shop Details
                                        </a>
                                        <a href="{{ route('merchant.rolling-reserves.index', ['shop_id' => $shop->id]) }}"
                                           class="group flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                            <i class="fas fa-piggy-bank mr-3 h-4 w-4 text-gray-400"></i>
                                            View Reserves
                                        </a>
                                    </div>
                                    <div class="py-1">
                                        <button onclick="copyToClipboard('{{ $shop->id }}')"
                                                class="group flex items-center w-full px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                            <i class="fas fa-copy mr-3 h-4 w-4 text-gray-400"></i>
                                            Copy Shop ID
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Pagination -->
{{--        @if($shops->hasPages())--}}
{{--            <div class="mt-8 flex items-center justify-center">--}}
{{--                <div class="bg-white rounded-lg shadow-sm ring-1 ring-gray-900/5 px-6 py-3">--}}
{{--                    {{ $shops->links() }}--}}
{{--                </div>--}}
{{--            </div>--}}
{{--        @endif--}}

    @else
        <!-- Empty State -->
        <div class="text-center py-12">
            <div class="mx-auto h-24 w-24 rounded-full bg-gray-100 flex items-center justify-center mb-6">
                <i class="fas fa-store text-4xl text-gray-400"></i>
            </div>
            <h3 class="text-lg font-medium text-gray-900 mb-2">No shops found</h3>
            <p class="text-gray-500 mb-8 max-w-md mx-auto">
                You don't have any shops configured yet. Shops will appear here once they're added to your merchant account.
            </p>
            <div class="flex items-center justify-center space-x-4">
                <button type="button" onclick="location.reload()"
                        class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-150">
                    <i class="fas fa-sync-alt -ml-1 mr-2 h-4 w-4"></i>
                    Refresh
                </button>
            </div>
        </div>
    @endif

    <!-- Shop Performance Summary Table (Desktop View) -->
    @if($shops->isNotEmpty())
        <div class="mt-8 bg-white shadow-sm rounded-xl ring-1 ring-gray-900/5 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                    <i class="fas fa-chart-bar mr-3 text-blue-500"></i>
                    Detailed Shop Performance
                </h3>
                <p class="mt-1 text-sm text-gray-500">Comprehensive view of all shop metrics</p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Shop
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Status
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Transactions
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Volume
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Success Rate
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Reserve Amount
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            MDR Rate
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Last Transaction
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Actions
                        </th>
                    </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($shops as $shop)
                        <tr class="hover:bg-gray-50 transition-colors duration-150">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="h-10 w-10 rounded-full bg-gradient-to-r from-blue-500 to-purple-600 flex items-center justify-center mr-3">
                                        <span class="text-white text-sm font-bold">{{ substr($shop->owner_name, 0, 1) }}</span>
                                    </div>
                                    <div>
                                        <div class="text-sm font-medium text-gray-900">{{ $shop->owner_name }}</div>
                                        <div class="text-xs text-gray-500">ID: {{ $shop->id }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                        {{ $shop->active === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                        <i class="fas fa-circle mr-1 h-2 w-2"></i>
                                        {{ ucfirst($shop->active) }}
                                    </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <div class="font-semibold">{{ number_format($shop->transactions_count ?? 0) }}</div>
                                <div class="text-xs text-gray-500">total</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <div class="font-semibold">€{{ number_format($shop->total_volume ?? 0, 0) }}</div>
                                <div class="text-xs text-gray-500">lifetime</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="flex-1">
                                        <div class="text-sm font-semibold text-gray-900">{{ number_format($shop->success_rate ?? 0, 1) }}%</div>
                                        <div class="w-full bg-gray-200 rounded-full h-1.5 mt-1">
                                            <div class="bg-green-500 h-1.5 rounded-full" style="width: {{ min($shop->success_rate ?? 0, 100) }}%"></div>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <div class="font-semibold">€{{ number_format($shop->rolling_reserve_amount ?? 0, 2) }}</div>
                                <div class="text-xs text-gray-500">{{ number_format($shop->settings->rolling_reserve_percentage ?? 0, 1) }}% rate</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <div class="font-semibold">{{ number_format($shop->settings->mdr_percentage ?? 0, 2) }}%</div>
                                <div class="text-xs text-gray-500">merchant discount</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <div>{{ $shop->last_transaction_at ? $shop->last_transaction_at->format('M j, Y') : 'Never' }}</div>
                                @if($shop->last_transaction_at)
                                    <div class="text-xs text-gray-500">{{ $shop->last_transaction_at->diffForHumans() }}</div>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <div class="flex items-center space-x-2">
                                    <a href="{{ route('merchant.shops.show', $shop->id) }}"
                                       class="text-blue-600 hover:text-blue-900 transition-colors duration-150">
                                        <i class="fas fa-eye h-4 w-4"></i>
                                        <span class="sr-only">View details</span>
                                    </a>
                                    <a href="{{ route('merchant.transactions.index', ['shop_id' => $shop->id]) }}"
                                       class="text-green-600 hover:text-green-900 transition-colors duration-150">
                                        <i class="fas fa-credit-card h-4 w-4"></i>
                                        <span class="sr-only">View transactions</span>
                                    </a>
                                    <button onclick="copyToClipboard('{{ $shop->id }}')"
                                            class="text-gray-400 hover:text-gray-600 transition-colors duration-150">
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
        </div>
    @endif
@endsection

@push('scripts')
    <script>
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                // Show success notification
                console.log('Copied to clipboard: ' + text);

                // You could implement a toast notification here
                showToast('Shop ID copied to clipboard!', 'success');
            }, function(err) {
                console.error('Could not copy text: ', err);
                showToast('Failed to copy to clipboard', 'error');
            });
        }

        function showToast(message, type = 'info') {
            // Simple toast implementation - you could enhance this
            const toast = document.createElement('div');
            toast.className = `fixed top-4 right-4 z-50 px-4 py-2 rounded-lg shadow-lg text-white ${
                type === 'success' ? 'bg-green-500' :
                    type === 'error' ? 'bg-red-500' :
                        'bg-blue-500'
            }`;
            toast.textContent = message;

            document.body.appendChild(toast);

            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transform = 'translateX(100%)';
                setTimeout(() => document.body.removeChild(toast), 300);
            }, 3000);
        }

        // Auto-refresh data every 2 minutes
        setInterval(function() {
            // You could implement partial refresh here
            console.log('Shop data refresh check');
        }, 120000);
    </script>
@endpush
