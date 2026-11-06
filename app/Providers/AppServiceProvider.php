<?php

namespace App\Providers;

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
        // El CRM está solo en español, aunque el .env traiga otro idioma.
        config(['app.locale' => 'es']);
        app()->setLocale('es');
    }
}
