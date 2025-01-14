<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- Styles -->
    @livewireStyles
</head>
<body class="font-sans antialiased">
<div x-data="{
                sidebarOpen: localStorage.getItem('sidebarOpen') === 'true',
                toggleSidebar() {
                    this.sidebarOpen = !this.sidebarOpen;
                    localStorage.setItem('sidebarOpen', this.sidebarOpen);
                }
            }"
     class="min-h-screen bg-gray-100">

    <!-- Mobile backdrop -->
    <div x-show="sidebarOpen"
         x-transition:enter="transition-opacity ease-linear duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-linear duration-300"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click="sidebarOpen = false"
         class="fixed inset-0 bg-gray-600 bg-opacity-75 z-20 md:hidden">
    </div>

    <!-- Sidebar -->
    <aside class="fixed top-0 left-0 z-40 h-full bg-white border-r border-gray-200 transform transition-transform duration-300 ease-in-out w-64"
           :class="{'translate-x-0': sidebarOpen, '-translate-x-full': !sidebarOpen}">
        @livewire('navigation-menu')
    </aside>

    <!-- Main Content -->
    <main class="transition-all duration-300"
          :class="{'pl-64': sidebarOpen, 'pl-0': !sidebarOpen}">
        <!-- Header with burger button -->
        <div class="relative bg-white shadow">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex items-center h-16">
                <!-- Toggle Button -->
                <button @click="toggleSidebar()"
                        class="inline-flex items-center justify-center p-2 rounded-md text-gray-500 hover:text-gray-900 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 focus:text-gray-900 transition-all duration-200">
                    <svg class="w-6 h-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': !sidebarOpen, 'inline-flex': sidebarOpen }"
                              stroke-linecap="round"
                              stroke-linejoin="round"
                              stroke-width="2"
                              d="M6 18L18 6M6 6l12 12" />
                        <path :class="{'hidden': sidebarOpen, 'inline-flex': !sidebarOpen }"
                              stroke-linecap="round"
                              stroke-linejoin="round"
                              stroke-width="2"
                              d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>

                <!-- Page Title (if header exists) -->
                @if (isset($header))
                    <div class="ml-4">
                        {{ $header }}
                    </div>
                @endif
            </div>
        </div>

        <!-- Page Content -->
        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                {{ $slot }}
            </div>
        </div>
    </main>
</div>

@stack('modals')
@livewireScripts
</body>
</html>
