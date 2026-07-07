<?php

namespace App\Livewire\Admin\Restaurant;

use Livewire\Component;

class Lounge extends Component
{
    use ManagesRestaurantTables;

    public string $area = 'lounge';

    protected function singular(): string
    {
        return 'Lounge space';
    }

    protected function plural(): string
    {
        return 'Lounge Spaces';
    }

    protected function areaLabel(): string
    {
        return 'Lounge';
    }

    protected function pageTitle(): string
    {
        return 'Restaurant Lounge';
    }

    protected function pageSubtitle(): string
    {
        return 'Lounge spaces guests can reserve on the restaurant page';
    }
}
