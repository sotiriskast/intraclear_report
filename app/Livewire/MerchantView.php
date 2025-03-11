<?php

namespace App\Livewire;

use App\Models\Merchant;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Title;
use Livewire\Component;
#[Lazy]
#[Layout('layouts.app', ['header' => 'Merchant Details'])]
#[Title('Merchant Details')]
class MerchantView extends Component
{
    public $merchant;

    public function mount(Merchant $merchant)
    {
        $this->merchant = $merchant;
    }

    public function manageFees()
    {
        return redirect()->route('merchant.fees', $this->merchant->id);
    }

    // Add this method
    public function manageApi()
    {
        return redirect()->route('merchant.api', $this->merchant->id);
    }

    public function render()
    {
        return view('livewire.merchant-view', [
            'merchant' => $this->merchant,
        ]);
    }
}
