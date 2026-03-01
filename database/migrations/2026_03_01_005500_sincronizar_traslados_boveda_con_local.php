<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Sincroniza producción para que sea IDÉNTICO al local del usuario.
     */
    public function up(): void
    {
        // 1. Eliminar foráneas para poder reordenar y cambiar reglas
        try {
            DB::statement("ALTER TABLE traslados_boveda DROP FOREIGN KEY traslados_boveda_vendedor_id_foreign");
        } catch (\Exception $e) {
        }
        try {
            DB::statement("ALTER TABLE traslados_boveda DROP FOREIGN KEY traslados_boveda_despliegue_pago_id_foreign");
        } catch (\Exception $e) {
        }

        // 2. Reordenar columnas para igualar al local
        Schema::table('traslados_boveda', function (Blueprint $table) {
            // Mover vendedor_id después de despliegue_pago_id
            $table->string('vendedor_id', 255)->after('despliegue_pago_id')->change();
        });

        // 3. Restaurar foráneas con las reglas exactas del local
        Schema::table('traslados_boveda', function (Blueprint $table) {
            // Restaurar vendedor_id con CASCADE
            $table->foreign('vendedor_id', 'traslados_boveda_vendedor_id_foreign')
                ->references('id')->on('user')
                ->onDelete('cascade');

            // Restaurar despliegue_pago_id con RESTRICT (como en local)
            $table->foreign('despliegue_pago_id', 'traslados_boveda_despliegue_pago_id_foreign')
                ->references('id')->on('desplieguedepago')
                ->onDelete('restrict');
        });
    }

    public function down(): void {}
};
