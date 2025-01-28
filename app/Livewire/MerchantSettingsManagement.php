<?php

namespace App\Livewire;

use App\Models\MerchantSetting;
use App\Repositories\MerchantSettingRepository;
use App\Repositories\MerchantRepository;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class MerchantSettingsManagement extends Component
{
    use WithPagination;

    public $merchants;
    public $selectedMerchantId;
    public $rollingReservePercentage = 10;
    public $holdingPeriodDays = 180;
    public $mdrPercentage = 5;
    public $transactionFee = 0.20;
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

    private $merchantSettingRepository;
    private $merchantRepository;
    protected function rules()
    {
        return [
            'selectedMerchantId' => [
                'required',
                'exists:merchants,id',
                function ($attribute, $value, $fail) {
                    // Check if a setting already exists for this merchant
                    $existingSetting = MerchantSetting::where('merchant_id', $value)
                        ->exists(); // Changed to check for any setting, not just active

                    if ($existingSetting) {
                        $fail('A merchant setting already exists and cannot be created again.');
                    }
                }
            ],
            'rollingReservePercentage' => 'required|integer|min:0|max:10000',
            'holdingPeriodDays' => 'required|integer|min:1|max:365',
            'mdrPercentage' => 'required|integer|min:0|max:10000',
            'transactionFee' => 'required|numeric|min:0',
            'payoutFee' => 'required|numeric|min:0',
            'refundFee' => 'required|numeric|min:0',
            'chargebackFee' => 'required|numeric|min:0',
            'monthlyFee' => 'required|numeric|min:0',
            'mastercardHighRiskFee' => 'required|numeric|min:0',
            'visaHighRiskFee' => 'required|numeric|min:0',
            'setupFee' => 'required|numeric|min:0',
            'setupFeeCharged' => 'boolean',
            'active' => 'boolean',
        ];
    }

    public function boot(
        MerchantSettingRepository $merchantSettingRepository,
        MerchantRepository        $merchantRepository
    )
    {
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
        //Double check if is already exists
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
            'chargeback_fee' => round($this->chargebackFee * 100),
            'monthly_fee' => round($this->monthlyFee * 100),
            'mastercard_high_risk_fee_applied' => round($this->mastercardHighRiskFee * 100),
            'visa_high_risk_fee_applied' => round($this->visaHighRiskFee * 100),
            'setup_fee' => round($this->setupFee * 100),
            'setup_fee_charged' => $this->setupFeeCharged,
            'active' => $this->active
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
        $this->chargebackFee = $setting->chargeback_fee / 100;
        $this->monthlyFee = $setting->monthly_fee / 100;
        $this->mastercardHighRiskFee = $setting->mastercard_high_risk_fee_applied / 100;
        $this->visaHighRiskFee = $setting->visa_high_risk_fee_applied / 100;
        $this->setupFee = $setting->setup_fee / 100;
        $this->setupFeeCharged = $setting->setup_fee_charged;
        $this->active = $setting->active;

        $this->showCreateModal = true;
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
            'payout_fee' => round($this->payoutFee * 100),
            'refund_fee' => round($this->refundFee * 100),
            'chargeback_fee' => round($this->chargebackFee * 100),
            'monthly_fee' => round($this->monthlyFee * 100),
            'mastercard_high_risk_fee_applied' => round($this->mastercardHighRiskFee * 100),
            'visa_high_risk_fee_applied' => round($this->visaHighRiskFee * 100),
            'setup_fee' => round($this->setupFee * 100),
            'setup_fee_charged' => $this->setupFeeCharged,
            'active' => $this->active
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
            'chargebackFee',
            'monthlyFee',
            'mastercardHighRiskFee',
            'visaHighRiskFee',
            'setupFee',
            'setupFeeCharged',
            'active',
            'showCreateModal',
            'editSettingId'
        ]);
    }

    public function render()
    {
        $merchantSettings = $this->merchantSettingRepository->getAll(['merchant']);

        return view('livewire.merchant-settings-management', [
            'merchantSettings' => $merchantSettings
        ]);
    }
}
