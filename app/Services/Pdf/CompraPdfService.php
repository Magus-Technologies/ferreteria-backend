<?php

namespace App\Services\Pdf;

use App\Models\Compra;
use App\Models\FuentePersonalizada;
use App\Models\PlantillaImpresion;
use Illuminate\Http\Response;

class CompraPdfService
{
    public function generar(string $compraId): Response
    {
        $compra = $this->obtenerCompra($compraId);
        $empresa = $compra->user->empresa;

        $productos = $this->prepararProductos($compra);
        $calculos = $this->calcularTotales($compra, $productos);

        $plantilla = PlantillaImpresion::obtenerParaConFormato((int) $empresa->id, 'compra', 'A4');
        $est = $this->resolverEstilos($plantilla->estilos ?? []);
        $msg = array_merge(PlantillaImpresion::DEFAULT_MENSAJES_EXTRA, $plantilla->mensajes_extra ?? []);
        $bloques = $this->resolverEstilosBloques($est, $plantilla->estilos_secciones ?? []);
        $fontFaceCss = FuentePersonalizada::generarFontFaceCss(
            (int) $empresa->id,
            FuentePersonalizada::extraerFuentesUsadas($plantilla->estilos ?? [], $plantilla->estilos_secciones ?? [])
        );
        $observaciones = $compra->descripcion ?: $msg['observaciones_default'];
        $consultaUrl = rtrim(env('FRONTEND_URL', 'http://localhost:3000'), '/') . '/consulta';

        $data = [
            'compra' => $compra,
            'empresa' => $empresa,
            'logoPath' => PdfService::getLogoPath($empresa->logo),
            'tipoDocumentoTitulo' => $this->getTituloDocumento($compra->tipo_documento->value ?? null),
            'numeroDocumento' => $this->formatNumeroDocumento($compra),
            'productos' => $productos,
            'calculos' => $calculos,
            'filas' => $this->prepararInfoProveedor($compra, $calculos),
            'son' => PdfService::numeroALetras($calculos['subtotal_bruto']),
            'observaciones' => $observaciones,
            'plantilla' => $plantilla,
            'est' => $est,
            'msg' => $msg,
            'bloques' => $bloques,
            'consultaUrl' => $consultaUrl,
            'font_face_css' => $fontFaceCss,
        ];

        $filename = "Compra-{$compra->serie}-{$compra->numero}.pdf";

        return PdfService::render('pdf.compra', $data, $filename);
    }

    private function obtenerCompra(string $compraId): Compra
    {
        return Compra::with([
            'user.empresa',
            'proveedor',
            'almacen',
            'productosPorAlmacen.productoAlmacen.producto.marca',
            'productosPorAlmacen.productoAlmacen.producto.unidadMedida',
            'productosPorAlmacen.unidadesDerivadas.unidadDerivadaInmutable',
            'pagosDeCompras',
        ])->findOrFail($compraId);
    }

    private function prepararProductos(Compra $compra): array
    {
        $productos = [];

        foreach ($compra->productosPorAlmacen as $pa) {
            $producto = $pa->productoAlmacen->producto;
            $costo = (float) $pa->costo;

            foreach ($pa->unidadesDerivadas as $ud) {
                $cantidad = (float) $ud->cantidad;
                $factor = (float) $ud->factor;
                $subtotal = $cantidad * $factor * $costo;

                $productos[] = [
                    'codigo' => $producto->cod_producto ?? '',
                    'nombre' => $producto->name,
                    'marca' => $producto->marca->name ?? '',
                    'unidad' => $ud->unidadDerivadaInmutable->name ?? '',
                    'cantidad' => $cantidad,
                    'costo' => $costo,
                    'subtotal' => $subtotal,
                ];
            }
        }

        return $productos;
    }

    private function calcularTotales(Compra $compra, array $productos): array
    {
        $subtotalBruto = array_sum(array_column($productos, 'subtotal'));
        $subtotal = $subtotalBruto / 1.18;
        $igv = $subtotalBruto - $subtotal;

        $totalPagado = $compra->pagosDeCompras
            ->sum(fn ($pago) => (float) $pago->monto);

        $deuda = $subtotalBruto - $totalPagado;

        return [
            'subtotal_bruto' => $subtotalBruto,
            'subtotal' => $subtotal,
            'igv' => $igv,
            'total' => $subtotalBruto,
            'total_pagado' => $totalPagado,
            'deuda' => $deuda,
        ];
    }

