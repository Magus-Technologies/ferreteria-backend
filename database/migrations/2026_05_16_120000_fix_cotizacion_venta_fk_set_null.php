<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cotizacion', function (Blueprint $table) {
            $table->dropForeign('Cotizacion_venta_id_fkey');
            $table->foreign('venta_id')
                ->references('id')
                ->on('venta')
                ->onDelete('set null')
                ->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('cotizacion', function (Blueprint $table) {
            $table->dropForeign(['venta_id']);
            $table->foreign('venta_id')
                ->references('id')
                ->on('venta')
                ->onDelete('restrict')
                ->onUpdate('cascade');
        });
    }
};
