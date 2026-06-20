<?php

namespace App\Services\Pdf;

use App\Models\NotaDebito;
use App\Models\PlantillaImpresion;
use Illuminate\Http\Response;

class NotaDebitoPdfService
{
    public function generar(string $id, string $formato = 'a4'): Response
    {
        $nota = $this->obtenerNota($id);
        $empresa = $nota->usuario->empresa;

        // Plantilla del comprobante para respetar flags como "ocultar logo" en ticket.
        $plantilla = PlantillaImpresion::obtenerParaConFormato(
            (int) $empresa->id,
            'nota-debito',
            $formato === 'ticket' ? 'Ticket' : 'A4'
        );
        $msg = array_merge(PlantillaImpresion::DEFAULT_MENSAJES_EXTRA, $plantilla->mensajes_extra ?? []);

        $productos = $this->prepararProductos($nota);
        $calculos = $this->calcularTotales($nota, $productos);

        $cliente = $this->prepararCliente($nota);
        $comprobanteAfectado = $this->prepararComprobanteAfectado($nota);

        $numeroCompleto = $nota->serie && $nota->numero
            ? $nota->serie . '-' . str_pad($nota->numero, 8, '0', STR_PAD_LEFT)
            : ($nota->numero_completo ?? 'S/N');

        $data = [
            'empresa' => $empresa,
            'logoPath' => PdfService::getLogoPath($empresa->logo),
            'tipoDocumentoTitulo' => 'NOTA DE DÉBITO ELECTRÓNICA',
            'numeroDocumento' => $numeroCompleto,
            'filas' => [
                [
                    'F. Emisión' => PdfService::formatFecha($nota->fecha_emision ?? $nota->fecha),
                    'Moneda' => 'SOLES (PEN)',
                ],
                [
                    (strlen($cliente['documento']) === 11 ? 'RUC' : 'DNI') => $cliente['documento'],
                    'Dirección' => $cliente['direccion'],
                ],
                [
                    'Señor(es)' => $cliente['nombre'],
                    'Doc. que modifica' => $comprobanteAfectado,
                ],
                [
                    'Motivo' => $nota->motivoNota->descripcion ?? $nota->descripcion ?? 'Sin motivo',
                    'Observaciones' => $nota->observaciones ?? '-',
                ],
            ],
            'productos' => $productos,
            'calculos' => $calculos,
            'son' => PdfService::numeroALetras($calculos['total']),
            'observaciones' => $nota->observaciones ?: '- NINGUNA',
            'msg' => $msg,
        ];

        $filename = "ND-{$numeroCompleto}.pdf";

        $view = $formato === 'ticket' ? 'pdf.nota-debito-ticket' : 'pdf.nota-debito';
        return PdfService::render($view, $data, $filename);
    }

    private function obtenerNota(string $id): NotaDebito
    {
        return NotaDebito::with([
            'usuario.empresa',
            'motivoNota',
            'venta.cliente',
            'comprobanteReferencia.detalles',
            'comprobanteReferencia.cliente',
            'comprobanteElectronico',
        ])->findOrFail($id);
    }

    private function prepararProductos(NotaDebito $nota): array
    {
        $productos = [];

        // Intentar detalles del comprobante de referencia
        $compRef = $nota->comprobanteReferencia;
        if ($compRef && $compRef->detalles && $compRef->detalles->count() > 0) {
            foreach ($compRef->detalles as $detalle) {
                $productos[] = [
                    'codigo' => $detalle->codigo_producto ?? 'N/A',
                    'nombre' => $detalle->descripcion ?? '',
                    'cantidad' => (float) ($detalle->cantidad ?? 0),
                    'unidad' => $detalle->unidad_medida ?? 'UND',
                    'precio' => (float) ($detalle->precio_unitario ?? 0),
                    'subtotal' => (float) ($detalle->valor_venta ?? $detalle->subtotal ?? 0),
                ];
            }
            return $productos;
        }

        // Intentar detalles del comprobante electrónico
        $compElec = $nota->comprobanteElectronico;
        if ($compElec && $compElec->detalles && $compElec->detalles->count() > 0) {
            foreach ($compElec->detalles as $detalle) {
                $productos[] = [
                    'codigo' => $detalle->codigo_producto ?? 'N/A',
                    'nombre' => $detalle->descripcion ?? '',
                    'cantidad' => (float) ($detalle->cantidad ?? 0),
                    'unidad' => $detalle->unidad_medida ?? 'UND',
                    'precio' => (float) ($detalle->precio_unitario ?? 0),
                    'subtotal' => (float) ($detalle->valor_venta ?? $detalle->subtotal ?? 0),
                ];
            }
            return $productos;
        }

        // Fallback
        $total = (float) ($nota->monto_total ?? 0);
        $subtotal = (float) ($nota->monto_subtotal ?? ($total / 1.18));

        $productos[] = [
            'codigo' => 'N/A',
            'nombre' => $nota->descripcion ?? 'Nota de Débito',
            'cantidad' => 1,
            'unidad' => 'UND',
            'precio' => $subtotal,
            'subtotal' => $subtotal,
        ];

        return $productos;
    }

    private function calcularTotales(NotaDebito $nota, array $productos): array
    {
        $total = (float) ($nota->monto_total ?? 0);
        $subtotal = (float) ($nota->monto_subtotal ?? 0);
        $igv = (float) ($nota->monto_igv ?? 0);

        if ($subtotal <= 0 && $total > 0) {
            $subtotal = $total / 1.18;
            $igv = $total - $subtotal;
        }

        return [
            'subtotal' => $subtotal,
            'igv' => $igv,
            'total' => $total,
        ];
    }

    private function prepararCliente(NotaDebito $nota): array
    {
        $cliente = $nota->comprobanteReferencia?->cliente;

        if (!$cliente) {
            $cliente = $nota->venta?->cliente;
        }

        $nombre = $cliente?->razon_social
            ?: trim(($cliente?->nombres ?? '') . ' ' . ($cliente?->apellidos ?? ''))
            ?: 'CLIENTES VARIOS';

        return [
            'nombre' => $nombre,
            'documento' => $cliente?->numero_documento ?? '99999999',
            'direccion' => $cliente?->direccion ?? '',
        ];
    }

    private function prepararComprobanteAfectado(NotaDebito $nota): string
    {
        $compRef = $nota->comprobanteReferencia;
        if ($compRef) {
            $tipo = match ($compRef->tipo_comprobante ?? '') {
                '01' => 'FACTURA',
                '03' => 'BOLETA',
                default => 'COMPROBANTE',
            };
            return "{$tipo} {$compRef->numero}";
        }

        return $nota->referencia_documento ?? 'N/A';
    }
}
