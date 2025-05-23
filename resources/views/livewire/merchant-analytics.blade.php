<div>
    <!-- Loading indicator at the top of the page -->
    <div wire:loading class="fixed top-0 left-0 right-0 bg-blue-600 h-1 z-50">
        <div class="w-24 h-full bg-blue-300 animate-pulse"></div>
    </div>

    @section('header')
        <h2 class="text-2xl font-bold text-gray-800">
            {{ __('Merchant Analytics') }}
        </h2>
    @endsection

    <x-slot name="header">
        <h2 class="text-2xl font-bold text-gray-800">
            {{ __('Merchant Analytics') }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4">
            <!-- Header with Merchant Info and Filters -->
            <div class="mb-6">
                <div class="bg-white p-4 rounded-lg shadow-sm mb-4">
                    <div class="flex flex-col md:flex-row justify-between items-start md:items-center">
                        <div>
                            <h1 class="text-xl font-semibold text-gray-800">{{ $merchant->name }}</h1>
                            <p class="text-sm text-gray-500">Account ID: {{ $merchant->account_id }}</p>
                        </div>
                        <div class="flex flex-col sm:flex-row gap-4 mt-4 md:mt-0">
                            @if($isLoading)
                                <div class="w-40">
                                    <div class="flex gap-2">
                                        <div class="w-6 h-6 rounded-full bg-gray-200 animate-pulse"></div>
                                        <div class="flex-1 space-y-2 py-1">
                                            <div class="h-2 rounded bg-gray-200 animate-pulse"></div>
                                            <div class="h-2 rounded bg-gray-200 w-3/4 animate-pulse"></div>
                                        </div>
                                    </div>
                                </div>
                            @endif
                            <select wire:model.live="period" wire:loading.attr="disabled" class="rounded-md border-gray-300 shadow-xs focus:border-indigo-300 focus:ring-3 focus:ring-indigo-200 focus:ring-opacity-50">
                                <option value="last7days">Last 7 Days</option>
                                <option value="last30days">Last 30 Days</option>
                                <option value="last90days">Last 90 Days</option>
                                <option value="thisyear">This Year</option>
                                <option value="lastyear">Last Year</option>
                                <option value="alltime">All Time</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                @if($isLoading)
                    <!-- Loading skeletons for Summary Cards -->
                    @for ($i = 0; $i < 4; $i++)
                        <div class="bg-white rounded-lg shadow-sm p-4">
                            <div class="space-y-3">
                                <div class="h-2 w-1/3 rounded bg-gray-200 animate-pulse"></div>
                                <div class="h-6 w-1/2 rounded bg-gray-200 animate-pulse"></div>
                                <div class="h-4 w-2/3 rounded bg-gray-200 animate-pulse"></div>
                            </div>
                        </div>
                    @endfor
                @else
                    <!-- Transactions Card -->
                    <div class="bg-white rounded-lg shadow-sm p-4">
                        <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wider mb-2">Transactions</h3>
                        <p class="text-2xl font-bold text-gray-800">{{ number_format($transactionMetrics['transaction_count']) }}</p>
                        <p class="text-lg text-gray-600">€{{ number_format($transactionMetrics['total_sales_eur'], 2) }}</p>
                    </div>

                    <!-- Fees Card -->
                    <div class="bg-white rounded-lg shadow-sm p-4">
                        <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wider mb-2">Fees</h3>
                        <p class="text-2xl font-bold text-gray-800">{{ number_format($feeMetrics['fee_count']) }}</p>
                        <p class="text-lg text-gray-600">€{{ number_format($feeMetrics['total_fees'], 2) }}</p>
                    </div>

                    <!-- Rolling Reserve Card -->
                    <div class="bg-white rounded-lg shadow-sm p-4">
                        <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wider mb-2">Rolling Reserve</h3>
                        <p class="text-2xl font-bold text-gray-800">{{ number_format($rollingReserveMetrics['pending_count']) }}</p>
                        <p class="text-lg text-gray-600">€{{ number_format($rollingReserveMetrics['total_reserved_eur'] ?? 0, 2) }}</p>
                    </div>

                    <!-- Chargebacks Card -->
                    <div class="bg-white rounded-lg shadow-sm p-4">
                        <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wider mb-2">Chargebacks</h3>
                        <p class="text-2xl font-bold text-gray-800">{{ number_format($chargebackMetrics['total_count']) }}</p>
                        <p class="text-lg text-gray-600">€{{ number_format($chargebackMetrics['total_amount_eur'], 2) }}</p>
                    </div>
                @endif
            </div>

            <!-- Lifetime Metrics -->
            <div class="bg-white rounded-lg shadow-sm overflow-hidden mb-6">
                @if($isLoading)
                    <div class="px-4 py-3 bg-gray-50 border-b border-gray-200">
                        <div class="h-6 w-1/4 rounded bg-gray-200 animate-pulse"></div>
                    </div>
                    <div class="p-4">
                        <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                            @for ($i = 0; $i < 5; $i++)
                                <div class="bg-gray-50 p-3 rounded-lg">
                                    <div class="h-2 w-1/2 rounded bg-gray-200 animate-pulse mb-1"></div>
                                    <div class="h-5 w-3/4 rounded bg-gray-200 animate-pulse"></div>
                                </div>
                            @endfor
                        </div>
                    </div>
                @else
                    <div class="px-4 py-3 bg-gray-50 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-800">Lifetime Statistics</h3>
                    </div>
                    <div class="p-4">
                        <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                            <div class="bg-gray-50 p-3 rounded-lg">
                                <span class="block text-xs font-medium text-gray-500 uppercase">Total Sales</span>
                                <span class="block text-lg font-bold">€{{ number_format($lifetimeMetrics['total_sales_eur'] ?? 0, 2) }}</span>
                            </div>

                            <div class="bg-gray-50 p-3 rounded-lg">
                                <span class="block text-xs font-medium text-gray-500 uppercase">Transactions</span>
                                <span class="block text-lg font-bold">{{ number_format($lifetimeMetrics['transaction_count'] ?? 0) }}</span>
                            </div>

                            <div class="bg-gray-50 p-3 rounded-lg">
                                <span class="block text-xs font-medium text-gray-500 uppercase">Total Fees</span>
                                <span class="block text-lg font-bold">€{{ number_format($lifetimeMetrics['fee_total'] ?? 0, 2) }}</span>
                            </div>

                            <div class="bg-gray-50 p-3 rounded-lg">
                                <span class="block text-xs font-medium text-gray-500 uppercase">Chargebacks</span>
                                <span class="block text-lg font-bold">{{ number_format($lifetimeMetrics['chargeback_count'] ?? 0) }}</span>
                            </div>

                            <div class="bg-gray-50 p-3 rounded-lg">
                                <span class="block text-xs font-medium text-gray-500 uppercase">Active Reserve</span>
                                <span class="block text-lg font-bold">€{{ number_format($lifetimeMetrics['reserve_amount_eur'] ?? 0, 2) }}</span>
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            <!-- Rolling Reserve Release Schedule - Upcoming Dates -->
            <div class="bg-white rounded-lg shadow-sm overflow-hidden mb-6">
                @if($isLoading)
                    <div class="px-4 py-3 bg-gray-50 border-b border-gray-200">
                        <div class="h-6 w-1/3 rounded bg-gray-200 animate-pulse"></div>
                    </div>
                    <div class="p-4 space-y-3">
                        <div class="h-8 bg-gray-100 rounded animate-pulse"></div>
                        <div class="h-8 bg-gray-100 rounded animate-pulse"></div>
                        <div class="h-8 bg-gray-100 rounded animate-pulse"></div>
                    </div>
                @else
                    <div class="px-4 py-3 bg-gray-50 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-800">Upcoming Reserve Releases</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Release Date</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Count</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Amount (EUR)</th>
                            </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($upcomingReleases ?? [] as $release)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $release['release_date']->format('d M Y') }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">{{ number_format($release['count']) }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">€{{ number_format($release['amount_eur'], 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">No scheduled releases</td>
                                </tr>
                            @endforelse

                            @if(!empty($upcomingReleases))
                                <tr class="bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">Total</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 text-right">{{ number_format(collect($upcomingReleases)->sum('count')) }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 text-right">€{{ number_format(collect($upcomingReleases)->sum('amount_eur'), 2) }}</td>
                                </tr>
                            @endif
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            <!-- Lazy-loading Tabs for Detailed Data -->
            <div x-data="{ activeTab: 'transactions' }" class="mb-6">
                <!-- Tab Navigation -->
                @if($isLoading)
                    <div class="bg-white rounded-lg shadow-sm p-4">
                        <div class="h-8 bg-gray-100 rounded animate-pulse"></div>
                    </div>
                @else
                    <div class="bg-white rounded-t-lg shadow-sm px-4 border-b border-gray-200">
                        <div class="flex overflow-x-auto -mb-px">
                            <button
                                @click="activeTab = 'transactions'"
                                :class="{ 'border-indigo-500 text-indigo-600': activeTab === 'transactions', 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': activeTab !== 'transactions' }"
                                class="py-4 px-1 font-medium text-sm border-b-2 whitespace-nowrap mr-8 focus:outline-hidden"
                            >
                                Transactions
                            </button>
                            <button
                                @click="activeTab = 'fees'"
                                :class="{ 'border-indigo-500 text-indigo-600': activeTab === 'fees', 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': activeTab !== 'fees' }"
                                class="py-4 px-1 font-medium text-sm border-b-2 whitespace-nowrap mr-8 focus:outline-hidden"
                            >
                                Fees
                            </button>
                            <button
                                @click="activeTab = 'reserves'"
                                :class="{ 'border-indigo-500 text-indigo-600': activeTab === 'reserves', 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': activeTab !== 'reserves' }"
                                class="py-4 px-1 font-medium text-sm border-b-2 whitespace-nowrap mr-8 focus:outline-hidden"
                            >
                                Reserves
                            </button>
                            <button
                                @click="activeTab = 'chargebacks'"
                                :class="{ 'border-indigo-500 text-indigo-600': activeTab === 'chargebacks', 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': activeTab !== 'chargebacks' }"
                                class="py-4 px-1 font-medium text-sm border-b-2 whitespace-nowrap mr-8 focus:outline-hidden"
                            >
                                Chargebacks
                            </button>
                            <button
                                @click="activeTab = 'trends'"
                                :class="{ 'border-indigo-500 text-indigo-600': activeTab === 'trends', 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': activeTab !== 'trends' }"
                                class="py-4 px-1 font-medium text-sm border-b-2 whitespace-nowrap focus:outline-hidden"
                            >
                                Trends
                            </button>
                        </div>
                    </div>

                    <!-- Tab Content -->
                    <div class="bg-white rounded-b-lg shadow-sm">
                        <!-- Transactions Tab -->
                        <div x-show="activeTab === 'transactions'">
                            <div class="px-4 py-3 bg-gray-50 border-b border-gray-200">
                                <h3 class="text-lg font-medium text-gray-800">Transaction Breakdown</h3>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Count</th>
                                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Amount (EUR)</th>
                                    </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">Successful Transactions</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">{{ number_format($transactionMetrics['transaction_count']) }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">€{{ number_format($transactionMetrics['total_sales_eur'], 2) }}</td>
                                    </tr>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">Declined Transactions</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">{{ number_format($transactionMetrics['declined_count']) }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">-</td>
                                    </tr>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">Refunds</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">{{ number_format($transactionMetrics['refund_count']) }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">-</td>
                                    </tr>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">Chargebacks</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">{{ number_format($chargebackMetrics['total_count']) }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">€{{ number_format($chargebackMetrics['total_amount_eur'], 2) }}</td>
                                    </tr>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">Payouts</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">{{ number_format($transactionMetrics['payout_count'] ?? 0) }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">-</td>
                                    </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Fees Tab -->
                        <div x-show="activeTab === 'fees'" x-cloak>
                            <div class="px-4 py-3 bg-gray-50 border-b border-gray-200">
                                <h3 class="text-lg font-medium text-gray-800">Fee Breakdown</h3>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fee Type</th>
                                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Count</th>
                                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Amount (EUR)</th>
                                    </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                    @forelse($feeMetrics['fees_by_type'] as $fee)
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $fee['name'] }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">{{ number_format($fee['count']) }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">€{{ number_format($fee['amount'], 2) }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="3" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">No fee data available for this period</td>
                                        </tr>
                                    @endforelse
                                    @if(!empty($feeMetrics['fees_by_type']))
                                        <tr class="bg-gray-50">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">Total</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 text-right">{{ number_format($feeMetrics['fee_count']) }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 text-right">€{{ number_format($feeMetrics['total_fees'], 2) }}</td>
                                        </tr>
                                    @endif
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Reserves Tab -->
                        <div x-show="activeTab === 'reserves'" x-cloak>
                            <div class="px-4 py-3 bg-gray-50 border-b border-gray-200">
                                <h3 class="text-lg font-medium text-gray-800">Rolling Reserve Status</h3>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Currency</th>
                                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Pending Amount</th>
                                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Amount (EUR)</th>
                                    </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                    @forelse($rollingReserveMetrics['pending_reserves'] ?? [] as $currency => $amount)
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $currency }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">{{ number_format($amount, 2) }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">€{{ number_format($rollingReserveMetrics['pending_reserves_eur'][$currency] ?? 0, 2) }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="3" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">No pending reserves</td>
                                        </tr>
                                    @endforelse
                                    @if(!empty($rollingReserveMetrics['pending_reserves']))
                                        <tr class="bg-gray-50">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">Total (EUR)</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 text-right">-</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 text-right">€{{ number_format($rollingReserveMetrics['total_reserved_eur'] ?? 0, 2) }}</td>
                                        </tr>
                                    @endif
                                    </tbody>
                                </table>
                            </div>

                            <div class="px-4 py-3 mt-6 bg-gray-50 border-b border-gray-200">
                                <h3 class="text-lg font-medium text-gray-800">Rolling Reserve by Month</h3>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Release Month</th>
                                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Count</th>
                                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Amount (EUR)</th>
                                    </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                    @forelse($rollingReserveMetrics['future_releases'] ?? [] as $release)
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $release['month'] }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">{{ number_format($release['count']) }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">€{{ number_format($release['amount_eur'], 2) }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="3" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">No scheduled releases</td>
                                        </tr>
                                    @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Chargebacks Tab -->
                        <div x-show="activeTab === 'chargebacks'" x-cloak>
                            <div class="px-4 py-3 bg-gray-50 border-b border-gray-200">
                                <h3 class="text-lg font-medium text-gray-800">Chargeback Status</h3>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Count</th>
                                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Amount (EUR)</th>
                                    </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                    @forelse($chargebackMetrics['by_status'] ?? [] as $status)
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ ucfirst(strtolower($status->status)) }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">{{ number_format($status->count) }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">€{{ number_format($status->amount_eur, 2) }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="3" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">No chargeback data</td>
                                        </tr>
                                    @endforelse
                                    @if(!empty($chargebackMetrics['by_status']))
                                        <tr class="bg-gray-50">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">Total</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 text-right">{{ number_format($chargebackMetrics['total_count']) }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 text-right">€{{ number_format($chargebackMetrics['total_amount_eur'], 2) }}</td>
                                        </tr>
                                    @endif
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Trends Tab -->
                        <div x-show="activeTab === 'trends'" x-cloak>
                            <!-- Fee Trends -->
                            <div class="border-b border-gray-200">
                                <div class="px-4 py-3 bg-gray-50 border-b border-gray-200">
                                    <h3 class="text-lg font-medium text-gray-800">Monthly Fee Trend</h3>
                                </div>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                        <tr>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Month</th>
                                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Count</th>
                                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Amount (EUR)</th>
                                        </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                        @forelse($feeMetrics['monthly_trend'] ?? [] as $month)
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $month['month'] }}</td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">{{ number_format($month['count']) }}</td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">€{{ number_format($month['amount'], 2) }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="3" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">No fee trend data available</td>
                                            </tr>
                                        @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Chargeback Trends -->
                            <div>
                                <div class="px-4 py-3 bg-gray-50 border-b border-gray-200">
                                    <h3 class="text-lg font-medium text-gray-800">Monthly Chargeback Trend</h3>
                                </div>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                        <tr>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Month</th>
                                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Count</th>
                                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Amount (EUR)</th>
                                        </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                        @forelse($chargebackMetrics['monthly_trend'] ?? [] as $month)
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $month['month'] }}</td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">{{ number_format($month['count']) }}</td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">€{{ number_format($month['amount_eur'], 2) }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="3" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">No chargeback trend data available</td>
                                            </tr>
                                        @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
