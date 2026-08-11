<?php

namespace Tests\Feature\Api;

use App\Models\Booking;
use App\Models\Device;
use App\Models\DeviceType;
use App\Models\DiningOrder;
use App\Models\MenuItem;
use App\Models\ReceptionNotification;
use App\Models\Room;
use App\Models\RoomUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The guest tablet's Place Order (Dining) screen: browsing the menu,
 * charging an order straight to the room, and paying directly via Paystack.
 */
class TabletDiningOrderTest extends TestCase
{
    use RefreshDatabase;

    private Room $room;

    private RoomUnit $unit;

    private Booking $booking;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->room = Room::create([
            'name' => 'Alba Suite',
            'slug' => 'alba-suite-dining',
            'type' => 'suite',
            'price' => 7500,
            'guests' => 4,
            'amenities' => ['High-Speed WiFi'],
        ]);

        $this->unit = RoomUnit::create([
            'room_id' => $this->room->id,
            'number' => '101',
            'status' => 'occupied',
        ]);

        $this->booking = Booking::create([
            'reference' => 'BK-'.Str::upper(Str::random(8)),
            'customer_name' => 'Daniel Ubani',
            'customer_email' => 'daniel@example.test',
            'room_id' => $this->room->id,
            'room_name' => $this->room->name,
            'room_unit_id' => $this->unit->id,
            'check_in' => now()->subDays(1)->toDateString(),
            'check_out' => now()->addDays(4)->toDateString(),
            'nights' => 5,
            'guests' => 3,
            'amount' => 37500,
            'status' => 'checked_in',
            'checked_in_at' => now()->subDay(),
        ]);

        $this->unit->update(['booking_id' => $this->booking->id]);

        $type = DeviceType::create(['name' => 'Tablet', 'slug' => 'tablet-dining']);
        $device = Device::create([
            'device_uuid' => (string) Str::uuid(),
            'device_code' => 'TAB-DN-101',
            'device_name' => 'Room 101 Tablet',
            'device_type_id' => $type->id,
            'mode' => 'guest',
            'room_id' => $this->room->id,
            'room_unit_id' => $this->unit->id,
            'status' => 'online',
            'is_provisioned' => true,
        ]);

