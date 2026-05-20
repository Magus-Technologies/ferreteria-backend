<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plantilla_impresion', function (Blueprint $table) {
            $table->dropColumn([
                'mensaje_cabecera',
                'cabecera_activo',
                'mensaje_inferior',
                'inferior_activo',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('plantilla_impresion', function (Blueprint $table) {
            $table->longText('mensaje_cabecera')->nullable()->after('empresa_id');
            $table->boolean('cabecera_activo')->default(false)->after('mensaje_cabecera');
            $table->longText('mensaje_inferior')->nullable()->after('cabecera_activo');
            $table->boolean('inferior_activo')->default(false)->after('mensaje_inferior');
        });
    }
};
