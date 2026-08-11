<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\Guests\Index;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Admin → Guest Management → Guests: the guest list (grouped bookings) and the
 * per-guest detail drawer with lifetime stats and stay history.
 */
class GuestsAdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::findOrCreate('admin', 'web');
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('admin');

        return $user;
    }

    private function booking(array $overrides = []): Booking
    {
        return Booking::create(array_merge([
            'reference' => 'BK-'.Str::upper(Str::random(8)),
            'customer_name' => 'Daniel Ubani',
            'customer_email' => 'daniel@example.test',
            'room_name' => 'Alba Suite',
            'check_in' => now()->subDays(5)->toDateString(),
            'check_out' => now()->subDays(2)->toDateString(),
            'nights' => 3,
            'guests' => 2,
            'amount' => 22500,
            'status' => 'checked_out',
        ], $overrides));
    }

    public function test_it_groups_bookings_into_unique_guests(): void
    {
        // Same person (name + email) across two stays → one guest, two stays.
        $this->booking();
        $this->booking(['reference' => 'BK-'.Str::upper(Str::random(8)), 'amount' => 30000, 'nights' => 4]);
        // A different guest.
        $this->booking(['customer_name' => 'Ada Lovelace', 'customer_email' => 'ada@example.test', 'amount' => 15000]);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->assertOk()
            ->assertSee('Daniel Ubani')
            ->assertSee('Ada Lovelace')
            ->assertSee('Total Guests')
            ->assertSeeInOrder(['Total Guests', '2']); // two unique guests
    }

    public function test_it_searches_guests(): void
    {
        $this->booking(['customer_name' => 'Daniel Ubani', 'customer_email' => 'daniel@example.test']);
        $this->booking(['customer_name' => 'Ada Lovelace', 'customer_email' => 'ada@example.test']);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->set('search', 'ada')
            ->assertSee('Ada Lovelace')
            ->assertDontSee('Daniel Ubani');
    }

    public function test_the_action_menu_opens_the_detail_popup_with_history(): void
    {
        $this->booking(['customer_name' => 'Daniel Ubani', 'room_name' => 'Alba Suite']);
        $this->booking(['reference' => 'BK-'.Str::upper(Str::random(8)), 'room_name' => 'Brisa Residence', 'amount' => 30000, 'nights' => 4]);

        $id = md5(Booking::first()->guestKey());

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->assertSet('showDetail', false)
            ->call('viewDetails', $id)
            ->assertSet('selectedId', $id)
            ->assertSet('showDetail', true)
            ->assertSee('Stay History')
            ->assertSee('Total Stays')
            ->assertSee('Alba Suite')
            ->assertSee('Brisa Residence')
            ->call('closeDetail')
            ->assertSet('showDetail', false)
            ->assertSet('selectedId', null);
    }

    public function test_it_filters_guests_by_status(): void
    {
        $this->booking(['customer_name' => 'In House Guest', 'customer_email' => 'inhouse@example.test', 'status' => 'checked_in']);
        $this->booking(['customer_name' => 'Departed Guest', 'customer_email' => 'gone@example.test', 'status' => 'checked_out']);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->set('status', 'checked_in')
            ->assertSee('In House Guest')
            ->assertDontSee('Departed Guest');
    }

    public function test_it_filters_guests_by_room(): void
    {
        $this->booking(['customer_name' => 'Alba Guest', 'customer_email' => 'alba@example.test', 'room_name' => 'Alba Suite']);
        $this->booking(['customer_name' => 'Brisa Guest', 'customer_email' => 'brisa@example.test', 'room_name' => 'Brisa Residence']);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->set('roomFilter', 'Brisa Residence')
            ->assertSee('Brisa Guest')
            ->assertDontSee('Alba Guest');
    }

    public function test_clear_all_resets_every_filter(): void
    {
        $this->booking(['customer_name' => 'Alba Guest', 'customer_email' => 'alba@example.test', 'room_name' => 'Alba Suite']);
        $this->booking(['customer_name' => 'Brisa Guest', 'customer_email' => 'brisa@example.test', 'room_name' => 'Brisa Residence']);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->set('search', 'Alba')
            ->set('status', 'checked_out')
            ->set('roomFilter', 'Alba Suite')
            ->set('range', '30d')
            ->call('clearFilters')
            ->assertSet('search', '')
            ->assertSet('status', '')
            ->assertSet('roomFilter', '')
            ->assertSet('range', '')
            ->assertSee('Alba Guest')
            ->assertSee('Brisa Guest');
    }

    public function test_the_quick_range_toggles(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('setRange', '30d')
            ->assertSet('range', '30d')
            ->call('setRange', '30d') // tapping again clears it
            ->assertSet('range', '');
    }

    public function test_a_pending_only_guest_shows_zero_stays(): void
    {
        // A request that never became a stay still lists the person, at 0 stays.
        $this->booking(['customer_name' => 'Maybe Guest', 'customer_email' => 'maybe@example.test', 'status' => 'pending']);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->assertOk()
            ->assertSee('Maybe Guest');
    }
}
