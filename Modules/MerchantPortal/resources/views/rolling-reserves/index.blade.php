@extends('merchantportal::layouts.master')

@section('title', 'Rolling Reserves')
@section('page-title', 'Rolling Reserves')

@section('content')
    <!-- Header with Info -->
    <div class="mb-8">
        <div class="md:flex md:items-center md:justify-between">
            <div class="flex-1 min-w-0">
                <h1 class="text-2xl font-bold text-gray-900">Rolling Reserves</h1>
                <p class="mt-1 text-sm text-gray-500">Monitor your reserve funds and release schedule</p>
            </div>

            <div class="mt-4 flex md:mt-0 md:ml-4">
                <button type="button"
                        onclick="downloadReport()"
                        class="btn-secondary mr-3">
                    <svg class="-ml-1 mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    Download Report
                </button>

                <button type="button"
                        onclick="refreshData()"
                        class="btn-primary">
                    <svg class="-ml-1 mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    Refresh
                </button>
            </div>
        </div>

        <!-- Info Banner -->
        <div class="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div class="ml-3 flex-1 md:flex md:justify-between">
                    <p class="text-sm text-blue-700">
                        Rolling reserves are funds held from your transactions as a security measure. These funds are automatically released based on your agreed schedule.
                    </p>
                    <p class="mt-3 text-sm md:mt-0 md:ml-6">
                        <a href="#" class="whitespace-nowrap font-medium text-blue-700 hover:text-blue-600">
                            Learn more <span aria-hidden="true">&rarr;</span>
                        </a>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <!-- Total Reserved -->
        <div class="card">
            <div class="card-body">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center">
                            <svg class="w-6 h-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v17.25m0 0c-1.472 0-2.882.265-4.185.75M12 20.25c1.472 0 2.882.265 4.185.75M18.75 4.97A48.416 48.416 0 0012 4.5c-2.291 0-4.545.16-6.75.47m13.5 0c1.01.143 2.01.317 3 .52m-3-.52l2.62 10.726c.122.499-.106 1.012-.329 1.243-.329.329-.778.329-1.15.329H19.5m-6 0h-2.25m2.25 0h6m-6 0v1.5c0 .621.504 1.125 1.125 1.125M12 7.5h1.5m-1.5 0h-1.5m1.5 0v2.25" />
                            </svg>
                        </div>
                    </div>
                    <div class="ml-4 flex-1">
                        <div class="text-sm font-medium text-gray-500">Total Reserved</div>
                        <div class="text-2xl font-bold text-gray-900">€{{ number_format($summary['total_reserved'] ?? 0, 2) }}</div>
                        <div class="text-sm text-gray-500">Current balance</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Available for Release -->
        <div class="card">
            <div class="card-body">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-12 h-12 bg-green-100 rounded-xl flex items-center justify-center">
                            <svg class="w-6 h-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                    </div>
                    <div class="ml-4 flex-1">
                        <div class="text-sm font-medium text-gray-500">Available for Release</div>
                        <div class="text-2xl font-bold text-green-600">€{{ number_format($summary['available_for_release'] ?? 0, 2) }}</div>
                        <div class="text-sm text-gray-500">Ready now</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Next Release -->
        <div class="card">
            <div class="card-body">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-12 h-12 bg-yellow-100 rounded-xl flex items-center justify-center">
                            <svg class="w-6 h-6 text-yellow-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                    </div>
                    <div class="ml-4 flex-1">
                        <div class="text-sm font-medium text-gray-500">Next Release</div>
                        <div class="text-2xl font-bold text-gray-900">€{{ number_format($summary['next_release_amount'] ?? 0, 2) }}</div>
                        <div class="text-sm text-gray-500">{{ $summary['next_release_date'] ?? 'TBD' }}</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Reserve Rate -->
        <div class="card">
            <div class="card-body">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-12 h-12 bg-purple-100 rounded-xl flex items-center justify-center">
                            <svg class="w-6 h-6 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                            </svg>
                        </div>
                    </div>
                    <div class="ml-4 flex-1">
                        <div class="text-sm font-medium text-gray-500">Reserve Rate</div>
                        <div class="text-2xl font-bold text-gray-900">{{ $summary['reserve_rate'] ?? 0 }}%</div>
                        <div class="text-sm text-gray-500">Current rate</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts and Timeline -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
        <!-- Reserve Chart -->
        <div class="lg:col-span-2">
            <div class="card">
                <div class="card-header">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-900">Reserve Balance Over Time</h3>
                        <div class="flex rounded-lg bg-gray-100 p-1">
                            <button class="px-3 py-1 text-sm font-medium text-gray-600 rounded-md bg-white shadow-sm">30 Days</button>
                            <button class="px-3 py-1 text-sm font-medium text-gray-500 rounded-md">90 Days</button>
                            <button class="px-3 py-1 text-sm font-medium text-gray-500 rounded-md">1 Year</button>
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    <!-- Chart placeholder -->
                    <div class="h-80 bg-gray-50 rounded-lg flex items-center justify-center">
                        <div class="text-center">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900">Reserve Balance Chart</h3>
                            <p class="mt-1 text-sm text-gray-500">Chart will be rendered here with your chart library</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Upcoming Releases -->
        <div class="space-y-6">
            <div class="card">
                <div class="card-header">
                    <h3 class="text-lg font-semibold text-gray-900">Upcoming Releases</h3>
                </div>

                <div class="card-body p-0">
                    <div class="divide-y divide-gray-200">
                        @forelse($upcoming_releases ?? [] as $release)
                            <div class="p-4 hover:bg-gray-50">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium text-gray-900">€{{ number_format($release['amount'], 2) }}</p>
                                        <p class="text-sm text-gray-500">{{ $release['date'] }}</p>
                                    </div>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $release['status'] === 'scheduled' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800' }}">
                                        {{ ucfirst($release['status']) }}
                                    </span>
                                </div>
                            </div>
                        @empty
                            <div class="p-4 text-center text-gray-500">
                                <p class="text-sm">No upcoming releases scheduled</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card">
                <div class="card-header">
                    <h3 class="text-lg font-semibold text-gray-900">Quick Actions</h3>
                </div>

                <div class="card-body space-y-3">
                    <button type="button"
                            onclick="requestEarlyRelease()"
                            class="w-full btn-primary">
                        Request Early Release
                    </button>

                    <button type="button"
                            onclick="viewReleaseSchedule()"
                            class="w-full btn-secondary">
                        View Release Schedule
                    </button>

                    <button type="button"
                            onclick="downloadStatement()"
                            class="w-full btn-secondary">
                        Download Statement
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Reserve Entries Table -->
    <div class="card">
        <div class="card-header">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-900">Reserve Entries</h3>

                <!-- Filters -->
                <div class="flex items-center space-x-3">
                    <select class="form-select text-sm" onchange="filterByStatus(this.value)">
                        <option value="">All Statuses</option>
                        <option value="reserved">Reserved</option>
                        <option value="released">Released</option>
                        <option value="scheduled">Scheduled for Release</option>
                    </select>

                    <select class="form-select text-sm" onchange="filterByPeriod(this.value)">
                        <option value="30">Last 30 Days</option>
                        <option value="90">Last 90 Days</option>
                        <option value="365">Last Year</option>
                        <option value="all">All Time</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="table">
                <thead class="table-header">
                <tr>
                    <th class="table-header-cell">Date</th>
                    <th class="table-header-cell">Transaction ID</th>
                    <th class="table-header-cell">Amount Reserved</th>
                    <th class="table-header-cell">Release Date</th>
                    <th class="table-header-cell">Status</th>
                    <th class="table-header-cell">Actions</th>
                </tr>
                </thead>
                <tbody class="table-body">
                @forelse($reserves ?? [] as $reserve)
                    <tr class="table-row-hover">
                        <td class="table-cell">
                            <div class="text-sm text-gray-900">{{ $reserve->created_at->format('M d, Y') }}</div>
                            <div class="text-xs text-gray-500">{{ $reserve->created_at->format('H:i:s') }}</div>
                        </td>

                        <td class="table-cell">
                            @if($reserve->transaction)
                                <a href="{{ route('merchantportal.transactions.show', $reserve->transaction) }}"
                                   class="text-indigo-600 hover:text-indigo-500 font-mono text-sm">
                                    #{{ $reserve->transaction->transaction_id }}
                                </a>
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>

                        <td class="table-cell">
                            <div class="text-sm font-medium text-gray-900">€{{ number_format($reserve->amount, 2) }}</div>
                            @if($reserve->rate)
                                <div class="text-xs text-gray-500">{{ $reserve->rate }}% rate</div>
                            @endif
                        </td>

                        <td class="table-cell">
                            @if($reserve->release_date)
                                <div class="text-sm text-gray-900">{{ $reserve->release_date->format('M d, Y') }}</div>
                                <div class="text-xs text-gray-500">
                                    {{ $reserve->release_date->diffForHumans() }}
                                </div>
                            @else
                                <span class="text-gray-400">Not scheduled</span>
                            @endif
                        </td>

                        <td class="table-cell">
                            @switch($reserve->status)
                                @case('reserved')
                                    <span class="badge badge-warning">Reserved</span>
                                    @break
                                @case('released')
                                    <span class="badge badge-success">Released</span>
                                    @break
                                @case('scheduled')
                                    <span class="badge badge-info">Scheduled</span>
                                    @break
                                @default
                                    <span class="badge badge-gray">{{ ucfirst($reserve->status) }}</span>
                            @endswitch
                        </td>

                        <td class="table-cell">
                            <div class="flex items-center space-x-2">
                                <button type="button"
                                        onclick="viewReserveDetails({{ $reserve->id }})"
                                        class="text-indigo-600 hover:text-indigo-500 text-sm">
                                    View
                                </button>

                                @if($reserve->status === 'reserved' && $reserve->eligible_for_early_release)
                                    <button type="button"
                                            onclick="requestEarlyReleaseFor({{ $reserve->id }})"
                                            class="text-green-600 hover:text-green-500 text-sm">
                                        Request Release
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center">
                            <div class="flex flex-col items-center">
                                <svg class="h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v17.25m0 0c-1.472 0-2.882.265-4.185.75M12 20.25c1.472 0 2.882.265 4.185.75M18.75 4.97A48.416 48.416 0 0012 4.5c-2.291 0-4.545.16-6.75.47m13.5 0c1.01.143 2.01.317 3 .52m-3-.52l2.62 10.726c.122.499-.106 1.012-.329 1.243-.329.329-.778.329-1.15.329H19.5m-6 0h-2.25m2.25 0h6m-6 0v1.5c0 .621.504 1.125 1.125 1.125M12 7.5h1.5m-1.5 0h-1.5m1.5 0v2.25" />
                                </svg>
                                <h3 class="mt-4 text-sm font-medium text-gray-900">No reserve entries</h3>
                                <p class="mt-2 text-sm text-gray-500">Reserve entries will appear here as transactions are processed.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if(isset($reserves) && $reserves->hasPages())
            <div class="card-footer">
                {{ $reserves->links() }}
            </div>
        @endif
    </div>

    @push('scripts')
        <script>
            function refreshData() {
                window.location.reload();
            }

            function downloadReport() {
                // Implement report download
                console.log('Downloading rolling reserves report');
            }

            function requestEarlyRelease() {
                // Implement early release request
                console.log('Requesting early release');
            }

            function viewReleaseSchedule() {
                // Implement release schedule view
                console.log('Viewing release schedule');
            }

            function downloadStatement() {
                // Implement statement download
                console.log('Downloading statement');
            }

            function viewReserveDetails(reserveId) {
                // Implement reserve details view
                console.log('Viewing reserve details:', reserveId);
            }

            function requestEarlyReleaseFor(reserveId) {
                if (confirm('Request early release for this reserve entry?')) {
                    // Implement specific reserve early release
                    console.log('Requesting early release for reserve:', reserveId);
                }
            }

            function filterByStatus(status) {
                // Implement status filtering
                console.log('Filtering by status:', status);
            }

            function filterByPeriod(period) {
                // Implement period filtering
                console.log('Filtering by period:', period);
            }

            // Auto-refresh data every 5 minutes
            setInterval(function() {
                // Check for updates without full page reload
                fetch(window.location.href + '?ajax=1')
                    .then(response => response.json())
                    .then(data => {
                        // Update summary cards if data has changed
                        console.log('Data refreshed');
                    })
                    .catch(error => console.error('Error refreshing data:', error));
            }, 300000); // 5 minutes
        </script>
    @endpush
@endsection
