<div class="p-6 bg-gray-50">
    <div class="max-w-7xl mx-auto">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900 mb-4">Merchant Financial Dashboard</h1>
            <div class="w-full md:w-1/3">
                <label for="merchant-select" class="block text-sm font-medium text-gray-700 mb-2">Select Merchant</label>
                <select
                    wire:model.live="selectedMerchantId"
                    id="merchant-select"
                    class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                >
                    @foreach ($merchants as $merchant)
                        <option value="{{ $merchant->id }}">
                            {{ $merchant->name }} ({{ $merchant->account_id }})
                        </option>
                    @endforeach
                </select>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <!-- Total Pending Reserves -->
            <div class="bg-white rounded-lg shadow-sm p-6 transition hover:shadow">
                <h3 class="text-lg font-semibold text-gray-700 mb-2">Pending Reserves</h3>
                <p class="text-3xl font-bold text-indigo-600">{{ $totalReserves['pending'] }}</p>
                <p class="text-sm text-gray-500">Total Entries</p>
            </div>

            <!-- Total Upcoming Releases -->
            <div class="bg-white rounded-lg shadow-sm p-6 transition hover:shadow">
                <h3 class="text-lg font-semibold text-gray-700 mb-2">Upcoming Releases</h3>
                <p class="text-3xl font-bold text-green-600">€{{ number_format($totalReserves['upcoming_releases'], 2) }}</p>
                <p class="text-sm text-gray-500">Total Amount</p>
            </div>

            <!-- Total Reserves -->
            <div class="bg-white rounded-lg shadow-sm p-6 transition hover:shadow">
                <h3 class="text-lg font-semibold text-gray-700 mb-2">Total Reserves</h3>
                <p class="text-3xl font-bold text-blue-600">€{{ number_format($totalReserves['total_eur'], 2) }}</p>
                <p class="text-sm text-gray-500">All Currencies (EUR)</p>
            </div>

            <!-- Total Fees -->
            <div class="bg-white rounded-lg shadow-sm p-6 transition hover:shadow">
                <h3 class="text-lg font-semibold text-gray-700 mb-2">Total Fees</h3>
                <p class="text-3xl font-bold text-purple-600">€{{ number_format($totalFees['total_amount_eur'], 2) }}</p>
                <p class="text-sm text-gray-500">Last 6 Months</p>
            </div>
        </div>

        <!-- Vue Charts Component -->
        <div
            id="vue-merchant-charts"
            wire:ignore
            data-fee-history="{{ $this->feeHistoryJson }}"
            data-rolling-reserves="{{ $this->rollingReserveJson }}"
        ></div>

        <!-- Detailed Information -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Rolling Reserves Details -->
            <div class="bg-white rounded-lg shadow-sm p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Merchant Rolling Reserves</h2>

                @if(!empty($rollingReserveSummary['pending_reserves']))
                    <div class="mb-4">
                        <h3 class="font-medium text-gray-700 mb-2">Pending Reserves</h3>
                        <div class="space-y-2">
                            @foreach($rollingReserveSummary['pending_reserves'] as $currency => $amount)
                                <div class="flex justify-between items-center py-2 border-b border-gray-100">
                                    <span class="font-medium">{{ $currency }}</span>
                                    <span class="text-indigo-600">€{{ number_format($amount, 2) }}</span>
                                </div>
                            @endforeach
                        </div>
                        <div class="mt-3 pt-3 border-t border-gray-200">
                            <div class="flex justify-between items-center">
                                <span class="font-semibold">Total EUR:</span>
                                <span class="font-semibold text-indigo-600">€{{ number_format($rollingReserveTotalEur, 2) }}</span>
                            </div>
                        </div>
                    </div>

                    @if(!empty($rollingReserveSummary['upcoming_releases']))
                        <div>
                            <h3 class="font-medium text-gray-700 mb-2">Upcoming Releases</h3>
                            <div class="space-y-2">
                                @foreach($rollingReserveSummary['upcoming_releases'] as $currency => $amount)
                                    <div class="flex justify-between items-center py-2 border-b border-gray-100">
                                        <span class="font-medium">{{ $currency }}</span>
                                        <span class="text-green-600">€{{ number_format($amount, 2) }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @else
                    <div class="text-gray-500 text-center py-8">
                        No rolling reserve data available for this merchant
                    </div>
                @endif
            </div>

            <!-- Fee History Details -->
            <div class="bg-white rounded-lg shadow-sm p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Fee History</h2>

                @if(count($feeHistorySummary) > 0)
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fee Type</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Amount (EUR)</th>
                            </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($feeHistorySummary as $fee)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ \Carbon\Carbon::parse($fee['applied_date'])->format('Y-m-d') }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ $fee['fee_type']['name'] }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-900">
                                        €{{ number_format($fee['fee_amount_eur'] / 100, 2) }}
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-gray-500 text-center py-8">
                        No fee history available for this merchant
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

@push('scripts')
    @vite('resources/js/merchant-charts.js')
@endpush
