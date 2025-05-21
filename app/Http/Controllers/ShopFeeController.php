<?php

namespace App\Http\Controllers;

use App\Models\FeeType;
use App\Models\Shop;
use App\Models\ShopFee;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ShopFeeController extends Controller
{
    /**
     * Display a listing of shop fees.
     */
    public function index()
    {
        $shopFees = ShopFee::with(['shop', 'shop.merchant', 'feeType'])
            ->latest()
            ->paginate(10);

        return view('admin.shop-fees.index', compact('shopFees'));
    }

    /**
     * Show the form for creating a new shop fee.
     *
     * @param Request $request
     * @return \Illuminate\View\View
     */
    public function create(Request $request)
    {
        // Get the selected shop if shop_id parameter is present
        $selectedShop = null;
        $selectedShopId = null;

        if ($request->has('shop_id')) {
            $selectedShopId = $request->shop_id;
            $selectedShop = Shop::with('merchant')->find($selectedShopId);
        }

        // Get all active shops for the dropdown
        $shops = Shop::with('merchant')->where('active', true)->get();

        // Get all fee types
        $feeTypes = FeeType::all();

        return view('admin.shop-fees.create', compact('shops', 'feeTypes', 'selectedShop', 'selectedShopId'));
    }

    /**
     * Store a newly created shop fee in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'shop_id' => 'required|exists:shops,id',
            'fee_type_id' => 'required|exists:fee_types,id',
            'amount' => 'required|numeric|min:0',
            'effective_from' => 'required|date',
            'effective_to' => 'nullable|date|after:effective_from',
            'active' => 'boolean',
        ]);

        // Convert amount to cents if the fee type is not a percentage
        $feeType = FeeType::find($validated['fee_type_id']);
        if (!$feeType->is_percentage) {
            $validated['amount'] = $validated['amount'] * 100;
        } else {
            // For percentage, store as basis points (e.g., 5.25% stored as 525)
            $validated['amount'] = $validated['amount'] * 100;
        }

        // Set active to false if not provided
        $validated['active'] = $validated['active'] ?? false;

        $shopFee = ShopFee::create($validated);
        $shop = Shop::find($validated['shop_id']);

        // Redirect back to the shop settings page if coming from there
        if ($request->has('shop_id')) {
            return redirect()->route('admin.shops.settings', $shop)
                ->with('message', 'Shop fee created successfully.');
        }

        return redirect()->route('admin.shop-fees.index')
            ->with('message', 'Shop fee created successfully.');
    }

    /**
     * Show the form for editing the specified shop fee.
     */
    public function edit(ShopFee $shopFee)
    {
        $shops = Shop::with('merchant')->get();
        $feeTypes = FeeType::all();

        // Convert amount from cents to dollars
        $shopFee->amount = $shopFee->amount / 100;

        return view('admin.shop-fees.edit', compact('shopFee', 'shops', 'feeTypes'));
    }

    /**
     * Update the specified shop fee in storage.
     */
    public function update(Request $request, ShopFee $shopFee)
    {
        $validated = $request->validate([
            'shop_id' => 'required|exists:shops,id',
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

        $shopFee->update($validated);

        return redirect()->route('admin.shop-fees.index')
            ->with('message', 'Shop fee updated successfully.');
    }

    /**
     * Remove the specified shop fee from storage.
     */
    public function destroy(ShopFee $shopFee)
    {
        $shopFee->delete();

        return redirect()->back()
            ->with('message', 'Shop fee deleted successfully.');
    }
}
