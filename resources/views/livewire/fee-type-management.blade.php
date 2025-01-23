<div>
    <x-slot name="header">
        <h2 class="text-2xl font-bold text-gray-800">
            {{ __('Fee Type Management') }}
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
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div class="ml-3 flex-1">
                            <p class="text-sm font-medium text-green-800">{{ session('message') }}</p>
                        </div>
                        <button @click="show = false" class="ml-auto">
                            <svg class="h-5 w-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                @endif
            </div>

            <!-- Add New Fee Type Button -->
            <div class="mt-6 mb-4">
                <x-button wire:click="openCreateModal"
                          class="flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    {{ __('Add New Fee Type') }}
                </x-button>
            </div>

            <!-- Fee Types List -->
            <div class="bg-white rounded-lg shadow">
                <!-- Mobile/Tablet View - Card Layout -->
                <div class="block lg:hidden">
                    @foreach($feeTypes as $feeType)
                        <div class="border-b last:border-b-0 px-4 py-4 hover:bg-gray-50">
                            <div class="flex justify-between items-center mb-2">
                                <div>
                                    <p class="text-sm font-medium text-gray-900">{{ $feeType->name }}</p>
                                    <p class="text-xs text-gray-500">Key: {{ $feeType->key }}</p>
                                </div>
                                <div class="flex space-x-2">
                                    <button wire:click="editFeeType({{ $feeType->id }})"
                                            class="text-gray-600 hover:text-indigo-600">
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </button>
                                    @if(!$feeType->trashed())
                                        <button wire:click="delete({{ $feeType->id }})"
                                                wire:confirm="Are you sure you want to delete this fee type?"
                                                class="text-red-600 hover:text-red-800">
                                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    @endif
                                </div>
                            </div>

                            <div class="mt-2 space-y-1">
                                <p class="text-xs text-gray-700">
                                    Frequency: {{ ucfirst($feeType->frequency_type) }}
                                </p>
                                <p class="text-xs text-gray-700">
                                    Type: {{ $feeType->is_percentage ? 'Percentage' : 'Fixed Amount' }}
                                </p>
                            </div>
                        </div>
                    @endforeach
                </div>

                <!-- Desktop View - Traditional Table -->
                <div class="hidden lg:block overflow-x-auto max-h-[600px]">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Name
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Key
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Frequency
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Type
                            </th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Actions
                            </th>
                        </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($feeTypes as $feeType)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">
                                        {{ $feeType->name }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-500">
                                        {{ $feeType->key }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        {{ ucfirst($feeType->frequency_type) }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-500">
                                        {{ $feeType->is_percentage ? 'Percentage' : 'Fixed Amount' }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex justify-end gap-2">
                                        <button wire:click="editFeeType({{ $feeType->id }})"
                                                class="text-gray-600 hover:text-indigo-600">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                        </button>
                                        @if(!$feeType->trashed())
                                            <button wire:click="delete({{ $feeType->id }})"
                                                    wire:confirm="Are you sure you want to delete this fee type?"
                                                    class="text-red-600 hover:text-red-800">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="px-6 py-4 border-t border-gray-200">
                    {{ $feeTypes->links() }}
                </div>
            </div>

            <!-- Create/Edit Fee Type Modal -->
            <x-dialog-modal wire:model.live="showCreateModal" max-width="2xl">
                <x-slot name="title">
                    <div class="flex items-center">
                        <div class="mr-3 rounded-full bg-indigo-100 p-2">
                            <svg class="h-6 w-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="{{ $editFeeTypeId ? 'M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z' : 'M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z' }}" />
                            </svg>
                        </div>
                        <h3 class="text-lg font-medium text-gray-900">
                            {{ $editFeeTypeId ? 'Edit Fee Type' : 'Create New Fee Type' }}
                        </h3>
                    </div>
                </x-slot>

                <x-slot name="content">
                    <div class="grid grid-cols-1 gap-6">
                        <div>
                            <x-label for="name" value="{{ __('Fee Type Name') }}" />
                            <x-input id="name"
                                     type="text"
                                     class="mt-1 block w-full"
                                     wire:model="name"
                                     placeholder="Enter fee type name" />
                            <x-input-error for="name" class="mt-2" />
                        </div>

                        <div>
                            <x-label for="key" value="{{ __('Unique Key') }}" />
                            <x-input id="key"
                                     type="text"
                                     class="mt-1 block w-full"
                                     wire:model="key"
                                     placeholder="Enter a unique identifier" />
                            <x-input-error for="key" class="mt-2" />
                        </div>

                        <div>
                            <x-label for="frequency_type" value="{{ __('Frequency Type') }}" />
                            <select wire:model="frequency_type"
                                    id="frequency_type"
                                    class="mt-1 block w-full border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm">
                                <option value="transaction">Per Transaction</option>
                                <option value="daily">Daily</option>
                                <option value="weekly">Weekly</option>
                                <option value="monthly">Monthly</option>
                                <option value="yearly">Yearly</option>
                                <option value="one_time">One Time</option>
                            </select>
                            <x-input-error for="frequency_type" class="mt-2" />
                        </div>

                        <div>
                            <x-label for="is_percentage" value="{{ __('Fee Calculation Type') }}" />
                            <div class="mt-1 space-y-2">
                                <label class="inline-flex items-center">
                                    <input type="radio"
                                           wire:model="is_percentage"
                                           value="0"
                                           class="form-radio text-indigo-600"
                                    />
                                    <span class="ml-2">Fixed Amount</span>
                                </label>
                                <label class="inline-flex items-center ml-6">
                                    <input type="radio"
                                           wire:model="is_percentage"
                                           value="1"
                                           class="form-radio text-indigo-600"
                                    />
                                    <span class="ml-2">Percentage</span>
                                </label>
                            </div>
                            <x-input-error for="is_percentage" class="mt-2" />
                        </div>
                    </div>
                </x-slot>

                <x-slot name="footer">
                    <div class="flex justify-end space-x-3">
                        <x-secondary-button wire:click="resetForm" wire:loading.attr="disabled">
                            {{ __('Cancel') }}
                        </x-secondary-button>

                        <x-button wire:click="{{ $editFeeTypeId ? 'update' : 'create' }}"
                                  wire:loading.attr="disabled"
                                  class="bg-indigo-600 hover:bg-indigo-700">
                            <span wire:loading.remove>
                                {{ $editFeeTypeId ? __('Save Changes') : __('Create Fee Type') }}
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
