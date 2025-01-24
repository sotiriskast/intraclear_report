<div>
    <x-slot name="header">
        <h2 class="text-2xl font-bold text-gray-800">
            {{ __('Fees for Merchant: ') . $merchant->name }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Notification Messages -->
            <div class="space-y-4">
                @if (session()->has('message'))
                    <div class="flex p-4 bg-green-50 rounded-lg border border-green-200">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div class="ml-3 flex-1">
                            <p class="text-sm font-medium text-green-800">{{ session('message') }}</p>
                        </div>
                    </div>
                @endif
            </div>

            <!-- Add New Merchant Fee Button -->
            <div class="mt-6 mb-4">
                <x-button wire:click="openCreateModal"
                          class="flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    {{ __('Add New Fee') }}
                </x-button>
            </div>

            <!-- Merchant Fees List -->
            <div class="bg-white rounded-lg shadow">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                            Fee Type
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                            Amount
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                            Effective Period
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Status
                        </th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">
                            Actions
                        </th>
                    </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($merchantFees as $fee)
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                {{ $fee->feeType->name }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                {{ $fee->amount }}
                                ({{ $fee->feeType->is_percentage ? 'Percentage' : 'Fixed' }})
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                {{ $fee->effective_from->format('d-m-Y') }}
                                @if($fee->effective_to)
                                    to {{ $fee->effective_to->format('d-m-Y') }}
                                @else
                                    (Ongoing)
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                    <span
                                        class="{{ $fee->active ? 'text-green-800 bg-green-100' : 'text-red-800 bg-red-100' }} inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium">
                                        {{ $fee->active ? 'Active' : 'Inactive' }}
                                    </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right">
                                <div class="flex justify-end space-x-2">
                                    <button wire:click="editMerchantFee({{ $fee->id }})"
                                            class="text-blue-600 hover:text-blue-800">
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </button>
                                    <button wire:click="delete({{ $fee->id }})"
                                            wire:confirm="Are you sure you want to delete this fee?"
                                            class="text-red-600 hover:text-red-800">
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>

                <!-- Pagination -->
                <div class="px-6 py-4 border-t border-gray-200">
                    {{ $merchantFees->links() }}
                </div>
            </div>

            <!-- Create/Edit Fee Modal -->
            <x-dialog-modal wire:model.live="showCreateModal" max-width="2xl">
                <x-slot name="title">
                    <div class="flex items-center">
                        <h3 class="text-lg font-medium text-gray-900">
                            {{ $editMerchantFeeId ? 'Edit Fee' : 'Add New Fee' }}
                        </h3>
                    </div>
                </x-slot>

                <x-slot name="content">
                    <div class="space-y-4">
                        <div>
                            <x-label for="selectedFeeTypeId">Fee Type</x-label>
                            <select wire:model="selectedFeeTypeId"
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                                <option value="">Select Fee Type</option>
                                @foreach($feeTypes as $feeType)
                                    <option value="{{ $feeType->id }}">
                                        {{ $feeType->name }}
                                        ({{ $feeType->is_percentage ? 'Percentage' : 'Fixed' }})
                                    </option>
                                @endforeach
                            </select>
                            <x-input-error for="selectedFeeTypeId" />
                        </div>

                        <div>
                            <x-label for="amount">Amount</x-label>
                            <x-input type="number"
                                     wire:model="amount"
                                     step="0.01"
                                     class="mt-1 block w-full"
                                     placeholder="Enter fee amount" />
                            <x-input-error for="amount" />
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
                                <x-label for="effectiveFrom">Effective From</x-label>
                                <x-input type="date"
                                         wire:model="effectiveFrom"
                                         class="mt-1 block w-full" />
                                <x-input-error for="effectiveFrom" />
                            </div>

                            <div>
                                <x-label for="effectiveTo">Effective To (Optional)</x-label>
                                <x-input type="date"
                                         wire:model="effectiveTo"
                                         class="mt-1 block w-full" />
                                <x-input-error for="effectiveTo" />
                            </div>
                        </div>
                    </div>
                </x-slot>

                <x-slot name="footer">
                    <div class="flex justify-end space-x-3">
                        <x-secondary-button wire:click="resetForm">
                            {{ __('Cancel') }}
                        </x-secondary-button>

                        <x-button wire:click="{{ $editMerchantFeeId ? 'update' : 'create' }}"
                                  class="bg-indigo-600 hover:bg-indigo-700">
                            {{ $editMerchantFeeId ? 'Update Fee' : 'Add Fee' }}
                        </x-button>
                    </div>
                </x-slot>
            </x-dialog-modal>
        </div>
    </div>
</div>