    private function prepararInfoProveedor(Compra $compra, array $calculos): array
    {
        $proveedor = $compra->proveedor;
        $fecha = $compra->fecha;

        return [
            [
                'TIPO DOC' => $compra->tipo_documento->value ?? '',
                'FORMA PAGO' => match($compra->forma_de_pago) {
                    \App\Enums\FormaDePago::Contado => 'Contado',
                    \App\Enums\FormaDePago::Credito => 'Crédito',
                    default => $compra->forma_de_pago->value ?? '',
                },
            ],
            [
                'PROVEEDOR' => ($proveedor?->ruc ?? '') . ' ' . ($proveedor?->razon_social ?? ''),
                'TOTAL PAGADO' => number_format($calculos['total_pagado'], 2),
            ],
            [
                'FECHA' => PdfService::formatFecha($fecha) . ' ' . PdfService::formatFecha($fecha, 'h:i:s A'),
                'DEUDA' => number_format($calculos['deuda'], 2),
            ],
            [
                'ALMACEN' => $compra->almacen->name ?? 'N/A',
                'USUARIO' => $compra->user->name ?? 'N/A',
            ],
        ];
    }

    private function formatNumeroDocumento(Compra $compra): string
    {
        $serie = $compra->serie ?: '001';
        $numero = str_pad($compra->numero ?? '0', 6, '0', STR_PAD_LEFT);

        return "{$serie}-{$numero}";
    }

    private function getTituloDocumento(?string $tipo): string
    {
        return match ($tipo) {
            '01' => 'FACTURA DE COMPRA',
            '03' => 'BOLETA DE COMPRA',
            'nv' => 'NOTA DE VENTA',
            default => 'COMPROBANTE DE COMPRA',
        };
    }

    /**
     * Resolver los estilos globales de la plantilla a valores listos para CSS.
     */
    private function resolverEstilos(array $estilos): array
    {
        $e = array_merge(PlantillaImpresion::DEFAULT_ESTILOS, $estilos);

        $densidad = $e['densidad'] ?? 'normal';
        $padMul = $densidad === 'compacta' ? 0.7 : ($densidad === 'espaciada' ? 1.4 : 1.0);

        $fontPt = (int) ($e['tamano_base'] ?? 8);
        $borderPx = (int) ($e['grosor_borde'] ?? 2);

        return [
            'color_tema'     => $e['color_tema'],
            'color_borde'    => $e['color_borde'],
            'color_texto'    => $e['color_texto'],
            'fuente'         => $e['fuente'],
            'font_pt'        => $fontPt,
            'font_sm_pt'     => max(6, $fontPt - 1),
            'font_lg_pt'     => $fontPt + 2,
            'border_px'      => $borderPx,
            'border_thin_px' => max(1, (int) round($borderPx / 2)),
            'pad_px'         => (int) round(4 * $padMul),
            'pad_lg_px'      => (int) round(6 * $padMul),
            'densidad'       => $densidad,
        ];
    }

