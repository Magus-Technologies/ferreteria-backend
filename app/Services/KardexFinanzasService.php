<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Servicio encargado de la lógica del Kardex de Finanzas.
 * Sigue el principio de responsabilidad única al separar la lógica de negocio del controlador.
 */
class KardexFinanzasService
{
    /**
     * Obtiene el listado paginado del kardex financiero con saldos acumulados.
     */
    public function getKardex(array $filters)
    {
        $metodoPagoId = $filters['metodo_pago_id'] ?? null;
        $subCajaId = $filters['sub_caja_id'] ?? null;
        $vendedorId = $filters['vendedor_id'] ?? null;
        $desde = $filters['desde'] ?? null;
        $hasta = $filters['hasta'] ?? null;
        $perPage = (int) ($filters['per_page'] ?? 50);
        $page = (int) ($filters['page'] ?? 1);

        $union = $this->buildUnionQuery($metodoPagoId, $subCajaId, $vendedorId, $desde, $hasta);

        if (!$union) {
            return [
                'data' => [],
                'total' => 0,
                'current_page' => 1,
                'per_page' => $perPage,
                'last_page' => 1,
            ];
        }

        return $this->paginate($union['sql'], $union['bindings'], $perPage, $page);
    }

    /**
     * Construye la consulta UNION ALL que consolida todas las fuentes financieras.
     */
    private function buildUnionQuery($metodoPagoId, $subCajaId, $vendedorId, $desde, $hasta)
    {
        $queries = [];
        $bindings = [];

        $sources = [
            'getVentasQuery',
            'getCobrosQuery',
            'getComprasQuery',
            'getIngresosExtrasQuery',
            'getGastosExtrasQuery'
        ];

        foreach ($sources as $method) {
            $result = $this->$method($metodoPagoId, $subCajaId, $vendedorId, $desde, $hasta);
            if ($result) {
                $queries[] = "({$result['sql']})";
                $bindings = array_merge($bindings, $result['bindings']);
            }
        }

        if (empty($queries)) return null;

        return [
            'sql' => implode(" UNION ALL ", $queries),
            'bindings' => $bindings
        ];
    }

    private function getVentasQuery($metodoPagoId, $subCajaId, $vendedorId, $desde, $hasta)
    {
        $bindings = [];
        $sql = "SELECT 
                'VENTA' as tipo,
                'ingreso' as movimiento,
                v.fecha,
                CONCAT(v.tipo_documento, ' ', v.serie, '-', v.numero) as documento,
                CASE 
                    WHEN sc.id IS NOT NULL THEN 
                        CONCAT(
                            sc.nombre, '/', 
                            mp.name, '/', 
                            m.name,
                            CASE WHEN mp.nombre_titular IS NOT NULL AND mp.nombre_titular != '' 
                                THEN CONCAT('/', mp.nombre_titular) 
                                ELSE '' 
                            END
                        )
                    ELSE 
                        CONCAT(
                            mp.name, '/', 
                            m.name,
                            CASE WHEN mp.nombre_titular IS NOT NULL AND mp.nombre_titular != '' 
                                THEN CONCAT('/', mp.nombre_titular) 
                                ELSE '' 
                            END
                        )
                END as metodo_pago,
                dpv.monto as entrada,
                0 as salida,
                v.id as referencia_id,
                v.user_id,
                m.metodo_de_pago_id,
                mp.subcaja_id
               FROM desplieguedepagoventa dpv
               JOIN desplieguedepago m ON m.id = dpv.despliegue_de_pago_id
               JOIN metododepago mp ON mp.id = m.metodo_de_pago_id
               LEFT JOIN sub_cajas sc ON sc.id = mp.subcaja_id
               JOIN venta v ON v.id = dpv.venta_id
               WHERE v.estado_de_venta != 'an'";
        
        if ($metodoPagoId) { $sql .= " AND m.metodo_de_pago_id = ?"; $bindings[] = $metodoPagoId; }
        if ($subCajaId) { $sql .= " AND mp.subcaja_id = ?"; $bindings[] = $subCajaId; }
        if ($vendedorId) { $sql .= " AND v.user_id = ?"; $bindings[] = $vendedorId; }
        if ($desde) { $sql .= " AND v.fecha >= ?"; $bindings[] = $desde . ' 00:00:00'; }
        if ($hasta) { $sql .= " AND v.fecha <= ?"; $bindings[] = $hasta . ' 23:59:59'; }

        return ['sql' => $sql, 'bindings' => $bindings];
    }

