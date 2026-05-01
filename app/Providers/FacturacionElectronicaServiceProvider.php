<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

// Repository Interfaces
use App\Repositories\Interfaces\NotaDebitoRepositoryInterface;
use App\Repositories\Interfaces\NotaCreditoRepositoryInterface;
use App\Repositories\Interfaces\ComprobanteElectronicoRepositoryInterface;
use App\Repositories\Interfaces\MotivoNotaRepositoryInterface;

// Repository Implementations
use App\Repositories\Implementations\NotaDebitoRepository;
use App\Repositories\Implementations\NotaCreditoRepository;
use App\Repositories\Implementations\ComprobanteElectronicoRepository;
use App\Repositories\Implementations\MotivoNotaRepository;

// Service Interfaces
use App\Services\Interfaces\NotaDebitoServiceInterface;
use App\Services\Interfaces\NotaCreditoServiceInterface;
use App\Services\Interfaces\FacturaServiceInterface;
use App\Services\Interfaces\SunatApiServiceInterface;
use App\Services\Interfaces\XmlStorageServiceInterface;

// Service Implementations
use App\Services\Implementations\NotaDebitoService;
use App\Services\Implementations\NotaCreditoService;
use App\Services\Implementations\FacturaService;
use App\Services\SunatApiService;
use App\Services\Implementations\XmlStorageService;

/**
 * Service Provider para Facturación Electrónica
 * 
 * Registra todas las dependencias necesarias para el módulo de facturación electrónica:
 * - Nota de Débito 
 * - Nota de Crédito 
 * - Facturación (futuro)
 * - Guía de Remisión (futuro)
 */
class FacturacionElectronicaServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // ========== REPOSITORIES ==========
        $this->app->bind(NotaDebitoRepositoryInterface::class, NotaDebitoRepository::class);
        $this->app->bind(NotaCreditoRepositoryInterface::class, NotaCreditoRepository::class);
        $this->app->bind(ComprobanteElectronicoRepositoryInterface::class, ComprobanteElectronicoRepository::class);
        $this->app->bind(MotivoNotaRepositoryInterface::class, MotivoNotaRepository::class);

        // ========== SERVICES ==========
        $this->app->bind(NotaDebitoServiceInterface::class, NotaDebitoService::class);
        $this->app->bind(NotaCreditoServiceInterface::class, NotaCreditoService::class);
        $this->app->bind(FacturaServiceInterface::class, FacturaService::class);
        $this->app->bind(SunatApiServiceInterface::class, SunatApiService::class);
        $this->app->bind(XmlStorageServiceInterface::class, XmlStorageService::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
