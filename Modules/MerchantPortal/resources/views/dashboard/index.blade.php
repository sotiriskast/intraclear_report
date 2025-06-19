@extends('merchantportal::layouts.merchant')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard')
@section('page-subtitle', 'Overview of your merchant account')

@section('content')
    <div class="row">
        <!-- Stats Cards -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="stats-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-1 opacity-75">Total Shops</h6>
                        <h3 class="mb-0 fw-bold">{{ $data['shops']->count() }}</h3>
                    </div>
                    <div class="stats-icon">
                        <i class="fas fa-store"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="stats-card bg-success">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-1 opacity-75">Recent Transactions</h6>
                        <h3 class="mb-0 fw-bold">{{ $data['recent_transactions']->count() }}</h3>
                    </div>
                    <div class="stats-icon">
                        <i class="fas fa-credit-card"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="stats-card bg-warning">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-1 opacity-75">Pending Reserves</h6>
                        <h3 class="mb-0 fw-bold">{{ $data['rolling_reserves']->count() }}</h3>
                    </div>
                    <div class="stats-icon">
                        <i class="fas fa-piggy-bank"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="stats-card bg-info">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-1 opacity-75">Success Rate</h6>
                        <h3 class="mb-0 fw-bold">{{ number_format($data['statistics']['success_rate'] ?? 0, 1) }}%</h3>
                    </div>
                    <div class="stats-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Recent Transactions -->
        <div class="col-lg-8 mb-4">
            <div class="merchant-card">
                <div class="card-header bg-white border-bottom">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-bold">
                            <i class="fas fa-credit-card me-2"></i>
                            Recent Transactions
                        </h6>
                        <a href="{{ route('merchant.transactions.index') }}" class="btn btn-sm btn-outline-primary">
                            View All
                        </a>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table merchant-table mb-0">
                            <thead>
                            <tr>
                                <th>Transaction ID</th>
                                <th>Shop</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($data['recent_transactions'] as $transaction)
                                <tr>
                                    <td>
                                        <code class="text-primary">#{{ $transaction->transaction_id }}</code>
                                    </td>
                                    <td>{{ $transaction->shop->name ?? 'N/A' }}</td>
                                    <td>
                                        <strong>${{ number_format($transaction->amount, 2) }}</strong>
                                    </td>
                                    <td>
                                        <span class="merchant-badge
                                            @if($transaction->status === 'completed') bg-success
                                            @elseif($transaction->status === 'pending') bg-warning
                                            @elseif($transaction->status === 'failed') bg-danger
                                            @else bg-secondary
                                            @endif
                                        ">
                                            {{ ucfirst($transaction->status) }}
                                        </span>
                                    </td>
                                    <td>{{ $transaction->created_at->format('M j, Y') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">
                                        <i class="fas fa-inbox fa-2x mb-3 d-block"></i>
                                        No recent transactions found
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="col-lg-4 mb-4">
            <div class="merchant-card">
                <div class="card-header bg-white border-bottom">
                    <h6 class="mb-0 fw-bold">
                        <i class="fas fa-chart-pie me-2"></i>
                        Quick Stats
                    </h6>
                </div>
                <div class="card-body">
                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-muted">Monthly Volume</span>
                            <strong>${{ number_format($data['monthly_summary']['volume'] ?? 0, 2) }}</strong>
                        </div>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar bg-primary" style="width: 75%"></div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-muted">Average Transaction</span>
                            <strong>${{ number_format($data['statistics']['average_transaction'] ?? 0, 2) }}</strong>
                        </div>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar bg-success" style="width: 60%"></div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-muted">Success Rate</span>
                            <strong>{{ number_format($data['statistics']['success_rate'] ?? 0, 1) }}%</strong>
                        </div>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar bg-info" style="width: {{ $data['statistics']['success_rate'] ?? 0 }}%"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Top Performing Shop -->
            @if(isset($data['statistics']['top_performing_shop']))
                <div class="merchant-card mt-4">
                    <div class="card-header bg-white border-bottom">
                        <h6 class="mb-0 fw-bold">
                            <i class="fas fa-trophy me-2"></i>
                            Top Performing Shop
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="bg-warning rounded-3 p-3 me-3">
                                <i class="fas fa-store text-white"></i>
                            </div>
                            <div>
                                <h6 class="mb-1">{{ $data['statistics']['top_performing_shop']->name }}</h6>
                                <small class="text-muted">
                                    ${{ number_format($data['statistics']['top_performing_shop']->monthly_volume ?? 0, 2) }} this month
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <!-- Monthly Chart -->
    <div class="row">
        <div class="col-12">
            <div class="merchant-card">
                <div class="card-header bg-white border-bottom">
                    <h6 class="mb-0 fw-bold">
                        <i class="fas fa-chart-area me-2"></i>
                        Monthly Transaction Overview
                    </h6>
                </div>
                <div class="card-body">
                    <canvas id="monthlyChart" height="100"></canvas>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        // Monthly Chart
        const ctx = document.getElementById('monthlyChart').getContext('2d');
        const monthlyChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                datasets: [{
                    label: 'Transaction Volume',
                    data: @json(array_values($data['monthly_summary']['chart_data'] ?? array_fill(0, 12, 0))),
                    borderColor: '#6366f1',
                    backgroundColor: 'rgba(99, 102, 241, 0.1)',
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
                                return 'link">
                                    <i class="fas fa-user"></i>
                                Profile
                                </a>

                                <a href="#" class="merchant-nav- + value.toLocaleString();
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

// Auto-refresh data every 5 minutes
setInterval(function() {
    fetch('{{ route("merchant.overview") }}')
        .then(response => response.json())
        .then(data => {
            // Update stats cards with new data
            console.log('Dashboard data refreshed:', data);
        })
        .catch(error => console.error('Error refreshing data:', error));
}, 300000); // 5 minutes
</script>
@endpush
