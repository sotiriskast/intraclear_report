<div class="flex flex-col h-full">
    <!-- Logo Section -->
    <div class="flex items-center justify-between h-16 px-4 border-b border-gray-200">
        <a href="{{ route('dashboard') }}" class="flex items-center">
            <x-application-mark class="block h-9 w-auto" />
            <span class="ml-2 text-xl font-semibold text-gray-900">
                {{ config('app.name') }}
            </span>
        </a>
    </div>

    <!-- Navigation Links -->
    <nav class="flex-1 overflow-y-auto px-4 py-4 space-y-2">
        <x-nav-link href="{{ route('dashboard') }}"
                    :active="request()->routeIs('dashboard')"
                    class="flex items-center px-3 py-2 rounded-md text-sm font-medium transition-colors">
            <svg class="mr-3 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
            </svg>
            <span>{{ __('Dashboard') }}</span>
        </x-nav-link>

        @if(auth()->user()->hasRole('admin'))
            <x-nav-link href="{{ route('admin.users') }}"
                        :active="request()->routeIs('admin.users')"
                        class="flex items-center px-3 py-2 rounded-md text-sm font-medium transition-colors">
                <svg class="mr-3 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                </svg>
                <span>{{ __('User Management') }}</span>
            </x-nav-link>
        @endif

        @if(auth()->user()->hasRole('super-admin'))
            <x-nav-link href="{{ route('admin.users') }}"
                        :active="request()->routeIs('admin.users')"
                        class="flex items-center px-3 py-2 rounded-md text-sm font-medium transition-colors">
                <svg class="mr-3 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                </svg>
                <span>{{ __('User Management') }}</span>
            </x-nav-link>
            <x-nav-link href="{{ route('admin.roles') }}"
                        :active="request()->routeIs('admin.roles')"
                        class="flex items-center px-3 py-2 rounded-md text-sm font-medium transition-colors">
                <svg class="mr-3 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
                <span>{{ __('Roles') }}</span>
            </x-nav-link>
        @endif
    </nav>

    <!-- Profile Section -->
    <div class="border-t border-gray-200 p-4">
        <div x-data="{ open: false }" @click.away="open = false" class="relative">
            <button @click="open = !open"
                    class="flex items-center w-full px-3 py-2 text-sm text-left text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-md focus:outline-none transition-colors">
                <div class="flex items-center flex-1">
                    @if (Laravel\Jetstream\Jetstream::managesProfilePhotos())
                        <img class="h-8 w-8 rounded-full object-cover"
                             src="{{ Auth::user()->profile_photo_url }}"
                             alt="{{ Auth::user()->name }}" />
                    @endif
                    <div class="ml-3">
                        <p class="text-sm font-medium text-gray-700">{{ Auth::user()->name }}</p>
                        <p class="text-xs text-gray-500">{{ Auth::user()->email }}</p>
                    </div>
                </div>
                <svg class="ml-2 h-4 w-4 transform transition-transform duration-200"
                     :class="{'rotate-180': open}"
                     fill="currentColor"
                     viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                </svg>
            </button>

            <!-- Profile Dropdown -->
            <div x-show="open"
                 x-transition:enter="transition ease-out duration-100"
                 x-transition:enter-start="transform opacity-0 scale-95"
                 x-transition:enter-end="transform opacity-100 scale-100"
                 x-transition:leave="transition ease-in duration-75"
                 x-transition:leave-start="transform opacity-100 scale-100"
                 x-transition:leave-end="transform opacity-0 scale-95"
                 class="absolute bottom-full left-0 w-full mb-1 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5">
                <div class="py-1">
                    <a href="{{ route('profile.show') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                        {{ __('Profile') }}
                    </a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
                                onclick="event.preventDefault(); this.closest('form').submit();">
                            {{ __('Log Out') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
