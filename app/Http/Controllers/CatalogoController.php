<?php

namespace App\Http\Controllers;

use App\Models\CatalogoCargo;
use App\Models\CatalogoEstadoCivil;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogoController extends Controller
{
    /**
     * Obtener lista de estados civiles
     * GET /api/catalogos/estados-civiles
     */
    public function estadosCiviles(): JsonResponse
    {
        $estadosCiviles = CatalogoEstadoCivil::activos()
            ->ordenado()
            ->get(['id', 'codigo', 'descripcion', 'orden']);

        return response()->json([
            'data' => $estadosCiviles
        ]);
    }

    /**
     * Obtener lista de roles del sistema
     * GET /api/roles
     */
    public function roles(): JsonResponse
    {
        $roles = Role::orderBy('name', 'asc')
            ->get(['id', 'name', 'descripcion']);

        return response()->json([
            'data' => $roles
        ]);
    }

    /**
     * Obtener lista de tipos de documento
     * GET /api/catalogos/tipos-documento
     */
    public function tiposDocumento(): JsonResponse
    {
        $tiposDocumento = [
            ['codigo' => 'DNI', 'descripcion' => 'DNI - Documento Nacional de Identidad'],
            ['codigo' => 'RUC', 'descripcion' => 'RUC - Registro Único de Contribuyentes'],
            ['codigo' => 'CE', 'descripcion' => 'CE - Carné de Extranjería'],
            ['codigo' => 'PASAPORTE', 'descripcion' => 'Pasaporte'],
        ];

        return response()->json([
            'data' => $tiposDocumento
        ]);
    }

    /**
     * Obtener lista de géneros
     * GET /api/catalogos/generos
     */
    public function generos(): JsonResponse
    {
        $generos = [
            ['codigo' => 'M', 'descripcion' => 'Masculino'],
            ['codigo' => 'F', 'descripcion' => 'Femenino'],
            ['codigo' => 'O', 'descripcion' => 'Otro'],
        ];

        return response()->json([
            'data' => $generos
        ]);
    }

    /**
     * Obtener lista de roles del sistema (para formularios)
     * GET /api/catalogos/roles-sistema
     * 
     * NOTA: Este endpoint devuelve el mapeo de roles del sistema
     * En el futuro, cuando se elimine el campo rol_sistema, este endpoint
     * será reemplazado por el endpoint /api/roles
     */
    public function rolesSistema(): JsonResponse
    {
        $rolesSistema = [
            ['codigo' => 'ADMINISTRADOR', 'descripcion' => 'Administrador', 'role_id' => 1],
            ['codigo' => 'VENDEDOR', 'descripcion' => 'Vendedor', 'role_id' => 2],
            ['codigo' => 'ALMACENERO', 'descripcion' => 'Almacenero', 'role_id' => 3],
            ['codigo' => 'CONTADOR', 'descripcion' => 'Contador', 'role_id' => 4],
            ['codigo' => 'DESPACHADOR', 'descripcion' => 'Despachador', 'role_id' => 9],
            ['codigo' => 'CONDUCTOR', 'descripcion' => 'Conductor', 'role_id' => 2],
        ];

        return response()->json([
            'data' => $rolesSistema
        ]);
    }

    /**
     * Obtener lista de cargos/ocupaciones
     * GET /api/catalogos/cargos?parent={codigo}
     * Opcional: ?only_parent_of={codigo} devuelve solo el cargo padre del codigo proporcionado
     */
    public function cargos(Request $request): JsonResponse
    {
        // Si se solicita el padre de un cargo específico, devolver solo ese padre
        if ($request->has('only_parent_of')) {
            $cargo = CatalogoCargo::where('codigo', $request->input('only_parent_of'))->first();
            if ($cargo && !empty($cargo->parent)) {
                $parent = CatalogoCargo::where('codigo', $cargo->parent)
                    ->first(['id', 'codigo', 'descripcion', 'parent', 'highlight', 'staff']);

                return response()->json([
                    'data' => $parent ? [$parent] : []
                ]);
            }

            return response()->json(['data' => []]);
        }

        $query = CatalogoCargo::activos()->ordenado();

        // Si viene parámetro parent, filtrar solo hijos del parent
        if ($request->has('parent')) {
            $query->where('parent', $request->input('parent'));
        }

        $cargos = $query->get(['id', 'codigo', 'descripcion', 'parent', 'highlight', 'staff']);

        return response()->json([
            'data' => $cargos
        ]);
    }

    /**
     * Guardar un cargo nuevo
     * POST /api/catalogos/cargos
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'codigo' => ['required', 'string', 'max:120', 'unique:catalogo_cargos,codigo'],
            'descripcion' => ['required', 'string', 'max:255'],
            'parent' => ['nullable', 'string', 'max:120'],
            'highlight' => ['sometimes', 'boolean'],
            'staff' => ['sometimes', 'boolean'],
            'estado' => ['sometimes', 'boolean'],
            'role_id' => ['nullable', 'integer', 'exists:role,id'],
        ]);

        if (!empty($validated['parent'])) {
            CatalogoCargo::where('codigo', $validated['parent'])->firstOrFail();
        }

        $cargo = CatalogoCargo::create($validated);

        return response()->json([
            'data' => $cargo
        ], 201);
    }

    /**
     * Obtener un cargo por código
     * GET /api/catalogos/cargos/{codigo}
     */
    public function show(string $codigo): JsonResponse
    {
        $cargo = CatalogoCargo::where('codigo', $codigo)
            ->firstOrFail(['codigo', 'descripcion', 'parent', 'highlight', 'staff']);

        return response()->json([
            'data' => $cargo
        ]);
    }

    /**
     * Actualizar un cargo existente
     * PUT /api/catalogos/cargos/{codigo}
     */
    public function update(Request $request, string $codigo): JsonResponse
    {
        $cargo = CatalogoCargo::where('codigo', $codigo)->firstOrFail();

        $validated = $request->validate([
            'codigo' => ['required', 'string', 'max:120', 'unique:catalogo_cargos,codigo,' . $cargo->id],
            'descripcion' => ['required', 'string', 'max:255'],
            'parent' => ['nullable', 'string', 'max:120'],
            'highlight' => ['sometimes', 'boolean'],
            'staff' => ['sometimes', 'boolean'],
            'estado' => ['sometimes', 'boolean'],
            'role_id' => ['nullable', 'integer', 'exists:role,id'],
        ]);

        if (!empty($validated['parent']) && $validated['parent'] === $cargo->codigo) {
            return response()->json(['message' => 'Un cargo no puede reportar a sí mismo.'], 422);
        }

        if (!empty($validated['parent'])) {
            CatalogoCargo::where('codigo', $validated['parent'])->firstOrFail();
        }

        $oldCodigo = $cargo->codigo;
        $cargo->update($validated);

        if ($oldCodigo !== $cargo->codigo) {
            CatalogoCargo::where('parent', $oldCodigo)->update(['parent' => $cargo->codigo]);
        }

        return response()->json([
            'data' => $cargo
        ]);
    }

    /**
     * Eliminar un cargo
     * DELETE /api/catalogos/cargos/{codigo}
     */
    public function destroy(string $codigo): JsonResponse
    {
        $cargo = CatalogoCargo::where('codigo', $codigo)->firstOrFail();

        // Solo se elimina si ningún usuario lo tiene asignado.
        $enUso = \App\Models\User::where('cargo', $cargo->codigo)->count();
        if ($enUso > 0) {
            return response()->json([
                'message' => "No se puede eliminar: el cargo está asignado a {$enUso} usuario(s). Desactívalo en su lugar.",
            ], 409);
        }

        CatalogoCargo::where('parent', $cargo->codigo)->update(['parent' => null]);
        $cargo->delete();

        return response()->json([], 204);
    }

    /**
     * Listado de cargos para gestión: incluye TODOS (activos e inactivos),
     * su estado y cuántos usuarios lo usan.
     * GET /api/catalogos/cargos-gestion
     */
    public function cargosGestion(): JsonResponse
    {
        $conteos = \App\Models\User::query()
            ->selectRaw('cargo, COUNT(*) as total')
            ->whereNotNull('cargo')
            ->groupBy('cargo')
            ->pluck('total', 'cargo');

        $cargos = CatalogoCargo::with('role:id,name,descripcion')
            ->orderBy('descripcion')
            ->get(['id', 'codigo', 'descripcion', 'parent', 'highlight', 'staff', 'estado', 'role_id'])
            ->map(function ($c) use ($conteos) {
                return [
                    'id' => $c->id,
                    'codigo' => $c->codigo,
                    'descripcion' => $c->descripcion,
                    'parent' => $c->parent,
                    'highlight' => (bool) $c->highlight,
                    'staff' => (bool) $c->staff,
                    'estado' => (bool) $c->estado,
                    'role_id' => $c->role_id,
                    'role' => $c->role ? ['id' => $c->role->id, 'name' => $c->role->name, 'descripcion' => $c->role->descripcion] : null,
                    'users_count' => (int) ($conteos[$c->codigo] ?? 0),
                ];
            });

        return response()->json(['data' => $cargos]);
    }

    /**
     * Activar / desactivar un cargo.
     * PATCH /api/catalogos/cargos/{codigo}/estado
     */
    public function toggleEstadoCargo(Request $request, string $codigo): JsonResponse
    {
        $request->validate(['estado' => ['required', 'boolean']]);

        $cargo = CatalogoCargo::where('codigo', $codigo)->firstOrFail();
        $cargo->update(['estado' => $request->boolean('estado')]);

        return response()->json(['data' => $cargo]);
    }
}
