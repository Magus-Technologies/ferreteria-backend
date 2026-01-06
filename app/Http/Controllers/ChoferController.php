<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ChoferController extends Controller
{
    /**
     * Listar todos los choferes con búsqueda y paginación
     */
    public function index(Request $request)
    {
        $query = DB::table('choferes')
            ->where('estado', 1); // Solo activos por defecto

        // Búsqueda por DNI, nombres o apellidos
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('dni', 'LIKE', "%{$search}%")
                  ->orWhere('nombres', 'LIKE', "%{$search}%")
                  ->orWhere('apellidos', 'LIKE', "%{$search}%")
                  ->orWhere(DB::raw("CONCAT(nombres, ' ', apellidos)"), 'LIKE', "%{$search}%");
            });
        }

        // Filtro por estado (si se especifica)
        if ($request->has('estado')) {
            $query->where('estado', $request->estado);
        }

        $perPage = $request->get('per_page', 20);
        
        $choferes = $query->orderBy('nombres', 'asc')
            ->paginate($perPage);

        return response()->json($choferes);
    }

    /**
     * Obtener un chofer por ID
     */
    public function show($id)
    {
        $chofer = DB::table('choferes')->where('id', $id)->first();

        if (!$chofer) {
            return response()->json([
                'message' => 'Chofer no encontrado'
            ], 404);
        }

        return response()->json($chofer);
    }

    /**
     * Crear un nuevo chofer
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'dni' => 'required|string|size:8|unique:choferes,dni',
            'nombres' => 'required|string|max:191',
            'apellidos' => 'required|string|max:191',
            'licencia' => 'required|string|max:191',
            'telefono' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:191',
            'direccion' => 'nullable|string|max:255',
            'estado' => 'nullable|boolean',
        ]);

        $validated['estado'] = $validated['estado'] ?? 1;

        $choferId = DB::table('choferes')->insertGetId($validated);

        $chofer = DB::table('choferes')->where('id', $choferId)->first();

        return response()->json([
            'message' => 'Chofer creado exitosamente',
            'data' => $chofer
        ], 201);
    }

    /**
     * Actualizar un chofer existente
     */
    public function update(Request $request, $id)
    {
        $chofer = DB::table('choferes')->where('id', $id)->first();

        if (!$chofer) {
            return response()->json([
                'message' => 'Chofer no encontrado'
            ], 404);
        }

        $validated = $request->validate([
            'dni' => [
                'required',
                'string',
                'size:8',
                Rule::unique('choferes', 'dni')->ignore($id)
            ],
            'nombres' => 'required|string|max:191',
            'apellidos' => 'required|string|max:191',
            'licencia' => 'required|string|max:191',
            'telefono' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:191',
            'direccion' => 'nullable|string|max:255',
            'estado' => 'nullable|boolean',
        ]);

        DB::table('choferes')->where('id', $id)->update($validated);

        $choferActualizado = DB::table('choferes')->where('id', $id)->first();

        return response()->json([
            'message' => 'Chofer actualizado exitosamente',
            'data' => $choferActualizado
        ]);
    }

    /**
     * Eliminar (desactivar) un chofer
     */
    public function destroy($id)
    {
        $chofer = DB::table('choferes')->where('id', $id)->first();

        if (!$chofer) {
            return response()->json([
                'message' => 'Chofer no encontrado'
            ], 404);
        }

        // Desactivar en lugar de eliminar
        DB::table('choferes')->where('id', $id)->update(['estado' => 0]);

        return response()->json([
            'message' => 'Chofer desactivado exitosamente'
        ]);
    }

    /**
     * Buscar chofer por DNI
     */
    public function buscarPorDni($dni)
    {
        $chofer = DB::table('choferes')
            ->where('dni', $dni)
            ->where('estado', 1)
            ->first();

        if (!$chofer) {
            return response()->json([
                'message' => 'Chofer no encontrado'
            ], 404);
        }

        return response()->json($chofer);
    }
}
