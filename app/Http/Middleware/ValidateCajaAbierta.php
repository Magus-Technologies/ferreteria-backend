<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Exceptions\CajaCerradaException;
use App\Models\AperturaCierreCaja;

class ValidateCajaAbierta
{
    /**
     * Validar que el usuario tenga una caja abierta antes de realizar operaciones
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no autenticado',
            ], 401);
        }

        // Los administradores están exentos de la validación de caja abierta
        if ($user->hasRole('admin') || $user->hasRole('administrador')) {
            return $next($request);
        }

        // Verificar si el usuario tiene una caja abierta (sin importar la fecha de apertura)
        // Esto permite que las cajas permanezcan abiertas por múltiples días
        
        // Opción 1: El usuario creó la apertura y está abierta
        $cajaAbierta = AperturaCierreCaja::where('user_id', $user->id)
            ->where('estado', 'abierta')
            ->first();

        // Opción 2: El usuario tiene una distribución de efectivo en una apertura abierta
        if (!$cajaAbierta) {
            $cajaAbierta = AperturaCierreCaja::where('estado', 'abierta')
                ->whereHas('distribucionesVendedores', function ($query) use ($user) {
                    $query->where('user_id', $user->id);
                })
                ->first();
        }

        if (!$cajaAbierta) {
            throw new CajaCerradaException('Debe aperturar una caja antes de realizar esta operación');
        }

        // Agregar la caja abierta al request para uso posterior
        $request->merge(['caja_abierta' => $cajaAbierta]);

        return $next($request);
    }
}
