<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entregaproducto', function (Blueprint $table) {
            $table->string('referencia_entrega')->nullable()->after('direccion_entrega');
        });
    }

    public function down(): void
    {
        Schema::table('entregaproducto', function (Blueprint $table) {
            $table->dropColumn('referencia_entrega');
        });
    }
};
