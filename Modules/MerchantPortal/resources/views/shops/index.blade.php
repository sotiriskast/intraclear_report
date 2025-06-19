@extends('merchantportal::layouts.master')

@section('title', 'Shops')
@section('page-title', 'Shops')
@section('page-subtitle', 'Manage your online stores and configurations')

@section('breadcrumb')
    <li class="flex items-center">
        <a href="{{ route('merchant.dashboard') }}" class="text-gray-400 hover:text-gray-500">Dashboard</a>
        <i class="fas fa-chevron-right mx-2 text-gray-300"></i>
    </li>
    <li class="text-gray-900">Shops</li>
@endsection

@section('content')
    <!-- Actions Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6">
        <div class="flex-1">
            <!-- Search -->
            <div class="max-w-lg">
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-search text-gray-400"></i>
                    </div>
                    <input type="text"
                           class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                           placeholder="Search shops...">
                </div>
            </div>
        </div>

        <div class="mt-4 sm:mt-0 sm:ml-4">
            <a href="{{ route('merchant.shops.create') }}"
               class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-150">
                <i class="fas fa-plus mr-2"></i>
                Add New Shop
            </a>
        </div>
    </div>

    <!-- Shops Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse($shops ?? [] as $shop)
            <div class="bg-white overflow-hidden shadow rounded-lg hover:shadow-lg transition-shadow duration-150">
                <!-- Shop Header -->
                <div class="px-6 py-4 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                @if($shop->logo)
                                    <img class="h-10 w-10 rounded-lg object-cover" src="{{ $shop->logo }}" alt="{{ $shop->name }}">
                                @else
                                    <div class="h-10 w-10 rounded-lg bg-gradient-to-r from-blue-500 to-purple-600 flex items-center justify-center">
                                        <span class="text-sm font-medium text-white">{{ substr($shop->name, 0, 2) }}</span>
                                    </div>
                                @endif
                            </div>
                            <div class="ml-3">
                                <h3 class="text-lg font-medium text-gray-900 truncate">
                                    {{ $shop->name }}
                                </h3>
                                <p class="text-sm text-gray-500 truncate">
                                    {{ $shop->domain ?? 'No domain set' }}
                                </p>
                            </div>
                        </div>

                        <!-- Status Badge -->
                        @php
                            $statusClasses = [
                                'active' => 'bg-green-100 text-green-800',
                                'inactive' => 'bg-red-100 text-red-800',
                                'pending' => 'bg-yellow-100 text-yellow-800'
                            ];
                            $status = $shop->status ?? 'pending';
                            $classes = $statusClasses[$status] ?? 'bg-gray-100 text-gray-800';
                        @endphp
                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full {{ $classes }}">
                            {{ ucfirst($status) }}
                        </span>
                    </div>
                </div>

                <!-- Shop Stats -->
                <div class="px-6 py-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Transactions</dt>
                            <dd class="mt-1 text-2xl font-semibold text-gray-900">{{ $shop->transactions_count ?? 0 }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Revenue</dt>
                            <dd class="mt-1 text-2xl font-semibold text-gray-900">${{ number_format($shop->total_revenue ?? 0, 0) }}</dd>
                        </div>
                    </div>

                    <!-- Last Transaction -->
                    <div class="mt-4 pt-4 border-t border-gray-200">
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-gray-500">Last transaction:</span>
                            <span class="font-medium text-gray-900">
                                {{ $shop->last_transaction_at ? $shop->last_transaction_at->diffForHumans() : 'Never' }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Shop Actions -->
                <div class="px-6 py-3 bg-gray-50 border-t border-gray-200">
                    <div class="flex items-center justify-between">
                        <div class="flex space-x-3">
                            <a href="{{ route('merchant.shops.show', $shop->id) }}"
                               class="text-blue-600 hover:text-blue-900 text-sm font-medium transition-colors duration-150">
                                View Details
                            </a>
                            <a href="{{ route('merchant.shops.edit', $shop->id) }}"
                               class="text-gray-600 hover:text-gray-900 text-sm font-medium transition-colors duration-150">
                                Edit
                            </a>
                        </div>

                        <!-- Dropdown Menu -->
                        <div class="relative">
                            <button type="button"
                                    class="text-gray-400 hover:text-gray-600 transition-colors duration-150"
                                    onclick="toggleDropdown('shop-menu-{{ $shop->id }}')">
                                <i class="fas fa-ellipsis-v"></i>
                            </button>

                            <div id="shop-menu-{{ $shop->id }}"
                                 class="hidden absolute right-0 z-10 mt-2 w-48 bg-white rounded-md shadow-lg ring-1 ring-black ring-opacity-5">
                                <div class="py-1">
                                    <a href="{{ route('merchant.shops.transactions', $shop->id) }}"
                                       class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                        <i class="fas fa-credit-card mr-3"></i>
                                        View Transactions
                                    </a>
                                    <a href="{{ route('merchant.shops.settings', $shop->id) }}"
                                       class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                        <i class="fas fa-cog mr-3"></i>
                                        Settings
                                    </a>
                                    <button type="button"
                                            onclick="toggleShopStatus('{{ $shop->id }}', '{{ $shop->status }}')"
                                            class="flex items-center w-full px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                        <i class="fas fa-{{ $shop->status === 'active' ? 'pause' : 'play' }} mr-3"></i>
                                        {{ $shop->status === 'active' ? 'Deactivate' : 'Activate' }}
                                    </button>
                                    <div class="border-t border-gray-100"></div>
                                    <button type="button"
                                            onclick="confirmDelete('{{ $shop->id }}', '{{ $shop->name }}')"
                                            class="flex items-center w-full px-4 py-2 text-sm text-red-700 hover:bg-red-50">
                                        <i class="fas fa-trash mr-3"></i>
                                        Delete Shop
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <!-- Empty State -->
            <div class="col-span-full">
                <div class="text-center py-12">
                    <div class="mx-auto h-24 w-24 text-gray-400">
                        <i class="fas fa-store text-6xl"></i>
                    </div>
                    <h3 class="mt-4 text-lg font-medium text-gray-900">No shops yet</h3>
                    <p class="mt-2 text-sm text-gray-500">
                        Get started by creating your first online shop.
                    </p>
                    <div class="mt-6">
                        <a href="{{ route('merchant.shops.create') }}"
                           class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            <i class="fas fa-plus mr-2"></i>
                            Create Your First Shop
                        </a>
                    </div>
                </div>
            </div>
        @endforelse
    </div>

    <!-- Quick Setup Guide (for new merchants) -->
    @if(($shops ?? collect())->count() === 0)
        <div class="mt-8 bg-blue-50 border border-blue-200 rounded-lg p-6">
            <div class="flex items-start">
                <div class="flex-shrink-0">
                    <i class="fas fa-lightbulb text-blue-600 text-xl"></i>
                </div>
                <div class="ml-4">
                    <h3 class="text-lg font-medium text-blue-900">Getting Started</h3>
                    <div class="mt-2 text-sm text-blue-700">
                        <p class="mb-3">Follow these steps to set up your first shop:</p>
                        <ol class="list-decimal list-inside space-y-1">
                            <li>Create a new shop with your business details</li>
                            <li>Configure payment methods and currencies</li>
                            <li>Set up your domain and branding</li>
                            <li>Test your integration with our API</li>
                            <li>Go live and start accepting payments!</li>
                        </ol>
                    </div>
                    <div class="mt-4">
                        <a href="{{ route('merchant.shops.create') }}"
                           class="inline-flex items-center px-3 py-2 border border-blue-300 shadow-sm text-sm leading-4 font-medium rounded-md text-blue-700 bg-white hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            Get Started
                            <i class="fas fa-arrow-right ml-2"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
    <script>
        // Toggle dropdown menu
        function toggleDropdown(menuId) {
            const menu = document.getElementById(menuId);
            const allMenus = document.querySelectorAll('[id^="shop-menu-"]');

            // Close all other menus
            allMenus.forEach(m => {
                if (m.id !== menuId) {
                    m.classList.add('hidden');
                }
            });

            // Toggle current menu
            menu.classList.toggle('hidden');
        }

        // Close dropdowns when clicking outside
        document.addEventListener('click', function(event) {
            const allMenus = document.querySelectorAll('[id^="shop-menu-"]');
            const clickedButton = event.target.closest('button[onclick*="toggleDropdown"]');

            if (!clickedButton) {
                allMenus.forEach(menu => menu.classList.add('hidden'));
            }
        });

        // Toggle shop status
        function toggleShopStatus(shopId, currentStatus) {
            const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
            const action = newStatus === 'active' ? 'activate' : 'deactivate';

            if (confirm(`Are you sure you want to ${action} this shop?`)) {
                // Here you would make an AJAX call to update the shop status
                fetch(`/merchant/shops/${shopId}/status`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ status: newStatus })
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            location.reload(); // Refresh to show updated status
                        } else {
                            alert('Failed to update shop status');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('An error occurred while updating shop status');
                    });
            }
        }

        // Confirm delete
        function confirmDelete(shopId, shopName) {
            if (confirm(`Are you sure you want to delete "${shopName}"? This action cannot be undone.`)) {
                // Here you would make an AJAX call to delete the shop
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = `/merchant/shops/${shopId}`;

                const methodInput = document.createElement('input');
                methodInput.type = 'hidden';
                methodInput.name = '_method';
                methodInput.value = 'DELETE';

                const tokenInput = document.createElement('input');
                tokenInput.type = 'hidden';
                tokenInput.name = '_token';
                tokenInput.value = document.querySelector('meta[name="csrf-token"]').content;

                form.appendChild(methodInput);
                form.appendChild(tokenInput);
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Search functionality
        const searchInput = document.querySelector('input[placeholder="Search shops..."]');
        if (searchInput) {
            searchInput.addEventListener('input', debounce(function(e) {
                const searchTerm = e.target.value.toLowerCase();
                const shopCards = document.querySelectorAll('.grid > div:not(.col-span-full)');

                shopCards.forEach(card => {
                    const shopName = card.querySelector('h3').textContent.toLowerCase();
                    const shopDomain = card.querySelector('p').textContent.toLowerCase();

                    if (shopName.includes(searchTerm) || shopDomain.includes(searchTerm)) {
                        card.style.display = 'block';
                    } else {
                        card.style.display = 'none';
                    }
                });
            }, 300));
        }

        // Debounce function
        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }
    </script>
@endpush
