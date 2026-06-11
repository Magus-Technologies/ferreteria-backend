<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * origen_id debe ser STRING: el origen puede ser una venta (id ULID, ej.
     * "01KTTD0J8JT9J1G94KW3GVGJ5Z") o una salida (id entero). Un ULID no cabe en
     * unsignedBigInteger → "Data truncated for column 'origen_id'".
     */
    public function up(): void
    {
        if (! Schema::hasTable('productoalmacen_lote_consumo')) {
            return;
        }

        Schema::table('productoalmacen_lote_consumo', function (Blueprint $table) {
            $table->string('origen_id', 40)->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('productoalmacen_lote_consumo')) {
            return;
        }

        Schema::table('productoalmacen_lote_consumo', function (Blueprint $table) {
            $table->unsignedBigInteger('origen_id')->change();
        });
    }
};
