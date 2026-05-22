<?php

namespace App\Services\Pdf;

use App\Models\IngresoSalida;
use App\Services\Pdf\Traits\ResuelveEstilosPlantilla;
use Illuminate\Http\Response;

class IngresoSalidaPdfService
{
    use ResuelveEstilosPlantilla;

    public function generar(int $id, string $formato = 'a4'): Response
    {
        $ingreso = $this->obtenerIngresoSalida($id);
        $empresa = $ingreso->user->empresa;

        $tipoDoc = $ingreso->tipo_documento->value === 'in' ? 'NOTA DE INGRESO' : 'NOTA DE SALIDA';
        $nroDoc = $this->formatNroDoc($ingreso);
        $productos = $this->prepararProductos($ingreso);
        $total = $this->calcularTotal($productos);

        if ($formato === 'ticket') {
            return $this->generarTicket($ingreso, $empresa, $tipoDoc, $nroDoc, $productos, $total);
        }

        $estilos = $this->prepararDatosPlantilla((int) $empresa->id, 'ingreso-salida', 'A4');

        $data = array_merge([
            'ingreso' => $ingreso,
            'empresa' => $empresa,
            'logoPath' => PdfService::getLogoPath($empresa->logo),
            'tipoDocumentoTitulo' => $tipoDoc . ' ELECTRÓNICA',
            'numeroDocumento' => $nroDoc,
            'productos' => $productos,
            'total' => $total,
            'filas' => $this->prepararInfoGeneral($ingreso, $tipoDoc),
            'son' => PdfService::numeroALetras($total),
            'observaciones' => $ingreso->descripcion ?: ($estilos['msg']['observaciones_default'] ?? '- NINGUNA'),
        ], $estilos);

        return PdfService::render('pdf.ingreso-salida', $data, "IS-{$nroDoc}.pdf");
    }

    private function generarTicket($ingreso, $empresa, string $tipoDoc, string $nroDoc, array $productos, float $total): Response
    {
        $estilos = $this->prepararDatosPlantilla((int) $empresa->id, 'ingreso-salida', 'Ticket');

        $data = array_merge([
            'ingreso' => $ingreso,
            'empresa' => $empresa,
            'logoPath' => PdfService::getLogoPath($empresa->logo),
            'tipoDoc' => $tipoDoc . ' ELECTRÓNICA',
            'nroDoc' => $nroDoc,
            'productos' => $productos,
            'total' => $total,
            'son' => PdfService::numeroALetras($total),
        ], $estilos);

        return PdfService::render(
            'pdf.ingreso-salida-ticket',
            $data,
            "TICKET-IS-{$nroDoc}.pdf",
            'portrait',
            [0, 0, 226.77, 841.89],
        );
    }

    private function obtenerIngresoSalida(int $id): IngresoSalida
    {
        return IngresoSalida::with([
            'user.empresa',
            'almacen',
            'tipoIngreso',
            'proveedor',
            'productosPorAlmacen.productoAlmacen.producto',
            'productosPorAlmacen.unidadesDerivadas.unidadDerivadaInmutable',
            'productosPorAlmacen.unidadesDerivadas.historial',
        ])->findOrFail($id);
    }

    private function formatNroDoc(IngresoSalida $ingreso): string
    {
        $prefix = $ingreso->tipo_documento->value === 'in' ? 'NI' : 'NS';
        $serie = str_pad($ingreso->serie ?? '0', 4, '0', STR_PAD_LEFT);
        $numero = str_pad($ingreso->numero ?? '0', 8, '0', STR_PAD_LEFT);
        return "{$prefix}{$serie}-{$numero}";
    }

    private function prepararProductos(IngresoSalida $ingreso): array
    {
        $productos = [];
        $esSalida = $ingreso->tipo_documento->value !== 'in';

        foreach ($ingreso->productosPorAlmacen as $pa) {
            $producto = $pa->productoAlmacen->producto;
            $costo = (float) $pa->costo;

            foreach ($pa->unidadesDerivadas as $ud) {
                $cantidad = (float) $ud->cantidad;
                $factor = (float) $ud->factor;
                $costoTotal = $cantidad * $costo * $factor;

                // Obtener historial correcto
                $historial = $this->getHistorial($ud->historial, $ingreso->estado, $esSalida);

                $unidadesContenidas = (float) ($producto->unidades_contenidas ?? 1);

                $productos[] = [
                    'codigo' => $producto->cod_producto ?? '',
                    'nombre' => $producto->name ?? '',
                    'cantidad' => $cantidad,
                    'unidad' => $ud->unidadDerivadaInmutable->name ?? '',
                    'stock_anterior' => (float) ($historial['stock_anterior'] ?? 0),
                    'stock_nuevo' => (float) ($historial['stock_nuevo'] ?? 0),
                    'stock_anterior_f' => self::formatStockF((float) ($historial['stock_anterior'] ?? 0), $unidadesContenidas),
                    'stock_nuevo_f' => self::formatStockF((float) ($historial['stock_nuevo'] ?? 0), $unidadesContenidas),
                    'costo' => $costo * $factor,
                    'costo_total' => $costoTotal,
                    'unidades_contenidas' => $unidadesContenidas,
                ];
            }
        }

        return $productos;
    }

    private function getHistorial($historial, bool $estado, bool $esSalida): array
    {
        if (!$historial || $historial->isEmpty()) {
            return ['stock_anterior' => 0, 'stock_nuevo' => 0];
        }

        $first = $historial->first();
        $stockAnterior = (float) $first->stock_anterior;
        $stockNuevo = (float) $first->stock_nuevo;
        $index = 0;

        if (!$esSalida) {
            if (($estado && $stockAnterior > $stockNuevo) || (!$estado && $stockAnterior < $stockNuevo)) {
                $index = 1;
            }
        } else {
            if (($estado && $stockAnterior < $stockNuevo) || (!$estado && $stockAnterior > $stockNuevo)) {
                $index = 1;
            }
        }

        $h = $historial->get($index) ?? $first;
        return ['stock_anterior' => (float) $h->stock_anterior, 'stock_nuevo' => (float) $h->stock_nuevo];
    }

    /**
     * Formatea stock como "XFY" (ej: 5F, 5F2, 0F3)
     */
    public static function formatStockF(float $stockFraccion, float $unidadesContenidas): string
    {
        $uc = $unidadesContenidas != 0 ? $unidadesContenidas : 1;
        $unidadesCompletas = intdiv((int) $stockFraccion, (int) $uc);
        $fraccion = abs($stockFraccion) % (int) $uc;
        $negative = $stockFraccion < 0 && $unidadesCompletas == 0 ? '-' : '';
        $fraccionStr = $fraccion != 0 ? $fraccion : '';

        return "{$negative}{$unidadesCompletas}F{$fraccionStr}";
    }

    private function calcularTotal(array $productos): float
    {
        return array_sum(array_column($productos, 'costo_total'));
    }

    private function prepararInfoGeneral(IngresoSalida $ingreso, string $tipoDoc): array
    {
        $tipoLabel = str_contains($tipoDoc, 'INGRESO') ? 'Ingreso' : 'Salida';

        return [
            [
                'Fecha de Emisión' => PdfService::formatFecha($ingreso->fecha),
                'Proveedor' => $ingreso->proveedor?->razon_social ?? '—',
            ],
            [
                'Almacén' => $ingreso->almacen->name ?? '—',
                'Tipo de ' . $tipoLabel => $ingreso->tipoIngreso->name ?? '—',
            ],
            [
                'Usuario' => $ingreso->user->name ?? '—',
                'Observaciones' => $ingreso->descripcion ?? '—',
            ],
        ];
    }
}
