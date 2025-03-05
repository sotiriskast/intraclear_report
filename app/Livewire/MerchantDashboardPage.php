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
        if ($this->merchants->isNotEmpty()) {
            $this->selectedMerchantId = $this->merchants->first()->id;
        }
    }

    public function updatedSelectedMerchantId(int $value): void
    {
        // This will refresh the React component since the props change
    }

    public function render(): View
    {
        return view('livewire.merchant-dashboard-page');
    }
}
