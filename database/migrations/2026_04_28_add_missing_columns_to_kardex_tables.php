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
        Schema::table('kardex_inventarios', function (Blueprint $table) {
            // Add missing columns if they don't exist
            if (!Schema::hasColumn('kardex_inventarios', 'cantidad_fraccion')) {
                $table->decimal('cantidad_fraccion', 16, 4)->default(0)->after('cantidad');
            }
            if (!Schema::hasColumn('kardex_inventarios', 'costo')) {
                $table->decimal('costo', 16, 4)->nullable()->after('precio');
            }
            if (!Schema::hasColumn('kardex_inventarios', 'entrada')) {
                $table->decimal('entrada', 16, 4)->default(0)->after('costo');
            }
            if (!Schema::hasColumn('kardex_inventarios', 'salida')) {
                $table->decimal('salida', 16, 4)->default(0)->after('entrada');
            }
            if (!Schema::hasColumn('kardex_inventarios', 'referencia_id')) {
                $table->string('referencia_id')->nullable()->after('salida');
            }
            if (!Schema::hasColumn('kardex_inventarios', 'producto_nombre')) {
                $table->string('producto_nombre')->nullable()->after('producto_id');
            }
            if (!Schema::hasColumn('kardex_inventarios', 'producto_codigo')) {
                $table->string('producto_codigo')->nullable()->after('producto_nombre');
            }
        });

        Schema::table('kardex_facturacions', function (Blueprint $table) {
            // Add missing columns if they don't exist
            if (!Schema::hasColumn('kardex_facturacions', 'cantidad_fraccion')) {
                $table->decimal('cantidad_fraccion', 16, 4)->default(0)->after('cantidad');
            }
            if (!Schema::hasColumn('kardex_facturacions', 'costo')) {
                $table->decimal('costo', 16, 4)->nullable()->after('precio');
            }
            if (!Schema::hasColumn('kardex_facturacions', 'entrada')) {
                $table->decimal('entrada', 16, 4)->default(0)->after('costo');
            }
            if (!Schema::hasColumn('kardex_facturacions', 'salida')) {
                $table->decimal('salida', 16, 4)->default(0)->after('entrada');
            }
            if (!Schema::hasColumn('kardex_facturacions', 'referencia_id')) {
                $table->string('referencia_id')->nullable()->after('salida');
            }
            if (!Schema::hasColumn('kardex_facturacions', 'producto_nombre')) {
                $table->string('producto_nombre')->nullable()->after('producto_id');
            }
            if (!Schema::hasColumn('kardex_facturacions', 'producto_codigo')) {
                $table->string('producto_codigo')->nullable()->after('producto_nombre');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('kardex_inventarios', function (Blueprint $table) {
            $table->dropColumnIfExists('cantidad_fraccion');
            $table->dropColumnIfExists('costo');
            $table->dropColumnIfExists('entrada');
            $table->dropColumnIfExists('salida');
            $table->dropColumnIfExists('referencia_id');
            $table->dropColumnIfExists('producto_nombre');
            $table->dropColumnIfExists('producto_codigo');
        });

        Schema::table('kardex_facturacions', function (Blueprint $table) {
            $table->dropColumnIfExists('cantidad_fraccion');
            $table->dropColumnIfExists('costo');
            $table->dropColumnIfExists('entrada');
            $table->dropColumnIfExists('salida');
            $table->dropColumnIfExists('referencia_id');
            $table->dropColumnIfExists('producto_nombre');
            $table->dropColumnIfExists('producto_codigo');
        });
    }
};
