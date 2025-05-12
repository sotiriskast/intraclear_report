<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('CESOP Reports') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                <div class="p-6">
                    @if (session('success'))
                        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                            <span class="block sm:inline">{{ session('success') }}</span>
                        </div>
                    @endif

                    @if (session('error'))
                        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                            <span class="block sm:inline">{{ session('error') }}</span>
                        </div>
                    @endif

                    <div class="mb-6 flex justify-between items-center">
                        <h3 class="text-lg font-semibold text-gray-700">Your CESOP Reports</h3>
                        <a href="{{ route('cesop.csv.index') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:border-indigo-900 focus:ring focus:ring-indigo-300 disabled:opacity-25 transition">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                            </svg>
                            Generate New Report
                        </a>
                    </div>

                    @if(empty($reports))
                        <div class="bg-gray-50 p-6 text-center rounded-lg">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto text-gray-400 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <p class="text-gray-600">You haven't generated any reports yet.</p>
                            <a href="{{ route('cesop.csv.index') }}" class="mt-4 inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:border-indigo-900 focus:ring focus:ring-indigo-300 disabled:opacity-25 transition">
                                Generate Your First Report
                            </a>
                        </div>
                    @else
                        <div class="overflow-hidden overflow-x-auto border border-gray-100 rounded-lg">
                            <table class="min-w-full text-sm divide-y divide-gray-200">
                                <thead>
                                <tr class="bg-gray-50">
                                    <th class="px-4 py-4 font-medium text-left text-gray-900 whitespace-nowrap">Report</th>
                                    <th class="px-4 py-4 font-medium text-left text-gray-900 whitespace-nowrap">Period</th>
                                    <th class="px-4 py-4 font-medium text-left text-gray-900 whitespace-nowrap">Created</th>
                                    <th class="px-4 py-4 font-medium text-left text-gray-900 whitespace-nowrap">Status</th>
                                    <th class="px-4 py-4 font-medium text-left text-gray-900 whitespace-nowrap">Actions</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($reports as $report)
                                    <tr>
                                        <td class="px-4 py-4 text-gray-900 whitespace-nowrap">
                                            {{ $report['file_name'] ?? 'Processing...' }}
                                        </td>
                                        <td class="px-4 py-4 text-gray-900 whitespace-nowrap">
                                            Q{{ $report['quarter'] }} {{ $report['year'] }}
                                        </td>
                                        <td class="px-4 py-4 text-gray-700 whitespace-nowrap">
                                            {{ \Carbon\Carbon::parse($report['created_at'])->format('M d, Y H:i') }}
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            @php
                                                $reportKey = 'cesop_report_' . auth()->id() . '_' . $report['job_id'];
                                                $reportData = cache()->get($reportKey);
                                                $status = $reportData ? 'completed' : 'processing';
                                            @endphp

                                            @if($status === 'completed')
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                        Completed
                                                    </span>
                                            @else
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                                        Processing
                                                    </span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-4 text-gray-700 whitespace-nowrap">
                                            @if($status === 'completed')
                                                <a href="{{ route('cesop.reports.download', $report['job_id']) }}" class="inline-flex items-center px-3 py-1 bg-blue-600 border border-transparent rounded text-xs text-white uppercase tracking-widest hover:bg-blue-700">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                                    </svg>
                                                    Download
                                                </a>
                                            @else
                                                <span class="text-sm text-gray-500">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 inline animate-spin" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                                        </svg>
                                                        Processing...
                                                    </span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
