<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SosAlert;
use App\Models\User;
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

        return response()->json(['data' => [
            'officer' => [
                'name' => $officer->name,
                'role' => 'Security Office',
            ],
            'stats' => [
                'active_incidents' => $incidents->where('status', SosAlert::ACTIVE)->count(),
                'visitors_today' => 0,
                'verified_passes' => 0,
            ],
            'incidents' => $incidents->map->toSecurityArray()->values(),
            // Populated when the Visitor Pass feature is built; empty for now so
            // the dashboard renders its truthful "no visitors yet" state.
            'visitors' => [],
            'pass_requests' => [],
        ]]);
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
