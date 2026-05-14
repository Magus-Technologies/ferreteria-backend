<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cotizacion', function (Blueprint $table) {
            $table->unsignedBigInteger('recomendado_por_id')->nullable()->after('almacen_id');
        });
    }

    public function down(): void
    {
        Schema::table('cotizacion', function (Blueprint $table) {
            $table->dropForeign(['recomendado_por_id']);
            $table->dropColumn('recomendado_por_id');
        });
    }
};