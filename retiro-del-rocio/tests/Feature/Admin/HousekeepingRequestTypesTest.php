<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\Housekeeping\RequestTypes;
use App\Models\HousekeepingRequest;
use App\Models\HousekeepingRequestType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Admin → Housekeeping → Request Types — the catalog behind the guest
 * tablet's Service Request tiles.
 */
class HousekeepingRequestTypesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['status' => 'active']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        // The migration itself seeds the real production catalog (towels,
        // amenities, checkout_inspection, ...) — clear it so each test starts
        // from a known, empty slate instead of colliding with those keys.
        HousekeepingRequestType::query()->delete();
    }

    private function type(array $overrides = []): HousekeepingRequestType
    {
        return HousekeepingRequestType::create(array_merge([
            'key' => 'towels',
            'label' => 'Towels',
            'icon' => 'dry_cleaning',
            'guest_visible' => true,
            'is_active' => true,
            'sort_order' => 1,
        ], $overrides));
    }

    public function test_the_catalog_lists_types(): void
    {
        $this->type(['label' => 'Towels']);

        Livewire::actingAs($this->admin())
            ->test(RequestTypes::class)
            ->assertOk()
            ->assertSee('Towels');
    }

    public function test_a_new_type_can_be_created(): void
    {
        Livewire::actingAs($this->admin())
            ->test(RequestTypes::class)
            ->set('fLabel', 'Extra Pillow')
            ->set('fIcon', 'bed')
            ->call('save')
            ->assertDispatched('toast');

        $this->assertDatabaseHas('housekeeping_request_types', [
            'label' => 'Extra Pillow',
            'key' => 'extra_pillow',
        ]);
    }

    public function test_saving_flushes_the_type_catalog_cache_so_the_change_is_immediate(): void
    {
        $this->type(['key' => 'towels', 'label' => 'Towels']);

        Livewire::actingAs($this->admin())
            ->test(RequestTypes::class)
            ->set('fLabel', 'Fresh Pillow')
            ->set('fIcon', 'bed')
            ->call('save');

        $this->assertContains('fresh_pillow', HousekeepingRequest::guestVisibleTypeKeys());
    }

    public function test_toggling_active_flips_the_flag(): void
    {
        $type = $this->type(['is_active' => true]);

        Livewire::actingAs($this->admin())
            ->test(RequestTypes::class)
            ->call('toggleActive', $type->id);

        $this->assertFalse($type->fresh()->is_active);
    }

    public function test_toggling_guest_visible_flips_the_flag(): void
    {
        $type = $this->type(['guest_visible' => true]);

        Livewire::actingAs($this->admin())
            ->test(RequestTypes::class)
            ->call('toggleGuestVisible', $type->id);

        $this->assertFalse($type->fresh()->guest_visible);
    }

    public function test_checkout_inspection_cannot_be_hidden_from_or_shown_on_the_guest_tablet(): void
    {
        $type = $this->type([
            'key' => HousekeepingRequest::CHECKOUT_INSPECTION,
            'label' => 'Checkout Inspection',
            'guest_visible' => false,
        ]);

        Livewire::actingAs($this->admin())
            ->test(RequestTypes::class)
            ->call('toggleGuestVisible', $type->id);

        $this->assertFalse($type->fresh()->guest_visible);
    }

    public function test_a_type_can_be_deleted(): void
    {
        $type = $this->type();

        Livewire::actingAs($this->admin())
            ->test(RequestTypes::class)
            ->call('delete', $type->id)
            ->assertDispatched('toast');

        $this->assertDatabaseMissing('housekeeping_request_types', ['id' => $type->id]);
    }

    public function test_checkout_inspection_cannot_be_deleted(): void
    {
        $type = $this->type([
            'key' => HousekeepingRequest::CHECKOUT_INSPECTION,
            'label' => 'Checkout Inspection',
        ]);

        Livewire::actingAs($this->admin())
            ->test(RequestTypes::class)
            ->call('delete', $type->id)
            ->assertDispatched('toast');

        $this->assertDatabaseHas('housekeeping_request_types', ['id' => $type->id]);
    }

    public function test_the_row_action_menu_offers_edit_toggle_and_delete(): void
    {
        $this->type(['key' => 'towels', 'label' => 'Towels']);

        Livewire::actingAs($this->admin())
            ->test(RequestTypes::class)
            ->assertSee('Edit')
            ->assertSee('Hide From Guest Tablet')
            ->assertSee('Deactivate')
            ->assertSee('Delete');
    }

    public function test_the_row_action_menu_hides_guest_visible_toggle_and_delete_for_checkout_inspection(): void
    {
        $this->type([
            'key' => HousekeepingRequest::CHECKOUT_INSPECTION,
            'label' => 'Checkout Inspection',
        ]);

        $html = Livewire::actingAs($this->admin())
            ->test(RequestTypes::class)
            ->assertSee('Edit')
            ->assertSee('Deactivate')
            ->html();

        $this->assertStringNotContainsString('Show On Guest Tablet', $html);
        $this->assertStringNotContainsString('Hide From Guest Tablet', $html);
        $this->assertStringNotContainsString('wire:click="delete(', $html);
    }

    public function test_it_paginates_beyond_the_first_page(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->type(['key' => 'type_'.$i, 'label' => 'Type '.$i, 'sort_order' => $i]);
        }

        $component = Livewire::actingAs($this->admin())
            ->test(RequestTypes::class)
            ->assertSee('Showing 1–8 of 20 types')
            ->assertSee('Type 0');

        $component->assertDontSee('Type 19');

        $component->call('nextPage')
            ->assertSee('Showing 9–16 of 20 types')
            ->assertSee('Type 8');

        $component->call('nextPage')
            ->assertSee('Showing 17–20 of 20 types')
            ->assertSee('Type 19');
    }

    public function test_it_filters_by_status(): void
    {
        $this->type(['key' => 'towels', 'label' => 'Towels', 'is_active' => true]);
        $this->type(['key' => 'amenities', 'label' => 'Amenities', 'is_active' => false]);

        Livewire::actingAs($this->admin())
            ->test(RequestTypes::class)
            ->set('statusFilter', 'inactive')
            ->assertSee('Amenities')
            ->assertDontSee('Towels');
    }
}
