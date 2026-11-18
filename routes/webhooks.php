<?php

use App\Http\Controllers\Webhooks\PixelforgeWebhookController;
use App\Http\Middleware\VerifyPixelforgeSignature;
use Illuminate\Support\Facades\Route;

Route::post('pixelforge', [PixelforgeWebhookController::class, 'store'])
    ->middleware(VerifyPixelforgeSignature::class)
    ->name('pixelforge');
