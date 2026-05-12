<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Services\DireccionClienteService;
use App\Http\Requests\DireccionCliente\StoreDireccionClienteRequest;
use App\Http\Requests\DireccionCliente\UpdateDireccionClienteRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ClienteController extends Controller
{
    public function __construct(
        private DireccionClienteService $direccionService
    ) {}
    /**
     * Listar todos los clientes
     */
    public function index(Request $request): JsonResponse
    {
        $query = Cliente::query()->with(['direcciones', 'profesion']);

        // Excluir "CLIENTE VARIOS" (DNI: 99999999) de las búsquedas
        $query->where('numero_documento', '!=', '99999999');

        // Filtros opcionales
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('numero_documento', 'like', "%{$search}%")
                  ->orWhere('nombres', 'like', "%{$search}%")
                  ->orWhere('apellidos', 'like', "%{$search}%")
                  ->orWhere('razon_social', 'like', "%{$search}%")
                  ->orWhereHas('profesion', function ($subQ) use ($search) {
                      $subQ->where('nombre', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->filled('profesion_id')) {
            $query->where('profesion_id', $request->profesion_id);
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
        $cliente = Cliente::with(['direcciones', 'profesion'])->findOrFail($id);

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
            'telefono' => 'nullable|string|max:20',
            'profesion_id' => 'nullable|integer|exists:profesion,id',
            'email' => 'nullable|email|max:255',
            'fecha_nacimiento' => 'nullable|date',
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

        $cliente = Cliente::create($validated)->load(['direcciones', 'profesion']);

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
            'telefono' => 'nullable|string|max:20',
            'profesion_id' => 'nullable|integer|exists:profesion,id',
            'email' => 'nullable|email|max:255',
            'fecha_nacimiento' => 'nullable|date',
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
        $cliente->load(['direcciones', 'profesion']);

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
            'exclude_id' => 'nullable|integer', // Para excluir el ID actual al editar
        ]);

        $query = Cliente::where('numero_documento', $request->numero_documento);

        // Si estamos editando, excluir el ID actual
        if ($request->has('exclude_id') && $request->exclude_id) {
            $query->where('id', '!=', $request->exclude_id);
        }

        $exists = $query->exists();

        return response()->json([
            'exists' => $exists,
            'message' => $exists ? 'El documento ya está registrado' : 'Documento disponible'
        ]);
    }

    /**
     * Obtener estadísticas de clientes
     */
    public function estadisticas(): JsonResponse
    {
        // Excluir "CLIENTE VARIOS" (DNI: 99999999) de las estadísticas
        $query = Cliente::where('numero_documento', '!=', '99999999');

        // Estadísticas básicas
        $activos = (clone $query)->where('estado', true)->count();
        $inactivos = (clone $query)->where('estado', false)->count();

        // VIP: Empresas con información completa (email, teléfono y dirección)
        $vip = (clone $query)->where('tipo_cliente', 'e')
            ->whereNotNull('email')
            ->whereNotNull('telefono')
            ->whereNotNull('direccion')
            ->where('email', '!=', '')
            ->where('telefono', '!=', '')
            ->where('direccion', '!=', '')
            ->count();

        // Frecuentes: Clientes con información de contacto completa (email y teléfono)
        $frecuentes = (clone $query)->whereNotNull('email')
            ->whereNotNull('telefono')
            ->where('email', '!=', '')
            ->where('telefono', '!=', '')
            ->count();

        // Problemáticos: Clientes inactivos o con información incompleta
        $problematicos = (clone $query)->where(function ($q) {
            $q->where('estado', false)
              ->orWhere(function ($subQ) {
                  $subQ->where(function ($emailQ) {
                      $emailQ->whereNull('email')->orWhere('email', '');
                  })->where(function ($telefonoQ) {
                      $telefonoQ->whereNull('telefono')->orWhere('telefono', '');
                  });
              });
        })->count();

        // Nuevos: No se puede calcular sin columna created_at
        // La tabla cliente no tiene timestamps
        $nuevos = 0;

        return response()->json([
            'data' => [
                'activos' => $activos,
                'inactivos' => $inactivos,
                'vip' => $vip,
                'frecuentes' => $frecuentes,
                'problematicos' => $problematicos,
                'nuevos' => $nuevos,
            ]
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

    // ============================================
    // MÉTODOS DE DIRECCIONES
    // ============================================

    /**
     * Ventas donde este cliente fue el recomendador (recomendado_por_id = clienteId)
     */
    public function recomendaciones(int $clienteId): JsonResponse
    {
        $cliente = Cliente::findOrFail($clienteId);

        $query = \App\Models\Venta::with([
                'cliente:id,numero_documento,nombres,apellidos,razon_social',
                'productosPorAlmacen:id,venta_id,costo',
                'productosPorAlmacen.unidadesDerivadas:id,producto_almacen_venta_id,cantidad,factor',
            ])
            ->where('recomendado_por_id', $clienteId)
            ->whereNotIn('estado_de_venta', ['an']);

        // Filtros opcionales por fecha
        if (request('fecha_desde')) {
            $query->whereDate('fecha', '>=', request('fecha_desde'));
        }
        if (request('fecha_hasta')) {
            $query->whereDate('fecha', '<=', request('fecha_hasta'));
        }

        $ventas = $query->orderBy('fecha', 'desc')
            ->get(['id', 'serie', 'numero', 'fecha', 'cliente_id', 'tipo_moneda', 'created_at']);

        $ventaIds = $ventas->pluck('id');

        // Total cobrado por venta
        $totalesPorVenta = \App\Models\DespliegueDePagoVenta::whereIn('venta_id', $ventaIds)
            ->selectRaw('venta_id, SUM(monto) as total')
            ->groupBy('venta_id')
            ->pluck('total', 'venta_id');

        $ventasData = $ventas->map(function ($v) use ($totalesPorVenta) {
            $total = (float) ($totalesPorVenta[$v->id] ?? 0);

            // Ganancia = total - costo total de productos
            $costoTotal = 0;
            foreach ($v->productosPorAlmacen as $pav) {
                foreach ($pav->unidadesDerivadas as $ud) {
                    $costoTotal += (float) $pav->costo * (float) $ud->cantidad * (float) $ud->factor;
                }
            }
            $ganancia = $total - $costoTotal;

            return [
                'id'          => $v->id,
                'serie'       => $v->serie,
                'numero'      => $v->numero,
                'fecha'       => $v->fecha,
                'cliente'     => $v->cliente,
                'tipo_moneda' => $v->tipo_moneda,
                'total'       => $total,
                'costo'       => round($costoTotal, 2),
                'ganancia'    => round($ganancia, 2),
            ];
        });

        return response()->json([
            'data' => [
                'total_ventas'    => $ventas->count(),
                'monto_total'     => $ventasData->sum('total'),
                'ganancia_total'  => $ventasData->sum('ganancia'),
                'ventas'          => $ventasData,
            ],
        ]);
    }

    /**
     */
    public function listarDirecciones(int $clienteId): JsonResponse
    {
        try {
            $cliente = Cliente::findOrFail($clienteId);
            $direcciones = $this->direccionService->listarDirecciones($clienteId);

            return response()->json([
                'data' => $direcciones
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Cliente no encontrado'
            ], 404);
        }
    }

    /**
     * Crear una nueva dirección para un cliente
     */
    public function crearDireccion(int $clienteId, StoreDireccionClienteRequest $request): JsonResponse
    {
        try {
            $cliente = Cliente::findOrFail($clienteId);
            
            $direccion = $this->direccionService->crearDireccion(
                $clienteId,
                $request->validated()
            );

            return response()->json([
                'data' => $direccion,
                'message' => 'Dirección creada exitosamente'
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Cliente no encontrado'
            ], 404);
        }
    }

    /**
     * Actualizar una dirección existente
     */
    public function actualizarDireccion(int $id, UpdateDireccionClienteRequest $request): JsonResponse
    {
        try {
            $direccion = $this->direccionService->actualizarDireccion(
                $id,
                $request->validated()
            );

            return response()->json([
                'data' => $direccion,
                'message' => 'Dirección actualizada exitosamente'
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Dirección no encontrada'
            ], 404);
        }
    }

    /**
     * Eliminar una dirección
     */
    public function eliminarDireccion(int $id): JsonResponse
    {
        try {
            $this->direccionService->eliminarDireccion($id);

            return response()->json([
                'message' => 'Dirección eliminada exitosamente'
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => $e->errors()['direccion'][0] ?? 'Error al eliminar dirección',
                'errors' => $e->errors()
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Dirección no encontrada'
            ], 404);
        }
    }

    /**
     * Marcar una dirección como principal
     */
    public function marcarDireccionPrincipal(int $id): JsonResponse
    {
        try {
            $direccion = $this->direccionService->marcarComoPrincipal($id);

            return response()->json([
                'data' => $direccion,
                'message' => 'Dirección marcada como principal'
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Dirección no encontrada'
            ], 404);
        }
    }
}
