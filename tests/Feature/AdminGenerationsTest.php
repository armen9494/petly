<?php

namespace Tests\Feature;

use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Fluent\AssertableJson;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AdminGenerationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_every_users_requests_newest_first_with_expected_and_actual_prices(): void
    {
        $ada = User::factory()->create(['email' => 'ada@example.com']);
        $grace = User::factory()->create(['email' => 'grace@example.com']);
        Generation::factory()->for($ada)->succeeded(providerCost: 50_000_000)->create(['image_path' => 'generations/ada.png']);
        $failed = Generation::factory()->for($grace)->accepted(GenerationStatus::Failed)
            ->create(['error_code' => 'generation_failed', 'error_message' => 'The model refused.']);

        $this->actingAs($this->admin())->get('/admin/generations')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('admin/Generations')
            ->has('generations.data', 2)
            ->has('generations.data.0', fn (AssertableJson $generation) => $generation
                ->where('user.email', 'grace@example.com')
                ->where('status', 'failed')
                ->where('estimated_min_price', '0.058162500')
                ->where('estimated_max_price', '0.086312500')
                ->where('price', null)
                ->where('pixelforge_id', $failed->pixelforge_id)
                ->where('error_code', 'generation_failed')
                ->where('error_message', 'The model refused.')
                ->missing('image_url')
                ->etc())
            ->has('generations.data.1', fn (AssertableJson $generation) => $generation
                ->where('user.email', 'ada@example.com')
                ->where('status', 'succeeded')
                ->where('estimated_min_price', '0.058162500')
                ->where('estimated_max_price', '0.086312500')
                ->where('price', '0.062500000')
                ->missing('image_url')
                ->etc()));
    }

    public function test_it_can_show_only_one_status(): void
    {
        Generation::factory()->succeeded()->create();
        $failed = Generation::factory()->accepted(GenerationStatus::Failed)->create();

        $this->actingAs($this->admin())->get('/admin/generations?status=failed')->assertInertia(fn (AssertableInertia $page) => $page
            ->has('generations.data', 1)
            ->where('generations.data.0.id', $failed->id)
            ->where('filters.status', 'failed'));
    }

    public function test_it_searches_prompts(): void
    {
        Generation::factory()->create(['prompt' => 'A lighthouse on a cliff at dawn']);
        $fox = Generation::factory()->create(['prompt' => 'A red fox asleep in the snow']);

        $this->actingAs($this->admin())->get('/admin/generations?search=FOX')->assertInertia(fn (AssertableInertia $page) => $page
            ->has('generations.data', 1)
            ->where('generations.data.0.id', $fox->id));
    }

    public function test_it_can_show_only_one_users_requests(): void
    {
        $ada = User::factory()->create(['email' => 'ada@example.com']);
        $adasRequest = Generation::factory()->for($ada)->create();
        Generation::factory()->create();

        $this->actingAs($this->admin())->get("/admin/generations?user={$ada->id}")->assertInertia(fn (AssertableInertia $page) => $page
            ->has('generations.data', 1)
            ->where('generations.data.0.id', $adasRequest->id)
            ->where('filters.user', ['id' => $ada->id, 'name' => $ada->name, 'email' => 'ada@example.com']));
    }

    public function test_filtering_by_a_user_who_does_not_exist_returns_404(): void
    {
        $this->actingAs($this->admin())->get('/admin/generations?user=999')->assertNotFound();
    }

    public function test_an_unknown_status_is_rejected(): void
    {
        $this->actingAs($this->admin())->get('/admin/generations?status=lost')->assertSessionHasErrors('status');
    }

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }
}
