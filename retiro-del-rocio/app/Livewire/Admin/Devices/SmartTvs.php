<?php

namespace App\Livewire\Admin\Devices;

use Livewire\Component;

class SmartTvs extends Component
{
    use ManagesDevices;

    protected function typeSlug(): string
    {
        return 'smart-tv';
    }

    protected function permPrefix(): string
    {
        return 'tv';
    }

    protected function singular(): string
    {
        return 'Smart TV';
    }

    protected function plural(): string
    {
        return 'Smart TVs';
    }

    protected function pageTitle(): string
    {
        return 'Smart TVs';
    }

    protected function pageSubtitle(): string
    {
        return 'In-room smart televisions across the property';
    }
}
