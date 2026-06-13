<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\Interfaces\DashboardContableServiceInterface;
use App\Services\Implementations\DashboardContableService;

class DashboardContableServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(DashboardContableServiceInterface::class, DashboardContableService::class);
    }

    public function boot(): void
    {
        //
    }
}
