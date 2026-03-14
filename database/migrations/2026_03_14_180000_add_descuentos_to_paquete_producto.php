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
        Schema::table('paquete_producto', function (Blueprint $table) {
            $table->decimal('descuento_publico', 9, 4)->nullable()->default(0)->after('precio_ultimo');
            $table->decimal('descuento_especial', 9, 4)->nullable()->default(0)->after('descuento_publico');
            $table->decimal('descuento_minimo', 9, 4)->nullable()->default(0)->after('descuento_especial');
            $table->decimal('descuento_ultimo', 9, 4)->nullable()->default(0)->after('descuento_minimo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('paquete_producto', function (Blueprint $table) {
            $table->dropColumn([
                'descuento_publico',
                'descuento_especial',
                'descuento_minimo',
                'descuento_ultimo',
            ]);
        });
    }
};
