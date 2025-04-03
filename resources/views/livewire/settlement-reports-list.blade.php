<div>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="text-2xl font-bold text-gray-800">
                {{ __('Settlement Reports') }}
            </h2>

        </div>
    </x-slot>

    <div class="py-6">

        <div class="max-w-7xl mx-auto">
            <div class="space-x-4">
                <a href="{{ route('settlements.archives') }}"
                   class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-white hover:bg-gray-700">
                    View Archives
                </a>
                <a href="{{ route('settlements.generate-form') }}"
                   class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-white hover:bg-blue-700">
                    Generate New Report
                </a>
            </div>
            <!-- Search -->
            <div class="bg-white shadow w-full max-w-md ml-auto my-4">
                <div class="relative">
                    <x-input
                        type="text"
                        wire:model.live.debounce.300ms="search"
                        placeholder="Search reports..."
                        class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md leading-5
                        bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400
                        focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50"
                    />
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Reports List -->
            <div class="bg-white rounded-lg shadow">
                <!-- Mobile/Tablet View - Card Layout -->
                <div class="block lg:hidden">
                    @forelse($reports as $report)
                        <div class="border-b last:border-b-0 px-4 py-4 hover:bg-gray-50">
                            <div class="flex justify-between items-center mb-2">
                                <div>
                                    <p class="text-sm font-medium text-gray-900">{{ $report->merchant_name }}</p>
                                    <p class="text-xs text-gray-500">ID: {{ $report->account_id }}</p>
                                </div>
                                <div>
                                    @if(Storage::exists($report->report_path))
                                        <a href="{{ route('settlements.download', $report->id) }}"
                                           class="text-blue-600 hover:text-blue-800">
                                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                            </svg>
                                        </a>
                                    @else
                                        <span class="text-red-600 text-sm">File not found</span>
                                    @endif
                                </div>
                            </div>

                            <div class="mt-2 space-y-1">
                                <p class="text-xs text-gray-700">
                                    Period: {{ \Carbon\Carbon::parse($report->start_date)->format('d/m/Y') }}
                                    to {{ \Carbon\Carbon::parse($report->end_date)->format('d/m/Y') }}
                                </p>
                                <p class="text-xs text-gray-700">
                                    Generated: {{ \Carbon\Carbon::parse($report->created_at)->format('d/m/Y H:i:s') }}
                                </p>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-4 text-gray-500">
                            No reports found.
                        </div>
                    @endforelse
                </div>

                <!-- Desktop View - Traditional Table -->
                <div class="hidden lg:block overflow-x-auto max-h-[600px]">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50 sticky top-0 z-10">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Merchant
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Account ID
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Period
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Generated At
                            </th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Actions
                            </th>
                        </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($reports as $report)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">
                                        {{ $report->merchant_name }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-500">
                                        {{ $report->account_id }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        {{ \Carbon\Carbon::parse($report->start_date)->format('d/m/Y') }}
                                        to
                                        {{ \Carbon\Carbon::parse($report->end_date)->format('d/m/Y') }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        {{ \Carbon\Carbon::parse($report->created_at)->format('d/m/Y H:i:s') }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    @if(Storage::exists($report->report_path))
                                        <a href="{{ route('settlements.download', $report->id) }}"
                                           class="text-blue-600 hover:text-blue-800">
                                            <svg class="h-5 w-5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                            </svg>
                                        </a>
                                    @else
                                        <span class="text-red-600">File not found</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-4 text-gray-500">
                                    No reports found.
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="px-6 py-4 border-t border-gray-200">
                    {{ $reports->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
