<?php

namespace App\Livewire;

use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Merchant;
use App\Models\FeeType;
use App\Models\MerchantFee;

#[Layout('layouts.app')]
class MerchantFeeManagement extends Component
{
    use WithPagination;

    public $merchants;
    public $feeTypes;
    public $selectedMerchantId;
    public $selectedFeeTypeId;
    public $amount;
    public $effectiveFrom;
    public $effectiveTo;
    public $active = true;
    public $showCreateModal = false;
    public $editMerchantFeeId = null;

    protected $rules = [
        'selectedMerchantId' => 'required|exists:merchants,id',
        'selectedFeeTypeId' => 'required|exists:fee_types,id',
        'amount' => 'required|numeric|min:0',
        'active' => 'boolean',
        'effectiveFrom' => 'required|date',
        'effectiveTo' => 'nullable|date|after:effectiveFrom',
    ];

    public function mount()
    {
        $this->merchants = Merchant::active()->get();
        $this->feeTypes = FeeType::all();
        $this->effectiveFrom = now()->format('Y-m-d');
    }

    public function openCreateModal()
    {
        $this->resetForm();
        $this->showCreateModal = true;
    }

    public function create()
    {
        $this->validate();

        MerchantFee::create([
            'merchant_id' => $this->selectedMerchantId,
            'fee_type_id' => $this->selectedFeeTypeId,
            'amount' => $this->amount,
            'effective_from' => $this->effectiveFrom,
            'effective_to' => $this->effectiveTo,
            'active' => $this->active
        ]);

        session()->flash('message', 'Merchant fee created successfully.');
        $this->resetForm();
    }

    public function editMerchantFee($id)
    {
        $merchantFee = MerchantFee::findOrFail($id);

        $this->editMerchantFeeId = $id;
        $this->selectedMerchantId = $merchantFee->merchant_id;
        $this->selectedFeeTypeId = $merchantFee->fee_type_id;
        $this->amount = $merchantFee->amount;
        $this->active = (bool) $merchantFee->active;
        $this->effectiveFrom = $merchantFee->effective_from->format('Y-m-d');
        $this->effectiveTo = $merchantFee->effective_to ? $merchantFee->effective_to->format('Y-m-d') : null;

        $this->showCreateModal = true;
    }

    public function update()
    {
        $this->validate();

        $merchantFee = MerchantFee::findOrFail($this->editMerchantFeeId);
        $merchantFee->update([
            'merchant_id' => $this->selectedMerchantId,
            'fee_type_id' => $this->selectedFeeTypeId,
            'amount' => $this->amount,
            'active' => $this->active,
            'effective_from' => $this->effectiveFrom,
            'effective_to' => $this->effectiveTo,
        ]);

        session()->flash('message', 'Merchant fee updated successfully.');
        $this->resetForm();
    }

    public function delete($id)
    {
        $merchantFee = MerchantFee::findOrFail($id);
        $merchantFee->delete();

        session()->flash('message', 'Merchant fee deleted successfully.');
    }

    public function resetForm()
    {
        $this->reset([
            'selectedMerchantId',
            'selectedFeeTypeId',
            'amount',
            'active',
            'effectiveFrom',
            'effectiveTo',
            'showCreateModal',
            'editMerchantFeeId'
        ]);
        $this->effectiveFrom = now()->format('Y-m-d');
    }

    public function render()
    {
        $merchantFees = MerchantFee::with(['merchant', 'feeType'])
            ->latest()
            ->paginate(10);

        return view('livewire.merchant-fee-management', [
            'merchantFees' => $merchantFees
        ]);
    }
}
