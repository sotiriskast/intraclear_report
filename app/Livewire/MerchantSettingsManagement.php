<?php

namespace App\Livewire;

use App\Models\MerchantSetting;
use App\Repositories\MerchantRepository;
use App\Repositories\MerchantSettingRepository;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
#[Lazy]
#[Layout('layouts.app', ['header' => 'Merchant Settings'])]
#[Title('Merchant Settings')]
class MerchantSettingsManagement extends Component
{
    use WithPagination;

    public $merchants;

    public $selectedMerchantId;

    public $rollingReservePercentage = 10;

    public $holdingPeriodDays = 180;

    public $mdrPercentage = 5;

    public $transactionFee = 0.35;

    public $declinedFee = 0.25;

    public $payoutFee = 1;

    public $refundFee = 1.25;

    public $chargebackFee = 25;

    public $monthlyFee = 150;

    public $mastercardHighRiskFee = 500;

    public $visaHighRiskFee = 450;

    public $setupFee = 500;

    public $setupFeeCharged = false;

    public $active = true;

    public $showCreateModal = false;

    public $editSettingId = null;
    public $exchangeRateMarkup = 1.01;

    private $merchantSettingRepository;

    private $merchantRepository;

    public $fxRateMarkup = 0;
    protected function rules()
    {
        return [
            'selectedMerchantId' => 'required',
            'rollingReservePercentage' => 'required|integer|min:0|max:10000',
            'holdingPeriodDays' => 'required|integer|min:1|max:365',
            'mdrPercentage' => 'required|numeric|min:0|max:10000',
            'transactionFee' => 'required|numeric|min:0',
            'payoutFee' => 'required|numeric|min:0',
            'refundFee' => 'required|numeric|min:0',
            'declinedFee' => 'required|numeric|min:0',
            'chargebackFee' => 'required|numeric|min:0',
            'monthlyFee' => 'required|numeric|min:0',
            'mastercardHighRiskFee' => 'required|numeric|min:0',
            'visaHighRiskFee' => 'required|numeric|min:0',
            'setupFee' => 'required|numeric|min:0',
            'setupFeeCharged' => 'boolean',
            'exchangeRateMarkup' => 'required|numeric',
            'fxRateMarkup' => 'required|numeric|min:0',
        ];
    }

    public function boot(
        MerchantSettingRepository $merchantSettingRepository,
        MerchantRepository $merchantRepository
    ) {
        $this->merchantSettingRepository = $merchantSettingRepository;
        $this->merchantRepository = $merchantRepository;
    }

    public function mount()
    {
        $this->merchants = $this->merchantRepository->getActive();
    }

    public function openCreateModal()
    {
        $this->resetForm();
        $this->showCreateModal = true;
    }

    public function create()
    {
        // Double check if is already exists
        $existingSetting = MerchantSetting::where('merchant_id', $this->selectedMerchantId)
            ->exists();

        if ($existingSetting) {
            session()->flash('error', 'A merchant setting already exists and cannot be created again.');

            return;
        }
        $this->validate();

        $this->merchantSettingRepository->create([
            'merchant_id' => $this->selectedMerchantId,
            'rolling_reserve_percentage' => round($this->rollingReservePercentage * 100),
            'holding_period_days' => $this->holdingPeriodDays,
            'mdr_percentage' => round($this->mdrPercentage * 100),
            'transaction_fee' => round($this->transactionFee * 100),
            'payout_fee' => round($this->payoutFee * 100),
            'refund_fee' => round($this->refundFee * 100),
            'declined_fee' => round($this->declinedFee * 100),
            'chargeback_fee' => round($this->chargebackFee * 100),
            'monthly_fee' => round($this->monthlyFee * 100),
            'mastercard_high_risk_fee_applied' => round($this->mastercardHighRiskFee * 100),
            'visa_high_risk_fee_applied' => round($this->visaHighRiskFee * 100),
            'setup_fee' => round($this->setupFee * 100),
            'setup_fee_charged' => $this->setupFeeCharged,
            'exchange_rate_markup' => $this->exchangeRateMarkup,
            'fx_rate_markup' => round($this->fxRateMarkup * 100),

        ]);

        session()->flash('message', 'Merchant settings created successfully.');
        $this->resetForm();
    }

