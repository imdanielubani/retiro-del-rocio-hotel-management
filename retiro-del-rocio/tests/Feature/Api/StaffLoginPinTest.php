<?php

namespace Tests\Feature\Api;

use App\Models\Device;
use App\Models\DeviceType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Staff tablet sign-in accepts either the account's password or its 4-digit
 * PIN — whichever tab the staffer picked on the login screen.
 */
class StaffLoginPinTest extends TestCase
{
    use RefreshDatabase;

    private function staffDevice(string $role = 'kitchen'): string
    {
        $type = DeviceType::create(['name' => 'Tablet', 'slug' => 'tablet-'.Str::random(6)]);
        $device = Device::create([
            'device_uuid' => (string) Str::uuid(),
            'device_code' => 'TAB-'.Str::upper(Str::random(6)),
            'device_name' => 'Kitchen Tablet',
            'device_type_id' => $type->id,
            'mode' => 'staff',
            'role' => $role,
            'status' => 'online',
            'is_provisioned' => true,
            'provisioned_at' => now(),
        ]);

        return $device->createToken('tablet')->plainTextToken;
    }

    private function chef(?string $pin = null): User
    {
        Role::findOrCreate('kitchen', 'web');
        $user = User::factory()->create([
            'status' => 'active',
            'email' => 'chef@example.test',
            'password' => Hash::make('CorrectPassword1'),
            'pin' => $pin ? Hash::make($pin) : null,
        ]);
        $user->assignRole('kitchen');

        return $user;
    }

    public function test_a_staffer_can_sign_in_with_their_password(): void
    {
        $this->chef();
        $deviceToken = $this->staffDevice();

        $this->withToken($deviceToken)
            ->postJson('/api/v1/tablets/staff-login', [
                'email' => 'chef@example.test',
                'password' => 'CorrectPassword1',
            ])
            ->assertOk()
            ->assertJsonPath('user.email', 'chef@example.test');
    }

    public function test_a_staffer_can_sign_in_with_only_their_pin_no_email_needed(): void
    {
        $this->chef(pin: '4821');
        $deviceToken = $this->staffDevice();

        $this->withToken($deviceToken)
            ->postJson('/api/v1/tablets/staff-login', ['pin' => '4821'])
            ->assertOk()
            ->assertJsonPath('user.email', 'chef@example.test');
    }

    public function test_a_wrong_pin_is_rejected(): void
    {
        $this->chef(pin: '4821');
        $deviceToken = $this->staffDevice();

        $this->withToken($deviceToken)
            ->postJson('/api/v1/tablets/staff-login', ['pin' => '0000'])
            ->assertStatus(422);
    }

    public function test_a_pin_login_is_rejected_when_the_account_has_no_pin_set(): void
    {
        $this->chef(); // no pin
        $deviceToken = $this->staffDevice();

        $this->withToken($deviceToken)
            ->postJson('/api/v1/tablets/staff-login', ['pin' => '1234'])
            ->assertStatus(422);
    }

    public function test_a_pin_does_not_match_on_a_different_roles_tablet(): void
    {
        Role::findOrCreate('bar', 'web');
        $waiter = User::factory()->create(['status' => 'active', 'pin' => Hash::make('9999')]);
        $waiter->assignRole('bar');

        $kitchenDeviceToken = $this->staffDevice('kitchen');
        $this->withToken($kitchenDeviceToken)
            ->postJson('/api/v1/tablets/staff-login', ['pin' => '9999'])
            ->assertStatus(422);
    }

    public function test_a_pin_matches_on_its_own_roles_tablet(): void
    {
        Role::findOrCreate('bar', 'web');
        $waiter = User::factory()->create(['status' => 'active', 'pin' => Hash::make('9999')]);
        $waiter->assignRole('bar');

        $barDeviceToken = $this->staffDevice('bar');
        $this->withToken($barDeviceToken)
            ->postJson('/api/v1/tablets/staff-login', ['pin' => '9999'])
            ->assertOk()
            ->assertJsonPath('user.id', $waiter->id);
    }

    public function test_neither_password_nor_pin_is_rejected_by_validation(): void
    {
        $this->chef();
        $deviceToken = $this->staffDevice();

        $this->withToken($deviceToken)
            ->postJson('/api/v1/tablets/staff-login', ['email' => 'chef@example.test'])
            ->assertStatus(422);
    }

    public function test_the_tablet_forgot_password_otp_routes_no_longer_exist(): void
    {
        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'chef@example.test'])->assertStatus(404);
        $this->postJson('/api/v1/auth/verify-otp', ['email' => 'chef@example.test', 'otp' => '123456'])->assertStatus(404);
        $this->postJson('/api/v1/auth/reset-password', ['email' => 'chef@example.test', 'otp' => '123456', 'password' => 'x'])->assertStatus(404);
    }
}
