<?php

namespace App\Services\Pdf;

use App\Models\Cotizacion;
use App\Models\FuentePersonalizada;
use App\Models\PlantillaImpresion;
use Illuminate\Http\Response;

class CotizacionPdfService
{
    public function generar(string $cotizacionId, string $formato = 'a4'): Response
    {
        $cotizacion = $this->obtenerCotizacion($cotizacionId);
        $empresa = $cotizacion->user->empresa;

        $productos = $this->prepararProductos($cotizacion);
        $calculos = $this->calcularTotales($productos);

        if ($formato === 'ticket') {
            return $this->generarTicket($cotizacion, $empresa, $productos, $calculos);
        }

        $plantilla = PlantillaImpresion::obtenerParaConFormato((int) $empresa->id, 'cotizacion', 'A4');
        $est = $this->resolverEstilos($plantilla->estilos ?? []);
        $msg = array_merge(PlantillaImpresion::DEFAULT_MENSAJES_EXTRA, $plantilla->mensajes_extra ?? []);
        $bloques = $this->resolverEstilosBloques($est, $plantilla->estilos_secciones ?? []);
        $fontFaceCss = FuentePersonalizada::generarFontFaceCss(
            (int) $empresa->id,
            FuentePersonalizada::extraerFuentesUsadas($plantilla->estilos ?? [], $plantilla->estilos_secciones ?? [])
        );
        $observaciones = $cotizacion->observaciones ?: $this->observacionesDefault();

        $data = [
            'cotizacion' => $cotizacion,
            'empresa' => $empresa,
            'logoPath' => PdfService::getLogoPath($empresa->logo),
            'tipoDocumentoTitulo' => 'Proforma',
            'numeroDocumento' => $cotizacion->numero,
            'productos' => $productos,
            'calculos' => $calculos,
            'filas' => $this->prepararInfoCliente($cotizacion),
            'son' => PdfService::numeroALetras($calculos['total']),
            'moneda' => $cotizacion->tipo_moneda?->value === 'Soles' ? 'SOL' : 'USD',
            'observaciones' => $observaciones,
            'plantilla' => $plantilla,
            'est' => $est,
            'msg' => $msg,
            'bloques' => $bloques,
            'font_face_css' => $fontFaceCss,
        ];

        $filename = "COTIZACION-{$cotizacion->numero}.pdf";

        return PdfService::render('pdf.cotizacion', $data, $filename);
    }

    private function generarTicket($cotizacion, $empresa, array $productos, array $calculos): Response
    {
        $cliente = $cotizacion->cliente;
        $clienteNombre = $cliente?->razon_social
            ?: trim(($cliente?->nombres ?? '') . ' ' . ($cliente?->apellidos ?? ''))
            ?: 'CLIENTE GENERAL';

        $direccion = $cotizacion->direccion ?? $cliente?->direccion ?? '';

        $plantilla = PlantillaImpresion::obtenerParaConFormato((int) $empresa->id, 'cotizacion', 'Ticket');
        $est = $this->resolverEstilos($plantilla->estilos ?? []);
        $msg = array_merge(PlantillaImpresion::DEFAULT_MENSAJES_EXTRA, $plantilla->mensajes_extra ?? []);
        $bloques = $this->resolverEstilosBloques($est, $plantilla->estilos_secciones ?? []);
        $fontFaceCss = FuentePersonalizada::generarFontFaceCss(
            (int) $empresa->id,
            FuentePersonalizada::extraerFuentesUsadas($plantilla->estilos ?? [], $plantilla->estilos_secciones ?? [])
        );
        $observaciones = $cotizacion->observaciones ?: $this->observacionesDefault();

        $data = [
            'titulo' => "Ticket {$cotizacion->numero}",
            'cotizacion' => $cotizacion,
            'empresa' => $empresa,
            'logoPath' => PdfService::getLogoPath($empresa->logo),
            'tipoDocumentoTitulo' => 'PROFORMA ELECTRÓNICA',
            'numeroDocumento' => $cotizacion->numero,
            'productos' => $productos,
            'calculos' => $calculos,
            'clienteNombre' => $clienteNombre,
            'clienteDocumento' => $cliente?->numero_documento ?? '99999999',
            'clienteDireccion' => $direccion,
            'vendedor' => $cotizacion->user->name,
            'son' => PdfService::numeroALetras($calculos['total']),
            'observaciones' => $observaciones,
            'plantilla' => $plantilla,
            'est' => $est,
            'msg' => $msg,
            'bloques' => $bloques,
            'font_face_css' => $fontFaceCss,
        ];

        $filename = "TICKET-{$cotizacion->numero}.pdf";

        // 80mm = 226.77pt
        return PdfService::render(
            'pdf.cotizacion-ticket',
            $data,
            $filename,
            'portrait',
            [0, 0, 226.77, 841.89],
        );
    }

    private function obtenerCotizacion(string $cotizacionId): Cotizacion
    {
        return Cotizacion::with([
            'user.empresa',
            'cliente',
            'almacen',
            'productosPorAlmacen.productoAlmacen.producto.marca',
            'productosPorAlmacen.unidadesDerivadas.unidadDerivadaInmutable',
        ])->findOrFail($cotizacionId);
    }

