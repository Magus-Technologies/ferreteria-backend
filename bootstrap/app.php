<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . "/../routes/web.php",
        api: __DIR__ . "/../routes/api.php",
        commands: __DIR__ . "/../routes/console.php",
        health: "/up",
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(
            prepend: [
                \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
            ],
        );

        $middleware->alias([
            "verified" => \App\Http\Middleware\EnsureEmailIsVerified::class,
            "producto.permissions" =>
                \App\Http\Middleware\CheckProductoPermissions::class,
            "almacen.access" =>
                \App\Http\Middleware\ValidateAlmacenAccess::class,
            "caja.abierta" =>
                \App\Http\Middleware\ValidateCajaAbierta::class,
        ]);

        // Excluir rutas de API del middleware CSRF
        $middleware->validateCsrfTokens(except: ["api/*"]);

        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Manejar errores de autenticación en API
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'No autenticado. Por favor, inicie sesión nuevamente.',
                ], 401);
            }
        });
    })
    ->create();
