<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('CESOP XML Encryption') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-xs sm:rounded-lg">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-2xl font-bold mb-6">Generate CESOP Report</h2>
{{--                        <a href="{{ route('cesop.report.index') }}"--}}
{{--                           class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-white hover:bg-gray-700">--}}
{{--                            Back to Dashboard--}}
{{--                        </a>--}}
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

                    {{-- Main Report Form --}}
                    <form action="{{ route('cesop.report.generate') }}" method="POST">
                        @csrf
                        <div class="space-y-6">
                            <!-- Quarter and Year Selection -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="quarter" class="block text-sm font-medium text-gray-700">Quarter</label>
                                    <select name="quarter" id="quarter"
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-xs focus:border-indigo-500 focus:ring-indigo-500">
                                        @foreach($availableQuarters as $q)
                                            <option value="{{ $q['quarter'] }}" data-year="{{ $q['year'] }}">
                                                {{ $q['label'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div>
                                    <label for="year" class="block text-sm font-medium text-gray-700">Year</label>
                                    <input type="number" name="year" id="year" value="{{ $availableQuarters[0]['year'] ?? date('Y') }}" min="2000" max="2050"
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-xs focus:border-indigo-500 focus:ring-indigo-500">
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="flex justify-end">
                                <button type="submit"
                                        class="inline-flex justify-center rounded-md border border-transparent bg-indigo-600 py-2 px-4 text-sm font-medium text-white shadow-xs hover:bg-indigo-700 focus:outline-hidden focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                                    Generate Report
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const quarterSelect = document.getElementById('quarter');
            const yearInput = document.getElementById('year');

            // Update year when quarter changes
            quarterSelect.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                yearInput.value = selectedOption.dataset.year;
            });
        });
    </script>
</x-app-layout>
