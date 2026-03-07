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
    public function generar(string $ventaId, string $formato = 'a4'): Response
    {
        $venta = $this->obtenerVenta($ventaId);
        $empresa = $venta->user->empresa;

        $productos = $this->prepararProductos($venta);
        $calculos = $this->calcularTotales($productos);

        if ($formato === 'ticket') {
            return $this->generarTicket($venta, $empresa, $productos, $calculos);
        }

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

    private function generarTicket($venta, $empresa, array $productos, array $calculos): Response
    {
        $cliente = $venta->cliente;
        $clienteNombre = $cliente?->razon_social
            ?: trim(($cliente?->nombres ?? '') . ' ' . ($cliente?->apellidos ?? ''))
            ?: 'CLIENTES VARIOS';

        $formaPago = $venta->forma_de_pago->value ?? '';
        $esCredito = stripos($formaPago, 'Credito') !== false || stripos($formaPago, 'Crédito') !== false;

        // Metodos de pago
        $metodosPago = [];
        foreach ($venta->despliegueDePagoVentas as $dp) {
            $metodosPago[] = [
                'nombre' => $dp->despliegueDePago->name ?? '',
                'monto' => (float) $dp->monto,
            ];
        }

        // Vales aplicados
        $vales = [];
        foreach ($venta->valesAplicados as $va) {
            if (!$va->genera_vale_futuro || !$va->codigo_vale_generado) {
                continue;
            }
            $valeCompra = $va->valeCompra;
            $tipoLabel = match ($valeCompra?->tipo_promocion) {
                'descuento_porcentaje' => 'DESCUENTO %',
                'descuento_fijo' => 'DESCUENTO FIJO',
                'producto_gratis' => 'PRODUCTO GRATIS',
                default => strtoupper($valeCompra?->tipo_promocion ?? ''),
            };
            $beneficio = match ($valeCompra?->descuento_tipo) {
                '%' => ($valeCompra?->descuento_valor ?? 0) . '% DESCUENTO',
                default => 'S/ ' . number_format($valeCompra?->descuento_valor ?? 0, 2) . ' DESCUENTO',
            };

            $vales[] = [
                'tipo_label' => $tipoLabel,
                'nombre' => $valeCompra?->nombre ?? '',
                'beneficio' => $beneficio,
                'codigo' => $va->codigo_vale_generado,
                'fecha_validez' => $va->fecha_validez_generado
                    ? \Carbon\Carbon::parse($va->fecha_validez_generado)->format('d/m/Y')
                    : null,
            ];
        }

        $fecha = $venta->fecha;

        $data = [
            'titulo' => "Ticket {$this->formatNumeroDocumento($venta)}",
            'empresa' => $empresa,
            'logoPath' => PdfService::getLogoPath($empresa->logo),
            'tipoDocumentoTitulo' => $this->getTituloDocumento($venta->tipo_documento->value),
            'numeroDocumento' => $this->formatNumeroDocumento($venta),
            'formaPago' => $formaPago,
            'esCredito' => $esCredito,
            'fechaEmision' => PdfService::formatFecha($fecha),
            'hora' => PdfService::formatFecha($fecha, 'H:i:s'),
            'fechaVencimiento' => $venta->fecha_vencimiento
                ? PdfService::formatFecha($venta->fecha_vencimiento)
                : '',
            'numeroGuia' => $venta->numero_guia ?? '',
            'vendedor' => $venta->user->name,
            'clienteNombre' => $clienteNombre,
            'clienteDocumento' => $cliente?->numero_documento ?? '99999999',
            'clienteDireccion' => $cliente?->direccion ?? '',
            'metodosPago' => $metodosPago,
            'productos' => $productos,
            'calculos' => $calculos,
            'son' => PdfService::numeroALetras($calculos['total']),
            'observaciones' => $venta->descripcion ?: '- NINGUNA',
            'vales' => $vales,
        ];

        $filename = "TICKET-{$venta->serie}-{$venta->numero}.pdf";

        return PdfService::render(
            'pdf.venta-ticket',
            $data,
            $filename,
            'portrait',
            [0, 0, 226.77, 841.89],
        );
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
            'despliegueDePagoVentas.despliegueDePago',
            'valesAplicados.valeCompra',
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
            '01' => 'FACTURA ELECTRONICA',
            '03' => 'BOLETA DE VENTA',
            'nv' => 'NOTA DE VENTA',
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
