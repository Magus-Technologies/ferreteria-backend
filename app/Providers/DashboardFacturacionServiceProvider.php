<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\Interfaces\DashboardFacturacionServiceInterface;
use App\Services\Implementations\DashboardFacturacionService;

class DashboardFacturacionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(DashboardFacturacionServiceInterface::class, DashboardFacturacionService::class);
    }

    public function boot(): void
    {
        //
    }
}
