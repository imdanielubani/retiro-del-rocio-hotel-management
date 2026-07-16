<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
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

        // Today's visitor passes, hotel-wide: verified ones fill "Visitors Today",
        // pending ones the "Visitor Pass Requests" column and the counters.
        $today = VisitorPass::whereDate('created_at', today())->latest('id')->get();
        $verified = $today->where('status', VisitorPass::VERIFIED);
        $pending = $today->where('status', VisitorPass::PENDING);

        return response()->json(['data' => [
            'officer' => [
                'name' => $officer->name,
                'role' => 'Security Office',
            ],
            'stats' => [
                'active_incidents' => $incidents->where('status', SosAlert::ACTIVE)->count(),
                'visitors_today' => $today->count(),
                'verified_passes' => $verified->count(),
            ],
            'incidents' => $incidents->map->toSecurityArray()->values(),
            'visitors' => $verified->map->toVisitorRowArray()->values(),
            'pass_requests' => $pending->map->toPassRequestArray()->values(),
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

    /** The authenticated staffer, who must hold the `security` role. */
    private function officer(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User, 401, 'Unauthenticated.');
        abort_unless($user->hasRole('security'), 403, 'Security access only.');

        return $user;
    }
}
