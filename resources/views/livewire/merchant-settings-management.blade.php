<div>
    <x-slot name="header">
        <h2 class="text-2xl font-bold text-gray-800">
            {{ __('Merchant Settings Management') }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Notification Messages -->
            <div class="space-y-4">
                @if (session()->has('message'))
                    <div class="flex p-4 bg-green-50 rounded-lg border border-green-200"
                         x-data="{ show: true }"
                         x-show="show"
                         x-transition.duration.300ms>
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div class="ml-3 flex-1">
                            <p class="text-sm font-medium text-green-800">{{ session('message') }}</p>
                        </div>
                        <button @click="show = false" class="ml-auto">
                            <svg class="h-5 w-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                @endif

            </div>

            <!-- Add New Merchant Settings Button -->
            <div class="mt-6 mb-4">
                <x-button wire:click="openCreateModal"
                          class="flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    {{ __('Add New Merchant Settings') }}
                </x-button>
            </div>

            <!-- Merchant Settings List -->
            <div class="bg-white rounded-lg shadow">
                <!-- Mobile/Tablet View - Card Layout -->
                <div class="block lg:hidden">
                    @foreach($merchantSettings as $setting)
                        <div class="border-b last:border-b-0 px-4 py-4 hover:bg-gray-50">
                            <div class="flex justify-between items-center mb-2">
                                <div>
                                    <p class="text-sm font-medium text-gray-900">{{ $setting->merchant->name }}</p>
                                </div>
                                <div class="flex space-x-2">
                                    <button wire:click="editSetting({{ $setting->id }})"
                                            class="text-gray-600 hover:text-indigo-600">
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                    </button>
                                    <button wire:click="delete({{ $setting->id }})"
                                            wire:confirm="Are you sure you want to delete these settings?"
                                            class="text-red-600 hover:text-red-800">
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                            <div class="mt-2 space-y-1 text-xs text-gray-700">
                                <p>Rolling Reserve: {{ number_format($setting->rolling_reserve_percentage / 100, 2) }}
                                    %</p>
                                <p>Holding Period: {{ $setting->holding_period_days }} days</p>
                                <p>MDR: {{ number_format($setting->mdr_percentage / 100, 2) }}%</p>
                                <p>Transaction Fee: {{ number_format($setting->transaction_fee / 100, 2) }} EUR</p>
                                <p>Setup Fee: {{ number_format($setting->setup_fee / 100, 2) }} EUR</p>
                                <p>
                                    <span
                                        class="{{ $setting->active ? 'text-green-800 bg-green-100' : 'text-red-800 bg-red-100' }} inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium">
                                        {{ $setting->active ? 'Active' : 'Inactive' }}
                                    </span>
                                </p>
                            </div>
                        </div>
                    @endforeach
                </div>

                <!-- Desktop View - Traditional Table -->
                <div class="hidden lg:block overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Merchant
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Rolling Reserve
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                MDR
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
                        @foreach($merchantSettings as $setting)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">
                                        {{ $setting->merchant->name }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        {{ number_format($setting->rolling_reserve_percentage / 100, 2) }}%
                                        ({{ $setting->holding_period_days }} days)
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        {{ number_format($setting->mdr_percentage / 100, 2) }}%
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span
                                        class="{{ $setting->active ? 'text-green-800 bg-green-100' : 'text-red-800 bg-red-100' }} inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium">
                                        {{ $setting->active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex justify-end gap-2">
                                        <button wire:click="editSetting({{ $setting->id }})"
                                                class="text-gray-600 hover:text-indigo-600">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                      d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                            </svg>
                                        </button>
                                        <button wire:click="delete({{ $setting->id }})"
                                                wire:confirm="Are you sure you want to delete these settings?"
                                                class="text-red-600 hover:text-red-800">
                                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                      d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="px-6 py-4 border-t border-gray-200">
                    {{ $merchantSettings->links() }}
                </div>
            </div>

            <!-- Form Modal -->
            <x-dialog-modal wire:model.live="showCreateModal" max-width="2xl">
                <x-slot name="title">
                    <div class="flex items-center">
                        <div class="mr-3 rounded-full bg-indigo-100 p-2">
                            <svg class="h-6 w-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="{{ $editSettingId ? 'M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z' : 'M12 6v6m0 0v6m0-6h6m-6 0H6' }}"/>
                            </svg>
                        </div>
                        <h3 class="text-lg font-medium text-gray-900">
                            {{ $editSettingId ? 'Edit Merchant Settings' : 'Create New Merchant Settings' }}
                        </h3>
                    </div>
                    @if (session()->has('error'))
                        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                            <span class="block sm:inline">{{ session('error') }}</span>
                        </div>
                    @endif
                </x-slot>

                <x-slot name="content">
                    <!-- Form Content -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Merchant Selection -->
                        <div class="col-span-2">
                            <x-label for="selectedMerchantId" value="{{ __('Merchant') }}"/>
                            <select wire:model="selectedMerchantId"
                                    class="mt-1 block w-full border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm">
                                <option value="">Select a Merchant</option>
                                @foreach($merchants as $merchant)
                                    <option value="{{ $merchant->id }}">{{ $merchant->name }}</option>
                                @endforeach
                            </select>
                            <x-input-error for="selectedMerchantId" class="mt-2"/>
                        </div>

                        <!-- Rolling Reserve Settings -->
                        <div>
                            <x-label for="rollingReservePercentage" value="{{ __('Rolling Reserve (%)') }}"/>
                            <x-input id="rollingReservePercentage" type="number" step="0.5" class="mt-1 block w-full"
                                     wire:model="rollingReservePercentage"/>
                            <x-input-error for="rollingReservePercentage" class="mt-2"/>
                        </div>

                        <div>
                            <x-label for="holdingPeriodDays" value="{{ __('Holding Period (Days)') }}"/>
                            <x-input id="holdingPeriodDays" type="number" class="mt-1 block w-full"
                                     wire:model="holdingPeriodDays"/>
                            <x-input-error for="holdingPeriodDays" class="mt-2"/>
                        </div>

                        <!-- Fee Settings -->
                        <div>
                            <x-label for="mdrPercentage" value="{{ __('MDR (%)') }}"/>
                            <x-input id="mdrPercentage" type="number" step="0.05" class="mt-1 block w-full"
                                     wire:model="mdrPercentage"/>
                            <x-input-error for="mdrPercentage" class="mt-2"/>
                        </div>

                        <div>
                            <x-label for="transactionFee" value="{{ __('Transaction Fee (EUR)') }}"/>
                            <x-input id="transactionFee" type="number" step="0.01" class="mt-1 block w-full"
                                     wire:model="transactionFee"/>
                            <x-input-error for="transactionFee" class="mt-2"/>
                        </div>

                        <!-- Continue with other fee inputs... -->

                        <!-- Status -->
                        <div class="col-span-2">
                            <label class="flex items-center">
                                <x-checkbox wire:model="active"/>
                                <span class="ml-2 text-sm text-gray-600">{{ __('Active') }}</span>
                            </label>
                            <x-input-error for="active" class="mt-2"/>
                        </div>
                        <!-- Other Fees -->
                        <div>
                            <x-label for="payoutFee" value="{{ __('Payout Fee (EUR)') }}"/>
                            <x-input id="payoutFee" type="number" step="0.01" class="mt-1 block w-full"
                                     wire:model="payoutFee"/>
                            <x-input-error for="payoutFee" class="mt-2"/>
                        </div>

                        <div>
                            <x-label for="refundFee" value="{{ __('Refund Fee (EUR)') }}"/>
                            <x-input id="refundFee" type="number" step="0.01" class="mt-1 block w-full"
                                     wire:model="refundFee"/>
                            <x-input-error for="refundFee" class="mt-2"/>
                        </div>

                        <div>
                            <x-label for="chargebackFee" value="{{ __('Chargeback Fee (EUR)') }}"/>
                            <x-input id="chargebackFee" type="number" step="0.01" class="mt-1 block w-full"
                                     wire:model="chargebackFee"/>
                            <x-input-error for="chargebackFee" class="mt-2"/>
                        </div>

                        <div>
                            <x-label for="monthlyFee" value="{{ __('Monthly Fee (EUR)') }}"/>
                            <x-input id="monthlyFee" type="number" step="0.01" class="mt-1 block w-full"
                                     wire:model="monthlyFee"/>
                            <x-input-error for="monthlyFee" class="mt-2"/>
                        </div>

                        <!-- Risk Fees -->
                        <div>
                            <x-label for="mastercardHighRiskFee" value="{{ __('Mastercard High Risk Fee (EUR)') }}"/>
                            <x-input id="mastercardHighRiskFee" type="number" step="0.01" class="mt-1 block w-full"
                                     wire:model="mastercardHighRiskFee"/>
                            <x-input-error for="mastercardHighRiskFee" class="mt-2"/>
                        </div>

                        <div>
                            <x-label for="visaHighRiskFee" value="{{ __('Visa High Risk Fee (EUR)') }}"/>
                            <x-input id="visaHighRiskFee" type="number" step="0.01" class="mt-1 block w-full"
                                     wire:model="visaHighRiskFee"/>
                            <x-input-error for="visaHighRiskFee" class="mt-2"/>
                        </div>

                        <!-- Setup Fee -->
                        <div>
                            <x-label for="setupFee" value="{{ __('Setup Fee (EUR)') }}"/>
                            <x-input id="setupFee" type="number" step="0.01" class="mt-1 block w-full"
                                     wire:model="setupFee"/>
                            <x-input-error for="setupFee" class="mt-2"/>
                        </div>

                        <div>
                            <label class="flex items-center">
                                <x-checkbox wire:model="setupFeeCharged"/>
                                <span class="ml-2 text-sm text-gray-600">{{ __('Setup Fee Already Charged') }}</span>
                            </label>
                            <x-input-error for="setupFeeCharged" class="mt-2"/>
                        </div>
                    </div>
                </x-slot>

                <x-slot name="footer">
                    <div class="flex justify-end space-x-3">
                        <x-secondary-button wire:click="resetForm" wire:loading.attr="disabled">
                            {{ __('Cancel') }}
                        </x-secondary-button>

                        <x-button wire:click="{{ $editSettingId ? 'update' : 'create' }}"
                                  wire:loading.attr="disabled"
                                  class="bg-indigo-600 hover:bg-indigo-700">
                            <span wire:loading.remove>
                                {{ $editSettingId ? __('Save Changes') : __('Create Settings') }}
                            </span>
                            <span wire:loading>
                                {{ __('Processing...') }}
                            </span>
                        </x-button>
                    </div>
                </x-slot>
            </x-dialog-modal>
        </div>
    </div>
</div>
