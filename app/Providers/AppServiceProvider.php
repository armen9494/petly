<?php

namespace App\Providers;

use Illuminate\Foundation\DevCommands;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // The app is served by Laravel Valet at http://pixelforge.test,
        // so `composer run dev` doesn't need to start `php artisan serve`.
        DevCommands::except('server');
    }
}
