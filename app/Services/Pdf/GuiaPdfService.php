<?php

namespace App\Services\Pdf;

use App\Models\GuiaRemision;
use App\Services\Pdf\Traits\ResuelveEstilosPlantilla;
use Illuminate\Http\Response;

class GuiaPdfService
{
    use ResuelveEstilosPlantilla;

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
        $estilos = $this->prepararDatosPlantilla((int) $empresa->id, 'guia', 'A4');

        $data = array_merge([
            'guia' => $guia,
            'empresa' => $empresa,
            'logoPath' => PdfService::getLogoPath($empresa->logo),
            'tipoDocumentoTitulo' => $this->getTituloDocumento($guia),
            'numeroDocumento' => $guia->numero_completo,
            'detalles' => $detalles,
            'pesoTotal' => $pesoTotal,
            'filas' => $this->prepararInfoGrid($guia),
            'observaciones' => $guia->observaciones ?: '-',
            'codigoQr' => $guia->sunat_codigo_qr,
            'consultaUrl' => $this->getConsultaUrl(),
        ], $estilos);

        $filename = "GRE-{$guia->serie}-{$guia->numero}.pdf";

        return PdfService::render('pdf.guia', $data, $filename);
    }

    private function generarTicket($guia, $empresa, array $detalles, float $pesoTotal): Response
    {
        $codigoMotivo = $guia->motivoTraslado?->codigo ?? '01';
        $esEntreEstablecimientos = $codigoMotivo === '08';

        // Destinatario: motivo 08 = misma empresa, otros = cliente
        if ($esEntreEstablecimientos) {
            $clienteNombre = $empresa->razon_social ?? config('sunat-api.razon_social');
            $clienteDocumento = $empresa->ruc ?? \App\Models\Empresa::getRucEmisor();
        } else {
            $cliente = $guia->cliente;
            $clienteNombre = $cliente?->razon_social
                ?: trim(($cliente?->nombres ?? '') . ' ' . ($cliente?->apellidos ?? ''))
                ?: 'VARIOS';
            $clienteDocumento = $cliente?->numero_documento ?? '-';
        }

        // Comprador (motivos 03, 14)
        $comprador = $guia->comprador;
        $compradorNombre = null;
        $compradorDocumento = null;
        if (in_array($codigoMotivo, ['03', '14']) && $comprador) {
            $compradorNombre = $comprador->razon_social
                ?: trim(($comprador->nombres ?? '') . ' ' . ($comprador->apellidos ?? ''))
                ?: '-';
            $compradorDocumento = $comprador->numero_documento ?? '-';
        }

        $chofer = $guia->chofer;
        $choferNombre = $chofer?->name
            ?: trim(($chofer?->nombres ?? '') . ' ' . ($chofer?->apellidos ?? ''))
            ?: '-';

        $estilos = $this->prepararDatosPlantilla((int) $empresa->id, 'guia', 'Ticket');

        $data = array_merge([
            'titulo' => $this->getTituloDocumento($guia) . "\n" . $guia->numero_completo,
            'tipoGuiaTitulo' => $this->getTituloDocumento($guia),
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
            'clienteDocumento' => $clienteDocumento,
            'compradorNombre' => $compradorNombre,
            'compradorDocumento' => $compradorDocumento,
            'esEntreEstablecimientos' => $esEntreEstablecimientos,
            'almacenOrigen' => $esEntreEstablecimientos ? ($guia->almacenOrigen?->name ?? '-') : null,
            'almacenDestino' => $esEntreEstablecimientos ? ($guia->almacenDestino?->name ?? '-') : null,
            'detalles' => $detalles,
            'pesoTotal' => $pesoTotal,
            'observaciones' => $guia->observaciones ?: '-',
            'referencia' => $guia->referencia ?: null,
            'codigoQr' => $guia->sunat_codigo_qr,
            'consultaUrl' => $this->getConsultaUrl(),
        ], $estilos);

        $filename = "TICKET-GRE-{$guia->serie}-{$guia->numero}.pdf";

        return PdfService::render(
            'pdf.guia-ticket',
            $data,
            $filename,
            'portrait',
            [0, 0, 226.77, 841.89],
        );
    }

    private function getTituloDocumento(GuiaRemision $guia): string
    {
        return match ($guia->tipo_guia) {
            'ELECTRONICA_TRANSPORTISTA' => 'GUIA DE REMISION TRANSPORTISTA ELECTRONICA',
            'FISICA' => 'GUIA DE REMISION FISICA',
            default => 'GUIA DE REMISION REMITENTE ELECTRONICA',
        };
    }

    private function obtenerGuia(string $guiaId): GuiaRemision
    {
        return GuiaRemision::with([
            'user.empresa',
            'cliente',
            'comprador',
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
        $codigoMotivo = $guia->motivoTraslado?->codigo ?? '01';
        $esEntreEstablecimientos = $codigoMotivo === '08';
        $empresa = $guia->user?->empresa;

        // Destinatario según motivo
        if ($esEntreEstablecimientos) {
            $clienteNombre = $empresa?->razon_social ?? config('sunat-api.razon_social');
            $clienteDocumento = $empresa?->ruc ?? \App\Models\Empresa::getRucEmisor();
        } else {
            $cliente = $guia->cliente;
            $clienteNombre = $cliente?->razon_social
                ?: trim(($cliente?->nombres ?? '') . ' ' . ($cliente?->apellidos ?? ''))
                ?: 'VARIOS';
            $clienteDocumento = $cliente?->numero_documento ?? '-';
        }

        $chofer = $guia->chofer;
        $choferNombre = $chofer?->name
            ?: trim(($chofer?->nombres ?? '') . ' ' . ($chofer?->apellidos ?? ''))
            ?: '-';

        $filas = [
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
                'RUC / DNI' => $clienteDocumento,
                'Destinatario' => $clienteNombre,
            ],
        ];

        if ($guia->referencia) {
            $filas[] = ['Referencia' => $guia->referencia];
        }

        // Comprador (motivos 03, 14)
        $comprador = $guia->comprador;
        if (in_array($codigoMotivo, ['03', '14']) && $comprador) {
            $compradorNombre = $comprador->razon_social
                ?: trim(($comprador->nombres ?? '') . ' ' . ($comprador->apellidos ?? ''))
                ?: '-';
            $filas[] = [
                'RUC / DNI Comprador' => $comprador->numero_documento ?? '-',
                'Comprador' => $compradorNombre,
            ];
        }

        // Almacenes (motivo 08)
        if ($esEntreEstablecimientos) {
            $filas[] = [
                'Almacen Origen' => $guia->almacenOrigen?->name ?? '-',
                'Almacen Destino' => $guia->almacenDestino?->name ?? '-',
            ];
        }

        return $filas;
    }

    private function getConsultaUrl(): string
    {
        $frontendUrl = rtrim(env('FRONTEND_URL', 'http://localhost:3000'), '/');

        return "{$frontendUrl}/consulta";
    }
}
