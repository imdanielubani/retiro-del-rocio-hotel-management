<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\Chat\Index;
use App\Models\StaffMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Admin → Chat — the admin/manager's own channel with each staff station,
 * sharing the same {@see StaffMessage} table every tablet's Chat screen uses.
 */
class AdminChatTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['status' => 'active']);
    }

    public function test_it_lists_every_station_as_a_channel(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->assertViewHas('channels', fn ($channels) => count($channels) === 4)
            ->assertSee('Reception')
            ->assertSee('Housekeeping')
            ->assertSee('Maintenance')
            ->assertSee('Security');
    }

    public function test_admin_sends_a_message_to_a_department(): void
    {
        $admin = $this->admin();

        $data = Livewire::actingAs($admin)
            ->test(Index::class)
            ->call('selectRole', 'maintenance')
            ->set('body', 'The lobby AC needs looking at.')
            ->call('send');

        $data->assertHasNoErrors();

        $this->assertDatabaseHas('staff_messages', [
            'channel_key' => 'admin_maintenance',
            'sender_role' => 'admin',
            'sender_name' => $admin->name,
            'body' => 'The lobby AC needs looking at.',
        ]);
    }

    public function test_a_message_requires_a_body(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('selectRole', 'reception')
            ->set('body', '')
            ->call('send')
            ->assertHasErrors('body');
    }

    public function test_opening_a_channel_marks_the_departments_messages_read(): void
    {
        StaffMessage::create([
            'channel_key' => StaffMessage::channelKey('admin', 'security'),
            'sender_role' => 'security',
            'sender_name' => 'Officer Bello',
            'body' => 'A visitor is waiting at the gate.',
        ]);

        $component = Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->assertViewHas(
                'channels',
                fn ($channels) => collect($channels)->firstWhere('role', 'security')['unread_count'] === 1,
            );

        $component->call('selectRole', 'security');

        $this->assertSame(
            0,
            StaffMessage::where('channel_key', StaffMessage::channelKey('admin', 'security'))
                ->whereNull('read_at')
                ->count(),
        );
    }

    public function test_an_unknown_role_cannot_be_selected_or_messaged(): void
    {
        $component = Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('selectRole', 'kitchen');

        $this->assertSame('', $component->get('selectedRole'));

        $component->set('selectedRole', 'kitchen')
            ->set('body', 'Hello?')
            ->call('send');

        $this->assertDatabaseMissing('staff_messages', ['channel_key' => 'admin_kitchen']);
    }
}