        $this->token = $device->createToken('tablet')->plainTextToken;
    }

    private function menuItem(array $overrides = []): MenuItem
    {
        return MenuItem::create(array_merge([
            'name' => 'Pan-Seared Atlantic Salmon',
            'slug' => 'salmon-'.Str::random(6),
            'category' => 'mains',
            'price' => 9000,
            'prep_minutes' => 25,
            'description' => 'Pan-seared salmon with seasonal vegetables.',
            'is_active' => true,
            'sort_order' => 1,
        ], $overrides));
    }

    public function test_the_menu_feed_lists_only_active_items(): void
    {
        $this->menuItem(['name' => 'Active Dish']);
        $this->menuItem(['name' => 'Retired Dish', 'is_active' => false]);

        $this->withToken($this->token)
            ->getJson('/api/v1/tablets/dining/menu')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.name', 'Active Dish')
            ->assertJsonPath('data.items.0.price_label', 'NGN 9,000')
            ->assertJsonPath('data.items.0.prep_label', '25 mins')
            ->assertJsonPath('data.service_fee_label', 'NGN 1,000')
            ->assertJsonCount(7, 'data.categories');
    }

    public function test_charging_an_order_to_the_room_confirms_it_immediately(): void
    {
        $salmon = $this->menuItem(['name' => 'Salmon', 'price' => 9000]);
        $wagyu = $this->menuItem(['name' => 'Wagyu', 'price' => 15000]);

        $this->withToken($this->token)
            ->postJson('/api/v1/tablets/dining/book', [
                'items' => [
                    ['menu_item_id' => $salmon->id, 'qty' => 1],
                    ['menu_item_id' => $wagyu->id, 'qty' => 2, 'note' => 'No truffle please'],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.items_label', 'Salmon, Wagyu')
            ->assertJsonPath('data.item_count', 3)
            // subtotal = 9000 + 15000*2 = 39000, +7.5% VAT (2925) +1000 service fee = 42925
            ->assertJsonPath('data.total_label', 'NGN 42,925')
            ->assertJsonPath('data.payment_method', 'room_charge');

        $order = DiningOrder::where('booking_id', $this->booking->id)->first();
        $this->assertNotNull($order);
        $this->assertSame('confirmed', $order->status);
        $this->assertSame('pending', $order->payment_status);
        $this->assertNull($order->paid_at);
        $this->assertSame(39000, (int) $order->subtotal);
        $this->assertSame(2925, (int) $order->vat);
        $this->assertSame(1000, (int) $order->service_fee);
        $this->assertSame(42925, (int) $order->total);
        $this->assertCount(2, $order->items);
        $this->assertSame('No truffle please', $order->items[1]['note']);

        $this->withToken($this->token)
            ->getJson('/api/v1/tablets/notifications')
            ->assertOk()
            ->assertJsonPath('data.0.category', 'dining')
            ->assertJsonPath('data.0.title', 'Order Placed');

        $this->assertSame(
            1,
            ReceptionNotification::where('category', 'booking')->where('title', 'New Dining Order')->count()
        );
    }

    public function test_an_inactive_dish_cannot_be_ordered(): void
    {
        $dish = $this->menuItem(['is_active' => false]);

        $this->withToken($this->token)
            ->postJson('/api/v1/tablets/dining/book', [
                'items' => [['menu_item_id' => $dish->id, 'qty' => 1]],
            ])
            ->assertNotFound();
    }

    public function test_an_empty_cart_is_rejected(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/tablets/dining/book', ['items' => []])
            ->assertStatus(422);
    }

    public function test_orders_lists_confirmed_orders_newest_first(): void
    {
        DiningOrder::create([
            'booking_id' => $this->booking->id,
            'reference' => 'DN-OLD-1',
            'items' => [['menu_item_id' => 1, 'name' => 'Salmon', 'price' => 9000, 'qty' => 1, 'note' => null]],
            'item_count' => 1,
            'subtotal' => 9000,
            'service_fee' => 1000,
            'total' => 10000,
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'payment_method' => 'room_charge',
            'paid_at' => now()->subHour(),
        ]);
        DiningOrder::create([
            'booking_id' => $this->booking->id,
            'reference' => 'DN-NEW-2',
            'items' => [['menu_item_id' => 2, 'name' => 'Wagyu', 'price' => 15000, 'qty' => 1, 'note' => null]],
            'item_count' => 1,
            'subtotal' => 15000,
            'service_fee' => 1000,
            'total' => 16000,
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'payment_method' => 'card',
            'paid_at' => now(),
        ]);

        $this->withToken($this->token)
            ->getJson('/api/v1/tablets/dining/orders')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.reference', 'DN-NEW-2')
            ->assertJsonPath('data.0.payment_method_label', 'Paid Online')
            ->assertJsonPath('data.0.charged_to_room', false)
            ->assertJsonPath('data.0.total_label', 'NGN 16,000')
            ->assertJsonPath('data.1.reference', 'DN-OLD-1')
            ->assertJsonPath('data.1.payment_method_label', 'Charged to Room')
            ->assertJsonPath('data.1.charged_to_room', true);
    }

    public function test_an_active_order_carries_a_code_eta_and_full_item_detail_for_reorder(): void
    {
        $salmon = $this->menuItem(['name' => 'Salmon', 'price' => 9000, 'prep_minutes' => 25]);
        $wagyu = $this->menuItem(['name' => 'Wagyu', 'price' => 15000, 'prep_minutes' => 35]);

        $orderId = $this->withToken($this->token)
            ->postJson('/api/v1/tablets/dining/book', [
                'items' => [
                    ['menu_item_id' => $salmon->id, 'qty' => 1],
                    ['menu_item_id' => $wagyu->id, 'qty' => 2],
                ],
            ])
            ->assertCreated()
            ->json('data.id');

        $this->withToken($this->token)
            ->getJson('/api/v1/tablets/dining/orders')
            ->assertOk()
            ->assertJsonPath('data.0.code', 'ORD-'.now()->format('ym').'-'.str_pad((string) $orderId, 3, '0', STR_PAD_LEFT))
            // ETA is the longest prep time among the order's items (Wagyu, 35 mins).
            ->assertJsonPath('data.0.eta_label', 'ETA ~35 mins')
            ->assertJsonPath('data.0.is_active', true)
            ->assertJsonCount(2, 'data.0.items')
            ->assertJsonPath('data.0.items.0.menu_item_id', $salmon->id)
            ->assertJsonPath('data.0.items.0.name', 'Salmon')
            ->assertJsonPath('data.0.items.0.price', 9000)
            ->assertJsonPath('data.0.items.0.qty', 1)
            ->assertJsonPath('data.0.items.1.menu_item_id', $wagyu->id)
            ->assertJsonPath('data.0.items.1.qty', 2);
    }

    public function test_a_delivered_order_has_no_eta_and_is_not_active(): void
    {
        DiningOrder::create([
            'booking_id' => $this->booking->id,
            'reference' => 'DN-DELIVERED-1',
            'items' => [['menu_item_id' => 1, 'name' => 'Salmon', 'price' => 9000, 'qty' => 1, 'note' => null, 'prep_minutes' => 25]],
            'item_count' => 1,
            'subtotal' => 9000,
            'service_fee' => 1000,
            'total' => 10000,
            'status' => 'delivered',
            'payment_status' => 'paid',
            'payment_method' => 'room_charge',
            'paid_at' => now()->subHour(),
        ]);

        $this->withToken($this->token)
            ->getJson('/api/v1/tablets/dining/orders')
            ->assertOk()
            ->assertJsonPath('data.0.is_active', false)
            ->assertJsonPath('data.0.eta_label', null)
            ->assertJsonPath('data.0.status_label', 'Delivered');
    }

    public function test_orders_excludes_an_abandoned_unpaid_paystack_checkout(): void
    {
        DiningOrder::create([
            'booking_id' => $this->booking->id,
            'reference' => 'DN-ABANDONED-1',
            'items' => [['menu_item_id' => 1, 'name' => 'Salmon', 'price' => 9000, 'qty' => 1, 'note' => null]],
            'item_count' => 1,
            'subtotal' => 9000,
            'service_fee' => 1000,
            'total' => 10000,
            'status' => 'pending',
            'payment_status' => 'pending',
        ]);

        $this->withToken($this->token)
            ->getJson('/api/v1/tablets/dining/orders')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_paying_via_paystack_confirms_the_order_after_verification(): void
    {
        config()->set('services.paystack.secret_key', 'sk_test_dining');
        config()->set('services.paystack.payment_url', 'https://api.paystack.co');

        $salmon = $this->menuItem(['price' => 9000]);

        Http::fake([
            '*/transaction/initialize' => Http::response([
                'status' => true,
                'data' => ['authorization_url' => 'https://checkout.paystack.com/abc123', 'reference' => 'ref'],
            ], 200),
            '*/transaction/verify/*' => Http::response([
                'status' => true,
                'data' => ['status' => 'success', 'amount' => 1067500, 'channel' => 'card'],
            ], 200),
        ]);

        $reference = $this->withToken($this->token)
            ->postJson('/api/v1/tablets/dining/initialize', [
                'items' => [['menu_item_id' => $salmon->id, 'qty' => 1]],
            ])
            ->assertOk()
            ->assertJsonPath('data.total_label', 'NGN 10,675') // 9000 + 675 VAT + 1000 fee
            ->json('data.reference');

        $this->assertSame('pending', DiningOrder::where('reference', $reference)->first()->payment_status);

        $this->withToken($this->token)
            ->postJson('/api/v1/tablets/dining/confirm', ['reference' => $reference])
            ->assertOk()
            ->assertJsonPath('data.payment_method', 'card');

        $order = DiningOrder::where('reference', $reference)->first();
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('confirmed', $order->status);
        $this->assertNotNull($order->paid_at);
    }

    public function test_a_paystack_charge_for_the_wrong_amount_is_rejected(): void
    {
        config()->set('services.paystack.secret_key', 'sk_test_dining');
        config()->set('services.paystack.payment_url', 'https://api.paystack.co');

        $salmon = $this->menuItem(['price' => 9000]);

        Http::fake([
            '*/transaction/initialize' => Http::response([
                'status' => true,
                'data' => ['authorization_url' => 'https://checkout.paystack.com/abc123', 'reference' => 'ref'],
            ], 200),
            '*/transaction/verify/*' => Http::response([
                'status' => true,
                'data' => ['status' => 'success', 'amount' => 100, 'channel' => 'card'],
            ], 200),
        ]);

        $reference = $this->withToken($this->token)
            ->postJson('/api/v1/tablets/dining/initialize', [
                'items' => [['menu_item_id' => $salmon->id, 'qty' => 1]],
            ])
            ->json('data.reference');

        $this->withToken($this->token)
            ->postJson('/api/v1/tablets/dining/confirm', ['reference' => $reference])
            ->assertStatus(402);

        $this->assertSame('pending', DiningOrder::where('reference', $reference)->first()->payment_status);
    }

    public function test_a_guest_cannot_confirm_another_bookings_reference(): void
    {
        $otherUnit = RoomUnit::create(['room_id' => $this->room->id, 'number' => '102', 'status' => 'occupied']);
        $otherBooking = Booking::create([
            'reference' => 'BK-'.Str::upper(Str::random(8)),
            'customer_name' => 'Another Guest',
            'room_id' => $this->room->id,
            'room_name' => $this->room->name,
            'room_unit_id' => $otherUnit->id,
            'check_in' => now()->subDays(1)->toDateString(),
            'check_out' => now()->addDays(2)->toDateString(),
            'nights' => 3,
            'guests' => 1,
            'amount' => 22500,
            'status' => 'checked_in',
            'checked_in_at' => now()->subDay(),
        ]);
        $foreign = DiningOrder::create([
            'booking_id' => $otherBooking->id,
            'reference' => 'DN-FOREIGN-1',
            'items' => [['menu_item_id' => 1, 'name' => 'X', 'price' => 1000, 'qty' => 1, 'note' => null]],
            'item_count' => 1,
            'subtotal' => 1000,
            'service_fee' => 1000,
            'total' => 2000,
            'status' => 'pending',
            'payment_status' => 'pending',
        ]);

        $this->withToken($this->token)
            ->postJson('/api/v1/tablets/dining/confirm', ['reference' => $foreign->reference])
            ->assertNotFound();
    }

    public function test_a_room_charge_order_appears_on_the_guests_bill(): void
    {
        $salmon = $this->menuItem(['price' => 9000]);

        $this->withToken($this->token)
            ->postJson('/api/v1/tablets/dining/book', [
                'items' => [['menu_item_id' => $salmon->id, 'qty' => 1]],
            ])
            ->assertCreated();

        $this->withToken($this->token)
            ->getJson('/api/v1/tablets/my-bills')
            ->assertOk()
            ->assertJsonPath('data.categories.2.key', 'restaurant')
            ->assertJsonPath('data.categories.2.has_charges', true)
            ->assertJsonPath('data.categories.2.amount_label', 'NGN 10,000');
    }
}
