<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SecurityNotification;
use App\Models\SosAlert;
use App\Models\User;
use App\Models\VisitorPass;
use App\Services\VisitorPassProvisioner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Security tablet dashboard. Authenticated by the officer's staff JWT (issued at
 * staff-login on a `security` station) — the JWT middleware binds the user, and
 * every endpoint re-checks the `security` role so a stray token from another
 * station cannot read incidents.
 *
 * Unlike the guest SOS endpoints, these are hotel-wide: security watches every
 * room, not one.
 */
class SecurityController extends Controller
{
    /**
     * GET /security/overview — everything the dashboard renders in one call:
     * the headline counters, the open SOS incidents and (once the Visitor Pass
     * feature lands) today's visitors and pending pass requests.
     */
    public function overview(Request $request): JsonResponse
    {
        $officer = $this->officer($request);

        $incidents = SosAlert::open()
            ->with('acknowledgedBy:id,name')
            ->latest('raised_at')
            ->get();

        // "Visitors Today" means people who came through the gate today, so it
        // keys off when they were VERIFIED — not when their pass happened to be
        // issued. A visitor invited last night who arrives this morning belongs
        // on today's list; the pass they were issued last week does not.
        $verified = VisitorPass::where('status', VisitorPass::VERIFIED)
            ->whereDate('verified_at', today())
            ->orderByDesc('verified_at')
            ->limit(50)
            ->get();

        // "Visitor Pass Requests" is a feed of the gate's recent work, not a
        // pending-only queue — the design (Figma 257:1336) shows verified entries
        // sitting among the pending ones, and an officer who has just worked
        // through the queue should still see what they admitted rather than a
        // column that empties itself.
        //
        // Pending is scoped to the last day rather than to the calendar day: a
        // pass issued at 11pm is still walking up to the gate at 00:05. Anything
        // the visitor never used is closed out by the reconcile command.
        $pending = VisitorPass::where('status', VisitorPass::PENDING)
            ->where('created_at', '>=', now()->subDay())
            ->latest('id')
            ->limit(25)
            ->get();

        // Both columns render the same population, for different jobs:
        //
        //  • "Visitors Today" (Figma 257:1256) answers *who is here* — arrivals
        //    first, then those still expected, who show as "Not Inside". Sending
        //    only verified passes is why that list looked empty and why the
        //    "Not Inside" state in the design never appeared: a visitor who has
        //    not arrived yet is exactly the one that pill is describing.
        //  • "Visitor Pass Requests" (Figma 257:1336) answers *what is left to
        //    do* — still-pending first, then what has already been admitted.
        $visitors = $verified->concat($pending)->values();
        $requests = $pending->concat($verified)->values();

        return response()->json(['data' => [
            'officer' => [
                'name' => $officer->name,
                'role' => 'Security Office',
            ],
            'stats' => [
                'active_incidents' => $incidents->where('status', SosAlert::ACTIVE)->count(),
                // Everything the gate has dealt with today: those already in,
                // plus those still expected. Counting only passes *issued* today
                // read as zero on a quiet morning after a busy night.
                'visitors_today' => $verified->count() + $pending->count(),
                'verified_passes' => $verified->count(),
            ],
            'incidents' => $incidents->map->toSecurityArray()->values(),
            'visitors' => $visitors->map->toVisitorRowArray()->values(),
            'pass_requests' => $requests->map->toPassRequestArray()->values(),
        ]]);
    }

    /**
     * GET /security/visitors — today's visitor passes hotel-wide, newest first.
     *
     * Drives the Visitor Verification list. Security watches every room's passes,
     * not one, so this is not scoped to a device.
     */
    public function visitors(Request $request): JsonResponse
    {
        $this->officer($request);

        $passes = VisitorPass::whereDate('created_at', today())
            ->latest('id')
            ->limit(100)
            ->get();

        return response()->json(['data' => $passes->map->toSecurityArray()->values()]);
    }

    /**
     * POST /security/visitors/verify — look up a visitor by the 6-digit code they
     * quote at the gate. Returns the matching *open* pass, or a clear 404: a spent
     * code reads differently from one that was never issued.
     */
    public function verifyCode(Request $request): JsonResponse
    {
        $this->officer($request);

        $data = $request->validate(['code' => ['required', 'string', 'max:12']]);
        $code = preg_replace('/\D/', '', $data['code']);

        // The officer may key either the online (TTLock) or the offline code.
        $pass = VisitorPass::open()
            ->where(fn ($q) => $q->where('code', $code)->orWhere('online_code', $code))
            ->latest('id')
            ->first();
        if ($pass) {
            return response()->json(['data' => $pass->toSecurityArray()]);
        }

        $spent = VisitorPass::where('code', $code)->orWhere('online_code', $code)->exists();

        return response()->json([
            'message' => $spent ? 'This code has already been used.' : 'Code not recognised.',
        ], 404);
    }

