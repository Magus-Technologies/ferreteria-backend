<?php

namespace App\Http\Controllers\Cajas;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cajas\AperturarCajaRequest;
use App\Models\AperturaCierreCaja;
use App\Models\CajaPrincipal;
use App\Models\MovimientoCaja;
use App\Models\SubCaja;
use App\Models\TransaccionCaja;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Exception;

class AperturaCajaController extends Controller
{
    /**
     * Aperturar una caja principal con distribución a vendedores
     */
    public function aperturar(AperturarCajaRequest $request): JsonResponse
    {
        try {
            return DB::transaction(function () use ($request) {
                // Obtener user_id del usuario autenticado
                $userId = auth()->id();
                
                if (!$userId) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Usuario no autenticado',
                    ], 401);
                }

                $cajaPrincipalId = $request->validated('caja_principal_id');
                $montoApertura = $request->validated('monto_apertura');
                $conteoBilletes = $request->validated('conteo_billetes_monedas');
                $vendedores = $request->validated('vendedores', []); // Array de vendedores

                // 1. Verificar que la caja principal existe
                $cajaPrincipal = CajaPrincipal::find($cajaPrincipalId);
                if (!$cajaPrincipal) {
                    return response()->json([
                        'success' => false,
                        'message' => 'La caja principal no existe',
                    ], 404);
                }

                // 2. Buscar la Caja Chica de esta caja principal
                $cajaChica = SubCaja::where('caja_principal_id', $cajaPrincipalId)
                    ->where('tipo_caja', 'CC')
                    ->first();

                if (!$cajaChica) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No se encontró la Caja Chica para esta caja principal',
                    ], 404);
                }

                // 3. Calcular monto total si hay vendedores
                $montoTotal = $montoApertura;
                if (!empty($vendedores)) {
                    $montoTotal = collect($vendedores)->sum('monto');
                }

                // 4. Verificar si ya hay una apertura activa
                $aperturaActiva = AperturaCierreCaja::where('caja_principal_id', $cajaPrincipalId)
                    ->where('estado', 'abierta')
                    ->first();

                if ($aperturaActiva) {
                    // ✅ Si ya hay apertura, solo agregar el monto a la caja chica
                    $saldoAnterior = $cajaChica->saldo_actual;
                    $cajaChica->saldo_actual += $montoTotal;
                    $cajaChica->save();
                    
                    // Actualizar el monto de apertura acumulado
                    $aperturaActiva->monto_apertura += $montoTotal;
                    $aperturaActiva->save();
                    
                    // Registrar distribución a vendedores
                    if (!empty($vendedores)) {
                        foreach ($vendedores as $vendedor) {
                            \App\Models\DistribucionEfectivoVendedor::create([
                                'apertura_cierre_caja_id' => $aperturaActiva->id,
                                'user_id' => $vendedor['user_id'],
                                'monto' => $vendedor['monto'],
                                'conteo_billetes_monedas' => $vendedor['conteo_billetes_monedas'] ?? null,
                            ]);
                        }
                    }
                    
                    // Buscar el despliegue de pago "Efectivo"
                    $desplieguePagoEfectivo = \App\Models\DespliegueDePago::where('name', 'Efectivo')
                        ->where('activo', true)
                        ->first();
                    
                    // Registrar transacción
                    TransaccionCaja::create([
                        'id' => (string) Str::ulid(),
                        'sub_caja_id' => $cajaChica->id,
                        'despliegue_pago_id' => $desplieguePagoEfectivo?->id,
                        'tipo_transaccion' => 'ingreso',
                        'monto' => $montoTotal,
                        'saldo_anterior' => $saldoAnterior,
                        'saldo_nuevo' => $cajaChica->saldo_actual,
                        'descripcion' => 'Distribución de efectivo a vendedores',
                        'referencia_id' => $aperturaActiva->id,
                        'referencia_tipo' => 'apertura',
                        'user_id' => $userId,
                        'fecha' => now(),
                    ]);
                    
                    // Registrar movimiento
                    MovimientoCaja::create([
                        'id' => (string) Str::ulid(),
                        'apertura_cierre_id' => $aperturaActiva->id,
                        'caja_principal_id' => $cajaPrincipalId,
                        'sub_caja_id' => $cajaChica->id,
                        'cajero_id' => $userId,
                        'fecha_hora' => now(),
                        'tipo_movimiento' => 'ingreso',
                        'concepto' => "Distribución de efectivo a " . count($vendedores) . " vendedor(es): S/. {$montoTotal}",
                        'saldo_inicial' => $saldoAnterior,
                        'ingreso' => $montoTotal,
                        'salida' => 0,
                        'saldo_final' => $cajaChica->saldo_actual,
                        'estado_caja' => 'abierta',
                    ]);
                    
                    $aperturaActiva->load(['cajaPrincipal', 'subCaja', 'user', 'distribucionesVendedores.vendedor']);
                    
                    return response()->json([
                        'success' => true,
                        'message' => "Efectivo distribuido exitosamente. Nuevo saldo: S/. {$cajaChica->saldo_actual}",
                        'data' => [
                            'apertura_id' => $aperturaActiva->id,
                            'monto_agregado' => number_format($montoTotal, 2, '.', ''),
                            'monto_apertura_total' => number_format($aperturaActiva->monto_apertura, 2, '.', ''),
                            'saldo_anterior' => number_format($saldoAnterior, 2, '.', ''),
                            'saldo_nuevo' => number_format($cajaChica->saldo_actual, 2, '.', ''),
                            'vendedores_count' => count($vendedores),
                            'distribuciones' => $aperturaActiva->distribucionesVendedores->map(function ($dist) {
                                return [
                                    'vendedor' => $dist->vendedor->name,
                                    'monto' => number_format($dist->monto, 2, '.', ''),
                                ];
                            }),
                        ],
                    ], 200);
                }

                // 5. Si no hay apertura, crear una nueva
                $apertura = AperturaCierreCaja::create([
                    'caja_principal_id' => $cajaPrincipalId,
                    'sub_caja_id' => $cajaChica->id,
                    'user_id' => $userId,
                    'monto_apertura' => $montoTotal,
                    'conteo_apertura_billetes_monedas' => $conteoBilletes,
                    'fecha_apertura' => now(),
                    'estado' => 'abierta',
                ]);

                // 6. Registrar distribución a vendedores
                if (!empty($vendedores)) {
                    foreach ($vendedores as $vendedor) {
                        \App\Models\DistribucionEfectivoVendedor::create([
                            'apertura_cierre_caja_id' => $apertura->id,
                            'user_id' => $vendedor['user_id'],
                            'monto' => $vendedor['monto'],
                            'conteo_billetes_monedas' => $vendedor['conteo_billetes_monedas'] ?? null,
                        ]);
                    }
                }

                // 7. Actualizar el saldo de la Caja Chica
                $cajaChica->saldo_actual += $montoTotal;
                $cajaChica->save();

                // 8. Cargar relaciones para la respuesta
                $apertura->load(['cajaPrincipal', 'subCaja', 'user', 'distribucionesVendedores.vendedor']);

                return response()->json([
                    'success' => true,
                    'message' => 'Caja aperturada y efectivo distribuido exitosamente',
                    'data' => [
                        'id' => $apertura->id,
                        'caja_principal_id' => $apertura->caja_principal_id,
                        'sub_caja_id' => $apertura->sub_caja_id,
                        'user_id' => $apertura->user_id,
                        'monto_apertura' => number_format($apertura->monto_apertura, 2, '.', ''),
                        'fecha_apertura' => $apertura->fecha_apertura->toIso8601String(),
                        'estado' => $apertura->estado,
                        'vendedores_count' => count($vendedores),
                        'distribuciones' => $apertura->distribucionesVendedores->map(function ($dist) {
                            return [
                                'vendedor_id' => $dist->user_id,
                                'vendedor' => $dist->vendedor->name,
                                'monto' => number_format($dist->monto, 2, '.', ''),
                            ];
                        }),
                        'caja_principal' => [
                            'id' => $apertura->cajaPrincipal->id,
                            'codigo' => $apertura->cajaPrincipal->codigo,
                            'nombre' => $apertura->cajaPrincipal->nombre,
                        ],
                        'sub_caja' => [
                            'id' => $apertura->subCaja->id,
                            'codigo' => $apertura->subCaja->codigo,
                            'nombre' => $apertura->subCaja->nombre,
                            'saldo_actual' => number_format($apertura->subCaja->saldo_actual, 2, '.', ''),
                        ],
                    ],
                ], 200);
            });
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al aperturar la caja: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Consultar si una caja tiene apertura activa
     */
    public function consultaApertura(int $cajaPrincipalId): JsonResponse
    {
        try {
            $apertura = AperturaCierreCaja::where('caja_principal_id', $cajaPrincipalId)
                ->where('estado', 'abierta')
                ->with(['cajaPrincipal', 'subCaja', 'user'])
                ->first();

            if (!$apertura) {
                return response()->json([
                    'success' => true,
                    'message' => 'No hay apertura activa',
                    'data' => null,
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Apertura activa encontrada',
                'data' => [
                    'id' => $apertura->id,
                    'caja_principal_id' => $apertura->caja_principal_id,
                    'monto_apertura' => number_format($apertura->monto_apertura, 2, '.', ''),
                    'fecha_apertura' => $apertura->fecha_apertura->toIso8601String(),
                    'estado' => $apertura->estado,
                    'caja_principal' => [
                        'id' => $apertura->cajaPrincipal->id,
                        'codigo' => $apertura->cajaPrincipal->codigo,
                        'nombre' => $apertura->cajaPrincipal->nombre,
                    ],
                    'sub_caja' => [
                        'id' => $apertura->subCaja->id,
                        'saldo_actual' => number_format($apertura->subCaja->saldo_actual, 2, '.', ''),
                    ],
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al consultar apertura: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Listar historial de aperturas/cierres
     */
    public function historial(): JsonResponse
    {
        try {
            $userId = auth()->id();
            $perPage = request()->query('per_page', 15);
            
            // Construir la consulta base
            $query = AperturaCierreCaja::with(['cajaPrincipal', 'subCaja', 'user']);
            
            // Si hay usuario autenticado, filtrar por sus cajas
            if ($userId) {
                $query->whereHas('cajaPrincipal', function ($q) use ($userId) {
                    $q->where('user_id', $userId);
                });
            }
            
            $historial = $query->orderBy('fecha_apertura', 'desc')
                ->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $historial->map(function ($apertura) {
                    return [
                        'id' => $apertura->id,
                        'caja_principal_id' => $apertura->caja_principal_id,
                        'monto_apertura' => number_format($apertura->monto_apertura, 2, '.', ''),
                        'monto_cierre' => $apertura->monto_cierre ? number_format($apertura->monto_cierre, 2, '.', '') : null,
                        'fecha_apertura' => $apertura->fecha_apertura->toIso8601String(),
                        'fecha_cierre' => $apertura->fecha_cierre?->toIso8601String(),
                        'estado' => $apertura->estado,
                        'caja_principal' => [
                            'id' => $apertura->cajaPrincipal->id,
                            'codigo' => $apertura->cajaPrincipal->codigo,
                            'nombre' => $apertura->cajaPrincipal->nombre,
                        ],
                        'sub_caja' => [
                            'id' => $apertura->subCaja->id,
                            'codigo' => $apertura->subCaja->codigo,
                            'nombre' => $apertura->subCaja->nombre,
                        ],
                        'user' => [
                            'id' => $apertura->user->id,
                            'name' => $apertura->user->name,
                        ],
                    ];
                }),
                'pagination' => [
                    'total' => $historial->total(),
                    'per_page' => $historial->perPage(),
                    'current_page' => $historial->currentPage(),
                    'last_page' => $historial->lastPage(),
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener historial: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Listar TODAS las aperturas/cierres (para administradores)
     */
    public function historialTodas(): JsonResponse
    {
        try {
            $perPage = request()->query('per_page', 15);
            $cajaPrincipalId = request()->query('caja_principal_id');
            
            $query = AperturaCierreCaja::with(['cajaPrincipal', 'subCaja', 'user', 'distribucionesVendedores.vendedor']);
            
            // Filtrar por caja principal si se especifica
            if ($cajaPrincipalId) {
                $query->where('caja_principal_id', $cajaPrincipalId);
            }
            
            $historial = $query->orderBy('fecha_apertura', 'desc')
                ->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $historial->map(function ($apertura) {
                    return [
                        'id' => $apertura->id,
                        'caja_principal_id' => $apertura->caja_principal_id,
                        'monto_apertura' => number_format($apertura->monto_apertura, 2, '.', ''),
                        'monto_cierre' => $apertura->monto_cierre ? number_format($apertura->monto_cierre, 2, '.', '') : null,
                        'fecha_apertura' => $apertura->fecha_apertura->toIso8601String(),
                        'fecha_cierre' => $apertura->fecha_cierre?->toIso8601String(),
                        'estado' => $apertura->estado,
                        'caja_principal' => [
                            'id' => $apertura->cajaPrincipal->id,
                            'codigo' => $apertura->cajaPrincipal->codigo,
                            'nombre' => $apertura->cajaPrincipal->nombre,
                        ],
                        'sub_caja' => [
                            'id' => $apertura->subCaja->id,
                            'codigo' => $apertura->subCaja->codigo,
                            'nombre' => $apertura->subCaja->nombre,
                        ],
                        'user' => [
                            'id' => $apertura->user->id,
                            'name' => $apertura->user->name,
                        ],
                        'distribuciones_vendedores' => $apertura->distribucionesVendedores->map(function ($dist) {
                            return [
                                'vendedor_id' => $dist->user_id,
                                'vendedor' => $dist->vendedor->name,
                                'monto' => number_format($dist->monto, 2, '.', ''),
                            ];
                        }),
                    ];
                }),
                'pagination' => [
                    'total' => $historial->total(),
                    'per_page' => $historial->perPage(),
                    'current_page' => $historial->currentPage(),
                    'last_page' => $historial->lastPage(),
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener historial: ' . $e->getMessage(),
            ], 500);
        }
    }
}
