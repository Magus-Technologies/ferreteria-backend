<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requerimiento_interno_productos', function (Blueprint $table) {
            $table->decimal('cantidad_ordenada', 9, 3)->default(0)->after('cantidad_pendiente')->comment('Cantidad ya ordenada en OC');
            $table->string('orden_compra_codigo', 50)->nullable()->after('cantidad_ordenada')->comment('Código de la OC donde se usó');
            $table->unsignedBigInteger('orden_compra_id')->nullable()->after('orden_compra_codigo')->comment('ID de la OC');
        });
    }

    public function down(): void
    {
        Schema::table('requerimiento_interno_productos', function (Blueprint $table) {
            $table->dropColumn(['cantidad_ordenada', 'orden_compra_codigo', 'orden_compra_id']);
        });
    }
};
