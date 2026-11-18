<?php

namespace Tests\Feature;

use App\Models\Generation;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MoveGenerationImagesIntoUserFoldersTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_moves_each_saved_image_into_its_owners_folder(): void
    {
        Storage::fake();
        $flat = $this->generationWithImageAt(fn (Generation $generation) => "generations/{$generation->uuid}.png");
        $alreadyMoved = $this->generationWithImageAt(fn (Generation $generation) => "generations/{$generation->user_id}/{$generation->uuid}.png");

        $this->migration()->up();

        $newPath = "generations/{$flat->user_id}/{$flat->uuid}.png";
        Storage::assertExists($newPath, 'png bytes');
        Storage::assertMissing("generations/{$flat->uuid}.png");
        $this->assertSame($newPath, $flat->fresh()->image_path);
        $this->assertSame($alreadyMoved->image_path, $alreadyMoved->fresh()->image_path);
        Storage::assertExists($alreadyMoved->image_path, 'png bytes');
    }

    public function test_it_finishes_a_move_that_stopped_before_the_generation_was_updated(): void
    {
        Storage::fake();
        $generation = $this->generationWithImageAt(fn (Generation $generation) => "generations/{$generation->uuid}.png");
        $newPath = "generations/{$generation->user_id}/{$generation->uuid}.png";
        Storage::move($generation->image_path, $newPath);

        $this->migration()->up();

        $this->assertSame($newPath, $generation->fresh()->image_path);
    }

    public function test_it_leaves_a_generation_alone_when_its_image_is_missing(): void
    {
        Storage::fake();
        $generation = Generation::factory()->succeeded()->create(['image_path' => 'generations/lost.png']);

        $this->migration()->up();

        $this->assertSame('generations/lost.png', $generation->fresh()->image_path);
    }

    public function test_rolling_back_puts_the_images_back_in_the_shared_folder(): void
    {
        Storage::fake();
        $generation = $this->generationWithImageAt(fn (Generation $generation) => "generations/{$generation->user_id}/{$generation->uuid}.png");

        $this->migration()->down();

        $flatPath = "generations/{$generation->uuid}.png";
        Storage::assertExists($flatPath, 'png bytes');
        $this->assertSame($flatPath, $generation->fresh()->image_path);
    }

    /**
     * @param  callable(Generation): string  $path
     */
    private function generationWithImageAt(callable $path): Generation
    {
        $generation = Generation::factory()->succeeded()->create();
        $generation->forceFill(['image_path' => $path($generation)])->save();
        Storage::put($generation->image_path, 'png bytes');

        return $generation;
    }

    private function migration(): Migration
    {
        return require database_path('migrations/2026_11_18_123442_move_generation_images_into_user_folders.php');
    }
}
