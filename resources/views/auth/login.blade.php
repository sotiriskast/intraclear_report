<x-guest-layout>
     <x-authentication-card>
        <x-slot name="logo">
            <x-authentication-card-logo class="w-72 h-36" />
        </x-slot>

        <x-validation-errors class="mb-4" />
         <x-flash-messages />
        @session('status')
        <div class="mb-4 font-medium text-sm text-green-600">
            {{ $value }}
        </div>
        @endsession

        <div class="mb-6">
            <h1 class="text-xl font-semibold text-gray-900 text-center">
                Sign in to your account
            </h1>
            <p class="mt-2 text-sm text-gray-600 text-center">
                Access either the merchant portal or admin dashboard
            </p>
        </div>

        <form method="POST" action="{{ route('login') }}">
            @csrf

            <div>
                <x-label for="email" value="{{ __('Email') }}" />
                <x-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            </div>

            <div class="mt-4">
                <x-label for="password" value="{{ __('Password') }}" />
                <x-input id="password" class="block mt-1 w-full" type="password" name="password" required autocomplete="current-password" />
            </div>

            <div class="block mt-4">
                <label for="remember_me" class="flex items-center">
                    <x-checkbox id="remember_me" name="remember" />
                    <span class="ms-2 text-sm text-gray-600">{{ __('Remember me') }}</span>
                </label>
            </div>

            <div class="flex items-center justify-end mt-4">
                @if (Route::has('password.request'))
                    <a class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-hidden focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" href="{{ route('password.request') }}">
                        {{ __('Forgot your password?') }}
                    </a>
                @endif

                <x-button class="ms-4">
                    {{ __('Log in') }}
                </x-button>
            </div>
        </form>

        <div class="mt-8 pt-6 border-t border-gray-200">
            <div class="flex items-center">
                <div class="w-full border-t border-gray-300"></div>
                <div class="px-4 text-sm text-gray-500">Information</div>
                <div class="w-full border-t border-gray-300"></div>
            </div>

            <div class="mt-6 bg-gray-50 rounded-lg p-4">
                <div class="flex space-x-3">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-blue-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="text-sm text-gray-700">
                        <p>You'll be automatically redirected to the appropriate portal based on your account type:</p>
                        <ul class="list-disc mt-2 ml-5 space-y-1">
                            <li>Merchant users → Merchant Portal</li>
                            <li>Admin users → Admin Dashboard</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </x-authentication-card>
</x-guest-layout>
