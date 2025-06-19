@extends('merchantportal::layouts.master')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard')
@section('page-subtitle', 'Overview of your merchant account performance')

@section('content')
    <!-- Key Metrics Grid -->
    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4 mb-8">
        <!-- Total Shops -->
        <div class="group relative overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-900/5 transition-all duration-300 hover:shadow-lg hover:scale-105">
            <div class="p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-r from-blue-500 to-blue-600 shadow-lg">
                            <i class="fas fa-store text-white"></i>
                        </div>
                    </div>
                    <div class="ml-4 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500">Total Shops</dt>
                            <dd class="text-2xl font-bold text-gray-900">{{ $data['shops']->count() ?? 0 }}</dd>
                        </dl>
                        <div class="mt-2 flex items-center text-sm">
                            <span class="text-green-600 font-medium">
                                {{ $data['shops']->where('status', 'active')->count() ?? 0 }} active
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="bg-gradient-to-r from-blue-50 to-blue-100 px-6 py-3">
                <div class="text-sm">
                    <a href="{{ route('merchant.shops.index') }}" class="font-medium text-blue-700 hover:text-blue-900 transition-colors duration-150 flex items-center">
                        View all shops
                        <i class="fas fa-arrow-right ml-1 h-3 w-3"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- Recent Transactions -->
        <div class="group relative overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-900/5 transition-all duration-300 hover:shadow-lg hover:scale-105">
            <div class="p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-r from-green-500 to-green-600 shadow-lg">
                            <i class="fas fa-credit-card text-white"></i>
                        </div>
                    </div>
                    <div class="ml-4 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500">Today's Transactions</dt>
                            <dd class="text-2xl font-bold text-gray-900">{{ $data['statistics']['transactions_today'] ?? 0 }}</dd>
                        </dl>
                        <div class="mt-2 flex items-center text-sm">
                            <span class="text-green-600 font-medium">
                                +{{ $data['statistics']['transactions_growth'] ?? 0 }}% from yesterday
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="bg-gradient-to-r from-green-50 to-green-100 px-6 py-3">
                <div class="text-sm">
                    <a href="{{ route('merchant.transactions.index') }}" class="font-medium text-green-700 hover:text-green-900 transition-colors duration-150 flex items-center">
                        View transactions
                        <i class="fas fa-arrow-right ml-1 h-3 w-3"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- Total Volume -->
        <div class="group relative overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-900/5 transition-all duration-300 hover:shadow-lg hover:scale-105">
            <div class="p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-r from-purple-500 to-purple-600 shadow-lg">
                            <i class="fas fa-euro-sign text-white"></i>
                        </div>
                    </div>
                    <div class="ml-4 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500">Monthly Volume</dt>
                            <dd class="text-2xl font-bold text-gray-900">€{{ number_format($data['statistics']['monthly_volume'] ?? 0, 0) }}</dd>
                        </dl>
                        <div class="mt-2 flex items-center text-sm">
                            <span class="text-purple-600 font-medium">
                                Avg: €{{ number_format($data['statistics']['average_transaction'] ?? 0, 0) }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="bg-gradient-to-r from-purple-50 to-purple-100 px-6 py-3">
                <div class="text-sm">
                    <a href="{{ route('merchant.rolling-reserves.index') }}" class="font-medium text-purple-700 hover:text-purple-900 transition-colors duration-150 flex items-center">
                        View reserves
                        <i class="fas fa-arrow-right ml-1 h-3 w-3"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- Success Rate -->
        <div class="group relative overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-900/5 transition-all duration-300 hover:shadow-lg hover:scale-105">
            <div class="p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-r from-indigo-500 to-indigo-600 shadow-lg">
                            <i class="fas fa-chart-line text-white"></i>
                        </div>
                    </div>
                    <div class="ml-4 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500">Success Rate</dt>
                            <dd class="text-2xl font-bold text-gray-900">{{ number_format($data['statistics']['success_rate'] ?? 0, 1) }}%</dd>
                        </dl>
                        <div class="mt-2 flex items-center text-sm">
                            <div class="flex-1 bg-gray-200 rounded-full h-2">
                                <div class="bg-gradient-to-r from-indigo-500 to-indigo-600 h-2 rounded-full transition-all duration-500" style="width: {{ $data['statistics']['success_rate'] ?? 0 }}%"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="bg-gradient-to-r from-indigo-50 to-indigo-100 px-6 py-3">
                <div class="text-sm">
                    <span class="font-medium text-indigo-700">Industry avg: 94.2%</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts and Analytics Row -->
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2 mb-8">
        <!-- Monthly Overview Chart -->
        <div class="bg-white shadow-sm rounded-xl ring-1 ring-gray-900/5">
            <div class="p-6 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                            <i class="fas fa-chart-area mr-3 text-blue-500"></i>
                            Monthly Transaction Overview
                        </h3>
                        <p class="mt-1 text-sm text-gray-500">Transaction volume over the past 12 months</p>
                    </div>
                    <div class="flex space-x-2">
                        <button class="px-3 py-1 text-xs bg-blue-100 text-blue-700 rounded-full font-medium">Volume</button>
                        <button class="px-3 py-1 text-xs text-gray-500 hover:bg-gray-100 rounded-full font-medium">Count</button>
                    </div>
                </div>
            </div>
            <div class="p-6">
                <div class="h-64 flex items-center justify-center bg-gray-50 rounded-lg">
                    <div class="text-center">
                        <i class="fas fa-chart-line text-4xl text-gray-300 mb-4"></i>
                        <p class="text-gray-500">Chart will be rendered here</p>
                        <p class="text-xs text-gray-400 mt-2">Monthly data: €{{ number_format($data['monthly_summary']['volume'] ?? 0, 0) }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Transaction Status Distribution -->
        <div class="bg-white shadow-sm rounded-xl ring-1 ring-gray-900/5">
            <div class="p-6 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                    <i class="fas fa-chart-pie mr-3 text-green-500"></i>
                    Transaction Status
                </h3>
                <p class="mt-1 text-sm text-gray-500">Distribution of transaction statuses</p>
            </div>
            <div class="p-6">
                <div class="space-y-4">
                    <!-- Successful -->
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="w-3 h-3 bg-green-500 rounded-full mr-3"></div>
                            <span class="text-sm font-medium text-gray-700">Successful</span>
                        </div>
                        <div class="flex items-center space-x-2">
                            <span class="text-sm text-gray-500">{{ $data['comprehensive_stats']['overview']['successful_transactions'] ?? 0 }}</span>
                            <span class="text-sm font-semibold text-gray-900">94.2%</span>
                        </div>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="bg-green-500 h-2 rounded-full" style="width: 94.2%"></div>
                    </div>

                    <!-- Failed -->
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="w-3 h-3 bg-red-500 rounded-full mr-3"></div>
                            <span class="text-sm font-medium text-gray-700">Failed</span>
                        </div>
                        <div class="flex items-center space-x-2">
                            <span class="text-sm text-gray-500">{{ $data['comprehensive_stats']['overview']['failed_transactions'] ?? 0 }}</span>
                            <span class="text-sm font-semibold text-gray-900">4.1%</span>
                        </div>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="bg-red-500 h-2 rounded-full" style="width: 4.1%"></div>
                    </div>

                    <!-- Pending -->
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="w-3 h-3 bg-yellow-500 rounded-full mr-3"></div>
                            <span class="text-sm font-medium text-gray-700">Pending</span>
                        </div>
                        <div class="flex items-center space-x-2">
                            <span class="text-sm text-gray-500">{{ $data['comprehensive_stats']['overview']['pending_transactions'] ?? 0 }}</span>
                            <span class="text-sm font-semibold text-gray-900">1.7%</span>
                        </div>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="bg-yellow-500 h-2 rounded-full" style="width: 1.7%"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Activity and Quick Actions -->
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3 mb-8">
        <!-- Recent Transactions -->
        <div class="lg:col-span-2 bg-white shadow-sm rounded-xl ring-1 ring-gray-900/5">
            <div class="p-6 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                        <i class="fas fa-clock mr-3 text-blue-500"></i>
                        Recent Transactions
                    </h3>
                    <a href="{{ route('merchant.transactions.index') }}" class="text-sm font-medium text-blue-600 hover:text-blue-500">
                        View all <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
            </div>
            <div class="overflow-hidden">
                @if($data['recent_transactions']->isNotEmpty())
                    <ul class="divide-y divide-gray-200">
                        @foreach($data['recent_transactions']->take(5) as $transaction)
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
                                                {{ $transaction->payment_id ?? $transaction->transaction_id }}
                                            </p>
                                            <p class="text-xs text-gray-500">
                                                {{ $transaction->shop->name ?? 'Unknown Shop' }} • {{ $transaction->created_at->diffForHumans() }}
                                            </p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-sm font-semibold text-gray-900">
                                            €{{ number_format(($transaction->tr_amount ?? $transaction->amount) / 100, 2) }}
                                        </p>
                                        <p class="text-xs text-gray-500 capitalize">
                                            {{ $transaction->status }}
                                        </p>
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <div class="p-6 text-center">
                        <i class="fas fa-credit-card text-4xl text-gray-300 mb-4"></i>
                        <p class="text-gray-500">No recent transactions</p>
                        <p class="text-xs text-gray-400 mt-2">Transactions will appear here once you start processing payments</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="bg-white shadow-sm rounded-xl ring-1 ring-gray-900/5">
            <div class="p-6 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                    <i class="fas fa-bolt mr-3 text-yellow-500"></i>
                    Quick Actions
                </h3>
            </div>
            <div class="p-6">
                <div class="space-y-4">
                    <!-- View All Transactions -->
                    <a href="{{ route('merchant.transactions.index') }}" class="flex items-center p-3 bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors duration-150 group">
                        <div class="flex-shrink-0">
                            <i class="fas fa-list text-blue-600"></i>
                        </div>
                        <div class="ml-3 flex-1">
                            <p class="text-sm font-medium text-blue-900">View All Transactions</p>
                            <p class="text-xs text-blue-600">Browse and filter transactions</p>
                        </div>
                        <i class="fas fa-chevron-right text-blue-600 group-hover:translate-x-1 transition-transform duration-150"></i>
                    </a>

                    <!-- Manage Shops -->
                    <a href="{{ route('merchant.shops.index') }}" class="flex items-center p-3 bg-green-50 rounded-lg hover:bg-green-100 transition-colors duration-150 group">
                        <div class="flex-shrink-0">
                            <i class="fas fa-store text-green-600"></i>
                        </div>
                        <div class="ml-3 flex-1">
                            <p class="text-sm font-medium text-green-900">Manage Shops</p>
                            <p class="text-xs text-green-600">Configure your shops</p>
                        </div>
                        <i class="fas fa-chevron-right text-green-600 group-hover:translate-x-1 transition-transform duration-150"></i>
                    </a>

                    <!-- Rolling Reserves -->
                    <a href="{{ route('merchant.rolling-reserves.index') }}" class="flex items-center p-3 bg-purple-50 rounded-lg hover:bg-purple-100 transition-colors duration-150 group">
                        <div class="flex-shrink-0">
                            <i class="fas fa-piggy-bank text-purple-600"></i>
                        </div>
                        <div class="ml-3 flex-1">
                            <p class="text-sm font-medium text-purple-900">Rolling Reserves</p>
                            <p class="text-xs text-purple-600">Monitor reserve status</p>
                        </div>
                        <i class="fas fa-chevron-right text-purple-600 group-hover:translate-x-1 transition-transform duration-150"></i>
                    </a>

                    <!-- API Documentation -->
                    <a href="#" class="flex items-center p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors duration-150 group">
                        <div class="flex-shrink-0">
                            <i class="fas fa-book text-gray-600"></i>
                        </div>
                        <div class="ml-3 flex-1">
                            <p class="text-sm font-medium text-gray-900">API Documentation</p>
                            <p class="text-xs text-gray-600">Integration guides</p>
                        </div>
                        <i class="fas fa-external-link-alt text-gray-600 group-hover:translate-x-1 transition-transform duration-150"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Shop Performance Summary -->
    @if($data['shops']->isNotEmpty())
        <div class="bg-white shadow-sm rounded-xl ring-1 ring-gray-900/5">
            <div class="p-6 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                    <i class="fas fa-store mr-3 text-green-500"></i>
                    Shop Performance
                </h3>
                <p class="mt-1 text-sm text-gray-500">Overview of your top performing shops</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Shop</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Transactions</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Volume</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Success Rate</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reserve</th>
                    </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($data['shops']->take(5) as $shop)
                        <tr class="hover:bg-gray-50 transition-colors duration-150">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="h-8 w-8 rounded-full bg-gradient-to-r from-blue-500 to-purple-600 flex items-center justify-center mr-3">
                                        <span class="text-white text-xs font-bold">{{ substr($shop->active, 0, 1) }}</span>
                                    </div>
                                    <div>
                                        <div class="text-sm font-medium text-gray-900">{{ $shop->owner_name }}</div>
                                        <div class="text-xs text-gray-500">ID: {{ $shop->id }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-{{ $shop->active === 'active' ? 'green' : 'red' }}-100 text-{{ $shop->active === 'active' ? 'green' : 'red' }}-800">
                                        {{ ucfirst($shop->active) }}
                                    </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ $shop->transactions_count ?? 0 }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                €{{ number_format($shop->total_volume ?? 0, 0) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ number_format($shop->success_rate ?? 0, 1) }}%
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                €{{ number_format($shop->rolling_reserve_amount ?? 0, 2) }}
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="px-6 py-3 bg-gray-50 border-t border-gray-200">
                <a href="{{ route('merchant.shops.index') }}" class="text-sm font-medium text-blue-600 hover:text-blue-500">
                    View all shops <i class="fas fa-arrow-right ml-1"></i>
                </a>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
    <script>
        // Auto-refresh dashboard data every 5 minutes
        setInterval(function() {
            // You can implement auto-refresh logic here if needed
            console.log('Dashboard auto-refresh check');
        }, 300000);

        // Add any dashboard-specific JavaScript here
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize tooltips, charts, etc.
            console.log('Dashboard loaded');
        });
    </script>
@endpush
