<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entregaproducto', function (Blueprint $table) {
            $table->unsignedBigInteger('grupo_entrega_id')
                ->nullable()
                ->after('venta_id');
            $table->index('grupo_entrega_id', 'entregaproducto_grupo_entrega_id_idx');
        });
    }

    public function down(): void
    {
        Schema::table('entregaproducto', function (Blueprint $table) {
            $table->dropIndex('entregaproducto_grupo_entrega_id_idx');
            $table->dropColumn('grupo_entrega_id');
        });
    }
};
