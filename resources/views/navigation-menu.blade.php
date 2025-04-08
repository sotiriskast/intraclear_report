<div class="flex flex-col h-full">
    <!-- Logo Section -->
    <div class="flex items-center justify-between h-16 px-4 border-b border-zinc-200">
        <a href="{{ route('admin.dashboard') }}" class="flex items-center">
            <x-authentication-card-logo class="w-72 h-16"/>
        </a>
    </div>
    @inject('navigationService', 'App\Services\NavigationService')

    <nav class="flex-1 overflow-y-auto px-4 py-4 space-y-2">
        @php
            $user = auth()->user();
            $navigation = $navigationService->filterNavigation($user);
        @endphp

        @foreach($navigation as $key => $item)

            @if(auth()->user()->two_factor_secret)
                @if(isset($item['route']))
                    {{-- Simple top-level link --}}
                    <x-nav-link
                        href="{{ route($item['route']) }}"
                        wire:click="$dispatch('closeSidebar')"
                        :active="request()->routeIs($item['route'])"
                        class="flex items-center px-3 py-2 rounded-md text-sm font-medium transition-colors"
                    >
                        <x-icon name="{{ $item['icon'] }}" class="mr-3 h-5 w-5"/>
                        <span>{{ __($item['label']) }}</span>
                    </x-nav-link>
                @elseif(isset($item['children']))
                    {{-- Dropdown navigation --}}
                    <div
                        x-data="{
                    open: {{ collect($item['children'])->contains(fn($child) => request()->routeIs($child['route'] ?? '')) ? 'true' : 'false' }}
                }"
                        class="relative"
                    >
                        <x-nav-link
                            @click="open = !open;$dispatch('closeSidebar')"
                            class="w-full flex items-center justify-between px-3 py-2 rounded-md text-sm font-medium transition-colors cursor-pointer
                           {{ collect($item['children'])->contains(fn($child) => request()->routeIs($child['route'] ?? ''))
                                ? 'bg-zinc-100 text-zinc-900'
                                : 'text-zinc-600 hover:bg-zinc-50' }}"

                        >
                            <div class="flex items-center">
                                <x-icon name="{{ $item['icon'] }}" class="mr-3 h-5 w-5"/>
                                <span>{{ __($item['label']) }}</span>
                            </div>
                            <x-icon
                                name="{{ 'chevron-' . (app()->getLocale() === 'ar' ? 'left' : 'right') }}"
                                x-show="!open"
                                class="h-4 w-4"
                            />
                            <x-icon
                                name="{{ 'chevron-' . (app()->getLocale() === 'ar' ? 'right' : 'down') }}"
                                x-show="open"
                                class="h-4 w-4"
                            />
                        </x-nav-link>

                        <div
                            x-show="open"
                            x-transition:enter="transition ease-out duration-200"
                            x-transition:enter-start="opacity-0 translate-y--2"
                            x-transition:enter-end="opacity-100 translate-y-0"
                            x-transition:leave="transition ease-in duration-150"
                            x-transition:leave-start="opacity-100 translate-y-0"
                            x-transition:leave-end="opacity-0 translate-y--2"
                            class="pl-6 space-y-1 mt-1"
                        >
                            @foreach($item['children'] as $child)
                                <x-nav-link
                                    href="{{ route($child['route']) }}"
                                    :active="request()->routeIs($child['route'])"
                                    class="block px-3 py-2 rounded-md text-sm font-medium transition-colors"
                                    wire:navigate.hover
                                >
                                    <span>{{ __($child['label']) }}</span>
                                </x-nav-link>
                            @endforeach
                        </div>
                    </div>
                @endif
            @endif

        @endforeach
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit"
                    class="w-full flex items-center justify-between px-3 py-2 rounded-md text-sm font-medium transition-colors">
                <div class="flex items-center">
                    <x-icon name="logout" class="mr-3 h-5 w-5"/>
                    <span>{{ __('Logout') }}</span>
                </div>
            </button>
        </form>
    </nav>
</div>
