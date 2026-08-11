<?php

namespace Tests\Feature\Api;

use App\Mail\VisitorPassMail;
use App\Models\Booking;
use App\Models\Device;
use App\Models\DeviceType;
use App\Models\Room;
use App\Models\RoomUnit;
use App\Models\User;
use App\Models\VisitorPass;
use App\Services\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The visitor's two-channel TTLock access: a one-time online passcode on the gate
 * lock plus the manual offline code, the auto-email, and the lock-usage reconcile
 * that confirms a visitor who lets themselves in.
 */
class VisitorPassTtlockTest extends TestCase
{
    use RefreshDatabase;

    private RoomUnit $unit;

    private Device $device;

    private string $deviceToken;

    protected function setUp(): void
    {
        parent::setUp();

        // TTLock configured against a single gate lock.
        config()->set('services.ttlock.base_url', 'https://euapi.ttlock.com');
        config()->set('services.ttlock.client_id', 'cid');
        config()->set('services.ttlock.client_secret', 'secret');
        config()->set('services.ttlock.access_token', 'token');
        config()->set('services.ttlock.refresh_token', null);
        config()->set('services.ttlock.lock_id', 'GATE1');

        $room = Room::create(['name' => 'Alba Suite', 'slug' => 'alba-vp-ttl', 'type' => 'suite', 'price' => 150000]);
        $this->unit = RoomUnit::create(['room_id' => $room->id, 'number' => '101', 'status' => 'occupied']);

        $booking = Booking::create([
            'reference' => 'BK-'.Str::upper(Str::random(8)),
            'customer_name' => 'Daniel Ubani',
            'room_id' => $room->id,
            'room_name' => $room->name,
            'room_unit_id' => $this->unit->id,
            'check_in' => now()->toDateString(),
            'check_out' => now()->addDays(3)->toDateString(),
            'nights' => 3, 'guests' => 2, 'amount' => 450000,
            'status' => 'checked_in', 'checked_in_at' => now(),
        ]);
        $this->unit->update(['booking_id' => $booking->id]);

        $type = DeviceType::create(['name' => 'Tablet', 'slug' => 'tablet-vp-ttl']);
        $this->device = Device::create([
            'device_uuid' => (string) Str::uuid(),
            'device_code' => 'TAB-VP-TTL',
            'device_name' => 'Room 101 Tablet',
            'device_type_id' => $type->id,
            'mode' => 'guest',
            'room_id' => $room->id,
            'room_unit_id' => $this->unit->id,
            'status' => 'online',
            'is_provisioned' => true,
        ]);
        $this->deviceToken = $this->device->createToken('tablet')->plainTextToken;
    }

    private function officerToken(): string
    {
        Role::findOrCreate('security');
        $user = User::factory()->create(['status' => 'active', 'name' => 'Officer']);
        $user->assignRole('security');

        return app(JwtService::class)->issue(['sub' => $user->id])['token'];
    }

    public function test_issuing_a_pass_mints_an_online_ttlock_code_and_emails_the_visitor(): void
    {
        Mail::fake();
        Http::fake([
            '*/v3/keyboardPwd/add' => Http::response(['keyboardPwdId' => 909]),
        ]);

        // The tablet is answered immediately, on the offline code; the gate code
        // is minted once the response is on its way.
        $data = $this->withToken($this->deviceToken)
            ->postJson('/api/v1/visitor-passes', [
                'visitor_name' => 'Michael Brown',
                'visitor_email' => 'm.brown@mail.com',
            ])
            ->assertCreated()
            ->assertJsonPath('data.ttlock_status', 'pending')
            ->json('data');

        $pass = VisitorPass::latest('id')->first();

        // Distinct 6-digit online + offline codes.
        $this->assertMatchesRegularExpression('/^\d{6}$/', $pass->online_code);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $data['offline_code']);
        $this->assertNotSame($pass->online_code, $data['offline_code']);

        $this->assertDatabaseHas('visitor_passes', [
            'id' => $pass->id,
            'keyboard_pwd_id' => '909',
            'lock_id' => 'GATE1',
            'ttlock_status' => 'active',
        ]);

