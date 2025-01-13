<div>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            {{ __('User Management') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            @if (session()->has('message'))
                <div class="p-4 mb-4 text-sm text-green-700 bg-green-100 rounded-lg">
                    {{ session('message') }}
                </div>
            @endif

            @if (session()->has('error'))
                <div class="p-4 mb-4 text-sm text-red-700 bg-red-100 rounded-lg">
                    {{ session('error') }}
                </div>
            @endif

            <div class="overflow-hidden bg-white shadow-xl sm:rounded-lg">
                <div class="p-6">
                    <!-- Create User Button -->
                    <div class="mb-4">
                        <x-button wire:click="$set('showCreateModal', true)">
                            {{ __('Create New User') }}
                        </x-button>
                    </div>

                    <!-- Users Table -->
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead>
                        <tr>
                            <th class="px-6 py-3 text-left">Name</th>
                            <th class="px-6 py-3 text-left">Email</th>
                            <th class="px-6 py-3 text-left">Roles</th>
                            <th class="px-6 py-3 text-left">Actions</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                        @foreach($users as $user)
                            <tr>
                                <td class="px-6 py-4">{{ $user->name }}</td>
                                <td class="px-6 py-4">{{ $user->email }}</td>
                                <td class="px-6 py-4">
                                    @foreach($user->roles as $role)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                {{ $role->name }}
                                            </span>
                                    @endforeach
                                </td>
                                <td class="px-6 py-4">
                                    <x-button wire:click="$dispatch('editUser', { userId: {{ $user->id }} })" class="mr-2">
                                        Edit
                                    </x-button>
                                    <x-danger-button wire:click="$dispatch('deleteUser', { userId: {{ $user->id }} })"
                                                     wire:confirm="Are you sure you want to delete this user?">
                                        Delete
                                    </x-danger-button>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>

                    <div class="mt-4">
                        {{ $users->links() }}
                    </div>

                    <!-- Create/Edit User Modal -->
                    <x-dialog-modal wire:model.live="showCreateModal">
                        <x-slot name="title">
                            {{ $isEditing ? 'Edit User' : 'Create New User' }}
                        </x-slot>

                        <x-slot name="content">
                            <div class="space-y-4">
                                <div>
                                    <x-label for="name" value="{{ __('Name') }}" />
                                    <x-input id="name" type="text" class="block w-full mt-1" wire:model="name" />
                                    <x-input-error for="name" class="mt-2" />
                                </div>

                                <div>
                                    <x-label for="email" value="{{ __('Email') }}" />
                                    <x-input id="email" type="email" class="block w-full mt-1" wire:model="email" />
                                    <x-input-error for="email" class="mt-2" />
                                </div>

                                @if(!$isEditing)
                                    <div>
                                        <x-label for="password" value="{{ __('Password') }}" />
                                        <x-input id="password" type="password" class="block w-full mt-1" wire:model="password" />
                                        <x-input-error for="password" class="mt-2" />
                                    </div>
                                @endif
                                <div>
                                    <x-label value="{{ __('Role') }}" />
                                    @error('selectedRole')
                                    <span class="text-red-500 text-sm">{{ $message }}</span>
                                    @enderror
                                    <div class="mt-2 space-y-2">
                                        @foreach($roles as $role)
                                            <label class="inline-flex items-center mr-4">
                                                <input type="radio"
                                                       wire:model="selectedRole"
                                                       value="{{ $role->id }}"
                                                       class="form-radio h-4 w-4 text-indigo-600">
                                                <span class="ml-2 text-gray-700">{{ $role->name }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>

                            </div>
                        </x-slot>

                        <x-slot name="footer">
                            <x-secondary-button wire:click="resetForm" wire:loading.attr="disabled">
                                {{ __('Cancel') }}
                            </x-secondary-button>

                            <x-button class="ml-3"
                                      wire:click="$dispatch('{{ $isEditing ? 'updateUser' : 'createUser' }}')"
                                      wire:loading.attr="disabled">
                                {{ $isEditing ? 'Update' : 'Create' }}
                            </x-button>
                        </x-slot>
                    </x-dialog-modal>
                </div>
            </div>
        </div>
    </div>
</div>
