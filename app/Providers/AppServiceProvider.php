<?php

namespace App\Providers;

use App\Repositories\Entrega\EntregaRepository;
use App\Repositories\Entrega\EntregaRepositoryInterface;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(EntregaRepositoryInterface::class, EntregaRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
