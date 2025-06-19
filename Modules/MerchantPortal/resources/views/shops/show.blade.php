@extends('merchantportal::layouts.merchant')

@section('title', 'Shop Details')
@section('page-title', $shop->name)
@section('page-subtitle', 'Shop performance and configuration')

@section('content')
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center">
                    <a href="{{ route('merchant.shops.index') }}" class="btn btn-outline-secondary me-3">
                        <i class="fas fa-arrow-left me-1"></i>
                        Back to Shops
                    </a>
                    <div>
                        <h4 class="mb-1">{{ $shop->name }}</h4>
                        <div class="d-flex align-items-center">
                        <span class="merchant-badge
                            @if($shop->status === 'active') bg-success text-white
                            @elseif($shop->status === 'inactive') bg-danger text-white
                            @else bg-warning text-dark
                            @endif me-2
                        ">
                            {{ ucfirst($shop->status) }}
                        </span>
                            <small class="text-muted">{{ $shop->domain }}</small>
                        </div>
                    </div>
                </div>
                <div class="btn-group">
                    <button class="btn btn-outline-primary">
                        <i class="fas fa-chart-line me-1"></i>
                        Analytics
                    </button>
                    <button class="btn btn-outline-secondary" onclick="window.location.reload()">
                        <i class="fas fa-sync me-1"></i>
                        Refresh
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Shop Stats -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="merchant-card">
                <div class="card-body text-center">
                    <div class="bg-primary rounded-circle p-3 mx-auto mb-3" style="width: 60px; height: 60px;">
                        <i class="fas fa-credit-card text-white fa-lg"></i>
                    </div>
                    <h4 class="mb-1">{{ $shop->total_transactions ?? 0 }}</h4>
                    <p class="text-muted mb-0">Total Transactions</p>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-3">
            <div class="merchant-card">
                <div class="card-body text-center">
                    <div class="bg-success rounded-circle p-3 mx-auto mb-3" style="width: 60px; height: 60px;">
                        <i class="fas fa-dollar-sign text-white fa-lg"></i>
                    </div>
                    <h4 class="mb-1">${{ number_format($shop->total_volume ?? 0, 2) }}</h4>
                    <p class="text-muted mb-0">Total Volume</p>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-3">
            <div class="merchant-card">
                <div class="card-body text-center">
                    <div class="bg-info rounded-circle p-3 mx-auto mb-3" style="width: 60px; height: 60px;">
                        <i class="fas fa-percentage text-white fa-lg"></i>
                    </div>
                    <h4 class="mb-1">{{ number_format($shop->success_rate ?? 0, 1) }}%</h4>
                    <p class="text-muted mb-0">Success Rate</p>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-3">
            <div class="merchant-card">
                <div class="card-body text-center">
                    <div class="bg-warning rounded-circle p-3 mx-auto mb-3" style="width: 60px; height: 60px;">
                        <i class="fas fa-calculator text-white fa-lg"></i>
                    </div>
                    <h4 class="mb-1">${{ number_format($shop->average_transaction ?? 0, 2) }}</h4>
                    <p class="text-muted mb-0">Average Transaction</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Shop Information -->
        <div class="col-lg-4 mb-4">
            <div class="merchant-card">
                <div class="card-header bg-white border-bottom">
                    <h6 class="mb-0 fw-bold">
                        <i class="fas fa-info-circle me-2"></i>
                        Shop Information
                    </h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="text-muted small">Shop Name</label>
                        <p class="mb-0 fw-bold">{{ $shop->name }}</p>
                    </div>

                    <div class="mb-3">
                        <label class="text-muted small">Domain</label>
                        <p class="mb-0">
                            <a href="https://{{ $shop->domain }}" target="_blank" class="text-decoration-none">
                                {{ $shop->domain }}
                                <i class="fas fa-external-link-alt ms-1 small"></i>
                            </a>
                        </p>
                    </div>

                    <div class="mb-3">
                        <label class="text-muted small">Shop ID</label>
                        <p class="mb-0"><code>{{ $shop->shop_id }}</code></p>
                    </div>

                    <div class="mb-3">
                        <label class="text-muted small">Created</label>
                        <p class="mb-0">{{ $shop->created_at->format('M j, Y') }}</p>
                    </div>

                    <div class="mb-3">
                        <label class="text-muted small">Last Transaction</label>
                        <p class="mb-0">
                            @if($shop->last_transaction_at)
                                {{ $shop->last_transaction_at->diffForHumans() }}
                            @else
                                <span class="text-muted">No transactions yet</span>
                            @endif
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Transactions -->
        <div class="col-lg-8 mb-4">
            <div class="merchant-card">
                <div class="card-header bg-white border-bottom">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-bold">
                            <i class="fas fa-credit-card me-2"></i>
                            Recent Transactions
                        </h6>
                        <a href="{{ route('merchant.transactions.index', ['shop_id' => $shop->id]) }}"
                           class="btn btn-sm btn-outline-primary">
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
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($shop->recentTransactions ?? [] as $transaction)
                                <tr>
                                    <td>
                                        <code class="text-primary">#{{ $transaction->transaction_id }}</code>
                                    </td>
                                    <td>
                                        <strong>${{ number_format($transaction->amount, 2) }}</strong>
                                    </td>
                                    <td>
                                        <span class="merchant-badge
                                            @if($transaction->status === 'completed') bg-success text-white
                                            @elseif($transaction->status === 'pending') bg-warning text-dark
                                            @elseif($transaction->status === 'failed') bg-danger text-white
                                            @else bg-secondary text-white
                                            @endif
                                        ">
                                            {{ ucfirst($transaction->status) }}
                                        </span>
                                    </td>
                                    <td>{{ $transaction->created_at->format('M j, Y g:i A') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center py-4 text-muted">
                                        <i class="fas fa-inbox fa-2x mb-3 d-block"></i>
                                        No recent transactions
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
