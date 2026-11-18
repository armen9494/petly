<?php

namespace App\Providers;

use App\Services\Billing;
use App\Services\Pixelforge\PixelforgeClient;
use Illuminate\Foundation\DevCommands;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(PixelforgeClient::class, fn () => new PixelforgeClient(
            baseUrl: config('services.pixelforge.base_url'),
            apiKey: config('services.pixelforge.api_key'),
        ));

        $this->app->singleton(Billing::class, fn () => new Billing(
            markupPercent: config('services.pixelforge.markup_percent'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Locally the app is served by a web server such as Valet or Herd, so
        // `composer run dev` skips `php artisan serve`. Horizon runs the queues
        // and already hides `queue:listen`, but except() replaces that list,
        // so 'queue' is repeated here.
        DevCommands::except('server', 'queue');

        // Runs pixelforge:sync every minute, which polls for missed webhooks.
        DevCommands::artisan('schedule:work', 'scheduler');
    }
}
