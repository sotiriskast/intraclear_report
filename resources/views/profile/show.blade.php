<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Profile') }}
        </h2>
    </x-slot>

    <div>
        <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8">
            @if (Laravel\Fortify\Features::canUpdateProfileInformation())
                @if(auth()->user()->two_factor_secret)
                    @livewire('profile.update-profile-information-form')
                    <x-section-border />
                @endif
            @endif

            @if (Laravel\Fortify\Features::enabled(Laravel\Fortify\Features::updatePasswords()))
                    @if(auth()->user()->two_factor_secret)
                        <div class="mt-10 sm:mt-0">
                            @livewire('profile.update-password-form')
                        </div>

                        <x-section-border />
                    @endif

            @endif

            @if (Laravel\Fortify\Features::canManageTwoFactorAuthentication())
                <div class="mt-10 sm:mt-0">
                    @livewire('profile.two-factor-authentication-form')
                </div>

                <x-section-border />
            @endif

            <div class="mt-10 sm:mt-0">
                @if(auth()->user()->two_factor_secret)
                    @livewire('profile.logout-other-browser-sessions-form')
                    <x-section-border />
                @endif
            </div>

{{--            @if (Laravel\Jetstream\Jetstream::hasAccountDeletionFeatures())--}}
{{--                <x-section-border />--}}

{{--                <div class="mt-10 sm:mt-0">--}}
{{--                    @livewire('profile.delete-user-form')--}}
{{--                </div>--}}
{{--            @endif--}}
        </div>
    </div>
</x-app-layout>
