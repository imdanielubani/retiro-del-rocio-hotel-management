<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\Devices\Tablets;
use App\Models\Device;
use App\Models\DeviceType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Deleting a tablet in the admin dashboard must actually cut it loose: its
 * device token stops working, so the app clears its stored pairing and starts
 * afresh at device setup instead of booting into the welcome screen.
 */
class DeviceDeletionUnpairsTabletTest extends TestCase
{
    use RefreshDatabase;

    private Device $device;

    private string $deviceToken;

    protected function setUp(): void
    {
        parent::setUp();

        $type = DeviceType::create(['name' => 'Tablet', 'slug' => 'tablet']);

        $this->device = Device::create([
            'device_uuid' => (string) Str::uuid(),
            'device_code' => 'TAB-RECEPTION',
            'device_name' => 'Reception Tablet',
            'device_type_id' => $type->id,
            'mode' => 'staff',
            'role' => 'reception',
            'status' => 'online',
            'is_provisioned' => true,
            'provisioned_at' => now(),
        ]);

        $this->deviceToken = $this->device->createToken('tablet')->plainTextToken;
    }

    private function adminUser(): User
    {
        foreach (['device.view', 'device.delete'] as $name) {
            Permission::findOrCreate($name);
        }
        $role = Role::findOrCreate('super-admin');
        $role->givePermissionTo(['device.view', 'device.delete']);

        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole($role);

        return $user;
    }

    public function test_a_paired_tablet_can_confirm_its_own_pairing(): void
    {
        $this->withToken($this->deviceToken)
            ->getJson('/api/v1/tablets/me')
            ->assertOk()
            ->assertJsonPath('device.device_code', 'TAB-RECEPTION')
            ->assertJsonPath('device.role', 'reception');
    }

    public function test_deleting_the_tablet_kills_its_token_so_the_app_starts_afresh(): void
    {
        Livewire::actingAs($this->adminUser())
            ->test(Tablets::class)
            ->call('delete', $this->device->id);

        $this->assertSoftDeleted('devices', ['id' => $this->device->id]);
        $this->assertDatabaseCount('personal_access_tokens', 0);

        // The tablet is a separate client — drop the admin's session, or Sanctum
        // falls back to it and authenticates the request as that admin.
        Auth::logout();
        $this->flushSession();
        $this->app['auth']->forgetGuards();

        // The app's launch check now fails — it clears the session and returns
        // to device setup rather than showing the tablet as still connected.
        $this->withToken($this->deviceToken)
            ->getJson('/api/v1/tablets/me')
            ->assertUnauthorized();

        // And the live room-status poll is rejected too, so an already-running
        // tablet drops back to setup without needing a relaunch.
        $this->withToken($this->deviceToken)
            ->getJson('/api/v1/tablets/room-status')
            ->assertUnauthorized();
    }
}
