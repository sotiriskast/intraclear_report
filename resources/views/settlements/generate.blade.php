<x-app-layout>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h2 class="text-2xl font-bold mb-6">Generate Settlement Report</h2>

                    @if (session('success'))
                        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4">
                            {{ session('success') }}
                        </div>

                        @if(session('generate_params'))
                            {{-- Hidden form for download --}}
                            <form id="downloadForm" action="{{ route('settlements.generate') }}" method="POST" class="hidden">
                                @csrf
                                <input type="hidden" name="merchant_id" value="{{ session('generate_params.merchant_id') }}">
                                <input type="hidden" name="start_date" value="{{ session('generate_params.start_date') }}">
                                <input type="hidden" name="end_date" value="{{ session('generate_params.end_date') }}">
                                <input type="hidden" name="currency" value="{{ session('generate_params.currency') }}">
                                <input type="hidden" name="download" value="1">
                            </form>
                            <script>
                                // Submit the download form after a short delay
                                setTimeout(function() {
                                    document.getElementById('downloadForm').submit();
                                }, 1000);
                            </script>
                        @endif
                    @endif

                    @if ($errors->any())
                        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4">
                            <ul>
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    {{-- Main Generate Form --}}
                    <form action="{{ route('settlements.generate') }}" method="POST">
                        @csrf
                        <div class="space-y-6">
                            <!-- Merchant Selection -->
                            <div>
                                <label for="merchant_id" class="block text-sm font-medium text-gray-700">Merchant</label>
                                <select name="merchant_id" id="merchant_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">All Merchants</option>
                                    @foreach($merchants as $merchant)
                                        <option value="{{ $merchant->account_id }}" {{ old('merchant_id') == $merchant->account_id ? 'selected' : '' }}>
                                            {{ $merchant->name }} ({{ $merchant->account_id }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- Date Range -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="start_date" class="block text-sm font-medium text-gray-700">Start Date</label>
                                    <input type="date"
                                           name="start_date"
                                           id="start_date"
                                           value="{{ old('start_date','2024-11-21') }}"
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                </div>

                                <div>
                                    <label for="end_date" class="block text-sm font-medium text-gray-700">End Date</label>
                                    <input type="date"
                                           name="end_date"
                                           id="end_date"
                                           value="{{ old('end_date','2024-11-28') }}"
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                </div>
                            </div>

                            <!-- Currency Selection -->
                            <div>
                                <label for="currency" class="block text-sm font-medium text-gray-700">Currency</label>
                                <select name="currency" id="currency" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">All Currencies</option>
                                    @foreach($currencies as $currency)
                                        <option value="{{ $currency }}" {{ old('currency') == $currency ? 'selected' : '' }}>
                                            {{ $currency }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- Submit Button -->
                            <div class="flex justify-end">
                                <button type="submit" class="inline-flex justify-center rounded-md border border-transparent bg-indigo-600 py-2 px-4 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                                    Generate Report
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
