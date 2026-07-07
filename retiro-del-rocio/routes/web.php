<?php

use App\Events\BookingConfirmed;
use App\Http\Controllers\Admin\Auth\LogoutController;
use App\Livewire\Admin\Auth\ForgotPassword;
use App\Livewire\Admin\Auth\Login;
use App\Livewire\Admin\Auth\ResetSuccess;
use App\Livewire\Admin\Auth\SetNewPassword;
use App\Livewire\Admin\Auth\VerifyCode;
use App\Livewire\Admin\Rooms\Edit;
use App\Livewire\Admin\Rooms\Index;
use App\Mail\BookingRequest;
use App\Mail\BookingReservation;
use App\Mail\ContactAcknowledgement;
use App\Mail\ContactEnquiry;
use App\Mail\GymMembershipConfirmation;
use App\Mail\PickupConfirmation;
use App\Mail\SpaReservation;
use App\Models\Booking;
use App\Mail\CinemaBookingConfirmation;
use App\Mail\RestaurantReservationConfirmation;
use App\Models\CinemaBooking;
use App\Models\CinemaSeatHold;
use App\Models\CinemaSnack;
use App\Models\GymMembership;
use App\Models\GymPlan;
use App\Models\ContactMessage;
use App\Models\Movie;
use App\Models\RestaurantReservation;
use App\Models\RestaurantTable;
use App\Models\Room;
use App\Models\SpaBooking;
use App\Models\SpaService;
use App\Models\User;
use App\Notifications\BookingReceived;
use App\Notifications\CinemaBookingReceived;
use App\Notifications\GymMembershipReceived;
use App\Notifications\MessageReceived;
use App\Notifications\RestaurantReservationReceived;
use App\Notifications\SpaBookingReceived;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;

// Public website.
Route::view('/', 'welcome')->name('home');
Route::view('spa-wellness', 'spa')->name('spa');
Route::view('rooms-apartment', 'rooms')->name('rooms');
Route::view('experience', 'experience')->name('experience');
Route::view('privacy-policy', 'privacy')->name('privacy');
Route::view('terms-of-service', 'terms')->name('terms');
Route::view('booking-policy', 'booking')->name('booking');
Route::get('rooms-apartment/{room:slug}', function (Room $room) {
    abort_unless($room->is_published, 404);

    return view('room-detail', [
        'room' => $room,
        'offers' => Room::published()->where('id', '!=', $room->id)->ordered()->take(2)->get(),
    ]);
})->name('rooms.show');

// Live room availability for a date range (used by the room detail page).
Route::get('rooms-apartment/{room:slug}/availability', function (Room $room) {
    $checkIn = request('check_in');
    $checkOut = request('check_out');

    if (! $checkIn || ! $checkOut || Carbon::parse($checkOut)->lte(Carbon::parse($checkIn))) {
        return response()->json(['ok' => false, 'available' => null, 'count' => null]);
    }

    $count = $room->availableUnitsForDates($checkIn, $checkOut);

    return response()->json([
        'ok' => true,
        'available' => $count === null ? true : $count > 0,
        'count' => $count, // null = no inventory limit configured
    ]);
})->name('rooms.availability');

/*
|--------------------------------------------------------------------------
| Checkout flow (Paystack)
|--------------------------------------------------------------------------
*/

// Build the full priced booking summary from the raw reservation input.
$buildBooking = function (array $b): array {
    // Prefer the real room price; fall back to the submitted label, then a default.
    $room = ! empty($b['room_slug']) ? Room::where('slug', $b['room_slug'])->first() : null;
    $pricePerNight = $room?->price
        ?? (! empty($b['price']) ? (int) preg_replace('/[^0-9]/', '', $b['price']) : 0)
        ?: 350000;
    $checkIn = Carbon::parse($b['check_in']);
    $checkOut = Carbon::parse($b['check_out']);
    $nights = max(1, (int) $checkIn->diffInDays($checkOut));
    $roomSubtotal = $pricePerNight * $nights;
    $pickupPrice = ! empty($b['pickup_price']) ? (int) preg_replace('/[^0-9]/', '', $b['pickup_price']) : 0;
    $subtotal = $roomSubtotal + $pickupPrice;
    $vat = (int) round($subtotal * 0.075);
    $fees = 1250;
    $total = $subtotal + $vat + $fees;
    $naira = fn ($n) => '₦'.number_format($n);

    if ($checkIn->isSameMonth($checkOut) && $checkIn->year === $checkOut->year) {
        $dateRange = $checkIn->format('j').' - '.$checkOut->format('j M, Y');
    } else {
        $dateRange = $checkIn->format('j M').' - '.$checkOut->format('j M, Y');
    }

    return [
        'room' => $b['room'],
        'room_slug' => $b['room_slug'] ?? null,
        'price_per_night' => $pricePerNight,
        'price' => $naira($pricePerNight),
        'guests' => (int) $b['guests'],
        'check_in' => $b['check_in'],
        'check_out' => $b['check_out'],
        'date_range' => $dateRange,
        'nights' => $nights,
        'pickup_vehicle' => $b['pickup_vehicle'] ?? null,
        'pickup_price' => $pickupPrice ? $naira($pickupPrice) : null,
        'location' => $b['location'] ?? null,
        'passengers' => $b['passengers'] ?? null,
        'arrival_date' => $b['arrival_date'] ?? null,
        'pickup_time' => $b['pickup_time'] ?? null,
        'flight_number' => $b['flight_number'] ?? null,
        'room_subtotal_label' => $naira($roomSubtotal),
        'vat_label' => $naira($vat),
        'fees_label' => $naira($fees),
        'total' => $total,
        'total_label' => $naira($total),
        'total_kobo' => $total * 100,
    ];
};

