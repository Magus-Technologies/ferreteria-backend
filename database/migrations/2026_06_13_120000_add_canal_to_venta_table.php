<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venta', function (Blueprint $table) {
            $table->enum('canal', ['presencial', 'web'])
                ->default('presencial')
                ->after('tipo_despacho')
                ->comment('Canal de venta: presencial (tienda) o web (ecommerce)');
        });
    }

    public function down(): void
    {
        Schema::table('venta', function (Blueprint $table) {
            $table->dropColumn('canal');
        });
    }
};
