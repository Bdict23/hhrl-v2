<?php

namespace App\Livewire\Inventory;

use Livewire\Component;

class Cardex extends Component
{
    public array $items = [];

    public function render()
    {
        return view('livewire.inventory.cardex');
    }
}
