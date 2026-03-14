<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entregaproducto', function (Blueprint $table) {
            $table->enum('tipo_pedido', ['interno', 'externo'])->default('interno')->after('user_id');
            $table->string('cargo_destino', 100)->nullable()->after('tipo_pedido');
            $table->dateTime('aceptado_at', 3)->nullable()->after('cargo_destino');
        });
    }

    public function down(): void
    {
        Schema::table('entregaproducto', function (Blueprint $table) {
            $table->dropColumn(['tipo_pedido', 'cargo_destino', 'aceptado_at']);
        });
    }
};
