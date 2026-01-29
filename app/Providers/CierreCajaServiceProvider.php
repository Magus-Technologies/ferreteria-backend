<?php

namespace App\Providers;

use App\Queries\CierreCaja\MovimientosCajaQuery;
use App\Repositories\Implementations\VentaRepository;
use App\Repositories\Interfaces\VentaRepositoryInterface;
use App\Services\CierreCaja\CalculadorResumenCaja;
use App\Services\CierreCaja\ClasificadorMovimientos;
use App\Services\CierreCaja\ValidadorSupervisorCaja;
use App\Services\Implementations\CierreCajaService;
use App\Services\Interfaces\CierreCajaServiceInterface;
use App\UseCases\CierreCaja\CerrarCajaUseCase;
use Illuminate\Support\ServiceProvider;

class CierreCajaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Repositories
        $this->app->bind(VentaRepositoryInterface::class, VentaRepository::class);

        // Queries
        $this->app->singleton(MovimientosCajaQuery::class);

        // Services
        $this->app->singleton(ValidadorSupervisorCaja::class);
        $this->app->singleton(ClasificadorMovimientos::class);
        $this->app->singleton(CalculadorResumenCaja::class);

        // Use Cases
        $this->app->singleton(CerrarCajaUseCase::class);

        // Facade Service
        $this->app->bind(
            CierreCajaServiceInterface::class,
            CierreCajaService::class
        );
    }
}
