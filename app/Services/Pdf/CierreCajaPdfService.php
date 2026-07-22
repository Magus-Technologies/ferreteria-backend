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
            ->orderBy('fecha_traslado')
            ->get();
        $totalTrasladosBoveda = (float) $trasladosBoveda->sum('monto');

        $montoCierre = (float) ($cierre->monto_cierre_efectivo ?? 0);
        $totalCuentas = (float) ($cierre->monto_cierre_cuentas ?? 0);

        // Efectivo esperado = inicial + ventas en efectivo
        $efectivoEsperado = 0;
        foreach ($resumen['detalle_metodos_pago'] as $metodo) {
            if (stripos($metodo['label'], 'efectivo') !== false) {
                $efectivoEsperado += $metodo['total'];
            }
        }
        $montoEsperado = $resumen['efectivo_inicial'] + $efectivoEsperado;
        $diferencia = $montoCierre - $montoEsperado;

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
        // El resumen se calcula de la misma forma que el endpoint de caja activa
        // Los datos ya vienen pre-calculados en el response del backend
        // Aquí simulamos la estructura que espera el blade

        $conceptos = $cierre->conceptos_adicionales
            ? json_decode($cierre->conceptos_adicionales, true)
            : [];

        // Intentar obtener el resumen del cierre si existe como JSON
        // Si no, construir uno básico
        return [
            'efectivo_inicial' => (float) ($cierre->monto_apertura ?? 0),
            'detalle_metodos_pago' => $conceptos['detalle_metodos_pago'] ?? [],
            'total_ventas' => (float) ($conceptos['total_ventas'] ?? 0),
            'total_ingresos' => (float) ($conceptos['total_ingresos'] ?? 0),
            'total_egresos' => (float) ($conceptos['total_egresos'] ?? 0),
            'total_prestamos_recibidos' => (float) ($conceptos['total_prestamos_recibidos'] ?? 0),
            'total_prestamos_dados' => (float) ($conceptos['total_prestamos_dados'] ?? 0),
            'monto_esperado' => (float) ($conceptos['monto_esperado'] ?? 0),
            'prestamos_recibidos' => $conceptos['prestamos_recibidos'] ?? [],
            'prestamos_dados' => $conceptos['prestamos_dados'] ?? [],
            'movimientos_internos' => $conceptos['movimientos_internos'] ?? [],
        ];
    }
}
