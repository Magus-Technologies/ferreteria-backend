<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo(Request $request): ?string
    {
        // Para rutas API, no redirigir, dejar que se lance la excepción
        if ($request->expectsJson() || $request->is('api/*')) {
            return null;
        }

        // Para rutas web (si las hubiera), redirigir al login
        return route('login');
    }
}
