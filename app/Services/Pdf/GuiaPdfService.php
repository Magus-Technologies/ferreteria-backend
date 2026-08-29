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

        [$choferNombre, $choferDni, $vehiculoPlaca] = $this->resolverChoferYVehiculo($guia);

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
            'vehiculoPlaca' => $vehiculoPlaca,
            'choferNombre' => $choferNombre,
            'choferDni' => $choferDni,
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
            $this->calcularAlturaTicket($detalles),
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

    /**
     * Alto del papel del ticket, en función de cuántos productos lleva.
     *
     * Antes era FIJO en 841.89pt: cualquier guía con muchos ítems desbordaba y
     * dompdf la partía en una segunda hoja — que en una impresora de tickets
     * (rollo continuo de 80mm) no tiene sentido. Mismo criterio que
     * VentaPdfService::calcularAlturaTicket.
     *
     * @param  array<int, mixed>  $detalles
     * @return array{0: int, 1: int, 2: float, 3: float}
     */
    private function calcularAlturaTicket(array $detalles): array
    {
        $alturaPiso = 841.89;

        // Productos que entran cómodos en el piso: los nombres de ferretería
        // envuelven 2-3 líneas a 6pt en 74mm de ancho útil.
        $productosBase = 8;
        $extraProductos = max(0, count($detalles) - $productosBase);

        return [0, 0, 226.77, $alturaPiso + ($extraProductos * 48)];
    }

    private function obtenerGuia(string $guiaId): GuiaRemision
    {
        return GuiaRemision::with([
            'user.empresa',
            'cliente',
            'comprador',
            'motivoTraslado',
            'chofer',
            // El chofer de transporte PRIVADO es un user, y su vehículo cuelga
            // de él (ver resolverChoferYVehiculo).
            'userChofer.vehiculo',
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

        [$choferNombre, $choferDni, $vehiculoPlaca] = $this->resolverChoferYVehiculo($guia);

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
                'Vehiculo' => $vehiculoPlaca,
                'Chofer' => "{$choferNombre} ({$choferDni})",
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
        // OJO: `env()` devuelve NULL cuando la config está cacheada
        // (`php artisan config:cache`), así que en producción caía al default
        // y el ticket imprimía "Consulte su documento en http://localhost:3000".
        // `config()` sí sobrevive al cacheo; se deja app.url como respaldo real.
        $frontendUrl = rtrim(
            config('app.frontend_url') ?: config('app.url') ?: 'http://localhost:3000',
            '/'
        );

        return "{$frontendUrl}/consulta";
    }

    /**
     * Resuelve chofer y placa con el MISMO criterio que el XML
     * (GuiaRemisionService::prepararDatosParaGreenter). Hay dos fuentes de
     * chofer y los PDFs solo miraban una:
     *
     *   - Transporte PRIVADO → el chofer es un USER de la empresa
     *     (`user_chofer_id`), y su vehículo cuelga de ese user
     *     (`user.vehiculo_id`), NO de la guía.
     *   - PÚBLICO / GRE-Transportista → chofer externo (`chofer_id`).
     *
     * Leyendo solo `$guia->chofer`, una guía privada imprimía "Chofer: -" y
     * "Vehiculo: -" aunque tuviera despachador asignado.
     *
     * @return array{0: string, 1: string, 2: string} [nombre, documento, placa]
     */
    private function resolverChoferYVehiculo(GuiaRemision $guia): array
    {
        $esTransportePrivado = $guia->modalidad_transporte === 'PRIVADO';
        $userChofer = $guia->userChofer;
        $choferExterno = $guia->chofer;

        $placaDelChofer = null;

        if ($esTransportePrivado && $userChofer) {
            $nombre = $userChofer->name ?: '-';
            $documento = $userChofer->numero_documento ?: '-';
            $placaDelChofer = $userChofer->vehiculo?->placa;
        } else {
            $nombre = $choferExterno?->name
                ?: trim(($choferExterno?->nombres ?? '') . ' ' . ($choferExterno?->apellidos ?? ''))
                ?: '-';
            $documento = $choferExterno?->dni ?: '-';
        }

        // La placa cargada a mano en la guía manda; si no hay, se cae a la del
        // vehículo asignado al despachador.
        $placa = $guia->vehiculo_placa ?: ($placaDelChofer ?: '-');

        return [$nombre, $documento, $placa];
    }
}
