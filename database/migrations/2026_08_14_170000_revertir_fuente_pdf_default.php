<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Revierte la migracion 2026_08_14_120000 (Tahoma Bold como fuente por defecto),
 * que dejo la generacion de PDF en error 500 en produccion.
 *
 * Deshace los dos efectos:
 *   1. Elimina la fila base de plantilla_impresion SOLO si la creo aquella
 *      migracion (es decir, si no tiene ningun otro contenido configurado).
 *      Antes no existia, y su ausencia hacia que se usaran los valores por
 *      defecto del modelo.
 *   2. Devuelve las plantillas por comprobante a la fuente Helvetica, que es
 *      la integrada en Dompdf y no depende de ningun archivo.
 */
return new class extends Migration
{
    private const FUENTE_ROTA = 'tahoma-bold';

    private const FUENTE_SEGURA = 'Helvetica';

    public function up(): void
    {
        // 1) Quitar la fila base que introdujo la migracion anterior.
        //    Solo se borra si esta "vacia": si alguien ya configuro algo ahi
        //    despues, se respeta y unicamente se corrige la fuente.
        $bases = DB::table('plantilla_impresion')->get();

        foreach ($bases as $base) {
            $estilos = $base->estilos ? (json_decode($base->estilos, true) ?: []) : [];
            $soloFuenteYTamano = empty(array_diff(array_keys($estilos), ['fuente', 'tamano_base']));

            $sinOtroContenido = $soloFuenteYTamano
                && empty($base->mensaje_despedida)
                && empty($base->logos_nota_venta)
                && empty($base->mensajes_extra)
                && empty($base->estilos_secciones);

            if ($sinOtroContenido) {
                DB::table('plantilla_impresion')->where('id', $base->id)->delete();
                continue;
            }

            if (($estilos['fuente'] ?? null) === self::FUENTE_ROTA) {
                $estilos['fuente'] = self::FUENTE_SEGURA;
                DB::table('plantilla_impresion')->where('id', $base->id)->update([
                    'estilos'    => json_encode($estilos),
                    'updated_at' => now(),
                ]);
            }
        }

        // 2) Volver a una fuente integrada en los comprobantes configurados.
        $detalles = DB::table('plantilla_impresion_detalles')->get();

        foreach ($detalles as $detalle) {
            $estilos = $detalle->estilos ? (json_decode($detalle->estilos, true) ?: []) : [];
            $secciones = $detalle->estilos_secciones
                ? (json_decode($detalle->estilos_secciones, true) ?: [])
                : [];

            $cambio = false;

            if (($estilos['fuente'] ?? null) === self::FUENTE_ROTA) {
                $estilos['fuente'] = self::FUENTE_SEGURA;
                $cambio = true;
            }

            foreach ($secciones as $key => $seccion) {
                if (is_array($seccion) && ($seccion['fuente'] ?? null) === self::FUENTE_ROTA) {
                    $secciones[$key]['fuente'] = self::FUENTE_SEGURA;
                    $cambio = true;
                }
            }

            if ($cambio) {
                DB::table('plantilla_impresion_detalles')->where('id', $detalle->id)->update([
                    'estilos'           => json_encode($estilos),
                    'estilos_secciones' => json_encode($secciones),
                    'updated_at'        => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Migracion correctiva: no se revierte.
    }
};
