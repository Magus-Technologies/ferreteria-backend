<?php

namespace App\Http\Controllers\FlujoFinanciero;

use App\Http\Controllers\Controller;
use App\Models\GastoExtra;
use App\Models\User;
use App\Models\AperturaCierreCaja;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use App\Traits\ManejaFlujoCajaExtra;

class GastoExtraController extends Controller
{
    use ManejaFlujoCajaExtra;

    /**
     * Listar todos los gastos extras
     */
    public function index()
    {
        $gastos = GastoExtra::with(['user', 'supervisor', 'desplieguePago.metodoDePago'])
            ->orderBy('created_at', 'desc')
            ->get();

        $subCajas = \App\Models\SubCaja::all();

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
        $query = GastoExtra::where('estado', 'aprobado');

        $totalGastos = $query->sum('monto');
        $totalTransacciones = $query->count();

        $gastosHoy = (clone $query)->whereDate('created_at', now()->toDateString())->sum('monto');
        $transaccionesHoy = (clone $query)->whereDate('created_at', now()->toDateString())->count();

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
            'supervisor_id' => 'nullable|string',
            'supervisor_password' => 'nullable|string',
            'despliegue_pago_id' => 'nullable|string',
        ]);

        try {
            return DB::transaction(function () use ($request) {
                $estado = 'pendiente';
                $supervisorValidadoId = null;

                // Validar supervisor si se envía
                if ($request->supervisor_id && $request->supervisor_password) {
                    $supervisor = $this->validarSupervisor($request->supervisor_id, $request->supervisor_password);

                    if (!$supervisor) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Credenciales de supervisor inválidas'
                        ], 403);
                    }

                    // Validar que exista apertura de hoy antes de aprobar
                    $aperturaDiaria = $this->validarAperturaDiaria();
                    if (!$aperturaDiaria) {
                        return response()->json([
                            'success' => false,
                            'message' => 'No hay apertura de caja para hoy. No se puede aprobar el gasto.'
                        ], 422);
                    }

                    $estado = 'aprobado';
                    $supervisorValidadoId = $supervisor->id;
                }

                $gastoId = Str::ulid()->toString();

                $gasto = GastoExtra::create([
                    'id' => $gastoId,
                    'monto' => $request->monto,
                    'concepto' => $request->concepto,
                    'estado' => $estado,
                    'user_id' => Auth::id() ?? User::first()?->id, // FIXME: remove fallback in prod
                    'supervisor_id' => $supervisorValidadoId,
                    'despliegue_pago_id' => $request->despliegue_pago_id,
                ]);

                if ($estado === 'aprobado') {
                    $this->registrarEnCajaActiva(
                        $gastoId,
                        'gasto_extra',
                        'egreso',
                        (float)$request->monto,
                        $request->despliegue_pago_id,
                        'Gasto Extra Automático: ' . $request->concepto
                    );
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Gasto registrado correctamente',
                    'data' => $gasto
                ], 201);
            });
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al registrar el gasto: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar un gasto extra (solo si está pendiente)
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
     * Anular un gasto extra
     */
    public function anular($id)
    {
        try {
            return DB::transaction(function () use ($id) {
                $gasto = GastoExtra::find($id);

                if (!$gasto) {
                    return response()->json(['success' => false, 'message' => 'Gasto no encontrado'], 404);
                }

                $estadoAnterior = $gasto->estado;

                $gasto->estado = 'anulado';
                $gasto->save();

                // Solo revertimos dinero en caja si había sido aprobado previamente
                if ($estadoAnterior === 'aprobado') {
                    $this->reversarEnCajaActiva(
                        $gasto->id,
                        'gasto_extra',
                        'Anulación manual de gasto extra'
                    );
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Gasto anulado correctamente',
                    'data' => $gasto
                ]);
            });
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al anular el gasto: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Aprobar un gasto pendiente
     */
    public function aprobar(Request $request, $id)
    {
        $request->validate([
            'supervisor_id' => 'required|string',
            'supervisor_password' => 'required|string',
        ]);

        try {
            return DB::transaction(function () use ($request, $id) {
                $gasto = GastoExtra::find($id);

                if (!$gasto) {
                    return response()->json(['success' => false, 'message' => 'Gasto no encontrado'], 404);
                }

                // Validar que exista apertura de hoy antes de aprobar
                $aperturaDiaria = $this->validarAperturaDiaria();
                if (!$aperturaDiaria) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No hay apertura de caja para hoy. No se puede aprobar el gasto.'
                    ], 422);
                }

                // Validar supervisor
                $supervisor = $this->validarSupervisor($request->supervisor_id, $request->supervisor_password);

                if (!$supervisor) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Credenciales de supervisor inválidas'
                    ], 403);
                }

                $gasto->estado = 'aprobado';
                $gasto->supervisor_id = $supervisor->id;
                $gasto->save();

                // Como recien se aprueba, lo impactamos en caja
                $this->registrarEnCajaActiva(
                    $gasto->id,
                    'gasto_extra',
                    'egreso',
                    (float)$gasto->monto,
                    $gasto->despliegue_pago_id,
                    'Aprobación de Gasto Extra: ' . $gasto->concepto
                );

                return response()->json([
                    'success' => true,
                    'message' => 'Gasto aprobado correctamente y debitado de caja',
                    'data' => $gasto
                ]);
            });
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al aprobar el gasto: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener gastos extras disponibles para asociar a una compra:
     * - Estado pendiente o aprobado (no anulados)
     * - Sin compra asociada (gasto_extra_id no usado en ninguna compra)
     * - Opcionalmente excluir el gasto ya asociado a la compra que se edita
     */
    public function disponibles(Request $request)
    {
        $excluirCompraId = $request->get('excluir_compra_id');

        $gastos = GastoExtra::with(['user', 'desplieguePago.metodoDePago'])
            ->whereIn('estado', ['pendiente', 'aprobado'])
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

    /**
     * Lógica compartida para validar un supervisor
     */
    private function validarSupervisor(string $supervisorId, string $password): ?User
    {
        $supervisor = User::find($supervisorId);
        if (!$supervisor || !$supervisor->es_supervisor || !$supervisor->supervisor_password) {
            return null;
        }

        if (!Hash::check($password, $supervisor->supervisor_password)) {
            return null;
        }

        return $supervisor;
    }

    /**
     * Validar que exista apertura de caja para hoy
     */
    private function validarAperturaDiaria(): ?AperturaCierreCaja
    {
        $hoy = now()->toDateString();

        $apertura = AperturaCierreCaja::where('estado', 'abierta')
            ->whereDate('fecha_apertura', $hoy)
            ->first();

        return $apertura;
    }
}