// Step 1 — "Make reservation" from the room detail page lands here.
Route::post('checkout', function () use ($buildBooking) {
    $data = request()->validate([
        'room' => ['required', 'string', 'max:190'],
        'room_slug' => ['nullable', 'string', 'max:190'],
        'price' => ['required', 'string', 'max:60'],
        'guests' => ['required', 'integer', 'min:1', 'max:30'],
        'check_in' => ['required', 'date'],
        'check_out' => ['required', 'date', 'after_or_equal:check_in'],
        'pickup_vehicle' => ['nullable', 'string', 'max:120'],
        'pickup_price' => ['nullable', 'string', 'max:60'],
        'location' => ['nullable', 'string', 'max:190'],
        'passengers' => ['nullable', 'integer', 'min:1', 'max:30'],
        'arrival_date' => ['nullable', 'date'],
        'pickup_time' => ['nullable', 'string', 'max:20'],
        'flight_number' => ['nullable', 'string', 'max:40'],
    ]);

    // Block booking when no room number is free for the requested dates.
    if (! empty($data['room_slug'])) {
        $room = Room::where('slug', $data['room_slug'])->first();
        if ($room && ! $room->isAvailableForDates($data['check_in'], $data['check_out'])) {
            return back()->with('toast', [
                'type' => 'error',
                'message' => 'Sorry, '.$room->name.' is fully booked for those dates. Please choose different dates.',
            ]);
        }
    }

    session(['booking' => $buildBooking($data)]);

    return redirect()->route('checkout');
})->name('checkout.start');

// Step 2 — the checkout page (customer details + summary + Paystack).
Route::get('checkout', function () {
    $booking = session('booking');

    if (! $booking) {
        return redirect()->route('rooms');
    }

    return view('checkout', [
        'booking' => $booking,
        'paystackKey' => config('services.paystack.public_key'),
    ]);
})->name('checkout');

// Step 3 — Paystack redirects/JS sends the user here after payment to verify.
Route::get('checkout/callback', function () {
    $reference = request('reference');
    $booking = session('booking');

    if (! $reference || ! $booking) {
        return redirect()->route('rooms');
    }

    $secret = config('services.paystack.secret_key');

    try {
        $response = Http::withToken($secret)
            ->acceptJson()
            ->get(rtrim(config('services.paystack.payment_url'), '/').'/transaction/verify/'.$reference);

        $body = $response->json();

        if (! $response->ok() || data_get($body, 'data.status') !== 'success') {
            return redirect()->route('checkout')->with('toast', [
                'type' => 'error',
                'message' => 'We could not verify your payment. If you were charged, please contact us with your reference: '.$reference,
            ]);
        }
    } catch (Throwable $e) {
        report($e);

        return redirect()->route('checkout')->with('toast', [
            'type' => 'error',
            'message' => 'Payment verification failed. Please try again or contact us.',
        ]);
    }

    $order = array_merge($booking, [
        'customer_name' => data_get($body, 'data.metadata.name'),
        'customer_phone' => data_get($body, 'data.metadata.phone'),
        'customer_email' => data_get($body, 'data.customer.email'),
        'reference' => $reference,
        'paid_at' => data_get($body, 'data.paid_at'),
    ]);

    session(['order' => $order]);
    session()->forget('booking');

    // Persist the paid booking so it appears in Admin → Apartments → Bookings.
    try {
        $room = Room::where('slug', $order['room_slug'] ?? null)
            ->orWhere('name', $order['room'] ?? null)
            ->first();

        $booking = Booking::updateOrCreate(
            ['reference' => $reference],
            [
                'room_id' => $room?->id,
                'room_name' => $order['room'] ?? null,
                'guests' => (int) ($order['guests'] ?? 1),
                'check_in' => $order['check_in'] ?? null,
                'check_out' => $order['check_out'] ?? null,
                'nights' => (int) ($order['nights'] ?? 1),
                'amount' => (int) ($order['total'] ?? 0),
                'customer_name' => $order['customer_name'] ?? null,
                'customer_email' => $order['customer_email'] ?? null,
                'customer_phone' => $order['customer_phone'] ?? null,
                'pickup_vehicle' => $order['pickup_vehicle'] ?? null,
                'pickup_price' => $order['pickup_price'] ?? null,
                'pickup_passengers' => ! empty($order['passengers']) ? (int) $order['passengers'] : null,
                'pickup_location' => $order['location'] ?? null,
                'pickup_arrival_date' => ! empty($order['arrival_date']) ? $order['arrival_date'] : null,
                'pickup_time' => $order['pickup_time'] ?? null,
                'pickup_flight_number' => $order['flight_number'] ?? null,
                'status' => 'paid',
                'payment_method' => data_get($body, 'data.channel'),
                'paid_at' => $order['paid_at'] ?? now(),
            ]
        );

        if ($booking->wasRecentlyCreated) {
            // Auto-allocate an available physical room number for the booked dates.
            try {
                $booking->autoAssignRoomUnit();
            } catch (Throwable $e) {
                report($e);
            }

            // Provision TTLock smart-lock access (passcode + QR + email) for the
            // confirmed booking. Queued + guarded, so it never blocks checkout.
            try {
                BookingConfirmed::dispatch($booking->fresh());
            } catch (Throwable $e) {
                report($e);
            }

            // Notify the admin bell of a brand-new booking.
            Notification::send(User::admins()->get(), new BookingReceived($booking));

            // Email the guest their reservation confirmation (with the room number),
            // plus a dedicated airport pick-up confirmation when one was booked.
            if ($booking->customer_email) {
                try {
                    Mail::to($booking->customer_email)->send(new BookingReservation($booking));

                    if ($booking->isPickup()) {
                        Mail::to($booking->customer_email)->send(new PickupConfirmation($booking));
                    }
                } catch (Throwable $e) {
                    report($e);
                }
            }
        }
    } catch (Throwable $e) {
        report($e);
    }

    // Notify the hotel of the confirmed, paid reservation.
    try {
        $recipient = config('mail.contact_to', config('mail.from.address'));
        Mail::to($recipient)->send(new BookingRequest([
            'room' => $order['room'],
            'price' => $order['total_label'],
            'guests' => $order['guests'],
            'check_in' => $order['check_in'],
            'check_out' => $order['check_out'],
            'name' => $order['customer_name'],
            'email' => $order['customer_email'],
            'phone' => $order['customer_phone'],
            'pickup_vehicle' => $order['pickup_vehicle'],
            'pickup_price' => $order['pickup_price'],
            'location' => $order['location'],
            'passengers' => $order['passengers'],
            'arrival_date' => $order['arrival_date'],
            'pickup_time' => $order['pickup_time'],
            'flight_number' => $order['flight_number'],
        ]));
    } catch (Throwable $e) {
        report($e);
    }

    return redirect()->route('checkout.success');
})->name('checkout.callback');

