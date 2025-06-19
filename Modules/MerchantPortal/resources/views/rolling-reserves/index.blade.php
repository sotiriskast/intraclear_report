@extends('merchantportal::layouts.master')

@section('title', 'Rolling Reserves')
@section('page-title', 'Rolling Reserves')
@section('page-subtitle', 'Monitor and track your rolling reserve balances')

@section('breadcrumb')
    <li>
        <div class="flex items-center">
            <i class="fas fa-chevron-right h-3 w-3 text-gray-400 mx-2"></i>
            <span class="text-sm font-medium text-gray-900">Rolling Reserves</span>
        </div>
    </li>
@endsection

@section('page-actions')
    <div class="flex items-center space-x-3">
        <!-- Export Button -->
        <button type="button" onclick="exportReserves()" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-150">
            <i class="fas fa-download -ml-1 mr-2 h-4 w-4"></i>
            Export
        </button>

        <!-- Refresh Button -->
        <button type="button" onclick="location.reload()" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-150">
            <i class="fas fa-sync-alt -ml-1 mr-2 h-4 w-4"></i>
            Refresh
        </button>
    </div>
@endsection

@section('content')
    <!-- Reserve Summary Cards -->
    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4 mb-8">
        <!-- Total Reserve Amount -->
        <div class="bg-white shadow-sm rounded-xl ring-1 ring-gray-900/5 p-6 hover:shadow-lg transition-shadow duration-300">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-r from-yellow-500 to-yellow-600 shadow-lg">
                        <i class="fas fa-piggy-bank text-white"></i>
                    </div>
                </div>
                <div class="ml-4 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500">Total Reserve</dt>
                        <dd class="text-2xl font-bold text-gray-900">€{{ number_format($totalReserve ?? 0, 2) }}</dd>
                    </dl>
                    <div class="mt-2 flex items-center text-sm">
                        <span class="text-yellow-600 font-medium">
                            Across all shops
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pending Release -->
        <div class="bg-white shadow-sm rounded-xl ring-1 ring-gray-900/5 p-6 hover:shadow-lg transition-shadow duration-300">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-r from-blue-500 to-blue-600 shadow-lg">
                        <i class="fas fa-clock text-white"></i>
                    </div>
                </div>
                <div class="ml-4 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500">Pending Release</dt>
                        <dd class="text-2xl font-bold text-gray-900">€{{ number_format($pendingRelease ?? 0, 2) }}</dd>
                    </dl>
                    <div class="mt-2 flex items-center text-sm">
                        <span class="text-blue-600 font-medium">
                            Next 30 days
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Available Balance -->
        <div class="bg-white shadow-sm rounded-xl ring-1 ring-gray-900/5 p-6 hover:shadow-lg transition-shadow duration-300">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-r from-green-500 to-green-600 shadow-lg">
                        <i class="fas fa-money-bill-wave text-white"></i>
                    </div>
                </div>
                <div class="ml-4 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500">Available Balance</dt>
                        <dd class="text-2xl font-bold text-gray-900">€{{ number_format($availableBalance ?? 0, 2) }}</dd>
                    </dl>
                    <div class="mt-2 flex items-center text-sm">
                        <span class="text-green-600 font-medium">
                            Ready for payout
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Reserve Percentage -->
        <div class="bg-white shadow-sm rounded-xl ring-1 ring-gray-900/5 p-6 hover:shadow-lg transition-shadow duration-300">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-r from-purple-500 to-purple-600 shadow-lg">
                        <i class="fas fa-percentage text-white"></i>
                    </div>
                </div>
                <div class="ml-4 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500">Avg Reserve Rate</dt>
                        <dd class="text-2xl font-bold text-gray-900">{{ number_format($averageReserveRate ?? 0, 1) }}%</dd>
                    </dl>
                    <div class="mt-2 flex items-center text-sm">
                        <div class="flex-1 bg-gray-200 rounded-full h-2 mr-2">
                            <div class="bg-gradient-to-r from-purple-500 to-purple-600 h-2 rounded-full transition-all duration-500"
                                 style="width: {{ min($averageReserveRate ?? 0, 100) }}%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters Section -->
    <div class="bg-white shadow-sm rounded-xl ring-1 ring-gray-900/5 mb-6" x-data="{ filtersOpen: {{ request()->hasAny(['shop_id', 'status', 'date_from', 'date_to']) ? 'true' : 'false' }} }">
        <div class="p-6 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                        <i class="fas fa-filter mr-3 text-blue-500"></i>
                        Reserve Filters
                    </h3>
                    <p class="mt-1 text-sm text-gray-500">Filter reserves by shop, status, and date range</p>
                </div>
                <button @click="filtersOpen = !filtersOpen" type="button" class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50 transition-colors duration-150">
                    <span x-text="filtersOpen ? 'Hide Filters' : 'Show Filters'"></span>
                    <i class="fas fa-chevron-down ml-2 h-4 w-4 transform transition-transform duration-200" :class="{ 'rotate-180': filtersOpen }"></i>
                </button>
            </div>
        </div>

        <div x-show="filtersOpen" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 transform -translate-y-2" x-transition:enter-end="opacity-100 transform translate-y-0" x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 transform translate-y-0" x-transition:leave-end="opacity-0 transform -translate-y-2" class="p-6">
            <form method="GET" action="{{ route('merchant.rolling-reserves.index') }}" class="space-y-6">
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    <!-- Shop Filter -->
                    <div>
                        <label for="shop_id" class="block text-sm font-medium text-gray-700 mb-2">Shop</label>
                        <select name="shop_id" id="shop_id" class="block w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                            <option value="">All Shops</option>
                            @foreach($shops ?? [] as $shop)
                                <option value="{{ $shop->id }}" {{ request('shop_id') == $shop->id ? 'selected' : '' }}>
                                    {{ $shop->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Status Filter -->
                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                        <select name="status" id="status" class="block w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                            <option value="">All Statuses</option>
                            <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                            <option value="released" {{ request('status') === 'released' ? 'selected' : '' }}>Released</option>
                            <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
                        </select>
                    </div>

                    <!-- Date Range -->
                    <div class="grid grid-cols-2 gap-3 sm:col-span-2">
                        <div class="relative">
                            <input type="date" name="date_from" value="{{ request('date_from') }}" class="block w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                            <label class="absolute -top-2 left-2 inline-block bg-white px-1 text-xs font-medium text-gray-500">From Date</label>
                        </div>
                        <div class="relative">
                            <input type="date" name="date_to" value="{{ request('date_to') }}" class="block w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                            <label class="absolute -top-2 left-2 inline-block bg-white px-1 text-xs font-medium text-gray-500">To Date</label>
                        </div>
                    </div>
                </div>

                <!-- Filter Actions -->
                <div class="flex items-center justify-between pt-4 border-t border-gray-200">
                    <div class="flex items-center space-x-3">
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-150">
                            <i class="fas fa-search -ml-1 mr-2 h-4 w-4"></i>
                            Apply Filters
                        </button>

                        @if(request()->hasAny(['shop_id', 'status', 'date_from', 'date_to']))
                            <a href="{{ route('merchant.rolling-reserves.index') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-150">
                                <i class="fas fa-times -ml-1 mr-2 h-4 w-4"></i>
                                Clear Filters
                            </a>
                        @endif
                    </div>

                    <div class="text-sm text-gray-500">
                        Showing {{ $reserves->count() ?? 0 }} reserves
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Rolling Reserves by Shop -->
    @if(isset($shopReserves) && $shopReserves->isNotEmpty())
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2 xl:grid-cols-3 mb-8">
            @foreach($shopReserves as $shopReserve)
                <div class="bg-white shadow-sm rounded-xl ring-1 ring-gray-900/5 overflow-hidden hover:shadow-lg transition-shadow duration-300">
                    <!-- Shop Header -->
                    <div class="p-6 border-b border-gray-200 bg-gradient-to-r from-yellow-50 to-yellow-100">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-3">
                                <div class="h-12 w-12 rounded-xl bg-gradient-to-r from-yellow-500 to-yellow-600 flex items-center justify-center shadow-lg">
                                    <span class="text-white text-lg font-bold">{{ substr($shopReserve->shop_name, 0, 1) }}</span>
                                </div>
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-900">{{ $shopReserve->shop_name }}</h3>
                                    <p class="text-sm text-gray-500">Shop ID: {{ $shopReserve->shop_id }}</p>
                                </div>
                            </div>

                            <!-- Reserve Rate Badge -->
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-yellow-100 text-yellow-800">
                                {{ number_format($shopReserve->reserve_percentage, 1) }}% rate
                            </span>
                        </div>
                    </div>

                    <!-- Reserve Metrics -->
                    <div class="p-6">
                        <div class="grid grid-cols-2 gap-4 mb-6">
                            <!-- Current Reserve -->
                            <div class="text-center p-4 bg-gray-50 rounded-lg">
                                <div class="text-2xl font-bold text-gray-900">€{{ number_format($shopReserve->current_reserve ?? 0, 2) }}</div>
                                <div class="text-xs text-gray-500 uppercase tracking-wide">Current Reserve</div>
                            </div>

                            <!-- Pending Release -->
                            <div class="text-center p-4 bg-gray-50 rounded-lg">
                                <div class="text-2xl font-bold text-gray-900">€{{ number_format($shopReserve->pending_release ?? 0, 2) }}</div>
                                <div class="text-xs text-gray-500 uppercase tracking-wide">Pending Release</div>
                            </div>
                        </div>

                        <!-- Reserve Timeline -->
                        <div class="space-y-3">
                            <h4 class="text-sm font-medium text-gray-900">Release Schedule</h4>

                            @if(isset($shopReserve->upcoming_releases) && $shopReserve->upcoming_releases->isNotEmpty())
                                <div class="space-y-2">
                                    @foreach($shopReserve->upcoming_releases->take(3) as $release)
                                        <div class="flex items-center justify-between p-3 bg-blue-50 rounded-lg">
                                            <div class="flex items-center space-x-3">
                                                <div class="w-2 h-2 bg-blue-500 rounded-full"></div>
                                                <div>
                                                    <div class="text-sm font-medium text-gray-900">
                                                        {{ $release->release_date->format('M j, Y') }}
                                                    </div>
                                                    <div class="text-xs text-gray-500">
                                                        {{ $release->release_date->diffForHumans() }}
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="text-sm font-semibold text-gray-900">
                                                €{{ number_format($release->amount, 2) }}
                                            </div>
                                        </div>
                                    @endforeach

                                    @if($shopReserve->upcoming_releases->count() > 3)
                                        <div class="text-center">
                                            <span class="text-xs text-gray-500">
                                                +{{ $shopReserve->upcoming_releases->count() - 3 }} more releases
                                            </span>
                                        </div>
                                    @endif
                                </div>
                            @else
                                <div class="text-center py-4">
                                    <i class="fas fa-calendar-times text-gray-300 text-2xl mb-2"></i>
                                    <p class="text-sm text-gray-500">No upcoming releases</p>
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- Shop Actions -->
                    <div class="px-6 py-4 bg-gray-50 border-t border-gray-200">
                        <div class="flex items-center justify-between">
                            <a href="{{ route('merchant.shops.show', $shopReserve->shop_id) }}"
                               class="inline-flex items-center text-blue-600 hover:text-blue-900 text-sm font-medium transition-colors duration-150">
                                <i class="fas fa-store mr-1 h-3 w-3"></i>
                                View Shop
                            </a>
                            <a href="{{ route('merchant.transactions.index', ['shop_id' => $shopReserve->shop_id]) }}"
                               class="inline-flex items-center text-green-600 hover:text-green-900 text-sm font-medium transition-colors duration-150">
                                <i class="fas fa-credit-card mr-1 h-3 w-3"></i>
                                Transactions
                            </a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <!-- Detailed Reserve History Table -->
    <div class="bg-white shadow-sm rounded-xl ring-1 ring-gray-900/5 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                    <i class="fas fa-history mr-3 text-blue-500"></i>
                    Reserve History
                </h3>
                <div class="flex items-center space-x-3">
                    <select onchange="changePerPage(this.value)" class="text-sm border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                        <option value="25" {{ request('per_page') == 25 ? 'selected' : '' }}>25 per page</option>
                        <option value="50" {{ request('per_page') == 50 ? 'selected' : '' }}>50 per page</option>
                        <option value="100" {{ request('per_page') == 100 ? 'selected' : '' }}>100 per page</option>
                    </select>
                </div>
            </div>
        </div>

        @if(isset($reserves) && $reserves->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <a href="{{ request()->fullUrlWithQuery(['sort' => 'transaction_date', 'direction' => request('direction') === 'asc' ? 'desc' : 'asc']) }}" class="group inline-flex items-center hover:text-gray-900">
                                Transaction Date
                                <span class="ml-2 flex-none rounded text-gray-400 group-hover:text-gray-500">
                                        <i class="fas fa-sort h-3 w-3"></i>
                                    </span>
                            </a>
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Shop
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Transaction ID
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <a href="{{ request()->fullUrlWithQuery(['sort' => 'reserve_amount', 'direction' => request('direction') === 'asc' ? 'desc' : 'asc']) }}" class="group inline-flex items-center hover:text-gray-900">
                                Reserve Amount
                                <span class="ml-2 flex-none rounded text-gray-400 group-hover:text-gray-500">
                                        <i class="fas fa-sort h-3 w-3"></i>
                                    </span>
                            </a>
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Reserve %
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <a href="{{ request()->fullUrlWithQuery(['sort' => 'release_date', 'direction' => request('direction') === 'asc' ? 'desc' : 'asc']) }}" class="group inline-flex items-center hover:text-gray-900">
                                Release Date
                                <span class="ml-2 flex-none rounded text-gray-400 group-hover:text-gray-500">
                                        <i class="fas fa-sort h-3 w-3"></i>
                                    </span>
                            </a>
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Status
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Actions
                        </th>
                    </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($reserves as $reserve)
                        <tr class="hover:bg-gray-50 transition-colors duration-150">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">
                                    {{ $reserve->transaction_date->format('M j, Y') }}
                                </div>
                                <div class="text-xs text-gray-500">
                                    {{ $reserve->transaction_date->format('H:i:s') }}
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="h-8 w-8 rounded-full bg-gradient-to-r from-yellow-500 to-yellow-600 flex items-center justify-center mr-3">
                                        <span class="text-white text-xs font-bold">{{ substr($reserve->shop->name ?? 'U', 0, 1) }}</span>
                                    </div>
                                    <div>
                                        <div class="text-sm font-medium text-gray-900">{{ $reserve->shop->name ?? 'Unknown Shop' }}</div>
                                        <div class="text-xs text-gray-500">ID: {{ $reserve->shop_id }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">
                                    {{ $reserve->transaction->payment_id ?? $reserve->transaction_id }}
                                </div>
                                <div class="text-xs text-gray-500">
                                    €{{ number_format($reserve->transaction_amount / 100, 2) }} transaction
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-semibold text-gray-900">
                                    €{{ number_format($reserve->reserve_amount, 2) }}
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                        {{ number_format($reserve->reserve_percentage, 1) }}%
                                    </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">
                                    {{ $reserve->release_date->format('M j, Y') }}
                                </div>
                                <div class="text-xs text-gray-500">
                                    {{ $reserve->release_date->diffForHumans() }}
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                        @switch($reserve->status)
                                            @case('released')
                                                bg-green-100 text-green-800
                                                @break
                                            @case('pending')
                                                bg-yellow-100 text-yellow-800
                                                @break
                                            @case('active')
                                                bg-blue-100 text-blue-800
                                                @break
                                            @default
                                                bg-gray-100 text-gray-800
                                        @endswitch
                                    ">
                                        <i class="fas fa-{{ $reserve->status === 'released' ? 'check' : ($reserve->status === 'pending' ? 'clock' : 'circle') }} mr-1 h-3 w-3"></i>
                                        {{ ucfirst($reserve->status) }}
                                    </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <div class="flex items-center space-x-2">
                                    <a href="{{ route('merchant.transactions.show', $reserve->transaction_id) }}" class="text-blue-600 hover:text-blue-900 transition-colors duration-150">
                                        <i class="fas fa-eye h-4 w-4"></i>
                                        <span class="sr-only">View transaction</span>
                                    </a>
                                    <button type="button" onclick="copyToClipboard('{{ $reserve->transaction_id }}')" class="text-gray-400 hover:text-gray-600 transition-colors duration-150">
                                        <i class="fas fa-copy h-4 w-4"></i>
                                        <span class="sr-only">Copy transaction ID</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            @if($reserves->hasPages())
                <div class="bg-white px-6 py-4 border-t border-gray-200">
                    <div class="flex items-center justify-between">
                        <div class="flex-1 flex justify-between sm:hidden">
                            {{ $reserves->appends(request()->query())->links() }}
                        </div>
                        <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                            <div>
                                <p class="text-sm text-gray-700">
                                    Showing <span class="font-medium">{{ $reserves->firstItem() }}</span> to
                                    <span class="font-medium">{{ $reserves->lastItem() }}</span> of
                                    <span class="font-medium">{{ $reserves->total() }}</span> results
                                </p>
                            </div>
                            <div>
                                {{ $reserves->appends(request()->query())->links() }}
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        @else
            <!-- Empty State -->
            <div class="text-center py-12">
                <div class="mx-auto h-24 w-24 rounded-full bg-gray-100 flex items-center justify-center mb-6">
                    <i class="fas fa-piggy-bank text-4xl text-gray-400"></i>
                </div>
                <h3 class="text-lg font-medium text-gray-900 mb-2">No rolling reserves found</h3>
                <p class="text-gray-500 mb-8 max-w-md mx-auto">
                    @if(request()->hasAny(['shop_id', 'status', 'date_from', 'date_to']))
                        No reserves match your current filters. Try adjusting your search criteria.
                    @else
                        No rolling reserves have been created yet. Reserves will appear here as transactions are processed.
                    @endif
                </p>
                @if(request()->hasAny(['shop_id', 'status', 'date_from', 'date_to']))
                    <div class="flex items-center justify-center space-x-4">
                        <a href="{{ route('merchant.rolling-reserves.index') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-150">
                            <i class="fas fa-times -ml-1 mr-2 h-4 w-4"></i>
                            Clear Filters
                        </a>
                        <button type="button" onclick="location.reload()" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-150">
                            <i class="fas fa-sync-alt -ml-1 mr-2 h-4 w-4"></i>
                            Refresh
                        </button>
                    </div>
                @endif
            </div>
        @endif
    </div>

    <!-- Reserve Release Calendar -->
    @if(isset($upcomingReleases) && $upcomingReleases->isNotEmpty())
        <div class="mt-8 bg-white shadow-sm rounded-xl ring-1 ring-gray-900/5 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                    <i class="fas fa-calendar-alt mr-3 text-green-500"></i>
                    Upcoming Reserve Releases
                </h3>
                <p class="mt-1 text-sm text-gray-500">Schedule of reserve releases over the next 90 days</p>
            </div>

            <div class="p-6">
                <div class="space-y-4">
                    @foreach($upcomingReleases->groupBy(function($date) { return $date->release_date->format('Y-m-d'); }) as $date => $releases)
                        <div class="flex items-center justify-between p-4 bg-gradient-to-r from-green-50 to-green-100 rounded-lg border border-green-200">
                            <div class="flex items-center space-x-4">
                                <div class="flex-shrink-0">
                                    <div class="h-12 w-12 rounded-xl bg-gradient-to-r from-green-500 to-green-600 flex items-center justify-center shadow-lg">
                                        <i class="fas fa-calendar-check text-white"></i>
                                    </div>
                                </div>
                                <div>
                                    <div class="text-lg font-semibold text-gray-900">
                                        {{ \Carbon\Carbon::parse($date)->format('M j, Y') }}
                                    </div>
                                    <div class="text-sm text-gray-600">
                                        {{ \Carbon\Carbon::parse($date)->diffForHumans() }} • {{ $releases->count() }} release{{ $releases->count() > 1 ? 's' : '' }}
                                    </div>
                                </div>
                            </div>

                            <div class="text-right">
                                <div class="text-2xl font-bold text-gray-900">
                                    €{{ number_format($releases->sum('amount'), 2) }}
                                </div>
                                <div class="text-sm text-gray-500">
                                    Total release amount
                                </div>
                            </div>
                        </div>

                        <!-- Detailed breakdown for this date -->
                        <div class="ml-16 space-y-2">
                            @foreach($releases->take(3) as $release)
                                <div class="flex items-center justify-between py-2 px-4 bg-gray-50 rounded-lg">
                                    <div class="flex items-center space-x-3">
                                        <div class="h-6 w-6 rounded-full bg-gradient-to-r from-blue-500 to-purple-600 flex items-center justify-center">
                                            <span class="text-white text-xs font-bold">{{ substr($release->shop->name ?? 'S', 0, 1) }}</span>
                                        </div>
                                        <span class="text-sm font-medium text-gray-900">{{ $release->shop->name ?? 'Unknown Shop' }}</span>
                                    </div>
                                    <span class="text-sm font-semibold text-gray-900">€{{ number_format($release->amount, 2) }}</span>
                                </div>
                            @endforeach

                            @if($releases->count() > 3)
                                <div class="text-center py-1">
                                    <span class="text-xs text-gray-500">
                                        +{{ $releases->count() - 3 }} more shops with releases on this date
                                    </span>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    <!-- Reserve Summary by Status -->
    @if(isset($reservesByStatus))
        <div class="mt-8 bg-white shadow-sm rounded-xl ring-1 ring-gray-900/5 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                    <i class="fas fa-chart-pie mr-3 text-purple-500"></i>
                    Reserve Status Breakdown
                </h3>
                <p class="mt-1 text-sm text-gray-500">Distribution of reserves by current status</p>
            </div>

            <div class="p-6">
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-3">
                    <!-- Active Reserves -->
                    <div class="bg-blue-50 rounded-lg p-6 border border-blue-200">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-2xl font-bold text-blue-900">
                                    €{{ number_format($reservesByStatus['active']['amount'] ?? 0, 2) }}
                                </div>
                                <div class="text-sm font-medium text-blue-700">Active Reserves</div>
                                <div class="text-xs text-blue-600 mt-1">
                                    {{ $reservesByStatus['active']['count'] ?? 0 }} reserves
                                </div>
                            </div>
                            <div class="h-12 w-12 rounded-xl bg-blue-500 flex items-center justify-center">
                                <i class="fas fa-circle text-white"></i>
                            </div>
                        </div>
                        <div class="mt-4">
                            <div class="flex justify-between text-xs text-blue-600 mb-1">
                                <span>Progress</span>
                                <span>{{ number_format(($reservesByStatus['active']['percentage'] ?? 0), 1) }}%</span>
                            </div>
                            <div class="w-full bg-blue-200 rounded-full h-2">
                                <div class="bg-blue-500 h-2 rounded-full transition-all duration-500"
                                     style="width: {{ min($reservesByStatus['active']['percentage'] ?? 0, 100) }}%"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Pending Releases -->
                    <div class="bg-yellow-50 rounded-lg p-6 border border-yellow-200">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-2xl font-bold text-yellow-900">
                                    €{{ number_format($reservesByStatus['pending']['amount'] ?? 0, 2) }}
                                </div>
                                <div class="text-sm font-medium text-yellow-700">Pending Release</div>
                                <div class="text-xs text-yellow-600 mt-1">
                                    {{ $reservesByStatus['pending']['count'] ?? 0 }} reserves
                                </div>
                            </div>
                            <div class="h-12 w-12 rounded-xl bg-yellow-500 flex items-center justify-center">
                                <i class="fas fa-clock text-white"></i>
                            </div>
                        </div>
                        <div class="mt-4">
                            <div class="flex justify-between text-xs text-yellow-600 mb-1">
                                <span>Progress</span>
                                <span>{{ number_format(($reservesByStatus['pending']['percentage'] ?? 0), 1) }}%</span>
                            </div>
                            <div class="w-full bg-yellow-200 rounded-full h-2">
                                <div class="bg-yellow-500 h-2 rounded-full transition-all duration-500"
                                     style="width: {{ min($reservesByStatus['pending']['percentage'] ?? 0, 100) }}%"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Released Reserves -->
                    <div class="bg-green-50 rounded-lg p-6 border border-green-200">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-2xl font-bold text-green-900">
                                    €{{ number_format($reservesByStatus['released']['amount'] ?? 0, 2) }}
                                </div>
                                <div class="text-sm font-medium text-green-700">Released</div>
                                <div class="text-xs text-green-600 mt-1">
                                    {{ $reservesByStatus['released']['count'] ?? 0 }} reserves
                                </div>
                            </div>
                            <div class="h-12 w-12 rounded-xl bg-green-500 flex items-center justify-center">
                                <i class="fas fa-check text-white"></i>
                            </div>
                        </div>
                        <div class="mt-4">
                            <div class="flex justify-between text-xs text-green-600 mb-1">
                                <span>Progress</span>
                                <span>{{ number_format(($reservesByStatus['released']['percentage'] ?? 0), 1) }}%</span>
                            </div>
                            <div class="w-full bg-green-200 rounded-full h-2">
                                <div class="bg-green-500 h-2 rounded-full transition-all duration-500"
                                     style="width: {{ min($reservesByStatus['released']['percentage'] ?? 0, 100) }}%"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
    <script>
        function changePerPage(value) {
            const url = new URL(window.location.href);
            url.searchParams.set('per_page', value);
            url.searchParams.delete('page');
            window.location.href = url.toString();
        }

        function exportReserves() {
            const url = new URL('{{ route("merchant.rolling-reserves.index") }}');
            const params = new URLSearchParams(window.location.search);
            params.set('export', 'csv');

            // Create a temporary form to submit the export request
            const form = document.createElement('form');
            form.method = 'GET';
            form.action = url.pathname;

            for (const [key, value] of params) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = key;
                input.value = value;
                form.appendChild(input);
            }

            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
        }

        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                console.log('Copied to clipboard: ' + text);
                showToast('Transaction ID copied to clipboard!', 'success');
            }, function(err) {
                console.error('Could not copy text: ', err);
                showToast('Failed to copy to clipboard', 'error');
            });
        }

        function showToast(message, type = 'info') {
            const toast = document.createElement('div');
            toast.className = `fixed top-4 right-4 z-50 px-4 py-2 rounded-lg shadow-lg text-white transform transition-all duration-300 ${
                type === 'success' ? 'bg-green-500' :
                    type === 'error' ? 'bg-red-500' :
                        'bg-blue-500'
            }`;
            toast.textContent = message;
            toast.style.transform = 'translateX(100%)';

            document.body.appendChild(toast);

            // Animate in
            setTimeout(() => {
                toast.style.transform = 'translateX(0)';
            }, 10);

            // Animate out and remove
            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transform = 'translateX(100%)';
                setTimeout(() => {
                    if (document.body.contains(toast)) {
                        document.body.removeChild(toast);
                    }
                }, 300);
            }, 3000);
        }

        // Auto-refresh reserve data every 5 minutes
        setInterval(function() {
            // You could implement partial refresh here if needed
            console.log('Reserve data refresh check');
        }, 300000);

        // Initialize tooltips and other interactive elements
        document.addEventListener('DOMContentLoaded', function() {
            // Add any initialization code here
            console.log('Rolling Reserves page loaded');
        });
    </script>
@endpush
