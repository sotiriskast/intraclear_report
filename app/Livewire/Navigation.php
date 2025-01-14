<?php

namespace App\Http\Livewire;

use Livewire\Component;

class Navigation extends Component
{
    public $isOpen = false;

    public function toggleMenu()
    {
        $this->isOpen = !$this->isOpen;
    }

    public function render()
    {
        return view('navigation-menu');
    }
}
