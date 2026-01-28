<?php

namespace App\Http\Controllers;

use App\Models\Restriction;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Controlador para el sistema de RESTRICCIONES (lista negra)
 *
 * A diferencia del sistema de permisos (lista blanca), aquí:
 * - Por defecto, todos tienen acceso a TODO
 * - Solo se guardan las RESTRICCIONES específicas
 * - El configurador muestra TODO y permite BLOQUEAR elementos
 */
class RestrictionController extends Controller
{
    /**
     * Listar todas las restricciones disponibles
     */
    public function index(): JsonResponse
    {
        $restrictions = Restriction::orderBy("name")->get();

        return response()->json([
            "data" => $restrictions,
        ]);
    }

    /**
     * Listar todos los roles con sus restricciones
     */
    public function roles(): JsonResponse
    {
        $roles = Role::with([
            "restrictions" => function ($query) {
                $query->orderBy("name");
            },
        ])
            ->orderBy("name")
            ->get();

        return response()->json([
            "data" => $roles,
        ]);
    }

    /**
     * Obtener un rol específico con sus restricciones
     */
    public function getRole($roleId): JsonResponse
    {
        $role = Role::with([
            "restrictions" => function ($query) {
                $query->orderBy("name");
            },
        ])->findOrFail($roleId);

        return response()->json([
            "data" => $role,
        ]);
    }

    /**
     * Asignar restricciones a un rol
     *
     * @param Request $request { restriction_ids: number[] }
     */
    public function assignRestrictionsToRole(
        Request $request,
        $roleId,
    ): JsonResponse {
        $request->validate([
            "restriction_ids" => "required|array",
            "restriction_ids.*" => "exists:restriction,id",
        ]);

        $role = Role::findOrFail($roleId);

        // Sincronizar restricciones (reemplaza las existentes)
        $role->restrictions()->sync($request->restriction_ids);

        return response()->json([
            "message" => "Restricciones asignadas correctamente",
            "data" => $role->load("restrictions"),
        ]);
    }

    /**
     * Toggle restricción: mostrar u ocultar una funcionalidad para un rol
     *
     * @param Request $request { permission_name: string, mostrar: bool }
     */
    public function toggleRestrictionForRole(
        Request $request,
        $roleId,
    ): JsonResponse {
        $request->validate([
            "permission_name" => "required|string",
            "mostrar" => "required|boolean",
        ]);

        $role = Role::findOrFail($roleId);
        $permissionName = $request->permission_name;
        $mostrar = $request->mostrar;

        // Buscar o crear la restricción
        $restriction = Restriction::firstOrCreate(
            ["name" => $permissionName],
            ["descripcion" => "Restricción: " . $permissionName],
        );

        if ($mostrar) {
            // Mostrar = quitar restricción
            $role->restrictions()->detach($restriction->id);
            $message = "El rol ahora PUEDE acceder a esta funcionalidad";
        } else {
            // Ocultar = agregar restricción
            if (
                !$role
                    ->restrictions()
                    ->where("restriction.id", $restriction->id)
                    ->exists()
            ) {
                $role->restrictions()->attach($restriction->id);
            }
            $message = "El rol ahora NO PUEDE acceder a esta funcionalidad";
        }

        return response()->json([
            "message" => $message,
            "data" => [
                "role" => $role->load("restrictions"),
                "restriction" => $restriction,
                "is_restricted" => !$mostrar,
            ],
        ]);
    }

    /**
     * Verificar si un usuario tiene acceso a una funcionalidad
     *
     * @param Request $request { feature: string }
     * @return JsonResponse { has_access: bool, is_restricted: bool }
     */
    public function checkAccess(Request $request, $userId): JsonResponse
    {
        $request->validate([
            "feature" => "required|string",
        ]);

        $user = User::with(["restrictions", "roles.restrictions"])->findOrFail(
            $userId,
        );
        $feature = $request->feature;

        $isRestricted = $user->isRestricted($feature);

        return response()->json([
            "data" => [
                "has_access" => !$isRestricted,
                "is_restricted" => $isRestricted,
                "feature" => $feature,
            ],
        ]);
    }
}
