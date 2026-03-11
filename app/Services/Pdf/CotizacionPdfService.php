<?php

namespace App\Services\Pdf;

use App\Models\Cotizacion;
use Illuminate\Http\Response;

class CotizacionPdfService
{
    public function generar(string $cotizacionId, string $formato = 'a4'): Response
    {
        $cotizacion = $this->obtenerCotizacion($cotizacionId);
        $empresa = $cotizacion->user->empresa;

        $productos = $this->prepararProductos($cotizacion);
        $calculos = $this->calcularTotales($productos);

        if ($formato === 'ticket') {
            return $this->generarTicket($cotizacion, $empresa, $productos, $calculos);
        }

        $data = [
            'cotizacion' => $cotizacion,
            'empresa' => $empresa,
            'logoPath' => PdfService::getLogoPath($empresa->logo),
            'tipoDocumentoTitulo' => 'Proforma',
            'numeroDocumento' => $cotizacion->numero,
            'productos' => $productos,
            'calculos' => $calculos,
            'filas' => $this->prepararInfoCliente($cotizacion),
            'son' => PdfService::numeroALetras($calculos['total']),
            'moneda' => $cotizacion->tipo_moneda?->value === 'Soles' ? 'SOL' : 'USD',
        ];

        $filename = "COTIZACION-{$cotizacion->numero}.pdf";

        return PdfService::render('pdf.cotizacion', $data, $filename);
    }

    private function generarTicket($cotizacion, $empresa, array $productos, array $calculos): Response
    {
        $cliente = $cotizacion->cliente;
        $clienteNombre = $cliente?->razon_social
            ?: trim(($cliente?->nombres ?? '') . ' ' . ($cliente?->apellidos ?? ''))
            ?: 'CLIENTE GENERAL';

        $data = [
            'titulo' => "Ticket {$cotizacion->numero}",
            'cotizacion' => $cotizacion,
            'empresa' => $empresa,
            'logoPath' => PdfService::getLogoPath($empresa->logo),
            'numeroDocumento' => $cotizacion->numero,
            'productos' => $productos,
            'calculos' => $calculos,
            'clienteNombre' => $clienteNombre,
            'clienteDocumento' => $cliente?->numero_documento ?? '99999999',
            'clienteDireccion' => $cliente?->direccion ?? '',
            'vendedor' => $cotizacion->user->name,
            'son' => PdfService::numeroALetras($calculos['total']),
        ];

        $filename = "TICKET-{$cotizacion->numero}.pdf";

        // 80mm = 226.77pt
        return PdfService::render(
            'pdf.cotizacion-ticket',
            $data,
            $filename,
            'portrait',
            [0, 0, 226.77, 841.89],
        );
    }

    private function obtenerCotizacion(string $cotizacionId): Cotizacion
    {
        return Cotizacion::with([
            'user.empresa',
            'cliente',
            'almacen',
            'productosPorAlmacen.productoAlmacen.producto.marca',
            'productosPorAlmacen.unidadesDerivadas.unidadDerivadaInmutable',
        ])->findOrFail($cotizacionId);
    }

    private function prepararProductos(Cotizacion $cotizacion): array
    {
        $productos = [];

        foreach ($cotizacion->productosPorAlmacen as $pa) {
            $producto = $pa->productoAlmacen->producto;

            foreach ($pa->unidadesDerivadas as $ud) {
                $cantidad = (float) $ud->cantidad;
                $precio = (float) ($ud->precio ?? 0);
                $recargo = (float) ($ud->recargo ?? 0);
                $descuento = (float) ($ud->descuento ?? 0);
                $subtotal = ($precio + $recargo) * $cantidad - $descuento;

                $productos[] = [
                    'codigo' => $producto->cod_producto ?? '',
                    'nombre' => $producto->name,
                    'marca' => $producto->marca->name ?? '',
                    'unidad' => $ud->unidadDerivadaInmutable->name ?? '',
                    'cantidad' => $cantidad,
                    'precio' => $precio,
                    'descuento' => $descuento,
                    'subtotal' => $subtotal,
                ];
            }
        }

        return $productos;
    }

    private function calcularTotales(array $productos): array
    {
        $subtotal = array_sum(array_column($productos, 'subtotal'));
        $totalDescuento = array_sum(array_column($productos, 'descuento'));
        $total = $subtotal - $totalDescuento;

        return [
            'subtotal' => $subtotal,
            'total_descuento' => $totalDescuento,
            'total' => $total,
        ];
    }

    private function prepararInfoCliente(Cotizacion $cotizacion): array
    {
        $cliente = $cotizacion->cliente;
        $clienteNombre = $cliente?->razon_social
            ?: trim(($cliente?->nombres ?? '') . ' ' . ($cliente?->apellidos ?? ''))
            ?: 'CLIENTE GENERAL';

        $moneda = $cotizacion->tipo_moneda?->value === 'Soles' ? 'SOL' : 'USD';

        return [
            [
                'Cliente' => $clienteNombre,
                'F. Emision' => PdfService::formatFecha($cotizacion->fecha),
            ],
            [
                'Direccion' => $cliente?->direccion ?? '',
                'Hora' => PdfService::formatFecha($cotizacion->fecha, 'H:i:s'),
            ],
            [
                'RUC / DNI' => $cliente?->numero_documento ?? '',
                'F. Vencimiento' => PdfService::formatFecha($cotizacion->fecha_vencimiento),
            ],
            [
                'Vendedor' => $cotizacion->user->name,
                'N Guia' => '',
            ],
            [
                'Forma Pago' => 'CREDITO 7 DIAS',
                'Moneda' => $moneda,
            ],
            [
                'Cajero' => $cotizacion->user->name,
                'Orden de Compra' => '',
            ],
        ];
    }
}
