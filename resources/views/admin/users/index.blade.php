<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="text-2xl font-bold text-gray-800">
                {{ __('User Management') }}
            </h2>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div x-data="{ show: false }">
                @if (session('message'))
                    <div x-show="show" class="mb-6 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-md relative" role="alert" x-init="show = true; setTimeout(() => show = false, 5000)">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="size-5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <div class="ms-3 flex-1">
                                <p class="text-sm font-medium text-green-800">{{ session('message') }}</p>
                            </div>
                            <button @click="show = false" class="ms-auto">
                                <svg class="size-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </div>
                @endif

                @if (session('error'))
                    <div x-show="show" class="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-md relative" role="alert" x-init="show = true; setTimeout(() => show = false, 5000)">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="size-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <div class="ms-3 flex-1">
                                <p class="text-sm font-medium text-red-800">{{ session('error') }}</p>
                            </div>
                            <button @click="show = false" class="ms-auto">
                                <svg class="size-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </div>
                @endif
            </div>

            {{-- Add New User Button --}}
            <div class="mt-6 mb-4">
                <a href="{{ route('admin.users.create') }}"
                   wire:navigate
                   class="inline-flex items-center gap-2 rounded-md bg-indigo-600 px-3.5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                    <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    {{ __('Add New User') }}
                </a>
            </div>

            {{-- Users List --}}
            <div class="bg-white rounded-lg shadow-sm ring-1 ring-gray-900/5">
                {{-- Desktop View Table --}}
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        {{-- Table Header --}}
                        <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                {{ __('User Details') }}
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                {{ __('Type') }}
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider hidden sm:table-cell">
                                {{ __('Role/Merchant') }}
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider hidden md:table-cell">
                                {{ __('Created') }}
                            </th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                {{ __('Actions') }}
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
                                        <div class="shrink-0 h-10 w-10">
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

                                {{-- User Type --}}
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                        {{ $user->user_type === 'super-admin' ? 'bg-purple-100 text-purple-800' :
                                          ($user->user_type === 'admin' ? 'bg-blue-100 text-blue-800' :
                                          'bg-green-100 text-green-800') }}">
                                        {{ ucfirst($user->user_type) }}
                                    </span>

                                    @if($user->user_type === 'merchant' && $user->merchant && !$user->merchant->active)
                                        <span class="ml-1 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                            Inactive Merchant
                                        </span>
                                    @endif
                                </td>

                                {{-- Role/Merchant --}}
                                <td class="px-6 py-4 whitespace-nowrap hidden sm:table-cell">
                                    @if($user->user_type === 'merchant')
                                        @if($user->merchant)
                                            <span class="text-sm text-gray-900">{{ $user->merchant->name }}</span>
                                        @else
                                            <span class="text-sm text-red-500">No merchant assigned</span>
                                        @endif
                                    @else
                                        <div class="flex flex-wrap gap-2">
                                            @foreach($user->roles as $role)
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                    {{ $role->name === 'super-admin' ? 'bg-purple-100 text-purple-800' :
                                                      ($role->name === 'admin' ? 'bg-blue-100 text-blue-800' :
                                                      'bg-gray-100 text-gray-800') }}">
                                                    {{ $role->name }}
                                                </span>
                                            @endforeach

                                            @if($user->roles->isEmpty())
                                                <span class="text-sm text-red-500">No role assigned</span>
                                            @endif
                                        </div>
                                    @endif
                                </td>

                                {{-- Created At --}}
                                <td class="px-6 py-4 whitespace-nowrap hidden md:table-cell">
                                    <span class="text-sm text-gray-500">{{ $user->created_at->format('M d, Y') }}</span>
                                </td>

                                {{-- Actions --}}
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex items-center justify-end space-x-2">
                                        <a href="{{ route('admin.users.edit', $user) }}" wire:navigate class="text-indigo-600 hover:text-indigo-900">
                                            <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                        </a>

                                        @if($user->id !== auth()->id() && !$user->hasRole('super-admin'))
                                            <form method="POST" action="{{ route('admin.users.destroy', $user) }}" onsubmit="return confirm('Are you sure you want to delete this user?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-red-600 hover:text-red-800">
                                                    <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                              d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Pagination --}}
            <div class="mt-4">
                {{ $users->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
