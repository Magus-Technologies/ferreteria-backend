<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

// Interfaces
use App\Repositories\Interfaces\CajaPrincipalRepositoryInterface;
use App\Repositories\Interfaces\SubCajaRepositoryInterface;
use App\Repositories\Interfaces\DesplieguePagoRepositoryInterface;
use App\Repositories\Interfaces\TransaccionCajaRepositoryInterface;
use App\Services\Interfaces\CajaServiceInterface;
use App\Services\Interfaces\TransaccionServiceInterface;

// Implementations
use App\Repositories\Implementations\CajaPrincipalRepository;
use App\Repositories\Implementations\SubCajaRepository;
use App\Repositories\Implementations\DesplieguePagoRepository;
use App\Repositories\Implementations\TransaccionCajaRepository;
use App\Services\Implementations\CajaService;
use App\Services\Implementations\TransaccionService;

class CajasServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Repositories
        $this->app->bind(CajaPrincipalRepositoryInterface::class, CajaPrincipalRepository::class);
        $this->app->bind(SubCajaRepositoryInterface::class, SubCajaRepository::class);
        $this->app->bind(DesplieguePagoRepositoryInterface::class, DesplieguePagoRepository::class);
        $this->app->bind(TransaccionCajaRepositoryInterface::class, TransaccionCajaRepository::class);

        // Services
        $this->app->bind(CajaServiceInterface::class, CajaService::class);
        $this->app->bind(TransaccionServiceInterface::class, TransaccionService::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
