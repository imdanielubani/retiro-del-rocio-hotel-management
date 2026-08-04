<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\Housekeeping\LostFound;
use App\Models\LostFoundItem;
use App\Models\Room;
use App\Models\RoomUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Admin → Housekeeping → Lost & Found — the desk's own front door onto
 * items a housekeeper has logged, without needing their tablet.
 */
class HousekeepingLostFoundAdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['status' => 'active']);
    }

    private function unit(string $number = '101'): RoomUnit
    {
        $room = Room::create([
            'name' => 'Alba Suite',
            'slug' => 'alba-suite-'.Str::random(6),
            'type' => 'suite',
            'price' => 150000,
        ]);

        return RoomUnit::create(['room_id' => $room->id, 'number' => $number, 'status' => 'available']);
    }

    private function item(array $overrides = []): LostFoundItem
    {
        return LostFoundItem::create(array_merge([
            'room_unit_id' => $this->unit()->id,
            'item_description' => 'Blue umbrella',
            'found_at' => now(),
        ], $overrides));
    }

    public function test_it_lists_logged_items(): void
    {
        $this->item(['item_description' => 'Blue umbrella']);

        Livewire::actingAs($this->admin())
            ->test(LostFound::class)
            ->assertOk()
            ->assertSee('Blue umbrella')
            ->assertSee('Room 101');
    }

    public function test_it_filters_by_status(): void
    {
        $this->item(['item_description' => 'Blue umbrella', 'status' => LostFoundItem::UNCLAIMED]);
        $returned = $this->item(['item_description' => 'Black wallet']);
        $returned->markReturned();

        Livewire::actingAs($this->admin())
            ->test(LostFound::class)
            ->set('statusFilter', 'unclaimed')
            ->assertSee('Blue umbrella')
            ->assertDontSee('Black wallet');
    }

    public function test_it_searches_by_item_or_room(): void
    {
        $this->item(['item_description' => 'Blue umbrella']);
        $this->item(['item_description' => 'Black wallet', 'room_unit_id' => $this->unit('202')->id]);

        Livewire::actingAs($this->admin())
            ->test(LostFound::class)
            ->set('search', 'wallet')
            ->assertSee('Black wallet')
            ->assertDontSee('Blue umbrella');
    }

    public function test_an_unclaimed_item_can_be_marked_returned_with_claimant_details(): void
    {
        $item = $this->item();

        Livewire::actingAs($this->admin())
            ->test(LostFound::class)
            ->call('openReturn', $item->id)
            ->set('claimantName', 'James Anderson')
            ->set('claimantContact', '+234 800 000 0000')
            ->call('confirmReturn')
            ->assertDispatched('toast');

        $fresh = $item->fresh();
        $this->assertSame(LostFoundItem::RETURNED, $fresh->status);
        $this->assertSame('James Anderson', $fresh->claimant_name);
        $this->assertSame('+234 800 000 0000', $fresh->claimant_contact);
    }

    public function test_an_item_can_be_marked_returned_without_claimant_details(): void
    {
        $item = $this->item();

        Livewire::actingAs($this->admin())
            ->test(LostFound::class)
            ->call('openReturn', $item->id)
            ->call('confirmReturn')
            ->assertDispatched('toast');

        $this->assertSame(LostFoundItem::RETURNED, $item->fresh()->status);
    }

    public function test_an_unclaimed_item_can_be_marked_disposed(): void
    {
        $item = $this->item();

        Livewire::actingAs($this->admin())
            ->test(LostFound::class)
            ->call('markDisposed', $item->id)
            ->assertDispatched('toast');

        $this->assertSame(LostFoundItem::DISPOSED, $item->fresh()->status);
    }

    public function test_an_already_returned_item_cannot_be_reopened_for_return(): void
    {
        $item = $this->item();
        $item->markReturned();

        Livewire::actingAs($this->admin())
            ->test(LostFound::class)
            ->call('openReturn', $item->id)
            ->assertSet('showReturn', false);
    }

    public function test_it_paginates_beyond_the_first_page(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->item(['item_description' => 'Item #'.$i]);
        }

        Livewire::actingAs($this->admin())
            ->test(LostFound::class)
            ->assertSee('Showing 1–8 of 20 items');
    }
}
