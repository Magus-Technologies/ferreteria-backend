<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('paquete_producto', function (Blueprint $table) {
            $table->decimal('descuento_publico', 9, 4)->default(0)->after('tipo_precio');
            $table->decimal('descuento_especial', 9, 4)->default(0)->after('descuento_publico');
            $table->decimal('descuento_minimo', 9, 4)->default(0)->after('descuento_especial');
            $table->decimal('descuento_ultimo', 9, 4)->default(0)->after('descuento_minimo');
        });

        // Migrar datos existentes: copiar descuento actual al tipo correspondiente
        DB::statement("
            UPDATE paquete_producto SET
                descuento_publico = CASE WHEN tipo_precio = 'publico' THEN descuento ELSE 0 END,
                descuento_especial = CASE WHEN tipo_precio = 'especial' THEN descuento ELSE 0 END,
                descuento_minimo = CASE WHEN tipo_precio = 'minimo' THEN descuento ELSE 0 END,
                descuento_ultimo = CASE WHEN tipo_precio = 'ultimo' THEN descuento ELSE 0 END
        ");

        Schema::table('paquete_producto', function (Blueprint $table) {
            $table->dropColumn('descuento');
        });
    }

    public function down(): void
    {
        Schema::table('paquete_producto', function (Blueprint $table) {
            $table->decimal('descuento', 9, 4)->default(0)->after('tipo_precio');
        });

        // Restaurar: copiar el descuento del tipo activo
        DB::statement("
            UPDATE paquete_producto SET descuento = CASE tipo_precio
                WHEN 'publico' THEN descuento_publico
                WHEN 'especial' THEN descuento_especial
                WHEN 'minimo' THEN descuento_minimo
                WHEN 'ultimo' THEN descuento_ultimo
                ELSE 0
            END
        ");

        Schema::table('paquete_producto', function (Blueprint $table) {
            $table->dropColumn(['descuento_publico', 'descuento_especial', 'descuento_minimo', 'descuento_ultimo']);
        });
    }
};
