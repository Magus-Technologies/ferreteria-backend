<?php

namespace App\Services\Pdf;

use App\Models\NotaCredito;
use Illuminate\Http\Response;

class NotaCreditoPdfService
{
    public function generar(string $id): Response
    {
        $nota = $this->obtenerNota($id);
        $empresa = $nota->usuario->empresa;

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
            'tipoDocumentoTitulo' => 'NOTA DE CRÉDITO ELECTRÓNICA',
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
                    'Cliente' => $cliente['nombre'],
                    'Motivo' => $nota->motivoNota->descripcion ?? 'Sin motivo',
                ],
                [
                    'Comp. Afectado' => $comprobanteAfectado,
                    'Observaciones' => $nota->observaciones ?? '-',
                ],
            ],
            'productos' => $productos,
            'calculos' => $calculos,
            'son' => PdfService::numeroALetras($calculos['total']),
            'observaciones' => $nota->observaciones ?: '- NINGUNA',
        ];

        $filename = "NC-{$numeroCompleto}.pdf";

        return PdfService::render('pdf.nota-credito', $data, $filename);
    }

    private function obtenerNota(string $id): NotaCredito
    {
        return NotaCredito::with([
            'usuario.empresa',
            'motivoNota',
            'venta.cliente',
            'comprobanteReferencia.detalles',
            'comprobanteReferencia.cliente',
            'comprobanteElectronico',
        ])->findOrFail($id);
    }

    private function prepararProductos(NotaCredito $nota): array
    {
        $productos = [];

        // Intentar usar detalles del comprobante de referencia
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

        // Fallback: producto genérico
        $total = (float) ($nota->monto_total ?? 0);
        $subtotal = (float) ($nota->monto_subtotal ?? ($total / 1.18));

        $productos[] = [
            'codigo' => 'N/A',
            'nombre' => $nota->descripcion ?? 'Nota de Crédito',
            'cantidad' => 1,
            'unidad' => 'UND',
            'precio' => $subtotal,
            'subtotal' => $subtotal,
        ];

        return $productos;
    }

    private function calcularTotales(NotaCredito $nota, array $productos): array
    {
        $total = (float) ($nota->monto_total ?? 0);
        $subtotal = (float) ($nota->monto_subtotal ?? 0);
        $igv = (float) ($nota->monto_igv ?? 0);

        // Si no vienen del modelo, calcular
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

    private function prepararCliente(NotaCredito $nota): array
    {
        // Primero intentar del comprobante de referencia
        $cliente = $nota->comprobanteReferencia?->cliente;

        // Si no, de la venta
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

    private function prepararComprobanteAfectado(NotaCredito $nota): string
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
