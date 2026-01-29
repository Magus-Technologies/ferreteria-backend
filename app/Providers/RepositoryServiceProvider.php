<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

// Interfaces
use App\Repositories\Interfaces\ProductoRepositoryInterface;
use App\Repositories\Interfaces\ProductoAlmacenRepositoryInterface;
use App\Repositories\Interfaces\ProductoPrecioRepositoryInterface;
use App\Repositories\Interfaces\CatalogRepositoryInterface;

// Implementations
use App\Repositories\Implementations\ProductoRepository;
use App\Repositories\Implementations\ProductoAlmacenRepository;
use App\Repositories\Implementations\ProductoPrecioRepository;
use App\Repositories\Implementations\CatalogRepository;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * All of the container bindings that should be registered.
     *
     * @var array
     */
    public array $bindings = [
        ProductoRepositoryInterface::class => ProductoRepository::class,
        ProductoAlmacenRepositoryInterface::class => ProductoAlmacenRepository::class,
        ProductoPrecioRepositoryInterface::class => ProductoPrecioRepository::class,
        CatalogRepositoryInterface::class => CatalogRepository::class,
    ];

    /**
     * Register services.
     */
    public function register(): void
    {
        // Bind ProductoRepository
        $this->app->bind(
            ProductoRepositoryInterface::class,
            ProductoRepository::class
        );

        // Bind ProductoAlmacenRepository
        $this->app->bind(
            ProductoAlmacenRepositoryInterface::class,
            ProductoAlmacenRepository::class
        );

        // Bind ProductoPrecioRepository
        $this->app->bind(
            ProductoPrecioRepositoryInterface::class,
            ProductoPrecioRepository::class
        );

        // Bind CatalogRepository
        $this->app->bind(
            CatalogRepositoryInterface::class,
            CatalogRepository::class
        );
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
