<?php

namespace App\Http\Controllers;

use App\Models\FeeType;
use App\Models\Merchant;
use App\Models\MerchantFee;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MerchantFeeController extends Controller
{
    /**
     * Display a listing of merchant fees.
     */
    public function index()
    {
        $merchantFees = MerchantFee::with(['merchant', 'feeType'])
            ->latest()
            ->paginate(10);

        return view('admin.merchant-fees.index', compact('merchantFees'));
    }

    /**
     * Show the form for creating a new merchant fee.
     */
    public function create()
    {
        $merchants = Merchant::active()->get();
        $feeTypes = FeeType::all();

        return view('admin.merchant-fees.create', compact('merchants', 'feeTypes'));
    }

    /**
     * Store a newly created merchant fee in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'merchant_id' => 'required|exists:merchants,id',
            'fee_type_id' => 'required|exists:fee_types,id',
            'amount' => 'required|numeric|min:0',
            'active' => 'boolean',
            'effective_from' => 'required|date',
            'effective_to' => 'nullable|date|after:effective_from',
        ]);

        // Convert amount to cents
        $validated['amount'] = $validated['amount'] * 100;

        // Set active to false if not provided
        $validated['active'] = $validated['active'] ?? false;

        MerchantFee::create($validated);

        return redirect()->route('admin.merchant-fees.index')
            ->with('message', 'Merchant fee created successfully.');
    }

    /**
     * Show the form for editing the specified merchant fee.
     */
    public function edit(MerchantFee $merchantFee)
    {
        $merchants = Merchant::active()->get();
        $feeTypes = FeeType::all();

        // Convert amount from cents to dollars
        $merchantFee->amount = $merchantFee->amount / 100;

        return view('admin.merchant-fees.edit', compact('merchantFee', 'merchants', 'feeTypes'));
    }

    /**
     * Update the specified merchant fee in storage.
     */
    public function update(Request $request, MerchantFee $merchantFee)
    {
        $validated = $request->validate([
            'merchant_id' => 'required|exists:merchants,id',
            'fee_type_id' => 'required|exists:fee_types,id',
            'amount' => 'required|numeric|min:0',
            'active' => 'boolean',
            'effective_from' => 'required|date',
            'effective_to' => 'nullable|date|after:effective_from',
        ]);

        // Convert amount to cents
        $validated['amount'] = $validated['amount'] * 100;

        // Set active to false if not provided
        $validated['active'] = $validated['active'] ?? false;

        $merchantFee->update($validated);

        return redirect()->route('admin.merchant-fees.index')
            ->with('message', 'Merchant fee updated successfully.');
    }

    /**
     * Remove the specified merchant fee from storage.
     */
    public function destroy(MerchantFee $merchantFee)
    {
        $merchantFee->delete();

        return redirect()->route('admin.merchant-fees.index')
            ->with('message', 'Merchant fee deleted successfully.');
    }
}
