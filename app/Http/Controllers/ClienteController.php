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

        // Excluir "CLIENTE VARIOS" (DNI: 99999999) de las búsquedas
        $query->where('numero_documento', '!=', '99999999');

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
        // Auto-detectar tipo de cliente según longitud del documento
        $tipoCliente = $request->tipo_cliente;
        if (!$tipoCliente || empty($tipoCliente)) {
            $longitudDocumento = strlen($request->numero_documento);
            $tipoCliente = $longitudDocumento === 8 ? 'p' : 'e';
        }

        // Convertir tipo_cliente a formato correcto si viene como "Persona" o "Empresa"
        if ($tipoCliente === 'Persona') {
            $tipoCliente = 'p';
        } elseif ($tipoCliente === 'Empresa') {
            $tipoCliente = 'e';
        }

        // Validación condicional según tipo de cliente
        $rules = [
            'tipo_cliente' => ['nullable', Rule::in(['p', 'e', 'Persona', 'Empresa'])],
            'numero_documento' => [
                'required',
                'string',
                'max:11',
                'unique:cliente,numero_documento'
            ],
            'direccion' => 'nullable|string|max:500',
            'direccion_2' => 'nullable|string|max:500',
            'direccion_3' => 'nullable|string|max:500',
            'direccion_4' => 'nullable|string|max:500',
            'telefono' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'estado' => 'nullable|boolean',
        ];

        // Si es Persona (DNI): nombres y apellidos son requeridos
        if ($tipoCliente === 'p') {
            $rules['nombres'] = 'required|string|max:255';
            $rules['apellidos'] = 'required|string|max:255';
            $rules['razon_social'] = 'nullable|string|max:255';
        } 
        // Si es Empresa (RUC): razon_social es requerida
        else {
            $rules['nombres'] = 'nullable|string|max:255';
            $rules['apellidos'] = 'nullable|string|max:255';
            $rules['razon_social'] = 'required|string|max:255';
        }

        $validated = $request->validate($rules);

        // Asignar tipo de cliente detectado
        $validated['tipo_cliente'] = $tipoCliente;

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

        // Auto-detectar tipo de cliente según longitud del documento
        $tipoCliente = $request->tipo_cliente ?? $cliente->tipo_cliente;
        if ($request->has('numero_documento')) {
            $longitudDocumento = strlen($request->numero_documento);
            $tipoCliente = $longitudDocumento === 8 ? 'p' : 'e';
        }

        // Convertir tipo_cliente a formato correcto si viene como "Persona" o "Empresa"
        if ($tipoCliente === 'Persona') {
            $tipoCliente = 'p';
        } elseif ($tipoCliente === 'Empresa') {
            $tipoCliente = 'e';
        }

        // Validación condicional según tipo de cliente
        $rules = [
            'tipo_cliente' => ['sometimes', Rule::in(['p', 'e', 'Persona', 'Empresa'])],
            'numero_documento' => [
                'sometimes',
                'required',
                'string',
                'max:11',
                Rule::unique('cliente', 'numero_documento')->ignore($cliente->id)
            ],
            'direccion' => 'nullable|string|max:500',
            'direccion_2' => 'nullable|string|max:500',
            'direccion_3' => 'nullable|string|max:500',
            'direccion_4' => 'nullable|string|max:500',
            'telefono' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'estado' => 'nullable|boolean',
        ];

        // Si es Persona (DNI): nombres y apellidos son requeridos
        if ($tipoCliente === 'p') {
            $rules['nombres'] = 'sometimes|required|string|max:255';
            $rules['apellidos'] = 'sometimes|required|string|max:255';
            $rules['razon_social'] = 'nullable|string|max:255';
        } 
        // Si es Empresa (RUC): razon_social es requerida
        else {
            $rules['nombres'] = 'nullable|string|max:255';
            $rules['apellidos'] = 'nullable|string|max:255';
            $rules['razon_social'] = 'sometimes|required|string|max:255';
        }

        $validated = $request->validate($rules);

        // Asignar tipo de cliente detectado si cambió el documento
        if ($request->has('numero_documento')) {
            $validated['tipo_cliente'] = $tipoCliente;
        }

        $cliente->update($validated);

        return response()->json([
            'data' => $cliente,
            'message' => 'Cliente actualizado exitosamente'
        ]);
    }

    /**
     * Verificar si un documento ya existe
     */
    public function checkDocumento(Request $request): JsonResponse
    {
        $request->validate([
            'numero_documento' => 'required|string|max:11',
            'exclude_id' => 'nullable|string', // Para excluir el ID actual al editar
        ]);

        $query = Cliente::where('numero_documento', $request->numero_documento);

        // Si estamos editando, excluir el ID actual
        if ($request->has('exclude_id')) {
            $query->where('id', '!=', $request->exclude_id);
        }

        $exists = $query->exists();

        return response()->json([
            'exists' => $exists,
            'message' => $exists ? 'El documento ya está registrado' : 'Documento disponible'
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
