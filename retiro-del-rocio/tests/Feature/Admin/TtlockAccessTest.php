<?php

namespace Tests\Feature\Admin;

use App\Events\TtlockAccessChanged;
use App\Jobs\GenerateTtlockAccess;
use App\Livewire\Admin\Ttlock\Locks;
use App\Mail\TtlockAccessMail;
use App\Models\Booking;
use App\Services\TTLockException;
use App\Services\TTLockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * TTLock gate-pass provisioning — the -3007 "same passcode" recovery and the
 * per-gate ("partial") status.
 *
 * -3007 means the code is already on that lock, almost always because a prior
 * attempt reached the lock but its id was never recorded (gateway latency). The
 * service must adopt the existing code instead of failing, and a booking whose
 * code works on at least one gate must never read as a blanket failure.
 */
class TtlockAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Minimal credentials so the service is "configured" and never tries a
        // real OAuth refresh (a non-expired access token is present).
        config()->set('services.ttlock.base_url', 'https://euapi.ttlock.com');
        config()->set('services.ttlock.client_id', 'test-client');
        config()->set('services.ttlock.client_secret', 'test-secret');
        config()->set('services.ttlock.access_token', 'test-token');
        config()->set('services.ttlock.refresh_token', null);
    }

    private function booking(array $overrides = []): Booking
    {
        return Booking::create(array_merge([
            'reference' => 'BK-'.Str::upper(Str::random(8)),
            'customer_name' => 'Daniel Ubani',
            'customer_email' => 'guest@example.test',
            'check_in' => now()->toDateString(),
            'check_out' => now()->addDays(3)->toDateString(),
            'nights' => 3,
            'amount' => 450000,
            'status' => 'paid',
        ], $overrides));
    }

    /* ============ Service: -3007 recovery ============ */

    public function test_it_adopts_an_existing_passcode_when_the_lock_reports_a_duplicate(): void
    {
        $listed = false;

        Http::fake(function ($request) use (&$listed) {
            $url = $request->url();
            $data = $request->data();

            if (str_contains($url, '/v3/keyboardPwd/add')) {
                // The lock already has this exact code (a prior attempt landed).
                return Http::response(['errcode' => -3007, 'errmsg' => 'The same passcode exists']);
            }

            if (str_contains($url, '/v3/lock/listKeyboardPwd')) {
                $listed = true;

                return Http::response([
                    'list' => [[
                        'keyboardPwd' => $data['keyboardPwd'] ?? '654321',
                        'keyboardPwdId' => 999,
                        'keyboardPwdName' => 'Daniel Ubani',
                    ]],
                    'pages' => 1,
                    'total' => 1,
                ]);
            }

            // changePeriod (best-effort window sync) and anything else.
            return Http::response(['errcode' => 0]);
        });

        $result = app(TTLockService::class)->createPasscode(
            'LOCK-A',
            Carbon::now(),
            Carbon::now()->addDays(3),
            'Daniel Ubani',
            '654321',
        );

        $this->assertTrue($listed, 'The service should look up the existing passcode.');
        $this->assertSame('654321', $result['keyboardPwd']);
        $this->assertSame(999, $result['keyboardPwdId']);
    }

    public function test_it_does_not_adopt_a_passcode_that_belongs_to_someone_else(): void
    {
        // Same digits collide, but the code on the lock carries a different name,
        // so it is NOT ours — the service must refuse to hijack/re-window it.
        Http::fake(function ($request) {
            $url = $request->url();
            $data = $request->data();

            if (str_contains($url, '/v3/keyboardPwd/add')) {
                return Http::response(['errcode' => -3007, 'errmsg' => 'The same passcode exists']);
            }

            if (str_contains($url, '/v3/lock/listKeyboardPwd')) {
                return Http::response([
                    'list' => [[
                        'keyboardPwd' => $data['keyboardPwd'] ?? '654321',
                        'keyboardPwdId' => 111,
                        'keyboardPwdName' => 'A Different Guest',
                    ]],
                    'pages' => 1,
                    'total' => 1,
                ]);
            }

            return Http::response(['errcode' => 0]);
        });

        $this->expectException(TTLockException::class);

        app(TTLockService::class)->createPasscode(
            'LOCK-A',
            Carbon::now(),
            Carbon::now()->addDays(3),
            'Daniel Ubani',
            '654321',
        );
    }

    public function test_a_normal_add_returns_the_new_passcode_id(): void
    {
        Http::fake([
            '*/v3/keyboardPwd/add' => Http::response(['keyboardPwdId' => 555]),
        ]);

        $result = app(TTLockService::class)->createPasscode(
            'LOCK-A',
            Carbon::now(),
            Carbon::now()->addDays(3),
            'Daniel Ubani',
            '654321',
        );

        $this->assertSame(555, $result['keyboardPwdId']);
    }

    /* ============ Job: multi-gate status ============ */

    public function test_a_duplicate_on_one_gate_recovers_to_a_fully_active_pass(): void
    {
        Mail::fake();
        config()->set('services.ttlock.lock_id', 'GATE-1,GATE-2');

        Http::fake(function ($request) {
            $url = $request->url();
            $data = $request->data();

            if (str_contains($url, '/v3/keyboardPwd/add')) {
                // Gate 1 accepts the code; Gate 2 already has it (self-collision).
                return ($data['lockId'] ?? null) === 'GATE-1'
                    ? Http::response(['keyboardPwdId' => 111])
                    : Http::response(['errcode' => -3007, 'errmsg' => 'The same passcode exists']);
            }

            if (str_contains($url, '/v3/lock/listKeyboardPwd')) {
                return Http::response([
                    'list' => [[
                        'keyboardPwd' => $data['keyboardPwd'] ?? '654321',
                        'keyboardPwdId' => 222,
                        'keyboardPwdName' => 'Daniel Ubani',
                    ]],
                    'pages' => 1,
                    'total' => 1,
                ]);
            }

            return Http::response(['errcode' => 0]);
        });

        // Deterministic shared code so the listKeyboardPwd match is stable.
        $booking = $this->booking(['passcode' => '654321']);

        GenerateTtlockAccess::dispatchSync($booking);

        $booking->refresh();
        $this->assertSame('active', $booking->ttlock_status);
        $this->assertNull($booking->ttlock_error);
        $this->assertSame('654321', $booking->passcode);
        $this->assertSame('111', (string) $booking->keyboard_pwd_id);

        $grants = collect($booking->ttlock_grants);
        $this->assertCount(2, $grants);
        $this->assertTrue($grants->every(fn ($g) => $g['status'] === 'active'));
        $this->assertSame('222', (string) $grants->firstWhere('lockId', 'GATE-2')['keyboardPwdId']);

        Mail::assertSent(TtlockAccessMail::class);
    }

    public function test_one_unreachable_gate_yields_a_partial_pass_not_a_failure(): void
    {
        Mail::fake();
        config()->set('services.ttlock.lock_id', 'GATE-1,GATE-2');

        Http::fake(function ($request) {
            $url = $request->url();
            $data = $request->data();

            if (str_contains($url, '/v3/keyboardPwd/add')) {
                // Gate 1 works; Gate 2 is genuinely down (unrecoverable error).
                return ($data['lockId'] ?? null) === 'GATE-1'
                    ? Http::response(['keyboardPwdId' => 111])
                    : Http::response(['errcode' => -3003, 'errmsg' => 'Lock is offline']);
            }

            return Http::response(['errcode' => 0]);
        });

        $booking = $this->booking(['passcode' => '654321']);

        GenerateTtlockAccess::dispatchSync($booking);

        $booking->refresh();
        // The guest DOES have a working code on Gate 1 — this must not read as failed.
        $this->assertSame('partial', $booking->ttlock_status);
        $this->assertSame('111', (string) $booking->keyboard_pwd_id);
        $this->assertStringContainsString('Gate 2', (string) $booking->ttlock_error);

        $grants = collect($booking->ttlock_grants);
        $this->assertSame('active', $grants->firstWhere('lockId', 'GATE-1')['status']);
        $this->assertSame('failed', $grants->firstWhere('lockId', 'GATE-2')['status']);

        // The working code is still delivered to the guest.
        Mail::assertSent(TtlockAccessMail::class);
    }

    /* ============ Realtime: live dashboard updates ============ */

    public function test_the_change_broadcast_targets_the_admin_channel(): void
    {
        // This is the exact contract the admin dashboard's Echo listener binds to
        // (channel "admin", event ".ttlock.changed", signal-only payload).
        $event = new TtlockAccessChanged(7, 'partial');

        $this->assertSame('ttlock.changed', $event->broadcastAs());
        $this->assertSame(['id' => 7, 'status' => 'partial'], $event->broadcastWith());

        $channels = array_map(fn ($c) => $c->name, $event->broadcastOn());
        $this->assertContains('admin', $channels);
    }

    public function test_a_failed_broadcast_refreshes_the_register_and_toasts(): void
    {
        $booking = $this->booking(['ttlock_status' => 'failed']);

        Livewire::test(Locks::class)
            ->call('onTtlockChanged', ['id' => $booking->id, 'status' => 'failed'])
            ->assertOk()
            ->assertDispatched('toast');
    }

    public function test_a_routine_broadcast_refreshes_without_a_toast(): void
    {
        $booking = $this->booking(['ttlock_status' => 'active', 'passcode' => '654321']);

        Livewire::test(Locks::class)
            ->call('onTtlockChanged', ['id' => $booking->id, 'status' => 'active'])
            ->assertOk()
            ->assertNotDispatched('toast');
    }
}
