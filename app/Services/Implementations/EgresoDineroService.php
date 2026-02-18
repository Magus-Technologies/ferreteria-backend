<?php

namespace App\Services\Implementations;

use App\Models\EgresoDinero;
use App\Models\Compra;
use App\Models\PagoDeCompra;
use App\Models\MovimientoInterno;
use App\Models\PrestamoEntreCajas;
use App\Models\TransaccionCaja;
use App\Services\Interfaces\EgresoDineroServiceInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Str;

class EgresoDineroService implements EgresoDineroServiceInterface
{
    /**
     * Obtener reporte detallado de gastos
     */
    public function obtenerReporteGastos(array $filtros, int $perPage = 50, int $page = 1): array
    {
        $query = $this->construirQueryGastos($filtros);

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
     * Obtener resumen de gastos para las cards
     */
    public function obtenerResumenGastos(array $filtros): array
    {
        $query = $this->construirQueryGastos($filtros);
        
        $resumen = $query->selectRaw('
            SUM(monto) as total_gastos,
            COUNT(*) as total_transacciones,
            AVG(monto) as promedio_gasto,
            SUM(CASE WHEN DATE(fecha) = CURDATE() THEN monto ELSE 0 END) as gastos_hoy,
            COUNT(CASE WHEN DATE(fecha) = CURDATE() THEN 1 END) as transacciones_hoy
        ')->first();

        return [
            'total_gastos' => round($resumen->total_gastos ?? 0, 2),
            'gastos_hoy' => round($resumen->gastos_hoy ?? 0, 2),
            'total_transacciones' => $resumen->total_transacciones ?? 0,
            'transacciones_hoy' => $resumen->transacciones_hoy ?? 0,
            'promedio_gasto' => round($resumen->promedio_gasto ?? 0, 2),
        ];
    }

    /**
     * Crear nuevo gasto de dinero
     */
    public function crearGasto(array $data): array
    {
        return DB::transaction(function () use ($data) {
            // Crear registro de gasto
            $gasto = EgresoDinero::create([
                'id' => (string) Str::ulid(),
                'monto' => $data['monto'],
                'vuelto' => $data['vuelto'] ?? 0,
                'observaciones' => $data['motivo'] . ($data['destino'] ? ' - Destino: ' . $data['destino'] : ''),
                'estado' => true,
                'despliegue_de_pago_id' => $data['despliegue_de_pago_id'],
                'user_id' => Auth::id(),
                'createdAt' => now(),
                'updatedAt' => now(),
            ]);

            // Registrar transacción en caja si corresponde
            $this->registrarTransaccionCaja($gasto, $data);

            return [
                'id' => $gasto->id,
                'monto' => $gasto->monto,
                'destino' => $data['destino'] ?? '',
                'motivo' => $data['motivo'],
                'comprobante' => $data['comprobante'] ?? 'EFRAIN',
                'fecha' => $gasto->createdAt->format('Y-m-d H:i:s'),
                'cajero' => Auth::user()->name,
                'autoriza' => $data['autoriza'] ?? Auth::user()->name,
                'anulado' => false,
            ];
        });
    }

    /**
     * Actualizar gasto existente
     */
    public function actualizarGasto(string $id, array $data): array
    {
        return DB::transaction(function () use ($id, $data) {
            $gasto = EgresoDinero::findOrFail($id);
            
            // Solo permitir actualizar si no está anulado
            if (!$gasto->estado) {
                throw new \Exception('No se puede actualizar un gasto anulado');
            }

            $gasto->update([
                'monto' => $data['monto'],
                'vuelto' => $data['vuelto'] ?? 0,
                'observaciones' => $data['motivo'] . ($data['destino'] ? ' - Destino: ' . $data['destino'] : ''),
                'despliegue_de_pago_id' => $data['despliegue_de_pago_id'],
                'updatedAt' => now(),
            ]);

            return [
                'id' => $gasto->id,
                'monto' => $gasto->monto,
                'destino' => $data['destino'] ?? '',
                'motivo' => $data['motivo'],
                'comprobante' => $data['comprobante'] ?? 'EFRAIN',
                'fecha' => $gasto->createdAt->format('Y-m-d H:i:s'),
                'cajero' => $gasto->user->name,
                'autoriza' => $data['autoriza'] ?? $gasto->user->name,
                'anulado' => false,
            ];
        });
    }

    /**
     * Anular/cancelar gasto
     */
    public function anularGasto(string $id, string $motivo): bool
    {
        return DB::transaction(function () use ($id, $motivo) {
            $gasto = EgresoDinero::findOrFail($id);
            
            // Marcar como anulado
            $gasto->update([
                'estado' => false,
                'observaciones' => $gasto->observaciones . ' - ANULADO: ' . $motivo,
                'updatedAt' => now(),
            ]);

            // Registrar transacción de anulación en caja si corresponde
            $this->registrarAnulacionEnCaja($gasto, $motivo);

            return true;
        });
    }

    /**
     * Exportar reporte de gastos
     */
    public function exportarReporte(array $filtros, string $formato = 'excel'): array
    {
        $nombreArchivo = 'reporte_gastos_' . date('Y-m-d_H-i-s') . '.' . ($formato === 'excel' ? 'xlsx' : 'pdf');
        
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
        \Log::info("Enviando reporte de gastos a: {$email}", $filtros);
    }

    /**
     * Construir query base para gastos
     */
    private function construirQueryGastos(array $filtros)
    {
        // Query unificada que incluye todas las fuentes de gastos
        $query = DB::table(DB::raw('(
            -- Gastos directos
            SELECT 
                ed.id,
                DATE_FORMAT(ed.createdAt, "%d/%m/%Y") as fecha,
                TIME_FORMAT(ed.createdAt, "%H:%i:%s") as hora,
                ed.monto,
                CASE 
                    WHEN ed.observaciones LIKE "% - Destino: %" THEN 
                        SUBSTRING_INDEX(SUBSTRING_INDEX(ed.observaciones, " - Destino: ", -1), " - ", 1)
                    ELSE "VARIOS"
                END as destino,
                SUBSTRING_INDEX(ed.observaciones, " - ", 1) as motivo,
                "EFRAIN" as comprobante,
                u.name as cajero,
                u.name as autoriza,
                CASE WHEN ed.estado = 0 THEN 1 ELSE 0 END as anulado,
                "Gasto Directo" as tipo_origen,
                dp.name as metodo_pago,
                ed.createdAt as fecha_ordenamiento
            FROM egresodinero ed
            LEFT JOIN user u ON ed.user_id = u.id
            LEFT JOIN desplieguedepago dp ON ed.despliegue_de_pago_id = dp.id
            
            UNION ALL
            
            -- Gastos por compras
            SELECT 
                CONCAT("compra_", c.id) as id,
                DATE_FORMAT(c.fecha, "%d/%m/%Y") as fecha,
                TIME_FORMAT(c.fecha, "%H:%i:%s") as hora,
                pc.monto,
                COALESCE(p.razon_social, "PROVEEDOR") as destino,
                CONCAT("Compra ", c.tipo_documento, " ", COALESCE(c.serie, "S/N"), "-", LPAD(COALESCE(c.numero, 0), 8, "0")) as motivo,
                COALESCE(pc.numero_letra, "EFRAIN") as comprobante,
                u.name as cajero,
                u.name as autoriza,
                CASE WHEN c.estado_de_compra = "Anulado" THEN 1 ELSE 0 END as anulado,
                "Compra" as tipo_origen,
                dp.name as metodo_pago,
                c.fecha as fecha_ordenamiento
            FROM compra c
            INNER JOIN pagodecompra pc ON c.id = pc.compra_id
            LEFT JOIN proveedor p ON c.proveedor_id = p.id
            LEFT JOIN desplieguedepago dp ON pc.despliegue_de_pago_id = dp.id
            LEFT JOIN user u ON c.user_id = u.id
            WHERE pc.estado = 1
            
            UNION ALL
            
            -- Movimientos internos (egresos)
            SELECT 
                CONCAT("movimiento_", mi.id) as id,
                DATE_FORMAT(mi.fecha, "%d/%m/%Y") as fecha,
                TIME_FORMAT(mi.fecha, "%H:%i:%s") as hora,
                mi.monto,
                "TRANSFERENCIA INTERNA" as destino,
                mi.justificacion as motivo,
                COALESCE(mi.comprobante, "EFRAIN") as comprobante,
                u.name as cajero,
                u.name as autoriza,
                0 as anulado,
                "Movimiento Interno" as tipo_origen,
                dp.name as metodo_pago,
                mi.fecha as fecha_ordenamiento
            FROM movimientos_internos mi
            LEFT JOIN desplieguedepago dp ON mi.despliegue_de_pago_origen_id = dp.id
            LEFT JOIN user u ON mi.user_id = u.id
            WHERE mi.sub_caja_origen_id IS NOT NULL
            
            UNION ALL
            
            -- Préstamos otorgados (aprobados)
            SELECT 
                CONCAT("prestamo_", pec.id) as id,
                DATE_FORMAT(pec.fecha_aprobacion, "%d/%m/%Y") as fecha,
                TIME_FORMAT(pec.fecha_aprobacion, "%H:%i:%s") as hora,
                pec.monto,
                "PRÉSTAMO OTORGADO" as destino,
                pec.motivo as motivo,
                "EFRAIN" as comprobante,
                up.name as cajero,
                ua.name as autoriza,
                0 as anulado,
                "Préstamo" as tipo_origen,
                dp.name as metodo_pago,
                pec.fecha_aprobacion as fecha_ordenamiento
            FROM prestamos_entre_cajas pec
            LEFT JOIN desplieguedepago dp ON pec.despliegue_de_pago_id = dp.id
            LEFT JOIN user up ON pec.user_presta_id = up.id
            LEFT JOIN user ua ON pec.aprobado_por_id = ua.id
            WHERE pec.estado_aprobacion = "aprobado"
        ) as gastos_unificados'))
        ->orderBy('fecha_ordenamiento', 'desc');

        // Aplicar filtros
        if (!empty($filtros['almacen_id'])) {
            // Para gastos, el filtro de almacén se aplica principalmente a compras
            $query->where(function($q) use ($filtros) {
                $q->where('tipo_origen', '!=', 'Compra')
                  ->orWhereExists(function($subQuery) use ($filtros) {
                      $subQuery->select(DB::raw(1))
                               ->from('compra as c2')
                               ->whereRaw('CONCAT("compra_", c2.id) = gastos_unificados.id')
                               ->where('c2.almacen_id', $filtros['almacen_id']);
                  });
            });
        }

        if (!empty($filtros['fechaDesde'])) {
            $query->whereDate('fecha_ordenamiento', '>=', $filtros['fechaDesde']);
        }

        if (!empty($filtros['fechaHasta'])) {
            $query->whereDate('fecha_ordenamiento', '<=', $filtros['fechaHasta']);
        }

        if (!empty($filtros['motivoGasto'])) {
            $search = '%' . $filtros['motivoGasto'] . '%';
            $query->where('motivo', 'like', $search);
        }

        if (!empty($filtros['cajeroRegistra'])) {
            $search = '%' . $filtros['cajeroRegistra'] . '%';
            $query->where('cajero', 'like', $search);
        }

        if (!empty($filtros['sucursal'])) {
            // Filtro por sucursal se puede aplicar via almacen_id o como filtro adicional
            $search = '%' . $filtros['sucursal'] . '%';
            $query->where('destino', 'like', $search);
        }

        if (!empty($filtros['busqueda'])) {
            $search = '%' . $filtros['busqueda'] . '%';
            $query->where(function($q) use ($search) {
                $q->where('motivo', 'like', $search)
                  ->orWhere('destino', 'like', $search)
                  ->orWhere('cajero', 'like', $search)
                  ->orWhere('autoriza', 'like', $search)
                  ->orWhere('comprobante', 'like', $search);
            });
        }

        return $query;
    }

    /**
     * Calcular resumen de los datos actuales
     */
    private function calcularResumenDatos($datos): array
    {
        $totalGastos = $datos->sum('monto');
        $totalTransacciones = $datos->count();
        $gastosHoy = $datos->where('fecha', date('d/m/Y'))->sum('monto');
        $transaccionesHoy = $datos->where('fecha', date('d/m/Y'))->count();
        $promedioGasto = $totalTransacciones > 0 ? $totalGastos / $totalTransacciones : 0;

        return [
            'total_gastos' => round($totalGastos, 2),
            'gastos_hoy' => round($gastosHoy, 2),
            'total_transacciones' => $totalTransacciones,
            'transacciones_hoy' => $transaccionesHoy,
            'promedio_gasto' => round($promedioGasto, 2),
        ];
    }

    /**
     * Registrar transacción en caja si corresponde
     */
    private function registrarTransaccionCaja(EgresoDinero $gasto, array $data): void
    {
        // Buscar sub-caja que acepta este método de pago
        $subCaja = DB::table('sub_cajas')
            ->whereRaw('JSON_CONTAINS(despliegues_pago_ids, ?)', ['"' . $gasto->despliegue_de_pago_id . '"'])
            ->orWhereRaw('JSON_CONTAINS(despliegues_pago_ids, ?)', ['"*"'])
            ->where('estado', 1)
            ->first();

        if ($subCaja) {
            // Verificar que hay saldo suficiente
            if ($subCaja->saldo_actual < $gasto->monto) {
                throw new \Exception('Saldo insuficiente en la caja para realizar este gasto');
            }

            // Registrar transacción
            TransaccionCaja::create([
                'id' => 'txn_' . Str::random(20),
                'sub_caja_id' => $subCaja->id,
                'user_id' => $gasto->user_id,
                'tipo_transaccion' => 'egreso',
                'monto' => $gasto->monto,
                'saldo_anterior' => $subCaja->saldo_actual,
                'saldo_nuevo' => $subCaja->saldo_actual - $gasto->monto,
                'despliegue_pago_id' => $gasto->despliegue_de_pago_id,
                'descripcion' => 'Gasto manual: ' . $gasto->observaciones,
                'referencia_tipo' => 'gasto_manual',
                'referencia_id' => $gasto->id,
                'fecha' => now(),
                'created_at' => now(),
            ]);

            // Actualizar saldo de sub-caja
            DB::table('sub_cajas')
                ->where('id', $subCaja->id)
                ->update([
                    'saldo_actual' => $subCaja->saldo_actual - $gasto->monto,
                    'updated_at' => now(),
                ]);
        }
    }

    /**
     * Registrar anulación en caja
     */
    private function registrarAnulacionEnCaja(EgresoDinero $gasto, string $motivo): void
    {
        // Buscar la transacción original
        $transaccionOriginal = TransaccionCaja::where('referencia_tipo', 'gasto_manual')
            ->where('referencia_id', $gasto->id)
            ->first();

        if ($transaccionOriginal) {
            $subCaja = DB::table('sub_cajas')->where('id', $transaccionOriginal->sub_caja_id)->first();
            
            if ($subCaja) {
                // Registrar transacción de anulación (ingreso para devolver el dinero)
                TransaccionCaja::create([
                    'id' => 'txn_' . Str::random(20),
                    'sub_caja_id' => $subCaja->id,
                    'user_id' => Auth::id(),
                    'tipo_transaccion' => 'ingreso',
                    'monto' => $gasto->monto,
                    'saldo_anterior' => $subCaja->saldo_actual,
                    'saldo_nuevo' => $subCaja->saldo_actual + $gasto->monto,
                    'despliegue_pago_id' => $gasto->despliegue_de_pago_id,
                    'descripcion' => 'Anulación de gasto: ' . $motivo,
                    'referencia_tipo' => 'anulacion_gasto',
                    'referencia_id' => $gasto->id,
                    'fecha' => now(),
                    'created_at' => now(),
                ]);

                // Actualizar saldo de sub-caja
                DB::table('sub_cajas')
                    ->where('id', $subCaja->id)
                    ->update([
                        'saldo_actual' => $subCaja->saldo_actual + $gasto->monto,
                        'updated_at' => now(),
                    ]);
            }
        }
    }
}