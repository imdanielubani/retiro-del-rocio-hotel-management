<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\RoomUnit;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Maintenance tablet. Authenticated by the technician's staff JWT — the JWT
 * middleware binds the user, and every endpoint re-checks the `maintenance`
 * role so a token from another station can't work this one.
 *
 * Hotel-wide, like reception and security: maintenance watches every room and
 * asset, not one.
 */
class MaintenanceController extends Controller
{
    /**
     * GET /maintenance/overview — the dashboard in one call: headline
     * counters and the open orders that need a technician's attention next
     * (urgent first, then oldest).
     */
    public function overview(Request $request): JsonResponse
    {
        $this->technician($request);

        $open = WorkOrder::with(['roomUnit', 'assignedTo'])
            ->where('status', '!=', WorkOrder::DONE)
            ->get();

        $priorityRank = ['urgent' => 0, 'high' => 1, 'medium' => 2, 'low' => 3];
        $sorted = $open->sortBy(fn (WorkOrder $o) => $priorityRank[$o->priority] ?? 4)->values();

        return response()->json(['data' => [
            'stats' => [
                'new' => $open->where('status', WorkOrder::NEW)->count(),
                'in_progress' => $open->where('status', WorkOrder::IN_PROGRESS)->count(),
                'urgent' => $open->where('priority', 'urgent')->count(),
                'completed_today' => WorkOrder::where('status', WorkOrder::DONE)
                    ->whereDate('completed_at', today())->count(),
            ],
            'work_orders' => $sorted->take(20)->map->toMaintenanceArray()->values(),
        ]]);
    }

    /**
     * GET /maintenance/work-orders — every order, newest first. Optional
     * `?status=` and `?priority=` narrow the board.
     */
    public function workOrders(Request $request): JsonResponse
    {
        $this->technician($request);

        $status = $request->query('status');
        $priority = $request->query('priority');

        $orders = WorkOrder::with(['roomUnit', 'assignedTo'])
            ->when(
                in_array($status, [WorkOrder::NEW, WorkOrder::ACCEPTED, WorkOrder::IN_PROGRESS, WorkOrder::DONE], true),
                fn ($q) => $q->where('status', $status),
            )
            ->when(
                in_array($priority, WorkOrder::PRIORITIES, true),
                fn ($q) => $q->where('priority', $priority),
            )
            ->latest('id')
            ->limit(150)
            ->get();

        return response()->json(['data' => $orders->map->toMaintenanceArray()->values()]);
    }

    /**
     * POST /maintenance/work-orders — report a fault, optionally against a
     * room; otherwise `asset_label` names what's broken.
     */
    public function createWorkOrder(Request $request): JsonResponse
    {
        $this->technician($request);

        $data = $request->validate([
            'room_unit_id' => ['nullable', 'integer', 'exists:room_units,id'],
            'asset_label' => ['nullable', 'string', 'max:120'],
            'title' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'priority' => ['nullable', 'in:'.implode(',', WorkOrder::PRIORITIES)],
            'reported_by' => ['nullable', 'string', 'max:80'],
        ]);

        $order = WorkOrder::create([
            'room_unit_id' => $data['room_unit_id'] ?? null,
            'asset_label' => $data['asset_label'] ?? null,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'priority' => $data['priority'] ?? 'medium',
            'reported_by' => $data['reported_by'] ?? null,
        ]);

        return response()->json(['data' => $order->fresh(['roomUnit', 'assignedTo'])->toMaintenanceArray()], 201);
    }

    /** POST /maintenance/work-orders/{order}/accept — take the order. */
    public function acceptWorkOrder(Request $request, WorkOrder $workOrder): JsonResponse
    {
        $technician = $this->technician($request);

        $workOrder->accept($technician);

        return response()->json(['data' => $workOrder->fresh(['roomUnit', 'assignedTo'])->toMaintenanceArray()]);
    }

    /** POST /maintenance/work-orders/{order}/start — begin work. */
    public function startWorkOrder(Request $request, WorkOrder $workOrder): JsonResponse
    {
        $this->technician($request);

        $workOrder->start();

        return response()->json(['data' => $workOrder->fresh(['roomUnit', 'assignedTo'])->toMaintenanceArray()]);
    }

    /** POST /maintenance/work-orders/{order}/complete — close the order. */
    public function completeWorkOrder(Request $request, WorkOrder $workOrder): JsonResponse
    {
        $this->technician($request);

        $workOrder->complete();

        return response()->json(['data' => $workOrder->fresh(['roomUnit', 'assignedTo'])->toMaintenanceArray()]);
    }

    /**
     * GET /maintenance/rooms — the room picker for "report a fault against a
     * room" (reuses the same read model housekeeping uses for its grid, minus
     * the housekeeping-specific fields the picker doesn't need).
     */
    public function rooms(Request $request): JsonResponse
    {
        $this->technician($request);

        $search = trim((string) $request->query('search', ''));

        $rooms = RoomUnit::with('room')
            ->when($search !== '', fn ($q) => $q->where('number', 'like', "%{$search}%"))
            ->orderBy('number')
            ->limit(100)
            ->get();

        return response()->json(['data' => $rooms->map(fn (RoomUnit $u) => [
            'id' => $u->id,
            'number' => $u->number,
            'room_name' => $u->room?->name,
        ])->values()]);
    }

    /** The authenticated staffer, who must hold the `maintenance` role. */
    private function technician(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User, 401, 'Unauthenticated.');
        abort_unless($user->hasRole('maintenance'), 403, 'Maintenance access only.');

        return $user;
    }
}
