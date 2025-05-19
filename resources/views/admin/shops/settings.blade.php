<x-app-layout>
    <x-slot name="header">
        <h2 class="text-2xl font-bold text-gray-800">
            {{ __('Shop Settings for ') }} {{ $shop->shop_id }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="mb-4">
                <a href="{{ route('admin.merchants.shops', $merchant) }}"
                   class="inline-flex items-center text-indigo-600 hover:text-indigo-900">
                    <svg class="size-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    {{ __('Back to Shops') }}
                </a>
            </div>

            <div class="bg-white rounded-lg shadow-sm ring-1 ring-gray-900/5">
                <div class="p-6 border-b border-gray-200">
                    <div class="flex justify-between items-center">
                        <h3 class="text-lg font-medium text-gray-900">Shop Information</h3>
                        <a href="{{ route('admin.shops.edit', $shop) }}"
                           class="inline-flex items-center text-sm font-medium text-indigo-600 hover:text-indigo-900">
                            <svg class="h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                            </svg>
                            Edit Shop Information
                        </a>
                    </div>

                    <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm font-medium text-gray-500">Shop ID</p>
                            <p class="mt-1 text-sm text-gray-900">{{ $shop->shop_id }}</p>
                        </div>

                        <div>
                            <p class="text-sm font-medium text-gray-500">Owner</p>
                            <p class="mt-1 text-sm text-gray-900">{{ $shop->owner_name ?? 'N/A' }}</p>
                        </div>

                        <div>
                            <p class="text-sm font-medium text-gray-500">Email</p>
                            <p class="mt-1 text-sm text-gray-900">{{ $shop->email ?? 'N/A' }}</p>
                        </div>

                        <div>
                            <p class="text-sm font-medium text-gray-500">Website</p>
                            <p class="mt-1 text-sm text-gray-900">
                                @if($shop->website)
                                    <a href="{{ $shop->website }}" target="_blank"
                                       class="text-indigo-600 hover:text-indigo-900">
                                        {{ $shop->website }}
                                    </a>
                                @else
                                    N/A
                                @endif
                            </p>
                        </div>

                        <div>
                            <p class="text-sm font-medium text-gray-500">Status</p>
                            <p class="mt-1">
                                <span
                                    class="{{ $shop->active ? 'text-green-800 bg-green-100' : 'text-red-800 bg-red-100' }} inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium">
                                    {{ $shop->active ? 'Active' : 'Inactive' }}
                                </span>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Shop Settings Section -->
                <div class="p-6 border-b border-gray-200">
                    <div class="flex justify-between items-center">
                        <h3 class="text-lg font-medium text-gray-900">Shop Settings</h3>
                        <a href="{{ route('admin.shop-settings.edit', $shop->settings ?? 0) }}"
                           class="inline-flex items-center text-sm font-medium text-indigo-600 hover:text-indigo-900">
                            <svg class="h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                            </svg>
                            {{ $shopSettings ? 'Edit Settings' : 'Create Settings' }}
                        </a>
                    </div>

                    @if($shopSettings)
                        <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <p class="text-sm font-medium text-gray-500">Rolling Reserve</p>
                                <p class="mt-1 text-sm text-gray-900">{{ number_format($shopSettings->rolling_reserve_percentage / 100, 2) }}
                                    %</p>
                            </div>

                            <div>
                                <p class="text-sm font-medium text-gray-500">Holding Period</p>
                                <p class="mt-1 text-sm text-gray-900">{{ $shopSettings->holding_period_days }} days</p>
                            </div>

                            <div>
                                <p class="text-sm font-medium text-gray-500">MDR Percentage</p>
                                <p class="mt-1 text-sm text-gray-900">{{ number_format($shopSettings->mdr_percentage / 100, 2) }}
                                    %</p>
                            </div>

                            <div>
                                <p class="text-sm font-medium text-gray-500">Transaction Fee</p>
                                <p class="mt-1 text-sm text-gray-900">
                                    €{{ number_format($shopSettings->transaction_fee / 100, 2) }}</p>
                            </div>

                            <div>
                                <p class="text-sm font-medium text-gray-500">Setup Fee Charged</p>
                                <p class="mt-1 text-sm text-gray-900">
                                    <span
                                        class="{{ $shopSettings->setup_fee_charged ? 'text-green-800 bg-green-100' : 'text-gray-800 bg-gray-100' }} inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium">
                                        {{ $shopSettings->setup_fee_charged ? 'Yes' : 'No' }}
                                    </span>
                                </p>
                            </div>

                            <div>
                                <p class="text-sm font-medium text-gray-500">Exchange Rate Markup</p>
                                <p class="mt-1 text-sm text-gray-900">{{ number_format(($shopSettings->exchange_rate_markup - 1) * 100,2) }}
                                    %</p>
                            </div>
                        </div>
                    @else
                        <div class="mt-4 flex items-center justify-center">
                            <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 flex-1">
                                <div class="flex">
                                    <div class="shrink-0">
                                        <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd"
                                                  d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                                                  clip-rule="evenodd"/>
                                        </svg>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm text-yellow-700">
                                            No settings have been defined for this shop yet. Click "Create Settings" to
                                            set up shop-specific configurations.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>

                <!-- Shop Fees Section -->
                <div class="p-6">
                    <div class="flex justify-between items-center">
                        <h3 class="text-lg font-medium text-gray-900">Shop Fees</h3>
                        <a href="{{ route('admin.shop-fees.create', ['shop_id' => $shop->id]) }}"
                           class="inline-flex items-center text-sm font-medium text-indigo-600 hover:text-indigo-900">
                            <svg class="h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M12 4v16m8-8H4"/>
                            </svg>
                            Add Custom Fee
                        </a>
                    </div>

                    @if($shop->fees && $shop->fees->count() > 0)
                        <div class="mt-4 overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Fee Type
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Amount
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Effective Period
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Status
                                    </th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Actions
                                    </th>
                                </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($shop->fees as $fee)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div
                                                class="text-sm font-medium text-gray-900">{{ $fee->feeType->name }}</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900">
                                                {{ number_format($fee->amount / 100, 2) }}
                                                {{ $fee->feeType->is_percentage ? '%' : ' EUR' }}
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-500">
                                                {{ $fee->effective_from->format('d-m-Y') }}
                                                @if($fee->effective_to)
                                                    to {{ $fee->effective_to->format('d-m-Y') }}
                                                @else
                                                    (Ongoing)
                                                @endif
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                                <span
                                                    class="{{ $fee->active ? 'text-green-800 bg-green-100' : 'text-red-800 bg-red-100' }} inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium">
                                                    {{ $fee->active ? 'Active' : 'Inactive' }}
                                                </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <div class="flex justify-end gap-2">
                                                <a href="{{ route('admin.shop-fees.edit', $fee) }}"
                                                   class="text-gray-600 hover:text-indigo-600">
                                                    <svg class="h-4 w-4" fill="none" stroke="currentColor"
                                                         viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                              stroke-width="2"
                                                              d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                    </svg>
                                                </a>
                                                <form action="{{ route('admin.shop-fees.destroy', $fee) }}"
                                                      method="POST" class="inline-block"
                                                      onsubmit="return confirm('Are you sure you want to delete this fee?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="text-red-600 hover:text-red-800">
                                                        <svg class="h-4 w-4" fill="none" stroke="currentColor"
                                                             viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                  stroke-width="2"
                                                                  d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                        </svg>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="mt-4 flex items-center justify-center">
                            <div class="bg-gray-50 border-l-4 border-gray-300 p-4 flex-1">
                                <div class="flex">
                                    <div class="shrink-0">
                                        <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor"
                                             viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm text-gray-600">
                                            No custom fees have been defined for this shop. Default merchant fees will
                                            be applied.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
