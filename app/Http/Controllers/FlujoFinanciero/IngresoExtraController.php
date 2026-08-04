<?php

namespace App\Http\Controllers\FlujoFinanciero;

use App\Http\Controllers\Controller;
use App\Models\IngresoExtra;
use App\Models\User;
use Illuminate\Http\Request;
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
    public function index(Request $request)
    {
        $query = IngresoExtra::with(['user', 'desplieguePago.metodoDePago'])
            ->orderBy('created_at', 'desc');

        // Filtro por fecha desde
        if ($request->has('fechaDesde')) {
            $query->where('created_at', '>=', $request->fechaDesde);
        }

        // Filtro por fecha hasta
        if ($request->has('fechaHasta')) {
            $query->where('created_at', '<=', $request->fechaHasta . ' 23:59:59');
        }

        // Filtro por motivo/concepto
        if ($request->has('motivoIngreso') && $request->motivoIngreso) {
            $query->where('concepto', 'like', '%' . $request->motivoIngreso . '%');
        }

        // Filtro por cajero/usuario (legacy: búsqueda parcial por nombre)
        if ($request->has('cajeroRegistra') && $request->cajeroRegistra) {
            $query->whereHas('user', function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->cajeroRegistra . '%');
            });
        }

        // Filtro por Vendedor (mismo criterio que Mis Ventas): coincidencia exacta
        // por user_id, no por nombre.
        if ($request->has('user_id') && $request->user_id) {
            $query->where('user_id', $request->user_id);
        }

        // Filtro por búsqueda general
        if ($request->has('busqueda') && $request->busqueda) {
            $busqueda = $request->busqueda;
            $query->where(function ($q) use ($busqueda) {
                $q->where('concepto', 'like', '%' . $busqueda . '%')
                  ->orWhere('monto', 'like', '%' . $busqueda . '%')
                  ->orWhereHas('user', function ($userQuery) use ($busqueda) {
                      $userQuery->where('name', 'like', '%' . $busqueda . '%');
                  });
            });
        }

        // Filtro por estado
        if ($request->has('estado') && $request->estado) {
            $query->where('estado', $request->estado);
        }

        $ingresos = $query->get();

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
    public function resumen(Request $request)
    {
        $query = IngresoExtra::query();

        if ($request->has('estado') && $request->estado) {
            $query->where('estado', $request->estado);
        } else {
            $query->where('estado', '!=', 'anulado');
        }

        if ($request->has('fechaDesde')) {
            $query->where('created_at', '>=', $request->fechaDesde);
        }

        if ($request->has('fechaHasta')) {
            $query->where('created_at', '<=', $request->fechaHasta . ' 23:59:59');
        }

        if ($request->has('user_id') && $request->user_id) {
            $query->where('user_id', $request->user_id);
        }

        $totalIngresos = (clone $query)->sum('monto');
        $totalTransacciones = (clone $query)->count();

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
            'concepto' => 'sometimes|nullable|string|max:1000',
            'despliegue_pago_id' => 'nullable|string',
        ]);

        try {
            return DB::transaction(function () use ($request) {
                $ingresoId = Str::ulid()->toString();

                $ingreso = IngresoExtra::create([
                    'id' => $ingresoId,
                    'monto' => $request->monto,
                    'concepto' => $request->concepto ?? '',
                    'estado' => 'aprobado',
                    'user_id' => Auth::id() ?? User::first()?->id,
                    'despliegue_pago_id' => $request->despliegue_pago_id,
                ]);

                $subCajaId = $this->obtenerSubCajaIdFromPago($request->despliegue_pago_id);

                $this->registrarEnCajaActiva(
                    $ingresoId,
                    'ingreso_extra',
                    'ingreso',
                    (float) $request->monto,
                    $request->despliegue_pago_id,
                    'Ingreso Extra: ' . $request->concepto,
                    $subCajaId
                );

                return response()->json([
                    'success' => true,
                    'message' => 'Ingreso registrado correctamente',
                    'data' => $ingreso
                ], 201);
            });
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Actualizar un ingreso extra
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'monto' => 'required|numeric|min:0.01',
            'concepto' => 'sometimes|nullable|string|max:1000',
            'despliegue_pago_id' => 'nullable|string',
        ]);

        try {
            return DB::transaction(function () use ($request, $id) {
                $ingreso = IngresoExtra::find($id);

                if (!$ingreso) {
                    return response()->json(['success' => false, 'message' => 'Ingreso no encontrado'], 404);
                }

                if ($ingreso->estado === 'anulado') {
                    return response()->json(['success' => false, 'message' => 'No se puede editar un ingreso anulado'], 422);
                }

                $ingreso->update([
                    'monto' => $request->monto,
                    'concepto' => $request->concepto ?? '',
                    'despliegue_pago_id' => $request->despliegue_pago_id,
                ]);

                // Revertir el efecto en caja de la transacción anterior (monto/método
                // viejos) y registrar el nuevo — si no, el dinero se queda en la sub-caja
                // y con el monto originales aunque el registro ya muestre otros valores.
                $this->reversarEnCajaActiva($ingreso->id, 'ingreso_extra', 'Edición de ingreso: ' . $ingreso->concepto);
                $this->registrarEnCajaActiva(
                    $ingreso->id,
                    'ingreso_extra',
                    'ingreso',
                    (float) $request->monto,
                    $request->despliegue_pago_id,
                    'Ingreso Extra (editado): ' . $ingreso->concepto
                );

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

                if ($ingreso->estado === 'anulado') {
                    return response()->json(['success' => false, 'message' => 'El ingreso ya está anulado'], 422);
                }

                $ingreso->estado = 'anulado';
                $ingreso->save();

                $this->reversarEnCajaActiva(
                    $ingreso->id,
                    'ingreso_extra',
                    'Anulación de ingreso: ' . $ingreso->concepto
                );

                return response()->json([
                    'success' => true,
                    'message' => 'Ingreso anulado correctamente y monto devuelto a caja',
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
     * Obtener la sub-caja asociada a un método de pago
     */
    private function obtenerSubCajaIdFromPago(?string $desplieguePagoId): ?string
    {
        if (!$desplieguePagoId) return null;

        $dp = \App\Models\DespliegueDePago::with('metodoDePago')->find($desplieguePagoId);
        return $dp?->metodoDePago?->subcaja_id;
    }
}
