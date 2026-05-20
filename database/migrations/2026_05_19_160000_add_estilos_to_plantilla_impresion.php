<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plantilla_impresion', function (Blueprint $table) {
            $table->json('estilos')->nullable()->after('logos_nota_venta');
            $table->json('mensajes_extra')->nullable()->after('estilos');
        });
    }

    public function down(): void
    {
        Schema::table('plantilla_impresion', function (Blueprint $table) {
            $table->dropColumn(['estilos', 'mensajes_extra']);
        });
    }
};
