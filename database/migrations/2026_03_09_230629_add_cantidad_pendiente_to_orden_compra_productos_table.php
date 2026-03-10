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
        Schema::table('orden_compra_productos', function (Blueprint $table) {
            $table->decimal('cantidad_pendiente', 9, 3)->after('cantidad')->default(0);
        });

        // Inicializar cantidad_pendiente con el valor de cantidad para registros existentes
        DB::table('orden_compra_productos')->update([
            'cantidad_pendiente' => DB::raw('cantidad')
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orden_compra_productos', function (Blueprint $table) {
            $table->dropColumn('cantidad_pendiente');
        });
    }
};
