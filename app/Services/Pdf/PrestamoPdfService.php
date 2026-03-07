<?php

namespace App\Services\Pdf;

use App\Models\Prestamo;
use Illuminate\Http\Response;

class PrestamoPdfService
{
    public function generar(string $prestamoId): Response
    {
        $prestamo = $this->obtenerPrestamo($prestamoId);
        $empresa = $prestamo->user->empresa;

        $productos = $this->prepararProductos($prestamo);
        $total = (float) ($prestamo->monto_total ?: array_sum(array_column($productos, 'subtotal')));

        $tipoOperacion = $prestamo->tipo_operacion === 'PRESTAR'
            ? 'PRESTAMO'
            : 'PRESTAMO RECIBIDO';

        $monedaSymbol = $prestamo->tipo_moneda === 'd' ? '$' : 'S/.';
        $monedaNombre = $prestamo->tipo_moneda === 'd' ? 'USD' : 'SOL';

        $data = [
            'prestamo' => $prestamo,
            'empresa' => $empresa,
            'logoPath' => PdfService::getLogoPath($empresa->logo),
            'tipoDocumentoTitulo' => $tipoOperacion,
            'numeroDocumento' => $prestamo->numero,
            'productos' => $productos,
            'filas' => $this->prepararInfoEntidad($prestamo, $monedaNombre),
            'observaciones' => $this->prepararObservaciones($prestamo),
            'totales' => [
                ['label' => 'MONTO TOTAL', 'valor' => $monedaSymbol . ' ' . number_format($total, 2)],
                ['label' => 'MONTO PAGADO', 'valor' => $monedaSymbol . ' ' . number_format((float) ($prestamo->monto_pagado ?? 0), 2)],
                ['label' => 'SALDO PENDIENTE', 'valor' => $monedaSymbol . ' ' . number_format((float) ($prestamo->monto_pendiente ?? 0), 2)],
            ],
            'son' => PdfService::numeroALetras($total),
            'moneda' => $monedaNombre,
            'tipoOperacion' => $tipoOperacion,
        ];

        $filename = "PRESTAMO-{$prestamo->numero}.pdf";

        return PdfService::render('pdf.prestamo', $data, $filename);
    }

    private function obtenerPrestamo(string $prestamoId): Prestamo
    {
        return Prestamo::with([
            'user.empresa',
            'cliente',
            'proveedor',
            'almacen',
            'productosPorAlmacen.productoAlmacen.producto.marca',
            'productosPorAlmacen.unidadesDerivadas',
        ])->findOrFail($prestamoId);
    }

    private function prepararProductos(Prestamo $prestamo): array
    {
        $productos = [];

        foreach ($prestamo->productosPorAlmacen as $pa) {
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
                    'unidad' => $ud->name ?? '',
                    'cantidad' => $cantidad,
                    'costo' => $costo,
                    'subtotal' => $subtotal,
                ];
            }
        }

        return $productos;
    }

    private function prepararInfoEntidad(Prestamo $prestamo, string $moneda): array
    {
        $esCliente = $prestamo->tipo_entidad === 'CLIENTE';
        $entidad = $esCliente ? $prestamo->cliente : $prestamo->proveedor;

        $entidadNombre = $entidad?->razon_social
            ?: trim(($entidad?->nombres ?? '') . ' ' . ($entidad?->apellidos ?? ''))
            ?: 'ENTIDAD GENERAL';

        $direccion = $prestamo->direccion
            ?: $entidad?->direccion ?? '';

        $documento = $prestamo->ruc_dni
            ?: $entidad?->numero_documento ?? '';

        $telefono = $prestamo->telefono
            ?: $entidad?->telefono ?? '';

        $filas = [
            [
                ($esCliente ? 'Cliente' : 'Proveedor') => $entidadNombre,
                'F. Emision' => PdfService::formatFecha($prestamo->fecha),
            ],
            [
                'Direccion' => $direccion,
                'Hora' => PdfService::formatFecha($prestamo->fecha, 'H:i:s'),
            ],
            [
                'RUC / DNI' => $documento,
                'F. Vencimiento' => PdfService::formatFecha($prestamo->fecha_vencimiento),
            ],
            [
                'Vendedor' => $prestamo->vendedor ?: $prestamo->user->name ?? '',
                'Estado' => strtoupper($prestamo->estado_prestamo ?? ''),
            ],
            [
                'Telefono' => $telefono,
                'Moneda' => $moneda,
            ],
        ];

        if ($prestamo->tasa_interes) {
            $filas[] = [
                'Tasa Interes' => $prestamo->tasa_interes . '% ' . ($prestamo->tipo_interes ?? ''),
                'Dias Gracia' => ($prestamo->dias_gracia ?? 0) . ' dias',
            ];
        }

        return $filas;
    }

    private function prepararObservaciones(Prestamo $prestamo): string
    {
        $obs = $prestamo->observaciones ?: '- NO HAY OBSERVACIONES ADICIONALES';

        if ($prestamo->garantia) {
            $obs .= "\nGARANTIA: " . $prestamo->garantia;
        }

        return $obs;
    }
}
