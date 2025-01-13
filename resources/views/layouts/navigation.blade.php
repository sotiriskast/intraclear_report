<div class="lg:fixed lg:flex lg:w-64 lg:flex-col">
    <!-- Sidebar for desktop -->
    <div class="hidden lg:flex lg:flex-col lg:w-64 lg:fixed lg:inset-y-0">
        <div class="flex flex-col flex-grow pt-5 overflow-y-auto bg-indigo-700">
            <div class="flex items-center flex-shrink-0 px-4">
                <span class="text-xl font-semibold text-white">{{ config('app.name', 'Laravel') }}</span>
            </div>
            <nav class="flex-1 px-2 mt-5 space-y-1">
                <x-nav-link href="{{ route('dashboard') }}" :active="request()->routeIs('dashboard')" class="group">
                    <svg class="mr-3 h-6 w-6 {{ request()->routeIs('dashboard') ? 'text-white' : 'text-indigo-300' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                    {{ __('Dashboard') }}
                </x-nav-link>

                @if (Auth::user()->hasRole('super-admin'))
                    <x-nav-link href="{{ route('admin.users') }}" :active="request()->routeIs('admin.users')" class="group">
                        <svg class="mr-3 h-6 w-6 {{ request()->routeIs('admin.users') ? 'text-white' : 'text-indigo-300' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                        {{ __('Users') }}
                    </x-nav-link>

                    <x-nav-link href="{{ route('admin.roles') }}" :active="request()->routeIs('admin.roles')" class="group">
                        <svg class="mr-3 h-6 w-6 {{ request()->routeIs('admin.roles') ? 'text-white' : 'text-indigo-300' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                        {{ __('Roles') }}
                    </x-nav-link>
                @endif
            </nav>
        </div>
    </div>

    <!-- Mobile menu -->
    <div x-show="sidebarOpen" class="relative z-40 lg:hidden">
        <div class="fixed inset-0 bg-gray-600 bg-opacity-75"></div>

        <div class="fixed inset-0 z-40 flex">
            <div class="relative flex-1 flex flex-col max-w-xs w-full bg-indigo-700">
                <div class="absolute top-0 right-0 -mr-12 pt-2">
                    <button @click="sidebarOpen = false" class="ml-1 flex items-center justify-center h-10 w-10 rounded-full focus:outline-none focus:ring-2 focus:ring-inset focus:ring-white">
                        <span class="sr-only">Close sidebar</span>
                        <svg class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="flex-1 h-0 pt-5 pb-4 overflow-y-auto">
                    <div class="flex-shrink-0 flex items-center px-4">
                        <span class="text-xl font-semibold text-white">{{ config('app.name', 'Laravel') }}</span>
                    </div>
                    <nav class="mt-5 px-2 space-y-1">
                        <x-responsive-nav-link href="{{ route('dashboard') }}" :active="request()->routeIs('dashboard')">
                            {{ __('Dashboard') }}
                        </x-responsive-nav-link>

                        @if (Auth::user()->hasRole('super-admin'))
                            <x-responsive-nav-link href="{{ route('admin.users') }}" :active="request()->routeIs('admin.users')">
                                {{ __('Users') }}
                            </x-responsive-nav-link>

                            <x-responsive-nav-link href="{{ route('admin.roles') }}" :active="request()->routeIs('admin.roles')">
                                {{ __('Roles') }}
                            </x-responsive-nav-link>
                        @endif
                    </nav>
                </div>
            </div>
        </div>
    </div>
</div>
