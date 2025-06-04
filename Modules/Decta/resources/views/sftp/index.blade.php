<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Decta SFTP Manager') }}
        </h2>
    </x-slot>

    <!-- Tailwind CSS and Alpine.js -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div x-data="sftpManager()" x-init="init()">

                <!-- Action Buttons Header -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg my-5">
                    <div class="p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900">SFTP Management</h3>
                                <p class="mt-1 text-sm text-gray-500">Manage Decta file downloads and processing</p>
                            </div>
                            <div class="flex space-x-3">
                                <button @click="testConnection()"
                                        :disabled="loading"
                                        class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 disabled:opacity-50">
                                    <span x-show="!connectionTesting">Test Connection</span>
                                    <span x-show="connectionTesting" class="flex items-center">
                                        <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        Testing...
                                    </span>
                                </button>
                                <button @click="refreshStatus()"
                                        :disabled="loading"
                                        class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                    </svg>
                                    Refresh
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Connection Status Banner -->
                <div x-show="connectionStatus"
                     :class="connectionStatus?.success ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200'"
                     class="bg-white overflow-hidden shadow-sm sm:rounded-lg border p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg x-show="connectionStatus?.success" class="h-5 w-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                            </svg>
                            <svg x-show="!connectionStatus?.success" class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 :class="connectionStatus?.success ? 'text-green-800' : 'text-red-800'" class="text-sm font-medium">
                                <span x-show="connectionStatus?.success">SFTP Connection Active</span>
                                <span x-show="!connectionStatus?.success">SFTP Connection Failed</span>
                            </h3>
                            <div :class="connectionStatus?.success ? 'text-green-700' : 'text-red-700'" class="mt-1 text-sm">
                                <p x-text="connectionStatus?.message"></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Status Cards -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <div class="w-8 h-8 bg-yellow-100 rounded-md flex items-center justify-center">
                                        <svg class="w-5 h-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                    </div>
                                </div>
                                <div class="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt class="text-sm font-medium text-gray-500 truncate">Pending</dt>
                                        <dd class="text-lg font-medium text-gray-900" x-text="statusCounts.pending || 0"></dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <div class="w-8 h-8 bg-blue-100 rounded-md flex items-center justify-center">
                                        <svg class="w-5 h-5 text-blue-600 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                        </svg>
                                    </div>
                                </div>
                                <div class="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt class="text-sm font-medium text-gray-500 truncate">Processing</dt>
                                        <dd class="text-lg font-medium text-gray-900" x-text="statusCounts.processing || 0"></dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <div class="w-8 h-8 bg-green-100 rounded-md flex items-center justify-center">
                                        <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                    </div>
                                </div>
                                <div class="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt class="text-sm font-medium text-gray-500 truncate">Processed</dt>
                                        <dd class="text-lg font-medium text-gray-900" x-text="statusCounts.processed || 0"></dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <div class="w-8 h-8 bg-red-100 rounded-md flex items-center justify-center">
                                        <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.5 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                                        </svg>
                                    </div>
                                </div>
                                <div class="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt class="text-sm font-medium text-gray-500 truncate">Failed</dt>
                                        <dd class="text-lg font-medium text-gray-900" x-text="statusCounts.failed || 0"></dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Main Content Tabs -->
                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg my-5">
                    <div class="border-b border-gray-200">
                        <nav class="-mb-px flex space-x-8 px-6" aria-label="Tabs">
                            <button @click="activeTab = 'remote'"
                                    :class="activeTab === 'remote' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                                    class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                                Remote Files
                            </button>
                            <button @click="activeTab = 'local'"
                                    :class="activeTab === 'local' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                                    class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                                Local Files
                            </button>
                            <button @click="activeTab = 'activity'"
                                    :class="activeTab === 'activity' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                                    class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                                Recent Activity
                            </button>
                        </nav>
                    </div>

                    <!-- Remote Files Tab -->
                    <div x-show="activeTab === 'remote'" class="p-6">
                        <div class="flex justify-between items-center mb-6">
                            <h3 class="text-lg font-medium text-gray-900">Remote SFTP Files</h3>
                            <div class="flex space-x-3">
                                <label class="flex items-center">
                                    <input type="checkbox" x-model="showAllRemoteFiles" @change="loadRemoteFiles()" class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                    <span class="ml-2 text-sm text-gray-600">Show all files</span>
                                </label>
                                <button @click="loadRemoteFiles()"
                                        :disabled="loadingRemoteFiles"
                                        class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50">
                                    <svg x-show="!loadingRemoteFiles" class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                    </svg>
                                    <svg x-show="loadingRemoteFiles" class="w-4 h-4 mr-1 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                    </svg>
                                    Refresh
                                </button>
                                <button @click="downloadSelected()"
                                        :disabled="selectedRemoteFiles.length === 0 || downloading"
                                        class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 disabled:opacity-50">
                                    <span x-show="!downloading">Download Selected</span>
                                    <span x-show="downloading" class="flex items-center">
                                        <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        Downloading...
                                    </span>
                                </button>
                            </div>
                        </div>

                        <!-- Remote Files Table -->
                        <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
                            <table class="min-w-full divide-y divide-gray-300">
                                <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">
                                        <input type="checkbox" @change="toggleAllRemoteFiles($event.target.checked)" class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">File Name</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Size</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Modified</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Status</th>
                                    <th scope="col" class="relative px-6 py-3"><span class="sr-only">Actions</span></th>
                                </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                <template x-for="file in remoteFiles" :key="file.name">
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <input type="checkbox"
                                                   :value="file.name"
                                                   x-model="selectedRemoteFiles"
                                                   class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900" x-text="file.name"></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500" x-text="file.size_human"></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500" x-text="file.modified_human"></td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span x-show="file.is_downloaded" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                Downloaded
                                            </span>
                                            <span x-show="!file.is_downloaded" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                                Available
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <button @click="downloadSingleFile(file.name)"
                                                    :disabled="downloading"
                                                    class="text-blue-600 hover:text-blue-900 disabled:opacity-50">
                                                Download
                                            </button>
                                        </td>
                                    </tr>
                                </template>
                                <tr x-show="remoteFiles.length === 0 && !loadingRemoteFiles">
                                    <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500">
                                        No files found. Click "Refresh" to load files from SFTP server.
                                    </td>
                                </tr>
                                <tr x-show="loadingRemoteFiles">
                                    <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500">
                                        <div class="flex items-center justify-center">
                                            <svg class="animate-spin h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                            </svg>
                                            Loading files from SFTP server...
                                        </div>
                                    </td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Local Files Tab -->
                    <div x-show="activeTab === 'local'" class="p-6">
                        <div class="flex justify-between items-center mb-6">
                            <h3 class="text-lg font-medium text-gray-900">Local Files</h3>
                            <button @click="processSelected()"
                                    :disabled="selectedLocalFiles.length === 0 || processing"
                                    class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 disabled:opacity-50">
                                <span x-show="!processing">Process Selected</span>
                                <span x-show="processing" class="flex items-center">
                                    <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    Processing...
                                </span>
                            </button>
                        </div>

                        <!-- Local Files Table -->
                        <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
                            <table class="min-w-full divide-y divide-gray-300">
                                <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">
                                        <input type="checkbox" @change="toggleAllLocalFiles($event.target.checked)" class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">File Name</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Size</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Downloaded</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Status</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Transactions</th>
                                    <th scope="col" class="relative px-6 py-3"><span class="sr-only">Actions</span></th>
                                </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                <template x-for="file in recentFiles" :key="file.id">
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <input type="checkbox"
                                                   :value="file.id"
                                                   x-model="selectedLocalFiles"
                                                   :disabled="file.status === 'processed'"
                                                   class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50 disabled:opacity-50">
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900" x-text="file.filename"></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500" x-text="file.file_size"></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500" x-text="formatDate(file.created_at)"></td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span :class="getStatusClass(file.status)" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" x-text="file.status"></span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <div x-show="file.transaction_count > 0">
                                                <span x-text="file.matched_count"></span>/<span x-text="file.transaction_count"></span>
                                                <span class="text-xs text-gray-400">(<span x-text="Math.round(file.match_rate)"></span>%)</span>
                                            </div>
                                            <span x-show="file.transaction_count === 0" class="text-gray-400">-</span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <div class="flex space-x-2">
                                                <button @click="downloadLocalFile(file.id)"
                                                        class="text-blue-600 hover:text-blue-900">
                                                    View
                                                </button>
                                                <button x-show="file.status === 'pending'"
                                                        @click="processSingleFile(file.id)"
                                                        :disabled="processing"
                                                        class="text-green-600 hover:text-green-900 disabled:opacity-50">
                                                    Process
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </template>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Recent Activity Tab -->
                    <div x-show="activeTab === 'activity'" class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-6">Recent Activity</h3>

                        <div class="flow-root">
                            <ul role="list" class="-mb-8">
                                <template x-for="(activity, index) in recentActivity" :key="activity.id">
                                    <li>
                                        <div class="relative pb-8">
                                            <div x-show="index !== recentActivity.length - 1" class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-gray-200" aria-hidden="true"></div>
                                            <div class="relative flex space-x-3">
                                                <div>
                                                    <span :class="getActivityIconClass(activity.status)" class="h-8 w-8 rounded-full flex items-center justify-center ring-8 ring-white">
                                                        <svg class="h-5 w-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                                        </svg>
                                                    </span>
                                                </div>
                                                <div class="min-w-0 flex-1 pt-1.5 flex justify-between space-x-4">
                                                    <div>
                                                        <p class="text-sm text-gray-500">
                                                            File <span class="font-medium text-gray-900" x-text="activity.filename"></span>
                                                            <span x-text="getActivityDescription(activity.status)"></span>
                                                        </p>
                                                    </div>
                                                    <div class="text-right text-sm whitespace-nowrap text-gray-500">
                                                        <span x-text="activity.updated_at"></span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </li>
                                </template>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Notifications -->
                <div x-show="notification.show"
                     x-transition:enter="transform ease-out duration-300 transition"
                     x-transition:enter-start="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
                     x-transition:enter-end="translate-y-0 opacity-100 sm:translate-x-0"
                     x-transition:leave="transition ease-in duration-100"
                     x-transition:leave-start="opacity-100"
                     x-transition:leave-end="opacity-0"
                     class="fixed inset-0 flex items-end justify-center px-4 py-6 pointer-events-none sm:p-6 sm:items-start sm:justify-end z-50">
                    <div class="max-w-sm w-full bg-white shadow-lg rounded-lg pointer-events-auto ring-1 ring-black ring-opacity-5 overflow-hidden">
                        <div class="p-4">
                            <div class="flex items-start">
                                <div class="flex-shrink-0">
                                    <svg x-show="notification.type === 'success'" class="h-6 w-6 text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    <svg x-show="notification.type === 'error'" class="h-6 w-6 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.5 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                                    </svg>
                                </div>
                                <div class="ml-3 w-0 flex-1 pt-0.5">
                                    <p class="text-sm font-medium text-gray-900" x-text="notification.title"></p>
                                    <p class="mt-1 text-sm text-gray-500" x-text="notification.message"></p>
                                </div>
                                <div class="ml-4 flex-shrink-0 flex">
                                    <button @click="notification.show = false" class="bg-white rounded-md inline-flex text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                        <span class="sr-only">Close</span>
                                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function sftpManager() {
            return {
                // State
                activeTab: 'remote',
                loading: false,
                connectionTesting: false,
                loadingRemoteFiles: false,
                downloading: false,
                processing: false,

                // Data
                connectionStatus: null,
                statusCounts: {},
                remoteFiles: [],
                recentFiles: [],
                recentActivity: [],

                // Selections
                selectedRemoteFiles: [],
                selectedLocalFiles: [],
                showAllRemoteFiles: false,

                // Notifications
                notification: {
                    show: false,
                    type: 'success',
                    title: '',
                    message: ''
                },

                // Initialize
                init() {
                    this.refreshStatus();
                    this.loadRemoteFiles();
                    this.loadLocalFiles();
                    // Auto-refresh every 30 seconds
                    setInterval(() => {
                        this.refreshStatus();
                        this.loadLocalFiles();
                    }, 30000);
                },

                // CSRF token helper
                getCsrfToken() {
                    return document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                },

                // API helpers
                async apiCall(url, options = {}) {
                    const defaultOptions = {
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': this.getCsrfToken(),
                            'Accept': 'application/json'
                        }
                    };

                    const response = await fetch(url, { ...defaultOptions, ...options });
                    const data = await response.json();

                    if (!response.ok) {
                        throw new Error(data.message || 'Request failed');
                    }

                    return data;
                },

                // Test SFTP connection
                async testConnection() {
                    this.connectionTesting = true;
                    try {
                        const result = await this.apiCall('/decta/sftp/test-connection', { method: 'POST' });
                        this.connectionStatus = result;
                        this.showNotification(
                            result.success ? 'success' : 'error',
                            result.success ? 'Connection Successful' : 'Connection Failed',
                            result.message
                        );
                    } catch (error) {
                        this.connectionStatus = { success: false, message: error.message };
                        this.showNotification('error', 'Connection Failed', error.message);
                    } finally {
                        this.connectionTesting = false;
                    }
                },

                // Load remote files
                async loadRemoteFiles() {
                    this.loadingRemoteFiles = true;
                    try {
                        const result = await this.apiCall(`/decta/sftp/list-files?show_all=${this.showAllRemoteFiles}`);
                        this.remoteFiles = result.files;
                        this.selectedRemoteFiles = [];
                    } catch (error) {
                        this.showNotification('error', 'Failed to Load Files', error.message);
                    } finally {
                        this.loadingRemoteFiles = false;
                    }
                },

                // Refresh status
                async refreshStatus() {
                    try {
                        const result = await this.apiCall('/decta/sftp/status');
                        this.statusCounts = result.status_counts;
                        this.recentActivity = result.recent_activity;

                        // Get updated recent files list - you'll need to pass this from the controller
                        this.recentFiles = @json($recentFiles);
                    } catch (error) {
                        console.error('Failed to refresh status:', error);
                    }
                },
// Load local files
                async loadLocalFiles() {
                    try {
                        const result = await this.apiCall('/decta/sftp/status');
                        if (result.recent_files) {
                            this.recentFiles = result.recent_files;
                        }
                    } catch (error) {
                        console.error('Failed to load local files:', error);
                    }
                },
                // Download files
                async downloadSelected() {
                    if (this.selectedRemoteFiles.length === 0) return;

                    this.downloading = true;
                    try {
                        const result = await this.apiCall('/decta/sftp/download', {
                            method: 'POST',
                            body: JSON.stringify({
                                files: this.selectedRemoteFiles,
                                force: false
                            })
                        });

                        this.showNotification(
                            result.success ? 'success' : 'error',
                            'Download Complete',
                            result.message
                        );

                        if (result.success) {
                            await this.loadRemoteFiles();
                            await this.refreshStatus();
                            this.selectedRemoteFiles = [];
                        }
                    } catch (error) {
                        this.showNotification('error', 'Download Failed', error.message);
                    } finally {
                        this.downloading = false;
                    }
                },

                // Download single file
                async downloadSingleFile(filename) {
                    this.downloading = true;
                    try {
                        const result = await this.apiCall('/decta/sftp/download', {
                            method: 'POST',
                            body: JSON.stringify({
                                files: [filename],
                                force: false
                            })
                        });

                        this.showNotification(
                            result.success ? 'success' : 'error',
                            'Download Complete',
                            result.message
                        );

                        if (result.success) {
                            await this.loadRemoteFiles();
                            await this.refreshStatus();
                        }
                    } catch (error) {
                        this.showNotification('error', 'Download Failed', error.message);
                    } finally {
                        this.downloading = false;
                    }
                },

                // Process files
                async processSelected() {
                    if (this.selectedLocalFiles.length === 0) return;

                    this.processing = true;
                    try {
                        const result = await this.apiCall('/decta/sftp/process', {
                            method: 'POST',
                            body: JSON.stringify({
                                file_ids: this.selectedLocalFiles,
                                skip_matching: false
                            })
                        });

                        this.showNotification('success', 'Processing Started', result.message);
                        await this.refreshStatus();
                        this.selectedLocalFiles = [];
                    } catch (error) {
                        this.showNotification('error', 'Processing Failed', error.message);
                    } finally {
                        this.processing = false;
                    }
                },

                // Process single file
                async processSingleFile(fileId) {
                    this.processing = true;
                    try {
                        const result = await this.apiCall('/decta/sftp/process', {
                            method: 'POST',
                            body: JSON.stringify({
                                file_ids: [fileId],
                                skip_matching: false
                            })
                        });

                        this.showNotification('success', 'Processing Started', result.message);
                        await this.refreshStatus();
                    } catch (error) {
                        this.showNotification('error', 'Processing Failed', error.message);
                    } finally {
                        this.processing = false;
                    }
                },

                // Download local file
                downloadLocalFile(fileId) {
                    window.open(`/decta/sftp/download-file?file_id=${fileId}`, '_blank');
                },

                // Selection helpers
                toggleAllRemoteFiles(checked) {
                    if (checked) {
                        this.selectedRemoteFiles = this.remoteFiles.map(f => f.name);
                    } else {
                        this.selectedRemoteFiles = [];
                    }
                },

                toggleAllLocalFiles(checked) {
                    if (checked) {
                        this.selectedLocalFiles = this.recentFiles
                            .filter(f => f.status !== 'processed')
                            .map(f => f.id);
                    } else {
                        this.selectedLocalFiles = [];
                    }
                },

                // UI helpers
                formatDate(dateString) {
                    return new Date(dateString).toLocaleString();
                },

                getStatusClass(status) {
                    const classes = {
                        'pending': 'bg-yellow-100 text-yellow-800',
                        'processing': 'bg-blue-100 text-blue-800',
                        'processed': 'bg-green-100 text-green-800',
                        'failed': 'bg-red-100 text-red-800'
                    };
                    return classes[status] || 'bg-gray-100 text-gray-800';
                },

                getActivityIconClass(status) {
                    const classes = {
                        'pending': 'bg-yellow-400',
                        'processing': 'bg-blue-400',
                        'processed': 'bg-green-400',
                        'failed': 'bg-red-400'
                    };
                    return classes[status] || 'bg-gray-400';
                },

                getActivityDescription(status) {
                    const descriptions = {
                        'pending': 'is pending processing',
                        'processing': 'is being processed',
                        'processed': 'was processed successfully',
                        'failed': 'processing failed'
                    };
                    return descriptions[status] || 'status updated';
                },

                // Show notification
                showNotification(type, title, message) {
                    this.notification = {
                        show: true,
                        type,
                        title,
                        message
                    };

                    // Auto-hide after 5 seconds
                    setTimeout(() => {
                        this.notification.show = false;
                    }, 5000);
                }
            }
        }

        // Set CSRF token for all AJAX requests
        document.addEventListener('DOMContentLoaded', function() {
            const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

            // Configure axios if available
            if (window.axios) {
                window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token;
            }
        });
    </script>
</x-app-layout>
