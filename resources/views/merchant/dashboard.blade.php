{{-- resources/views/merchant/dashboard.blade.php --}}
    <!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }} - Merchant Dashboard</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

    <style>
        .navbar-brand {
            font-weight: 600;
        }
        .merchant-info {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 1.5rem;
        }
        .welcome-card {
            border: none;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border-radius: 10px;
        }
        .coming-soon {
            background: #f8f9fa;
            border: 2px dashed #dee2e6;
            border-radius: 10px;
            padding: 3rem;
            text-align: center;
            color: #6c757d;
        }
        .security-notice {
            background: linear-gradient(45deg, #28a745, #20c997);
            color: white;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 2rem;
        }
    </style>
</head>
<body class="bg-light">
{{-- Security Success Messages --}}
@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show m-3" role="alert">
        <i class="fas fa-check-circle me-2"></i>
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@if (session('error'))
    <div class="alert alert-danger alert-dismissible fade show m-3" role="alert">
        <i class="fas fa-exclamation-triangle me-2"></i>
        {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

{{-- Navigation --}}
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container-fluid">
        <a class="navbar-brand" href="{{ route('merchant.dashboard') }}">
            <i class="fas fa-store me-2"></i>
            Merchant Portal
            <span class="badge bg-light text-primary ms-2">SECURE</span>
        </a>

        <div class="navbar-nav ms-auto">
            <div class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                    <i class="fas fa-user-circle me-1"></i>
                    {{ auth()->user()->name }}
                    <span class="badge bg-warning text-dark ms-1">MERCHANT</span>
                </a>
                <ul class="dropdown-menu">
                    <li>
                        <h6 class="dropdown-header">
                            <i class="fas fa-shield-alt me-1"></i>
                            Secure Access
                        </h6>
                    </li>
                    <li>
                        <a class="dropdown-item" href="#">
                            <i class="fas fa-user me-2"></i>Profile
                        </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <form method="POST" action="{{ route('merchant.logout') }}" class="d-inline">
                            @csrf
                            <button type="submit" class="dropdown-item">
                                <i class="fas fa-sign-out-alt me-2"></i>Logout
                            </button>
                        </form>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</nav>

{{-- Main Content --}}
<div class="container-fluid mt-4">
    <div class="row">
        {{-- Security Notice --}}
        <div class="col-12">
            <div class="security-notice">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h6 class="mb-2">
                            <i class="fas fa-lock me-2"></i>
                            <strong>Secure Merchant Portal</strong>
                        </h6>
                        <small class="opacity-75">
                            You are accessing the merchant-only portal. Admin portal access is restricted.
                        </small>
                    </div>
                    <div class="col-md-4 text-md-end">
                        <div class="badge bg-light text-success fs-6 px-3 py-2">
                            <i class="fas fa-check-circle me-1"></i>
                            ISOLATED ACCESS
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Welcome Section --}}
        <div class="col-12 mb-4">
            <div class="merchant-info">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h2 class="mb-2">
                            <i class="fas fa-hand-wave me-2"></i>
                            Welcome back, {{ auth()->user()->name }}!
                        </h2>
                        <p class="mb-0 opacity-75">
                            Merchant: <strong>{{ auth()->user()->merchant->name }}</strong>
                        </p>
                        <p class="mb-0 opacity-75">
                            Account ID: <strong>#{{ auth()->user()->merchant->account_id }}</strong>
                        </p>
                        <p class="mb-0 opacity-75">
                            <i class="fas fa-user-tag me-1"></i>
                            User Type: <strong>{{ strtoupper(auth()->user()->user_type) }}</strong>
                        </p>
                    </div>
                    <div class="col-md-4 text-md-end">
                        <div class="badge bg-light text-dark fs-6 px-3 py-2">
                            <i class="fas fa-calendar me-1"></i>
                            {{ now()->format('M d, Y') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Quick Stats Cards --}}
        <div class="col-md-3 mb-4">
            <div class="card welcome-card h-100">
                <div class="card-body text-center">
                    <div class="display-6 text-primary mb-2">
                        <i class="fas fa-credit-card"></i>
                    </div>
                    <h5>Transactions</h5>
                    <p class="text-muted">View your transaction history</p>
                    <span class="badge bg-secondary">Coming Soon</span>
                </div>
            </div>
        </div>

        <div class="col-md-3 mb-4">
            <div class="card welcome-card h-100">
                <div class="card-body text-center">
                    <div class="display-6 text-success mb-2">
                        <i class="fas fa-piggy-bank"></i>
                    </div>
                    <h5>Rolling Reserves</h5>
                    <p class="text-muted">Monitor your reserve balances</p>
                    <span class="badge bg-secondary">Coming Soon</span>
                </div>
            </div>
        </div>

        <div class="col-md-3 mb-4">
            <div class="card welcome-card h-100">
                <div class="card-body text-center">
                    <div class="display-6 text-info mb-2">
                        <i class="fas fa-shop"></i>
                    </div>
                    <h5>Shops</h5>
                    <p class="text-muted">Manage your shop settings</p>
                    <span class="badge bg-secondary">Coming Soon</span>
                </div>
            </div>
        </div>

        <div class="col-md-3 mb-4">
            <div class="card welcome-card h-100">
                <div class="card-body text-center">
                    <div class="display-6 text-warning mb-2">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <h5>Reports</h5>
                    <p class="text-muted">Access detailed reports</p>
                    <span class="badge bg-secondary">Coming Soon</span>
                </div>
            </div>
        </div>

        {{-- Coming Soon Section --}}
        <div class="col-12">
            <div class="coming-soon">
                <div class="display-1 mb-3">
                    <i class="fas fa-tools"></i>
                </div>
                <h3>Portal Under Development</h3>
                <p class="lead">
                    Your merchant portal is being built with amazing features.
                    Stay tuned for transaction management, reporting, and more!
                </p>
                <div class="mt-4">
                        <span class="badge bg-success fs-6 px-3 py-2 me-2">
                            <i class="fas fa-check me-1"></i>Authentication ✓
                        </span>
                    <span class="badge bg-success fs-6 px-3 py-2 me-2">
                            <i class="fas fa-check me-1"></i>Security Isolation ✓
                        </span>
                    <span class="badge bg-warning fs-6 px-3 py-2 me-2">
                            <i class="fas fa-clock me-1"></i>Dashboard (In Progress)
                        </span>
                    <span class="badge bg-secondary fs-6 px-3 py-2">
                            <i class="fas fa-list me-1"></i>Features (Planned)
                        </span>
                </div>

                <div class="mt-4 p-3 bg-light rounded">
                    <h6 class="text-success">
                        <i class="fas fa-shield-check me-2"></i>
                        Security Features Active:
                    </h6>
                    <div class="row">
                        <div class="col-md-6">
                            <ul class="list-unstyled mb-0">
                                <li><i class="fas fa-check text-success me-2"></i>Merchant-only access</li>
                                <li><i class="fas fa-check text-success me-2"></i>Admin portal blocked</li>
