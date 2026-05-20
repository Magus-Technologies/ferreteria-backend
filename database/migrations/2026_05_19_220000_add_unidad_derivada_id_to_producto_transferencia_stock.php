<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('producto_transferencia_stock', function (Blueprint $table) {
            $table->unsignedBigInteger('unidad_derivada_id')->nullable()->after('unidad_derivada_inmutable_id');
        });
    }

    public function down(): void
    {
        Schema::table('producto_transferencia_stock', function (Blueprint $table) {
            $table->dropColumn('unidad_derivada_id');
        });
    }
};
