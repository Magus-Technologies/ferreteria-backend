<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reestructura "entregas":
 *
 *   entregaproducto (orden lógica de entrega — 1 fila por destino/pedido)
 *      └─▶ detalleentregaproducto (qué productos+cantidad SOLICITADOS)
 *      └─▶ entregaevento (cada despacho físico parcial — chofer/fecha/vehículo)
 *               └─▶ detalleentregaevento (cuánto de cada detalle salió en ESE evento)
 *
 * Cada `entregaevento` tiene su propio `chofer`, `vehiculo`, `fecha_programada`,
 * `hora_inicio/fin` y `estado` — permite que el mismo pedido se despache en
 * varios viajes con diferentes despachadores y fechas, sin duplicar filas
 * en `entregaproducto`.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('entregaevento', function (Blueprint $table) {
            $table->id();
            // entregaproducto.id es int SIGNED — debe coincidir exactamente para la FK
            $table->integer('entrega_producto_id');
            // Estado del despacho: pr (programado), ec (en camino), en (entregado), an (anulado)
            $table->enum('estado', ['pr', 'ec', 'en', 'an'])->default('pr')->index();
            // Cuando se planificó vs cuando se ejecutó
            $table->dateTime('fecha_programada', 3)->nullable()->index();
            $table->dateTime('fecha_ejecutada', 3)->nullable();
            $table->string('hora_inicio')->nullable();
            $table->string('hora_fin')->nullable();
            // Logística — pueden variar por evento (chofer A el lunes, chofer B el miércoles)
            $table->string('chofer_id')->nullable()->index();
            $table->unsignedBigInteger('vehiculo_id')->nullable()->index();
            $table->enum('quien_entrega', ['vendedor', 'almacen', 'chofer'])->nullable();
            $table->enum('tipo_pedido', ['interno', 'externo'])->default('interno');
            $table->string('cargo_destino', 100)->nullable();
            // Dirección (opcional — si está null se hereda de entregaproducto)
            $table->string('direccion_entrega')->nullable();
            $table->string('referencia_entrega', 255)->nullable();
            $table->decimal('latitud', 10, 7)->nullable();
            $table->decimal('longitud', 10, 7)->nullable();
            $table->text('observaciones')->nullable();
            // Auditoría
            $table->string('user_id');                // creador del evento
            $table->string('user_entregado_id')->nullable(); // quien marcó como entregado
            $table->dateTime('aceptado_at', 3)->nullable();
            $table->dateTime('fecha_anulacion')->nullable();
            $table->text('motivo_anulacion')->nullable();
            $table->string('user_anulacion_id')->nullable();
            $table->timestamps(3);

            $table->foreign('entrega_producto_id')
                ->references('id')->on('entregaproducto')
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
            // entregaevento.id es bigint unsigned (generado con $table->id())
            $table->unsignedBigInteger('entrega_evento_id');
            // detalleentregaproducto.id es int SIGNED — debe coincidir exactamente para la FK
            $table->integer('detalle_entrega_producto_id');
            // Cantidad físicamente despachada en ESTE evento
            $table->decimal('cantidad', 9, 3);
            $table->string('ubicacion')->nullable();

            $table->foreign('entrega_evento_id')
                ->references('id')->on('entregaevento')
                ->onDelete('cascade');
            $table->foreign('detalle_entrega_producto_id', 'fk_deve_detalle_entrega')
                ->references('id')->on('detalleentregaproducto')
                ->onDelete('cascade');

            $table->index(['entrega_evento_id', 'detalle_entrega_producto_id'], 'idx_deve_evento_detalle');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detalleentregaevento');
        Schema::dropIfExists('entregaevento');
    }
};
