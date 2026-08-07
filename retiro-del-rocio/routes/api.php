<?php

use App\Http\Controllers\Api\V1\AppConfigController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DeviceController;
use App\Http\Controllers\Api\V1\GuestChatController;
use App\Http\Controllers\Api\V1\GuestIntercomCallController;
use App\Http\Controllers\Api\V1\GuestServiceRequestController;
use App\Http\Controllers\Api\V1\HousekeepingController;
use App\Http\Controllers\Api\V1\MaintenanceController;
use App\Http\Controllers\Api\V1\PasswordResetController;
use App\Http\Controllers\Api\V1\ReceptionChatController;
use App\Http\Controllers\Api\V1\ReceptionController;
use App\Http\Controllers\Api\V1\ReceptionIntercomCallController;
use App\Http\Controllers\Api\V1\SecurityController;
use App\Http\Controllers\Api\V1\SosController;
use App\Http\Controllers\Api\V1\StaffChatController;
use App\Http\Controllers\Api\V1\StaffIntercomCallController;
use App\Http\Controllers\Api\V1\TabletController;
use App\Http\Controllers\Api\V1\VisitorPassController;
use App\Http\Middleware\TouchLastSeen;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API routes — consumed by the Flutter tablet/mobile apps.
|--------------------------------------------------------------------------
| Registered under the `/api` prefix and the `api` middleware group in
| bootstrap/app.php. Token auth via Laravel Sanctum (auth:sanctum) — a
| SEPARATE guard from the web session used by the website + admin dashboard,
| so nothing here affects those. Versioned under /v1 from day one.
*/

// On its own sub-domain (API_DOMAIN) the API lives at /v1; locally, with no
// domain configured, it falls back to /api/v1. The `apiPrefix` is set to '' in
// bootstrap/app.php so this file owns the full path.
$apiDomain = config('app.api_domain');

$api = Route::prefix($apiDomain ? 'v1' : 'api/v1');

if ($apiDomain) {
    $api->domain($apiDomain);
}

