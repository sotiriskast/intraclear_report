<?php

namespace App\Http\Controllers;

use App\Models\Merchant;
use App\Models\MerchantSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MerchantSettingController extends Controller
{
    /**
     * Display a listing of the merchant settings.
     */
    public function index()
    {
        $merchantSettings = MerchantSetting::with(['merchant'])
            ->paginate(10);

        return view('admin.merchant-settings.index', compact('merchantSettings'));
    }

    /**
     * Show the form for creating new merchant settings.
     */
    public function create()
    {
        $merchants = Merchant::active()->get();

        return view('admin.merchant-settings.create', compact('merchants'));
    }

    /**
     * Store newly created merchant settings in storage.
     */
    public function store(Request $request)
    {
        $validated = $this->validateMerchantSettings($request);

        // Check if settings already exist for this merchant
        $existingSetting = MerchantSetting::where('merchant_id', $validated['merchant_id'])
            ->exists();

        if ($existingSetting) {
            return redirect()->route('admin.merchant-settings.create')
                ->with('error', 'A merchant setting already exists and cannot be created again.');
        }

        // Convert percentages and amounts to stored format (multiplied by 100)
        $validated = $this->convertValuesToStorageFormat($validated);

        MerchantSetting::create($validated);

        return redirect()->route('admin.merchant-settings.index')
            ->with('message', 'Merchant settings created successfully.');
    }

    /**
     * Show the form for editing the merchant settings.
     */
    public function edit(MerchantSetting $merchantSetting)
    {
        $merchant = Merchant::query()->find($merchantSetting->merchant_id);

        // Convert settings values from storage format to display format
        $merchantSetting = $this->convertValuesToDisplayFormat($merchantSetting);

        return view('admin.merchant-settings.edit', compact('merchantSetting', 'merchant'));
    }

    /**
     * Update the specified merchant settings in storage.
     */
    public function update(Request $request, MerchantSetting $merchantSetting)
    {
        $validated = $this->validateMerchantSettings($request);

        // Convert percentages and amounts to stored format (multiplied by 100)
        $validated = $this->convertValuesToStorageFormat($validated);

        $merchantSetting->update($validated);

        return redirect()->route('admin.merchant-settings.index')
            ->with('message', 'Merchant settings updated successfully.');
    }

    /**
     * Remove the merchant settings from storage.
     */
    public function destroy(MerchantSetting $merchantSetting)
    {
        $merchantSetting->delete();

        return redirect()->route('admin.merchant-settings.index')
            ->with('message', 'Merchant settings deleted successfully.');
    }

    /**
     * Validate the merchant settings data.
     */
    private function validateMerchantSettings(Request $request)
    {
        return $request->validate([
            'merchant_id' => 'required|integer|exists:merchants,id',
            'rolling_reserve_percentage' => 'required|numeric|min:0|max:100',
            'holding_period_days' => 'required|integer|min:1|max:365',
            'mdr_percentage' => 'required|numeric|min:0|max:100',
            'transaction_fee' => 'required|numeric|min:0',
            'payout_fee' => 'required|numeric|min:0',
            'refund_fee' => 'required|numeric|min:0',
            'declined_fee' => 'required|numeric|min:0',
            'chargeback_fee' => 'required|numeric|min:0',
            'monthly_fee' => 'required|numeric|min:0',
            'mastercard_high_risk_fee' => 'required|numeric|min:0',
            'visa_high_risk_fee' => 'required|numeric|min:0',
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
            'mastercard_high_risk_fee',
            'visa_high_risk_fee',
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

        // Rename field names if necessary to match the database columns
        if (isset($data['mastercard_high_risk_fee'])) {
            $data['mastercard_high_risk_fee_applied'] = $data['mastercard_high_risk_fee'];
            unset($data['mastercard_high_risk_fee']);
        }

        if (isset($data['visa_high_risk_fee'])) {
            $data['visa_high_risk_fee_applied'] = $data['visa_high_risk_fee'];
            unset($data['visa_high_risk_fee']);
        }

        return $data;
    }

    /**
     * Convert percentages and monetary values from storage format to display format.
     */
    private function convertValuesToDisplayFormat(MerchantSetting $setting)
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
            'setup_fee',
            'fx_rate_markup',
        ];

        foreach ($fieldsToConvert as $field) {
            $setting->$field = $setting->$field / 100;
        }

        // Handle renamed fields
        $setting->mastercard_high_risk_fee = $setting->mastercard_high_risk_fee_applied / 100;
        $setting->visa_high_risk_fee = $setting->visa_high_risk_fee_applied / 100;

        return $setting;
    }

    /**
     * Format exchange rate for display.
     */
    public function formatExchangeRate($value)
    {
        return number_format($value, 3);
    }
}
