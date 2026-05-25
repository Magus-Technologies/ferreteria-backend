<?php

namespace App\Services\Pdf;

use App\Models\Prestamo;
use App\Services\Pdf\Traits\ResuelveEstilosPlantilla;
use Illuminate\Http\Response;

class PrestamoPdfService
{
    use ResuelveEstilosPlantilla;

    public function generar(string $prestamoId, string $formato = 'a4', ?array $columnas = null): Response
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

        if ($formato === 'ticket') {
            return $this->generarTicket($prestamo, $empresa, $productos, $total, $tipoOperacion, $monedaSymbol, $monedaNombre, $columnas);
        }

        // Totales: se incluyen según los extras seleccionados (si no se pasa nada, todos)
        $totalesDisponibles = [
            'monto_total' => ['label' => 'MONTO TOTAL', 'valor' => $monedaSymbol . ' ' . number_format($total, 2)],
            'monto_pagado' => ['label' => 'MONTO PAGADO', 'valor' => $monedaSymbol . ' ' . number_format((float) ($prestamo->monto_pagado ?? 0), 2)],
            'saldo_pendiente' => ['label' => 'SALDO PENDIENTE', 'valor' => $monedaSymbol . ' ' . number_format((float) ($prestamo->monto_pendiente ?? 0), 2)],
        ];
        $totales = [];
        foreach ($totalesDisponibles as $key => $fila) {
            if ($columnas === null || in_array($key, $columnas, true)) {
                $totales[] = $fila;
            }
        }

        $estilos = $this->prepararDatosPlantilla((int) $empresa->id, 'prestamo', 'A4');

        $data = array_merge($estilos, [
            'prestamo' => $prestamo,
            'empresa' => $empresa,
            'logoPath' => PdfService::getLogoPath($empresa->logo),
            'tipoDocumentoTitulo' => $tipoOperacion,
            'numeroDocumento' => $prestamo->numero,
            'productos' => $productos,
            'filas' => $this->prepararInfoEntidad($prestamo, $monedaNombre),
            'observaciones' => $this->prepararObservaciones($prestamo),
            'totales' => $totales,
            'son' => PdfService::numeroALetras($total),
            'moneda' => $monedaNombre,
            'tipoOperacion' => $tipoOperacion,
            'columnas' => $columnas, // Soporte para columnas seleccionables
        ]);

        $filename = "PRESTAMO-{$prestamo->numero}.pdf";

        return PdfService::render('pdf.prestamo', $data, $filename);
    }

    private function generarTicket($prestamo, $empresa, array $productos, float $total, string $tipoOperacion, string $monedaSymbol, string $monedaNombre, ?array $columnas = null): Response
    {
        $esCliente = $prestamo->tipo_entidad === 'CLIENTE';
        $entidad = $esCliente ? $prestamo->cliente : $prestamo->proveedor;

        $entidadNombre = $entidad?->razon_social
            ?: trim(($entidad?->nombres ?? '') . ' ' . ($entidad?->apellidos ?? ''))
            ?: 'ENTIDAD GENERAL';

        $estilos = $this->prepararDatosPlantilla((int) $empresa->id, 'prestamo', 'Ticket');

        $data = array_merge($estilos, [
            'titulo' => "Ticket {$prestamo->numero}",
            'empresa' => $empresa,
            'logoPath' => PdfService::getLogoPath($empresa->logo),
            'tipoOperacion' => $tipoOperacion,
            'numeroDocumento' => $prestamo->numero,
            'fechaEmision' => PdfService::formatFecha($prestamo->fecha),
            'hora' => PdfService::formatFecha($prestamo->fecha, 'H:i:s'),
            'fechaVencimiento' => PdfService::formatFecha($prestamo->fecha_vencimiento),
            'vendedor' => $prestamo->vendedor ?: $prestamo->user->name ?? '',
            'estado' => strtoupper($prestamo->estado_prestamo ?? ''),
            'monedaNombre' => $monedaNombre,
            'monedaSymbol' => $monedaSymbol,
            'tasaInteres' => $prestamo->tasa_interes
                ? $prestamo->tasa_interes . '% ' . ($prestamo->tipo_interes ?? '')
                : null,
            'esCliente' => $esCliente,
            'entidadNombre' => $entidadNombre,
            'entidadDocumento' => $prestamo->ruc_dni ?: $entidad?->numero_documento ?? '',
            'entidadDireccion' => $prestamo->direccion ?: $entidad?->direccion ?? '',
            'productos' => $productos,
            'montoTotal' => $total,
            'montoPagado' => (float) ($prestamo->monto_pagado ?? 0),
            'montoPendiente' => (float) ($prestamo->monto_pendiente ?? 0),
            'observaciones' => $prestamo->observaciones ?: '- NINGUNA',
            'garantia' => $prestamo->garantia,
            'columnas' => $columnas, // Soporte para columnas seleccionables
        ]);

        $filename = "TICKET-PRESTAMO-{$prestamo->numero}.pdf";

        return PdfService::render(
            'pdf.prestamo-ticket',
            $data,
            $filename,
            'portrait',
            [0, 0, 226.77, 841.89],
        );
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
