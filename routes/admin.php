<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\GenerationController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [DashboardController::class, 'show'])->name('dashboard');
    Route::resource('users', UserController::class)->only(['index', 'show', 'update']);
    Route::get('generations', [GenerationController::class, 'index'])->name('generations.index');
    Route::get('report', [ReportController::class, 'show'])->name('report');
});
