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
                        'almacen_id', 'marca_id'
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

        // Crear token
        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'image' => $user->image,
                'efectivo' => (float) $user->efectivo,
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
                    'almacen_id', 'marca_id'
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

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'image' => $user->image,
            'efectivo' => (float) $user->efectivo,
            'empresa' => $user->empresa,
            'all_permissions' => $allPermissions,
        ]);
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
