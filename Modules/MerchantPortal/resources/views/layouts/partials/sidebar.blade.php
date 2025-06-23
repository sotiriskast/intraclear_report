<!-- Sidebar component -->
<div class="flex grow flex-col gap-y-5 overflow-y-auto border-r border-gray-200 bg-white px-6 pb-4">
    <!-- Logo and brand -->
    <div class="flex h-16 shrink-0 items-center">
        <div class="flex items-center space-x-3">
            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-gradient-to-r from-blue-600 to-purple-600">
                <i class="fas fa-store text-white text-sm"></i>
            </div>
            <div>
                <h1 class="text-lg font-bold text-gray-900">Merchant Portal</h1>
                <p class="text-xs text-gray-500">Payment Gateway</p>
            </div>
        </div>
    </div>

    <!-- Merchant info card -->
    <div class="bg-gradient-to-r from-blue-50 to-purple-50 rounded-lg p-4 border border-blue-200">
        <div class="flex items-center space-x-3">
            <div class="flex-shrink-0">
                <div class="h-10 w-10 rounded-full bg-gradient-to-r from-blue-500 to-purple-600 flex items-center justify-center">
                    <span class="text-white text-sm font-bold">{{ substr(auth()->user()->merchant->name ?? 'M', 0, 1) }}</span>
                </div>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-gray-900 truncate">
                    {{ auth()->user()->merchant->name ?? 'Merchant Name' }}
                </p>
                <p class="text-xs text-gray-500 truncate">
                    ID: {{ auth()->user()->merchant->id ?? '000000' }}
                </p>
                <div class="flex items-center mt-1">
                    <div class="h-2 w-2 rounded-full bg-green-400 mr-1"></div>
                    <span class="text-xs text-green-600 font-medium">Active</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Navigation menu -->
    <nav class="flex flex-1 flex-col">
        <ul role="list" class="flex flex-1 flex-col gap-y-7">
            <li>
                <ul role="list" class="-mx-2 space-y-1">
                    <!-- Dashboard -->
                    <li>
                        <a href="{{ route('merchant.dashboard') }}"
                           class="group flex gap-x-3 rounded-md px-3 py-2 text-sm font-semibold leading-6 transition-all duration-150 {{ request()->routeIs('merchant.dashboard') ? 'bg-blue-50 text-blue-700 border-r-2 border-blue-600' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900' }}">
                            <i class="fas fa-chart-pie h-5 w-5 shrink-0 {{ request()->routeIs('merchant.dashboard') ? 'text-blue-600' : 'text-gray-400 group-hover:text-gray-500' }}"></i>
                            Dashboard
                            @if(request()->routeIs('merchant.dashboard'))
                                <span class="ml-auto">
                                    <i class="fas fa-chevron-right h-3 w-3 text-blue-600"></i>
                                </span>
                            @endif
                        </a>
                    </li>

                    <!-- Transactions -->
                    <li>
                        <a href="{{ route('merchant.transactions.index') }}"
                           class="group flex gap-x-3 rounded-md px-3 py-2 text-sm font-semibold leading-6 transition-all duration-150 {{ request()->routeIs('merchant.transactions.*') ? 'bg-blue-50 text-blue-700 border-r-2 border-blue-600' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900' }}">
                            <i class="fas fa-credit-card h-5 w-5 shrink-0 {{ request()->routeIs('merchant.transactions.*') ? 'text-blue-600' : 'text-gray-400 group-hover:text-gray-500' }}"></i>
                            Transactions
                            @if(request()->routeIs('merchant.transactions.*'))
                                <span class="ml-auto">
                                    <i class="fas fa-chevron-right h-3 w-3 text-blue-600"></i>
                                </span>
                            @endif
                        </a>
                    </li>

                    <!-- Shops -->
                    <li>
                        <a href="{{ route('merchant.shops.index') }}"
                           class="group flex gap-x-3 rounded-md px-3 py-2 text-sm font-semibold leading-6 transition-all duration-150 {{ request()->routeIs('merchant.shops.*') ? 'bg-blue-50 text-blue-700 border-r-2 border-blue-600' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900' }}">
                            <i class="fas fa-store h-5 w-5 shrink-0 {{ request()->routeIs('merchant.shops.*') ? 'text-blue-600' : 'text-gray-400 group-hover:text-gray-500' }}"></i>
                            Shops
                            @if(request()->routeIs('merchant.shops.*'))
                                <span class="ml-auto">
                                    <i class="fas fa-chevron-right h-3 w-3 text-blue-600"></i>
                                </span>
                            @endif
                        </a>
                    </li>

                    <!-- Rolling Reserves -->
                    <li>
                        <a href="{{ route('merchant.rolling-reserves.index') }}"
                           class="group flex gap-x-3 rounded-md px-3 py-2 text-sm font-semibold leading-6 transition-all duration-150 {{ request()->routeIs('merchant.rolling-reserves.*') ? 'bg-blue-50 text-blue-700 border-r-2 border-blue-600' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900' }}">
                            <i class="fas fa-piggy-bank h-5 w-5 shrink-0 {{ request()->routeIs('merchant.rolling-reserves.*') ? 'text-blue-600' : 'text-gray-400 group-hover:text-gray-500' }}"></i>
                            Rolling Reserves
                            @if(request()->routeIs('merchant.rolling-reserves.*'))
                                <span class="ml-auto">
                                    <i class="fas fa-chevron-right h-3 w-3 text-blue-600"></i>
                                </span>
                            @endif
                        </a>
                    </li>
                </ul>
            </li>

            <!-- Secondary navigation -->
            <li>
                <div class="text-xs font-semibold leading-6 text-gray-400 uppercase tracking-wide">
                    Account
                </div>
                <ul role="list" class="-mx-2 mt-2 space-y-1">
                    <!-- Reports -->
                    <li>
                        <a href="#"
                           class="group flex gap-x-3 rounded-md px-3 py-2 text-sm font-semibold leading-6 text-gray-700 hover:bg-gray-50 hover:text-gray-900 transition-all duration-150">
                            <i class="fas fa-chart-bar h-5 w-5 shrink-0 text-gray-400 group-hover:text-gray-500"></i>
                            Reports
                            <span class="ml-auto text-xs bg-gray-100 text-gray-600 px-2 py-1 rounded-full">Soon</span>
                        </a>
                    </li>

                    <!-- Settings -->
                    <li>
                        <a href="#"
                           class="group flex gap-x-3 rounded-md px-3 py-2 text-sm font-semibold leading-6 text-gray-700 hover:bg-gray-50 hover:text-gray-900 transition-all duration-150">
                            <i class="fas fa-cog h-5 w-5 shrink-0 text-gray-400 group-hover:text-gray-500"></i>
                            Settings
                        </a>
                    </li>

                    <!-- API Documentation -->
                    <li>
                        <a href="#"
                           class="group flex gap-x-3 rounded-md px-3 py-2 text-sm font-semibold leading-6 text-gray-700 hover:bg-gray-50 hover:text-gray-900 transition-all duration-150">
                            <i class="fas fa-book h-5 w-5 shrink-0 text-gray-400 group-hover:text-gray-500"></i>
                            API Docs
                            <i class="fas fa-external-link-alt ml-auto h-3 w-3 text-gray-400"></i>
                        </a>
                    </li>

                    <!-- Support -->
                    <li>
                        <a href="#"
                           class="group flex gap-x-3 rounded-md px-3 py-2 text-sm font-semibold leading-6 text-gray-700 hover:bg-gray-50 hover:text-gray-900 transition-all duration-150">
                            <i class="fas fa-life-ring h-5 w-5 shrink-0 text-gray-400 group-hover:text-gray-500"></i>
                            Support
                        </a>
                    </li>
                </ul>
            </li>

            <!-- Stats summary at bottom -->
            <li class="mt-auto">
                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                    <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">
                        Quick Stats
                    </div>
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-xs text-gray-600">Today's Transactions</span>
                            <span class="text-xs font-semibold text-gray-900">{{ $dailyStats['transactions'] ?? 0 }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-xs text-gray-600">Volume</span>
                            <span class="text-xs font-semibold text-gray-900">€{{ number_format($dailyStats['volume'] ?? 0, 0) }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-xs text-gray-600">Success Rate</span>
                            <span class="text-xs font-semibold text-green-600">{{ number_format($dailyStats['success_rate'] ?? 0, 1) }}%</span>
                        </div>
                    </div>
                </div>
            </li>
        </ul>
    </nav>
</div>
