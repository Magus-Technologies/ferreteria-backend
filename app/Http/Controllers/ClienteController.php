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
    use \App\Traits\BroadcastsModelChanges;
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
        if ($request->has('search') && trim((string) $request->search) !== '') {
            $tokens = self::tokenizeSearch((string) $request->search);
            if (! empty($tokens)) {
                // Búsqueda multi-tokken: cada token debe matchear AL MENOS
                // en uno de los campos (AND entre tokens, OR entre campos).
                // Acentos: comparamos `LOWER(REPLACE(..., acentos, sinAcento))`
                // en SQL para que "Perez" matchee "Pérez".
                //
                // Con 2+ PALABRAS la consulta es un NOMBRE (persona/empresa):
                // se restringe a campos de identidad. Si incluyera contacto,
                // "ELIAS C" traería a GRUPO MI REDENTOR porque su email
                // contiene "elias". Se cuentan las palabras del texto CRUDO
                // (no los tokens: tokenizeSearch descarta los de 1 caracter,
                // y la "c" de "ELIAS C" es justamente una de esas).
                // Con 1 palabra se mantiene la búsqueda amplia (tel/email).
                $palabrasCrudas = preg_split('/\s+/u', trim((string) $request->search), -1, PREG_SPLIT_NO_EMPTY);
                $camposTexto = count($palabrasCrudas) >= 2
                    ? [
                        'numero_documento',
                        'nombres',
                        'apellidos',
                        'razon_social',
                    ]
                    : [
                        'numero_documento',
                        'nombres',
                        'apellidos',
                        'razon_social',
                        'telefono',
                        'celular',
                        'email',
                        'contacto_referencia',
                    ];
                $query->where(function ($q) use ($tokens, $camposTexto) {
                    foreach ($tokens as $token) {
                        $q->where(function ($sub) use ($token, $camposTexto) {
                            foreach ($camposTexto as $campo) {
                                $sub->orWhereRaw(
                                    "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(LOWER($campo), 'á','a'), 'é','e'), 'í','i'), 'ó','o'), 'ú','u'), 'ñ','n') LIKE ?",
                                    ["%{$token}%"]
                                );
                            }
                        });
                    }
                });
            }
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

        // Filtrar por clientes que tienen ventas en el rango de fechas
        if ($request->filled('fecha_desde') || $request->filled('fecha_hasta')) {
            $query->whereHas('ventas', function ($q) use ($request) {
                if ($request->filled('fecha_desde')) {
                    $q->whereDate('fecha', '>=', $request->fecha_desde);
                }
                if ($request->filled('fecha_hasta')) {
                    $q->whereDate('fecha', '<=', $request->fecha_hasta);
                }
            });
        }

        // Filtrar solo clientes que han recomendado al menos una venta
        if ($request->boolean('con_recomendaciones')) {
            $query->whereHas('ventasRecomendadas', function ($q) {
                $q->whereNotIn('estado_de_venta', ['an']);
            });
        }

        // Ordenar por frecuencia (cantidad de ventas) si se solicita
        if ($request->boolean('ordenar_por_frecuencia')) {
            $query->withCount(['ventas' => function ($q) {
                $q->whereNotIn('estado_de_venta', ['an']);
            }])
            ->orderBy('ventas_count', 'desc');
        } else {
            // Siempre incluir el conteo de ventas para que esté disponible en el frontend
            $query->withCount(['ventas' => function ($q) {
                $q->whereNotIn('estado_de_venta', ['an']);
            }])
            ->orderBy('razon_social', 'asc');
        }

        // Paginación
        $perPage = $request->get('per_page', 15);
        $clientes = $query->paginate($perPage);

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
                'nullable',
                'string',
                'max:11',
                'unique:cliente,numero_documento'
            ],
            'telefono' => 'nullable|string|max:20',
            'celular' => 'nullable|string|max:20',
            'profesion_id' => 'nullable|integer|exists:profesion,id',
            'email' => 'nullable|email|max:255',
            'fecha_nacimiento' => 'nullable|date',
            'estado' => 'nullable|boolean',
        ];

        // Si es Persona (DNI): nombres y apellidos son requeridos.
        // Excepcion: clientes de venta rapida SIN documento (nombre libre
        // tipeado en crear-venta) — una sola palabra no tiene apellido
        // separable, asi que apellidos pasa a ser opcional.
        if ($tipoCliente === 'p') {
            $rules['nombres'] = 'required|string|max:255';
            $rules['apellidos'] = $request->filled('numero_documento')
                ? 'required|string|max:255'
                : 'nullable|string|max:255';
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

        // Sin documento: generar placeholder único para no violar la restricción UNIQUE.
        if (empty($validated['numero_documento'])) {
            $validated['numero_documento'] = 'SN-' . \Illuminate\Support\Str::upper(\Illuminate\Support\Str::random(8));
        }

        // Estado por defecto
        $validated['estado'] = $validated['estado'] ?? true;

        $cliente = Cliente::create($validated)->load(['direcciones', 'profesion']);

        // Notificar a otras pestañas en tiempo real
        $this->broadcastChange('clientes', 'created', (string) $cliente->id);

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
            'celular' => 'nullable|string|max:20',
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

        // Notificar a otras pestañas en tiempo real
        $this->broadcastChange('clientes', 'updated', (string) $cliente->id);

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

        // VIP: Empresas con información de contacto completa (email y teléfono)
        $vip = (clone $query)->where('tipo_cliente', 'e')
            ->whereNotNull('email')
            ->whereNotNull('telefono')
            ->where('email', '!=', '')
            ->where('telefono', '!=', '')
            ->count();

        // Frecuentes: Clientes con más de 3 ventas registradas
        $frecuentes = (clone $query)->whereHas('ventas', function ($q) {
            $q->whereNotIn('estado_de_venta', ['an']);
        }, '>', 3)->count();

        // Problemáticos: Clientes con calificación 'problematico' registrada
        $problematicos = (clone $query)->whereHas('calificaciones', function ($q) {
            $q->where('estado', 'problematico');
        })->count();

        // Nuevos: la tabla cliente no tiene timestamps, no se puede calcular
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
            // Notificar a otras pestañas en tiempo real ANTES de eliminar
            $this->broadcastChange('clientes', 'deleted', (string) $cliente->id);
            
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
     * Normaliza acentos, pasa a minúsculas y tokeniza por espacios.
     *
     * Ej: "  Juan   PÉREZ  " => ["juan", "perez"]
     *
     * Usado por el endpoint index para hacer una búsqueda multi-token
     * accent-insensitive. Sin esta normalización, buscar "Perez" no
     * encuentra clientes con "Pérez" en la DB, y buscar "Juan Perez"
     * falla porque busca la cadena completa en un solo campo.
     */
    private static function tokenizeSearch(string $raw): array
    {
        $accentMap = [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u',
            'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u', 'Ü' => 'u',
            'ñ' => 'n', 'Ñ' => 'n',
        ];
        $normalized = strtolower(strtr($raw, $accentMap));
        $parts = preg_split('/\s+/u', trim($normalized), -1, PREG_SPLIT_NO_EMPTY);
        // Descartamos tokens de 1 solo caracter para evitar ruido (p.ej. "y", "a", "1").
        $significativos = array_values(array_filter($parts, fn ($t) => mb_strlen($t) >= 2));

        // Si TODOS los tokens eran de 1 caracter no alcanza con descartarlos:
        // el caller lee "sin tokens" como "sin filtro" y termina devolviendo la
        // lista COMPLETA de contactos. Buscar "F S" traía a todo el mundo
        // (Fiorela incluida) como si fuera un match, cuando en realidad no se
        // estaba filtrando nada. En ese caso se usa el texto entero como un
        // único término: es literalmente lo que el usuario escribió, y si no
        // coincide con nadie corresponde devolver vacío, no devolver todo.
        if (empty($significativos)) {
            $completo = trim(preg_replace('/\s+/u', ' ', $normalized));

            return $completo === '' ? [] : [$completo];
        }

        return $significativos;
    }

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
