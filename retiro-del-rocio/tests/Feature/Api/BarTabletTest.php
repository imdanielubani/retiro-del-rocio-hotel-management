<?php

namespace Tests\Feature\Api;

use App\Models\Booking;
use App\Models\Device;
use App\Models\DeviceType;
use App\Models\DiningOrder;
use App\Models\MenuItem;
use App\Models\Room;
use App\Models\RoomUnit;
use App\Models\User;
use App\Services\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The Bar Tablet: the drink order board, the waiter's POS (open a tab, ring
 * up drinks, close/settle), age verification, and its shared plumbing with
 * the guest tablet's Place Order flow and the Bar & Lounge admin dashboard —
 * all three read/write the exact same DiningOrder rows.
 */
class BarTabletTest extends TestCase
{
    use RefreshDatabase;

    private function bartenderToken(string $name = 'Ada Bartender'): string
    {
        Role::findOrCreate('bar', 'web');
        $user = User::factory()->create(['status' => 'active', 'name' => $name]);
        $user->assignRole('bar');

        return app(JwtService::class)->issue(['sub' => $user->id])['token'];
    }

    private function drink(array $overrides = []): MenuItem
    {
        return MenuItem::create(array_merge([
            'name' => 'Fresh Chapman',
            'slug' => 'chapman-'.Str::random(6),
            'category' => 'drinks',
            'price' => 3500,
            'is_active' => true,
            'is_alcoholic' => false,
            'sort_order' => 1,
        ], $overrides));
    }

    private function food(array $overrides = []): MenuItem
    {
        return MenuItem::create(array_merge([
            'name' => 'Jollof Rice',
            'slug' => 'jollof-'.Str::random(6),
            'category' => 'mains',
            'price' => 5000,
            'is_active' => true,
            'is_alcoholic' => false,
            'sort_order' => 1,
        ], $overrides));
    }

    /** A guest device token + active booking, for placing a guest-tablet drink order. */
    private function guestToken(): string
    {
        $room = Room::create([
            'name' => 'Alba Suite', 'slug' => 'alba-suite-bar-'.Str::random(6),
            'type' => 'suite', 'price' => 7500, 'guests' => 4,
        ]);
        $unit = RoomUnit::create(['room_id' => $room->id, 'number' => (string) random_int(100, 999), 'status' => 'occupied']);
        $booking = Booking::create([
            'reference' => 'BK-'.Str::upper(Str::random(8)),
            'customer_name' => 'Grace Hopper', 'customer_email' => 'grace@example.test',
            'room_id' => $room->id, 'room_name' => $room->name, 'room_unit_id' => $unit->id,
            'check_in' => now()->subDay()->toDateString(), 'check_out' => now()->addDays(3)->toDateString(),
            'nights' => 4, 'guests' => 2, 'amount' => 30000, 'status' => 'checked_in', 'checked_in_at' => now()->subDay(),
        ]);
        $unit->update(['booking_id' => $booking->id]);

        $type = DeviceType::create(['name' => 'Tablet', 'slug' => 'tablet-bar-'.Str::random(6)]);
        $device = Device::create([
            'device_uuid' => (string) Str::uuid(), 'device_code' => 'TAB-BAR-'.Str::random(4),
            'device_name' => 'Room Tablet', 'device_type_id' => $type->id, 'mode' => 'guest',
            'room_id' => $room->id, 'room_unit_id' => $unit->id, 'status' => 'online', 'is_provisioned' => true,
        ]);

        return $device->createToken('tablet')->plainTextToken;
    }

    public function test_a_non_bar_user_is_rejected(): void
    {
        Role::findOrCreate('housekeeping', 'web');
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('housekeeping');
        $token = app(JwtService::class)->issue(['sub' => $user->id])['token'];

        $this->withToken($token)->getJson('/api/v1/bar/overview')->assertForbidden();
    }

