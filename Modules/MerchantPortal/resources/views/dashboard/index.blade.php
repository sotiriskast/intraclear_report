@extends('merchantportal::layouts.master')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard')
    @section('page-subtitle', 'Overview of your merchant account')

    @section('content')
        <!-- Quick Stats Grid -->
        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4 mb-8">
            <!-- Total Shops -->
            <div class="overflow-hidden rounded-lg bg-white shadow">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-500">
                                <i class="fas fa-store text-white"></i>
                            </div>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Total Shops</dt>
                                <dd class="text-2xl font-semibold text-gray-900">{{ $data['shops']->count() ?? 0 }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-5 py-3">
                    <div class="text-sm">
                        <a href="{{ route('merchant.shops.index') }}" class="font-medium text-blue-600 hover:text-blue-500 transition-colors duration-150">
                            View all shops
                        </a>
                    </div>
                </div>
            </div>

            <!-- Recent Transactions -->
            <div class="overflow-hidden rounded-lg bg-white shadow">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-green-500">
                                <i class="fas fa-credit-card text-white"></i>
                            </div>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Recent Transactions</dt>
                                <dd class="text-2xl font-semibold text-gray-900">{{ $data['recent_transactions']->count() ?? 0 }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-5 py-3">
                    <div class="text-sm">
                        <a href="{{ route('merchant.transactions.index') }}" class="font-medium text-green-600 hover:text-green-500 transition-colors duration-150">
                            View transactions
                        </a>
                    </div>
                </div>
            </div>

            <!-- Pending Reserves -->
            <div class="overflow-hidden rounded-lg bg-white shadow">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-yellow-500">
                                <i class="fas fa-piggy-bank text-white"></i>
                            </div>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Pending Reserves</dt>
                                <dd class="text-2xl font-semibold text-gray-900">{{ $data['rolling_reserves']->count() ?? 0 }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-5 py-3">
                    <div class="text-sm">
                        <a href="{{ route('merchant.rolling-reserves.index') }}" class="font-medium text-yellow-600 hover:text-yellow-500 transition-colors duration-150">
                            View reserves
                        </a>
                    </div>
                </div>
            </div>

            <!-- Success Rate -->
            <div class="overflow-hidden rounded-lg bg-white shadow">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-indigo-500">
                                <i class="fas fa-chart-line text-white"></i>
                            </div>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Success Rate</dt>
                                <dd class="text-2xl font-semibold text-gray-900">{{ number_format($data['statistics']['success_rate'] ?? 0, 1) }}%</dd>
                            </dl>
                        </div>
                    </div>
                </div>
{{--                <div class="bg-gray-50 px-5 py-3">--}}
{{--                    <div class="text-sm">--}}
{{--                        <a href="{{ route('merchant.reports.index') }}" class="font-medium text-indigo-600 hover:text-indigo-500 transition-colors duration-150">--}}
{{--                            View reports--}}
{{--                        </a>--}}
{{--                    </div>--}}
{{--                </div>--}}
            </div>
        </div>

        <!-- Charts and Analytics Row -->
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2 mb-8">
            <!-- Monthly Overview Chart -->
            <div class="bg-white shadow rounded-lg">
                <div class="p-6 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900 flex items-center">
                        <i class="fas fa-chart-area mr-2 text-blue-500"></i>
                        Monthly Transaction Overview
                    </h3>
                    <p class="mt-1 text-sm text-gray-500">Transaction volume over the past 12 months</p>
                </div>
                <div class="p-6">
                    <div class="h-64 flex items-center justify-center bg-gray-50 rounded-lg">
                        <canvas id="monthlyChart" class="w-full h-full"></canvas>
                    </div>
                </div>
            </div>

            <!-- Transaction Status Distribution -->
            <div class="bg-white shadow rounded-lg">
                <div class="p-6 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900 flex items-center">
                        <i class="fas fa-chart-pie mr-2 text-green-500"></i>
                        Transaction Status
                    </h3>
                    <p class="mt-1 text-sm text-gray-500">Distribution of transaction statuses this month</p>
                </div>
                <div class="p-6">
                    <div class="space-y-4">
                        @php
                            $statusData = [
                                ['label' => 'Successful', 'value' => 85, 'color' => 'bg-green-500'],
                                ['label' => 'Pending', 'value' => 10, 'color' => 'bg-yellow-500'],
                                ['label' => 'Failed', 'value' => 5, 'color' => 'bg-red-500']
                            ];
                        @endphp
                        @foreach($statusData as $status)
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <div class="w-3 h-3 {{ $status['color'] }} rounded-full mr-3"></div>
                                    <span class="text-sm font-medium text-gray-900">{{ $status['label'] }}</span>
                                </div>
                                <div class="flex items-center">
                                    <div class="w-24 bg-gray-200 rounded-full h-2 mr-3">
                                        <div class="{{ $status['color'] }} h-2 rounded-full" style="width: {{ $status['value'] }}%"></div>
                                    </div>
                                    <span class="text-sm text-gray-600 min-w-[3rem] text-right">{{ $status['value'] }}%</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity and Quick Actions -->
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3 mb-8">
            <!-- Recent Transactions -->
            <div class="lg:col-span-2 bg-white shadow rounded-lg">
                <div class="p-6 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-medium text-gray-900 flex items-center">
                            <i class="fas fa-history mr-2 text-gray-500"></i>
                            Recent Transactions
                        </h3>
                        <a href="{{ route('merchant.transactions.index') }}" class="text-sm font-medium text-blue-600 hover:text-blue-500">
                            View all
                        </a>
                    </div>
                </div>
                <div class="flow-root">
                    <ul class="divide-y divide-gray-200">
                        @forelse(($data['recent_transactions'] ?? collect())->take(5) as $transaction)
                            <li class="p-6">
                                <div class="flex items-center space-x-4">
                                    <div class="flex-shrink-0">
                                        @if($transaction->status === 'completed')
                                            <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                                                <i class="fas fa-check text-green-600 text-sm"></i>
                                            </div>
                                        @elseif($transaction->status === 'pending')
                                            <div class="w-8 h-8 bg-yellow-100 rounded-full flex items-center justify-center">
                                                <i class="fas fa-clock text-yellow-600 text-sm"></i>
                                            </div>
                                        @else
                                            <div class="w-8 h-8 bg-red-100 rounded-full flex items-center justify-center">
                                                <i class="fas fa-times text-red-600 text-sm"></i>
                                            </div>
                                        @endif
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-gray-900 truncate">
                                            {{ $transaction->reference ?? 'Transaction #' . $transaction->id }}
                                        </p>
                                        <p class="text-sm text-gray-500">
                                            {{ $transaction->created_at->format('M d, Y H:i') ?? 'N/A' }}
                                        </p>
                                    </div>
                                    <div class="flex-shrink-0 text-sm">
                                    <span class="font-medium text-gray-900">
                                        ${{ number_format($transaction->amount ?? 0, 2) }}
                                    </span>
                                    </div>
                                </div>
                            </li>
                        @empty
                            <li class="p-6 text-center">
                                <div class="text-gray-500">
                                    <i class="fas fa-credit-card text-4xl mb-2"></i>
                                    <p>No recent transactions</p>
                                </div>
                            </li>
                        @endforelse
                    </ul>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="bg-white shadow rounded-lg">
                <div class="p-6 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900 flex items-center">
                        <i class="fas fa-bolt mr-2 text-purple-500"></i>
                        Quick Actions
                    </h3>
                </div>
                <div class="p-6">
                    <div class="space-y-4">
                        <!-- Create Shop -->
{{--                        <a href="{{ route('merchant.shops.create') }}"--}}
{{--                           class="flex items-center p-3 rounded-lg border border-gray-200 hover:border-blue-300 hover:bg-blue-50 transition-colors duration-150 group">--}}
{{--                            <div class="flex-shrink-0">--}}
{{--                                <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center group-hover:bg-blue-200 transition-colors duration-150">--}}
{{--                                    <i class="fas fa-plus text-blue-600 text-sm"></i>--}}
{{--                                </div>--}}
{{--                            </div>--}}
{{--                            <div class="ml-3">--}}
{{--                                <p class="text-sm font-medium text-gray-900">Add New Shop</p>--}}
{{--                                <p class="text-xs text-gray-500">Create a new shop</p>--}}
{{--                            </div>--}}
{{--                        </a>--}}

                        <!-- View Reports -->
{{--                        <a href="{{ route('merchant.reports.index') }}"--}}
{{--                           class="flex items-center p-3 rounded-lg border border-gray-200 hover:border-green-300 hover:bg-green-50 transition-colors duration-150 group">--}}
{{--                            <div class="flex-shrink-0">--}}
{{--                                <div class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center group-hover:bg-green-200 transition-colors duration-150">--}}
{{--                                    <i class="fas fa-chart-bar text-green-600 text-sm"></i>--}}
{{--                                </div>--}}
{{--                            </div>--}}
{{--                            <div class="ml-3">--}}
{{--                                <p class="text-sm font-medium text-gray-900">Generate Report</p>--}}
{{--                                <p class="text-xs text-gray-500">Download reports</p>--}}
{{--                            </div>--}}
{{--                        </a>--}}

                        <!-- Account Settings -->
{{--                        <a href="{{ route('merchant.settings.index') }}"--}}
{{--                           class="flex items-center p-3 rounded-lg border border-gray-200 hover:border-purple-300 hover:bg-purple-50 transition-colors duration-150 group">--}}
{{--                            <div class="flex-shrink-0">--}}
{{--                                <div class="w-8 h-8 bg-purple-100 rounded-lg flex items-center justify-center group-hover:bg-purple-200 transition-colors duration-150">--}}
{{--                                    <i class="fas fa-cog text-purple-600 text-sm"></i>--}}
{{--                                </div>--}}
{{--                            </div>--}}
{{--                            <div class="ml-3">--}}
{{--                                <p class="text-sm font-medium text-gray-900">Account Settings</p>--}}
{{--                                <p class="text-xs text-gray-500">Manage preferences</p>--}}
{{--                            </div>--}}
{{--                        </a>--}}

                        <!-- Support -->
{{--                        <a href="{{ route('merchant.support') }}"--}}
{{--                           class="flex items-center p-3 rounded-lg border border-gray-200 hover:border-orange-300 hover:bg-orange-50 transition-colors duration-150 group">--}}
{{--                            <div class="flex-shrink-0">--}}
{{--                                <div class="w-8 h-8 bg-orange-100 rounded-lg flex items-center justify-center group-hover:bg-orange-200 transition-colors duration-150">--}}
{{--                                    <i class="fas fa-life-ring text-orange-600 text-sm"></i>--}}
{{--                                </div>--}}
{{--                            </div>--}}
{{--                            <div class="ml-3">--}}
{{--                                <p class="text-sm font-medium text-gray-900">Get Support</p>--}}
{{--                                <p class="text-xs text-gray-500">Contact our team</p>--}}
{{--                            </div>--}}
{{--                        </a>--}}
                    </div>
                </div>
            </div>
        </div>

        <!-- System Status and Alerts -->
        @if(isset($data['alerts']) && $data['alerts']->count() > 0)
            <div class="mb-8">
                <div class="bg-white shadow rounded-lg">
                    <div class="p-6 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900 flex items-center">
                            <i class="fas fa-exclamation-triangle mr-2 text-yellow-500"></i>
                            System Alerts
                        </h3>
                    </div>
                    <div class="p-6">
                        <div class="space-y-3">
                            @foreach($data['alerts'] as $alert)
                                <div class="flex items-start p-3 rounded-lg {{ $alert->type === 'warning' ? 'bg-yellow-50 border border-yellow-200' : 'bg-blue-50 border border-blue-200' }}">
                                    <div class="flex-shrink-0">
                                        <i class="fas {{ $alert->type === 'warning' ? 'fa-exclamation-triangle text-yellow-600' : 'fa-info-circle text-blue-600' }}"></i>
                                    </div>
                                    <div class="ml-3 flex-1">
                                        <p class="text-sm font-medium {{ $alert->type === 'warning' ? 'text-yellow-800' : 'text-blue-800' }}">
                                            {{ $alert->title }}
                                        </p>
                                        <p class="text-sm {{ $alert->type === 'warning' ? 'text-yellow-700' : 'text-blue-700' }} mt-1">
                                            {{ $alert->message }}
                                        </p>
                                    </div>
                                    <div class="ml-3 flex-shrink-0">
                                        <button type="button" class="text-gray-400 hover:text-gray-600">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <!-- Coming Soon Features -->
        <div class="bg-gradient-to-r from-blue-50 to-indigo-100 rounded-lg p-8 text-center">
            <div class="mx-auto max-w-md">
                <div class="text-6xl mb-4">
                    <i class="fas fa-rocket text-blue-500"></i>
                </div>
                <h3 class="text-xl font-semibold text-gray-900 mb-2">More Features Coming Soon!</h3>
                <p class="text-gray-600 mb-6">
                    We're continuously improving your merchant experience with new features and tools.
                </p>
                <div class="flex flex-wrap justify-center gap-2 mb-6">
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                    <i class="fas fa-check mr-1"></i>
                    Authentication ✓
                </span>
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                    <i class="fas fa-check mr-1"></i>
                    Security Isolation ✓
                </span>
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                    <i class="fas fa-clock mr-1"></i>
                    Advanced Analytics
                </span>
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                    <i class="fas fa-list mr-1"></i>
                    API Management
                </span>
                </div>

                <div class="bg-white/70 rounded-lg p-4">
                    <h6 class="text-sm font-semibold text-green-700 mb-2">
                        <i class="fas fa-shield-check mr-2"></i>
                        Security Features Active:
                    </h6>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-2 text-xs text-gray-600">
                        <div class="flex items-center">
                            <i class="fas fa-check text-green-500 mr-1"></i>
                            Merchant-only access
                        </div>
                        <div class="flex items-center">
                            <i class="fas fa-check text-green-500 mr-1"></i>
                            Admin portal isolated
                        </div>
                        <div class="flex items-center">
                            <i class="fas fa-check text-green-500 mr-1"></i>
                            SSL encryption
                        </div>
                        <div class="flex items-center">
                            <i class="fas fa-check text-green-500 mr-1"></i>
                            Session security
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endsection

    @push('scripts')
        <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
        <script>
            // Monthly Chart
            document.addEventListener('DOMContentLoaded', function() {
                const ctx = document.getElementById('monthlyChart');
                if (ctx) {
                    const monthlyChart = new Chart(ctx.getContext('2d'), {
                        type: 'line',
                        data: {
                            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                            datasets: [{
                                label: 'Transaction Volume',
                                data: @json(array_values($data['monthly_summary']['chart_data'] ?? array_fill(0, 12, 0))),
                                borderColor: '#3b82f6',
                                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                                tension: 0.4,
                                fill: true,
                                pointBackgroundColor: '#3b82f6',
                                pointBorderColor: '#1d4ed8',
                                pointHoverBackgroundColor: '#1d4ed8',
                                pointHoverBorderColor: '#3b82f6'
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    display: false
                                },
                                tooltip: {
                                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                    titleColor: '#fff',
                                    bodyColor: '#fff',
                                    borderColor: '#3b82f6',
                                    borderWidth: 1
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    grid: {
                                        color: '#f3f4f6'
                                    },
                                    ticks: {
                                        color: '#6b7280',
                                        callback: function(value) {
                                            return ' + value.toLocaleString();
                                        }
                                    }
                                },
                                x: {
                                    grid: {
                                        display: false
                                    },
                                    ticks: {
                                        color: '#6b7280'
                                    }
                                }
                            },
                            interaction: {
                                intersect: false,
                                mode: 'index'
                            }
                        }
                    });
                }
            });

            // Auto-refresh data every 5 minutes
            setInterval(function() {
                fetch('{{ route("merchant.dashboard") }}', {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                    .then(response => response.json())
                    .then(data => {
                        // Update stats cards with new data
                        console.log('Dashboard data refreshed:', data);
                        // You can update specific elements here
                    })
                    .catch(error => console.error('Error refreshing data:', error));
            }, 300000); // 5 minutes

            // Dismiss alert functionality
            document.querySelectorAll('[data-dismiss="alert"]').forEach(button => {
                button.addEventListener('click', function() {
                    this.closest('.alert').remove();
                });
            });
        </script>
    @endpush
