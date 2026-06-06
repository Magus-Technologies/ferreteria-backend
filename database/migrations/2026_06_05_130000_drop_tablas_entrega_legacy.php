<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DROP de las tablas legacy de entregas.
 *
 * Para este punto, TODO el código (lecturas, escrituras, eventos, kardex,
 * disponibilidad) opera sobre la tabla nueva `entrega` / `entrega_detalle`.
 * Las tablas `entregaproducto` y `detalleentregaproducto` ya no se consultan
 * en ningún flujo alcanzable, y los eventos fueron re-apuntados a la nueva.
 *
 * Se eliminan las tablas legacy y las columnas puente `entrega_legacy_id` /
 * `detalle_legacy_id` que las referenciaban durante la migración.
 */
return new class extends Migration {
    public function up(): void
    {
        // Quitar columnas puente (no tienen FK en BD, solo referencia lógica).
        Schema::table('entrega', function (Blueprint $table) {
            if (Schema::hasColumn('entrega', 'entrega_legacy_id')) {
                $table->dropColumn('entrega_legacy_id');
            }
        });

        Schema::table('entrega_detalle', function (Blueprint $table) {
            if (Schema::hasColumn('entrega_detalle', 'detalle_legacy_id')) {
                $table->dropColumn('detalle_legacy_id');
            }
        });

        // Orden: primero el detalle (hijo, FK a entregaproducto), luego la madre.
        Schema::dropIfExists('detalleentregaproducto');
        Schema::dropIfExists('entregaproducto');
    }

    public function down(): void
    {
        // Migración de eliminación: no se reconstruyen las tablas legacy.
        // (El esquema vivía en migraciones anteriores; recrearlo sin datos no
        // aporta y rompería la coherencia del sistema ya migrado.)
    }
};
