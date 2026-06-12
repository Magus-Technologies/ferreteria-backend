<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Eliminar duplicados previos (conservar el más reciente) ──────
        // Antes de agregar el UNIQUE constraint, limpiar duplicados que ya
        // existen en producción (boletas con mismo serie+numero generadas
        // por race condition).
        $duplicados = DB::select("
            SELECT serie, numero, COUNT(*) as cnt,
                   GROUP_CONCAT(id ORDER BY created_at DESC) as ids
            FROM venta
            WHERE serie IS NOT NULL AND numero IS NOT NULL
            GROUP BY serie, numero
            HAVING cnt > 1
        ");

        foreach ($duplicados as $dup) {
            // Conservar el primer ID (el más reciente por ORDER BY created_at DESC),
            // eliminar el resto. Solo eliminar ventas en estado 'ee' (En Espera/borrador)
            // o ventas sin entregas asociadas para no romper datos críticos.
            $ids = explode(',', $dup->ids);
            $idMantener = array_shift($ids); // Primer ID = más reciente

            // Eliminar los duplicados más antiguos SOLO si están en borrador
            // o no tienen entregas asociadas (para seguridad).
            foreach ($ids as $idEliminar) {
                $tieneEntregas = DB::table('entrega')->where('venta_id', $idEliminar)->exists();
                $estadoVenta = DB::table('venta')->where('id', $idEliminar)->value('estado_de_venta');

                if (! $tieneEntregas && $estadoVenta === 'ee') {
                    // Primero eliminar hijos
                    DB::table('productoalmacenventa')->where('venta_id', $idEliminar)->delete();
                    DB::table('desplieguedepagoventa')->where('venta_id', $idEliminar)->delete();
                    DB::table('venta_historial')->where('venta_id', $idEliminar)->delete();
                    DB::table('venta')->where('id', $idEliminar)->delete();
                }
                // Si tiene entregas o no es borrador, no se elimina —
                // el usuario deberá limpiarlo manualmente.
            }
        }

        // ── 2. Verificar que no queden duplicados ───────────────────────────
        // Si quedan duplicados que NO se pudieron limpiar automáticamente
        // (ventas reales con entregas o ya facturadas), NO se puede agregar
        // el constraint — el ALTER TABLE fallaría y rompería el deploy.
        // En ese caso se omite el constraint y se deja constancia: el
        // SerieDocumentoService atómico ya previene nuevos duplicados a nivel
        // de aplicación. Tras limpiar manualmente, agregar el constraint con:
        //   ALTER TABLE venta ADD UNIQUE uq_venta_serie_numero (serie, numero);
        $restantes = DB::select("
            SELECT serie, numero, COUNT(*) as cnt
            FROM venta
            WHERE serie IS NOT NULL AND numero IS NOT NULL
            GROUP BY serie, numero
            HAVING cnt > 1
        ");

        if (! empty($restantes)) {
            $lista = collect($restantes)
                ->map(fn ($d) => "{$d->serie}-{$d->numero} (x{$d->cnt})")
                ->implode(', ');

            $mensaje = "uq_venta_serie_numero NO agregado: duplicados pendientes de limpieza manual: {$lista}";
            \Illuminate\Support\Facades\Log::warning($mensaje);
            echo "\nWARNING: {$mensaje}\n";

            return;
        }

        // ── 3. Agregar UNIQUE constraint ────────────────────────────────────
        // Safety net: aunque el servicio atómico previene la race condition a
        // nivel de aplicación, el constraint garantiza unicidad en la DB.
        Schema::table('venta', function (Blueprint $table) {
            $table->unique(['serie', 'numero'], 'uq_venta_serie_numero');
        });
    }

    public function down(): void
    {
        // El constraint puede no existir si up() lo omitió por duplicados
        $existe = DB::select("SHOW INDEX FROM venta WHERE Key_name = 'uq_venta_serie_numero'");
        if (! empty($existe)) {
            Schema::table('venta', function (Blueprint $table) {
                $table->dropUnique('uq_venta_serie_numero');
            });
        }
    }
};
