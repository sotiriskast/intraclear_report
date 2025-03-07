<?php
namespace App\Livewire;

use Illuminate\View\View;
use Livewire\Component;
use App\Models\Merchant;

class MerchantDashboardPage extends Component
{
    public $selectedMerchantId;
    public $merchants;

    public function mount(): void
    {
        $this->merchants = Merchant::active()->get();

        // Default to null (all merchants) if there are no merchants
        // otherwise select the first one
        if ($this->merchants->isNotEmpty()) {
            $this->selectedMerchantId = $this->merchants->first()->id;
        } else {
            $this->selectedMerchantId = null;
        }
    }

    public function updatedSelectedMerchantId($value): void
    {
        // Allow null value to represent all merchants
        if ($value === '') {
            $this->selectedMerchantId = null;
        }
        // This will refresh the React component since the props change
    }

    public function render(): View
    {
        return view('livewire.merchant-dashboard-page');
    }
}
