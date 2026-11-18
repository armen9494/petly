<?php

namespace Tests\Feature;

use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Models\User;
use App\Services\CreditLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_overview_sums_the_last_30_utc_days_and_whats_running_now(): void
    {
        $this->travelTo('2026-11-17 12:00:00');
        $admin = User::factory()->admin()->create(['created_at' => '2026-01-01 00:00:00']);
        $newUser = User::factory()->create(['email' => 'new@example.com', 'created_at' => '2026-10-19 00:00:00']);
        $olderUser = User::factory()->inactive()->create(['email' => 'older@example.com', 'created_at' => '2026-10-18 23:59:59']);

        $succeeded = Generation::factory()->for($newUser)
            ->succeeded(providerCost: 100_000_000, completedAt: '2026-11-16 10:00:00')
            ->create(['created_at' => '2026-11-16 09:59:00']);
        Generation::factory()->for($olderUser)
            ->succeeded(providerCost: 40_000_000, completedAt: '2026-10-18 12:00:00')
            ->create(['created_at' => '2026-10-18 11:59:00']);
        Generation::factory()->for($newUser)->accepted(GenerationStatus::Failed)
            ->create(['created_at' => '2026-11-10 08:00:00', 'error_code' => 'generation_failed', 'error_message' => 'The model refused.']);
        Generation::factory()->for($newUser)->accepted(GenerationStatus::Processing)->create(['created_at' => '2026-11-17 11:44:59']);
        Generation::factory()->for($newUser)->create(['created_at' => '2026-11-17 11:45:00']);

        $ledger = app(CreditLedger::class);
        $ledger->purchase($newUser, 10_000_000_000);
        $ledger->charge($succeeded);

        $this->actingAs($admin)->get('/admin')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('admin/Dashboard')
            ->where('stats.from', '2026-10-19')
            ->where('stats.to', '2026-11-17')
            ->where('stats.users', ['total' => 3, 'new' => 1, 'inactive' => 1])
            ->where('stats.generations', ['succeeded' => 1, 'failed' => 1, 'in_flight' => 2, 'stuck' => 1])
            ->where('stats.spend.generations', 1)
            ->where('stats.spend.charged', '0.125000000')
            ->where('stats.spend.provider_cost', '0.100000000')
            ->where('stats.spend.profit', '0.025000000')
            ->where('stats.unspent_credits', '9.875000000')
            ->where('stuckAfterMinutes', 15)
            ->has('newestUsers', 3)
            ->where('newestUsers.0.email', 'older@example.com')
            ->has('latestFailures', 1)
            ->where('latestFailures.0.error_code', 'generation_failed')
            ->where('latestFailures.0.error_message', 'The model refused.')
            ->where('latestFailures.0.user.email', 'new@example.com'));
    }
}
