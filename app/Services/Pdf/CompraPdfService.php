<?php

namespace App\Services\Pdf;

use App\Models\Compra;
use Illuminate\Http\Response;

class CompraPdfService
{
    public function generar(string $compraId): Response
    {
        $compra = $this->obtenerCompra($compraId);
        $empresa = $compra->user->empresa;

        $productos = $this->prepararProductos($compra);
        $calculos = $this->calcularTotales($compra, $productos);

        $data = [
            'compra' => $compra,
            'empresa' => $empresa,
            'logoPath' => PdfService::getLogoPath($empresa->logo),
            'tipoDocumentoTitulo' => 'VISTA PREVIA DE FACTURA DE COMPRAS',
            'numeroDocumento' => $this->formatNumeroDocumento($compra),
            'productos' => $productos,
            'calculos' => $calculos,
            'filas' => $this->prepararInfoProveedor($compra, $calculos),
            'observaciones' => $compra->descripcion ?: '- NINGUNA',
        ];

        $filename = "Compra-{$compra->serie}-{$compra->numero}.pdf";

        return PdfService::render('pdf.compra', $data, $filename);
    }

    private function obtenerCompra(string $compraId): Compra
    {
        return Compra::with([
            'user.empresa',
            'proveedor',
            'almacen',
            'productosPorAlmacen.productoAlmacen.producto.marca',
            'productosPorAlmacen.productoAlmacen.producto.unidadMedida',
            'productosPorAlmacen.unidadesDerivadas.unidadDerivadaInmutable',
            'pagosDeCompras',
        ])->findOrFail($compraId);
    }

    private function prepararProductos(Compra $compra): array
    {
        $productos = [];

        foreach ($compra->productosPorAlmacen as $pa) {
            $producto = $pa->productoAlmacen->producto;
            $costo = (float) $pa->costo;

            foreach ($pa->unidadesDerivadas as $ud) {
                $cantidad = (float) $ud->cantidad;
                $factor = (float) $ud->factor;
                $subtotal = $cantidad * $factor * $costo;

                $productos[] = [
                    'codigo' => $producto->cod_producto ?? '',
                    'nombre' => $producto->name,
                    'marca' => $producto->marca->name ?? '',
                    'unidad' => $ud->unidadDerivadaInmutable->name ?? '',
                    'cantidad' => $cantidad,
                    'costo' => $costo,
                    'subtotal' => $subtotal,
                ];
            }
        }

        return $productos;
    }

    private function calcularTotales(Compra $compra, array $productos): array
    {
        $subtotalBruto = array_sum(array_column($productos, 'subtotal'));
        $subtotal = $subtotalBruto / 1.18;
        $igv = $subtotalBruto - $subtotal;

        $totalPagado = $compra->pagosDeCompras
            ->sum(fn ($pago) => (float) $pago->monto);

        $deuda = $subtotalBruto - $totalPagado;

        return [
            'subtotal_bruto' => $subtotalBruto,
            'subtotal' => $subtotal,
            'igv' => $igv,
            'total_pagado' => $totalPagado,
            'deuda' => $deuda,
        ];
    }

    private function prepararInfoProveedor(Compra $compra, array $calculos): array
    {
        $proveedor = $compra->proveedor;
        $fecha = $compra->fecha;

        return [
            [
                'TIPO DOC' => $compra->tipo_documento->value ?? '',
                'FORMA PAGO' => $compra->forma_de_pago->value ?? '',
            ],
            [
                'PROVEEDOR' => ($proveedor?->ruc ?? '') . ' ' . ($proveedor?->razon_social ?? ''),
                'TOTAL PAGADO' => number_format($calculos['total_pagado'], 2),
            ],
            [
                'FECHA' => PdfService::formatFecha($fecha) . ' ' . PdfService::formatFecha($fecha, 'h:i:s A'),
                'DEUDA' => number_format($calculos['deuda'], 2),
            ],
            [
                'ALMACEN' => $compra->almacen->name ?? 'N/A',
                'USUARIO' => $compra->user->name ?? 'N/A',
            ],
        ];
    }

    private function formatNumeroDocumento(Compra $compra): string
    {
        $serie = $compra->serie ?: '001';
        $numero = str_pad($compra->numero ?? '0', 6, '0', STR_PAD_LEFT);

        return "{$serie}-{$numero}";
    }
}
