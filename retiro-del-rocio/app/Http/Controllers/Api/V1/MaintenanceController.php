<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\GuestNotification;
use App\Models\MaintenanceNotification;
use App\Models\PartsRequest;
use App\Models\RoomUnit;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderAttachment;
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

        $open = WorkOrder::with(['roomUnit.room', 'assignedTo', 'asset'])
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
                'sla_breaches' => $open->filter->isSlaBreached()->count(),
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

        $orders = WorkOrder::with(['roomUnit.room', 'assignedTo', 'asset'])
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
            'asset_id' => ['nullable', 'integer', 'exists:assets,id'],
            'asset_label' => ['nullable', 'string', 'max:120'],
            'title' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'priority' => ['nullable', 'in:'.implode(',', WorkOrder::PRIORITIES)],
            'reported_by' => ['nullable', 'string', 'max:80'],
        ]);

        $order = WorkOrder::create([
            'room_unit_id' => $data['room_unit_id'] ?? null,
            'asset_id' => $data['asset_id'] ?? null,
            'asset_label' => $data['asset_label'] ?? null,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'priority' => $data['priority'] ?? 'medium',
            'reported_by' => $data['reported_by'] ?? null,
        ]);

        return response()->json(['data' => $order->fresh(['roomUnit.room', 'assignedTo', 'asset'])->toMaintenanceArray()], 201);
    }

    /** GET /maintenance/work-orders/{order} — the detail screen: the order plus its attachments. */
    public function workOrderDetail(Request $request, WorkOrder $workOrder): JsonResponse
    {
        $this->technician($request);

        $workOrder->load(['roomUnit.room', 'assignedTo', 'asset', 'attachments']);

        return response()->json(['data' => $workOrder->toMaintenanceDetailArray()]);
    }

    /**
     * POST /maintenance/work-orders/{order}/attachments — attach a photo or
     * video the technician just captured (evidence of the fault, or proof of
     * the fix).
     */
    public function uploadAttachment(Request $request, WorkOrder $workOrder): JsonResponse
    {
        $technician = $this->technician($request);

        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,mp4,mov,webm', 'max:51200'],
        ]);

        $file = $data['file'];
        $isVideo = str_starts_with((string) $file->getMimeType(), 'video/');
        $path = $file->store('work-order-attachments/'.$workOrder->id, 'public');

        WorkOrderAttachment::create([
            'work_order_id' => $workOrder->id,
            'path' => $path,
            'type' => $isVideo ? WorkOrderAttachment::VIDEO : WorkOrderAttachment::PHOTO,
            'uploaded_by' => $technician->name,
        ]);

        $workOrder->load(['roomUnit.room', 'assignedTo', 'asset', 'attachments']);

        return response()->json(['data' => $workOrder->toMaintenanceDetailArray()], 201);
    }

    /** POST /maintenance/work-orders/{order}/accept — take the order. */
    public function acceptWorkOrder(Request $request, WorkOrder $workOrder): JsonResponse
    {
        $technician = $this->technician($request);

        $workOrder->accept($technician);

        return response()->json(['data' => $workOrder->fresh(['roomUnit.room', 'assignedTo'])->toMaintenanceArray()]);
    }

    /** POST /maintenance/work-orders/{order}/start — begin work. */
    public function startWorkOrder(Request $request, WorkOrder $workOrder): JsonResponse
    {
        $this->technician($request);

        $workOrder->start();

        return response()->json(['data' => $workOrder->fresh(['roomUnit.room', 'assignedTo'])->toMaintenanceArray()]);
    }

    /** POST /maintenance/work-orders/{order}/complete — close the order. */
    public function completeWorkOrder(Request $request, WorkOrder $workOrder): JsonResponse
    {
        $this->technician($request);

        if ($workOrder->complete()) {
            $this->notifyGuestFaultCompleted($workOrder);
        }

        return response()->json(['data' => $workOrder->fresh(['roomUnit.room', 'assignedTo'])->toMaintenanceArray()]);
    }

    /** POST /maintenance/work-orders/{order}/escalate — bump it up one priority level. */
    public function escalateWorkOrder(Request $request, WorkOrder $workOrder): JsonResponse
    {
        $this->technician($request);

        $workOrder->escalate();

        return response()->json(['data' => $workOrder->fresh(['roomUnit.room', 'assignedTo', 'asset'])->toMaintenanceArray()]);
    }

    /** POST /maintenance/work-orders/{order}/status — the Status Update bottom sheet's manual override. */
    public function updateWorkOrderStatus(Request $request, WorkOrder $workOrder): JsonResponse
    {
        $this->technician($request);

        $data = $request->validate([
            'status' => ['required', 'in:'.implode(',', [WorkOrder::NEW, WorkOrder::ACCEPTED, WorkOrder::IN_PROGRESS, WorkOrder::DONE])],
        ]);

        $wasOpen = $workOrder->isOpen();
        $workOrder->setStatus($data['status']);

        if ($wasOpen && $workOrder->status === WorkOrder::DONE) {
            $this->notifyGuestFaultCompleted($workOrder);
        }

        return response()->json(['data' => $workOrder->fresh(['roomUnit.room', 'assignedTo', 'asset'])->toMaintenanceArray()]);
    }

    /** POST /maintenance/work-orders/{order}/assign — the Assign Technician bottom sheet. */
    public function assignWorkOrder(Request $request, WorkOrder $workOrder): JsonResponse
    {
        $this->technician($request);

        $data = $request->validate(['technician_id' => ['required', 'integer', 'exists:users,id']]);

        $technician = User::find($data['technician_id']);
        abort_unless($technician?->hasRole('maintenance'), 422, 'That user is not a maintenance technician.');

        $workOrder->update(['assigned_to' => $technician->id]);

        return response()->json(['data' => $workOrder->fresh(['roomUnit.room', 'assignedTo', 'asset'])->toMaintenanceArray()]);
    }

    /** GET /maintenance/technicians — the Assign Technician bottom sheet's picker. */
    public function technicians(Request $request): JsonResponse
    {
        $this->technician($request);

        $technicians = User::role('maintenance')->where('status', 'active')->orderBy('name')->get(['id', 'name']);

        return response()->json(['data' => $technicians->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name])->values()]);
    }

    /**
     * Push a live signal to the guest's own tablet the moment their fault is
     * closed, over the room's existing realtime channel, so their Service
     * Request history updates in a couple of seconds rather than waiting on
     * its own poll. A fault reported by staff (no `booking_id`) has no guest
     * to notify.
     */
    private function notifyGuestFaultCompleted(WorkOrder $workOrder): void
    {
        $booking = $workOrder->booking()->first();
        $unit = $workOrder->roomUnit()->first();

        if (! $booking || ! $unit) {
            return;
        }

        GuestNotification::notify(
            $booking,
            $unit,
            'maintenance',
            'Maintenance Request Completed',
            'Your '.$workOrder->title.' request has been completed.',
        );
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

    /**
     * GET /maintenance/assets — the Assets tab list. Optional `?search=`
     * (name), `?category=`, and `?due=1` (preventive-maintenance due/overdue
     * only).
     */
    public function assets(Request $request): JsonResponse
    {
        $this->technician($request);

        $search = trim((string) $request->query('search', ''));
        $category = trim((string) $request->query('category', ''));
        $dueOnly = $request->boolean('due');

        $assets = Asset::with('roomUnit')
            ->when($search !== '', fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->when($category !== '', fn ($q) => $q->where('category', $category))
            ->orderBy('name')
            ->limit(200)
            ->get();

        $rows = $dueOnly ? $assets->filter->isDueForService()->values() : $assets;

        return response()->json(['data' => $rows->map->toMaintenanceArray()->values()]);
    }

    /**
     * POST /maintenance/assets — register a new asset for the picker and
     * service-history/PM tracking.
     */
    public function createAsset(Request $request): JsonResponse
    {
        $this->technician($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:60'],
            'room_unit_id' => ['nullable', 'integer', 'exists:room_units,id'],
            'location_label' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'service_interval_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
        ]);

        $asset = Asset::create($data);

        return response()->json(['data' => $asset->fresh('roomUnit')->toMaintenanceArray()], 201);
    }

    /**
     * GET /maintenance/assets/{asset} — the asset's detail screen: its
     * profile plus every work order ever raised against it, newest first.
     */
    public function assetDetail(Request $request, Asset $asset): JsonResponse
    {
        $this->technician($request);

        $asset->load(['roomUnit', 'workOrders' => fn ($q) => $q->with(['roomUnit.room', 'assignedTo'])->latest('id')->limit(50)]);

        return response()->json(['data' => $asset->toMaintenanceDetailArray()]);
    }

    /**
     * POST /maintenance/assets/{asset}/mark-serviced — a technician logs that
     * an asset was just serviced, restarting its preventive-maintenance
     * interval from now.
     */
    public function markAssetServiced(Request $request, Asset $asset): JsonResponse
    {
        $this->technician($request);

        $asset->update(['last_serviced_at' => now()]);

        return response()->json(['data' => $asset->fresh('roomUnit')->toMaintenanceArray()]);
    }

    /**
     * GET /maintenance/parts-requests — the Requests tab list, newest first.
     * Optional `?status=`.
     */
    public function partsRequests(Request $request): JsonResponse
    {
        $this->technician($request);

        $status = $request->query('status');

        $requests = PartsRequest::with('workOrder.roomUnit.room', 'workOrder.asset')
            ->when(
                in_array($status, [PartsRequest::PENDING, PartsRequest::FULFILLED, PartsRequest::DENIED], true),
                fn ($q) => $q->where('status', $status),
            )
            ->latest('id')
            ->limit(150)
            ->get();

        return response()->json(['data' => $requests->map->toMaintenanceArray()->values()]);
    }

    /**
     * POST /maintenance/work-orders/{order}/parts-requests — a technician
     * asks for a part against an order.
     */
    public function createPartsRequest(Request $request, WorkOrder $workOrder): JsonResponse
    {
        $technician = $this->technician($request);

        $data = $request->validate([
            'part_name' => ['required', 'string', 'max:120'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:999'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $partsRequest = PartsRequest::create([
            'work_order_id' => $workOrder->id,
            'part_name' => $data['part_name'],
            'quantity' => $data['quantity'] ?? 1,
            'note' => $data['note'] ?? null,
            'requested_by' => $technician->name,
        ]);

        return response()->json(['data' => $partsRequest->fresh('workOrder')->toMaintenanceArray()], 201);
    }

    /** POST /maintenance/parts-requests/{partsRequest}/fulfill */
    public function fulfillPartsRequest(Request $request, PartsRequest $partsRequest): JsonResponse
    {
        $this->technician($request);

        $partsRequest->fulfill();

        return response()->json(['data' => $partsRequest->fresh('workOrder')->toMaintenanceArray()]);
    }

    /** POST /maintenance/parts-requests/{partsRequest}/deny */
    public function denyPartsRequest(Request $request, PartsRequest $partsRequest): JsonResponse
    {
        $this->technician($request);

        $partsRequest->deny();

        return response()->json(['data' => $partsRequest->fresh('workOrder')->toMaintenanceArray()]);
    }

    /** GET /maintenance/notifications — the notification feed, newest first. */
    public function notifications(Request $request): JsonResponse
    {
        $this->technician($request);

        $notifications = MaintenanceNotification::with('workOrder.roomUnit.room', 'workOrder.asset')
            ->latest()->limit(100)->get();

        return response()->json(['data' => $notifications->map->toMaintenanceArray()->values()]);
    }

    /** POST /maintenance/notifications/{notification}/read — mark one as read. */
    public function markNotificationRead(Request $request, int $notification): JsonResponse
    {
        $this->technician($request);

        $record = MaintenanceNotification::find($notification);
        abort_unless($record, 404, 'Notification not found.');

        if (! $record->read_at) {
            $record->update(['read_at' => now()]);
        }

        return response()->json(['data' => $record->toMaintenanceArray()]);
    }

    /** POST /maintenance/notifications/read-all — "Mark all read". */
    public function markAllNotificationsRead(Request $request): JsonResponse
    {
        $this->technician($request);

        MaintenanceNotification::whereNull('read_at')->update(['read_at' => now()]);

        return response()->json(['ok' => true]);
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
