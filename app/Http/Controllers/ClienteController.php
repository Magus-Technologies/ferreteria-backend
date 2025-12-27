<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class ClienteController extends Controller
{
    /**
     * Listar todos los clientes
     */
    public function index(Request $request): JsonResponse
    {
        $query = Cliente::query();

        // Filtros opcionales
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('numero_documento', 'like', "%{$search}%")
                  ->orWhere('nombres', 'like', "%{$search}%")
                  ->orWhere('apellidos', 'like', "%{$search}%")
                  ->orWhere('razon_social', 'like', "%{$search}%");
            });
        }

        if ($request->has('tipo_cliente')) {
            $query->where('tipo_cliente', $request->tipo_cliente);
        }

        if ($request->has('estado')) {
            $query->where('estado', $request->boolean('estado'));
        }

        // Paginación
        $perPage = $request->get('per_page', 15);
        $clientes = $query->orderBy('razon_social', 'asc')
                         ->paginate($perPage);

        return response()->json($clientes);
    }

    /**
     * Mostrar un cliente específico
     */
    public function show(string $id): JsonResponse
    {
        $cliente = Cliente::findOrFail($id);

        return response()->json([
            'data' => $cliente
        ]);
    }

    /**
     * Crear un nuevo cliente
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tipo_cliente' => ['required', Rule::in(['p', 'e'])],
            'numero_documento' => [
                'required',
                'string',
                'max:11',
                'unique:cliente,numero_documento'
            ],
            'nombres' => 'nullable|string|max:255',
            'apellidos' => 'nullable|string|max:255',
            'razon_social' => 'nullable|string|max:255',
            'direccion' => 'nullable|string|max:500',
            'telefono' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'estado' => 'nullable|boolean',
        ]);

        // Estado por defecto
        $validated['estado'] = $validated['estado'] ?? true;

        $cliente = Cliente::create($validated);

        return response()->json([
            'data' => $cliente,
            'message' => 'Cliente creado exitosamente'
        ], 201);
    }

    /**
     * Actualizar un cliente existente
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $cliente = Cliente::findOrFail($id);

        $validated = $request->validate([
            'tipo_cliente' => ['sometimes', 'required', Rule::in(['p', 'e'])],
            'numero_documento' => [
                'sometimes',
                'required',
                'string',
                'max:11',
                Rule::unique('cliente', 'numero_documento')->ignore($cliente->id)
            ],
            'nombres' => 'nullable|string|max:255',
            'apellidos' => 'nullable|string|max:255',
            'razon_social' => 'nullable|string|max:255',
            'direccion' => 'nullable|string|max:500',
            'telefono' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'estado' => 'nullable|boolean',
        ]);

        $cliente->update($validated);

        return response()->json([
            'data' => $cliente,
            'message' => 'Cliente actualizado exitosamente'
        ]);
    }

    /**
     * Eliminar un cliente
     */
    public function destroy(string $id): JsonResponse
    {
        $cliente = Cliente::findOrFail($id);

        try {
            $cliente->delete();

            return response()->json([
                'message' => 'Cliente eliminado exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'No se puede eliminar el cliente porque está en uso'
            ], 422);
        }
    }
}
