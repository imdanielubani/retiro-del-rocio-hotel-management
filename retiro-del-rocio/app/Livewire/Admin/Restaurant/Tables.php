<?php

namespace App\Livewire\Admin\Restaurant;

use Livewire\Component;

class Tables extends Component
{
    use ManagesRestaurantTables;

    public string $area = 'dining';

    protected function singular(): string
    {
        return 'Table';
    }

    protected function plural(): string
    {
        return 'Tables';
    }

    protected function areaLabel(): string
    {
        return 'Dining';
    }

    protected function pageTitle(): string
    {
        return 'Restaurant Tables';
    }

    protected function pageSubtitle(): string
    {
        return 'Dining tables guests can reserve on the restaurant page';
    }
}
