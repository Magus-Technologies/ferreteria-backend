<?php

namespace App\Http\Controllers;

use App\Models\TipoIngresoSalida;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TipoIngresoSalidaController extends Controller
{
    /**
     * Listar todos los tipos de ingreso/salida
     */
    public function index(Request $request): JsonResponse
    {
        $query = TipoIngresoSalida::query();

        // Filtros opcionales
        if ($request->has("search")) {
            $search = $request->search;
            $query->where("name", "like", "%{$search}%");
        }

        if ($request->has("estado")) {
            $query->where("estado", $request->boolean("estado"));
        }

        // Si no se solicita paginación, devolver array simple
        if (!$request->has("per_page") && !$request->has("page")) {
            $tiposIngresoSalida = $query->orderBy("name", "asc")->get();
            return response()->json($tiposIngresoSalida);
        }

        // Paginación
        $perPage = $request->get("per_page", 15);
        $tiposIngresoSalida = $query
            ->orderBy("name", "asc")
            ->paginate($perPage);

        return response()->json($tiposIngresoSalida);
    }

    /**
     * Mostrar un tipo de ingreso/salida específico
     */
    public function show(string $id): JsonResponse
    {
        $tipoIngresoSalida = TipoIngresoSalida::findOrFail($id);

        return response()->json([
            "data" => $tipoIngresoSalida,
        ]);
    }

    /**
     * Crear un nuevo tipo de ingreso/salida
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            "name" => "required|string|max:191|unique:tipoingresosalida,name",
            "estado" => "nullable|boolean",
        ]);

        // Estado por defecto
        $validated["estado"] = $validated["estado"] ?? true;

        $tipoIngresoSalida = TipoIngresoSalida::create($validated);

        return response()->json(
            [
                "data" => $tipoIngresoSalida,
                "message" => "Tipo de ingreso/salida creado exitosamente",
            ],
            201,
        );
    }

    /**
     * Actualizar un tipo de ingreso/salida existente
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $tipoIngresoSalida = TipoIngresoSalida::findOrFail($id);

        $validated = $request->validate([
            "name" =>
                "sometimes|required|string|max:191|unique:tipoingresosalida,name," .
                $tipoIngresoSalida->id,
            "estado" => "nullable|boolean",
        ]);

        $tipoIngresoSalida->update($validated);

        return response()->json([
            "data" => $tipoIngresoSalida,
            "message" => "Tipo de ingreso/salida actualizado exitosamente",
        ]);
    }

    /**
     * Eliminar un tipo de ingreso/salida
     */
    public function destroy(string $id): JsonResponse
    {
        $tipoIngresoSalida = TipoIngresoSalida::findOrFail($id);

        // Verificar si tiene ingresos/salidas asociados
        if ($tipoIngresoSalida->ingresos()->exists()) {
            return response()->json(
                [
                    "message" =>
                        "No se puede eliminar el tipo de ingreso/salida porque tiene ingresos/salidas asociados",
                ],
                422,
            );
        }

        try {
            $tipoIngresoSalida->delete();

            return response()->json([
                "message" => "Tipo de ingreso/salida eliminado exitosamente",
            ]);
        } catch (\Exception $e) {
            return response()->json(
                [
                    "message" =>
                        "No se puede eliminar el tipo de ingreso/salida porque está en uso",
                ],
                422,
            );
        }
    }
}