    public function test_a_waiter_can_open_a_tab_and_ring_up_a_drink_priced_from_the_live_catalogue(): void
    {
        $drink = $this->drink(['price' => 3500]);

        $open = $this->withToken($this->bartenderToken())
            ->postJson('/api/v1/bar/tabs', ['table_label' => 'Table 4', 'guest_name' => 'Walk-in'])
            ->assertCreated()
            ->json('data');

        $this->assertSame('open', $open['status']);

        // Client sends a tampered price — the server must ignore it and use the live catalogue price.
        $this->withToken($this->bartenderToken())
            ->postJson("/api/v1/bar/tabs/{$open['id']}/orders", [
                'items' => [['menu_item_id' => $drink->id, 'qty' => 2, 'price' => 1]],
            ])
            ->assertCreated()
            ->assertJsonPath('data.orders.0.total_label', '₦8,525'); // (3500*2) + 525 VAT + 1000 service fee

        $this->assertDatabaseHas('dining_orders', ['bar_tab_id' => $open['id'], 'subtotal' => 7000, 'vat' => 525, 'total' => 8525]);
    }

    public function test_voiding_an_item_recomputes_the_order_and_tab_totals(): void
    {
        $drinkA = $this->drink(['name' => 'Chapman', 'price' => 3500]);
        $drinkB = $this->drink(['name' => 'House Wine', 'price' => 6000]);
        $token = $this->bartenderToken();

        $tab = $this->withToken($token)->postJson('/api/v1/bar/tabs', [])->json('data');
        $order = $this->withToken($token)->postJson("/api/v1/bar/tabs/{$tab['id']}/orders", [
            'items' => [
                ['menu_item_id' => $drinkA->id, 'qty' => 1],
                ['menu_item_id' => $drinkB->id, 'qty' => 1],
            ],
        ])->json('data.orders.0');

        $this->withToken($token)
            ->postJson("/api/v1/bar/orders/{$order['id']}/void-item", ['item_index' => 0, 'reason' => 'Wrong drink'])
            ->assertOk()
            ->assertJsonPath('data.total_label', '₦7,450'); // 6000 + 450 VAT + 1000 fee, chapman voided

        $tabAfter = $this->withToken($token)->getJson("/api/v1/bar/tabs/{$tab['id']}")->json('data');
        $this->assertSame('₦7,450', $tabAfter['total_label']);
    }

    public function test_closing_a_tab_settles_it_and_marks_every_order_paid(): void
    {
        $drink = $this->drink();
        $token = $this->bartenderToken();

        $tab = $this->withToken($token)->postJson('/api/v1/bar/tabs', [])->json('data');
        $this->withToken($token)->postJson("/api/v1/bar/tabs/{$tab['id']}/orders", [
            'items' => [['menu_item_id' => $drink->id, 'qty' => 1]],
        ])->assertCreated();

        $this->withToken($token)
            ->postJson("/api/v1/bar/tabs/{$tab['id']}/close", ['payment_method' => 'cash'])
            ->assertOk()
            ->assertJsonPath('data.status', 'settled');

        $order = DiningOrder::where('bar_tab_id', $tab['id'])->firstOrFail();
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('cash', $order->payment_method);
    }

    public function test_a_guest_tablet_drink_order_and_a_pos_order_both_appear_on_the_live_board(): void
    {
        $drink = $this->drink();
        $barToken = $this->bartenderToken();
        $guestToken = $this->guestToken();

        $this->withToken($guestToken)->postJson('/api/v1/tablets/dining/book', [
            'items' => [['menu_item_id' => $drink->id, 'qty' => 1]],
        ])->assertCreated();

        $tab = $this->withToken($barToken)->postJson('/api/v1/bar/tabs', [])->json('data');
        $this->withToken($barToken)->postJson("/api/v1/bar/tabs/{$tab['id']}/orders", [
            'items' => [['menu_item_id' => $drink->id, 'qty' => 1]],
        ])->assertCreated();

        $board = $this->withToken($barToken)->getJson('/api/v1/bar/orders')->json('data');
        $this->assertCount(2, $board);
        $sources = collect($board)->pluck('source')->sort()->values()->all();
        $this->assertSame(['guest_tablet', 'pos'], $sources);

        // The exact same scope the admin Bar & Lounge Orders screen uses.
        $this->assertSame(2, DiningOrder::forBarLounge()->count());
    }

