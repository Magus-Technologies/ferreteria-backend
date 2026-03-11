<?php

namespace App\Services\Pdf;

use App\Models\EntregaProducto;
use Illuminate\Http\Response;

class EntregaProductoPdfService
{
    public function generar(int $id): Response
    {
        $entrega = EntregaProducto::with([
            'venta.cliente',
            'almacenSalida',
            'despachador',
            'user',
            'productosEntregados.unidadDerivadaVenta.productoAlmacenVenta.productoAlmacen.producto',
            'productosEntregados.unidadDerivadaVenta.unidadDerivadaInmutable',
        ])->findOrFail($id);

        $empresa = $entrega->user->empresa ?? $entrega->venta->user->empresa ?? null;
        if (!$empresa) {
            // Try to get empresa from user relation
            $entrega->load('user.empresa', 'venta.user.empresa');
            $empresa = $entrega->user->empresa ?? $entrega->venta->user->empresa;
        }

        $cliente = $entrega->venta->cliente ?? null;
        $productos = $this->prepararProductos($entrega);

        // Format venta number
        $serie = $entrega->venta->serie ?? '';
        $numero = $entrega->venta->numero ?? '';
        $nroVenta = $serie && $numero ? "{$serie}-{$numero}" : ($entrega->venta->codigo ?? '—');

        // Tipo entrega label
        $tipoEntregaLabel = match($entrega->tipo_entrega) {
            'rt' => 'Recojo en Tienda',
            'de' => 'Despacho a Domicilio',
            'pa' => 'Parcial',
            default => $entrega->tipo_entrega,
        };

        // Tipo despacho label
        $tipoDespachoLabel = match($entrega->tipo_despacho) {
            'in' => 'Inmediato',
            'pr' => 'Programado',
            default => $entrega->tipo_despacho,
        };

        $data = [
            'entrega' => $entrega,
            'empresa' => $empresa,
            'logoPath' => PdfService::getLogoPath($empresa->logo ?? null),
            'cliente' => $cliente,
            'productos' => $productos,
            'nroVenta' => $nroVenta,
            'tipoEntregaLabel' => $tipoEntregaLabel,
            'tipoDespachoLabel' => $tipoDespachoLabel,
        ];

        return PdfService::render(
            'pdf.entrega-ticket',
            $data,
            "TICKET-ENTREGA-{$entrega->id}.pdf",
            'portrait',
            [0, 0, 226.77, 841.89],
        );
    }

    private function prepararProductos(EntregaProducto $entrega): array
    {
        $productos = [];

        foreach ($entrega->productosEntregados as $detalle) {
            $udv = $detalle->unidadDerivadaVenta;
            $pav = $udv?->productoAlmacenVenta;
            $pa = $pav?->productoAlmacen;
            $producto = $pa?->producto;

            $productos[] = [
                'codigo' => $producto->cod_producto ?? '',
                'nombre' => $producto->name ?? '',
                'cantidad' => (float) $detalle->cantidad_entregada,
                'unidad' => $udv?->unidadDerivadaInmutable?->name ?? '',
                'ubicacion' => $detalle->ubicacion ?? '',
            ];
        }

        return $productos;
    }
}
