<?php

namespace App\Livewire;

use App\Models\Shop;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Lazy]
#[Layout('layouts.app', ['header' => 'Shops'])]
#[Title('Shops')]
class ShopManagement extends Component
{
    use WithPagination;

    public $search = '';

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function render()
    {
        $shops = Shop::with('merchant')
            ->when($this->search, function ($query) {
                return $query->where(function ($q) {
                    $q->where('shop_id', 'like', '%'.$this->search.'%')
                        ->orWhere('email', 'like', '%'.$this->search.'%')
                        ->orWhere('website', 'like', '%'.$this->search.'%')
                        ->orWhere('owner_name', 'like', '%'.$this->search.'%')
                        ->orWhereHas('merchant', function ($merchantQuery) {
                            $merchantQuery->where('name', 'like', '%'.$this->search.'%');
                        });
                });
            })
            ->orderBy('shop_id', 'desc')
            ->paginate(15);

        return view('livewire.shop-management', [
            'shops' => $shops,
            'searchTerm' => $this->search,
        ]);
    }
}
