<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('traslados_boveda', function (Blueprint $table) {
            // sub_cajas.id es int(11) con signo en la DB del usuario
            if (!Schema::hasColumn('traslados_boveda', 'sub_caja_id')) {
                $table->integer('sub_caja_id')->nullable()->after('vendedor_id');
                $table->foreign('sub_caja_id')->references('id')->on('sub_cajas')->onDelete('cascade');
            }

            // desplieguedepago.id es varchar(191)
            if (!Schema::hasColumn('traslados_boveda', 'despliegue_pago_id')) {
                $table->string('despliegue_pago_id', 191)->nullable()->after('sub_caja_id');
                $table->foreign('despliegue_pago_id')->references('id')->on('desplieguedepago')->onDelete('restrict');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('traslados_boveda', function (Blueprint $table) {
            if (Schema::hasColumn('traslados_boveda', 'sub_caja_id')) {
                $table->dropForeign(['sub_caja_id']);
                $table->dropColumn('sub_caja_id');
            }
            if (Schema::hasColumn('traslados_boveda', 'despliegue_pago_id')) {
                $table->dropForeign(['despliegue_pago_id']);
                $table->dropColumn('despliegue_pago_id');
            }
        });
    }
};
