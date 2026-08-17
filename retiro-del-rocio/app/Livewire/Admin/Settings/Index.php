<?php

namespace App\Livewire\Admin\Settings;

use App\Livewire\Admin\Auth\SetNewPassword;
use App\Support\HotelSettings;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Settings (Figma 463:1544) — a tabbed panel. "Hotel Info", "Security"
 * (change password) and "Website" (maintenance mode) are live. Payment
 * Settings, Email Config and Notifications tabs were removed — not
 * applicable to this deployment.
 *
 * Check-in / check-out saved here flow straight through to booking details and
 * the in-room tablets, which read them via {@see HotelSettings}.
 */
class Index extends Component
{
    public const TABS = [
        'hotel' => ['label' => 'Hotel Info', 'icon' => 'building'],
        'security' => ['label' => 'Security', 'icon' => 'shield'],
        'website' => ['label' => 'Website', 'icon' => 'globe'],
    ];

    #[Url(as: 'tab', keep: true)]
    public string $tab = 'hotel';

    // ----- Hotel Info form -----
    public string $name = '';

    public string $tagline = '';

    public string $address = '';

    public string $city = '';

    public string $country = '';

    public string $phone = '';

    public string $email = '';

    public string $description = '';

    /** 24-hour `HH:MM`, bound to <input type="time">. */
    public string $checkInTime = '15:00';

    public string $checkOutTime = '11:00';

    // ----- Security / Change Password form -----
    public string $currentPassword = '';

    public string $newPassword = '';

    public string $newPassword_confirmation = '';

    // ----- Website / Maintenance mode form -----
    public bool $maintenanceMode = false;

    public string $maintenanceMessage = '';

    public function mount(): void
    {
        $user = auth()->user();
        abort_unless(
            $user && ($user->hasRole('super-admin') || $user->can('manage settings')),
            403
        );

        if (! array_key_exists($this->tab, self::TABS)) {
            $this->tab = 'hotel';
        }

        $this->name = (string) HotelSettings::get('hotel.name');
        $this->tagline = (string) HotelSettings::get('hotel.tagline');
        $this->address = (string) HotelSettings::get('hotel.address');
        $this->city = (string) HotelSettings::get('hotel.city');
        $this->country = (string) HotelSettings::get('hotel.country');
        $this->phone = (string) HotelSettings::get('hotel.phone');
        $this->email = (string) HotelSettings::get('hotel.email');
        $this->description = (string) HotelSettings::get('hotel.description');
        $this->checkInTime = HotelSettings::checkInTime();
        $this->checkOutTime = HotelSettings::checkOutTime();

        $this->maintenanceMode = HotelSettings::maintenanceMode();
        $this->maintenanceMessage = HotelSettings::maintenanceMessage();
    }

    public function selectTab(string $tab): void
    {
        if (array_key_exists($tab, self::TABS)) {
            $this->tab = $tab;
        }
    }

    public function save(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'tagline' => ['nullable', 'string', 'max:160'],
            'address' => ['nullable', 'string', 'max:190'],
            'city' => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:190'],
            'description' => ['nullable', 'string', 'max:2000'],
            'checkInTime' => ['required', 'date_format:H:i'],
            'checkOutTime' => ['required', 'date_format:H:i'],
        ], [], [
            'checkInTime' => 'check-in time',
            'checkOutTime' => 'check-out time',
        ]);

        HotelSettings::put('hotel.name', $data['name']);
        HotelSettings::put('hotel.tagline', $data['tagline']);
        HotelSettings::put('hotel.address', $data['address']);
        HotelSettings::put('hotel.city', $data['city']);
        HotelSettings::put('hotel.country', $data['country']);
        HotelSettings::put('hotel.phone', $data['phone']);
        HotelSettings::put('hotel.email', $data['email']);
        HotelSettings::put('hotel.description', $data['description']);
        HotelSettings::setCheckInTime($data['checkInTime']);
        HotelSettings::setCheckOutTime($data['checkOutTime']);

        // Re-read: the setters normalise, and the tablets will now poll these.
        $this->checkInTime = HotelSettings::checkInTime();
        $this->checkOutTime = HotelSettings::checkOutTime();

        $this->dispatch(
            'toast',
            type: 'success',
            message: 'Hotel information saved. Check-in '.HotelSettings::checkInLabel()
                .' · check-out '.HotelSettings::checkOutLabel().'.'
        );
    }

    /**
     * Change the signed-in admin's own password. Same strength policy as the
     * public password-reset flow ({@see SetNewPassword}).
     */
    public function changePassword(): void
    {
        $data = $this->validate([
            'currentPassword' => ['required', 'current_password:web'],
            'newPassword' => [
                'required',
                'string',
                'confirmed',
                'min:12',
                'regex:/[A-Z]/',
                'regex:/[0-9]/',
                'regex:/[^A-Za-z0-9]/',
            ],
        ], [
            'currentPassword.current_password' => 'That current password is incorrect.',
            'newPassword.min' => 'New password must be at least 12 characters.',
            'newPassword.confirmed' => 'The password confirmation does not match.',
            'newPassword.regex' => 'New password must include an uppercase letter, a number and a special character.',
        ], [
            'currentPassword' => 'current password',
            'newPassword' => 'new password',
        ]);

        auth()->user()->forceFill([
            'password' => Hash::make($data['newPassword']),
        ])->save();

        $this->reset(['currentPassword', 'newPassword', 'newPassword_confirmation']);

        $this->dispatch('toast', type: 'success', message: 'Password changed.');
    }

    /** Toggle the public-site maintenance page and save its message. */
    public function saveWebsite(): void
    {
        $data = $this->validate([
            'maintenanceMode' => ['boolean'],
            'maintenanceMessage' => ['nullable', 'string', 'max:500'],
        ]);

        HotelSettings::setMaintenanceMode($data['maintenanceMode']);
        HotelSettings::setMaintenanceMessage($data['maintenanceMessage']);

        // Re-read: the setter falls back to the default when cleared.
        $this->maintenanceMessage = HotelSettings::maintenanceMessage();

        $this->dispatch(
            'toast',
            type: $this->maintenanceMode ? 'info' : 'success',
            message: $this->maintenanceMode
                ? 'Maintenance mode is ON — the public site now shows the maintenance page.'
                : 'Maintenance mode is off. The public site is live.'
        );
    }

    public function render()
    {
        return view('admin.settings.index', [
            'tabs' => self::TABS,
        ])->layout('components.admin.app', [
            'title' => 'Settings',
            'subtitle' => 'Hotel information, policy and system configuration',
        ]);
    }
}
