<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\Security\VisitorAccessLog;
use App\Models\User;
use App\Models\VisitorPass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Admin → Security → Visitor Access log (gate entries, exits & denials).
 */
class VisitorAccessLogTest extends TestCase
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

    public function test_it_lists_only_passes_with_an_access_decision(): void
    {
        // Entered (verified), denied, and a still-pending one that never accessed.
        $this->pass(['visitor_name' => 'Entered Guy', 'status' => VisitorPass::VERIFIED, 'verified_at' => now(), 'verified_via' => 'lock']);
        $this->pass(['visitor_name' => 'Denied Guy', 'code' => '222333', 'status' => VisitorPass::DENIED, 'denied_at' => now()]);
        $this->pass(['visitor_name' => 'Pending Guy', 'code' => '333444']);

        Livewire::actingAs($this->admin())
            ->test(VisitorAccessLog::class)
            ->assertOk()
            ->assertSee('Entered Guy')
            ->assertSee('Denied Guy')
            ->assertDontSee('Pending Guy')
            ->assertSee('Entered Today')
            ->assertSee('Currently Inside');
    }

    public function test_it_filters_by_outcome(): void
    {
        $this->pass(['visitor_name' => 'By Lock', 'status' => VisitorPass::VERIFIED, 'verified_at' => now(), 'verified_via' => 'lock']);
        $this->pass(['visitor_name' => 'By Keypad', 'code' => '222333', 'status' => VisitorPass::VERIFIED, 'verified_at' => now(), 'verified_via' => 'keypad']);

        Livewire::actingAs($this->admin())
            ->test(VisitorAccessLog::class)
            ->set('method', 'lock')
            ->assertSee('By Lock')
            ->assertDontSee('By Keypad');
    }

    public function test_it_marks_a_visitor_exited(): void
    {
        $pass = $this->pass(['status' => VisitorPass::VERIFIED, 'verified_at' => now()->subHour(), 'verified_via' => 'keypad']);

        Livewire::actingAs($this->admin())
            ->test(VisitorAccessLog::class)
            ->call('markExited', $pass->id)
            ->assertDispatched('toast');

        $this->assertNotNull($pass->fresh()->exited_at);
    }

    public function test_it_exports_a_csv(): void
    {
        $this->pass(['status' => VisitorPass::VERIFIED, 'verified_at' => now(), 'verified_via' => 'lock']);

        $response = Livewire::actingAs($this->admin())
            ->test(VisitorAccessLog::class)
            ->call('export');

        $response->assertFileDownloaded();
    }
}
