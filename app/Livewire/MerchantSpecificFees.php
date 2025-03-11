<?php

namespace App\Livewire;

use App\Models\FeeType;
use App\Models\Merchant;
use App\Models\MerchantFee;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
#[Lazy]
#[Layout('layouts.app', ['header' => 'Merchant Fee Details'])]
#[Title('Merchant Fee Details')]
class MerchantSpecificFees extends Component
{
    use WithPagination;

    public $merchant;

    public $feeTypes;

    public $selectedFeeTypeId;

    public $amount;

    public $effectiveFrom;

    public $effectiveTo;

    public $active = true;

    public $showCreateModal = false;

    public $editMerchantFeeId = null;

    protected $rules = [
        'selectedFeeTypeId' => 'required|exists:fee_types,id',
        'amount' => 'required|numeric|min:0',
        'active' => 'boolean',
        'effectiveFrom' => 'required|date',
        'effectiveTo' => 'nullable|date|after:effectiveFrom',
    ];

    public function mount(Merchant $merchant)
    {
        $this->merchant = $merchant;
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
            'merchant_id' => $this->merchant->id,
            'fee_type_id' => $this->selectedFeeTypeId,
            'amount' => $this->amount,
            'effective_from' => $this->effectiveFrom,
            'effective_to' => $this->effectiveTo,
            'active' => $this->active,
        ]);

        session()->flash('message', 'Merchant fee created successfully.');
        $this->resetForm();
    }

    public function editMerchantFee($id)
    {
        $merchantFee = MerchantFee::findOrFail($id);

        $this->editMerchantFeeId = $id;
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
            'selectedFeeTypeId',
            'amount',
            'active',
            'effectiveFrom',
            'effectiveTo',
            'showCreateModal',
            'editMerchantFeeId',
        ]);
        $this->effectiveFrom = now()->format('Y-m-d');
    }

    public function render()
    {
        $merchantFees = $this->merchant->merchantFees()
            ->with('feeType')
            ->latest()
            ->paginate(10);

        return view('livewire.merchant-specific-fees', [
            'merchantFees' => $merchantFees,
        ]);
    }
}
