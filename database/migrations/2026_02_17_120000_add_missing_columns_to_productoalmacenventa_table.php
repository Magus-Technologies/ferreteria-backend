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
        Schema::table('productoalmacenventa', function (Blueprint $table) {
            // Verificar si las columnas no existen antes de agregarlas
            if (!Schema::hasColumn('productoalmacenventa', 'cantidad')) {
                $table->decimal('cantidad', 10, 4)->default(0)->after('producto_almacen_id');
            }
            
            if (!Schema::hasColumn('productoalmacenventa', 'precio_unitario')) {
                $table->decimal('precio_unitario', 10, 4)->default(0)->after('cantidad');
            }
            
            if (!Schema::hasColumn('productoalmacenventa', 'costo')) {
                $table->decimal('costo', 10, 4)->default(0)->after('precio_unitario');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('productoalmacenventa', function (Blueprint $table) {
            $table->dropColumn(['cantidad', 'precio_unitario', 'costo']);
        });
    }
};