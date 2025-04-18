<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet"/>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta http-equiv="Content-Security-Policy" content="upgrade-insecure-requests">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- Styles -->
    @livewireStyles
</head>
<body class="font-sans antialiased">
<div
    x-data="{
        sidebarOpen: localStorage.getItem('sidebarOpen') === 'true',
        toggleSidebar() {
            this.sidebarOpen = !this.sidebarOpen;
            localStorage.setItem('sidebarOpen', this.sidebarOpen);
        },
        closeSidebar() {
            this.sidebarOpen = false;
            localStorage.setItem('sidebarOpen', this.sidebarOpen);
        },
        initSidebar() {
            if (window.innerWidth >= 1024) {
                this.sidebarOpen = localStorage.getItem('sidebarOpen') !== 'false';
            } else {
                this.sidebarOpen = false;
            }
        }
    }"
    x-init="initSidebar()"
    class="min-h-svh bg-zinc-100 flex"
>
    <!-- Sidebar Backdrop (Mobile/Tablet only) -->
    <div
        x-show="sidebarOpen && window.innerWidth < 1024"
        x-transition:enter="transition-opacity ease-linear duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition-opacity ease-linear duration-300"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        @click="closeSidebar()"
        class="fixed inset-0 bg-zinc-600/75 z-40 lg:hidden"
    ></div>

    <!-- Sidebar -->
    <aside
        class="fixed top-0 left-0 z-40 h-full bg-white border-r border-zinc-200 w-64 transform transition-transform duration-300 ease-in-out lg:z-20"
        :class="{
            '-translate-x-full': !sidebarOpen,
            'translate-x-0': sidebarOpen
        }"
    >
        <!-- Close Button (visible only on mobile) -->
        <div class="flex items-center justify-between h-16 px-4 border-b border-zinc-200 lg:hidden">
            <button
                @click="closeSidebar()"
                class="text-zinc-500 hover:text-zinc-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 rounded-md p-1"
            >
                <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M6 18L18 6M6 6l12 12"
                    />
                </svg>
            </button>
        </div>
        @livewire('navigation-menu')
    </aside>

    <!-- Main Content -->
    <main
        :class="{
            'lg:ml-64': sidebarOpen,
            'lg:ml-0': !sidebarOpen
        }"
        class="flex-1 transition-all duration-300 lg:ml-64"
    >
        <div class="sticky top-0 z-30 bg-white shadow-sm">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex items-center h-16">
                <!-- Toggle Button -->
                <button
                    @click="toggleSidebar()"
                    class="inline-flex items-center justify-center p-2 rounded-md text-zinc-500 hover:text-zinc-900 hover:bg-zinc-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:bg-zinc-100 focus-visible:text-zinc-900 transition-all duration-200"
                >
                    <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M4 6h16M4 12h16M4 18h16"
                        />
                    </svg>
                </button>

                <!-- Page Title -->
                @hasSection('header')
                    <div class="ml-4">
                        @yield('header')
                    </div>
                @else
                    <div class="ml-4">
                        @if (isset($header) && is_string($header))
                            <h2 class="text-2xl font-bold text-zinc-800">{{ $header }}</h2>
                        @elseif (isset($header))
                            {{ $header }}
                        @endif
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
@stack('scripts')
</body>
</html>
