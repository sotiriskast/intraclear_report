@extends('merchantportal::layouts.merchant')

@section('title', 'Shops')
@section('page-title', 'Shops')
@section('page-subtitle', 'Manage your online stores')

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="merchant-card">
                <div class="card-header bg-white border-bottom">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-bold">
                            <i class="fas fa-store me-2"></i>
                            Your Shops ({{ $shops->count() }})
                        </h6>
                        <button class="btn btn-sm btn-outline-secondary" onclick="window.location.reload()">
                            <i class="fas fa-sync me-1"></i>
                            Refresh
                        </button>
                    </div>
                </div>
                <div class="card-body p-0">
                    @if($shops->count() > 0)
                        <div class="row g-0">
                            @foreach($shops as $shop)
                                <div class="col-xl-4 col-lg-6">
                                    <div class="border-end border-bottom p-4">
                                        <div class="d-flex align-items-start">
                                            <div class="bg-primary rounded-3 p-3 me-3">
                                                <i class="fas fa-store text-white"></i>
                                            </div>
                                            <div class="flex-grow-1">
                                                <h6 class="mb-1 fw-bold">{{ $shop->name }}</h6>
                                                <p class="text-muted mb-2 small">{{ $shop->domain }}</p>

                                                <div class="mb-3">
                                                    <div class="row text-center">
                                                        <div class="col-4">
                                                            <div class="border-end">
                                                                <h6 class="mb-0 text-success">{{ $shop->transactions_count ?? 0 }}</h6>
                                                                <small class="text-muted">Transactions</small>
                                                            </div>
                                                        </div>
                                                        <div class="col-4">
                                                            <div class="border-end">
                                                                <h6 class="mb-0 text-primary">${{ number_format($shop->monthly_volume ?? 0, 0) }}</h6>
                                                                <small class="text-muted">Volume</small>
                                                            </div>
                                                        </div>
                                                        <div class="col-4">
                                                            <h6 class="mb-0 text-info">{{ number_format($shop->success_rate ?? 0, 1) }}%</h6>
                                                            <small class="text-muted">Success</small>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="d-flex justify-content-between align-items-center">
                                                <span class="merchant-badge
                                                    @if($shop->status === 'active') bg-success text-white
                                                    @elseif($shop->status === 'inactive') bg-danger text-white
                                                    @else bg-warning text-dark
                                                    @endif
                                                ">
                                                    {{ ucfirst($shop->status) }}
                                                </span>
                                                    <a href="{{ route('merchant.shops.show', $shop->id) }}"
                                                       class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-eye me-1"></i>
                                                        View Details
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-5">
                            <div class="text-muted">
                                <i class="fas fa-store fa-3x mb-3 d-block"></i>
                                <h6>No shops found</h6>
                                <p class="mb-0">You don't have any shops configured yet.</p>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Shop Performance Chart -->
    @if($shops->count() > 0)
        <div class="row mt-4">
            <div class="col-12">
                <div class="merchant-card">
                    <div class="card-header bg-white border-bottom">
                        <h6 class="mb-0 fw-bold">
                            <i class="fas fa-chart-bar me-2"></i>
                            Shop Performance Comparison
                        </h6>
                    </div>
                    <div class="card-body">
                        <canvas id="shopPerformanceChart" height="100"></canvas>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
    @if($shops->count() > 0)
        <script>
            // Shop Performance Chart
            const shopCtx = document.getElementById('shopPerformanceChart').getContext('2d');
            const shopChart = new Chart(shopCtx, {
                type: 'bar',
                data: {
                    labels: @json($shops->pluck('name')),
                    datasets: [{
                        label: 'Monthly Volume ($)',
                        data: @json($shops->pluck('monthly_volume')),
                        backgroundColor: 'rgba(99, 102, 241, 0.8)',
                        borderColor: '#6366f1',
                        borderWidth: 1
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
                                    return ' + value.toLocaleString();
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
        </script>
    @endif
@endpush
