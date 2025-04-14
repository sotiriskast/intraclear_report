<div>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="text-2xl font-bold text-gray-800">
                {{ __('Merchants') }}
            </h2>

        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto">
            <div class="bg-white shadow-sm w-full max-w-md ml-auto my-4">
                <div class="relative">
                    <x-input
                        type="text"
                        wire:model.live.debounce.300ms="search"
                        placeholder="Search merchants"
                        class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md leading-5
                        bg-white placeholder-gray-500 focus:outline-hidden focus:placeholder-gray-400
                        focus:border-blue-300 focus:ring-3 focus:ring-blue-200 focus:ring-opacity-50"
                    />
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                </div>
            </div>
            <!-- Merchants List -->
            <div class="bg-white rounded-lg shadow-sm">
                <!-- Mobile/Tablet View - Card Layout -->
                <div class="block lg:hidden">
                    @forelse($merchants as $merchant)
                        <div class="border-b last:border-b-0 px-4 py-4 hover:bg-gray-50">
                            <div class="flex justify-between items-center mb-2">
                                <div>
                                    <p class="text-sm font-medium text-gray-900">{{ $merchant->name }}</p>
                                    <p class="text-xs text-gray-500">ID: {{ $merchant->account_id }}</p>
                                </div>
                                <div class="flex space-x-2">
                                    <a href="{{ route('merchant.view', $merchant->id) }}"
                                       wire:navigate
                                       class="text-blue-600 hover:text-blue-800">
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                    </a>
                                </div>
                            </div>

                            <div class="mt-2 space-y-1">
                                <p class="text-xs text-gray-700">
                                    Email: {{ $merchant->email ?? 'N/A' }}
                                </p>
                                <p class="text-xs text-gray-700">
                                    Phone: {{ $merchant->phone ?? 'N/A' }}
                                </p>
                                <p class="text-xs text-gray-700">
                                    Status:
                                    <span class="{{ $merchant->active ? 'text-green-800' : 'text-red-800' }}">
                                        {{ $merchant->active ? 'Active' : 'Inactive' }}
                                    </span>
                                </p>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-4 text-gray-500">
                            No merchants found.
                        </div>
                    @endforelse
                </div>

                <!-- Desktop View - Traditional Table -->
                <div class="hidden lg:block overflow-x-auto max-h-[600px]">
                    <table class="min-w-full divide-y divide-gray-200 ">
                        <thead class="bg-gray-50  sticky top-0 z-10">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Name
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Merchant ID
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Email
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
                        @forelse($merchants as $merchant)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">
                                        {{ $merchant->name }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-500">
                                        {{ $merchant->account_id }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        {{ $merchant->email ?? 'N/A' }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="{{ $merchant->active ? 'text-green-800 bg-green-100' : 'text-red-800 bg-red-100' }} inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium">
                                        {{ $merchant->active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex justify-end gap-2">
                                        <a href="{{ route('merchant.view', $merchant->id) }}"
                                           wire:navigate
                                           class="text-blue-600 hover:text-blue-800">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-4 text-gray-500">
                                    No merchants found.
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="px-6 py-4 border-t border-gray-200">
                    {{ $merchants->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
