<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use App\Models\ShopSetting;
use App\Repositories\ShopSettingRepository;
use Illuminate\Http\Request;

class ShopSettingController extends Controller
{
    protected $shopSettingRepository;

    public function __construct(ShopSettingRepository $shopSettingRepository)
    {
        $this->shopSettingRepository = $shopSettingRepository;
    }

    /**
     * Display a listing of the shop settings.
     */
    public function index()
    {
        $shopSettings = ShopSetting::with(['shop.merchant'])
            ->paginate(10);

        return view('admin.shop-settings.index', compact('shopSettings'));
    }

    /**
     * Show the form for creating new shop settings.
     */
    public function create(Request $request)
    {
        $shop = null;
        if ($request->has('shop_id')) {
            $shop = Shop::findOrFail($request->shop_id);
        }

        $shops = Shop::doesntHave('settings')->get();

        return view('admin.shop-settings.create', compact('shops', 'shop'));
    }

    /**
     * Store newly created shop settings in storage.
     */
    public function store(Request $request)
    {
        $validated = $this->validateShopSettings($request);

        // Check if settings already exist for this shop
        $existingSetting = ShopSetting::where('shop_id', $validated['shop_id'])
            ->exists();

        if ($existingSetting) {
            return redirect()->route('admin.shop-settings.create')
                ->with('error', 'A shop setting already exists and cannot be created again.');
        }

        // Convert percentages and amounts to stored format (multiplied by 100)
        $validated = $this->convertValuesToStorageFormat($validated);

        ShopSetting::create($validated);

        $shop = Shop::find($validated['shop_id']);

        return redirect()->route('admin.shops.settings', $shop)
            ->with('message', 'Shop settings created successfully.');
    }

    /**
     * Show the form for editing the shop settings.
     */
    public function edit(ShopSetting $shopSetting)
    {
        $shop = Shop::find($shopSetting->shop_id);

        // Convert settings values from storage format to display format
        $shopSetting = $this->convertValuesToDisplayFormat($shopSetting);

        return view('admin.shop-settings.edit', compact('shopSetting', 'shop'));
    }

    /**
     * Update the specified shop settings in storage.
     */
    public function update(Request $request, ShopSetting $shopSetting)
    {
        $validated = $this->validateShopSettings($request);

        // Convert percentages and amounts to stored format (multiplied by 100)
        $validated = $this->convertValuesToStorageFormat($validated);

        $shopSetting->update($validated);

        $shop = Shop::find($shopSetting->shop_id);

        return redirect()->route('admin.shops.settings', $shop)
            ->with('message', 'Shop settings updated successfully.');
    }

    /**
     * Remove the shop settings from storage.
     */
    public function destroy(ShopSetting $shopSetting)
    {
        $shop = Shop::find($shopSetting->shop_id);
        $shopSetting->delete();

        return redirect()->route('admin.shops.settings', $shop)
            ->with('message', 'Shop settings deleted successfully.');
    }

    /**
     * Validate the shop settings data.
     */
    private function validateShopSettings(Request $request)
    {
        return $request->validate([
            'shop_id' => 'required|integer|exists:shops,id',
            'rolling_reserve_percentage' => 'required|numeric|min:0|max:100',
            'holding_period_days' => 'required|integer|min:1|max:365',
            'mdr_percentage' => 'required|numeric|min:0|max:100',
            'transaction_fee' => 'required|numeric|min:0',
            'payout_fee' => 'required|numeric|min:0',
            'refund_fee' => 'required|numeric|min:0',
            'declined_fee' => 'required|numeric|min:0',
            'chargeback_fee' => 'required|numeric|min:0',
            'monthly_fee' => 'required|numeric|min:0',
            'mastercard_high_risk_fee_applied' => 'required|numeric|min:0',
            'visa_high_risk_fee_applied' => 'required|numeric|min:0',
            'setup_fee' => 'required|numeric|min:0',
            'setup_fee_charged' => 'sometimes|boolean',
            'exchange_rate_markup' => 'required|numeric|min:1',
            'fx_rate_markup' => 'required|numeric|min:0',
        ]);
    }

    /**
     * Convert percentages and monetary values to storage format (multiplied by 100).
     */
    private function convertValuesToStorageFormat(array $data)
    {
        $fieldsToMultiply = [
            'rolling_reserve_percentage',
            'mdr_percentage',
            'transaction_fee',
            'payout_fee',
            'refund_fee',
            'declined_fee',
            'chargeback_fee',
            'monthly_fee',
            'mastercard_high_risk_fee_applied',
            'visa_high_risk_fee_applied',
            'setup_fee',
            'fx_rate_markup',
        ];

        foreach ($fieldsToMultiply as $field) {
            if (isset($data[$field])) {
                $data[$field] = round($data[$field] * 100);
            }
        }

        // Set default value for setup_fee_charged
        $data['setup_fee_charged'] = $data['setup_fee_charged'] ?? false;

        // Handle exchange_rate_markup (which is not multiplied by 100)
        if (isset($data['exchange_rate_markup'])) {
            $data['exchange_rate_markup'] = (float)$data['exchange_rate_markup'];
        }

        return $data;
    }

    /**
     * Convert percentages and monetary values from storage format to display format.
     */
    private function convertValuesToDisplayFormat(ShopSetting $setting)
    {
        $fieldsToConvert = [
            'rolling_reserve_percentage',
            'mdr_percentage',
            'transaction_fee',
            'payout_fee',
            'refund_fee',
            'declined_fee',
            'chargeback_fee',
            'monthly_fee',
            'mastercard_high_risk_fee_applied',
            'visa_high_risk_fee_applied',
            'setup_fee',
            'fx_rate_markup',
        ];

        foreach ($fieldsToConvert as $field) {
            $setting->$field = $setting->$field / 100;
        }

        return $setting;
    }
}