$api->group(function () {
    // --- Public ---
    Route::get('health', fn () => response()->json([
        'status' => 'ok',
        'app' => config('app.name'),
        'time' => now()->toIso8601String(),
    ]))->name('api.v1.health');

    // App bootstrap config (onboarding video URL, etc.) — consumed before a
    // device is provisioned, so it stays public.
    Route::get('app/config', [AppConfigController::class, 'show'])
        ->name('api.v1.app.config');

    // Staff session check — authenticated by the staff JWT (not the device token).
    Route::get('staff/me', function (Request $request) {
        $user = $request->user();

        return response()->json(['data' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $user->getRoleNames()->values(),
        ]]);
    })->middleware('jwt')->name('api.v1.staff.me');

    Route::post('auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:6,1')
        ->name('api.v1.auth.login');

    // OTP password reset (tablet app): request code → verify → set new password.
    Route::post('auth/forgot-password', [PasswordResetController::class, 'sendOtp'])
        ->middleware('throttle:5,1')->name('api.v1.auth.forgot-password');
    Route::post('auth/verify-otp', [PasswordResetController::class, 'verifyOtp'])
        ->middleware('throttle:10,1')->name('api.v1.auth.verify-otp');
    Route::post('auth/reset-password', [PasswordResetController::class, 'reset'])
        ->middleware('throttle:10,1')->name('api.v1.auth.reset-password');

    // Tablet provisioning — public, authenticated by the QR's provision token.
    Route::post('tablets/provision', [TabletController::class, 'provision'])
        ->middleware('throttle:12,1')
        ->name('api.v1.tablets.provision');

    // --- Authenticated (Sanctum bearer token) ---
    Route::middleware(['auth:sanctum', TouchLastSeen::class])->group(function () {
        Route::get('auth/me', [AuthController::class, 'me'])->name('api.v1.auth.me');
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('api.v1.auth.logout');

        // The tablet re-checking its own pairing at launch. Declared before the
        // `tablets/{code}` lookup below so "me" is not read as a device code.
        Route::get('tablets/me', [TabletController::class, 'me'])->name('api.v1.tablets.me');

        // Device self-reporting (called by the tablet with its device token).
        Route::post('tablets/heartbeat', [TabletController::class, 'heartbeat'])->name('api.v1.tablets.heartbeat');
        Route::post('tablets/sync', [TabletController::class, 'sync'])->name('api.v1.tablets.sync');

        // The tablet's live room occupancy + checked-in guest (guest welcome).
        Route::get('tablets/room-status', [TabletController::class, 'roomStatus'])->name('api.v1.tablets.room-status');

        // The checked-in guest's My Stay screen + self-service stay extension.
        Route::get('tablets/my-stay', [TabletController::class, 'myStay'])->name('api.v1.tablets.my-stay');
        // Extend the stay, either charged straight to the room or paid
        // directly via Paystack (price + open the charge, then verify it).
        Route::post('tablets/extend-stay/charge-to-room', [TabletController::class, 'chargeExtensionToRoom'])
            ->middleware('throttle:20,1')->name('api.v1.tablets.extend-stay.charge-to-room');
        Route::post('tablets/extend-stay/initialize', [TabletController::class, 'initializeExtension'])
            ->middleware('throttle:20,1')->name('api.v1.tablets.extend-stay.initialize');
        Route::post('tablets/extend-stay', [TabletController::class, 'extendStay'])
            ->middleware('throttle:20,1')->name('api.v1.tablets.extend-stay');

        // Spa & Wellness — browse services and book a session, either charged
        // straight to the room or paid directly via Paystack.
        Route::get('tablets/spa/services', [TabletController::class, 'spaServices'])->name('api.v1.tablets.spa.services');
        Route::get('tablets/spa/appointments', [TabletController::class, 'spaAppointments'])->name('api.v1.tablets.spa.appointments');
        Route::post('tablets/spa/book', [TabletController::class, 'bookSpaToRoom'])
            ->middleware('throttle:20,1')->name('api.v1.tablets.spa.book');
        Route::post('tablets/spa/initialize', [TabletController::class, 'initializeSpaBooking'])
            ->middleware('throttle:20,1')->name('api.v1.tablets.spa.initialize');
        Route::post('tablets/spa/confirm', [TabletController::class, 'confirmSpaBooking'])
            ->middleware('throttle:20,1')->name('api.v1.tablets.spa.confirm');

        // Cinema — browse movies + snacks and book a private room, either
        // charged straight to the room or paid directly via Paystack.
        Route::get('tablets/cinema/movies', [TabletController::class, 'cinemaCatalog'])->name('api.v1.tablets.cinema.movies');
        Route::get('tablets/cinema/bookings', [TabletController::class, 'cinemaBookings'])->name('api.v1.tablets.cinema.bookings');
        Route::get('tablets/cinema/{movie:slug}/availability', [TabletController::class, 'cinemaRoomAvailability'])
            ->name('api.v1.tablets.cinema.availability');
        Route::post('tablets/cinema/book', [TabletController::class, 'bookCinemaToRoom'])
            ->middleware('throttle:20,1')->name('api.v1.tablets.cinema.book');
        Route::post('tablets/cinema/initialize', [TabletController::class, 'initializeCinemaBooking'])
            ->middleware('throttle:20,1')->name('api.v1.tablets.cinema.initialize');
        Route::post('tablets/cinema/confirm', [TabletController::class, 'confirmCinemaBooking'])
            ->middleware('throttle:20,1')->name('api.v1.tablets.cinema.confirm');

        // My Bills — the guest's itemised folio, with an optional Paystack
        // pre-settlement of the outstanding balance ahead of checkout.
        Route::get('tablets/my-bills', [TabletController::class, 'myBills'])->name('api.v1.tablets.my-bills');
        Route::post('tablets/my-bills/initialize', [TabletController::class, 'initializeBillPayment'])
            ->middleware('throttle:20,1')->name('api.v1.tablets.my-bills.initialize');
        Route::post('tablets/my-bills/confirm', [TabletController::class, 'confirmBillPayment'])
            ->middleware('throttle:20,1')->name('api.v1.tablets.my-bills.confirm');

        // The checked-in guest's Notifications feed.
        Route::get('tablets/notifications', [TabletController::class, 'notifications'])
            ->name('api.v1.tablets.notifications');
        Route::post('tablets/notifications/read-all', [TabletController::class, 'markAllNotificationsRead'])
            ->name('api.v1.tablets.notifications.read-all');
        Route::post('tablets/notifications/{notification}/read', [TabletController::class, 'markNotificationRead'])
            ->whereNumber('notification')->name('api.v1.tablets.notifications.read');

        // Emergency SOS from a guest's in-room tablet. Raising is throttled — a
        // panicking guest may hammer the button — but the endpoint is idempotent,
        // so a burst still yields exactly one alert for the room.
        Route::get('sos/active', [SosController::class, 'active'])->name('api.v1.sos.active');
        Route::post('sos', [SosController::class, 'store'])
            ->middleware('throttle:20,1')->name('api.v1.sos.store');
        Route::post('sos/{alert}/cancel', [SosController::class, 'cancel'])
            ->middleware('throttle:20,1')->name('api.v1.sos.cancel');

        // Visitor passes issued from a guest's in-room tablet — the guest invites
        // a visitor and the server mints a unique entry code. Scoped to the room.
        Route::get('visitor-passes', [VisitorPassController::class, 'index'])
            ->name('api.v1.visitor-passes.index');
        Route::post('visitor-passes', [VisitorPassController::class, 'store'])
            ->middleware('throttle:30,1')->name('api.v1.visitor-passes.store');

        // Service requests raised from a guest's in-room tablet — housekeeping
        // asks (towels, amenities, DND, make-up room) and maintenance faults,
        // both landing straight on the relevant staff tablet's own board.
        Route::get('service-requests', [GuestServiceRequestController::class, 'index'])
            ->name('api.v1.service-requests.index');
        Route::get('service-requests/types', [GuestServiceRequestController::class, 'types'])
            ->name('api.v1.service-requests.types');
        Route::post('service-requests', [GuestServiceRequestController::class, 'store'])
            ->middleware('throttle:20,1')->name('api.v1.service-requests.store');

        // Concierge Chat — a guest's in-room tablet messaging the front desk.
        Route::get('chat/messages', [GuestChatController::class, 'index'])
            ->name('api.v1.chat.messages.index');
        Route::post('chat/messages', [GuestChatController::class, 'store'])
            ->middleware('throttle:30,1')->name('api.v1.chat.messages.store');
        Route::post('chat/typing', [GuestChatController::class, 'typing'])
            ->middleware('throttle:60,1')->name('api.v1.chat.typing');

        // Intercom — voice-call signalling with Reception from a guest's
        // in-room tablet. No real audio: ringing/answer/decline/hang-up only.
        Route::post('intercom/calls', [GuestIntercomCallController::class, 'store'])
            ->middleware('throttle:20,1')->name('api.v1.intercom.calls.store');
        Route::get('intercom/calls/current', [GuestIntercomCallController::class, 'current'])
            ->name('api.v1.intercom.calls.current');
        Route::post('intercom/calls/{call}/answer', [GuestIntercomCallController::class, 'answer'])
            ->middleware('throttle:20,1')->name('api.v1.intercom.calls.answer');
        Route::post('intercom/calls/{call}/decline', [GuestIntercomCallController::class, 'decline'])
            ->middleware('throttle:20,1')->name('api.v1.intercom.calls.decline');
        Route::post('intercom/calls/{call}/end', [GuestIntercomCallController::class, 'end'])
            ->middleware('throttle:20,1')->name('api.v1.intercom.calls.end');

        // Staff sign-in on a staff tablet (device token identifies the station).
        Route::post('tablets/staff-login', [TabletController::class, 'staffLogin'])
            ->middleware('throttle:10,1')->name('api.v1.tablets.staff-login');

        // Staff device management (user token + DevicePolicy).
        Route::get('devices', [DeviceController::class, 'index'])->name('api.v1.devices.index');
        Route::get('devices/{device}', [DeviceController::class, 'show'])->whereNumber('device')->name('api.v1.devices.show');
        Route::get('tablets/{code}', [DeviceController::class, 'showByCode'])->name('api.v1.tablets.show');
        Route::post('tablets/restart', [TabletController::class, 'restart'])->name('api.v1.tablets.restart');
        Route::post('tablets/lock', [TabletController::class, 'lock'])->name('api.v1.tablets.lock');
        Route::post('tablets/unlock', [TabletController::class, 'unlock'])->name('api.v1.tablets.unlock');
    });

    // --- Security tablet (staff JWT, security role) ---
    // The security officer signs in on the security station, then their JWT
    // (not the device token) authorises the hotel-wide dashboard and lets them
    // acknowledge / resolve incoming SOS incidents. The role is re-checked in
    // the controller on every call.
    Route::middleware(['jwt', TouchLastSeen::class])->group(function () {
        Route::get('security/overview', [SecurityController::class, 'overview'])
            ->name('api.v1.security.overview');
        Route::get('security/incidents', [SecurityController::class, 'incidents'])
            ->name('api.v1.security.incidents');
        Route::post('security/incidents/{alert}/respond', [SecurityController::class, 'respond'])
            ->middleware('throttle:60,1')->name('api.v1.security.incidents.respond');
        Route::post('security/incidents/{alert}/resolve', [SecurityController::class, 'resolve'])
            ->middleware('throttle:60,1')->name('api.v1.security.incidents.resolve');

        // Visitor verification at the gate — list today's passes, look one up by
        // the code the visitor quotes, and admit them.
        Route::get('security/visitors', [SecurityController::class, 'visitors'])
            ->name('api.v1.security.visitors');
        Route::post('security/visitors/verify', [SecurityController::class, 'verifyCode'])
            ->middleware('throttle:60,1')->name('api.v1.security.visitors.verify');
        Route::post('security/visitors/{pass}/grant', [SecurityController::class, 'grant'])
            ->middleware('throttle:60,1')->name('api.v1.security.visitors.grant');
        Route::post('security/visitors/{pass}/deny', [SecurityController::class, 'deny'])
            ->middleware('throttle:60,1')->name('api.v1.security.visitors.deny');
        Route::post('security/visitors/{pass}/exit', [SecurityController::class, 'exit'])
            ->middleware('throttle:60,1')->name('api.v1.security.visitors.exit');

        // Security's notification feed — today, only "a guest invited a
        // visitor".
        Route::get('security/notifications', [SecurityController::class, 'notifications'])
            ->name('api.v1.security.notifications');
        Route::post('security/notifications/read-all', [SecurityController::class, 'markAllNotificationsRead'])
            ->name('api.v1.security.notifications.read-all');
        Route::post('security/notifications/{notification}/read', [SecurityController::class, 'markNotificationRead'])
            ->whereNumber('notification')->name('api.v1.security.notifications.read');

        // --- Reception tablet (staff JWT, reception role) ---
        // The receptionist signs in on the reception station; their JWT authorises
        // the front-desk dashboard and the check-in / check-out actions. The role
        // is re-checked in the controller on every call.
        Route::get('reception/overview', [ReceptionController::class, 'overview'])
            ->name('api.v1.reception.overview');
        Route::get('reception/room-status', [ReceptionController::class, 'roomStatus'])
            ->name('api.v1.reception.room-status');

        // The front desk's read-only views of the admin's guests and bookings.
        Route::get('reception/guests', [ReceptionController::class, 'guests'])
            ->name('api.v1.reception.guests');
        Route::get('reception/guests/profile', [ReceptionController::class, 'guestProfile'])
            ->name('api.v1.reception.guest-profile');
        Route::get('reception/bookings', [ReceptionController::class, 'bookings'])
            ->name('api.v1.reception.bookings');

        Route::get('reception/bookings/{booking}/rooms', [ReceptionController::class, 'roomOptions'])
            ->name('api.v1.reception.rooms');
        Route::post('reception/bookings/{booking}/check-in', [ReceptionController::class, 'checkIn'])
            ->middleware('throttle:60,1')->name('api.v1.reception.check-in');
        Route::get('reception/bookings/{booking}/departure-readiness', [ReceptionController::class, 'departureReadiness'])
            ->name('api.v1.reception.departure-readiness');
        Route::post('reception/bookings/{booking}/settle-bill', [ReceptionController::class, 'settleBill'])
            ->middleware('throttle:60,1')->name('api.v1.reception.settle-bill');
        Route::post('reception/bookings/{booking}/request-inspection', [ReceptionController::class, 'requestInspection'])
            ->middleware('throttle:60,1')->name('api.v1.reception.request-inspection');
        Route::post('reception/bookings/{booking}/check-out', [ReceptionController::class, 'checkOut'])
            ->middleware('throttle:60,1')->name('api.v1.reception.check-out');

        // Bills — every checked-in guest's outstanding room-charge balance,
        // plus one guest's itemised folio for the desk to review or settle.
        Route::get('reception/bills', [ReceptionController::class, 'bills'])
            ->name('api.v1.reception.bills');
        Route::get('reception/bookings/{booking}/bill', [ReceptionController::class, 'bookingBill'])
            ->name('api.v1.reception.booking-bill');

        // Visitor Pass — every visitor invited or arrived, read-only (gate
        // access itself stays with security).
        Route::get('reception/visitors', [ReceptionController::class, 'visitors'])
            ->name('api.v1.reception.visitors');

        // SOS incidents: hotel-wide emergencies. Reception sees the same alerts as
        // security and can acknowledge / resolve them from the front desk.
        Route::get('reception/incidents', [ReceptionController::class, 'incidents'])
            ->name('api.v1.reception.incidents');
        Route::post('reception/incidents/{alert}/respond', [ReceptionController::class, 'respond'])
            ->middleware('throttle:60,1')->name('api.v1.reception.incidents.respond');
        Route::post('reception/incidents/{alert}/resolve', [ReceptionController::class, 'resolve'])
            ->middleware('throttle:60,1')->name('api.v1.reception.incidents.resolve');

        // Vehicle pickup: the desk sees guests arriving by car and assigns a driver.
        Route::get('reception/pickups', [ReceptionController::class, 'pickups'])
            ->name('api.v1.reception.pickups');
        Route::get('reception/drivers', [ReceptionController::class, 'drivers'])
            ->name('api.v1.reception.drivers');
        Route::post('reception/bookings/{booking}/assign-driver', [ReceptionController::class, 'assignDriver'])
            ->middleware('throttle:60,1')->name('api.v1.reception.assign-driver');
        Route::post('reception/bookings/{booking}/pickup-complete', [ReceptionController::class, 'completePickup'])
            ->middleware('throttle:60,1')->name('api.v1.reception.pickup-complete');

        // Front-desk notifications — a stay extension paid, a new reservation,
        // etc. Shared across the station, same pattern as the guest tablet's
        // notifications feed.
        Route::get('reception/notifications', [ReceptionController::class, 'notifications'])
            ->name('api.v1.reception.notifications');
        Route::post('reception/notifications/read-all', [ReceptionController::class, 'markAllNotificationsRead'])
            ->name('api.v1.reception.notifications.read-all');
        Route::post('reception/notifications/{notification}/read', [ReceptionController::class, 'markNotificationRead'])
            ->whereNumber('notification')->name('api.v1.reception.notifications.read');

        // Reception's Chat screen — Concierge Chat threads with every
        // in-house guest. Reception's own staff-to-staff channels are served
        // by the shared "staff/chat/*" routes below, alongside every other
        // station's tablet.
        Route::get('reception/chat/guests', [ReceptionChatController::class, 'guestConversations'])
            ->name('api.v1.reception.chat.guests');
        Route::get('reception/chat/guests/{booking}/messages', [ReceptionChatController::class, 'guestMessages'])
            ->name('api.v1.reception.chat.guests.messages');
        Route::post('reception/chat/guests/{booking}/messages', [ReceptionChatController::class, 'sendGuestMessage'])
            ->middleware('throttle:30,1')->name('api.v1.reception.chat.guests.send');
        Route::post('reception/chat/guests/{booking}/typing', [ReceptionChatController::class, 'typingToGuest'])
            ->middleware('throttle:60,1')->name('api.v1.reception.chat.guests.typing');

        // Reception's Intercom — voice-call signalling with a checked-in
        // guest's room. No real audio: ringing/answer/decline/hang-up only.
        Route::post('reception/intercom/calls', [ReceptionIntercomCallController::class, 'store'])
            ->middleware('throttle:20,1')->name('api.v1.reception.intercom.calls.store');
        Route::get('reception/intercom/calls/current', [ReceptionIntercomCallController::class, 'current'])
            ->name('api.v1.reception.intercom.calls.current');
        Route::post('reception/intercom/calls/{call}/answer', [ReceptionIntercomCallController::class, 'answer'])
            ->middleware('throttle:20,1')->name('api.v1.reception.intercom.calls.answer');
        Route::post('reception/intercom/calls/{call}/decline', [ReceptionIntercomCallController::class, 'decline'])
            ->middleware('throttle:20,1')->name('api.v1.reception.intercom.calls.decline');
        Route::post('reception/intercom/calls/{call}/end', [ReceptionIntercomCallController::class, 'end'])
            ->middleware('throttle:20,1')->name('api.v1.reception.intercom.calls.end');

        // --- Housekeeping tablet (staff JWT, housekeeping role) ---
        // The housekeeper signs in on the housekeeping station; their JWT
        // authorises the room-status board and guest-request queue. The role
        // is re-checked in the controller on every call.
        Route::get('housekeeping/overview', [HousekeepingController::class, 'overview'])
            ->name('api.v1.housekeeping.overview');
        Route::get('housekeeping/rooms', [HousekeepingController::class, 'rooms'])
            ->name('api.v1.housekeeping.rooms');
        Route::post('housekeeping/rooms/{unit}/status', [HousekeepingController::class, 'updateRoomStatus'])
            ->middleware('throttle:60,1')->name('api.v1.housekeeping.rooms.status');
        Route::get('housekeeping/requests', [HousekeepingController::class, 'requests'])
            ->name('api.v1.housekeeping.requests');
        Route::post('housekeeping/requests', [HousekeepingController::class, 'createRequest'])
            ->middleware('throttle:30,1')->name('api.v1.housekeeping.requests.create');
        Route::post('housekeeping/requests/{housekeepingRequest}/complete', [HousekeepingController::class, 'completeRequest'])
            ->middleware('throttle:60,1')->name('api.v1.housekeeping.requests.complete');

        // Lost & Found — items a housekeeper finds while turning over a room.
        Route::get('housekeeping/lost-found', [HousekeepingController::class, 'lostFoundItems'])
            ->name('api.v1.housekeeping.lost-found');
        Route::post('housekeeping/lost-found', [HousekeepingController::class, 'createLostFoundItem'])
            ->middleware('throttle:30,1')->name('api.v1.housekeeping.lost-found.create');
        Route::post('housekeeping/lost-found/{lostFoundItem}/status', [HousekeepingController::class, 'updateLostFoundItemStatus'])
            ->middleware('throttle:60,1')->name('api.v1.housekeeping.lost-found.status');

        // A housekeeper reporting a fault they noticed while turning over a
        // room — lands on maintenance's own Work Orders board.
        Route::post('housekeeping/work-orders', [HousekeepingController::class, 'reportFault'])
            ->middleware('throttle:30,1')->name('api.v1.housekeeping.work-orders.create');

        // Housekeeping's notification feed — today, only "a guest raised a
        // housekeeping request".
        Route::get('housekeeping/notifications', [HousekeepingController::class, 'notifications'])
            ->name('api.v1.housekeeping.notifications');
        Route::post('housekeeping/notifications/read-all', [HousekeepingController::class, 'markAllNotificationsRead'])
            ->name('api.v1.housekeeping.notifications.read-all');
        Route::post('housekeeping/notifications/{notification}/read', [HousekeepingController::class, 'markNotificationRead'])
            ->whereNumber('notification')->name('api.v1.housekeeping.notifications.read');

        // --- Maintenance tablet (staff JWT, maintenance role) ---
        // The technician signs in on the maintenance station; their JWT
        // authorises the work-order board. The role is re-checked in the
        // controller on every call.
        Route::get('maintenance/overview', [MaintenanceController::class, 'overview'])
            ->name('api.v1.maintenance.overview');
        Route::get('maintenance/work-orders', [MaintenanceController::class, 'workOrders'])
            ->name('api.v1.maintenance.work-orders');
        Route::post('maintenance/work-orders', [MaintenanceController::class, 'createWorkOrder'])
            ->middleware('throttle:30,1')->name('api.v1.maintenance.work-orders.create');
        Route::get('maintenance/work-orders/{workOrder}', [MaintenanceController::class, 'workOrderDetail'])
            ->name('api.v1.maintenance.work-orders.show');
        Route::post('maintenance/work-orders/{workOrder}/attachments', [MaintenanceController::class, 'uploadAttachment'])
            ->middleware('throttle:20,1')->name('api.v1.maintenance.work-orders.attachments.create');
        Route::post('maintenance/work-orders/{workOrder}/accept', [MaintenanceController::class, 'acceptWorkOrder'])
            ->middleware('throttle:60,1')->name('api.v1.maintenance.work-orders.accept');
        Route::post('maintenance/work-orders/{workOrder}/start', [MaintenanceController::class, 'startWorkOrder'])
            ->middleware('throttle:60,1')->name('api.v1.maintenance.work-orders.start');
        Route::post('maintenance/work-orders/{workOrder}/complete', [MaintenanceController::class, 'completeWorkOrder'])
            ->middleware('throttle:60,1')->name('api.v1.maintenance.work-orders.complete');
        Route::get('maintenance/rooms', [MaintenanceController::class, 'rooms'])
            ->name('api.v1.maintenance.rooms');

        // Assets registry: the picker for "report a fault against an asset",
        // and its own tab with service history + preventive-maintenance due
        // list.
        Route::get('maintenance/assets', [MaintenanceController::class, 'assets'])
            ->name('api.v1.maintenance.assets');
        Route::post('maintenance/assets', [MaintenanceController::class, 'createAsset'])
            ->middleware('throttle:30,1')->name('api.v1.maintenance.assets.create');
        Route::get('maintenance/assets/{asset}', [MaintenanceController::class, 'assetDetail'])
            ->name('api.v1.maintenance.assets.show');
        Route::post('maintenance/assets/{asset}/mark-serviced', [MaintenanceController::class, 'markAssetServiced'])
            ->middleware('throttle:30,1')->name('api.v1.maintenance.assets.mark-serviced');

        // Requests tab: parts requests a technician raised against a work
        // order, and the fulfil/deny actions on them.
        Route::get('maintenance/parts-requests', [MaintenanceController::class, 'partsRequests'])
            ->name('api.v1.maintenance.parts-requests');
        Route::post('maintenance/work-orders/{workOrder}/parts-requests', [MaintenanceController::class, 'createPartsRequest'])
            ->middleware('throttle:30,1')->name('api.v1.maintenance.parts-requests.create');
        Route::post('maintenance/parts-requests/{partsRequest}/fulfill', [MaintenanceController::class, 'fulfillPartsRequest'])
            ->middleware('throttle:30,1')->name('api.v1.maintenance.parts-requests.fulfill');
        Route::post('maintenance/parts-requests/{partsRequest}/deny', [MaintenanceController::class, 'denyPartsRequest'])
            ->middleware('throttle:30,1')->name('api.v1.maintenance.parts-requests.deny');

        Route::post('maintenance/work-orders/{workOrder}/escalate', [MaintenanceController::class, 'escalateWorkOrder'])
            ->middleware('throttle:30,1')->name('api.v1.maintenance.work-orders.escalate');
        Route::post('maintenance/work-orders/{workOrder}/status', [MaintenanceController::class, 'updateWorkOrderStatus'])
            ->middleware('throttle:30,1')->name('api.v1.maintenance.work-orders.status');
        Route::post('maintenance/work-orders/{workOrder}/assign', [MaintenanceController::class, 'assignWorkOrder'])
            ->middleware('throttle:30,1')->name('api.v1.maintenance.work-orders.assign');
        Route::get('maintenance/technicians', [MaintenanceController::class, 'technicians'])
            ->name('api.v1.maintenance.technicians');

        Route::get('maintenance/notifications', [MaintenanceController::class, 'notifications'])
            ->name('api.v1.maintenance.notifications');
        Route::post('maintenance/notifications/read-all', [MaintenanceController::class, 'markAllNotificationsRead'])
            ->name('api.v1.maintenance.notifications.read-all');
        Route::post('maintenance/notifications/{notification}/read', [MaintenanceController::class, 'markNotificationRead'])
            ->whereNumber('notification')->name('api.v1.maintenance.notifications.read');

        // --- Staff Chat (shared by every staff tablet's Chat screen) ---
        // Reception, Housekeeping, Maintenance and Security all message each
        // other — and the Admin dashboard — through this one set of routes.
        // The caller's own station is read off their JWT, never the URL.
        Route::get('staff/chat/channels', [StaffChatController::class, 'channels'])
            ->name('api.v1.staff.chat.channels');
        Route::get('staff/chat/channels/{role}/messages', [StaffChatController::class, 'messages'])
            ->name('api.v1.staff.chat.channels.messages');
        Route::post('staff/chat/channels/{role}/messages', [StaffChatController::class, 'send'])
            ->middleware('throttle:30,1')->name('api.v1.staff.chat.channels.send');
        Route::post('staff/chat/channels/{role}/typing', [StaffChatController::class, 'typing'])
            ->middleware('throttle:60,1')->name('api.v1.staff.chat.channels.typing');

        // --- Staff Intercom (shared by every staff tablet's Intercom screen) ---
        // Voice-call signalling between any two stations. No real audio:
        // ringing/answer/decline/hang-up only, same as the guest ↔ Reception
        // calls in GuestIntercomCallController/ReceptionIntercomCallController —
        // all three write to the same intercom_calls table.
        Route::post('staff/intercom/calls', [StaffIntercomCallController::class, 'store'])
            ->middleware('throttle:20,1')->name('api.v1.staff.intercom.calls.store');
        Route::get('staff/intercom/calls/current', [StaffIntercomCallController::class, 'current'])
            ->name('api.v1.staff.intercom.calls.current');
        Route::post('staff/intercom/calls/{call}/answer', [StaffIntercomCallController::class, 'answer'])
            ->middleware('throttle:20,1')->name('api.v1.staff.intercom.calls.answer');
        Route::post('staff/intercom/calls/{call}/decline', [StaffIntercomCallController::class, 'decline'])
            ->middleware('throttle:20,1')->name('api.v1.staff.intercom.calls.decline');
        Route::post('staff/intercom/calls/{call}/end', [StaffIntercomCallController::class, 'end'])
            ->middleware('throttle:20,1')->name('api.v1.staff.intercom.calls.end');
    });
});