    private function prepararProductos(Cotizacion $cotizacion): array
    {
        $productos = [];

        foreach ($cotizacion->productosPorAlmacen as $pa) {
            $producto = $pa->productoAlmacen->producto;

            foreach ($pa->unidadesDerivadas as $ud) {
                $cantidad = (float) $ud->cantidad;
                $precio = (float) ($ud->precio ?? 0);
                $recargo = (float) ($ud->recargo ?? 0);
                $descuento = (float) ($ud->descuento ?? 0);
                $subtotal = ($precio + $recargo) * $cantidad - $descuento;

                $productos[] = [
                    'codigo' => $producto->cod_producto ?? '',
                    'nombre' => $producto->name,
                    'marca' => $producto->marca->name ?? '',
                    'unidad' => $ud->unidadDerivadaInmutable->name ?? '',
                    'cantidad' => $cantidad,
                    'precio' => $precio + $recargo,
                    'descuento' => $descuento,
                    'subtotal' => $subtotal,
                ];
            }
        }

        return $productos;
    }

    private function calcularTotales(array $productos): array
    {
        $subtotal = array_sum(array_column($productos, 'subtotal'));
        $totalDescuento = array_sum(array_column($productos, 'descuento'));
        $total = $subtotal - $totalDescuento;

        return [
            'subtotal' => $subtotal,
            'total_descuento' => $totalDescuento,
            'total' => $total,
        ];
    }

    private function prepararInfoCliente(Cotizacion $cotizacion): array
    {
        $cliente = $cotizacion->cliente;
        $clienteNombre = $cliente?->razon_social
            ?: trim(($cliente?->nombres ?? '') . ' ' . ($cliente?->apellidos ?? ''))
            ?: 'CLIENTE GENERAL';

        $moneda = $cotizacion->tipo_moneda?->value === 's' ? 'SOL' : 'USD';

        $filas = [
            [
                'Cliente' => $clienteNombre,
                'F. Emision' => PdfService::formatFecha($cotizacion->fecha),
            ],
            [
                'Direccion' => $cotizacion->direccion ?? $cliente?->direccion ?? '',
                'Hora' => PdfService::formatFecha($cotizacion->fecha, 'H:i:s'),
            ],
            [
                'Vendedor' => $cotizacion->user->name,
                'N Guia' => '',
            ],
            [
                'Forma Pago' => $cotizacion->forma_de_pago ?? 'Contado',
                'Moneda' => $moneda,
            ],
        ];

        // Insertar RUC/DNI + F. Vencimiento antes de Vendedor (índice 2)
        $filaDocumento = ['RUC / DNI' => $cliente?->numero_documento ?? ''];
        if (!$this->esContado($cotizacion->forma_de_pago)) {
            $filaDocumento['F. Vencimiento'] = PdfService::formatFecha($cotizacion->fecha_vencimiento);
        }
        array_splice($filas, 2, 0, [$filaDocumento]);

        return $filas;
    }

    private function esContado(?string $formaDePago): bool
    {
        if (empty($formaDePago)) {
            return true;
        }
        return stripos($formaDePago, 'contado') !== false;
    }

    private function observacionesDefault(): string
    {
        return "- LA MERCADERIA VIAJA POR CUENTA Y RIESGO DEL CLIENTE.\n"
            . "- UNA VEZ RECIBIDA LA MERCADERIA NO HAY LUGAR A RECLAMO.\n"
            . "- REPARTO MINIMO 1 DIA DE ANTICIPACION.\n"
            . "- CAMBIO Y/O DEVOLUCION SERA EN UN PLAZO 5 DIAS CALENDARIO.\n"
            . "- NO SE ACEPTAN CAMBIOS NI DEVOLUCIONES CON DANOS FISICOS O ACCESORIOS FALTANTES, SOLO POR FALLAS DE FABRICACION.";
    }

    /**
     * Resolver los estilos del PDF mezclando los del usuario con los defaults.
     */
    private function resolverEstilos(array $estilos): array
    {
        $e = array_merge(PlantillaImpresion::DEFAULT_ESTILOS, $estilos);

        $densidad = $e['densidad'] ?? 'normal';
        $padMul = $densidad === 'compacta' ? 0.7 : ($densidad === 'espaciada' ? 1.4 : 1.0);

        $fontPt = (int) ($e['tamano_base'] ?? 8);
        $borderPx = (int) ($e['grosor_borde'] ?? 2);

        return [
            'color_tema'  => $e['color_tema'],
            'color_borde' => $e['color_borde'],
            'color_texto' => $e['color_texto'],
            'fuente'      => $e['fuente'],
            'font_pt'     => $fontPt,
            'font_sm_pt'  => max(6, $fontPt - 1),
            'font_lg_pt'  => $fontPt + 2,
            'border_px'   => $borderPx,
            'border_thin_px' => max(1, (int) round($borderPx / 2)),
            'pad_px'      => (int) round(4 * $padMul),
            'pad_lg_px'   => (int) round(6 * $padMul),
            'densidad'    => $densidad,
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