// Step 4 — reservation successful screen.
Route::get('reservation-successful', function () {
    $order = session('order');

    if (! $order) {
        return redirect()->route('rooms');
    }

    return view('reservation-success', ['order' => $order]);
})->name('checkout.success');

// Printable receipt.
Route::get('reservation-successful/receipt', function () {
    $order = session('order');

    if (! $order) {
        return redirect()->route('rooms');
    }

    return view('receipt', ['order' => $order]);
})->name('checkout.receipt');

/*
|--------------------------------------------------------------------------
| Spa & Wellness reservation flow (Paystack) — parallels the room checkout
|--------------------------------------------------------------------------
*/

$buildSpaBooking = function (array $data): array {
    $guests = max(1, (int) $data['guests']);
    $naira = fn ($n) => '₦'.number_format($n);

    $services = SpaService::with('category')->active()
        ->whereIn('slug', (array) $data['services'])
        ->ordered()->get()
        ->map(fn ($s) => [
            'name' => $s->name,
            'slug' => $s->slug,
            'price' => $s->price,
            'guests' => $guests,
            'subtotal' => $s->price * $guests,
            'price_label' => $naira($s->price),
            'subtotal_label' => $naira($s->price * $guests),
            'image' => $s->imageUrl(),
            'duration_minutes' => $s->duration_minutes,
            'category' => $s->category?->name,
            'category_color' => $s->category?->color ?? '#6b7280',
        ])->values()->all();

    $subtotal = collect($services)->sum('subtotal');
    $fees = 2000;                              // convenience fee
    $taxes = (int) round($subtotal * 0.075);   // VAT 7.5%
    $total = $subtotal + $fees + $taxes;
    $date = ! empty($data['date']) ? Carbon::parse($data['date']) : null;

    // Format the chosen time to 12-hour with AM/PM, e.g. "15:00" -> "3:00 PM".
    $timeRaw = $data['time'] ?? null;
    $timeLabel = null;
    if ($timeRaw) {
        try {
            $timeLabel = Carbon::createFromFormat('H:i', substr($timeRaw, 0, 5))->format('g:i A');
        } catch (Throwable $e) {
            $timeLabel = $timeRaw;
        }
    }

    return [
        'services' => $services,
        'guests' => $guests,
        'date' => $date?->toDateString(),
        'date_label' => $date?->format('F j, Y') ?? '—',
        'time' => $timeRaw,
        'time_label' => $timeLabel,
        'special_request' => $data['special_request'] ?? null,
        'subtotal' => $subtotal,
        'subtotal_label' => $naira($subtotal),
        'fees' => $fees,
        'fees_label' => $naira($fees),
        'taxes' => $taxes,
        'taxes_label' => $naira($taxes),
        'total' => $total,
        'total_label' => $naira($total),
        'total_kobo' => $total * 100,
    ];
};

