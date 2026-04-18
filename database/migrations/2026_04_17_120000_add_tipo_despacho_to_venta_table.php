<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venta', function (Blueprint $table) {
            $table->enum('tipo_despacho', ['et', 'do', 'pa'])
                ->nullable()
                ->after('estado_de_venta')
                ->comment('et=En Tienda, do=Domicilio, pa=Parcial');
        });
    }

    public function down(): void
    {
        Schema::table('venta', function (Blueprint $table) {
            $table->dropColumn('tipo_despacho');
        });
    }
};
