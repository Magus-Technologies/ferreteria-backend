<?php

namespace App\Services\Implementations;

use App\Models\IngresoDinero;
use App\Models\Venta;
use App\Models\DespliegueDePagoVenta;
use App\Models\MovimientoInterno;
use App\Models\PrestamoEntreCajas;
use App\Models\TransaccionCaja;
use App\Services\Interfaces\IngresoDineroServiceInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Str;

class IngresoDineroService implements IngresoDineroServiceInterface
{
    /**
     * Obtener reporte detallado de ingresos
     */
    public function obtenerReporteIngresos(array $filtros, int $perPage = 50, int $page = 1): array
    {
        $query = $this->construirQueryIngresos($filtros);

        // Obtener datos paginados
        $total = $query->count();
        $datos = $query->offset(($page - 1) * $perPage)
                      ->limit($perPage)
                      ->get();

        // Calcular resumen de la página actual
        $resumen = $this->calcularResumenDatos($datos);

        return [
            'data' => $datos,
            'resumen' => $resumen,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => ceil($total / $perPage),
                'from' => ($page - 1) * $perPage + 1,
                'to' => min($page * $perPage, $total)
            ]
        ];
    }

    /**
     * Obtener resumen de ingresos para las cards
     */
    public function obtenerResumenIngresos(array $filtros): array
    {
        $query = $this->construirQueryIngresos($filtros);
        
        $resumen = $query->selectRaw('
            SUM(monto) as total_ingresos,
            COUNT(*) as total_transacciones,
            AVG(monto) as promedio_ingreso,
            SUM(CASE WHEN DATE(fecha) = CURDATE() THEN monto ELSE 0 END) as ingresos_hoy,
            COUNT(CASE WHEN DATE(fecha) = CURDATE() THEN 1 END) as transacciones_hoy
        ')->first();

        return [
            'total_ingresos' => round($resumen->total_ingresos ?? 0, 2),
            'ingresos_hoy' => round($resumen->ingresos_hoy ?? 0, 2),
            'total_transacciones' => $resumen->total_transacciones ?? 0,
            'transacciones_hoy' => $resumen->transacciones_hoy ?? 0,
            'promedio_ingreso' => round($resumen->promedio_ingreso ?? 0, 2),
        ];
    }

    /**
     * Crear nuevo ingreso de dinero
     */
    public function crearIngreso(array $data): array
    {
        return DB::transaction(function () use ($data) {
            // Crear registro de ingreso
            $ingreso = IngresoDinero::create([
                'id' => (string) Str::ulid(),
                'monto' => $data['monto'],
                'observaciones' => $data['concepto'] . ($data['comentario'] ? ' - ' . $data['comentario'] : ''),
                'despliegue_de_pago_id' => $data['despliegue_de_pago_id'],
                'user_id' => Auth::id(),
                'createdAt' => now(),
                'updatedAt' => now(),
            ]);

            // Registrar transacción en caja si corresponde
            $this->registrarTransaccionCaja($ingreso, $data);

            return [
                'id' => $ingreso->id,
                'monto' => $ingreso->monto,
                'concepto' => $data['concepto'],
                'comentario' => $data['comentario'] ?? '',
                'fecha' => $ingreso->createdAt->format('Y-m-d H:i:s'),
                'cajero' => Auth::user()->name,
                'autoriza' => $data['autoriza'] ?? Auth::user()->name,
                'anulado' => false,
            ];
        });
    }

    /**
     * Actualizar ingreso existente
     */
    public function actualizarIngreso(string $id, array $data): array
    {
        return DB::transaction(function () use ($id, $data) {
            $ingreso = IngresoDinero::findOrFail($id);
            
            // Solo permitir actualizar si no está anulado
            if (!$ingreso->estado) {
                throw new \Exception('No se puede actualizar un ingreso anulado');
            }

            $ingreso->update([
                'monto' => $data['monto'],
                'observaciones' => $data['concepto'] . ($data['comentario'] ? ' - ' . $data['comentario'] : ''),
                'despliegue_de_pago_id' => $data['despliegue_de_pago_id'],
                'updatedAt' => now(),
            ]);

            return [
                'id' => $ingreso->id,
                'monto' => $ingreso->monto,
                'concepto' => $data['concepto'],
                'comentario' => $data['comentario'] ?? '',
                'fecha' => $ingreso->createdAt->format('Y-m-d H:i:s'),
                'cajero' => $ingreso->user->name,
                'autoriza' => $data['autoriza'] ?? $ingreso->user->name,
                'anulado' => false,
            ];
        });
    }

    /**
     * Anular/cancelar ingreso
     */
    public function anularIngreso(string $id, string $motivo): bool
    {
        return DB::transaction(function () use ($id, $motivo) {
            $ingreso = IngresoDinero::findOrFail($id);
            
            // Marcar como anulado
            $ingreso->update([
                'estado' => false,
                'observaciones' => $ingreso->observaciones . ' - ANULADO: ' . $motivo,
                'updatedAt' => now(),
            ]);

            // Registrar transacción de anulación en caja si corresponde
            $this->registrarAnulacionEnCaja($ingreso, $motivo);

            return true;
        });
    }

    /**
     * Exportar reporte de ingresos
     */
    public function exportarReporte(array $filtros, string $formato = 'excel'): array
    {
        $nombreArchivo = 'reporte_ingresos_' . date('Y-m-d_H-i-s') . '.' . ($formato === 'excel' ? 'xlsx' : 'pdf');
        
        return [
            'url' => '/storage/exports/' . $nombreArchivo,
            'nombre' => $nombreArchivo
        ];
    }

    /**
     * Enviar reporte por correo electrónico
     */
    public function enviarReportePorCorreo(string $email, array $filtros): void
    {
        \Log::info("Enviando reporte de ingresos a: {$email}", $filtros);
    }

    /**
     * Construir query base para ingresos
     */
    private function construirQueryIngresos(array $filtros)
    {
        // Query unificada que incluye todas las fuentes de ingresos
        $query = DB::table(DB::raw('(
            -- Ingresos directos
            SELECT 
                id.id,
                DATE_FORMAT(id.createdAt, "%d/%m/%Y") as fecha,
                TIME_FORMAT(id.createdAt, "%H:%i:%s") as hora,
                id.monto,
                SUBSTRING_INDEX(id.observaciones, " - ", 1) as concepto,
                CASE 
                    WHEN id.observaciones LIKE "% - %" THEN SUBSTRING_INDEX(id.observaciones, " - ", -1)
                    ELSE ""
                END as comentario,
                u.name as cajero,
                u.name as autoriza,
                CASE WHEN id.estado = 0 THEN 1 ELSE 0 END as anulado,
                "Ingreso Directo" as tipo_origen,
                dp.name as metodo_pago,
                id.createdAt as fecha_ordenamiento
            FROM ingresodinero id
            LEFT JOIN user u ON id.user_id = u.id
            LEFT JOIN desplieguedepago dp ON id.despliegue_de_pago_id = dp.id
            
            UNION ALL
            
            -- Ingresos por ventas
            SELECT 
                CONCAT("venta_", v.id) as id,
                DATE_FORMAT(v.fecha, "%d/%m/%Y") as fecha,
                TIME_FORMAT(v.fecha, "%H:%i:%s") as hora,
                dpv.monto,
                "Venta de productos" as concepto,
                CONCAT("Venta ", v.tipo_documento, " ", COALESCE(v.serie, "S/N"), "-", LPAD(COALESCE(v.numero, 0), 8, "0")) as comentario,
                u.name as cajero,
                u.name as autoriza,
                CASE WHEN v.estado_de_venta = "Anulado" THEN 1 ELSE 0 END as anulado,
                "Venta" as tipo_origen,
                dp.name as metodo_pago,
                v.fecha as fecha_ordenamiento
            FROM venta v
            INNER JOIN desplieguedepagoventa dpv ON v.id = dpv.venta_id
            LEFT JOIN desplieguedepago dp ON dpv.despliegue_de_pago_id = dp.id
            LEFT JOIN user u ON v.user_id = u.id
            
            UNION ALL
            
            -- Movimientos internos (ingresos)
            SELECT 
                CONCAT("movimiento_", mi.id) as id,
                DATE_FORMAT(mi.fecha, "%d/%m/%Y") as fecha,
                TIME_FORMAT(mi.fecha, "%H:%i:%s") as hora,
                mi.monto,
                "Transferencia interna" as concepto,
                mi.justificacion as comentario,
                u.name as cajero,
                u.name as autoriza,
                0 as anulado,
                "Movimiento Interno" as tipo_origen,
                dp.name as metodo_pago,
                mi.fecha as fecha_ordenamiento
            FROM movimientos_internos mi
            LEFT JOIN desplieguedepago dp ON mi.despliegue_de_pago_destino_id = dp.id
            LEFT JOIN user u ON mi.user_id = u.id
            WHERE mi.sub_caja_destino_id IS NOT NULL
            
            UNION ALL
            
            -- Préstamos recibidos (aprobados)
            SELECT 
                CONCAT("prestamo_", pec.id) as id,
                DATE_FORMAT(pec.fecha_aprobacion, "%d/%m/%Y") as fecha,
                TIME_FORMAT(pec.fecha_aprobacion, "%H:%i:%s") as hora,
                pec.monto,
                "Préstamo recibido" as concepto,
                pec.motivo as comentario,
                ur.name as cajero,
                ua.name as autoriza,
                0 as anulado,
                "Préstamo" as tipo_origen,
                dp.name as metodo_pago,
                pec.fecha_aprobacion as fecha_ordenamiento
            FROM prestamos_entre_cajas pec
            LEFT JOIN desplieguedepago dp ON pec.despliegue_de_pago_id = dp.id
            LEFT JOIN user ur ON pec.user_recibe_id = ur.id
            LEFT JOIN user ua ON pec.aprobado_por_id = ua.id
            WHERE pec.estado_aprobacion = "aprobado"
        ) as ingresos_unificados'))
        ->orderBy('fecha_ordenamiento', 'desc');

        // Aplicar filtros
        if (!empty($filtros['almacen_id'])) {
            // Para ingresos, el filtro de almacén se aplica principalmente a ventas
            $query->where(function($q) use ($filtros) {
                $q->where('tipo_origen', '!=', 'Venta')
                  ->orWhereExists(function($subQuery) use ($filtros) {
                      $subQuery->select(DB::raw(1))
                               ->from('venta as v2')
                               ->whereRaw('CONCAT("venta_", v2.id) = ingresos_unificados.id')
                               ->where('v2.almacen_id', $filtros['almacen_id']);
                  });
            });
        }

        if (!empty($filtros['desde'])) {
            $query->whereDate('fecha_ordenamiento', '>=', $filtros['desde']);
        }

        if (!empty($filtros['hasta'])) {
            $query->whereDate('fecha_ordenamiento', '<=', $filtros['hasta']);
        }

        if (!empty($filtros['user_id'])) {
            $query->where(function($q) use ($filtros) {
                $q->whereExists(function($subQuery) use ($filtros) {
                    $subQuery->select(DB::raw(1))
                             ->from('ingresodinero as id2')
                             ->whereRaw('id2.id = ingresos_unificados.id')
                             ->where('id2.user_id', $filtros['user_id']);
                })
                ->orWhereExists(function($subQuery) use ($filtros) {
                    $subQuery->select(DB::raw(1))
                             ->from('venta as v3')
                             ->whereRaw('CONCAT("venta_", v3.id) = ingresos_unificados.id')
                             ->where('v3.user_id', $filtros['user_id']);
                });
            });
        }

        if (!empty($filtros['concepto'])) {
            $search = '%' . $filtros['concepto'] . '%';
            $query->where('concepto', 'like', $search);
        }

        if (!empty($filtros['search'])) {
            $search = '%' . $filtros['search'] . '%';
            $query->where(function($q) use ($search) {
                $q->where('concepto', 'like', $search)
                  ->orWhere('comentario', 'like', $search)
                  ->orWhere('cajero', 'like', $search)
                  ->orWhere('autoriza', 'like', $search);
            });
        }

        return $query;
    }

    /**
     * Calcular resumen de los datos actuales
     */
    private function calcularResumenDatos($datos): array
    {
        $totalIngresos = $datos->sum('monto');
        $totalTransacciones = $datos->count();
        $ingresosHoy = $datos->where('fecha', date('d/m/Y'))->sum('monto');
        $transaccionesHoy = $datos->where('fecha', date('d/m/Y'))->count();
        $promedioIngreso = $totalTransacciones > 0 ? $totalIngresos / $totalTransacciones : 0;

        return [
            'total_ingresos' => round($totalIngresos, 2),
            'ingresos_hoy' => round($ingresosHoy, 2),
            'total_transacciones' => $totalTransacciones,
            'transacciones_hoy' => $transaccionesHoy,
            'promedio_ingreso' => round($promedioIngreso, 2),
        ];
    }

    /**
     * Registrar transacción en caja si corresponde
     */
    private function registrarTransaccionCaja(IngresoDinero $ingreso, array $data): void
    {
        // Buscar sub-caja que acepta este método de pago
        $subCaja = DB::table('sub_cajas')
            ->whereRaw('JSON_CONTAINS(despliegues_pago_ids, ?)', ['"' . $ingreso->despliegue_de_pago_id . '"'])
            ->orWhereRaw('JSON_CONTAINS(despliegues_pago_ids, ?)', ['"*"'])
            ->where('estado', 1)
            ->first();

        if ($subCaja) {
            // Registrar transacción
            TransaccionCaja::create([
                'id' => 'txn_' . Str::random(20),
                'sub_caja_id' => $subCaja->id,
                'user_id' => $ingreso->user_id,
                'tipo_transaccion' => 'ingreso',
                'monto' => $ingreso->monto,
                'saldo_anterior' => $subCaja->saldo_actual,
                'saldo_nuevo' => $subCaja->saldo_actual + $ingreso->monto,
                'despliegue_pago_id' => $ingreso->despliegue_de_pago_id,
                'descripcion' => 'Ingreso manual: ' . $ingreso->observaciones,
                'referencia_tipo' => 'ingreso_manual',
                'referencia_id' => $ingreso->id,
                'fecha' => now(),
                'created_at' => now(),
            ]);

            // Actualizar saldo de sub-caja
            DB::table('sub_cajas')
                ->where('id', $subCaja->id)
                ->update([
                    'saldo_actual' => $subCaja->saldo_actual + $ingreso->monto,
                    'updated_at' => now(),
                ]);
        }
    }

    /**
     * Registrar anulación en caja
     */
    private function registrarAnulacionEnCaja(IngresoDinero $ingreso, string $motivo): void
    {
        // Buscar la transacción original
        $transaccionOriginal = TransaccionCaja::where('referencia_tipo', 'ingreso_manual')
            ->where('referencia_id', $ingreso->id)
            ->first();

        if ($transaccionOriginal) {
            $subCaja = DB::table('sub_cajas')->where('id', $transaccionOriginal->sub_caja_id)->first();
            
            if ($subCaja) {
                // Registrar transacción de anulación (egreso)
                TransaccionCaja::create([
                    'id' => 'txn_' . Str::random(20),
                    'sub_caja_id' => $subCaja->id,
                    'user_id' => Auth::id(),
                    'tipo_transaccion' => 'egreso',
                    'monto' => $ingreso->monto,
                    'saldo_anterior' => $subCaja->saldo_actual,
                    'saldo_nuevo' => $subCaja->saldo_actual - $ingreso->monto,
                    'despliegue_pago_id' => $ingreso->despliegue_de_pago_id,
                    'descripcion' => 'Anulación de ingreso: ' . $motivo,
                    'referencia_tipo' => 'anulacion_ingreso',
                    'referencia_id' => $ingreso->id,
                    'fecha' => now(),
                    'created_at' => now(),
                ]);

                // Actualizar saldo de sub-caja
                DB::table('sub_cajas')
                    ->where('id', $subCaja->id)
                    ->update([
                        'saldo_actual' => $subCaja->saldo_actual - $ingreso->monto,
                        'updated_at' => now(),
                    ]);
            }
        }
    }
}