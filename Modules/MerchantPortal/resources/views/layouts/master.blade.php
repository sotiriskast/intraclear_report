<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Merchant Portal') - {{ config('app.name') }}</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    {{-- {{ module_vite('build-merchantportal', 'resources/assets/sass/app.scss') }} --}}
    <style>
        :root {
            --merchant-primary: #6366f1;
            --merchant-secondary: #8b5cf6;
            --merchant-success: #10b981;
            --merchant-warning: #f59e0b;
            --merchant-danger: #ef4444;
            --merchant-bg: #f8fafc;
            --merchant-sidebar: #ffffff;
        }

        body {
            background-color: var(--merchant-bg);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        }

        .merchant-sidebar {
            background: var(--merchant-sidebar);
            border-right: 1px solid #e5e7eb;
            min-height: 100vh;
            width: 280px;
            position: fixed;
            left: 0;
            top: 0;
            z-index: 1000;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.05);
        }

        .merchant-content {
            margin-left: 280px;
            min-height: 100vh;
            padding: 0;
        }

        .merchant-header {
            background: white;
            border-bottom: 1px solid #e5e7eb;
            padding: 1rem 2rem;
            display: flex;
            justify-content: between;
            align-items: center;
        }

        .merchant-nav-link {
            display: flex;
            align-items: center;
            padding: 0.75rem 1.5rem;
            color: #6b7280;
            text-decoration: none;
            transition: all 0.2s;
        }

        .merchant-nav-link:hover,
        .merchant-nav-link.active {
            background-color: #f3f4f6;
            color: var(--merchant-primary);
            text-decoration: none;
        }

        .merchant-nav-link i {
            margin-right: 0.75rem;
            width: 20px;
            text-align: center;
        }

        .merchant-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border: 1px solid #e5e7eb;
        }

        .stats-card {
            background: linear-gradient(135deg, var(--merchant-primary), var(--merchant-secondary));
            color: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
        }

        .stats-card .stats-icon {
            font-size: 2rem;
            opacity: 0.8;
        }

        .merchant-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-weight: 500;
        }

        .merchant-table {
            border-radius: 8px;
            overflow: hidden;
        }

        .merchant-table th {
            background-color: #f9fafb;
            border: none;
            font-weight: 600;
            color: #374151;
            padding: 1rem;
        }

        .merchant-table td {
            padding: 1rem;
            border-top: 1px solid #f3f4f6;
        }

        @media (max-width: 768px) {
            .merchant-sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s;
            }

            .merchant-sidebar.show {
                transform: translateX(0);
            }

            .merchant-content {
                margin-left: 0;
            }
        }
    </style>

    @stack('styles')
</head>
<body>
<div class="d-flex">
    <!-- Sidebar -->
    <nav class="merchant-sidebar">
        <div class="p-4 border-bottom">
            <div class="d-flex align-items-center">
                <div class="bg-primary rounded-3 p-2 me-3">
                    <i class="fas fa-store text-white"></i>
                </div>
                <div>
                    <h6 class="mb-0 fw-bold">Merchant Portal</h6>
                    <small class="text-muted">{{ auth()->user()->merchant->name ?? 'Merchant' }}</small>
                </div>
            </div>
        </div>

        <div class="py-3">
            <a href="{{ route('merchant.dashboard') }}"
               class="merchant-nav-link {{ request()->routeIs('merchant.dashboard') ? 'active' : '' }}">
                <i class="fas fa-chart-line"></i>
                Dashboard
            </a>

            <a href="{{ route('merchant.transactions.index') }}"
               class="merchant-nav-link {{ request()->routeIs('merchant.transactions.*') ? 'active' : '' }}">
                <i class="fas fa-credit-card"></i>
                Transactions
            </a>

            <a href="{{ route('merchant.shops.index') }}"
               class="merchant-nav-link {{ request()->routeIs('merchant.shops.*') ? 'active' : '' }}">
                <i class="fas fa-store"></i>
                Shops
            </a>

            <a href="{{ route('merchant.rolling-reserves.index') }}"
               class="merchant-nav-link {{ request()->routeIs('merchant.rolling-reserves.*') ? 'active' : '' }}">
                <i class="fas fa-piggy-bank"></i>
                Rolling Reserves
            </a>

            <hr class="my-3 mx-3">

            <div class="px-3">
                <small class="text-muted text-uppercase fw-bold">Account</small>
            </div>

            <a href="#" class="merchant-nav-link">
                <i class="fas fa-cog"></i>
                Settings
            </a>

            <form method="POST" action="{{ route('logout') }}" class="d-inline w-100">
                @csrf
                <button type="submit" class="merchant-nav-link border-0 bg-transparent w-100 text-start">
                    <i class="fas fa-sign-out-alt"></i>
                    Logout
                </button>
            </form>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="merchant-content flex-grow-1">
        <!-- Header -->
        <header class="merchant-header">
            <div class="d-flex align-items-center">
                <button class="btn btn-link d-md-none me-3" type="button" data-bs-toggle="offcanvas"
                        data-bs-target="#merchantSidebar">
                    <i class="fas fa-bars"></i>
                </button>
                <div>
                    <h5 class="mb-0">@yield('page-title', 'Dashboard')</h5>
                    <small class="text-muted">@yield('page-subtitle', 'Welcome to your merchant portal')</small>
                </div>
            </div>

            <div class="d-flex align-items-center">
                <!-- Security Badge -->
                <div class="badge bg-success me-3">
                    <i class="fas fa-shield-alt me-1"></i>
                    Secure Access
                </div>

                <!-- User Menu -->
                <div class="dropdown">
                    <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user me-2"></i>
                        {{ auth()->user()->name }}
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><h6 class="dropdown-header">{{ auth()->user()->email }}</h6></li>
                        <li>
                            <hr class="dropdown-divider">
                        </li>
                        <li><a class="dropdown-item" href="#"><i class="fas fa-user me-2"></i>Profile</a></li>
                        <li><a class="dropdown-item" href="#"><i class="fas fa-cog me-2"></i>Settings</a></li>
                        <li>
                            <hr class="dropdown-divider">
                        </li>
                        <li>
                            <form method="POST" action="{{ route('logout') }}" class="d-inline">
                                @csrf
                                <button type="submit" class="dropdown-item">
                                    <i class="fas fa-sign-out-alt me-2"></i>Logout
                                </button>
                            </form>
                        </li>
                    </ul>
                </div>
            </div>
        </header>

        <!-- Page Content -->
        <main class="p-4">
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @yield('content')
        </main>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
@stack('scripts')
{{-- {{ module_vite('build-merchantportal', 'resources/assets/js/app.js') }} --}}
</body>
</html>
