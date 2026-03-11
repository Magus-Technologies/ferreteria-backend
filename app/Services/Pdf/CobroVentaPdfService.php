<?php

namespace App\Services\Pdf;

use App\Models\CobroVenta;
use Illuminate\Http\Response;

class CobroVentaPdfService
{
    /**
     * Generar PDF ticket de un cobro de venta.
     */
    public function generar(string $cobroId): Response
    {
        $cobro = CobroVenta::with([
            'venta.cliente',
            'venta.user',
            'despliegueDePago.metodoDePago',
            'user',
        ])->findOrFail($cobroId);

        $venta = $cobro->venta;
        $empresa = $venta->user->empresa;
        $cliente = $venta->cliente;

        $clienteNombre = $cliente?->razon_social
            ?: trim(($cliente?->nombres ?? '') . ' ' . ($cliente?->apellidos ?? ''))
            ?: 'CLIENTES VARIOS';

        $clienteDoc = $cliente?->numero_documento ?? '-';

        $tipoDocMap = [
            '01' => 'FACTURA',
            '03' => 'BOLETA',
            'nv' => 'NOTA DE VENTA',
        ];
        $tipoDoc = $tipoDocMap[$venta->tipo_documento->value ?? ''] ?? $venta->tipo_documento->value ?? '';
        $nroDocumento = $venta->serie . '-' . str_pad($venta->numero, 8, '0', STR_PAD_LEFT);

        // Calcular totales de cobros para saldo
        $totalVenta = $venta->cobrosVenta()->where('estado', true)->sum('monto');
        // El total de la venta real se calcula desde productos
        $totalNeto = $this->calcularTotalVenta($venta);
        $saldoPendiente = $totalNeto - $totalVenta;

        $metodoPago = $cobro->despliegueDePago?->metodoDePago?->name
            ? $cobro->despliegueDePago->metodoDePago->name . ' / ' . $cobro->despliegueDePago->name
            : $cobro->despliegueDePago?->name ?? '-';

        $data = [
            'empresa' => $empresa,
            'logoPath' => PdfService::getLogoPath($empresa->logo),
            'cobro' => $cobro,
            'venta' => $venta,
            'clienteNombre' => $clienteNombre,
            'clienteDoc' => $clienteDoc,
            'tipoDoc' => $tipoDoc,
            'nroDocumento' => $nroDocumento,
            'metodoPago' => $metodoPago,
            'totalNeto' => number_format($totalNeto, 2),
            'totalCobrado' => number_format($totalVenta, 2),
            'saldoPendiente' => number_format(max(0, $saldoPendiente), 2),
            'registradoPor' => $cobro->user?->name ?? '-',
        ];

        $filename = "cobro-{$cobro->id}.pdf";

        return PdfService::render(
            'pdf.cobro-venta-ticket',
            $data,
            $filename,
            'portrait',
            [0, 0, 226.77, 841.89]
        );
    }

    /**
     * Calcular el total neto de la venta desde sus productos.
     */
    private function calcularTotalVenta($venta): float
    {
        $total = 0;
        $venta->load('productosPorAlmacen.unidadesDerivadas');

        foreach ($venta->productosPorAlmacen as $ppa) {
            foreach ($ppa->unidadesDerivadas as $ud) {
                $precio = (float) ($ud->precio ?? 0);
                $cantidad = (float) ($ud->cantidad ?? 0);
                $descuento = (float) ($ud->descuento ?? 0);
                $bonificacion = (bool) ($ud->bonificacion ?? false);
                if (!$bonificacion) {
                    $total += ($precio * $cantidad) - $descuento;
                }
            }
        }

        return $total;
    }
}
