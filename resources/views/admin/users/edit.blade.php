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

                        {{-- Role Assignment --}}
                        <div class="col-span-1 sm:col-span-2">
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
                                                {{ $role->name === 'admin' ? 'Full system access and control' : 'Standard user capabilities' }}
                                            </span>
                                        </div>
                                        @if(old('role', $user->roles->first()?->id) == $role->id)
                                            <div class="absolute inset-0 border-2 border-indigo-500 rounded-lg pointer-events-none"></div>
                                        @endif
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
</x-app-layout>
