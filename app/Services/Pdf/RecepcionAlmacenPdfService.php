<?php

namespace App\Services\Pdf;

use App\Models\RecepcionAlmacen;
use Illuminate\Http\Response;

class RecepcionAlmacenPdfService
{
    public function generar(int $id, string $formato = 'a4'): Response
    {
        $recepcion = $this->obtenerRecepcion($id);
        $empresa = $recepcion->user->empresa;

        $nroDoc = $this->formatNroDoc($recepcion, $empresa);
        $productos = $this->prepararProductos($recepcion);
        $total = array_sum(array_column($productos, 'cantidad'));

        if ($formato === 'ticket') {
            return $this->generarTicket($recepcion, $empresa, $nroDoc, $productos, $total);
        }

        $src = $recepcion->compra ?? $recepcion->ordenCompra;

        $data = [
            'recepcion' => $recepcion,
            'empresa' => $empresa,
            'logoPath' => PdfService::getLogoPath($empresa->logo),
            'tipoDocumentoTitulo' => 'RECEPCIÓN DE ALMACÉN',
            'numeroDocumento' => $nroDoc,
            'productos' => $productos,
            'total' => $total,
            'filas' => $this->prepararInfoGeneral($recepcion, $src),
            'filasProveedor' => $this->prepararInfoProveedor($recepcion, $src),
            'filasTransportista' => $this->prepararInfoTransportista($recepcion),
            'observaciones' => $recepcion->observaciones ?: '- NINGUNA',
        ];

        return PdfService::render('pdf.recepcion-almacen', $data, "RA-{$nroDoc}.pdf");
    }

    private function generarTicket($recepcion, $empresa, string $nroDoc, array $productos, float $total): Response
    {
        $src = $recepcion->compra ?? $recepcion->ordenCompra;

        $data = [
            'recepcion' => $recepcion,
            'empresa' => $empresa,
            'logoPath' => PdfService::getLogoPath($empresa->logo),
            'nroDoc' => $nroDoc,
            'productos' => $productos,
            'total' => $total,
            'src' => $src,
        ];

        return PdfService::render(
            'pdf.recepcion-almacen-ticket',
            $data,
            "TICKET-RA-{$nroDoc}.pdf",
            'portrait',
            [0, 0, 226.77, 841.89],
        );
    }

    private function obtenerRecepcion(int $id): RecepcionAlmacen
    {
        return RecepcionAlmacen::with([
            'user.empresa',
            'compra.proveedor',
            'compra.almacen',
            'ordenCompra.proveedor',
            'ordenCompra.almacen',
            'productosPorAlmacen.productoAlmacen.producto.marca',
            'productosPorAlmacen.unidadesDerivadas.unidadDerivadaInmutable',
            'productosPorAlmacen.unidadesDerivadas.historial',
        ])->findOrFail($id);
    }

    private function formatNroDoc(RecepcionAlmacen $recepcion, $empresa): string
    {
        $serie = str_pad($empresa->serie_recepcion_almacen ?? '0', 4, '0', STR_PAD_LEFT);
        $numero = str_pad($recepcion->numero ?? '0', 8, '0', STR_PAD_LEFT);
        return "RA{$serie}-{$numero}";
    }

    private function prepararProductos(RecepcionAlmacen $recepcion): array
    {
        $productos = [];

        foreach ($recepcion->productosPorAlmacen as $pa) {
            $producto = $pa->productoAlmacen->producto;

            foreach ($pa->unidadesDerivadas as $ud) {
                // Historial
                $historial = $this->getHistorial($ud->historial, $recepcion->estado);

                $unidadesContenidas = (float) ($producto->unidades_contenidas ?? 1);

                $productos[] = [
                    'codigo' => $producto->cod_producto ?? '',
                    'nombre' => $producto->name ?? '',
                    'marca' => $producto->marca->name ?? '',
                    'unidad' => $ud->unidadDerivadaInmutable->name ?? '',
                    'cantidad' => (float) $ud->cantidad,
                    'stock_anterior' => (float) ($historial['stock_anterior'] ?? 0),
                    'stock_nuevo' => (float) ($historial['stock_nuevo'] ?? 0),
                    'stock_anterior_f' => IngresoSalidaPdfService::formatStockF((float) ($historial['stock_anterior'] ?? 0), $unidadesContenidas),
                    'stock_nuevo_f' => IngresoSalidaPdfService::formatStockF((float) ($historial['stock_nuevo'] ?? 0), $unidadesContenidas),
                    'bonificacion' => (bool) ($ud->bonificacion ?? false),
                    'vencimiento' => $ud->vencimiento ?? '',
                    'lote' => $ud->lote ?? '',
                    'unidades_contenidas' => $unidadesContenidas,
                ];
            }
        }

        return $productos;
    }

    private function getHistorial($historial, bool $estado): array
    {
        if (!$historial || $historial->isEmpty()) {
            return ['stock_anterior' => 0, 'stock_nuevo' => 0];
        }

        $first = $historial->first();
        $stockAnterior = (float) $first->stock_anterior;
        $stockNuevo = (float) $first->stock_nuevo;
        $index = 0;

        if (($estado && $stockAnterior > $stockNuevo) || (!$estado && $stockAnterior < $stockNuevo)) {
            $index = 1;
        }

        $h = $historial->get($index) ?? $first;
        return ['stock_anterior' => (float) $h->stock_anterior, 'stock_nuevo' => (float) $h->stock_nuevo];
    }

    private function prepararInfoGeneral(RecepcionAlmacen $recepcion, $src): array
    {
        return [
            [
                'Fecha de Recepción' => PdfService::formatFecha($recepcion->fecha),
                'Fecha de Compra' => PdfService::formatFecha($src?->fecha),
            ],
            [
                'Almacén' => $src?->almacen?->name ?? '—',
                'Usuario' => $recepcion->user->name ?? '—',
            ],
        ];
    }

    private function prepararInfoProveedor(RecepcionAlmacen $recepcion, $src): array
    {
        $nroDocCompra = '—';
        if ($recepcion->compra) {
            $serie = $recepcion->compra->serie ?? '';
            $numero = $recepcion->compra->numero ?? '';
            $nroDocCompra = $serie && $numero ? "{$serie}-{$numero}" : ($recepcion->compra->codigo ?? '—');
        } elseif ($recepcion->ordenCompra) {
            $nroDocCompra = $recepcion->ordenCompra->codigo ?? '—';
        }

        return [
            [
                'RUC' => $src?->proveedor?->ruc ?? '—',
                'Razón Social' => $src?->proveedor?->razon_social ?? '—',
            ],
            [
                'Documento' => $nroDocCompra,
                'Guía Remisión' => $src?->guia ?? '—',
            ],
        ];
    }

    private function prepararInfoTransportista(RecepcionAlmacen $recepcion): array
    {
        return [
            [
                'RUC' => $recepcion->transportista_ruc ?? '—',
                'Razón Social' => $recepcion->transportista_razon_social ?? '—',
            ],
            [
                'Placa Vehicular' => $recepcion->transportista_placa ?? '—',
                'Licencia' => $recepcion->transportista_licencia ?? '—',
            ],
            [
                'Nombres y Apellidos' => $recepcion->transportista_name ?? '—',
                'Guía Rem. Transportista' => $recepcion->transportista_guia_remision ?? '—',
            ],
        ];
    }
}
