<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\StayHistory\Index;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Admin → Guest Management → Stay History: the chronological record of real
 * stays (paid, in-house, completed) with its filters and stat cards.
 */
class StayHistoryAdminTest extends TestCase
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
            'room_name' => 'Alba Suite',
            'check_in' => now()->subDays(5)->toDateString(),
            'check_out' => now()->subDays(2)->toDateString(),
            'nights' => 3,
            'guests' => 2,
            'amount' => 22500,
            'status' => 'checked_out',
        ], $overrides));
    }

    public function test_it_lists_real_stays_and_excludes_requests_and_cancellations(): void
    {
        $this->booking(['customer_name' => 'Stayed Guest', 'status' => 'checked_out']);
        $this->booking(['customer_name' => 'Pending Guest', 'status' => 'pending']);
        $this->booking(['customer_name' => 'Cancelled Guest', 'status' => 'cancelled']);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->assertOk()
            ->assertSee('Stayed Guest')
            ->assertDontSee('Pending Guest')
            ->assertDontSee('Cancelled Guest')
            ->assertSee('Total Stays')
            ->assertSee('Total Nights');
    }

    public function test_it_totals_nights_and_revenue_across_stays(): void
    {
        $this->booking(['nights' => 3, 'amount' => 22500]);
        $this->booking(['reference' => 'BK-'.Str::upper(Str::random(8)), 'nights' => 2, 'amount' => 15000]);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->assertSee('₦37,500')  // 22,500 + 15,000
            ->assertSee('5');       // 3 + 2 nights
    }

    public function test_it_filters_by_status(): void
    {
        $this->booking(['customer_name' => 'In House Guest', 'status' => 'checked_in']);
        $this->booking(['customer_name' => 'Completed Guest', 'status' => 'checked_out']);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->set('status', 'checked_in')
            ->assertSee('In House Guest')
            ->assertDontSee('Completed Guest');
    }

    public function test_it_searches_by_guest_or_room(): void
    {
        $this->booking(['customer_name' => 'Daniel Ubani', 'room_name' => 'Alba Suite']);
        $this->booking(['customer_name' => 'Ada Lovelace', 'room_name' => 'Brisa Residence']);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->set('search', 'Brisa')
            ->assertSee('Ada Lovelace')
            ->assertDontSee('Daniel Ubani');
    }

    public function test_it_filters_by_room(): void
    {
        $this->booking(['customer_name' => 'Alba Guest', 'room_name' => 'Alba Suite']);
        $this->booking(['customer_name' => 'Brisa Guest', 'room_name' => 'Brisa Residence']);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->set('roomFilter', 'Brisa Residence')
            ->assertSee('Brisa Guest')
            ->assertDontSee('Alba Guest');
    }

    public function test_the_quick_range_narrows_stays_and_toggles(): void
    {
        // A stay that started 40 days ago falls outside "Last 30 days".
        $this->booking(['customer_name' => 'Recent Guest', 'check_in' => now()->subDays(3)->toDateString(), 'check_out' => now()->toDateString()]);
        $this->booking(['customer_name' => 'Old Guest', 'check_in' => now()->subDays(40)->toDateString(), 'check_out' => now()->subDays(37)->toDateString()]);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('setRange', '30d')
            ->assertSet('range', '30d')
            ->assertSee('Recent Guest')
            ->assertDontSee('Old Guest')
            ->call('setRange', '30d') // tapping again clears it
            ->assertSet('range', '')
            ->assertSee('Old Guest');
    }

    public function test_clear_all_resets_every_filter(): void
    {
        $this->booking(['customer_name' => 'Alba Guest', 'room_name' => 'Alba Suite']);
        $this->booking(['customer_name' => 'Brisa Guest', 'room_name' => 'Brisa Residence']);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->set('search', 'Alba')
            ->set('status', 'checked_in')
            ->set('roomFilter', 'Alba Suite')
            ->set('range', '7d')
            ->call('clearFilters')
            ->assertSet('search', '')
            ->assertSet('status', '')
            ->assertSet('roomFilter', '')
            ->assertSet('range', '')
            ->assertSee('Alba Guest')
            ->assertSee('Brisa Guest');
    }
}
