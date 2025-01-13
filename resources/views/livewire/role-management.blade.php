<div>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            {{ __('Role Management') }}
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
                    <div class="mb-4">
                        <x-button wire:click="$set('showCreateModal', true)">
                            {{ __('Create New Role') }}
                        </x-button>
                    </div>

                    <table class="min-w-full divide-y divide-gray-200">
                        <thead>
                        <tr>
                            <th class="px-6 py-3 text-left">Name</th>
                            <th class="px-6 py-3 text-left">Slug</th>
                            <th class="px-6 py-3 text-left">Permissions</th>
                            <th class="px-6 py-3 text-left">Actions</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                        @foreach($roles as $role)
                            <tr>
                                <td class="px-6 py-4">{{ $role->name }}</td>
                                <td class="px-6 py-4">{{ $role->slug }}</td>
                                <td class="px-6 py-4">
                                    @foreach($role->permissions as $permission)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                {{ $permission->name }}
                                            </span>
                                    @endforeach
                                </td>
                                <td class="px-6 py-4">
                                    <x-button wire:click="editRole({{ $role->id }})" class="mr-2">
                                        Edit
                                    </x-button>
                                    <x-danger-button wire:click="delete({{ $role->id }})" wire:confirm="Are you sure you want to delete this role?">
                                        Delete
                                    </x-danger-button>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>

                    <div class="mt-4">
                        {{ $roles->links() }}
                    </div>

                    <!-- Create/Edit Role Modal -->
                    <x-dialog-modal wire:model.live="showCreateModal">
                        <x-slot name="title">
                            {{ $editRole ? 'Edit Role' : 'Create New Role' }}
                        </x-slot>

                        <x-slot name="content">
                            <div class="space-y-4">
                                <div>
                                    <x-label for="name" value="{{ __('Name') }}" />
                                    <x-input id="name" type="text" class="block w-full mt-1" wire:model="name" />
                                    <x-input-error for="name" class="mt-2" />
                                </div>

                                <div>
                                    <x-label for="slug" value="{{ __('Slug') }}" />
                                    <x-input id="slug" type="text" class="block w-full mt-1" wire:model="slug" />
                                    <x-input-error for="slug" class="mt-2" />
                                </div>

                                <div>
                                    <x-label value="{{ __('Permissions') }}" />
                                    @foreach($permissions as $permission)
                                        <label class="inline-flex items-center mt-3">
                                            <input type="checkbox" wire:model="selectedPermissions" value="{{ $permission->id }}" class="form-checkbox h-5 w-5 text-gray-600">
                                            <span class="ml-2 text-gray-700">{{ $permission->name }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        </x-slot>

                        <x-slot name="footer">
                            <x-secondary-button wire:click="$set('showCreateModal', false)" wire:loading.attr="disabled">
                                {{ __('Cancel') }}
                            </x-secondary-button>

                            <x-button class="ml-3" wire:click="{{ $editRole ? 'update' : 'create' }}" wire:loading.attr="disabled">
                                {{ $editRole ? 'Update' : 'Create' }}
                            </x-button>
                        </x-slot>
                    </x-dialog-modal>
                </div>
            </div>
        </div>
    </div>
</div>
