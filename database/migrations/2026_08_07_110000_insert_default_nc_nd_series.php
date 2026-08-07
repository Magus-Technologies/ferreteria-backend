<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea las series por defecto de Notas de Crédito (BC01) y Notas de
     * Débito (BD01) para cada almacén activo que aún no las tenga.
     *
     * El frontend envía la serie hardcodeada BC01/BD01 al crear notas, y el
     * backend (NotaCreditoService::obtenerSerie / NotaDebitoService) valida
     * serie + tipo_documento + almacen_id + activo contra seriedocumento.
     */
    public function up(): void
    {
        if (!Schema::hasTable('seriedocumento')) {
            return;
        }

        $almacenes = DB::table('almacen')->where('activo', true)->pluck('id');

        foreach ($almacenes as $almacenId) {
            $this->crearSerieSiFalta($almacenId, 'nc', 'BC01');
            $this->crearSerieSiFalta($almacenId, 'nd', 'BD01');
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('seriedocumento')) {
            return;
        }

        DB::table('seriedocumento')
            ->whereIn('tipo_documento', ['nc', 'nd'])
            ->whereIn('serie', ['BC01', 'BD01'])
            ->delete();
    }

    private function crearSerieSiFalta(int $almacenId, string $tipoDocumento, string $serie): void
    {
        $existe = DB::table('seriedocumento')
            ->where('serie', $serie)
            ->where('tipo_documento', $tipoDocumento)
            ->where('almacen_id', $almacenId)
            ->exists();

        if (!$existe) {
            DB::table('seriedocumento')->insert([
                'tipo_documento' => $tipoDocumento,
                'serie' => $serie,
                'correlativo' => 0,
                'almacen_id' => $almacenId,
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
};
