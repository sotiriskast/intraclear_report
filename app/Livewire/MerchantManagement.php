<?php

namespace App\Livewire;

use App\Models\Merchant;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
#[Lazy]
#[Layout('layouts.app', ['header' => 'Merchants'])]
#[Title('Merchants')]
class MerchantManagement extends Component
{
    use WithPagination;

    public $search = '';

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function render()
    {
        $merchants = Merchant::when($this->search, function ($query) {
            return $query->where(function ($q) {
                $q->where('name', 'like', '%'.$this->search.'%')
                    ->orWhere('account_id', 'like', '%'.$this->search.'%')
                    ->orWhere('email', 'like', '%'.$this->search.'%')
                    ->orWhere('phone', 'like', '%'.$this->search.'%');
            });
        })->orderBy('account_id','desc')->paginate(15);

        return view('livewire.merchant-management', [
            'merchants' => $merchants,
            'searchTerm' => $this->search,
        ]);
    }
}
