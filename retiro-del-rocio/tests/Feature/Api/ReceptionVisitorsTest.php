<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\VisitorPass;
use App\Services\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The reception tablet's Visitor Pass screen: a read-only view of every
 * visitor invited or arrived — not scoped to today, unlike security's gate
 * queue, since reception needs to see who is coming later too.
 */
class ReceptionVisitorsTest extends TestCase
{
    use RefreshDatabase;

    private function receptionToken(): string
    {
        Role::findOrCreate('reception');
        $user = User::factory()->create(['status' => 'active', 'name' => 'Front Desk']);
        $user->assignRole('reception');

        return app(JwtService::class)->issue(['sub' => $user->id])['token'];
    }

    private function visitor(array $overrides = []): VisitorPass
    {
        $createdAt = $overrides['created_at'] ?? null;
        unset($overrides['created_at']);

        $pass = VisitorPass::create(array_merge([
            'host_name' => 'Daniel Ubani',
            'room_number' => '101',
            'suite_name' => 'Alba Suite',
            'visitor_name' => 'Michael Brown',
            'visitor_email' => 'm.brown@mail.com',
            'visitor_phone' => '+44 7700 900111',
            'code' => '482913',
            'status' => VisitorPass::PENDING,
        ], $overrides));

        // `created_at` isn't mass-assignable — it must reflect a chosen
        // invitation time for the ordering test, so it's forced after create.
        if ($createdAt) {
            $pass->forceFill(['created_at' => $createdAt])->save();
        }

        return $pass;
    }

    public function test_visitors_lists_every_pass_regardless_of_day(): void
    {
        $this->visitor([
            'visitor_name' => 'Invited Later',
            'status' => VisitorPass::PENDING,
            'created_at' => now()->addDays(2),
        ]);
        $this->visitor([
            'visitor_name' => 'Arrived Yesterday',
            'status' => VisitorPass::VERIFIED,
            'verified_at' => now()->subDay(),
            'created_at' => now()->subDays(2),
        ]);

        $this->withToken($this->receptionToken())
            ->getJson('/api/v1/reception/visitors')
            ->assertOk()
            ->assertJsonCount(2, 'data.visitors')
            ->assertJsonPath('data.visitors.0.visitor_name', 'Invited Later')
            ->assertJsonPath('data.visitors.0.status', 'pending')
            ->assertJsonPath('data.visitors.0.status_label', 'Pending')
            ->assertJsonPath('data.visitors.1.visitor_name', 'Arrived Yesterday')
            ->assertJsonPath('data.visitors.1.status', 'inside');
    }

    public function test_visitors_includes_host_room_and_contact(): void
    {
        $this->visitor();

        $this->withToken($this->receptionToken())
            ->getJson('/api/v1/reception/visitors')
            ->assertOk()
            ->assertJsonPath('data.visitors.0.host_name', 'Daniel Ubani')
            ->assertJsonPath('data.visitors.0.room_number', '101')
            ->assertJsonPath('data.visitors.0.suite_name', 'Alba Suite')
            ->assertJsonPath('data.visitors.0.email', 'm.brown@mail.com')
            ->assertJsonPath('data.visitors.0.phone', '+44 7700 900111');
    }

    public function test_status_filter_narrows_the_list(): void
    {
        $this->visitor(['visitor_name' => 'Pending Guest', 'status' => VisitorPass::PENDING]);
        $this->visitor(['visitor_name' => 'Denied Guest', 'status' => VisitorPass::DENIED, 'denied_at' => now()]);

        $this->withToken($this->receptionToken())
            ->getJson('/api/v1/reception/visitors?status=denied')
            ->assertOk()
            ->assertJsonCount(1, 'data.visitors')
            ->assertJsonPath('data.visitors.0.visitor_name', 'Denied Guest');
    }

    public function test_search_narrows_by_visitor_host_or_room(): void
    {
        $this->visitor(['visitor_name' => 'Michael Brown', 'host_name' => 'Daniel Ubani', 'room_number' => '101']);
        $this->visitor(['visitor_name' => 'Sarah Connor', 'host_name' => 'John Doe', 'room_number' => '202']);

        $this->withToken($this->receptionToken())
            ->getJson('/api/v1/reception/visitors?search=Michael')
            ->assertOk()
            ->assertJsonCount(1, 'data.visitors');

        $this->withToken($this->receptionToken())
            ->getJson('/api/v1/reception/visitors?search=John Doe')
            ->assertOk()
            ->assertJsonCount(1, 'data.visitors')
            ->assertJsonPath('data.visitors.0.visitor_name', 'Sarah Connor');

        $this->withToken($this->receptionToken())
            ->getJson('/api/v1/reception/visitors?search=202')
            ->assertOk()
            ->assertJsonCount(1, 'data.visitors')
            ->assertJsonPath('data.visitors.0.visitor_name', 'Sarah Connor');
    }

    public function test_summary_counts_expected_inside_and_today(): void
    {
        $this->visitor(['status' => VisitorPass::PENDING]);
        $this->visitor(['status' => VisitorPass::VERIFIED, 'verified_at' => now()]);

        $this->withToken($this->receptionToken())
            ->getJson('/api/v1/reception/visitors')
            ->assertOk()
            ->assertJsonPath('data.summary.expected', 1)
            ->assertJsonPath('data.summary.inside', 1)
            ->assertJsonPath('data.summary.today', 2);
    }

    public function test_visitors_requires_the_reception_role(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $token = app(JwtService::class)->issue(['sub' => $user->id])['token'];

        $this->withToken($token)
            ->getJson('/api/v1/reception/visitors')
            ->assertStatus(403);
    }
}
