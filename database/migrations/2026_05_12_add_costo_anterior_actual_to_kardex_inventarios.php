<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Agrega los campos costo_anterior y costo_actual para rastrear cambios de precio
     */
    public function up(): void
    {
        Schema::table('kardex_inventarios', function (Blueprint $table) {
            if (!Schema::hasColumn('kardex_inventarios', 'costo_anterior')) {
                $table->decimal('costo_anterior', 16, 4)->nullable()->after('costo');
            }
            if (!Schema::hasColumn('kardex_inventarios', 'costo_actual')) {
                $table->decimal('costo_actual', 16, 4)->nullable()->after('costo_anterior');
            }
            if (!Schema::hasColumn('kardex_inventarios', 'factor')) {
                $table->decimal('factor', 16, 4)->default(1)->after('costo_actual');
            }
            if (!Schema::hasColumn('kardex_inventarios', 'proveedor_nombre')) {
                $table->string('proveedor_nombre')->nullable()->after('factor');
            }
        });

        Schema::table('kardex_facturacions', function (Blueprint $table) {
            if (!Schema::hasColumn('kardex_facturacions', 'costo_anterior')) {
                $table->decimal('costo_anterior', 16, 4)->nullable()->after('costo');
            }
            if (!Schema::hasColumn('kardex_facturacions', 'costo_actual')) {
                $table->decimal('costo_actual', 16, 4)->nullable()->after('costo_anterior');
            }
            if (!Schema::hasColumn('kardex_facturacions', 'factor')) {
                $table->decimal('factor', 16, 4)->default(1)->after('costo_actual');
            }
            if (!Schema::hasColumn('kardex_facturacions', 'proveedor_nombre')) {
                $table->string('proveedor_nombre')->nullable()->after('factor');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('kardex_inventarios', function (Blueprint $table) {
            $table->dropColumnIfExists('costo_anterior');
            $table->dropColumnIfExists('costo_actual');
            $table->dropColumnIfExists('factor');
            $table->dropColumnIfExists('proveedor_nombre');
        });

        Schema::table('kardex_facturacions', function (Blueprint $table) {
            $table->dropColumnIfExists('costo_anterior');
            $table->dropColumnIfExists('costo_actual');
            $table->dropColumnIfExists('factor');
            $table->dropColumnIfExists('proveedor_nombre');
        });
    }
};