// Step 1 — "Complete Reservation" from the spa popup lands here.
Route::post('spa-wellness/reserve', function () use ($buildSpaBooking) {
    $data = request()->validate([
        'services' => ['required', 'array', 'min:1'],
        'services.*' => ['string', 'exists:spa_services,slug'],
        'guests' => ['required', 'integer', 'min:1', 'max:30'],
        'date' => ['required', 'date'],
        'time' => ['nullable', 'string', 'max:20'],
        'special_request' => ['nullable', 'string', 'max:1000'],
    ]);

    $booking = $buildSpaBooking($data);
    if (empty($booking['services'])) {
        return back()->with('toast', ['type' => 'error', 'message' => 'Please choose at least one spa service.']);
    }

    session(['spa_booking' => $booking]);
    session()->forget('spa_order');

    // The spa page reopens the popup at the checkout step (session('spa_booking')).
    return redirect()->route('spa');
})->name('spa.checkout.start');

// Clear an in-progress booking (e.g. "Edit selection" / start over).
Route::get('spa-wellness/reset', function () {
    session()->forget(['spa_booking', 'spa_order']);

    return redirect()->route('spa');
})->name('spa.checkout.reset');

// Step 3 — Paystack verification + persist the spa booking.
Route::get('spa-wellness/callback', function () {
    $reference = request('reference');
    $booking = session('spa_booking');

    if (! $reference || ! $booking) {
        return redirect()->route('spa');
    }

    $secret = config('services.paystack.secret_key');

    try {
        $response = Http::withToken($secret)->acceptJson()
            ->get(rtrim(config('services.paystack.payment_url'), '/').'/transaction/verify/'.$reference);
        $body = $response->json();

        if (! $response->ok() || data_get($body, 'data.status') !== 'success') {
            return redirect()->route('spa')->with('toast', [
                'type' => 'error',
                'message' => 'We could not verify your payment. If you were charged, contact us with reference: '.$reference,
            ]);
        }
    } catch (Throwable $e) {
        report($e);

        return redirect()->route('spa')->with('toast', [
            'type' => 'error', 'message' => 'Payment verification failed. Please try again or contact us.',
        ]);
    }

    $order = array_merge($booking, [
        'customer_name' => data_get($body, 'data.metadata.name'),
        'customer_phone' => data_get($body, 'data.metadata.phone'),
        'customer_email' => data_get($body, 'data.customer.email'),
        'reference' => $reference,
        'paid_at' => data_get($body, 'data.paid_at'),
    ]);

    session()->forget('spa_booking');
    session(['spa_success' => $order]); // consumed once by the spa page (read + forgotten), shows success only here

    try {
        // Booking is automatic: once Paystack confirms payment we mark the
        // session confirmed + paid, then email the guest their confirmation.
        $spaBooking = SpaBooking::updateOrCreate(
            ['reference' => $reference],
            [
                'services' => $order['services'],
                'guests' => (int) $order['guests'],
                'date' => $order['date'] ?? null,
                'time' => $order['time_label'] ?? ($order['time'] ?? null),
                'special_request' => $order['special_request'] ?? null,
                'subtotal' => (int) $order['subtotal'],
                'fees' => (int) $order['fees'],
                'taxes' => (int) $order['taxes'],
                'total' => (int) $order['total'],
                'customer_name' => $order['customer_name'] ?? null,
                'customer_email' => $order['customer_email'] ?? null,
                'customer_phone' => $order['customer_phone'] ?? null,
                'status' => 'confirmed',
                'payment_status' => 'paid',
                'payment_method' => data_get($body, 'data.channel'),
                'paid_at' => $order['paid_at'] ?? now(),
            ]
        );

        // Surface the booking ID on the success screen.
        session(['spa_success' => array_merge($order, ['code' => $spaBooking->sessionCode()])]);

        if ($spaBooking->customer_email) {
            Mail::to($spaBooking->customer_email)->send(new SpaReservation($spaBooking));
        }

        // Ring the admin bell for the new spa reservation.
        Notification::send(User::admins()->get(), new SpaBookingReceived($spaBooking));
    } catch (Throwable $e) {
        report($e);
    }

    // The spa page reopens the popup at the success step (session('spa_order')).
    return redirect()->route('spa');
})->name('spa.checkout.callback');

/*
|--------------------------------------------------------------------------
| Gym membership flow (Paystack)
|--------------------------------------------------------------------------
*/
Route::view('gym', 'gym')->name('gym');

