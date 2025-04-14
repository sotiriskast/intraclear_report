<?php

namespace App\Livewire;

use App\Models\Merchant;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Lazy;
use Livewire\Component;
#[Lazy]
#[Layout('layouts.app')]
class MerchantView extends Component
{
    public $merchant;

    public function mount(Merchant $merchant)
    {
        $this->merchant = $merchant;
    }
    public function render()
    {
        return view('livewire.merchant-view', [
            'merchant' => $this->merchant,
        ]);
    }
}
