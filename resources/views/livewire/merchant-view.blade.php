<div>
    @section('header')
        <h2 class="text-2xl font-bold text-gray-800">
            {{ __('Merchant Details') }}
        </h2>
    @endsection

    <x-slot name="header">
        <h2 class="text-2xl font-bold text-gray-800">
            {{ __('Merchant Details') }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4">
            <div class="bg-white shadow-sm overflow-hidden sm:rounded-lg">
                <div class="px-4 py-5 sm:px-6 flex justify-between items-center">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">
                        {{ $merchant->name }}
                    </h3>
                    <div class="flex gap-2">
                        <x-button wire:click="manageFees" wire:navigate class="bg-blue-600 hover:bg-blue-700">
                            {{ __('Manage Fees') }}
                        </x-button>
                        <x-button wire:click="viewAnalytics" wire:navigate class="bg-green-600 hover:bg-green-700">
                            {{ __('Analytics') }}
                        </x-button>
                        <!-- Add this button -->
                        @can('manage-merchants-api-keys')
                            <x-button wire:click="manageApi" wire:navigate class="bg-indigo-600 hover:bg-indigo-700">
                                {{ __('Manage API') }}
                            </x-button>
                        @endcan
                    </div>
                </div>
                <div class="border-t border-gray-200">
                    <dl>
                        <div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium text-gray-500">Merchant ID</dt>
                            <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                                {{ $merchant->account_id }}
                            </dd>
                        </div>
                        <div class="bg-white px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium text-gray-500">Email</dt>
                            <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                                {{ $merchant->email ?? 'N/A' }}
                            </dd>
                        </div>
                        <div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium text-gray-500">Phone</dt>
                            <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                                {{ $merchant->phone ?? 'N/A' }}
                            </dd>
                        </div>
                        <div class="bg-white px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium text-gray-500">Status</dt>
                            <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                                <span class="{{ $merchant->active ? 'text-green-800 bg-green-100' : 'text-red-800 bg-red-100' }} inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium">
                                    {{ $merchant->active ? 'Active' : 'Inactive' }}
                                </span>
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</div>
