<?php

namespace App\Services\Pdf;

use App\Models\AperturaCierreCaja;
use Illuminate\Http\Response;

class AperturaCajaPdfService
{
    public function generar(string $id, string $formato = 'ticket'): Response
    {
        $apertura = $this->obtenerApertura($id);
        $empresa = $apertura->user->empresa;

        $conteo = $apertura->conteo_apertura_billetes_monedas;

        // Preparar distribuciones de vendedores
        $distribuciones = $apertura->distribucionesVendedores->map(function ($dist) {
            return [
                'vendedor' => $dist->vendedor->name ?? 'N/A',
                'monto' => (float) $dist->monto,
                'conteo_billetes_monedas' => $dist->conteo_billetes_monedas,
            ];
        })->toArray();

        $nroDoc = 'APERTURA-' . $apertura->id;

        $data = [
            'empresa' => $empresa,
            'logoPath' => PdfService::getLogoPath($empresa->logo),
            'apertura' => $apertura,
            'nroDoc' => $nroDoc,
            'conteo' => $conteo,
            'distribuciones' => $distribuciones,
        ];

        $filename = "{$nroDoc}.pdf";

        return PdfService::render(
            'pdf.apertura-caja-ticket',
            $data,
            $filename,
            'portrait',
            [0, 0, 226.77, 900],
        );
    }

    private function obtenerApertura(string $id): AperturaCierreCaja
    {
        return AperturaCierreCaja::with([
            'user.empresa',
            'cajaPrincipal',
            'distribucionesVendedores.vendedor',
        ])->findOrFail($id);
    }
}
