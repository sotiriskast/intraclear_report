<?php

namespace App\Livewire;

use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use App\Models\FeeType;

#[Layout('layouts.app')]
class FeeTypeManagement extends Component
{
    use WithPagination;

    public $name;
    public $key;
    public $frequency_type;
    public $is_percentage = false;
    public $showCreateModal = false;
    public $editFeeTypeId = null;

    protected $rules = [
        'name' => 'required|string|max:255',
        'key' => 'required|string|unique:fee_types,key,NULL,id,deleted_at,NULL',
        'frequency_type' => 'required|in:transaction,daily,weekly,monthly,yearly,one_time',
        'is_percentage' => 'boolean'
    ];

    public function mount()
    {
        $this->frequency_type = 'transaction';
    }

    public function openCreateModal()
    {
        $this->resetForm();
        $this->showCreateModal = true;
    }

    public function create()
    {
        $this->validate();

        FeeType::create([
            'name' => $this->name,
            'key' => $this->key,
            'frequency_type' => $this->frequency_type,
            'is_percentage' => $this->is_percentage
        ]);

        session()->flash('message', 'Fee Type created successfully.');
        $this->resetForm();
    }

    public function editFeeType($id)
    {
        $feeType = FeeType::findOrFail($id);

        $this->editFeeTypeId = $id;
        $this->name = $feeType->name;
        $this->key = $feeType->key;
        $this->frequency_type = $feeType->frequency_type;
        $this->is_percentage = $feeType->is_percentage;

        $this->showCreateModal = true;
    }

    public function update()
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'key' => 'required|string|unique:fee_types,key,'.$this->editFeeTypeId.',id,deleted_at,NULL',
            'frequency_type' => 'required|in:transaction,daily,weekly,monthly,yearly,one_time',
            'is_percentage' => 'boolean'
        ]);

        $feeType = FeeType::findOrFail($this->editFeeTypeId);
        $feeType->update([
            'name' => $this->name,
            'key' => $this->key,
            'frequency_type' => $this->frequency_type,
            'is_percentage' => $this->is_percentage
        ]);

        session()->flash('message', 'Fee Type updated successfully.');
        $this->resetForm();
    }

    public function delete($id)
    {
        $feeType = FeeType::findOrFail($id);
        $feeType->delete();

        session()->flash('message', 'Fee Type deleted successfully.');
    }

    public function resetForm()
    {
        $this->reset([
            'name',
            'key',
            'frequency_type',
            'is_percentage',
            'showCreateModal',
            'editFeeTypeId'
        ]);
        $this->frequency_type = 'transaction';
    }

    public function render()
    {
        $feeTypes = FeeType::withTrashed()->latest()->paginate(10);

        return view('livewire.fee-type-management', [
            'feeTypes' => $feeTypes
        ]);
    }
}
