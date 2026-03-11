<?php

namespace App\Services\Pdf;

use App\Models\AperturaCierreCaja;
use Illuminate\Http\Response;

class CierreCajaPdfService
{
    public function generar(string $id, string $formato = 'ticket'): Response
    {
        $cierre = $this->obtenerCierre($id);
        $empresa = $cierre->user->empresa;
        $resumen = $this->prepararResumen($cierre);

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

        $data = [
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
        ];

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
        return AperturaCierreCaja::with([
            'user.empresa',
            'cajaPrincipal',
            'supervisor',
        ])->findOrFail($id);
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