    public function test_serving_an_alcoholic_order_requires_age_verification_first(): void
    {
        $wine = $this->drink(['name' => 'House Red Wine', 'is_alcoholic' => true]);
        $token = $this->bartenderToken();

        $tab = $this->withToken($token)->postJson('/api/v1/bar/tabs', [])->json('data');
        $order = $this->withToken($token)->postJson("/api/v1/bar/tabs/{$tab['id']}/orders", [
            'items' => [['menu_item_id' => $wine->id, 'qty' => 1]],
        ])->json('data.orders.0');

        $this->withToken($token)
            ->postJson("/api/v1/bar/orders/{$order['id']}/serve")
            ->assertStatus(422);

        $this->withToken($token)->postJson("/api/v1/bar/orders/{$order['id']}/verify-age")->assertOk();

        $this->withToken($token)
            ->postJson("/api/v1/bar/orders/{$order['id']}/serve")
            ->assertOk()
            ->assertJsonPath('data.status', 'delivered');
    }

    public function test_a_non_alcoholic_order_can_be_served_without_verification(): void
    {
        $soda = $this->drink(['is_alcoholic' => false]);
        $token = $this->bartenderToken();

        $tab = $this->withToken($token)->postJson('/api/v1/bar/tabs', [])->json('data');
        $order = $this->withToken($token)->postJson("/api/v1/bar/tabs/{$tab['id']}/orders", [
            'items' => [['menu_item_id' => $soda->id, 'qty' => 1]],
        ])->json('data.orders.0');

        $this->withToken($token)
            ->postJson("/api/v1/bar/orders/{$order['id']}/serve")
            ->assertOk()
            ->assertJsonPath('data.status', 'delivered');
    }

    public function test_assigning_a_non_bar_user_to_an_order_is_rejected(): void
    {
        $drink = $this->drink();
        $token = $this->bartenderToken();

        $tab = $this->withToken($token)->postJson('/api/v1/bar/tabs', [])->json('data');
        $order = $this->withToken($token)->postJson("/api/v1/bar/tabs/{$tab['id']}/orders", [
            'items' => [['menu_item_id' => $drink->id, 'qty' => 1]],
        ])->json('data.orders.0');

        $outsider = User::factory()->create(['status' => 'active']);

        $this->withToken($token)
            ->postJson("/api/v1/bar/orders/{$order['id']}/assign", ['bartender_id' => $outsider->id])
            ->assertStatus(422);
    }

    public function test_assigning_a_bar_staff_member_to_an_order_succeeds(): void
    {
        $drink = $this->drink();
        $token = $this->bartenderToken('Opener');

        Role::findOrCreate('bar', 'web');
        $otherBartender = User::factory()->create(['status' => 'active', 'name' => 'Second Shift']);
        $otherBartender->assignRole('bar');

        $tab = $this->withToken($token)->postJson('/api/v1/bar/tabs', [])->json('data');
        $order = $this->withToken($token)->postJson("/api/v1/bar/tabs/{$tab['id']}/orders", [
            'items' => [['menu_item_id' => $drink->id, 'qty' => 1]],
        ])->json('data.orders.0');

        $this->withToken($token)
            ->postJson("/api/v1/bar/orders/{$order['id']}/assign", ['bartender_id' => $otherBartender->id])
            ->assertOk()
            ->assertJsonPath('data.assigned_to_name', 'Second Shift');
    }

    public function test_a_new_drink_order_creates_a_bar_notification(): void
    {
        $drink = $this->drink();
        $token = $this->bartenderToken();

        $tab = $this->withToken($token)->postJson('/api/v1/bar/tabs', [])->json('data');
        $this->withToken($token)->postJson("/api/v1/bar/tabs/{$tab['id']}/orders", [
            'items' => [['menu_item_id' => $drink->id, 'qty' => 1]],
        ])->assertCreated();

        $this->withToken($token)
            ->getJson('/api/v1/bar/notifications')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.category', 'new_order');
    }

