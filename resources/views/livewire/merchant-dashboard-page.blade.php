<div>
    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-2xl font-bold text-gray-800">
                {{ __('Merchant Rolling Reserve Dashboard') }}
            </h2>

            <div class="mt-4">
                <label for="merchant-select" class="block text-sm font-medium text-gray-700">Select Merchant</label>
                <select id="merchant-select" wire:model.live="selectedMerchantId" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
                    @foreach($merchants as $merchant)
                        <option value="{{ $merchant->id }}">{{ $merchant->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="mt-6">
                <x-merchant-dashboard :merchant-id="$selectedMerchantId" />
            </div>
        </div>
    </div>
</div>
