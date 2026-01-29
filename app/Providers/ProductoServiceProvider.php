<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Contracts\ProductoServiceInterface;
use App\Contracts\ProductoImportServiceInterface;
use App\Contracts\ProductoFileServiceInterface;
use App\Contracts\ProductoValidationServiceInterface;
use App\Contracts\ProductoPriceServiceInterface;
use App\Services\Producto\ProductoService;
use App\Services\Producto\ProductoImportService;
use App\Services\Producto\ProductoFileService;
use App\Services\Producto\ProductoValidationService;
use App\Services\Producto\ProductoPriceService;
use App\Services\Cache\ProductoCacheService;

class ProductoServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register(): void
    {
        // Bind ProductoCacheService as singleton
        $this->app->singleton(ProductoCacheService::class, function ($app) {
            return new ProductoCacheService();
        });

        // Bind ProductoService
        $this->app->bind(ProductoServiceInterface::class, ProductoService::class);

        // Bind ProductoImportService
        $this->app->bind(ProductoImportServiceInterface::class, ProductoImportService::class);

        // Bind ProductoFileService
        $this->app->bind(ProductoFileServiceInterface::class, ProductoFileService::class);

        // Bind ProductoValidationService
        $this->app->bind(ProductoValidationServiceInterface::class, ProductoValidationService::class);

        // Bind ProductoPriceService
        $this->app->bind(ProductoPriceServiceInterface::class, ProductoPriceService::class);
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot(): void
    {
        //
    }
}
