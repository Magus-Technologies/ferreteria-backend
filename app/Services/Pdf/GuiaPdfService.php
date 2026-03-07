<?php

namespace App\Services\Pdf;

use App\Models\GuiaRemision;
use Illuminate\Http\Response;

class GuiaPdfService
{
    public function generar(string $guiaId, string $formato = 'a4'): Response
    {
        $guia = $this->obtenerGuia($guiaId);
        $empresa = $guia->user->empresa;

        $detalles = $this->prepararDetalles($guia);
        $pesoTotal = array_sum(array_column($detalles, 'peso'));

        if ($formato === 'ticket') {
            return $this->generarTicket($guia, $empresa, $detalles, $pesoTotal);
        }

        return $this->generarA4($guia, $empresa, $detalles, $pesoTotal);
    }

    private function generarA4($guia, $empresa, array $detalles, float $pesoTotal): Response
    {
        $data = [
            'guia' => $guia,
            'empresa' => $empresa,
            'logoPath' => PdfService::getLogoPath($empresa->logo),
            'tipoDocumentoTitulo' => 'GUIA DE REMISION ELECTRONICA',
            'numeroDocumento' => $guia->numero_completo,
            'detalles' => $detalles,
            'pesoTotal' => $pesoTotal,
            'filas' => $this->prepararInfoGrid($guia),
            'observaciones' => $guia->observaciones ?: '-',
            'codigoQr' => $guia->sunat_codigo_qr,
        ];

        $filename = "GRE-{$guia->serie}-{$guia->numero}.pdf";

        return PdfService::render('pdf.guia', $data, $filename);
    }

    private function generarTicket($guia, $empresa, array $detalles, float $pesoTotal): Response
    {
        $cliente = $guia->cliente;
        $clienteNombre = $cliente?->razon_social
            ?: trim(($cliente?->nombres ?? '') . ' ' . ($cliente?->apellidos ?? ''))
            ?: 'VARIOS';

        $chofer = $guia->chofer;
        $choferNombre = $chofer?->name
            ?: trim(($chofer?->nombres ?? '') . ' ' . ($chofer?->apellidos ?? ''))
            ?: '-';

        $data = [
            'titulo' => "GRE {$guia->numero_completo}",
            'empresa' => $empresa,
            'logoPath' => PdfService::getLogoPath($empresa->logo),
            'numeroDocumento' => $guia->numero_completo,
            'fechaEmision' => $guia->fecha_emision ? $guia->fecha_emision->format('d/m/Y') : '-',
            'fechaTraslado' => $guia->fecha_traslado ? $guia->fecha_traslado->format('d/m/Y') : '-',
            'motivoTraslado' => $guia->motivoTraslado?->descripcion ?? '-',
            'modalidad' => $guia->modalidad_transporte === 'PRIVADO' ? 'Transporte Privado' : 'Transporte Publico',
            'puntoPartida' => $guia->punto_partida ?? '-',
            'puntoLlegada' => $guia->punto_llegada ?? '-',
            'vehiculoPlaca' => $guia->vehiculo_placa ?? '-',
            'choferNombre' => $choferNombre,
            'choferDni' => $chofer?->dni ?? '-',
            'clienteNombre' => $clienteNombre,
            'clienteDocumento' => $cliente?->numero_documento ?? '-',
            'detalles' => $detalles,
            'pesoTotal' => $pesoTotal,
            'observaciones' => $guia->observaciones ?: '-',
            'codigoQr' => $guia->sunat_codigo_qr,
        ];

        $filename = "TICKET-GRE-{$guia->serie}-{$guia->numero}.pdf";

        return PdfService::render(
            'pdf.guia-ticket',
            $data,
            $filename,
            'portrait',
            [0, 0, 226.77, 841.89],
        );
    }

    private function obtenerGuia(string $guiaId): GuiaRemision
    {
        return GuiaRemision::with([
            'user.empresa',
            'cliente',
            'motivoTraslado',
            'chofer',
            'almacenOrigen',
            'almacenDestino',
            'detalles.producto.marca',
            'detalles.producto.unidadMedida',
            'detalles.unidadDerivadaInmutable',
        ])->findOrFail($guiaId);
    }

    private function prepararDetalles(GuiaRemision $guia): array
    {
        $detalles = [];

        foreach ($guia->detalles as $d) {
            $detalles[] = [
                'codigo' => $d->producto?->cod_producto ?? '',
                'nombre' => $d->producto?->name ?? 'Producto',
                'cantidad' => (float) $d->cantidad,
                'unidad' => $d->unidad_derivada_inmutable_name ?? 'UND',
                'peso' => (float) ($d->peso_total ?? 0),
            ];
        }

        return $detalles;
    }

    private function prepararInfoGrid(GuiaRemision $guia): array
    {
        $cliente = $guia->cliente;
        $clienteNombre = $cliente?->razon_social
            ?: trim(($cliente?->nombres ?? '') . ' ' . ($cliente?->apellidos ?? ''))
            ?: 'VARIOS';

        $chofer = $guia->chofer;
        $choferNombre = $chofer?->name
            ?: trim(($chofer?->nombres ?? '') . ' ' . ($chofer?->apellidos ?? ''))
            ?: '-';

        return [
            [
                'F. Emision' => $guia->fecha_emision ? $guia->fecha_emision->format('d/m/Y') : '-',
                'F. Traslado' => $guia->fecha_traslado ? $guia->fecha_traslado->format('d/m/Y') : '-',
            ],
            [
                'Motivo Traslado' => $guia->motivoTraslado?->descripcion ?? '-',
                'Modalidad' => $guia->modalidad_transporte === 'PRIVADO' ? 'Transporte Privado' : 'Transporte Publico',
            ],
            [
                'Punto Partida' => $guia->punto_partida ?? '-',
                'Punto Llegada' => $guia->punto_llegada ?? '-',
            ],
            [
                'Vehiculo' => $guia->vehiculo_placa ?? '-',
                'Chofer' => "{$choferNombre} ({$chofer?->dni})",
            ],
            [
                'RUC / DNI' => $cliente?->numero_documento ?? '-',
                'Destinatario' => $clienteNombre,
            ],
        ];
    }
}
