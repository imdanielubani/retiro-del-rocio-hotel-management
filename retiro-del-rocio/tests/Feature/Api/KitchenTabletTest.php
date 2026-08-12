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

    public function test_kitchen_can_advance_a_ticket_from_new_to_preparing_to_served(): void
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

        $this->withToken($token)
            ->postJson("/api/v1/kitchen/orders/{$order->id}/serve")
            ->assertOk()
            ->assertJsonPath('data.status', 'delivered')
            ->assertJsonPath('data.board_column', 'served');
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
            ->assertJsonPath('data.total_label', '₦5,838'); // 4500 + 338 VAT (7.5%, rounded) + 1000 service fee
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

        $history = $this->withToken($token)->getJson('/api/v1/kitchen/history')->json('data');
        $this->assertCount(1, $history);

        $found = $this->withToken($token)
            ->getJson('/api/v1/kitchen/history?search=Grace')
            ->json('data');
        $this->assertCount(1, $found);
    }
}
