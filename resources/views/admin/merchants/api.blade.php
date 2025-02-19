<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                API Access for {{ $merchant->name }}
            </h2>
            <span class="text-sm text-gray-600">Account ID: {{ $merchant->account_id }}</span>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if (session('success'))
                <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative">
                    <span class="block sm:inline">{{ session('success') }}</span>
                </div>
            @endif

            @if (session('newApiKey'))
                <div class="mb-6 p-4 bg-yellow-50 border-l-4 border-yellow-400">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-yellow-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2h-1V9a1 1 0 00-1-1z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-yellow-800">Important: Copy this API key now</h3>
                            <div class="mt-2 text-sm text-yellow-700">
                                <p>This key will not be shown again. Make sure to store it securely.</p>
                            </div>
                            <div class="mt-4">
                                <div class="flex items-center">
                                    <input type="text" id="api-key" value="{{ session('newApiKey') }}" readonly class="flex-1 p-2 border border-gray-300 rounded mr-2 bg-gray-50 text-gray-800 font-mono text-sm">
                                    <button onclick="copyApiKey()" class="px-3 py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" />
                                        </svg>
                                    </button>
                                </div>
                                <p class="mt-1 text-xs text-gray-500">Copied: <span id="copy-status">No</span></p>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">API Key Status</h3>
                    <div class="bg-gray-50 p-4 rounded border mb-4">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                @if($hasApiKey)
                                    <span class="h-4 w-4 bg-green-500 rounded-full inline-block"></span>
                                @else
                                    <span class="h-4 w-4 bg-red-500 rounded-full inline-block"></span>
                                @endif
                            </div>
                            <div class="ml-3">
                                <span class="text-gray-700">
                                    @if($hasApiKey)
                                        API key is set
                                    @else
                                        No API key set
                                    @endif
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="bg-gray-50 p-4 rounded border">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <span class="inline-flex items-center justify-center h-6 w-6 rounded-full bg-blue-100 text-blue-800">
                                    {{ $tokenCount }}
                                </span>
                            </div>
                            <div class="ml-3">
                                <span class="text-gray-700">
                                    Active API tokens
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="p-6 bg-white border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">API Key Management</h3>

                    <div class="space-y-4">
                        <div class="flex space-x-2">
                            <form method="POST" action="{{ route('merchant.api.generate', $merchant) }}" class="inline">
                                @csrf
                                <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                    <svg class="-ml-1 mr-2 h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                    </svg>
                                    Generate New API Key
                                </button>
                            </form>

                            @if($tokenCount > 0)
                                <form method="POST" action="{{ route('merchant.api.revoke', $merchant) }}" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500" onclick="return confirm('Are you sure you want to revoke all API tokens? This will log out all API sessions.')">
                                        <svg class="-ml-1 mr-2 h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                        Revoke All Tokens
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>

                    <div class="mt-8 bg-yellow-50 border-l-4 border-yellow-400 p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-yellow-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-yellow-700">
                                    <strong>Warning:</strong> Generating a new API key will invalidate the old key and requires updating the key in any integration.
                                    Revoking tokens will immediately invalidate all active API sessions.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-6 bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">API Integration Information</h3>

                    <div class="space-y-4">
                        <div>
                            <h4 class="font-medium text-gray-700">Base URL</h4>
                            <pre class="mt-1 block w-full p-2 border border-gray-200 rounded bg-gray-50 text-sm font-mono">{{ config('app.url') }}/api/v1</pre>
                        </div>

                        <div>
                            <h4 class="font-medium text-gray-700">Account ID</h4>
                            <pre class="mt-1 block w-full p-2 border border-gray-200 rounded bg-gray-50 text-sm font-mono">{{ $merchant->account_id }}</pre>
                        </div>

                        <div>
                            <h4 class="font-medium text-gray-700">API Endpoints</h4>
                            <div class="mt-2 space-y-2 text-sm">
                                <div class="p-2 bg-gray-50 rounded border">
                                    <span class="font-semibold">POST /auth/login</span>
                                    <p class="text-gray-600 mt-1">Authenticate and get token</p>
                                </div>
                                <div class="p-2 bg-gray-50 rounded border">
                                    <span class="font-semibold">GET /rolling-reserves</span>
                                    <p class="text-gray-600 mt-1">List all rolling reserves</p>
                                </div>
                                <div class="p-2 bg-gray-50 rounded border">
                                    <span class="font-semibold">GET /rolling-reserves/summary</span>
                                    <p class="text-gray-600 mt-1">Get summary statistics</p>
                                </div>
                                <div class="p-2 bg-gray-50 rounded border">
                                    <span class="font-semibold">GET /rolling-reserves/{id}</span>
                                    <p class="text-gray-600 mt-1">Get specific reserve details</p>
                                </div>
                                <div class="p-2 bg-gray-50 rounded border">
                                    <span class="font-semibold">POST /auth/logout</span>
                                    <p class="text-gray-600 mt-1">Invalidate current token</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function copyApiKey() {
            const apiKeyInput = document.getElementById('api-key');
            const copyStatus = document.getElementById('copy-status');

            apiKeyInput.select();
            document.execCommand('copy');

            copyStatus.textContent = 'Yes';
            setTimeout(() => {
                copyStatus.textContent = 'Yes';
            }, 2000);
        }
    </script>
</x-app-layout>
