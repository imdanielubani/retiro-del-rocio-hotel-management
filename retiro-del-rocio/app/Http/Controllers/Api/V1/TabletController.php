<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\DeviceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\HeartbeatRequest;
use App\Http\Requests\Api\V1\ProvisionTabletRequest;
use App\Http\Requests\Api\V1\StaffLoginRequest;
use App\Http\Resources\DeviceResource;
use App\Jobs\SendStayExtensionReceipt;
use App\Models\BillPayment;
use App\Models\Booking;
use App\Models\Device;
use App\Models\GuestNotification;
use App\Models\ReceptionNotification;
use App\Models\RoomUnit;
use App\Models\SpaBooking;
use App\Models\SpaCategory;
use App\Models\SpaService;
use App\Models\StayExtensionPayment;
use App\Models\User;
use App\Models\VisitorPass;
use App\Services\DeviceCommandService;
use App\Services\JwtService;
use App\Support\ComputesBookingBill;
use Closure;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Tablet-facing API. `provision` is public (validated by the QR's provision
 * token); `heartbeat`/`sync` are called by the tablet with its own device
 * token; the command endpoints are called by staff with a user token.
 */
class TabletController extends Controller
{
    use AuthorizesRequests;
    use ComputesBookingBill;

    /** POST /tablets/provision — bind a tablet using its QR payload. */
    public function provision(ProvisionTabletRequest $request): JsonResponse
    {
        $data = $request->validated();

        // Both halves of the QR payload must match. The provisioning token is
        // the secret; a device code on its own can be read off the dashboard or
        // guessed, so it never pairs a tablet by itself.
        $device = Device::where('device_code', $data['device_code'])
            ->where('provision_token', $data['provision_token'])
            ->first();

        if (! $device) {
            throw ValidationException::withMessages([
                'device_code' => ['Invalid device code or provisioning token.'],
            ]);
        }

        // Re-provisioning replaces the device: drop any previous device tokens.
        $device->tokens()->delete();

        $device->forceFill([
            'is_provisioned' => true,
            'provisioned_at' => now(),
            'status' => DeviceStatus::Online,
            'last_seen_at' => now(),
            'serial_number' => $data['serial_number'] ?? $device->serial_number,
            'manufacturer' => $data['manufacturer'] ?? $device->manufacturer,
            'model' => $data['model'] ?? $device->model,
            'android_version' => $data['android_version'] ?? $device->android_version,
            'app_version' => $data['app_version'] ?? $device->app_version,
            'mac_address' => $data['mac_address'] ?? $device->mac_address,
            'ip_address' => $request->ip(),
        ])->save();

        $device->log('provision', 'Device provisioned via QR.', ['ip' => $request->ip()]);

        $token = $device->createToken('tablet:'.$device->device_code, ['device'])->plainTextToken;

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'device' => new DeviceResource($device->load(['type', 'room', 'roomUnit'])),
        ]);
    }

    /**
     * POST /tablets/staff-login — a staffer signs into a STAFF tablet. The
     * tablet is identified by its device token; the staffer must hold the role
     * the tablet is locked to. Returns the staffer's own user token + role.
     */
    public function staffLogin(StaffLoginRequest $request): JsonResponse
    {
        $device = $this->device($request);

        if (! $device->isStaff() || ! $device->role) {
            return response()->json(['message' => 'This tablet is not a staff station.'], 422);
        }

        $data = $request->validated();
        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if ($user->status && $user->status !== 'active') {
            return response()->json(['message' => 'Your account is not active.'], 403);
        }

        if (! $user->hasRole($device->role)) {
            throw ValidationException::withMessages([
                'email' => ['This account is not authorised for the '.$device->role.' tablet.'],
            ]);
        }

        $device->log('login', $user->name.' signed in on the '.$device->role.' tablet.');

        // Issue a short-lived JWT (TTL from config/jwt.php → .env). Its exp claim
        // drives the tablet app's session-expiring warning + timeout.
        $jwt = app(JwtService::class)->issue([
            'sub' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $device->role,
            'roles' => $user->getRoleNames()->values()->all(),
            'device' => $device->device_code,
        ]);

        return response()->json([
            'token' => $jwt['token'],
            'token_type' => 'Bearer',
            'expires_in' => $jwt['expires_in'],
            'expires_at' => Carbon::createFromTimestamp($jwt['expires_at'])->toIso8601String(),
            'role' => $device->role,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->getRoleNames()->values(),
            ],
        ]);
    }

    /** POST /tablets/heartbeat — telemetry from the tablet (device token). */
    public function heartbeat(HeartbeatRequest $request): JsonResponse
    {
        $device = $this->device($request);
        $data = $request->validated();

        $device->forceFill([
            'battery_level' => $data['battery_level'] ?? $device->battery_level,
            'wifi_strength' => $data['wifi_strength'] ?? $device->wifi_strength,
            'app_version' => $data['app_version'] ?? $device->app_version,
            'ip_address' => $data['ip_address'] ?? $request->ip(),
            'status' => $data['status'] ?? DeviceStatus::Online->value,
            'last_seen_at' => now(),
        ])->save();

        return response()->json(['ok' => true, 'device' => new DeviceResource($device)]);
    }

    /** POST /tablets/sync — device confirms a sync and collects pending commands. */
    public function sync(Request $request): JsonResponse
    {
        $device = $this->device($request);
        $since = $device->last_sync_at;

        $commands = $device->activityLogs()
            ->whereIn('event', ['restart', 'lock', 'unlock'])
            ->when($since, fn ($q) => $q->where('created_at', '>', $since))
            ->latest()
            ->limit(20)
            ->get(['event', 'created_at']);

        $device->forceFill([
            'last_sync_at' => now(),
            'last_seen_at' => now(),
            'status' => DeviceStatus::Online,
        ])->save();

        $device->log('sync', 'Device synced.');

        return response()->json([
            'ok' => true,
            'commands' => $commands->map(fn ($c) => [
                'command' => $c->event,
                'issued_at' => $c->created_at->toIso8601String(),
            ])->values(),
            'device' => new DeviceResource($device),
        ]);
    }

    /**
     * GET /tablets/me — the tablet's own record, used to confirm at launch that
     * its pairing is still valid. A deleted or unpaired device can no longer
     * authenticate here, so the app clears its session and returns to setup.
     */
    public function me(Request $request): JsonResponse
    {
        $device = $this->device($request);

        abort_unless($device->is_provisioned, 403, 'This device is no longer paired.');

        return response()->json([
            'device' => new DeviceResource($device->load(['type', 'room', 'roomUnit'])),
        ]);
    }

    /**
     * GET /tablets/room-status — the tablet's current room occupancy and, when
     * a guest is checked in, their booking details. Drives the guest welcome.
     */
    public function roomStatus(Request $request): JsonResponse
    {
        $device = $this->device($request);
        $unit = $device->roomUnit()->with(['room', 'booking'])->first();

        if (! $unit) {
            return response()->json(['data' => [
                'occupancy' => 'unassigned',
                'suite_name' => null,
                'room_number' => null,
                'guest' => null,
            ]]);
        }

        // Only a guest reception has actually checked in is shown on the tablet.
        $booking = $unit->status === 'occupied' && $unit->booking?->status === 'checked_in'
            ? $unit->booking
            : null;

        return response()->json(['data' => [
            'occupancy' => $unit->status, // available | occupied | maintenance
            'suite_name' => optional($unit->room)->name,
            'room_number' => $unit->number,
            'guest' => $booking ? [
                'name' => $booking->customer_name,
                'reference' => $booking->bookingCode(),
                'nights' => (int) $booking->nights,
                // The real arrival once reception checks them in; the expected
                // time (date + hotel policy) until then.
                'check_in' => optional($booking->arrivalAt())->toIso8601String(),
                'check_out' => optional($booking->departureAt())->toIso8601String(),
            ] : null,
        ]]);
    }

    /**
     * GET /tablets/my-stay — the checked-in guest's full reservation for the My
     * Stay screen: the room, dates, nightly rate, party, a bill summary, the
     * current balance and the room's inclusions. Scoped to this tablet's room by
     * its device token, so it only ever reveals the guest actually in the room.
     */
    public function myStay(Request $request): JsonResponse
    {
        [$booking, $unit] = $this->activeStay($request);

        return response()->json(['data' => $this->stayPayload($booking, $unit)]);
    }

    /**
     * GET /tablets/notifications — the checked-in guest's notification feed for
     * this stay, newest first. Scoped to the active booking (not just the room)
     * so a new guest never sees the previous occupant's notifications.
     */
    public function notifications(Request $request): JsonResponse
    {
        [$booking] = $this->activeStay($request);

        $notifications = GuestNotification::where('booking_id', $booking->id)
            ->latest()
            ->get();

        return response()->json(['data' => $notifications->map->toGuestArray()->values()]);
    }

    /** POST /tablets/notifications/{notification}/read — mark one as read. */
    public function markNotificationRead(Request $request, int $notification): JsonResponse
    {
        [$booking] = $this->activeStay($request);

        $record = GuestNotification::where('id', $notification)
            ->where('booking_id', $booking->id)
            ->first();
        abort_unless($record, 404, 'Notification not found.');

        if (! $record->read_at) {
            $record->update(['read_at' => now()]);
        }

        return response()->json(['data' => $record->toGuestArray()]);
    }

    /** POST /tablets/notifications/read-all — "Mark all read". */
    public function markAllNotificationsRead(Request $request): JsonResponse
    {
        [$booking] = $this->activeStay($request);

        GuestNotification::where('booking_id', $booking->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['ok' => true]);
    }

    /**
     * VAT charged on a stay extension, added at payment time (Paystack) but not
     * onto the room folio — it is a tax line, remitted, not room revenue.
     */
    private const VAT_RATE = 0.075;

    /**
     * POST /tablets/extend-stay/initialize — price the extension and open a
     * Paystack transaction for the guest to pay. Returns the hosted-checkout
     * authorization URL plus the priced summary (subtotal, VAT 7.5%, total). No
     * booking is touched here — the checkout only moves once the charge is
     * verified in {@see extendStay()}.
     */
    public function initializeExtension(Request $request): JsonResponse
    {
        [$booking, $unit] = $this->activeStay($request);

        $data = $request->validate(['check_out' => ['required', 'date']]);
        $q = $this->extensionQuote($booking, $unit, $data['check_out']);

        $secret = config('services.paystack.secret_key');
        abort_if(blank($secret), 503, 'Online payment is not available right now.');

        $reference = 'EXT-'.$booking->id.'-'.strtoupper(Str::random(10));
        $callbackUrl = rtrim((string) config('app.url'), '/').'/tablet/extend-return';
        $email = $booking->customer_email ?: 'guest'.$booking->id.'@retirodelrocio.ng';

        try {
            $response = Http::withToken($secret)->acceptJson()->post(
                rtrim((string) config('services.paystack.payment_url'), '/').'/transaction/initialize',
                [
                    'email' => $email,
                    'amount' => $q['total'] * 100, // Paystack works in kobo.
                    'reference' => $reference,
                    'callback_url' => $callbackUrl,
                    'metadata' => [
                        'booking_id' => $booking->id,
                        'purpose' => 'stay_extension',
                        'new_check_out' => $q['newOut']->toDateString(),
                        'nights' => $q['nights'],
                    ],
                ],
            );
        } catch (\Throwable $e) {
            report($e);
            abort(502, 'Could not start the payment. Please try again.');
        }

        $authUrl = data_get($response->json(), 'data.authorization_url');
        abort_if(! $response->ok() || ! $authUrl, 502, 'Could not start the payment. Please try again.');

        StayExtensionPayment::create([
            'booking_id' => $booking->id,
            'reference' => $reference,
            'nights' => $q['nights'],
            'new_check_out' => $q['newOut']->toDateString(),
            // Pre-VAT room charge; VAT is stored separately (matches every
            // other charge-source model: SpaBooking.subtotal/vat, etc.).
            'amount' => $q['base'],
            'vat' => $q['vat'],
            'status' => StayExtensionPayment::PENDING,
        ]);

        return response()->json(['data' => [
            'authorization_url' => $authUrl,
            'reference' => $reference,
            'callback_url' => $callbackUrl,
            'additional_nights' => $q['nights'],
            'subtotal' => $q['base'],
            'subtotal_label' => 'NGN '.number_format($q['base']),
            'vat' => $q['vat'],
            'vat_label' => 'NGN '.number_format($q['vat']),
            'total' => $q['total'],
            'total_label' => 'NGN '.number_format($q['total']),
        ]]);
    }

    /**
     * POST /tablets/extend-stay/charge-to-room — extend the stay straight
     * against the room's folio, no Paystack round trip needed. Confirmed the
     * moment it's recorded, the same as the spa's "Charge to Room" option.
     */
    public function chargeExtensionToRoom(Request $request): JsonResponse
    {
        [$booking, $unit] = $this->activeStay($request);

        $data = $request->validate(['check_out' => ['required', 'date']]);
        $q = $this->extensionQuote($booking, $unit, $data['check_out']);

        $payment = StayExtensionPayment::create([
            'booking_id' => $booking->id,
            'reference' => 'EXT-'.$booking->id.'-'.strtoupper(Str::random(10)),
            'nights' => $q['nights'],
            'new_check_out' => $q['newOut']->toDateString(),
            'amount' => $q['base'],
            'vat' => $q['vat'],
            'status' => StayExtensionPayment::SUCCESS,
            'paid_at' => now(),
            'payment_method' => 'room_charge',
        ]);

        $this->applyExtension($booking, $q, null);

        SendStayExtensionReceipt::dispatchSync($payment->id);
        $this->notifyExtended($booking, $unit, $payment);

        return response()->json(['data' => $this->extensionPayload($booking, $unit, $payment)]);
    }

    /**
     * POST /tablets/extend-stay — verify the Paystack charge and, once it is
     * confirmed, move the checkout later and bill the extra nights. Idempotent:
     * a repeated call for an already-applied reference just returns the stay.
     */
    public function extendStay(Request $request): JsonResponse
    {
        [$booking, $unit] = $this->activeStay($request);

        $data = $request->validate([
            'check_out' => ['required', 'date'],
            'reference' => ['required', 'string'],
        ]);

        $payment = StayExtensionPayment::where('reference', $data['reference'])
            ->where('booking_id', $booking->id)
            ->first();
        abort_unless($payment, 404, 'We could not find that payment.');

        // Already verified and applied — the checkout has moved. Return as-is.
        if ($payment->isSuccessful()) {
            return response()->json(['data' => $this->extensionPayload($booking, $unit, $payment)]);
        }

        // Confirm the charge with Paystack before touching the booking.
        $secret = config('services.paystack.secret_key');
        try {
            $verify = Http::withToken($secret)->acceptJson()->get(
                rtrim((string) config('services.paystack.payment_url'), '/').'/transaction/verify/'.$data['reference']
            );
        } catch (\Throwable $e) {
            report($e);
            abort(502, 'Could not verify the payment. Please try again.');
        }

        $body = $verify->json();
        abort_unless(
            $verify->ok() && data_get($body, 'data.status') === 'success',
            402,
            'Your payment was not completed.'
        );
        abort_if(
            (int) data_get($body, 'data.amount') < ((int) $payment->amount + (int) $payment->vat) * 100,
            402,
            'The payment amount did not match.'
        );

        // Re-price now: the room must still be free for the extra nights.
        $q = $this->extensionQuote($booking, $unit, $data['check_out']);
        $channel = data_get($body, 'data.channel');
        $this->applyExtension($booking, $q, $channel);

        // Stamp when (and how) the guest paid so the admin Payments module can
        // list this extension as its own dated transaction.
        $payment->update([
            'status' => StayExtensionPayment::SUCCESS,
            'paid_at' => now(),
            'payment_method' => $channel,
        ]);

        // Email the guest their receipt. Sent synchronously (and guarded) rather
        // than after-response: moving the checkout re-issues the gate code via its
        // own afterResponse job, and if that throws (e.g. a lock is offline) it
        // aborts the whole terminating-callback chain — which silently skipped the
        // receipt. This sits after the idempotent early-return, so a repeated
        // verify never re-sends it.
        SendStayExtensionReceipt::dispatchSync($payment->id);
        $this->notifyExtended($booking, $unit, $payment);

        return response()->json(['data' => $this->extensionPayload($booking, $unit, $payment)]);
    }

    /**
     * Apply a priced extension to the booking's dates and folio. Shared by the
     * Paystack path (after verification) and the charge-to-room path
     * (immediately) — the folio always carries the pre-VAT room charge; VAT is
     * a payment-time tax line, never folio revenue. A null [$channel] (the
     * charge-to-room path) leaves the booking's own payment method untouched.
     */
    private function applyExtension(Booking $booking, array $q, ?string $channel): void
    {
        $booking->forceFill([
            'check_out' => $q['newOut']->toDateString(),
            'nights' => (int) $booking->nights + $q['nights'],
            'amount' => (int) $booking->amount + $q['base'],
            'payment_method' => $channel ?? $booking->payment_method,
        ])->save();
        $booking->refresh();
    }

    /** Notify the guest's own tablet feed and the front desk of an applied stay extension. */
    private function notifyExtended(Booking $booking, RoomUnit $unit, StayExtensionPayment $payment): void
    {
        GuestNotification::notify(
            $booking,
            $unit,
            'payment',
            'Stay Extended',
            'Your stay has been extended to '.Carbon::parse($booking->check_out)->format('F j, Y').'.',
        );

        $totalLabel = '₦'.number_format((int) $payment->amount + (int) $payment->vat);
        $verb = $payment->payment_method === 'room_charge' ? 'charged to their room bill' : 'paid';

        // The front desk should know a guest just extended their stay — it
        // changes the room's expected checkout date and, once settled, the
        // day's revenue.
        ReceptionNotification::notify(
            'payment',
            'Stay Extension',
            ($booking->customer_name ?: 'A guest').' in Room '.($unit->number ?? $booking->room_name).
                ' extended their stay to '.Carbon::parse($booking->check_out)->format('F j, Y').
                ' — '.$totalLabel.' '.$verb.'.',
            $booking,
        );
    }

    /* ---------------- Spa & Wellness ---------------- */

    /**
     * The fixed appointment times the spa offers each day (Figma design — no
     * per-slot capacity system exists yet, so every slot is always offered).
     */
    private const SPA_TIMES = ['9:00 AM', '10:30 AM', '12:00 PM', '2:00 PM', '3:30 PM', '5:00 PM', '6:30 PM'];

    /**
     * GET /tablets/spa/services — the spa's bookable services (with their
     * real admin-managed categories) plus today's available appointment
     * times, for the guest tablet's Spa & Wellness screen.
     */
    public function spaServices(Request $request): JsonResponse
    {
        $this->activeStay($request);

        $services = SpaService::active()->ordered()->with('category')->get();

        return response()->json(['data' => [
            'services' => $services->map->toGuestArray()->values(),
            'categories' => SpaCategory::ordered()->get()->map->toGuestArray()->values(),
            'available_times' => self::SPA_TIMES,
        ]]);
    }

    /**
     * GET /tablets/spa/appointments — the checked-in guest's own spa bookings,
     * newest first: every confirmed session, whether charged to the room or
     * paid directly via Paystack. A Paystack checkout the guest never
     * completed stays `pending`/unpaid and is left out — it was never a real
     * appointment.
     */
    public function spaAppointments(Request $request): JsonResponse
    {
        [$booking] = $this->activeStay($request);

        $appointments = SpaBooking::where('booking_id', $booking->id)
            ->where('payment_status', 'paid')
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get();

        return response()->json(['data' => $appointments->map->toGuestAppointmentArray()->values()]);
    }

    /**
     * POST /tablets/spa/book — charge a spa session straight to the room's
     * folio, no Paystack round trip needed. Confirmed the moment it's
     * recorded, the same as a guest signing a chit at the spa desk.
     */
    public function bookSpaToRoom(Request $request): JsonResponse
    {
        [$booking, $unit] = $this->activeStay($request);
        $data = $this->validateSpaBooking($request);
        $service = $this->findSpaService($data['service_slug']);
        $q = $this->spaQuote($service);

        $spaBooking = SpaBooking::create([
            'booking_id' => $booking->id,
            'reference' => 'SPA-'.$booking->id.'-'.strtoupper(Str::random(10)),
            'services' => [['name' => $service->name, 'slug' => $service->slug, 'price' => $service->price, 'qty' => 1]],
            'guests' => 1,
            'date' => now()->toDateString(),
            'time' => $data['time'],
            'subtotal' => $q['base'],
            'vat' => $q['vat'],
            'total' => $q['base'],
            'customer_name' => $booking->customer_name,
            'customer_email' => $booking->customer_email,
            'customer_phone' => $booking->customer_phone,
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'payment_method' => 'room_charge',
            'paid_at' => now(),
        ]);

        $this->notifySpaBooked($booking, $unit, $spaBooking, $service);

        return response()->json(['data' => $spaBooking->toGuestConfirmationArray()]);
    }

    /**
     * POST /tablets/spa/initialize — price the session and open a Paystack
     * transaction, for the guest paying directly rather than charging the room.
     */
    public function initializeSpaBooking(Request $request): JsonResponse
    {
        [$booking] = $this->activeStay($request);
        $data = $this->validateSpaBooking($request);
        $service = $this->findSpaService($data['service_slug']);
        $q = $this->spaQuote($service);

        $secret = config('services.paystack.secret_key');
        abort_if(blank($secret), 503, 'Online payment is not available right now.');

        $reference = 'SPA-'.$booking->id.'-'.strtoupper(Str::random(10));
        $callbackUrl = rtrim((string) config('app.url'), '/').'/tablet/extend-return';
        $email = $booking->customer_email ?: 'guest'.$booking->id.'@retirodelrocio.ng';

        try {
            $response = Http::withToken($secret)->acceptJson()->post(
                rtrim((string) config('services.paystack.payment_url'), '/').'/transaction/initialize',
                [
                    'email' => $email,
                    'amount' => $q['total'] * 100,
                    'reference' => $reference,
                    'callback_url' => $callbackUrl,
                    'metadata' => [
                        'booking_id' => $booking->id,
                        'purpose' => 'spa_booking',
                        'service_slug' => $service->slug,
                    ],
                ],
            );
        } catch (\Throwable $e) {
            report($e);
            abort(502, 'Could not start the payment. Please try again.');
        }

        $authUrl = data_get($response->json(), 'data.authorization_url');
        abort_if(! $response->ok() || ! $authUrl, 502, 'Could not start the payment. Please try again.');

        SpaBooking::create([
            'booking_id' => $booking->id,
            'reference' => $reference,
            'services' => [['name' => $service->name, 'slug' => $service->slug, 'price' => $service->price, 'qty' => 1]],
            'guests' => 1,
            'date' => now()->toDateString(),
            'time' => $data['time'],
            'subtotal' => $q['base'],
            'vat' => $q['vat'],
            'total' => $q['base'],
            'customer_name' => $booking->customer_name,
            'customer_email' => $booking->customer_email,
            'customer_phone' => $booking->customer_phone,
            'status' => 'pending',
            'payment_status' => 'pending',
        ]);

        return response()->json(['data' => [
            'authorization_url' => $authUrl,
            'reference' => $reference,
            'callback_url' => $callbackUrl,
            'subtotal' => $q['base'],
            'subtotal_label' => 'NGN '.number_format($q['base']),
            'vat' => $q['vat'],
            'vat_label' => 'NGN '.number_format($q['vat']),
            'total' => $q['total'],
            'total_label' => 'NGN '.number_format($q['total']),
        ]]);
    }

    /**
     * POST /tablets/spa/confirm — verify the Paystack charge and confirm the
     * booking. Idempotent: a repeated call for an already-applied reference
     * just returns the booking.
     */
    public function confirmSpaBooking(Request $request): JsonResponse
    {
        [$booking, $unit] = $this->activeStay($request);

        $data = $request->validate(['reference' => ['required', 'string']]);

        $spaBooking = SpaBooking::where('reference', $data['reference'])
            ->where('booking_id', $booking->id)
            ->first();
        abort_unless($spaBooking, 404, 'We could not find that booking.');

        if ($spaBooking->payment_status === 'paid') {
            return response()->json(['data' => $spaBooking->toGuestConfirmationArray()]);
        }

        $secret = config('services.paystack.secret_key');
        try {
            $verify = Http::withToken($secret)->acceptJson()->get(
                rtrim((string) config('services.paystack.payment_url'), '/').'/transaction/verify/'.$data['reference']
            );
        } catch (\Throwable $e) {
            report($e);
            abort(502, 'Could not verify the payment. Please try again.');
        }

        $body = $verify->json();
        abort_unless(
            $verify->ok() && data_get($body, 'data.status') === 'success',
            402,
            'Your payment was not completed.'
        );
        abort_if(
            (int) data_get($body, 'data.amount') < ((int) $spaBooking->subtotal + (int) $spaBooking->vat) * 100,
            402,
            'The payment amount did not match.'
        );

        $spaBooking->update([
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'payment_method' => data_get($body, 'data.channel'),
            'paid_at' => now(),
        ]);

        $service = SpaService::where('slug', data_get($spaBooking->services, '0.slug'))->first();
        $this->notifySpaBooked($booking, $unit, $spaBooking->fresh(), $service);

        return response()->json(['data' => $spaBooking->fresh()->toGuestConfirmationArray()]);
    }

    /** @return array{service_slug: string, time: string} */
    private function validateSpaBooking(Request $request): array
    {
        return $request->validate([
            'service_slug' => ['required', 'string'],
            'time' => ['required', 'string', Rule::in(self::SPA_TIMES)],
        ]);
    }

    private function findSpaService(string $slug): SpaService
    {
        $service = SpaService::active()->where('slug', $slug)->first();
        abort_unless($service, 404, 'That service is not available.');

        return $service;
    }

    /** Price a spa session: the service's price plus VAT on top. */
    private function spaQuote(SpaService $service): array
    {
        $base = (int) $service->price;
        $vat = (int) round($base * self::VAT_RATE);
        $total = $base + $vat;

        return compact('base', 'vat', 'total');
    }

    /** Notify the guest's own tablet feed and the front desk of a new spa booking. */
    private function notifySpaBooked(Booking $booking, RoomUnit $unit, SpaBooking $spaBooking, ?SpaService $service): void
    {
        $serviceName = $service?->name ?? collect($spaBooking->services)->pluck('name')->first() ?? 'Spa session';

        GuestNotification::notify(
            $booking,
            $unit,
            'spa',
            'Spa Booking Confirmed',
            $serviceName.' is booked for '.$spaBooking->time.' today.',
        );

        ReceptionNotification::notify(
            'booking',
            'New Spa Booking',
            ($booking->customer_name ?: 'A guest').' in Room '.($unit->number ?? $booking->room_name).
                ' booked '.$serviceName.' at '.$spaBooking->time.'.',
            $booking,
        );
    }

    /**
     * Validate and price a stay extension. Aborts with a guest-facing message on
     * any problem (past date, too long, or the room already claimed).
     *
     * @return array{newOut: Carbon, nights: int, rate: int, base: int, vat: int, total: int}
     */
    private function extensionQuote(Booking $booking, RoomUnit $unit, string $checkOutInput): array
    {
        $currentOut = Carbon::parse($booking->check_out)->startOfDay();
        $newOut = Carbon::parse($checkOutInput)->startOfDay();

        abort_if($newOut->lessThanOrEqualTo($currentOut), 422, 'Choose a checkout date after your current one.');

        $nights = (int) $currentOut->diffInDays($newOut);
        abort_if($nights > 30, 422, 'You can extend by up to 30 nights at a time.');

        // The room must remain free for the extra nights: no other active booking
        // on this unit may overlap [current checkout, new checkout).
        $conflict = Booking::where('id', '!=', $booking->id)
            ->where('room_unit_id', $unit->id)
            ->whereIn('status', ['paid', 'checked_in'])
            ->whereDate('check_in', '<', $newOut->toDateString())
            ->whereDate('check_out', '>', $currentOut->toDateString())
            ->exists();
        abort_if($conflict, 422, 'The room is booked by another guest for those dates.');

        $room = $unit->room ?? $booking->room;
        $rate = $room && $room->price
            ? (int) $room->price
            : intdiv((int) $booking->amount, max(1, (int) $booking->nights));

        $base = $nights * $rate;
        $vat = (int) round($base * self::VAT_RATE);
        $total = $base + $vat;

        return compact('newOut', 'nights', 'rate', 'base', 'vat', 'total');
    }

    /** The My Stay payload plus the applied extension's details. */
    private function extensionPayload(Booking $booking, RoomUnit $unit, StayExtensionPayment $payment): array
    {
        $payload = $this->stayPayload($booking, $unit);
        $payload['extension'] = [
            'additional_nights' => (int) $payment->nights,
            'additional_cost' => (int) $payment->amount,
            'additional_cost_label' => 'NGN '.number_format((int) $payment->amount),
            'new_check_out' => optional($booking->departureAt())->toIso8601String(),
            'new_check_out_label' => Carbon::parse($booking->check_out)->format('F j, Y'),
        ];

        return $payload;
    }

    /* ---------------- My Bills ---------------- */

    /**
     * GET /tablets/my-bills — the checked-in guest's itemised folio for the My
     * Bills screen: stay extensions charged to the room, spa sessions charged
     * to the room, the categories with no charge source yet (Restaurant & Bar,
     * Cinema, Gym), a priced summary and the checkout countdown. The room
     * rate and airport pickup are settled at booking time and never appear.
     */
    public function myBills(Request $request): JsonResponse
    {
        [$booking, $unit] = $this->activeStay($request);

        return response()->json(['data' => $this->billsPayload($booking, $unit)]);
    }

    /**
     * POST /tablets/my-bills/initialize — price the guest's outstanding balance
     * and open a Paystack transaction to pre-settle it ahead of checkout. No
     * folio is touched here — that only happens once the charge is verified in
     * {@see confirmBillPayment()}.
     */
    public function initializeBillPayment(Request $request): JsonResponse
    {
        [$booking] = $this->activeStay($request);

        $q = $this->billQuote($booking);
        abort_if($q['due'] <= 0, 422, 'There is nothing to pay right now.');

        $secret = config('services.paystack.secret_key');
        abort_if(blank($secret), 503, 'Online payment is not available right now.');

        $reference = 'BILL-'.$booking->id.'-'.strtoupper(Str::random(10));
        $callbackUrl = rtrim((string) config('app.url'), '/').'/tablet/extend-return';
        $email = $booking->customer_email ?: 'guest'.$booking->id.'@retirodelrocio.ng';

        try {
            $response = Http::withToken($secret)->acceptJson()->post(
                rtrim((string) config('services.paystack.payment_url'), '/').'/transaction/initialize',
                [
                    'email' => $email,
                    'amount' => $q['due'] * 100,
                    'reference' => $reference,
                    'callback_url' => $callbackUrl,
                    'metadata' => [
                        'booking_id' => $booking->id,
                        'purpose' => 'bill_payment',
                    ],
                ],
            );
        } catch (\Throwable $e) {
            report($e);
            abort(502, 'Could not start the payment. Please try again.');
        }

        $authUrl = data_get($response->json(), 'data.authorization_url');
        abort_if(! $response->ok() || ! $authUrl, 502, 'Could not start the payment. Please try again.');

        BillPayment::create([
            'booking_id' => $booking->id,
            'reference' => $reference,
            'amount' => $q['amount_due'],
            'vat' => $q['vat_due'],
            'status' => BillPayment::PENDING,
        ]);

        return response()->json(['data' => [
            'authorization_url' => $authUrl,
            'reference' => $reference,
            'callback_url' => $callbackUrl,
            'subtotal' => $q['amount_due'],
            'subtotal_label' => 'NGN '.number_format($q['amount_due']),
            'vat' => $q['vat_due'],
            'vat_label' => 'NGN '.number_format($q['vat_due']),
            'total' => $q['due'],
            'total_label' => 'NGN '.number_format($q['due']),
        ]]);
    }

    /**
     * POST /tablets/my-bills/confirm — verify the Paystack charge and record
     * the settlement. Idempotent: a repeated call for an already-applied
     * reference just returns the payment.
     */
    public function confirmBillPayment(Request $request): JsonResponse
    {
        [$booking, $unit] = $this->activeStay($request);

        $data = $request->validate(['reference' => ['required', 'string']]);

        $payment = BillPayment::where('reference', $data['reference'])
            ->where('booking_id', $booking->id)
            ->first();
        abort_unless($payment, 404, 'We could not find that payment.');

        if ($payment->isSuccessful()) {
            return response()->json(['data' => $payment->toGuestConfirmationArray()]);
        }

        $secret = config('services.paystack.secret_key');
        try {
            $verify = Http::withToken($secret)->acceptJson()->get(
                rtrim((string) config('services.paystack.payment_url'), '/').'/transaction/verify/'.$data['reference']
            );
        } catch (\Throwable $e) {
            report($e);
            abort(502, 'Could not verify the payment. Please try again.');
        }

        $body = $verify->json();
        abort_unless(
            $verify->ok() && data_get($body, 'data.status') === 'success',
            402,
            'Your payment was not completed.'
        );
        abort_if(
            (int) data_get($body, 'data.amount') < ((int) $payment->amount + (int) $payment->vat) * 100,
            402,
            'The payment amount did not match.'
        );

        $payment->update([
            'status' => BillPayment::SUCCESS,
            'paid_at' => now(),
            'payment_method' => data_get($body, 'data.channel'),
        ]);

        $totalLabel = $payment->toGuestConfirmationArray()['total_label'];

        GuestNotification::notify(
            $booking,
            $unit,
            'payment',
            'Bill Confirmed',
            'You paid '.$totalLabel.' towards your room bill.',
        );

        ReceptionNotification::notify(
            'payment',
            'Guest Bill Payment',
            ($booking->customer_name ?: 'A guest').' in Room '.($unit->number ?? $booking->room_name).
                ' paid '.$totalLabel.' towards their bill from the tablet.',
            $booking,
        );

        return response()->json(['data' => $payment->fresh()->toGuestConfirmationArray()]);
    }

    /**
     * The itemised My Bills payload: real room charges, real room-charge spa
     * sessions, the categories with no charge source yet, a priced summary
     * and the checkout countdown.
     */
    private function billsPayload(Booking $booking, RoomUnit $unit): array
    {
        $room = $unit->room ?? $booking->room;
        $breakdown = $this->billBreakdown($booking);

        $checkOut = $booking->departureAt();
        $daysRemaining = $checkOut
            ? max(0, (int) round(now()->startOfDay()->diffInDays($checkOut->copy()->startOfDay(), false)))
            : 0;

        return [
            'reservation' => [
                'room_name' => $room?->name ?? $booking->room_name,
                'unit_label' => $unit->number ? 'Room '.$unit->number : null,
            ],
            'categories' => $breakdown['categories'],
            'summary' => [
                'lines' => $breakdown['summary_lines'],
                'total_due' => $breakdown['due'],
                'total_due_label' => 'NGN '.number_format($breakdown['due']),
            ],
            'checkout_reminder' => [
                'check_out_label' => $checkOut ? $checkOut->format('D, M j \a\t g:i A') : null,
                'days_remaining' => $daysRemaining,
                'days_remaining_label' => $daysRemaining === 1 ? '1 day remaining' : $daysRemaining.' days remaining',
            ],
            'can_pay' => $breakdown['due'] > 0,
        ];
    }

    /**
     * Resolve the guest currently checked into this tablet's room, or 404. The
     * device token scopes the whole thing to one room.
     *
     * @return array{0: Booking, 1: RoomUnit}
     */
    private function activeStay(Request $request): array
    {
        $device = $this->device($request);
        $unit = $device->roomUnit()->with(['room', 'booking.room'])->first();

        $booking = $unit && $unit->status === 'occupied' && $unit->booking?->status === 'checked_in'
            ? $unit->booking
            : null;

        abort_unless($booking, 404, 'No active stay for this room.');

        return [$booking, $unit];
    }

    /** The My Stay payload for a checked-in booking. All figures are real. */
    private function stayPayload(Booking $booking, RoomUnit $unit): array
    {
        $room = $unit->room ?? $booking->room;
        $nights = max(1, (int) $booking->nights);
        $rate = $room && $room->price
            ? (int) $room->price
            : intdiv((int) $booking->amount, $nights);

        $pickup = (int) ($booking->pickup_price ?? 0);
        $amount = (int) $booking->amount;

        // Every successful extension, however it was paid — Stay Summary is
        // the guest's full financial history for this stay, unlike My Bills
        // (which only ever shows what's still charged to the room).
        $extensions = $booking->stayExtensionPayments()
            ->where('status', StayExtensionPayment::SUCCESS)
            ->get();
        $extensionsTotal = (int) $extensions->sum('amount');
        $roomSubtotal = max(0, $amount - $pickup - $extensionsTotal);

        $lines = [[
            'label' => 'Room Rate',
            'sub' => 'NGN '.number_format($rate).' × '.$nights,
            'amount_label' => 'NGN '.number_format($roomSubtotal),
        ]];
        if ($pickup > 0) {
            $lines[] = [
                'label' => 'Airport Pickup',
                'sub' => $booking->pickup_vehicle,
                'amount_label' => 'NGN '.number_format($pickup),
            ];
        }
        foreach ($extensions as $extension) {
            $lines[] = [
                'label' => 'Stay Extension',
                'sub' => $extension->nights.' night(s) to '.Carbon::parse($extension->new_check_out)->format('M j, Y'),
                'amount_label' => 'NGN '.number_format((int) $extension->amount),
            ];
        }

        // What's still charged to the room right now — the same real balance
        // the My Bills screen shows, so "Current Bill" leads straight there.
        $due = $this->billQuote($booking)['due'];

        $inclusions = collect($room?->amenities ?? [])
            ->filter(fn ($a) => is_string($a) && trim($a) !== '')
            ->map(fn ($a) => ['title' => trim($a), 'value' => 'Included'])
            ->values()
            ->all();
        if ($booking->isPickup()) {
            array_unshift($inclusions, ['title' => 'Airport Transfer', 'value' => 'Booked']);
        }

        // The visitors this guest has invited during the stay — still-expected
        // (pending) or arrived (verified). Denied/cancelled/expired passes are
        // gate noise the guest need not see on their reservation card.
        $visitors = VisitorPass::where('booking_id', $booking->id)
            ->whereIn('status', [VisitorPass::PENDING, VisitorPass::VERIFIED])
            ->latest('id')
            ->limit(8)
            ->get()
            ->map->toStayVisitorArray()
            ->all();

        return [
            'reservation' => [
                'room_name' => $room?->name ?? $booking->room_name,
                'unit_label' => $unit->number ? 'Room '.$unit->number : null,
                'image_url' => $room?->featuredUrl(),
                'check_in' => optional($booking->arrivalAt())->toIso8601String(),
                'check_out' => optional($booking->departureAt())->toIso8601String(),
                'nights' => $nights,
                'rate_per_night' => $rate,
                'rate_label' => 'NGN '.number_format($rate).'/night',
            ],
            'guests' => [
                'primary_name' => $booking->customer_name,
                'party_size' => max(1, (int) $booking->guests),
            ],
            'visitors' => $visitors,
            'summary' => [
                'lines' => $lines,
                'total_label' => 'NGN '.number_format($amount),
            ],
            'current_bill_label' => 'NGN '.number_format($due),
            'current_bill_due' => $due > 0,
            'inclusions' => $inclusions,
        ];
    }

    public function restart(Request $request, DeviceCommandService $commands): JsonResponse
    {
        return $this->command($request, 'restart', fn (Device $d) => $commands->restart($d));
    }

    public function lock(Request $request, DeviceCommandService $commands): JsonResponse
    {
        return $this->command($request, 'lock', fn (Device $d) => $commands->lock($d));
    }

    public function unlock(Request $request, DeviceCommandService $commands): JsonResponse
    {
        return $this->command($request, 'unlock', fn (Device $d) => $commands->unlock($d));
    }

    /** Shared staff-command handler: validate, authorize via policy, record. */
    private function command(Request $request, string $ability, Closure $run): JsonResponse
    {
        abort_unless($request->user() instanceof User, 403, 'Staff access only.');

        $data = $request->validate(['device_code' => ['required', 'string', 'max:60']]);
        $device = Device::where('device_code', $data['device_code'])->firstOrFail();

        $this->authorize($ability, $device);
        $run($device);

        return response()->json([
            'ok' => true,
            'message' => ucfirst($ability).' command queued for '.$device->device_code.'.',
        ]);
    }

    private function device(Request $request): Device
    {
        $device = $request->user();
        abort_unless($device instanceof Device, 403, 'Device token required.');

        return $device;
    }
}
