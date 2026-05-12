<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Agrega campos para rastrear dos costos simultáneamente (anterior y actual)
     * con sus respectivos stocks para implementar PEPS
     */
    public function up(): void
    {
        Schema::table('productoalmacen', function (Blueprint $table) {
            if (!Schema::hasColumn('productoalmacen', 'costo_anterior')) {
                $table->decimal('costo_anterior', 16, 4)->nullable()->after('costo');
            }
            if (!Schema::hasColumn('productoalmacen', 'costo_actual')) {
                $table->decimal('costo_actual', 16, 4)->nullable()->after('costo_anterior');
            }
            if (!Schema::hasColumn('productoalmacen', 'stock_costo_anterior')) {
                $table->decimal('stock_costo_anterior', 16, 3)->default(0)->after('costo_actual');
            }
            if (!Schema::hasColumn('productoalmacen', 'stock_costo_actual')) {
                $table->decimal('stock_costo_actual', 16, 3)->default(0)->after('stock_costo_anterior');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('productoalmacen', function (Blueprint $table) {
            $table->dropColumnIfExists('costo_anterior');
            $table->dropColumnIfExists('costo_actual');
            $table->dropColumnIfExists('stock_costo_anterior');
            $table->dropColumnIfExists('stock_costo_actual');
        });
    }
};
