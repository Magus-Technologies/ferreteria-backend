<?php

namespace App\Services\Pdf;

use App\Models\ValeCompra;
use App\Services\Pdf\Traits\ResuelveEstilosPlantilla;
use Illuminate\Http\Response;

class ValeCompraPdfService
{
    use ResuelveEstilosPlantilla;

    private const TIPO_LABELS = [
        'SORTEO' => 'SORTEO',
        'DESCUENTO_MISMA_COMPRA' => 'DESCUENTO',
        'DESCUENTO_PROXIMA_COMPRA' => 'VALE DESCUENTO',
        'PRODUCTO_GRATIS' => 'PRODUCTO GRATIS',
        'DOS_POR_UNO' => '2x1',
    ];

    private const MODALIDAD_LABELS = [
        'CANTIDAD_MINIMA' => 'Por Cantidad Minima',
        'POR_CATEGORIA' => 'Por Categoria',
        'POR_PRODUCTOS' => 'Por Productos Especificos',
        'MIXTO' => 'Mixto (Categoria + Productos)',
    ];

    public function generar(int $valeId): Response
    {
        $vale = $this->obtenerVale($valeId);
        $empresa = $this->obtenerEmpresa($vale);

        [$beneficioPrincipal, $beneficioDetalle] = $this->calcularBeneficio($vale);

        $cantMin = fmod((float) $vale->cantidad_minima, 1) == 0
            ? number_format((float) $vale->cantidad_minima, 0)
            : (string) $vale->cantidad_minima;

        $precios = collect([
            $vale->aplica_precio_publico ? 'Publico' : null,
            $vale->aplica_precio_especial ? 'Especial' : null,
            $vale->aplica_precio_minimo ? 'Minimo' : null,
            $vale->aplica_precio_ultimo ? 'Ultimo' : null,
        ])->filter()->values()->all();

        $estilos = $this->prepararDatosPlantilla((int) $empresa->id, 'vale-compra', 'Ticket');

        $data = array_merge($estilos, [
            'vale' => $vale,
            'empresa' => $empresa,
            'tipoLabel' => self::TIPO_LABELS[$vale->tipo_promocion] ?? 'VALE',
            'modalidadLabel' => self::MODALIDAD_LABELS[$vale->modalidad] ?? $vale->modalidad,
            'beneficioPrincipal' => $beneficioPrincipal,
            'beneficioDetalle' => $beneficioDetalle,
            'cantidadMinima' => $cantMin,
            'fechaInicio' => PdfService::formatFecha($vale->fecha_inicio),
            'fechaFin' => $vale->fecha_fin ? PdfService::formatFecha($vale->fecha_fin) : 'Sin limite',
            'productos' => $vale->productos,
            'categorias' => $vale->categorias,
            'precios' => $precios,
        ]);

        $filename = "VALE-{$vale->codigo}.pdf";

        // 80mm = 226.77pt
        return PdfService::render(
            'pdf.vale-compra-ticket',
            $data,
            $filename,
            'portrait',
            [0, 0, 226.77, 841.89],
        );
    }

    private function obtenerVale(int $valeId): ValeCompra
    {
        return ValeCompra::with([
            'productos',
            'categorias',
            'productoGratis',
            'creador.empresa',
        ])->findOrFail($valeId);
    }

    private function obtenerEmpresa(ValeCompra $vale): object
    {
        if ($vale->creador && $vale->creador->empresa) {
            return $vale->creador->empresa;
        }

        // Fallback: obtener la primera empresa disponible
        $empresaModel = \App\Models\Empresa::first();
        return $empresaModel ?? (object) [
            'razon_social' => 'FERRETERIA',
            'nombre_comercial' => 'FERRETERIA',
            'ruc' => '',
            'direccion' => '',
            'telefono' => '',
        ];
    }

    private function calcularBeneficio(ValeCompra $vale): array
    {
        $principal = '';
        $detalle = '';

        switch ($vale->tipo_promocion) {
            case 'DESCUENTO_MISMA_COMPRA':
            case 'DESCUENTO_PROXIMA_COMPRA':
                if ($vale->descuento_tipo === 'PORCENTAJE') {
                    $val = (float) $vale->descuento_valor;
                    $principal = (fmod($val, 1) == 0 ? number_format($val, 0) : number_format($val, 2)) . '%';
                } else {
                    $principal = 'S/ ' . number_format((float) $vale->descuento_valor, 2);
                }
                $detalle = $vale->tipo_promocion === 'DESCUENTO_PROXIMA_COMPRA'
                    ? 'Descuento en tu proxima compra'
                    : 'Descuento en esta compra';
                break;

            case 'PRODUCTO_GRATIS':
                $principal = 'GRATIS';
                $productoNombre = $vale->productoGratis->name ?? 'Producto';
                $detalle = (int) $vale->cantidad_producto_gratis . 'x ' . $productoNombre;
                break;

            case 'DOS_POR_UNO':
                $principal = '2x1';
                $extra = (int) ($vale->cantidad_producto_gratis ?? 1);
                $cantMin = (int) $vale->cantidad_minima;
                $detalle = "Compra {$cantMin}, lleva " . ($cantMin + $extra);
                break;

            case 'SORTEO':
                $principal = 'SORTEO';
                $detalle = 'Participas automaticamente';
                break;
        }

        return [$principal, $detalle];
    }
}
