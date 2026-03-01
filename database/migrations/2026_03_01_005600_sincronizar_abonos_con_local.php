<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Sincroniza abonos_deuda_personal para que producción sea igual al local.
     */
    public function up(): void
    {
        // 1. Eliminar la foránea que está mal vinculada (apunta a 'users' y es bigint)
        try {
            DB::statement("ALTER TABLE abonos_deuda_personal DROP FOREIGN KEY abonos_deuda_personal_registrado_por_user_id_foreign");
        } catch (\Exception $e) {
        }

        // 2. Cambiar tipo de columna a VARCHAR para soportar los IDs de string
        Schema::table('abonos_deuda_personal', function (Blueprint $table) {
            $table->string('registrado_por_user_id', 191)->change();
        });

        // 3. Restaurar la foránea apuntando a la tabla 'user' (singular) con RESTRICT
        Schema::table('abonos_deuda_personal', function (Blueprint $table) {
            $table->foreign('registrado_por_user_id', 'abonos_deuda_personal_registrado_por_user_id_foreign')
                ->references('id')->on('user')
                ->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No revertir para mantener sincronización
    }
};
