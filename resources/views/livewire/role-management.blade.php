<div>
    <x-slot name="header">
        <h2 class="text-2xl font-bold text-gray-800">
            {{ __('Role Management') }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Notification Messages -->
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

            <!-- Add New Role Button -->
            <div class="mt-6 mb-4">
                <x-button wire:click="$set('showCreateModal', true)"
                          class="flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    {{ __('Add New Role') }}
                </x-button>
            </div>

            <!-- Roles List -->
            <div class="bg-white rounded-lg shadow">
                <!-- Mobile/Tablet View - Card Layout -->
                <div class="block lg:hidden">
                    @foreach($roles as $role)
                        <div class="border-b last:border-b-0 px-4 py-4 hover:bg-gray-50">
                            <div class="flex justify-between items-center mb-2">
                                <div>
                                    <p class="text-sm font-medium text-gray-900">{{ $role->name }}</p>
                                    <p class="text-xs text-gray-500">Slug: {{ $role->slug }}</p>
                                </div>
                                <div class="flex space-x-2">
                                    <button wire:click="editRole({{ $role->id }})"
                                            class="text-gray-600 hover:text-indigo-600">
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </button>
                                    <button wire:click="delete({{ $role->id }})"
                                            wire:confirm="Are you sure you want to delete this role?"
                                            class="text-red-600 hover:text-red-800">
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-lienjoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            <div class="mt-2">
                                <p class="text-xs font-medium text-gray-700 mb-1">Permissions:</p>
                                <div class="flex flex-wrap gap-1">
                                    @forelse($role->permissions as $permission)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                {{ $permission->name }}
                            </span>
                                    @empty
                                        <span class="text-xs text-gray-500">No permissions</span>
                                    @endforelse
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <!-- Desktop View - Traditional Table -->
                <div class="hidden lg:block overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Name
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Slug
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Permissions
                            </th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Actions
                            </th>
                        </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($roles as $role)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">{{ $role->name }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-500">{{ $role->slug }}</div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex flex-wrap gap-2">
                                        @foreach($role->permissions as $permission)
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        {{ $permission->name }}
                                    </span>
                                        @endforeach
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex justify-end gap-2">
                                        <button wire:click="editRole({{ $role->id }})"
                                                class="text-gray-600 hover:text-indigo-600">
                                            <svg class="h-4 w-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                        </button>
                                        <button wire:click="delete({{ $role->id }})"
                                                wire:confirm="Are you sure you want to delete this role?"
                                                class="text-red-600 hover:text-red-800">
                                            <svg class="h-4 w-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="px-6 py-4 border-t border-gray-200">
                    {{ $roles->links() }}
                </div>
            </div>
            <!-- Create/Edit Role Modal -->
            <x-dialog-modal wire:model.live="showCreateModal" max-width="2xl">
                <x-slot name="title">
                    <div class="flex items-center">
                        <div class="mr-3 rounded-full bg-indigo-100 p-2">
                            <svg class="h-6 w-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="{{ $editRoleId ? 'M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z' : 'M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z' }}" />
                            </svg>
                        </div>
                        <h3 class="text-lg font-medium text-gray-900">
                            {{ $editRoleId ? 'Edit Role Details' : 'Create New Role' }}
                        </h3>
                    </div>
                </x-slot>

                <x-slot name="content">
                    <div class="grid grid-cols-1 gap-6">
                        <div>
                            <x-label for="name" value="{{ __('Role Name') }}" />
                            <x-input id="name" type="text" class="mt-1 block w-full" wire:model="name" />
                            <x-input-error for="name" class="mt-2" />
                        </div>
                        <div>
                            <x-label value="{{ __('Permissions') }}" class="mb-3" />
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                @foreach($permissions as $permission)
                                    <label class="relative flex items-start p-4 rounded-lg border cursor-pointer hover:bg-gray-50">
                                        <div class="flex items-center h-5">
                                            <input type="checkbox"
                                                   wire:model="selectedPermissions"
                                                   value="{{ $permission->id }}"
                                                   class="h-4 w-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500">
                                        </div>
                                        <div class="ml-3">
                                            <span class="block text-sm font-medium text-gray-900">
                                                {{ $permission->name }}
                                            </span>
                                            <span class="block text-sm text-gray-500">
                                                {{ $permission->description ?? 'No description available' }}
                                            </span>
                                        </div>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </x-slot>

                <x-slot name="footer">
                    <div class="flex justify-end space-x-3">
                        <x-secondary-button wire:click="resetForm" wire:loading.attr="disabled">
                            {{ __('Cancel') }}
                        </x-secondary-button>

                        <x-button wire:click="{{ $editRoleId ? 'update' : 'create' }}"
                                  wire:loading.attr="disabled"
                                  class="bg-indigo-600 hover:bg-indigo-700">
                            <span wire:loading.remove>
                                {{ $editRoleId ? __('Save Changes') : __('Create Role') }}
                            </span>
                            <span wire:loading>
                                {{ __('Processing...') }}
                            </span>
                        </x-button>
                    </div>
                </x-slot>
            </x-dialog-modal>
        </div>
    </div>
</div>
