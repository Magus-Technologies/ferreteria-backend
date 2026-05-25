<?php

namespace App\Services\Pdf;

use App\Models\PagoDeCompra;
use App\Services\Pdf\Traits\ResuelveEstilosPlantilla;
use Illuminate\Http\Response;

class PagoCompraPdfService
{
    use ResuelveEstilosPlantilla;

    public function generar(string $pagoId): Response
    {
        $pago = PagoDeCompra::with([
            'compra.user.empresa',
            'compra.proveedor',
            'despliegueDePago.metodoDePago',
        ])->findOrFail($pagoId);

        $compra = $pago->compra;
        $empresa = $compra->user->empresa;
        $proveedor = $compra->proveedor;

        $proveedorNombre = $proveedor?->razon_social ?? 'Sin proveedor';
        $proveedorRuc = $proveedor?->ruc ?? '-';

        $tipoDocMap = ['01' => 'FACTURA', '03' => 'BOLETA', 'gr' => 'GUÍA REMISIÓN'];
        $tipoDoc = $tipoDocMap[$compra->tipo_documento ?? ''] ?? ($compra->tipo_documento ?? '');
        $nroDocumento = ($compra->serie ?? '') . '-' . str_pad($compra->numero ?? 0, 8, '0', STR_PAD_LEFT);

        $totalCompra = $this->calcularTotalCompra($compra);
        $totalPagado = $compra->pagosDeCompras()->where('estado', true)->sum('monto');
        $saldoPendiente = $totalCompra - $totalPagado;

        $metodoPago = $pago->despliegueDePago?->metodoDePago?->name
            ? $pago->despliegueDePago->metodoDePago->name . ' / ' . $pago->despliegueDePago->name
            : $pago->despliegueDePago?->name ?? '-';

        $estilos = $this->prepararDatosPlantilla((int) $empresa->id, 'pago-compra', 'Ticket');

        $data = array_merge($estilos, [
            'empresa'        => $empresa,
            'logoPath'       => PdfService::getLogoPath($empresa->logo),
            'pago'           => $pago,
            'compra'         => $compra,
            'proveedorNombre' => $proveedorNombre,
            'proveedorRuc'   => $proveedorRuc,
            'tipoDoc'        => $tipoDoc,
            'nroDocumento'   => $nroDocumento,
            'metodoPago'     => $metodoPago,
            'totalNeto'      => number_format($totalCompra, 2),
            'totalPagado'    => number_format($totalPagado, 2),
            'saldoPendiente' => number_format(max(0, $saldoPendiente), 2),
        ]);

        $filename = "pago-compra-{$pago->id}.pdf";

        return PdfService::render(
            'pdf.pago-compra-ticket',
            $data,
            $filename,
            'portrait',
            [0, 0, 226.77, 841.89]
        );
    }

    private function calcularTotalCompra($compra): float
    {
        $total = 0;
        $compra->load('productosPorAlmacen.unidadesDerivadas');

        foreach ($compra->productosPorAlmacen as $ppa) {
            foreach ($ppa->unidadesDerivadas as $ud) {
                $costo = (float) ($ppa->costo ?? 0);
                $cantidad = (float) ($ud->cantidad ?? 0);
                $flete = (float) ($ud->flete ?? 0);
                $bonificacion = (bool) ($ud->bonificacion ?? false);
                if (!$bonificacion) {
                    $total += ($costo * $cantidad) + $flete;
                }
            }
        }

        return $total + (float) ($compra->percepcion ?? 0);
    }
}
