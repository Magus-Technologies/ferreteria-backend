<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Desglose PEPS del costo al momento de la venta: cuántas unidades (en fracción)
     * salieron del lote ANTERIOR y del ACTUAL, con su costo respectivo. Permite que el
     * "Análisis de Pérdidas en Ventas" muestre dos filas (una por costo) cuando la venta
     * consumió de dos lotes. Solo informativo/reporte: NO afecta stock ni el costo guardado.
     */
    public function up(): void
    {
        Schema::table('productoalmacenventa', function (Blueprint $table) {
            if (!Schema::hasColumn('productoalmacenventa', 'cant_costo_anterior')) {
                $table->decimal('cant_costo_anterior', 16, 4)->default(0)->after('costo');
            }
            if (!Schema::hasColumn('productoalmacenventa', 'costo_anterior')) {
                $table->decimal('costo_anterior', 16, 4)->nullable()->after('cant_costo_anterior');
            }
            if (!Schema::hasColumn('productoalmacenventa', 'cant_costo_actual')) {
                $table->decimal('cant_costo_actual', 16, 4)->default(0)->after('costo_anterior');
            }
            if (!Schema::hasColumn('productoalmacenventa', 'costo_actual')) {
                $table->decimal('costo_actual', 16, 4)->nullable()->after('cant_costo_actual');
            }
        });
    }

    public function down(): void
    {
        Schema::table('productoalmacenventa', function (Blueprint $table) {
            $table->dropColumn(['cant_costo_anterior', 'costo_anterior', 'cant_costo_actual', 'costo_actual']);
        });
    }
};
