<div>
    @section('header')
        <h2 class="text-2xl font-bold text-gray-800">
            {{ __('Dashboard') }}
        </h2>
    @endsection

    <x-slot name="header">
        <h2 class="text-2xl font-bold text-gray-800">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>
    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="mt-6">
                <x-merchant-dashboard :merchant-id="$selectedMerchantId" />
            </div>
        </div>
    </div>
</div>
