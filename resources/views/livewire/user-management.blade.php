<div>
    {{-- Header --}}
    <x-slot name="header">
        <h2 class="text-2xl font-bold text-gray-800">
            {{ __('User Management') }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            {{-- Flash Messages --}}
            <div class="space-y-4">
                @if (session()->has('message'))
                    <div class="flex p-4 bg-green-50 rounded-lg border border-green-200"
                         x-data="{ show: true }"
                         x-show="show"
                         x-transition.duration.300ms>
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div class="ml-3 flex-1">
                            <p class="text-sm font-medium text-green-800">{{ session('message') }}</p>
                        </div>
                        <button @click="show = false" class="ml-auto">
                            <svg class="h-5 w-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                @endif

                @if (session()->has('error'))
                    <div class="flex p-4 bg-red-50 rounded-lg border border-red-200"
                         x-data="{ show: true }"
                         x-show="show"
                         x-transition.duration.300ms>
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div class="ml-3 flex-1">
                            <p class="text-sm font-medium text-red-800">{{ session('error') }}</p>
                        </div>
                        <button @click="show = false" class="ml-auto">
                            <svg class="h-5 w-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                @endif
            </div>

            {{-- Add New User Button --}}
            <div class="mt-6 mb-4">
                <x-button wire:click="$set('showCreateModal', true)"
                          class="flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    {{ __('Add New User') }}
                </x-button>
            </div>

            {{-- Users List --}}
            <div class="bg-white rounded-lg shadow">
                <div class="block lg:hidden">
                    @foreach($users as $user)
                        <div class="border-b last:border-b-0 px-4 py-4 hover:bg-gray-50">
                            {{-- User Info --}}
                            <div class="flex items-center mb-2">
                                <div class="flex-shrink-0 h-10 w-10 mr-4">
                                    <div class="h-10 w-10 rounded-full bg-indigo-100 flex items-center justify-center">
                                        <span class="text-indigo-700 font-medium text-sm">
                                            {{ substr($user->name, 0, 2) }}
                                        </span>
                                    </div>
                                </div>
                                <div class="flex-1">
                                    <p class="text-sm font-medium text-gray-900">{{ $user->name }}</p>
                                    <p class="text-xs text-gray-500">{{ $user->email }}</p>
                                </div>
                                {{-- Action Buttons --}}
                                <div class="flex space-x-2">
                                    <button wire:click="editUser({{ $user->id }})"
                                            class="text-gray-600 hover:text-indigo-600">
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </button>
                                    <button wire:click="deleteUser({{ $user->id }})"
                                            wire:confirm="Are you sure you want to delete this user?"
                                            class="text-red-600 hover:text-red-800">
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                            {{-- User Roles --}}
                            <div class="mt-2">
                                <div class="flex flex-wrap gap-1">
                                    @foreach($user->roles as $role)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                                                   {{ $role->name === 'admin' ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800' }}">
                                            {{ $role->name }}
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Desktop View Table --}}
                <div class="hidden lg:block overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        {{-- Table Header --}}
                        <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                User Details
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider hidden sm:table-cell">
                                Roles
                            </th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Actions
                            </th>
                        </tr>
                        </thead>
                        {{-- Table Body --}}
                        <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($users as $user)
                            <tr class="hover:bg-gray-50">
                                {{-- User Details --}}
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10">
                                            <div class="h-10 w-10 rounded-full bg-indigo-100 flex items-center justify-center">
                                                    <span class="text-indigo-700 font-medium text-sm">
                                                        {{ substr($user->name, 0, 2) }}
                                                    </span>
                                            </div>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900">{{ $user->name }}</div>
                                            <div class="text-sm text-gray-500">{{ $user->email }}</div>
                                        </div>
                                    </div>
                                </td>
                                {{-- User Roles --}}
                                <td class="px-6 py-4 whitespace-nowrap hidden sm:table-cell">
                                    <div class="flex flex-wrap gap-2">
                                        @foreach($user->roles as $role)
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                           {{ $role->name === 'admin' ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800' }}">
                                                    {{ $role->name }}
                                                </span>
                                        @endforeach
                                    </div>
                                </td>
                                {{-- Action Buttons --}}
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex justify-end gap-2">
                                        <button wire:click="$dispatch('edit-user', { userId: {{ $user->id }} })"
                                                class="text-gray-600 hover:text-indigo-600">
                                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                      d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                        </button>
                                        <button wire:click="$dispatch('delete-user', { userId: {{ $user->id }} })"
                                                wire:confirm="Are you sure you want to delete this user?"
                                                class="text-red-600 hover:text-red-800">
                                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                      d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Pagination --}}
                <div class="px-6 py-4 border-t border-gray-200">
                    {{ $users->links() }}
                </div>
            </div>

            {{-- Create/Edit Modal --}}
            <x-dialog-modal wire:model.live="showCreateModal" max-width="2xl">
                <x-slot name="title">
                    <div class="flex items-center">
                        <div class="mr-3 rounded-full bg-indigo-100 p-2">
                            <svg class="h-6 w-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="{{ $isEditing ? 'M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z' : 'M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z' }}" />
                            </svg>
                        </div>
                        <h3 class="text-lg font-medium text-gray-900">
                            {{ $isEditing ? 'Edit User Details' : 'Create New User' }}
                        </h3>
                    </div>
                </x-slot>

                <x-slot name="content">
                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        {{-- Name Field --}}
                        <div class="col-span-1 sm:col-span-2">
                            <x-label for="name" value="{{ __('Full Name') }}" />
                            <x-input id="name" type="text" class="mt-1 block w-full" wire:model="name" placeholder="Enter user's full name" />
                            <x-input-error for="name" class="mt-2" />
                        </div>

                        {{-- Email Field --}}
                        <div class="col-span-1 sm:col-span-2">
                            <x-label for="email" value="{{ __('Email Address') }}" />
                            <x-input id="email" type="email" class="mt-1 block w-full" wire:model="email" placeholder="Enter user's email address" />
                            <x-input-error for="email" class="mt-2" />
                        </div>

                        {{-- Password Field (Only for Create) --}}
                        @if(!$isEditing)
                            <div class="col-span-1 sm:col-span-2">
                                <x-label for="password" value="{{ __('Password') }}" />
                                <x-input id="password" type="password" class="mt-1 block w-full" wire:model="password" placeholder="Enter a strong password" />
                                <x-input-error for="password" class="mt-2" />
                            </div>
                        @endif

                        {{-- Role Assignment --}}
                        <div class="col-span-1 sm:col-span-2">
                            <x-label value="{{ __('Role Assignment') }}" />
                            <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
                                @foreach($roles as $role)
                                    <label class="relative flex items-center p-4 rounded-lg border cursor-pointer hover:bg-gray-50">
                                        <input type="radio"
                                               wire:model="selectedRole"
                                               value="{{ $role->id }}"
                                               class="h-4 w-4 text-indigo-600 border-gray-300 focus:ring-indigo-500">
                                        <div class="ml-4">
                                            <span class="block text-sm font-medium text-gray-900">
                                                {{ ucfirst($role->name) }}
                                            </span>
                                            <span class="block text-sm text-gray-500">
                                                {{ $role->name === 'admin' ? 'Full system access and control' : 'Standard user capabilities' }}
                                            </span>
                                        </div>
                                        @if($selectedRole == $role->id)
                                            <div class="absolute inset-0 border-2 border-indigo-500 rounded-lg pointer-events-none"></div>
                                        @endif
                                    </label>
                                @endforeach
                            </div>
                            <x-input-error for="selectedRole" class="mt-2" />
                        </div>
                    </div>
                </x-slot>

                <x-slot name="footer">
                    <div class="flex justify-end space-x-3">
                        <x-secondary-button wire:click="resetForm" wire:loading.attr="disabled">
                            {{ __('Cancel') }}
                        </x-secondary-button>

                        @if($isEditing)
                            <x-button wire:click="$dispatch('update-user')"
                                      wire:loading.attr="disabled"
                                      class="bg-indigo-600 hover:bg-indigo-700">
                <span wire:loading.remove>
                    {{ __('Save Changes') }}
                </span>
                                <span wire:loading>
                    {{ __('Processing...') }}
                </span>
                            </x-button>
                        @else
                            <x-button wire:click="$dispatch('create-user')"
                                      wire:loading.attr="disabled"
                                      class="bg-indigo-600 hover:bg-indigo-700">
                <span wire:loading.remove>
                    {{ __('Create User') }}
                </span>
                                <span wire:loading>
                    {{ __('Processing...') }}
                </span>
                            </x-button>
                        @endif
                    </div>
                </x-slot>
            </x-dialog-modal>
        </div>
    </div>
</div>