// Subscribe / renew — called via a hidden POST after a successful Paystack charge.
Route::post('gym/subscribe', function () {
    $data = request()->validate([
        'reference' => ['required', 'string', 'max:190'],
        'plan' => ['required', 'string', 'exists:gym_plans,slug'],
        'type' => ['required', 'in:subscribe,renewal'],
        'name' => ['required', 'string', 'max:160'],
        'email' => ['required', 'email', 'max:190'],
        'phone' => ['nullable', 'string', 'max:40'],
        'dob' => ['nullable', 'date'],
        'channel' => ['nullable', 'string', 'max:30'],
    ]);

    $plan = GymPlan::where('slug', $data['plan'])->first();

    // Verify the payment with Paystack before recording anything.
    try {
        $response = Http::withToken(config('services.paystack.secret_key'))->acceptJson()
            ->get(rtrim(config('services.paystack.payment_url'), '/').'/transaction/verify/'.$data['reference']);
        $body = $response->json();

        if (! $response->ok() || data_get($body, 'data.status') !== 'success') {
            return redirect()->route('gym')->with('toast', [
                'type' => 'error',
                'message' => 'We could not verify your payment. If you were charged, contact us with reference: '.$data['reference'],
            ]);
        }
    } catch (Throwable $e) {
        report($e);

        return redirect()->route('gym')->with('toast', ['type' => 'error', 'message' => 'Payment verification failed. Please try again or contact us.']);
    }

    $phone = $data['phone'] ? '+234'.ltrim($data['phone'], '+0') : null;

    try {
        $membership = GymMembership::create([
            'code' => GymMembership::makeCode(),
            'reference' => $data['reference'],
            'gym_plan_id' => $plan->id,
            'plan_name' => $plan->name,
            'price' => $plan->price,
            'period' => $plan->period,
            'type' => $data['type'],
            'customer_name' => $data['name'],
            'customer_email' => $data['email'],
            'customer_phone' => $phone,
            'dob' => $data['dob'] ?? null,
            'status' => 'active',
            'payment_status' => 'paid',
            'starts_at' => now()->toDateString(),
            'ends_at' => now()->addMonthsNoOverflow($plan->durationMonths())->toDateString(),
            'payment_method' => data_get($body, 'data.channel'),
            'paid_at' => data_get($body, 'data.paid_at') ?? now(),
        ]);

        if ($membership->customer_email) {
            Mail::to($membership->customer_email)->send(new GymMembershipConfirmation($membership));
        }
        Notification::send(User::admins()->get(), new GymMembershipReceived($membership));

        session(['gym_success' => [
            'code' => $membership->code,
            'plan_name' => $membership->plan_name,
            'customer_name' => $membership->customer_name,
            'customer_email' => $membership->customer_email,
            'customer_phone' => $membership->customer_phone,
            'ends_at' => optional($membership->ends_at)->format('M j, Y'),
        ]]);
    } catch (Throwable $e) {
        report($e);

        return redirect()->route('gym')->with('toast', ['type' => 'error', 'message' => 'Your payment went through but we could not save your membership. Please contact us with reference: '.$data['reference']]);
    }

    return redirect()->route('gym');
})->name('gym.subscribe');

/*
|--------------------------------------------------------------------------
| Restaurant reservation flow (Paystack)
|--------------------------------------------------------------------------
*/
Route::view('restaurant', 'restaurant')->name('restaurant');

// Reserve — called via a hidden POST after a successful Paystack charge.
Route::post('restaurant/reserve', function () {
    $data = request()->validate([
        'reference' => ['required', 'string', 'max:190'],
        'area' => ['required', 'in:dining,lounge'],
        'table_id' => ['nullable', 'integer', 'exists:restaurant_tables,id'],
        'occasion' => ['nullable', 'string', 'max:120'],
        'guests' => ['required', 'integer', 'min:1', 'max:30'],
        'date' => ['required', 'date'],
        'time' => ['nullable', 'string', 'max:20'],
        'special_request' => ['nullable', 'string', 'max:1000'],
        'name' => ['required', 'string', 'max:160'],
        'email' => ['required', 'email', 'max:190'],
        'phone' => ['nullable', 'string', 'max:40'],
        'channel' => ['nullable', 'string', 'max:30'],
    ]);

    $table = ! empty($data['table_id']) ? RestaurantTable::find($data['table_id']) : null;
    $fee = (int) preg_replace('/[^0-9]/', '', cms('restaurant.reservation_fee')) ?: 10000;

    // Verify the payment with Paystack before recording anything.
    try {
        $response = Http::withToken(config('services.paystack.secret_key'))->acceptJson()
            ->get(rtrim(config('services.paystack.payment_url'), '/').'/transaction/verify/'.$data['reference']);
        $body = $response->json();

        if (! $response->ok() || data_get($body, 'data.status') !== 'success') {
            return redirect()->route('restaurant')->with('toast', [
                'type' => 'error',
                'message' => 'We could not verify your payment. If you were charged, contact us with reference: '.$data['reference'],
            ]);
        }
    } catch (Throwable $e) {
        report($e);

        return redirect()->route('restaurant')->with('toast', ['type' => 'error', 'message' => 'Payment verification failed. Please try again or contact us.']);
    }

    $phone = $data['phone'] ? '+234'.ltrim($data['phone'], '+0') : null;

    try {
        $reservation = RestaurantReservation::create([
            'code' => RestaurantReservation::makeCode(),
            'reference' => $data['reference'],
            'area' => $data['area'],
            'restaurant_table_id' => $table?->id,
            'table_label' => $table?->name,
            'occasion' => $data['occasion'] ?? null,
            'guests' => $data['guests'],
            'reserved_date' => $data['date'],
            'reserved_time' => $data['time'] ?? null,
            'special_request' => $data['special_request'] ?? null,
            'customer_name' => $data['name'],
            'customer_email' => $data['email'],
            'customer_phone' => $phone,
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'fee' => $fee,
            'payment_method' => data_get($body, 'data.channel'),
            'paid_at' => data_get($body, 'data.paid_at') ?? now(),
        ]);

        if ($reservation->customer_email) {
            Mail::to($reservation->customer_email)->send(new RestaurantReservationConfirmation($reservation));
        }
        Notification::send(User::admins()->get(), new RestaurantReservationReceived($reservation));

        session(['restaurant_success' => [
            'code' => $reservation->code,
            'area_label' => $reservation->areaLabel(),
            'occasion' => $reservation->occasion ?: '—',
            'guests_label' => $reservation->guestsLabel(),
            'date' => optional($reservation->reserved_date)->format('M j, Y'),
            'time' => $reservation->timeLabel(),
            'fee_label' => $reservation->feeLabel(),
            'customer_name' => $reservation->customer_name,
            'customer_email' => $reservation->customer_email,
            'customer_phone' => $reservation->customer_phone,
        ]]);
    } catch (Throwable $e) {
        report($e);

        return redirect()->route('restaurant')->with('toast', ['type' => 'error', 'message' => 'Your payment went through but we could not save your reservation. Please contact us with reference: '.$data['reference']]);
    }

    return redirect()->route('restaurant');
})->name('restaurant.reserve');

