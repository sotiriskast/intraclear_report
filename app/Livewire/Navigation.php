<?php

namespace App\Livewire;

use Livewire\Attributes\Lazy;
use Livewire\Component;
#[Lazy]
class Navigation extends Component
{
    public $isOpen = false;

    public function toggleMenu()
    {
        $this->isOpen = ! $this->isOpen;
    }

    public function closeSidebar()
    {
        $this->dispatch('sidebar-close');
    }

    public function render()
    {
        return view('navigation-menu');
    }
}
