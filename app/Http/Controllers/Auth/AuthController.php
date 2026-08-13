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
                        'id', 'ruc', 'razon_social', 'telefono', 'email',
                        'serie_ingreso', 'serie_salida', 'serie_recepcion_almacen',
                        'almacen_id', 'marca_id', 'logo'
                    ]);
                },
                'vehiculo:id,name,tipo,placa',
                'restrictions:id,name',
                'roles.restrictions:id,name',
            ])
            ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Las credenciales proporcionadas son incorrectas.'],
            ]);
        }

        // Obtener todas las restricciones (directas + de roles)
        $allRestrictions = array_unique([
            ...$user->restrictions->pluck('name')->toArray(),
            ...$user->roles->flatMap->restrictions->pluck('name')->toArray(),
        ]);

        // Calcular efectivo disponible del vendedor desde las distribuciones
        $efectivoDisponible = $this->calcularEfectivoVendedor($user->id);

        // Vistas que requieren autorización (acceso) para el rol y las ya otorgadas
        $accesos = $this->accesosDeUsuario($user);

        // Crear token
        $token = $user->createToken('auth-token')->plainTextToken;

        // Obtener el ID del cargo del usuario y si es raíz (parent=null)
        $cargoId = null;
        $esRootCargo = false;
        if ($user->cargo) {
            $catalogoCargo = \App\Models\CatalogoCargo::whereRaw('LOWER(descripcion) = ?', [strtolower($user->cargo)])
                ->first();
            $cargoId = $catalogoCargo?->id;
            $esRootCargo = $catalogoCargo && $catalogoCargo->parent === null;
        }

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'image' => $user->image,
                'efectivo' => $efectivoDisponible,
                'empresa' => $user->empresa,
                'all_restrictions' => $allRestrictions,
                'auth_required' => $accesos['required'],
                'auth_granted' => $accesos['granted'],
                'rol_sistema' => $user->rol_sistema,
                'role_name' => $user->roles->first()?->name,
                'cargo' => $user->cargo,
                'cargo_id' => $cargoId,
                'es_root_cargo' => $esRootCargo,
                'vehiculo_id' => $user->vehiculo_id,
                'vehiculo' => $user->vehiculo,
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
                    'id', 'ruc', 'razon_social', 'telefono', 'email',
                    'serie_ingreso', 'serie_salida', 'serie_recepcion_almacen',
                    'almacen_id', 'marca_id', 'logo'
                ]);
            },
            'vehiculo:id,name,tipo,placa',
            'restrictions:id,name',
            'roles.restrictions:id,name',
        ]);

        // Obtener todas las restricciones (directas + de roles)
        $allRestrictions = array_unique([
            ...$user->restrictions->pluck('name')->toArray(),
            ...$user->roles->flatMap->restrictions->pluck('name')->toArray(),
        ]);

        // Calcular efectivo disponible del vendedor desde las distribuciones
        $efectivoDisponible = $this->calcularEfectivoVendedor($user->id);

        // Vistas que requieren autorización (acceso) para el rol y las ya otorgadas
        $accesos = $this->accesosDeUsuario($user);

        // Obtener el ID del cargo del usuario y si es raíz (parent=null)
        $cargoId = null;
        $esRootCargo = false;
        if ($user->cargo) {
            $catalogoCargo = \App\Models\CatalogoCargo::whereRaw('LOWER(descripcion) = ?', [strtolower($user->cargo)])
                ->first();
            $cargoId = $catalogoCargo?->id;
            $esRootCargo = $catalogoCargo && $catalogoCargo->parent === null;
        }

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'image' => $user->image,
            'efectivo' => $efectivoDisponible,
            'empresa' => $user->empresa,
            'all_restrictions' => $allRestrictions,
            'auth_required' => $accesos['required'],
            'auth_granted' => $accesos['granted'],
            'rol_sistema' => $user->rol_sistema,
            'role_name' => $user->roles->first()?->name,
            'cargo' => $user->cargo,
            'cargo_id' => $cargoId,
            'es_root_cargo' => $esRootCargo,
            'vehiculo_id' => $user->vehiculo_id,
            'vehiculo' => $user->vehiculo,
        ]);
    }

    /**
     * Vistas/elementos que requieren autorización de acceso para el usuario.
     *  - required: módulos (componentId) con config accion='acceso' activa para sus roles
     *  - granted:  módulos con una autorización otorgada vigente para el usuario
     */
    private function accesosDeUsuario(User $user): array
    {
        $roleIds = $user->roles->pluck('id')->toArray();

        $required = empty($roleIds) ? [] : \App\Models\AutorizacionConfig::whereIn('role_id', $roleIds)
            ->where('accion', 'acceso')
            ->where('requiere_autorizacion', true)
            ->pluck('modulo')
            ->unique()
            ->values()
            ->toArray();

        $granted = \App\Models\AutorizacionOtorgada::where('user_id', $user->id)
            ->where('accion', 'acceso')
            ->activas()
            ->pluck('modulo')
            ->unique()
            ->values()
            ->toArray();

        return ['required' => $required, 'granted' => $granted];
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
                ->sinFilasBase()
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
        // Limpiar el token FCM antes de cerrar sesión
        $user = $request->user();
        if ($user) {
            User::where('id', $user->id)->update([
                'fcm_token' => null,
            ]);
        }
        
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
