<?php

namespace App\Providers;

use App\Services\Implementations\GananciasService;
use App\Services\Interfaces\GananciasServiceInterface;
use Illuminate\Support\ServiceProvider;

class GananciasServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(GananciasServiceInterface::class, GananciasService::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}