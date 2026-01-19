<?php

namespace App\Http\Controllers\Cajas;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cajas\CrearMovimientoInternoRequest;
use App\Models\MovimientoInterno;
use App\Models\SubCaja;
use App\Models\TransaccionCaja;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MovimientoInternoController extends Controller
{
    /**
     * Listar movimientos internos
     */
    public function index(): JsonResponse
    {
        $userId = auth()->id();
        
        $movimientos = MovimientoInterno::with([
            'subCajaOrigen',
            'subCajaDestino',
            'user'
        ])
        ->where('user_id', $userId)
        ->orderBy('fecha', 'desc')
        ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $movimientos,
        ]);
    }

    /**
     * Crear movimiento interno entre sub-cajas del mismo vendedor
     */
    public function store(CrearMovimientoInternoRequest $request): JsonResponse
    {
        try {
            return DB::transaction(function () use ($request) {
                $validated = $request->validated();
                $userId = auth()->id();

                $subCajaOrigen = SubCaja::with('cajaPrincipal')->findOrFail($validated['sub_caja_origen_id']);
                $subCajaDestino = SubCaja::with('cajaPrincipal')->findOrFail($validated['sub_caja_destino_id']);

                // Verificar que ambas sub-cajas pertenezcan al mismo vendedor
                if ($subCajaOrigen->cajaPrincipal->user_id !== $userId || 
                    $subCajaDestino->cajaPrincipal->user_id !== $userId) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Solo puedes mover dinero entre tus propias sub-cajas',
                    ], 403);
                }

                // Verificar saldo suficiente
                if ($subCajaOrigen->saldo_actual < $validated['monto']) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Saldo insuficiente en la sub-caja origen',
                        'data' => [
                            'saldo_disponible' => $subCajaOrigen->saldo_actual,
                            'monto_solicitado' => $validated['monto'],
                        ],
                    ], 400);
                }

                // Crear movimiento interno
                $movimiento = MovimientoInterno::create([
                    'id' => (string) Str::ulid(),
                    'sub_caja_origen_id' => $validated['sub_caja_origen_id'],
                    'sub_caja_destino_id' => $validated['sub_caja_destino_id'],
                    'monto' => $validated['monto'],
                    'despliegue_de_pago_id' => $validated['despliegue_de_pago_id'] ?? null,
                    'justificacion' => $validated['justificacion'],
                    'comprobante' => $validated['comprobante'] ?? null,
                    'user_id' => $userId,
                    'fecha' => now(),
                ]);

                // Actualizar saldos
                $saldoAnteriorOrigen = $subCajaOrigen->saldo_actual;
                $subCajaOrigen->saldo_actual -= $validated['monto'];
                $subCajaOrigen->save();

                $saldoAnteriorDestino = $subCajaDestino->saldo_actual;
                $subCajaDestino->saldo_actual += $validated['monto'];
                $subCajaDestino->save();

                // Registrar transacciones
                TransaccionCaja::create([
                    'id' => (string) Str::ulid(),
                    'sub_caja_id' => $subCajaOrigen->id,
                    'tipo_transaccion' => 'movimiento_interno_salida',
                    'monto' => $validated['monto'],
                    'saldo_anterior' => $saldoAnteriorOrigen,
                    'saldo_nuevo' => $subCajaOrigen->saldo_actual,
                    'descripcion' => "Movimiento a {$subCajaDestino->nombre}: {$validated['justificacion']}",
                    'referencia_id' => $movimiento->id,
                    'referencia_tipo' => 'movimiento_interno',
                    'user_id' => $userId,
                    'fecha' => now(),
                ]);

                TransaccionCaja::create([
                    'id' => (string) Str::ulid(),
                    'sub_caja_id' => $subCajaDestino->id,
                    'tipo_transaccion' => 'movimiento_interno_entrada',
                    'monto' => $validated['monto'],
                    'saldo_anterior' => $saldoAnteriorDestino,
                    'saldo_nuevo' => $subCajaDestino->saldo_actual,
                    'descripcion' => "Movimiento desde {$subCajaOrigen->nombre}: {$validated['justificacion']}",
                    'referencia_id' => $movimiento->id,
                    'referencia_tipo' => 'movimiento_interno',
                    'user_id' => $userId,
                    'fecha' => now(),
                ]);

                $movimiento->load(['subCajaOrigen', 'subCajaDestino', 'user']);

                return response()->json([
                    'success' => true,
                    'message' => 'Movimiento interno registrado exitosamente',
                    'data' => $movimiento,
                ], 201);
            });
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear movimiento interno: ' . $e->getMessage(),
            ], 500);
        }
    }
}