/*
|--------------------------------------------------------------------------
| Cinema ticket flow (Paystack)
|--------------------------------------------------------------------------
*/
Route::view('cinema', 'cinema')->name('cinema');

// Browse all movies (now showing + coming soon) with type + genre filters.
Route::get('cinema/movies', function () {
    return view('cinema-all', [
        'movies' => Movie::active()->ordered()->get(),
    ]);
})->name('cinema.movies');

Route::get('cinema/{movie:slug}', function (Movie $movie) {
    abort_unless($movie->is_active, 404);

    return view('cinema-movie', [
        'movie' => $movie,
        'snacks' => CinemaSnack::active()->ordered()->get(),
        'related' => Movie::active()->where('id', '!=', $movie->id)->where('classification', 'now_showing')->ordered()->take(5)->get(),
    ]);
})->name('cinema.movie');

// Private rooms already taken for a showing (booked or actively held) — drives the room picker.
Route::get('cinema/{movie:slug}/seats', function (Movie $movie) {
    $date = request('date');
    $time = request('time');

    if (! $date || ! $time) {
        return response()->json(['taken' => []]);
    }

    return response()->json(['taken' => CinemaSeatHold::takenSeats($movie->id, $date, $time)]);
})->name('cinema.seats');

// Hold the chosen private room before payment so nobody else can take it.
Route::post('cinema/hold', function () {
    $data = request()->validate([
        'movie' => ['required', 'string', 'exists:movies,slug'],
        'date' => ['required', 'date'],
        'time' => ['required', 'string', 'max:30'],
        'room' => ['required', 'string', 'in:'.implode(',', Movie::ROOMS)],
        'token' => ['required', 'string', 'max:80'],
    ]);

    $movie = Movie::where('slug', $data['movie'])->first();
    // The holds table locks one row per (movie,date,time,room) — exactly one booking per room.
    $result = CinemaSeatHold::placeHold($movie->id, $data['date'], $data['time'], [$data['room']], $data['token']);

    return response()->json($result);
})->name('cinema.hold');

// Release a hold when the guest abandons checkout.
Route::post('cinema/release', function () {
    $data = request()->validate(['token' => ['required', 'string', 'max:80']]);
    CinemaSeatHold::release($data['token']);

    return response()->json(['ok' => true]);
})->name('cinema.release');

