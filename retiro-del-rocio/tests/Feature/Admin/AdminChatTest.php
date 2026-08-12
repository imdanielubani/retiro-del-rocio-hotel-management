<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\Chat\Index;
use App\Models\StaffMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Admin → Chat — the admin/manager's own channel with each individual staff
 * member, sharing the same {@see StaffMessage} table every tablet's Chat
 * screen uses. Rewritten alongside the staff-chat individualization: a
 * channel is a (viewer, contact) user pair, not a (viewer, role) pair, so
 * two accounts holding the same role get separate threads here too.
 */
class AdminChatTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::findOrCreate('super-admin', 'web');
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('super-admin');

        return $user;
    }

    private function staff(string $role, string $name): User
    {
        Role::findOrCreate($role, 'web');
        $user = User::factory()->create(['status' => 'active', 'name' => $name]);
        $user->assignRole($role);

        return $user;
    }

    public function test_it_lists_every_reachable_staff_member_as_a_channel(): void
    {
        $this->staff('reception', 'Front Desk');
        $this->staff('housekeeping', 'Housekeeping Lead');
        $this->staff('maintenance', 'Facilities');
        $this->staff('security', 'Gate Officer');

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->assertViewHas('channels', fn ($channels) => count($channels) === 4)
            ->assertSee('Front Desk')
            ->assertSee('Housekeeping Lead')
            ->assertSee('Facilities')
            ->assertSee('Gate Officer');
    }

    public function test_admin_sends_a_message_to_an_individual_staff_member(): void
    {
        $admin = $this->admin();
        $maintenance = $this->staff('maintenance', 'Facilities');

        $data = Livewire::actingAs($admin)
            ->test(Index::class)
            ->call('selectContact', $maintenance->id)
            ->set('body', 'The lobby AC needs looking at.')
            ->call('send');

        $data->assertHasNoErrors();

        $this->assertDatabaseHas('staff_messages', [
            'channel_key' => StaffMessage::channelKey($admin->id, $maintenance->id),
            'sender_id' => $admin->id,
            'recipient_id' => $maintenance->id,
            'body' => 'The lobby AC needs looking at.',
        ]);
    }

    public function test_two_accounts_holding_the_same_role_get_separate_threads(): void
    {
        $admin = $this->admin();
        $bar1 = $this->staff('bar', 'Bar 1');
        $bar2 = $this->staff('bar', 'Bar 2');

        Livewire::actingAs($admin)->test(Index::class)
            ->call('selectContact', $bar1->id)
            ->set('body', 'Hi Bar 1')
            ->call('send');

        Livewire::actingAs($admin)->test(Index::class)
            ->call('selectContact', $bar2->id)
            ->set('body', 'Hi Bar 2')
            ->call('send');

        $this->assertDatabaseHas('staff_messages', ['channel_key' => StaffMessage::channelKey($admin->id, $bar1->id), 'body' => 'Hi Bar 1']);
        $this->assertDatabaseHas('staff_messages', ['channel_key' => StaffMessage::channelKey($admin->id, $bar2->id), 'body' => 'Hi Bar 2']);
        $this->assertDatabaseMissing('staff_messages', ['channel_key' => StaffMessage::channelKey($admin->id, $bar1->id), 'body' => 'Hi Bar 2']);
    }

    public function test_a_message_requires_a_body(): void
    {
        $reception = $this->staff('reception', 'Front Desk');

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('selectContact', $reception->id)
            ->set('body', '')
            ->call('send')
            ->assertHasErrors('body');
    }

    public function test_opening_a_channel_marks_that_persons_messages_read(): void
    {
        $admin = $this->admin();
        $security = $this->staff('security', 'Gate Officer');

        StaffMessage::create([
            'channel_key' => StaffMessage::channelKey($admin->id, $security->id),
            'sender_id' => $security->id,
            'recipient_id' => $admin->id,
            'sender_role' => 'security',
            'sender_name' => $security->name,
            'body' => 'A visitor is waiting at the gate.',
        ]);

        $component = Livewire::actingAs($admin)
            ->test(Index::class)
            ->assertViewHas(
                'channels',
                fn ($channels) => collect($channels)->firstWhere('user_id', $security->id)['unread_count'] === 1,
            );

        $component->call('selectContact', $security->id);

        $this->assertSame(
            0,
            StaffMessage::where('channel_key', StaffMessage::channelKey($admin->id, $security->id))
                ->whereNull('read_at')
                ->count(),
        );
    }

    public function test_a_user_with_no_chat_eligible_role_cannot_be_selected_or_messaged(): void
    {
        $stranger = User::factory()->create(['status' => 'active']); // no role assigned

        $component = Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('selectContact', $stranger->id);

        $this->assertNull($component->get('selectedContactId'));

        $component->set('selectedContactId', $stranger->id)
            ->set('body', 'Hello?')
            ->call('send');

        $this->assertDatabaseMissing('staff_messages', ['recipient_id' => $stranger->id]);
    }
}
