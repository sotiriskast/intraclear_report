<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('CESOP XML Generation') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-xs sm:rounded-lg">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-2xl font-bold mb-6">Import from Excel</h2>
                    </div>

                    @if (session('success'))
                        <div
                            class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-sm relative mb-4">
                            {{ session('success') }}
                        </div>
                    @endif

                    @if (session('error'))
                        <div
                            class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-sm relative mb-4">
                            {{ session('error') }}
                        </div>
                    @endif

                    @if ($errors->any())
                        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-sm relative mb-4">
                            <ul>
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form action="{{ route('cesop.report.import-excel.upload') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="space-y-6">
                            <!-- File Upload -->
                            <div>
                                <label for="excel_file" class="block text-sm font-medium text-gray-700">
                                    Excel File (XLSX, XLS, CSV)
                                </label>
                                <input type="file" id="excel_file" name="excel_file" accept=".xlsx,.xls,.csv" required
                                       class="mt-1 block w-full shadow-xs focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                                <p class="mt-1 text-sm text-gray-500">
                                    Upload an Excel file with merchant and transaction data.
                                </p>
                            </div>

                            <!-- Validation Checkbox -->
                            <div class="flex items-start">
                                <div class="flex items-center h-5">
                                    <input id="validate" name="validate" type="checkbox" value="1" checked
                                           class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                </div>
                                <div class="ml-3 text-sm">
                                    <label for="validate" class="font-medium text-gray-700">Validate XML after generation</label>
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="flex justify-end">
                                <button type="submit"
                                        class="inline-flex justify-center rounded-md border border-transparent bg-indigo-600 py-2 px-4 text-sm font-medium text-white shadow-xs hover:bg-indigo-700 focus:outline-hidden focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                                    Import and Generate XML
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
