<?php

namespace App\Livewire;

use App\Models\Shop;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Lazy;
use Livewire\Component;

#[Lazy]
#[Layout('layouts.app')]
class ShopView extends Component
{
    public $shop;

    public function mount(Shop $shop)
    {
        $this->shop = $shop->load('merchant', 'settings');
    }

    public function render()
    {
        return view('livewire.shop-view', [
            'shop' => $this->shop,
        ]);
    }
}
