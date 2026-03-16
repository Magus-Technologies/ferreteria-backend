<?php

namespace App\Services\Pdf;

use App\Models\TransferenciaStock;
use Illuminate\Http\Response;

class TransferenciaStockPdfService
{
    public function generar(int $id, string $formato = 'ticket'): Response
    {
        $transferencia = $this->obtenerTransferencia($id);
        $empresa = $transferencia->user->empresa;

        $tipoDoc = 'TRANSFERENCIA DE STOCK';
        $nroDoc = $this->formatNroDoc($transferencia);
        $productos = $this->prepararProductos($transferencia);
        $total = $this->calcularTotal($productos);

        $data = [
            'transferencia' => $transferencia,
            'empresa' => $empresa,
            'logoPath' => PdfService::getLogoPath($empresa->logo),
            'tipoDoc' => $tipoDoc,
            'nroDoc' => $nroDoc,
            'productos' => $productos,
            'total' => $total,
            'son' => PdfService::numeroALetras($total),
        ];

        if ($formato === 'ticket') {
            return PdfService::render(
                'pdf.transferencia-stock-ticket',
                $data,
                "TICKET-TS-{$nroDoc}.pdf",
                'portrait',
                [0, 0, 226.77, 841.89],
            );
        }

        $data['filas'] = $this->prepararInfoGeneral($transferencia);
        $data['tipoDocumentoTitulo'] = $tipoDoc;
        $data['numeroDocumento'] = $nroDoc;
        $data['observaciones'] = $transferencia->descripcion ?: '- NINGUNA';

        return PdfService::render('pdf.transferencia-stock', $data, "TS-{$nroDoc}.pdf");
    }

    private function obtenerTransferencia(int $id): TransferenciaStock
    {
        return TransferenciaStock::with([
            'user.empresa',
            'almacenOrigen',
            'almacenDestino',
            'productos.productoAlmacenOrigen.producto',
            'productos.unidadDerivadaInmutable',
        ])->findOrFail($id);
    }

    private function formatNroDoc(TransferenciaStock $t): string
    {
        $serie = str_pad($t->serie ?? '0', 4, '0', STR_PAD_LEFT);
        $numero = str_pad($t->numero ?? '0', 8, '0', STR_PAD_LEFT);
        return "TS{$serie}-{$numero}";
    }

    private function prepararProductos(TransferenciaStock $transferencia): array
    {
        $productos = [];

        foreach ($transferencia->productos as $detalle) {
            $producto = $detalle->productoAlmacenOrigen->producto;
            $uc = (float) ($producto->unidades_contenidas ?? 1);

            $productos[] = [
                'codigo' => $producto->cod_producto ?? '',
                'nombre' => $producto->name ?? '',
                'cantidad' => (float) $detalle->cantidad,
                'unidad' => $detalle->unidadDerivadaInmutable->name ?? '',
                'costo' => (float) $detalle->costo * (float) $detalle->factor,
                'costo_total' => (float) $detalle->cantidad * (float) $detalle->costo * (float) $detalle->factor,
                'stock_anterior_origen' => (float) $detalle->stock_anterior_origen,
                'stock_nuevo_origen' => (float) $detalle->stock_nuevo_origen,
                'stock_anterior_destino' => (float) $detalle->stock_anterior_destino,
                'stock_nuevo_destino' => (float) $detalle->stock_nuevo_destino,
                'stk_ant_origen_f' => IngresoSalidaPdfService::formatStockF((float) $detalle->stock_anterior_origen, $uc),
                'stk_nvo_origen_f' => IngresoSalidaPdfService::formatStockF((float) $detalle->stock_nuevo_origen, $uc),
                'stk_ant_destino_f' => IngresoSalidaPdfService::formatStockF((float) $detalle->stock_anterior_destino, $uc),
                'stk_nvo_destino_f' => IngresoSalidaPdfService::formatStockF((float) $detalle->stock_nuevo_destino, $uc),
            ];
        }

        return $productos;
    }

    private function calcularTotal(array $productos): float
    {
        return array_sum(array_column($productos, 'costo_total'));
    }

    private function prepararInfoGeneral(TransferenciaStock $t): array
    {
        return [
            [
                'Fecha de Emisión' => PdfService::formatFecha($t->fecha),
                'Usuario' => $t->user->name ?? '—',
            ],
            [
                'Almacén Origen' => $t->almacenOrigen->name ?? '—',
                'Almacén Destino' => $t->almacenDestino->name ?? '—',
            ],
            [
                'Observaciones' => $t->descripcion ?? '—',
                'Estado' => $t->estado ? 'ACTIVO' : 'ANULADO',
            ],
        ];
    }
}
