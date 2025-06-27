<x-app-layout>
    <x-slot name="header">
        <h2 class="text-2xl font-bold text-gray-800">
            {{ __('Edit User') }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white rounded-lg shadow-sm ring-1 ring-gray-900/5 p-6">
                <div class="mb-4">
                    <a href="{{ route('admin.users.index') }}" wire:navigate class="inline-flex items-center text-indigo-600 hover:text-indigo-900">
                        <svg class="size-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                        {{ __('Back to Users') }}
                    </a>
                </div>

                @if (session('error'))
                    <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-md relative" role="alert">
                        <span class="block sm:inline">{{ session('error') }}</span>
                    </div>
                @endif

                <form action="{{ route('admin.users.update', $user) }}" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        {{-- Name Field --}}
                        <div class="col-span-1 sm:col-span-2">
                            <label for="name" class="block text-sm font-medium text-gray-700">
                                {{ __('Full Name') }}
                            </label>
                            <input id="name"
                                   name="name"
                                   type="text"
                                   value="{{ old('name', $user->name) }}"
                                   class="mt-1 block w-full rounded-md border-gray-300 px-3 py-2 text-gray-900 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 sm:text-sm shadow-sm"
                                   placeholder="Enter user's full name"
                                   required />
                            @error('name')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Email Field --}}
                        <div class="col-span-1 sm:col-span-2">
                            <label for="email" class="block text-sm font-medium text-gray-700">
                                {{ __('Email Address') }}
                            </label>
                            <input id="email"
                                   name="email"
                                   type="email"
                                   value="{{ old('email', $user->email) }}"
                                   class="mt-1 block w-full rounded-md border-gray-300 px-3 py-2 text-gray-900 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 sm:text-sm shadow-sm"
                                   placeholder="Enter user's email address"
                                   required />
                            @error('email')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Password Field --}}
                        <div class="col-span-1 sm:col-span-2">
                            <label for="password" class="block text-sm font-medium text-gray-700">
                                {{ __('Password') }}
                            </label>
                            <input id="password"
                                   name="password"
                                   type="password"
                                   class="mt-1 block w-full rounded-md border-gray-300 px-3 py-2 text-gray-900 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 sm:text-sm shadow-sm"
                                   placeholder="Enter a new password (leave empty to keep current)" />
                            <p class="mt-1 text-sm text-gray-500">Leave blank to keep current password</p>
                            @error('password')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Password Confirmation Field (for merchant users) --}}
                        <div id="password-confirmation-field" class="col-span-1 sm:col-span-2 {{ old('user_type', $user->user_type) === 'merchant' ? '' : 'hidden' }}">
                            <label for="password_confirmation" class="block text-sm font-medium text-gray-700">
                                {{ __('Confirm Password') }}
                            </label>
                            <input id="password_confirmation"
                                   name="password_confirmation"
                                   type="password"
                                   class="mt-1 block w-full rounded-md border-gray-300 px-3 py-2 text-gray-900 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 sm:text-sm shadow-sm"
                                   placeholder="Confirm password" />
                            @error('password_confirmation')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- User Type Selection --}}
                        <div class="col-span-1 sm:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                {{ __('User Type') }}
                            </label>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <label class="relative flex items-center p-4 rounded-lg border cursor-pointer hover:bg-gray-50">
                                    <input type="radio"
                                           name="user_type"
                                           value="admin"
                                           {{ old('user_type', $user->user_type) === 'admin' || old('user_type', $user->user_type) === 'super-admin' ? 'checked' : '' }}
                                           class="h-4 w-4 text-indigo-600 border-gray-300 focus:ring-indigo-500"
                                           onchange="toggleUserTypeFields()">
                                    <div class="ml-4">
                                        <span class="block text-sm font-medium text-gray-900">
                                            {{ __('Admin User') }}
                                        </span>
                                        <span class="block text-sm text-gray-500">
                                            {{ __('Administrative access with role-based permissions') }}
                                        </span>
                                    </div>
                                </label>

                                <label class="relative flex items-center p-4 rounded-lg border cursor-pointer hover:bg-gray-50">
                                    <input type="radio"
                                           name="user_type"
                                           value="merchant"
                                           {{ old('user_type', $user->user_type) === 'merchant' ? 'checked' : '' }}
                                           class="h-4 w-4 text-indigo-600 border-gray-300 focus:ring-indigo-500"
                                           onchange="toggleUserTypeFields()">
                                    <div class="ml-4">
                                        <span class="block text-sm font-medium text-gray-900">
                                            {{ __('Merchant User') }}
                                        </span>
                                        <span class="block text-sm text-gray-500">
                                            {{ __('Access to merchant portal with merchant-specific permissions') }}
                                        </span>
                                    </div>
                                </label>
                            </div>
                            @error('user_type')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Merchant Selection (for merchant users) --}}
                        <div id="merchant-field" class="col-span-1 sm:col-span-2 {{ old('user_type', $user->user_type) === 'merchant' ? '' : 'hidden' }}">
                            <label for="merchant_id" class="block text-sm font-medium text-gray-700">
                                {{ __('Associated Merchant') }}
                            </label>
                            <select id="merchant_id"
                                    name="merchant_id"
                                    class="mt-1 block w-full rounded-md border-gray-300 px-3 py-2 text-gray-900 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 sm:text-sm shadow-sm">
                                <option value="">{{ __('Select Merchant') }}</option>
                                @foreach($merchants as $merchant)
                                    <option value="{{ $merchant->id }}" {{ old('merchant_id', $user->merchant_id) == $merchant->id ? 'selected' : '' }}>
                                        {{ $merchant->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('merchant_id')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Role Assignment (for admin users) --}}
                        <div id="role-field" class="col-span-1 sm:col-span-2 {{ old('user_type', $user->user_type) === 'merchant' ? 'hidden' : '' }}">
                            <label class="block text-sm font-medium text-gray-700">
                                {{ __('Role Assignment') }}
                            </label>
                            <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
                                @foreach($roles as $role)
                                    <label class="relative flex items-center p-4 rounded-lg border cursor-pointer hover:bg-gray-50">
                                        <input type="radio"
                                               name="role"
                                               value="{{ $role->id }}"
                                               {{ old('role', $user->roles->first()?->id) == $role->id ? 'checked' : '' }}
                                               class="h-4 w-4 text-indigo-600 border-gray-300 focus:ring-indigo-500">
                                        <div class="ml-4">
                                            <span class="block text-sm font-medium text-gray-900">
                                                {{ ucfirst($role->name) }}
                                            </span>
                                            <span class="block text-sm text-gray-500">
                                                {{ $role->name === 'admin' ? 'Full system access and control' :
                                                   ($role->name === 'super-admin' ? 'Complete access to all system functions' : 'Standard user capabilities') }}
                                            </span>
                                        </div>
                                    </label>
                                @endforeach
                            </div>
                            @error('role')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="flex justify-end mt-6">
                        <a href="{{ route('admin.users.index') }}"
                           wire:navigate
                           class="inline-flex items-center justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gray-500 mr-3">
                            {{ __('Cancel') }}
                        </a>
                        <button type="submit"
                                class="inline-flex items-center justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                            {{ __('Save Changes') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function toggleUserTypeFields() {
            const userType = document.querySelector('input[name="user_type"]:checked')?.value;
            const merchantField = document.getElementById('merchant-field');
            const roleField = document.getElementById('role-field');
            const passwordConfirmationField = document.getElementById('password-confirmation-field');

            if (userType === 'merchant') {
                merchantField.classList.remove('hidden');
                roleField.classList.add('hidden');
                passwordConfirmationField.classList.remove('hidden');

                // Make merchant_id required
                document.getElementById('merchant_id').setAttribute('required', 'required');

                // Remove required from role
                const roleInputs = document.querySelectorAll('input[name="role"]');
                roleInputs.forEach(input => input.removeAttribute('required'));
            } else {
                merchantField.classList.add('hidden');
                roleField.classList.remove('hidden');
                passwordConfirmationField.classList.add('hidden');

                // Remove required from merchant_id
                document.getElementById('merchant_id').removeAttribute('required');

                // Make role required
                const roleInputs = document.querySelectorAll('input[name="role"]');
                if (roleInputs.length > 0) {
                    roleInputs[0].setAttribute('required', 'required');
                }
            }
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            toggleUserTypeFields();
        });
    </script>
</x-app-layout>
