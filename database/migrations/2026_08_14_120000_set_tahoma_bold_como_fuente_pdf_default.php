<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Deja Tahoma Bold como fuente predeterminada de TODOS los PDF, en 8pt.
 *
 * Se hace por migracion porque el deploy es automatico y no hay forma de
 * ejecutar comandos en el servidor. La migracion:
 *   1. Copia el TTF que viaja en el repo (resources/fonts) al storage.
 *   2. Lo registra en fuentes_personalizadas para cada empresa.
 *   3. Escribe el nivel base (plantilla_impresion), que heredan todos los
 *      comprobantes sin configuracion propia.
 *   4. Actualiza los comprobantes que si tengan configuracion propia, porque
 *      esos pisan al nivel base.
 *
 * Es idempotente: se puede volver a correr sin duplicar ni romper nada.
 */
return new class extends Migration
{
    /** Nombre con el que se referencia la fuente desde las plantillas. */
    private const FUENTE = 'tahoma-bold';

    private const TAMANO = 8;

    private const ARCHIVO_ORIGEN = 'TAHOMABD.TTF';

    public function up(): void
    {
        $origen = resource_path('fonts/' . self::ARCHIVO_ORIGEN);

        if (!is_file($origen)) {
            // Sin el archivo no tiene sentido registrar la fuente: las plantillas
            // apuntarian a algo inexistente y los PDF saldrian con la fuente de
            // respaldo. Se aborta sin romper el deploy.
            echo "  [fuente-pdf] No se encontro $origen, se omite la migracion.\n";
            return;
        }

        $empresas = DB::table('empresa')->pluck('id');

        foreach ($empresas as $empresaId) {
            $this->instalarFuente((int) $empresaId, $origen);
            $this->aplicarEnPlantillas((int) $empresaId);
        }
    }

    /**
     * Copia el TTF al storage de la empresa y lo registra, si aun no existe.
     */
    private function instalarFuente(int $empresaId, string $origen): void
    {
        $rutaRelativa = "fonts/{$empresaId}/" . self::FUENTE . '.ttf';
        $destino = Storage::disk('public')->path($rutaRelativa);

        if (!is_file($destino)) {
            @mkdir(dirname($destino), 0775, true);
            copy($origen, $destino);
        }

        $existente = DB::table('fuentes_personalizadas')
            ->where('empresa_id', $empresaId)
            ->whereRaw('LOWER(nombre) = ?', [self::FUENTE])
            ->first();

        if ($existente) {
            DB::table('fuentes_personalizadas')
                ->where('id', $existente->id)
                ->update([
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
     * Escribe la fuente y el tamano en los dos niveles de la herencia.
     */
    private function aplicarEnPlantillas(int $empresaId): void
    {
        // --- Nivel base: lo heredan los comprobantes sin configuracion propia
        $base = DB::table('plantilla_impresion')->where('empresa_id', $empresaId)->first();
        $estilosBase = $base && $base->estilos ? (json_decode($base->estilos, true) ?: []) : [];
        $estilosBase['fuente'] = self::FUENTE;
        $estilosBase['tamano_base'] = self::TAMANO;

        if ($base) {
            DB::table('plantilla_impresion')->where('id', $base->id)->update([
                'estilos'    => json_encode($estilosBase),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('plantilla_impresion')->insert([
                'empresa_id'       => $empresaId,
                'despedida_activo' => 1,
                'estilos'          => json_encode($estilosBase),
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);
        }

        // --- Detalles por comprobante: pisan al nivel base
        $detalles = DB::table('plantilla_impresion_detalles')
            ->where('empresa_id', $empresaId)
            ->get();

        foreach ($detalles as $detalle) {
            $estilos = $detalle->estilos ? (json_decode($detalle->estilos, true) ?: []) : [];
            $estilos['fuente'] = self::FUENTE;
            $estilos['tamano_base'] = self::TAMANO;

            // Un override de fuente por seccion tambien pisa al global, asi que
            // se alinea; los tamanos por seccion se respetan tal cual estan.
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
        // Migracion de datos: revertir dejaria las plantillas apuntando a una
        // fuente inexistente. Si hace falta volver atras, se cambia la fuente
        // desde la pantalla de Plantilla de Impresion.
    }
};