    private function getCobrosQuery($metodoPagoId, $subCajaId, $vendedorId, $desde, $hasta)
    {
        $bindings = [];
        
        // Query para cobros activos (estado = 1)
        $sqlActivos = "SELECT 
                'COBRO' as tipo,
                'ingreso' as movimiento,
                cv.fecha,
                CONCAT('COBRO ', v.serie, '-', v.numero) as documento,
                CASE 
                    WHEN sc.id IS NOT NULL THEN 
                        CONCAT(
                            sc.nombre, '/', 
                            mp.name, '/', 
                            m.name,
                            CASE WHEN mp.nombre_titular IS NOT NULL AND mp.nombre_titular != '' 
                                THEN CONCAT('/', mp.nombre_titular) 
                                ELSE '' 
                            END
                        )
                    ELSE 
                        CONCAT(
                            mp.name, '/', 
                            m.name,
                            CASE WHEN mp.nombre_titular IS NOT NULL AND mp.nombre_titular != '' 
                                THEN CONCAT('/', mp.nombre_titular) 
                                ELSE '' 
                            END
                        )
                END as metodo_pago,
                cv.monto as entrada,
                0 as salida,
                cv.id as referencia_id,
                cv.user_id,
                m.metodo_de_pago_id,
                mp.subcaja_id
               FROM cobroventa cv
               JOIN desplieguedepago m ON m.id = cv.despliegue_de_pago_id
               JOIN metododepago mp ON mp.id = m.metodo_de_pago_id
               LEFT JOIN sub_cajas sc ON sc.id = mp.subcaja_id
               JOIN venta v ON v.id = cv.venta_id
               WHERE cv.estado = 1";
        
        $bindingsActivos = [];
        if ($metodoPagoId) { $sqlActivos .= " AND m.metodo_de_pago_id = ?"; $bindingsActivos[] = $metodoPagoId; }
        if ($subCajaId) { $sqlActivos .= " AND mp.subcaja_id = ?"; $bindingsActivos[] = $subCajaId; }
        if ($vendedorId) { $sqlActivos .= " AND cv.user_id = ?"; $bindingsActivos[] = $vendedorId; }
        if ($desde) { $sqlActivos .= " AND cv.fecha >= ?"; $bindingsActivos[] = $desde . ' 00:00:00'; }
        if ($hasta) { $sqlActivos .= " AND cv.fecha <= ?"; $bindingsActivos[] = $hasta . ' 23:59:59'; }

        // Query para cobros anulados (estado = 0) - muestra la anulación en la fecha de anulación
        $sqlAnulados = "SELECT 
                'COBRO ANULADO' as tipo,
                'egreso' as movimiento,
                COALESCE(cv.fecha_anulacion, cv.fecha) as fecha,
                CONCAT('COBRO ANULADO ', v.serie, '-', v.numero) as documento,
                CASE 
                    WHEN sc.id IS NOT NULL THEN 
                        CONCAT(
                            sc.nombre, '/', 
                            mp.name, '/', 
                            m.name,
                            CASE WHEN mp.nombre_titular IS NOT NULL AND mp.nombre_titular != '' 
                                THEN CONCAT('/', mp.nombre_titular) 
                                ELSE '' 
                            END
                        )
                    ELSE 
                        CONCAT(
                            mp.name, '/', 
                            m.name,
                            CASE WHEN mp.nombre_titular IS NOT NULL AND mp.nombre_titular != '' 
                                THEN CONCAT('/', mp.nombre_titular) 
                                ELSE '' 
                            END
                        )
                END as metodo_pago,
                0 as entrada,
                cv.monto as salida,
                cv.id as referencia_id,
                cv.user_id,
                m.metodo_de_pago_id,
                mp.subcaja_id
               FROM cobroventa cv
               JOIN desplieguedepago m ON m.id = cv.despliegue_de_pago_id
               JOIN metododepago mp ON mp.id = m.metodo_de_pago_id
               LEFT JOIN sub_cajas sc ON sc.id = mp.subcaja_id
               JOIN venta v ON v.id = cv.venta_id
               WHERE cv.estado = 0";
        
        $bindingsAnulados = [];
        if ($metodoPagoId) { $sqlAnulados .= " AND m.metodo_de_pago_id = ?"; $bindingsAnulados[] = $metodoPagoId; }
        if ($subCajaId) { $sqlAnulados .= " AND mp.subcaja_id = ?"; $bindingsAnulados[] = $subCajaId; }
        if ($vendedorId) { $sqlAnulados .= " AND cv.user_id = ?"; $bindingsAnulados[] = $vendedorId; }
        if ($desde) { $sqlAnulados .= " AND COALESCE(cv.fecha_anulacion, cv.fecha) >= ?"; $bindingsAnulados[] = $desde . ' 00:00:00'; }
        if ($hasta) { $sqlAnulados .= " AND COALESCE(cv.fecha_anulacion, cv.fecha) <= ?"; $bindingsAnulados[] = $hasta . ' 23:59:59'; }

        // Combinar ambas queries con UNION ALL
        $sql = "({$sqlActivos}) UNION ALL ({$sqlAnulados})";
        $bindings = array_merge($bindingsActivos, $bindingsAnulados);

        return ['sql' => $sql, 'bindings' => $bindings];
    }

