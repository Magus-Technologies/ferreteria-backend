<?php

namespace App\Providers;

use App\Services\Interfaces\IngresoDineroServiceInterface;
use App\Services\Implementations\IngresoDineroService;
use App\Services\Interfaces\EgresoDineroServiceInterface;
use App\Services\Implementations\EgresoDineroService;
use App\Services\Interfaces\GananciasServiceInterface;
use App\Services\Implementations\GananciasService;
use Illuminate\Support\ServiceProvider;

class GestionContableServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Registrar servicios de gestión contable y financiera
        $this->app->bind(IngresoDineroServiceInterface::class, IngresoDineroService::class);
        $this->app->bind(EgresoDineroServiceInterface::class, EgresoDineroService::class);
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