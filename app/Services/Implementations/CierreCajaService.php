<?php

namespace App\Services\Implementations;

use App\Models\AperturaCierreCaja;
use App\Models\User;
use App\Services\Interfaces\CierreCajaServiceInterface;
use App\Repositories\Interfaces\AperturaCierreCajaRepositoryInterface;
use App\DTOs\CierreCaja\CajaActivaDTO;
use App\DTOs\CierreCaja\CierreCajaResultadoDTO;
use App\DTOs\CierreCaja\DiferenciasCajaDTO;
use App\Exceptions\AperturaNoEncontradaException;
use App\Exceptions\CajaYaCerradaException;
use App\Exceptions\SupervisorRequeridoException;
use App\Exceptions\SupervisorInvalidoException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class CierreCajaService implements CierreCajaServiceInterface
{
    const LIMITE_DIFERENCIA = 10.00;

    public function __construct(
        private AperturaCierreCajaRepositoryInterface $aperturaRepository
    ) {}

    public function obtenerCajaActivaConResumen(string $userId): CajaActivaDTO
    {
        $apertura = $this->aperturaRepository->findCajaActiva($userId);

        if (!$apertura) {
            throw new AperturaNoEncontradaException();
        }

        $resumen = $this->calcularResumenCaja($apertura->id);

        return new CajaActivaDTO($apertura, $resumen);
    }

    public function cerrarCajaConResumen(string $aperturaId, array $datosCierre): CierreCajaResultadoDTO
    {
        return DB::transaction(function () use ($aperturaId, $datosCierre) {
            $apertura = $this->aperturaRepository->findById($aperturaId);

            if (!$apertura) {
                throw new AperturaNoEncontradaException('Apertura no encontrada');
            }

            if ($apertura->estaCerrada()) {
                throw new CajaYaCerradaException();
            }

            // Calcular resumen y diferencias
            $resumen = $this->calcularResumenCaja($aperturaId);
            $diferencias = $this->calcularDiferencias(
                $resumen['total_en_caja'],
                $datosCierre['monto_cierre_efectivo'],
                $datosCierre['total_cuentas']
            );

            // Validar si requiere supervisor
            $this->validarRequiereSupervisor(
                $diferencias->diferenciaTotal,
                $datosCierre['supervisor_id'] ?? null,
                $datosCierre['forzar_cierre'] ?? false
            );

            // Actualizar apertura
            $apertura->update([
                'monto_cierre' => $diferencias->totalContado,
                'monto_cierre_efectivo' => $datosCierre['monto_cierre_efectivo'],
                'monto_cierre_cuentas' => $datosCierre['total_cuentas'],
                'conteo_billetes_monedas' => $datosCierre['conteo_billetes_monedas'] ?? null,
                'conceptos_adicionales' => $datosCierre['conceptos_adicionales'] ?? null,
                'comentarios' => $datosCierre['comentarios'] ?? null,
                'supervisor_id' => $datosCierre['supervisor_id'] ?? null,
                'diferencia_efectivo' => $diferencias->diferenciaEfectivo,
                'diferencia_total' => $diferencias->diferenciaTotal,
                'forzar_cierre' => $datosCierre['forzar_cierre'] ?? false,
                'fecha_cierre' => now(),
                'estado' => 'cerrada',
            ]);

            $apertura->fresh(['supervisor', 'cajaPrincipal', 'subCaja', 'user']);

            return new CierreCajaResultadoDTO($apertura, $diferencias, $resumen);
        });
    }

    public function calcularResumenCaja(string $aperturaId): array
    {
        $apertura = $this->aperturaRepository->findById($aperturaId);
        
        if (!$apertura) {
            return $this->resumenVacio();
        }

        $montoApertura = (float) $apertura->monto_apertura;
        
        // Usar solo la sub-caja específica de esta apertura
        $subCajaId = $apertura->sub_caja_id;
        
        // Obtener transacciones con información del método de pago BASE
        $transacciones = DB::table('transacciones_caja as t')
            ->where('t.sub_caja_id', $subCajaId)
            ->where('t.user_id', $apertura->user_id)
            ->where('t.fecha', '>=', $apertura->fecha_apertura)
            ->leftJoin('desplieguedepago as dp', 't.despliegue_pago_id', '=', 'dp.id')
            ->leftJoin('metododepago as mp', 'dp.metodo_de_pago_id', '=', 'mp.id')
            ->select(
                't.id',
                't.tipo_transaccion',
                't.monto',
                't.despliegue_pago_id',
                'dp.name as despliegue_nombre',
                'mp.name as metodo_base_nombre'
            )
            ->get();

        if ($transacciones->isEmpty()) {
            return $this->resumenVacio($montoApertura);
        }

        // Agrupar por método BASE (no por despliegue específico)
        $metodosPago = [];
        $totalIngresos = 0;
        $totalEgresos = 0;

        foreach ($transacciones as $transaccion) {
            $monto = (float) $transaccion->monto;
            
            // Usar el método BASE para agrupar (ej: "Efectivo CA", "BCP CB")
            // Si no tiene método base, usar el despliegue, si no tiene ninguno, "Sin método"
            $nombreMetodo = $transaccion->metodo_base_nombre 
                ?? $transaccion->despliegue_nombre 
                ?? 'Sin método';
            
            if (!isset($metodosPago[$nombreMetodo])) {
                $metodosPago[$nombreMetodo] = 0;
            }

            if (in_array($transaccion->tipo_transaccion, ['ingreso', 'prestamo_recibido', 'movimiento_interno_entrada'])) {
                $metodosPago[$nombreMetodo] += $monto;
                $totalIngresos += $monto;
            } elseif (in_array($transaccion->tipo_transaccion, ['egreso', 'prestamo_enviado', 'movimiento_interno_salida'])) {
                $metodosPago[$nombreMetodo] -= $monto;
                $totalEgresos += $monto;
            }
        }

        // Obtener ventas y agrupar por método BASE también
        $ventas = DB::table('venta as v')
            ->where('v.user_id', $apertura->user_id)
            ->where('v.fecha', '>=', $apertura->fecha_apertura)
            ->where('v.estado_de_venta', 'cr')
            ->join('desplieguedepagoventa as dpv', 'v.id', '=', 'dpv.venta_id')
            ->join('desplieguedepago as dp', 'dpv.despliegue_de_pago_id', '=', 'dp.id')
            ->leftJoin('metododepago as mp', 'dp.metodo_de_pago_id', '=', 'mp.id')
            ->select(
                'dpv.monto',
                'dp.name as despliegue_nombre',
                'mp.name as metodo_base_nombre'
            )
            ->get();

        foreach ($ventas as $venta) {
            $monto = (float) $venta->monto;
            $nombreMetodo = $venta->metodo_base_nombre 
                ?? $venta->despliegue_nombre 
                ?? 'Sin método';
            
            if (!isset($metodosPago[$nombreMetodo])) {
                $metodosPago[$nombreMetodo] = 0;
            }
            
            $metodosPago[$nombreMetodo] += $monto;
            $totalIngresos += $monto;
        }

        $totalEnCaja = $montoApertura + $totalIngresos - $totalEgresos;

        return [
            'monto_apertura' => $montoApertura,
            'metodos_pago' => $metodosPago,
            'total_ingresos' => $totalIngresos,
            'total_egresos' => $totalEgresos,
            'total_en_caja' => $totalEnCaja,
            'resumen_ventas' => $totalIngresos,
            'resumen_ingresos' => $totalIngresos,
            'resumen_egresos' => $totalEgresos,
        ];
    }

    public function obtenerDetalleMovimientos(string $aperturaId): array
    {
        $apertura = $this->aperturaRepository->findById($aperturaId);
        
        if (!$apertura) {
            throw new AperturaNoEncontradaException('Apertura no encontrada');
        }

        // Usar solo la sub-caja específica de esta apertura
        $subCajaId = $apertura->sub_caja_id;
        
        $transacciones = DB::table('transacciones_caja as t')
            ->where('t.sub_caja_id', $subCajaId)
            ->where('t.user_id', $apertura->user_id)
            ->where('t.fecha', '>=', $apertura->fecha_apertura)
            ->leftJoin('desplieguedepago as dp', 't.despliegue_pago_id', '=', 'dp.id')
            ->leftJoin('metododepago as mp', 'dp.metodo_de_pago_id', '=', 'mp.id')
            ->leftJoin('sub_cajas as sc', 't.sub_caja_id', '=', 'sc.id')
            ->select(
                't.id',
                't.tipo_transaccion',
                't.monto',
                't.descripcion',
                't.fecha',
                'dp.name as metodo_pago_nombre',
                'mp.name as metodo_base_nombre',
                'sc.nombre as sub_caja_nombre',
                'sc.codigo as sub_caja_codigo'
            )
            ->orderBy('t.fecha', 'asc')
            ->get();

        $movimientos = $this->clasificarMovimientos($transacciones);
        $totalesPorMetodo = $this->calcularTotalesPorMetodo($transacciones);

        // Obtener ventas
        $ventas = $this->obtenerVentas($apertura);
        $movimientos['ventas'] = $ventas['movimientos'];
        $totalesPorMetodo = array_merge($totalesPorMetodo, $ventas['totales']);

        $totalIngresos = array_sum(array_map(fn($m) => (float)$m['monto'], $movimientos['ingresos']));
        $totalEgresos = array_sum(array_map(fn($m) => (float)$m['monto'], $movimientos['egresos']));
        $totalVentas = array_sum(array_map(fn($m) => (float)$m['monto'], $movimientos['ventas']));

        return [
            'movimientos' => $movimientos,
            'totales_por_metodo' => $totalesPorMetodo,
            'resumen' => [
                'total_ventas' => number_format($totalVentas, 2, '.', ''),
                'total_ingresos' => number_format($totalIngresos, 2, '.', ''),
                'total_egresos' => number_format($totalEgresos, 2, '.', ''),
                'total_movimientos' => count($transacciones) + count($ventas['movimientos']),
            ],
        ];
    }

    public function cerrarCaja(
        string $aperturaId,
        float $montoEfectivo,
        float $totalCuentas,
        ?array $conteoBilletes = null,
        ?array $conceptosAdicionales = null,
        ?string $comentarios = null,
        ?string $supervisorId = null,
        bool $forzarCierre = false
    ): AperturaCierreCaja {
        return DB::transaction(function () use (
            $aperturaId,
            $montoEfectivo,
            $totalCuentas,
            $conteoBilletes,
            $conceptosAdicionales,
            $comentarios,
            $supervisorId,
            $forzarCierre
        ) {
            $apertura = $this->aperturaRepository->findById($aperturaId);

            if (!$apertura) {
                throw new AperturaNoEncontradaException('Apertura no encontrada');
            }

            if ($apertura->estaCerrada()) {
                throw new CajaYaCerradaException();
            }

            $resumen = $this->calcularResumenCaja($aperturaId);
            $efectivoEsperado = $resumen['total_en_caja'];
            $totalContado = $montoEfectivo + $totalCuentas;
            $diferenciaTotal = $totalContado - $efectivoEsperado;

            $requiereSupervisor = abs($diferenciaTotal) > self::LIMITE_DIFERENCIA;

            if ($requiereSupervisor && !$supervisorId && !$forzarCierre) {
                throw new SupervisorRequeridoException($diferenciaTotal, self::LIMITE_DIFERENCIA);
            }

            $apertura->update([
                'monto_cierre' => $totalContado,
                'monto_cierre_efectivo' => $montoEfectivo,
                'monto_cierre_cuentas' => $totalCuentas,
                'conteo_billetes_monedas' => $conteoBilletes,
                'conceptos_adicionales' => $conceptosAdicionales,
                'comentarios' => $comentarios,
                'supervisor_id' => $supervisorId,
                'diferencia_efectivo' => $montoEfectivo - $efectivoEsperado,
                'diferencia_total' => $diferenciaTotal,
                'forzar_cierre' => $forzarCierre,
                'fecha_cierre' => now(),
                'estado' => 'cerrada',
            ]);

            return $apertura->fresh(['supervisor', 'cajaPrincipal', 'subCaja', 'user']);
        });
    }

    public function validarSupervisor(string $email, string $password): ?array
    {
        $user = User::where('email', $email)->first();
        
        if (!$user || !Hash::check($password, $user->password)) {
            return null;
        }

        $puedeAutorizar = $user->hasRole('admin') || $user->hasRole('supervisor');

        if (!$puedeAutorizar) {
            return null;
        }

        return [
            'supervisor_id' => $user->id,
            'name' => $user->name,
            'puede_autorizar' => true,
        ];
    }

    // Métodos privados auxiliares

    private function calcularDiferencias(
        float $totalEsperado,
        float $efectivoContado,
        float $totalCuentas
    ): DiferenciasCajaDTO {
        $totalContado = $efectivoContado + $totalCuentas;
        $diferenciaTotal = $totalContado - $totalEsperado;
        $diferenciaEfectivo = $efectivoContado - $totalEsperado;

        return new DiferenciasCajaDTO(
            efectivoEsperado: $totalEsperado,
            efectivoContado: $efectivoContado,
            diferenciaEfectivo: $diferenciaEfectivo,
            totalEsperado: $totalEsperado,
            totalContado: $totalContado,
            diferenciaTotal: $diferenciaTotal,
            sobrante: max(0, $diferenciaTotal),
            faltante: max(0, -$diferenciaTotal)
        );
    }

    private function validarRequiereSupervisor(
        float $diferenciaTotal,
        ?string $supervisorId,
        bool $forzarCierre
    ): void {
        $requiereSupervisor = abs($diferenciaTotal) > self::LIMITE_DIFERENCIA;

        if ($requiereSupervisor && !$supervisorId && !$forzarCierre) {
            throw new SupervisorRequeridoException($diferenciaTotal, self::LIMITE_DIFERENCIA);
        }
    }

    private function resumenVacio(float $montoApertura = 0): array
    {
        return [
            'monto_apertura' => $montoApertura,
            'metodos_pago' => [],
            'total_ingresos' => 0,
            'total_egresos' => 0,
            'total_en_caja' => $montoApertura,
            'resumen_ventas' => 0,
            'resumen_ingresos' => 0,
            'resumen_egresos' => 0,
        ];
    }

    private function obtenerNombreMetodoPago(?string $desplieguePagoId): string
    {
        if (!$desplieguePagoId) {
            return 'Sin método';
        }

        $metodoPago = DB::table('desplieguedepago')
            ->where('id', $desplieguePagoId)
            ->first();

        return $metodoPago ? $metodoPago->name : 'Sin método';
    }

    private function clasificarMovimientos($transacciones): array
    {
        $movimientos = [
            'ventas' => [],
            'ingresos' => [],
            'egresos' => [],
            'prestamos_enviados' => [],
            'prestamos_recibidos' => [],
            'movimientos_internos_salida' => [],
            'movimientos_internos_entrada' => [],
        ];

        foreach ($transacciones as $transaccion) {
            // Usar método base para mostrar
            $metodoPago = $transaccion->metodo_base_nombre 
                ?? $transaccion->metodo_pago_nombre 
                ?? 'Sin método';
                
            $movimiento = [
                'id' => $transaccion->id,
                'fecha' => $transaccion->fecha,
                'descripcion' => $transaccion->descripcion,
                'monto' => number_format((float)$transaccion->monto, 2, '.', ''),
                'metodo_pago' => $metodoPago,
                'metodo_pago_detalle' => $transaccion->metodo_pago_nombre ?? 'Sin método',
                'sub_caja' => $transaccion->sub_caja_nombre,
                'sub_caja_codigo' => $transaccion->sub_caja_codigo,
            ];

            switch ($transaccion->tipo_transaccion) {
                case 'ingreso':
                    $movimientos['ingresos'][] = $movimiento;
                    break;
                case 'egreso':
                    $movimientos['egresos'][] = $movimiento;
                    break;
                case 'prestamo_enviado':
                    $movimientos['prestamos_enviados'][] = $movimiento;
                    break;
                case 'prestamo_recibido':
                    $movimientos['prestamos_recibidos'][] = $movimiento;
                    break;
                case 'movimiento_interno_salida':
                    $movimientos['movimientos_internos_salida'][] = $movimiento;
                    break;
                case 'movimiento_interno_entrada':
                    $movimientos['movimientos_internos_entrada'][] = $movimiento;
                    break;
            }
        }

        return $movimientos;
    }

    private function calcularTotalesPorMetodo($transacciones): array
    {
        $totales = [];

        foreach ($transacciones as $transaccion) {
            $monto = (float) $transaccion->monto;
            // Usar método base para agrupar
            $metodoPago = $transaccion->metodo_base_nombre 
                ?? $transaccion->metodo_pago_nombre 
                ?? 'Sin método';
            
            if (!isset($totales[$metodoPago])) {
                $totales[$metodoPago] = 0;
            }

            if (in_array($transaccion->tipo_transaccion, ['ingreso', 'prestamo_recibido', 'movimiento_interno_entrada'])) {
                $totales[$metodoPago] += $monto;
            } else {
                $totales[$metodoPago] -= $monto;
            }
        }

        return $totales;
    }

    private function obtenerVentas($apertura): array
    {
        $ventas = DB::table('venta as v')
            ->where('v.user_id', $apertura->user_id)
            ->where('v.fecha', '>=', $apertura->fecha_apertura)
            ->where('v.estado_de_venta', 'cr')
            ->join('desplieguedepagoventa as dpv', 'v.id', '=', 'dpv.venta_id')
            ->join('desplieguedepago as dp', 'dpv.despliegue_de_pago_id', '=', 'dp.id')
            ->leftJoin('metododepago as mp', 'dp.metodo_de_pago_id', '=', 'mp.id')
            ->select(
                'v.id',
                'v.serie',
                'v.numero',
                'v.fecha',
                'dpv.monto',
                'dp.name as metodo_pago_nombre',
                'mp.name as metodo_base_nombre'
            )
            ->get();

        $movimientos = [];
        $totales = [];

        foreach ($ventas as $venta) {
            $monto = (float) $venta->monto;
            // Usar método base para agrupar
            $metodoPago = $venta->metodo_base_nombre 
                ?? $venta->metodo_pago_nombre 
                ?? 'Sin método';
            
            if (!isset($totales[$metodoPago])) {
                $totales[$metodoPago] = 0;
            }
            
            $totales[$metodoPago] += $monto;
            
            $movimientos[] = [
                'id' => $venta->id,
                'serie_numero' => $venta->serie . '-' . $venta->numero,
                'fecha' => $venta->fecha,
                'monto' => number_format($monto, 2, '.', ''),
                'metodo_pago' => $metodoPago,
                'metodo_pago_detalle' => $venta->metodo_pago_nombre ?? 'Sin método',
            ];
        }

        return [
            'movimientos' => $movimientos,
            'totales' => $totales,
        ];
    }
}
