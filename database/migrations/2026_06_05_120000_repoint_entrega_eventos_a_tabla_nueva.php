<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Re-apunta el sistema de eventos de entrega de la tabla LEGACY a la NUEVA.
 *
 * Antes:
 *   entregaevento.entrega_producto_id        -> entregaproducto (legacy)
 *   detalleentregaevento.detalle_entrega_producto_id -> detalleentregaproducto (legacy)
 *
 * Ahora:
 *   entregaevento.entrega_id          -> entrega (nueva)
 *   detalleentregaevento.entrega_detalle_id -> entrega_detalle (nueva)
 *
 * Las tablas de eventos están VACÍAS, así que se recrean (sin migrar datos).
 * Esto desacopla los eventos de la legacy para poder dropearla.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::dropIfExists('detalleentregaevento');
        Schema::dropIfExists('entregaevento');

        Schema::create('entregaevento', function (Blueprint $table) {
            $table->id();
            // FK a la tabla NUEVA (entrega.id es bigint unsigned)
            $table->unsignedBigInteger('entrega_id');
            $table->enum('estado', ['pr', 'ec', 'en', 'an'])->default('pr')->index();
            $table->dateTime('fecha_programada', 3)->nullable()->index();
            $table->dateTime('fecha_ejecutada', 3)->nullable();
            $table->string('hora_inicio')->nullable();
            $table->string('hora_fin')->nullable();
            $table->string('chofer_id')->nullable()->index();
            $table->unsignedBigInteger('vehiculo_id')->nullable()->index();
            $table->enum('quien_entrega', ['vendedor', 'almacen', 'chofer'])->nullable();
            $table->enum('tipo_pedido', ['interno', 'externo'])->default('interno');
            $table->string('cargo_destino', 100)->nullable();
            $table->string('direccion_entrega')->nullable();
            $table->string('referencia_entrega', 255)->nullable();
            $table->decimal('latitud', 10, 7)->nullable();
            $table->decimal('longitud', 10, 7)->nullable();
            $table->text('observaciones')->nullable();
            $table->string('user_id');
            $table->string('user_entregado_id')->nullable();
            $table->dateTime('aceptado_at', 3)->nullable();
            $table->dateTime('fecha_anulacion')->nullable();
            $table->text('motivo_anulacion')->nullable();
            $table->string('user_anulacion_id')->nullable();
            $table->timestamps(3);

            $table->foreign('entrega_id')
                ->references('id')->on('entrega')
                ->onDelete('cascade');
            $table->foreign('vehiculo_id')
                ->references('id')->on('vehiculo')
                ->onDelete('set null');
            $table->foreign('chofer_id')
                ->references('id')->on('user')
                ->onDelete('set null');
            $table->foreign('user_id')
                ->references('id')->on('user');
            $table->foreign('user_entregado_id')
                ->references('id')->on('user')
                ->onDelete('set null');
            $table->foreign('user_anulacion_id')
                ->references('id')->on('user')
                ->onDelete('set null');
        });

        Schema::create('detalleentregaevento', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entrega_evento_id');
            // FK a la tabla NUEVA (entrega_detalle.id es bigint unsigned)
            $table->unsignedBigInteger('entrega_detalle_id');
            $table->decimal('cantidad', 9, 3);
            $table->string('ubicacion')->nullable();

            $table->foreign('entrega_evento_id')
                ->references('id')->on('entregaevento')
                ->onDelete('cascade');
            $table->foreign('entrega_detalle_id', 'fk_deve_entrega_detalle')
                ->references('id')->on('entrega_detalle')
                ->onDelete('cascade');

            $table->index(['entrega_evento_id', 'entrega_detalle_id'], 'idx_deve_evento_detalle');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detalleentregaevento');
        Schema::dropIfExists('entregaevento');
    }
};
