<div>
    <x-slot name="header">
        <h2 class="text-2xl font-bold text-gray-800">
            {{ __('Merchant Fee Management') }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl ">
            <!-- Notification Messages -->
            <div class="space-y-4">
                @if (session()->has('message'))
                    <div class="flex p-4 bg-green-50 rounded-lg border border-green-200"
                         x-data="{ show: true }"
                         x-show="show"
                         x-transition.duration.300ms>
                        <div class="shrink-0">
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

            <!-- Add New Merchant Fee Button -->
            <div class="mt-6 mb-4">
                <x-button wire:click="openCreateModal"
                          class="flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    {{ __('Add New Merchant Fee') }}
                </x-button>
            </div>

            <!-- Merchant Fees List -->
            <div class="bg-white rounded-lg shadow-sm">
                <!-- Mobile/Tablet View - Card Layout -->
                <div class="block lg:hidden">
                    @foreach($merchantFees as $fee)
                        <div class="border-b last:border-b-0 px-4 py-4 hover:bg-gray-50">
                            <div class="flex justify-between items-center mb-2">
                                <div>
                                    <p class="text-sm font-medium text-gray-900">{{ $fee->merchant->name }}</p>
                                    <p class="text-xs text-gray-500">{{ $fee->feeType->name }}</p>
                                </div>
                                <div class="flex space-x-2">
                                    <button wire:click="editMerchantFee({{ $fee->id }})"
                                            class="text-gray-600 hover:text-indigo-600">
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                    </button>
                                    <button wire:click="delete({{ $fee->id }})"
                                            wire:confirm="Are you sure you want to delete this merchant fee?"
                                            class="text-red-600 hover:text-red-800">
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-lienjoin="round" stroke-width="2"
                                                  d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                            <div class="mt-2 space-y-1">
                                <p class="text-xs text-gray-700">
                                    Amount: {{ number_format($fee->amount / 100, 2) }}
                                    ({{ $fee->feeType->is_percentage ? 'Percentage' : 'Fixed' }})
                                </p>
                                <p class="text-xs text-gray-700">
                                     <span
                                         class="{{ $fee->active ? 'text-green-800 bg-green-100' : 'text-red-800 bg-red-100' }} inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium">
                                        {{ $fee->active ? 'Active' : 'Inactive' }}
                                    </span>
                                </p>
                                <p class="text-xs text-gray-700">
                                    Effective: {{ $fee->effective_from->format('d-m-Y') }}
                                    @if($fee->effective_to)
                                        to {{ $fee->effective_to->format('d-m-Y') }}
                                    @else
                                        (Ongoing)
                                    @endif
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
                                Merchant
                            </th>
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
                        @foreach($merchantFees as $fee)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">
                                        {{ $fee->merchant->name }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-500">
                                        {{ $fee->feeType->name }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        {{ number_format($fee->amount / 100, 2) }}
                                        ({{ $fee->feeType->is_percentage ? 'Percentage' : 'Fixed' }})
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
                                        <button wire:click="editMerchantFee({{ $fee->id }})"
                                                class="text-gray-600 hover:text-indigo-600">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                      d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                            </svg>
                                        </button>
                                        <button wire:click="delete({{ $fee->id }})"
                                                wire:confirm="Are you sure you want to delete this merchant fee?"
                                                class="text-red-600 hover:text-red-800">
                                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-lienjoin="round" stroke-width="2"
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
                    {{ $merchantFees->links() }}
                </div>
            </div>

            <!-- Create/Edit Merchant Fee Modal -->
            <x-dialog-modal wire:model.live="showCreateModal" max-width="2xl">
                <x-slot name="title">
                    <div class="flex items-center">
                        <div class="mr-3 rounded-full bg-indigo-100 p-2">
                            <svg class="h-6 w-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="{{ $editMerchantFeeId ? 'M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z' : 'M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z' }}"/>
                            </svg>
                        </div>
                        <h3 class="text-lg font-medium text-gray-900">
                            {{ $editMerchantFeeId ? 'Edit Merchant Fee' : 'Create New Merchant Fee' }}
                        </h3>
                    </div>
                </x-slot>

                <x-slot name="content">
                    <div class="grid grid-cols-1 gap-6">
                        <div>
                            <x-label for="selectedMerchantId" value="{{ __('Merchant') }}"/>
                            <select wire:model="selectedMerchantId"
                                    id="selectedMerchantId"
                                    class="mt-1 block w-full border-gray-300 focus:border-indigo-300 focus:ring-3 focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-xs">
                                <option value="">Select a Merchant</option>
                                @foreach($merchants as $merchant)
                                    <option value="{{ $merchant->id }}">{{ $merchant->name }}</option>
                                @endforeach
                            </select>
                            <x-input-error for="selectedMerchantId" class="mt-2"/>
                        </div>

                        <div>
                            <x-label for="selectedFeeTypeId" value="{{ __('Fee Type') }}"/>
                            <select wire:model="selectedFeeTypeId"
                                    id="selectedFeeTypeId"
                                    class="mt-1 block w-full border-gray-300 focus:border-indigo-300 focus:ring-3 focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-xs">
                                <option value="">Select a Fee Type</option>
                                @foreach($feeTypes as $feeType)
                                    <option value="{{ $feeType->id }}">
                                        {{ $feeType->name }}
                                        ({{ $feeType->is_percentage ? 'Percentage' : 'Fixed' }})
                                    </option>
                                @endforeach
                            </select>
                            <x-input-error for="selectedFeeTypeId" class="mt-2"/>
                        </div>

                        <div>
                            <x-label for="amount" value="{{ __('Amount') }}"/>
                            <x-input id="amount"
                                     type="number"
                                     step="0.01"
                                     class="mt-1 block w-full"
                                     wire:model="amount"
                                     placeholder="Enter fee amount"/>
                            <x-input-error for="amount" class="mt-2"/>
                        </div>
                        <div>
                            <x-label for="active" value="{{ __('Active') }}"/>
                            <x-checkbox id="active"
                                        class="mt-1 block"
                                        wire:model="active"
                            />
                            <x-input-error for="active" class="mt-2"/>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <x-label for="effectiveFrom" value="{{ __('Effective From') }}"/>
                                <x-input id="effectiveFrom"
                                         type="date"
                                         class="mt-1 block w-full"
                                         wire:model="effectiveFrom"/>
                                <x-input-error for="effectiveFrom" class="mt-2"/>
                            </div>

                            <div>
                                <x-label for="effectiveTo" value="{{ __('Effective To (Optional)') }}"/>
                                <x-input id="effectiveTo"
                                         type="date"
                                         class="mt-1 block w-full"
                                         wire:model="effectiveTo"
                                         placeholder="Leave blank for ongoing"/>
                                <x-input-error for="effectiveTo" class="mt-2"/>
                            </div>
                        </div>
                    </div>
                </x-slot>

                <x-slot name="footer">
                    <div class="flex justify-end space-x-3">
                        <x-secondary-button wire:click="resetForm" wire:loading.attr="disabled">
                            {{ __('Cancel') }}
                        </x-secondary-button>

                        <x-button wire:click="{{ $editMerchantFeeId ? 'update' : 'create' }}"
                                  wire:loading.attr="disabled"
                                  class="bg-indigo-600 hover:bg-indigo-700">
                            <span wire:loading.remove>
                                {{ $editMerchantFeeId ? __('Save Changes') : __('Create Fee') }}
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
