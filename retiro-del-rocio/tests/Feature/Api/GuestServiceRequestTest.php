<?php

namespace Tests\Feature\Api;

use App\Models\Booking;
use App\Models\Device;
use App\Models\DeviceType;
use App\Models\HousekeepingNotification;
use App\Models\HousekeepingRequest;
use App\Models\HousekeepingRequestType;
use App\Models\ReceptionNotification;
use App\Models\Room;
use App\Models\RoomUnit;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Housekeeping / maintenance requests raised from a guest's in-room tablet's
 * Service Request quick-service tile.
 */
class GuestServiceRequestTest extends TestCase
{
    use RefreshDatabase;

    private RoomUnit $unit;

    private Device $device;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $room = Room::create([
            'name' => 'Alba Suite',
            'slug' => 'alba-suite-sr',
            'type' => 'suite',
            'price' => 150000,
        ]);

        $this->unit = RoomUnit::create([
            'room_id' => $room->id,
            'number' => '101',
            'status' => 'occupied',
        ]);

        $booking = Booking::create([
            'reference' => 'BK-'.Str::upper(Str::random(8)),
            'customer_name' => 'Daniel Ubani',
            'room_id' => $room->id,
            'room_name' => $room->name,
            'room_unit_id' => $this->unit->id,
            'check_in' => now()->toDateString(),
            'check_out' => now()->addDays(3)->toDateString(),
            'nights' => 3,
            'guests' => 2,
            'amount' => 450000,
            'status' => 'checked_in',
            'checked_in_at' => now(),
        ]);

        $this->unit->update(['booking_id' => $booking->id]);

        $type = DeviceType::create(['name' => 'Tablet', 'slug' => 'tablet-sr']);

        $this->device = Device::create([
            'device_uuid' => (string) Str::uuid(),
            'device_code' => 'TAB-SR-101',
            'device_name' => 'Room 101 Tablet',
            'device_type_id' => $type->id,
            'mode' => 'guest',
            'room_id' => $room->id,
            'room_unit_id' => $this->unit->id,
            'status' => 'online',
            'is_provisioned' => true,
        ]);