    private function getComprasQuery($metodoPagoId, $subCajaId, $vendedorId, $desde, $hasta)
    {
        $bindings = [];
        
        // Query para pagos activos (estado = 1)
        $sqlActivos = "SELECT 
                'COMPRA' as tipo,
                'egreso' as movimiento,
                pdc.fecha,
                CONCAT('PAGO COMPRA ', COALESCE(c.serie, ''), '-', COALESCE(c.numero, '')) as documento,
                CASE 
                    WHEN sc.id IS NOT NULL THEN 
                        CONCAT(
                            sc.nombre, '/', 
                            mp.name, '/', 
                            m.name,
                            CASE WHEN mp.nombre_titular IS NOT NULL AND mp.nombre_titular != '' 
                                THEN CONCAT('/', mp.nombre_titular) 
                                ELSE '' 
                            END
                        )
                    ELSE 
                        CONCAT(
                            mp.name, '/', 
                            m.name,
                            CASE WHEN mp.nombre_titular IS NOT NULL AND mp.nombre_titular != '' 
                                THEN CONCAT('/', mp.nombre_titular) 
                                ELSE '' 
                            END
                        )
                END as metodo_pago,
                0 as entrada,
                pdc.monto as salida,
                pdc.id as referencia_id,
                c.user_id,
                m.metodo_de_pago_id,
                mp.subcaja_id
               FROM pagodecompra pdc
               JOIN desplieguedepago m ON m.id = pdc.despliegue_de_pago_id
               JOIN metododepago mp ON mp.id = m.metodo_de_pago_id
               LEFT JOIN sub_cajas sc ON sc.id = mp.subcaja_id
               JOIN compra c ON c.id = pdc.compra_id
               WHERE pdc.estado = 1 AND c.estado_de_compra != 'an'";

        $bindingsActivos = [];
        if ($metodoPagoId) { $sqlActivos .= " AND m.metodo_de_pago_id = ?"; $bindingsActivos[] = $metodoPagoId; }
        if ($subCajaId) { $sqlActivos .= " AND mp.subcaja_id = ?"; $bindingsActivos[] = $subCajaId; }
        if ($vendedorId) { $sqlActivos .= " AND c.user_id = ?"; $bindingsActivos[] = $vendedorId; }
        if ($desde) { $sqlActivos .= " AND pdc.fecha >= ?"; $bindingsActivos[] = $desde . ' 00:00:00'; }
        if ($hasta) { $sqlActivos .= " AND pdc.fecha <= ?"; $bindingsActivos[] = $hasta . ' 23:59:59'; }

        // Query para pagos anulados (estado = 0) - muestra la anulación en la fecha de anulación
        $sqlAnulados = "SELECT 
                'PAGO ANULADO' as tipo,
                'ingreso' as movimiento,
                COALESCE(pdc.fecha_anulacion, pdc.fecha) as fecha,
                CONCAT('PAGO ANULADO ', COALESCE(c.serie, ''), '-', COALESCE(c.numero, '')) as documento,
                CASE 
                    WHEN sc.id IS NOT NULL THEN 
                        CONCAT(
                            sc.nombre, '/', 
                            mp.name, '/', 
                            m.name,
                            CASE WHEN mp.nombre_titular IS NOT NULL AND mp.nombre_titular != '' 
                                THEN CONCAT('/', mp.nombre_titular) 
                                ELSE '' 
                            END
                        )
                    ELSE 
                        CONCAT(
                            mp.name, '/', 
                            m.name,
                            CASE WHEN mp.nombre_titular IS NOT NULL AND mp.nombre_titular != '' 
                                THEN CONCAT('/', mp.nombre_titular) 
                                ELSE '' 
                            END
                        )
                END as metodo_pago,
                pdc.monto as entrada,
                0 as salida,
                pdc.id as referencia_id,
                c.user_id,
                m.metodo_de_pago_id,
                mp.subcaja_id
               FROM pagodecompra pdc
               JOIN desplieguedepago m ON m.id = pdc.despliegue_de_pago_id
               JOIN metododepago mp ON mp.id = m.metodo_de_pago_id
               LEFT JOIN sub_cajas sc ON sc.id = mp.subcaja_id
               JOIN compra c ON c.id = pdc.compra_id
               WHERE pdc.estado = 0 AND c.estado_de_compra != 'an'";

        $bindingsAnulados = [];
        if ($metodoPagoId) { $sqlAnulados .= " AND m.metodo_de_pago_id = ?"; $bindingsAnulados[] = $metodoPagoId; }
        if ($subCajaId) { $sqlAnulados .= " AND mp.subcaja_id = ?"; $bindingsAnulados[] = $subCajaId; }
        if ($vendedorId) { $sqlAnulados .= " AND c.user_id = ?"; $bindingsAnulados[] = $vendedorId; }
        if ($desde) { $sqlAnulados .= " AND COALESCE(pdc.fecha_anulacion, pdc.fecha) >= ?"; $bindingsAnulados[] = $desde . ' 00:00:00'; }
        if ($hasta) { $sqlAnulados .= " AND COALESCE(pdc.fecha_anulacion, pdc.fecha) <= ?"; $bindingsAnulados[] = $hasta . ' 23:59:59'; }

        // Combinar ambas queries con UNION ALL
        $sql = "({$sqlActivos}) UNION ALL ({$sqlAnulados})";
        $bindings = array_merge($bindingsActivos, $bindingsAnulados);

        return ['sql' => $sql, 'bindings' => $bindings];
    }

