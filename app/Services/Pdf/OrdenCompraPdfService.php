<?php

namespace App\Services\Pdf;

use App\Models\OrdenCompra;
use App\Services\Pdf\Traits\ResuelveEstilosPlantilla;
use Illuminate\Http\Response;

class OrdenCompraPdfService
{
    use ResuelveEstilosPlantilla;

    public function generar(int $id, ?array $columnas = null, string $formato = 'a4'): Response
    {
        $data = $this->prepararData($id, $columnas, $formato);

        if ($formato === 'ticket') {
            return PdfService::render(
                'pdf.orden-compra-ticket',
                $data,
                "TICKET-OC-{$data['orden']->codigo}.pdf",
                'portrait',
                [0, 0, 226.77, 841.89],
            );
        }

        return PdfService::render('pdf.orden-compra', $data, "OC-{$data['orden']->codigo}.pdf");
    }

    public function generarBinario(int $id, ?array $columnas = null, string $formato = 'a4'): string
    {
        $data = $this->prepararData($id, $columnas, $formato);

        if ($formato === 'ticket') {
            return PdfService::output(
                'pdf.orden-compra-ticket',
                $data,
                'portrait',
                [0, 0, 226.77, 841.89],
            );
        }

        return PdfService::output('pdf.orden-compra', $data);
    }

    private function prepararData(int $id, ?array $columnas = null, string $formato = 'a4'): array
    {
        $orden = $this->obtenerOrden($id);
        $empresa = $orden->user->empresa;

        $monedaSimbolo = $orden->tipo_moneda === 'd' ? '$' : 'S/.';
        $productos = $this->prepararProductos($orden);
        $calculos = $this->calcularTotales($orden, $productos);

        $formatoPlantilla = $formato === 'ticket' ? 'Ticket' : 'A4';
        $estilos = $this->prepararDatosPlantilla((int) $empresa->id, 'orden-compra', $formatoPlantilla);

        return array_merge([
            'orden' => $orden,
            'empresa' => $empresa,
            'logoPath' => PdfService::getLogoPath($empresa->logo),
            'tipoDocumentoTitulo' => 'ORDEN DE COMPRA',
            'numeroDocumento' => $orden->codigo,
            'productos' => $productos,
            'calculos' => $calculos,
            'monedaSimbolo' => $monedaSimbolo,
            'filas' => $this->prepararInfoGeneral($orden, $monedaSimbolo),
            'filasProveedor' => $this->prepararInfoProveedor($orden),
            'filasPago' => $this->prepararInfoPago($orden),
            'son' => PdfService::numeroALetras($calculos['total']),
            'moneda' => $orden->tipo_moneda === 'd' ? 'DOLARES' : 'SOLES',
            'observaciones' => $estilos['msg']['observaciones_default'] ?? '- NINGUNA',
            'columnas' => $columnas, // Añadido soporte para columnas seleccionables!
        ], $estilos);
    }

    private function obtenerOrden(int $id): OrdenCompra
    {
        return OrdenCompra::with([
            'user.empresa',
            'proveedor',
            'productos.producto',
            'requerimiento',
            'almacen',
        ])->findOrFail($id);
    }

    private function prepararProductos(OrdenCompra $orden): array
    {
        $productos = [];
        foreach ($orden->productos as $p) {
            $productos[] = [
                'codigo' => $p->codigo ?: ($p->producto?->cod_producto ?? '—'),
                'nombre' => $p->nombre ?: ($p->producto?->name ?? '—'),
                'marca' => $p->marca ?? '—',
                'unidad' => $p->unidad ?? 'UND',
                'cantidad' => (float) $p->cantidad,
                'precio' => (float) $p->precio,
                'flete' => (float) ($p->flete ?? 0),
                'subtotal' => (float) $p->subtotal,
            ];
        }
        return $productos;
    }

    /** IGV vigente en Perú. */
    private const IGV = 0.18;

    private function calcularTotales(OrdenCompra $orden, array $productos): array
    {
        $subtotal = array_sum(array_column($productos, 'subtotal'));
        $fleteTotal = array_sum(array_column($productos, 'flete'));
        $percepcion = (float) ($orden->percepcion ?? 0);
        $total = (float) ($orden->total ?? ($subtotal + $fleteTotal + $percepcion));

        // Los precios de la orden ya vienen CON IGV incluido, así que la base
        // gravada se desagrega hacia atrás (total ÷ 1.18) en vez de sumarle el
        // impuesto por encima. El IGV se saca por diferencia para que
        // `op_gravadas + igv` dé exactamente el total, sin descuadres de redondeo.
        $opGravadas = round($total / (1 + self::IGV), 2);
        $igv = round($total - $opGravadas, 2);

        return [
            'subtotal' => $subtotal,
            'flete_total' => $fleteTotal,
            'percepcion' => $percepcion,
            'op_gravadas' => $opGravadas,
            'igv' => $igv,
            'igv_porcentaje' => (int) round(self::IGV * 100),
            'total' => $total,
        ];
    }

    private function prepararInfoGeneral(OrdenCompra $orden, string $monedaSimbolo): array
    {
        $filas = [
            [
                'N° Orden' => $orden->codigo,
                'Fecha' => PdfService::formatFecha($orden->fecha),
            ],
            [
                'Solicitante' => $orden->user->name ?? '—',
                'Moneda' => $orden->tipo_moneda === 's' ? 'Soles (S/.)' : 'Dólares ($)',
            ],
        ];

        if ((float) $orden->tipo_de_cambio > 0) {
            $filas[] = [
                'T. Cambio' => number_format((float) $orden->tipo_de_cambio, 4),
                'Requerimiento' => $orden->requerimiento?->codigo ?? '—',
            ];
        } elseif ($orden->requerimiento) {
            $filas[1]['Requerimiento'] = $orden->requerimiento->codigo;
        }

        return $filas;
    }

    private function prepararInfoProveedor(OrdenCompra $orden): array
    {
        return [
            [
                'Razón Social' => $orden->proveedor?->razon_social ?? '—',
                'RUC' => $orden->proveedor?->ruc ?? $orden->ruc ?? '—',
            ],
        ];
    }

    private function prepararInfoPago(OrdenCompra $orden): array
    {
        $filas = [
            [
                'Forma de Pago' => $orden->forma_de_pago === 'co' ? 'Contado' : 'Crédito',
            ],
        ];

        if ($orden->forma_de_pago === 'cr') {
            $filas[0]['Días Crédito'] = ($orden->numero_dias ?? 0) . ' días';
            $filas[] = [
                'Vencimiento' => PdfService::formatFecha($orden->fecha_vencimiento),
            ];
        }

        if ($orden->tipo_documento) {
            $filas[] = [
                'Tipo Doc.' => $orden->tipo_documento,
                'Serie / Nro' => ($orden->serie ?? '') . ' - ' . ($orden->numero ?? ''),
            ];
        }

        if ($orden->guia) {
            $filas[] = ['Guía Rem.' => $orden->guia];
        }

        return $filas;
    }
}
