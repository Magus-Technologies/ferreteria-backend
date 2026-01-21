<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

// Interfaces
use App\Repositories\Interfaces\CajaPrincipalRepositoryInterface;
use App\Repositories\Interfaces\SubCajaRepositoryInterface;
use App\Repositories\Interfaces\DesplieguePagoRepositoryInterface;
use App\Repositories\Interfaces\TransaccionCajaRepositoryInterface;
use App\Repositories\Interfaces\AperturaCierreCajaRepositoryInterface;
use App\Repositories\Interfaces\PrestamoEntreCajasRepositoryInterface;
use App\Services\Interfaces\CajaServiceInterface;
use App\Services\Interfaces\TransaccionServiceInterface;
use App\Services\Interfaces\CierreCajaServiceInterface;
use App\Services\Interfaces\PrestamoEntreCajasServiceInterface;

// Implementations
use App\Repositories\Implementations\CajaPrincipalRepository;
use App\Repositories\Implementations\SubCajaRepository;
use App\Repositories\Implementations\DesplieguePagoRepository;
use App\Repositories\Implementations\TransaccionCajaRepository;
use App\Repositories\Implementations\AperturaCierreCajaRepository;
use App\Repositories\Implementations\PrestamoEntreCajasRepository;
use App\Services\Implementations\CajaService;
use App\Services\Implementations\TransaccionService;
use App\Services\Implementations\CierreCajaService;
use App\Services\Implementations\PrestamoEntreCajasService;

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
        $this->app->bind(AperturaCierreCajaRepositoryInterface::class, AperturaCierreCajaRepository::class);
        $this->app->bind(PrestamoEntreCajasRepositoryInterface::class, PrestamoEntreCajasRepository::class);

        // Services
        $this->app->bind(CajaServiceInterface::class, CajaService::class);
        $this->app->bind(TransaccionServiceInterface::class, TransaccionService::class);
        $this->app->bind(CierreCajaServiceInterface::class, CierreCajaService::class);
        $this->app->bind(PrestamoEntreCajasServiceInterface::class, PrestamoEntreCajasService::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
