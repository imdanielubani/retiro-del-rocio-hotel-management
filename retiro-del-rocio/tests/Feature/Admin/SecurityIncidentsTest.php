<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\Security\Incidents;
use App\Models\SosAlert;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Admin → Security → SOS Incident Register.
 */
class SecurityIncidentsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['status' => 'active']);
    }

    private function alert(array $overrides = []): SosAlert
    {
        return SosAlert::create(array_merge([
            'room_number' => '101',
            'suite_name' => 'Alba Suite',
            'guest_name' => 'James Anderson',
            'status' => SosAlert::ACTIVE,
            'raised_at' => now(),
        ], $overrides));
    }

    public function test_the_register_lists_incidents(): void
    {
        $this->alert(['room_number' => '101', 'guest_name' => 'James Anderson']);

        Livewire::actingAs($this->admin())
            ->test(Incidents::class)
            ->assertOk()
            ->assertSee('Room 101')
            ->assertSee('James Anderson')
            ->assertSee('Avg Response');
    }

    public function test_it_filters_by_status(): void
    {
        // Both closed, so neither appears in the live "open incidents" strip and
        // the assertion is purely about the filtered table.
        $this->alert(['room_number' => '202', 'status' => SosAlert::RESOLVED, 'resolved_at' => now()]);
        $this->alert(['room_number' => '303', 'status' => SosAlert::CANCELLED, 'cancelled_at' => now()]);

        Livewire::actingAs($this->admin())
            ->test(Incidents::class)
            ->set('status', 'resolved')
            ->assertSee('Room 202')
            ->assertDontSee('Room 303');
    }

    public function test_it_searches_by_room(): void
    {
        $this->alert(['room_number' => '202', 'status' => SosAlert::RESOLVED, 'resolved_at' => now()]);
        $this->alert(['room_number' => '303', 'status' => SosAlert::RESOLVED, 'resolved_at' => now()]);

        Livewire::actingAs($this->admin())
            ->test(Incidents::class)
            ->set('search', '303')
            ->assertSee('Room 303')
            ->assertDontSee('Room 202');
    }

    public function test_a_new_sos_broadcast_raises_a_toast(): void
    {
        $alert = $this->alert(['room_number' => '101']);

        Livewire::actingAs($this->admin())
            ->test(Incidents::class)
            ->call('onSosChanged', ['id' => $alert->id, 'status' => 'active'])
            ->assertDispatched('toast');
    }

    public function test_the_response_time_is_shown(): void
    {
        $this->alert([
            'room_number' => '101',
            'status' => SosAlert::ACKNOWLEDGED,
            'raised_at' => now()->subSeconds(90),
            'acknowledged_at' => now(),
        ]);

        Livewire::actingAs($this->admin())
            ->test(Incidents::class)
            ->assertSee('1m 30s');
    }

    public function test_it_exports_a_csv(): void
    {
        $this->alert(['room_number' => '101']);

        $response = Livewire::actingAs($this->admin())
            ->test(Incidents::class)
            ->call('export');

        $response->assertFileDownloaded();
    }
}
