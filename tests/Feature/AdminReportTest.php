<?php

namespace Tests\Feature;

use App\Models\Generation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AdminReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_reports_spend_cost_and_profit_reconciled_with_pixelforge(): void
    {
        $this->travelTo('2026-11-17 12:00:00');
        $alice = User::factory()->create(['email' => 'alice@example.com']);
        $bob = User::factory()->create(['email' => 'bob@example.com']);

        Generation::factory()->for($alice)->succeeded(providerCost: 100_000_000, completedAt: '2026-11-16 23:59:59')->create();
        Generation::factory()->for($bob)->succeeded(providerCost: 69_080_000, completedAt: '2026-11-16 08:00:00')->create();
        Generation::factory()->for($alice)->succeeded(providerCost: 40_000_000, completedAt: '2026-11-17 00:00:00')->create();
        Generation::factory()->for($alice)->accepted()->create();

        Http::fake(['*/v1/usage*' => Http::response($this->usage([
            '2026-11-16' => [2, '0.169080000'],
            '2026-11-17' => [1, '0.040000001'],
        ]))]);

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get('/admin/report?from=2026-11-16&to=2026-11-17')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/Report')
                ->where('report.total.generations', 3)
                ->where('report.total.provider_cost', '0.209080000')
                ->where('report.total.charged', '0.261350000')
                ->where('report.total.profit', '0.052270000')
                ->where('report.days.0.date', '2026-11-17')
                ->where('report.days.1.date', '2026-11-16')
                ->where('report.days.1.provider_cost', '0.169080000')
                ->where('report.days.1.pixelforge.cost', '0.169080000')
                ->where('report.days.0.pixelforge.cost_nanos', 40_000_001)
                ->where('report.users.0.user.email', 'alice@example.com')
                ->where('report.users.0.generations', 2)
                ->has('report.days_by_user', 3));

        Http::assertSent(fn ($request) => $request['start_date'] === '2026-11-16' && $request['end_date'] === '2026-11-17');
    }

    public function test_it_still_renders_when_pixelforge_is_down(): void
    {
        Http::fake(['*/v1/usage*' => Http::response(['error' => ['code' => 'overloaded', 'message' => 'Overloaded.']], 503)]);

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get('/admin/report')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('report.total.pixelforge', null)
                ->where('report.usage_error', "Couldn't load Pixelforge's usage report: Overloaded."));
    }

    public function test_only_admins_can_see_it(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin/report')->assertForbidden();
    }

    public function test_the_range_is_limited_to_what_pixelforge_reports(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get('/admin/report?from=2026-01-01&to=2026-11-17')
            ->assertSessionHasErrors('from');
    }

    public function test_it_defaults_to_the_last_30_utc_days(): void
    {
        $this->travelTo('2026-11-17 12:00:00');
        Http::fake(['*/v1/usage*' => Http::response($this->usage([]))]);

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get('/admin/report')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('from', '2026-10-19')
                ->where('to', '2026-11-17')
                ->has('report.days', 30));

        Http::assertSent(fn ($request) => $request['start_date'] === '2026-10-19' && $request['end_date'] === '2026-11-17');
    }

    public function test_it_rejects_a_range_that_ends_before_it_starts(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get('/admin/report?from=2026-11-17&to=2026-11-16')
            ->assertSessionHasErrors(['from' => 'Choose a range of 1 to 92 days.']);
    }

    /**
     * @param  array<string, array{int, string}>  $days
     * @return array<string, mixed>
     */
    private function usage(array $days): array
    {
        return [
            'object' => 'usage',
            'data' => collect($days)->map(fn (array $day, string $date) => [
                'date' => $date,
                'generations' => $day[0],
                'input_text_tokens' => 0,
                'input_image_tokens' => 0,
                'output_tokens' => 0,
                'reasoning_tokens' => 0,
                'cost_usd' => $day[1],
            ])->values()->all(),
        ];
    }
}
