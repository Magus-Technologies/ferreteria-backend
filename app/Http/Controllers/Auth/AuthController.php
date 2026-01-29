<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Login user and create token
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)
            ->with([
                'empresa' => function ($query) {
                    $query->select([
                        'id', 'ruc', 'razon_social', 'direccion', 'telefono', 'email',
                        'serie_ingreso', 'serie_salida', 'serie_recepcion_almacen',
                        'almacen_id', 'marca_id', 'logo'
                    ]);
                },
                'permissions:id,name',
                'roles.permissions:id,name',
            ])
            ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Las credenciales proporcionadas son incorrectas.'],
            ]);
        }

        // Obtener todos los permisos (directos + de roles)
        $allPermissions = array_unique([
            ...$user->permissions->pluck('name')->toArray(),
            ...$user->roles->flatMap->permissions->pluck('name')->toArray(),
        ]);

        // Calcular efectivo disponible del vendedor desde las distribuciones
        $efectivoDisponible = $this->calcularEfectivoVendedor($user->id);

        // Crear token
        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'image' => $user->image,
                'efectivo' => $efectivoDisponible,
                'empresa' => $user->empresa,
                'all_permissions' => $allPermissions,
            ],
            'token' => $token,
        ]);
    }

    /**
     * Get authenticated user
     */
    public function user(Request $request): JsonResponse
    {
        $user = $request->user()->load([
            'empresa' => function ($query) {
                $query->select([
                    'id', 'ruc', 'razon_social', 'direccion', 'telefono', 'email',
                    'serie_ingreso', 'serie_salida', 'serie_recepcion_almacen',
                    'almacen_id', 'marca_id', 'logo'
                ]);
            },
            'permissions:id,name',
            'roles.permissions:id,name',
        ]);

        // Obtener todos los permisos (directos + de roles)
        $allPermissions = array_unique([
            ...$user->permissions->pluck('name')->toArray(),
            ...$user->roles->flatMap->permissions->pluck('name')->toArray(),
        ]);

        // Calcular efectivo disponible del vendedor desde las distribuciones
        $efectivoDisponible = $this->calcularEfectivoVendedor($user->id);

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'image' => $user->image,
            'efectivo' => $efectivoDisponible,
            'empresa' => $user->empresa,
            'all_permissions' => $allPermissions,
        ]);
    }

    /**
     * Calcular efectivo disponible del vendedor desde las distribuciones de la apertura activa
     */
    private function calcularEfectivoVendedor(string $userId): float
    {
        // Buscar TODAS las aperturas activas donde el vendedor tenga distribuciones
        $distribuciones = \App\Models\DistribucionEfectivoVendedor::where('user_id', $userId)
            ->whereHas('aperturaCierreCaja', function ($query) {
                $query->whereNull('fecha_cierre'); // Solo aperturas activas
            })
            ->with('aperturaCierreCaja.cajaPrincipal')
            ->get();

        if ($distribuciones->isEmpty()) {
            return 0.0;
        }

        $efectivoTotal = 0;

        foreach ($distribuciones as $distribucion) {
            $apertura = $distribucion->aperturaCierreCaja;
            $cajaPrincipal = $apertura->cajaPrincipal;

            // Monto inicial de la distribución
            $montoInicial = $distribucion->monto;

            // Obtener las Cajas Chicas de esta caja principal
            $cajasChicas = \App\Models\SubCaja::where('caja_principal_id', $cajaPrincipal->id)
                ->where('tipo_caja', 'CC')
                ->pluck('id');

            if ($cajasChicas->isEmpty()) {
                $efectivoTotal += $montoInicial;
                continue;
            }

            // Calcular transacciones de efectivo del vendedor en estas Cajas Chicas
            $transacciones = \App\Models\TransaccionCaja::whereIn('sub_caja_id', $cajasChicas)
                ->where('user_id', $userId)
                ->where(function ($query) {
                    $query->whereNull('referencia_tipo')
                          ->orWhere('referencia_tipo', '!=', 'apertura');
                })
                ->get();

            $ingresos = $transacciones->where('tipo_transaccion', 'ingreso')->sum('monto');
            $egresos = $transacciones->where('tipo_transaccion', 'egreso')->sum('monto');

            $efectivoTotal += ($montoInicial + $ingresos - $egresos);
        }

        return (float) $efectivoTotal;
    }

    /**
     * Logout user (revoke token)
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Sesión cerrada exitosamente',
        ]);
    }

    /**
     * Logout from all devices
     */
    public function logoutAll(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return response()->json([
            'message' => 'Sesión cerrada en todos los dispositivos',
        ]);
    }
}
