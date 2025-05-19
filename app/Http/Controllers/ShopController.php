<?php

namespace App\Http\Controllers;

use App\Models\Merchant;
use App\Models\Shop;
use App\Repositories\ShopRepository;
use Illuminate\Http\Request;

class ShopController extends Controller
{
    protected $shopRepository;

    public function __construct(ShopRepository $shopRepository)
    {
        $this->shopRepository = $shopRepository;
    }

    /**
     * Display a listing of shops for the specified merchant.
     *
     * @param Merchant $merchant
     * @return \Illuminate\View\View
     */
    public function index(Merchant $merchant)
    {
        $shops = $this->shopRepository->getByMerchant($merchant->id)
            ->paginate(10);

        return view('admin.shops.index', compact('shops', 'merchant'));
    }

    /**
     * Show the form for editing the specified shop.
     *
     * @param Shop $shop
     * @return \Illuminate\View\View
     */
    public function edit(Shop $shop)
    {
        $merchant = $shop->merchant;
        return view('admin.shops.edit', compact('shop', 'merchant'));
    }

    /**
     * Update the specified shop in storage.
     *
     * @param Request $request
     * @param Shop $shop
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, Shop $shop)
    {
        $validated = $request->validate([
            'shop_id' => 'required|integer|unique:shops,shop_id,' . $shop->id . ',id,merchant_id,' . $shop->merchant_id,
            'email' => 'nullable|email|max:255',
            'website' => 'nullable|url|max:255',
            'owner_name' => 'nullable|string|max:255',
            'active' => 'boolean',
        ]);

        // Set active to false if not provided
        $validated['active'] = $validated['active'] ?? false;

        $shop->update($validated);

        return redirect()->route('admin.merchants.shops', $shop->merchant)
            ->with('message', 'Shop updated successfully.');
    }

    /**
     * Display shop settings.
     *
     * @param Shop $shop
     * @return \Illuminate\View\View
     */
    public function settings(Shop $shop)
    {
        // Eager load the settings and fees.feeType relationships
        $shop->load(['settings', 'fees.feeType']);

        $shopSettings = $shop->settings;
        $merchant = $shop->merchant;

        return view('admin.shops.settings', compact('shop', 'shopSettings', 'merchant'));
    }
}
