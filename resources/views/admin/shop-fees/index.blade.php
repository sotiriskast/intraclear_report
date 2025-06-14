<x-app-layout>
    <x-slot name="header">
        <h2 class="text-2xl font-bold text-gray-800">
            {{ __('Shop Fee Management') }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Notification Messages -->
            <div class="space-y-4">
                @if (session('message'))
                    <div class="flex p-4 bg-green-50 rounded-lg border border-green-200"
                         x-data="{ show: true }"
                         x-show="show"
                         x-transition.duration.300ms>
                        <div class="shrink-0">
                            <svg class="size-5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div class="ms-3 flex-1">
                            <p class="text-sm font-medium text-green-800">{{ session('message') }}</p>
                        </div>
                        <button @click="show = false" class="ms-auto">
                            <svg class="size-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                @endif
            </div>

            <!-- Add New Shop Fee Button -->
            <div class="mt-6 mb-4">
                <a href="{{ route('admin.shop-fees.create') }}"
                   wire:navigate
                   class="inline-flex items-center gap-2 rounded-md bg-indigo-600 px-3.5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                    <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    {{ __('Add New Shop Fee') }}
                </a>
            </div>

            <!-- Shop Fees List -->
            <div class="bg-white rounded-lg shadow-sm ring-1 ring-gray-900/5">
                <!-- Mobile/Tablet View - Card Layout -->
                <div class="block lg:hidden">
                    @foreach($shopFees as $fee)
                        <div class="border-b last:border-b-0 px-4 py-4 hover:bg-gray-50">
                            <div class="flex justify-between items-center mb-2">
                                <div>
                                    <p class="text-sm font-medium text-gray-900">{{ $fee->shop->website ?? 'Shop ID: ' . $fee->shop->shop_id }}</p>
                                    <p class="text-xs text-gray-500">{{ $fee->feeType->name }} | {{ $fee->shop->merchant->name }}</p>
                                </div>
                                <div class="flex gap-2">
                                    <a href="{{ route('admin.shop-fees.edit', $fee) }}"
                                       class="text-gray-600 hover:text-indigo-600">
                                        <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                    </a>
                                    <form action="{{ route('admin.shop-fees.destroy', $fee) }}" method="POST"
                                          class="inline-block"
                                          onsubmit="return confirm('Are you sure you want to delete this shop fee?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:text-red-800">
                                            <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                      d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                        </button>
                                    </form>
                                </div>
                            </div>
                            <div class="mt-2 space-y-1">
                                <p class="text-xs text-gray-700">
                                    Amount: {{ number_format($fee->amount / 100, 2) }}
                                    ({{ $fee->feeType->is_percentage ? 'Percentage' : 'Fixed' }})
                                </p>
                                <p class="text-xs text-gray-700">
                                     <span
                                         class="{{ $fee->active ? 'text-green-700 bg-green-100' : 'text-red-700 bg-red-100' }} inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium">
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
                            <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Shop / Merchant
                            </th>
                            <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Fee Type
                            </th>
                            <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Amount
                            </th>
                            <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Effective Period
                            </th>
                            <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Status
                            </th>
                            <th scope="col" class="px-6 py-3 text-end text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Actions
                            </th>
                        </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($shopFees as $fee)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">
                                        {{ $fee->shop->website ?? 'Shop ID: ' . $fee->shop->shop_id }}
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        {{ $fee->shop->merchant->name }}
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
                                        class="{{ $fee->active ? 'text-green-700 bg-green-100' : 'text-red-700 bg-red-100' }} inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium">
                                        {{ $fee->active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-end text-sm font-medium">
                                    <div class="flex justify-end gap-2">
                                        <a href="{{ route('admin.shop-fees.edit', $fee) }}"
                                           class="text-gray-600 hover:text-indigo-600">
                                            <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                      d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                            </svg>
                                        </a>
                                        <form action="{{ route('admin.shop-fees.destroy', $fee) }}" method="POST"
                                              class="inline-block"
                                              onsubmit="return confirm('Are you sure you want to delete this shop fee?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:text-red-800">
                                                <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                          d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                </svg>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="px-6 py-4 border-t border-gray-200">
                    {{ $shopFees->links() }}
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
