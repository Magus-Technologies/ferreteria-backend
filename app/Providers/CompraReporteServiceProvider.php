<?php

namespace App\Providers;

use App\Services\Implementations\CompraReporteService;
use App\Services\Interfaces\CompraReporteServiceInterface;
use Illuminate\Support\ServiceProvider;

class CompraReporteServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CompraReporteServiceInterface::class, CompraReporteService::class);
    }

    public function boot(): void
    {
        //
    }
}
