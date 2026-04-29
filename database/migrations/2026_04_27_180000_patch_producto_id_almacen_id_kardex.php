<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kardex_inventarios', function (Blueprint $table) {
            if (!Schema::hasColumn('kardex_inventarios', 'almacen_id')) {
                $table->bigInteger('almacen_id')->nullable();
            }
            if (!Schema::hasColumn('kardex_inventarios', 'producto_id')) {
                $table->bigInteger('producto_id')->nullable();
            }
        });

        Schema::table('kardex_facturacions', function (Blueprint $table) {
            if (!Schema::hasColumn('kardex_facturacions', 'almacen_id')) {
                $table->bigInteger('almacen_id')->nullable();
            }
            if (!Schema::hasColumn('kardex_facturacions', 'producto_id')) {
                $table->bigInteger('producto_id')->nullable();
            }
        });
    }

    public function down(): void
    {
        // No-op: estos campos vienen del create migration original;
        // este patch solo los añade si tu DB local quedó sin ellos por
        // haber ejecutado el create antes de que se le agregaran.
    }
};
