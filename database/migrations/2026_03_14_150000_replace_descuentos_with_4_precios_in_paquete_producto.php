<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('paquete_producto', function (Blueprint $table) {
            // Agregar 4 columnas de precio
            $table->decimal('precio_publico', 9, 4)->nullable()->after('cantidad');
            $table->decimal('precio_especial', 9, 4)->nullable()->after('precio_publico');
            $table->decimal('precio_minimo', 9, 4)->nullable()->after('precio_especial');
            $table->decimal('precio_ultimo', 9, 4)->nullable()->after('precio_minimo');
        });

        // Migrar precio_sugerido al tipo correspondiente
        \Illuminate\Support\Facades\DB::statement("
            UPDATE paquete_producto SET
                precio_publico = CASE WHEN tipo_precio = 'publico' THEN precio_sugerido ELSE NULL END,
                precio_especial = CASE WHEN tipo_precio = 'especial' THEN precio_sugerido ELSE NULL END,
                precio_minimo = CASE WHEN tipo_precio = 'minimo' THEN precio_sugerido ELSE NULL END,
                precio_ultimo = CASE WHEN tipo_precio = 'ultimo' THEN precio_sugerido ELSE NULL END
        ");

        Schema::table('paquete_producto', function (Blueprint $table) {
            // Eliminar columnas viejas
            $table->dropColumn([
                'precio_sugerido',
                'tipo_precio',
                'descuento_publico',
                'descuento_especial',
                'descuento_minimo',
                'descuento_ultimo',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('paquete_producto', function (Blueprint $table) {
            $table->decimal('precio_sugerido', 9, 4)->nullable()->after('cantidad');
            $table->string('tipo_precio', 20)->default('publico')->after('precio_sugerido');
            $table->decimal('descuento_publico', 9, 4)->default(0)->after('tipo_precio');
            $table->decimal('descuento_especial', 9, 4)->default(0)->after('descuento_publico');
            $table->decimal('descuento_minimo', 9, 4)->default(0)->after('descuento_especial');
            $table->decimal('descuento_ultimo', 9, 4)->default(0)->after('descuento_minimo');
        });

        // Restaurar precio_sugerido desde precio_publico
        \Illuminate\Support\Facades\DB::statement("
            UPDATE paquete_producto SET
                precio_sugerido = COALESCE(precio_publico, precio_especial, precio_minimo, precio_ultimo),
                tipo_precio = 'publico'
        ");

        Schema::table('paquete_producto', function (Blueprint $table) {
            $table->dropColumn(['precio_publico', 'precio_especial', 'precio_minimo', 'precio_ultimo']);
        });
    }
};
