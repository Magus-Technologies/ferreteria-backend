<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productoalmacenunidadderivada', function (Blueprint $table) {
            if (!Schema::hasColumn('productoalmacenunidadderivada', 'peso')) {
                // Peso por unidad derivada en kilogramos. Permite que una caja
                // y una unidad del mismo producto tengan pesos distintos.
                $table->decimal('peso', 10, 3)->nullable()->after('factor');
            }
        });
    }

    public function down(): void
    {
        Schema::table('productoalmacenunidadderivada', function (Blueprint $table) {
            if (Schema::hasColumn('productoalmacenunidadderivada', 'peso')) {
                $table->dropColumn('peso');
            }
        });
    }
};
