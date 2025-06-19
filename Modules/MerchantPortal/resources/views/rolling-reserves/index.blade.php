
@extends('merchantportal::layouts.merchant')

@section('title', 'Rolling Reserves')
@section('page-title', 'Rolling Reserves')
@section('page-subtitle', 'Track your reserve funds and release schedules')

@section('content')
    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="merchant-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Total Reserved</h6>
                            <h4 class="mb-0 text-primary">${{ number_format($summary['total_reserved'] ?? 0, 2) }}</h4>
                        </div>
                        <div class="bg-primary rounded-3 p-3">
                            <i class="fas fa-piggy-bank text-white"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-3">
            <div class="merchant-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Pending Release</h6>
                            <h4 class="mb-0 text-warning">${{ number_format($summary['pending_release'] ?? 0, 2) }}</h4>
                        </div>
                        <div class="bg-warning rounded-3 p-3">
                            <i class="fas fa-clock text-white"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-3">
            <div class="merchant-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Released This Month</h6>
                            <h4 class="mb-0 text-success">${{ number_format($summary['released_this_month'] ?? 0, 2) }}</h4>
                        </div>
                        <div class="bg-success rounded-3 p-3">
                            <i class="fas fa-check-circle text-white"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-3">
            <div class="merchant-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Reserve Percentage</h6>
                            <h4 class="mb-0 text-info">{{ number_format($summary['reserve_percentage'] ?? 0, 1) }}%</h4>
                        </div>
                        <div class="bg-info rounded-3 p-3">
                            <i class="fas fa-percentage text-white"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="merchant-card">
                <div class="card-header bg-white border-bottom">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-bold">
                            <i class="fas fa-filter me-2"></i>
                            Filter Reserves
                        </h6>
                        <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#reserveFilterCollapse">
                            <i class="fas fa-chevron-down"></i>
                        </button>
                    </div>
                </div>
                <div class="collapse" id="reserveFilterCollapse">
                    <div class="card-body">
                        <form method="GET" action="{{ route('merchant.rolling-reserves.index') }}">
                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Status</label>
                                    <select class="form-select" name="status">
                                        <option value="">All Statuses</option>
                                        <option value="pending" {{ ($filters['status'] ?? '') === 'pending' ? 'selected' : '' }}>Pending</option>
                                        <option value="released" {{ ($filters['status'] ?? '') === 'released' ? 'selected' : '' }}>Released</option>
                                        <option value="held" {{ ($filters['status'] ?? '') === 'held' ? 'selected' : '' }}>Held</option>
                                    </select>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Date From</label>
                                    <input type="date" class="form-control" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Date To</label>
                                    <input type="date" class="form-control" name="date_to" value="{{ $filters['date_to'] ?? '' }}">
                                </div>
                                <div class="col-md-3 mb-3 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary me-2">
                                        <i class="fas fa-search me-1"></i>
                                        Filter
                                    </button>
                                    <a href="{{ route('merchant.rolling-reserves.index') }}" class="btn btn-outline-secondary">
                                        <i class="fas fa-times me-1"></i>
                                        Clear
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Reserves Table -->
    <div class="row">
        <div class="col-12">
            <div class="merchant-card">
                <div class="card-header bg-white border-bottom">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-bold">
                            <i class="fas fa-list me-2"></i>
                            Rolling Reserves ({{ $reserves->total() }})
                        </h6>
                        <div class="btn-group">
                            <button class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-download me-1"></i>
                                Export
                            </button>
                            <button class="btn btn-sm btn-outline-secondary" onclick="window.location.reload()">
                                <i class="fas fa-sync me-1"></i>
                                Refresh
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table merchant-table mb-0">
                            <thead>
                            <tr>
                                <th>Reserve ID</th>
                                <th>Transaction</th>
                                <th>Reserved Amount</th>
                                <th>Status</th>
                                <th>Hold Period</th>
                                <th>Release Date</th>
                                <th>Created</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($reserves as $reserve)
                                <tr>
                                    <td>
                                        <code class="text-primary">#{{ $reserve->id }}</code>
                                    </td>
                                    <td>
                                        @if($reserve->transaction)
                                            <div>
                                                <strong>#{{ $reserve->transaction->transaction_id }}</strong>
                                                <br><small class="text-muted">{{ $reserve->transaction->shop->name ?? 'N/A' }}</small>
                                            </div>
                                        @else
                                            <span class="text-muted">N/A</span>
                                        @endif
                                    </td>
                                    <td>
                                        <strong class="text-dark">${{ number_format($reserve->amount, 2) }}</strong>
                                        <br><small class="text-muted">{{ number_format($reserve->percentage, 1) }}% of transaction</small>
                                    </td>
                                    <td>
                                        <span class="merchant-badge
                                            @if($reserve->status === 'released') bg-success text-white
                                            @elseif($reserve->status === 'pending') bg-warning text-dark
                                            @elseif($reserve->status === 'held') bg-danger text-white
                                            @else bg-secondary text-white
                                            @endif
                                        ">
                                            {{ ucfirst($reserve->status) }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <i class="fas fa-calendar me-2 text-muted"></i>
                                            {{ $reserve->hold_period_days }} days
                                        </div>
                                    </td>
                                    <td>
                                        @if($reserve->release_date)
                                            <div>
                                                <strong>{{ $reserve->release_date->format('M j, Y') }}</strong>
                                                <br><small class="text-muted">
                                                    @if($reserve->release_date->isFuture())
                                                        {{ $reserve->release_date->diffForHumans() }}
                                                    @else
                                                        {{ $reserve->release_date->diffForHumans() }}
                                                    @endif
                                                </small>
                                            </div>
                                        @else
                                            <span class="text-muted">Not scheduled</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div>
                                            <strong>{{ $reserve->created_at->format('M j, Y') }}</strong>
                                            <br><small class="text-muted">{{ $reserve->created_at->format('g:i A') }}</small>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-5">
                                        <div class="text-muted">
                                            <i class="fas fa-piggy-bank fa-3x mb-3 d-block"></i>
                                            <h6>No rolling reserves found</h6>
                                            <p class="mb-0">No reserves match your current filters.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                @if($reserves->hasPages())
                    <div class="card-footer bg-white border-top">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="text-muted">
                                Showing {{ $reserves->firstItem() }} to {{ $reserves->lastItem() }}
                                of {{ $reserves->total() }} results
                            </div>
                            {{ $reserves->links() }}
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Reserve Release Timeline -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="merchant-card">
                <div class="card-header bg-white border-bottom">
                    <h6 class="mb-0 fw-bold">
                        <i class="fas fa-chart-line me-2"></i>
                        Reserve Release Timeline
                    </h6>
                </div>
                <div class="card-body">
                    <canvas id="reserveTimelineChart" height="100"></canvas>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        // Reserve Timeline Chart
        const reserveCtx = document.getElementById('reserveTimelineChart').getContext('2d');
        const reserveChart = new Chart(reserveCtx, {
            type: 'line',
            data: {
                labels: @json($timeline['labels'] ?? []),
                datasets: [{
                    label: 'Scheduled Releases',
                    data: @json($timeline['data'] ?? []),
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: '#f3f4f6'
                        },
                        ticks: {
                            callback: function(value) {
                                return ' + value.toLocaleString();'
                            }
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });

        // Auto-refresh reserves data every 60 seconds
        setInterval(function() {
            fetch('{{ route("merchant.rolling-reserves.summary") }}')
                .then(response => response.json())
                .then(data => {
                    console.log('Reserve data refreshed:', data);
                })
                .catch(error => console.error('Error refreshing reserve data:', error));
        }, 60000);
    </script>
@endpush
