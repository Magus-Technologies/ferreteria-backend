<?php

namespace App\Http\Controllers\Cajas;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cajas\CrearPrestamoRequest;
use App\Models\PrestamoEntreCajas;
use App\Models\SubCaja;
use App\Models\TransaccionCaja;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PrestamoEntreCajasController extends Controller
{
    /**
     * Listar préstamos
     */
    public function index(): JsonResponse
    {
        $prestamos = PrestamoEntreCajas::with([
            'subCajaOrigen',
            'subCajaDestino',
            'userPresta',
            'userRecibe'
        ])
        ->orderBy('fecha_prestamo', 'desc')
        ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $prestamos,
        ]);
    }

    /**
     * Crear préstamo entre cajas
     */
    public function store(CrearPrestamoRequest $request): JsonResponse
    {
        try {
            return DB::transaction(function () use ($request) {
                $validated = $request->validated();
                $userId = auth()->id();

                // Verificar saldo suficiente
                $subCajaOrigen = SubCaja::findOrFail($validated['sub_caja_origen_id']);
                
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

                $subCajaDestino = SubCaja::findOrFail($validated['sub_caja_destino_id']);

                // Crear préstamo
                $prestamo = PrestamoEntreCajas::create([
                    'id' => (string) Str::ulid(),
                    'sub_caja_origen_id' => $validated['sub_caja_origen_id'],
                    'sub_caja_destino_id' => $validated['sub_caja_destino_id'],
                    'monto' => $validated['monto'],
                    'despliegue_de_pago_id' => $validated['despliegue_de_pago_id'] ?? null,
                    'estado' => 'pendiente',
                    'motivo' => $validated['motivo'] ?? null,
                    'user_presta_id' => $userId,
                    'user_recibe_id' => $validated['user_recibe_id'],
                    'fecha_prestamo' => now(),
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
                    'tipo_transaccion' => 'prestamo_enviado',
                    'monto' => $validated['monto'],
                    'saldo_anterior' => $saldoAnteriorOrigen,
                    'saldo_nuevo' => $subCajaOrigen->saldo_actual,
                    'descripcion' => "Préstamo enviado a {$subCajaDestino->nombre}",
                    'referencia_id' => $prestamo->id,
                    'referencia_tipo' => 'prestamo',
                    'user_id' => $userId,
                    'fecha' => now(),
                ]);

                TransaccionCaja::create([
                    'id' => (string) Str::ulid(),
                    'sub_caja_id' => $subCajaDestino->id,
                    'tipo_transaccion' => 'prestamo_recibido',
                    'monto' => $validated['monto'],
                    'saldo_anterior' => $saldoAnteriorDestino,
                    'saldo_nuevo' => $subCajaDestino->saldo_actual,
                    'descripcion' => "Préstamo recibido de {$subCajaOrigen->nombre}",
                    'referencia_id' => $prestamo->id,
                    'referencia_tipo' => 'prestamo',
                    'user_id' => $validated['user_recibe_id'],
                    'fecha' => now(),
                ]);

                $prestamo->load(['subCajaOrigen', 'subCajaDestino', 'userPresta', 'userRecibe']);

                return response()->json([
                    'success' => true,
                    'message' => 'Préstamo registrado exitosamente',
                    'data' => $prestamo,
                ], 201);
            });
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear préstamo: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Devolver préstamo
     */
    public function devolver(string $id): JsonResponse
    {
        try {
            return DB::transaction(function () use ($id) {
                $prestamo = PrestamoEntreCajas::with(['subCajaOrigen', 'subCajaDestino'])
                    ->findOrFail($id);

                if ($prestamo->estado !== 'pendiente') {
                    return response()->json([
                        'success' => false,
                        'message' => 'Este préstamo ya fue devuelto o cancelado',
                    ], 400);
                }

                $userId = auth()->id();

                // Verificar saldo suficiente en destino
                if ($prestamo->subCajaDestino->saldo_actual < $prestamo->monto) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Saldo insuficiente para devolver el préstamo',
                    ], 400);
                }

                // Actualizar saldos (revertir)
                $saldoAnteriorDestino = $prestamo->subCajaDestino->saldo_actual;
                $prestamo->subCajaDestino->saldo_actual -= $prestamo->monto;
                $prestamo->subCajaDestino->save();

                $saldoAnteriorOrigen = $prestamo->subCajaOrigen->saldo_actual;
                $prestamo->subCajaOrigen->saldo_actual += $prestamo->monto;
                $prestamo->subCajaOrigen->save();

                // Actualizar préstamo
                $prestamo->update([
                    'estado' => 'devuelto',
                    'fecha_devolucion' => now(),
                ]);

                // Registrar transacciones
                TransaccionCaja::create([
                    'id' => (string) Str::ulid(),
                    'sub_caja_id' => $prestamo->subCajaDestino->id,
                    'tipo_transaccion' => 'egreso',
                    'monto' => $prestamo->monto,
                    'saldo_anterior' => $saldoAnteriorDestino,
                    'saldo_nuevo' => $prestamo->subCajaDestino->saldo_actual,
                    'descripcion' => "Devolución de préstamo a {$prestamo->subCajaOrigen->nombre}",
                    'referencia_id' => $prestamo->id,
                    'referencia_tipo' => 'devolucion_prestamo',
                    'user_id' => $userId,
                    'fecha' => now(),
                ]);

                TransaccionCaja::create([
                    'id' => (string) Str::ulid(),
                    'sub_caja_id' => $prestamo->subCajaOrigen->id,
                    'tipo_transaccion' => 'ingreso',
                    'monto' => $prestamo->monto,
                    'saldo_anterior' => $saldoAnteriorOrigen,
                    'saldo_nuevo' => $prestamo->subCajaOrigen->saldo_actual,
                    'descripcion' => "Devolución de préstamo recibida de {$prestamo->subCajaDestino->nombre}",
                    'referencia_id' => $prestamo->id,
                    'referencia_tipo' => 'devolucion_prestamo',
                    'user_id' => $userId,
                    'fecha' => now(),
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Préstamo devuelto exitosamente',
                    'data' => $prestamo->fresh(['subCajaOrigen', 'subCajaDestino']),
                ]);
            });
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al devolver préstamo: ' . $e->getMessage(),
            ], 500);
        }
    }
}
