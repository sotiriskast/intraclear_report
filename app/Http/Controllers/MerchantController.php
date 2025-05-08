<?php

namespace App\Http\Controllers;

use App\Models\Merchant;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\Intl\Countries;

class MerchantController extends Controller
{
    /**
     * Show the form for editing the specified merchant.
     */
    public function edit(Merchant $merchant)
    {
        $countries = Countries::getNames();
        return view('admin.merchants.edit', compact('merchant', 'countries'));
    }

    /**
     * Update the specified merchant in storage.
     */
    public function update(Request $request, Merchant $merchant)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', Rule::unique('merchants')->ignore($merchant->id)],
            'phone' => 'nullable|string|max:20',
            'legal_name' => 'nullable|string|max:255',
            'iban' => 'nullable|string|max:255',
            'register_country' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'street' => 'nullable|string|max:255',
            'postcode' => 'nullable|string|max:20',
            'vat' => 'nullable|string|max:50',
            'mcc1' => 'nullable|string|max:10',
            'mcc2' => 'nullable|string|max:10',
            'mcc3' => 'nullable|string|max:10',
            'iso_country_code' => 'nullable|string|size:2',
            'active' => 'boolean',
        ]);

        // Set active to false if not provided
        $validated['active'] = $validated['active'] ?? false;

        $merchant->update($validated);

        return redirect()->route('merchant.view', $merchant)
            ->with('message', 'Merchant updated successfully.');
    }
}
