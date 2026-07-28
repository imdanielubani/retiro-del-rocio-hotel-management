<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\Payment\Index;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Admin → Payments: the headline stat cards. Revenue must count a paid stay for
 * its whole lifecycle (paid → checked in → checked out), and refunds must reflect
 * money actually returned, not every cancellation.
 */
class PaymentStatsTest extends TestCase
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
            'check_in' => now()->subDays(2)->toDateString(),
            'check_out' => now()->addDays(1)->toDateString(),
            'nights' => 3,
            'guests' => 2,
            'amount' => 30000,
            'status' => 'checked_out',
            'paid_at' => now(),
        ], $overrides));
    }

    public function test_monthly_revenue_counts_paid_checked_in_and_checked_out_stays(): void
    {
        // The bug: a guest who checked in/out was dropped from revenue, zeroing it.
        $this->booking(['status' => 'checked_out', 'amount' => 40000, 'paid_at' => now()]);
        $this->booking(['status' => 'checked_in', 'amount' => 20000, 'paid_at' => now()]);
        // A pending request is not revenue.
        $this->booking(['status' => 'pending', 'amount' => 99000, 'paid_at' => null]);

        // ₦60,000 only appears as the revenue card value (the pending 99k booking
        // would push it to ₦159,000 if wrongly counted), so seeing it is proof the
        // paid stays are counted and the pending one is not.
        // The revenue card's label names the actual period it's reporting on
        // (e.g. "July 2026 Revenue"), not a generic "Monthly Revenue".
        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->assertSee(now()->format('F Y').' Revenue')
            ->assertSee('₦60,000');
    }

    public function test_refunds_card_counts_only_completed_refunds(): void
    {
        // Cancelled but never refunded → must NOT count.
        $this->booking(['status' => 'cancelled', 'amount' => 50000, 'refund_status' => 'pending', 'paid_at' => null]);
        // Actually refunded (a partial refund of 25,000).
        $this->booking(['status' => 'cancelled', 'amount' => 30000, 'refund_status' => 'completed', 'refund_amount' => 25000, 'paid_at' => null]);

        // ₦25,000 only appears as the refunds card value (the old bug counted the
        // full ₦80,000 of all cancellations), so seeing it proves the card now
        // reflects money actually returned.
        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->assertSee('Refunds Issued')
            ->assertSee('₦25,000');
    }

    public function test_the_ledger_shows_only_successful_payments_by_default(): void
    {
        $this->booking(['customer_name' => 'Paid Patty', 'status' => 'checked_out', 'paid_at' => now()]);
        $this->booking(['customer_name' => 'Pending Pete', 'status' => 'pending', 'paid_at' => null]);
        $this->booking(['customer_name' => 'Cancelled Cara', 'status' => 'cancelled', 'paid_at' => null]);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->assertSee('Paid Patty')
            ->assertDontSee('Pending Pete')      // never paid → not a transaction
            ->assertDontSee('Cancelled Cara');   // reversed → not counted as money
    }

    public function test_revenue_by_department_breaks_down_the_month(): void
    {
        $this->booking(['status' => 'checked_out', 'amount' => 50000, 'paid_at' => now()]);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->assertSee('Revenue by Department')
            ->assertSee('Rooms')
            ->assertSee('100%'); // all this month's revenue is rooms
    }

    public function test_total_transactions_counts_successful_payments_this_month(): void
    {
        $this->booking(['status' => 'checked_out', 'amount' => 20000, 'paid_at' => now()]);
        $this->booking(['status' => 'checked_in', 'amount' => 20000, 'paid_at' => now()]);
        // Never paid → not a transaction.
        $this->booking(['status' => 'pending', 'amount' => 99000, 'paid_at' => null]);

        // Anchored on the surrounding tag (not a bare "2") so it can't coincide
        // with an unrelated digit elsewhere on the page, e.g. an SVG viewBox.
        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->assertSee('Total Transactions')
            ->assertSee('>2</p>', false);
    }

    public function test_the_ledger_row_and_filtered_total_show_the_guests_actual_total_paid(): void
    {
        // ₦30,000 base + 7.5% VAT (₦2,250) = ₦32,250 actually paid by the guest.
        $this->booking(['customer_name' => 'Vat Guest', 'status' => 'checked_out', 'amount' => 30000, 'vat' => 2250, 'paid_at' => now()]);

        // The row must show the VAT-inclusive total the guest actually paid.
        // (₦30,000 alone still legitimately appears elsewhere, in the
        // VAT-exclusive "Revenue by Department" card — that's by design.)
        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->assertSee('₦32,250');
    }

    public function test_picking_a_past_month_scopes_every_headline_card_to_it(): void
    {
        // This month: one paid stay.
        $this->booking(['status' => 'checked_out', 'amount' => 40000, 'paid_at' => now()]);
        // Two months ago: a different paid stay, plus a refund processed then.
        $twoMonthsAgo = now()->subMonthsNoOverflow(2);
        $this->booking(['status' => 'checked_out', 'amount' => 70000, 'paid_at' => $twoMonthsAgo]);
        $old = $this->booking(['status' => 'cancelled', 'refund_status' => 'completed', 'refund_amount' => 15000, 'paid_at' => null]);
        $old->forceFill(['updated_at' => $twoMonthsAgo])->saveQuietly();

        // Switching the Year/Month filters to that month re-scopes the cards —
        // seeing ₦70,000 (not this month's ₦40,000) proves it, and the label
        // names the picked month rather than the generic default.
        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->set('year', (string) $twoMonthsAgo->year)
            ->set('month', (string) $twoMonthsAgo->month)
            ->assertSee($twoMonthsAgo->format('F Y').' Revenue')
            ->assertSee('₦70,000')
            ->assertDontSee('₦40,000')
            ->assertSee('₦15,000'); // the refund processed that month
    }
}
