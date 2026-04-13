<?php

namespace App\Http\Controllers\FlujoFinanciero;

use App\Http\Controllers\Controller;
use App\Models\GastoExtra;
use App\Models\SubCaja;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Traits\ManejaFlujoCajaExtra;

class GastoExtraController extends Controller
{
    use ManejaFlujoCajaExtra;
    /**
     * Listar todos los gastos extras
     */
    public function index()
    {
        $gastos = GastoExtra::with(['user', 'desplieguePago.metodoDePago', 'compra.proveedor'])
            ->orderBy('created_at', 'desc')
            ->get();

        $subCajas = SubCaja::all();

        $gastos->each(function ($gasto) use ($subCajas) {
            if ($gasto->desplieguePago) {
                $subcaja = $subCajas->first(function ($sc) use ($gasto) {
                    $ids = $sc->despliegues_pago_ids ?? [];
                    if (is_string($ids)) {
                        $ids = json_decode($ids, true) ?? [];
                    }
                    return in_array($gasto->despliegue_pago_id, $ids);
                });

                if ($subcaja) {
                    $gasto->desplieguePago->subcaja_nombre = $subcaja->nombre;
                }
            }
        });

        return response()->json([
            'success' => true,
            'data' => $gastos
        ]);
    }

    /**
     * Resumen de gastos extras para las tarjetas
     */
    public function resumen()
    {
        $totalGastos = GastoExtra::sum('monto');
        $totalTransacciones = GastoExtra::count();

        $gastosHoy = GastoExtra::whereDate('created_at', now()->toDateString())->sum('monto');
        $transaccionesHoy = GastoExtra::whereDate('created_at', now()->toDateString())->count();

        $promedioGasto = $totalTransacciones > 0 ? $totalGastos / $totalTransacciones : 0;

        return response()->json([
            'success' => true,
            'data' => [
                'total_gastos' => round($totalGastos, 2),
                'gastos_hoy' => round($gastosHoy, 2),
                'total_transacciones' => $totalTransacciones,
                'transacciones_hoy' => $transaccionesHoy,
                'promedio_gasto' => round($promedioGasto, 2),
            ]
        ]);
    }

    /**
     * Crear un nuevo gasto extra
     */
    public function store(Request $request)
    {
        $request->validate([
            'monto' => 'required|numeric|min:0.01',
            'concepto' => 'required|string|max:1000',
            'despliegue_pago_id' => 'nullable|string',
        ]);

        try {
            return DB::transaction(function () use ($request) {
                // Determinar la sub-caja a partir del método de pago
                $subCajaId = null;
                if ($request->despliegue_pago_id) {
                    $dp = \App\Models\DespliegueDePago::with('metodoDePago')->find($request->despliegue_pago_id);
                    $subCajaId = $dp?->metodoDePago?->subcaja_id;
                }

                $gasto = GastoExtra::create([
                    'monto' => $request->monto,
                    'concepto' => $request->concepto,
                    'user_id' => Auth::id() ?? User::first()?->id,
                    'despliegue_pago_id' => $request->despliegue_pago_id,
                ]);

                // Registrar en caja (esto validará saldo y lanzará excepción si es insuficiente)
                $this->registrarEnCajaActiva(
                    $gasto->id,
                    'gasto_extra',
                    'egreso',
                    (float) $request->monto,
                    $request->despliegue_pago_id,
                    'Gasto Extra: ' . $request->concepto,
                    $subCajaId
                );

                return response()->json([
                    'success' => true,
                    'message' => 'Gasto registrado correctamente y descontado de caja',
                    'data' => $gasto
                ], 201);
            });
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422); // 422 for validation/balance errors
        }
    }

    /**
     * Actualizar un gasto extra
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'monto' => 'required|numeric|min:0.01',
            'concepto' => 'required|string|max:1000',
            'despliegue_pago_id' => 'nullable|string',
        ]);

        try {
            return DB::transaction(function () use ($request, $id) {
                $gasto = GastoExtra::find($id);

                if (!$gasto) {
                    return response()->json(['success' => false, 'message' => 'Gasto no encontrado'], 404);
                }

                $gasto->update([
                    'monto' => $request->monto,
                    'concepto' => $request->concepto,
                    'despliegue_pago_id' => $request->despliegue_pago_id,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Gasto actualizado correctamente',
                    'data' => $gasto
                ]);
            });
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el gasto: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar un gasto extra (solo si no está asociado a una compra)
     */
    public function destroy($id)
    {
        try {
            return DB::transaction(function () use ($id) {
                $gasto = GastoExtra::with('compra')->find($id);

                if (!$gasto) {
                    return response()->json(['success' => false, 'message' => 'Gasto no encontrado'], 404);
                }

                if ($gasto->compra) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No se puede eliminar un gasto ya asociado a una compra.'
                    ], 422);
                }

                $gastoId = $gasto->id;
                $concepto = $gasto->concepto;
                
                $gasto->delete();

                // Reversar el dinero en caja
                $this->reversarEnCajaActiva(
                    $gastoId,
                    'gasto_extra',
                    'Anulación de gasto: ' . $concepto
                );

                return response()->json([
                    'success' => true,
                    'message' => 'Gasto eliminado correctamente y monto devuelto a caja',
                ]);
            });
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el gasto: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener gastos extras disponibles para asociar a una compra:
     * - Sin compra asociada
     * - Opcionalmente excluir el gasto ya asociado a la compra que se edita
     */
    public function disponibles(Request $request)
    {
        $excluirCompraId = $request->get('excluir_compra_id');

        $gastos = GastoExtra::with(['user', 'desplieguePago.metodoDePago'])
            ->whereDoesntHave('compra', function ($query) use ($excluirCompraId) {
                if ($excluirCompraId) {
                    $query->where('id', '!=', $excluirCompraId);
                }
            })
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $gastos,
        ]);
    }
}
