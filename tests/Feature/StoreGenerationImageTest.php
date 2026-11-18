<?php

namespace Tests\Feature;

use App\Jobs\StoreGenerationImage;
use App\Models\Generation;
use DateTimeInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class StoreGenerationImageTest extends TestCase
{
    use RefreshDatabase;

    private const string IMAGE_URL = 'https://pixelforge.promptedit.dev/images/gen_01.png?signature=abc';

    public function test_it_saves_a_copy_of_the_image(): void
    {
        Storage::fake();
        Http::fake([self::IMAGE_URL => Http::response('png bytes', 200, ['Content-Type' => 'image/png'])]);
        $generation = $this->succeededGeneration(expiresAt: now()->addDay());

        StoreGenerationImage::dispatchSync($generation);

        $path = "generations/{$generation->uuid}.png";
        Storage::assertExists($path, 'png bytes');
        $this->assertSame($path, $generation->fresh()->image_path);
    }

    public function test_it_does_not_download_an_image_whose_link_has_expired(): void
    {
        Storage::fake();
        Http::fake([self::IMAGE_URL => Http::response('png bytes', 200, ['Content-Type' => 'image/png'])]);
        $generation = $this->succeededGeneration(expiresAt: now()->subMinute());

        StoreGenerationImage::dispatchSync($generation);

        Http::assertNothingSent();
        $this->assertNull($generation->fresh()->image_path);
    }

    public function test_it_refuses_to_save_a_response_that_is_not_a_png(): void
    {
        Storage::fake();
        Http::fake([self::IMAGE_URL => Http::response('<html>Not found</html>', 200, ['Content-Type' => 'text/html'])]);
        $generation = $this->succeededGeneration(expiresAt: now()->addDay());

        $this->assertThrows(fn () => StoreGenerationImage::dispatchSync($generation), RuntimeException::class);

        Storage::assertMissing("generations/{$generation->uuid}.png");
        $this->assertNull($generation->fresh()->image_path);
    }

    private function succeededGeneration(DateTimeInterface $expiresAt): Generation
    {
        return Generation::factory()->succeeded()->create([
            'image_url' => self::IMAGE_URL,
            'image_url_expires_at' => $expiresAt,
        ]);
    }
}
