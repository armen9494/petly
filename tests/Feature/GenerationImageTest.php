<?php

namespace Tests\Feature;

use App\Models\Generation;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class GenerationImageTest extends TestCase
{
    use RefreshDatabase;

    private const string PIXELFORGE_URL = 'https://pixelforge.promptedit.dev/images/gen_01.png?signature=abc';

    public function test_the_owner_gets_the_saved_copy(): void
    {
        Storage::fake();
        $generation = $this->generationWithSavedImage(linkExpiresAt: now()->addDay());
        Storage::put($generation->image_path, 'png bytes');

        $this->actingAs($generation->user)
            ->get(route('generations.image', $generation))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertStreamedContent('png bytes');
    }

    public function test_other_users_get_a_404(): void
    {
        Storage::fake();
        $generation = $this->generationWithSavedImage(linkExpiresAt: now()->addDay());
        Storage::put($generation->image_path, 'png bytes');

        $this->actingAs(User::factory()->create())
            ->get(route('generations.image', $generation))
            ->assertNotFound();
    }

    public function test_guests_are_sent_to_log_in(): void
    {
        $generation = $this->generationWithSavedImage(linkExpiresAt: now()->addDay());

        $this->get(route('generations.image', $generation))->assertRedirect('/login');
    }

    public function test_it_falls_back_to_pixelforges_link_when_the_copy_is_missing(): void
    {
        Storage::fake();
        $generation = $this->generationWithSavedImage(linkExpiresAt: now()->addDay());

        $this->actingAs($generation->user)
            ->get(route('generations.image', $generation))
            ->assertRedirect(self::PIXELFORGE_URL);
    }

    public function test_it_returns_404_when_the_copy_is_missing_and_the_link_has_expired(): void
    {
        Storage::fake();
        $generation = $this->generationWithSavedImage(linkExpiresAt: now()->subMinute());

        $this->actingAs($generation->user)
            ->get(route('generations.image', $generation))
            ->assertNotFound();
    }

    public function test_the_history_shows_the_saved_copy_after_pixelforges_link_expires(): void
    {
        $generation = $this->generationWithSavedImage(linkExpiresAt: now()->subMinute());

        $this->actingAs($generation->user)->get('/')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('generations.data.0.image_url', route('generations.image', $generation))
            ->where('generations.data.0.image_expired', false));
    }

    public function test_the_history_marks_an_unsaved_image_as_expired_once_its_link_lapses(): void
    {
        $generation = Generation::factory()->succeeded()->create([
            'image_url' => self::PIXELFORGE_URL,
            'image_url_expires_at' => now()->subMinute(),
        ]);

        $this->actingAs($generation->user)->get('/')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('generations.data.0.image_url', null)
            ->where('generations.data.0.image_expired', true));
    }

    private function generationWithSavedImage(DateTimeInterface $linkExpiresAt): Generation
    {
        return Generation::factory()->succeeded()->create([
            'image_url' => self::PIXELFORGE_URL,
            'image_url_expires_at' => $linkExpiresAt,
            'image_path' => 'generations/saved.png',
        ]);
    }
}
