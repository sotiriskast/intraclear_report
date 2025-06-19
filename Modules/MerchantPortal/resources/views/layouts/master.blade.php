<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-gray-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">

    <title>@yield('title', 'Merchant Portal') - {{ config('app.name', 'Payment Gateway') }}</title>

    <meta name="description" content="{{ $description ?? 'Merchant Portal - Manage your payments, transactions, and shops' }}">
    <meta name="keywords" content="{{ $keywords ?? 'merchant portal, payments, transactions, e-commerce' }}">
    <meta name="author" content="{{ $author ?? config('app.name') }}">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- TailwindCSS v4 -->
    @vite(['resources/css/app.css'])

    <!-- Module Specific Assets -->
{{--    {{ module_vite('build-merchantportal', 'resources/assets/sass/app.scss') }}--}}

    @stack('styles')
</head>

<body class="h-full bg-gray-50 font-inter">
<div id="app" class="min-h-full">
    <!-- Off-canvas menu for mobile, show/hide based on off-canvas menu state -->
    <div x-data="{ sidebarOpen: false }" class="lg:flex lg:min-h-full">
        <!-- Mobile sidebar overlay -->
        <div x-show="sidebarOpen" class="fixed inset-0 z-50 lg:hidden" x-cloak>
            <div x-show="sidebarOpen" x-transition:enter="transition-opacity ease-linear duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition-opacity ease-linear duration-300" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 bg-gray-900/80"></div>

            <div x-show="sidebarOpen" x-transition:enter="transition ease-in-out duration-300 transform" x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0" x-transition:leave="transition ease-in-out duration-300 transform" x-transition:leave-start="translate-x-0" x-transition:leave-end="-translate-x-full" class="relative flex w-full max-w-xs flex-1 flex-col bg-white">
                <!-- Close button -->
                <div class="absolute left-full top-0 flex w-16 justify-center pt-5">
                    <button type="button" class="-m-2.5 p-2.5" @click="sidebarOpen = false">
                        <span class="sr-only">Close sidebar</span>
                        <i class="fas fa-times h-6 w-6 text-white"></i>
                    </button>
                </div>

                <!-- Sidebar content -->
                @include('merchantportal::layouts.partials.sidebar')
            </div>
        </div>

        <!-- Static sidebar for desktop -->
        <div class="hidden lg:fixed lg:inset-y-0 lg:z-50 lg:flex lg:w-72 lg:flex-col">
            @include('merchantportal::layouts.partials.sidebar')
        </div>

        <!-- Main content area -->
        <div class="lg:pl-72 flex-1 flex flex-col">
            <!-- Top navigation -->
            <div class="sticky top-0 z-40 flex h-16 shrink-0 items-center gap-x-4 border-b border-gray-200 bg-white px-4 shadow-sm sm:gap-x-6 sm:px-6 lg:px-8">
                <!-- Mobile menu button -->
                <button type="button" class="-m-2.5 p-2.5 text-gray-700 lg:hidden" @click="sidebarOpen = true">
                    <span class="sr-only">Open sidebar</span>
                    <i class="fas fa-bars h-5 w-5"></i>
                </button>

                <!-- Separator -->
                <div class="h-6 w-px bg-gray-200 lg:hidden"></div>

                <!-- Page title and breadcrumb -->
                <div class="flex flex-1 gap-x-4 self-stretch lg:gap-x-6">
                    <div class="flex flex-1 items-center">
                        <div>
                            <nav class="flex" aria-label="Breadcrumb">
                                <ol class="flex items-center space-x-2">
                                    <li>
                                        <div class="flex items-center">
                                            <a href="{{ route('merchant.dashboard') }}" class="text-sm font-medium text-gray-500 hover:text-gray-700">
                                                <i class="fas fa-home"></i>
                                            </a>
                                        </div>
                                    </li>
                                    @if(trim($__env->yieldContent('breadcrumb')))
                                        @yield('breadcrumb')
                                    @endif
                                </ol>
                            </nav>

                            @if(trim($__env->yieldContent('page-title')))
                                <h1 class="text-2xl font-bold text-gray-900 mt-1">@yield('page-title')</h1>
                                @if(trim($__env->yieldContent('page-subtitle')))
                                    <p class="text-sm text-gray-600 mt-1">@yield('page-subtitle')</p>
                                @endif
                            @endif
                        </div>
                    </div>

                    <!-- Search bar -->
                    @if(trim($__env->yieldContent('search-bar')))
                        <div class="flex flex-1 justify-center">
                            @yield('search-bar')
                        </div>
                    @endif

                    <!-- Right side actions -->
                    <div class="flex items-center gap-x-4 lg:gap-x-6">
                        @if(trim($__env->yieldContent('page-actions')))
                            @yield('page-actions')
                        @endif

                        <!-- Notifications -->
                        <button type="button" class="relative -m-2.5 p-2.5 text-gray-400 hover:text-gray-500">
                            <span class="sr-only">View notifications</span>
                            <i class="fas fa-bell h-6 w-6"></i>
                            <!-- Notification badge -->
                            <span class="absolute -top-1 -right-1 h-4 w-4 rounded-full bg-red-400 text-xs text-white flex items-center justify-center">3</span>
                        </button>

                        <!-- Profile dropdown -->
                        <div class="relative" x-data="{ open: false }">
                            <button type="button" class="flex items-center gap-x-2 text-sm font-semibold leading-6 text-gray-900" @click="open = !open">
                                <div class="h-8 w-8 rounded-full bg-gradient-to-r from-blue-500 to-purple-600 flex items-center justify-center">
                                    <span class="text-white text-sm font-bold">{{ substr(auth()->user()->name ?? 'M', 0, 1) }}</span>
                                </div>
                                <span class="hidden lg:flex lg:items-center">
                                        <span class="text-sm font-semibold leading-6 text-gray-900">{{ auth()->user()->name ?? 'Merchant' }}</span>
                                        <i class="fas fa-chevron-down ml-2 h-4 w-4 text-gray-400"></i>
                                    </span>
                            </button>

                            <!-- Dropdown menu -->
                            <div x-show="open" @click.away="open = false" x-transition:enter="transition ease-out duration-100" x-transition:enter-start="transform opacity-0 scale-95" x-transition:enter-end="transform opacity-100 scale-100" x-transition:leave="transition ease-in duration-75" x-transition:leave-start="transform opacity-100 scale-100" x-transition:leave-end="transform opacity-0 scale-95" class="absolute right-0 z-10 mt-2.5 w-48 origin-top-right rounded-md bg-white py-2 shadow-lg ring-1 ring-gray-900/5" x-cloak>
                                <a href="#" class="block px-3 py-1 text-sm leading-6 text-gray-900 hover:bg-gray-50">
                                    <i class="fas fa-user mr-2"></i>Your profile
                                </a>
                                <a href="#" class="block px-3 py-1 text-sm leading-6 text-gray-900 hover:bg-gray-50">
                                    <i class="fas fa-cog mr-2"></i>Settings
                                </a>
                                <hr class="my-1">
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="block w-full text-left px-3 py-1 text-sm leading-6 text-gray-900 hover:bg-gray-50">
                                        <i class="fas fa-sign-out-alt mr-2"></i>Sign out
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main content -->
            <main class="flex-1">
                <!-- Flash messages -->
                @if(session('success'))
                    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 pt-4">
                        <div class="rounded-md bg-green-50 p-4 border border-green-200">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-check-circle h-5 w-5 text-green-400"></i>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm font-medium text-green-800">{{ session('success') }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                @if(session('error'))
                    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 pt-4">
                        <div class="rounded-md bg-red-50 p-4 border border-red-200">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-exclamation-circle h-5 w-5 text-red-400"></i>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm font-medium text-red-800">{{ session('error') }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                @if ($errors->any())
                    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 pt-4">
                        <div class="rounded-md bg-red-50 p-4 border border-red-200">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-exclamation-triangle h-5 w-5 text-red-400"></i>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-red-800">There were errors with your submission:</h3>
                                    <div class="mt-2 text-sm text-red-700">
                                        <ul class="list-disc pl-5 space-y-1">
                                            @foreach ($errors->all() as $error)
                                                <li>{{ $error }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                <!-- Page content -->
                <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                    @yield('content')
                </div>
            </main>
        </div>
    </div>
</div>

<!-- Loading overlay -->
<div id="loading-overlay" class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm z-50 hidden items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl p-6 flex items-center space-x-3">
        <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-600"></div>
        <span class="text-gray-700 font-medium">Loading...</span>
    </div>
</div>

<!-- Alpine.js -->
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.0/dist/cdn.min.js"></script>

<!-- Module Scripts -->
{{--{{ module_vite('build-merchantportal', 'resources/assets/js/app.js') }}--}}

@stack('scripts')

<!-- Global JavaScript utilities -->
<script>
    // Loading overlay utilities
    window.showLoading = function() {
        document.getElementById('loading-overlay').classList.remove('hidden');
        document.getElementById('loading-overlay').classList.add('flex');
    }

    window.hideLoading = function() {
        document.getElementById('loading-overlay').classList.add('hidden');
        document.getElementById('loading-overlay').classList.remove('flex');
    }

    // Auto-hide flash messages
    setTimeout(function() {
        const alerts = document.querySelectorAll('[class*="bg-green-50"], [class*="bg-red-50"], [class*="bg-yellow-50"]');
        alerts.forEach(function(alert) {
            if (alert.classList.contains('bg-green-50')) {
                alert.style.transition = 'opacity 0.5s ease-out';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            }
        });
    }, 5000);

    // CSRF token setup for AJAX requests
    window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
    const token = document.head.querySelector('meta[name="csrf-token"]');
    if (token) {
        window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token.content;
    }
</script>
</body>
</html>
