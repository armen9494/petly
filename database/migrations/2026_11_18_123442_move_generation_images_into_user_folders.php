<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Move the images saved before each user had a folder, from
     * generations/{uuid}.png to generations/{user_id}/{uuid}.png on the
     * default filesystem disk. It is safe to run again if it stops part way.
     */
    public function up(): void
    {
        DB::table('generations')
            ->whereNotNull('image_path')
            ->where('image_path', 'not like', 'generations/%/%')
            ->lazyById()
            ->each(fn (object $generation) => $this->moveImage($generation, "generations/{$generation->user_id}/{$generation->uuid}.png"));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('generations')
            ->where('image_path', 'like', 'generations/%/%')
            ->lazyById()
            ->each(fn (object $generation) => $this->moveImage($generation, "generations/{$generation->uuid}.png"));
    }

    /**
     * Move a generation's image to $path and point the generation at it.
     *
     * If the image is already at $path (an earlier run stopped between the
     * two steps), only the generation is updated. If it is at neither, the
     * generation is left as it is and keeps falling back to Pixelforge's link.
     */
    private function moveImage(object $generation, string $path): void
    {
        if (Storage::exists($generation->image_path)) {
            if (! Storage::move($generation->image_path, $path)) {
                throw new RuntimeException("Couldn't move the image for generation {$generation->id}.");
            }
        } elseif (Storage::missing($path)) {
            return;
        }

        DB::table('generations')->where('id', $generation->id)->update(['image_path' => $path]);
    }
};
