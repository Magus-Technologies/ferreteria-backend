<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PermissionController extends Controller
{
    /**
     * Listar todos los permisos
     */
    public function index(): JsonResponse
    {
        $permissions = Permission::orderBy('name')->get();
        
        return response()->json([
            'data' => $permissions,
        ]);
    }

    /**
     * Listar todos los roles con sus permisos
     */
    public function roles(): JsonResponse
    {
        $roles = Role::with(['permissions' => function ($query) {
            $query->orderBy('name');
        }])->orderBy('name')->get();
        
        return response()->json([
            'data' => $roles,
        ]);
    }

    /**
     * Obtener un rol específico con sus permisos
     */
    public function getRole($roleId): JsonResponse
    {
        $role = Role::with(['permissions' => function ($query) {
            $query->orderBy('name');
        }])->findOrFail($roleId);
        
        return response()->json([
            'data' => $role,
        ]);
    }

    /**
     * Crear un nuevo rol
     */
    public function createRole(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|unique:role,name|max:255',
            'descripcion' => 'required|string|max:255',
        ]);

        $role = Role::create([
            'name' => $request->name,
            'descripcion' => $request->descripcion,
        ]);

        return response()->json([
            'data' => $role,
            'message' => 'Rol creado correctamente',
        ], 201);
    }

    /**
     * Actualizar un rol
     */
    public function updateRole(Request $request, $roleId): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:role,name,' . $roleId,
            'descripcion' => 'required|string|max:255',
        ]);

        $role = Role::findOrFail($roleId);
        $role->update([
            'name' => $request->name,
            'descripcion' => $request->descripcion,
        ]);

        return response()->json([
            'data' => $role,
            'message' => 'Rol actualizado correctamente',
        ]);
    }

    /**
     * Eliminar un rol
     */
    public function deleteRole($roleId): JsonResponse
    {
        $role = Role::findOrFail($roleId);
        
        // No permitir eliminar admin_global
        if ($role->name === 'admin_global') {
            return response()->json([
                'message' => 'No se puede eliminar el rol de administrador global',
            ], 403);
        }

        $role->delete();

        return response()->json([
            'message' => 'Rol eliminado correctamente',
        ]);
    }

    /**
     * Asignar permisos a un usuario (permisos directos)
     */
    public function assignToUser(Request $request, $userId): JsonResponse
    {
        $request->validate([
            'permission_ids' => 'required|array',
            'permission_ids.*' => 'exists:permission,id',
        ]);

        $user = User::findOrFail($userId);
        
        // Sincronizar permisos directos
        $user->permissions()->sync($request->permission_ids);

        return response()->json([
            'message' => 'Permisos asignados correctamente',
        ]);
    }

    /**
     * Asignar roles a un usuario
     */
    public function assignRoleToUser(Request $request, $userId): JsonResponse
    {
        $request->validate([
            'role_ids' => 'required|array',
            'role_ids.*' => 'exists:role,id',
        ]);

        $user = User::findOrFail($userId);
        
        // Sincronizar roles
        $user->roles()->sync($request->role_ids);

        return response()->json([
            'message' => 'Roles asignados correctamente',
        ]);
    }

    /**
     * Asignar permisos a un rol
     */
    public function assignToRole(Request $request, $roleId): JsonResponse
    {
        $request->validate([
            'permission_ids' => 'required|array',
            'permission_ids.*' => 'exists:permission,id',
        ]);

        $role = Role::findOrFail($roleId);
        
        // Sincronizar permisos del rol
        $role->permissions()->sync($request->permission_ids);

        return response()->json([
            'message' => 'Permisos asignados al rol correctamente',
        ]);
    }

    /**
     * Obtener permisos de un usuario (directos + de roles)
     */
    public function userPermissions($userId): JsonResponse
    {
        $user = User::with([
            'permissions' => function ($query) {
                $query->orderBy('name');
            },
            'roles.permissions' => function ($query) {
                $query->orderBy('name');
            },
            'roles' => function ($query) {
                $query->orderBy('name');
            }
        ])->findOrFail($userId);

        // Obtener permisos únicos de roles
        $rolePermissions = $user->roles->flatMap->permissions->unique('id')->values();

        // Obtener IDs únicos de todos los permisos
        $allPermissionIds = array_unique([
            ...$user->permissions->pluck('id')->toArray(),
            ...$rolePermissions->pluck('id')->toArray(),
        ]);

        return response()->json([
            'data' => [
                'direct_permissions' => $user->permissions,
                'role_permissions' => $rolePermissions,
                'roles' => $user->roles,
                'all_permission_ids' => $allPermissionIds,
            ],
        ]);
    }

    /**
     * Obtener todos los usuarios con sus roles y permisos
     */
    public function users(): JsonResponse
    {
        $users = User::with([
            'roles' => function ($query) {
                $query->orderBy('name');
            },
            'permissions' => function ($query) {
                $query->orderBy('name');
            }
        ])->orderBy('name')->get();

        // Agregar all_permissions a cada usuario
        $users->each(function ($user) {
            $allPermissions = array_unique([
                ...$user->permissions->pluck('name')->toArray(),
                ...$user->roles->flatMap->permissions->pluck('name')->toArray(),
            ]);
            $user->all_permissions = $allPermissions;
        });

        return response()->json([
            'data' => $users,
        ]);
    }

    /**
     * Verificar si un usuario tiene un permiso específico
     */
    public function checkPermission(Request $request, $userId): JsonResponse
    {
        $request->validate([
            'permission' => 'required|string',
        ]);

        $user = User::with(['permissions', 'roles.permissions'])->findOrFail($userId);

        // Verificar si es admin_global
        if ($user->roles->contains('name', 'admin_global')) {
            return response()->json([
                'has_permission' => true,
                'reason' => 'admin_global',
            ]);
        }

        // Obtener todos los permisos
        $allPermissions = array_unique([
            ...$user->permissions->pluck('name')->toArray(),
            ...$user->roles->flatMap->permissions->pluck('name')->toArray(),
        ]);

        $hasPermission = in_array($request->permission, $allPermissions);

        return response()->json([
            'has_permission' => $hasPermission,
            'reason' => $hasPermission ? 'direct_or_role' : 'no_permission',
        ]);
    }

    /**
     * Obtener permisos agrupados por módulo
     */
    public function groupedPermissions(): JsonResponse
    {
        $permissions = Permission::orderBy('name')->get();

        // Agrupar por módulo (primera parte del nombre antes del punto)
        $grouped = $permissions->groupBy(function ($permission) {
            $parts = explode('.', $permission->name);
            return $parts[0];
        });

        return response()->json([
            'data' => $grouped,
        ]);
    }

    /**
     * Obtener estadísticas de permisos
     */
    public function stats(): JsonResponse
    {
        $totalPermissions = Permission::count();
        $totalRoles = Role::count();
        $totalUsers = User::count();
        
        // Usuarios por rol
        $usersByRole = DB::table('_roletouser')
            ->join('role', '_roletouser.B', '=', 'role.id')
            ->select('role.name', 'role.descripcion', DB::raw('COUNT(*) as total'))
            ->groupBy('role.id', 'role.name', 'role.descripcion')
            ->get();

        // Permisos más asignados
        $mostAssignedPermissions = DB::table('_permissiontorole')
            ->join('permission', '_permissiontorole.A', '=', 'permission.id')
            ->select('permission.name', 'permission.descripcion', DB::raw('COUNT(*) as total'))
            ->groupBy('permission.id', 'permission.name', 'permission.descripcion')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        return response()->json([
            'data' => [
                'total_permissions' => $totalPermissions,
                'total_roles' => $totalRoles,
                'total_users' => $totalUsers,
                'users_by_role' => $usersByRole,
                'most_assigned_permissions' => $mostAssignedPermissions,
            ],
        ]);
    }
}

