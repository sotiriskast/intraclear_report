<x-app-layout>
    <x-slot name="header">
        <h2 class="text-2xl font-bold text-gray-800">
            {{ __('Edit Fee Type') }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white rounded-lg shadow-sm ring-1 ring-gray-900/5 p-6">
                <div class="mb-4">
                    <a href="{{ route('admin.fee-types.index') }}" class="inline-flex items-center text-indigo-600 hover:text-indigo-900">
                        <svg class="size-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                        {{ __('Back to Fee Types') }}
                    </a>
                </div>

                @if (session('error'))
                    <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-md relative" role="alert">
                        <span class="block sm:inline">{{ session('error') }}</span>
                    </div>
                @endif

                <form action="{{ route('admin.fee-types.update', $feeType) }}" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="grid grid-cols-1 gap-6">
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700">
                                {{ __('Fee Type Name') }}
                            </label>
                            <input id="name"
                                   name="name"
                                   type="text"
                                   value="{{ old('name', $feeType->name) }}"
                                   class="mt-1 block w-full rounded-md border-gray-300 px-3 py-2 text-gray-900 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 sm:text-sm shadow-sm"
                                   placeholder="Enter fee type name"
                                   required />
                            @error('name')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="key" class="block text-sm font-medium text-gray-700">
                                {{ __('Unique Key') }}
                            </label>
                            <input id="key"
                                   name="key"
                                   type="text"
                                   value="{{ old('key', $feeType->key) }}"
                                   class="mt-1 block w-full rounded-md border-gray-300 px-3 py-2 text-gray-900 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 sm:text-sm shadow-sm"
                                   placeholder="Enter a unique identifier"
                                   required />
                            @error('key')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="frequency_type" class="block text-sm font-medium text-gray-700">
                                {{ __('Frequency Type') }}
                            </label>
                            <select name="frequency_type"
                                    id="frequency_type"
                                    class="mt-1 block w-full rounded-md border-gray-300 px-3 py-2 text-gray-900 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 sm:text-sm shadow-sm">
                                <option value="transaction" {{ old('frequency_type', $feeType->frequency_type) == 'transaction' ? 'selected' : '' }}>Per Transaction</option>
                                <option value="daily" {{ old('frequency_type', $feeType->frequency_type) == 'daily' ? 'selected' : '' }}>Daily</option>
                                <option value="weekly" {{ old('frequency_type', $feeType->frequency_type) == 'weekly' ? 'selected' : '' }}>Weekly</option>
                                <option value="monthly" {{ old('frequency_type', $feeType->frequency_type) == 'monthly' ? 'selected' : '' }}>Monthly</option>
                                <option value="yearly" {{ old('frequency_type', $feeType->frequency_type) == 'yearly' ? 'selected' : '' }}>Yearly</option>
                                <option value="one_time" {{ old('frequency_type', $feeType->frequency_type) == 'one_time' ? 'selected' : '' }}>One Time</option>
                            </select>
                            @error('frequency_type')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">
                                {{ __('Fee Calculation Type') }}
                            </label>
                            <div class="mt-1 space-y-2">
                                <label class="inline-flex items-center">
                                    <input type="radio"
                                           name="is_percentage"
                                           value="0"
                                           {{ old('is_percentage', $feeType->is_percentage ? '1' : '0') == '0' ? 'checked' : '' }}
                                           class="h-4 w-4 text-indigo-600 border-gray-300 focus:ring-indigo-500" />
                                    <span class="ml-2">Fixed Amount</span>
                                </label>
                                <label class="inline-flex items-center ml-6">
                                    <input type="radio"
                                           name="is_percentage"
                                           value="1"
                                           {{ old('is_percentage', $feeType->is_percentage ? '1' : '0') == '1' ? 'checked' : '' }}
                                           class="h-4 w-4 text-indigo-600 border-gray-300 focus:ring-indigo-500" />
                                    <span class="ml-2">Percentage</span>
                                </label>
                            </div>
                            @error('is_percentage')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="flex justify-end mt-6">
                        <a href="{{ route('admin.fee-types.index') }}"
                           wire:navigate
                           class="inline-flex items-center justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gray-500 mr-3">
                            {{ __('Cancel') }}
                        </a>
                        <button type="submit"
                                class="inline-flex items-center justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                            {{ __('Save Changes') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
