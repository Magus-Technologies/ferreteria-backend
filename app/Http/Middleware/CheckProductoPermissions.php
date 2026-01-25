<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to check product-related permissions
 *
 * Maps HTTP methods to required permissions:
 * - GET (index, show) → PRODUCTO_LISTADO
 * - POST (store) → PRODUCTO_CREAR
 * - PUT/PATCH (update) → PRODUCTO_EDITAR
 * - DELETE (destroy) → PRODUCTO_ELIMINAR
 *
 * Special routes:
 * - POST /import → PRODUCTO_IMPORTAR
 * - POST /upload-files → PRODUCTO_EDITAR
 */
class CheckProductoPermissions
{
    /**
     * Permission mappings for standard CRUD operations
     */
    private const METHOD_PERMISSIONS = [
        'GET' => 'PRODUCTO_LISTADO',
        'POST' => 'PRODUCTO_CREAR',
        'PUT' => 'PRODUCTO_EDITAR',
        'PATCH' => 'PRODUCTO_EDITAR',
        'DELETE' => 'PRODUCTO_ELIMINAR',
    ];

    /**
     * Special route permissions (override method-based)
     */
    private const ROUTE_PERMISSIONS = [
        'productos.import' => 'PRODUCTO_IMPORTAR',
        'productos.import.progress' => 'PRODUCTO_IMPORTAR',
        'productos.import.cancel' => 'PRODUCTO_IMPORTAR',
        'productos.import.results' => 'PRODUCTO_IMPORTAR',
        'productos.upload' => 'PRODUCTO_EDITAR',
        'productos.upload.masivo' => 'PRODUCTO_EDITAR',
        'productos.precios.update' => 'PRODUCTO_EDITAR',
        'productos.precios.bulk' => 'PRODUCTO_EDITAR',
    ];

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ?string $permission = null): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'error' => 'No autenticado',
                'message' => 'Debe iniciar sesión para acceder a este recurso',
            ], 401);
        }

        // Determine required permission
        $requiredPermission = $permission ?? $this->getRequiredPermission($request);

        if (!$requiredPermission) {
            // No permission required for this route
            return $next($request);
        }

        // Check if user has the required permission
        if (!$this->userHasPermission($user, $requiredPermission)) {
            return response()->json([
                'error' => 'Acceso denegado',
                'message' => "No tiene permiso para realizar esta acción ({$requiredPermission})",
                'required_permission' => $requiredPermission,
            ], 403);
        }

        return $next($request);
    }

    /**
     * Determine the required permission for the current request
     */
    private function getRequiredPermission(Request $request): ?string
    {
        // Check for route-specific permission first
        $routeName = $request->route()?->getName();
        if ($routeName && isset(self::ROUTE_PERMISSIONS[$routeName])) {
            return self::ROUTE_PERMISSIONS[$routeName];
        }

        // Fall back to method-based permission
        $method = strtoupper($request->method());
        return self::METHOD_PERMISSIONS[$method] ?? null;
    }

    /**
     * Check if user has the required permission
     */
    private function userHasPermission($user, string $permission): bool
    {
        // Check if user model has permission checking method
        if (method_exists($user, 'hasPermissionTo')) {
            return $user->hasPermissionTo($permission);
        }

        // Check if user model has permissions relationship
        if (method_exists($user, 'permissions')) {
            return $user->permissions->contains('name', $permission);
        }

        // Check if user model has getAllPermissions method (Spatie)
        if (method_exists($user, 'getAllPermissions')) {
            return $user->getAllPermissions()->contains('name', $permission);
        }

        // If no permission system is implemented, allow access
        // (remove this in production if permissions are required)
        return true;
    }
}
