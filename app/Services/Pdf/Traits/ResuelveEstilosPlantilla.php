<?php

namespace App\Services\Pdf\Traits;

use App\Models\PlantillaImpresion;

/**
 * Trait reutilizable para resolver los estilos globales y por bloque de una
 * PlantillaImpresion. Devuelve estructuras con CSS pre-generado listo para
 * inyectar en los blades.
 */
trait ResuelveEstilosPlantilla
{
    /**
     * Resuelve los estilos globales (color, fuente, tamaño base, etc.).
     */
    protected function resolverEstilos(array $estilos): array
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
     * Resuelve los estilos por bloque mezclando overrides con defaults globales.
     * Cada bloque incluye un CSS inline listo para usar.
     */
    protected function resolverEstilosBloques(array $est, array $overrides): array
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

    /**
     * Carga la plantilla, resuelve sus estilos y genera el CSS @font-face de las
     * fuentes personalizadas. Devuelve un array listo para mergear con el data
     * que se pasa al blade.
     *
     * @return array{plantilla: PlantillaImpresion, est: array, msg: array, bloques: array, font_face_css: string}
     */
    protected function prepararDatosPlantilla(int $empresaId, string $comprobante, string $formato): array
    {
        $plantilla = PlantillaImpresion::obtenerParaConFormato($empresaId, $comprobante, $formato);
        $est = $this->resolverEstilos($plantilla->estilos ?? []);
        $msg = array_merge(PlantillaImpresion::DEFAULT_MENSAJES_EXTRA, $plantilla->mensajes_extra ?? []);
        $bloques = $this->resolverEstilosBloques($est, $plantilla->estilos_secciones ?? []);
        $fontFaceCss = \App\Models\FuentePersonalizada::generarFontFaceCss(
            $empresaId,
            \App\Models\FuentePersonalizada::extraerFuentesUsadas(
                $plantilla->estilos ?? [],
                $plantilla->estilos_secciones ?? []
            )
        );

        return [
            'plantilla'     => $plantilla,
            'est'           => $est,
            'msg'           => $msg,
            'bloques'       => $bloques,
            'font_face_css' => $fontFaceCss,
        ];
    }
}
