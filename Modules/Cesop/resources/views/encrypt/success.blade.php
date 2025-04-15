<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Encryption Successful') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                <div class="bg-green-500 text-white px-6 py-4 border-b border-gray-200">
                    <h2 class="text-xl font-semibold">Encryption Successful</h2>
                </div>

                <div class="p-6">
                    <div class="bg-green-50 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4">
                        {{ session('success') }}
                    </div>

                    <div class="space-y-4">
                        <p class="text-gray-600">
                            Your XML file has been encrypted using the TaxisNet PGP public key and is ready for submission.
                        </p>

                        <p class="text-gray-600">
                            Encrypted file: <span class="font-semibold">{{ basename($encrypted_file) }}</span>
                        </p>

                        <div class="flex space-x-4 mt-6">
                            <a href="{{ route('cesop.download', ['filename' => basename($encrypted_file)]) }}"
                               class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:border-indigo-900 focus:ring focus:ring-indigo-300 disabled:opacity-25 transition">
                                Download Encrypted File
                            </a>

                            <a href="{{ route('cesop.index') }}"
                               class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 active:bg-gray-900 focus:outline-none focus:border-gray-900 focus:ring focus:ring-gray-300 disabled:opacity-25 transition">
                                Encrypt Another File
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
