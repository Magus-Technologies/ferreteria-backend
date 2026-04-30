<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega `user_entregado_id` a `entregaproducto` para registrar quién marcó
 * la entrega como ENTREGADO. Es distinto de `user_id` (quien CREÓ la entrega).
 *
 * Caso de uso: una venta en EnTienda con quien_entrega=almacen sale PENDIENTE.
 * Cuando alguien del almacén la marca como entregada desde Mis Entregas, se
 * guarda su user_id en `user_entregado_id` para auditoría.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entregaproducto', function (Blueprint $table) {
            $table->string('user_entregado_id', 191)->nullable()->after('user_id');
            $table->index('user_entregado_id');
        });
    }

    public function down(): void
    {
        Schema::table('entregaproducto', function (Blueprint $table) {
            $table->dropIndex(['user_entregado_id']);
            $table->dropColumn('user_entregado_id');
        });
    }
};
