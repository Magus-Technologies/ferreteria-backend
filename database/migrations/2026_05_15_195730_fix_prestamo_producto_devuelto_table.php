<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Safe migration: only creates table if it doesn't exist
     */
    public function up(): void
    {
        // Check if table already exists
        $tables = DB::select("SHOW TABLES LIKE 'prestamo_producto_devuelto'");
        if (count($tables) === 0) {
            Schema::create('prestamo_producto_devuelto', function (Blueprint $table) {
                $table->id();
                $table->integer('prestamo_devolucion_id');
                $table->integer('producto_almacen_prestamo_id');
                $table->string('unidad_derivada_inmutable_prestamo_id', 191)->nullable();
                $table->decimal('cantidad', 12, 4);
                $table->decimal('factor', 9, 4);
                $table->decimal('cantidad_fraccion', 12, 4);
                $table->timestamps();

                $table->foreign('prestamo_devolucion_id')->references('id')->on('prestamo_devolucion')->onDelete('cascade');
                $table->foreign('producto_almacen_prestamo_id')->references('id')->on('productoalmacenprestamo')->onDelete('cascade');
                $table->index('prestamo_devolucion_id');
                $table->index('producto_almacen_prestamo_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prestamo_producto_devuelto');
    }
};