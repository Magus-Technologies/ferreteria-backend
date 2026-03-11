<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\Interfaces\InventarioReporteServiceInterface;
use App\Services\Implementations\InventarioReporteService;

class InventarioReporteServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(InventarioReporteServiceInterface::class, InventarioReporteService::class);
    }

    public function boot(): void
    {
        //
    }
}