    /**
     * Resolver los estilos por bloque mezclando overrides con defaults globales.
     */
    private function resolverEstilosBloques(array $est, array $overrides): array
    {
        $fg = $est['fuente'];
        $defaultsPorBloque = [
            'empresa_razon'     => ['color' => $est['color_texto'], 'tamano' => $est['font_lg_pt'], 'peso' => 'bold',   'alineacion' => 'center', 'fuente' => $fg],
            'empresa_direccion' => ['color' => $est['color_texto'], 'tamano' => $est['font_sm_pt'], 'peso' => 'normal', 'alineacion' => 'center', 'fuente' => $fg],
            'caja_ruc'          => ['color' => $est['color_texto'], 'tamano' => $est['font_lg_pt'], 'peso' => 'bold',   'alineacion' => 'center', 'fuente' => $fg],
            'caja_tipo'         => ['color' => $est['color_texto'], 'tamano' => $est['font_lg_pt'] + 1, 'peso' => 'bold', 'alineacion' => 'center', 'fuente' => $fg],
            'caja_numero'       => ['color' => $est['color_texto'], 'tamano' => $est['font_lg_pt'] + 1, 'peso' => 'bold', 'alineacion' => 'center', 'fuente' => $fg],
            'info_label'        => ['color' => $est['color_texto'], 'tamano' => $est['font_sm_pt'], 'peso' => 'bold',   'alineacion' => 'left',   'fuente' => $fg],
            'info_valor'        => ['color' => $est['color_texto'], 'tamano' => $est['font_sm_pt'], 'peso' => 'normal', 'alineacion' => 'left',   'fuente' => $fg],
            'tabla_header'      => ['color' => $est['color_texto'], 'tamano' => $est['font_sm_pt'], 'peso' => 'bold',   'alineacion' => 'center', 'fuente' => $fg],
            'tabla_fila'        => ['color' => $est['color_texto'], 'tamano' => $est['font_sm_pt'], 'peso' => 'normal', 'alineacion' => 'left',   'fuente' => $fg],
            'son'               => ['color' => $est['color_texto'], 'tamano' => $est['font_sm_pt'], 'peso' => 'bold',   'alineacion' => 'left',   'fuente' => $fg],
            'obs_label'         => ['color' => $est['color_texto'], 'tamano' => $est['font_pt'],    'peso' => 'bold',   'alineacion' => 'left',   'fuente' => $fg],
            'obs_valor'         => ['color' => $est['color_texto'], 'tamano' => max(6, $est['font_sm_pt'] - 1), 'peso' => 'normal', 'alineacion' => 'left', 'fuente' => $fg],
            'total_label'       => ['color' => $est['color_texto'], 'tamano' => $est['font_pt'],    'peso' => 'bold',   'alineacion' => 'right',  'fuente' => $fg],
            'total_valor'       => ['color' => $est['color_texto'], 'tamano' => $est['font_pt'],    'peso' => 'normal', 'alineacion' => 'right',  'fuente' => $fg],
            'despedida_footer'  => ['color' => $est['color_texto'], 'tamano' => $est['font_pt'],    'peso' => 'bold',   'alineacion' => 'center', 'fuente' => $fg],
            'consulta_leyenda'  => ['color' => '#666666',           'tamano' => $est['font_sm_pt'], 'peso' => 'normal', 'alineacion' => 'center', 'fuente' => $fg],
            'consulta_url'      => ['color' => '#333333',           'tamano' => $est['font_sm_pt'], 'peso' => 'bold',   'alineacion' => 'center', 'fuente' => $fg],
        ];

        $resultado = [];
        foreach ($defaultsPorBloque as $key => $def) {
            $ov = $overrides[$key] ?? [];
            $resultado[$key] = [
                'color'      => !empty($ov['color']) ? $ov['color'] : $def['color'],
                'tamano'     => !empty($ov['tamano']) ? (int) $ov['tamano'] : $def['tamano'],
                'peso'       => !empty($ov['peso']) ? $ov['peso'] : $def['peso'],
                'alineacion' => !empty($ov['alineacion']) ? $ov['alineacion'] : $def['alineacion'],
                'fuente'     => !empty($ov['fuente']) ? $ov['fuente'] : $def['fuente'],
                'cursiva'    => isset($ov['cursiva']) ? (bool) $ov['cursiva'] : false,
                'subrayado'  => isset($ov['subrayado']) ? (bool) $ov['subrayado'] : false,
            ];
            $resultado[$key]['css'] = sprintf(
                'color: %s; font-size: %dpt; font-weight: %s; text-align: %s; font-family: "%s", Arial, sans-serif; font-style: %s; text-decoration: %s;',
                $resultado[$key]['color'],
                $resultado[$key]['tamano'],
                $resultado[$key]['peso'],
                $resultado[$key]['alineacion'],
                $resultado[$key]['fuente'],
                $resultado[$key]['cursiva'] ? 'italic' : 'normal',
                $resultado[$key]['subrayado'] ? 'underline' : 'none'
            );
        }

        return $resultado;
    }
}
