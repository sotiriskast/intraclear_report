
@extends('merchantportal::layouts.merchant')

@section('title', 'Transactions')
@section('page-title', 'Transactions')
@section('page-subtitle', 'View and manage your transaction history')

@section('content')
    <div class="row mb-4">
        <div class="col-12">
            <div class="merchant-card">
                <div class="card-header bg-white border-bottom">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-bold">
                            <i class="fas fa-filter me-2"></i>
                            Filter Transactions
                        </h6>
                        <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#filterCollapse">
                            <i class="fas fa-chevron-down"></i>
                        </button>
                    </div>
                </div>
                <div class="collapse" id="filterCollapse">
                    <div class="card-body">
                        <form method="GET" action="{{ route('merchant.transactions.index') }}">
                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Date From</label>
                                    <input type="date" class="form-control" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Date To</label>
                                    <input type="date" class="form-control" name="date_to" value="{{ $filters['date_to'] ?? '' }}">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Status</label>
                                    <select class="form-select" name="status">
                                        <option value="">All Statuses</option>
                                        <option value="pending" {{ ($filters['status'] ?? '') === 'pending' ? 'selected' : '' }}>Pending</option>
                                        <option value="completed" {{ ($filters['status'] ?? '') === 'completed' ? 'selected' : '' }}>Completed</option>
                                        <option value="failed" {{ ($filters['status'] ?? '') === 'failed' ? 'selected' : '' }}>Failed</option>
                                        <option value="refunded" {{ ($filters['status'] ?? '') === 'refunded' ? 'selected' : '' }}>Refunded</option>
                                    </select>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Shop</label>
                                    <select class="form-select" name="shop_id">
                                        <option value="">All Shops</option>
                                        @foreach(auth()->user()->merchant->shops as $shop)
                                            <option value="{{ $shop->id }}" {{ ($filters['shop_id'] ?? '') == $shop->id ? 'selected' : '' }}>
                                                {{ $shop->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Min Amount</label>
                                    <input type="number" class="form-control" name="amount_min" step="0.01" value="{{ $filters['amount_min'] ?? '' }}" placeholder="0.00">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Max Amount</label>
                                    <input type="number" class="form-control" name="amount_max" step="0.01" value="{{ $filters['amount_max'] ?? '' }}" placeholder="0.00">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Per Page</label>
                                    <select class="form-select" name="per_page">
                                        <option value="25" {{ ($filters['per_page'] ?? 25) == 25 ? 'selected' : '' }}>25</option>
                                        <option value="50" {{ ($filters['per_page'] ?? 25) == 50 ? 'selected' : '' }}>50</option>
                                        <option value="100" {{ ($filters['per_page'] ?? 25) == 100 ? 'selected' : '' }}>100</option>
                                    </select>
                                </div>
                                <div class="col-md-3 mb-3 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary me-2">
                                        <i class="fas fa-search me-1"></i>
                                        Filter
                                    </button>
                                    <a href="{{ route('merchant.transactions.index') }}" class="btn btn-outline-secondary">
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

    <div class="row">
        <div class="col-12">
            <div class="merchant-card">
                <div class="card-header bg-white border-bottom">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-bold">
                            <i class="fas fa-credit-card me-2"></i>
                            Transactions ({{ $transactions->total() }})
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
                                <th>Transaction ID</th>
                                <th>Shop</th>
                                <th>Amount</th>
                                <th>Currency</th>
                                <th>Status</th>
                                <th>Payment Method</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($transactions as $transaction)
                                <tr>
                                    <td>
                                        <code class="text-primary">#{{ $transaction->transaction_id }}</code>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="bg-light rounded-2 p-2 me-2">
                                                <i class="fas fa-store text-muted"></i>
                                            </div>
                                            <div>
                                                <strong>{{ $transaction->shop->name ?? 'N/A' }}</strong>
                                                @if($transaction->shop)
                                                    <br><small class="text-muted">{{ $transaction->shop->domain }}</small>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <strong class="text-dark">${{ number_format($transaction->amount, 2) }}</strong>
                                    </td>
                                    <td>{{ strtoupper($transaction->currency) }}</td>
                                    <td>
                                        <span class="merchant-badge
                                            @if($transaction->status === 'completed') bg-success text-white
                                            @elseif($transaction->status === 'pending') bg-warning text-dark
                                            @elseif($transaction->status === 'failed') bg-danger text-white
                                            @elseif($transaction->status === 'refunded') bg-info text-white
                                            @else bg-secondary text-white
                                            @endif
                                        ">
                                            {{ ucfirst($transaction->status) }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <i class="fas fa-credit-card me-2 text-muted"></i>
                                            {{ ucfirst($transaction->payment_method ?? 'Card') }}
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            <strong>{{ $transaction->created_at->format('M j, Y') }}</strong>
                                            <br><small class="text-muted">{{ $transaction->created_at->format('g:i A') }}</small>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="{{ route('merchant.transactions.show', $transaction->id) }}"
                                               class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center py-5">
                                        <div class="text-muted">
                                            <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
                                            <h6>No transactions found</h6>
                                            <p class="mb-0">Try adjusting your filters or check back later.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                @if($transactions->hasPages())
                    <div class="card-footer bg-white border-top">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="text-muted">
                                Showing {{ $transactions->firstItem() }} to {{ $transactions->lastItem() }}
                                of {{ $transactions->total() }} results
                            </div>
                            {{ $transactions->links() }}
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        // Auto-refresh transactions every 30 seconds
        setInterval(function() {
            const currentUrl = new URL(window.location);
            currentUrl.searchParams.set('ajax', '1');

            fetch(currentUrl)
                .then(response => response.json())
                .then(data => {
                    // Update transaction table with new data
                    console.log('Transactions refreshed');
                })
                .catch(error => console.error('Error refreshing transactions:', error));
        }, 30000);
    </script>
@endpush
