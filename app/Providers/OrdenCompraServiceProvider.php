<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

// Repository Interfaces
use App\Repositories\Interfaces\RequerimientoInternoRepositoryInterface;
use App\Repositories\Interfaces\OrdenCompraRepositoryInterface;

// Repository Implementations
use App\Repositories\Implementations\RequerimientoInternoRepository;
use App\Repositories\Implementations\OrdenCompraRepository;

// Service Interfaces
use App\Services\Interfaces\RequerimientoInternoServiceInterface;
use App\Services\Interfaces\OrdenCompraServiceInterface;

// Service Implementations
use App\Services\Implementations\RequerimientoInternoService;
use App\Services\Implementations\OrdenCompraService;

/**
 * Service Provider para Requerimientos Internos y Órdenes de Compra
 *
 * Registra las dependencias (repositories y services) para el módulo
 * de gestión comercial e inventario.
 */
class OrdenCompraServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // ========== REPOSITORIES ==========
        $this->app->bind(
            RequerimientoInternoRepositoryInterface::class,
            RequerimientoInternoRepository::class
        );

        $this->app->bind(
            OrdenCompraRepositoryInterface::class,
            OrdenCompraRepository::class
        );

        // ========== SERVICES ==========
        $this->app->bind(
            RequerimientoInternoServiceInterface::class,
            RequerimientoInternoService::class
        );

        $this->app->bind(
            OrdenCompraServiceInterface::class,
            OrdenCompraService::class
        );
    }

    public function boot(): void
    {
        //
    }
}
