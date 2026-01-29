<?php

namespace App\Http\Controllers\Cajas;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cajas\CrearSubCajaRequest as HttpCrearSubCajaRequest;
use App\Http\Requests\Cajas\ActualizarSubCajaRequest;
use App\Http\Resources\Cajas\SubCajaResource;
use App\UseCases\CrearSubCaja\CrearSubCajaRequest;
use App\UseCases\CrearSubCaja\CrearSubCajaUseCase;
use App\Repositories\Interfaces\SubCajaRepositoryInterface;
use App\Services\Interfaces\CajaServiceInterface;
use Illuminate\Http\JsonResponse;

class SubCajaController extends Controller
{
    public function __construct(
        private CrearSubCajaUseCase $crearSubCajaUseCase,
        private SubCajaRepositoryInterface $subCajaRepository,
        private CajaServiceInterface $cajaService
    ) {}

    /**
     * Listar sub-cajas de una caja principal
     */
    public function index(int $cajaPrincipalId): JsonResponse
    {
        try {
            $subCajas = $this->subCajaRepository->findByCajaPrincipalId($cajaPrincipalId);

            return response()->json([
                'success' => true,
                'data' => SubCajaResource::collection($subCajas),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Crear una nueva sub-caja
     */
    public function store(HttpCrearSubCajaRequest $request): JsonResponse
    {
        try {
            $useCaseRequest = new CrearSubCajaRequest(
                cajaPrincipalId: $request->validated('caja_principal_id'),
                nombre: $request->validated('nombre'),
                desplieguePagoIds: $request->validated('despliegues_pago_ids'),
                tiposComprobante: $request->validated('tipos_comprobante'),
                proposito: $request->validated('proposito')
            );

            $response = $this->crearSubCajaUseCase->execute($useCaseRequest);

            return response()->json([
                'success' => $response->success,
                'message' => $response->message,
                'data' => new SubCajaResource($response->subCaja),
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getCode() ?: 500);
        }
    }

    /**
     * Obtener una sub-caja por ID
     */
    public function show(string $id): JsonResponse
    {
        try {
            $subCaja = $this->subCajaRepository->findById($id);

            if (!$subCaja) {
                return response()->json([
                    'success' => false,
                    'message' => 'Sub-caja no encontrada',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => new SubCajaResource($subCaja),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Actualizar una sub-caja
     */
    public function update(ActualizarSubCajaRequest $request, int $id): JsonResponse
    {
        try {
            $subCaja = $this->cajaService->actualizarSubCaja($id, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Sub-caja actualizada exitosamente',
                'data' => new SubCajaResource($subCaja),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getCode() ?: 500);
        }
    }

    /**
     * Eliminar una sub-caja
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $deleted = $this->cajaService->eliminarSubCaja($id);

            return response()->json([
                'success' => $deleted,
                'message' => $deleted ? 'Sub-caja eliminada exitosamente' : 'No se pudo eliminar la sub-caja',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getCode() ?: 500);
        }
    }

    /**
     * Obtener métodos de pago disponibles para ventas
     * Retorna las sub-cajas con sus métodos de pago en formato: SubCaja/Banco/Método/Titular
     */
    public function metodosParaVentas(): JsonResponse
    {
        try {
            // Obtener TODAS las sub-cajas activas (cualquier usuario puede usarlas)
            $subCajas = \App\Models\SubCaja::where('estado', 1)->get();

            $metodos = [];

            foreach ($subCajas as $subCaja) {
                // Obtener los despliegues de pago de esta sub-caja
                $despliegues = \App\Models\DespliegueDePago::whereIn('id', $subCaja->despliegues_pago_ids)
                    ->with('metodoDePago')
                    ->where('activo', true)
                    ->where('mostrar', true)
                    ->get();

                foreach ($despliegues as $despliegue) {
                    $banco = $despliegue->metodoDePago->name ?? 'Sin Banco';
                    $metodo = $despliegue->name;
                    $titular = $despliegue->metodoDePago->nombre_titular ?? '';
                    $cuentaBancaria = $despliegue->metodoDePago->cuenta_bancaria ?? null;
                    
                    // Identificar tipo basándose en el nombre y cuenta bancaria
                    $tipo = 'efectivo'; // Por defecto
                    $bancoLower = strtolower($banco);
                    $metodoLower = strtolower($metodo);
                    
                    // Si tiene cuenta bancaria, es banco
                    if ($cuentaBancaria) {
                        $tipo = 'banco';
                    }
                    // Si el nombre contiene "efectivo", es efectivo
                    elseif (str_contains($bancoLower, 'efectivo') || str_contains($metodoLower, 'efectivo')) {
                        $tipo = 'efectivo';
                    }
                    // Si contiene nombres de bancos, es banco
                    elseif (str_contains($bancoLower, 'bcp') || 
                              str_contains($bancoLower, 'bbva') || 
                              str_contains($bancoLower, 'interbank') ||
                              str_contains($bancoLower, 'scotiabank') ||
                              str_contains($bancoLower, 'banco') ||
                              str_contains($metodoLower, 'bcp') ||
                              str_contains($metodoLower, 'bbva') ||
                              str_contains($metodoLower, 'interbank') ||
                              str_contains($metodoLower, 'scotiabank')) {
                        $tipo = 'banco';
                    }
                    // Si contiene billeteras digitales
                    elseif (str_contains($bancoLower, 'yape') || 
                              str_contains($bancoLower, 'plin') ||
                              str_contains($bancoLower, 'tunki') ||
                              str_contains($metodoLower, 'yape') ||
                              str_contains($metodoLower, 'plin') ||
                              str_contains($metodoLower, 'tunki')) {
                        $tipo = 'billetera';
                    }
                    
                    // Formato: SubCaja/Banco/Método/Titular
                    $label = $titular 
                        ? "{$subCaja->nombre}/{$banco}/{$metodo}/{$titular}"
                        : "{$subCaja->nombre}/{$banco}/{$metodo}";

                    // Crear un identificador único combinando sub_caja_id y despliegue_id
                    // Esto evita duplicados cuando múltiples sub-cajas usan el mismo despliegue
                    $uniqueValue = "{$subCaja->id}-{$despliegue->id}";
                    
                    $metodos[] = [
                        'value' => $uniqueValue, // Usar identificador único
                        'label' => $label,
                        'sub_caja_id' => $subCaja->id,
                        'despliegue_pago_id' => $despliegue->id, // Mantener el ID original
                        'sub_caja_nombre' => $subCaja->nombre,
                        'tipos_comprobante' => $subCaja->tipos_comprobante,
                        'banco' => $banco,
                        'metodo' => $metodo,
                        'titular' => $titular,
                        'tipo' => $tipo, // Agregar tipo para filtrado
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'data' => $metodos,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener sub-cajas con saldo del vendedor actual
     */
    public function getConSaldoVendedor(int $cajaPrincipalId): JsonResponse
    {
        try {
            $userId = auth()->id();
            $subCajas = $this->subCajaRepository->findByCajaPrincipalId($cajaPrincipalId);
            
            $subCajasConSaldo = $subCajas->map(function ($subCaja) use ($userId) {
                // Calcular saldo del vendedor en esta sub-caja
                $saldoVendedor = $this->calcularSaldoVendedorEnSubCaja($subCaja->id, $userId);
                
                return [
                    'id' => $subCaja->id,
                    'codigo' => $subCaja->codigo,
                    'nombre' => $subCaja->nombre,
                    'tipo_caja' => $subCaja->tipo_caja,
                    'saldo_actual' => $subCaja->saldo_actual, // Saldo total
                    'saldo_vendedor' => $saldoVendedor, // Saldo del vendedor actual
                    'despliegues_pago' => $subCaja->getDesplieguePagos(),
                    'es_caja_chica' => $subCaja->esCajaChica(),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $subCajasConSaldo,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener sub-cajas: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener TODAS las sub-cajas con saldo del vendedor actual (sin filtrar por caja principal)
     * Útil para vendedores que no tienen caja asignada pero interactúan con todas las sub-cajas
     */
    public function getTodasConSaldoVendedor(): JsonResponse
    {
        try {
            $userId = auth()->id();
            
            // Obtener TODAS las sub-cajas activas
            $subCajas = \App\Models\SubCaja::where('estado', true)->get();
            
            $subCajasConSaldo = $subCajas->map(function ($subCaja) use ($userId) {
                // Calcular saldo del vendedor en esta sub-caja
                $saldoVendedor = $this->calcularSaldoVendedorEnSubCaja($subCaja->id, $userId);
                
                return [
                    'id' => $subCaja->id,
                    'codigo' => $subCaja->codigo,
                    'nombre' => $subCaja->nombre,
                    'tipo_caja' => $subCaja->tipo_caja,
                    'caja_principal_id' => $subCaja->caja_principal_id,
                    'saldo_actual' => $subCaja->saldo_actual, // Saldo total
                    'saldo_vendedor' => $saldoVendedor, // Saldo del vendedor actual
                    'despliegues_pago' => $subCaja->getDesplieguePagos(),
                    'es_caja_chica' => $subCaja->esCajaChica(),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $subCajasConSaldo,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener sub-cajas: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener todos los vendedores con efectivo disponible
     * Los vendedores NO tienen cajas asignadas, solo tienen transacciones en las sub-cajas
     */
    public function getVendedoresConEfectivo(): JsonResponse
    {
        try {
            $userId = auth()->id();
            
            \Log::info('🔍 Buscando vendedores con efectivo', ['user_actual' => $userId]);
            
            // Obtener TODAS las Cajas Chicas
            $cajasChicas = \App\Models\SubCaja::where('tipo_caja', 'CC')->get();
            
            \Log::info('📊 Cajas Chicas encontradas', ['total' => $cajasChicas->count()]);
            
            // Obtener todos los vendedores que tienen transacciones
            $vendedoresConTransacciones = \App\Models\TransaccionCaja::whereIn('sub_caja_id', $cajasChicas->pluck('id'))
                ->where('user_id', '!=', $userId) // Excluir usuario actual
                ->distinct()
                ->pluck('user_id');
            
            \Log::info('👥 Vendedores con transacciones', ['total' => $vendedoresConTransacciones->count()]);
            
            $vendedoresConEfectivo = [];

            foreach ($vendedoresConTransacciones as $vendedorId) {
                $vendedor = \App\Models\User::find($vendedorId);
                
                if (!$vendedor) {
                    continue;
                }
                
                \Log::info('🔍 Revisando vendedor', ['vendedor' => $vendedor->name]);
                
                // Calcular efectivo total del vendedor en TODAS las Cajas Chicas
                $efectivoTotal = 0;
                
                foreach ($cajasChicas as $cajaChica) {
                    $efectivoEnCaja = $this->calcularEfectivoEnSubCaja($cajaChica->id, $vendedorId);
                    $efectivoTotal += $efectivoEnCaja;
                    
                    if ($efectivoEnCaja > 0) {
                        \Log::info('💰 Efectivo en caja', [
                            'vendedor' => $vendedor->name,
                            'caja' => $cajaChica->nombre,
                            'efectivo' => $efectivoEnCaja
                        ]);
                    }
                }
                
                \Log::info('💰 Efectivo total calculado', [
                    'vendedor' => $vendedor->name,
                    'efectivo_total' => $efectivoTotal
                ]);
                
                // Solo incluir si tiene efectivo > 0
                if ($efectivoTotal > 0) {
                    $vendedoresConEfectivo[] = [
                        'vendedor_id' => $vendedorId,
                        'vendedor_nombre' => $vendedor->name,
                        'efectivo_disponible' => number_format($efectivoTotal, 2, '.', ''),
                    ];
                }
            }
            
            \Log::info('✅ Vendedores con efectivo', ['total' => count($vendedoresConEfectivo)]);

            return response()->json([
                'success' => true,
                'data' => $vendedoresConEfectivo,
            ]);
        } catch (\Exception $e) {
            \Log::error('❌ Error al obtener vendedores con efectivo', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener vendedores: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Calcular efectivo disponible en una sub-caja específica del vendedor
     * Solo considera transacciones de EFECTIVO
     */
    private function calcularEfectivoEnSubCaja(int $subCajaId, string|int $vendedorId): float
    {
        $subCaja = \App\Models\SubCaja::find($subCajaId);
        if (!$subCaja) {
            return 0;
        }
        
        $montoInicial = 0;
        
        // Solo si es Caja Chica, considerar la distribución inicial de efectivo
        if ($subCaja->tipo_caja === 'CC') {
            // Obtener la apertura activa de la caja principal
            $aperturaActiva = \App\Models\AperturaCierreCaja::where('caja_principal_id', $subCaja->caja_principal_id)
                ->whereNull('fecha_cierre')
                ->first();
            
            if ($aperturaActiva) {
                // Sumar solo las distribuciones de efectivo del vendedor
                $distribuciones = \App\Models\DistribucionEfectivoVendedor::where('apertura_cierre_caja_id', $aperturaActiva->id)
                    ->where('user_id', $vendedorId)
                    ->get();
                
                $montoInicial = $distribuciones->sum('monto');
            }
        }
        
        // Obtener IDs de despliegues de pago tipo EFECTIVO de esta sub-caja
        $desplieguePagoIds = $subCaja->despliegues_pago_ids ?? [];
        
        // Filtrar solo los que son efectivo
        $desplieguePagoEfectivoIds = \App\Models\DespliegueDePago::whereIn('id', $desplieguePagoIds)
            ->whereHas('metodoDePago', function ($query) {
                $query->whereNull('cuenta_bancaria')
                      ->where(function ($q) {
                          $q->where('name', 'like', '%efectivo%')
                            ->orWhere('name', 'like', '%Efectivo%');
                      });
            })
            ->pluck('id')
            ->toArray();
        
        // Si no hay métodos de efectivo en esta sub-caja, retornar solo el monto inicial
        if (empty($desplieguePagoEfectivoIds)) {
            return $montoInicial;
        }
        
        // Calcular transacciones de efectivo (excluyendo aperturas)
        $transacciones = \App\Models\TransaccionCaja::where('sub_caja_id', $subCajaId)
            ->where('user_id', $vendedorId)
            ->where(function ($query) use ($desplieguePagoEfectivoIds) {
                $query->whereIn('despliegue_pago_id', $desplieguePagoEfectivoIds)
                      ->orWhere(function ($q) {
                          $q->whereNull('despliegue_pago_id')
                            ->where('referencia_tipo', 'venta');
                      });
            })
            ->where(function ($query) {
                $query->whereNull('referencia_tipo')
                      ->orWhere('referencia_tipo', '!=', 'apertura');
            })
            ->get();
        
        $ingresos = $transacciones->where('tipo_transaccion', 'ingreso')->sum('monto');
        $egresos = $transacciones->where('tipo_transaccion', 'egreso')->sum('monto');
        
        return $montoInicial + $ingresos - $egresos;
    }

    /**
     * Calcular saldo del vendedor en una sub-caja
     * Considera TODAS las transacciones del vendedor en esta sub-caja
     */
    private function calcularSaldoVendedorEnSubCaja(int $subCajaId, string|int $userId): string
    {
        // Obtener la sub-caja
        $subCaja = \App\Models\SubCaja::find($subCajaId);
        if (!$subCaja) {
            return '0.00';
        }
        
        $montoInicial = 0;
        
        // Solo si es Caja Chica, considerar la distribución inicial de efectivo
        if ($subCaja->esCajaChica()) {
            // Obtener la apertura activa de la caja principal
            $aperturaActiva = \App\Models\AperturaCierreCaja::where('caja_principal_id', $subCaja->caja_principal_id)
                ->whereNull('fecha_cierre')
                ->first();
            
            if ($aperturaActiva) {
                // Sumar solo las distribuciones de efectivo del vendedor
                $distribuciones = \App\Models\DistribucionEfectivoVendedor::where('apertura_cierre_caja_id', $aperturaActiva->id)
                    ->where('user_id', $userId)
                    ->get();
                
                $montoInicial = $distribuciones->sum('monto');
            }
        }
        
        // Obtener IDs de despliegues de pago de esta sub-caja
        $desplieguePagoIds = $subCaja->despliegues_pago_ids ?? [];
        
        // Si no hay métodos de pago en esta sub-caja, retornar solo el monto inicial
        if (empty($desplieguePagoIds)) {
            return number_format($montoInicial, 2, '.', '');
        }
        
        // Calcular TODAS las transacciones del vendedor en esta sub-caja
        // EXCLUIR transacciones de tipo "apertura" para evitar duplicar las distribuciones
        $transacciones = \App\Models\TransaccionCaja::where('sub_caja_id', $subCajaId)
            ->where('user_id', $userId)
            ->whereIn('despliegue_pago_id', $desplieguePagoIds)
            ->where(function ($query) {
                $query->whereNull('referencia_tipo')
                      ->orWhere('referencia_tipo', '!=', 'apertura');
            })
            ->get();
        
        $ingresos = $transacciones->where('tipo_transaccion', 'ingreso')->sum('monto');
        $egresos = $transacciones->where('tipo_transaccion', 'egreso')->sum('monto');
        
        // Saldo = Monto inicial (solo en Caja Chica) + Ingresos - Egresos
        $saldo = $montoInicial + $ingresos - $egresos;
        
        return number_format($saldo, 2, '.', '');
    }

    /**
     * Buscar o crear sub-caja por despliegue de pago
     * Este método permite obtener la sub-caja correcta cuando solo se tiene el ID del despliegue de pago
     */
    public function buscarPorDesplieguePago(string $desplieguePagoId): JsonResponse
    {
        try {
            // Buscar el despliegue de pago
            $desplieguePago = \App\Models\DespliegueDePago::with('metodoDePago')->find($desplieguePagoId);
            
            if (!$desplieguePago) {
                return response()->json([
                    'success' => false,
                    'message' => 'Método de pago no encontrado',
                ], 404);
            }

            // Buscar sub-cajas que contengan este despliegue de pago
            $subCajas = \App\Models\SubCaja::whereJsonContains('despliegues_pago_ids', $desplieguePagoId)
                ->where('estado', 1)
                ->with(['cajaPrincipal.user'])
                ->get();

            if ($subCajas->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No hay sub-cajas configuradas para este método de pago',
                    'data' => null,
                ], 404);
            }

            // Si hay múltiples sub-cajas, retornar la primera activa
            $subCaja = $subCajas->first();

            return response()->json([
                'success' => true,
                'data' => new SubCajaResource($subCaja),
                'despliegue_pago' => [
                    'id' => $desplieguePago->id,
                    'name' => $desplieguePago->name,
                    'metodo_de_pago' => [
                        'id' => $desplieguePago->metodoDePago->id,
                        'name' => $desplieguePago->metodoDePago->name,
                        'cuenta_bancaria' => $desplieguePago->metodoDePago->cuenta_bancaria,
                        'nombre_titular' => $desplieguePago->metodoDePago->nombre_titular,
                    ],
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
