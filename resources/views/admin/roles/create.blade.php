<x-app-layout>
    <x-slot name="header">
        <h2 class="text-2xl font-bold text-gray-800">
            {{ __('Create New Role') }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white rounded-lg shadow-sm ring-1 ring-gray-900/5 p-6">
                <div class="mb-4">
                    <a href="{{ route('admin.roles.index') }}" wire:navigate class="inline-flex items-center text-indigo-600 hover:text-indigo-900">
                        <svg class="size-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                        {{ __('Back to Roles') }}
                    </a>
                </div>

                @if (session('error'))
                    <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-md relative" role="alert">
                        <span class="block sm:inline">{{ session('error') }}</span>
                    </div>
                @endif

                <form action="{{ route('admin.roles.store') }}" method="POST">
                    @csrf
                    <div class="grid grid-cols-1 gap-6">
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700">
                                {{ __('Role Name') }}
                            </label>
                            <input id="name"
                                   name="name"
                                   type="text"
                                   value="{{ old('name') }}"
                                   class="mt-1 block w-full rounded-md border-gray-300 px-3 py-2 text-gray-900 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 sm:text-sm shadow-sm"
                                   required />
                            @error('name')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-3">
                                {{ __('Permissions') }}
                            </label>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                @foreach($permissions as $permission)
                                    <label class="relative flex items-start p-4 rounded-lg border cursor-pointer hover:bg-gray-50">
                                        <div class="flex items-center h-5">
                                            <input type="checkbox"
                                                   name="permissions[]"
                                                   value="{{ $permission->id }}"
                                                   {{ in_array($permission->id, old('permissions', [])) ? 'checked' : '' }}
                                                   class="h-4 w-4 text-indigo-600 border-gray-300 rounded-sm focus:ring-indigo-500">
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
                            @error('permissions')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="flex justify-end mt-6">
                        <a href="{{ route('admin.roles.index') }}"
                           wire:navigate
                           class="inline-flex items-center justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gray-500 mr-3">
                            {{ __('Cancel') }}
                        </a>
                        <button type="submit"
                                class="inline-flex items-center justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                            {{ __('Create Role') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
