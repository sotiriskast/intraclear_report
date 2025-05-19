<x-app-layout>
    <x-slot name="header">
        <h2 class="text-2xl font-bold text-gray-800">
            {{ __('Create New Shop Fee') }}
            @if($selectedShop)
                for {{ $selectedShop->shop_id }} ({{ $selectedShop->merchant->name }})
            @endif
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white rounded-lg shadow-sm ring-1 ring-gray-900/5 p-6">
                <div class="mb-4">
                    @if($selectedShop)
                        <a href="{{ route('admin.shops.settings', $selectedShop) }}"
                           class="inline-flex items-center text-indigo-600 hover:text-indigo-900">
                            <svg class="size-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                            </svg>
                            {{ __('Back') }}
                        </a>
                    @else
                        <a href="{{ route('admin.shop-fees.index') }}"
                           class="inline-flex items-center text-indigo-600 hover:text-indigo-900">

                            <svg class="size-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                            </svg>
                            {{ __('Back') }}
                        </a>
                    @endif
                </div>

                @if (session('error'))
                    <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-md relative"
                         role="alert">
                        <span class="block sm:inline">{{ session('error') }}</span>
                    </div>
                @endif

                <form action="{{ route('admin.shop-fees.store') }}" method="POST">
                    @csrf
                    <div class="grid grid-cols-1 gap-6">
                        <div>
                            <label for="shop_id" class="block text-sm font-medium text-gray-700">
                                {{ __('Shop') }}
                            </label>
                            <select name="shop_id" id="shop_id"
                                    class="mt-1 block w-full rounded-md border-gray-300 pe-10 ps-3 py-2 text-gray-900 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 sm:text-sm shadow-sm"
                                {{ $selectedShop ? 'disabled' : '' }}>
                                <option value="">Select a Shop</option>
                                @foreach($shops as $shop)
                                    <option
                                        value="{{ $shop->id }}" {{ old('shop_id', $selectedShopId) == $shop->id ? 'selected' : '' }}>
                                        Shop ID: {{ $shop->shop_id }} ({{ $shop->merchant->name }})
                                    </option>
                                @endforeach
                            </select>

                            <!-- Add a hidden field to ensure the shop_id is submitted even when the select is disabled -->
                            @if($selectedShop)
                                <input type="hidden" name="shop_id" value="{{ $selectedShopId }}">
                            @endif

                            @error('shop_id')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="fee_type_id" class="block text-sm font-medium text-gray-700">
                                {{ __('Fee Type') }}
                            </label>
                            <select name="fee_type_id" id="fee_type_id"
                                    class="mt-1 block w-full rounded-md border-gray-300 pe-10 ps-3 py-2 text-gray-900 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 sm:text-sm shadow-sm">
                                <option value="">Select a Fee Type</option>
                                @foreach($feeTypes as $feeType)
                                    <option
                                        value="{{ $feeType->id }}" {{ old('fee_type_id') == $feeType->id ? 'selected' : '' }}>
                                        {{ $feeType->name }}
                                        ({{ $feeType->is_percentage ? 'Percentage' : 'Fixed' }})
                                    </option>
                                @endforeach
                            </select>
                            @error('fee_type_id')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="amount" class="block text-sm font-medium text-gray-700">
                                {{ __('Amount') }}
                            </label>
                            <input id="amount"
                                   name="amount"
                                   type="number"
                                   step="0.01"
                                   value="{{ old('amount') }}"
                                   class="mt-1 block w-full rounded-md border-gray-300 px-3 py-2 text-gray-900 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 sm:text-sm shadow-sm"
                                   placeholder="Enter fee amount"/>
                            @error('amount')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="flex items-center">
                            <input type="checkbox"
                                   id="active"
                                   name="active"
                                   value="1"
                                   {{ old('active', '1') == '1' ? 'checked' : '' }}
                                   class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <label for="active" class="ms-2 text-sm text-gray-700">
                                {{ __('Active') }}
                            </label>
                            @error('active')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="effective_from" class="block text-sm font-medium text-gray-700">
                                    {{ __('Effective From') }}
                                </label>
                                <input id="effective_from"
                                       name="effective_from"
                                       type="date"
                                       value="{{ old('effective_from', now()->format('Y-m-d')) }}"
                                       class="mt-1 block w-full rounded-md border-gray-300 px-3 py-2 text-gray-900 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 sm:text-sm shadow-sm"/>
                                @error('effective_from')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="effective_to" class="block text-sm font-medium text-gray-700">
                                    {{ __('Effective To (Optional)') }}
                                </label>
                                <input id="effective_to"
                                       name="effective_to"
                                       type="date"
                                       value="{{ old('effective_to') }}"
                                       class="mt-1 block w-full rounded-md border-gray-300 px-3 py-2 text-gray-900 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 sm:text-sm shadow-sm"
                                       placeholder="Leave blank for ongoing"/>
                                @error('effective_to')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end mt-6">
                        @if($selectedShop)
                            <a href="{{ route('admin.shops.settings', $selectedShop) }}"
                               class="inline-flex items-center justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gray-500 mr-3">
                                {{ __('Cancel') }}
                            </a>
                        @else
                            <a href="{{ route('admin.shop-fees.index') }}"
                               class="inline-flex items-center justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gray-500 mr-3">
                                {{ __('Cancel') }}
                            </a>
                        @endif
                        <button type="submit"
                                class="inline-flex items-center justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                            {{ __('Create Fee') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