        $this->token = $this->device->createToken('tablet')->plainTextToken;
    }

    public function test_a_guest_raises_a_housekeeping_request(): void
    {
        $data = $this->withToken($this->token)
            ->postJson('/api/v1/service-requests', [
                'category' => 'housekeeping',
                'type' => 'towels',
                'notes' => 'Two extra towels please',
            ])
            ->assertCreated()
            ->assertJsonPath('data.category', 'housekeeping')
            ->assertJsonPath('data.title', 'Towels')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.is_open', true)
            ->json('data');

        $this->assertDatabaseHas('housekeeping_requests', [
            'id' => $data['id'],
            'room_unit_id' => $this->unit->id,
            'type' => 'towels',
            'notes' => 'Two extra towels please',
        ]);
    }

    public function test_a_guest_raises_a_maintenance_fault(): void
    {
        $data = $this->withToken($this->token)
            ->postJson('/api/v1/service-requests', [
                'category' => 'maintenance',
                'title' => 'AC not cooling',
                'description' => 'Room feels warm all night',
                'priority' => 'high',
            ])
            ->assertCreated()
            ->assertJsonPath('data.category', 'maintenance')
            ->assertJsonPath('data.title', 'AC not cooling')
            ->assertJsonPath('data.status', 'new')
            ->assertJsonPath('data.is_open', true)
            ->json('data');

        $this->assertDatabaseHas('work_orders', [
            'id' => $data['id'],
            'room_unit_id' => $this->unit->id,
            'title' => 'AC not cooling',
            'priority' => 'high',
            'reported_by' => 'Daniel Ubani',
        ]);
    }

    public function test_a_maintenance_fault_defaults_to_medium_priority(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/service-requests', [
                'category' => 'maintenance',
                'title' => 'Flickering light',
            ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Flickering light');

        $this->assertDatabaseHas('work_orders', ['title' => 'Flickering light', 'priority' => 'medium']);
    }

    public function test_the_category_is_required(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/service-requests', ['type' => 'towels'])
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('category');
    }

    public function test_an_invalid_housekeeping_type_is_rejected(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/service-requests', ['category' => 'housekeeping', 'type' => 'champagne'])
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('type');
    }

    public function test_the_reception_raised_checkout_inspection_type_cannot_be_self_submitted(): void
    {
        // The catalog seeds this type as guest_visible=false — a guest must
        // never be able to POST it themselves and short-circuit reception's
        // own inspection gate.
        $this->withToken($this->token)
            ->postJson('/api/v1/service-requests', [
                'category' => 'housekeeping',
                'type' => HousekeepingRequest::CHECKOUT_INSPECTION,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('type');
    }

    public function test_the_types_endpoint_lists_only_guest_visible_active_types_in_order(): void
    {
        HousekeepingRequestType::where('key', 'other')->update(['is_active' => false]);

        $data = $this->withToken($this->token)
            ->getJson('/api/v1/service-requests/types')
            ->assertOk()
            ->json('data');

        $keys = array_column($data, 'key');
        $this->assertSame(['towels', 'amenities', 'dnd', 'make_up_room'], $keys);
        $this->assertNotContains('checkout_inspection', $keys, 'staff-only type must never reach the guest');
    }

    public function test_a_freshly_added_catalog_type_can_immediately_be_submitted(): void
    {
        HousekeepingRequestType::create([
            'key' => 'extra_pillow',
            'label' => 'Extra Pillow',
            'icon' => 'bed',
            'guest_visible' => true,
            'is_active' => true,
            'sort_order' => 99,
        ]);
        HousekeepingRequest::flushTypeCatalogCache();

        $this->withToken($this->token)
            ->postJson('/api/v1/service-requests', ['category' => 'housekeeping', 'type' => 'extra_pillow'])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Extra Pillow');
    }

    public function test_a_maintenance_fault_requires_a_title(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/service-requests', ['category' => 'maintenance'])
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('title');
    }

    public function test_raising_a_request_notifies_the_front_desk(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/service-requests', ['category' => 'housekeeping', 'type' => 'dnd'])
            ->assertCreated();

        $this->assertSame(1, ReceptionNotification::count());
        $this->assertSame('Housekeeping Request', ReceptionNotification::first()->title);
    }

    public function test_a_housekeeping_request_also_notifies_housekeeping(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/service-requests', ['category' => 'housekeeping', 'type' => 'towels'])
            ->assertCreated();

        $this->assertSame(1, HousekeepingNotification::count());
        $this->assertSame('New Housekeeping Request', HousekeepingNotification::first()->title);
    }

    public function test_a_maintenance_fault_does_not_notify_housekeeping(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/service-requests', ['category' => 'maintenance', 'title' => 'Broken lamp'])
            ->assertCreated();

        $this->assertSame(0, HousekeepingNotification::count());
        // Reception still hears about it either way.
        $this->assertSame(1, ReceptionNotification::count());
    }

    public function test_the_history_combines_both_categories_newest_first(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/service-requests', ['category' => 'housekeeping', 'type' => 'towels'])
            ->assertCreated();
        // Both requests otherwise land in the same wall-clock second under a
        // fast test run, which makes "newest first" ambiguous between tables
        // that don't share an id sequence — back-date the first one so the
        // ordering this test asserts is actually deterministic.
        HousekeepingRequest::first()->forceFill(['created_at' => now()->subMinute()])->save();

        $this->withToken($this->token)
            ->postJson('/api/v1/service-requests', ['category' => 'maintenance', 'title' => 'Broken lamp'])
            ->assertCreated();

        $this->withToken($this->token)
            ->getJson('/api/v1/service-requests')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.category', 'maintenance')
            ->assertJsonPath('data.0.title', 'Broken lamp')
            ->assertJsonPath('data.1.category', 'housekeeping')
            ->assertJsonPath('data.1.title', 'Towels');
    }

    public function test_the_history_is_empty_between_stays(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/service-requests', ['category' => 'housekeeping', 'type' => 'towels'])
            ->assertCreated();

        $this->unit->booking->update(['status' => 'checked_out', 'checked_out_at' => now()]);

        $this->withToken($this->token)
            ->getJson('/api/v1/service-requests')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->withToken($this->token)
            ->postJson('/api/v1/service-requests', ['category' => 'housekeeping', 'type' => 'towels'])
            ->assertStatus(409);
    }

    public function test_the_next_guest_never_sees_the_previous_guests_maintenance_history(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/service-requests', ['category' => 'maintenance', 'title' => 'Broken lamp'])
            ->assertCreated();

        $this->unit->booking->update(['status' => 'checked_out', 'checked_out_at' => now()]);

        $next = Booking::create([
            'reference' => 'BK-'.Str::upper(Str::random(8)),
            'customer_name' => 'Zara Ahmed',
            'room_id' => $this->unit->room_id,
            'room_name' => 'Alba Suite',
            'room_unit_id' => $this->unit->id,
            'check_in' => now()->toDateString(),
            'check_out' => now()->addDays(2)->toDateString(),
            'nights' => 2, 'guests' => 1, 'amount' => 300000,
            'status' => 'checked_in', 'checked_in_at' => now(),
        ]);
        $this->unit->update(['booking_id' => $next->id]);

        $this->withToken($this->token)
            ->getJson('/api/v1/service-requests')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_an_unauthenticated_tablet_cannot_raise_a_request(): void
    {
        $this->postJson('/api/v1/service-requests', ['category' => 'housekeeping', 'type' => 'towels'])
            ->assertUnauthorized();
    }

    public function test_the_request_lands_on_the_relevant_staff_boards(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/service-requests', ['category' => 'housekeeping', 'type' => 'towels'])
            ->assertCreated();
        $this->withToken($this->token)
            ->postJson('/api/v1/service-requests', ['category' => 'maintenance', 'title' => 'Broken lamp'])
            ->assertCreated();

        $this->assertSame(1, HousekeepingRequest::count());
        $this->assertSame(1, WorkOrder::count());
    }
}