    private function getIngresosExtrasQuery($metodoPagoId, $subCajaId, $vendedorId, $desde, $hasta)
    {
        $bindings = [];
        $sql = "SELECT 
                'ING_EXTRA' as tipo,
                'ingreso' as movimiento,
                ie.created_at as fecha,
                ie.concepto as documento,
                CASE 
                    WHEN sc.id IS NOT NULL THEN 
                        CONCAT(
                            sc.nombre, '/', 
                            mp.name, '/', 
                            m.name,
                            CASE WHEN mp.nombre_titular IS NOT NULL AND mp.nombre_titular != '' 
                                THEN CONCAT('/', mp.nombre_titular) 
                                ELSE '' 
                            END
                        )
                    ELSE 
                        CONCAT(
                            mp.name, '/', 
                            m.name,
                            CASE WHEN mp.nombre_titular IS NOT NULL AND mp.nombre_titular != '' 
                                THEN CONCAT('/', mp.nombre_titular) 
                                ELSE '' 
                            END
                        )
                END as metodo_pago,
                ie.monto as entrada,
                0 as salida,
                ie.id as referencia_id,
                ie.user_id,
                m.metodo_de_pago_id,
                mp.subcaja_id
               FROM ingresos_extras ie
               JOIN desplieguedepago m ON m.id = ie.despliegue_pago_id
               JOIN metododepago mp ON mp.id = m.metodo_de_pago_id
               LEFT JOIN sub_cajas sc ON sc.id = mp.subcaja_id
               WHERE ie.estado = 'aprobado'";

        if ($metodoPagoId) { $sql .= " AND m.metodo_de_pago_id = ?"; $bindings[] = $metodoPagoId; }
        if ($subCajaId) { $sql .= " AND mp.subcaja_id = ?"; $bindings[] = $subCajaId; }
        if ($vendedorId) { $sql .= " AND ie.user_id = ?"; $bindings[] = $vendedorId; }
        if ($desde) { $sql .= " AND ie.created_at >= ?"; $bindings[] = $desde . ' 00:00:00'; }
        if ($hasta) { $sql .= " AND ie.created_at <= ?"; $bindings[] = $hasta . ' 23:59:59'; }

        return ['sql' => $sql, 'bindings' => $bindings];
    }

