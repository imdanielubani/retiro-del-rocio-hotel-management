<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\Security\VisitorPasses;
use App\Models\User;
use App\Models\VisitorPass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Admin → Security → Visitor Passes register.
 */
class VisitorPassesAdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['status' => 'active']);
    }

    private function pass(array $overrides = []): VisitorPass
    {
        return VisitorPass::create(array_merge([
            'visitor_name' => 'Michael Brown',
            'host_name' => 'Daniel Ubani',
            'room_number' => '101',
            'code' => '482913',
            'status' => VisitorPass::PENDING,
        ], $overrides));
    }

    public function test_the_register_lists_passes_and_counts(): void
    {
        $this->pass(['visitor_name' => 'Michael Brown']);
        $this->pass(['visitor_name' => 'Zara Ahmed', 'code' => '222333', 'status' => VisitorPass::VERIFIED, 'verified_at' => now(), 'verified_via' => 'lock']);

        Livewire::actingAs($this->admin())
            ->test(VisitorPasses::class)
            ->assertOk()
            ->assertSee('Michael Brown')
            ->assertSee('Zara Ahmed')
            ->assertSee('Currently Inside')
            ->assertSee('Issued Today');
    }

    public function test_it_filters_by_inside(): void
    {
        $this->pass(['visitor_name' => 'Inside Guy', 'status' => VisitorPass::VERIFIED, 'verified_at' => now()]);
        $this->pass(['visitor_name' => 'Left Guy', 'code' => '222333', 'status' => VisitorPass::VERIFIED, 'verified_at' => now(), 'exited_at' => now()]);

        Livewire::actingAs($this->admin())
            ->test(VisitorPasses::class)
            ->set('status', 'inside')
            ->assertSee('Inside Guy')
            ->assertDontSee('Left Guy');
    }

    public function test_it_searches_by_visitor(): void
    {
        $this->pass(['visitor_name' => 'Alpha One']);
        $this->pass(['visitor_name' => 'Beta Two', 'code' => '222333']);

        Livewire::actingAs($this->admin())
            ->test(VisitorPasses::class)
            ->set('search', 'Beta')
            ->assertSee('Beta Two')
            ->assertDontSee('Alpha One');
    }

    public function test_revoking_cancels_an_open_pass(): void
    {
        $pass = $this->pass();

        Livewire::actingAs($this->admin())
            ->test(VisitorPasses::class)
            ->call('revoke', $pass->id)
            ->assertDispatched('toast');

        $this->assertSame('cancelled', $pass->fresh()->status);
    }

    public function test_marking_exited_records_the_departure(): void
    {
        $pass = $this->pass(['status' => VisitorPass::VERIFIED, 'verified_at' => now()]);

        Livewire::actingAs($this->admin())
            ->test(VisitorPasses::class)
            ->call('markExited', $pass->id)
            ->assertDispatched('toast');

        $this->assertNotNull($pass->fresh()->exited_at);
        $this->assertFalse($pass->fresh()->isInside());
    }

    public function test_denying_marks_the_pass_denied(): void
    {
        $pass = $this->pass();

        Livewire::actingAs($this->admin())
            ->test(VisitorPasses::class)
            ->call('deny', $pass->id)
            ->assertDispatched('toast');

        $this->assertSame('denied', $pass->fresh()->status);
    }

    public function test_viewing_opens_the_detail_drawer(): void
    {
        $pass = $this->pass(['visitor_name' => 'Sophie Anderson']);

        Livewire::actingAs($this->admin())
            ->test(VisitorPasses::class)
            ->call('view', $pass->id)
            ->assertSet('selectedId', $pass->id)
            ->assertSee('Timeline')
            ->assertSee('Sophie Anderson');
    }

    public function test_it_exports_a_csv(): void
    {
        $this->pass();

        $response = Livewire::actingAs($this->admin())
            ->test(VisitorPasses::class)
            ->call('export');

        $response->assertFileDownloaded();
    }
}
