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
 * The Kitchen Tablet (KDS): the food ticket board, and its shared plumbing
 * with the guest tablet's Place Order flow, the Bar Tablet's POS (a mixed
 * order still flows to Kitchen), and the Kitchen admin dashboard — all read/
 * write the exact same DiningOrder rows.
 */
class KitchenTabletTest extends TestCase
{
    use RefreshDatabase;

    private function chefToken(string $name = 'Amara Chef'): string
    {
        Role::findOrCreate('kitchen', 'web');
        $user = User::factory()->create(['status' => 'active', 'name' => $name]);
        $user->assignRole('kitchen');

        return app(JwtService::class)->issue(['sub' => $user->id])['token'];
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

    /** A guest device token + active booking, for placing a guest-tablet food order. */
    private function guestToken(): array
    {
        $room = Room::create([
            'name' => 'Alba Suite', 'slug' => 'alba-suite-kitchen-'.Str::random(6),
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

        $type = DeviceType::create(['name' => 'Tablet', 'slug' => 'tablet-kitchen-'.Str::random(6)]);
        $device = Device::create([
            'device_uuid' => (string) Str::uuid(), 'device_code' => 'TAB-KIT-'.Str::random(4),
            'device_name' => 'Room Tablet', 'device_type_id' => $type->id, 'mode' => 'guest',
            'room_id' => $room->id, 'room_unit_id' => $unit->id, 'status' => 'online', 'is_provisioned' => true,
        ]);

        return [$device->createToken('tablet')->plainTextToken, $unit->number];
    }

    public function test_a_non_kitchen_user_is_rejected(): void
    {
        Role::findOrCreate('housekeeping', 'web');
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('housekeeping');
        $token = app(JwtService::class)->issue(['sub' => $user->id])['token'];

        $this->withToken($token)->getJson('/api/v1/kitchen/overview')->assertForbidden();
    }

    public function test_a_guest_placed_food_order_appears_on_the_kitchen_live_board_with_its_room(): void
    {
        $food = $this->food(['name' => 'Jollof Rice']);
        [$guestToken, $roomNumber] = $this->guestToken();
        $chefToken = $this->chefToken();

        $this->withToken($guestToken)->postJson('/api/v1/tablets/dining/book', [
            'items' => [['menu_item_id' => $food->id, 'qty' => 1]],
        ])->assertCreated();

        $board = $this->withToken($chefToken)->getJson('/api/v1/kitchen/orders')->json('data');
        $this->assertCount(1, $board);
        $this->assertSame('Jollof Rice', $board[0]['items'][0]['name']);
        $this->assertSame('Room '.$roomNumber, $board[0]['room_label']);
        $this->assertSame('new', $board[0]['board_column']);

        // The exact same scope the admin Kitchen Orders screen uses.
        $this->assertSame(1, DiningOrder::forKitchen()->count());
    }

    public function test_a_guest_order_note_and_allergy_note_both_reach_the_kitchen(): void
    {
        $food = $this->food(['name' => 'Jollof Rice']);
        [$guestToken] = $this->guestToken();
        $chefToken = $this->chefToken();

        $this->withToken($guestToken)->postJson('/api/v1/tablets/dining/book', [
            'items' => [[
                'menu_item_id' => $food->id,
                'qty' => 1,
                'note' => 'Extra spicy please',
                'allergies' => 'Peanuts',
            ]],
        ])->assertCreated();

        $order = DiningOrder::forKitchen()->firstOrFail();

        $this->withToken($chefToken)
            ->getJson('/api/v1/kitchen/orders')
            ->assertJsonPath('data.0.items.0.note', 'Extra spicy please')
            ->assertJsonPath('data.0.items.0.allergies', 'Peanuts');

        $this->withToken($chefToken)
            ->getJson("/api/v1/kitchen/orders/{$order->id}")
            ->assertJsonPath('data.items.0.note', 'Extra spicy please')
            ->assertJsonPath('data.items.0.allergies', 'Peanuts');
    }

    public function test_kitchen_can_advance_a_ticket_from_new_to_preparing_to_ready_to_on_the_way_to_delivered(): void
    {
        $food = $this->food();
        [$guestToken] = $this->guestToken();
        $token = $this->chefToken();

        $this->withToken($guestToken)->postJson('/api/v1/tablets/dining/book', [
            'items' => [['menu_item_id' => $food->id, 'qty' => 1]],
        ])->assertCreated();
        $order = DiningOrder::forKitchen()->firstOrFail();

        $this->withToken($token)
            ->postJson("/api/v1/kitchen/orders/{$order->id}/prepare")
            ->assertOk()
            ->assertJsonPath('data.status', 'preparing')
            ->assertJsonPath('data.board_column', 'preparing');

        // A plain guest-tablet food order has no waiter to hand off to —
        // Kitchen itself carries it the rest of the way, visiting every
        // stage of the guest tablet's tracker rather than jumping straight
        // to delivered.
        $this->withToken($token)
            ->postJson("/api/v1/kitchen/orders/{$order->id}/serve")
            ->assertOk()
            ->assertJsonPath('data.status', 'ready')
            ->assertJsonPath('data.board_column', 'ready');

        $this->withToken($token)
            ->postJson("/api/v1/kitchen/orders/{$order->id}/on-way")
            ->assertOk()
            ->assertJsonPath('data.status', 'on_way');

        $this->withToken($token)
            ->postJson("/api/v1/kitchen/orders/{$order->id}/deliver")
            ->assertOk()
            ->assertJsonPath('data.status', 'delivered')
            ->assertJsonPath('data.board_column', 'served');
    }

    public function test_kitchen_can_skip_straight_from_ready_to_delivered_for_a_nearby_room(): void
    {
        $food = $this->food();
        [$guestToken] = $this->guestToken();
        $token = $this->chefToken();

        $this->withToken($guestToken)->postJson('/api/v1/tablets/dining/book', [
            'items' => [['menu_item_id' => $food->id, 'qty' => 1]],
        ])->assertCreated();
        $order = DiningOrder::forKitchen()->firstOrFail();

        $this->withToken($token)->postJson("/api/v1/kitchen/orders/{$order->id}/prepare")->assertOk();
        $this->withToken($token)->postJson("/api/v1/kitchen/orders/{$order->id}/serve")->assertOk();

        $this->withToken($token)
            ->postJson("/api/v1/kitchen/orders/{$order->id}/deliver")
            ->assertOk()
            ->assertJsonPath('data.status', 'delivered');
    }

    public function test_kitchen_cannot_mark_a_dine_in_tab_order_on_the_way_or_delivered(): void
    {
        $drink = $this->drink();
        $food = $this->food();
        Role::findOrCreate('bar', 'web');
        $bartender = User::factory()->create(['status' => 'active']);
        $bartender->assignRole('bar');
        $barToken = app(JwtService::class)->issue(['sub' => $bartender->id])['token'];
        $token = $this->chefToken();

        $tab = $this->withToken($barToken)->postJson('/api/v1/bar/tabs', [])->json('data');
        $this->withToken($barToken)->postJson("/api/v1/bar/tabs/{$tab['id']}/orders", [
            'items' => [
                ['menu_item_id' => $drink->id, 'qty' => 1],
                ['menu_item_id' => $food->id, 'qty' => 1],
            ],
        ])->assertCreated();
        $order = DiningOrder::where('bar_tab_id', $tab['id'])->firstOrFail();

        $this->withToken($token)->postJson("/api/v1/kitchen/orders/{$order->id}/prepare")->assertOk();
        $this->withToken($token)->postJson("/api/v1/kitchen/orders/{$order->id}/serve")->assertOk();

        $this->withToken($token)
            ->postJson("/api/v1/kitchen/orders/{$order->id}/on-way")
            ->assertStatus(422);
        $this->withToken($token)
            ->postJson("/api/v1/kitchen/orders/{$order->id}/deliver")
            ->assertStatus(422);
    }

    public function test_voiding_an_item_recomputes_the_ticket_total(): void
    {
        $riceA = $this->food(['name' => 'Jollof Rice', 'price' => 5000]);
        $riceB = $this->food(['name' => 'Fried Rice', 'price' => 4500]);
        [$guestToken] = $this->guestToken();
        $token = $this->chefToken();

        $this->withToken($guestToken)->postJson('/api/v1/tablets/dining/book', [
            'items' => [
                ['menu_item_id' => $riceA->id, 'qty' => 1],
                ['menu_item_id' => $riceB->id, 'qty' => 1],
            ],
        ])->assertCreated();
        $order = DiningOrder::forKitchen()->firstOrFail();

        $this->withToken($token)
            ->postJson("/api/v1/kitchen/orders/{$order->id}/void-item", ['item_index' => 0, 'reason' => 'Out of stock'])
            ->assertOk()
            ->assertJsonPath('data.total_label', '₦4,838'); // 4500 + 338 VAT (7.5%, rounded), no service fee
    }

    public function test_assigning_a_non_kitchen_user_to_a_ticket_is_rejected(): void
    {
        $food = $this->food();
        [$guestToken] = $this->guestToken();
        $token = $this->chefToken();

        $this->withToken($guestToken)->postJson('/api/v1/tablets/dining/book', [
            'items' => [['menu_item_id' => $food->id, 'qty' => 1]],
        ])->assertCreated();
        $order = DiningOrder::forKitchen()->firstOrFail();

        $outsider = User::factory()->create(['status' => 'active']);

        $this->withToken($token)
            ->postJson("/api/v1/kitchen/orders/{$order->id}/assign", ['chef_id' => $outsider->id])
            ->assertStatus(422);
    }

    public function test_assigning_a_kitchen_staff_member_to_a_ticket_succeeds(): void
    {
        $food = $this->food();
        [$guestToken] = $this->guestToken();
        $token = $this->chefToken('Opener');

        Role::findOrCreate('kitchen', 'web');
        $otherChef = User::factory()->create(['status' => 'active', 'name' => 'Second Shift']);
        $otherChef->assignRole('kitchen');

        $this->withToken($guestToken)->postJson('/api/v1/tablets/dining/book', [
            'items' => [['menu_item_id' => $food->id, 'qty' => 1]],
        ])->assertCreated();
        $order = DiningOrder::forKitchen()->firstOrFail();

        $this->withToken($token)
            ->postJson("/api/v1/kitchen/orders/{$order->id}/assign", ['chef_id' => $otherChef->id])
            ->assertOk()
            ->assertJsonPath('data.assigned_to_name', 'Second Shift');
    }

    public function test_a_new_food_order_creates_a_kitchen_notification(): void
    {
        $food = $this->food();
        [$guestToken] = $this->guestToken();
        $token = $this->chefToken();

        $this->withToken($guestToken)->postJson('/api/v1/tablets/dining/book', [
            'items' => [['menu_item_id' => $food->id, 'qty' => 1]],
        ])->assertCreated();

        $this->withToken($token)
            ->getJson('/api/v1/kitchen/notifications')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.category', 'new_order');
    }

    public function test_kitchen_menu_only_includes_food_items_not_drinks(): void
    {
        $this->food(['name' => 'Jollof Rice']);
        $this->drink(['name' => 'Fresh Chapman']);
        $token = $this->chefToken();

        $names = $this->withToken($token)->getJson('/api/v1/kitchen/menu')
            ->assertOk()
            ->json('data.*.name');

        $this->assertContains('Jollof Rice', $names);
        $this->assertNotContains('Fresh Chapman', $names);
    }

    public function test_staff_can_86_a_dish_from_the_menu_availability_screen(): void
    {
        $dish = $this->food(['is_active' => true]);
        $token = $this->chefToken();

        $this->withToken($token)
            ->postJson("/api/v1/kitchen/menu/{$dish->id}/toggle")
            ->assertOk()
            ->assertJsonPath('data.category', 'mains');

        $this->assertFalse($dish->fresh()->is_active);
    }

    public function test_a_drink_only_order_is_not_visible_to_the_kitchen(): void
    {
        $drink = $this->drink();
        [$guestToken] = $this->guestToken();
        $token = $this->chefToken();

        $this->withToken($guestToken)->postJson('/api/v1/tablets/dining/book', [
            'items' => [['menu_item_id' => $drink->id, 'qty' => 1]],
        ])->assertCreated();
        $order = DiningOrder::first();

        $this->assertSame(0, DiningOrder::forKitchen()->count());
        $this->withToken($token)->getJson("/api/v1/kitchen/orders/{$order->id}")->assertNotFound();
        $this->withToken($token)->getJson('/api/v1/kitchen/orders')->assertJsonCount(0, 'data');
    }

    public function test_a_served_food_ticket_appears_in_history_and_can_be_searched(): void
    {
        $food = $this->food();
        [$guestToken] = $this->guestToken();
        $token = $this->chefToken();

        $this->withToken($guestToken)->postJson('/api/v1/tablets/dining/book', [
            'items' => [['menu_item_id' => $food->id, 'qty' => 1]],
        ])->assertCreated();
        $order = DiningOrder::forKitchen()->firstOrFail();
        $this->withToken($token)->postJson("/api/v1/kitchen/orders/{$order->id}/serve")->assertOk();
        $this->withToken($token)->postJson("/api/v1/kitchen/orders/{$order->id}/deliver")->assertOk();

        $history = $this->withToken($token)->getJson('/api/v1/kitchen/history')->json('data');
        $this->assertCount(1, $history);

        $found = $this->withToken($token)
            ->getJson('/api/v1/kitchen/history?search=Grace')
            ->json('data');
        $this->assertCount(1, $found);
    }

    /**
     * A waiter can close out a bar tab's payment without ever tapping the
     * ticket through its own status steps — the food order's `status` can
     * stay stuck at "new"/"ready" forever even though the tab is settled
     * and done. History has to catch these too, not just orders that
     * individually reached delivered/cancelled.
     */
    public function test_a_food_ticket_on_a_settled_bar_tab_appears_in_history_even_if_never_individually_served(): void
    {
        Role::findOrCreate('bar', 'web');
        $bartender = User::factory()->create(['status' => 'active']);
        $bartender->assignRole('bar');
        $barToken = app(JwtService::class)->issue(['sub' => $bartender->id])['token'];

        $food = $this->food();
        $kitchenToken = $this->chefToken();

        $tab = $this->withToken($barToken)->postJson('/api/v1/bar/tabs', [])->json('data');
        $this->withToken($barToken)->postJson("/api/v1/bar/tabs/{$tab['id']}/orders", [
            'items' => [['menu_item_id' => $food->id, 'qty' => 1]],
        ])->assertCreated();
        $order = DiningOrder::where('bar_tab_id', $tab['id'])->firstOrFail();
        $statusBeforeClose = $order->status;
        $this->assertNotContains($statusBeforeClose, ['delivered', 'cancelled']);

        $this->withToken($barToken)
            ->postJson("/api/v1/bar/tabs/{$tab['id']}/close", ['payment_method' => 'cash'])
            ->assertOk();
        $this->assertSame($statusBeforeClose, $order->fresh()->status);

        $history = $this->withToken($kitchenToken)->getJson('/api/v1/kitchen/history')->json('data');
        $this->assertCount(1, $history);
        $this->assertSame($order->id, $history[0]['id']);
    }

    public function test_kitchen_can_set_an_eta_and_the_bar_tablet_sees_it(): void
    {
        $food = $this->food();
        $drink = $this->drink();
        [$guestToken] = $this->guestToken();
        $kitchenToken = $this->chefToken();

        $this->withToken($guestToken)->postJson('/api/v1/tablets/dining/book', [
            'items' => [
                ['menu_item_id' => $food->id, 'qty' => 1],
                ['menu_item_id' => $drink->id, 'qty' => 1],
            ],
        ])->assertCreated();
        $order = DiningOrder::forKitchen()->firstOrFail();

        $this->withToken($kitchenToken)
            ->postJson("/api/v1/kitchen/orders/{$order->id}/eta", ['minutes' => 10])
            ->assertOk()
            ->assertJsonPath('data.estimated_ready_minutes', 10);

        // Bar & Lounge staff see the same order (it also has a drink) with the same ETA.
        Role::findOrCreate('bar', 'web');
        $bartender = User::factory()->create(['status' => 'active']);
        $bartender->assignRole('bar');
        $barToken = app(JwtService::class)->issue(['sub' => $bartender->id])['token'];

        $this->withToken($barToken)
            ->getJson("/api/v1/bar/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.estimated_ready_minutes', 10);
    }

    public function test_the_kitchen_can_increase_the_eta_by_calling_it_again(): void
    {
        $food = $this->food();
        [$guestToken] = $this->guestToken();
        $token = $this->chefToken();

        $this->withToken($guestToken)->postJson('/api/v1/tablets/dining/book', [
            'items' => [['menu_item_id' => $food->id, 'qty' => 1]],
        ])->assertCreated();
        $order = DiningOrder::forKitchen()->firstOrFail();

        $this->withToken($token)->postJson("/api/v1/kitchen/orders/{$order->id}/eta", ['minutes' => 10])->assertOk();
        $firstEta = $order->fresh()->estimated_ready_at;

        $this->withToken($token)
            ->postJson("/api/v1/kitchen/orders/{$order->id}/eta", ['minutes' => 20])
            ->assertOk()
            ->assertJsonPath('data.estimated_ready_minutes', 20);

        $this->assertTrue($order->fresh()->estimated_ready_at->gt($firstEta));
    }

    public function test_an_eta_cannot_be_set_once_the_ticket_is_ready_for_pickup(): void
    {
        $food = $this->food();
        [$guestToken] = $this->guestToken();
        $token = $this->chefToken();

        $this->withToken($guestToken)->postJson('/api/v1/tablets/dining/book', [
            'items' => [['menu_item_id' => $food->id, 'qty' => 1]],
        ])->assertCreated();
        $order = DiningOrder::forKitchen()->firstOrFail();

        $this->withToken($token)->postJson("/api/v1/kitchen/orders/{$order->id}/serve")->assertOk();

        $this->withToken($token)
            ->postJson("/api/v1/kitchen/orders/{$order->id}/eta", ['minutes' => 5])
            ->assertStatus(422);
    }

    /**
     * A plain guest-tablet food order (no waiter involved) has nowhere to
     * hand off to, so Kitchen finalises it directly — the ready-for-pickup
     * hand-off only exists for a bar/waiter-run order: one rung up on a
     * tab and mixed with a drink, so the waiter is already at the table.
     */
    public function test_a_mixed_food_and_drink_tab_order_is_ready_for_pickup_before_the_waiter_serves_it(): void
    {
        $food = $this->food();
        $drink = $this->drink();
        Role::findOrCreate('bar', 'web');
        $bartender = User::factory()->create(['status' => 'active']);
        $bartender->assignRole('bar');
        $barToken = app(JwtService::class)->issue(['sub' => $bartender->id])['token'];
        $kitchenToken = $this->chefToken();

        $tab = $this->withToken($barToken)->postJson('/api/v1/bar/tabs', ['table_label' => 'Table 9'])->json('data');
        $this->withToken($barToken)->postJson("/api/v1/bar/tabs/{$tab['id']}/orders", [
            'items' => [
                ['menu_item_id' => $food->id, 'qty' => 1],
                ['menu_item_id' => $drink->id, 'qty' => 1],
            ],
        ])->assertCreated();
        $order = DiningOrder::where('bar_tab_id', $tab['id'])->firstOrFail();

        // Kitchen readies it — still on the live board, not yet served.
        $this->withToken($kitchenToken)
            ->postJson("/api/v1/kitchen/orders/{$order->id}/serve")
            ->assertOk()
            ->assertJsonPath('data.status', 'ready')
            ->assertJsonPath('data.board_column', 'ready');
        $this->assertCount(1, $this->withToken($kitchenToken)->getJson('/api/v1/kitchen/orders')->json('data'));

        // The waiter picks it up and serves it — that's the Bar Tablet's own action.
        $this->withToken($barToken)
            ->postJson("/api/v1/bar/orders/{$order->id}/serve")
            ->assertOk()
            ->assertJsonPath('data.status', 'delivered')
            ->assertJsonPath('data.board_column', 'served');
    }

    public function test_marking_a_room_service_order_preparing_notifies_the_guest_tablet(): void
    {
        $food = $this->food();
        [$guestToken] = $this->guestToken();
        $token = $this->chefToken();

        $this->withToken($guestToken)->postJson('/api/v1/tablets/dining/book', [
            'items' => [['menu_item_id' => $food->id, 'qty' => 1]],
        ])->assertCreated();
        $order = DiningOrder::forKitchen()->firstOrFail();

        $this->withToken($token)->postJson("/api/v1/kitchen/orders/{$order->id}/prepare")->assertOk();

        $notifications = $this->withToken($guestToken)->getJson('/api/v1/tablets/notifications')->json('data');
        $this->assertTrue(collect($notifications)->contains(fn ($n) => str_contains($n['message'], 'being prepared')));
    }

    public function test_the_guest_my_orders_eta_reflects_the_kitchens_live_eta_not_the_snapshotted_prep_time(): void
    {
        // 25-minute prep time snapshotted at order time — this must not be
        // what My Orders shows once the kitchen sets a real ready time.
        $food = $this->food(['prep_minutes' => 25]);
        [$guestToken] = $this->guestToken();
        $token = $this->chefToken();

        $this->withToken($guestToken)->postJson('/api/v1/tablets/dining/book', [
            'items' => [['menu_item_id' => $food->id, 'qty' => 1]],
        ])->assertCreated();
        $order = DiningOrder::forKitchen()->firstOrFail();

        // Before the kitchen sets anything, My Orders falls back to the snapshotted prep time.
        $this->withToken($guestToken)
            ->getJson('/api/v1/tablets/dining/orders')
            ->assertJsonPath('data.0.eta_label', 'ETA ~25 mins');

        $this->withToken($token)->postJson("/api/v1/kitchen/orders/{$order->id}/eta", ['minutes' => 10])->assertOk();

        $this->withToken($guestToken)
            ->getJson('/api/v1/tablets/dining/orders')
            ->assertJsonPath('data.0.eta_label', 'Ready in ~10 minutes');

        // The kitchen increases it — My Orders reflects the new time too.
        $this->withToken($token)->postJson("/api/v1/kitchen/orders/{$order->id}/eta", ['minutes' => 20])->assertOk();

        $this->withToken($guestToken)
            ->getJson('/api/v1/tablets/dining/orders')
            ->assertJsonPath('data.0.eta_label', 'Ready in ~20 minutes');
    }

    public function test_setting_an_eta_notifies_the_guest_tablet(): void
    {
        $food = $this->food();
        [$guestToken] = $this->guestToken();
        $token = $this->chefToken();

        $this->withToken($guestToken)->postJson('/api/v1/tablets/dining/book', [
            'items' => [['menu_item_id' => $food->id, 'qty' => 1]],
        ])->assertCreated();
        $order = DiningOrder::forKitchen()->firstOrFail();

        $this->withToken($token)->postJson("/api/v1/kitchen/orders/{$order->id}/eta", ['minutes' => 15])->assertOk();

        $notifications = $this->withToken($guestToken)->getJson('/api/v1/tablets/notifications')->json('data');
        $this->assertTrue(collect($notifications)->contains(fn ($n) => str_contains($n['message'], 'ready in about 15 minutes')));
    }

    public function test_marking_a_pos_food_order_preparing_notifies_only_the_waiter_running_it(): void
    {
        $food = $this->food();
        $token = $this->chefToken();

        Role::findOrCreate('bar', 'web');
        $waiterA = User::factory()->create(['status' => 'active']);
        $waiterA->assignRole('bar');
        $waiterB = User::factory()->create(['status' => 'active']);
        $waiterB->assignRole('bar');
        $tokenA = app(JwtService::class)->issue(['sub' => $waiterA->id])['token'];
        $tokenB = app(JwtService::class)->issue(['sub' => $waiterB->id])['token'];

        $tab = $this->withToken($tokenA)->postJson('/api/v1/bar/tabs', [])->json('data');
        $this->withToken($tokenA)->postJson("/api/v1/bar/tabs/{$tab['id']}/orders", [
            'items' => [['menu_item_id' => $food->id, 'qty' => 1]],
        ])->assertCreated();
        $order = DiningOrder::where('bar_tab_id', $tab['id'])->firstOrFail();

        $this->withToken($token)->postJson("/api/v1/kitchen/orders/{$order->id}/prepare")->assertOk();

        $waiterANotifications = $this->withToken($tokenA)->getJson('/api/v1/bar/notifications')->json('data');
        $this->assertTrue(collect($waiterANotifications)->contains(fn ($n) => $n['category'] === 'order_update'));

        // Waiter B never rang this order up — the earlier scoping fix keeps them from seeing it.
        $waiterBNotifications = $this->withToken($tokenB)->getJson('/api/v1/bar/notifications')->json('data');
        $this->assertFalse(collect($waiterBNotifications)->contains(fn ($n) => $n['category'] === 'order_update'));
    }

    public function test_a_drinks_only_order_never_fires_preparing_or_ready_notifications(): void
    {
        $drink = $this->drink();
        [$guestToken] = $this->guestToken();

        $this->withToken($guestToken)->postJson('/api/v1/tablets/dining/book', [
            'items' => [['menu_item_id' => $drink->id, 'qty' => 1]],
        ])->assertCreated();
        $order = DiningOrder::first();

        Role::findOrCreate('bar', 'web');
        $bartender = User::factory()->create(['status' => 'active']);
        $bartender->assignRole('bar');
        $barToken = app(JwtService::class)->issue(['sub' => $bartender->id])['token'];

        $this->withToken($barToken)->postJson("/api/v1/bar/orders/{$order->id}/serve")->assertOk();

        // A drink has no kitchen preparing/ready stage — only the delivered
        // notification below fires — but it does still notify on delivery.
        $notifications = $this->withToken($guestToken)->getJson('/api/v1/tablets/notifications')->json('data');
        $this->assertFalse(collect($notifications)->contains(fn ($n) => str_contains($n['message'], 'being prepared')));
        $this->assertFalse(collect($notifications)->contains(fn ($n) => str_contains($n['message'], 'is ready')));
        $this->assertTrue(collect($notifications)->contains(fn ($n) => str_contains($n['message'], 'has been delivered')));
    }

    public function test_a_bartender_marking_a_room_service_drink_on_the_way_notifies_the_guest_tablet(): void
    {
        $drink = $this->drink();
        [$guestToken] = $this->guestToken();

        $this->withToken($guestToken)->postJson('/api/v1/tablets/dining/book', [
            'items' => [['menu_item_id' => $drink->id, 'qty' => 1]],
        ])->assertCreated();
        $order = DiningOrder::first();

        Role::findOrCreate('bar', 'web');
        $bartender = User::factory()->create(['status' => 'active']);
        $bartender->assignRole('bar');
        $barToken = app(JwtService::class)->issue(['sub' => $bartender->id])['token'];

        $this->withToken($barToken)
            ->postJson("/api/v1/bar/orders/{$order->id}/on-way")
            ->assertOk()
            ->assertJsonPath('data.status', 'on_way');

        $notifications = $this->withToken($guestToken)->getJson('/api/v1/tablets/notifications')->json('data');
        $this->assertTrue(collect($notifications)->contains(fn ($n) => str_contains($n['message'], 'on its way to your room')));
    }
}