    public function editSetting($id)
    {
        $setting = $this->merchantSettingRepository->findById($id);

        $this->editSettingId = $id;
        $this->selectedMerchantId = $setting->merchant_id;
        $this->rollingReservePercentage = $setting->rolling_reserve_percentage / 100;
        $this->holdingPeriodDays = $setting->holding_period_days;
        $this->mdrPercentage = $setting->mdr_percentage / 100;
        $this->transactionFee = $setting->transaction_fee / 100;
        $this->payoutFee = $setting->payout_fee / 100;
        $this->refundFee = $setting->refund_fee / 100;
        $this->declinedFee = $setting->declined_fee / 100;
        $this->chargebackFee = $setting->chargeback_fee / 100;
        $this->monthlyFee = $setting->monthly_fee / 100;
        $this->mastercardHighRiskFee = $setting->mastercard_high_risk_fee_applied / 100;
        $this->visaHighRiskFee = $setting->visa_high_risk_fee_applied / 100;
        $this->setupFee = $setting->setup_fee / 100;
        $this->setupFeeCharged = $setting->setup_fee_charged;
        $this->showCreateModal = true;
        $this->exchangeRateMarkup = $setting->exchange_rate_markup;
        $this->fxRateMarkup = $setting->fx_rate_markup / 100;
    }

    public function update()
    {
        $this->validate();

        $this->merchantSettingRepository->update($this->editSettingId, [
            'merchant_id' => $this->selectedMerchantId,
            'rolling_reserve_percentage' => round($this->rollingReservePercentage * 100),
            'holding_period_days' => $this->holdingPeriodDays,
            'mdr_percentage' => round($this->mdrPercentage * 100),
            'transaction_fee' => round($this->transactionFee * 100),
            'declined_fee' => round($this->declinedFee * 100),
            'payout_fee' => round($this->payoutFee * 100),
            'refund_fee' => round($this->refundFee * 100),
            'chargeback_fee' => round($this->chargebackFee * 100),
            'monthly_fee' => round($this->monthlyFee * 100),
            'mastercard_high_risk_fee_applied' => round($this->mastercardHighRiskFee * 100),
            'visa_high_risk_fee_applied' => round($this->visaHighRiskFee * 100),
            'setup_fee' => round($this->setupFee * 100),
            'setup_fee_charged' => $this->setupFeeCharged,
            'exchange_rate_markup' => $this->exchangeRateMarkup,
            'fx_rate_markup' => round($this->fxRateMarkup * 100),

        ]);

        session()->flash('message', 'Merchant settings updated successfully.');
        $this->resetForm();
    }

    public function delete($id)
    {
        $this->merchantSettingRepository->delete($id);
        session()->flash('message', 'Merchant settings deleted successfully.');
    }

    public function resetForm()
    {
        $this->reset([
            'selectedMerchantId',
            'rollingReservePercentage',
            'holdingPeriodDays',
            'mdrPercentage',
            'transactionFee',
            'payoutFee',
            'refundFee',
            'declinedFee',
            'chargebackFee',
            'monthlyFee',
            'mastercardHighRiskFee',
            'visaHighRiskFee',
            'setupFee',
            'setupFeeCharged',
            'active',
            'showCreateModal',
            'editSettingId',
            'exchangeRateMarkup',
            'fxRateMarkup',

        ]);
    }
    /**
     * Format exchange rate to remove trailing zeros
     *
     * @param float $value The exchange rate value
     * @return string Formatted exchange rate
     */
    public function formatExchangeRate($value)
    {
        // This removes trailing zeros
        return rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.');
    }
    /**
     * Format FX rate markup as percentage with two decimal places
     *
     * @param int $value The FX rate markup value in basis points
     * @return string Formatted percentage with two decimal places
     */
    public function formatFxRateMarkup($value)
    {
        // Convert from basis points to percentage with exactly two decimal places
        return number_format($value / 100, 2) . '%';
    }
    public function render()
    {
        $merchantSettings = $this->merchantSettingRepository->getAll(['merchant']);

        return view('livewire.merchant-settings-management', [
            'merchantSettings' => $merchantSettings,
        ]);
    }
}