// Book — called via a hidden POST after a successful Paystack charge.
Route::post('cinema/book', function () {
    $data = request()->validate([
        'reference' => ['required', 'string', 'max:190'],
        'movie' => ['required', 'string', 'exists:movies,slug'],
        'date' => ['required', 'date'],
        'time' => ['required', 'string', 'max:30'],
        'room' => ['required', 'string', 'in:'.implode(',', Movie::ROOMS)],
        'guests' => ['required', 'integer', 'min:1', 'max:'.Movie::SEATS_PER_ROOM],
        'snacks' => ['nullable', 'string', 'max:4000'],
        'name' => ['required', 'string', 'max:160'],
        'email' => ['required', 'email', 'max:190'],
        'phone' => ['nullable', 'string', 'max:40'],
        'channel' => ['nullable', 'string', 'max:30'],
    ]);

    $movie = Movie::where('slug', $data['movie'])->first();
    $snacks = collect(json_decode($data['snacks'] ?? '[]', true) ?: [])
        ->map(fn ($s) => ['name' => (string) ($s['name'] ?? ''), 'qty' => (int) ($s['qty'] ?? 0), 'price' => (int) ($s['price'] ?? 0)])
        ->filter(fn ($s) => $s['qty'] > 0)->values()->all();

    $roomPrice = (int) $movie->room_price;      // flat price for the whole private room
    $snacksTotal = collect($snacks)->sum(fn ($s) => $s['qty'] * $s['price']);
    $subtotal = $roomPrice + $snacksTotal;
    $fee = 2000;                                // convenience fee (mirrors spa)
    $taxes = (int) round($subtotal * 0.075);    // VAT 7.5%
    $amount = $subtotal + $fee + $taxes;

    // Verify the payment with Paystack before recording anything.
    try {
        $response = Http::withToken(config('services.paystack.secret_key'))->acceptJson()
            ->get(rtrim(config('services.paystack.payment_url'), '/').'/transaction/verify/'.$data['reference']);
        $body = $response->json();

        if (! $response->ok() || data_get($body, 'data.status') !== 'success') {
            return redirect()->route('cinema.movie', $movie)->with('toast', [
                'type' => 'error',
                'message' => 'We could not verify your payment. If you were charged, contact us with reference: '.$data['reference'],
            ]);
        }
    } catch (Throwable $e) {
        report($e);

        return redirect()->route('cinema.movie', $movie)->with('toast', ['type' => 'error', 'message' => 'Payment verification failed. Please try again or contact us.']);
    }

    $phone = $data['phone'] ? '+234'.ltrim($data['phone'], '+0') : null;

    try {
        $booking = CinemaBooking::create([
            'code' => CinemaBooking::makeCode(),
            'reference' => $data['reference'],
            'movie_id' => $movie->id,
            'movie_title' => $movie->title,
            'show_date' => $data['date'],
            'show_time' => $data['time'],
            'room' => $data['room'],
            'guests' => $data['guests'],
            'seats' => [],
            'snacks' => $snacks,
            'subtotal' => $subtotal,
            'fee' => $fee,
            'taxes' => $taxes,
            'amount' => $amount,
            'customer_name' => $data['name'],
            'customer_email' => $data['email'],
            'customer_phone' => $phone,
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'payment_method' => data_get($body, 'data.channel'),
            'paid_at' => data_get($body, 'data.paid_at') ?? now(),
        ]);

        // Lock the private room to this booking (the hold was placed before payment).
        // If the room was lost in the rare hold-expiry race, refund + notify rather
        // than double-book it.
        {
            $secured = CinemaSeatHold::claimForBooking($movie->id, $data['date'], $data['time'], [$data['room']], $data['reference'], $booking);

            if (! in_array($data['room'], $secured, true)) {
                $booking->update(['status' => 'cancelled', 'payment_status' => 'refunded']);
                if ($booking->customer_email) {
                    try {
                        Mail::to($booking->customer_email)->send(new \App\Mail\CinemaBookingCancelled($booking->fresh()));
                    } catch (Throwable $e) {
                        report($e);
                    }
                }

                return redirect()->route('cinema.movie', $movie)->with('toast', [
                    'type' => 'error',
                    'message' => 'Sorry, '.$data['room'].' was just booked for that showtime. Your payment will be refunded within 24 hours.',
                ]);
            }
        }

        if ($booking->customer_email) {
            Mail::to($booking->customer_email)->send(new CinemaBookingConfirmation($booking));
        }
        Notification::send(User::admins()->get(), new CinemaBookingReceived($booking));

        session(['cinema_success' => [
            'code' => $booking->code,
            'movie_title' => $booking->movie_title,
            'date' => optional($booking->show_date)->format('M j, Y'),
            'time' => $booking->show_time,
            'room' => $booking->roomLabel(),
            'guests' => $booking->guestsLabel(),
            'snacks' => $booking->snacksLabel(),
            'fee' => $booking->feeLabel(),
            'taxes' => $booking->taxesLabel(),
            'total' => $booking->amountLabel(),
            'poster' => $movie->posterUrl(),
            'customer_name' => $booking->customer_name,
            'customer_email' => $booking->customer_email,
            'customer_phone' => $booking->customer_phone,
        ]]);
    } catch (Throwable $e) {
        report($e);

        return redirect()->route('cinema.movie', $movie)->with('toast', ['type' => 'error', 'message' => 'Your payment went through but we could not save your booking. Please contact us with reference: '.$data['reference']]);
    }

    return redirect()->route('cinema.movie', $movie);
})->name('cinema.book');

Route::view('contact-us', 'contact')->name('contact');
Route::post('contact-us', function () {
    $data = request()->validate([
        'first_name' => ['required', 'string', 'max:120'],
        'last_name' => ['required', 'string', 'max:120'],
        'email' => ['required', 'email', 'max:190'],
        'phone' => ['nullable', 'string', 'max:40'],
        'message' => ['nullable', 'string', 'max:5000'],
    ]);

    // Persist the enquiry so it appears in Admin → Website CMS → Messages,
    // and notify the admin bell.
    try {
        $message = ContactMessage::create($data + ['status' => 'new']);
        Notification::send(User::admins()->get(), new MessageReceived($message));
    } catch (Throwable $e) {
        report($e);
    }

    try {
        $recipient = config('mail.contact_to', config('mail.from.address'));
        Mail::to($recipient)->send(new ContactEnquiry($data));

        // Automated acknowledgement to the guest.
        Mail::to($data['email'])->send(new ContactAcknowledgement($data));
    } catch (Throwable $e) {
        report($e);
        // The message is already saved; surface a soft notice but don't lose it.
    }

    return back()->with('toast', [
        'type' => 'success',
        'message' => 'Thanks '.$data['first_name'].'! Your message has been received — we will get back to you shortly.',
    ]);
})->name('contact.submit');

