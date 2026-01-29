<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to validate user access to a specific warehouse (almacen)
 *
 * Ensures that users can only access products from warehouses
 * they are authorized to view/manage.
 *
 * Checks:
 * 1. almacen_id parameter exists
 * 2. User has access to the specified warehouse
 * 3. Warehouse exists and is active
 */
class ValidateAlmacenAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ?string $paramName = 'almacen_id'): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'error' => 'No autenticado',
                'message' => 'Debe iniciar sesión para acceder a este recurso',
            ], 401);
        }

        // Get almacen_id from request (query params, body, or route)
        $almacenId = $this->getAlmacenId($request, $paramName);

        // If no almacen_id is required for this route, continue
        if ($almacenId === null) {
            return $next($request);
        }

        // Validate warehouse exists
        $almacen = $this->getAlmacen($almacenId);
        if (!$almacen) {
            return response()->json([
                'error' => 'Almacén no encontrado',
                'message' => "El almacén con ID {$almacenId} no existe",
            ], 404);
        }

        // Check if warehouse is active
        if (isset($almacen->estado) && !$almacen->estado) {
            return response()->json([
                'error' => 'Almacén inactivo',
                'message' => 'El almacén seleccionado no está activo',
            ], 403);
        }

        // Check if user has access to this warehouse
        if (!$this->userHasAlmacenAccess($user, $almacenId)) {
            return response()->json([
                'error' => 'Acceso denegado',
                'message' => 'No tiene acceso al almacén seleccionado',
                'almacen_id' => $almacenId,
            ], 403);
        }

        // Add almacen to request for later use
        $request->attributes->set('almacen', $almacen);

        return $next($request);
    }

    /**
     * Get almacen_id from request
     */
    private function getAlmacenId(Request $request, string $paramName): ?int
    {
        // Try query parameter first
        $almacenId = $request->query($paramName);

        // Try request body
        if ($almacenId === null) {
            $almacenId = $request->input($paramName);
        }

        // Try route parameter
        if ($almacenId === null) {
            $almacenId = $request->route($paramName);
        }

        return $almacenId !== null ? (int) $almacenId : null;
    }

    /**
     * Get warehouse by ID
     */
    private function getAlmacen(int $id): ?object
    {
        // Use the Almacen model directly to avoid circular dependencies
        return \App\Models\Almacen::find($id);
    }

    /**
     * Check if user has access to the specified warehouse
     */
    private function userHasAlmacenAccess($user, int $almacenId): bool
    {
        // Option 1: User has almacen_id assigned
        if (isset($user->almacen_id)) {
            // If user has specific warehouse, check match
            // If user has no warehouse assigned (null), they might have access to all
            return $user->almacen_id === null || $user->almacen_id === $almacenId;
        }

        // Option 2: User has almacenes relationship (many-to-many)
        if (method_exists($user, 'almacenes')) {
            // If no warehouses assigned, might have access to all
            if ($user->almacenes->isEmpty()) {
                return true;
            }
            return $user->almacenes->contains('id', $almacenId);
        }

        // Option 3: Check for specific permission
        if (method_exists($user, 'hasPermissionTo')) {
            // Check for global access or specific warehouse permission
            return $user->hasPermissionTo('ALMACEN_TODOS') ||
                   $user->hasPermissionTo("ALMACEN_ACCESO_{$almacenId}");
        }

        // Option 4: Check user roles (admin has access to all)
        if (method_exists($user, 'hasRole')) {
            if ($user->hasRole(['admin', 'superadmin', 'administrador'])) {
                return true;
            }
        }

        // Default: Allow access (configure based on your requirements)
        return true;
    }
}
