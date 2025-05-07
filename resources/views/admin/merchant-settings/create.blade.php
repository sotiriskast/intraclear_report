<x-app-layout>
    <x-slot name="header">
        <h2 class="text-2xl font-bold text-gray-800">
            {{ __('Create New Merchant Settings') }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            @if (session('error'))
                <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-md relative" role="alert">
                    <span class="block sm:inline">{{ session('error') }}</span>
                </div>
            @endif

            <div class="bg-white rounded-lg shadow-sm ring-1 ring-gray-900/5 p-6">
                <form action="{{ route('admin.merchant-settings.store') }}" method="POST">
                    @csrf
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Merchant Selection -->
                        <div class="col-span-2">
                            <label for="merchant_id" class="block text-sm font-medium text-gray-700">
                                {{ __('Merchant') }}
                            </label>
                            <select name="merchant_id" id="merchant_id"
                                    class="mt-1 block w-full rounded-md border-gray-300 pe-10 ps-3 py-2 text-gray-900 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 sm:text-sm shadow-sm">
                                <option value="">Select a Merchant</option>
                                @foreach($merchants as $merchant)
                                    <option value="{{ $merchant->id }}" {{ old('merchant_id') == $merchant->id ? 'selected' : '' }}>
                                        {{ $merchant->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('merchant_id')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Rolling Reserve Settings -->
                        <div>
                            <label for="rolling_reserve_percentage" class="block text-sm font-medium text-gray-700">
                                {{ __('Rolling Reserve (%)') }}
                            </label>
                            <input id="rolling_reserve_percentage"
                                   name="rolling_reserve_percentage"
                                   type="number"
                                   step="0.5"
                                   value="{{ old('rolling_reserve_percentage', 10) }}"
                                   class="mt-1 block w-full rounded-md border-gray-300 px-3 py-2 text-gray-900 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 sm:text-sm shadow-sm"/>
                            @error('rolling_reserve_percentage')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="holding_period_days" class="block text-sm font-medium text-gray-700">
                                {{ __('Holding Period (Days)') }}
                            </label>
                            <input id="holding_period_days"
                                   name="holding_period_days"
                                   type="number"
                                   value="{{ old('holding_period_days', 180) }}"
                                   class="mt-1 block w-full rounded-md border-gray-300 px-3 py-2 text-gray-900 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 sm:text-sm shadow-sm"/>
                            @error('holding_period_days')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Fee Settings -->
                        <div>
                            <label for="mdr_percentage" class="block text-sm font-medium text-gray-700">
                                {{ __('MDR (%)') }}
                            </label>
                            <input id="mdr_percentage"
                                   name="mdr_percentage"
                                   type="number"
                                   step="0.05"
                                   value="{{ old('mdr_percentage', 5) }}"
                                   class="mt-1 block w-full rounded-md border-gray-300 px-3 py-2 text-gray-900 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 sm:text-sm shadow-sm"/>
                            @error('mdr_percentage')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="transaction_fee" class="block text-sm font-medium text-gray-700">
                                {{ __('Transaction Fee (EUR)') }}
                            </label>
                            <input id="transaction_fee"
                                   name="transaction_fee"
                                   type="number"
                                   step="0.01"
                                   value="{{ old('transaction_fee', 0.35) }}"
                                   class="mt-1 block w-full rounded-md border-gray-300 px-3 py-2 text-gray-900 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 sm:text-sm shadow-sm"/>
                            @error('transaction_fee')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="declined_fee" class="block text-sm font-medium text-gray-700">
                                {{ __('Declined Fee (EUR)') }}
                            </label>
                            <input id="declined_fee"
                                   name="declined_fee"
                                   type="number"
                                   step="0.01"
                                   value="{{ old('declined_fee', 0.25) }}"
                                   class="mt-1 block w-full rounded-md border-gray-300 px-3 py-2 text-gray-900 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 sm:text-sm shadow-sm"/>
                            @error('declined_fee')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Other Fees -->
                        <div>
                            <label for="payout_fee" class="block text-sm font-medium text-gray-700">
                                {{ __('Payout Fee (EUR)') }}
                            </label>
                            <input id="payout_fee"
                                   name="payout_fee"
                                   type="number"
                                   step="0.01"
                                   value="{{ old('payout_fee', 1) }}"
                                   class="mt-1 block w-full rounded-md border-gray-300 px-3 py-2 text-gray-900 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 sm:text-sm shadow-sm"/>
                            @error('payout_fee')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="refund_fee" class="block text-sm font-medium text-gray-700">
                                {{ __('Refund Fee (EUR)') }}
                            </label>
                            <input id="refund_fee"
                                   name="refund_fee"
                                   type="number"
                                   step="0.01"
                                   value="{{ old('refund_fee', 1.25) }}"
                                   class="mt-1 block w-full rounded-md border-gray-300 px-3 py-2 text-gray-900 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 sm:text-sm shadow-sm"/>
                            @error('refund_fee')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="chargeback_fee" class="block text-sm font-medium text-gray-700">
                                {{ __('Chargeback Fee (EUR)') }}
                            </label>
                            <input id="chargeback_fee"
                                   name="chargeback_fee"
                                   type="number"
                                   step="0.01"
                                   value="{{ old('chargeback_fee', 25) }}"
                                   class="mt-1 block w-full rounded-md border-gray-300 px-3 py-2 text-gray-900 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 sm:text-sm shadow-sm"/>
                            @error('chargeback_fee')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="monthly_fee" class="block text-sm font-medium text-gray-700">
                                {{ __('Monthly Fee (EUR)') }}
                            </label>
                            <input id="monthly_fee"
                                   name="monthly_fee"
                                   type="number"
                                   step="0.01"
                                   value="{{ old('monthly_fee', 150) }}"
                                   class="mt-1 block w-full rounded-md border-gray-300 px-3 py-2 text-gray-900 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 sm:text-sm shadow-sm"/>
                            @error('monthly_fee')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Risk Fees -->
                        <div>
                            <label for="mastercard_high_risk_fee" class="block text-sm font-medium text-gray-700">
                                {{ __('Mastercard High Risk Fee (EUR)') }}
                            </label>
                            <input id="mastercard_high_risk_fee"
                                   name="mastercard_high_risk_fee"
                                   type="number"
                                   step="0.01"
                                   value="{{ old('mastercard_high_risk_fee', 500) }}"
                                   class="mt-1 block w-full rounded-md border-gray-300 px-3 py-2 text-gray-900 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 sm:text-sm shadow-sm"/>
                            @error('mastercard_high_risk_fee')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                            <div>
                                <label for="visa_high_risk_fee" class="block text-sm font-medium text-gray-700">
                                    {{ __('Visa High Risk Fee (EUR)') }}
                                </label>
                                <input id="visa_high_risk_fee"
                                       name="visa_high_risk_fee"
                                       type="number"
                                       step="0.01"
                                       value="{{ old('visa_high_risk_fee', 450) }}"
                                       class="mt-1 block w-full rounded-md border-gray-300 px-3 py-2 text-gray-900 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 sm:text-sm shadow-sm"/>
                                @error('visa_high_risk_fee')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Setup Fee -->
                            <div>
                                <label for="setup_fee" class="block text-sm font-medium text-gray-700">
                                    {{ __('Setup Fee (EUR)') }}
                                </label>
                                <input id="setup_fee"
                                       name="setup_fee"
                                       type="number"
                                       step="0.01"
                                       value="{{ old('setup_fee', 500) }}"
                                       class="mt-1 block w-full rounded-md border-gray-300 px-3 py-2 text-gray-900 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 sm:text-sm shadow-sm"/>
                                @error('setup_fee')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Exchange Rate Markup -->
                            <div>
                                <label for="exchange_rate_markup" class="block text-sm font-medium text-gray-700">
                                    {{ __('Exchange Rate Markup') }}
                                </label>
                                <input id="exchange_rate_markup"
                                       name="exchange_rate_markup"
                                       type="number"
                                       step="0.001"
                                       value="{{ old('exchange_rate_markup', 1.01) }}"
                                       class="mt-1 block w-full rounded-md border-gray-300 px-3 py-2 text-gray-900 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 sm:text-sm shadow-sm"/>
                                <p class="mt-1 text-xs text-gray-500">Default: 1.01 (1% markup). Determines the exchange rate adjustment for non-EUR transactions.</p>
                                @error('exchange_rate_markup')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- FX Rate Markup -->
                            <div>
                                <label for="fx_rate_markup" class="block text-sm font-medium text-gray-700">
                                    {{ __('FX Rate Markup (%)') }}
                                </label>
                                <input id="fx_rate_markup"
                                       name="fx_rate_markup"
                                       type="number"
                                       step="0.01"
                                       value="{{ old('fx_rate_markup', 1) }}"
                                       class="mt-1 block w-full rounded-md border-gray-300 px-3 py-2 text-gray-900 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 sm:text-sm shadow-sm"/>
                                <p class="mt-1 text-xs text-gray-500">Percentage markup applied to currency exchange (e.g., 1.00 for 1%)</p>
                                @error('fx_rate_markup')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <div class="flex items-center">
                                    <input type="checkbox"
                                           id="setup_fee_charged"
                                           name="setup_fee_charged"
                                           value="1"
                                           {{ old('setup_fee_charged') ? 'checked' : '' }}
                                           class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                    <label for="setup_fee_charged" class="ms-2 text-sm text-gray-700">
                                        {{ __('Setup Fee Already Charged') }}
                                    </label>
                                </div>
                                @error('setup_fee_charged')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="flex justify-end gap-3 mt-6">
                            <a href="{{ route('admin.merchant-settings.index') }}"
                               wire:navigate
                               class="inline-flex items-center justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gray-500">
                                {{ __('Cancel') }}
                            </a>

                            <button type="submit"
                                    class="inline-flex items-center justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                                {{ __('Create Settings') }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
