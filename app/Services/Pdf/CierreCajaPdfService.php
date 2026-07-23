<?php

namespace App\Services\Pdf;

use App\Models\AperturaCierreCaja;
use App\Services\Pdf\Traits\ResuelveEstilosPlantilla;
use Illuminate\Http\Response;

class CierreCajaPdfService
{
    use ResuelveEstilosPlantilla;

    public function generar(string $id, string $formato = 'ticket'): Response
    {
        $cierre = $this->obtenerCierre($id);
        $empresa = $cierre->user->empresa;
        $resumen = $this->prepararResumen($cierre);

        // Traslados a Bóveda de esta caja (informativos: no afectan los totales)
        $trasladosBoveda = \App\Models\TrasladoBoveda::with(['vendedor', 'supervisor'])
            ->whereRaw('UPPER(apertura_cierre_caja_id) = ?', [strtoupper((string) $cierre->id)])
            ->where('estado', 'activo')
            ->orderBy('fecha_traslado')
            ->get();
        $totalTrasladosBoveda = (float) $trasladosBoveda->sum('monto');

        $montoCierre = (float) ($cierre->monto_cierre_efectivo ?? 0);
        $totalCuentas = (float) ($cierre->monto_cierre_cuentas ?? 0);

        // Monto esperado REAL del resumen (incluye ingresos extras, gastos y
        // traslados a bóveda). Fallback para registros antiguos sin snapshot:
        // inicial + ventas en efectivo (fórmula anterior, incompleta).
        if ($resumen['monto_esperado'] > 0) {
            $montoEsperado = $resumen['monto_esperado'];
        } else {
            $efectivoEsperado = 0;
            foreach ($resumen['detalle_metodos_pago'] as $metodo) {
                if (stripos($metodo['label'], 'efectivo') !== false) {
                    $efectivoEsperado += $metodo['total'];
                }
            }
            $montoEsperado = $resumen['efectivo_inicial'] + $efectivoEsperado;
        }
        // Redondear: el snapshot arrastra residuos de coma flotante (831.1000000000004)
        // y sin esto una caja cuadrada saldría como "FALTANTE S/ -0.00".
        $montoEsperado = round($montoEsperado, 2);
        $diferencia = round($montoCierre - $montoEsperado, 2);

        $otrosIngresos = ($resumen['total_ingresos'] - $resumen['total_ventas'] - $resumen['total_prestamos_recibidos']);
        $gastos = ($resumen['total_egresos'] - $resumen['total_prestamos_dados']);

        $nroDoc = 'CIERRE-' . str_pad($cierre->id, 6, '0', STR_PAD_LEFT);

        $estilos = $this->prepararDatosPlantilla((int) $empresa->id, 'cierre-caja', $formato === 'a4' ? 'A4' : 'Ticket');

        $data = array_merge($estilos, [
            'empresa' => $empresa,
            'logoPath' => PdfService::getLogoPath($empresa->logo),
            'cierre' => $cierre,
            'resumen' => $resumen,
            'nroDoc' => $nroDoc,
            'montoCierre' => $montoCierre,
            'totalCuentas' => $totalCuentas,
            'montoEsperado' => $montoEsperado,
            'diferencia' => $diferencia,
            'faltante' => $diferencia < 0 ? abs($diferencia) : 0,
            'sobrante' => $diferencia > 0 ? $diferencia : 0,
            'otrosIngresos' => max($otrosIngresos, 0),
            'gastos' => max($gastos, 0),
            'conteo' => $cierre->conteo_billetes_monedas ? json_decode($cierre->conteo_billetes_monedas, true) : null,
            'trasladosBoveda' => $trasladosBoveda,
            'totalTrasladosBoveda' => $totalTrasladosBoveda,
        ]);

        $filename = "{$nroDoc}.pdf";

        if ($formato === 'a4') {
            return PdfService::render('pdf.cierre-caja', $data, $filename);
        }

        return PdfService::render(
            'pdf.cierre-caja-ticket',
            $data,
            $filename,
            'portrait',
            [0, 0, 226.77, 1200],
        );
    }

    private function obtenerCierre(string $id): AperturaCierreCaja
    {
        $conRelaciones = fn () => AperturaCierreCaja::with([
            'user.empresa',
            'cajaPrincipal',
            'supervisor',
        ]);

        // Búsqueda CASE-INSENSITIVE: los ULID se guardan en MAYÚSCULAS, pero el front a
        // veces manda el id en minúsculas. En local la collation suele ser case-insensitive
        // (matchea igual), pero en producción puede ser case-sensitive → daba 404.
        $idUpper = strtoupper($id);
        $cierre = $conRelaciones()->whereRaw('UPPER(id) = ?', [$idUpper])->first();

        // Si no es una apertura, puede ser el id de un ARQUEO → resolver su apertura.
        if (!$cierre) {
            $arqueo = \App\Models\ArqueoDiario::whereRaw('UPPER(id) = ?', [$idUpper])->first();
            if ($arqueo) {
                $cierre = $conRelaciones()
                    ->whereRaw('UPPER(id) = ?', [strtoupper((string) $arqueo->apertura_cierre_caja_id)])
                    ->first();
            }
        }

        if (!$cierre) {
            abort(404, 'Cierre de caja no encontrado');
        }

        return $cierre;
    }

    private function prepararResumen(AperturaCierreCaja $cierre): array
    {
        // FUENTE PRINCIPAL: el resumen_snapshot del último arqueo de esta apertura.
        // Es exactamente lo que la pantalla muestra y lo que cuadró al cerrar.
        // (Antes se leía conceptos_adicionales, un campo legacy que nadie escribe:
        // el ticket salía con métodos de pago vacíos y "monto esperado" = solo el
        // efectivo inicial, inventando faltantes que no existían.)
        $arqueo = \App\Models\ArqueoDiario::whereRaw('UPPER(apertura_cierre_caja_id) = ?', [strtoupper((string) $cierre->id)])
            ->latest()
            ->first();

        $snapshot = $arqueo?->resumen_snapshot;
        if (is_string($snapshot)) {
            $snapshot = json_decode($snapshot, true);
        }

        // Fallback legacy para registros antiguos sin arqueo
        if (!is_array($snapshot) || empty($snapshot)) {
            $conceptos = $cierre->conceptos_adicionales;
            if (is_string($conceptos)) {
                $conceptos = json_decode($conceptos, true);
            }
            $snapshot = is_array($conceptos) ? $conceptos : [];
        }

        return [
            'efectivo_inicial' => (float) ($snapshot['efectivo_inicial'] ?? $cierre->monto_apertura ?? 0),
            'detalle_metodos_pago' => $snapshot['detalle_metodos_pago'] ?? [],
            'total_ventas' => (float) ($snapshot['total_ventas'] ?? 0),
            'total_ingresos' => (float) ($snapshot['total_ingresos'] ?? 0),
            'total_egresos' => (float) ($snapshot['total_egresos'] ?? 0),
            'total_prestamos_recibidos' => (float) ($snapshot['total_prestamos_recibidos'] ?? 0),
            'total_prestamos_dados' => (float) ($snapshot['total_prestamos_dados'] ?? 0),
            'monto_esperado' => (float) ($snapshot['monto_esperado'] ?? 0),
            'prestamos_recibidos' => $snapshot['prestamos_recibidos'] ?? [],
            'prestamos_dados' => $snapshot['prestamos_dados'] ?? [],
            'movimientos_internos' => $snapshot['movimientos_internos'] ?? [],
        ];
    }
}
