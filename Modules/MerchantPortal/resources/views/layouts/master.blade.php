<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Merchant Portal') | {{ config('app.name', 'Laravel') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Vite -->
    @vite(['resources/css/app.css'])

    @stack('styles')
</head>
<body class="bg-gray-50 font-sans antialiased">
<div class="min-h-screen">
    <!-- Mobile sidebar backdrop -->
    <div id="sidebar-backdrop" class="fixed inset-0 z-40 bg-gray-900/50 lg:hidden hidden" onclick="toggleSidebar()"></div>

    <!-- Sidebar -->
    <aside id="sidebar" class="fixed inset-y-0 left-0 z-50 w-64 bg-white shadow-lg transform -translate-x-full transition-transform duration-300 ease-in-out lg:translate-x-0 lg:static lg:inset-0">
        <!-- Logo -->
        <div class="flex items-center h-16 px-6 border-b border-gray-200">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-gradient-to-r from-blue-500 to-indigo-600 rounded-lg flex items-center justify-center">
                        <i class="fas fa-store text-white text-sm"></i>
                    </div>
                </div>
                <div class="ml-3">
                    <div class="text-lg font-semibold text-gray-900">Merchant Portal</div>
                    <div class="text-xs text-gray-500">{{ auth()->user()->merchant->name ?? 'Merchant Dashboard' }}</div>
                </div>
            </div>
        </div>

        <!-- Navigation -->
        <nav class="mt-6 px-3">
            <div class="space-y-1">
                <!-- Dashboard -->
                <a href="{{ route('merchant.dashboard') }}"
                   class="group flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors duration-150 {{ request()->routeIs('merchant.dashboard') ? 'bg-blue-50 text-blue-700 border-r-2 border-blue-600' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900' }}">
                    <i class="fas fa-chart-line w-5 h-5 mr-3 {{ request()->routeIs('merchant.dashboard') ? 'text-blue-600' : 'text-gray-400 group-hover:text-gray-500' }}"></i>
                    Dashboard
                </a>

                <!-- Transactions -->
                <a href="{{ route('merchant.transactions.index') }}"
                   class="group flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors duration-150 {{ request()->routeIs('merchant.transactions.*') ? 'bg-blue-50 text-blue-700 border-r-2 border-blue-600' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900' }}">
                    <i class="fas fa-credit-card w-5 h-5 mr-3 {{ request()->routeIs('merchant.transactions.*') ? 'text-blue-600' : 'text-gray-400 group-hover:text-gray-500' }}"></i>
                    Transactions
                </a>

                <!-- Shops -->
                <a href="{{ route('merchant.shops.index') }}"
                   class="group flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors duration-150 {{ request()->routeIs('merchant.shops.*') ? 'bg-blue-50 text-blue-700 border-r-2 border-blue-600' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900' }}">
                    <i class="fas fa-store w-5 h-5 mr-3 {{ request()->routeIs('merchant.shops.*') ? 'text-blue-600' : 'text-gray-400 group-hover:text-gray-500' }}"></i>
                    Shops
                </a>

                <!-- Rolling Reserves -->
                <a href="{{ route('merchant.rolling-reserves.index') }}"
                   class="group flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors duration-150 {{ request()->routeIs('merchant.rolling-reserves.*') ? 'bg-blue-50 text-blue-700 border-r-2 border-blue-600' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900' }}">
                    <i class="fas fa-piggy-bank w-5 h-5 mr-3 {{ request()->routeIs('merchant.rolling-reserves.*') ? 'text-blue-600' : 'text-gray-400 group-hover:text-gray-500' }}"></i>
                    Reserves
                </a>
            </div>

        </nav>

        <!-- User menu at bottom -->
        <div class="absolute bottom-0 left-0 right-0 p-4 border-t border-gray-200">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-gray-300 rounded-full flex items-center justify-center">
                        <i class="fas fa-user text-gray-600 text-sm"></i>
                    </div>
                </div>
                <div class="ml-3 flex-1 min-w-0">
                    <div class="text-sm font-medium text-gray-900 truncate">
                        {{ auth()->user()->name }}
                    </div>
                    <div class="text-xs text-gray-500 truncate">
                        {{ auth()->user()->email }}
                    </div>
                </div>
                <div class="ml-2">
                    <form method="POST" action="{{ route('logout') }}" class="inline">
                        @csrf
                        <button type="submit" class="text-gray-400 hover:text-gray-600 transition-colors duration-150" title="Logout">
                            <i class="fas fa-sign-out-alt"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </aside>

    <!-- Main content -->
    <div class="lg:pl-64">
        <!-- Top navigation -->
        <div class="sticky top-0 z-40 flex h-16 shrink-0 items-center gap-x-4 border-b border-gray-200 bg-white px-4 shadow-sm sm:gap-x-6 sm:px-6 lg:px-8">
            <!-- Mobile menu button -->
            <button type="button" class="-m-2.5 p-2.5 text-gray-700 lg:hidden" onclick="toggleSidebar()">
                <span class="sr-only">Open sidebar</span>
                <i class="fas fa-bars h-6 w-6"></i>
            </button>

            <!-- Separator -->
            <div class="h-6 w-px bg-gray-200 lg:hidden" aria-hidden="true"></div>

            <!-- Page title and breadcrumb -->
            <div class="flex flex-1 items-center gap-x-4 self-stretch lg:gap-x-6">
                <div class="flex-1">
                    <div class="flex items-center">
                        <h1 class="text-lg font-semibold leading-6 text-gray-900">
                            @yield('page-title', 'Dashboard')
                        </h1>
                        @hasSection('page-subtitle')
                            <div class="hidden sm:block ml-3 text-sm text-gray-500">
                                @yield('page-subtitle')
                            </div>
                        @endif
                    </div>
                    @hasSection('breadcrumb')
                        <nav class="flex mt-1" aria-label="Breadcrumb">
                            <ol class="flex items-center space-x-2 text-sm text-gray-500">
                                @yield('breadcrumb')
                            </ol>
                        </nav>
                    @endif
                </div>

                <!-- Right side items -->
                <div class="flex items-center gap-x-4 lg:gap-x-6">
                    <!-- Notifications -->
                    <button type="button" class="-m-2.5 p-2.5 text-gray-400 hover:text-gray-500">
                        <span class="sr-only">View notifications</span>
                        <i class="fas fa-bell h-5 w-5"></i>
                    </button>

                    <!-- Separator -->
                    <div class="hidden lg:block lg:h-6 lg:w-px lg:bg-gray-200" aria-hidden="true"></div>

                    <!-- Profile dropdown -->
                    <div class="relative">
                        <button type="button" class="-m-1.5 flex items-center p-1.5" id="user-menu-button" onclick="toggleUserMenu()">
                            <span class="sr-only">Open user menu</span>
                            <div class="h-8 w-8 rounded-full bg-gray-300 flex items-center justify-center">
                                <i class="fas fa-user text-gray-600 text-sm"></i>
                            </div>
                            <span class="hidden lg:flex lg:items-center">
                                    <span class="ml-4 text-sm font-semibold leading-6 text-gray-900">{{ auth()->user()->name }}</span>
                                    <i class="ml-2 h-5 w-5 text-gray-400 fas fa-chevron-down"></i>
                                </span>
                        </button>

                        <!-- Dropdown menu -->
                        <div id="user-menu" class="hidden absolute right-0 z-10 mt-2.5 w-32 origin-top-right rounded-md bg-white py-2 shadow-lg ring-1 ring-gray-900/5 focus:outline-none">
{{--                            <a href="{{ route('merchant.profile.edit') }}" class="block px-3 py-1 text-sm leading-6 text-gray-900 hover:bg-gray-50">Profile</a>--}}
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="block w-full text-left px-3 py-1 text-sm leading-6 text-gray-900 hover:bg-gray-50">Sign out</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Page content -->
        <main class="py-6">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <!-- Flash messages -->
                @if (session('success'))
                    <div class="mb-6 rounded-md bg-green-50 p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-check-circle h-5 w-5 text-green-400"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm font-medium text-green-800">{{ session('success') }}</p>
                            </div>
                        </div>
                    </div>
                @endif

                @if (session('error'))
                    <div class="mb-6 rounded-md bg-red-50 p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-exclamation-circle h-5 w-5 text-red-400"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm font-medium text-red-800">{{ session('error') }}</p>
                            </div>
                        </div>
                    </div>
                @endif

                @if ($errors->any())
                    <div class="mb-6 rounded-md bg-red-50 p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-exclamation-triangle h-5 w-5 text-red-400"></i>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-red-800">There were errors with your submission</h3>
                                <div class="mt-2 text-sm text-red-700">
                                    <ul class="list-disc space-y-1 pl-5">
                                        @foreach ($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                <!-- Page content -->
                @yield('content')
            </div>
        </main>
    </div>
</div>

<!-- Scripts -->
@vite(['resources/js/app.js'])

<script>
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const backdrop = document.getElementById('sidebar-backdrop');

        sidebar.classList.toggle('-translate-x-full');
        backdrop.classList.toggle('hidden');
    }

    function toggleUserMenu() {
        const menu = document.getElementById('user-menu');
        menu.classList.toggle('hidden');
    }

    // Close user menu when clicking outside
    document.addEventListener('click', function(event) {
        const menu = document.getElementById('user-menu');
        const button = document.getElementById('user-menu-button');

        if (!menu.contains(event.target) && !button.contains(event.target)) {
            menu.classList.add('hidden');
        }
    });

    // Close mobile sidebar when clicking outside
    document.addEventListener('click', function(event) {
        const sidebar = document.getElementById('sidebar');
        const backdrop = document.getElementById('sidebar-backdrop');

        if (event.target === backdrop) {
            sidebar.classList.add('-translate-x-full');
            backdrop.classList.add('hidden');
        }
    });
</script>

@stack('scripts')
</body>
</html>
