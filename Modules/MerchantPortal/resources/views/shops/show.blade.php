@extends('merchantportal::layouts.master')

@section('title', 'Shop Details - ' . $shop->owner_name)
@section('page-title', $shop->owner_name)
@section('page-subtitle', 'Detailed information and performance metrics for this shop')

@section('breadcrumb')
    <li>
        <div class="flex items-center">
            <i class="fas fa-chevron-right h-3 w-3 text-gray-400 mx-2"></i>
            <a href="{{ route('merchant.shops.index') }}" class="text-sm font-medium text-gray-500 hover:text-gray-700">
                Shops
            </a>
        </div>
    </li>
    <li>
        <div class="flex items-center">
            <i class="fas fa-chevron-right h-3 w-3 text-gray-400 mx-2"></i>
            <span class="text-sm font-medium text-gray-900">{{ $shop->owner_name }}</span>
        </div>
    </li>
@endsection

@section('page-actions')
    <div class="flex items-center space-x-3">
        <!-- View Transactions -->
        <a href="{{ route('merchant.transactions.index', ['shop_id' => $shop->id]) }}" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-150">
            <i class="fas fa-credit-card -ml-1 mr-2 h-4 w-4"></i>
            View Transactions
        </a>

        <!-- Refresh Button -->
        <button type="button" onclick="location.reload()" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-150">
            <i class="fas fa-sync-alt -ml-1 mr-2 h-4 w-4"></i>
            Refresh
        </button>

        <!-- More Actions Dropdown -->
        <div class="relative" x-data="{ open: false }">
            <button @click="open = !open" type="button" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-150">
                <i class="fas fa-ellipsis-h -ml-1 mr-2 h-4 w-4"></i>
                More
                <i class="fas fa-chevron-down ml-1 h-3 w-3"></i>
            </button>

            <div x-show="open" @click.away="open = false" x-transition:enter="transition ease-out duration-100" x-transition:enter-start="transform opacity-0 scale-95" x-transition:enter-end="transform opacity-100 scale-100" x-transition:leave="transition ease-in duration-75" x-transition:leave-start="transform opacity-100 scale-100" x-transition:leave-end="transform opacity-0 scale-95" class="absolute right-0 z-10 mt-2 w-48 origin-top-right rounded-lg bg-white shadow-lg ring-1 ring-black ring-opacity-5" x-cloak>
                <div class="py-1">
                    <a href="{{ route('merchant.rolling-reserves.index', ['shop_id' => $shop->id]) }}" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                        <i class="fas fa-piggy-bank mr-3 h-4 w-4 text-gray-400"></i>
                        View Reserves
                    </a>
                    <button onclick="copyToClipboard('{{ $shop->id }}')" class="flex items-center w-full px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                        <i class="fas fa-copy mr-3 h-4 w-4 text-gray-400"></i>
                        Copy Shop ID
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('content')
    <!-- Shop Header Card -->
    <div class="bg-white shadow-sm rounded-xl ring-1 ring-gray-900/5 overflow-hidden mb-8">
        <div class="p-6 border-b border-gray-200 bg-gradient-to-r from-blue-50 to-purple-50">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <div class="h-16 w-16 rounded-xl bg-gradient-to-r from-blue-500 to-purple-600 flex items-center justify-center shadow-lg">
                        <span class="text-white text-2xl font-bold">{{ substr($shop->owner_name, 0, 1) }}</span>
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">{{ $shop->owner_name }}</h1>
                        <div class="flex items-center space-x-4 mt-2">
                            <span class="text-sm text-gray-600">Shop ID: {{ $shop->id }}</span>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                {{ $shop->active === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                <i class="fas fa-circle mr-1 h-2 w-2"></i>
                                {{ ucfirst($shop->active ?? 'inactive') }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Quick Stats -->
                <div class="hidden lg:flex space-x-6">
                    <div class="text-center">
                        <div class="text-2xl font-bold text-gray-900">{{ number_format($shop->total_transactions ?? 0) }}</div>
                        <div class="text-xs text-gray-500 uppercase tracking-wide">Transactions</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-gray-900">€{{ number_format($shop->total_volume ?? 0, 0) }}</div>
                        <div class="text-xs text-gray-500 uppercase tracking-wide">Total Volume</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-gray-900">{{ number_format($shop->success_rate ?? 0, 1) }}%</div>
                        <div class="text-xs text-gray-500 uppercase tracking-wide">Success Rate</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Shop Information Details -->
        <div class="p-6">
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <!-- Basic Information -->
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                        <i class="fas fa-info-circle mr-2 text-blue-500"></i>
                        Basic Information
                    </h3>
                    <dl class="space-y-3">
                        <div class="flex justify-between">
                            <dt class="text-sm font-medium text-gray-500">Owner Name</dt>
                            <dd class="text-sm text-gray-900">{{ $shop->owner_name ?? 'Not specified' }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-sm font-medium text-gray-500">Email</dt>
                            <dd class="text-sm text-gray-900">{{ $shop->email ?? 'Not specified' }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-sm font-medium text-gray-500">Website</dt>
                            <dd class="text-sm text-gray-900">
                                @if($shop->website)
                                    <a href="{{ $shop->website }}" target="_blank" class="text-blue-600 hover:text-blue-800">
                                        {{ $shop->website }}
                                        <i class="fas fa-external-link-alt ml-1 h-3 w-3"></i>
                                    </a>
                                @else
                                    Not specified
                                @endif
                            </dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-sm font-medium text-gray-500">Created</dt>
                            <dd class="text-sm text-gray-900">{{ $shop->created_at->format('M j, Y') }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-sm font-medium text-gray-500">Last Transaction</dt>
                            <dd class="text-sm text-gray-900">
                                {{ $shop->last_transaction_at ? $shop->last_transaction_at->diffForHumans() : 'Never' }}
                            </dd>
                        </div>
                    </dl>
                </div>

                <!-- Payment Settings -->
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                        <i class="fas fa-cog mr-2 text-green-500"></i>
                        Payment Settings
                    </h3>
                    @if($shop->settings)
                        <dl class="space-y-3">
                            <div class="flex justify-between">
                                <dt class="text-sm font-medium text-gray-500">MDR Rate</dt>
                                <dd class="text-sm text-gray-900">{{ number_format($shop->settings->mdr_percentage ?? 0, 2) }}%</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-sm font-medium text-gray-500">Rolling Reserve Rate</dt>
                                <dd class="text-sm text-gray-900">{{ number_format($shop->settings->rolling_reserve_percentage ?? 0, 1) }}%</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-sm font-medium text-gray-500">Settlement Currency</dt>
                                <dd class="text-sm text-gray-900">{{ $shop->settings->settlement_currency ?? 'EUR' }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-sm font-medium text-gray-500">Settlement Period</dt>
                                <dd class="text-sm text-gray-900">{{ $shop->settings->settlement_period ?? 'T+2' }} days</dd>
                            </div>
                        </dl>
                    @else
                        <div class="text-center py-4">
                            <i class="fas fa-exclamation-triangle text-yellow-400 text-2xl mb-2"></i>
                            <p class="text-sm text-gray-500">No payment settings configured</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Performance Metrics Grid -->
    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4 mb-8">
        <!-- Total Transactions -->
        <div class="bg-white shadow-sm rounded-xl ring-1 ring-gray-900/5 p-6 hover:shadow-lg transition-shadow duration-300">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-r from-blue-500 to-blue-600 shadow-lg">
                        <i class="fas fa-credit-card text-white"></i>
                    </div>
                </div>
                <div class="ml-4 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500">Total Transactions</dt>
                        <dd class="text-2xl font-bold text-gray-900">{{ number_format($shop->total_transactions ?? 0) }}</dd>
                    </dl>
                    <div class="mt-2 flex items-center text-sm">
                        <span class="text-blue-600 font-medium">
                            All time
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Total Volume -->
        <div class="bg-white shadow-sm rounded-xl ring-1 ring-gray-900/5 p-6 hover:shadow-lg transition-shadow duration-300">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-r from-green-500 to-green-600 shadow-lg">
                        <i class="fas fa-euro-sign text-white"></i>
                    </div>
                </div>
                <div class="ml-4 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500">Total Volume</dt>
                        <dd class="text-2xl font-bold text-gray-900">€{{ number_format($shop->total_volume ?? 0, 0) }}</dd>
                    </dl>
                    <div class="mt-2 flex items-center text-sm">
                        <span class="text-green-600 font-medium">
                            Lifetime value
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Success Rate -->
        <div class="bg-white shadow-sm rounded-xl ring-1 ring-gray-900/5 p-6 hover:shadow-lg transition-shadow duration-300">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-r from-purple-500 to-purple-600 shadow-lg">
                        <i class="fas fa-chart-line text-white"></i>
                    </div>
                </div>
                <div class="ml-4 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500">Success Rate</dt>
                        <dd class="text-2xl font-bold text-gray-900">{{ number_format($shop->success_rate ?? 0, 1) }}%</dd>
                    </dl>
                    <div class="mt-2 flex items-center text-sm">
                        <div class="flex-1 bg-gray-200 rounded-full h-2 mr-2">
                            <div class="bg-gradient-to-r from-purple-500 to-purple-600 h-2 rounded-full transition-all duration-500"
                                 style="width: {{ min($shop->success_rate ?? 0, 100) }}%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Average Transaction -->
        <div class="bg-white shadow-sm rounded-xl ring-1 ring-gray-900/5 p-6 hover:shadow-lg transition-shadow duration-300">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-r from-yellow-500 to-yellow-600 shadow-lg">
                        <i class="fas fa-calculator text-white"></i>
                    </div>
                </div>
                <div class="ml-4 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500">Avg Transaction</dt>
                        <dd class="text-2xl font-bold text-gray-900">€{{ number_format($shop->average_transaction ?? 0, 0) }}</dd>
                    </dl>
                    <div class="mt-2 flex items-center text-sm">
                        <span class="text-yellow-600 font-medium">
                            Per transaction
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Content Sections -->
    <div class="grid grid-cols-1 gap-8 lg:grid-cols-3">
        <!-- Recent Transactions (2/3 width) -->
        <div class="lg:col-span-2">
            <div class="bg-white shadow-sm rounded-xl ring-1 ring-gray-900/5 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                            <i class="fas fa-list mr-3 text-blue-500"></i>
                            Recent Transactions
                        </h3>
                        <a href="{{ route('merchant.transactions.index', ['shop_id' => $shop->id]) }}" class="text-sm font-medium text-blue-600 hover:text-blue-500">
                            View all <i class="fas fa-arrow-right ml-1"></i>
                        </a>
                    </div>
                </div>

                @if(isset($shop->recentTransactions) && $shop->recentTransactions->isNotEmpty())
                    <div class="overflow-hidden">
                        <ul class="divide-y divide-gray-200">
                            @foreach($shop->recentTransactions->take(10) as $transaction)
                                <li class="p-6 hover:bg-gray-50 transition-colors duration-150">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center space-x-4">
                                            <div class="flex-shrink-0">
                                                <div class="h-10 w-10 rounded-full bg-{{ $transaction->status === 'matched' ? 'green' : ($transaction->status === 'failed' ? 'red' : 'yellow') }}-100 flex items-center justify-center">
                                                    <i class="fas fa-{{ $transaction->status === 'matched' ? 'check' : ($transaction->status === 'failed' ? 'times' : 'clock') }} text-{{ $transaction->status === 'matched' ? 'green' : ($transaction->status === 'failed' ? 'red' : 'yellow') }}-600"></i>
                                                </div>
                                            </div>
                                            <div>
                                                <p class="text-sm font-medium text-gray-900">
                                                    {{ $transaction->payment_id }}
                                                </p>
                                                <p class="text-xs text-gray-500">
                                                    {{ $transaction->tr_date_time->format('M j, Y H:i') }}
                                                </p>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-sm font-semibold text-gray-900">
                                                €{{ number_format($transaction->tr_amount / 100, 2) }}
                                            </p>
                                            <p class="text-xs text-gray-500 capitalize">
                                                {{ $transaction->status }}
                                            </p>
                                        </div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @else
                    <div class="p-6 text-center">
                        <i class="fas fa-credit-card text-4xl text-gray-300 mb-4"></i>
                        <p class="text-gray-500">No transactions found</p>
                        <p class="text-xs text-gray-400 mt-2">Transactions will appear here once payments are processed</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Rolling Reserves & Quick Info (1/3 width) -->
        <div class="space-y-6">
            <!-- Rolling Reserves -->
            <div class="bg-white shadow-sm rounded-xl ring-1 ring-gray-900/5 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                            <i class="fas fa-piggy-bank mr-3 text-yellow-500"></i>
                            Rolling Reserves
                        </h3>
                        <a href="{{ route('merchant.rolling-reserves.index', ['shop_id' => $shop->id]) }}" class="text-sm font-medium text-yellow-600 hover:text-yellow-500">
                            View all <i class="fas fa-arrow-right ml-1"></i>
                        </a>
                    </div>
                </div>

                <div class="p-6">
                    <!-- Current Reserve Summary -->
                    <div class="mb-6">
                        <div class="text-center p-4 bg-yellow-50 rounded-lg border border-yellow-200">
                            <div class="text-2xl font-bold text-yellow-900">€{{ number_format($currentReserveAmount ?? 0, 2) }}</div>
                            <div class="text-sm text-yellow-700">Current Reserve Balance</div>
                            <div class="text-xs text-yellow-600 mt-1">
                                {{ number_format($shop->settings->rolling_reserve_percentage ?? 0, 1) }}% reserve rate
                            </div>
                        </div>
                    </div>

                    @if(isset($shop->rollingReserves) && $shop->rollingReserves->isNotEmpty())
                        <div class="space-y-3">
                            <h4 class="text-sm font-medium text-gray-900">Recent Reserves</h4>
                            @foreach($shop->rollingReserves->take(5) as $reserve)
                                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                    <div>
                                        <div class="text-sm font-medium text-gray-900">
                                            €{{ number_format($reserve->amount, 2) }}
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            {{ $reserve->created_at->format('M j') }}
                                        </div>
                                    </div>
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium
                                        {{ $reserve->status === 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800' }}">
                                        {{ ucfirst($reserve->status) }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-4">
                            <i class="fas fa-piggy-bank text-gray-300 text-2xl mb-2"></i>
                            <p class="text-sm text-gray-500">No active reserves</p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="bg-white shadow-sm rounded-xl ring-1 ring-gray-900/5 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                        <i class="fas fa-bolt mr-3 text-purple-500"></i>
                        Quick Actions
                    </h3>
                </div>

                <div class="p-6">
                    <div class="space-y-3">
                        <!-- View Transactions -->
                        <a href="{{ route('merchant.transactions.index', ['shop_id' => $shop->id]) }}"
                           class="flex items-center p-3 bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors duration-150 group">
                            <div class="flex-shrink-0">
                                <i class="fas fa-list text-blue-600"></i>
                            </div>
                            <div class="ml-3 flex-1">
                                <p class="text-sm font-medium text-blue-900">View Transactions</p>
                                <p class="text-xs text-blue-600">Browse all transactions</p>
                            </div>
                            <i class="fas fa-chevron-right text-blue-600 group-hover:translate-x-1 transition-transform duration-150"></i>
                        </a>

                        <!-- View Reserves -->
                        <a href="{{ route('merchant.rolling-reserves.index', ['shop_id' => $shop->id]) }}"
                           class="flex items-center p-3 bg-yellow-50 rounded-lg hover:bg-yellow-100 transition-colors duration-150 group">
                            <div class="flex-shrink-0">
                                <i class="fas fa-piggy-bank text-yellow-600"></i>
                            </div>
                            <div class="ml-3 flex-1">
                                <p class="text-sm font-medium text-yellow-900">Rolling Reserves</p>
                                <p class="text-xs text-yellow-600">Monitor reserves</p>
                            </div>
                            <i class="fas fa-chevron-right text-yellow-600 group-hover:translate-x-1 transition-transform duration-150"></i>
                        </a>

                        <!-- Copy Shop ID -->
                        <button onclick="copyToClipboard('{{ $shop->id }}')"
                                class="flex items-center w-full p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors duration-150 group">
                            <div class="flex-shrink-0">
                                <i class="fas fa-copy text-gray-600"></i>
                            </div>
                            <div class="ml-3 flex-1 text-left">
                                <p class="text-sm font-medium text-gray-900">Copy Shop ID</p>
                                <p class="text-xs text-gray-600">{{ $shop->id }}</p>
                            </div>
                            <i class="fas fa-chevron-right text-gray-600 group-hover:translate-x-1 transition-transform duration-150"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Shop Status -->
            <div class="bg-white shadow-sm rounded-xl ring-1 ring-gray-900/5 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                        <i class="fas fa-info-circle mr-3 text-indigo-500"></i>
                        Shop Status
                    </h3>
                </div>

                <div class="p-6">
                    <div class="space-y-4">
                        <!-- Status Indicator -->
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium text-gray-700">Current Status</span>
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium
                                {{ $shop->active === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                <i class="fas fa-circle mr-1 h-2 w-2"></i>
                                {{ ucfirst($shop->active ?? 'inactive') }}
                            </span>
                        </div>

                        <!-- Health Indicators -->
                        <div class="space-y-3">
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-gray-600">Configuration</span>
                                <span class="font-medium {{ $shop->settings ? 'text-green-600' : 'text-red-600' }}">
                                    <i class="fas fa-{{ $shop->settings ? 'check' : 'times' }} mr-1"></i>
                                    {{ $shop->settings ? 'Complete' : 'Incomplete' }}
                                </span>
                            </div>

                            <div class="flex items-center justify-between text-sm">
                                <span class="text-gray-600">Recent Activity</span>
                                <span class="font-medium {{ $shop->last_transaction_at && $shop->last_transaction_at->gt(now()->subDays(7)) ? 'text-green-600' : 'text-yellow-600' }}">
                                    <i class="fas fa-{{ $shop->last_transaction_at && $shop->last_transaction_at->gt(now()->subDays(7)) ? 'check' : 'exclamation-triangle' }} mr-1"></i>
                                    {{ $shop->last_transaction_at && $shop->last_transaction_at->gt(now()->subDays(7)) ? 'Active' : 'Inactive' }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                console.log('Copied to clipboard: ' + text);
                showToast('Shop ID copied to clipboard!', 'success');
            }, function(err) {
                console.error('Could not copy text: ', err);
                showToast('Failed to copy to clipboard', 'error');
            });
        }

        function showToast(message, type = 'info') {
            const toast = document.createElement('div');
            toast.className = `fixed top-4 right-4 z-50 px-4 py-2 rounded-lg shadow-lg text-white transform transition-all duration-300 ${
                type === 'success' ? 'bg-green-500' :
                    type === 'error' ? 'bg-red-500' :
                        'bg-blue-500'
            }`;
            toast.textContent = message;
            toast.style.transform = 'translateX(100%)';

            document.body.appendChild(toast);

            // Animate in
            setTimeout(() => {
                toast.style.transform = 'translateX(0)';
            }, 10);

            // Animate out and remove
            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transform = 'translateX(100%)';
                setTimeout(() => {
                    if (document.body.contains(toast)) {
                        document.body.removeChild(toast);
                    }
                }, 300);
            }, 3000);
        }

        // Auto-refresh shop data every 2 minutes
        setInterval(function() {
            // Optional: implement partial refresh for real-time data
            console.log('Shop data refresh check');
        }, 120000);

        // Initialize any additional functionality
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Shop detail page loaded for shop: {{ $shop->id }}');

            // Optional: Add real-time transaction updates
            // initializeWebSocketConnection();
        });

        // Optional: Real-time updates (if WebSocket is available)
        function initializeWebSocketConnection() {
            // Implementation for real-time transaction updates
            // This would connect to your WebSocket server if available
            console.log('WebSocket connection would be initialized here');
        }

        // Performance chart initialization (if chart library is available)
        function initializePerformanceChart() {
            // Implementation for performance charts
            // This would use Chart.js or similar library if needed
            console.log('Performance chart would be initialized here');
        }
    </script>
@endpush