    /**
     * POST /security/visitors/{pass}/grant — admit the visitor. The pass moves to
     * verified and the officer is recorded; a second tap is a no-op.
     */
    public function grant(Request $request, VisitorPass $pass): JsonResponse
    {
        $officer = $this->officer($request);

        // Admitted at the manned gate. Delete the online code too, so a pass
        // verified on the keypad can't also be walked in on later at the lock.
        if ($pass->grant($officer, 'keypad')) {
            app(VisitorPassProvisioner::class)->revoke($pass);
            $pass->forceFill(['ttlock_status' => $pass->keyboard_pwd_id ? 'deleted' : $pass->ttlock_status])->save();
        }

        return response()->json(['data' => $pass->fresh()->toSecurityArray()]);
    }

    /**
     * POST /security/visitors/{pass}/deny — turn the visitor away. Their codes
     * stop working immediately; a second tap is a no-op.
     */
    public function deny(Request $request, VisitorPass $pass): JsonResponse
    {
        $officer = $this->officer($request);

        if ($pass->deny($officer)) {
            app(VisitorPassProvisioner::class)->revoke($pass);
            $pass->forceFill(['ttlock_status' => $pass->keyboard_pwd_id ? 'deleted' : $pass->ttlock_status])->save();
        }

        return response()->json(['data' => $pass->fresh()->toSecurityArray()]);
    }

    /**
     * POST /security/visitors/{pass}/exit — the officer marks a verified visitor
     * as having left the property. Only valid for a visitor who is actually
     * inside (verified, not already marked as exited); a second tap is a no-op.
     */
    public function exit(Request $request, VisitorPass $pass): JsonResponse
    {
        $this->officer($request);

        abort_unless($pass->status === VisitorPass::VERIFIED, 409, 'This visitor was never checked in.');

        $pass->markExited();

        return response()->json(['data' => $pass->fresh()->toSecurityArray()]);
    }

    /**
     * GET /security/incidents — the SOS Alert Logs: every emergency ever raised,
     * most recent first, with its full timeline. Optionally narrowed by
     * `?status=` (active | acknowledged | resolved | cancelled | open).
     */
    public function incidents(Request $request): JsonResponse
    {
        $this->officer($request);

        $status = $request->query('status');

        $incidents = SosAlert::query()
            ->with(['acknowledgedBy:id,name', 'resolvedBy:id,name'])
            ->when($status === 'open', fn ($q) => $q->open())
            ->when(
                in_array($status, [SosAlert::ACTIVE, SosAlert::ACKNOWLEDGED, SosAlert::RESOLVED, SosAlert::CANCELLED], true),
                fn ($q) => $q->where('status', $status),
            )
            ->latest('raised_at')
            ->limit(100)
            ->get();

        return response()->json(['data' => $incidents->map->toLogArray()->values()]);
    }

    /**
     * POST /security/incidents/{alert}/respond — the officer acknowledges an
     * incoming SOS: the guest tablet flips to "Security is on their way", and the
     * alert moves off the ACTIVE counter.
     */
    public function respond(Request $request, SosAlert $alert): JsonResponse
    {
        $officer = $this->officer($request);

        // Already acknowledged/resolved by another officer — return current state
        // rather than error, so two tablets tapping at once both settle cleanly.
        $alert->acknowledge($officer);

        return response()->json(['data' => $alert->fresh()->toSecurityArray()]);
    }

    /**
     * POST /security/incidents/{alert}/resolve — the incident is dealt with. Once
     * resolved it stays resolved; a later tap is a no-op.
     */
    public function resolve(Request $request, SosAlert $alert): JsonResponse
    {
        $officer = $this->officer($request);

        $alert->resolve($officer);

        return response()->json(['data' => $alert->fresh()->toSecurityArray()]);
    }

    /* ---------------- Notifications ---------------- */

    /**
     * GET /security/notifications — security's notification feed, newest
     * first. Today the only trigger is a guest inviting a visitor; security
     * is one shared station, so the feed (and its read state) is shared
     * across whoever is signed in, not scoped to a single officer.
     */
    public function notifications(Request $request): JsonResponse
    {
        $this->officer($request);

        $notifications = SecurityNotification::latest()->limit(100)->get();

        return response()->json(['data' => $notifications->map->toSecurityArray()->values()]);
    }

    /** POST /security/notifications/{notification}/read — mark one as read. */
    public function markNotificationRead(Request $request, int $notification): JsonResponse
    {
        $this->officer($request);

        $record = SecurityNotification::find($notification);
        abort_unless($record, 404, 'Notification not found.');

        if (! $record->read_at) {
            $record->update(['read_at' => now()]);
        }

        return response()->json(['data' => $record->toSecurityArray()]);
    }

    /** POST /security/notifications/read-all — "Mark all read". */
    public function markAllNotificationsRead(Request $request): JsonResponse
    {
        $this->officer($request);

        SecurityNotification::whereNull('read_at')->update(['read_at' => now()]);

        return response()->json(['ok' => true]);
    }

    /** The authenticated staffer, who must hold the `security` role. */
    private function officer(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User, 401, 'Unauthenticated.');
        abort_unless($user->hasRole('security'), 403, 'Security access only.');

        return $user;
    }
}
