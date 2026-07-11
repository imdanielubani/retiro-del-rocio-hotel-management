<?php

namespace App\Livewire\Admin\Devices;

use Livewire\Component;

class Tablets extends Component
{
    use ManagesDevices;

    protected function typeSlug(): string
    {
        return 'tablet';
    }

    /** Tablets can be guest (room-bound) or staff (role-locked) stations. */
    protected function supportsStaffMode(): bool
    {
        return true;
    }

    protected function permPrefix(): string
    {
        return 'device';
    }

    protected function singular(): string
    {
        return 'Tablet';
    }

    protected function plural(): string
    {
        return 'Tablets';
    }

    protected function pageTitle(): string
    {
        return 'Tablets';
    }

    protected function pageSubtitle(): string
    {
        return 'Guest & staff tablets across the property';
    }
}
