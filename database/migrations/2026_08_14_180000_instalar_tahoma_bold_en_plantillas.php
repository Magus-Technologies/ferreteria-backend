<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Instala Tahoma Bold como fuente de los PDF, en 8pt.
 *
 * A diferencia del primer intento (2026_08_14_120000, revertido por dejar la
 * emision de PDF en error 500), esta migracion NO crea la fila base de
 * plantilla_impresion: el valor por defecto se define en el codigo, en
 * PlantillaImpresion::DEFAULT_ESTILOS. Aqui solo se instala el archivo de la
 * fuente y se alinean los comprobantes que ya tienen configuracion propia.
 *
 * Es idempotente.
 */
return new class extends Migration
{
    private const FUENTE = 'tahoma-bold';

    private const TAMANO = 8;

    private const ARCHIVO_ORIGEN = 'TAHOMABD.TTF';

    public function up(): void
    {
        $origen = resource_path('fonts/' . self::ARCHIVO_ORIGEN);

        if (!is_file($origen)) {
            echo "  [fuente-pdf] No se encontro $origen, se omite.\n";
            return;
        }

        $this->prepararDirectorioDeFuentes();

        foreach (DB::table('empresa')->pluck('id') as $empresaId) {
            $this->instalarArchivo((int) $empresaId, $origen);
            $this->alinearDetalles((int) $empresaId);
        }
    }

    /**
     * Dompdf escribe el cache de cada fuente (.ufm) en su font_dir. El deploy
     * corre como root, asi que si el directorio se crea aqui queda a nombre de
     * root y despues php-fpm (nginx/www-data) no puede escribir: los PDF
     * revientan con "Permission denied".
     *
     * Se crea el directorio y se le copia dueno y permisos de storage/, que ya
     * pertenece al usuario correcto.
     */
    private function prepararDirectorioDeFuentes(): void
    {
        $fontDir = config('dompdf.options.font_dir') ?: storage_path('fonts');
        $referencia = storage_path();

        if (!is_dir($fontDir)) {
            @mkdir($fontDir, 0775, true);
        }

        if (!is_dir($fontDir) || !is_dir($referencia)) {
            return;
        }

        // Solo tiene efecto si el proceso puede cambiar el dueno (root).
        if (function_exists('fileowner') && function_exists('chown')) {
            $uid = @fileowner($referencia);
            $gid = @filegroup($referencia);
            if ($uid !== false) @chown($fontDir, $uid);
            if ($gid !== false) @chgrp($fontDir, $gid);
        }

        @chmod($fontDir, 0775);

        // Los .ufm ya escritos por root tambien deben quedar accesibles
        foreach (glob(rtrim($fontDir, '/\\') . DIRECTORY_SEPARATOR . '*') ?: [] as $archivo) {
            if (isset($uid) && $uid !== false) @chown($archivo, $uid);
            if (isset($gid) && $gid !== false) @chgrp($archivo, $gid);
            @chmod($archivo, 0664);
        }
    }

    /**
     * Copia el TTF al storage de la empresa y lo registra en el catalogo.
     */
    private function instalarArchivo(int $empresaId, string $origen): void
    {
        $rutaRelativa = "fonts/{$empresaId}/" . self::FUENTE . '.ttf';
        $destino = Storage::disk('public')->path($rutaRelativa);

        if (!is_file($destino)) {
            @mkdir(dirname($destino), 0775, true);
            if (!@copy($origen, $destino)) {
                echo "  [fuente-pdf] No se pudo copiar la fuente a $destino\n";
                return;
            }
        }

        $existente = DB::table('fuentes_personalizadas')
            ->where('empresa_id', $empresaId)
            ->whereRaw('LOWER(nombre) = ?', [self::FUENTE])
            ->first();

        if ($existente) {
            DB::table('fuentes_personalizadas')->where('id', $existente->id)->update([
                'archivo_path' => $rutaRelativa,
                'tipo_mime'    => 'font/ttf',
                'updated_at'   => now(),
            ]);
            return;
        }

        DB::table('fuentes_personalizadas')->insert([
            'empresa_id'       => $empresaId,
            'nombre'           => self::FUENTE,
            'archivo_original' => self::ARCHIVO_ORIGEN,
            'archivo_path'     => $rutaRelativa,
            'tipo_mime'        => 'font/ttf',
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);
    }

    /**
     * Los comprobantes con configuracion propia pisan al valor por defecto del
     * codigo, asi que hay que actualizarlos explicitamente.
     */
    private function alinearDetalles(int $empresaId): void
    {
        $detalles = DB::table('plantilla_impresion_detalles')
            ->where('empresa_id', $empresaId)
            ->get();

        foreach ($detalles as $detalle) {
            $estilos = $detalle->estilos ? (json_decode($detalle->estilos, true) ?: []) : [];
            $estilos['fuente'] = self::FUENTE;
            $estilos['tamano_base'] = self::TAMANO;

            $secciones = $detalle->estilos_secciones
                ? (json_decode($detalle->estilos_secciones, true) ?: [])
                : [];
            foreach ($secciones as $key => $seccion) {
                if (is_array($seccion) && !empty($seccion['fuente'])) {
                    $secciones[$key]['fuente'] = self::FUENTE;
                }
            }

            DB::table('plantilla_impresion_detalles')->where('id', $detalle->id)->update([
                'estilos'           => json_encode($estilos),
                'estilos_secciones' => json_encode($secciones),
                'updated_at'        => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Migracion de datos: no se revierte.
    }
};
