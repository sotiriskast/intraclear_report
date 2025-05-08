<!-- admin/merchants/edit.blade.php -->
<x-app-layout>
    <x-slot name="header">
        <h2 class="text-2xl font-bold text-gray-800">
            {{ __('Edit Merchant') }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4">
            <div class="bg-white shadow-sm overflow-hidden sm:rounded-lg p-6">
                <form action="{{ route('merchants.update', $merchant) }}" method="POST">
                    @csrf
                    @method('PUT')

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700">{{ __('Name') }}</label>
                            <input id="name" class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" type="text" name="name" value="{{ old('name', $merchant->name) }}" required />
                            @error('name')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700">{{ __('Email') }}</label>
                            <input id="email" class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" type="email" name="email" value="{{ old('email', $merchant->email) }}" required />
                            @error('email')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="phone" class="block text-sm font-medium text-gray-700">{{ __('Phone') }}</label>
                            <input id="phone" class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" type="text" name="phone" value="{{ old('phone', $merchant->phone) }}" />
                            @error('phone')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="iban" class="block text-sm font-medium text-gray-700">{{ __('Phone') }}</label>
                            <input id="iban" class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" type="text" name="iban" value="{{ old('iban', $merchant->iban) }}" />
                            @error('iban')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="legal_name" class="block text-sm font-medium text-gray-700">{{ __('Legal Name') }}</label>
                            <input id="legal_name" class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" type="text" name="legal_name" value="{{ old('legal_name', $merchant->legal_name) }}" />
                            @error('legal_name')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="country_select" class="block text-sm font-medium text-gray-700">{{ __('Country') }}</label>
                            <select id="country_select" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" onchange="updateCountryCode()">
                                <option value="">Select a country</option>
                                @foreach($countries as $code => $name)
                                    <option value="{{ $code }}" {{ $merchant->iso_country_code == $code ? 'selected' : '' }}>
                                        {{ $name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="register_country" class="block text-sm font-medium text-gray-700">{{ __('Register Country') }}</label>
                            <input id="register_country" readonly class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" type="text" name="register_country" value="{{ old('register_country', $merchant->register_country) }}" />
                            @error('register_country')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="iso_country_code" class="block text-sm font-medium text-gray-700">{{ __('ISO Country Code') }}</label>
                            <input id="iso_country_code" class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" type="text" name="iso_country_code" value="{{ old('iso_country_code', $merchant->iso_country_code) }}" maxlength="2" readonly />
                            @error('iso_country_code')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="city" class="block text-sm font-medium text-gray-700">{{ __('City') }}</label>
                            <input id="city" class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" type="text" name="city" value="{{ old('city', $merchant->city) }}" />
                            @error('city')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="street" class="block text-sm font-medium text-gray-700">{{ __('Street') }}</label>
                            <input id="street" class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" type="text" name="street" value="{{ old('street', $merchant->street) }}" />
                            @error('street')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="postcode" class="block text-sm font-medium text-gray-700">{{ __('Postcode') }}</label>
                            <input id="postcode" class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" type="text" name="postcode" value="{{ old('postcode', $merchant->postcode) }}" />
                            @error('postcode')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="vat" class="block text-sm font-medium text-gray-700">{{ __('VAT/TIC') }}</label>
                            <input id="vat" class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" type="text" name="vat" value="{{ old('vat', $merchant->vat) }}" />
                            @error('vat')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="mcc1" class="block text-sm font-medium text-gray-700">{{ __('MCC1') }}</label>
                            <input id="mcc1" class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" type="text" name="mcc1" value="{{ old('mcc1', $merchant->mcc1) }}" />
                            @error('mcc1')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="mcc2" class="block text-sm font-medium text-gray-700">{{ __('MCC2') }}</label>
                            <input id="mcc2" class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" type="text" name="mcc2" value="{{ old('mcc2', $merchant->mcc2) }}" />
                            @error('mcc2')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="mcc3" class="block text-sm font-medium text-gray-700">{{ __('MCC3') }}</label>
                            <input id="mcc3" class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" type="text" name="mcc3" value="{{ old('mcc3', $merchant->mcc3) }}" />
                            @error('mcc3')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="flex items-center mt-4">
                            <input id="active" type="checkbox" name="active" value="1" {{ old('active', $merchant->active) ? 'checked' : '' }} class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                            <label for="active" class="ml-2 text-sm text-gray-600">{{ __('Active') }}</label>
                        </div>
                    </div>

                    <div class="flex items-center justify-end mt-6">
                        <a href="{{ url()->previous() }}" class="inline-flex items-center px-4 py-2 bg-gray-300 border border-transparent rounded-md font-semibold text-xs text-gray-800 uppercase tracking-widest hover:bg-gray-400 active:bg-gray-500 focus:outline-none focus:border-gray-500 focus:ring focus:ring-gray-300 mr-3">
                            {{ __('Cancel') }}
                        </a>
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:outline-none focus:border-blue-700 focus:ring focus:ring-blue-200 active:bg-blue-700 transition">
                            {{ __('Update') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script>
        function updateCountryCode() {
            const countrySelect = document.getElementById('country_select');
            const registerCountryInput = document.getElementById('register_country');
            const isoCountryCodeInput = document.getElementById('iso_country_code');

            if (countrySelect.value) {
                const selectedOption = countrySelect.options[countrySelect.selectedIndex];
                isoCountryCodeInput.value = countrySelect.value;
                registerCountryInput.value = selectedOption.text;
            } else {
                isoCountryCodeInput.value = '';
                registerCountryInput.value = '';
            }
        }

        // Run on page load to set initial values
        document.addEventListener('DOMContentLoaded', function() {
            updateCountryCode();
        });
    </script>
</x-app-layout>
