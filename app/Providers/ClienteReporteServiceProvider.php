<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\Interfaces\ClienteReporteServiceInterface;
use App\Services\Implementations\ClienteReporteService;

class ClienteReporteServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ClienteReporteServiceInterface::class, ClienteReporteService::class);
    }

    public function boot(): void
    {
        //
    }
}