Route::prefix('admin')->name('admin.')->group(function () {
    // Guest-only authentication screens.
    Route::middleware('guest')->group(function () {
        Route::get('login', Login::class)->name('login');
        Route::get('forgot-password', ForgotPassword::class)->name('password.request');
        Route::get('verify-code', VerifyCode::class)->name('password.verify');
        Route::get('set-password', SetNewPassword::class)->name('password.set');
        Route::get('password-reset-success', ResetSuccess::class)->name('password.success');
    });

    // Authenticated admin portal.
    Route::middleware(['auth', 'admin'])->group(function () {
        Route::view('/', 'admin.dashboard')->name('dashboard');
        Route::post('logout', LogoutController::class)->name('logout');

        // Apartments — Rooms (full-page Livewire components)
        Route::get('apartments/rooms', Index::class)->name('rooms.index');
        Route::get('apartments/rooms/create', Edit::class)->name('rooms.create');
        Route::get('apartments/rooms/{room}/edit', Edit::class)->name('rooms.edit');
        Route::get('apartments/rooms/{room}/calendar', \App\Livewire\Admin\Rooms\Calendar::class)->name('rooms.calendar');

        // Apartments — Bookings
        Route::get('apartments/bookings', App\Livewire\Admin\Bookings\Index::class)->name('bookings.index');
        Route::get('apartments/bookings/{booking}', App\Livewire\Admin\Bookings\Show::class)->name('bookings.show');

        // Website CMS — page hub + per-page editor + contact messages
        Route::get('website-cms', App\Livewire\Admin\Cms\Index::class)->name('cms.index');
        Route::get('website-cms/page/{page}', App\Livewire\Admin\Cms\Edit::class)->name('cms.edit');
        Route::get('website-cms/messages', App\Livewire\Admin\Messages\Index::class)->name('messages.index');

        // Airport Pickups — Vehicles (fleet shown on the website pick-up popup)
        Route::get('airport-pickups/vehicles', App\Livewire\Admin\Vehicles\Index::class)->name('vehicles.index');
        Route::get('airport-pickups/bookings', App\Livewire\Admin\Vehicles\Bookings::class)->name('vehicles.bookings');

        // Spa & Wellness — services fleet + reservations
        Route::get('spa-wellness/services', App\Livewire\Admin\Spa\Services::class)->name('spa.services');
        Route::get('spa-wellness/bookings', App\Livewire\Admin\Spa\Bookings::class)->name('spa.bookings');

        // Gym & Fitness — plans + memberships
        Route::get('gym/plans', App\Livewire\Admin\Gym\Plans::class)->name('gym.plans');
        Route::get('gym/memberships', App\Livewire\Admin\Gym\Memberships::class)->name('gym.memberships');
        Route::get('gym/memberships/{membership}/receipt', function (GymMembership $membership) {
            return view('gym-receipt', ['m' => $membership]);
        })->name('gym.receipt');

        // Restaurant — tables, lounge + reservations
        Route::get('restaurant/tables', App\Livewire\Admin\Restaurant\Tables::class)->name('restaurant.tables');
        Route::get('restaurant/lounge', App\Livewire\Admin\Restaurant\Lounge::class)->name('restaurant.lounge');
        Route::get('restaurant/reservations', App\Livewire\Admin\Restaurant\Reservations::class)->name('restaurant.reservations');
        Route::get('restaurant/reservations/{reservation}/receipt', function (RestaurantReservation $reservation) {
            return view('restaurant-receipt', ['r' => $reservation]);
        })->name('restaurant.receipt');

        // Cinema — movies, snacks + ticket bookings
        Route::get('cinema/movies', App\Livewire\Admin\Cinema\Movies::class)->name('cinema.movies');
        Route::get('cinema/snacks', App\Livewire\Admin\Cinema\Snacks::class)->name('cinema.snacks');
        Route::get('cinema/bookings', App\Livewire\Admin\Cinema\Bookings::class)->name('cinema.bookings');
        Route::get('cinema/bookings/{booking}/receipt', function (CinemaBooking $booking) {
            return view('cinema-receipt', ['b' => $booking]);
        })->name('cinema.receipt');

        // Payment — transactions captured from checkout
        Route::get('payment', App\Livewire\Admin\Payment\Index::class)->name('payment.index');

        // Access Control — TTLock smart locks (lock mapping + passcode dashboard)
        Route::get('access-control/ttlock', App\Livewire\Admin\Ttlock\Locks::class)->name('ttlock.locks');
    });
});