        Mail::assertSent(VisitorPassMail::class);
    }

    public function test_one_code_is_pushed_to_every_gate_lock(): void
    {
        Mail::fake();
        config()->set('services.ttlock.lock_id', 'GATE1,GATE2'); // two gates

        $adds = [];
        Http::fake(function ($request) use (&$adds) {
            if (str_contains($request->url(), '/v3/keyboardPwd/add')) {
                $data = $request->data();
                $adds[] = ['lockId' => $data['lockId'], 'code' => $data['keyboardPwd']];

                // Each lock returns its own keyboardPwdId.
                return Http::response(['keyboardPwdId' => $data['lockId'] === 'GATE1' ? 111 : 222]);
            }

            return Http::response(['errcode' => 0]);
        });

        $this->withToken($this->deviceToken)
            ->postJson('/api/v1/visitor-passes', ['visitor_name' => 'Michael Brown'])
            ->assertCreated();

        // The SAME code was added to BOTH gates.
        $this->assertCount(2, $adds);
        $this->assertSame($adds[0]['code'], $adds[1]['code']);
        $this->assertEqualsCanonicalizing(['GATE1', 'GATE2'], array_column($adds, 'lockId'));

        // Both gate grants are stored for later removal.
        $pass = VisitorPass::latest('id')->first();
        $grants = collect($pass->ttlock_grants);
        $this->assertCount(2, $grants);
        $this->assertEqualsCanonicalizing(['111', '222'], $grants->pluck('keyboardPwdId')->all());
        $this->assertSame($adds[0]['code'], $pass->online_code);
        $this->assertSame('active', $pass->ttlock_status);
    }

    public function test_using_the_code_deletes_it_from_every_gate(): void
    {
        config()->set('services.ttlock.lock_id', 'GATE1,GATE2');

        $pass = VisitorPass::create([
            'room_unit_id' => $this->unit->id,
            'visitor_name' => 'Michael Brown',
            'code' => '333444',
            'online_code' => '654321',
            'keyboard_pwd_id' => '111',
            'lock_id' => 'GATE1',
            'ttlock_grants' => [
                ['lockId' => 'GATE1', 'keyboardPwdId' => '111'],
                ['lockId' => 'GATE2', 'keyboardPwdId' => '222'],
            ],
            'ttlock_status' => 'active',
            'status' => VisitorPass::PENDING,
            'expires_at' => now()->addHours(6),
        ]);

        $deletes = [];
        Http::fake(function ($request) use (&$deletes) {
            $url = $request->url();
            if (str_contains($url, '/v3/lockRecord/list')) {
                // The visitor used the code at GATE2.
                return Http::response(str_contains((string) $request['lockId'], 'GATE2')
                    ? ['list' => [['success' => 1, 'keyboardPwd' => '654321', 'lockDate' => now()->getTimestampMs()]]]
                    : ['list' => []]);
            }
            if (str_contains($url, '/v3/keyboardPwd/delete')) {
                $deletes[] = $request['lockId'];

                return Http::response(['errcode' => 0]);
            }

            return Http::response(['errcode' => 0]);
        });

        $this->artisan('visitors:reconcile-entries')->assertSuccessful();

        $pass->refresh();
        $this->assertSame('verified', $pass->status);
        $this->assertSame('lock', $pass->verified_via);

        // Deleted from BOTH gates, so it can't be reused at the other one.
        $this->assertEqualsCanonicalizing(['GATE1', 'GATE2'], $deletes);
    }

    public function test_when_ttlock_is_offline_the_pass_still_issues_on_the_offline_code(): void
    {
        Mail::fake();
        config()->set('services.ttlock.lock_id', null); // no gate → offline mode

        $data = $this->withToken($this->deviceToken)
            ->postJson('/api/v1/visitor-passes', ['visitor_name' => 'Zara Ahmed'])
            ->assertCreated()
            ->json('data');

        $this->assertNull($data['online_code']);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $data['offline_code']);

        // Provisioning settles to "offline" once the response has gone out.
        $pass = VisitorPass::latest('id')->first();
        $this->assertSame('offline', $pass->ttlock_status);
        $this->assertNull($pass->online_code);
    }

    public function test_the_officer_can_verify_by_the_online_code(): void
    {
        $pass = VisitorPass::create([
            'room_unit_id' => $this->unit->id,
            'visitor_name' => 'Michael Brown',
            'code' => '333444',
            'online_code' => '111222',
            'keyboard_pwd_id' => '909',
            'lock_id' => 'GATE1',
            'ttlock_status' => 'active',
            'status' => VisitorPass::PENDING,
            'expires_at' => now()->addHours(6),
        ]);

        $this->withToken($this->officerToken())
            ->postJson('/api/v1/security/visitors/verify', ['code' => '111222'])
            ->assertOk()
            ->assertJsonPath('data.id', $pass->id)
            ->assertJsonPath('data.online_code', '111222');
    }

    public function test_using_the_ttlock_code_at_the_gate_auto_confirms_the_visitor(): void
    {
        $pass = VisitorPass::create([
            'room_unit_id' => $this->unit->id,
            'visitor_name' => 'Michael Brown',
            'code' => '333444',
            'online_code' => '654321',
            'keyboard_pwd_id' => '909',
            'lock_id' => 'GATE1',
            'ttlock_status' => 'active',
            'status' => VisitorPass::PENDING,
            'expires_at' => now()->addHours(6),
        ]);

        Http::fake([
            '*/v3/lockRecord/list' => Http::response([
                'list' => [
                    ['success' => 1, 'keyboardPwd' => '654321', 'lockDate' => now()->getTimestampMs()],
                ],
            ]),
            '*/v3/keyboardPwd/delete' => Http::response(['errcode' => 0]),
        ]);

        $this->artisan('visitors:reconcile-entries')->assertSuccessful();

        $pass->refresh();
        $this->assertSame('verified', $pass->status);
        $this->assertSame('lock', $pass->verified_via);
        $this->assertSame('used', $pass->ttlock_status);
        $this->assertNull($pass->handled_by); // nobody keyed it — the visitor did
    }

    public function test_checking_the_host_out_kills_their_visitors_gate_codes(): void
    {
        $booking = $this->unit->booking;

        $open = VisitorPass::create([
            'room_unit_id' => $this->unit->id,
            'booking_id' => $booking->id,
            'visitor_name' => 'Still Expected',
            'code' => '444555',
            'online_code' => '777888',
            'keyboard_pwd_id' => '909',
            'lock_id' => 'GATE1',
            'ttlock_status' => 'active',
            'status' => VisitorPass::PENDING,
            'expires_at' => now()->addHours(6),
        ]);

        // Someone who already came in stays on the register untouched.
        $inside = VisitorPass::create([
            'room_unit_id' => $this->unit->id,
            'booking_id' => $booking->id,
            'visitor_name' => 'Already Inside',
            'code' => '666777',
            'status' => VisitorPass::VERIFIED,
            'verified_at' => now(),
        ]);

        $deletes = [];
        Http::fake(function ($request) use (&$deletes) {
            if (str_contains($request->url(), '/v3/keyboardPwd/delete')) {
                $deletes[] = $request['keyboardPwdId'];
            }

            return Http::response(['errcode' => 0]);
        });

        $closed = app(\App\Services\VisitorPassProvisioner::class)->closeOutBooking($booking->id);

        $this->assertSame(1, $closed);
        $this->assertSame('cancelled', $open->fresh()->status);
        $this->assertSame(['909'], $deletes); // pulled off the gate
        $this->assertSame('verified', $inside->fresh()->status);

        // And the code no longer opens anything at the gate.
        $this->withToken($this->officerToken())
            ->postJson('/api/v1/security/visitors/verify', ['code' => '777888'])
            ->assertNotFound();
    }

    public function test_reconcile_expires_a_pass_whose_window_has_closed(): void
    {
        $pass = VisitorPass::create([
            'room_unit_id' => $this->unit->id,
            'visitor_name' => 'Late Visitor',
            'code' => '999000',
            'online_code' => '888111',
            'keyboard_pwd_id' => '909',
            'lock_id' => 'GATE1',
            'ttlock_status' => 'active',
            'status' => VisitorPass::PENDING,
            'expires_at' => now()->subMinutes(5),
        ]);

        Http::fake([
            '*/v3/keyboardPwd/delete' => Http::response(['errcode' => 0]),
            '*/v3/lockRecord/list' => Http::response(['list' => []]),
        ]);

        $this->artisan('visitors:reconcile-entries')->assertSuccessful();

        $this->assertSame('expired', $pass->fresh()->status);
    }
}
