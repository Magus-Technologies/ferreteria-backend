<?php

namespace App\Services\Pdf;

use App\Models\ComprobanteElectronico;
use App\Models\Venta;
use Illuminate\Http\Response;

class VentaPdfService
{
    /**
     * Generar PDF de una venta.
     */
    public function generar(string $ventaId): Response
    {
        $venta = $this->obtenerVenta($ventaId);
        $empresa = $venta->user->empresa;

        $productos = $this->prepararProductos($venta);
        $calculos = $this->calcularTotales($productos);
        $codigoQr = $this->obtenerCodigoQr($venta);

        $data = [
            'venta' => $venta,
            'empresa' => $empresa,
            'logoPath' => PdfService::getLogoPath($empresa->logo),
            'tipoDocumentoTitulo' => $this->getTituloDocumento($venta->tipo_documento->value),
            'numeroDocumento' => $this->formatNumeroDocumento($venta),
            'productos' => $productos,
            'calculos' => $calculos,
            'codigoQr' => $codigoQr,
            'filas' => $this->prepararInfoCliente($venta),
            'son' => PdfService::numeroALetras($calculos['total']),
            'observaciones' => $venta->descripcion ?: '- NINGUNA',
        ];

        $filename = "{$venta->tipo_documento->value}-{$venta->serie}-{$venta->numero}.pdf";

        return PdfService::render('pdf.venta', $data, $filename);
    }

    /**
     * Obtener la venta con todas sus relaciones necesarias.
     */
    private function obtenerVenta(string $ventaId): Venta
    {
        return Venta::with([
            'user.empresa',
            'cliente',
            'almacen',
            'productosPorAlmacen.productoAlmacen.producto.marca',
            'productosPorAlmacen.productoAlmacen.producto.unidadMedida',
            'productosPorAlmacen.unidadesDerivadas.unidadDerivadaInmutable',
        ])->findOrFail($ventaId);
    }

    /**
     * Preparar la lista de productos para la tabla.
     */
    private function prepararProductos(Venta $venta): array
    {
        $productos = [];

        foreach ($venta->productosPorAlmacen as $pa) {
            $producto = $pa->productoAlmacen->producto;

            foreach ($pa->unidadesDerivadas as $ud) {
                $cantidad = (float) $ud->cantidad;
                $factor = (float) $ud->factor;
                $precio = (float) $ud->precio;
                $descuento = (float) ($ud->descuento ?? 0);
                $subtotal = $cantidad * $factor * $precio;

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

    /**
     * Calcular subtotal, IGV y total.
     */
    private function calcularTotales(array $productos): array
    {
        $subtotal = array_sum(array_column($productos, 'subtotal'));
        $totalDescuento = array_sum(array_column($productos, 'descuento'));
        $base = $subtotal - $totalDescuento;
        $igv = $base * 0.18;
        $total = $base + $igv;

        return [
            'subtotal' => $base,
            'igv' => $igv,
            'total' => $total,
            'total_descuento' => $totalDescuento,
        ];
    }

    /**
     * Obtener el codigo QR del comprobante electronico si existe.
     */
    private function obtenerCodigoQr(Venta $venta): ?string
    {
        if (!$venta->serie || !$venta->numero) {
            return null;
        }

        $comprobante = ComprobanteElectronico::where('serie', $venta->serie)
            ->where('correlativo', $venta->numero)
            ->first();

        return $comprobante?->codigo_qr;
    }

    /**
     * Preparar las filas de informacion del cliente.
     */
    private function prepararInfoCliente(Venta $venta): array
    {
        $cliente = $venta->cliente;
        $clienteNombre = $cliente?->razon_social
            ?: trim(($cliente?->nombres ?? '') . ' ' . ($cliente?->apellidos ?? ''))
            ?: 'CLIENTES VARIOS';

        $fecha = $venta->fecha;

        return [
            [
                'Cliente' => $clienteNombre,
                'F. Emision' => PdfService::formatFecha($fecha),
            ],
            [
                'Direccion' => $cliente?->direccion ?? '',
                'Hora' => PdfService::formatFecha($fecha, 'H:i:s'),
            ],
            [
                'RUC / DNI' => $cliente?->numero_documento ?? '',
                'Tipo Doc.' => $venta->tipo_documento->value,
            ],
            [
                'Vendedor' => $venta->user->name,
                'Almacen' => $venta->almacen->name,
            ],
            [
                'Forma Pago' => $venta->forma_de_pago->value ?? '',
                'Moneda' => 'SOLES',
            ],
            [
                'Cajero' => $venta->user->name,
                'Estado' => $venta->estado_de_venta->value ?? '',
            ],
        ];
    }

    /**
     * Obtener el titulo segun el tipo de documento.
     */
    private function getTituloDocumento(string $tipo): string
    {
        return match ($tipo) {
            'Factura' => 'FACTURA ELECTRONICA',
            'Boleta' => 'BOLETA DE VENTA',
            'NotaDeVenta' => 'NOTA DE VENTA',
            default => strtoupper($tipo),
        };
    }

    /**
     * Formatear el numero de documento (serie-numero).
     */
    private function formatNumeroDocumento(Venta $venta): string
    {
        $serie = $venta->serie ?: 'S/N';
        $numero = str_pad($venta->numero ?? '0', 8, '0', STR_PAD_LEFT);

        return "{$serie}-{$numero}";
    }
}
