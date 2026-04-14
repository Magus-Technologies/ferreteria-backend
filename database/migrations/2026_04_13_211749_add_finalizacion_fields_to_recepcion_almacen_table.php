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
        Schema::table('recepcionalmacen', function (Blueprint $table) {
            $table->text('motivo_finalizacion')->nullable()->after('observaciones');
            $table->timestamp('fecha_finalizacion')->nullable()->after('motivo_finalizacion');
            $table->boolean('es_finalizacion')->default(false)->after('fecha_finalizacion');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('recepcionalmacen', function (Blueprint $table) {
            $table->dropColumn(['motivo_finalizacion', 'fecha_finalizacion', 'es_finalizacion']);
        });
    }
};
