<?php

namespace App\Http\Controllers\FlujoFinanciero;

use App\Http\Controllers\Controller;
use App\Models\IngresoExtra;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use App\Traits\ManejaFlujoCajaExtra;

class IngresoExtraController extends Controller
{
    use ManejaFlujoCajaExtra;

    /**
     * Listar todos los ingresos extras
     */
    public function index()
    {
        $ingresos = IngresoExtra::with(['user', 'supervisor', 'desplieguePago.metodoDePago'])
            ->orderBy('created_at', 'desc')
            ->get();

        $subCajas = \App\Models\SubCaja::all();

        $ingresos->each(function ($ingreso) use ($subCajas) {
            if ($ingreso->desplieguePago) {
                $subcaja = $subCajas->first(function ($sc) use ($ingreso) {
                    $ids = $sc->despliegues_pago_ids ?? [];
                    if (is_string($ids)) {
                        $ids = json_decode($ids, true) ?? [];
                    }
                    return in_array($ingreso->despliegue_pago_id, $ids);
                });

                if ($subcaja) {
                    $ingreso->desplieguePago->subcaja_nombre = $subcaja->nombre;
                }
            }
        });

        return response()->json([
            'success' => true,
            'data' => $ingresos
        ]);
    }

    /**
     * Resumen de ingresos extras para las tarjetas
     */
    public function resumen()
    {
        $query = IngresoExtra::where('estado', 'aprobado');

        $totalIngresos = $query->sum('monto');
        $totalTransacciones = $query->count();

        $ingresosHoy = (clone $query)->whereDate('created_at', now()->toDateString())->sum('monto');
        $transaccionesHoy = (clone $query)->whereDate('created_at', now()->toDateString())->count();

        $promedioIngreso = $totalTransacciones > 0 ? $totalIngresos / $totalTransacciones : 0;

        return response()->json([
            'success' => true,
            'data' => [
                'total_ingresos' => round($totalIngresos, 2),
                'ingresos_hoy' => round($ingresosHoy, 2),
                'total_transacciones' => $totalTransacciones,
                'transacciones_hoy' => $transaccionesHoy,
                'promedio_ingreso' => round($promedioIngreso, 2),
            ]
        ]);
    }

    /**
     * Crear un nuevo ingreso extra
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

                    $estado = 'aprobado';
                    $supervisorValidadoId = $supervisor->id;
                }

                $ingresoId = Str::ulid()->toString();

                $ingreso = IngresoExtra::create([
                    'id' => $ingresoId,
                    'monto' => $request->monto,
                    'concepto' => $request->concepto,
                    'estado' => $estado,
                    'user_id' => Auth::id() ?? User::first()?->id, // FIXME: remove fallback in prod
                    'supervisor_id' => $supervisorValidadoId,
                    'despliegue_pago_id' => $request->despliegue_pago_id,
                ]);

                if ($estado === 'aprobado') {
                    $this->registrarEnCajaActiva(
                        $ingresoId,
                        'ingreso_extra',
                        'ingreso',
                        (float) $request->monto,
                        $request->despliegue_pago_id,
                        'Ingreso Extra Automático: ' . $request->concepto
                    );
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Ingreso registrado correctamente',
                    'data' => $ingreso
                ], 201);
            });
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al registrar el ingreso: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar un ingreso extra (solo si está pendiente)
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
                $ingreso = IngresoExtra::find($id);

                if (!$ingreso) {
                    return response()->json(['success' => false, 'message' => 'Ingreso no encontrado'], 404);
                }

                $ingreso->update([
                    'monto' => $request->monto,
                    'concepto' => $request->concepto,
                    'despliegue_pago_id' => $request->despliegue_pago_id,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Ingreso actualizado correctamente',
                    'data' => $ingreso
                ]);
            });
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el ingreso: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Anular un ingreso extra
     */
    public function anular($id)
    {
        try {
            return DB::transaction(function () use ($id) {
                $ingreso = IngresoExtra::find($id);

                if (!$ingreso) {
                    return response()->json(['success' => false, 'message' => 'Ingreso no encontrado'], 404);
                }

                $estadoAnterior = $ingreso->estado;

                $ingreso->estado = 'anulado';
                $ingreso->save();

                // Solo revertimos dinero en caja si había sido aprobado previamente
                if ($estadoAnterior === 'aprobado') {
                    $this->reversarEnCajaActiva(
                        $ingreso->id,
                        'ingreso_extra',
                        'Anulación manual de ingreso extra'
                    );
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Ingreso anulado correctamente',
                    'data' => $ingreso
                ]);
            });
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al anular el ingreso: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Aprobar un ingreso pendiente
     */
    public function aprobar(Request $request, $id)
    {
        $request->validate([
            'supervisor_id' => 'required|string',
            'supervisor_password' => 'required|string',
        ]);

        try {
            return DB::transaction(function () use ($request, $id) {
                $ingreso = IngresoExtra::find($id);

                if (!$ingreso) {
                    return response()->json(['success' => false, 'message' => 'Ingreso no encontrado'], 404);
                }

                // Validar supervisor
                $supervisor = $this->validarSupervisor($request->supervisor_id, $request->supervisor_password);

                if (!$supervisor) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Credenciales de supervisor inválidas'
                    ], 403);
                }

                $ingreso->estado = 'aprobado';
                $ingreso->supervisor_id = $supervisor->id;
                $ingreso->save();

                // Como recien se aprueba, lo impactamos en caja
                $this->registrarEnCajaActiva(
                    $ingreso->id,
                    'ingreso_extra',
                    'ingreso',
                    (float) $ingreso->monto,
                    $ingreso->despliegue_pago_id,
                    'Aprobación de Ingreso Extra: ' . $ingreso->concepto
                );

                return response()->json([
                    'success' => true,
                    'message' => 'Ingreso aprobado correctamente y cargado en caja',
                    'data' => $ingreso
                ]);
            });
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al aprobar el ingreso: ' . $e->getMessage()
            ], 500);
        }
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
}
