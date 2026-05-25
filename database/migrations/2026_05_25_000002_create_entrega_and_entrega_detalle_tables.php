<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ─────────────────────────────────────────────────────────
        // 1. Tabla principal de entregas (reemplaza entregaproducto)
        // ─────────────────────────────────────────────────────────
        Schema::create('entrega', function (Blueprint $table) {
            $table->id();

            // Relación con venta
            $table->string('venta_id', 191);
            $table->integer('venta_entrega_secuencia')->unsigned()->default(1)
                  ->comment('Número secuencial de despacho por venta (1, 2, 3...)');

            // Catálogos (FK a tablas de catálogo)
            $table->foreignId('tipo_entrega_id')->constrained('tipo_entrega');
            $table->foreignId('tipo_despacho_id')->nullable()->constrained('tipo_despacho');
            $table->foreignId('estado_entrega_id')->constrained('estado_entrega');
            $table->foreignId('quien_entrega_id')->nullable()->constrained('quien_entrega');

            // Logística
            $table->integer('almacen_salida_id');
            $table->string('chofer_id', 191)->nullable();
            $table->unsignedBigInteger('vehiculo_id')->nullable();

            // Tipo de pedido
            $table->enum('tipo_pedido', ['interno', 'externo'])->default('interno');
            $table->string('cargo_destino', 100)->nullable();

            // Usuarios
            $table->string('user_creador_id', 191)
                  ->comment('Usuario que creó la entrega (era user_id en entregaproducto)');
            $table->string('user_entregado_id', 191)->nullable();
            $table->string('user_anulacion_id', 191)->nullable();

            // Fechas
            $table->date('fecha_creacion');
            $table->date('fecha_programada')->nullable();
            $table->timestamp('fecha_ejecutada')->nullable();
            $table->timestamp('fecha_anulacion')->nullable();
            $table->time('hora_inicio')->nullable();
            $table->time('hora_fin')->nullable();

            // Ubicación
            $table->text('direccion_entrega')->nullable();
            $table->text('referencia_entrega')->nullable();
            $table->decimal('latitud', 10, 7)->nullable();
            $table->decimal('longitud', 10, 7)->nullable();

            // Notas
            $table->text('observaciones')->nullable();
            $table->text('motivo_anulacion')->nullable();

            // Idempotencia de stock
            $table->boolean('stock_aplicado')->default(false)
                  ->comment('Flag para evitar doble decremento de stock');

            // Cross-reference a tabla legacy (temporal, se elimina al completar migración)
            $table->integer('entrega_legacy_id')->nullable()
                  ->comment('ID en entregaproducto — referencia temporal durante migración');

            $table->timestamps();

            // Índices
            $table->index(['venta_id', 'venta_entrega_secuencia']);
            $table->index(['chofer_id', 'estado_entrega_id']);
            $table->index(['estado_entrega_id', 'fecha_programada']);

            // Foreign keys
            $table->foreign('venta_id')->references('id')->on('venta');
            $table->foreign('almacen_salida_id')->references('id')->on('almacen');
            $table->foreign('vehiculo_id')->references('id')->on('vehiculo');
            $table->foreign('user_creador_id')->references('id')->on('user');
            $table->foreign('user_entregado_id')->references('id')->on('user');
            $table->foreign('user_anulacion_id')->references('id')->on('user');
        });

        // ─────────────────────────────────────────────────────────
        // 2. Tabla de detalles (reemplaza detalleentregaproducto)
        // ─────────────────────────────────────────────────────────
        Schema::create('entrega_detalle', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entrega_id')->constrained('entrega')->cascadeOnDelete();
            $table->integer('unidad_derivada_venta_id');
            $table->decimal('cantidad', 12, 3);
            $table->string('ubicacion', 191)->nullable();

            // Cross-reference a tabla legacy
            $table->integer('detalle_legacy_id')->nullable()
                  ->comment('ID en detalleentregaproducto — referencia temporal');

            $table->timestamps();

            $table->index('entrega_id');
            $table->index('unidad_derivada_venta_id');
            $table->foreign('unidad_derivada_venta_id')
                  ->references('id')
                  ->on('unidadderivadainmutableventa');
        });

        // ─────────────────────────────────────────────────────────
        // 3. Backfill desde tablas legacy
        // ─────────────────────────────────────────────────────────
        if (DB::table('entregaproducto')->exists()) {
            DB::statement("
                INSERT INTO entrega (
                    venta_id,
                    venta_entrega_secuencia,
                    tipo_entrega_id,
                    tipo_despacho_id,
                    estado_entrega_id,
                    quien_entrega_id,
                    almacen_salida_id,
                    chofer_id,
                    vehiculo_id,
                    tipo_pedido,
                    cargo_destino,
                    user_creador_id,
                    user_entregado_id,
                    user_anulacion_id,
                    fecha_creacion,
                    fecha_programada,
                    fecha_ejecutada,
                    fecha_anulacion,
                    hora_inicio,
                    hora_fin,
                    direccion_entrega,
                    referencia_entrega,
                    latitud,
                    longitud,
                    observaciones,
                    motivo_anulacion,
                    stock_aplicado,
                    entrega_legacy_id,
                    created_at,
                    updated_at
                )
                SELECT
                    ep.venta_id,
                    ROW_NUMBER() OVER (PARTITION BY ep.venta_id ORDER BY ep.id) AS venta_entrega_secuencia,
                    te.id  AS tipo_entrega_id,
                    td.id  AS tipo_despacho_id,
                    ee.id  AS estado_entrega_id,
                    qe.id  AS quien_entrega_id,
                    ep.almacen_salida_id,
                    ep.chofer_id,
                    ep.vehiculo_id,
                    ep.tipo_pedido,
                    ep.cargo_destino,
                    ep.user_id AS user_creador_id,
                    ep.user_entregado_id,
                    ep.user_anulacion_id,
                    DATE(ep.fecha_entrega) AS fecha_creacion,
                    IF(ep.fecha_programada IS NOT NULL, DATE(ep.fecha_programada), NULL) AS fecha_programada,
                    IF(ep.estado_entrega = 'en', ep.updated_at, NULL) AS fecha_ejecutada,
                    IF(ep.fecha_anulacion IS NOT NULL, TIMESTAMP(ep.fecha_anulacion), NULL) AS fecha_anulacion,
                    IF(ep.hora_inicio IS NOT NULL AND ep.hora_inicio != '', TIME(ep.hora_inicio), NULL) AS hora_inicio,
                    IF(ep.hora_fin IS NOT NULL AND ep.hora_fin != '', TIME(ep.hora_fin), NULL) AS hora_fin,
                    ep.direccion_entrega,
                    ep.referencia_entrega,
                    ep.latitud,
                    ep.longitud,
                    ep.observaciones,
                    ep.motivo_anulacion,
                    IF(ep.estado_entrega = 'en', 1, 0) AS stock_aplicado,
                    ep.id  AS entrega_legacy_id,
                    ep.created_at,
                    ep.updated_at
                FROM entregaproducto ep
                JOIN tipo_entrega te  ON te.codigo  = ep.tipo_entrega
                JOIN tipo_despacho td ON td.codigo  = ep.tipo_despacho
                JOIN estado_entrega ee ON ee.codigo = ep.estado_entrega
                LEFT JOIN quien_entrega qe ON qe.codigo = ep.quien_entrega
                ORDER BY ep.venta_id, ep.id
            ");

            DB::statement("
                INSERT INTO entrega_detalle (
                    entrega_id,
                    unidad_derivada_venta_id,
                    cantidad,
                    ubicacion,
                    detalle_legacy_id,
                    created_at,
                    updated_at
                )
                SELECT
                    e.id AS entrega_id,
                    dep.unidad_derivada_venta_id,
                    dep.cantidad_solicitada AS cantidad,
                    dep.ubicacion,
                    dep.id AS detalle_legacy_id,
                    NOW(),
                    NOW()
                FROM detalleentregaproducto dep
                JOIN entrega e ON e.entrega_legacy_id = dep.entrega_producto_id
            ");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('entrega_detalle');
        Schema::dropIfExists('entrega');
    }
};