    private function getGastosExtrasQuery($metodoPagoId, $subCajaId, $vendedorId, $desde, $hasta)
    {
        $bindings = [];
        $sql = "SELECT 
                'GAS_EXTRA' as tipo,
                'egreso' as movimiento,
                ge.created_at as fecha,
                ge.concepto as documento,
                CASE 
                    WHEN sc.id IS NOT NULL THEN 
                        CONCAT(
                            sc.nombre, '/', 
                            mp.name, '/', 
                            m.name,
                            CASE WHEN mp.nombre_titular IS NOT NULL AND mp.nombre_titular != '' 
                                THEN CONCAT('/', mp.nombre_titular) 
                                ELSE '' 
                            END
                        )
                    ELSE 
                        CONCAT(
                            mp.name, '/', 
                            m.name,
                            CASE WHEN mp.nombre_titular IS NOT NULL AND mp.nombre_titular != '' 
                                THEN CONCAT('/', mp.nombre_titular) 
                                ELSE '' 
                            END
                        )
                END as metodo_pago,
                0 as entrada,
                ge.monto as salida,
                ge.id as referencia_id,
                ge.user_id,
                m.metodo_de_pago_id,
                mp.subcaja_id
               FROM gastos_extras ge
               JOIN desplieguedepago m ON m.id = ge.despliegue_pago_id
               JOIN metododepago mp ON mp.id = m.metodo_de_pago_id
               LEFT JOIN sub_cajas sc ON sc.id = mp.subcaja_id
               WHERE 1=1";

        if ($metodoPagoId) { $sql .= " AND m.metodo_de_pago_id = ?"; $bindings[] = $metodoPagoId; }
        if ($subCajaId) { $sql .= " AND mp.subcaja_id = ?"; $bindings[] = $subCajaId; }
        if ($vendedorId) { $sql .= " AND ge.user_id = ?"; $bindings[] = $vendedorId; }
        if ($desde) { $sql .= " AND ge.created_at >= ?"; $bindings[] = $desde . ' 00:00:00'; }
        if ($hasta) { $sql .= " AND ge.created_at <= ?"; $bindings[] = $hasta . ' 23:59:59'; }

        return ['sql' => $sql, 'bindings' => $bindings];
    }

    /**
     * Realiza la paginación y el cálculo de saldos acumulados.
     */
    private function paginate(string $unionSql, array $bindings, int $perPage, int $page)
    {
        $wrappedSql = "SELECT * FROM ({$unionSql}) as movimientos";

        // 1. Obtener total de registros
        $totals = DB::selectOne(
            "SELECT COUNT(*) as total FROM ({$unionSql}) as t",
            $bindings
        );
        $total = (int) $totals->total;

        // 2. Ejecutar consulta con LIMIT y OFFSET
        if ($perPage === -1) {
            $rows = DB::select("{$wrappedSql} ORDER BY fecha ASC, referencia_id ASC", $bindings);
            $offset = 0;
        } else {
            $offset = ($page - 1) * $perPage;
            $rows = DB::select(
                "{$wrappedSql} ORDER BY fecha ASC, referencia_id ASC LIMIT ? OFFSET ?",
                array_merge($bindings, [$perPage, $offset])
            );
        }

        // 3. Saldo inicial para la página actual (acumulado histórico hasta el offset)
        $saldoAcumulado = 0;
        if ($offset > 0) {
            $pre = DB::selectOne(
                "SELECT COALESCE(SUM(entrada), 0) as se, COALESCE(SUM(salida), 0) as ss FROM (SELECT entrada, salida FROM ({$unionSql}) as t ORDER BY fecha ASC, referencia_id ASC LIMIT ?) as pre",
                array_merge($bindings, [$offset])
            );
            $saldoAcumulado = (float) $pre->se - (float) $pre->ss;
        }

        // 4. Mapear resultados con saldos
        $data = [];
        foreach ($rows as $row) {
            $row->saldo_anterior = round($saldoAcumulado, 2);
            $saldoAcumulado += (float) $row->entrada - (float) $row->salida;
            $row->saldo = round($saldoAcumulado, 2);
            $data[] = $row;
        }

        return [
            'data' => array_reverse($data),
            'total' => $total,
            'current_page' => $page,
            'per_page' => $perPage,
            'last_page' => $perPage === -1 ? 1 : (int) ceil($total / $perPage),
        ];
    }
}
