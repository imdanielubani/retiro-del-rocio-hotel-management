<?php

namespace Tests\Feature\Api;

use App\Models\Booking;
use App\Models\Device;
use App\Models\DeviceType;
use App\Models\Room;
use App\Models\RoomUnit;
use App\Models\User;
use App\Services\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The reception check-in flow: the room-assignment options, and the check-in
 * submit that records the guest's identity document and stores its scan in
 * Cloudinary.
 */
class ReceptionCheckInFlowTest extends TestCase
{
    use RefreshDatabase;

    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();

        $this->room = Room::create([
            'name' => 'Brisa Residence',
            'slug' => 'brisa-residence',
            'type' => 'suite',
            'price' => 150000,
        ]);
    }

    private function token(): string
    {
        Role::findOrCreate('reception');
        $user = User::factory()->create(['status' => 'active', 'name' => 'Front Desk']);
        $user->assignRole('reception');

        return app(JwtService::class)->issue(['sub' => $user->id])['token'];
    }

    private function unit(string $number, string $status = 'available'): RoomUnit
    {
        return RoomUnit::create(['room_id' => $this->room->id, 'number' => $number, 'status' => $status]);
    }

    private function booking(array $overrides = []): Booking
    {
        return Booking::create(array_merge([
            'reference' => 'BK-'.Str::upper(Str::random(8)),
            'customer_name' => 'Fatima Al-Rashid',
            'room_id' => $this->room->id,
            'room_name' => $this->room->name,
            'check_in' => today()->toDateString(),
            'check_out' => today()->addDays(2)->toDateString(),
            'nights' => 2, 'guests' => 2, 'amount' => 300000,
            'status' => 'paid',
        ], $overrides));
    }

    private function configureCloudinary(): void
    {
        config()->set('cloudinary.cloud_name', 'demo');
        config()->set('cloudinary.api_key', 'key');
        config()->set('cloudinary.api_secret', 'secret');
    }

    public function test_room_options_show_the_assigned_unit_and_same_type_alternatives(): void
    {
        $this->unit('201');
        $this->unit('202');
        $booking = $this->booking();

        $data = $this->withToken($this->token())
            ->getJson("/api/v1/reception/bookings/{$booking->id}/rooms")
            ->assertOk()
            ->json('data');

        $this->assertNotNull($data['assigned']);
        $this->assertContains($data['assigned']['number'], ['201', '202']);
        // The other free unit is offered, and never the one just assigned.
        $this->assertCount(1, $data['available']);
        $this->assertNotSame($data['assigned']['number'], $data['available'][0]['number']);
        $this->assertStringContainsString('NGN', $data['available'][0]['price_label']);
    }

    public function test_room_options_flag_units_that_have_an_in_room_tablet(): void
    {
        $withTablet = $this->unit('301');
        $withoutTablet = $this->unit('302');
        $type = DeviceType::create(['name' => 'Tablet']);
        Device::create([
            'room_id' => $this->room->id,
            'room_unit_id' => $withTablet->id,
            'device_type_id' => $type->id,
            'device_code' => 'TAB-301',
            'device_name' => 'Room 301 Tablet',
        ]);
        $booking = $this->booking(['room_unit_id' => $withTablet->id]);

        $data = $this->withToken($this->token())
            ->getJson("/api/v1/reception/bookings/{$booking->id}/rooms")
            ->assertOk()
            ->json('data');

        // The assigned unit carries a tablet; the free alternative does not.
        $this->assertSame('301', $data['assigned']['number']);
        $this->assertTrue($data['assigned']['has_tablet']);
        $this->assertSame('302', $data['available'][0]['number']);
        $this->assertFalse($data['available'][0]['has_tablet']);
    }

    public function test_check_in_records_the_identity_and_uploads_the_scan_to_cloudinary(): void
    {
        $this->configureCloudinary();
        Http::fake([
            'api.cloudinary.com/*' => Http::response([
                'public_id' => 'checkin-ids/abc123',
                'secure_url' => 'https://res.cloudinary.com/demo/image/upload/checkin-ids/abc123.jpg',
            ]),
        ]);

        $unit = $this->unit('205');
        $booking = $this->booking(['room_unit_id' => $unit->id]);

        $this->withToken($this->token())
            ->post("/api/v1/reception/bookings/{$booking->id}/check-in", [
                'document_type' => 'passport',
                'document_number' => '784-1985-1234567-1',
                'room_unit_id' => $unit->id,
                'document' => UploadedFile::fake()->image('passport.jpg'),
            ])
            ->assertOk()
            ->assertJsonPath('data.document_label', 'International Passport')
            ->assertJsonPath('data.document_number', '784-1985-1234567-1')
            ->assertJsonPath('data.room_number', '205');

        $booking->refresh();
        $this->assertSame('checked_in', $booking->status);
        $this->assertSame('passport', $booking->id_document_type);
        $this->assertSame('checkin-ids/abc123', $booking->id_document_public_id);
        $this->assertStringContainsString('res.cloudinary.com', $booking->id_document_url);
        $this->assertNotNull($booking->identity_verified_at);
        $this->assertSame('occupied', $unit->fresh()->status);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'api.cloudinary.com')
            && str_contains($request->url(), '/demo/'));
    }

    public function test_check_in_still_succeeds_when_cloudinary_is_unconfigured(): void
    {
        // Force the unconfigured case regardless of the environment's real creds:
        // the scan is skipped and the check-in still completes on the number alone.
        config()->set('cloudinary.cloud_name', null);
        config()->set('cloudinary.api_key', null);
        config()->set('cloudinary.api_secret', null);
        Http::preventStrayRequests();

        $unit = $this->unit('205');
        $booking = $this->booking(['room_unit_id' => $unit->id]);

        $this->withToken($this->token())
            ->post("/api/v1/reception/bookings/{$booking->id}/check-in", [
                'document_type' => 'nin',
                'document_number' => '12345678901',
                'document' => UploadedFile::fake()->image('nin.jpg'),
            ])
            ->assertOk()
            ->assertJsonPath('data.document_label', 'NIN');

        $booking->refresh();
        $this->assertSame('checked_in', $booking->status);
        $this->assertNull($booking->id_document_url); // upload skipped
        $this->assertSame('12345678901', $booking->id_document_number);
    }

    public function test_check_in_accepts_the_additional_id_types(): void
    {
        $unit = $this->unit('206');
        $booking = $this->booking(['room_unit_id' => $unit->id]);

        // A driver's licence (no scan) is now an accepted document, labelled for
        // the admin dashboard and the completion screen alike.
        $this->withToken($this->token())
            ->post("/api/v1/reception/bookings/{$booking->id}/check-in", [
                'document_type' => 'drivers_license',
                'document_number' => 'DL-99881',
            ])
            ->assertOk()
            ->assertJsonPath('data.document_label', "Driver's License");

        $fresh = $booking->fresh();
        $this->assertSame('checked_in', $fresh->status);
        $this->assertSame('drivers_license', $fresh->id_document_type);
        $this->assertSame('DL-99881', $fresh->id_document_number);
    }

    public function test_check_in_rejects_an_unknown_id_type(): void
    {
        $unit = $this->unit('207');
        $booking = $this->booking(['room_unit_id' => $unit->id]);

        $this->withToken($this->token())
            ->postJson("/api/v1/reception/bookings/{$booking->id}/check-in", [
                'document_type' => 'made_up',
                'document_number' => 'X',
            ])
            ->assertStatus(422);
    }

    public function test_room_options_include_available_rooms_of_every_type(): void
    {
        $this->unit('201'); // this booking's room type — auto-assigned to it
        $this->unit('202'); // a second unit of the booked type stays free
        $otherRoom = Room::create(['name' => 'Alba Suite', 'slug' => 'alba', 'type' => 'suite', 'price' => 200000]);
        RoomUnit::create(['room_id' => $otherRoom->id, 'number' => '999', 'status' => 'available']);
        $booking = $this->booking();

        $data = $this->withToken($this->token())
            ->getJson("/api/v1/reception/bookings/{$booking->id}/rooms")
            ->assertOk()
            ->json('data');

        // The whole free inventory is offered — both room types show, not just
        // the booked one.
        $names = collect($data['available'])->pluck('room_name');
        $this->assertTrue($names->contains('Brisa Residence'));
        $this->assertTrue($names->contains('Alba Suite'));
    }

    public function test_moving_to_another_room_type_reassigns_without_repricing(): void
    {
        $otherRoom = Room::create(['name' => 'Alba Suite', 'slug' => 'alba', 'type' => 'suite', 'price' => 200000]);
        $foreign = RoomUnit::create(['room_id' => $otherRoom->id, 'number' => '999', 'status' => 'available']);
        $booking = $this->booking(); // amount 300000, room Brisa Residence

        $this->withToken($this->token())
            ->postJson("/api/v1/reception/bookings/{$booking->id}/check-in", [
                'room_unit_id' => $foreign->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.room_number', '999');

        $booking->refresh();
        $this->assertSame('checked_in', $booking->status);
        $this->assertSame($foreign->id, $booking->room_unit_id);
        // The booking now points at the room it actually occupies…
        $this->assertSame($otherRoom->id, $booking->room_id);
        $this->assertSame('Alba Suite', $booking->room_name);
        // …but the charge is untouched — no silent repricing.
        $this->assertSame(300000, (int) $booking->amount);
        $this->assertSame('occupied', $foreign->fresh()->status);
    }

    public function test_check_in_rejects_an_occupied_room(): void
    {
        $taken = $this->unit('207', 'occupied');
        $booking = $this->booking();

        $this->withToken($this->token())
            ->postJson("/api/v1/reception/bookings/{$booking->id}/check-in", [
                'room_unit_id' => $taken->id,
            ])
            ->assertStatus(422);
    }
}
