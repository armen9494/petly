<?php

use App\Http\Controllers\Web\BrowserSessionController;
use App\Http\Controllers\Web\CreditController;
use App\Http\Controllers\Web\EstimateController;
use App\Http\Controllers\Web\GenerationController;
use App\Http\Controllers\Web\GenerationImageController;
use App\Http\Controllers\Web\ProfileController;
use App\Http\Controllers\Web\StudioController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('/', [StudioController::class, 'show'])->name('studio');
    Route::post('generations', [GenerationController::class, 'store'])->name('generations.store');
    Route::get('generations/{generation}/image', [GenerationImageController::class, 'show'])
        ->can('view', 'generation')
        ->name('generations.image');
    Route::get('estimate', [EstimateController::class, 'show'])->name('estimate');

    Route::get('credits', [CreditController::class, 'index'])->name('credits.index');
    Route::post('credits', [CreditController::class, 'store'])->name('credits.store');

    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('profile/sessions', [BrowserSessionController::class, 'destroyOthers'])->name('browser-sessions.destroy-others');
    Route::delete('profile/sessions/{session}', [BrowserSessionController::class, 'destroy'])
        ->where('session', '[0-9a-f]{64}')
        ->name('browser-sessions.destroy');
});
