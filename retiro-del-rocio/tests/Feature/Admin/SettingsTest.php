<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\Settings\Index;
use App\Models\User;
use App\Support\HotelSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Settings → Hotel Info. The front-desk policy saved here is the single source
 * of truth for arrival/departure times across the dashboard and the tablets.
 */
class SettingsTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        Permission::findOrCreate('manage settings');
        $role = Role::findOrCreate('super-admin');

        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole($role);

        return $user;
    }

    private function staff(): User
    {
        Role::findOrCreate('reception');

        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('reception');

        return $user;
    }

    public function test_it_falls_back_to_the_configured_defaults(): void
    {
        $this->assertSame('15:00', HotelSettings::checkInTime());
        $this->assertSame('12:00', HotelSettings::checkOutTime());
        $this->assertSame('3:00 PM', HotelSettings::checkInLabel());
        $this->assertSame('12:00 PM', HotelSettings::checkOutLabel());
    }

    public function test_timestamps_are_recorded_in_nigerian_time(): void
    {
        // The hotel is in Jos (WAT, UTC+1). A check-in stamped in UTC would read
        // an hour behind the clock the front desk is looking at.
        $this->assertSame('Africa/Lagos', config('app.timezone'));
        $this->assertSame('+01:00', now()->format('P'));
    }

    public function test_an_admin_saves_the_hotel_profile_and_policy_times(): void
    {
        Livewire::actingAs($this->superAdmin())
            ->test(Index::class)
            ->set('name', 'Retiro Del Rocio')
            ->set('tagline', 'Where Stillness Finds You')
            ->set('city', 'Jos, Plateau State')
            ->set('email', 'info@retirodelrocio.com')
            ->set('checkInTime', '14:00')
            ->set('checkOutTime', '10:30')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Retiro Del Rocio', HotelSettings::get('hotel.name'));
        $this->assertSame('Jos, Plateau State', HotelSettings::get('hotel.city'));

        // What the whole system now reads — bookings and every paired tablet.
        $this->assertSame('14:00', HotelSettings::checkInTime());
        $this->assertSame('10:30', HotelSettings::checkOutTime());
        $this->assertSame('2:00 PM', HotelSettings::checkInLabel());
        $this->assertSame('10:30 AM', HotelSettings::checkOutLabel());
    }

    public function test_it_rejects_an_unusable_time_and_a_bad_email(): void
    {
        Livewire::actingAs($this->superAdmin())
            ->test(Index::class)
            ->set('name', 'Retiro Del Rocio')
            ->set('email', 'not-an-email')
            ->set('checkInTime', '25:99')
            ->call('save')
            ->assertHasErrors(['email', 'checkInTime']);

        // Nothing was written — the defaults still stand.
        $this->assertSame('15:00', HotelSettings::checkInTime());
    }

    public function test_staff_without_the_permission_cannot_open_settings(): void
    {
        Livewire::actingAs($this->staff())
            ->test(Index::class)
            ->assertForbidden();
    }
}