    public function test_toggling_vip_flags_the_tab(): void
    {
        $token = $this->bartenderToken();

        $tab = $this->withToken($token)->postJson('/api/v1/bar/tabs', ['is_vip' => false])->json('data');
        $this->assertFalse($tab['is_vip']);

        $this->withToken($token)
            ->postJson("/api/v1/bar/tabs/{$tab['id']}/vip")
            ->assertOk()
            ->assertJsonPath('data.is_vip', true);
    }

    public function test_staff_can_toggle_drink_availability_from_the_bar_menu_screen(): void
    {
        $drink = $this->drink(['is_active' => true]);
        $token = $this->bartenderToken();

        $this->withToken($token)
            ->postJson("/api/v1/bar/menu/{$drink->id}/toggle")
            ->assertOk()
            ->assertJsonPath('data.category', 'drinks');

        $this->assertFalse($drink->fresh()->is_active);
    }

    public function test_the_bar_pos_menu_includes_kitchen_food_items_for_the_waiter_to_ring_up(): void
    {
        $this->drink(['name' => 'Fresh Chapman']);
        $this->food(['name' => 'Jollof Rice']);
        $token = $this->bartenderToken();

        $names = $this->withToken($token)->getJson('/api/v1/bar/menu')
            ->assertOk()
            ->json('data.*.name');

        $this->assertContains('Fresh Chapman', $names);
        $this->assertContains('Jollof Rice', $names);
    }

    public function test_a_waiter_can_ring_up_a_food_item_and_it_flows_to_the_kitchen(): void
    {
        $food = $this->food(['price' => 5000]);
        $token = $this->bartenderToken();

        $tab = $this->withToken($token)->postJson('/api/v1/bar/tabs', [])->json('data');
        $this->withToken($token)->postJson("/api/v1/bar/tabs/{$tab['id']}/orders", [
            'items' => [['menu_item_id' => $food->id, 'qty' => 1]],
        ])->assertCreated();

        $order = DiningOrder::where('bar_tab_id', $tab['id'])->firstOrFail();
        $this->assertTrue((bool) $order->has_food);
        $this->assertFalse((bool) $order->has_drinks);
        // Same scope the admin Kitchen Orders screen (and, later, the Kitchen Tablet) reads from.
        $this->assertSame(1, DiningOrder::forKitchen()->count());
    }

    public function test_a_drinks_only_order_has_no_preparing_stage_and_goes_straight_to_served(): void
    {
        $drink = $this->drink();
        $token = $this->bartenderToken();

        $tab = $this->withToken($token)->postJson('/api/v1/bar/tabs', [])->json('data');
        $order = $this->withToken($token)->postJson("/api/v1/bar/tabs/{$tab['id']}/orders", [
            'items' => [['menu_item_id' => $drink->id, 'qty' => 1]],
        ])->json('data.orders.0');

        $this->assertFalse($order['has_food']);

        $this->withToken($token)
            ->postJson("/api/v1/bar/orders/{$order['id']}/prepare")
            ->assertStatus(422);

        $this->withToken($token)
            ->postJson("/api/v1/bar/orders/{$order['id']}/serve")
            ->assertOk()
            ->assertJsonPath('data.status', 'delivered');
    }

    public function test_a_mixed_food_and_drink_order_still_supports_the_preparing_stage(): void
    {
        $drink = $this->drink();
        $food = $this->food();
        $token = $this->bartenderToken();

        $tab = $this->withToken($token)->postJson('/api/v1/bar/tabs', [])->json('data');
        $order = $this->withToken($token)->postJson("/api/v1/bar/tabs/{$tab['id']}/orders", [
            'items' => [
                ['menu_item_id' => $drink->id, 'qty' => 1],
                ['menu_item_id' => $food->id, 'qty' => 1],
            ],
        ])->json('data.orders.0');

        $this->assertTrue($order['has_food']);

        $this->withToken($token)
            ->postJson("/api/v1/bar/orders/{$order['id']}/prepare")
            ->assertOk()
            ->assertJsonPath('data.status', 'preparing');

        $this->withToken($token)
            ->postJson("/api/v1/bar/orders/{$order['id']}/serve")
            ->assertOk()
            ->assertJsonPath('data.status', 'delivered');
    }
}
