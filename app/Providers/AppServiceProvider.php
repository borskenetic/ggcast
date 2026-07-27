<?php

namespace App\Providers;

use App\Support\Branding;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Carbon\Carbon;

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
        // Set PHP's default timezone to Asia/Manila
        date_default_timezone_set(Config::get('app.timezone'));

        // Optional: Set Carbon locale if you use translated dates
        Carbon::setLocale(Config::get('app.locale'));

        // Hostinger / shared hosting: ensure generated asset() URLs use HTTPS in production
        // (prevents mixed-content and \"unstyled\" pages when APP_URL is mis-set to http://)
        if (app()->environment('production')) {
            URL::forceScheme('https');
        }

        View::share('brand', Branding::forViews());
    }
}
